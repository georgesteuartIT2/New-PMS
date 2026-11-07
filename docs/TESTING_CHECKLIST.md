# GS ProjectFlow V2 — Acceptance Testing Checklist

Test with a clean database and at least these accounts: PMO, PM, Team Member A, Team Member B, Project Sponsor, Project Owner, Steering Committee, custom view-only role. You can use `php artisan demo:seed` in the local environment.

## 1. Authentication and accounts
- [ ] PMO `pmo:create` prints a random temporary password one time.
- [ ] First password login sends user to Change Password, and cannot skip by manually visiting `/dashboard`.
- [ ] New password persists after logout and re-login; old one is rejected.
- [ ] Manual PMO-created user is not sent a setup email.
- [ ] Disabled user login is blocked, including via Microsoft sign-in.
- [ ] Microsoft-only user cannot use a nonexistent local password to log in.
- [ ] User not imported/linked from Entra is denied access after successful Microsoft authentication.
- [ ] Search Entra by name; Add exactly one verified user; repeating Add does not duplicate.
- [ ] Link existing matching manual user through explicit PMO action.
- [ ] Wrong-tenant sign-in cannot be used to acquire another user's access.

## 2. Projects and templates
- [ ] PMO creates a blank project with PM and optional project team in one form.
- [ ] PMO creates and edits a reusable template containing main tasks and nested subtasks.
- [ ] PMO creates a template-based project and imported template tasks are independent copies.
- [ ] Without a PM, project remains Draft.
- [ ] PM assignment generates an in-app + email notification and makes project Pending.
- [ ] Project sponsor/team member cannot view pending project even using its direct URL.
- [ ] Only the invited PM can accept or decline.
- [ ] Acceptance makes project Active and visible to assigned roles.
- [ ] Declined project can be reassigned by PMO.
- [ ] PM full access is limited to own project, not PMO administration.
- [ ] Completed/archived status acts according to read-only policy.

## 3. Tasks and Excel
- [ ] Create main task and several subtasks with start/end date+time.
- [ ] WBS numbering shows 1, 1.1, 1.2, 2 etc. after hierarchy modifications.
- [ ] Task start/end validation blocks end before start.
- [ ] Assign a project member to a task and remove assignment.
- [ ] Team Member A sees only their assigned tasks and cannot open Team Member B task via URL.
- [ ] Sponsor is read-only by default and cannot submit task progress changes.
- [ ] Change status on My Tasks and Board with no extra Save click.
- [ ] Update progress; complete task; project progress recalculates.
- [ ] Task dependencies reject direct/self and transitive cycles.
- [ ] Add comment to own task; restricted view-only role cannot comment.
- [ ] Upload and download supported files to own task, restricted from unauthorized users.
- [ ] Project Files aggregates attachments securely.
- [ ] Export blank `.xlsx` and check Instructions tab.
- [ ] Export template-based project and see populated task rows.
- [ ] Import new tasks; preview then confirm.
- [ ] Reimport changed task codes; updates existing tasks without deleting unrelated tasks.
- [ ] Reject invalid dates, invalid members, missing parent codes, circular parent/dependency links and duplicate codes before saving.

## 4. Gantt, UI and administration
- [ ] PMO Portfolio Gantt contains all projects.
- [ ] Each project Gantt contains scheduled tasks and progress.
- [ ] Calendar month navigation works; task click opens authorized detail.
- [ ] PMO role matrix grants/removes project-specific permissions.
- [ ] Custom role can be created, edited and assigned to a project.
- [ ] All forms show understandable validation errors.
- [ ] Sidebar displays only relevant navigation for PMO, PM, Team Member and view-only user.
- [ ] Smartphone width: sidebar opens/closes, forms and tables remain usable, colors match approved palette.
- [ ] Use keyboard only: forms, buttons, dialogs and navigation are reachable.

## 5. Release requirements
- [ ] `composer install` succeeds on the target PHP version.
- [ ] `php artisan migrate --seed` succeeds against actual MySQL.
- [ ] `php artisan route:list` and `php artisan test` pass.
- [ ] Real SMTP and queue worker tested.
- [ ] Microsoft app registration, tenant and least-privilege Graph admin consent tested.
- [ ] HTTPS, APP_DEBUG=false, secure cookies, backups, file scanning policy and monitoring configured.
- [ ] Organization approves actual account onboarding, file retention and permissions.
