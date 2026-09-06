#!/usr/bin/env bash
# TASK-005 — R4/R5 sentinel probe orchestrator (test-only).
#
# Menjalankan dua proses PHP probe secara tersinkronisasi:
#   A: bootstrap -> resolve root -> Storage::fake('public') -> buat sentinel
#      -> signal READY -> tunggu -> cek sentinel akhir.
#   B: tunggu READY -> bootstrap -> Storage::fake('public') -> signal DONE.
#
# MODE=baseline : kedua proses tanpa TEST_TOKEN (harus root sama).
# MODE=tokenized : A dan B mendapat TEST_TOKEN unik sebelum bootstrap (root berbeda).
#
# Penggunaan:
#   bash tests/Support/Task005/fake-storage-probe-runner.sh <evidence-dir> <baseline|tokenized>

set -u

# Argumen wajib ditangani sebelum parameter positional dibaca agar pemanggilan
# tanpa argumen menghasilkan usage yang stabil, bukan unbound-variable error.
if [ "$#" -ne 2 ]; then
  echo "usage: fake-storage-probe-runner.sh <evidence-dir> <baseline|tokenized>" >&2
  exit 64
fi

EV="$1"
MODE="$2"
PROBE="tests/Support/Task005/fake-storage-sentinel-probe.php"
DISKS_REL="storage/framework/testing/disks"
mkdir -p "$EV"

# Validasi mode: selain baseline|tokenized adalah usage error (exit 64),
# bukan fallback diam-diam ke baseline shared-root.
if [ "$MODE" != "baseline" ] && [ "$MODE" != "tokenized" ]; then
  echo "usage: fake-storage-probe-runner.sh <evidence-dir> <baseline|tokenized>" >&2
  exit 64
fi

# Setiap invocation memakai run-dir unik (epoch-nano + pid) agar dua pemanggilan
# konkuren tidak saling menimpa barrier READY/DONE maupun file bukti.
RUNDIR="$EV/run-$(date +%s%N)-$$"
mkdir -p "$RUNDIR"
ls -la "$DISKS_REL" 2>/dev/null > "$RUNDIR/disks-before.txt" || echo "dir tidak ada" > "$RUNDIR/disks-before.txt"

if [ "$MODE" = "tokenized" ]; then
  RUNTAG="p$(date +%s%N)-$$"
  TOKEN_A="A_${RUNTAG}"
  TOKEN_B="B_${RUNTAG}"
else
  RUNTAG="none"
  TOKEN_A=""
  TOKEN_B=""
fi
start=$(date +%s)
# A jalan di latar; B menunggu READY sehingga urutan A->B->A terjamin.
php "$PROBE" A "$TOKEN_A" "" "$RUNDIR/ready.txt" "$RUNDIR/done.txt" > "$RUNDIR/A.out" 2>&1 &
pid_a=$!
php "$PROBE" B "$TOKEN_B" "" "$RUNDIR/ready.txt" "$RUNDIR/done.txt" > "$RUNDIR/B.out" 2>&1 &
pid_b=$!
wait "$pid_a"
exit_a=$?
wait "$pid_b"
exit_b=$?
end=$(date +%s)

ls -la "$DISKS_REL" 2>/dev/null > "$RUNDIR/disks-after.txt" || echo "dir tidak ada" > "$RUNDIR/disks-after.txt"

{
  echo "mode=$MODE"
  echo "run_tag=${RUNTAG:-none}"
  echo "start_epoch=$start"
  echo "end_epoch=$end"
  echo "pid_a=$pid_a"
  echo "pid_b=$pid_b"
  echo "token_a=[$TOKEN_A]"
  echo "token_b=[$TOKEN_B]"
  echo "exit_a=$exit_a"
  echo "exit_b=$exit_b"
  echo "--- A meta lines ---"
  cat "$RUNDIR/A.out"
  echo "--- B meta line ---"
  cat "$RUNDIR/B.out"
} > "$RUNDIR/manifest.txt"

# Hapus artefak tepat milik probe agar acceptance criterion no leakage terpenuhi.
# Manifest/meta sudah disalin ke Temp sebelum cleanup ini.
if [ "$MODE" = "tokenized" ]; then
  rm -rf "$DISKS_REL/public_test_$TOKEN_A"
  rm -rf "$DISKS_REL/public_test_$TOKEN_B"
else
  SENTINEL_PATH=$(grep -o '"sentinel":"[^"]*"' "$RUNDIR/A.out" | head -1 | sed 's/"sentinel":"//; s/"$//')
  if [ -n "$SENTINEL_PATH" ] && [ -f "$SENTINEL_PATH" ]; then
    rm -f "$SENTINEL_PATH"
  fi
fi
ls -la "$DISKS_REL" 2>/dev/null > "$RUNDIR/disks-after-cleanup.txt" || echo "dir tidak ada" > "$RUNDIR/disks-after-cleanup.txt"

cat "$RUNDIR/manifest.txt"
echo "DONE mode=$MODE ev=$RUNDIR"

# Exit status runner harus mencerminkan kegagalan proses anak (probe kini
# juga exit non-nol bila outcome sentinel berlawanan dengan mode-nya).
if [ "$exit_a" -ne 0 ] || [ "$exit_b" -ne 0 ]; then
  exit 1
fi
exit 0
