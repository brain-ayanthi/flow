# PressFlow Laravel/MySQL database සකස් කිරීම

Bundle එකේ `laravel-app/` folder එක සම්පූර්ණ Laravel 12 source skeleton එකකි; `artisan`, `composer.json`, `public/`, `config/`, routes සහ migrations ඇතුළත්. `vendor/` dependencies නොමැති බැවින් Composer මඟින් install කළ යුතුය. XAMPP සඳහා සවිස්තරාත්මක පියවර `backend/docs/XAMPP_SETUP_SI.md` හි ඇත.

## 1. Composer dependencies

XAMPP භාවිත කරන Windows පරිගණකයක bundle root එකේ `setup-xampp-windows.bat` ධාවනය කරන්න. වෙනත් පරිසරයක `laravel-app/` තුළ `composer install` ධාවනය කර `php artisan key:generate` කරන්න.

## 2. හිස් MySQL database එක සාදන්න

`laravel-app/database/create_database.mysql.sql` එක MySQL/MariaDB administrator account එකකින් run කරන්න, නැත්නම් පහත SQL එක භාවිත කරන්න:

```sql
CREATE DATABASE IF NOT EXISTS `pressflow`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

මෙය database එක පමණක් සාදයි; tables Laravel migrations මඟින් සාදනු ලබයි. Production සඳහා database එකට සීමිත අවසර ඇති application user එකක් සකස් කරන්න; `root` production සඳහා භාවිත නොකරන්න.

## 3. `.env` සකස් කර migrations run කරන්න

Actual Laravel project's `.env` file එක `laravel-app/.env` යටතේ ඇත. එහි MySQL settings ඔබගේ local/server account එකට ගළපන්න; password එක source bundle එකට හෝ chat එකට ඇතුළත් නොකරන්න. ඉන්පසු `laravel-app/` තුළ:

```sh
php artisan config:clear
php artisan migrate --seed
php artisan migrate:status
php artisan storage:link
```

`migrate:status` මඟින් database tables සාර්ථකව migrate වී ඇත්ද තහවුරු කරන්න. Feature tests ධාවනය කරන්න:

```sh
php artisan test
```

**Status:** මෙහි PHP, Composer සහ database සේවාවක් නොමැති නිසා sandbox එකේ migrations හෝ tests ධාවනය කර නැත; live database එකක් මෙතැන නිර්මාණය කර නැත. Migration/runtime තහවුරු කිරීම ඔබගේ PHP + MySQL/Laravel පරිසරයේ කළ යුතුය.
