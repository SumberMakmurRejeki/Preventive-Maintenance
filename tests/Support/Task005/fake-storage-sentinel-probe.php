<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

// Probe TASK-005: probe sentinel dua peran untuk mekanisme pembersihan
// fake disk public Laravel.
//
// Urutan wajib (ditegakkan oleh sinyal berkas dari shell):
//   A: bootstrap -> resolve root -> Storage::fake('public') -> buat sentinel
//      -> signal READY -> tunggu DONE -> cek ulang keberadaan sentinel.
//   B: tunggu READY SEBELUM bootstrap -> bootstrap -> Storage::fake('public')
//      -> signal DONE.
//
// Penggunaan: php fake-storage-sentinel-probe.php <A|B> <token|''> '' <ready> <done>
require dirname(__DIR__, 3).'/vendor/autoload.php';

$basePath = dirname(__DIR__, 3);
$role = $argv[1] ?? '';
$token = $argv[2] ?? '';
$readyFile = $argv[4] ?? '';
$doneFile = $argv[5] ?? '';

// R4/R5 harus berjalan dengan konfigurasi test, setara phpunit.xml.
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['APP_MAINTENANCE_DRIVER'] = 'file';
$_SERVER['BCRYPT_ROUNDS'] = '4';
$_SERVER['CACHE_STORE'] = 'array';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';
$_SERVER['MAIL_MAILER'] = 'array';
$_SERVER['PULSE_ENABLED'] = 'false';
$_SERVER['QUEUE_CONNECTION'] = 'sync';
$_SERVER['SESSION_DRIVER'] = 'array';
$_SERVER['TELESCOPE_ENABLED'] = 'false';

// ParallelTesting::token() hanya membaca $_SERVER['TEST_TOKEN']; set/clear
// sebelum bootstrap agar nilai tidak diwarisi dari proses launcher.
if ($token === '') {
    unset($_SERVER['TEST_TOKEN']);
} else {
    $_SERVER['TEST_TOKEN'] = $token;
}

if ($role !== 'A' && $role !== 'B') {
    fwrite(STDERR, "unknown probe role\n");
    exit(64);
}

// Proses B wajib menunggu sinyal READY sebelum bootstrap Laravel, sehingga
// urutan A bootstrap/READY -> B bootstrap/fake -> A after-check terjamin.
if ($role === 'B') {
    $deadline = time() + 120;
    while (! is_file($readyFile)) {
        if (time() >= $deadline) {
            fwrite(STDERR, "B timed out waiting for READY\n");
            exit(75);
        }
        usleep(2000);
    }
}

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$frameworkToken = ParallelTesting::token() ?: '';
$root = $basePath.'/storage/framework/testing/disks/public';
if ($frameworkToken !== '') {
    $root .= '_test_'.$frameworkToken;
}
$meta = [
    'pid' => getmypid(),
    'role' => $role,
    'requested_token' => $token,
    'framework_token' => $frameworkToken,
    'root' => $root,
];

if ($role === 'A') {
    // A: fake() membentuk root, lalu buat sentinel unik di dalam root tersebut.
    Storage::fake('public');
    $sentinelPath = $root.'/task005-sentinel-'.getmypid().'.txt';
    file_put_contents($sentinelPath, 'owner=A pid='.getmypid().PHP_EOL);
    $meta['sentinel'] = $sentinelPath;
    $meta['sentinel_exists_before_b'] = is_file($sentinelPath);
    echo json_encode($meta).PHP_EOL;
    file_put_contents($readyFile, $sentinelPath.PHP_EOL);
    $deadline = time() + 120;
    while (! is_file($doneFile)) {
        if (time() >= $deadline) {
            fwrite(STDERR, "A timed out waiting for DONE\n");
            exit(75);
        }
        usleep(2000);
    }
    // Check akhir oleh A: sentinel masih ada setelah B melakukan fake()?
    $meta['sentinel_exists_after_b'] = is_file($sentinelPath);
    // Setelah bukti akhir diambil, hapus sentinel milik probe ini sendiri
    // agar tidak ada artefak persisten di root bersama.
    @unlink($sentinelPath);
    // Assertion fail-closed. Mode diturunkan dari token: token kosong = baseline
    // (root bersama) sehingga sentinel WAJIB hilang; token terisi = tokenized
    // (root terpisah) sehingga sentinel WAJIB bertahan. Outcome yang berlawanan
    // harus exit non-nol agar R4/R5 tidak pernah lulus semu.
    // Semua assertion dievaluasi setelah DONE diterima, jadi proses B sudah
    // selesai dan tidak mungkin menggantung karena kegagalan di sini.
    $meta['mode'] = $token === '' ? 'baseline' : 'tokenized';
    $expectedAfter = $token !== '';
    $meta['expected_sentinel_exists_after_b'] = $expectedAfter;
    $failures = [];
    if ($meta['sentinel_exists_before_b'] !== true) {
        $failures[] = 'sentinel tidak terbentuk di root A sebelum B dijalankan';
    }
    if ($meta['sentinel_exists_after_b'] !== $expectedAfter) {
        $failures[] = sprintf(
            'sentinel_exists_after_b=%s bertentangan dengan mode %s (harus %s)',
            var_export($meta['sentinel_exists_after_b'], true),
            $meta['mode'],
            var_export($expectedAfter, true)
        );
    }
    $meta['assertions_failed'] = $failures;
    echo json_encode($meta).PHP_EOL;
    if ($failures !== []) {
        fwrite(STDERR, 'PROBE A ASSERTION FAILED: '.implode('; ', $failures)."\n");
        exit(1);
    }
    exit(0);
}

// B: setelah READY, jalankan mekanisme framework yang sedang diuji.
Storage::fake('public');
$meta['sentinel_from_ready_file'] = trim((string) @file_get_contents($readyFile));
$meta['sentinel_exists_after_b_fake'] = $meta['sentinel_from_ready_file'] !== '' && is_file($meta['sentinel_from_ready_file']);
// DONE ditulis lebih dulu agar proses A selalu terbebaskan, bahkan bila
// assertion B di bawah gagal.
file_put_contents($doneFile, (string) time().PHP_EOL);
// Assertion fail-closed dari sudut pandang B: baseline harus melihat sentinel A
// sudah hilang (root sama dibersihkan fake()), tokenized harus melihat sentinel
// A tetap ada karena root B terpisah.
$meta['mode'] = $token === '' ? 'baseline' : 'tokenized';
$expectedFromB = $token !== '';
$meta['expected_sentinel_exists_after_b_fake'] = $expectedFromB;
$failures = [];
if ($meta['sentinel_from_ready_file'] === '') {
    $failures[] = 'file READY tidak memuat path sentinel';
}
if ($meta['sentinel_exists_after_b_fake'] !== $expectedFromB) {
    $failures[] = sprintf(
        'sentinel_exists_after_b_fake=%s bertentangan dengan mode %s (harus %s)',
        var_export($meta['sentinel_exists_after_b_fake'], true),
        $meta['mode'],
        var_export($expectedFromB, true)
    );
}
$meta['assertions_failed'] = $failures;
echo json_encode($meta).PHP_EOL;
if ($failures !== []) {
    fwrite(STDERR, 'PROBE B ASSERTION FAILED: '.implode('; ', $failures)."\n");
    exit(1);
}
exit(0);
