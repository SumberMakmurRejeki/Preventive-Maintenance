# ADR-007 — Transaction Snapshot Strategy

**Status:** Implemented / Verified
**Implementation:** Slice A and Slice B complete; CLOSED / PASS / MANAGER ACCEPTED — 2026-09-09
**Tanggal:** 2026-08-17 (decision); closure synchronized 2026-09-09

## Context

Data master di PRIME dapat berubah seiring waktu.

Contohnya:

- nama mesin diperbaiki;
- nama part diubah;
- standard PM diperbarui;
- pilihan PM berubah;
- range minimum dan maksimum diubah;
- unit atau label diperbarui.

Jika historical transaction selalu membaca langsung dari master terbaru, maka transaksi lama dapat terlihat berbeda dari kondisi saat pekerjaan sebenarnya dilakukan.

Contoh:

PM dilakukan dengan standard:

`Temperatur Body: 50–150 °C`

Beberapa bulan kemudian master diubah menjadi:

`Temperatur Body: 40–160 °C`

Historical PM lama tetap harus menunjukkan bahwa saat pekerjaan dilakukan, standard yang berlaku adalah `50–150 °C`.

PRIME membutuhkan strategi yang menjaga historical truth tanpa membuat seluruh master menjadi immutable.

## Decision

PRIME menggunakan **transaction snapshot** untuk data yang diperlukan agar sebuah transaksi dapat dipahami sesuai kondisi pada saat transaksi terjadi.

Prinsipnya:

```text
Master
→ sumber konfigurasi saat ini

Transaction Snapshot
→ kondisi yang berlaku saat transaksi terjadi
```

Snapshot disimpan pada transaction atau transaction item yang relevan.

Untuk PM, snapshot dapat mencakup informasi seperti:

- machine identity/name yang relevan;
- part name;
- standard name;
- input type;
- option yang tersedia;
- selected option code dan label;
- target value;
- minimum dan maximum;
- unit.

Snapshot harus proporsional. Tidak semua field master perlu disalin, hanya data yang diperlukan untuk memahami, menampilkan, atau menganalisis transaksi secara historis.

Perubahan master setelah transaksi dibuat tidak boleh otomatis menulis ulang snapshot lama.

Stable identifier tetap disimpan jika berguna untuk menghubungkan data lintas waktu, tetapi historical display tidak boleh bergantung sepenuhnya pada kondisi master terbaru.

## ADR-007 Slice B Closure

Slice B implements atomic historical identity reads for PM Review and PM Report. A complete execution snapshot bundle is used for historical display; incomplete, null, blank, or whitespace bundles render `Data historis tidak tersedia (legacy)` instead of mixing current master values.

Browser proof covered a representative normal PM transaction, mutable Machine-name and Location-name changes, current-master search/filter context, historical desktop and mobile rendering, historical Detail rendering, one canonical execution, and zero console errors or warnings. `machine_code` is immutable under the current PRIME contract; no machine-code rename proof is claimed.

Slice B verification: focused PM Review, PM Report, and PM Executor regression passed with 100 tests and 694 assertions. The Slice B source commit is `c531d5c`; accepted local UAT bootstrap tooling is committed separately as `5ccb5f8`.

## Alternatives Considered

### Selalu membaca data dari master terbaru

Tidak dipilih karena historical transaction dapat berubah makna setelah master diperbarui.

### Membuat seluruh master immutable

Tidak dipilih karena master tetap perlu dapat dikoreksi dan dikembangkan untuk kebutuhan operasional berikutnya.

### Menyalin seluruh record master ke setiap transaksi

Tidak dipilih karena menghasilkan duplikasi berlebihan dan menyimpan data yang tidak relevan.

## Consequences

### Positif

- historical PM tetap merepresentasikan kondisi saat pekerjaan dilakukan;
- perubahan master tidak merusak report lama;
- transaksi tetap dapat dipahami walaupun master kemudian berubah atau dinonaktifkan;
- analytics lintas waktu lebih dapat dipercaya.

### Trade-off

- sebagian data akan terduplikasi;
- application logic perlu menentukan kapan snapshot dibuat;
- schema transaction menjadi sedikit lebih besar;
- perubahan struktur snapshot perlu dikelola dengan hati-hati.

## Invariants

- perubahan master tidak mengubah snapshot transaksi lama;
- approved dan historical transaction tetap dapat dipahami tanpa bergantung pada master terbaru;
- snapshot hanya menyimpan data yang relevan terhadap historical meaning;
- stable code/identifier dipertahankan jika diperlukan untuk analytics;
- snapshot tidak digunakan sebagai pengganti master untuk konfigurasi pekerjaan baru.

## Out of Scope

ADR ini tidak menentukan:

- nama kolom snapshot final;
- format JSON atau relational final;
- migration database;
- strategi versioning schema;
- retention policy;
- implementasi correction workflow.

## Related Decisions

- `ADR-001-live-vs-historical-pm.md`
- `ADR-002-pm-option-catalog.md`
- `ADR-003-preserve-historical-transactions.md`
- `ADR-004-one-execution-per-occurrence.md`
- `ADR-006-schedule-reconciliation-protected-occurrences.md`
