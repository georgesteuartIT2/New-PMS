# GS ProjectFlow: screen and route catalogue

This is the application screen map for GS ProjectFlow V2, grouped by visibility. All pages are rendered using Blade and use the approved color tokens.

## Every signed-in, active user
- `/dashboard` — role-specific overview and pending PM invitations
- `/tasks/my` — only assignments belonging to the signed-in user, with inline status update
- `/tasks/{task}` — protected details, dates, status, progress, attachments, updates, dependencies
- `/notifications` — inbox and mark-as-read
- `/profile` — personal name, Microsoft link and password controls
- `/change-password` — mandatory on first temporary-password login

## Project-based pages (permission gated)
- `/projects` — project list for PM and view-only project members, not a team-only data leak
- `/projects/{project}` — project overview, task list and team
- `/projects/{project}/board` — low-click task status board
- `/projects/{project}/calendar` — month calendar for task schedule
- `/projects/{project}/files` — grouped private task attachments
- `/projects/{project}/gantt` — project task timeline
- `/projects/{project}/edit` — permitted project editing

## PMO only
- `/projects/create` — blank or template with PM and optional project members
- `/users` — account creation and management
- `/users/{user}` — account details and memberships
- `/users/azure/search` — search Microsoft Entra by name and click Add
- `/templates` — reusable templates
- `/templates/{template}` — default task editor
- `/templates/{template}/edit` — update template details
- `/roles` — custom role directory, defaults, role editor
- `/projects/{project}/permissions` — permission matrix scoped to a specific project
- `/gantt` — portfolio Gantt
- `/reports` — portfolio summaries and CSV export
- `/activity` — audit history
- `/settings` — integration configuration status

## Controls and interactions
- PM invitation: Accept/Decline on dashboard and project overview
- Inline status change: My Tasks / Board
- Quick Add Task, Export Excel, Import Excel → Preview → Confirm: project overview
- Add/Remove project member: project overview
- Comments, attachments and dependencies: task details

## Intentional restrictions
- Team members do not see a general project list, portfolio Gantt or other people's tasks.
- Microsoft sign-in never registers new users. PMO searches Entra and explicitly adds a user.
- Temp passwords are shown to PMO one time. No setup-email feature.
- Before PM acceptance, other project roles cannot access the project.
- Private attachments are never served as unauthenticated public URLs.
