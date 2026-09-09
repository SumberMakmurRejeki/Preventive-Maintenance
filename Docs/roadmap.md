# Master Roadmap PRIME v1

**Project:** PRIME — Preventive Maintenance System
**Date:** 2026-09-03
**Owner:** User + ChatGPT Manager
**Repository Executor / Technical Auditor:** OMP
**Status:** AUTHORITATIVE MASTER ROADMAP
**Management approval:** APPROVED
**Technical validation:** PASS WITH NON-BLOCKING NOTES
**Implementation authorization:** NONE BY ROADMAP ALONE — roadmap ini sendiri tidak mengotorisasi implementasi apa pun
**Canonical document:** `Docs/roadmap.md`
**Current operational position:** Phase 2 — Historical Truth & Transaction Snapshot Foundation — ADR-007 CLOSED / PASS / MANAGER ACCEPTED (Slice A published 8f47e05; Slice B committed c531d5c); next roadmap activation requires manager decision

**Approved management source:** `Docs/work/MASTER-ROADMAP-PRIME-v1-FINAL.md`
**Repository validation evidence:** `Docs/work/session-reports/2026-09-03-PRIME-master-roadmap-v1-final-repository-validation.md`

# 0. PRIME Live Progress Tracker

| Field                        | Current value                                                |
| ---------------------------- | ------------------------------------------------------------ |
| Last Updated                 | 2026-09-09                                                   |
| Current Phase                | Phase 2 — Historical Truth & Transaction Snapshot Foundation |
| Current Task                 | ADR-007 — Transaction Snapshot Strategy — CLOSED / PASS / MANAGER ACCEPTED — 2026-09-09 |
| Last Closed Item             | ADR-007 Slice B — Historical Read-Side Consistency — CLOSED / PASS / MANAGER ACCEPTED — 2026-09-09 |
| Current Gate                 | Next roadmap activation (requires manager decision)          |
| Next Gate                    | Manager decision on next roadmap item from declared dependency order |

| V1 Status                    | NOT READY                                                    |
| Implementation Authorization | NONE FOR COMMIT EXECUTION UNTIL USER + CHATGPT APPROVAL      |

## Phase Overview Tracker

| Phase | Name | Progress Status | Current / Last Evidence | Next Gate | Updated |
| ----- | ---- | --------------- | ------------------------ | --------- | ------- |
| 0 | Current Work Closure & Development Baseline | CLOSED | TASK-004 / ADR-004 CLOSED; TASK-005 CLOSED; R1/R2/R3 PASS; regression PASS; independent review PASS; manager acceptance complete | Phase 0 exit gate PASS | 2026-09-05 |
| 1 | Repository & Schema Integration Baseline | CLOSED / EXIT GATE PASS | C1–C10; PR1–PR3; FR1–FR2 complete / accepted; manager acceptance complete | Phase 2 activation for analysis-planning | 2026-09-07 |
| 2 | Historical Truth & Transaction Snapshot Foundation | ACTIVE | ADR-007 CLOSED / PASS / MANAGER ACCEPTED (2026-09-09); Slice A published checkpoint 8f47e05; Slice B source commit c531d5c | Manager decision on next roadmap item | 2026-09-09 |
| 3 | PM Lifecycle & Planning Completion | NOT STARTED | — | Activate after Phase 2 closure and authorization | 2026-09-04 |
| 4 | PM Configuration Integrity & Stable Option Identity | NOT STARTED | — | Activate after Phase 3 closure and authorization | 2026-09-04 |
| 5 | Historical PM | NOT STARTED | — | Activate after Phase 4 closure and authorization | 2026-09-04 |
| 6 | Breakdown Domain Hardening | NOT STARTED | — | Activate after Phase 5 closure and authorization | 2026-09-04 |
| 7 | Cross-Domain Transaction Reliability | NOT STARTED | — | Activate after Phase 6 closure and authorization | 2026-09-04 |
| 8 | Authorization, Retention & Governance | NOT STARTED | — | Activate after Phase 7 closure and authorization | 2026-09-04 |
| 9 | Read-Side & Reporting Alignment | NOT STARTED | — | Activate after Phase 8 closure and authorization | 2026-09-04 |
| 10 | Performance, Operations & Production Operating Contract | NOT STARTED | — | Activate after Phase 9 closure and authorization | 2026-09-04 |
| 11 | Test & Engineering Quality | NOT STARTED | — | Activate after Phase 10 closure and authorization | 2026-09-04 |
| 12 | Documentation & Lightweight Traceability | NOT STARTED | — | Activate after Phase 11 closure and authorization | 2026-09-04 |
| 13 | V1 Hardening | NOT STARTED | — | Activate after Phase 12 closure and authorization | 2026-09-04 |
| 14 | V1 Release | NOT STARTED | — | Activate after Phase 13 closure and authorization | 2026-09-04 |
| 15 | AI Readiness | FUTURE | — | Activate only after V1 is stable and authorized | 2026-09-04 |
| 16 | Laravel AI / SDK Foundation | FUTURE | — | Activate after Phase 15 closure and authorization | 2026-09-04 |
| 17 | Read-Only PRIME Chatbot | FUTURE | — | Activate after Phase 16 closure and authorization | 2026-09-04 |
| 18 | RAG / Knowledge Assistant | FUTURE | — | Activate after Phase 17 closure and authorization | 2026-09-04 |
| 19 | AI Analytics & Insights | FUTURE | — | Activate after Phase 18 closure and authorization | 2026-09-04 |
| 20 | Assisted AI Actions | FUTURE | — | Activate after Phase 19 closure and authorization | 2026-09-04 |
| 21 | AI Security, Evaluation & Production Rollout | FUTURE | — | Activate after Phase 20 closure and authorization | 2026-09-04 |

### Phase 0 — Detailed Checklist

- [x] TASK-004 Slice 1 — ACCEPTED
- [x] TASK-004 Slice 2 — ACCEPTED
- [x] TASK-005 Fake-Storage Isolation — CLOSED / PASS — MANAGER ACCEPTED
- [x] TASK-004 Slice 3 — Authorization granted
- [x] TASK-004 Slice 3 — Implementation complete
- [x] Integrated MySQL R1/R2/R3 proof — PASS
- [x] Final scoped regression — PASS
- [x] Independent final review — PASS
- [x] Manager acceptance — PASS
- [x] TASK-004 — CLOSED
- [x] ADR-004 current documentation synchronized
- [x] Phase 0 exit gate — PASS
- [x] Phase 0 — CLOSED

### **YOU ARE HERE:** Phase 2 — Historical Truth & Transaction Snapshot Foundation — ADR-007 CLOSED; next roadmap activation requires manager decision.


<!-- PRIME-MANUAL-TRACKING:START -->
Last manual update:
\- 2026-09-05 — TASK-004 / ADR-004 manager accepted and closed; Phase 0 exit gate passed.

User note:
Banyak checkpoint lama belum pernah disimpan sebagai Git commit dan saat ini bercampur di staged/unstaged/untracked worktree.

Current concern/blocker:

- Protected 19-path staged checkpoint mixes TASK-002 and TASK-003 changes. 
- Some unstaged files mix ADR-003 and ADR-004 hunks. 
- Current-state docs/state require synchronization before docs commit. 
- Ambient/local/generated files still require ownership classification.

Manual next priority:
Phase 1.1 — authorize safe checkpoint split and execute logical commit queue without losing accepted historical work.
<!-- PRIME-MANUAL-TRACKING:END -->

This block may be edited manually by the User. Future automated synchronization must preserve user-entered content unless explicitly authorized to change it.

> Catatan: isi `PRIME-MANUAL-TRACKING` di atas dipertahankan sebagai catatan historis/manual. Klaim Phase 1.1 di dalamnya telah disupersede oleh penyelesaian rekonstruksi C1–C10.


### Tracker Update Rules

1. `IMPLEMENTED` does not mean `DONE`.
2. Mark a checkbox complete only when its evidence/status is actually accepted.
3. A task or slice becomes `CLOSED` only after implementation, verification, independent review, and manager acceptance.
4. When a task or slice closes, update this tracker, `ACTIVE.md`, and `state.yaml` where applicable.
5. When a Phase exit gate passes, mark the Phase `CLOSED` and activate the next Phase only after management authorization.
6. Historical evidence must not be rewritten as current truth.
7. Roadmap progress updates do not authorize implementation.
8. Manual user notes must be preserved.

### Recent Progress Updates

| Date | Item | Change | Evidence |
| ---- | ---- | ------ | -------- |
| 2026-09-05 | TASK-004 / ADR-004 | CLOSED | Docs/work/session-reports/2026-09-05-TASK-004-manager-acceptance-closure.md |
| 2026-09-05 | TASK-004 Slice 3 | R1/R2/R3 PASS; scoped regression 90 tests / 413 assertions / 0 failures / 0 errors | `Docs/work/session-reports/2026-09-05-PRIME-TASK-004-S3-execution-verification-attempt-3.md |
| 2026-09-05 | Phase 1.1 Git Hygiene | C1–C10 reconstruction COMPLETE; Phase 1.1 DONE | Git hygiene audit and accepted checkpoint evidence |
| 2026-09-07 | Phase 1 closure synchronization | HEAD d541d8d; staged files 0; PR1–PR3 and FR1–FR2 complete / accepted; Phase 1 exit gate READY FOR MANAGER CLOSURE | Current Git checkpoint and accepted reconstruction history |
| 2026-09-07 | ADR-007 Slice A — Transaction Identity Snapshots | CLOSED / PASS / MANAGER ACCEPTED — published checkpoint 8f47e05 | Commit: feat(pm): snapshot execution transaction identity; 4 files, 229 insertions; SQLite regression, MySQL rehearsal, Laravel Migrator verification, independent review, manager acceptance all PASS |
| 2026-09-09 | ADR-007 Slice B — Historical Read-Side Consistency | CLOSED / PASS / MANAGER ACCEPTED; Slice B source commit c531d5c; accepted local UAT bootstrap commit 5ccb5f8; focused regression 100 tests / 694 assertions PASS | Docs/work/evidence/ADR-007-Slice-B/historical-proof/ |


### Future Tracker Item Template

```text
Item: TASK-XXX / Slice X
Status: NOT STARTED | IN PROGRESS | VERIFIED | ACCEPTED | CLOSED
Evidence: <path>
Manager decision: <decision or pending>
Updated: YYYY-MM-DD
Next gate: <next action>
```

------

# 1. Tujuan Roadmap

PRIME dikembangkan sebagai sistem transaksi maintenance yang dapat dipercaya.

Urutan fundamental:

```text
Master / Configuration
        ↓
Schedule
        ↓
Occurrence
        ↓
Execution
        ↓
Review
        ↓
Approved Historical Truth
        ↓
Reports / Analytics
        ↓
AI
```

Roadmap ini mengikuti prinsip:

```text
Correctness
↓
Historical Truth
↓
Lifecycle Integrity
↓
Domain Completion
↓
Reliability
↓
Read-Side Consistency
↓
Operational Readiness
↓
V1
↓
AI Readiness
↓
AI Capabilities
```

PRIME saat ini sudah mempunyai coverage fungsional luas, tetapi belum dapat dinyatakan V1-ready karena masih terdapat schema alignment, Git reproducibility, concurrency/reliability debt, historical semantics yang belum lengkap, testing, dan operational gaps.

------

# 2. Prinsip Pengembangan PRIME

## 2.1 Source of Truth

Database PRIME dan domain services merupakan source of truth.

```text
UI
↓
Controller
↓
Domain / Application Service
↓
Eloquent
↓
Database
```

Business-critical lifecycle dan historical behavior tidak boleh bergantung pada controller atau tampilan saja.

------

## 2.2 Historical Integrity

Transaksi yang telah menjadi evidence tidak boleh berubah artinya ketika master data berubah.

```text
Current master
≠
Historical transaction truth
```

Historical transaction harus mempunyai snapshot/provenance yang cukup untuk menjelaskan kondisi saat transaksi terjadi.

------

## 2.3 Implemented ≠ Done

Status pekerjaan PRIME menggunakan disiplin:

```text
PLANNED
↓
IMPLEMENTED
↓
VERIFIED
↓
INDEPENDENTLY REVIEWED
↓
ACCEPTED
↓
CLOSED
```

Keberadaan source atau test tidak otomatis berarti pekerjaan selesai.

------

## 2.4 V1 bukan sekadar code selesai

V1 membutuhkan:

```text
correct code
+
correct schema
+
clean/reproducible repository
+
tests
+
UAT
+
operations
+
backup/recovery
+
deployment evidence
```

------

## 2.5 AI bukan fondasi

AI tidak boleh digunakan untuk menutupi data/domain yang belum stabil.

```text
Stable PRIME
↓
AI-ready PRIME
↓
AI integration
```

------

# 3. Evidence Contract

Setiap pekerjaan yang akan dieksekusi harus memiliki evidence contract.

Minimal:

| Elemen                       | Wajib                |
| ---------------------------- | -------------------- |
| Owner                        | Ya                   |
| Target environment           | Ya                   |
| Objective                    | Ya                   |
| Scope                        | Ya                   |
| Validation command/procedure | Ya                   |
| Evidence artifact            | Ya                   |
| Pass/fail rule               | Ya                   |
| Stop condition               | Ya                   |
| Evidence freshness           | Ya saat release gate |

Ini menjawab kekurangan terbesar pada Draft v1: V1 gate sebelumnya sudah mempunyai kategori yang benar, tetapi belum mempunyai owner, command/procedure, environment, artifact, freshness, dan aturan acceptance yang cukup jelas.

------

# 4. Current Position

```text

PHASE 2
Historical Truth & Transaction Snapshot Foundation

ADR-007 Slice A: CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED
Published code checkpoint: 8f47e05d955b5f2a50a49228bfd90c40bcb8f893

ADR-007 Slice B: CLOSED / PASS / MANAGER ACCEPTED — 2026-09-09
Slice B source commit: c531d5c (feat(pm): preserve historical identity in PM readers)
Accepted local UAT bootstrap commit: 5ccb5f8 (test(uat): add safe local Playwright admin bootstrap)

Phase 2 status: ACTIVE
ADR-007 overall: IMPLEMENTED / VERIFIED / CLOSED
```

Current evidence:

```text
Published Slice-A code checkpoint:
develop / 8f47e05d955b5f2a50a49228bfd90c40bcb8f893
4 files, 229 insertions
All verification gates PASS

Closure/docs sync commit:
this documentation closure commit

Slice-A status: CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED
Slice-B status: CLOSED / PASS / MANAGER ACCEPTED
Phase 1: CLOSED / EXIT GATE PASS / MANAGER ACCEPTED
Phase 2: ACTIVE
ADR-007 overall: IMPLEMENTED / VERIFIED / CLOSED

Current remaining sequence:
ADR-007 CLOSED / PASS / MANAGER ACCEPTED
↓
next roadmap activation requires manager decision
```

No commit execution is authorized by this roadmap update alone.


------

# 5. Master Phase Overview

| Phase | Nama                                                    | Classification            |
| ----- | ------------------------------------------------------- | ------------------------- |
| 0     | Current Work Closure & Development Baseline             | V1 Mandatory              |
| 1     | Repository & Schema Integration Baseline                | V1 Mandatory              |
| 2     | Historical Truth & Transaction Snapshot Foundation      | V1 Mandatory              |
| 3     | PM Lifecycle & Planning Completion                      | V1 Mandatory              |
| 4     | PM Configuration Integrity & Stable Option Identity     | V1 Mandatory              |
| 5     | Historical PM                                           | V1 Mandatory              |
| 6     | Breakdown Domain Hardening                              | V1 Mandatory              |
| 7     | Cross-Domain Transaction Reliability                    | V1 Mandatory              |
| 8     | Authorization, Retention & Governance                   | V1 Baseline / Conditional |
| 9     | Read-Side & Reporting Alignment                         | V1 Mandatory              |
| 10    | Performance, Operations & Production Operating Contract | V1 Mandatory              |
| 11    | Test & Engineering Quality                              | V1 Mandatory              |
| 12    | Documentation & Lightweight Traceability                | V1 Mandatory              |
| 13    | V1 Hardening                                            | V1 Mandatory              |
| 14    | V1 Release                                              | Release                   |
| 15    | AI Readiness                                            | Future                    |
| 16    | Laravel AI / SDK Foundation                             | Future                    |
| 17    | Read-Only PRIME Chatbot                                 | Future                    |
| 18    | RAG / Knowledge Assistant                               | Future                    |
| 19    | AI Analytics & Insights                                 | Future                    |
| 20    | Assisted AI Actions                                     | Future                    |
| 21    | AI Security, Evaluation & Production Rollout            | Future                    |

# PHASE 0 — Current Work Closure & Development Baseline

**Status: CLOSED / EXIT GATE PASS — 2026-09-05**

## Objective

Menyelesaikan TASK-004 tanpa membuka pekerjaan arsitektur baru.

## Scope

### 0.1 Fake-Storage Test Infrastructure

Current classification:

```text
CLOSED / PASS — TEST-INFRASTRUCTURE ISOLATION VERIFIED — MANAGER ACCEPTED
```

Evidence:

- shared-root cleanup PROVEN;
- token isolation PROVEN EFFECTIVE;
- scoped regression PASS;
- independent review PASS;
- production defect NOT ESTABLISHED.

Tujuan:

```text
Concurrent test process A
→ storage A

Concurrent test process B
→ storage B
```

Tidak boleh saling membersihkan fixture.

Tidak ada production fix kecuali ditemukan causal production defect baru.

------

### 0.2 TASK-004 Slice 3

Final integrated proof:

```text
Application serialization
+
DB uniqueness
+
two-connection MySQL concurrency
```

Minimum proof:

```text
R1
R2
R3
final canonical count = 1
no unexplained 1213
no unexplained timeout
cleanup residual = 0
```

------

### 0.3 TASK-004 Final Independent Closure

Setelah Slice 3:

```text
independent review
↓
manager acceptance
↓
TASK-004 CLOSED
↓
ADR-004 implemented/verified
```

------

### 0.4 TASK-004 Documentation Closure

Perbaiki current-vs-historical ambiguity pada:

```text
ADR-004
TASK-004 historical architecture sections
fake-storage bug status wording
ACTIVE/state workflow pointer
```

OMP menemukan bahwa repository masih mempunyai sejumlah status historical yang dapat terbaca seperti current truth.

## Exit Gate

```text
## Exit Gate

```text
[x] TASK-004 CLOSED
[x] ADR-004 current documentation synchronized
[x] Fake-storage test issue resolved/classified
[x] No unreviewed causal defect

PHASE 0 EXIT GATE: PASS
PHASE 0: CLOSED — 2026-09-05
```

------

# PHASE 1 — Repository & Schema Integration Baseline

**Status: CLOSED / EXIT GATE PASS / MANAGER ACCEPTED — 2026-09-07** **Current work: Phase 2 analysis-planning activation**


## Objective

Membuat current PRIME state dapat direproduksi dan schema antar-environment dapat dilacak.

------

## 1.1 Git Hygiene Audit

Bukan langsung cleanup.

```text
read-only inventory
↓
classify every dirty path
↓
map path → TASK / ADR / tooling / unknown
↓
logical commit plan
↓
User approval
↓
controlled commit execution
```

### Current status — 2026-09-07

```text
Controlled C1–C10 reconstruction  DONE
Read-only inventory             PASS
Path classification             PASS
Logical commit plan             COMPLETE
Git checkpoint preserved        PASS
Phase 1.1 required work         COMPLETE
Phase 1.2 Local DB alignment     CLOSED / PASS
Phase 1.3 Schema governance      CLOSED / PASS
```

Commit/reconstruction status:

```text
C1–C10 reconstruction COMPLETE
PR1 / PR2 / PR3 COMPLETE — PASS / MANAGER ACCEPTED
FR1 / FR2 COMPLETE — PASS / MANAGER ACCEPTED
TASK-004 migration committed in C10
TASK-005 isolation work committed in C8
Next gate: ADR-007 Slice B — Historical Read-Side Consistency — bounded current-reader assessment / implementation planning
```




### Rule

Tidak boleh:

```text
git add .
git reset --hard
git clean
mass restore
```

tanpa file-level evidence dan authorization.

------

## 1.2 Local DB Schema Alignment

Current evidence:

```text
db_preventive_maintenance_test
→ canonical unique proven

db_preventive_maintenance
→ 15/15 repository migrations applied
→ latest: 2026_09_02_203946_add_canonical_execution_unique_index
→ pm_executions_pm_schedule_date_id_unique: PRESENT

STAGING
→ NOT YET PROVEN

PRODUCTION
→ NOT YET PROVEN
```

Local migration gate:

```text
confirm DB identity
↓
read-only duplicate preflight
↓
0 duplicate groups
↓
explicit authorization
↓
migrate
↓
schema verification
```

Jika duplicate ditemukan:

```text
STOP / HUMAN DATA DECISION
```

## 1.3 Environment Schema Governance

**Status: CLOSED / PASS — 2026-09-07**

Governance contract: `Docs/environment-schema-governance.md`

Current truth:

```text
TEST
→ PROVEN

LOCAL
→ PROVEN

STAGING
→ NOT YET PROVEN

PRODUCTION
→ NOT YET PROVEN
```

Contract mencakup environment identity, application commit/release, database engine/version, migration ledger, latest applied migration, critical constraint state, migration owner, migration procedure, pre-migration gate, post-migration verification, backup requirement, rollback/recovery responsibility, evidence artifact, dan evidence date/freshness.

Klasifikasi schema drift wajib memakai migration ledger dan critical constraint evidence secara bersamaan. Jumlah migration yang sama saja tidak cukup untuk menyatakan `ALIGNED`.

## Exit Gate

```text
develop reproducible
logical Git history established
local schema aligned
environment migration process documented
Phase 1.1 required reproducibility work complete
Phase 1.2 CLOSED / PASS
Phase 1.3 CLOSED / PASS
required Phase 1 residual blockers captured
```

Phase 1 exit gate: **CLOSED / EXIT GATE PASS / MANAGER ACCEPTED — 2026-09-07**.



------

# PHASE 2 — Historical Truth & Transaction Snapshot Foundation
**Status: ACTIVE / ADR-007 CLOSED**

**Current work:** ADR-007 closed; next roadmap activation requires manager decision.

**Next gate:** Manager decision on the next roadmap item from the declared dependency order.

## Objective

Menjamin transaksi masa lalu tidak berubah makna akibat perubahan master.

## Primary ADR

```text
ADR-007 — Transaction Snapshot Strategy
```

## Work

Snapshot strategy harus mencakup:

```text
Machine identity
Location identity
Checksheet/config version
Part
Standard
Option identity
Operator
Admin/reviewer
Performed information
Transaction provenance
```

Current PRIME sudah mempunyai banyak snapshot, tetapi PM machine/location/configuration identity masih belum lengkap.

------

## 2.1 Historical Correction Policy

Sebelum V1, PRIME harus mempunyai **keputusan eksplisit** tentang approved/finalized record yang salah.

Tidak boleh:

```text
silent edit
silent delete
history rewrite
```

Allowed conceptual model:

```text
Original immutable evidence
↓
Void / correction / replacement reference
↓
Actor
Reason
Timestamp
Audit trail
```

### Management decision

Untuk V1:

- **policy/contract wajib ada**;
- full correction UI/workflow hanya wajib jika business requirement V1 memang membutuhkan koreksi transaksi finalized;
- bila belum diimplementasikan, behavior harus fail closed dan correction menjadi post-V1 controlled capability.

## Exit Gate

```text
Snapshot contract accepted
Historical display stability verified
Correction policy explicit
No silent historical reinterpretation
```

------

# PHASE 3 — PM Lifecycle & Planning Completion

## Objective

Menyelesaikan lifecycle Machine, Schedule, PM occurrence, serta planning behavior.

## 3.1 ADR-010 Schedule Lifecycle

Target:

```text
ACTIVE
↕
PAUSED
↓
ENDED
```

Rules harus mencakup:

```text
pause reason
resume
no catch-up backlog
end finality
impact to occurrences
```

------

## 3.2 ADR-010 Machine Lifecycle

Target:

```text
ACTIVE
↕
INACTIVE
↓
RETIRED
```

Rules:

```text
INACTIVE
→ temporary suppression

RETIRED
→ permanent operational retirement

RECOMMISSION
→ explicit process
→ does not automatically revive ended schedules
```

------

## 3.3 Central Lifecycle Transition Authority

Ini merupakan tambahan resmi dari validasi roadmap OMP. OMP menemukan lifecycle mutation saat ini tersebar di beberapa service/command sehingga roadmap memerlukan satu authoritative transition boundary.

Target:

```text
Lifecycle Policy / Transition Authority
               ↓
Executor
Review
Status Cron
Reconciliation
Other lifecycle writers
```

Tidak harus menjadi satu giant service.

Yang wajib hanya:

> satu definisi canonical tentang transition yang legal dan invariant yang harus dipertahankan.

------

## 3.4 ADR-009 Planning Follow-Up

Implement/decide:

```text
global planning period
rolling extension
idempotency
planning-window decrease
no historical backlog
Asia/Jakarta business date
```

Planning extension tidak boleh bypass paused/ended/retired semantics.

## Exit Gate

```text
Lifecycle transitions centralized
Machine/Schedule lifecycle accepted
Planning follow-up deterministic
No catch-up backlog
```

------

# PHASE 4 — PM Configuration Integrity & Stable Option Identity

> Phase ini dipindahkan **sebelum Historical PM** berdasarkan management decision dan OMP validation.

OMP menilai Historical PM → Option Catalog sequencing berisiko bila Historical PM membutuhkan stable option identity.

## Primary ADR

```text
ADR-002 — PM Option Catalog
```

## Objective

Memiliki stable identity untuk option/value PM.

Target:

```text
stable code
display label
active/inactive lifecycle
configuration snapshot
transaction snapshot
```

Contoh:

```text
code = NORMAL
label = Normal
```

Label boleh berubah.

Historical meaning dari `NORMAL` tidak boleh berubah.

## Dependency Rule

Historical PM yang menggunakan option-based fields **tidak boleh diimplementasikan sebelum stable option identity contract tersedia**.

## Exit Gate

```text
Stable option code
Snapshot semantics verified
Deactivate does not rewrite history
```

------

# PHASE 5 — Historical PM

## Primary ADR

```text
ADR-001 — Live vs Historical PM
```

## Objective

Mengizinkan maintenance masa lalu dicatat tanpa mencemari live operational lifecycle.

## Contract

Historical PM harus membedakan:

```text
scheduled_date
performed_date
entered_at
performed_by
entered_by
source / provenance
```

Historical PM:

```text
NOT live backlog
NOT overdue
NOT missed
NOT live notification
NOT normal executor delayed work
```

Current repository memang belum memiliki production historical/manual PM workflow.

## Dependencies

Historical PM membutuhkan:

```text
ADR-004 canonical identity
ADR-005 lifecycle exclusion semantics
ADR-007 snapshots
ADR-009 operational boundaries
ADR-002 stable option identity where applicable
```

ADR-010 full live lifecycle membantu eligibility semantics, tetapi historical PM tetap harus terisolasi dari live lifecycle. OMP mengonfirmasi dependency ini.

## Exit Gate

```text
Historical entry cannot affect live backlog
Historical provenance preserved
Snapshot identity stable
Report/history consume historical truth correctly
```

------

# PHASE 6 — Breakdown Domain Hardening

## 6.1 ADR-008 Structured Root Cause

Current:

```text
root_cause = free text
action_taken = free text
countermeasure = free text
```

Target:

```text
Root Cause Category
↓
Cause
↓
Detail

Action Taken
Countermeasure
```

Stable identity diperlukan untuk:

```text
Pareto
analytics
reporting
future AI
```

------

## 6.2 Breakdown Concurrency

Review:

```text
create
close
review/update
```

Perlu authoritative locked re-read dan deterministic overlap behavior.

------

## 6.3 Breakdown Code Allocation

Current `count()+1` strategy harus dievaluasi untuk concurrent inserts.

DB uniqueness dapat menjadi backstop tetapi aplikasi harus memberi deterministic/business-facing behavior.

------

## 6.4 Historical Preservation

Breakdown finalized history harus mengikuti ADR-003 dan historical correction policy.

## Exit Gate

```text
Structured RCA accepted
Concurrent writer behavior deterministic
Finalized records protected
Analytics-ready identity available
```

------

# PHASE 7 — Cross-Domain Transaction Reliability

## Objective

Menyelesaikan correctness debt yang memotong beberapa module.

## Scope

### 7.1 PM Review Serialization — RI-012

Prevent:

```text
Admin A edit
||
Admin B approve
```

dari menghasilkan stale post-approval update.

------

### 7.2 PM Status Cron Serialization — RI-013

Prevent:

```text
Cron marks overdue
||
Operator starts PM
```

berdasarkan stale read.

------

### 7.3 Breakdown Serialization — RI-014

Parent-first/authoritative locking untuk create/close/update sesuai contract yang diputuskan.

------

### 7.4 File / DB Consistency — RI-015

Current risk:

```text
write file
↓
DB rollback
↓
orphan
```

Target dapat menggunakan staging/compensation/after-commit strategy sesuai design nanti.

------

### 7.5 Notification Idempotency — RI-016

Application:

```text
check
→ insert
```

tidak cukup jika business identity membutuhkan true uniqueness.

Evaluate application + database backstop.

## Exit Gate

```text
No known high-risk stale writer
Filesystem/DB failure deterministic
Notification identity deterministic
Required concurrency proofs PASS
```

------

# PHASE 8 — Authorization, Retention & Governance

## 8.1 V1 Authorization Baseline

V1 baseline tetap:

```text
Admin
Operator
Guest
```

dengan:

```text
authentication
session
role middleware
CSRF
sensitive action authorization
```

------

## 8.2 Fine-Grained Authorization

```text
location
plant
department
shift
machine
record ownership
```

Classification:

> **CONDITIONAL / POST-V1 by default**

Tidak wajib untuk initial V1 kecuali business access matrix membuktikan kebutuhan.

Jangan membangun advanced RBAC tanpa business requirement.

------

## 8.3 Retention / Archival Policy

Tentukan policy untuk:

```text
DB transactions
PM media
Breakdown media
QR assets
Reports
Logs
```

------

## 8.4 Correction / Audit Governance

Pastikan finalized data, corrections, actor identity, reasons, dan historical provenance mempunyai governance yang sama.

## Exit Gate

```text
V1 authorization baseline verified
Retention decision established
Critical historical governance explicit
```

------

# PHASE 9 — Read-Side & Reporting Alignment

## Objective

Membuat semua reader memakai semantic truth yang sama.

Current readers antara lain:

```text
Dashboard
Calendar
Machine Landing
Review
PM Reports
Breakdown Reports
Notifications
Future AI
```

Saat ini beberapa reader mendefinisikan status/history masing-masing. OMP mengonfirmasi read-side alignment tepat ditempatkan setelah transaction/lifecycle/snapshot semantics stabil.

## Target Architecture

```text
Domain / Query Services
          ↓
Dashboard
Calendar
Reports
Landing
Future AI
```

------

## Historical Reports

Report transaksi masa lalu harus memprioritaskan transaction snapshot/historical identity, bukan current machine/location master.

## Exit Gate

```text
Same fixture
↓
Dashboard / Calendar / Report
↓
same lifecycle meaning
same historical meaning
```

------

# PHASE 10 — Performance, Operations & Production Operating Contract

## Objective

Mengubah PRIME dari aplikasi yang bekerja menjadi sistem yang dapat dioperasikan secara konsisten.

## 10.1 Dashboard / Query Scalability

Evaluate berdasarkan actual volume:

```text
query count
date range
memory
response time
indexes
```

Tidak optimasi spekulatif.

------

## 10.2 Report Export

Current synchronous export tetap acceptable sampai evidence volume/SLA mengatakan sebaliknya.

Queued export:

```text
CONDITIONAL
```

Jika dibutuhkan:

```text
request
↓
queue
↓
generate
↓
notify
↓
download
```

------

## 10.3 Queue / Scheduler Operations

Define:

```text
worker command
retry
timeout
scheduler process
overlap behavior
multi-node topology
lock backend
recovery procedure
```

------

## 10.4 Observability

Baseline:

```text
structured application logs
job failures
scheduler failures
health checks
operational alerts
```

Metrics/SLO dikembangkan sesuai kebutuhan operasional.

------

## 10.5 Production Operating Contract

Ini merupakan missing candidate kedua dari OMP.

Dokumen/contract harus menjawab:

```text
Environment identity
Deployment owner
Migration owner
Rollback owner
Queue topology
Scheduler topology
Cache/lock backend
Filesystem ownership
Backup responsibility
Restore responsibility
Health checks
Logging/alert ownership
```

## Exit Gate

```text
Production operating contract accepted
Operations smoke procedure defined
Runtime ownership explicit
```

------

# PHASE 11 — Test & Engineering Quality

## 11.1 Automated Test Baseline

V1 policy:

> **No unexplained test failures.**

Setiap failure harus:

```text
FIXED
or
EXPLICITLY CLASSIFIED
+
EVIDENCED
+
MANAGER ACCEPTED
```

Critical correctness tests harus green.

Latest recorded TASK-004 full-suite evidence masih `317 passed / 3 failed / 1453 assertions`, sehingga current release evidence belum final.

------

## 11.2 JavaScript Test Command

JS tests yang sudah ada harus mempunyai documented standardized command.

------

## 11.3 CI

Minimum candidate:

```text
dependency install
↓
PHP tests
↓
JS tests
↓
format/check
↓
frontend build
```

Static analysis boleh ditambahkan berdasarkan cost/value; tidak wajib overengineered.

------

## 11.4 Test Infrastructure

Fake storage isolation setelah Phase 0 menjadi bagian baseline test reliability.

## Exit Gate

```text
Repeatable test commands
No unexplained failures
Test infrastructure deterministic
CI/release checks reproducible
```

------

# PHASE 12 — Documentation & Lightweight Traceability

## Objective

Membuat documentation current/historical tidak ambigu dan menyediakan traceability secukupnya.

## 12.1 Documentation Governance

Distinguish:

```text
CURRENT
HISTORICAL
SUPERSEDED
FUTURE
```

OMP menemukan lima current-state conflict groups dan historical-label drift pada repository docs.

------

## 12.2 Documentation Router

Tambahkan/update document routing strategy yang jelas.

Tidak harus membuat banyak bureaucracy files.

------

## 12.3 Lightweight Requirement Traceability

Kita **tidak mewajibkan enterprise epic bureaucracy**.

Model resmi PRIME:

```text
Master Roadmap
↓
ADR / Domain Decision
↓
TASK
↓
Implementation
↓
Automated / Runtime / UAT Evidence
↓
Independent Review
↓
Manager Acceptance
↓
Release Evidence
```

Itu adalah formal planning contract kita.

## Exit Gate

```text
No contradictory current status
Each active item traceable to decision/task/evidence
V1 scope traceable without excessive artifact proliferation
```

------

# PHASE 13 — V1 Hardening

## Objective

Tidak menambah feature besar.

Hanya menyelesaikan release blockers.

## Gate Categories

```text
Domain correctness
Data integrity
Schema
Git reproducibility
Authorization/security
Concurrency
Storage consistency
Regression
Browser/UAT
Performance sanity
Queue/Scheduler
Backup/Restore
Observability
Documentation
Deployment readiness
```

OMP mengonfirmasi daftar kategori ini cukup luas; kekurangannya hanya gate mechanics yang sekarang sudah dimasukkan ke Evidence Contract.

## Mandatory Gate Contract

Setiap gate harus mencatat:

```text
owner
environment
command/procedure
artifact
freshness
pass/fail rule
```

------

## V1 Mandatory Domain Scope

Sebelum V1:

```text
TASK-004 closure
ADR-007 snapshot foundation
ADR-010 lifecycle
ADR-009 required planning follow-up
ADR-002 option identity
ADR-001 Historical PM
ADR-008 structured RCA
cross-domain reliability
read-side alignment
testing/operations/release hardening
```

------

## Conditional Scope

Fine-grained authorization:

```text
only if business requirement requires it
```

Queued report:

```text
only if volume/SLA evidence requires it
```

Full correction UI:

```text
only if V1 business workflow requires correction
```

tetapi correction policy tetap wajib.

## Exit Gate

```text
No P0/P1 unresolved correctness issue
No unexplained test failures
All mandatory domain scope accepted
Schema aligned
Repository reproducible
Critical UAT PASS
Operational evidence PASS
```

------

# PHASE 14 — V1 Release

## Objective

Membawa PRIME dari verified development state ke controlled V1 release.

Conceptual flow:

```text
develop
↓
release candidate
↓
V1 validation package
↓
UAT
↓
deployment readiness
↓
release approval
↓
main
```

`main` tidak disentuh sebelum gate ini.

## Required Release Evidence

```text
release commit identity
DB migration evidence
schema identity
automated regression
browser/UAT
production/staging smoke
Git reproducibility
backup/restore readiness
queue/scheduler readiness
health checks
rollback path
documentation snapshot
```

Current evidence belum dapat menyatakan release readiness; production schema, smoke, restore drill, dan runtime topology masih belum terbukti.

## V1 Definition

PRIME V1 berarti:

> Sistem PM dan Breakdown yang mempunyai lifecycle konsisten, historical truth yang dapat dipercaya, data transaction yang terlindungi, reporting yang menggunakan semantic source of truth yang sama, repository/schema yang reproducible, serta deployment/operations yang mempunyai evidence.

------

# PHASE 15 — AI Readiness

## Classification

```text
FUTURE
NOT IMPLEMENTATION AUTHORIZATION
```

AI hanya dimulai setelah V1 stabil.

Current repository AI readiness masih `NOT READY / FUTURE`.

## Required Foundation

```text
stable historical truth
complete snapshots
stable identifiers
structured RCA
canonical query semantics
authorization
privacy
retention
dataset lineage
evaluation
```

## AI Security Early Constraint

Walaupun Phase 21 adalah final production gate, mulai Phase 15 seluruh AI planning sudah harus mempertimbangkan:

```text
authorization
privacy
data minimization
auditability
evaluation
human approval
```

Ini merupakan koreksi OMP: security/evaluation tidak boleh menjadi last-minute activity.

------

# PHASE 16 — Laravel AI / SDK Foundation

## Objective

Membuat AI integration abstraction, bukan chatbot langsung.

Architecture:

```text
PRIME Domain / Query Services
           ↓
AI Application Service
           ↓
Provider / Laravel AI SDK Adapter
           ↓
Model Provider
```

Rules:

```text
No raw unrestricted DB access
No API key inside business code
No uncontrolled mutation
Authorization preserved
Model/provider replaceable
Observability built in
```

## Human Decisions Required

Later decide:

```text
AI use case
Laravel SDK/package
provider
model
cost boundary
evaluation policy
```

Tidak diputuskan sekarang.

------

# PHASE 17 — Read-Only PRIME Chatbot

AI capability pertama harus read-only.

Example questions:

```text
PM terakhir Mesin X kapan?

PM apa yang overdue?

Berapa breakdown Mesin X bulan ini?

Apa RCA terbanyak periode ini?
```

Architecture:

```text
User
↓
Chatbot
↓
AI Service
↓
Authorized Query Services
↓
PRIME DB
```

Output deterministic facts harus traceable.

------

# PHASE 18 — RAG / Knowledge Assistant

Tambahkan controlled knowledge retrieval untuk:

```text
SOP
work instructions
machine manuals
maintenance procedures
internal knowledge
```

Setiap response harus mampu membedakan:

```text
PRIME FACT
DOCUMENT FACT
AI INFERENCE
```

Required controls:

```text
corpus ownership
access policy
provenance
retention
retrieval evaluation
```

------

# PHASE 19 — AI Analytics & Insights

Setelah structured data matang:

```text
trends
anomalies
Pareto interpretation
maintenance insights
RCA assistance
```

Contoh:

```text
"Breakdown Electrical → Motor meningkat dalam 3 bulan terakhir."
```

Factual metrics tetap dihitung deterministically.

LLM menjelaskan, bukan menciptakan angka.

------

# PHASE 20 — Assisted AI Actions

AI boleh menghasilkan proposal:

```text
maintenance draft
classification suggestion
report narrative
schedule adjustment proposal
root-cause suggestion
```

Mutation path:

```text
AI proposal
↓
Human review
↓
Explicit approval
↓
PRIME Domain Service
↓
Mutation
↓
Audit trail
```

Tidak ada autonomous DB write by default.

------

# PHASE 21 — AI Security, Evaluation & Production Rollout

Final production gate:

```text
authorization
privacy
prompt-injection defense
tool permissions
eval dataset
hallucination/factuality metrics
cost limits
rate limits
audit log
monitoring
fallback
rollback
incident process
UAT
```

Only after PASS:

```text
AI Production Rollout
```

OMP menilai keseluruhan chain V1 → AI readiness → SDK → read-only chatbot → RAG → insights → assisted actions → production gate **technically safe conditionally**.

------

# 6. RI-001 sampai RI-030 Traceability

| RI                                          | Final Roadmap                         |
| ------------------------------------------- | ------------------------------------- |
| RI-001 Historical/manual PM                 | Phase 5                               |
| RI-002 Complete transaction snapshots       | Phase 2 → 9 → 15                      |
| RI-003 PM Option Catalog                    | Phase 4                               |
| RI-004 Audited correction/void              | Phase 2 / 8                           |
| RI-005 Global planning period               | Phase 3                               |
| RI-006 Rolling schedule extension           | Phase 3                               |
| RI-007 Schedule Paused/Ended                | Phase 3                               |
| RI-008 Machine Retired                      | Phase 3                               |
| RI-009 Structured Breakdown RCA             | Phase 6 → 15 → 19                     |
| RI-010 Canonical execution closure          | Phase 0                               |
| RI-011 Schema deployment alignment          | Phase 1 → 14                          |
| RI-012 PM Review serialization              | Phase 7                               |
| RI-013 PM cron serialization                | Phase 7                               |
| RI-014 Breakdown serialization              | Phase 6 / 7                           |
| RI-015 File/DB consistency                  | Phase 7 → 13                          |
| RI-016 Notification uniqueness              | Phase 7                               |
| RI-017 Fine-grained authorization           | Phase 8 / conditional; AI gates later |
| RI-018 Read-model alignment                 | Phase 9                               |
| RI-019 Dashboard/query scalability          | Phase 10                              |
| RI-020 Queued report export                 | Phase 10 conditional                  |
| RI-021 Test baseline cleanup                | Phase 11 → 13 → 14                    |
| RI-022 Fake-storage isolation               | Phase 0                               |
| RI-023 JavaScript test command              | Phase 11                              |
| RI-024 CI/static analysis                   | Phase 11 / 14                         |
| RI-025 Queue/scheduler operations           | Phase 10 / 14                         |
| RI-026 Observability                        | Phase 10 / 14                         |
| RI-027 Backup/recovery/retention            | Phase 8 / 10 / 14                     |
| RI-028 Documentation governance             | Phase 12                              |
| RI-029 Lightweight requirement traceability | Phase 12                              |
| RI-030 AI readiness                         | Phase 15–21                           |

Semua 30 roadmap input yang ditemukan audit OMP tetap terwakili; validasi sebelumnya juga memastikan coverage RI 30/30.

------

# 7. V1 Scope Boundary

## Mandatory Before V1

```text
TASK-004 closure
Canonical execution invariant
Transaction snapshots
Machine/Schedule lifecycle
Required planning follow-up
Stable PM option identity
Historical PM
Structured Breakdown RCA
Critical transaction reliability
Read-side semantic alignment
Schema alignment
Git reproducibility
Test baseline
Operational readiness
Documentation synchronization
V1 hardening
```

## Conditional Before V1

```text
Fine-grained RBAC
Queued report exports
Full correction UI
advanced observability
```

ditentukan oleh business requirements dan actual volume/risk.

## Explicitly Post-V1 / Future

```text
Laravel AI / SDK
Chatbot
RAG
AI analytics
Assisted AI actions
AI production rollout
Predictive maintenance
OCR
IoT integration
```

------

# 8. Not Now

Sampai phase dependency terpenuhi, jangan membuka:

```text
AI/chatbot implementation
RAG
predictive maintenance
OCR
IoT
advanced RBAC tanpa requirement
major UI redesign
bulk historical import
overcomplex root-cause taxonomy
```

------

# 9. Roadmap Change Control

Roadmap tidak otomatis mengotorisasi task.

Setiap item mengikuti:

```text
Roadmap
↓
Management chooses next item
↓
Analysis
↓
ADR/domain decision if required
↓
TASK
↓
Plan
↓
User authorization
↓
OMP implementation
↓
Verification
↓
Independent review
↓
Manager acceptance
↓
Docs/state synchronization
```

------

# 10. Responsibility Model

| Role            | Responsibility                                               |
| --------------- | ------------------------------------------------------------ |
| User            | Business owner, scope, approval, final human decisions       |
| ChatGPT         | Roadmap manager, dependency manager, OMP prompt author, evidence reviewer, gate decision |
| OMP             | Repository auditor/executor/testing agent within bounded scope |
| Repository Docs | Persistent shared state                                      |

------

# 11. Current Immediate Handoff

Roadmap progress tracking does not change the required authorization boundary or current implementation scope.

```text
CURRENT POSITION

PHASE 2
Historical Truth & Transaction Snapshot Foundation
↓
ADR-007 Slice A — CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED
↓
ADR-007 Slice B — CLOSED / PASS / MANAGER ACCEPTED (2026-09-09; source commit c531d5c)

PHASE 1
Repository & Schema Integration Baseline
↓
Phase 1 — CLOSED / EXIT GATE PASS / MANAGER ACCEPTED

PHASE 0
CLOSED / PASS

TASK-004
CLOSED / MANAGER ACCEPTED

ADR-004
Accepted — Implemented + Verified

TASK-005
CLOSED / PASS — MANAGER ACCEPTED

GIT HYGIENE AUDIT
COMPLETE

CURRENT PHASE STATUS
Phase 1 — CLOSED / EXIT GATE PASS
Phase 1.1 — DONE
Phase 1.2 — CLOSED / PASS
Phase 1.3 — CLOSED / PASS
Required Phase 1 residual blockers — CAPTURED
Phase 2 — ACTIVE

CURRENT GATE
Next roadmap activation

NEXT MANAGEMENT GATE
Manager decision on the next roadmap item from the declared dependency order
no next implementation authorized without manager decision

```

No Git index mutation or commit is authorized by this roadmap update alone.

# 12. Final Roadmap Status

```text
MASTER ROADMAP PRIME v1

Management roadmap:
APPROVED

Management Design:
FINAL

Repository-wide inventory:
COMPLETE

Repository validation:
PASS WITH NON-BLOCKING NOTES

Canonical status:
AUTHORITATIVE

OMP corrections incorporated:
YES

RI-001 — RI-030 coverage:
30 / 30

Historical PM before V1:
YES

PM Option Catalog dependency before Historical PM:
YES

Central Lifecycle Transition Authority:
ADDED

Production Operating Contract:
ADDED

Fine-grained authorization mandatory V1:
NO — CONDITIONAL

V1 unexplained test failure policy:
ZERO UNEXPLAINED FAILURES

Planning governance:
LIGHTWEIGHT TRACEABILITY

AI:
POST-V1 / FUTURE

Implementation authorization:
NONE BY ROADMAP ALONE

Current phase:
Phase 2 — Historical Truth & Transaction Snapshot Foundation — ACTIVE

Current Slice-A status:
ADR-007 Slice A — Transaction Identity Snapshots — CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED

Published Slice-A code checkpoint:
8f47e05d955b5f2a50a49228bfd90c40bcb8f893

Current work:
ADR-007 — Transaction Snapshot Strategy — CLOSED / PASS / MANAGER ACCEPTED

ADR-007 overall:
IMPLEMENTED / VERIFIED / CLOSED

Current Git gate:
develop / origin-develop parent dcf4ff77 / staged 0

Next management handoff:
manager decision on next roadmap item

Next implementation authorization:
NONE until manager decision
```
