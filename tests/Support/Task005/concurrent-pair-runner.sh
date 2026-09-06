#!/usr/bin/env bash
# TASK-005 — Bounded test-only concurrent pair runner (R3 baseline / R5 tokenized).
#
# Menjalankan dua proses PHP test yang independen secara SEBENARNYA paralel
# (bukan satu proses), lalu mencatat bukti per proses:
#   - nomor repetisi, label proses, PID, perintah, TEST_TOKEN, root fake yang
#     ter-resolve (dihitung dari aturan framework), exit code, stdout/stderr,
#   - daftar direktori storage/framework/testing/disks sebelum/sesudah setiap pair.
#
# MODE=baseline  : tanpa TEST_TOKEN (dipastikan unset) — root fake publik bersama.
# MODE=tokenized : setiap proses mendapat TEST_TOKEN unik yang di-set SEBELUM
#                  bootstrap Laravel (melalui environment proses) — root fake terpisah.
#
# Penggunaan:
#   bash tests/Support/Task005/concurrent-pair-runner.sh <evidence-dir> <reps> <baseline|tokenized>
#
# Script ini hanya untuk test-infrastructure TASK-005. Tidak menyentuh kode produksi.

set -u

# Argumen wajib ditangani sebelum parameter positional dibaca agar pemanggilan
# tanpa argumen menghasilkan usage yang stabil, bukan unbound-variable error.
if [ "$#" -ne 3 ]; then
  echo "usage: concurrent-pair-runner.sh <evidence-dir> <positive-reps> <baseline|tokenized>" >&2
  exit 64
fi

EV="$1"
REPS="$2"
MODE="$3"
# Validasi jumlah repetisi: batch kosong atau nilai non-numerik tidak boleh
# terlihat sukses karena tidak pernah menjalankan pasangan proses.
case "$REPS" in
  ''|*[!0-9]*)
    echo "usage: concurrent-pair-runner.sh <evidence-dir> <positive-reps> <baseline|tokenized>" >&2
    exit 64
    ;;
esac
NORMALIZED_REPS=$(printf '%s' "$REPS" | sed 's/^0*//')
if [ -z "$NORMALIZED_REPS" ]; then
  echo "usage: concurrent-pair-runner.sh <evidence-dir> <positive-reps> <baseline|tokenized>" >&2
  exit 64
fi
REPS="$NORMALIZED_REPS"

# Validasi mode: hanya baseline|tokenized yang legal; mode salah adalah usage
# error (exit 64) agar tidak ada fallback diam-diam ke shared-root baseline.
if [ "$MODE" != "baseline" ] && [ "$MODE" != "tokenized" ]; then
  echo "usage: concurrent-pair-runner.sh <evidence-dir> <reps> <baseline|tokenized>" >&2
  exit 64
fi


DISKS_REL="storage/framework/testing/disks"
mkdir -p "$EV"

# Akumulator kegagalan: exit code non-nol proses anak harus tercermin pada
# exit status runner, sehingga batch gagal tidak terlihat sukses.
FAILED_PAIRS=0

# Setiap invocation memakai subdirektori batch unik (pid + epoch) sehingga dua
# pemanggilan konkuren pada evidence-dir sama tidak saling menimpa file batch.
BATCHDIR="$EV/batch-$$-$(date +%s)"
mkdir -p "$BATCHDIR"

# Snapshot daftar direktori fake disk sebelum seluruh batch dimulai (bukti residual awal).
ls -la "$DISKS_REL" 2>/dev/null > "$BATCHDIR/batch-start-disks.txt" || echo "dir tidak ada sebelum batch" > "$BATCHDIR/batch-start-disks.txt"

for rep in $(seq 1 "$REPS"); do
  runid="run-$(date +%s)-rep${rep}-$$"
  pairdir="$BATCHDIR/$runid"
  mkdir -p "$pairdir"

  # Token unik per proses hanya pada mode tokenized; baseline memastikan TEST_TOKEN unset.
  if [ "$MODE" = "tokenized" ]; then
    TOKEN_A="A_${runid}"
    TOKEN_B="B_${runid}"
  else
    TOKEN_A=""
    TOKEN_B=""
  fi

  # Root fake yang di-resolve menurut aturan Storage::fake()
  # (vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:101-124):
  # tanpa token -> storage/framework/testing/disks/public
  # dengan token -> storage/framework/testing/disks/public_test_<token>
  if [ -n "$TOKEN_A" ]; then
    ROOT_A="$DISKS_REL/public_test_$TOKEN_A"
    ROOT_B="$DISKS_REL/public_test_$TOKEN_B"
  else
    ROOT_A="$DISKS_REL/public"
    ROOT_B="$DISKS_REL/public"
  fi

  # Snapshot kondisi fake-disk sebelum pair (bukti tidak ada dependensi antar run).
  ls -la "$DISKS_REL" 2>/dev/null > "$pairdir/disks-before.txt" || echo "dir tidak ada sebelum pair" > "$pairdir/disks-before.txt"

  start=$(date +%s)
  # Proses A = test media executor (R1), Proses B = test proteksi review (R2).
  # Kedua proses dimulai secara konkuren; wait mengambil exit code masing-masing.
if [ -n "$TOKEN_A" ]; then
  # Post-fix R5/R6: launcher menerima token eksplisit sebelum bootstrap PHP.
  TEST_TOKEN="$TOKEN_A" bash tests/Support/Task005/isolated-test-process.sh artisan test \
    tests/Feature/Feature/PM/PmExecutorTest.php \
    --filter=test_http_delete_media_approved_redirect_branch_preserves_evidence \
    --compact --no-coverage > "$pairdir/A.out" 2>&1 &
else
  # R3 baseline tetap bypass launcher agar root shared dapat direproduksi.
  env -u TEST_TOKEN php artisan test tests/Feature/Feature/PM/PmExecutorTest.php \
    --filter=test_http_delete_media_approved_redirect_branch_preserves_evidence \
    --compact --no-coverage > "$pairdir/A.out" 2>&1 &
fi
pid_a=$!
if [ -n "$TOKEN_B" ]; then
  # Post-fix R5/R6: launcher kedua juga menerima token unik proses B.
  TEST_TOKEN="$TOKEN_B" bash tests/Support/Task005/isolated-test-process.sh artisan test \
    tests/Feature/Feature/PM/PmReviewTest.php \
    --filter=test_admin_cannot_delete_approved_execution \
    --compact --no-coverage > "$pairdir/B.out" 2>&1 &
else
  # R3 baseline tetap bypass launcher agar root shared dapat direproduksi.
  env -u TEST_TOKEN php artisan test tests/Feature/Feature/PM/PmReviewTest.php \
    --filter=test_admin_cannot_delete_approved_execution \
    --compact --no-coverage > "$pairdir/B.out" 2>&1 &
fi
pid_b=$!
  wait "$pid_a"
  exit_a=$?
  wait "$pid_b"
  exit_b=$?
  end=$(date +%s)

  # Catat kegagalan pair: exit code non-nol salah satu proses = pair gagal.
  if [ "$exit_a" -ne 0 ] || [ "$exit_b" -ne 0 ]; then
    FAILED_PAIRS=$((FAILED_PAIRS + 1))
  fi

  # Snapshot kondisi fake-disk sesudah pair (bukti root yang dipakai + sisa fixture).
  ls -la "$DISKS_REL" 2>/dev/null > "$pairdir/disks-after.txt" || echo "dir tidak ada sesudah pair" > "$pairdir/disks-after.txt"

  # Manifest teks per pair: seluruh field bukti yang diwajibkan TASK-005.
  {
    echo "repetition=$rep"
    echo "run_id=$runid"
    echo "mode=$MODE"
    echo "start_epoch=$start"
    echo "end_epoch=$end"
    echo "--- process A ---"
    echo "label=A-executor"
    echo "pid=$pid_a"
    echo "token=[$TOKEN_A]"
    echo "expected_fake_root=$ROOT_A"
    echo "command=TEST_TOKEN=\$TOKEN bash tests/Support/Task005/isolated-test-process.sh artisan test tests/Feature/Feature/PM/PmExecutorTest.php --filter=test_http_delete_media_approved_redirect_branch_preserves_evidence --compact --no-coverage (mode=tokenized) | php artisan test tests/Feature/Feature/PM/PmExecutorTest.php --filter=test_http_delete_media_approved_redirect_branch_preserves_evidence --compact --no-coverage (mode=baseline)"
    echo "exit=$exit_a"
    echo "result_tail:"
    tail -3 "$pairdir/A.out"
    echo "--- process B ---"
    echo "label=B-review"
    echo "pid=$pid_b"
    echo "token=[$TOKEN_B]"
    echo "expected_fake_root=$ROOT_B"
    echo "command=TEST_TOKEN=\$TOKEN bash tests/Support/Task005/isolated-test-process.sh artisan test tests/Feature/Feature/PM/PmReviewTest.php --filter=test_admin_cannot_delete_approved_execution --compact --no-coverage (mode=tokenized) | php artisan test tests/Feature/Feature/PM/PmReviewTest.php --filter=test_admin_cannot_delete_approved_execution --compact --no-coverage (mode=baseline)"
    echo "exit=$exit_b"
    echo "result_tail:"
    tail -3 "$pairdir/B.out"
  } > "$pairdir/manifest.txt"

  # Ringkas satu baris per pair untuk agregasi cepat.
  a_tail=$(tail -3 "$pairdir/A.out" | grep -E 'Tests:|Duration' | tr '\n' ' ')
  b_tail=$(tail -3 "$pairdir/B.out" | grep -E 'Tests:|Duration' | tr '\n' ' ')
  echo "rep=$rep pid_a=$pid_a pid_b=$pid_b exit_a=$exit_a exit_b=$exit_b | A: $a_tail | B: $b_tail"
  # Hapus tepat root terisolasi milik pair ini agar tidak ada fake-storage
  # leakage persisten (snapshot disks-after sudah dicatat di pairdir).
  if [ -n "$TOKEN_A" ]; then
    rm -rf "$DISKS_REL/public_test_$TOKEN_A"
    rm -rf "$DISKS_REL/public_test_$TOKEN_B"
  fi
done

ls -la "$DISKS_REL" 2>/dev/null > "$BATCHDIR/batch-end-disks.txt" || echo "dir tidak ada sesudah batch" > "$BATCHDIR/batch-end-disks.txt"
echo "DONE mode=$MODE reps=$REPS failed_pairs=$FAILED_PAIRS ev=$BATCHDIR"

# Exit status runner harus mencerminkan kegagalan proses anak (bukan selalu 0),
# agar batch yang gagal tidak terlihat sukses bagi pemanggil/CI.
if [ "$FAILED_PAIRS" -ne 0 ]; then
  exit 1
fi
exit 0
