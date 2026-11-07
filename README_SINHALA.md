**GS ProjectFlow V3 upgrade:** Existing database එක update කරන්න කලින් `docs/UPDATE_GUIDE_SINHALA.md` කියවන්න. `migrate:fresh` භාවිත කරන්න එපා.

# GS ProjectFlow V2 - Sinhala Setup Guide

මේ ZIP එක Laravel + PHP + MySQL source project එකක්. **සියලුම main interfaces සහ feature modules** source එකට ඇතුළත්. Full runtime test එක ඔබගේ Windows PC එකේ run කරන්න ඕන. මේ environment එකේ Composer, MySQL සහ Azure credentials නැති නිසා browser end-to-end verification කරන්න බැරි වුණා.

## 1. Install කිරීම

1. ZIP extract කරලා `GS_ProjectFlow_Complete_V2` folder එක VS Code වලින් open කරන්න.
2. Terminal → New Terminal open කරන්න.
3. මෙන්න commands:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

4. MySQL වල `gs_projectflow` කියලා database එකක් create කරන්න. `.env` file එකේ actual DB_USERNAME / DB_PASSWORD දාන්න.
5. ඊට පස්සේ:

```powershell
php artisan migrate --seed
php artisan pmo:create
php artisan serve
```

6. Browser → http://127.0.0.1:8000
7. PMO temp password එකෙන් login වෙලා තමන්ගේ අලුත් password එක දාන්න.

## 2. Sample data දාලා pages බලන්න

PMO account හදලා ඉවර වුණාම local environment එකේ:

```powershell
php artisan demo:seed
```

මේක demo projects දෙකක්, template එකක්, tasks, subtasks, demo PM / team members create කරනවා. New demo user temporary passwords terminal එකේ එක වතාවක් display වෙනවා. Production වල මේ command එක run නොකරන්න.

## 3. Main screens බලන order එක

1. PMO Dashboard → Projects / Pending projects
2. Users → Create user / Add from Azure
3. Project Roles → Default roles / Custom roles
4. Templates → Create template / Add tasks
5. Projects → Create project (Blank / Template + optional team)
6. PM login → Dashboard invitation → Accept
7. Project Overview → Tasks / Task Board / Calendar / Files / Gantt
8. Project → Download Excel → Edit → Import → Preview → Confirm
9. Task Details → Update status / Progress / File uploads / Dependencies / Comments
10. PMO → Portfolio Gantt / Reports / Activity Log / System settings

## 4. Microsoft login

Azure search එක ක්‍රියාත්මක වීමට company IT admin ගෙන් `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`, redirect URI සහ Graph `User.Read.All` permission/admin consent configure කරන්න ඕන.

**Correct workflow:** PMO → Users → Add from Azure → Name Search → Add. Microsoft login මඟින් user account එකක් auto-create වෙන්නේ නැහැ. PMO import/link නොකළ user කෙනෙක්ට access නැහැ.

## 5. Project invitation email

Setup Email feature එකක් **නැහැ**. User manually create කරද්දී temporary password එක one-time පෙන්වනවා. PM ට project assign කරනකොට විතරක් project invitation email + in-app notification යනවා.

New terminal:

```powershell
php artisan queue:work
```

`MAIL_MAILER=log` නම් test emails `storage/logs/laravel.log` ඇතුළේ පේනවා. Real email සඳහා SMTP configure කරන්න.

## 6. Error එකක් ආවොත්

- `composer install` fail: PHP extension / Composer / internet check කරන්න.
- `could not find driver`: PHP `pdo_mysql` enable කරන්න.
- `Access denied for user`: `.env` database username/password check කරන්න.
- `Missing application key`: `php artisan key:generate` run කරන්න.
- `Table not found`: `php artisan migrate --seed` run කරන්න.
- Azure login fail: Tenant, redirect URI, app registration, Graph permissions check කරන්න.
- Notifications නොඑනවා නම්: `php artisan queue:work` run කරන්න.

Application boot වූ පසු:

```powershell
php artisan route:list
php artisan test
```

**සැලකිල්ලට ගන්න:** Syntax checks කරලා තියෙනවා. Laravel/MySQL/browser real integration tests මේ environment එකෙන් කරන්න බැරි වුණා. Production deployment එකකට පෙර ඔයාගේ PC එකේ සහ staging එකේ සියලු workflows test කරන්න ඕන.
