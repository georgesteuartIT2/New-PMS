# GS ProjectFlow UI / UX review

## Current experience
A shared minimal visual system covers authenticated and guest screens. The approved palette is white #FFFFFF, light gray #F5F6F7, dark green #315F58 and charcoal #22272B. Semantic warning/error colours remain for deadlines and status.

Typography uses local Segoe UI fonts, consistent heading sizes, readable body text and tabular numbers. Thin borders, quiet surfaces, modest radii and limited shadows keep the pages clear. Focus states, visible field labels and reduced-motion support are included.

## Navigation
- PMO: Dashboard, Projects, Tasks, Calendar, People, Administration.
- User: My Workspace, My Projects, My Tasks, Calendar.
- Templates belong to Projects; Workload belongs to People.
- Roles, Subsidiaries, Activity and Integrations belong to Administration.
- Project-level responsibilities are separate from the PMO Admin / User system identity.
- Reports and Portfolio Gantt are absent from the sidebar.

## Page coverage
| Area | Result |
| --- | --- |
| Login / password | Clean brand panel, visible sign-in labels, password visibility control, inline help, responsive mobile branding. Shared password screen styling. |
| Dashboard | Compact summaries, consistent table cards and task links; attention items precede secondary information. |
| Projects | Compact linked counts, consistent list/filter controls and direct creation action. |
| Project creation | One form for essentials, dates, manager and optional team; sticky save/draft actions; explicit PM approval wording. |
| Project workspace | Inline summary, compact task toolbar, direct Add task/Subtask, task search/status filters, task drawer and readable mobile task rows. |
| Tasks | Scope selector beside title, linked summary counts, primary search/project/assignee filters, optional advanced filters and mobile task layout. |
| Calendar | Consistent month controls, aligned columns, working More button, direct task drawer and mobile agenda. |
| People / workload | Named actions, consistent cards/controls, compact metrics and refined workload table. |
| Templates / task plan | Consistent configuration, creation forms, reusable-plan dialog surfaces and direct Use action. |
| Roles / permissions | Quiet selected states and descriptive names; technical permission keys hidden from the primary presentation. Permission values unchanged. |
| Administration | Four clear configuration destinations with contextual section navigation. |
| Profile | Inline profile editing with separate password section. |
| Notifications / activity / reports / timelines | Shared tables, statuses, pagination, headings and empty states. Existing behaviours retained. |

## Visual implementation
- public/css/workspace.css: shared responsive structure and navigation.
- public/css/premium.css: brand tokens, typography and common component presentation.
- public/css/project-workspace.css: compact project workspace and mobile task layout.
- public/css/task-monitor.css: task monitoring and assigned-task presentation.

The shared premium stylesheet loads before the two specialist page styles. File version query strings are used for the new visual layers.

## Verification
- 43 Blade templates compiled and passed PHP parsing.
- 8 smoke checks passed.
- Rendered inline JavaScript passed syntax checks.
- 39 rendered page variants checked at 1440px and 390px: 78 browser checks with zero failures; workload action refinement also rechecked separately.
- Checks cover page overflow, six/four primary destinations, active navigation, mobile menu/Escape, profile state/Escape, task search/reset, direct Add task, bulk edit and project tabs.
- Additional checks cover calendar More/mobile agenda, filter submission with search/focus preservation, login password visibility/help, People/Subsidiary create dialogs.
- PMO Administration access renders; an ordinary User receives HTTP 403.
- Screenshots and JSON evidence are ignored local review artifacts in storage/app/ui-review/.

## Limits
The browser uses locally rendered HTML from the real Laravel app in an isolated Chrome session, not the user's browser login session. These results do not establish authenticated CRUD, actual PM approval/decline transitions, uploads, SMTP or Microsoft/Outlook integration end to end. Representative-user usability testing has not been performed.

Existing local calendar/meeting work is retained. No account/password change, real update request, approval, deletion, external message, GitHub push or deployment was performed.
## Projects library and workspace refinement
The Projects list uses one compact title/action row, linked status counts instead of large cards, and a slim search/filter/view toolbar. Templates and Portfolio timeline remain direct secondary actions. Completed-task count and percentage share a Progress column, leaving adequate width for project names and people. Project names are visible green links; duplicate Open/View buttons are removed, with contextual approval/assignment/review actions retained.
Project rows use fixed, deliberate column proportions and readable mobile layouts. Action menus escape table clipping, remain inside the viewport and close with Escape, scroll or resize. List/grid buttons expose their selected state.
Project workspace uses a restrained identity icon, consistent title/meta layout, inline summary and aligned task-number/tree/title columns. Long task names wrap inside their column. Names no longer collide with assignees, and dates retain their own columns.
Styles: public/css/project-library.css for the list; public/css/project-workspace.css for the workspace.
Verification: 5 affected PMO/User page variants at 1440px, 1280px and 390px, 15 browser checks passed. Checks cover desktop name-column width, table/title overflow, list/grid switching and selected state, action-menu bounds/Escape, plus the existing task filters, tabs and dialogs. All 43 Blade templates passed PHP parsing, inline JS passed syntax checks and 8 smoke checks passed. Real approvals/writes were not exercised.
## Project overview / ownership refinement
The workspace header is composed as a single restrained overview panel. Project name, code, subsidiary and status form the identity row. The details row uses labelled Project manager, Schedule and Progress groups, with the actual manager name from the existing manager relation and a Not assigned fallback. Task completion is grouped with progress instead of occupying a separate scattered item.
Mobile places manager and progress together and the schedule on a full-width row. No new interaction is required to see the manager.
Validation: PMO/User project variants passed at 1440px, 1280px and 390px (6 checks). All 43 Blade templates pass PHP parsing. No business data changed.
## Complete project edit dialog
The project editor now includes editable name, description, start/due dates and status using the existing PUT endpoint. Existing code, subsidiary, template/blank origin, selected template name/category/working hours, project manager, creator and creation date are displayed as context. Original baseline dates are available when recorded.
Assigned project members and task assignees are combined without duplicate people; roles are displayed. Visibility and the Manage team handoff follow existing permissions. Template origin is informational and saving details does not replace the task plan. Team changes remain in the existing team workflow.
The dialog uses a two-column desktop composition, stacked mobile sections, a scrollable body and visible Save/Cancel footer. Date constraints update when start changes. Failed project-detail validation preserves old fields and reopens the editor using a dedicated old-input marker.
Files: resources/views/projects/_edit-dialog.blade.php, public/js/project-editor.js, public/css/project-workspace.css.
Verification: template-based PMO and blank User project variants passed at 1440px, 1280px and 390px (6 checks). Assertions cover native dialog size/footer, valid initial fields, external save-button form association, submitted field names, date constraints, setup type, roster count/deduplication and Manage team handoff. Popup screenshots are in storage/app/ui-review/*-edit-dialog.png. All 44 Blade templates pass PHP parsing. Real project saves and validation redirects were not exercised end to end.
### Shared editable project popup
- Project overview and project-table Edit now use the same dialog partial. The table loads the authorized editor on demand; legacy edit URLs open the overview dialog.
- Template can be changed to another active template or Blank; existing tasks, schedules and progress are retained, as explained beside the selector.
- Users with manage_team can add/remove people and change project roles in the popup. Removal clears task assignments in the same transaction. PM roles, duplicates and unauthorized team edits are rejected.
- Manager reassignment remains limited to the existing pre-activation/on-hold workflow and requests acceptance again.
- Validation errors stay in the dialog; successful saves refresh the current page.
- Checked template/blank saves, membership saves/removal, task preservation and invalid roles/duplicates using a rolled-back database transaction. Shared editor endpoint rendered successfully. Four PMO/User desktop/mobile browser combinations passed including template form association and add/remove controls. No test database changes retained.
