# TASK-006 — Machine & Schedule Lifecycle

## 1. Status and authorization

- **Phase:** Phase 3 — PM Lifecycle & Planning Completion.
- **Primary ADR:** ADR-010 — Schedule & Machine Lifecycle.
- **TASK-006:** IN PROGRESS.
- **Implementation:** Slice A complete; B-E not started.
- **ADR-010:** IMPLEMENTATION IN PROGRESS / SLICE A ACCEPTED / NOT DONE.
- **Slice A — Contract & Data Foundation:** IMPLEMENTED / VERIFIED / INDEPENDENTLY REVIEWED / MANAGER ACCEPTED / ENGINEERING COMMITTED / CLOSED / PASS / PUBLISHED at engineering checkpoint `23d64f0c6369931820ae9ec1767e2ce3c52ed430`; tracking closure commit `4a7c48a3c9e7463f065c3bf77fbce8e71b93509e` is published.
- **Slice B — Schedule Lifecycle:** NOT STARTED / NOT AUTHORIZED.
- **Slice C — Machine Lifecycle:** NOT STARTED / NOT AUTHORIZED.
- **Slice D — Distributed Writer Integration:** NOT STARTED / NOT AUTHORIZED.
- **Slice E — Concurrency, UAT & Closure:** NOT STARTED / NOT AUTHORIZED.

The planning authorization above was superseded by the later explicit Slice A
implementation authorization. Slice A engineering and tracking publication are
complete; Slice B-E remain unauthorized for any code, migration, test, database,
Git-index, commit, or deployment work.

## 2. Goal

Introduce explicit Machine and Schedule lifecycle semantics that suppress
non-live PM work, prevent resume/reactivation catch-up backlog, and preserve
already-started canonical executions and all existing historical truth.

## 3. Accepted business contract

### 3.1 Already-started PM

If a canonical execution became `IN_PROGRESS` while Machine and Schedule were
`ACTIVE`, a later Machine `INACTIVE`/`RETIRED` or Schedule `PAUSED`/`ENDED`
transition blocks new starts only. That canonical execution remains eligible for
ordinary `IN_PROGRESS → WAITING_REVIEW → APPROVED` validation and review.

### 3.2 Legacy mapping

| Entity | `is_active = true` | `is_active = false` | Must not infer |
| --- | --- | --- | --- |
| Machine | `ACTIVE` | `INACTIVE` | `RETIRED` |
| PmSchedule | `ACTIVE` | `PAUSED` | `ENDED` |

### 3.3 Resume/reactivation and recommission

`PAUSED → ACTIVE` and `INACTIVE → ACTIVE` write a new
`effective_live_from = BusinessDate::today()`. This boundary is separate from
ADR-009 `operational_from`, which remains provenance. Pre-boundary mutable
occurrences are preserved but non-live/suppressed; their reporting/KPI treatment
is deferred to reporting policy / Phase 9.

`RETIRED → ACTIVE` is forbidden in the ordinary machine lifecycle. An explicit
recommission workflow restores machine eligibility, applies a new live boundary,
creates a new schedule for the new era, and never revives an existing `ENDED`
schedule.

### 3.4 Read-side contract

Executor, actionable QR, live queue, live overdue/missed processing, and live
notification eligibility suppress non-live work. Waiting historical truth,
approved executions, snapshots, and audit evidence remain readable and are not
rewritten.

## 4. State machines and lock rules

```text
Schedule: ACTIVE ↔ PAUSED; ACTIVE → ENDED; PAUSED → ENDED
Forbidden ordinary transition: ENDED → ACTIVE

Machine: ACTIVE ↔ INACTIVE; ACTIVE → RETIRED; INACTIVE → RETIRED
Forbidden ordinary transition: RETIRED → ACTIVE
```

Lifecycle/execution mutation order is:

```text
Machine → PmSchedule → PmScheduleDate → PmExecution → items/media
```

The explicit recommission/new-schedule creation workflow instead uses:

```text
PmChecksheet → Machine → PmSchedule → PmScheduleDate
```

These are distinct workflows. Do not add `Machine → PmChecksheet` to the
lifecycle/execution order because existing checksheet writers use the opposite
creation order.

## 5. Delivery order

```text
Slice A
  ↓
Slice B + Slice C
  ↓
Slice D
  ↓
Slice E
```

B and C may proceed in parallel only after Slice A passes independent review.
D requires both B and C. E requires D.

## 6. Slice A — Contract & Data Foundation

### Result

Slice A delivered the smallest additive lifecycle data and pure-domain foundation needed by later Schedule and Machine transition services. It did not cut live readers over to lifecycle eligibility.

### Slice A engineering record — 2026-09-14

- **Status:** IMPLEMENTED / VERIFIED / INDEPENDENTLY REVIEWED / CORRECTIONS VERIFIED / MANAGER ACCEPTED / ENGINEERING COMMITTED.
- **Engineering checkpoint:** `23d64f0c6369931820ae9ec1767e2ce3c52ed430` (`feat(pm): add machine and schedule lifecycle foundation`, 13 paths on `develop`).
- **Verification result:** lifecycle finite-state MySQL proof PASS (invalid DB states rejected, legacy boolean mappings preserved, protected history unchanged); migration apply/rollback/reapply PASS; RED VARCHAR control PASS; real machines and `pm_schedules` unchanged during RED; scratch/fixture cleanup zero residual; `LifecycleSchemaTest` PASS; `LifecyclePolicyTest` PASS; changed-writer regression suites PASS.
- **Independent review / corrections:** independent fresh-context reviews PASS; identified review corrections were applied and re-verified.
- **Manager acceptance:** ACCEPTED.
- **Publication:** ENGINEERING COMMIT `23d64f0c6369931820ae9ec1767e2ce3c52ed430` and tracking closure commit `4a7c48a3c9e7463f065c3bf77fbce8e71b93509e` are remotely published. Slice A is CLOSED / PASS / MANAGER ACCEPTED / PUBLISHED.
- **Baseline note:** unrelated full-suite baseline failures are known non-causal baseline findings, not Slice A defects.
- **Dependency sequence:** unchanged — `A → (B + C) → D → E`; Slice B-E remain NOT STARTED / NOT AUTHORIZED.

### Required now

| Entity | Field | Representation and purpose |
| --- | --- | --- |
| `machines` | `lifecycle_status` | Non-null finite state: `active`, `inactive`, or `retired`; default `active`, then legacy backfill maps false to `inactive`. |
| `machines` | `effective_live_from` | Nullable `date`; the Machine reactivation/recommission live boundary. |
| `pm_schedules` | `lifecycle_status` | Non-null finite state: `active`, `paused`, or `ended`; default `active`, then legacy backfill maps false to `paused`. |
| `pm_schedules` | `effective_live_from` | Nullable `date`; the Schedule resume/new-era live boundary. |
| domain | `LifecyclePolicy` | Pure legal-transition, `isLive`, compatibility-projection, and effective-boundary predicates; no persistence. |

The lowercase persisted values match the existing PM status convention. This
document uses uppercase terms for business states. `lifecycle_status` itself is
the terminal provenance: `retired` and `ended` are distinguishable from the
temporary states without guessing from `is_active`.

### Defer

- `lifecycle_changed_at` and `lifecycle_reason` on either entity: they have no
  durable transition event until Slice B/C creates the authoritative service.
- `retired_at`, `retired_reason`, `paused_at`, `ended_at`, and terminal-specific
  reason columns: duplicate the general event fields; introduce only if B/C
  proves a query or audit contract cannot be met with one transition record.
- Actor/audit persistence, transition endpoints/UI, distributed writers,
  executor/reconciler/cron/notification read-side integration, and MySQL
  two-worker lifecycle proof: later slices own these behaviors.

### Compatibility contract for `is_active`

During Slice A, existing production eligibility remains on the legacy
`is_active` contract. `lifecycle_status` is the future canonical lifecycle
representation, and every currently-authorized production mutation of
`is_active` must write its legacy lifecycle projection in the same persistence
operation:

| Entity | `is_active = true` | `is_active = false` |
| --- | --- | --- |
| Machine | `lifecycle_status = active` | `lifecycle_status = inactive` |
| PmSchedule | `lifecycle_status = active` | `lifecycle_status = paused` |

Legacy writers cannot write `retired` or `ended`; those terminal states remain
exclusive to the authoritative Slice B/C transition services.

| Entity | Current production mutation path | Classification | Slice A action |
| --- | --- | --- | --- |
| Machine | `MachineService::create()` | A — mutates | Merge the Machine compatibility projection with the create payload. |
| Machine | `MachineService::update()` via `StoreMachineRequest` / `UpdateMachineRequest` payload | A — mutates | Merge the Machine compatibility projection whenever `is_active` is supplied. |
| Machine | `MachineService::deactivate()` | A — mutates | Persist `is_active = false` and `lifecycle_status = inactive` together. |
| Machine | `MachineService::activate()` | A — mutates | Persist `is_active = true` and `lifecycle_status = active` together. |
| PmSchedule | `PmChecksheetService::syncWizardData()` `updateOrCreate()` | A — mutates | Write the Schedule compatibility projection with the legacy `is_active` value. |
| PmSchedule | `PmScheduleUpdateService::persistScheduleConfig()` `updateOrCreate()` | A — mutates | Write the Schedule compatibility projection with the legacy `is_active` value. |
| Requests/controllers/queries | Read-only lookup, validation, or delegation paths | B — cannot mutate | No direct persistence change; preserve their existing delegation. |
| Future lifecycle endpoints/services | Not implemented | C — deferred | Slice B/C own terminal transitions; Slice D owns full reader/writer cutover. |

Repository audit found no additional production query-builder, relation, or raw
SQL writer that changes `machines.is_active` or `pm_schedules.is_active`.

| Mechanism | Assessment |
| --- | --- |
| Explicit service-level write-through using pure `LifecyclePolicy` projection methods | **Recommended.** Covers the six discovered writers, keeps each persistence mutation visible, and preserves later transition-service ownership. |
| Model event or mutator | Rejected. It hides lifecycle mutation behind the model and makes future terminal transition/audit ownership harder to inspect. |
| Database trigger | Rejected. It creates a second hidden authority, complicates deployment/rollback, and cannot express the later audited terminal workflow clearly. |

**Frozen mechanism:** add two pure compatibility-projection methods to
`LifecyclePolicy`, one for Machine and one for Schedule. Each returns the paired
legacy boolean and allowed non-terminal `lifecycle_status`. The six mutation
paths above explicitly merge that return value into their existing
create/update/`forceFill` operation. This retains one audited mapping without
model events, mutators, triggers, or hidden persistence magic. A bounded
maintenance/write-freeze deployment is required: freeze these mutations, apply
the additive migration/backfill, deploy all six explicit write-through paths,
validate zero mismatches, then reopen writes. Old application workers must not
serve one of those mutations after the migration.

### Boundary contract

Slice A defines two distinct predicates. It does not apply either predicate to
production readers until Slice D.

**New occurrence materialization floor** governs creation/restoration of new
`PmScheduleDate` rows. ADR-009 remains first:

```text
adr009_materialization_start =
  max(operational_from, BusinessDate::today())

adr009_generation_start =
  max(start_date when present, adr009_materialization_start)

new_occurrence_materialization_floor =
  max(
    adr009_generation_start,
    Machine.effective_live_from when present,
    PmSchedule.effective_live_from when present
  )
```

`operational_from = NULL` remains unresolved/fail-closed before either ADR-009
formula is evaluated. The `start_date` term is retained because the current
`PlanningPeriodPolicy::generationStart()` and reconciler already honor an
explicitly later `start_date`.

New rows may be materialized only when Machine and Schedule are both `active`;
the formula is a date floor and does not authorize materialization for a
non-live parent.

**Existing mutable occurrence live-eligibility floor** governs whether an
already-stored mutable occurrence may be selected for a new Executor start,
appear in a live queue, age through overdue/missed processing, or become
notification-eligible. It is a persisted lower bound, not today's date:

```text
persisted_live_eligibility_floor =
  max(
    operational_from,
    start_date when later,
    Machine.effective_live_from when present,
    PmSchedule.effective_live_from when present
  )

existing_mutable_occurrence_is_live =
  Machine.lifecycle_status = active
  AND PmSchedule.lifecycle_status = active
  AND operational_from is not NULL
  AND occurrence is mutable with no canonical execution
  AND occurrence.scheduled_date >= persisted_live_eligibility_floor
```

`BusinessDate::today()` is not a moving executor or live-eligibility floor.
An occurrence created after the persisted resume/reactivation boundary remains
eligible for normal overdue/missed handling on later business days. An
`IN_PROGRESS` occurrence with a canonical execution is not mutable under this
predicate and remains completable through review even if a parent later becomes
non-live.

Contract examples:

1. `operational_from = 1 Sep`, Machine reactivated `10 Sep`, and Schedule
   resumed `15 Sep`: the lifecycle contribution to the materialization floor is
   `15 Sep`; no new occurrence can be created before the maximum of that date
   and the ADR-009 generation start.
2. A Schedule resumed `10 Sep` preserves a mutable `5 Sep` row but suppresses
   it from live work. A legitimately created `11 Sep` row remains live on
   `15 Sep` and may become overdue/missed through normal rules.
3. An `8 Sep` occurrence that already has canonical `IN_PROGRESS` execution
   before a later parent pause/inactivation cannot start a new execution, but
   its existing execution may continue to `WAITING_REVIEW → APPROVED`.

### Migration and backfill plan

1. Add only the four fields above. `lifecycle_status` uses a finite database
   representation with default `active`; `effective_live_from` is nullable.
2. In the same migration, map extant rows from `is_active`: false Machine rows
   to `inactive`, false Schedule rows to `paused`. Never infer `retired` or
   `ended` and never write `effective_live_from` for legacy rows.
3. Deploy within the compatibility write-freeze described above. Validate that
   every production `is_active` write has deployed explicit projection logic
   before re-enabling Machine/Schedule mutation.
4. Do not change `operational_from`, `start_date`, `generate_until`,
   `pm_schedule_dates`, `pm_executions`, snapshots, soft deletes, or protected
   occurrence status.
5. Reuse existing primary-key and parent-relation indexes. Add no standalone
   lifecycle-status index in Slice A because no authorized query path yet needs
   one; assess index shape from actual Slice D queries.
6. Validate non-null state counts, exact boolean mappings, zero inferred
   terminal states, zero boolean/status mismatches, and unchanged
   counts/checksums for protected occurrence and execution rows before/after on
   `db_preventive_maintenance_test` only.
7. Exercise rollback/reapply only in isolated test infrastructure before any
   lifecycle transition uses the columns. After transition data exists,
   production rollback must be forward-only to avoid discarding lifecycle
   evidence.

### Tasks

- [x] Add the additive migration, model casts/fillable contracts, and explicit write-through to every inventoried Machine/Schedule legacy writer; preserve `is_active` fields and current reader query scopes.
- [x] Add pure `LifecyclePolicy` methods for legal transitions, compatibility projection, materialization-floor calculation, and persisted live-eligibility-floor calculation; do not add persistence to the policy.
- [x] Add unit tests before implementation for legal/forbidden transitions, projection, compatibility synchronization, two boundary predicates, and `BusinessDate::today()` semantics.
- [x] Add migration/schema coverage for legacy mappings, no inferred terminal state, nullable boundary, zero writer drift, and no protected-history mutation.
- [x] Run focused tests, relevant PM/Master regressions, schema validation, and independent fresh-context review.

### Acceptance criteria

- [x] Given legacy true/false rows, when Slice A migrates them, then Machine rows become only `active`/`inactive` and Schedule rows become only `active`/`paused`.
- [x] Given any false legacy row, when it is backfilled, then it never becomes `retired` or `ended`.
- [x] Given every inventoried production `is_active` writer, when it creates, updates, activates, deactivates, or upserts its entity, then it persists the paired non-terminal `lifecycle_status` in the same write.
- [x] Given a legacy boolean writer, when it writes `false`, then it cannot create `retired`/`ended`; when it writes `true`, it cannot leave `inactive`/`paused` status behind.
- [x] Given legal and forbidden state pairs, when `LifecyclePolicy` evaluates them, then it exactly matches both accepted state machines.
- [x] Given `operational_from`, later `start_date`, and optional Machine/Schedule boundaries, when the materialization policy evaluates them, then it returns the maximum valid floor and remains unresolved for `operational_from = NULL`.
- [x] Given a mutable pre-boundary occurrence, when live eligibility evaluates it after resume/reactivation, then it is preserved but non-live; given a post-boundary occurrence, a later business date alone does not suppress normal overdue/missed processing.
- [x] Given an existing canonical `IN_PROGRESS` execution, when a parent later becomes non-live, then the mutable-occurrence predicate does not strand its review/approval completion path.
- [x] Given existing protected occurrence/execution/snapshot rows, when the migration runs, then their values and row counts are unchanged.
- [x] Given existing legacy readers remain unmigrated, when Slice A closes, then no live eligibility query has switched wholesale to `lifecycle_status`.
- [x] Focused unit, feature, and migration/schema verification pass, followed by an independent fresh-context review of the full Slice A scope.

### Verification design

| Class | Required proof |
| --- | --- |
| Unit | State matrices, projection mappings, all materialization/eligibility floor permutations, and `BusinessDate` boundary behavior. |
| Feature | Every inventoried legacy writer keeps boolean/status synchronized; Executor, reconciliation, status-sync, Machine Landing, and Master Machine readers remain on their current contract until cutover. |
| Migration/schema | Apply, map, inspect, rollback/reapply in isolated test DB; validate zero mismatches, no terminal inference, and immutable historical rows. |
| Browser | Not required unless Slice A introduces UI, which it must not. |
| MySQL concurrency | Not required for Slice A because it introduces no concurrent lifecycle mutation path; the deployment write-freeze is verified operationally. |

### Review checkpoint

A fresh-context reviewer must verify the full Slice A diff, migration safety,
boolean/status compatibility, ADR-009 provenance preservation, and absence of
reader/writer cutover before B/C may be authorized.

## 7. Slice B — Schedule Lifecycle

### Goal

Implement `ScheduleLifecycleService` as the authoritative transactional,
locked, reason/audit-capable Schedule transition service for pause, resume, and
end. It updates the compatibility projection and applies the new schedule
boundary on resume.

### Acceptance criteria

- [ ] Legal Schedule transitions succeed; `ENDED → ACTIVE` fails closed.
- [ ] New starts and live aging are blocked for paused/ended schedules, while a
  canonical in-progress execution may reach review and approval.
- [ ] Resume sets a new Schedule boundary without changing `operational_from`
  or materializing a backlog.
- [ ] Focused tests and independent fresh-context review pass.

## 8. Slice C — Machine Lifecycle

### Goal

Implement `MachineLifecycleService` as the authoritative transactional,
locked, reason/audit-capable Machine transition service for deactivate,
reactivate, retire, and explicit recommission.

### Acceptance criteria

- [ ] Legal Machine transitions succeed; ordinary `RETIRED → ACTIVE` fails
  closed.
- [ ] Recommission creates a new operational era and new schedule without
  reviving old ended schedules.
- [ ] New starts are blocked but canonical in-progress execution remains
  completable.
- [ ] Focused tests and independent fresh-context review pass.

## 9. Slice D — Distributed Writer Integration

### Goal

Move all lifecycle decisions to the accepted authority and lifecycle-policy
predicates across executor, actionable QR, queue, reconciliation, cron status
aging, notifications, and administration writers.

### Acceptance criteria

- [ ] Every live read-side suppresses non-live work; preserved pre-boundary
  mutable rows do not become executor work or age only because a parent resumed.
- [ ] Every lifecycle writer delegates to the authoritative service and writes
  `is_active` only as the defined compatibility projection.
- [ ] No writer remains able to cause status/projection drift.
- [ ] Focused and related module regressions plus independent fresh-context
  review pass.

## 10. Slice E — Concurrency, UAT, and closure

### Goal

Prove full lifecycle integrity under real MySQL concurrency and user-observable
operational flows, then close TASK-006 only after all prior evidence is accepted.

### Acceptance criteria

- [ ] Real two-worker MySQL proof covers lifecycle/execution contention using
  the accepted lock orders with no deadlock, timeout, duplicate execution, or
  historical mutation.
- [ ] Manual UAT proves pause/resume, retire/recommission, blocked new starts,
  completion of pre-existing canonical execution, and preserved historical
  visibility.
- [ ] Reporting treatment remains explicitly deferred unless an ADR-010
  correctness condition required a narrower rule.
- [ ] Full required regression evidence and an independent fresh-context review
  pass before TASK-006/ADR-010 closure is proposed.

## 11. Out of scope

- Admin lifecycle UI or endpoints in Slice A.
- Historical PM implementation, reporting/KPI redesign, ADR-009 rolling
  extension, stable option catalog, and Breakdown RCA.
- Deletion or rewrite of PM executions, protected schedule dates, snapshots, or
  old occurrences.
- TASK-007 or any additional task for this decomposition.

## 12. Related documents

- `Docs/roadmap.md`
- `Docs/decisions/ADR-010-schedule-and-machine-lifecycle.md`
- `Docs/decisions/ADR-009-live-schedule-operational-window-and-planning.md`
- `Docs/domain/pm-lifecycle.md`
- `Docs/domain/domain-rules.md`
- `specs/product/SCOPE_LATEST.yaml`
- `specs/IMPACT_LATEST.md`
