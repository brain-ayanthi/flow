-- Run this once as a MySQL/MariaDB administrator before php artisan migrate.
-- Create a least-privilege application user separately; do not use the DB admin account in production.
CREATE DATABASE IF NOT EXISTS `pressflow`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
