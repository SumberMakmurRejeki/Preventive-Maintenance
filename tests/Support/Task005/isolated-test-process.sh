#!/usr/bin/env bash
# TASK-005 — Isolated test process launcher (test-only, pre-bootstrap).
#
# Menjalankan SATU proses test PHP dengan TEST_TOKEN unik yang ditetapkan
# SEBELUM bootstrap Laravel. Ini mekanisme isolasi Storage::fake() paling
# sempit dan memakai jalur resmi framework:
#   - ParallelTesting::token() membaca $_SERVER['TEST_TOKEN']
#     (vendor/laravel/framework/src/Illuminate/Testing/ParallelTesting.php:297-301);
#   - Storage::fake() memakai token untuk root fake terpisah
#     storage/framework/testing/disks/public_test_<token>
#     (vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:101-124).
#
# Akibatnya setiap proses test konkuren membersihkan dan menulis root miliknya
# sendiri, sehingga Storage::fake() di satu proses tidak dapat menghapus
# fixture proses lain — mekanisme kegagalan yang dibuktikan probe R4 TASK-005.
#
# Tidak mengubah kode produksi, phpunit.xml, berkas test, konfigurasi
# filesystem, atau perilaku aplikasi. Murni test-infrastructure dan opt-in:
# perintah test serial yang sudah ada tetap berjalan seperti sebelumnya.
#
# Jika TEST_TOKEN sudah diset environment (mis. `php artisan test --parallel`
# atau orchestrator yang menetapkan token eksplisit), nilai tersebut
# DIPERTAHANKAN dan root-nya tidak dihapus oleh script ini.
#
# Penggunaan:
#   bash tests/Support/Task005/isolated-test-process.sh artisan test tests/Feature/Feature/PM/PmExecutorTest.php --filter=<nama-test>

set -u

OWN_TOKEN=0
if [ -z "${TEST_TOKEN:-}" ]; then
  # Token unik per proses: pid launcher + waktu nanodetik + nilai acak.
  TEST_TOKEN="task005-$$-$(date +%s%N)-$RANDOM"
  export TEST_TOKEN
  OWN_TOKEN=1
fi

php "$@"
status=$?

# Bersihkan root fake milik token yang dibuat script ini agar direktori
# public_test_<token> tidak menumpuk. Token bawaan environment tidak disentuh.
if [ "$OWN_TOKEN" -eq 1 ]; then
  ISOLATED_ROOT="storage/framework/testing/disks/public_test_${TEST_TOKEN}"
  if [ -d "$ISOLATED_ROOT" ]; then
    rm -rf "$ISOLATED_ROOT"
  fi
fi

exit "$status"
