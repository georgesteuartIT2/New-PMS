# GS ProjectFlow - Complete system flow

Updated: 11 November 2026. These diagrams reflect the current implementation, including the latest Owner/PM approval changes.

PMO Admin, User and Admin Viewer are system roles. Owner, PM and Team Member are project responsibilities.

## 1. Complete new-project lifecycle

```mermaid
flowchart TD
    A["PMO: Name, subsidiary, description"] --> CODE["Generate unique subsidiary project code"]
    CODE --> FIRST{"First reviewer?"}
    FIRST -->|Owner| O["Owner receives request"]
    O --> OD{"Owner decision"}
    OD -->|Reject with reason| OR["PMO reviews reason and reassigns"]
    OR --> FIRST
    OD -->|Select PM and approve| P["PM receives request"]
    FIRST -->|Direct PM| P
    P --> PD{"PM decision"}
    PD -->|Reject with reason| PR["Owner and PMO see reason"]
    PR --> RE["Owner or PMO reassigns PM"]
    RE --> P
    PD -->|Accept| THANKS["Thank-you popup and plan setup"]
    THANKS --> MODE{"Starting plan"}
    MODE -->|Blank| BLANK["Empty plan"]
    MODE -->|Template| LIB["Browse and search active templates"]
    LIB --> NEWT["Optional: + New template (manual or Excel), saved and auto-selected"]
    NEWT --> COPY
    LIB --> PREVIEW["Preview tasks and dependencies"]
    PREVIEW --> COPY["Use template: copy tasks into project"]
    BLANK --> DATES["Set project dates and optional LKR budget"]
    COPY --> DATES
    DATES --> TASKS["Add/edit tasks manually or upload Excel for review"]
    TASKS --> PEOPLE["Assign people to tasks"]
    PEOPLE --> EXISTS{"Person in system?"}
    EXISTS -->|Yes| ASSIGN["Save assignment and project membership"]
    EXISTS -->|No| AZURE["Search Azure and save person to system"]
    AZURE --> ASSIGN
    ASSIGN --> SAVE["Save and notify team"]
    SAVE --> BASE["Publish plan and capture baseline"]
    BASE --> EMAIL["Queue team emails and inbox updates"]
    EMAIL --> WORK["Team works on assigned tasks"]
    WORK --> UPDATE["Update task status/progress"]
    UPDATE --> PROGRESS["Recalculate parent and project completion"]
    PROGRESS --> MONITOR["Dashboards, Task/Team Monitor, Timeline, Calendar"]
    MONITOR --> NEXT{"Next action?"}
    NEXT -->|Continue| WORK
    NEXT -->|Blocker| ISSUE["Report reason and corrective action"]
    ISSUE --> FOLLOW["Leadership reviews and resolves issue"]
    FOLLOW --> WORK
    NEXT -->|Expense| BUDGET["Record expense and update actual cost"]
    BUDGET --> WORK
    NEXT -->|Closure| CLOSE["Review and submit closure manually"]
    CLOSE --> DONE["Project marked completed; activity recorded"]
    classDef primary fill:#315F58,color:#FFFFFF,stroke:#315F58;
    classDef decision fill:#FFFFFF,color:#315F58,stroke:#315F58;
    class A,THANKS,SAVE,BASE,DONE primary;
    class FIRST,OD,PD,MODE,EXISTS,NEXT decision;
```

## 2. Sign-in and role-based access

```mermaid
flowchart TD
    START["Open system"] --> LOGIN{"Sign-in method"}
    LOGIN -->|Microsoft| MS["Microsoft authentication and callback validation"]
    LOGIN -->|Email/password| LOCAL["Validate local credentials"]
    MS --> VALID{"Registered active account and valid login?"}
    LOCAL --> VALID
    VALID -->|No| DENY["Explain error and deny access"]
    VALID -->|Yes| ROLE{"System role"}
    ROLE -->|PMO Admin| PMO["Portfolio dashboard"]
    ROLE -->|User| USER["My workspace"]
    ROLE -->|Admin Viewer| VIEWER["Portfolio dashboard - view only"]
    PMO --> ADMIN["Projects, templates, monitoring, calendar, people, activity and settings"]
    USER --> INVOLVE{"Project involvement"}
    INVOLVE -->|Owner or PM| LEAD["Full management of that new-workflow project"]
    INVOLVE -->|Other project role| MEMBER["Saved permissions and assigned-task access"]
    VIEWER --> READ["Read authorized portfolio information; no management mutations"]
    LEAD --> PAGES["My projects, All tasks, authorized Team Monitor and Calendar"]
    MEMBER --> PAGES
```

## 3. Daily tasks, progress and monitoring

```mermaid
flowchart TD
    PLAN["Published project plan"] --> LIST["Project task list / All tasks"]
    LIST --> ACTION{"Authorized action"}
    ACTION -->|Team member| OWN["Update assigned work; comments/files as permitted"]
    ACTION -->|Leadership| MANAGE["Edit tasks, subtasks, assignments and dependencies"]
    OWN --> NORMAL["Normalize task status and progress"]
    MANAGE --> NORMAL
    NORMAL --> COUNT["Count completed executable leaf tasks"]
    COUNT --> PERCENT["Project progress = completed leaf tasks / total leaf tasks"]
    PERCENT --> DASH["Refresh progress summaries and dashboards"]
    MANAGE --> SCHEDULE["Calculate schedule forecasts and critical path"]
    SCHEDULE --> HEALTH["Overdue / critical / unassigned / due-soon indicators"]
    HEALTH --> MON["Task Monitor and filtered task lists"]
    DASH --> MON
    LIST --> TEAM["Team Monitor: projects, open work, overdue work, next deadline"]
    TEAM --> FOCUS["Open the relevant project and focus task"]
    MON --> FOCUS
    FOCUS --> LIST
    LIST --> CAL["Calendar: scheduled tasks and meetings"]
```

## 4. Risks, blockers and unread reports

```mermaid
flowchart TD
    REPORT["Authorized user reports a task blocker/delay"] --> SAVE["Save reason, party, follow-up owner, details and revised target"]
    SAVE --> OPEN["Open report linked to task/project"]
    OPEN --> NOTIFY["Notify PMO, PM and follow-up owner"]
    OPEN --> DASH["PMO dashboard: Reported blockers and unread count"]
    OPEN --> RISKS["Project: Risks and Issues"]
    OPEN --> ANALYSIS["Project schedule analysis: reported reason and impact"]
    DASH --> READ["Open and mark report read for this viewer"]
    READ --> RISKS
    RISKS --> ACTION["Review and carry out corrective action"]
    ACTION --> CHECK{"Resolved?"}
    CHECK -->|No| ACTION
    CHECK -->|Authorized confirmation| RESOLVE["Mark Resolved and record activity"]
    RESOLVE --> HISTORY["Keep history; remove from open-blocker summaries"]
```

## 5. Budget and closure

```mermaid
flowchart TD
    TAB["Budget and Closure"] --> ACTION{"Authorized action"}
    ACTION -->|Budget| PLAN["Set/change planned budget in LKR"]
    PLAN --> EXPENSE["Record amount, description, date and optional receipt"]
    EXPENSE --> ACTUAL["Actual cost = sum of recorded expenses"]
    ACTUAL --> REMAIN["Remaining = planned budget minus actual cost"]
    REMAIN --> DISPLAY["Update budget summary, overspending and expense history"]
    ACTION -->|Closure| REVIEW["Review completion and closure information"]
    REVIEW --> SUBMIT["Authorized user submits closure"]
    SUBMIT --> COMPLETE["Current endpoint marks Completed and records closure activity"]
```

## 6. Notifications, email and administration

```mermaid
flowchart LR
    EVENTS["Assignments, decisions, plan publication and blockers"] --> INBOX["Database notifications"]
    INBOX --> BELL["Unread bell count"]
    INBOX --> PAGE["All / Unread notification page"]
    PAGE --> OPEN["Open authorized destination and mark own notification read"]
    EVENTS -->|Email-enabled events| QUEUE["project-notifications queue"]
    QUEUE --> WORKER["Queue worker"]
    SETTINGS["PMO Settings: encrypted email configuration"] --> WORKER
    WORKER --> SMTP["Configured SMTP service"]
    SMTP --> MAIL["Recipient mailbox"]
    PMO["PMO administration"] --> PEOPLE["People and Azure directory"]
    PMO --> ROLES["Roles and permissions"]
    PMO --> SUBS["Subsidiaries and project numbering"]
    PMO --> TEMPLATES["Shared template management"]
    PMO --> SETTINGS
    PMO --> AUDIT["Activity log"]
```

## Important workflow rules

- Reassignment appears only after rejection. Pending requests cannot be reassigned through UI or direct endpoints.
- After Owner rejection, PMO can reassign. After PM rejection, Owner or PMO can reassign the PM. Only PMO replaces an Owner.
- PM acceptance opens plan setup. Team-ready emails are queued after Save and notify team publishes the plan.
- Ordinary team members cannot open unpublished plans.
- Browse templates is inside plan setup. Entered dates are retained. Shared templates are not modified; project task copies can be edited.
- New-workflow Owner and PM have project-level management access, not company administration. Project deletion remains PMO-only.
- Existing projects retain their legacy workflow and saved role policies.
- Progress counts executable leaf tasks, avoiding parent/subtask double counting. Task completion does not automatically close the project.
- Reading a blocker is separate from resolving it. Critical path does not establish a real-world cause for delay.
- Schedule forecasts are estimates and do not replace the approved baseline.
- Current closure behavior: submitting closure marks the project Completed. There is no separate PMO closure-approval stage or enforced all-tasks-complete gate in that endpoint.
- Database inbox delivery is immediate; email delivery requires the queue worker and working SMTP configuration.
- Admin Viewer has portfolio read-only access; management settings and write operations remain protected.
- The final active PMO account is protected against deletion/demotion.

## Using the diagrams

Open this file in a Mermaid-capable Markdown preview or on GitHub. SYSTEM_FLOW.mmd contains the main lifecycle source for Mermaid editors or export. This documentation does not change application behavior.

