# Interface refresh

Shared styling lives in public/css/interface-system.css, loaded by layouts/app.blade.php. It covers navigation, typography, controls, forms, dialogs, dashboard panels, standard records, roles and settings, with mobile and reduced-motion rules. Spreadsheet and Gantt layouts retain their specialised controls.

Project records use compact Owner/PM lines, one workflow status with a short responsibility hint, and horizontal actions. Unpublished plans do not display delay-review buttons. Legacy board/files routes lead to the authorised unified task workspace.

Verification: main PMO and user pages rendered through Laravel; desktop/mobile automated layout checks and visual inspection of Projects and All Tasks. Browser snapshots are local review artifacts. Access/workflow feature tests remain the functional checks. Live Microsoft authentication and email delivery are outside this visual verification.
