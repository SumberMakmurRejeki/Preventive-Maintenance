<?php

/**
 * TASK-006 / ADR-010 Slice C — bukti backstop MySQL untuk invariant satu
 * current era per assignment pada pm_schedules.
 *
 * Skrip ini dijalankan manual (di luar PHPUnit testsuite):
 *   php tests/Support/TASK-006/slice-c-current-era-mysql-proof.php
 *
 * Aturan keselamatan:
 * - HANYA boleh berjalan pada database db_preventive_maintenance_test;
 * - identitas database diverifikasi lewat SELECT DATABASE() pada koneksi yang
 *   sama sebelum mutasi apa pun;
 * - database aplikasi utama (db_preventive_maintenance) tidak pernah disentuh;
 * - seluruh fixture milik skrip ini dibersihkan di akhir dan keberadaan sisa
 *   fixture diperiksa ulang (residual check).
 */

declare(strict_types=1);

const TARGET_DATABASE = 'db_preventive_maintenance_test';

$failures = [];
$proofs = 0;

/**
 * Catat hasil satu pembuktian.
 */
function prove(string $label, bool $passed, string $detail = ''): void
{
    global $failures, $proofs;

    $proofs++;

    if (! $passed) {
        $failures[] = $label.($detail !== '' ? " ({$detail})" : '');
    }

    printf("[%s] %s%s\n", $passed ? 'PASS' : 'FAIL', $label, $detail !== '' ? " — {$detail}" : '');
}

/**
 * Baca nilai sederhana dari .env tanpa mengubah konfigurasi apa pun.
 * Hanya kredensial koneksi yang dibaca; tidak ada nilai rahasia yang dicetak.
 */
function envValue(string $key, string $default = ''): string
{
    $path = __DIR__.'/../../../.env';

    if (! is_file($path)) {
        return $default;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), $key.'=')) {
            return trim(trim(substr(trim($line), strlen($key) + 1)), "\"'");
        }
    }

    return $default;
}

// ---------------------------------------------------------------------------
// 1. Koneksi + guard identitas database (fail closed)
// ---------------------------------------------------------------------------

$host = envValue('DB_HOST', '127.0.0.1');
$port = envValue('DB_PORT', '3306');
$username = envValue('DB_USERNAME', 'root');
$password = envValue('DB_PASSWORD', '');

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname=".TARGET_DATABASE,
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

// Guard wajib: identitas database diperiksa pada koneksi yang sama.
$actualDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

if ($actualDatabase !== TARGET_DATABASE) {
    fwrite(STDERR, "STOP — UNSAFE MYSQL DATABASE TARGET (SELECT DATABASE() = {$actualDatabase})\n");
    exit(2);
}

prove('database identity guard', true, 'SELECT DATABASE() = '.$actualDatabase);

// ---------------------------------------------------------------------------
// 2. Bukti migrasi & bentuk kolom generated + unique index
// ---------------------------------------------------------------------------

$migrationNames = [
    '2026_09_11_090000_add_lifecycle_fields_to_machines_and_pm_schedules',
    '2026_09_15_000001_add_current_era_unique_index_to_pm_schedules',
];

foreach ($migrationNames as $migration) {
    $exists = (int) $pdo->query(
        'SELECT COUNT(*) FROM migrations WHERE migration = '.$pdo->quote($migration)
    )->fetchColumn();
    prove("migration applied: {$migration}", $exists === 1);
}

$generated = $pdo->query(
    'SELECT EXTRA, GENERATION_EXPRESSION, COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = '.$pdo->quote(TARGET_DATABASE)."
       AND TABLE_NAME = 'pm_schedules' AND COLUMN_NAME = 'current_era_key'"
)->fetch(PDO::FETCH_ASSOC);

prove(
    'current_era_key is a VIRTUAL generated column',
    is_array($generated)
        && str_contains((string) $generated['EXTRA'], 'VIRTUAL GENERATED')
        && str_contains((string) $generated['GENERATION_EXPRESSION'], 'lifecycle_status')
        && str_contains((string) $generated['GENERATION_EXPRESSION'], 'deleted_at'),
    is_array($generated) ? (string) $generated['GENERATION_EXPRESSION'] : 'column missing',
);

$indexRow = $pdo->query(
    "SHOW INDEX FROM pm_schedules WHERE Key_name = 'pm_schedules_current_era_unique'"
)->fetch(PDO::FETCH_ASSOC);

prove(
    'unique index pm_schedules_current_era_unique exists and is unique',
    is_array($indexRow)
        && (int) $indexRow['Non_unique'] === 0
        && $indexRow['Column_name'] === 'current_era_key',
    is_array($indexRow) ? 'Non_unique='.$indexRow['Non_unique'] : 'index missing',
);

// ---------------------------------------------------------------------------
// 3. Fixture ter-scope (dibersihkan di akhir)
// ---------------------------------------------------------------------------

// Kepemilikan fixture: setiap id dicatat SEGERA setelah barisnya berhasil
// dibuat (di dalam helper insert), sehingga cleanup tidak pernah merujuk id
// yang belum ada dan kegagalan setup parsial tetap tercleanup.
$suffix = bin2hex(random_bytes(4));
$locationId = null;
$machineId = null;
$createdTables = [];
$ownedChecksheetIds = [];
$ownedAssignmentIds = [];
$ownedScheduleIds = [];
$controlChecksheetId = null;
$probeChecksheetId = null;
$baselineCounts = [];

// Kegagalan proof dan kegagalan cleanup dilaporkan terpisah.
$cleanupFailures = [];
$fatal = null;

try {

    $baselineCounts = [
        'pm_schedules' => (int) $pdo->query('SELECT COUNT(*) FROM pm_schedules')->fetchColumn(),
        'pm_checksheet_machines' => (int) $pdo->query('SELECT COUNT(*) FROM pm_checksheet_machines')->fetchColumn(),
        'pm_checksheets' => (int) $pdo->query('SELECT COUNT(*) FROM pm_checksheets')->fetchColumn(),
        'machines' => (int) $pdo->query('SELECT COUNT(*) FROM machines')->fetchColumn(),
        'locations' => (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
    ];

    // Baris kontrol: sengaja TIDAK didaftarkan sebagai fixture milik skrip.
    // Bukti scoping: DELETE ter-scope pada kepemilikan tidak boleh menyentuh
    // baris lain di tabel yang sama. Baris ini dihapus terakhir di finally.
    $pdo->exec(
        'INSERT INTO pm_checksheets (checksheet_code, checksheet_name, is_active, created_at, updated_at)
         VALUES ('.$pdo->quote('PROOF-CTRL-'.$suffix).", 'Slice C Proof Control Checksheet', 0, NOW(), NOW())"
    );
    $controlChecksheetId = (int) $pdo->lastInsertId();

    $pdo->exec(
        'INSERT INTO locations (location_code, location_name, is_active, created_at, updated_at)
         VALUES ('.$pdo->quote('PROOF-LOC-'.$suffix).", 'Slice C Proof Location', 0, NOW(), NOW())"
    );
    $locationId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO machines (location_id, machine_code, machine_name, qr_token, is_active, lifecycle_status, created_at, updated_at)
         VALUES ({$locationId}, ".$pdo->quote('PROOF-MC-'.$suffix).", 'Slice C Proof Machine', ".$pdo->quote('proof-qr-'.$suffix).", 0, 'retired', NOW(), NOW())"
    );
    $machineId = (int) $pdo->lastInsertId();

    /**
     * Insert satu checksheet dan catat kepemilikannya SEGERA setelah insert
     * berhasil, sehingga kegagalan langkah berikutnya tetap tercleanup.
     */
    function insertChecksheet(PDO $pdo, string $suffix, string $tag, array &$ownedChecksheetIds): int
    {
        $pdo->exec(
            'INSERT INTO pm_checksheets (checksheet_code, checksheet_name, is_active, created_at, updated_at)
             VALUES ('.$pdo->quote("PROOF-PM-{$suffix}-{$tag}").", 'Slice C Proof Checksheet {$tag}', 0, NOW(), NOW())"
        );

        // Kepemilikan didaftarkan segera; id tidak menunggu fungsi selesai.
        $checksheetId = (int) $pdo->lastInsertId();
        $ownedChecksheetIds[] = $checksheetId;

        return $checksheetId;
    }

    /**
     * Insert satu assignment dan catat kepemilikannya SEGERA setelah insert
     * berhasil.
     */
    function insertAssignment(PDO $pdo, int $checksheetId, int $machineId, array &$ownedAssignmentIds): int
    {
        $pdo->exec(
            "INSERT INTO pm_checksheet_machines (pm_checksheet_id, machine_id, assigned_at, created_at, updated_at)
             VALUES ({$checksheetId}, {$machineId}, NOW(), NOW(), NOW())"
        );

        // Kepemilikan didaftarkan segera setelah baris benar-benar dibuat.
        $assignmentId = (int) $pdo->lastInsertId();
        $ownedAssignmentIds[] = $assignmentId;

        return $assignmentId;
    }

    /**
     * Buat checksheet + assignment untuk skenario pembuktian.
     *
     * @return array{0: int, 1: int} [checksheetId, assignmentId]
     */
    function makeAssignment(PDO $pdo, string $suffix, string $tag, int $machineId, array &$ownedChecksheetIds, array &$ownedAssignmentIds): array
    {
        $checksheetId = insertChecksheet(
            $pdo,
            $suffix,
            $tag,
            $ownedChecksheetIds
        );

        $assignmentId = insertAssignment(
            $pdo,
            $checksheetId,
            $machineId,
            $ownedAssignmentIds
        );

        return [$checksheetId, $assignmentId];
    }

    /**
     * Insert satu baris pm_schedules untuk pembuktian invariant.
     */
    function insertSchedule(PDO $pdo, int $assignmentId, string $lifecycleStatus, int $isActive): int
    {
        $pdo->exec(
            "INSERT INTO pm_schedules
                (pm_checksheet_machine_id, frequency_type, operational_from, start_date, generate_until, is_active, lifecycle_status, created_at, updated_at)
             VALUES ({$assignmentId}, 'daily', '2026-09-01', '2026-09-01', '2026-09-05', {$isActive}, ".$pdo->quote($lifecycleStatus).', NOW(), NOW())'
        );

        return (int) $pdo->lastInsertId();
    }

    /**
     * Jalankan insert yang diharapkan gagal karena unique index.
     *
     * @return array{0: bool, 1: string} [tertolak, detail error MySQL]
     */
    function expectUniqueViolation(PDO $pdo, int $assignmentId, string $lifecycleStatus, int $isActive, array &$ownedScheduleIds): array
    {
        try {
            // Insert yang seharusnya ditolak ternyata berhasil: baris tetap
            // didaftarkan sebagai milik skrip ini agar cleanup tidak bocor.
            $scheduleId = insertSchedule($pdo, $assignmentId, $lifecycleStatus, $isActive);
            $ownedScheduleIds[] = $scheduleId;

            return [false, 'insert berhasil padahal seharusnya ditolak'];
        } catch (PDOException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? '');
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $message = $exception->getMessage();

            return [
                $sqlState === '23000' && $driverCode === 1062 && str_contains($message, 'pm_schedules_current_era_unique'),
                "sqlstate={$sqlState} errno={$driverCode}",
            ];
        }
    }

    [$checksheetA, $assignmentA] = makeAssignment($pdo, $suffix, 'A', $machineId, $ownedChecksheetIds, $ownedAssignmentIds);

    [$checksheetB, $assignmentB] = makeAssignment($pdo, $suffix, 'B', $machineId, $ownedChecksheetIds, $ownedAssignmentIds);

    // ---------------------------------------------------------------------------
    // 4. Beberapa era ENDED boleh hidup bersamaan
    // ---------------------------------------------------------------------------

    $ownedScheduleIds[] = $endedOne = insertSchedule($pdo, $assignmentA, 'ended', 0);
    $ownedScheduleIds[] = $endedTwo = insertSchedule($pdo, $assignmentA, 'ended', 0);

    $endedCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM pm_schedules WHERE pm_checksheet_machine_id = {$assignmentA} AND lifecycle_status = 'ended'"
    )->fetchColumn();

    prove('multiple ENDED eras are allowed', $endedCount === 2, "count={$endedCount}");

    // ---------------------------------------------------------------------------
    // 5. ENDED + ACTIVE boleh berdampingan (era current baru)
    // ---------------------------------------------------------------------------

    $ownedScheduleIds[] = $activeOne = insertSchedule($pdo, $assignmentA, 'active', 1);

    prove(
        'ENDED plus ACTIVE is allowed',
        $activeOne > 0 && $endedCount === 2,
        "active_id={$activeOne}",
    );

    $currentKey = (int) $pdo->query("SELECT current_era_key FROM pm_schedules WHERE id = {$activeOne}")->fetchColumn();
    prove('generated key of current ACTIVE era equals assignment id', $currentKey === $assignmentA, "key={$currentKey}");

    $endedKey = $pdo->query("SELECT current_era_key FROM pm_schedules WHERE id = {$endedOne}")->fetchColumn();
    prove('generated key of ENDED era is NULL', $endedKey === null, 'key='.var_export($endedKey, true));

    // ---------------------------------------------------------------------------
    // 6. Kombinasi current era kedua ditolak database (23000 / 1062)
    // ---------------------------------------------------------------------------

    [$rejectedActive, $detailActive] = expectUniqueViolation($pdo, $assignmentA, 'active', 1, $ownedScheduleIds);
    prove('ACTIVE plus ACTIVE is rejected by unique backstop', $rejectedActive, $detailActive);

    [$rejectedPaused, $detailPaused] = expectUniqueViolation($pdo, $assignmentA, 'paused', 0, $ownedScheduleIds);
    prove('ACTIVE plus PAUSED is rejected by unique backstop', $rejectedPaused, $detailPaused);

    // ---------------------------------------------------------------------------
    // 7. Baris current yang soft-deleted tidak memblokir era current baru
    // ---------------------------------------------------------------------------

    $pdo->exec("UPDATE pm_schedules SET deleted_at = NOW() WHERE id = {$activeOne}");
    $softDeletedKey = $pdo->query("SELECT current_era_key FROM pm_schedules WHERE id = {$activeOne}")->fetchColumn();
    prove('generated key of soft-deleted current row is NULL', $softDeletedKey === null, 'key='.var_export($softDeletedKey, true));

    $ownedScheduleIds[] = $pausedOne = insertSchedule($pdo, $assignmentA, 'paused', 0);
    prove(
        'soft-deleted non-terminal row does not block a new current era',
        $pausedOne > 0,
        "paused_id={$pausedOne}",
    );

    [$rejectedPausedPair, $detailPausedPair] = expectUniqueViolation($pdo, $assignmentA, 'paused', 0, $ownedScheduleIds);
    prove('PAUSED plus PAUSED is rejected by unique backstop', $rejectedPausedPair, $detailPausedPair);

    // ---------------------------------------------------------------------------
    // 8. Preflight migrasi mendeteksi collision (dibuktikan pada tabel scratch
    //    tanpa unique index, karena index membuat collision mustahil secara fisik)
    // ---------------------------------------------------------------------------

    // Tabel scratch dimiliki penuh oleh skrip ini: nama unik per run sehingga
    // tidak perlu (dan tidak boleh) DROP tanpa syarat pada nama generik bersama.
    // $createdTables hanya diisi SETELAH CREATE TABLE sukses, sehingga cleanup
    // tidak pernah mengklaim kepemilikan tabel yang gagal dibuat.
    $scratchTable = 'proof_current_era_preflight_'.$suffix;

    $pdo->exec(
        "CREATE TABLE {$scratchTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            pm_checksheet_machine_id BIGINT UNSIGNED NOT NULL,
            lifecycle_status ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
            deleted_at TIMESTAMP NULL DEFAULT NULL
        )"
    );
    $createdTables[] = $scratchTable;

    $pdo->exec(
        "INSERT INTO {$scratchTable} (pm_checksheet_machine_id, lifecycle_status, deleted_at) VALUES
            ({$assignmentA}, 'active', NULL),
            ({$assignmentA}, 'paused', NULL),
            ({$assignmentA}, 'ended', NULL),
            ({$assignmentA}, 'active', NOW())"
    );

    // Query preflight identik dengan yang dijalankan migrasi.
    $preflightSql = "SELECT pm_checksheet_machine_id, COUNT(*) AS current_era_count
         FROM {$scratchTable}
         WHERE deleted_at IS NULL AND lifecycle_status IN ('active','paused')
         GROUP BY pm_checksheet_machine_id
         HAVING COUNT(*) > 1";

    $collisions = $pdo->query($preflightSql)->fetchAll(PDO::FETCH_ASSOC);

    prove(
        'preflight detects duplicate current era',
        count($collisions) === 1
            && (int) $collisions[0]['pm_checksheet_machine_id'] === $assignmentA
            && (int) $collisions[0]['current_era_count'] === 2,
        'collisions='.json_encode($collisions),
    );

    // Preflight pada data nyata (tanpa collision) harus bersih.
    $realCollisions = $pdo->query(
        "SELECT pm_checksheet_machine_id, COUNT(*) AS current_era_count
         FROM pm_schedules
         WHERE deleted_at IS NULL AND lifecycle_status IN ('active','paused')
         GROUP BY pm_checksheet_machine_id
         HAVING COUNT(*) > 1"
    )->fetchAll(PDO::FETCH_ASSOC);

    prove('no real current-era collision present after proof fixtures', $realCollisions === [], 'collisions='.json_encode($realCollisions));

    // ---------------------------------------------------------------------------
    // 9. Rollback dan reapply pada assignment terpisah
    // ---------------------------------------------------------------------------

    $ownedScheduleIds[] = $firstActiveB = insertSchedule($pdo, $assignmentB, 'active', 1);

    $pdo->beginTransaction();
    [$rejectedInTransaction, $detailInTransaction] = expectUniqueViolation($pdo, $assignmentB, 'paused', 0, $ownedScheduleIds);
    $pdo->rollBack();

    $countAfterRollback = (int) $pdo->query(
        "SELECT COUNT(*) FROM pm_schedules WHERE pm_checksheet_machine_id = {$assignmentB}"
    )->fetchColumn();

    prove(
        'constraint failure inside transaction is rollback-safe',
        $rejectedInTransaction && $countAfterRollback === 1,
        "{$detailInTransaction} rows_after_rollback={$countAfterRollback}",
    );

    // Reapply: baris current dihapus (simulasi rollback di level aplikasi) lalu dibuat ulang.
    $pdo->exec("DELETE FROM pm_schedules WHERE id = {$firstActiveB}");
    $ownedScheduleIds[] = $secondActiveB = insertSchedule($pdo, $assignmentB, 'active', 1);

    $reapplyKey = (int) $pdo->query("SELECT current_era_key FROM pm_schedules WHERE id = {$secondActiveB}")->fetchColumn();

    prove(
        'reapply after rollback succeeds with a fresh current era',
        $secondActiveB > 0 && $reapplyKey === $assignmentB,
        "new_id={$secondActiveB} key={$reapplyKey}",
    );

    // ---------------------------------------------------------------------------
    // 10. Bukti kegagalan setup parsial: checksheet dibuat, assignment gagal
    // ---------------------------------------------------------------------------

    // Guard wajib: probe tidak boleh berjalan di dalam transaksi, supaya bukti
    // cleanup tidak pernah bergantung pada rollback transaksi sebelumnya.
    if ($pdo->inTransaction()) {
        throw new RuntimeException(
            'STOP — PROBE PARTIAL SETUP DILARANG BERJALAN DI DALAM TRANSAKSI'
        );
    }

    printf("[GUARD] probe partial setup berjalan di luar transaksi — OK\n");

    // Precondition probe: machine id 0 dijamin tidak ada, sehingga FK
    // pcm_machine_id_foreign pasti menolak insert assignment di bawah.
    prove(
        'partial setup precondition: machine id 0 does not exist',
        (int) $pdo->query('SELECT COUNT(*) FROM machines WHERE id = 0')->fetchColumn() === 0,
    );

    // Checksheet didaftarkan segera saat dibuat; inilah titik yang dulu bocor.
    $probeChecksheetId = insertChecksheet($pdo, $suffix, 'PROBE', $ownedChecksheetIds);

    $probeAssignmentRejected = false;
    $probeDetail = '';

    try {
        // Insert assignment dengan machine id yang tidak ada: HARUS gagal
        // setelah checksheet berhasil dibuat (skenario kegagalan parsial).
        insertAssignment($pdo, $probeChecksheetId, 0, $ownedAssignmentIds);
        $probeDetail = 'insert berhasil padahal seharusnya ditolak';
    } catch (PDOException $exception) {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        // Bukti harus spesifik: SQLSTATE 23000 saja tidak cukup, karena
        // kegagalan integrity lain bisa lolos. Nama FK wajib muncul supaya
        // probe benar-benar membuktikan trigger FK pcm_machine_id_foreign.
        $probeAssignmentRejected = $sqlState === '23000'
            && str_contains($message, 'pcm_machine_id_foreign');
        $probeDetail = "sqlstate={$sqlState} errno={$driverCode} constraint="
            .(str_contains($message, 'pcm_machine_id_foreign') ? 'pcm_machine_id_foreign' : 'tidak terdeteksi');
    }

    prove(
        'partial setup: assignment insert fails after checksheet creation',
        $probeAssignmentRejected,
        $probeDetail,
    );

    // Baris yatim harus benar-benar ada sebelum cleanup, supaya bukti
    // "dihapus oleh cleanup" tidak kosong maknanya.
    $probeStillPresent = (int) $pdo->query(
        "SELECT COUNT(*) FROM pm_checksheets WHERE id = {$probeChecksheetId}"
    )->fetchColumn();

    prove(
        'partial setup: orphan checksheet exists before cleanup',
        $probeStillPresent === 1,
        "checksheet_id={$probeChecksheetId}",
    );

} catch (Throwable $exception) {
    // Proof berhenti di tengah jalan: cleanup di finally tetap berjalan agar
    // owned fixture tidak bocor hanya karena pembuktian terhenti.
    $fatal = $exception;
} finally {
    // -----------------------------------------------------------------------
    // 11. Cleanup owned fixture (failure-safe, tanpa DROP/DELETE menyeluruh)
    // -----------------------------------------------------------------------

    // Setiap langkah dibungkus try/catch sendiri: kegagalan satu langkah
    // tidak menghentikan langkah lain. Hanya baris milik skrip ini yang dihapus.
    $deletions = [];

    if ($ownedScheduleIds !== []) {
        $deletions['pm_schedules owned rows'] =
            'DELETE FROM pm_schedules WHERE id IN ('.implode(',', $ownedScheduleIds).')';
    }
    if ($ownedAssignmentIds !== []) {
        $deletions['pm_schedules owned assignments'] =
            'DELETE FROM pm_schedules WHERE pm_checksheet_machine_id IN ('.implode(',', $ownedAssignmentIds).')';
    }
    if ($ownedChecksheetIds !== []) {
        $deletions['pm_checksheet_machines'] =
            'DELETE FROM pm_checksheet_machines WHERE pm_checksheet_id IN ('.implode(',', $ownedChecksheetIds).')';
        $deletions['pm_checksheets'] =
            'DELETE FROM pm_checksheets WHERE id IN ('.implode(',', $ownedChecksheetIds).')';
    }
    if ($machineId !== null) {
        $deletions['machines'] = "DELETE FROM machines WHERE id = {$machineId}";
    }
    if ($locationId !== null) {
        $deletions['locations'] = "DELETE FROM locations WHERE id = {$locationId}";
    }

    foreach ($deletions as $label => $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            $cleanupFailures[] = "{$label}: ".$exception->getMessage();
        }
    }

    // Tabel scratch dihapus terbalik dari urutan pembuatan, dengan IF EXISTS
    // supaya kegagalan satu tabel tidak menghentikan pembersihan tabel lainnya.
    foreach (array_reverse($createdTables) as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        } catch (Throwable $exception) {
            $cleanupFailures[] = "drop {$table}: ".$exception->getMessage();
        }
    }

    // Bukti scoping: baris kontrol tidak terdaftar sebagai fixture milik
    // skrip ini, jadi DELETE ter-scope di atas tidak boleh menghapusnya.
    if ($controlChecksheetId !== null) {
        $controlPreserved = (int) $pdo->query(
            "SELECT COUNT(*) FROM pm_checksheets WHERE id = {$controlChecksheetId}"
        )->fetchColumn();

        prove(
            'scoped cleanup preserves unowned control row',
            $controlPreserved === 1,
            "control_id={$controlChecksheetId}",
        );

        // Baris kontrol dihapus terakhir dengan statement tersendiri agar
        // tidak meninggalkan residu apa pun di database test.
        try {
            $pdo->exec("DELETE FROM pm_checksheets WHERE id = {$controlChecksheetId}");
        } catch (Throwable $exception) {
            $cleanupFailures[] = 'control checksheet: '.$exception->getMessage();
        }
    }

    // Bukti kepemilikan segera: checksheet yatim dari skenario kegagalan
    // setup parsial harus hilang karena id-nya didaftarkan saat dibuat.
    if ($probeChecksheetId !== null) {
        $probeResidual = (int) $pdo->query(
            "SELECT COUNT(*) FROM pm_checksheets WHERE id = {$probeChecksheetId}"
        )->fetchColumn();

        prove(
            'partial setup orphan checksheet removed by cleanup',
            $probeResidual === 0,
            "remaining={$probeResidual}",
        );
    }
    // Residual check hanya membuktikan objek milik skrip ini sudah lenyap.

    try {
        $residual = [
            'pm_schedules' => (int) $pdo->query('SELECT COUNT(*) FROM pm_schedules')->fetchColumn(),
            'pm_checksheet_machines' => (int) $pdo->query('SELECT COUNT(*) FROM pm_checksheet_machines')->fetchColumn(),
            'pm_checksheets' => (int) $pdo->query('SELECT COUNT(*) FROM pm_checksheets')->fetchColumn(),
            'machines' => (int) $pdo->query('SELECT COUNT(*) FROM machines')->fetchColumn(),
            'locations' => (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
        ];

        foreach ($baselineCounts as $table => $baseline) {
            prove(
                "no residual owned fixture in {$table}",
                $residual[$table] === $baseline,
                "baseline={$baseline} residual={$residual[$table]}",
            );
        }

        $ownedTableNames = $createdTables === []
            ? []
            : array_map(fn (string $table): string => $pdo->quote($table), $createdTables);

        $residualScratchTables = $ownedTableNames === []
            ? 0
            : (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = '.$pdo->quote(TARGET_DATABASE).'
                       AND TABLE_NAME IN ('.implode(',', $ownedTableNames).')'
            )->fetchColumn();

        prove('no residual scratch table', $residualScratchTables === 0, "found={$residualScratchTables}");
    } catch (Throwable $exception) {
        $cleanupFailures[] = 'residual verification: '.$exception->getMessage();
    }

    prove(
        'owned fixture cleanup executed without failure',
        $cleanupFailures === [],
        $cleanupFailures === [] ? 'cleanup bersih' : implode('; ', $cleanupFailures),
    );
}

// ---------------------------------------------------------------------------
// Ringkasan
// ---------------------------------------------------------------------------

printf("\n%d pembuktian dijalankan; %d gagal.\n", $proofs, count($failures));

if ($fatal !== null) {
    // Owned fixture cleanup tetap dijalankan di finally; kegagalan cleanup
    // dilaporkan sebagai pembuktian tersendiri di atas.
    fwrite(STDERR, 'PROOF ABORTED: '.$fatal->getMessage()."\n");
    fwrite(STDERR, 'kegagalan cleanup = '.count($cleanupFailures)."\n");
    exit(1);
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED PROOFS:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "SLICE C MYSQL BACKSTOP PROOF — PASS\n";
