<?php

declare(strict_types=1);

/**
 * Bukti kontrak finite-state database TASK-006 Slice A pada MySQL terisolasi.
 *
 * Script ini HANYA berjalan pada db_preventive_maintenance_test dan menolak
 * berjalan bila identitas database, engine, atau sql_mode tidak sesuai.
 * Database utama db_preventive_maintenance tidak pernah disentuh.
 *
 * Mode:
 *   inspect  -> laporan read-only: identitas, sql_mode, state migrasi, kolom.
 *   red      -> isolated pre-correction VARCHAR controls accept 'arbitrary'.
 *   prove    -> bukti lengkap apply/rollback/reapply + rejection + cleanup.
 *   fail-inject -> kontrol kegagalan fixture: bukti cleanup failure-atomic (transaksi rollback).
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const SLICE_A_DATABASE = 'db_preventive_maintenance_test';
const SLICE_A_MIGRATION = '2026_09_11_090000_add_lifecycle_fields_to_machines_and_pm_schedules';
const SLICE_A_MACHINE_STATES = ['active', 'inactive', 'retired'];
const SLICE_A_SCHEDULE_STATES = ['active', 'paused', 'ended'];

/**
 * Eksepsi kontrol khusus harness (mode fail-inject) untuk membuktikan cleanup
 * fixture failure-atomic. Bukan bagian dari kode produksi.
 */
final class FixtureSeedingIntentionalFailure extends RuntimeException {}

$mode = $argv[1] ?? 'inspect';

try {
    bootstrapApplication();
    $identity = assertSafeMySqlConnection();
    echo 'database_identity='.$identity['database'].' engines='.implode(',', $identity['engines']).PHP_EOL;
    echo 'session_sql_mode='.$identity['sql_mode'].PHP_EOL;

    match ($mode) {
        'inspect' => runInspect(),
        'reset' => runReset(),
        'red' => runRed(),
        'red-partial-setup-failure' => runRedPartialSetupFailure(),
        'prove' => runProve(),
        'fail-inject' => runFailInject(),
        default => throw new InvalidArgumentException('Mode tidak dikenal: '.$mode),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    echo 'STOP — SLICE A FINITE-STATE PROOF INCONCLUSIVE'.PHP_EOL;
    exit(1);
}

/**
 * Memaksa bootstrap ke database test terisolasi sebelum aplikasi dibuat.
 */
function bootstrapApplication(): void
{
    foreach ([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => SLICE_A_DATABASE,
        'DB_URL' => '',
    ] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql']);
    config(['database.connections.mysql.database' => SLICE_A_DATABASE]);
    DB::purge('mysql');
    DB::reconnect('mysql');
}

/**
 * Guard keselamatan: identitas database, engine InnoDB, dan strict sql_mode.
 *
 * @return array{database: string, engines: array<int, string>, sql_mode: string}
 */
function assertSafeMySqlConnection(): array
{
    $connection = DB::connection('mysql');
    $database = (string) $connection->selectOne('SELECT DATABASE() AS database_name')->database_name;
    $engines = array_values(array_unique(array_map(
        static fn (object $row): string => (string) $row->engine_name,
        $connection->select(
            'SELECT ENGINE AS engine_name FROM information_schema.TABLES '
           .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?) GROUP BY ENGINE ORDER BY ENGINE',
            ['machines', 'pm_schedules'],
        ),
    )));
    $sqlMode = (string) $connection->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;

    if ($database !== SLICE_A_DATABASE) {
        throw new RuntimeException('Identitas database tidak aman: '.$database);
    }

    if ($engines !== ['InnoDB']) {
        throw new RuntimeException('Engine tidak sesuai: '.implode(',', $engines));
    }

    // Tanpa strict mode MySQL menerima enum invalid sebagai string kosong,
    // sehingga bukti rejection tidak bermakna.
    if (! str_contains($sqlMode, 'STRICT_TRANS_TABLES')) {
        throw new RuntimeException('sql_mode tidak strict: '.$sqlMode);
    }

    return ['database' => $database, 'engines' => $engines, 'sql_mode' => $sqlMode];
}

function lifecycleMigrationApplied(): bool
{
    return DB::table('migrations')->where('migration', SLICE_A_MIGRATION)->exists();
}

function lifecycleColumnExists(string $table): bool
{
    return DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', SLICE_A_DATABASE)
        ->where('TABLE_NAME', $table)
        ->where('COLUMN_NAME', 'lifecycle_status')
        ->exists();
}

/**
 * Path absolut file migrasi Slice A — target migrasi eksplisit tunggal.
 */
function sliceAMigrationFile(): string
{
    return base_path('database/migrations/'.SLICE_A_MIGRATION.'.php');
}

/**
 * Snapshot tabel migrations (nama migrasi => batch) untuk invarian isolasi.
 *
 * @return array<string, int>
 */
function migrationTableSnapshot(): array
{
    return DB::table('migrations')->orderBy('batch')->orderBy('id')->pluck('batch', 'migration')->all();
}

/**
 * Nama migrasi pending: file di database/migrations yang belum tercatat di
 * tabel migrations. Menjadi kontrol "unrelated pending migrations".
 *
 * @return list<string>
 */
function pendingMigrationNames(): array
{
    $migrator = app('migrator');
    $files = $migrator->getMigrationFiles([database_path('migrations')]);
    $applied = $migrator->getRepository()->getRan();

    return array_values(array_diff(array_keys($files), $applied));
}

/**
 * Baseline state pre-proof: baris applied TANPA Slice A dan set pending yang
 * selalu memuat Slice A, apa pun state migrasi saat ini.
 *
 * @return array{0: array<string, int>, 1: list<string>}
 */
function preProofMigrationBaseline(): array
{
    $applied = migrationTableSnapshot();
    unset($applied[SLICE_A_MIGRATION]);

    $pending = pendingMigrationNames();

    if (! in_array(SLICE_A_MIGRATION, $pending, true)) {
        $pending[] = SLICE_A_MIGRATION;
        sort($pending);
    }

    return [$applied, array_values($pending)];
}

/**
 * Prasyarat apply tertarget: file migrasi Slice A ada dan masih pending.
 * Harness fail closed bila prasyarat tidak terbukti.
 */
function assertSliceAMigrationPending(): void
{
    if (! is_file(sliceAMigrationFile())) {
        throw new RuntimeException('File migrasi Slice A tidak ditemukan: '.sliceAMigrationFile());
    }

    if (! in_array(SLICE_A_MIGRATION, pendingMigrationNames(), true)) {
        throw new RuntimeException('Migrasi Slice A tidak berada di set pending; apply tertarget dibatalkan.');
    }
}

/**
 * Menerapkan HANYA migrasi Slice A melalui --path file tunggal.
 *
 * Laravel hanya menjalankan file yang diberikan pada --path, sehingga migrasi
 * pending lain tidak ikut ter-apply; isolasi dibuktikan behavioral lewat
 * assertMigrationIsolation().
 *
 * @param  array<string, int>  $baselineApplied
 * @param  list<string>  $baselinePending
 * @return int batch migrasi Slice A hasil apply
 */
function applySliceAMigrationOnly(array $baselineApplied, array $baselinePending): int
{
    assertSliceAMigrationPending();

    Artisan::call('migrate', [
        '--path' => sliceAMigrationFile(),
        '--realpath' => true,
        '--force' => true,
    ]);
    echo trim(Artisan::output()).PHP_EOL;

    if (! lifecycleMigrationApplied()) {
        throw new RuntimeException('Migrasi Slice A tidak tercatat di tabel migrations setelah apply.');
    }

    assertMigrationIsolation($baselineApplied, $baselinePending, 'apply', true);

    $batch = (int) DB::table('migrations')->where('migration', SLICE_A_MIGRATION)->value('batch');

    // Invarian kunci rollback per-batch: batch hasil apply harus berisi tepat satu migrasi.
    assertSame(1, DB::table('migrations')->where('batch', $batch)->count(), 'Batch apply Slice A harus berisi tepat satu migrasi.');

    return $batch;
}

/**
 * Me-rollback HANYA migrasi Slice A lewat --batch (batch yang dicatat saat
 * apply) + --path file tunggal. Tidak bergantung pada batch terakhir, urutan
 * migrasi, atau semantik --step.
 *
 * @param  array<string, int>  $baselineApplied
 * @param  list<string>  $baselinePending
 */
function rollbackSliceAMigrationOnly(array $baselineApplied, array $baselinePending): void
{
    if (! lifecycleMigrationApplied()) {
        return; // Slice A tidak sedang applied; tidak ada yang perlu di-rollback.
    }

    $batch = (int) DB::table('migrations')->where('migration', SLICE_A_MIGRATION)->value('batch');
    $batchRows = array_values(array_map(
        static fn (object $row): string => (string) $row->migration,
        DB::table('migrations')->where('batch', $batch)->orderBy('migration')->get(['migration'])->all(),
    ));

    // Fail closed: rollback per-batch hanya aman bila batch tersebut tunggal.
    assertSame([SLICE_A_MIGRATION], $batchRows, 'Batch migrasi tidak tunggal; rollback per-batch dibatalkan.');

    Artisan::call('migrate:rollback', [
        '--batch' => (string) $batch,
        '--path' => sliceAMigrationFile(),
        '--realpath' => true,
        '--force' => true,
    ]);
    echo trim(Artisan::output()).PHP_EOL;

    if (lifecycleMigrationApplied()) {
        throw new RuntimeException('Migrasi Slice A masih tercatat di tabel migrations setelah rollback.');
    }

    if (lifecycleColumnExists('machines') || lifecycleColumnExists('pm_schedules')) {
        throw new RuntimeException('Kolom lifecycle masih ada setelah rollback.');
    }

    assertMigrationIsolation($baselineApplied, $baselinePending, 'rollback', false);
}

/**
 * Invarian isolasi migrasi: selain Slice A tidak ada baris migrations yang
 * berubah (nama/batch), tidak ada baris hilang, dan set pending unrelated tetap.
 *
 * @param  array<string, int>  $baselineApplied
 * @param  list<string>  $baselinePending
 */
function assertMigrationIsolation(array $baselineApplied, array $baselinePending, string $stage, bool $sliceApplied): void
{
    $applied = migrationTableSnapshot();

    // Baris unrelated harus identik dengan baseline.
    foreach ($baselineApplied as $name => $batch) {
        assertSame($batch, $applied[$name] ?? null, "Baris migrasi unrelated berubah pada {$stage}: {$name}");
    }

    // Tidak boleh ada baris baru selain Slice A, dan tidak boleh ada baris hilang.
    $newRows = array_values(array_diff(array_keys($applied), array_keys($baselineApplied)));
    assertSame($sliceApplied ? [SLICE_A_MIGRATION] : [], $newRows, "Migrasi selain Slice A ter-apply pada {$stage}.");

    $removedRows = array_values(array_diff(array_keys($baselineApplied), array_keys($applied)));
    assertSame([], $removedRows, "Baris migrasi hilang pada {$stage}.");

    // Status applied Slice A harus mengikuti fase proof.
    assertSame($sliceApplied, isset($applied[SLICE_A_MIGRATION]), "Status applied Slice A tidak sesuai pada {$stage}.");

    // Set pending: hanya Slice A yang boleh berpindah antara pending dan applied.
    $expectedPending = $sliceApplied
        ? array_values(array_diff($baselinePending, [SLICE_A_MIGRATION]))
        : $baselinePending;
    assertSame($expectedPending, pendingMigrationNames(), "Set pending migrasi unrelated berubah pada {$stage}.");
}

/**
 * Membaca definisi kolom langsung dari information_schema.
 *
 * @return array<string, string|null>
 */
function columnDefinition(string $table, string $column): array
{
    $row = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', SLICE_A_DATABASE)
        ->where('TABLE_NAME', $table)
        ->where('COLUMN_NAME', $column)
        ->first(['COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT']);

    if ($row === null) {
        throw new RuntimeException('Kolom tidak ditemukan: '.$table.'.'.$column);
    }

    return [
        'type' => (string) $row->COLUMN_TYPE,
        'nullable' => (string) $row->IS_NULLABLE,
        'default' => $row->COLUMN_DEFAULT === null ? null : (string) $row->COLUMN_DEFAULT,
    ];
}

function runInspect(): void
{
    echo 'migration_applied='.(lifecycleMigrationApplied() ? 'yes' : 'no').PHP_EOL;
    echo 'lifecycle_migration_file='.sliceAMigrationFile().' exists='.(is_file(sliceAMigrationFile()) ? 'yes' : 'no').PHP_EOL;
    echo 'migration_table_rows='.count(migrationTableSnapshot()).PHP_EOL;
    echo 'pending_migrations='.json_encode(pendingMigrationNames(), JSON_THROW_ON_ERROR).PHP_EOL;
    echo 'machine_lifecycle_column='.(lifecycleColumnExists('machines') ? 'present' : 'absent').PHP_EOL;
    echo 'schedule_lifecycle_column='.(lifecycleColumnExists('pm_schedules') ? 'present' : 'absent').PHP_EOL;

    if (lifecycleColumnExists('machines')) {
        echo 'machines.lifecycle_status='.json_encode(columnDefinition('machines', 'lifecycle_status'), JSON_THROW_ON_ERROR).PHP_EOL;
        echo 'pm_schedules.lifecycle_status='.json_encode(columnDefinition('pm_schedules', 'lifecycle_status'), JSON_THROW_ON_ERROR).PHP_EOL;
    }

    echo 'INSPECT — READ ONLY'.PHP_EOL;
}

function runReset(): void
{
    if (lifecycleMigrationApplied()) {
        // Rollback tertarget: batch Slice A + --path file tunggal (bukan batch terakhir).
        [$baselineApplied, $baselinePending] = preProofMigrationBaseline();
        rollbackSliceAMigrationOnly($baselineApplied, $baselinePending);
    }

    assertPreProofState();

    echo 'RESET — PRE-PROOF STATE RESTORED'.PHP_EOL;
}

/**
 * Kontrol RED terisolasi: karakteristik VARCHAR pra-koreksi menerima nilai
 * arbitrary tanpa menerapkan migrasi lifecycle.
 *
 * Non-invasiveness dibuktikan eksplisit: snapshot BEFORE (baris + hash konten
 * lengkap) tabel aplikasi machines dan pm_schedules, plus snapshot tabel
 * migrations dan keberadaan kolom lifecycle, dibandingkan identik AFTER.
 * Snapshot hanya READ-ONLY; kontrol RED tetap hanya menyentuh tabel scratch.
 */
function runRed(): void
{
    $suffix = strtolower(substr(hash('sha256', 'SLICEA_RED_'.date('Ymd_His_u').'_'.getmypid().'_'.random_int(0, PHP_INT_MAX)), 0, 10));
    $tables = [
        'machine' => 'slice_a_red_machine_'.$suffix,
        'schedule' => 'slice_a_red_schedule_'.$suffix,
    ];

    assertScratchTablesAvailable($tables);

    $createdTables = [];
    try {
        // Snapshot non-invasiveness dibaca SETELAH guard pra-kontrol RED.
        $machinesBefore = realTableSnapshot('machines');
        $schedulesBefore = realTableSnapshot('pm_schedules');
        $migrationsBefore = migrationTableSnapshot();
        $lifecycleColumnsBefore = [
            'machines' => lifecycleColumnExists('machines'),
            'pm_schedules' => lifecycleColumnExists('pm_schedules'),
        ];

        // Tabel scratch minimal ini mengisolasi kelemahan VARCHAR pra-koreksi.
        createRedScratchTable($tables['machine']);
        $createdTables[] = $tables['machine'];
        createRedScratchTable($tables['schedule']);
        $createdTables[] = $tables['schedule'];

        DB::table($tables['machine'])->insert(['lifecycle_status' => 'arbitrary']);
        DB::table($tables['schedule'])->insert(['lifecycle_status' => 'arbitrary']);

        $machineAccepted = DB::table($tables['machine'])->where('lifecycle_status', 'arbitrary')->exists();
        $scheduleAccepted = DB::table($tables['schedule'])->where('lifecycle_status', 'arbitrary')->exists();
        echo 'pre_correction_varchar_control_machine_arbitrary_accepted='.($machineAccepted ? 'yes' : 'no').PHP_EOL;
        echo 'pre_correction_varchar_control_schedule_arbitrary_accepted='.($scheduleAccepted ? 'yes' : 'no').PHP_EOL;

        assertSame(true, $machineAccepted, 'Kontrol VARCHAR machine menolak arbitrary.');
        assertSame(true, $scheduleAccepted, 'Kontrol VARCHAR schedule menolak arbitrary.');
        assertSame(false, lifecycleMigrationApplied(), 'Kontrol RED mengubah state migrasi lifecycle.');
        assertSame(false, lifecycleColumnExists('machines'), 'Kontrol RED mengubah tabel machines.');
        assertSame(false, lifecycleColumnExists('pm_schedules'), 'Kontrol RED mengubah tabel pm_schedules.');

        // Bukti non-invasiveness: state tabel aplikasi, migrations, dan kolom
        // lifecycle harus identik sebelum vs sesudah kontrol RED (baca-saja).
        assertRealTablesUnchanged($machinesBefore, realTableSnapshot('machines'), 'machines');
        assertRealTablesUnchanged($schedulesBefore, realTableSnapshot('pm_schedules'), 'pm_schedules');
        assertSame($migrationsBefore, migrationTableSnapshot(), 'Kontrol RED mengubah state tabel migrations.');
        assertSame($lifecycleColumnsBefore, [
            'machines' => lifecycleColumnExists('machines'),
            'pm_schedules' => lifecycleColumnExists('pm_schedules'),
        ], 'Kontrol RED mengubah keberadaan kolom lifecycle.');

        echo 'non_invasive_machines_before=count='.$machinesBefore['count'].' hash='.$machinesBefore['hash'].PHP_EOL;
        echo 'non_invasive_machines_after_identical=yes'.PHP_EOL;
        echo 'non_invasive_pm_schedules_before=count='.$schedulesBefore['count'].' hash='.$schedulesBefore['hash'].PHP_EOL;
        echo 'non_invasive_pm_schedules_after_identical=yes'.PHP_EOL;
        echo 'non_invasive_migrations_state_identical=yes'.PHP_EOL;
        echo 'non_invasive_lifecycle_columns_absent=yes'.PHP_EOL;
        echo 'RED — PRE-CORRECTION VARCHAR CONTROL PASS'.PHP_EOL;
    } finally {
        cleanupOwnedScratchTables($createdTables);
    }

    assertScratchTablesAbsent($tables);
    echo 'pre_correction_varchar_control_cleanup=PASS'.PHP_EOL;
}

/**
 * Snapshot READ-ONLY seluruh baris tabel aplikasi dalam urutan primary key.
 *
 * Mengembalikan jumlah baris plus hash deterministik atas SELURUH kolom
 * (complete-row) setiap baris, sehingga perubahan jumlah maupun perubahan
 * konten in-place dengan jumlah tetap sama-sama terdeteksi.
 *
 * @return array{count: int, hash: string}
 */
function realTableSnapshot(string $table): array
{
    assertSafeMySqlConnection();

    // Baca seluruh baris berurut PK agar hash stabil antar-pemanggilan.
    $rows = DB::table($table)->orderBy('id')->get();

    // Canonicalisasi deterministik: urutan kolom sesuai definisi tabel,
    // setiap baris di-encode JSON dengan flag tetap, lalu digabung per baris.
    $canonical = $rows
        ->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->implode("\n");

    return [
        'count' => $rows->count(),
        'hash' => hash('sha256', $table."\n".$canonical),
    ];
}

/**
 * Memastikan snapshot BEFORE dan AFTER sebuah tabel aplikasi identik.
 *
 * @param  array{count: int, hash: string}  $before
 * @param  array{count: int, hash: string}  $after
 */
function assertRealTablesUnchanged(array $before, array $after, string $table): void
{
    assertSame($before['count'], $after['count'], 'Jumlah baris tabel '.$table.' berubah selama kontrol RED.');
    assertSame($before['hash'], $after['hash'], 'Konten baris tabel '.$table.' berubah selama kontrol RED.');
}

/**
 * Membuktikan cleanup saat setup scratch berhenti setelah tabel pertama dibuat.
 */
function runRedPartialSetupFailure(): void
{
    assertPreProofState();
    $baselineMigrations = migrationTableSnapshot();
    $baselineMachines = realTableSnapshot('machines');
    $baselineSchedules = realTableSnapshot('pm_schedules');
    $baselineLifecycleColumns = [
        'machines' => lifecycleColumnExists('machines'),
        'pm_schedules' => lifecycleColumnExists('pm_schedules'),
    ];
    $baselineTotals = fixtureTableTotals();
    $suffix = strtolower(substr(hash('sha256', 'SLICEA_RED_PARTIAL_'.date('Ymd_His_u').'_'.getmypid().'_'.random_int(0, PHP_INT_MAX)), 0, 10));
    $tables = [
        'machine' => 'slice_a_red_partial_machine_'.$suffix,
        'schedule' => 'slice_a_red_partial_schedule_'.$suffix,
    ];
    assertScratchTablesAvailable($tables);
    $createdTables = [];
    $failureObserved = false;

    try {
        createRedScratchTable($tables['machine']);
        $createdTables[] = $tables['machine'];
        // Kegagalan sengaja terjadi sebelum CREATE kedua; nama tabel kedua tidak dimiliki.
        throw new FixtureSeedingIntentionalFailure('Kegagalan setup scratch kedua yang diharapkan.');
    } catch (FixtureSeedingIntentionalFailure $expected) {
        $failureObserved = true;
        echo 'partial_setup_intentional_failure_observed=yes'.PHP_EOL;
    } finally {
        cleanupOwnedScratchTables($createdTables);
    }

    assertSame(true, $failureObserved, 'Kegagalan setup parsial tidak teramati.');
    assertScratchTablesAbsent($tables);
    assertSame($baselineMigrations, migrationTableSnapshot(), 'State migrations berubah selama setup parsial.');
    assertRealTablesUnchanged($baselineMachines, realTableSnapshot('machines'), 'machines during partial setup');
    assertRealTablesUnchanged($baselineSchedules, realTableSnapshot('pm_schedules'), 'pm_schedules during partial setup');
    assertSame($baselineLifecycleColumns, [
        'machines' => lifecycleColumnExists('machines'),
        'pm_schedules' => lifecycleColumnExists('pm_schedules'),
    ], 'Kolom lifecycle berubah selama setup parsial.');
    assertSame($baselineTotals, fixtureTableTotals(), 'State DB unrelated berubah selama setup parsial.');
    assertPreProofState();
    echo 'partial_setup_real_tables_unchanged=yes'.PHP_EOL;
    echo 'partial_setup_migrations_unchanged=yes'.PHP_EOL;
}

function runProve(): void
{
    assertPreProofState();

    // Baseline migrasi pre-proof direkam sebelum mutasi apa pun (Part C).
    [$baselineApplied, $baselinePending] = preProofMigrationBaseline();
    echo 'unrelated_pending_control='.(count($baselinePending) > 0 ? implode(',', $baselinePending) : 'none (invarian tabel migrations before/after)').PHP_EOL;

    $token = 'SLICEA_'.date('Ymd_His').'_'.getmypid();
    $fixtures = [];

    try {
        // Fixture legacy harus ada SEBELUM apply agar backfill benar-benar terbukti.
        $fixtures = seedFixtures($token);

        $protectedDatesBefore = tableSnapshot('pm_schedule_dates', ['id', 'pm_schedule_id', 'machine_id', 'scheduled_date', 'status', 'deleted_at'], [$fixtures['schedule_date']]);
        $protectedExecutionsBefore = tableSnapshot('pm_executions', ['id', 'pm_schedule_date_id', 'machine_id', 'status', 'deleted_at'], [$fixtures['execution']]);
        $operationalFromBefore = (string) DB::table('pm_schedules')->where('id', $fixtures['schedule_active'])->value('operational_from');

        // Apply tertarget: hanya file migrasi Slice A (--path file tunggal).
        applySliceAMigrationOnly($baselineApplied, $baselinePending);
        echo 'migrate_apply=PASS'.PHP_EOL;

        assertFiniteColumnDefinitions();
        assertLegacyMapping($fixtures, 'apply');
        assertNoTerminalInference($fixtures);
        assertEffectiveLiveFromNull($fixtures);
        assertProtectedHistoryIntact($fixtures, $protectedDatesBefore, $protectedExecutionsBefore, 'apply');
        assertSame($operationalFromBefore, (string) DB::table('pm_schedules')->where('id', $fixtures['schedule_active'])->value('operational_from'), 'operational_from berubah setelah apply.');

        assertInvalidStateRejected($fixtures, 'apply');

        // Rollback tertarget: batch Slice A + --path file tunggal; kolom hilang,
        // data protected tetap identik.
        rollbackSliceAMigrationOnly($baselineApplied, $baselinePending);
        echo 'migrate_rollback=PASS'.PHP_EOL;

        assertProtectedHistoryIntact($fixtures, $protectedDatesBefore, $protectedExecutionsBefore, 'rollback');

        // Reapply tertarget: backfill deterministik kembali, rejection tetap berlaku.
        applySliceAMigrationOnly($baselineApplied, $baselinePending);
        echo 'migrate_reapply=PASS'.PHP_EOL;

        assertFiniteColumnDefinitions();
        assertLegacyMapping($fixtures, 'reapply');
        assertNoTerminalInference($fixtures);
        assertEffectiveLiveFromNull($fixtures);
        assertProtectedHistoryIntact($fixtures, $protectedDatesBefore, $protectedExecutionsBefore, 'reapply');
        assertInvalidStateRejected($fixtures, 'reapply');

        $residual = cleanupFixtures($fixtures);
        $fixtures = [];
        echo 'fixture_residuals='.json_encode($residual, JSON_THROW_ON_ERROR).PHP_EOL;

        if (array_sum($residual) !== 0) {
            throw new RuntimeException('Residual fixture Slice A masih tersisa.');
        }
    } finally {
        rollbackSliceAMigrationOnly($baselineApplied, $baselinePending);
        if ($fixtures !== []) {
            $residual = cleanupFixtures($fixtures);
            echo 'fixture_residuals='.json_encode($residual, JSON_THROW_ON_ERROR).PHP_EOL;
        }
        assertPreProofState();
        assertMigrationIsolation($baselineApplied, $baselinePending, 'final', false);
    }

    echo 'mysql_strict_enum_contract=PASS'.PHP_EOL;
    echo 'protected_history=PASS'.PHP_EOL;
    echo 'migration_isolation=PASS (unrelated migrations unchanged)'.PHP_EOL;
    echo 'final_migration_state=pre-proof (pending)'.PHP_EOL;
    echo 'TASK-006 SLICE A MYSQL FINITE-STATE PROOF — PASS'.PHP_EOL;
    echo 'READY FOR FRESH INDEPENDENT RE-REVIEW'.PHP_EOL;
}

/**
 * State awal wajib: migrasi lifecycle belum ter-apply dan kolom belum ada.
 */
function assertPreProofState(): void
{
    if (lifecycleMigrationApplied()) {
        throw new RuntimeException('Migrasi lifecycle sudah ter-apply; jalankan mode reset lebih dulu.');
    }

    if (lifecycleColumnExists('machines') || lifecycleColumnExists('pm_schedules')) {
        throw new RuntimeException('Kolom lifecycle sudah ada sebelum proof.');
    }
}

/**
 * Membuat fixture legacy milik sesi ini; semua ID dikembalikan eksplisit.
 *
 * Seluruh seeding dibungkus SATU transaksi: bila ada insert yang gagal di
 * tengah jalan (termasuk injeksi kegagalan kontrol), semua baris milik sesi ini
 * otomatis dibatalkan sehingga tidak pernah ada fixture setengah jadi.
 *
 * @param  string  $token  penanda unik sesi proof
 * @param  string|null  $failAfterStep  tahap pemicu injeksi kegagalan (hanya mode fail-inject)
 * @return array<string, int|string>
 */
function seedFixtures(string $token, ?string $failAfterStep = null): array
{
    return DB::transaction(static function () use ($token, $failAfterStep): array {
        $suffix = substr(hash('sha256', $token), 0, 10);
        $now = now()->seconds(0)->toDateTimeString();

        $locationId = DB::table('locations')->insertGetId([
            'location_code' => 'SLICEA-'.strtoupper($suffix),
            'location_name' => 'Slice A Proof Line',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'location');

        $machineActive = DB::table('machines')->insertGetId([
            'location_id' => $locationId,
            'machine_code' => 'SLICEA-ACT-'.strtoupper($suffix),
            'machine_name' => 'Slice A Active Machine',
            'qr_token' => 'slicea-'.$token.'-act',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'machine_active');

        $machineInactive = DB::table('machines')->insertGetId([
            'location_id' => $locationId,
            'machine_code' => 'SLICEA-INA-'.strtoupper($suffix),
            'machine_name' => 'Slice A Inactive Machine',
            'qr_token' => 'slicea-'.$token.'-ina',
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'machine_inactive');

        $checksheetId = DB::table('pm_checksheets')->insertGetId([
            'checksheet_code' => 'SLICEA-'.strtoupper($suffix),
            'checksheet_name' => 'Slice A Proof Checksheet',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'checksheet');

        $assignmentActive = DB::table('pm_checksheet_machines')->insertGetId([
            'pm_checksheet_id' => $checksheetId,
            'machine_id' => $machineActive,
            'assigned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'assignment_active');

        $assignmentInactive = DB::table('pm_checksheet_machines')->insertGetId([
            'pm_checksheet_id' => $checksheetId,
            'machine_id' => $machineInactive,
            'assigned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'assignment_inactive');

        $scheduleActive = DB::table('pm_schedules')->insertGetId([
            'pm_checksheet_machine_id' => $assignmentActive,
            'frequency_type' => 'daily',
            'start_date' => '2026-09-01',
            'generate_until' => '2027-09-01',
            'operational_from' => '2026-08-15',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'schedule_active');

        $scheduleInactive = DB::table('pm_schedules')->insertGetId([
            'pm_checksheet_machine_id' => $assignmentInactive,
            'frequency_type' => 'daily',
            'start_date' => '2026-09-01',
            'generate_until' => '2027-09-01',
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'schedule_inactive');

        $scheduleDate = DB::table('pm_schedule_dates')->insertGetId([
            'pm_schedule_id' => $scheduleActive,
            'machine_id' => $machineActive,
            'scheduled_date' => '2026-09-05',
            'status' => 'in_progress',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        maybeInjectFixtureFailure($failAfterStep, 'schedule_date');

        $execution = DB::table('pm_executions')->insert([
            'pm_schedule_date_id' => $scheduleDate,
            'machine_id' => $machineActive,
            'status' => 'in_progress',
            'created_at' => $now,
            'updated_at' => $now,
        ]) ?: 0;

        $executionId = (int) DB::table('pm_executions')->where('pm_schedule_date_id', $scheduleDate)->value('id');

        if ($executionId === 0) {
            throw new RuntimeException('Gagal membuat fixture execution.');
        }

        return [
            'location' => $locationId,
            'machine_active' => $machineActive,
            'machine_inactive' => $machineInactive,
            'checksheet' => $checksheetId,
            'assignment_active' => $assignmentActive,
            'assignment_inactive' => $assignmentInactive,
            'schedule_active' => $scheduleActive,
            'schedule_inactive' => $scheduleInactive,
            'schedule_date' => $scheduleDate,
            'execution' => $executionId,
        ];
    });
}

/**
 * Injeksi kegagalan khusus harness (hanya terpicu pada mode fail-inject).
 *
 * Sengaja gagal SETELAH minimal satu fixture owned ter-insert dan SEBELUM
 * seeding selesai, agar cleanup failure-atomic dibuktikan secara nyata.
 *
 * @param  string|null  $failAfterStep  tahap pemicu injeksi
 * @param  string  $step  tahap yang baru selesai di-insert
 */
function maybeInjectFixtureFailure(?string $failAfterStep, string $step): void
{
    if ($failAfterStep !== null && $failAfterStep === $step) {
        throw new FixtureSeedingIntentionalFailure('Injeksi kegagalan fixture terpicu setelah tahap: '.$step);
    }
}

/**
 * @param  list<string>  $columns
 * @param  list<int>  $ids
 * @return list<array<string, string|null>>
 */
function tableSnapshot(string $table, array $columns, array $ids): array
{
    return DB::table($table)
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get($columns)
        ->map(static fn (object $row): array => array_map(
            static fn ($value): ?string => $value === null ? null : (string) $value,
            (array) $row,
        ))
        ->all();
}

function assertFiniteColumnDefinitions(): void
{
    $machine = columnDefinition('machines', 'lifecycle_status');
    $schedule = columnDefinition('pm_schedules', 'lifecycle_status');

    assertSame("enum('active','inactive','retired')", $machine['type'], 'Definisi machines.lifecycle_status salah.');
    assertSame("enum('active','paused','ended')", $schedule['type'], 'Definisi pm_schedules.lifecycle_status salah.');
    assertSame('NO', $machine['nullable'], 'machines.lifecycle_status harus NOT NULL.');
    assertSame('NO', $schedule['nullable'], 'pm_schedules.lifecycle_status harus NOT NULL.');
    assertSame('active', $machine['default'], 'Default machines.lifecycle_status salah.');
    assertSame('active', $schedule['default'], 'Default pm_schedules.lifecycle_status salah.');

    $effectiveLiveFrom = columnDefinition('machines', 'effective_live_from');
    assertSame('date', $effectiveLiveFrom['type'], 'effective_live_from harus date.');
    assertSame('YES', $effectiveLiveFrom['nullable'], 'effective_live_from harus nullable.');
    assertSame(null, $effectiveLiveFrom['default'], 'effective_live_from tidak boleh punya default.');

    echo 'column_definitions=PASS machines='.$machine['type'].' pm_schedules='.$schedule['type'].PHP_EOL;
}

/**
 * @param  array<string, int|string>  $fixtures
 */
function assertLegacyMapping(array $fixtures, string $stage): void
{
    assertSame('active', (string) DB::table('machines')->where('id', $fixtures['machine_active'])->value('lifecycle_status'), 'Mapping machine aktif salah ('.$stage.').');
    assertSame('inactive', (string) DB::table('machines')->where('id', $fixtures['machine_inactive'])->value('lifecycle_status'), 'Mapping machine nonaktif salah ('.$stage.').');
    assertSame('active', (string) DB::table('pm_schedules')->where('id', $fixtures['schedule_active'])->value('lifecycle_status'), 'Mapping schedule aktif salah ('.$stage.').');
    assertSame('paused', (string) DB::table('pm_schedules')->where('id', $fixtures['schedule_inactive'])->value('lifecycle_status'), 'Mapping schedule nonaktif salah ('.$stage.').');

    echo 'legacy_mapping='.$stage.' PASS'.PHP_EOL;
}

/**
 * @param  array<string, int|string>  $fixtures
 */
function assertNoTerminalInference(array $fixtures): void
{
    $machineIds = [$fixtures['machine_active'], $fixtures['machine_inactive']];
    $scheduleIds = [$fixtures['schedule_active'], $fixtures['schedule_inactive']];

    assertSame(0, DB::table('machines')->whereIn('id', $machineIds)->whereIn('lifecycle_status', ['retired'])->count(), 'Machine legacy tidak boleh menjadi retired.');
    assertSame(0, DB::table('pm_schedules')->whereIn('id', $scheduleIds)->whereIn('lifecycle_status', ['ended'])->count(), 'Schedule legacy tidak boleh menjadi ended.');

    echo 'terminal_inference=none PASS'.PHP_EOL;
}

/**
 * @param  array<string, int|string>  $fixtures
 */
function assertEffectiveLiveFromNull(array $fixtures): void
{
    assertSame(0, DB::table('machines')->whereIn('id', [$fixtures['machine_active'], $fixtures['machine_inactive']])->whereNotNull('effective_live_from')->count(), 'Machine legacy tidak boleh punya effective_live_from.');
    assertSame(0, DB::table('pm_schedules')->whereIn('id', [$fixtures['schedule_active'], $fixtures['schedule_inactive']])->whereNotNull('effective_live_from')->count(), 'Schedule legacy tidak boleh punya effective_live_from.');

    echo 'effective_live_from_legacy=null PASS'.PHP_EOL;
}

/**
 * @param  array<string, int|string>  $fixtures
 * @param  list<array<string, string|null>>  $datesBefore
 * @param  list<array<string, string|null>>  $executionsBefore
 */
function assertProtectedHistoryIntact(array $fixtures, array $datesBefore, array $executionsBefore, string $stage): void
{
    $datesAfter = tableSnapshot('pm_schedule_dates', ['id', 'pm_schedule_id', 'machine_id', 'scheduled_date', 'status', 'deleted_at'], [$fixtures['schedule_date']]);
    $executionsAfter = tableSnapshot('pm_executions', ['id', 'pm_schedule_date_id', 'machine_id', 'status', 'deleted_at'], [$fixtures['execution']]);

    assertSame($datesBefore, $datesAfter, 'PmScheduleDate berubah pada '.$stage.'.');
    assertSame($executionsBefore, $executionsAfter, 'PmExecution berubah pada '.$stage.'.');

    echo 'protected_history='.$stage.' PASS'.PHP_EOL;
}

/**
 * @param  array<string, int|string>  $fixtures
 */
function assertInvalidStateRejected(array $fixtures, string $stage): void
{
    $machineRejected = ! writesInvalidMachineState($fixtures['machine_active']);
    $scheduleRejected = ! writesInvalidScheduleState($fixtures['schedule_active']);

    assertSame(true, $machineRejected, 'MySQL menerima machine lifecycle_status arbitrary ('.$stage.').');
    assertSame(true, $scheduleRejected, 'MySQL menerima schedule lifecycle_status arbitrary ('.$stage.').');

    echo 'invalid_state_rejection='.$stage.' PASS'.PHP_EOL;
}

function writesInvalidMachineState(int $machineId): bool
{
    return writeSucceeds(static function () use ($machineId): void {
        DB::statement('UPDATE machines SET lifecycle_status = ? WHERE id = ?', ['arbitrary', $machineId]);
    });
}

function writesInvalidScheduleState(int $scheduleId): bool
{
    return writeSucceeds(static function () use ($scheduleId): void {
        DB::statement('UPDATE pm_schedules SET lifecycle_status = ? WHERE id = ?', ['arbitrary', $scheduleId]);
    });
}

function writeSucceeds(Closure $write): bool
{
    try {
        $write();
    } catch (QueryException $exception) {
        // Nilai invalid harus ditolak oleh definisi kolom, bukan constraint lain.
        if (! str_contains($exception->getMessage(), 'lifecycle_status')) {
            throw $exception;
        }

        return false;
    }

    return true;
}

/**
 * Memastikan seluruh nama tabel scratch kontrol RED belum dipakai.
 *
 * @param  array<string, string>  $tables
 */
function assertScratchTablesAvailable(array $tables): void
{
    foreach ($tables as $table) {
        if (DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', SLICE_A_DATABASE)
            ->where('TABLE_NAME', $table)
            ->exists()) {
            throw new RuntimeException('Nama tabel kontrol RED sudah digunakan: '.$table);
        }
    }
}

/**
 * Membuat satu tabel scratch kontrol RED (VARCHAR(20) pra-koreksi).
 */
function createRedScratchTable(string $table): void
{
    assertSafeMySqlConnection();
    DB::statement('CREATE TABLE `'.$table.'` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lifecycle_status VARCHAR(20) NOT NULL DEFAULT \'active\') ENGINE=InnoDB');
}

/**
 * Cleanup zero-residual untuk tabel scratch milik sesi ini.
 *
 * Hanya nama yang benar-benar berhasil dibuat yang di-drop, urutan kebalikan
 * pembuatan, dengan DROP TABLE IF EXISTS. Kegagalan satu DROP tidak boleh
 * menghentikan cleanup objek owned lainnya (kegagalan dilaporkan, bukan
 * ditelan); residual akhir diverifikasi pemanggil via assertScratchTablesAbsent.
 *
 * @param  list<string>  $createdTables
 */
function cleanupOwnedScratchTables(array $createdTables): void
{
    $cleanupFailures = [];

    foreach (array_reverse($createdTables) as $table) {
        try {
            assertSafeMySqlConnection();
            DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable $cleanupException) {
            // Lanjutkan cleanup owned lainnya; kegagalan dicatat dan dilaporkan.
            $cleanupFailures[] = $table.': '.$cleanupException->getMessage();
        }
    }

    if ($cleanupFailures !== []) {
        throw new RuntimeException('Cleanup tabel scratch kontrol RED gagal: '.implode(' | ', $cleanupFailures));
    }
}

/**
 * Bukti nol residual: seluruh nama tabel scratch owned (termasuk yang gagal
 * dibuat) sudah tidak ada di database test.
 *
 * @param  array<string, string>  $tables
 */
function assertScratchTablesAbsent(array $tables): void
{
    foreach ($tables as $table) {
        assertSame(false, DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', SLICE_A_DATABASE)
            ->where('TABLE_NAME', $table)
            ->exists(), 'Tabel kontrol RED masih tersisa: '.$table);
    }
}

/**
 * @param  array<string, int|string>  $fixtures
 * @return array<string, int>
 */
function cleanupFixtures(array $fixtures): array
{
    if ($fixtures === []) {
        return ['rows' => 0];
    }

    assertSafeMySqlConnection();

    $executionIds = [(int) $fixtures['execution']];
    $scheduleDateIds = [(int) $fixtures['schedule_date']];
    $scheduleIds = [(int) $fixtures['schedule_active'], (int) $fixtures['schedule_inactive']];
    $assignmentIds = [(int) $fixtures['assignment_active'], (int) $fixtures['assignment_inactive']];

    // Urutan child-to-parent; hanya ID milik sesi ini yang dihapus.
    DB::table('user_activity_logs')->where('table_name', 'pm_executions')->whereIn('record_id', $executionIds)->delete();
    DB::table('pm_execution_history')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_execution_media')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_execution_items')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_executions')->whereIn('id', $executionIds)->delete();
    DB::table('pm_schedule_dates')->whereIn('id', $scheduleDateIds)->delete();
    DB::table('pm_schedules')->whereIn('id', $scheduleIds)->delete();
    DB::table('pm_checksheet_machines')->whereIn('id', $assignmentIds)->delete();
    DB::table('pm_checksheets')->whereIn('id', [(int) $fixtures['checksheet']])->delete();
    DB::table('machines')->whereIn('id', [(int) $fixtures['machine_active'], (int) $fixtures['machine_inactive']])->delete();
    DB::table('locations')->whereIn('id', [(int) $fixtures['location']])->delete();

    $residual = [
        'executions' => DB::table('pm_executions')->whereIn('id', $executionIds)->count(),
        'schedule_dates' => DB::table('pm_schedule_dates')->whereIn('id', $scheduleDateIds)->count(),
        'schedules' => DB::table('pm_schedules')->whereIn('id', $scheduleIds)->count(),
        'checksheet_machines' => DB::table('pm_checksheet_machines')->whereIn('id', $assignmentIds)->count(),
        'checksheets' => DB::table('pm_checksheets')->where('id', (int) $fixtures['checksheet'])->count(),
        'machines' => DB::table('machines')->whereIn('id', [(int) $fixtures['machine_active'], (int) $fixtures['machine_inactive']])->count(),
        'locations' => DB::table('locations')->where('id', (int) $fixtures['location'])->count(),
    ];

    return $residual;
}

/**
 * Residual fixture owned sesi ini berdasarkan kode deterministik (baca-saja).
 *
 * Dipakai mode fail-inject: setelah transaksi seeding di-rollback, ID fixture
 * tidak tersedia, sehingga kepemilikan dibuktikan lewat kode unik sesi.
 *
 * @return array<string, int>
 */
function ownedFixtureResidualByCode(string $suffix): array
{
    $upper = strtoupper($suffix);
    $machineCodes = ['SLICEA-ACT-'.$upper, 'SLICEA-INA-'.$upper];

    $checksheetIds = DB::table('pm_checksheets')->where('checksheet_code', 'SLICEA-'.$upper)->pluck('id');
    $assignmentIds = DB::table('pm_checksheet_machines')->whereIn('pm_checksheet_id', $checksheetIds)->pluck('id');
    $scheduleIds = DB::table('pm_schedules')->whereIn('pm_checksheet_machine_id', $assignmentIds)->pluck('id');
    $scheduleDateIds = DB::table('pm_schedule_dates')->whereIn('pm_schedule_id', $scheduleIds)->pluck('id');

    return [
        'locations' => DB::table('locations')->where('location_code', 'SLICEA-'.$upper)->count(),
        'machines' => DB::table('machines')->whereIn('machine_code', $machineCodes)->count(),
        'checksheets' => $checksheetIds->count(),
        'checksheet_machines' => $assignmentIds->count(),
        'schedules' => $scheduleIds->count(),
        'schedule_dates' => $scheduleDateIds->count(),
        'executions' => DB::table('pm_executions')->whereIn('pm_schedule_date_id', $scheduleDateIds)->count(),
    ];
}

/**
 * Total baris tabel fixture (baca-saja) untuk membuktikan tabel unrelated
 * tidak berubah selama kontrol kegagalan.
 *
 * @return array<string, int>
 */
function fixtureTableTotals(): array
{
    $totals = [];

    foreach (['locations', 'machines', 'pm_checksheets', 'pm_checksheet_machines', 'pm_schedules', 'pm_schedule_dates', 'pm_executions'] as $table) {
        $totals[$table] = DB::table($table)->count();
    }

    return $totals;
}

/**
 * Mode fail-inject: kontrol kegagalan sengaja setelah fixture pertama dibuat.
 *
 * PASS hanya bila: kegagalan injeksi teramati, residual fixture owned nol,
 * state migrasi kembali pre-proof, dan state unrelated (tabel + migrations)
 * tidak berubah.
 */
function runFailInject(): void
{
    assertPreProofState();

    [$baselineApplied, $baselinePending] = preProofMigrationBaseline();
    $totalsBefore = fixtureTableTotals();

    $token = 'SLICEA_FAIL_'.date('Ymd_His').'_'.getmypid();
    $suffix = substr(hash('sha256', $token), 0, 10);

    echo 'fail_inject_point=after_machine_active'.PHP_EOL;

    $failureObserved = false;

    try {
        seedFixtures($token, 'machine_active');
    } catch (FixtureSeedingIntentionalFailure $expected) {
        $failureObserved = true;
        echo 'intentional_failure_observed=yes'.PHP_EOL;
        echo 'intentional_failure_message='.$expected->getMessage().PHP_EOL;
    }

    if (! $failureObserved) {
        throw new RuntimeException('Injeksi kegagalan tidak terpicu; seeding selesai tanpa kegagalan.');
    }

    // Transaksi seeding harus sudah membatalkan SELURUH baris milik sesi ini.
    $residual = ownedFixtureResidualByCode($suffix);
    echo 'fail_inject_fixture_residual='.json_encode($residual, JSON_THROW_ON_ERROR).PHP_EOL;
    assertSame(0, array_sum($residual), 'Residual fixture owned masih ada setelah kegagalan injeksi.');

    // Tabel unrelated dan state migrasi harus identik dengan baseline.
    assertSame($totalsBefore, fixtureTableTotals(), 'Total baris tabel fixture berubah selama kontrol kegagalan.');
    assertPreProofState();
    assertMigrationIsolation($baselineApplied, $baselinePending, 'fail-inject', false);

    echo 'FAILURE INJECTION — FIXTURE CLEANUP FAILURE-ATOMIC: PASS'.PHP_EOL;
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}
