# Impact: TASK-006 — Machine & Schedule Lifecycle

## Target

`Machine`, `PmSchedule`, `PmScheduleDate`, and their current `is_active`-based eligibility are shared domain inputs. TASK-006 Slice A now adds explicit lifecycle state and effective-live boundaries without changing `operational_from`.

## Dependents

- `app/Services/PM/PmExecutionService.php`: resolves executable occurrences and
  writes the next occurrence after submit.
- `app/Services/PM/PmScheduleDateReconciler.php` and
  `app/Console/Commands/PM/ReconcilePmScheduleDatesCommand.php`: currently
  select active schedules for materialization and reconciliation.
- `app/Console/Commands/PM/SyncPmScheduleStatusCommand.php`: ages mutable
  occurrences to overdue/missed.
- `app/Services/PM/MachineLandingService.php`,
  `app/Http/Controllers/PM/MachineLandingController.php`, and
  `app/Http/Controllers/PM/PmExecutorController.php`: expose live executor
  eligibility.
- `app/Services/Master/MachineService.php`: current legacy machine create,
  update, activate, and deactivate writer.
- `app/Http/Controllers/Master/MasterMesinController.php` and
  `app/Http/Requests/Master/{StoreMachineRequest,UpdateMachineRequest}.php`:
  current machine administration path.
- `app/Http/Controllers/Breakdown/{BreakdownInputController,BreakdownCloseController}.php`:
  reads machine activation for a different domain; it must not be silently
  coupled to PM lifecycle semantics.

## Current `is_active` writer inventory

**Machine — requires Slice A compatibility synchronization**

- `MachineService::create()` persists the request-derived `is_active` payload.
- `MachineService::update()` persists request-derived `is_active` through its
  normalized payload.
- `MachineService::deactivate()` writes `is_active = false`.
- `MachineService::activate()` writes `is_active = true`.

`StoreMachineRequest` and `UpdateMachineRequest` validate/normalize input only;
they do not persist a Machine independently.

**PmSchedule — requires Slice A compatibility synchronization**

- `PmChecksheetService::syncWizardData()` calls `PmSchedule::updateOrCreate()`
  with `is_active = true`.
- `PmScheduleUpdateService::persistScheduleConfig()` calls
  `PmSchedule::updateOrCreate()` with `is_active = true`.

No additional production query-builder, relation, or raw-SQL writer that changes
`machines.is_active` or `pm_schedules.is_active` was found. Slice A needs only
these six explicit service-level write-through paths; full authority and
read-side cutover remain Slice B/C/D work.

## Boundary impact

`PlanningPeriodPolicy::materializationStart()` currently returns
`max(operational_from, BusinessDate::today())` and fails closed for
`operational_from = NULL`; the reconciler then applies the later `start_date`
through `generationStart()`. Executor selection, Machine Landing, and
status-sync currently select/age occurrence rows without either lifecycle
boundary. Slice A therefore defines, but does not apply, separate:

- a new-occurrence materialization floor extending the ADR-009 generation
  floor with persisted Machine/Schedule boundaries; and
- a persisted existing-mutable-occurrence eligibility floor, which deliberately
  does not use today's date as a moving cut-off.

## Affected Tasks and Decisions

- TASK-006 / ADR-010 owns the lifecycle state, transition authority, and
  distributed-reader integration.
- ADR-009 remains partial. Its `operational_from` provenance and
  no-historical-backlog contract are dependencies, not implementation scope.
- TASK-002 remains CLOSED / PASS and must not be reopened.
- ADR-003, ADR-004, ADR-005, ADR-006, and ADR-007 remain closed unless a causal
  regression is found.
- Historical PM and reporting classification for preserved pre-boundary mutable
  occurrences are deferred.

## Existing Test Coverage

- `tests/Feature/Feature/PM/PmExecutorTest.php`: execution start/submit,
  historical boundary, and legacy `operational_from = NULL` behavior.
- `tests/Feature/Feature/PM/PmScheduleDateReconcilerTest.php` and
  `ReconcilePmScheduleDatesCommandTest.php`: schedule materialization and
  protected-occurrence preservation.
- `tests/Feature/Feature/PM/SyncPmScheduleStatusCommandTest.php`: overdue and
  missed aging.
- `tests/Feature/Feature/PM/MachineLandingTest.php`: machine landing and PM
  executor availability.
- `tests/Feature/Feature/Master/MasterMesinTest.php` and
  `MasterPmChecksheetOperationalWindowTest.php`: machine administration and
  ADR-009 operational-window behavior.

## Slice A Result

Slice A is implemented, verified, independently reviewed, manager accepted, and committed at `23d64f0c6369931820ae9ec1767e2ce3c52ed430`. The accepted foundation includes lifecycle schema/data fields, finite-state DB representation, explicit compatibility write-through, pure policy/boundary foundation, and verification evidence.

- Lifecycle finite-state MySQL proof: PASS; invalid states rejected.
- Legacy mappings preserved; protected history preserved.
- Migration apply/rollback/reapply: PASS; RED VARCHAR control: PASS.
- Scratch/fixture cleanup: zero residual; real machines and `pm_schedules` unchanged during RED.
- `LifecycleSchemaTest`, `LifecyclePolicyTest`, changed-writer regressions, independent review, and manager acceptance: PASS/accepted.

Transition services, distributed reader/writer cutover, lifecycle concurrency proof, UAT, and TASK-006 closure remain future work in Slice B-E.

## Remaining Gaps for Later Slices

- No live-reader test yet proves that a paused/inactive/ended/retired parent
  suppresses new work while an already canonical IN_PROGRESS execution can
  reach review and approval (Slice D).
- No MySQL two-worker proof yet covers lifecycle mutations and execution
  starts under the agreed lock order (Slice E).

## Risk: High

The target is a shared critical-domain chain with more than ten dependent reader
and writer paths. Incorrect cutover can create live backlog, age in-progress
work, or reinterpret preserved transactions.

## Recommended Action

Prepare bounded tracking publication for Slice A. Do not claim Schedule/Machine transition services or distributed reader cutover are implemented, and do not authorize Slice B-E without the required dependency and review gates.
