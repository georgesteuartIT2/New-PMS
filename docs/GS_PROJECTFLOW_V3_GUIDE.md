# GS ProjectFlow V3 | Corporate Workspace Upgrade

This is an **in-place upgrade of the uploaded GS_ProjectFlow_Complete_V2** Laravel application, not a replacement project. All pre-existing modules remain in the project, including Microsoft login, Azure search/add, users, project roles, subsidiaries, templates, task Excel import/export, notifications, PM acceptance, files and reports.

## User experience

- **PMO Dashboard**: projects at risk, critical work, reported blockers and their follow-up owners; PM assignment and pending acceptance requests.
- **PM Dashboard**: PM invitations, next assigned tasks and managed projects.
- **Projects**: compact summary, automatic search and status filtering, direct access to the Portfolio Gantt and next actions.
- **Project Workspace**: one default Gantt-first work plan; task row/bar opens a right-side task panel. The chart can switch between day, week and month; critical work highlights on at-risk projects. The Project Info section stays collapsed until needed.
- **Task panel**: start/end date and time, priority, assignees, inline progress update, quick assignment, schedule editing, dependencies, secure attachments, comments and delay reporting.
- **Template projects**: relative reference-date tasks shift to the new project's start date; dependencies and hierarchy are copied. Project creation is still one page.
- **Palette**: White `#FFFFFF`, Surface `#F5F6F7`, Primary `#315F58`, Text `#22272B`. Subtle warning red is used only for actionable risk/critical warnings.

## Critical Path and delays

The `CriticalPathCalculator` runs a standard forward/backward pass over a DAG of task dependencies using **working minutes** (Monday to Friday, configurable office hours taken from the project template when provided). Main tasks that have subtasks are treated as summary rows, and their dependency relationships are propagated to their leaf tasks to avoid double-counting work.

A critical task has zero calculated float. **Missing dependencies make the CPM result unreliable**. Instead of guessing, the interface asks users to add dependencies. If task schedules fall outside the default weekday working calendar, elapsed time is used as a fallback. Company holidays, custom shifts and external resource capacity are not yet integrated into the CPM engine. Do not treat the forecast as an approved revised deadline.

`ProjectHealthService` distinguishes:
- `on_track`: no recorded blockers, overdue tasks or forecast-overrun signal;
- `at_risk`: overdue task, blocker or estimated forecast beyond the project deadline;
- `overdue`: project end date has passed and project is not closed;
- non-active statuses are not declared behind schedule automatically.

A PM/PMO can report a task delay with **reason**, **involved party**, **follow-up action owner**, and **revised target**. These fields do **not** establish blame or fault; the assigned task owner and the responsible party can differ. An explicit delay report is more reliable than an algorithm's inference of cause.

A new project baseline is saved when its PM accepts the invitation. For existing historical projects, baselines remain empty because the original approved schedule cannot be reconstructed automatically. An authorized PMO/PM can save the current schedule as a baseline once; it cannot be silently overwritten.

## Upgrading an existing Windows/MySQL installation

1. Back up the existing project folder and the **MySQL database**.
2. Extract this ZIP into a separate folder. Do not overwrite the existing `.env` with a sample file.
3. Copy your existing `.env` into the new project folder **locally**, keeping it private.
4. In a terminal opened **inside the folder containing `artisan`**, run:
   ```powershell
   composer install
   php artisan optimize:clear
   php artisan migrate
   php artisan serve
   ```
5. If you use email notifications, run `php artisan queue:work` in a second terminal and configure your mail transport.
6. Existing users, projects, tasks and templates are **not deleted by the V3 migration**.
7. For a fresh installation only, run `php artisan migrate --seed` and `php artisan pmo:create`. **Do not run `migrate:fresh` on an existing database.**

The archive intentionally omits `.env`, application keys, logs, session files, sensitive runtime data, local Git history, and Node modules. It includes source code and dependency manifests. The full bundled version also contains PHP `vendor/` dependencies; Composer is still recommended to verify the user's platform.

## Tests

Run (no database required):
```
php tests/smoke.php
```
Run Laravel tests on a machine that has PHP DOM, mbstring, and a configured testing database:
```
php artisan test
```
Manual verification checklist:

1. PMO signs in; PMO dashboard shows an unassigned draft under Needs Attention.
2. Assign PM and submit; PM gets a notification and reviews tasks before acceptance.
3. PM accepts; project becomes active; other roles gain only permitted access.
4. Create a main task, two subtasks and predecessor dependencies. Confirm the Gantt and CPM.
5. Mark an incomplete task overdue; verify At Risk or Overdue and the dashboard risk list.
6. Report a delay with an external supplier and a separate follow-up owner. Confirm PMO can view both; resolve the report.
7. Open a task bar and update status/progress, edit schedule, upload/download a file, and add comments.
8. Team Member cannot open other employees' tasks or the whole project.
9. Create a template with dated tasks and dependencies, create a project at a different date and confirm the shifted schedule.
10. Verify Microsoft login and live Azure name search/Add with your **company's** Entra ID configuration.

### Verification limitations

This build environment has PHP but lacks the PDO database drivers, DOM and mbstring extensions necessary for MySQL-backed integration tests and PHPUnit UI testing. The developer performed PHP syntax, route-registration, Blade-source compilation, JavaScript syntax and pure scheduling smoke checks, **not** a full browser + MySQL + Entra end-to-end test. Deployment should follow the manual checklist and company QA before production use.
