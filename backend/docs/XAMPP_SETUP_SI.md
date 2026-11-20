# XAMPP මත PressFlow Laravel project එක ධාවනය කිරීම (Windows)

Bundle එකේ `laravel-app/` තුළ Laravel 12 source tree එක ඇතුළත්ය—`artisan`, `composer.json`, `public/`, `config/`, routes, migrations සහ PressFlow code සමඟ. Composer dependencies (`vendor/`) bundle එකට ඇතුළත් නොවේ; Windows පරිගණකයේ Composer හරහා ඒවා ස්ථාපනය කළ යුතුය. මෙම setup එක sandbox එකේ ධාවනය කර තහවුරු කර නැත.

## 1. XAMPP සහ Composer සූදානම් කරන්න

1. XAMPP Control Panel එකෙන් **Apache** සහ **MySQL** ආරම්භ කරන්න.
2. `C:\xampp\php\php.ini` විවෘත කර අවශ්‍ය PHP extensions සක්‍රිය කර තිබේද බලන්න; අවම වශයෙන් `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `dom/xml`, `ctype` සහ `tokenizer` තිබිය යුතුයි. `artisan test` හි SQLite in-memory tests සඳහා `pdo_sqlite` ද සක්‍රිය කරන්න. වෙනස් කළ පසු Apache restart කරන්න.
3. Composer ස්ථාපනය කරන විට PHP ලෙස `C:\xampp\php\php.exe` තෝරන්න. Command Prompt එකකින් පරීක්ෂා කරන්න:
   ```bat
   C:\xampp\php\php.exe -v
   composer --version
   ```
   PHP version එක Laravel 12 සඳහා අවශ්‍ය version එකට ගැළපිය යුතුයි.

## 2. Laravel dependencies ස්ථාපනය කරන්න

1. `PressFlow_XAMPP_Laravel_2026-10-12.zip` extract කරන්න.
2. Bundle root එකේ `setup-xampp-windows.bat` ධාවනය කරන්න. එය XAMPP PHP භාවිත කර `laravel-app/` තුළ `composer install`, Sanctum dependencies ස්ථාපනය කරයි; `.env` නොමැති විට පමණක් template එකෙන් සාදා, `APP_KEY` හිස් නම් පමණක් generate කරයි.
3. Script එකෙන් error එකක් ලැබුණොත්, `XAMPP_DIR` value එක `setup-xampp-windows.bat` තුළ ඔබගේ XAMPP path එකට ගළපන්න. Composer install කිරීමේදී internet connection අවශ්‍යයි.

`laravel-app/artisan` යනු Laravel command-line entrypoint එකයි. Bundle එකේ `.env.example` ඇත, නමුත් `.env` file එක ඇතුළත් නොවේ—එමඟින් පවතින shop එකක credentials සහ `APP_KEY` අනවශ්‍ය ලෙස overwrite වීම වැළැක්වේ. `setup-xampp-windows.bat` `.env` නොමැති නම් පමණක් template එක copy කරයි; පවතින `.env` සහ non-empty `APP_KEY` ආරක්ෂා කරයි. `.env.example` හි database values ඔබගේ පරිගණකයේ සැබෑ XAMPP/MySQL credentials ලෙස නොසලකන්න. Migration කිරීමට පෙර host, database, username සහ password ඔබගේම ස්ථාපනයට ගැළපෙන බව පරීක්ෂා කර සකසන්න.

`Please provide a valid cache path` දෝෂය ආවොත්, bundle එකේ නව `laravel-app/config/view.php` තිබෙන බව බලන්න. Command Prompt එකේ app root එකේ සිට පහත commands run කර server එක නැවත ආරම්භ කරන්න:

```bat
if not exist storage\framework\views mkdir storage\framework\views
if not exist storage\framework\cache\data mkdir storage\framework\cache\data
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\logs mkdir storage\logs
C:\xampp\php\php.exe artisan config:clear
```

## 3. MySQL database එක සාදා migrations ධාවනය කරන්න

XAMPP Control Panel එකෙන් MySQL start කර `http://localhost/phpmyadmin` විවෘත කරන්න. **පහත database-creation සහ `migrate --seed` පියවර අලුත්, හිස් database එකක් සඳහා පමණි. දැනට shop data ඇති database එකක් update කරනවා නම් ඒවා skip කර පහළ “දැනට තිබෙන database එක update කිරීම” කොටස පමණක් අනුගමනය කරන්න.** අලුත් install එකක SQL tab එකේ පහත statement එක run කරන්න, නැත්නම් `laravel-app/database/create_database.mysql.sql` import කරන්න:

```sql
CREATE DATABASE IF NOT EXISTS `pressflow`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

ඉන්පසු Command Prompt එකකින්:

```bat
cd /d PATH_TO_EXTRACTED_BUNDLE\pressflow\laravel-app
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan migrate --seed
C:\xampp\php\php.exe artisan migrate:status
C:\xampp\php\php.exe artisan storage:link
```

`migrate:status` මඟින් migrations සාර්ථක බව තහවුරු කරන්න. දැනට තිබෙන PressFlow database එකක මෙම updated bundle එක යොදනවා නම් **database එක නැවත සාදන්න හෝ `migrate:fresh` ධාවනය කරන්න එපා**; `.env` සහ database එක තබාගෙන app files update කර පහත incremental migration එක පමණක් සාමාන්‍යයෙන් ධාවනය කරන්න:

```bat
cd /d PATH_TO_EXTRACTED_BUNDLE\pressflow\laravel-app
C:\xampp\php\php.exe artisan migrate
```

මෙම build එකේ additive migrations `2026_10_12_000001_add_jobcard_snapshots_and_round_off.php` (job-date/contact snapshots සහ round-off fields) සහ `2026_10_13_000001_create_jobcard_masters_and_pricing.php` (tenant-scoped Jobcard Masters සහ Digital/Paper rate matrix) ඇතුළත්ය. `artisan migrate` pending migrations පමණක් යොදයි; දැනට ඇති rows/data delete නොකරයි. Tests ධාවනය කිරීමට:

```bat
C:\xampp\php\php.exe artisan test
```

## 4. Browser එකෙන් විවෘත කිරීම

### ඉක්මන් local check

```bat
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

ඉන්පසු `http://127.0.0.1:8000` විවෘත කරන්න. මෙහි MySQL එක XAMPP වෙතින් භාවිත වන අතර PHP development server එක Laravel වෙතින් ධාවනය වේ. Login එකේ `419 Page Expired` ආවොත්, `.env` තුළ `SESSION_DOMAIN=localhost` පේළිය ඉවත් කර `SESSION_DOMAIN` unset කර තබන්න (127.0.0.1 URL එකට). ඉන්පසු `php artisan config:clear` run කර `127.0.0.1` URL එකම භාවිත කර නැවත login වෙන්න; අවශ්‍ය නම් browser එකේ ඒ site එකේ cookies clear කරන්න.

### XAMPP Apache VirtualHost (විකල්පය)

Apache web root එක Laravel project root එකට නොව **`laravel-app/public`** folder එකට පමණක් යොමු කරන්න. `C:\xampp\apache\conf\extra\httpd-vhosts.conf` තුළ paths ඔබගේ extract කළ ස්ථානයට ගළපා එක් කරන්න:

```apache
<VirtualHost *:80>
    ServerName pressflow.local
    DocumentRoot "C:/path/to/pressflow/laravel-app/public"
    <Directory "C:/path/to/pressflow/laravel-app/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`httpd.conf` තුළ `httpd-vhosts.conf` include කර ඇති බව සහ `mod_rewrite` සක්‍රිය බව බලන්න. Windows hosts file (`C:\Windows\System32\drivers\etc\hosts`) වෙත `127.0.0.1 pressflow.local` එක් කර Apache restart කරන්න. එවිට `laravel-app/.env` හි `APP_URL=http://pressflow.local`, `SESSION_DOMAIN=pressflow.local`, `SANCTUM_STATEFUL_DOMAINS=pressflow.local` සකසන්න.

## ආරක්ෂාව

`.env.example` හි values development-only ය. XAMPP default `root`/හිස් password එක production සඳහා කිසිවිටෙක භාවිත නොකරන්න; `APP_DEBUG=true` production සඳහා තබන්න එපා. `.env` හෝ database passwords share/commit නොකරන්න. No legal tax rates are seeded, and additional POS/purchase/inventory/accounting workflows remain under development.
