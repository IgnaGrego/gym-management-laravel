# Review — SPEC-002: Client Management

## Verdict

```text
PASS
```

## Metadata

| Field | Value |
| --- | --- |
| Specification | docs/specs/SPEC-002.md |
| Architecture | docs/architecture/SPEC-002.md |
| ADRs | docs/adr/ADR-001.md, docs/adr/ADR-002.md |
| Reviewed against | AGENTS.md, ARCHITECTURE.md, docs/requirements/analyst-pass-001.md |
| Reviewer | reviewer (SDD) |
| Date | 2026-08-15 |
| Implementation attempts | 1 |

## Result

The SPEC-002 implementation satisfies its approved specification, its architecture document, and the governing ADRs. Full test suite passes: 79 tests, 307 assertions (SPEC-001 + SPEC-002).

### Functional (FR-001..FR-007) — satisfied

Create with required name/DNI + optional contact/health fields; list and search by name/DNI/email only; detail view including health notes; edit persisting changes; explicit provisioning (CLIENT role, unique login email, password policy); link status shown in detail and list; health fields never in lists or search (verified by tests).

### Business rules (BR-001..BR-009) — enforced

Standalone records; provisioning is the only path that creates a User (client create/edit never does); 1:1 link enforced by both the unique `clients.user_id` index and the Action; ADMIN-only management; unique DNI (DB + validation, create and edit); no hard delete (no policy, no action, `bulkActions([])`); health confidentiality; independent User/Client lifecycles; unique login email.

### Authorization — satisfied

`ClientPolicy` (viewAny/view/create/update = ADMIN-only, no delete) on top of SPEC-001 role helpers (ADR-001); `ProvisionClientUser` re-authorizes server-side (`Gate::authorize` on `update` Client + `create` User); TRAINER/CLIENT receive 403 on all client pages and inside the Action (tested). C-13 isolation satisfied — CLIENT has no client-management access at all.

### Validation — satisfied

Duplicate DNI (ERR-001), missing required fields (ERR-002), duplicate login email (ERR-003), second-link rejection (ERR-004), unauthorized access (ERR-005), email/phone formats (ERR-006), and framework-default password policy (min 8) all covered by tests.

### Persistence — satisfied

`clients` table matches the architecture: `full_name`/`dni` required, `dni` unique, contact/health nullable, `user_id` nullable unique FK → `users.id` with `nullOnDelete` (ADR-002). No SPEC-001 schema changes; provisioning is a single `DB::transaction` (create User with CLIENT role + associate link). No hard deletion.

### Testing — complete and green

Every acceptance criterion AC-1..AC-14 is mapped to a passing test (ClientManagementTest, ClientProvisioningTest, ClientPolicyTest, ClientTest), with server-side authorization assertions only.

### Architecture — satisfied

Matches `docs/architecture/SPEC-002.md` — Filament `ClientResource` (form/table/infolist, health only in forms + detail), `App\Actions\ProvisionClientUser` as the single non-CRUD Action, additive `User::client()` relationship only, no events/jobs/new routes/new seeders. ADR-002 decisions honored (`clients.user_id` link, in-table separated health columns). Documented technical defaults applied for OQ-05 (name snapshot, no sync), DNI format (no regex), and OQ-07/OQ-08.

### Scope — satisfied

No unrelated changes found; SPEC-001 behavior preserved (all SPEC-001 tests pass; UserPolicy/UserResource/auth/seeders unchanged).

## Minor observations (not violations)

1. The Action uses `Role::firstOrCreate(Role::CLIENT)` before attach (equivalent to the architecture's `attach(Role::CLIENT)` given the seeded catalog).
2. Phone validation is length-only per the architecture's own definition (no phone format regex is specified).

## PO confirmations applied

- AD-01..AD-07 confirmed by PO before implementation (recorded in docs/sdd/state.yaml).
- OQ-01..OQ-08 implemented with documented technical defaults; carried forward for PO confirmation before/at Review.
