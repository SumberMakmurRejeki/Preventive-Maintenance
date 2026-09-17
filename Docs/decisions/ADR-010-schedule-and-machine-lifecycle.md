# ADR-010 — Schedule dan Machine Lifecycle

**Status:** IMPLEMENTATION IN PROGRESS / SLICES A-B ACCEPTED / SLICE C CLOSED / NOT DONE
**Implementation:** TASK-006 assigned.
- **Slices A-B:** PUBLISHED.
- **Slice C:** IMPLEMENTED / VERIFIED / CORRECTIONS 7/7 SATISFIED /
  ANOMALY RCA CLOSED / MANAGER ACCEPTED / ENGINEERING COMMITTED /
  PUBLISHED / CLOSED / PASS.
- **Engineering FINAL:** `503a1c5eb9a011b7537636337304d7fec0879c3e`.
- **Publication:** COMPLETE.
- **Slices D-E:** NOT STARTED / NOT AUTHORIZED.
**Tanggal:** 2026-08-19

## Context

Eligibility PM tidak hanya ditentukan oleh tanggal occurrence. Schedule dan machine dapat dihentikan sementara atau permanen. Jika state tersebut tidak dibedakan, PRIME dapat membuat pekerjaan baru ketika aset tidak operasional, membuat overdue/missed selama periode pause, atau menghidupkan kembali schedule lama yang seharusnya sudah menjadi histori.

Schedule juga harus tetap dibedakan dari machine. Satu checksheet dapat digunakan beberapa machine, dan retirement satu machine tidak boleh mengakhiri konfigurasi machine lain.

## Decision

### Schedule lifecycle

Schedule memiliki state bisnis:

```text
ACTIVE → PAUSED → ACTIVE
ACTIVE/PAUSED → ENDED
```

`PAUSED` berarti penghentian sementara:

- tidak ada PM live actionable;
- tidak ada overdue/missed untuk periode pause;
- resume tidak membuat catch-up backlog;
- state dapat dilanjutkan.

`ENDED` adalah terminal pada normal workflow:

- tidak dapat di-resume sebagai schedule lama;
- kebutuhan PM baru menggunakan schedule baru;
- history schedule lama dipertahankan.

### Machine lifecycle

Machine memiliki state:

```text
ACTIVE → INACTIVE → ACTIVE
ACTIVE/INACTIVE → RETIRED
```

Live PM actionable hanya boleh dibuat ketika:

```text
Machine ACTIVE AND Schedule ACTIVE
```

`INACTIVE` menghentikan eligibility secara efektif tanpa mengubah stored schedule menjadi `PAUSED`. Manual `PAUSED` tetap `PAUSED`, dan `ENDED` tetap `ENDED`. Resume machine tidak membuat catch-up backlog.

`RETIRED` menghentikan PM live baru. Schedule ACTIVE atau PAUSED milik machine tersebut diakhiri dengan reason retirement. History PM, breakdown, dan schedule tidak dihapus. Recommission machine tidak menghidupkan schedule lama yang sudah `ENDED`; admin membuat schedule baru melalui workflow yang diaudit.

Master checksheet tidak ikut retired atau inactive secara global.

### Execution yang sudah dimulai

Jika Machine dan Schedule masih `ACTIVE` saat canonical execution menjadi
`IN_PROGRESS`, lifecycle setelahnya hanya memblokir start baru. Execution itu
tetap dapat melewati `IN_PROGRESS → WAITING_REVIEW → APPROVED` menurut validasi
dan review biasa. Lifecycle tidak boleh mengubah kebenaran transaksi tersebut.

### Mapping legacy dan compatibility

Backfill legacy wajib deterministik:

| Entity | `is_active = true` | `is_active = false` |
| --- | --- | --- |
| Machine | `ACTIVE` | `INACTIVE` |
| PmSchedule | `ACTIVE` | `PAUSED` |

`false` tidak pernah membuktikan `RETIRED` atau `ENDED`. `is_active` dapat
bertahan sementara sebagai compatibility projection, tetapi bukan source of
truth lifecycle jangka panjang. Selama periode compatibility, setiap production
writer yang masih boleh mengubah `is_active` wajib menyimpan projection
non-terminal yang sama pada `lifecycle_status` dalam operasi persist yang sama:
Machine `true/false → active/inactive`; Schedule `true/false → active/paused`.
Legacy writer tidak boleh menulis terminal state.

### Effective-live boundary

`PAUSED → ACTIVE` dan `INACTIVE → ACTIVE` harus menyimpan boundary baru pada
`BusinessDate::today()` (Asia/Jakarta). Boundary ini mencegah catch-up backlog.
`operational_from` ADR-009 tetap provenance dan tidak didefinisikan ulang.

Boundary berlaku pada dua operasi yang berbeda:

1. **Materialisasi occurrence baru** tetap memulai dengan ADR-009
   `max(operational_from, BusinessDate::today())`, lalu mempertahankan
   `start_date` yang lebih baru sebagaimana `PlanningPeriodPolicy` saat ini.
   `Machine.effective_live_from` dan `PmSchedule.effective_live_from`, bila ada,
   adalah lower bound tambahan. `operational_from = NULL` tetap fail closed.
2. **Eligibility occurrence mutable yang sudah ada** memakai lower bound
   persisted dari provenance schedule dan effective-live boundary yang berlaku;
   tidak memakai `BusinessDate::today()` sebagai batas bergerak. Occurrence
   mutable sebelum boundary tetap tersimpan namun non-live/suppressed.

Dengan demikian occurrence valid sesudah resume dapat aging normal pada hari
berikutnya, sedangkan canonical execution `IN_PROGRESS` bukan occurrence mutable
dan tetap dapat melanjutkan review/approval. Klasifikasi KPI/reporting untuk
occurrence suppressed didefer ke reporting policy / Phase 9.

### Recommission

`RETIRED → ACTIVE` adalah transisi ordinary yang dilarang dan harus fail closed.
Recommission adalah business action eksplisit: machine memperoleh eligibility
operasional dan boundary baru, schedule baru dibuat untuk era baru, sedangkan
schedule lama yang `ENDED` dan histori terkait tetap dipertahankan.

### Read-side

Executor, actionable QR, live queue, overdue/missed processing, dan notification
eligibility hanya boleh memproses pekerjaan yang live. Waiting historical truth,
approved execution, snapshot, dan audit evidence harus tetap dapat dibaca.

## Consequences

- Eligibility dapat dijelaskan tanpa menghapus histori.
- Pause manual dapat dibedakan dari machine yang sementara inactive.
- Retirement satu machine tidak mengubah machine lain pada checksheet yang sama.
- Lifecycle implementation, audit, dan suppression occurrence memerlukan task terpisah dari TASK-002.

## Related Decisions

- `ADR-001-live-vs-historical-pm.md`
- `ADR-003-preserve-historical-transactions.md`
- `ADR-005-pm-lifecycle-and-status-policy.md`
- `ADR-006-schedule-reconciliation-protected-occurrences.md`
- `ADR-009-live-schedule-operational-window-and-planning.md`
