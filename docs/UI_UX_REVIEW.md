# GS ProjectFlow ? system UI and UX review

## Design direction
White background (#FFFFFF), light gray surfaces (#F5F6F7), dark green actions (#315F58), charcoal text (#22272B). The interface prioritizes the next action, readable records and consistent controls. Daily workflows use direct links and side panels; optional configuration is progressively revealed.

## Navigation and roles
- PMO Admin: Dashboard, Projects, My Tasks, Calendar, Monitoring, People, Administration.
- User: My Workspace, My Projects, My Tasks, Calendar. Project responsibilities remain separate from these two system roles.
- Reports and portfolio timelines remain contextual secondary actions, absent from the sidebar.
- Administration uses contextual section navigation. Company-wide task/team review is grouped under Monitoring; People remains the account directory.

## Delivered screen changes
| Area | Current behaviour |
| --- | --- |
| Dashboard / My Workspace | Role-specific linked summaries, actionable priorities, upcoming work and project preview. PMO has team follow-up links; managers have direct team-work shortcuts. Decorative charts and repeated summaries removed from the daily workspace. |
| Projects library | Compact linked counts, readable project/manager columns, combined completion/progress, direct project names, list/grid choices and menus that remain inside the viewport. |
| Project workspace | Five primary sections: Tasks, Timeline, Team, Issues & changes, Overview. Changes live with issues; milestones live in Overview. Legacy changes/milestones hashes select their corresponding section. Calendar/Files remain absent from primary tabs as requested. |
| PM acceptance | Compact assignment callout explains the decision and offers Accept/Decline. Activation remains the assigned manager's decision. Decline collects its reason through the existing dialog. |
| Project create | One-page creation, inline template selection and preview without a forced trip to Templates, clear manager approval wording, optional team, date constraints and persistent action footer. |
| Project edit | Overview and table use the same authorized dialog. Details, linked template/Blank, team membership and roles save together. Existing tasks/progress are preserved when changing the linked template. Removing people clears their task assignments. Manager reassignment follows the existing invitation workflow. |
| PMO tasks | Six purposeful columns: task/project, assignee, due date, progress, status/health, next action. Task names open the shared panel. Unassigned work offers Assign person directly; assigned work offers Request update. Feedback stays in context. |
| My tasks | Task name and Update task open the shared panel rather than detouring through the project. Existing inline updates and managed-project views remain available. |
| Task panel | Quick update precedes management settings. Assignment and update shortcuts focus the appropriate controls. Details, schedule, dependencies, files, comments and reported delays remain within the same task context. |
| Calendar | Month controls, filters, project tasks and meetings. Desktop month grid; mobile agenda. More/fewer event controls and direct task panels remain functional. |
| People / workload | Consistent directory, employee/workload actions, restrained summaries, readable tables and contextual navigation. Microsoft/manual user workflows preserved. |
| Templates | Shared form, table and dialog styling; project creation can choose templates inline. Existing schedule builder/import/preview workflows retained. |
| Administration / roles | Consistent configuration destinations, role lists, permission selections and save controls. Existing permission values and enforcement retained. |
| Notifications | Restrained unread markers, All/Unread filters, clear timestamps, direct open actions and useful empty states. |
| Profile / activity / reports / integrations | Shared headings, record surfaces, forms, pagination and responsive layouts. Existing account, audit and reporting actions retained. |
| Sign-in / password | Existing branded sign-in treatment, visible labels, password visibility and inline help preserved. |

## Accessibility and responsive behaviour
- Project sections support arrow keys, Home/End, selected state and labelled tab panels.
- Simple tables gain labelled mobile records; timelines, bulk-edit tables and specialist task grids retain their own layouts.
- Scrollable tables are keyboard accessible; pagination links have readable labels.
- Invalid fields inside optional settings expand their containing section.
- Visible focus, reduced-motion support, larger touch targets, responsive dialogs and task-panel focus restoration.

## Source organization
- public/css/workspace.css: established shell and responsive behaviour.
- public/css/premium.css: established shared components and sign-in treatment.
- public/css/system.css: final system-wide visual rules, dashboard, notifications, record layouts and task-table refinements.
- public/css/project-library.css, project-workspace.css, task-monitor.css: specialist views.
- public/js/system.js: responsive record labels, keyboard project tabs and form accessibility.
- public/js/project-workspace.js: shared task panel, project sections, timelines and existing project interactions.
- public/js/project-editor.js + resources/views/projects/_edit-dialog.blade.php: shared project editor.

## Validation
- All 44 Blade view sources compile and pass PHP parsing; view cache compiles.
- Eight existing scheduling/critical-path/Blade smoke checks pass.
- Rendered inline scripts and changed JavaScript files pass syntax checks.
- Full review: 40 rendered page variants at 1440px and 390px (80 combinations), zero failures. Affected task/project variants rechecked after final refinements.
- Ten additional shared-interaction checks pass at desktop/mobile: PMO/User dashboards, PMO tasks, My tasks and projects-table editor. Panel responses are intercepted with real rendered panel HTML for these browser interaction checks; no forms are submitted there.
- Browser assertions cover page overflow, primary destinations, selected navigation, mobile menu/profile Escape behaviour, project sections and keyboard switching, task search/reset, dialogs, date constraints, inline template selection, calendar event expansion, filters, list/grid state and project menus.
- Controller/database transaction checks verify template/Blank updates, membership save/removal, existing task preservation, rejection of duplicate users/PM membership-role changes, and the shared editor endpoint.
- Transaction checks verify that PMO cannot accept for a manager, manager acceptance activates and captures the baseline, and decline records the decision. All test-created records/updates rolled back.
- Ordinary User requests to administration, templates and reports remain HTTP 403; PMO pages render successfully.

## Verification limits
Browser layout tests use isolated Chrome and HTML rendered by Laravel, not the user's browser login session. Transaction checks exercise controllers and the database but do not establish a complete live authenticated browser submission path. External Microsoft/Outlook, mail delivery and uploads were not exercised end to end. Representative-user usability testing remains the way to validate learnability with the intended audience.

Local screenshots, test helpers and browser reports are ignored review artifacts in storage/app/ui-review/. Existing calendar/meeting and integration work was preserved. No deployment or GitHub push was performed.

## Create Project: focused minimal form
The create page now uses one restrained form with three sections: Project details, Plan & dates, People. Project name/subsidiary share a row, the generated code is quiet inline context, and description sits with the project identity. Blank/Template choices are compact; the template selector remains visible after selection and the task preview is optional. Save draft and Send for PM approval stay in the action footer.

Project manager and Team members remain directly available. Sponsor, Owner and Steering committee remain under Additional project roles. Manual-user creation was removed from this page, including its popup and script branch. All three people pickers retain local search and Azure Add & select. Directory requests are debounced independently, stale searches are cancelled, keyboard selection is supported and feedback is inline. Azure imports save the user before selecting them; repeated imports return the existing saved account instead of a redirect. Old PM, team and governance selections restore after validation.

Scoped sources: resources/views/projects/create.blade.php, public/css/project-create.css, public/js/project-create-accessibility.js. Only eligible active project users are supplied to the local picker. Existing server-side create/approval workflows remain in place.

Validation: 44 Blade templates parse; eight smoke checks pass. Six create workflow combinations (fresh/restored x 1440/1280/390) pass, covering keyboard/local selection, inline template changes/deadlines, Azure PM/team/additional-role selection with intercepted responses and submitted people fields. Real controller/database transaction checks with a mocked directory response verify user persistence, Microsoft account link, repeated import reuse, project invitation/team membership and rollback. No live Microsoft call, email or retained test records were produced.


## Monitoring hub
- New PMO-only /monitoring page, with Task monitoring and Team monitoring tabs. One shared title/navigation; only the selected tab loads its data.
- Sidebar has a dedicated Monitoring destination. My Tasks points to personal assignments; People points to account management. Monitoring is the sole active primary destination on either monitoring tab.
- Task monitoring preserves summaries, filters, export, shared task panel and update requests. Team monitoring uses compact summary counts, primary search/project/workload filters, optional subsidiary/overdue/critical controls and a readable eight-column table. Person names open workload details; the duplicate View column is removed.
- Tab searches/filters are remembered separately within the signed-in user's browser session. Filter forms and pagination retain the active tab. Tab links and popup focus work with the keyboard.
- Legacy PMO monitoring HTML URLs redirect with their query filters to the corresponding new tab. Existing JSON, employee-details, export, request-update and reassignment endpoints remain available with their existing authorization.
- Sources: MonitoringController.php, monitoring/_header.blade.php, public/css/monitoring.css, public/js/monitoring.js and the two existing monitoring views.
- Validation: 14 read-only route/access/JSON/redirect checks pass; 45 Blade templates parse; eight smoke checks pass. 24 affected-page viewport checks passed, with team variants rechecked after table refinement. Six additional monitoring interaction checks (two tabs x desktop/laptop/mobile) pass for filter memory, form tab/search preservation, table alignment, keyboard navigation, task panel and employee workload popup/focus. Popup data responses are intercepted with real rendered/read-only server data. No update request, reassignment, external message or database mutation was submitted during this review.
