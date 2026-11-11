# Project approval and plan setup

New projects created by PMO use the following workflow:

1. PMO enters name, subsidiary and description, then selects a Project Owner or Project Manager. The subsidiary counter generates a unique project code inside a database transaction.
2. An Owner request appears in the Owner inbox and dashboard. The Owner chooses an active PM and approves, or rejects with a reason.
3. The nominated PM reviews and accepts or rejects. Rejection is visible to the Owner and PMO. Both can reassign a PM; only PMO can replace an Owner.
4. Acceptance opens a thank-you dialog and plan setup. Choose blank or a saved template and enter dates. Template tasks are copied to the project; the shared template is not changed.
5. Prepare tasks in the project workspace: add manually, upload Excel for review, and assign people. During planning, system/Microsoft directory selection can add the selected person to the project team. Azure additions are saved to the system through the existing verified directory endpoint.
6. Save & notify team publishes the plan, captures its baseline and queues one project-ready email per active team member. Repeated publication is rejected. Team members cannot open the unpublished plan.

Leadership access applies only to the designated Owner and PM of new workflow projects, scoped to that project. It grants no company administration permissions and never grants project deletion. Admin Viewer remains read-only. Existing projects keep their prior role policies and approval flow.

Email uses the existing encrypted email settings and project-notifications queue. A queue worker is required. Tests use isolated data and fake mail; they do not send real invitations.

Database fields: owner_user_id, approval_stage, plan_initialized, plan_published_at and approval_reason. Rejections and changes also produce audit records and notifications. Planning dates are placeholders until chosen and are displayed as not scheduled before setup.

## Browsing templates from plan setup

PM and Owner can use Browse templates inside plan setup to search active plans, preview tasks and choose a template. Entered project dates are retained. The project-scoped read-only endpoints authorize the current leadership and PMO. The library provides no shared-template editing controls; shared template management keeps its existing company permission checks.

Reassignment is offered only after rejection. Pending Owner/PM requests cannot be reassigned through the UI or direct endpoints. After Owner rejection PMO can reassign; after PM rejection the Owner or PMO can reassign.

## Budget and template selection / creation during plan setup

After accepting, the PM (or Owner/PMO) sets project dates and an optional planned budget (LKR) in the plan setup dialog; the budget can also be managed later under Budget & Closure.

When choosing **Use a template**:
- The user is directed to the Manage Templates library (`/templates?select_for_project=1&plan_project=...`), preserving any entered dates and budget.
- Existing templates can be filtered, searched, and previewed. Clicking **Continue with Template →** returns directly to plan setup with the chosen template active.
- Clicking **+ Create Template** opens the full Template Builder (`/templates/create?select_for_project=1&plan_project=...`), allowing manual task hierarchy input or Excel upload.
- Saving a new template registers it in the library and immediately returns to the project plan setup with the new template auto-selected.
- Project-scoped access authorizes the designated Owner, PM, and PMO while the plan is in `planning` stage. Company-wide template administration (edit, archive, delete) remains strictly governed by company permissions.
