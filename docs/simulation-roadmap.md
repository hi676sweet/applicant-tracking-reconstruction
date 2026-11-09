# Teamline: workbook roadmap and source map

**Synthetic planning reference. No historical Git data or completed-work record is asserted.**

This document imports Teamline's planning allocation from `portfolio_daily_activity_simulation_2023_2026.xlsx`. The workbook labels its dates and slots as a simulation. This repository is a new November 2026 reconstruction; its Git author and committer dates retain their actual values.

The supplied resume reports LET'S DO YOUR PLAN employment from August 2023 through March 2026. The workbook's narrower project allocations are hypothetical planning choices. They do not establish an individual assignment or an actual development period.

## Workbook allocation

| Field | Simulation value |
| --- | --- |
| Allocation window | 2024-12-02 through 2025-07-31 |
| Planned weekdays | 174 |
| Slots per planned weekday | 6 |
| Synthetic planned slots | 1044 |
| Time zone | JST, UTC+09:00 |
| Workbook status | Synthetic plan |

Slot counts describe the workbook's model. They are not counts of commits created, contributions made, or completed engineering tasks.

## Monthly roadmap

The following months are simulation labels copied from the workbook. The topic list is indicative; it does not mark those topics as historically completed.

| Simulation month | Planned weekdays | Indicative workbook topics |
| --- | ---: | --- |
| 2024-12 | 22 | recruitment workflow scope; pipeline stages; sample candidates; recruiter permissions |
| 2025-01 | 23 | organizations; applications; audit tables; candidate records |
| 2025-02 | 20 | duplicate prevention; pipeline advancement; interview cancellation; candidate intake |
| 2025-03 | 21 | offer transition; role assignment; interview booking; duplicate prevention |
| 2025-04 | 22 | candidate search; interview calendar list; responsive recruiter UI; pipeline overview |
| 2025-05 | 22 | responsive recruiter UI; pipeline overview; Livewire forms; candidate overlap checks |
| 2025-06 | 21 | append-only audit controls; candidate overlap checks; atomic audit records; UTC schedule normalization |
| 2025-07 | 23 | calendar conflict tests; role access tests; pipeline guard tests; audit rollback tests |

## Phase allocation

| Planned phase | Simulated weekdays |
| --- | ---: |
| Architecture & scope | 17 |
| Core data model | 27 |
| Primary workflows | 41 |
| Search & usability | 26 |
| Integrity & security | 25 |
| Regression testing | 21 |
| Polish & documentation | 17 |

## Feature-to-source map

Use these links to inspect how the current demo addresses the workbook's themes. Test links identify existing regression sources; this document does not certify every generic workbook objective or substitute for running the checks.

| Workbook topics | Current implementation | Existing regression sources |
| --- | --- | --- |
| Recruiter permissions, organization scope and role filters | [`app/Models/User.php`](../app/Models/User.php); [`app/Livewire/Pipeline.php`](../app/Livewire/Pipeline.php); [`app/Http/Controllers/WorkspaceController.php`](../app/Http/Controllers/WorkspaceController.php) | [`tests/Feature/WorkspaceTest.php`](../tests/Feature/WorkspaceTest.php) |
| Candidate intake, duplicate prevention and pipeline stages | [`app/Services/HiringWorkflow.php`](../app/Services/HiringWorkflow.php); [`app/Models/Application.php`](../app/Models/Application.php) | [`tests/Feature/WorkspaceTest.php`](../tests/Feature/WorkspaceTest.php); [`tests/Feature/HiringWorkflowTest.php`](../tests/Feature/HiringWorkflowTest.php) |
| Interview booking, UTC times, collisions and cancellation | [`app/Services/HiringWorkflow.php`](../app/Services/HiringWorkflow.php); [`app/Models/Interview.php`](../app/Models/Interview.php) | [`tests/Feature/HiringWorkflowTest.php`](../tests/Feature/HiringWorkflowTest.php) |
| Stale updates, atomic events and append-only audit guards | [`app/Services/HiringWorkflow.php`](../app/Services/HiringWorkflow.php); [`app/Models/ActivityLog.php`](../app/Models/ActivityLog.php); [`database/migrations`](../database/migrations) | [`tests/Feature/HiringWorkflowTest.php`](../tests/Feature/HiringWorkflowTest.php) |
| Recruiter forms, search and read-only account presentation | [`app/Livewire/Pipeline.php`](../app/Livewire/Pipeline.php); [`resources/views`](../resources/views); [`public/teamline.css`](../public/teamline.css) | [`tests/Feature/WorkspaceTest.php`](../tests/Feature/WorkspaceTest.php) |

## Review boundaries

The current demo uses intentionally public accounts and deterministic recruiting rules. It does not integrate a real calendar, send email or supply AI recommendations. The workbook's performance-review topic has no benchmark result attached.

The workbook includes broad polish, accessibility and review topics. There is no formal accessibility certification or performance result attached to this roadmap. Run the checks documented in the [README](../README.md) and inspect the linked cases before making new coverage claims.

## Source traceability

- Workbook: `portfolio_daily_activity_simulation_2023_2026.xlsx`.
- SHA-256: `2603bf41d899d5216e935309785acdd4b4748bccf722b1b5fd2e5161b621b9de`.
- Imported sheets: Overview, Daily Plan, Commit Schedule, Monthly Roadmap, and Repository Notes.
- Machine-readable summary: [simulation-source.json](simulation-source.json).
- Documentation prepared: November 10, 2026, Asia/Tokyo.

Only planning summaries and source references are imported. No scheduled commit messages are executed, no artificial commit sequence is generated, and the workbook does not control repository history.
