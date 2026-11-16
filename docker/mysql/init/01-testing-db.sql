-- Separate database for automated tests, so tests never touch your local data.
CREATE DATABASE IF NOT EXISTS iibsom_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON iibsom_testing.* TO 'iibsom'@'%';
FLUSH PRIVILEGES;
