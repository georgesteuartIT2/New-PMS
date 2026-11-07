**Updated corporate PMO/PM workspace (V3):** Please read `docs/GS_PROJECTFLOW_V3_GUIDE.md` and `docs/UPDATE_GUIDE_SINHALA.md` before upgrading an existing database.

# GS ProjectFlow V2

A **Laravel 13 + PHP 8.3+ + MySQL** project management application with server-rendered Blade interfaces and small vanilla JavaScript enhancements. This is an expanded source-code deliverable for the project requirements discussed in the chat. It includes application routes, controllers, migrations, models, views, authorization and sample data tooling. It is not a preinstalled `vendor/` bundle or a verified production deployment.

**Design direction:** clean, easy for nontechnical users, minimum clicks. Exact palette: `#FFFFFF` background, `#F5F6F7` surface, `#315F58` primary, `#22272B` text. No frontend framework or npm build step is needed.

## A. Local installation (Windows / Laragon / XAMPP)

Requirements: PHP 8.3 or newer with cURL, PDO MySQL, mbstring, OpenSSL, XML, DOM, ZIP, GD and fileinfo extensions; Composer 2; MySQL 8 or compatible; terminal/VS Code. The environment building this archive did not contain these runtime dependencies, so full integration testing has **not** been performed here.

1. Extract the ZIP. Open the extracted `GS_ProjectFlow_Complete_V2` folder in VS Code.
2. Install packages:

   ```powershell
   composer install
   ```

3. Create `.env` and the application key:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

4. Create a new MySQL database named `gs_projectflow`. Configure `.env` with the actual database username/password:

   ```dotenv
   APP_URL=http://127.0.0.1:8000
   APP_TIMEZONE=Asia/Colombo
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=gs_projectflow
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   The password shown above is an **example for some local development configurations**, not a production recommendation.

5. Create schema and standard roles:

   ```powershell
   php artisan migrate --seed
   ```

6. Create your first PMO administrator:

   ```powershell
   php artisan pmo:create
   ```

   Enter the administrator's email/name. The randomly generated temporary password is displayed once in the terminal. On first **password** sign-in it must be changed. There is **no setup email** feature.

7. Start the application:

   ```powershell
   php artisan serve
   ```

   Open `http://127.0.0.1:8000` in a browser.

8. In a second terminal, start the notification queue worker:

   ```powershell
   php artisan queue:work
   ```

   By default `MAIL_MAILER=log`: notification emails are written to `storage/logs/laravel.log`. Real email delivery requires valid SMTP configuration.

### Optional sample data for visual testing

After `php artisan pmo:create`, while `APP_ENV=local`, run:

```powershell
php artisan demo:seed
```

This creates **fictional, clearly named** sample users, a reusable website template, an active project with sample tasks/subtasks, and a pending PM invitation. New sample users receive randomly generated temporary passwords shown once in the terminal. Running it again does not intentionally duplicate records. **Do not run this in production**; the command blocks non-local environments.

## B. Main pages and real workflows

### Authentication

- `/login`: normal email/password sign-in and Microsoft sign-in button.
- `/change-password`: mandatory first password change after temporary-password login.
- `/profile`: name, sign-in methods and change local password.
- Public registration is intentionally unavailable.

### PMO administration

- `/dashboard`: overview, project counts and quick actions.
- `/users`: create users with randomly generated passwords, view/edit/deactivate, search and reset local passwords.
- `/users/azure/search`: **type employee name → search Microsoft Entra → click Add**. A PMO action adds a verified Azure user to the app; first Microsoft login does **not** automatically register a new account. Duplicate email accounts are not auto-linked.
- `/users/{id}`: account details, Microsoft link state and project assignments.
- `/roles`: standard and custom roles, defaults and custom-role editing.
- `/templates`: create templates and manage reusable main/subtask structures; edit template details.
- `/gantt`: all-project portfolio Gantt.
- `/reports`: company-wide progress and downloadable CSV report.
- `/activity`: activity/audit records.
- `/settings`: sanitized application integration status.

### Projects

- `/projects`: searchable project list filtered by membership and permissions.
- `/projects/create`: create blank/template-based project, assign PM and optional team members **in one form**.
- `/projects/{id}`: overview, task list, team, add member, Quick Add Task, Excel import/export and PM acceptance controls.
- `/projects/{id}/board`: four-column low-click status board.
- `/projects/{id}/calendar`: scheduled tasks by month.
- `/projects/{id}/files`: protected list of uploaded task attachments.
- `/projects/{id}/gantt`: task timeline.
- `/projects/{id}/edit`: project description/dates/status editor.
- `/projects/{id}/permissions`: PMO-managed **project-specific** permission matrix.

### Tasks

- `/tasks/my`: only the signed-in user's assigned tasks, with fast inline status changes.
- `/tasks/{id}`: details, dates/times, parent/subtask assignment, progress, priority, dependencies, people, comments, and private attachments.
- Spreadsheet export is `.xlsx` with a separate Instructions worksheet. Blank project exports have empty task rows; template-based project exports include the tasks copied from the template.
- Import flow is **Download → Edit → Upload → Preview → Confirm**. Matching stable task codes update existing tasks, new codes add tasks, nothing is silently deleted. It validates dates, time, project membership, parent chains and dependency cycles.

## C. Microsoft Entra configuration

Microsoft login and directory search are separate integrations using one company tenant. An administrator must:

1. Register an organization-only web app in **Microsoft Entra ID**.
2. Configure redirect URI `http://127.0.0.1:8000/auth/azure/callback` locally (HTTPS callback in production).
3. Create a client secret and store it only in `.env` (never in Git).
4. Grant the necessary Graph **Application** permission `User.Read.All` and admin consent for server-side user search, applying your organization's least-privilege review.
5. Add:

   ```dotenv
   AZURE_CLIENT_ID=YOUR_APP_CLIENT_ID
   AZURE_CLIENT_SECRET=YOUR_APP_SECRET
   AZURE_TENANT_ID=YOUR_COMPANY_TENANT_GUID
   AZURE_REDIRECT_URI=http://127.0.0.1:8000/auth/azure/callback
   ```

6. Run `php artisan config:clear`.
7. PMO opens **Users → Add from Azure**, searches by employee name and clicks **Add** for the correct person.
8. That user can then use **Continue with Microsoft** on the normal login page.

A company Microsoft account which has **not** been explicitly imported/linked by PMO is denied GS ProjectFlow access. There is no Microsoft self-registration, and no Pending Approval queue. Login is bound to the preprovisioned provider object ID and configured tenant rather than email alone.

## D. Project activation & permissions

- PMO may create a project as a draft, or assign a PM immediately.
- On assigning PM, project moves to `pending`; the PM gets an in-app and email notification.
- PM sees invitation with **Accept** / **Decline**. An acceptance alone activates the project.
- Before acceptance, the other project roles **cannot view** the project. PMO may prepare tasks, members and Excel imports during pending.
- PMO has system-wide management permissions.
- PM has full project-level permissions after acceptance, not organization-wide PMO powers.
- PS, PO and Steering Committee are read-only by default; PMO can change their permissions for specific projects.
- Team Members cannot open project overview or other users' tasks. They see their own tasks, with limited progress/status, comment and file options.
- Custom roles have defaults plus project-specific overrides. All authorization is checked on the server, not only hidden from the UI.

## E. Email, files and background work

- `QUEUE_CONNECTION=database` (default), so run a queue worker for outgoing project assignment notifications.
- `MAIL_MAILER=log` means local emails go into a log; production requires SMTP and queue supervision.
- Uploaded files are stored on the **private local disk** and served only through authenticated, authorized download routes. File types/sizes are validated.
- Account passwords are hashed through Laravel's `hashed` cast. Temporary passwords are shown one time and are not sent by setup email.

## F. Verification and constraints

Recommended after installation:

```powershell
php artisan route:list
php artisan test
php artisan optimize:clear
```

Use `/docs/SCREENS_AND_ROUTES.md` for the screen map and `/docs/TESTING_CHECKLIST.md` for step-by-step acceptance tests.

**Build verification completed:** PHP syntax lint and JavaScript syntax lint; static route/view reference consistency. **Not verified in this environment:** Composer dependency resolution, Laravel boot, actual MySQL migration execution, HTTP end-to-end workflows, real SMTP delivery and company Microsoft Graph authorization. These require your local/staging environment and credentials. The app must pass acceptance tests and a security review before production.

## G. Folder layout

```text
app/
  Console/Commands/        pmo:create, demo:seed
  Http/Controllers/         Auth, Users, Azure, Roles, Projects, Workspaces, Tasks, Excel, Gantt
  Http/Middleware/          account state and forced password change
  Models/                   Eloquent models and relations
  Notifications/            queued assignment notification
  Services/                 permission checks, spreadsheet parser, task numbering, Azure API
bootstrap/                  Laravel boot and middleware aliases
config/                     app, database, auth, services, queue, mail, etc.
database/migrations/         core tables and comments
database/seeders/            standard project roles
public/css/app.css          approved visual theme
public/js/app.js            dialogs, low-click interactions, navigation
resources/views/            login, dashboards, projects, tasks, users, templates, reports
routes/web.php              named, protected HTTP routes
tests/Feature/              project and permission tests
docs/                       screen catalogue, acceptance checklist, project plan
```

## H. Notes on limits

This deliverable is a substantial **application source implementation**, not a claim that every integration is currently live or that production deployment is complete. Any company-specific permission settings, Graph permissions, production SMTP, operational backup policy, domain/HTTPS and independent UAT remain configuration/release tasks.
