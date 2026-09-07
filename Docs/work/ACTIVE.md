# Active Work

**Last Updated:** 2026-09-08

**Roadmap Phase:** Phase 2 — Historical Truth & Transaction Snapshot Foundation
**Authoritative Roadmap:** `Docs/roadmap.md`
**Current Task:** ADR-007 Slice B — Historical Read-Side Consistency — bounded analysis / planning
**Planning Document:** `Docs/roadmap.md` (canonical current position)
**Previous Task:** ADR-007 Slice A — Transaction Identity Snapshots (**CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED**)
**Status:** Phase 2 **ACTIVE**; ADR-007 Slice A **CLOSED / PASS / MANAGER ACCEPTED**; ADR-007 Slice B **NEXT / PLANNING ONLY**; Slice B implementation **NOT AUTHORIZED**

## Current ADR Assessment

Architecture survey dan impact analysis current-source selesai pada 2026-09-01.

### Confirmed P0

1. **ADR-004 — One Canonical Execution per Occurrence**
   - `PmExecutionService::startExecutionOnly()` remains the single production writer method, reached through start, submit, and media upload; Slice 1 parent-lock behavior and canonical collision handling are implemented.
   - Canonical execution creation is serialized by the Machine → PmScheduleDate → PmExecution lock order.
   - `pm_executions.pm_schedule_date_id` is `bigint unsigned NOT NULL`; ordinary index is preserved and named unique index `pm_executions_pm_schedule_date_id_unique` is applied.
   - The database uniqueness backstop now rejects concurrent duplicate inserts; accepted Slice 2 verification proves final count **1**.
   - Duplicate preflight includes soft-deleted executions and found 0 duplicate groups; no historical rewrite was performed.
   - Current schema still does not support `pm_schedule_date_id = NULL`; manual/historical execution remains outside this task.
2. **Database decision**
   - **DB UNIQUE CONSTRAINT REQUIRED.**
   - Application parent locking memberi deterministic reuse/rejection; named unique index menjadi final invariant backstop.
   - Duplicate preflight harus mencakup soft-deleted rows dan fail closed ke human data decision.
3. **Lock decision**
   - Direct `PmScheduleDate → PmExecution` locking akan inversion dengan current submit/approve `Execution → ScheduleDate`.
   - Safe partial order: `Machine → PmScheduleDate → PmExecution → items/media`.
   - `PmReviewService::approve()` harus mengikuti parent-first order; media/review-update paths yang tidak mengambil parent tetap tidak diubah.

### Existing Decisions Preserved

- TASK-003 / ADR-003: **CLOSED / PASS — IMPLEMENTED / VERIFIED**.
- ADR-005 lifecycle behavior: implemented + verified; no lifecycle redesign.
- ADR-006 protected occurrence reconciliation: implemented + verified; no reconciliation redesign.
- Authorization, routes, frontend, Breakdown, media lifecycle, Machine delete, checksheet historical protection, dan Report tidak dibuka kembali.

## Current Status

`ADR-004 ACCEPTED — IMPLEMENTED + VERIFIED — SLICES 1, 2, AND 3 COMPLETE — MANAGER ACCEPTED`

`TASK-004 CLOSED / MANAGER ACCEPTED — Slice 1 ACCEPTED; Slice 2 ACCEPTED; Slice 3 COMPLETE — ACCEPTED`

`TASK-005 CLOSED / PASS — MANAGER ACCEPTED — shared-root cleanup mechanism PROVEN; tokenized isolation PROVEN EFFECTIVE; production causal defect NOT ESTABLISHED; historical exact causality NOT CONCLUSIVELY PROVEN`

Final implementation sequence:

1. Slice 1 — application canonical serialization, lifecycle/idempotency contract, canonical relation, dan approve lock reorder.
2. Slice 2 — fail-closed duplicate preflight dan named database unique constraint.
3. Slice 3 — two-connection MySQL R1/R2/R3 proof dan independent closure.

R4 NULL/manual control tidak berlaku karena current FK adalah NOT NULL.

## Current Handoff

**Next Action:** `ADR-007 Slice B bounded current-reader assessment / implementation planning`

**Implementation status:** ADR-007 Slice A delivered transaction-level snapshots for machine code/name, location code/name, and checksheet code/name while retaining existing provenance foreign keys. Slice A passed implementation verification, SQLite regression, MySQL rehearsal, real Laravel Migrator verification, independent review, and manager acceptance.

**Slice-A code checkpoint:** Commit `8f47e05d955b5f2a50a49228bfd90c40bcb8f893` (`feat(pm): snapshot execution transaction identity`) is committed and pushed to `origin/develop`.

**Canonical roadmap synchronization:** `Docs/roadmap.md` is authoritative. Phase 2 remains active. ADR-007 Slice A is closed; Slice B is the next planning gate. No Slice B implementation is authorized.

### Current closure checkpoint

ADR-007 Slice A: **CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED — 2026-09-07**.

ADR-007 overall remains **PARTIAL / IN PROGRESS**. Historical read-side consistency, reviewer/history provenance work, and explicit finalized correction policy remain open.

PR4 remains **HOLD / NON-BLOCKING**. C11 / P2 remains **HOLD / NOT AUTHORIZED**.

### Current Git checkpoint

Published Slice-A code checkpoint: `develop` / `8f47e05d955b5f2a50a49228bfd90c40bcb8f893` / staged files `0`. This SHA is the parent code checkpoint for the documentation/state synchronization commit.

<!-- Historical checkpoint (pre-Slice-A): Phase 1 exit gate CLOSED / EXIT GATE PASS / MANAGER ACCEPTED — 2026-09-07; Git checkpoint d541d8d8ba46a12e60d77d506e4dadc38b22d5cf; no push performed at that time. -->

### Current Slice 2 Verification and Closure — 2026-09-02/03

- Migration: `database/migrations/2026_09_02_203946_add_canonical_execution_unique_index.php`.
- Schema invariant: `pm_schedule_date_id` adalah `bigint unsigned NOT NULL`; named unique index `pm_executions_pm_schedule_date_id_unique` hadir; ordinary index `pm_executions_pm_schedule_date_id_index` dan FK tetap dipertahankan.
- Duplicate preflight mencakup soft-deleted rows: **PASS**, 0 duplicate group; tidak ada cleanup atau historical rewrite.
- Migration apply, rollback, dan reapply: **PASS**; R3 direct duplicate insert: **PASS** (`SQLSTATE 23000`, MySQL `1062`), final count **1**, cleanup residual **0**.
- Real Laravel migrator/deployment path pada `db_preventive_maintenance_test`: **PASS**; migration tracking row hadir dan status **Ran**.
- Original independent-review migrator finding telah diselesaikan. Bounded independent re-review: **PASS — READY FOR MANAGER ACCEPTANCE**; manager/user acceptance: **Slice 2 ACCEPTED**.
- Media/fake-storage RCA tidak menemukan causal Slice 2 production defect. Full suite evidence tetap **317 passed / 3 failed / 1453 assertions**; tiga failure adalah known/unrelated baseline failures.
- Main/local database tidak dimutasi.

### Separate Test-Infrastructure Issue — RESOLVED via TASK-005 (2026-09-04)

- Fake-storage concurrency issue: **CLOSED / PASS — MANAGER ACCEPTED** melalui TASK-005 (test-infrastructure isolation).
- Shared `Storage::fake('public')` root cleanup mechanism: **PROVEN** deterministic (R4 sentinel probe, tanpa PHPUnit). `TEST_TOKEN` tokenized root isolation: **PROVEN EFFECTIVE** (R5 probe + final 20-pair tokenized run; zero fake-storage leakage).
- Production causal defect: **NOT ESTABLISHED**; TASK-004 Slice 2 causal defect: **NOT ESTABLISHED**; historical exact named-test causality: **NOT CONCLUSIVELY PROVEN** (residual uncertainty — bukan production defect).
- Evidence: `Docs/work/session-reports/2026-09-04-PRIME-TASK-005-fake-storage-implementation-verification.md` dan `Docs/work/session-reports/2026-09-04-PRIME-TASK-005-manager-acceptance-closure.md`; bug artifact dan registry di-update **resolved/closed** (2026-09-04).
### Historical Slice 1 Correction Evidence — 2026-09-01

- `PmExecutorTest`: **43 passed / 215 assertions** (termasuk 7 regression test baru untuk collision classification dan media-part ownership).
- Related suites: `PmReviewTest` **15 / 83**; `PmScheduleDateReconcilerTest` **8 / 38**; `SyncPmScheduleStatusCommandTest` **12 / 39**; `MachineLandingTest` **9 / 27** — total **44 passed / 187 assertions**.
- RED proof: memaksa classifier mengembalikan `true` membuat test unrelated-violation gagal; marker dihapus setelahnya.
- Scoped Pint pada enam file yang berubah: **PASS**; scoped `git diff --check`: **CLEAN**; `php -l`: bersih.
- MySQL R1/R2 rerun pasca-correction: orchestrator conn 2347; R1 conn 2348/2349 execution **900205729**; R2 conn 2350/2351 execution **900205730**; overlap terbukti; final count **1** per skenario; exit codes 0/0; tanpa 1213/timeout; cleanup residual **0**.
- Preflight read-only pada `db_preventive_maintenance_test`: **0 duplicate group**, **0 executions**, named unique index **belum ada**.
- Staged checkpoint SHA-256 tetap `258a434dc633e5fc049be2c99587ccea5c1237f0266d4e2cb5c81705398f85c3`; tidak ada migration Slice 2 dibuat.
- Risiko tersisa: `PmExecutionService.php` (674 baris) dan `PmReviewService.php` (443 baris) masih di atas preferensi 300 baris; media file write masih terjadi di dalam DB transaction (CONSIDER, belum diperbaiki).


**Mandatory stop conditions:**

- duplicate preflight menemukan row;
- active schema bertentangan dengan migration evidence;
- lock inversion tidak dapat dihilangkan;
- implementation mengubah ownership/authorization atau manual/historical PM boundary;
- existing staged/dirty checkpoint terubah.

Planning evidence, impact matrix, test matrix, migration plan, dan MySQL proof design berada pada TASK-004 planning document.

## Historical checkpoints

The prior Slice 1–4 evidence below remains unchanged.

### Previous Slice 5 checkpoint

The 2026-08-30 partial-verification note is superseded by the 2026-08-31 correction evidence above; Slice 5 remains open pending independent review.

### Historical checkpoints

- Slice 2 — **CLOSED / PASS — INDEPENDENT REVIEW VERIFIED**.
- Bounded correction applied on 2026-08-24:
  - Authorization test expectation restored to `assertRedirect('/403')`.
  - Edit-validation test Referer setup restored with `->from(...)`.
  - Physical file preservation assertions added to OPEN and CLOSED protection tests.
  - Pint formatting resolved on all three changed files.
- Verification: **26 passed, 1 failed** (known baseline: BreakdownClose empty-state copy mismatch).
- Classification: BreakdownClose is **NON-CAUSAL / KNOWN BASELINE**; all Slice 2 regressions corrected.
- Protection command: **5 passed (41 assertions)**; source review: **RACE SAFE**.
- Pint: **PASS**; scoped `git diff --check`: **PASS**.
- Browser: **NOT REQUIRED**; source and HTTP feature tests prove the Slice 2 contract.
- RCA evidence: `specs/bugs/BUG-TASK-003-SLICE-2.md`; verification artifact: `specs/verifications/AUDIT-TASK-003-SLICE-2.md`.
- Historical Slice 2 checkpoint: next action was preparing Slice 3; current Slice 3 status is recorded above.
- TASK-003 overall dan ADR-003 remain **OPEN**; Slice 4 remains **NOT STARTED**.

### TASK-003 Slice 3 Data-Safety Design Checkpoint

- Retry proof on 2026-08-26: harness self-test **3/3 PASS**; C1 **3/3 PASS**; C2 **3/3 PASS**; C3 rollback **PASS**; C4 six controlled C1/C2 repetitions **PASS**.
- MySQL 1213 count: **0**; synchronization timeout: **0**; orphan count: **0**; protected-history loss: **0**.
- Bounded multi-entity proof passed with Machine IDs ascending then Schedule IDs ascending.
- Protected occurrence control preserved Machine, occurrence, execution, and QR path with zero destructive mutation.
- Verification artifact: `specs/verifications/TASK-003-SLICE-3-DATA-SAFETY-VERIFY.yaml`.
- Cleanup caveat: initial failed multi-proof revision used broad `S3M%` checksheet cleanup without a pre-run ambient baseline; current matching fixture counts are zero, but ambient-row impact cannot be proven retrospectively. Temporary script was corrected to exact captured IDs.

### TASK-003 Slice 3 Implementation Checkpoint

- Slice 3 — **VERIFIED — DIRECT RECONCILER MYSQL RUNTIME PASS**. The implementation details below are retained as the implementation checkpoint; current-session runtime verification is summarized under "Current Slice 3 Runtime Evidence — 2026-08-27" above.
- Implemented on 2026-08-26:
  - `MachineService::delete()`: protected predicate checks Breakdown (incl soft-deleted), PmExecution (incl soft-deleted), protected lifecycle occurrences (in_progress/waiting_review/approved, occurrences with execution/history, soft-deleted protected evidence). Mutable occurrences (scheduled/overdue/missed without execution) remain deletable. Configuration-only Machine (assignment/parts/standards/schedule but no protected history) remains hard-deletable.
  - `PmScheduleDateReconciler::evaluate()`: lock ordering Machine IDs ascending → Schedule IDs ascending → ScheduleDate IDs ascending.
  - `PmChecksheetService::reconcileScheduleDates()`: lock ordering Machine IDs ascending → Schedule IDs ascending. Machine ID query filtered to `whereHas('schedules', active())` to avoid excessive locking.
  - `PmScheduleUpdateService::apply()`: lock ordering Machine IDs ascending → Schedule IDs ascending. Machine ID query covers ALL assigned machines (not just active schedules) because `persistScheduleConfig()` can create/activate schedules for assignments without active schedules.
  - Canonical rejection copy: `"Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus."`
- Test evidence:
  - `MasterMesinTest`: 31 passed (protected delete scenarios A-G including repeated rejection, authorization, QR cleanup).
  - `PmChecksheetScheduleUpdatePreviewApplyTest`: 25 passed (including regression test for assignment without active schedule — verifies upsert behavior, not concurrency).
  - `PmScheduleDateReconcilerTest`: 7 passed (lock ordering, protected date preservation, conflict detection).
  - Total: **63 passed (246 assertions)**.
- Lock ordering applied to all four causal writers to prevent deadlock with MachineService::delete().
- Current-session runtime proof (2026-08-27) — see "Current Slice 3 Runtime Evidence — 2026-08-27" above for full detail:
  - R1 reconciler-first vs Machine delete: **PASS**.
  - R2 Machine holder-first vs direct reconciler: **PASS**.
  - R3 bounded multi-record ascending lock order via `PmChecksheetService::reconcileScheduleDates()`: **PASS**.
  - Protected-history control (delete rejected, no loss): **PASS**.
  - Aggregate recomputed from SQL: MySQL 1213 **0**, synchronization timeout **0**, orphan **0**, invalid child commit **0**, protected-history loss **0**.
  - Exact fixture cleanup: residuals **0**; no `HSLICE3` rows remain.
  - Regression: **95 passed / 430 assertions**. Scoped Pint **PASS**. Scoped `git diff --check` **PASS**.
- No FK/migration changes. No BreakdownService/PmExecutionService changes.
- Final post-remediation independent review (2026-08-28): **PASS — TASK-003 SLICE 3 POST-REMEDIATION INDEPENDENT REVIEW CONFIRMED**.
  - Final evidence:
    - JavaScript security tests: **7 passed / 0 failed**.
    - Slice-3 Laravel regression: **95 passed / 430 assertions**.
    - Full Laravel suite: **261 passed / 1178 assertions**, with 3 known non-causal baseline failures.
    - Frontend build: **PASS**.
    - Scoped Pint: **PASS**.
    - Scoped git diff --check: **PASS**.
    - Persisted R1/R2/R3 evidence remains valid; re-execution not required.
  - Slice 3: **CLOSED / PASS — INDEPENDENT REVIEW VERIFIED**.
  - TASK-003: **OPEN**.
  - ADR-003: **OPEN**.
  - Slice 4: **NOT STARTED**.
  - Next action: **PREPARE TASK-003 SLICE 4 — PM CHECKSHEET HISTORICAL DELETE PROTECTION — DO NOT IMPLEMENT YET**.

TASK-002 Slice 2 — Schedule Update Safety: **CORRECTED AFTER INDEPENDENT REVIEW — Automated verification PASS**.

TASK-002 Slice 3 — Defensive Compatibility + Admin UX: **CLOSED / PASS — FINAL 8-SCENARIO BROWSER EVIDENCE PASS**.

### Final Browser Evidence — 2026-08-24

Pre-flight passed on `:8127`: PHP CLI/web runtime 8.3.7, current compiled frontend loaded, isolated Admin authenticated, and browser console baseline had no blocking errors. Fixture IDs: Admin `7`, location `6`, machine `8`, checksheets `13–20`, assignments `13–20`, parts `14–21`, schedules `13–20`, schedule dates `13–20`, execution `5` and `6`.

**Scenario 1 — Same-count stale + persistent business notice: PASS.** Date `13` changed from `scheduled` to `in_progress` without changing the one-row count. Old Apply returned HTTP `409`; automatic fresh preview returned HTTP `200`; DB remained one `in_progress` row and zero executions. Settled browser state retained the exact stale/refresh business notice and authoritative impact table. Screenshot: `.uat-playwright-temp/task002-s1-stale-notice.png`.

**Scenario 2 — Execution-state stale: PASS.** Date `14` changed to `in_progress` and execution `6` was created as `in_progress` without changing the row count. Old Apply returned HTTP `409`; fresh preview returned HTTP `200`; DB retained the date and execution unchanged. Screenshot: `.uat-playwright-temp/task002-s2-execution-stale.png`.

**Scenario 3 — Legacy NULL manual reconciliation: PASS.** `/pm/master-checksheet/15` showed the unresolved legacy operational-window warning, did not offer ordinary synchronization, and did not create new occurrences. DB retained `operational_from=NULL`, approved date `15`, and approved execution `5`. Screenshot: `.uat-playwright-temp/task002-s3-legacy-null.png`.

**Scenario 4 — Malformed Apply response: PASS.** Apply-only HTML interception returned HTTP `200`; the page showed `Penerapan jadwal tidak dapat dimuat. Silakan coba kembali.` with no raw HTML, parser error, stack trace, success state, or mutation. Date `16` remained `scheduled` with zero executions; console errors were zero. Screenshot: `.uat-playwright-temp/task002-s4-malformed-apply.png`.

**Scenario 5 — Cross-timezone date-only: PASS.** Stored date `2026-09-03` rendered `03/09/2026` in separate contexts whose runtime zones were verified as `Asia/Jakarta` and `America/Los_Angeles`; neither rendered `02/09/2026` or `04/09/2026`. Screenshots: `.uat-playwright-temp/task002-s5-asia-jakarta.png` and `.uat-playwright-temp/task002-s5-america-los-angeles.png`.

**Scenario 6 — Invalid preview token: PASS.** Controlled invalid-token Apply returned HTTP `409`, followed by HTTP `200` fresh preview. Browser showed only business-facing stale/refresh copy; date `18` remained `scheduled` with zero executions.

**Scenario 7 — Network error: PASS.** Controlled preview network abort showed `Pratinjau jadwal tidak dapat dimuat. Silakan coba kembali.`; the only console entry was the expected `ERR_FAILED` resource log, with no raw JavaScript error or false success. Date `19` remained `scheduled` with zero executions. Screenshot: `.uat-playwright-temp/task002-s7-network-error.png`.

**Scenario 8 — Apply without valid preview: PASS.** Preview response was controlled to omit its token. The Apply click produced no Apply request, no success state, and no mutation; date `20` remained `scheduled` with zero executions.

Exact fixture cleanup completed FK-safely. Verification returned zero for the temporary Admin, location, machine, checksheets, assignments, parts, standards, schedules, dates, and executions. Temporary browser/password artifacts were removed.

### Browser Gate Result

All eight current scenarios passed against the current source/build. No stale Apply bypass, history overwrite, partial mutation, or authorization bypass was observed.

Automated verification remains PASS: `PmChecksheetScheduleUpdatePreviewApplyTest` **24 passed / 72 assertions**; `MasterPmChecksheetOperationalWindowTest` **10 passed / 34 assertions**; `DatabaseConfigDeprecationTest` **2 passed / 6 assertions**; related PM regression **29 passed / 131 assertions**; `npm run build` PASS.


### Slice 3 verification evidence

- Focused preview/apply suite (contract fix): **21 passed / 69 assertions** in `tests/Feature/Feature/Master/PmChecksheetScheduleUpdatePreviewApplyTest.php` (includes new `test_preview_and_apply_accept_wizard_payload_without_master_identity`).
- Operational-window regression suite: **9 passed / 31 assertions** in `tests/Feature/Feature/Master/MasterPmChecksheetOperationalWindowTest.php`.
- `vendor/bin/pint` + `git diff --check` (scoped): **PASS** on changed files.
- Real-browser UAT on `:8127` after contract fix:
  - Legacy-NULL schedule (stored `operational_from` null) edit wizard step 6 → settled **amber unresolved panel** ("Jadwal ini belum memiliki tanggal awal operasional (legacy)…"), preview HTTP 200 `{status:unresolved, unresolved:true, token:'', counts:0}`, apply button hidden+disabled, Simpan Perubahan visible.
  - Dated fixture (`operational_from=2026-09-01`) edit wizard step 6 → **green ready-to-apply panel**, created-date counts listed, apply button visible+enabled.
  - Screenshots: `C:\Users\USER\AppData\Local\Temp\omp-sshots-15607400c89e9409.webp` (step 6 loading), `...15607407ff5e940a.webp` (settled amber + impact table + apply hidden), `...15607428009e940b.webp` (green panel + enabled apply).

### Slice 3 focused correction (2026-08-21)

Defect A — PHP 8.5 deprecation mencemari JSON response:
- Root cause: `config/database.php` menggunakan `PDO::MYSQL_ATTR_SSL_CA` yang deprecated di PHP 8.5, menghasilkan deprecation notice HTML yang mengawali JSON response.
- Fix: compatibility conditional `class_exists('Pdo\\Mysql') ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA` pada mysql dan mariadb connection.
- Regression test: `tests/Feature/Feature/Master/DatabaseConfigDeprecationTest.php` (2 passed / 6 assertions).

Defect B — frontend menampilkan raw JSON parse error:
- Root cause: `runServerPreview()` dan apply handler memanggil `response.json()` tanpa defensive handling; malformed response (HTML/deprecation) menyebabkan `SyntaxError` bocor ke Admin.
- Fix: helper `safeParseJson()` memeriksa `content-type` header dan menangkap parse failure; kedua handler menampilkan pesan bisnis stabil ("Pratinjau jadwal tidak dapat dimuat. Silakan coba kembali." / "Penerapan jadwal gagal. Silakan coba kembali.") dan memblokir mutation jika response tidak valid.
- Frontend build: `npm run build` PASS.

Verification:
- `php artisan test --filter="PmChecksheetScheduleUpdatePreviewApplyTest"` → 21 passed / 69 assertions.
- `php artisan test --filter="MasterPmChecksheetOperationalWindowTest"` → 9 passed / 31 assertions.
- `php artisan test --filter="DatabaseConfigDeprecationTest"` → 2 passed / 6 assertions.
- `php artisan test` (full suite) → 223 passed / 993 assertions; 3 pre-existing unrelated failures (AuthenticationFlowTest, DashboardMonitoringTest, BreakdownCloseTest).
- `vendor/bin/pint` PASS; `npm run build` PASS.

### Historical 8-scenario UAT — superseded for current gate

The prior 8-scenario browser UAT remains historical evidence for the pre-correction implementation state. It does not close the current post-review browser gate.

### Slice 3 contract fix (found via UAT)

The step-6 server preview/apply endpoints originally validated through `UpdatePmChecksheetRequest`, which REQUIRES `checksheet_code`/`checksheet_name` — the wizard's `collectPayload()` only sends `selected_machine_ids/parts/standards/schedule`, so every edit returned HTTP 422 "Kode checksheet wajib diisi." and the amber legacy panel could never render. Fix: dedicated `app/Http/Requests/Master/PreviewScheduleUpdateRequest.php` (admin `authorize()`, validates only `wizard_payload` + schedule rules incl. nullable `operational_from`, and the past-date business check with unchanged-value exemption, skipping when NULL so legacy unresolved passes). `previewScheduleUpdate()`/`applyScheduleUpdate()` now type-hint it. `PmScheduleUpdateService`/`PmChecksheetService` kept their pre-existing handling; controller passes `$request->input('wizard_payload.schedule')`. Normal PUT create/update validation unchanged.

## Slice 3 Files

### Created
- `app/Http/Requests/Master/PreviewScheduleUpdateRequest.php`

### Modified
- `app/Http/Controllers/Master/MasterPmChecksheetController.php` (preview/apply now use `PreviewScheduleUpdateRequest`)
- `tests/Feature/Feature/Master/PmChecksheetScheduleUpdatePreviewApplyTest.php` (postPreview/postApply now send wizard_payload only)

## Next Step

TASK-002 telah final closed.

Lanjutkan ke project-level ADR gap review untuk ADR-001 sampai ADR-010 guna menentukan:
- implementation status;
- automated-test coverage;
- browser/UAT coverage;
- blocker;
- next action;
- priority.

Jangan membuka kembali TASK-002 kecuali ditemukan regression baru yang memiliki causal evidence terhadap contract TASK-002.

Required browser gates are complete:

1. stale rejection and persistent stale/refresh business message after same-count occurrence state change;
2. execution-state stale rejection without schedule-date row-count change;
3. legacy NULL manual reconciliation reports unresolved correctly;
4. malformed Apply response uses Apply-specific business fallback;
5. deterministic date-only rendering under `Asia/Jakarta` and `America/Los_Angeles`;
6. invalid preview token cannot Apply;
7. network failure shows stable business-facing error;
8. Apply without a valid preview is blocked.

## Blocker

No TASK-002 blocker remains.

The prior HTTP 419, stale-message UX, and Playwright verification blockers are resolved.

Final independent close-out confirmed that TASK-002 business/domain/safety gates are satisfied. Generic audit-code hygiene findings related to the unrelated dirty working tree, `AGENTS.md` whitespace, function-length heuristic, and maintainability debt are non-blocking for TASK-002.

TASK-002 is FINAL CLOSED / PASS.

This status does not declare deployment or production readiness.

## Historical 8-Scenario Browser UAT — 2026-08-23

**Historical Result:** PASS for the earlier implementation state; not evidence that the current post-review targeted gate passes.

- Environment pre-flight: CLI/web PHP 8.3.7, Composer platform PASS, `ext-zip` enabled, server `:8000`.
- Correction regression: clean JSON preview PASS; malformed HTML response generated business fallback without raw `SyntaxError` and without Apply.
- Scenario 1 PASS: fixture `9102`, preview 53 created + 1 protected, Apply persisted 54 unique occurrences.
- Scenario 2 PASS: zero impact, created/removed `0`, no mutation.
- Scenario 3 PASS: fixture `9103`, legacy NULL unresolved; Apply unavailable; history retained 1 occurrence + 1 approved execution.
- Scenario 4 PASS historically: stale Apply HTTP `409`, DB unchanged, preview refresh, fresh Apply succeeded.
- Scenario 5 PASS: protected schedule date `9103` and approved execution `1` remained.
- Scenario 6 PASS: Back did not mutate; mutation occurred after preview ulang and Apply.
- Scenario 7 PASS: displayed `03/09/2026` matched stored `2026-09-03` after `Asia/Jakarta` normalization; no date shift.
- Scenario 8 PASS: fixture `9104`, schedules `9104`/`9105`; preview 106 created + 1 removed; persisted 53 unique occurrences per machine.
- Fixture cleanup verified exact historical UAT identifiers and related records at zero.
- Evidence screenshots: `uat-s1-review.png`, `uat-s2-zero.png`, `uat-regression-malformed-preview.png`, `uat-s3-legacy-null.png`, `uat-s4-preview-before-stale.png`, `uat-s4-stale-rejected.png`, `uat-s6-cancel.png`, `uat-s7-date-only.png`, `uat-s8-multi-review.png`.

The historical browser timezone context comparison was not run because the selected CLI session did not expose a timezone override; date-only acceptance still passed.


### Slice 3 Final Correction (2026-08-23)

Based on Final Independent Review findings:

**Correction A (HIGH) — Stale fingerprint serialization:**
- Root cause: `PmScheduleUpdateService::fingerprint()` had nested loop bug — `$dateData` built but never appended to `$scheduleData['dates']`; `$scheduleData` appended inside date loop (duplicated per date); `$machineData` appended inside schedule loop (duplicated per schedule).
- Fix: Corrected nesting — append `$dateData` to `$scheduleData['dates']` after execution loop, append `$scheduleData` to `$machineData['schedules']` after date loop, append `$machineData` to `$data['machines']` after schedule loop.
- Regression tests (3 new): `test_fingerprint_detects_status_change_without_row_count_change`, `test_fingerprint_detects_execution_addition_without_date_count_change`, `test_fingerprint_detects_execution_soft_delete_without_date_count_change` — all PASS.

**Correction B (MEDIUM/HIGH) — Legacy NULL manual reconciliation:**
- Root cause: When `operational_from = NULL`, reconciler returns all-zero counts → `has_changes = false` → view shows "Tidak ada perubahan jadwal yang dapat diterapkan." (zero-impact message) instead of reporting unresolved schedules.
- Fix: Added `unresolved` check in `PmChecksheetService::reconcileScheduleDates()` BEFORE `has_conflicts`/`has_changes` checks; throws `DomainException` with business message. Added `unresolved` alert in `show.blade.php` before `has_conflicts`/`has_changes` checks.
- Regression test (1 new): `test_manual_reconciliation_reports_unresolved_when_operational_from_null` — PASS.

**Correction C (minor) — Malformed Apply response copy:**
- Root cause: Apply handler's `safeParseJson` failure message said "Pratinjau jadwal tidak dapat dimuat" (preview copy) instead of apply-specific message.
- Fix: Changed to "Penerapan jadwal tidak dapat dimuat. Silakan coba kembali."

Verification after HTTP 419 RCA:**
- `PmChecksheetScheduleUpdatePreviewApplyTest` → **24 passed / 72 assertions**.
- `MasterPmChecksheetOperationalWindowTest` → **10 passed / 34 assertions**.
- `DatabaseConfigDeprecationTest` → **2 passed / 6 assertions**.
- Related PM regression → **29 passed / 131 assertions**.
- Full `php artisan test` → **227 passed / 999 assertions; 3 unrelated/pre-existing failures**.
- The new fingerprint and legacy manual-reconciliation corrections now pass through the normal HTTP/application test path.
- HTTP 419 verification blocker is RESOLVED.

**Files changed:**
- `app/Services/Master/PmScheduleUpdateService.php` (fingerprint nesting fix)
- `app/Services/Master/PmChecksheetService.php` (unresolved check in reconcile)
- `resources/views/pages/master/checksheet/show.blade.php` (unresolved alert)
- `resources/js/app.js` (apply error message)
- `tests/Feature/Feature/Master/PmChecksheetScheduleUpdatePreviewApplyTest.php` (3 fingerprint tests)
- `tests/Feature/Feature/Master/MasterPmChecksheetOperationalWindowTest.php` (1 reconciliation test)

**Status:** Corrections implemented and automated verification PASS after HTTP 419 resolution.
**Next Step:** Targeted Browser Regression.

# HTTP 419 RCA — TASK-002 HTTP Verification

## Phase 1 — Reproduce (2026-08-23)

- Baseline runtime: PHP 8.3.7, Laravel 12.60.2.
- `php artisan env` reported `local`.
- `php artisan about` reported config not cached; effective cache `database`, database `mysql`, session `database`.
- `php artisan test --filter="PmChecksheetScheduleUpdatePreviewApplyTest"` reproduced **21 failed / 3 passed**, with all HTTP preview/apply assertions receiving **419**. The first failing request is `POST /pm/master-checksheet/{id}/preview-schedule-update`; expected `200`, actual `419`.
- Cross-module control `php artisan test --filter="test_admin_login_redirects_to_dashboard"` reproduced **419** for `POST /login`; expected redirect, actual `419`.

## Phase 2 — Isolate

- Both failing routes are in the `web` middleware group. The TASK-002 route additionally uses `prime.auth`, `prime.operator.session`, and `prime.role:admin`; `/login` uses only `web` plus `prime.guest`.
- Laravel's `VerifyCsrfToken` returns HTTP 419 by throwing `TokenMismatchException`; its test bypass is conditional on `app->runningUnitTests()`.
- Current runtime inspection returned `app_env=local`, `running_unit_tests=false`, `session_driver=database`, `cache_store=database`, `db_connection=mysql`.
- A controlled test run with `APP_ENV=testing SESSION_DRIVER=array CACHE_STORE=array DB_CONNECTION=sqlite DB_DATABASE=:memory:` made the cross-module login test pass and the complete TASK-002 HTTP suite pass (**24 passed / 72 assertions**).
- Initial classification: test environment/bootstrap precedence, not TASK-002 business logic and not production middleware behavior. Final causal confirmation remains in Phase 4.
## Phase 3 — Hypothesize

Ranked hypotheses and falsification:

1. **PHPUnit environment overrides are not authoritative (highest).** Existing process variables from `.env` remain `APP_ENV=local`, so `<env>` entries in `phpunit.xml` do not switch the application into testing mode. Falsification: run the same tests with explicit testing variables; already falsified the competing product-regression explanation because both control and TASK-002 HTTP tests pass under the isolated test environment.
2. **Stale framework/config cache.** Cached config could preserve local drivers. Falsification: `php artisan about` reports config/routes/events not cached, and `bootstrap/cache/` has no config cache artifact. Not supported.
3. **Custom PRIME authentication/authorization middleware.** Falsification: `/login` fails before custom authenticated/role middleware applies, and its `web` route still returns 419. Not supported.
4. **TASK-002 regression in preview/apply code.** Falsification: the same 24-test HTTP suite passes under the correct test environment, while the three service-level fingerprint tests pass even under the contaminated baseline. Not supported.

The remaining hypothesis is a test harness environment-precedence defect: PHPUnit declares testing values, but the existing environment wins because the declarations are not forced.
## Phase 4 — Verify

### Confirmed root cause

The test process inherited local environment values, including `$_SERVER['APP_ENV']=local`, from the developer environment. `phpunit.xml` previously declared only `<env>` values without forcing/overriding the server variables. PHPUnit's `force="true"` updates `getenv()`/`$_ENV`, but Laravel's `Env` repository reads the default `ServerConstAdapter` first; `$_SERVER['APP_ENV']` therefore remained `local`. Laravel booted with `app->runningUnitTests() === false`, so `VerifyCsrfToken` did not take its test bypass and threw `TokenMismatchException` for every POST without a CSRF token, yielding HTTP 419.

Falsification and confirmation:

- The same failing TASK-002 and `/login` POSTs pass when explicit testing values are supplied at process launch.
- `phpunit.xml` source in PHPUnit 11.5.55 confirms `force` applies to env variables, while server variables are populated separately.
- A temporary non-repository PHPUnit probe observed `getenv=testing`, `$_ENV=testing`, `$_SERVER=local` with env-only forcing; after adding server declarations it observed all three as `testing`.
- With the corrected harness, the original commands pass without command-line environment prefixes.

### Safe diagnostic correction

- Modified only `phpunit.xml`.
- Added forced `<env>` values and matching `<server>` values for the declared test environment (`APP_ENV`, SQLite in-memory database, array cache/session, and other existing test drivers).
- No application/business logic, CSRF exception, auth/authz middleware, or production configuration was changed.

### Reproduction after

- `php artisan test --filter="PmChecksheetScheduleUpdatePreviewApplyTest"` → **24 passed / 72 assertions**.
- `php artisan test --filter="test_admin_login_redirects_to_dashboard"` → **1 passed / 5 assertions**.
- Required suites:
  - `PmChecksheetScheduleUpdatePreviewApplyTest` → **24 passed / 72 assertions**.
  - `MasterPmChecksheetOperationalWindowTest` → **10 passed / 34 assertions**.
  - `DatabaseConfigDeprecationTest` → **2 passed / 6 assertions**.

### Verdict

**RESOLVED** — HTTP 419 was a test-environment/server-variable precedence defect. It was cross-module and did not originate in TASK-002.

### Related regression and full-suite classification

- Related PM regression (`PmChecksheetScheduleReconciliationTest|PmScheduleDateReconcilerTest|SyncPmScheduleStatusCommandTest`) → **29 passed / 131 assertions**.
- Full `php artisan test` → **227 passed / 999 assertions; 3 failed**.
- Remaining failures are the known unrelated/pre-existing `AuthenticationFlowTest::test_login_page_renders_staff_and_guest_access_options`, `DashboardMonitoringTest::test_dashboard_breakdown_status_shows_all_closed_logs_in_selected_range`, and `BreakdownCloseTest::test_closed_breakdown_is_not_shown_in_open_breakdown_cards`; none are caused by the `phpunit.xml` test-environment correction or TASK-002.

### Security and safety confirmation

- CSRF was not disabled or excepted.
- `prime.auth`, `prime.operator.session`, and `prime.role` were not weakened.
- Production middleware/configuration was not bypassed.
- No destructive database command, migration reset, history rewrite, commit, push, reset, stash, rebase, or clean was performed.
- Only `phpunit.xml` was changed for the diagnostic correction; `Docs/work/ACTIVE.md` received this RCA evidence. Existing unrelated dirty work was preserved.

### Next gate

**READY FOR TARGETED BROWSER REGRESSION**. This RCA run intentionally stopped before browser UAT per task scope. TASK-002 remains subject to its independent-review/browser gates and is not being declared closed here.



note ; > Historical record only. TASK-002 is FINAL CLOSED / PASS. Current work has moved to the ADR gap-remediation phase.

---

## TASK-003 Slice 4 — PM Checksheet Historical Delete Protection — 2026-08-28

**Status: CLOSED / PASS**

### Summary

Implemented conservative hard-delete eligibility for PM Checksheet using a persistent provenance model (`assignment_history_known` + `first_observed_machine_assignment_at`). All work is backend-only. The disabled Delete UI enhancement was intentionally excluded per scope.

### Changed files

- `database/migrations/2026_08_28_000001_add_provenance_fields_to_pm_checksheets_table.php`
- `app/Models/PmChecksheet.php`
- `app/Services/Master/PmChecksheetService.php`
- `app/Http/Controllers/Master/MasterPmChecksheetController.php`
- `tests/Feature/Feature/Master/MasterPmChecksheetTest.php`

### Verification

- `php artisan test --filter="MasterPmChecksheetTest"` → **19 passed / 124 assertions**
- Related regression suite → **64 passed / 279 assertions** (no regressions)
- Full suite → **274 passed, 3 known pre-existing baseline failures** (unchanged from prior baseline)
- Scoped Pint → PASS. Scoped `git diff --check` → PASS.

### Open items

- Migration not yet applied to `db_preventive_maintenance` or `db_preventive_maintenance_test`. Deployment requires coordinated DDL scheduling.
- MySQL runtime proof R1–R4 deferred (not required for automated TDD gate).
- Disabled Delete UI enhancement remains excluded.

### Next gate

**TASK-003 Slice 4 CLOSED.** MySQL runtime proof (R1–R4) deferred. Await next task authorization.
