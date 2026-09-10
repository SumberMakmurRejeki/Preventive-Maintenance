<?php

declare(strict_types=1);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmExecution;
use App\Models\PmExecutionHistory;
use App\Models\PmExecutionItem;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\PrimeNotification;
use App\Models\User;
use App\Services\PM\PmReviewService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

const RI012_DATABASE = 'db_preventive_maintenance_test';
const RI012_TIMEOUT_SECONDS = 45;

$mode = $argv[1] ?? 'orchestrate';

if ($mode === 'worker') {
    $workerDirectory = $argv[4] ?? '';

    try {
        runWorker(
            scenario: $argv[2] ?? '',
            role: $argv[3] ?? '',
            directory: $workerDirectory,
            fixturePath: $argv[5] ?? '',
        );
        exit(0);
    } catch (Throwable $exception) {
        // Error worker ditulis ke berkas agar orchestrator dapat membedakan
        // assertion bisnis dari deadlock, timeout, atau kegagalan bootstrap.
        if ($workerDirectory !== '') {
            writeJson($workerDirectory.'/'.($argv[3] ?? 'unknown').'.error.json', [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
        fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}

try {
    runOrchestrator();
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    echo "STOP — PROOF INCONCLUSIVE\n";
    exit(1);
}

function runOrchestrator(): void
{
    bootstrapApplication();
    $connectionProof = assertSafeMySqlConnection();
    echo 'database_identity='.$connectionProof['database'].' engine='.implode(',', $connectionProof['engines']).PHP_EOL;

    $token = 'RI012_'.date('Ymd_His').'_'.getmypid().'_'.bin2hex(random_bytes(4));
    $directory = storage_path('framework/testing/ri012/'.$token);
    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Tidak dapat membuat direktori bukti RI-012.');
    }

    $fixtures = [];
    $fixturePath = $directory.'/fixtures.json';

    try {
        // Semua fixture dibuat satu per skenario supaya setiap race memiliki
        // parent row dan occurrence sendiri tanpa kontensi silang.
        foreach (['r1', 'r2', 'r3'] as $scenario) {
            $fixtures[$scenario] = createFixture($token, $scenario);
        }
        writeJson($fixturePath, $fixtures);

        $r1 = runR1($directory, $fixturePath, $fixtures['r1']);
        echo "R1 result=PASS ordering=stale-loaded -> approval-committed -> stale-update-rejected\n";
        printScenarioSummary('R1', $r1);

        $r2 = runR2($directory, $fixturePath, $fixtures['r2']);
        echo "R2 result=PASS ordering=approval-lock-held -> update-lock-attempted -> approval-committed -> update-rejected\n";
        printScenarioSummary('R2', $r2);

        $r3 = runR3($directory, $fixturePath, $fixtures['r3']);
        echo "R3 result=PASS ordering=update-lock-held -> approval-lock-attempted -> update-committed -> approval-committed\n";
        printScenarioSummary('R3', $r3);
    } finally {
        if ($fixtures !== []) {
            $residuals = cleanupFixtures($fixtures);
            echo 'fixture_residuals='.json_encode($residuals, JSON_THROW_ON_ERROR).PHP_EOL;
            if (array_sum($residuals) !== 0) {
                throw new RuntimeException('Residual fixture RI-012 masih tersisa.');
            }
        }
    }

    echo "mysql_1213_count=0\n";
    echo "unexplained_timeout_count=0\n";
    echo "stale_post_approval_mutation_count=0\n";
    echo "invalid_final_lifecycle_count=0\n";
    echo 'evidence_directory='.$directory.PHP_EOL;
    echo "RI-012 MYSQL CONCURRENCY PROOF — PASS\n";
    echo "R1 / R2 / R3 VERIFIED\n";
    echo "READY FOR INDEPENDENT REVIEW\n";
}

function runR1(string $directory, string $fixturePath, array $fixture): array
{
    $scenarioDirectory = prepareScenarioDirectory($directory, 'r1');
    $a = startWorker('r1', 'a', $scenarioDirectory, $fixturePath);
    waitForMarker($scenarioDirectory, 'a_stale_model_ready');

    $b = startWorker('r1', 'b', $scenarioDirectory, $fixturePath);
    waitForMarker($scenarioDirectory, 'b_approved_committed');
    writeMarker($scenarioDirectory, 'release_a_stale');

    $aResult = waitForWorker($a, $scenarioDirectory, 'a');
    $bResult = waitForWorker($b, $scenarioDirectory, 'b');
    assertSame('rejected', $aResult['outcome'], 'R1 stale update harus ditolak.');
    assertSame('approved', $bResult['outcome'], 'R1 approval harus berhasil.');

    assertNoDeadlockOrTimeout($aResult, 'R1 A');
    assertNoDeadlockOrTimeout($bResult, 'R1 B');
    assertApprovedState($fixture, 'R1 approval');
    assertNoStaleMutation($fixture, $fixture['baseline'], 'R1', 'Approved r1', 'Review note R3');

    return ['a' => $aResult, 'b' => $bResult];
}

function runR2(string $directory, string $fixturePath, array $fixture): array
{
    $scenarioDirectory = prepareScenarioDirectory($directory, 'r2');
    $a = startWorker('r2', 'a', $scenarioDirectory, $fixturePath);
    waitForMarker($scenarioDirectory, 'a_stale_model_ready');

    // Worker B menahan outer transaction setelah approve menyelesaikan
    // transaction bersarang, sehingga lock InnoDB tetap aktif secara nyata.
    $b = startWorker('r2', 'b', $scenarioDirectory, $fixturePath);
    waitForMarker($scenarioDirectory, 'b_lock_held');
    writeMarker($scenarioDirectory, 'release_a_begin');
    // Query listener baru dipanggil setelah query selesai; observasi lock wait
    // Performance Schema dipakai untuk membuktikan overlap sebelum release B.
    waitForExecutionLockWait((int) $fixture['execution']);
    writeMarker($scenarioDirectory, 'release_b');
    waitForMarker($scenarioDirectory, 'a_lock_query_sent');
    waitForMarker($scenarioDirectory, 'b_approved_committed');

    $aResult = waitForWorker($a, $scenarioDirectory, 'a');
    $bResult = waitForWorker($b, $scenarioDirectory, 'b');
    assertSame('rejected', $aResult['outcome'], 'R2 stale update harus ditolak setelah lock dilepas.');
    assertSame('approved', $bResult['outcome'], 'R2 approval harus berhasil.');

    assertNoDeadlockOrTimeout($aResult, 'R2 A');
    assertNoDeadlockOrTimeout($bResult, 'R2 B');
    assertApprovedState($fixture, 'R2 approval');
    assertNoStaleMutation($fixture, $fixture['baseline'], 'R2', 'Approved r2', 'Review note R3');

    return ['a' => $aResult, 'b' => $bResult];
}

function runR3(string $directory, string $fixturePath, array $fixture): array
{
    $scenarioDirectory = prepareScenarioDirectory($directory, 'r3');
    $a = startWorker('r3', 'a', $scenarioDirectory, $fixturePath);
    waitForMarker($scenarioDirectory, 'a_update_lock_held');
    $b = startWorker('r3', 'b', $scenarioDirectory, $fixturePath);
    // B sudah mengirim SELECT ... FOR UPDATE ketika InnoDB mencatat baris
    // requesting pada data_lock_waits; ini menghindari sinkronisasi sleep-only.
    waitForExecutionLockWait((int) $fixture['execution']);
    writeMarker($scenarioDirectory, 'release_a');
    waitForMarker($scenarioDirectory, 'b_execution_lock_query_sent');
    waitForMarker($scenarioDirectory, 'b_approved_committed');

    $aResult = waitForWorker($a, $scenarioDirectory, 'a');
    $bResult = waitForWorker($b, $scenarioDirectory, 'b');
    assertSame('updated', $aResult['outcome'], 'R3 update valid yang menang lebih dulu harus berhasil.');
    assertSame('approved', $bResult['outcome'], 'R3 approval setelah update harus berhasil.');

    assertNoDeadlockOrTimeout($aResult, 'R3 A');
    assertNoDeadlockOrTimeout($bResult, 'R3 B');
    assertUpdateThenApprovalState($fixture, 'R3');

    return ['a' => $aResult, 'b' => $bResult];
}

function runWorker(string $scenario, string $role, string $directory, string $fixturePath): void
{
    if (! in_array($scenario, ['r1', 'r2', 'r3'], true) || ! in_array($role, ['a', 'b'], true)) {
        throw new InvalidArgumentException('Worker RI-012 tidak valid.');
    }

    bootstrapApplication();
    assertSafeMySqlConnection();
    $fixtures = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    $fixture = $fixtures[$scenario];
    $execution = PmExecution::query()->findOrFail($fixture['execution']);
    $admin = User::query()->findOrFail($fixture['admin']);
    $service = app(PmReviewService::class);

    if ($role === 'a') {
        $staleExecution = PmExecution::query()->findOrFail($fixture['execution']);
        writeMarker($directory, 'a_stale_model_ready');

        if ($scenario === 'r1') {
            waitForMarker($directory, 'release_a_stale');
        } elseif ($scenario === 'r2') {
            waitForMarker($directory, 'release_a_begin');
            listenForExecutionLock($directory, 'a_lock_query_sent');
        } else {
            listenForExecutionLock($directory, 'a_execution_lock_query_sent');
        }

        if ($scenario === 'r3') {
            $connection = DB::connection('mysql');
            $connection->beginTransaction();
            try {
                $service->update(
                    request: reviewRequest($fixture['execution'], 'PUT'),
                    execution: $staleExecution,
                    admin: $admin,
                    payload: updatePayload($fixture),
                );
                writeMarker($directory, 'a_update_lock_held');
                waitForMarker($directory, 'release_a');
                $connection->commit();
                writeMarker($directory, 'a_update_committed');
                writeJson($directory.'/a.result.json', ['outcome' => 'updated']);
            } catch (Throwable $exception) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                throw $exception;
            }

            return;
        }

        try {
            $service->update(
                request: reviewRequest($fixture['execution'], 'PUT'),
                execution: $staleExecution,
                admin: $admin,
                payload: updatePayload($fixture),
            );
            writeJson($directory.'/a.result.json', ['outcome' => 'unexpectedly_updated']);
        } catch (ValidationException $exception) {
            writeJson($directory.'/a.result.json', [
                'outcome' => 'rejected',
                'errors' => $exception->errors(),
            ]);
        }

        return;
    }

    if ($scenario === 'r2') {
        $connection = DB::connection('mysql');
        $connection->beginTransaction();
        try {
            $service->approve(
                request: reviewRequest($fixture['execution'], 'PATCH'),
                execution: $execution,
                admin: $admin,
                reviewNote: $scenario === 'r3' ? null : 'Approved '.$scenario,
            );
            writeMarker($directory, 'b_lock_held');
            waitForMarker($directory, 'release_b');
            $connection->commit();
            writeMarker($directory, 'b_approved_committed');
            writeJson($directory.'/b.result.json', ['outcome' => 'approved']);
        } catch (Throwable $exception) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw $exception;
        }

        return;
    }

    if ($scenario === 'r3') {
        listenForExecutionLock($directory, 'b_execution_lock_query_sent');
    }

    try {
        $service->approve(
            request: reviewRequest($fixture['execution'], 'PATCH'),
            execution: $execution,
            admin: $admin,
            reviewNote: $scenario === 'r3' ? null : 'Approved '.$scenario,
        );
        writeMarker($directory, 'b_approved_committed');
        writeJson($directory.'/b.result.json', ['outcome' => 'approved']);
    } catch (ValidationException $exception) {
        writeJson($directory.'/b.result.json', [
            'outcome' => 'rejected',
            'errors' => $exception->errors(),
        ]);
        throw $exception;
    }
}

function bootstrapApplication(): void
{
    // Environment dipaksa ke database dedicated sebelum bootstrap agar child
    // process tidak pernah mewarisi target database utama dari .env.
    foreach ([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => RI012_DATABASE,
        'DB_URL' => '',
    ] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql']);
    config(['database.connections.mysql.database' => RI012_DATABASE]);
    DB::purge('mysql');
    DB::reconnect('mysql');
}

/** @return array{database:string,engines:array<int,string>} */
function assertSafeMySqlConnection(): array
{
    $connection = DB::connection('mysql');
    $database = (string) $connection->selectOne('SELECT DATABASE() AS database_name')->database_name;
    $engines = array_values(array_unique(array_map(
        static fn (object $row): string => (string) $row->engine_name,
        $connection->select(
            'SELECT ENGINE AS engine_name FROM information_schema.TABLES '
           .'WHERE TABLE_SCHEMA = DATABASE() '
            .'AND TABLE_NAME IN (?, ?) GROUP BY ENGINE ORDER BY ENGINE',
            ['pm_executions', 'pm_schedule_dates'],
        ),
    )));

    // Identitas database dan engine harus tepat sebelum fixture apa pun dibuat
    // atau dibersihkan; target ambigu dianggap kegagalan keselamatan.
    if ($database !== RI012_DATABASE || $engines !== ['InnoDB']) {
        throw new RuntimeException('Unsafe RI-012 database identity or engine.');
    }

    return ['database' => $database, 'engines' => $engines];
}

/** @return array<string,int|string|array<string,mixed>> */
function createFixture(string $token, string $scenario): array
{
    assertSafeMySqlConnection();
    $suffix = strtoupper($scenario).'_'.substr($token, -8);
    $now = now()->seconds(0);

    $location = Location::query()->create([
        'location_code' => 'RI012-'.$suffix,
        'location_name' => 'RI-012 '.$scenario,
        'is_active' => true,
    ]);
    $machine = Machine::query()->create([
        'location_id' => $location->id,
        'machine_code' => 'RI012-'.$suffix,
        'machine_name' => 'RI-012 '.$scenario.' Machine',
        'qr_token' => 'ri012-'.$token.'-'.$scenario,
        'is_active' => true,
    ]);
    $admin = User::query()->create([
        'name' => 'RI-012 Admin '.$scenario,
        'username' => 'r12_'.substr(hash('sha256', $token.'_'.$scenario.'_admin'), 0, 40),
        'password' => password_hash('ri012-proof', PASSWORD_BCRYPT),
        'role' => 'admin',
        'is_active' => true,
    ]);
    $operator = User::query()->create([
        'name' => 'RI-012 Operator '.$scenario,
        'username' => 'r12_'.substr(hash('sha256', $token.'_'.$scenario.'_operator'), 0, 40),
        'password' => password_hash('ri012-proof', PASSWORD_BCRYPT),
        'role' => 'operator',
        'is_active' => true,
    ]);
    $checksheet = PmChecksheet::query()->create([
        'checksheet_code' => 'RI012-'.$suffix,
        'checksheet_name' => 'RI-012 '.$scenario.' Checksheet',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $checksheetMachine = PmChecksheetMachine::query()->create([
        'pm_checksheet_id' => $checksheet->id,
        'machine_id' => $machine->id,
        'assigned_at' => $now,
        'created_by' => $admin->id,
    ]);
    $part = PmChecksheetPart::query()->create([
        'pm_checksheet_machine_id' => $checksheetMachine->id,
        'part_name' => 'RI-012 Part '.$scenario,
        'is_active' => true,
    ]);
    $actionStandard = PmChecksheetStandard::query()->create([
        'pm_checksheet_part_id' => $part->id,
        'standard_name' => 'RI-012 Action '.$scenario,
        'input_type' => 'action',
        'action_options' => ['OK', 'LUBRIKASI'],
        'is_required' => true,
        'is_active' => true,
    ]);
    $numberStandard = PmChecksheetStandard::query()->create([
        'pm_checksheet_part_id' => $part->id,
        'standard_name' => 'RI-012 Number '.$scenario,
        'input_type' => 'number',
        'target_value' => 50,
        'unit' => 'C',
        'is_required' => true,
        'is_active' => true,
    ]);
    $schedule = PmSchedule::query()->create([
        'pm_checksheet_machine_id' => $checksheetMachine->id,
        'frequency_type' => 'daily',
        'start_date' => $now->copy()->subDay()->toDateString(),
        'generate_until' => $now->copy()->addDay()->toDateString(),
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $scheduleDate = PmScheduleDate::query()->create([
        'pm_schedule_id' => $schedule->id,
        'machine_id' => $machine->id,
        'scheduled_date' => $now->toDateString(),
        'status' => 'waiting_review',
    ]);
    $execution = PmExecution::query()->create([
        'machine_code_snapshot' => $machine->machine_code,
        'machine_name_snapshot' => $machine->machine_name,
        'location_code_snapshot' => $location->location_code,
        'location_name_snapshot' => $location->location_name,
        'pm_schedule_date_id' => $scheduleDate->id,
        'machine_id' => $machine->id,
        'operator_id' => $operator->id,
        'operator_name_snapshot' => $operator->name,
        'status' => 'waiting_review',
        'started_at' => $now->copy()->subHour(),
        'submitted_at' => $now->copy()->subMinutes(20),
    ]);
    $actionItem = PmExecutionItem::query()->create([
        'pm_execution_id' => $execution->id,
        'pm_checksheet_part_id' => $part->id,
        'pm_checksheet_standard_id' => $actionStandard->id,
        'part_name_snapshot' => $part->part_name,
        'standard_name_snapshot' => $actionStandard->standard_name,
        'input_type_snapshot' => 'action',
        'action_options_snapshot' => ['OK', 'LUBRIKASI'],
        'action_value' => 'OK',
        'is_warning' => false,
        'note' => 'Catatan baseline',
    ]);
    $numberItem = PmExecutionItem::query()->create([
        'pm_execution_id' => $execution->id,
        'pm_checksheet_part_id' => $part->id,
        'pm_checksheet_standard_id' => $numberStandard->id,
        'part_name_snapshot' => $part->part_name,
        'standard_name_snapshot' => $numberStandard->standard_name,
        'input_type_snapshot' => 'number',
        'target_value_snapshot' => 50,
        'unit_snapshot' => 'C',
        'number_value' => 50,
        'is_warning' => false,
        'note' => 'Catatan baseline',
    ]);
    $notification = PrimeNotification::query()->create([
        'notification_type' => 'pm_waiting_review',
        'title' => 'RI-012 waiting review',
        'message' => 'RI-012 '.$scenario,
        'target_role' => 'admin',
        'related_table' => 'pm_executions',
        'related_id' => $execution->id,
        'target_url' => '/pm/review',
    ]);

    return [
        'location' => $location->id,
        'machine' => $machine->id,
        'admin' => $admin->id,
        'operator' => $operator->id,
        'checksheet' => $checksheet->id,
        'checksheet_machine' => $checksheetMachine->id,
        'part' => $part->id,
        'action_standard' => $actionStandard->id,
        'number_standard' => $numberStandard->id,
        'schedule' => $schedule->id,
        'schedule_date' => $scheduleDate->id,
        'execution' => $execution->id,
        'action_item' => $actionItem->id,
        'number_item' => $numberItem->id,
        'notification' => $notification->id,
        'updated_submitted_at' => $now->copy()->subMinutes(7)->toDateTimeString(),
        'baseline' => snapshotFixture([
            'execution' => $execution->id,
            'schedule_date' => $scheduleDate->id,
            'action_item' => $actionItem->id,
            'number_item' => $numberItem->id,
            'notification' => $notification->id,
        ]),
    ];
}

/** @return array<string,mixed> */
function snapshotFixture(array $fixture): array
{
    $execution = PmExecution::query()->findOrFail($fixture['execution']);
    $actionItem = PmExecutionItem::query()->findOrFail($fixture['action_item']);
    $numberItem = PmExecutionItem::query()->findOrFail($fixture['number_item']);

    return [
        'execution' => [
            'machine_code_snapshot' => $execution->machine_code_snapshot,
            'machine_name_snapshot' => $execution->machine_name_snapshot,
            'location_code_snapshot' => $execution->location_code_snapshot,
            'location_name_snapshot' => $execution->location_name_snapshot,
            'status' => $execution->status,
            'started_at' => $execution->started_at?->toDateTimeString(),
            'submitted_at' => $execution->submitted_at?->toDateTimeString(),
            'approved_at' => $execution->approved_at?->toDateTimeString(),
            'approved_by' => $execution->approved_by,
            'approved_by_name_snapshot' => $execution->approved_by_name_snapshot,
            'review_note' => $execution->review_note,
        ],
        'action_item' => $actionItem->only(['action_value', 'number_value', 'is_warning', 'warning_message', 'note']),
        'number_item' => $numberItem->only(['action_value', 'number_value', 'is_warning', 'warning_message', 'note']),
        'schedule_status' => (string) PmScheduleDate::query()->findOrFail($fixture['schedule_date'])->status,
        'history_count' => PmExecutionHistory::query()->where('pm_execution_id', $fixture['execution'])->count(),
        'activity_count' => DB::table('user_activity_logs')->where('table_name', 'pm_executions')->where('record_id', $fixture['execution'])->count(),
        'notification_count' => PrimeNotification::query()->whereKey($fixture['notification'])->count(),
    ];
}

function assertApprovedState(array $fixture, string $label): void
{
    $execution = PmExecution::query()->findOrFail($fixture['execution']);
    assertSame('approved', $execution->status, $label.' execution lifecycle tidak approved.');
    assertSame((int) $fixture['admin'], (int) $execution->approved_by, $label.' approval metadata salah.');
    assertSame('approved', (string) PmScheduleDate::query()->findOrFail($fixture['schedule_date'])->status, $label.' occurrence lifecycle tidak approved.');
    assertSame(1, PmExecutionHistory::query()->where('pm_execution_id', $fixture['execution'])->where('field_name', 'Status PM')->count(), $label.' approval history salah.');
    assertSame(1, DB::table('user_activity_logs')->where('table_name', 'pm_executions')->where('record_id', $fixture['execution'])->where('action', 'approve')->count(), $label.' approval activity salah.');
    assertSame(0, PrimeNotification::query()->whereKey($fixture['notification'])->count(), $label.' notification waiting review belum dihapus.');
}

function assertNoStaleMutation(array $fixture, array $baseline, string $label, string $approvalReviewNote, string $staleUpdateReviewNote): void
{
    $current = snapshotFixture([
        'execution' => $fixture['execution'],
        'schedule_date' => $fixture['schedule_date'],
        'action_item' => $fixture['action_item'],
        'number_item' => $fixture['number_item'],
        'notification' => $fixture['notification'],
    ]);
    assertSame($baseline['execution']['machine_code_snapshot'], $current['execution']['machine_code_snapshot'], $label.' stale update mengubah snapshot code.');
    assertSame($baseline['execution']['machine_name_snapshot'], $current['execution']['machine_name_snapshot'], $label.' stale update mengubah snapshot name.');
    assertSame($baseline['execution']['location_code_snapshot'], $current['execution']['location_code_snapshot'], $label.' stale update mengubah snapshot location code.');
    assertSame($baseline['execution']['location_name_snapshot'], $current['execution']['location_name_snapshot'], $label.' stale update mengubah snapshot location name.');
    assertSame($baseline['execution']['submitted_at'], $current['execution']['submitted_at'], $label.' stale update mengubah submitted_at.');
    // Approval boleh menulis review_note; oracle ini memastikan nilai legal tersebut
    // tetap utuh dan tidak pernah digantikan oleh payload update stale.
    assertSame($approvalReviewNote, $current['execution']['review_note'], $label.' review_note approval tidak sesuai.');
    assertNotSame($staleUpdateReviewNote, $current['execution']['review_note'], $label.' stale update mengubah review_note.');
    assertSame($baseline['action_item'], $current['action_item'], $label.' stale update mengubah action item.');
    assertSame($baseline['number_item'], $current['number_item'], $label.' stale update mengubah number item.');
    assertSame(1, $current['history_count'], $label.' memiliki history update stale.');
    assertSame(1, $current['activity_count'], $label.' memiliki activity update stale.');
}

function assertUpdateThenApprovalState(array $fixture, string $label): void
{
    $execution = PmExecution::query()->findOrFail($fixture['execution']);
    $actionItem = PmExecutionItem::query()->findOrFail($fixture['action_item']);
    $numberItem = PmExecutionItem::query()->findOrFail($fixture['number_item']);
    assertSame('approved', $execution->status, $label.' execution lifecycle tidak approved.');
    assertSame($fixture['updated_submitted_at'], $execution->submitted_at?->toDateTimeString(), $label.' submitted_at update hilang.');
    assertSame('Review note R3', $execution->review_note, $label.' review note update hilang.');
    assertSame('LUBRIKASI', $actionItem->action_value, $label.' action value update hilang.');
    assertSame('55.00', number_format((float) $numberItem->number_value, 2, '.', ''), $label.' number value update hilang.');
    assertSame(true, (bool) $numberItem->is_warning, $label.' warning hasil update hilang.');
    assertSame('Catatan R3', $actionItem->note, $label.' part note action hilang.');
    assertSame('Catatan R3', $numberItem->note, $label.' part note number hilang.');
    assertSame('approved', (string) PmScheduleDate::query()->findOrFail($fixture['schedule_date'])->status, $label.' occurrence lifecycle tidak approved.');
    $history = PmExecutionHistory::query()->where('pm_execution_id', $fixture['execution'])->orderBy('id')->get(['id', 'field_name']);
    assertSame(6, $history->count(), $label.' jumlah history tidak sesuai.');
    assertSame('Status PM', $history->last()->field_name, $label.' approval history bukan event terakhir.');
    assertSame(5, $history->where('field_name', '!=', 'Status PM')->count(), $label.' update history tidak lengkap.');
    assertSame(1, DB::table('user_activity_logs')->where('table_name', 'pm_executions')->where('record_id', $fixture['execution'])->where('action', 'update')->count(), $label.' update activity tidak ada.');
    assertSame(1, DB::table('user_activity_logs')->where('table_name', 'pm_executions')->where('record_id', $fixture['execution'])->where('action', 'approve')->count(), $label.' approval activity tidak ada.');
    assertSame(0, PrimeNotification::query()->whereKey($fixture['notification'])->count(), $label.' notification belum dihapus.');
}

function updatePayload(array $fixture): array
{
    return [
        'items' => [
            $fixture['action_item'] => ['action_value' => 'LUBRIKASI'],
            $fixture['number_item'] => ['number_value' => '55'],
        ],
        'part_notes' => [$fixture['part'] => 'Catatan R3'],
        'submitted_at' => date('Y-m-d\\TH:i', strtotime($fixture['updated_submitted_at'])),
        'change_note' => 'RI-012 update concurrency',
        'review_note' => 'Review note R3',
    ];
}

function reviewRequest(int $executionId, string $method): Request
{
    // Service activity log membutuhkan session store walau worker tidak
    // menjalankan HTTP kernel secara penuh.
    $request = Request::create('/pm/review/'.$executionId, $method, []);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

function listenForExecutionLock(string $directory, string $marker): void
{
    DB::listen(static function (QueryExecuted $query) use ($directory, $marker): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'pm_executions') && str_contains($sql, 'for update')) {
            writeMarker($directory, $marker);
        }
    });
}

function prepareScenarioDirectory(string $directory, string $scenario): string
{
    $scenarioDirectory = $directory.'/'.$scenario;
    if (! mkdir($scenarioDirectory, 0777, true) && ! is_dir($scenarioDirectory)) {
        throw new RuntimeException('Tidak dapat membuat direktori skenario.');
    }

    return $scenarioDirectory;
}

/** @return resource */
function startWorker(string $scenario, string $role, string $directory, string $fixturePath)
{
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        __FILE__,
        'worker',
        $scenario,
        $role,
        $directory,
        $fixturePath,
    ]));
    $stdoutPath = $directory.'/'.$role.'.stdout.log';
    $stderrPath = $directory.'/'.$role.'.stderr.log';
    $process = proc_open($command, [
        0 => ['file', 'NUL', 'r'],
        1 => ['file', $stdoutPath, 'w'],
        2 => ['file', $stderrPath, 'w'],
    ], $pipes, dirname(__DIR__, 3));

    if (! is_resource($process)) {
        throw new RuntimeException('Tidak dapat memulai worker RI-012.');
    }

    return $process;
}

function waitForWorker($process, string $directory, string $role): array
{
    $exitCode = proc_close($process);
    $resultPath = $directory.'/'.$role.'.result.json';
    $errorPath = $directory.'/'.$role.'.error.json';
    if ($exitCode !== 0) {
        $error = is_file($errorPath) ? (string) file_get_contents($errorPath) : 'worker exit '.$exitCode;
        throw new RuntimeException('Worker '.$role.' gagal: '.$error);
    }
    if (! is_file($resultPath)) {
        throw new RuntimeException('Worker '.$role.' selesai tanpa result.');
    }

    return json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
}

function waitForMarker(string $directory, string $marker): void
{
    $path = $directory.'/'.$marker;
    $deadline = microtime(true) + RI012_TIMEOUT_SECONDS;
    while (! is_file($path)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timeout menunggu marker '.$marker.'.');
        }
        usleep(50_000);
    }
}
function waitForExecutionLockWait(int $executionId): void
{
    $connection = DB::connection('mysql');
    $deadline = microtime(true) + RI012_TIMEOUT_SECONDS;
    while (true) {
        // Performance Schema mengamati request lock aktual pada koneksi worker,
        // bukan menebak overlap dari elapsed time atau delay tetap.
        $waitCount = (int) ($connection->selectOne(
            'SELECT COUNT(*) AS wait_count '
           .'FROM performance_schema.data_lock_waits AS waits '
           .'JOIN performance_schema.data_locks AS requesting '
            .'ON requesting.ENGINE_LOCK_ID = waits.REQUESTING_ENGINE_LOCK_ID '
           .'WHERE requesting.OBJECT_SCHEMA = DATABASE() '
           .'AND requesting.OBJECT_NAME = ? '
           .'AND requesting.LOCK_DATA = ? '
           .'AND requesting.LOCK_STATUS = ?',
            ['pm_executions', (string) $executionId, 'WAITING'],
        )->wait_count ?? 0);
        if ($waitCount > 0) {
            return;
        }
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timeout mengamati execution lock wait '.$executionId.'.');
        }
        usleep(50_000);
    }
}

function writeMarker(string $directory, string $marker): void
{
    file_put_contents($directory.'/'.$marker, sprintf('%.6f', microtime(true)), LOCK_EX);
}

function writeJson(string $path, array $payload): void
{
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
}

function assertNoDeadlockOrTimeout(array $result, string $label): void
{
    $encoded = strtolower(json_encode($result, JSON_THROW_ON_ERROR));
    if (str_contains($encoded, '1213') || str_contains($encoded, 'lock wait timeout') || str_contains($encoded, '1205')) {
        throw new RuntimeException($label.' mengalami deadlock atau lock timeout.');
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}
function assertNotSame(mixed $unexpected, mixed $actual, string $message): void
{
    if ($unexpected === $actual) {
        throw new RuntimeException($message.' unexpected='.var_export($unexpected, true));
    }
}

function printScenarioSummary(string $scenario, array $result): void
{
    echo $scenario.'_workers='.json_encode(array_map(static fn (array $worker): string => (string) ($worker['outcome'] ?? 'missing'), $result), JSON_THROW_ON_ERROR).PHP_EOL;
}

/** @param array<string,array<string,mixed>> $fixtures */
function cleanupFixtures(array $fixtures): array
{
    assertSafeMySqlConnection();
    $ids = static fn (string $key): array => array_values(array_map(static fn (array $fixture): int => (int) $fixture[$key], $fixtures));
    $executionIds = $ids('execution');
    $notificationIds = $ids('notification');
    $userIds = array_merge($ids('admin'), $ids('operator'));

    // Cleanup memakai ID yang ditangkap saat insert dan urutan child-to-parent;
    // tidak ada wildcard yang dapat menyentuh data ambient.
    DB::table('notification_reads')->whereIn('notification_id', $notificationIds)->delete();
    DB::table('notifications')->whereIn('id', $notificationIds)->delete();
    DB::table('user_activity_logs')->where('table_name', 'pm_executions')->whereIn('record_id', $executionIds)->delete();
    DB::table('pm_execution_history')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_execution_media')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_execution_items')->whereIn('pm_execution_id', $executionIds)->delete();
    DB::table('pm_executions')->whereIn('id', $executionIds)->delete();
    DB::table('pm_schedule_dates')->whereIn('id', $ids('schedule_date'))->delete();
    DB::table('pm_schedules')->whereIn('id', $ids('schedule'))->delete();
    DB::table('pm_checksheet_standards')->whereIn('id', array_merge($ids('action_standard'), $ids('number_standard')))->delete();
    DB::table('pm_checksheet_parts')->whereIn('id', $ids('part'))->delete();
    DB::table('pm_checksheet_machines')->whereIn('id', $ids('checksheet_machine'))->delete();
    DB::table('pm_checksheets')->whereIn('id', $ids('checksheet'))->delete();
    DB::table('machines')->whereIn('id', $ids('machine'))->delete();
    DB::table('locations')->whereIn('id', $ids('location'))->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();

    return [
        'notifications' => DB::table('notifications')->whereIn('id', $notificationIds)->count(),
        'activity_logs' => DB::table('user_activity_logs')->where('table_name', 'pm_executions')->whereIn('record_id', $executionIds)->count(),
        'history' => DB::table('pm_execution_history')->whereIn('pm_execution_id', $executionIds)->count(),
        'media' => DB::table('pm_execution_media')->whereIn('pm_execution_id', $executionIds)->count(),
        'items' => DB::table('pm_execution_items')->whereIn('pm_execution_id', $executionIds)->count(),
        'executions' => DB::table('pm_executions')->whereIn('id', $executionIds)->count(),
        'schedule_dates' => DB::table('pm_schedule_dates')->whereIn('id', $ids('schedule_date'))->count(),
        'schedules' => DB::table('pm_schedules')->whereIn('id', $ids('schedule'))->count(),
        'standards' => DB::table('pm_checksheet_standards')->whereIn('id', array_merge($ids('action_standard'), $ids('number_standard')))->count(),
        'parts' => DB::table('pm_checksheet_parts')->whereIn('id', $ids('part'))->count(),
        'checksheet_machines' => DB::table('pm_checksheet_machines')->whereIn('id', $ids('checksheet_machine'))->count(),
        'checksheets' => DB::table('pm_checksheets')->whereIn('id', $ids('checksheet'))->count(),
        'machines' => DB::table('machines')->whereIn('id', $ids('machine'))->count(),
        'locations' => DB::table('locations')->whereIn('id', $ids('location'))->count(),
        'users' => DB::table('users')->whereIn('id', $userIds)->count(),
    ];
}
