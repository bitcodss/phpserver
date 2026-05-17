# docs/archive/

Plan and audit artifacts from shipped work. **Kept for design rationale only — not active.**

For shipped-work summaries + commit hashes: [`../../CHANGELOG.md`](../../CHANGELOG.md).
For current operational state: [`../project-status.md`](../project-status.md).

| File | What it is | Shipped as |
|---|---|---|
| `audit-plan.md` | Pre-work plan for the 2026-05-14 code review | `e777c5d` |
| `audit-findings.md` | 33 findings catalogued during the audit | `e777c5d` closed 24; residuals tracked in `../project-status.md` §5.6 |
| `infra-hardening-plan.md` | Plan for F-006 / F-007 / F-023 (broker sidecar + FPM socket) | `e777c5d` |
| `backup-v2-plan.md` | 5-round-reviewed plan for Backup v2 UI + engine | `67c5aec` |
| `todo.md` | Security baseline checklist (all boxes checked) | `e777c5d` / `4bb1da4` / `3dec623` / `67c5aec` |

If you're tempted to follow a step in one of these files: **stop, check the CHANGELOG and project-status first.** These are historical snapshots and may reference paths, containers, or assumptions that no longer hold.
