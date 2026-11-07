# GS ProjectFlow V3 | Update කිරීම

මේ ZIP එක ඔබ upload කළ V2 source code එකටම කළ update එකක්. කලින් users, projects, templates, tasks සහ Microsoft/Azure modules තියාගෙන PMO/PM dashboards, Gantt, Critical Path, delay tracking සහ task side panel වැඩි දියුණු කරලා තියෙනවා.

## Existing system එක upgrade කරන විදිහ

1. දැනට තියෙන project folder එකත් MySQL database එකත් backup කරන්න.
2. අලුත් ZIP එක **වෙනම folder එකකට** extract කරන්න.
3. පැරණි project එකේ `.env` file එක අලුත් project folder එකට **ඔබගේ PC එකේම** copy කරන්න. `.env` එක public share කරන්න එපා.
4. VS Code terminal එකේ `artisan` file එක තියෙන folder එකට ගිහින් commands run කරන්න:

```powershell
composer install
php artisan optimize:clear
php artisan migrate
php artisan serve
```

5. Browser: `http://127.0.0.1:8000`
6. Login වෙලා Dashboard → Projects → Project Open කරලා Work Plan (Gantt) එක බලන්න.
7. Task එකක් click කළාම side panel එකේ dates, times, assignments, status, progress, files, comments සහ `Report a delay` තියෙනවා.
8. PMO Dashboard එකේ `Projects at risk` සහ `Needs your attention` පෙන්වනවා.

**අතිශයින් වැදගත්:** Existing database එකකට `php artisan migrate:fresh` run කරන්න එපා. එය දැනට තියෙන tables/data delete කරන්න පුළුවන්.

## Critical Path ගැන

Critical Path නිවැරදිව calculate කරන්න tasks අතර dependencies අවශ්‍යයි. Dependencies නැත්නම් system එක වැරදි critical tasks හිතලා පෙන්වන්නේ නැහැ. Task owner සහ ප්‍රමාදය ඇති කළ පාර්ශ්වය එකම කෙනෙක් කියලා පෙන්වන්නේ නැහැ. Delay reason, involved party, follow-up owner වෙන වෙනම save වෙනවා.

## Test කිරීම

```powershell
php tests/smoke.php
```

මෙය basic calculation සහ view compilation tests පමණයි. සම්පූර්ණ MySQL, Laravel authentication, Azure සහ email workflows ඔබේ local environment එකේ separately test කළ යුතුයි. විස්තර `docs/GS_PROJECTFLOW_V3_GUIDE.md` තුළ තියෙනවා.
