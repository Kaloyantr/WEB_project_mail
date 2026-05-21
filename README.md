# WEB Project Mail

Вътрешна пощенска система, изградена с чист PHP, HTML, CSS и JavaScript.

Проектът няма framework-и, външни библиотеки или CDN. Backend-ът връща JSON, frontend-ът използва `fetch` със session cookies, а базата е MySQL/MariaDB.

## Функционалности

- Login, logout и session authentication
- Роли: `user`, `moderator`, `admin`
- Управление на потребители
- Съобщения, входящи, изпратени и разговори
- Отговори в conversation thread
- Групи и членове на групи
- Topics/реферати
- Anonymous boxes
- Review assignments с автоматично създаване на `Reviewer N`
- Reviews като специален `message_type='review'`
- Moderation queue с approve/reject
- Сигнал за нарушение на анонимността
- Rules module
- CSV import/export
- Audit logs
- Динамична навигация според ролята

## Технологии

- PHP 8+
- XAMPP + MariaDB/MySQL
- PDO MySQL (`pdo_mysql`)git 
- HTML
- CSS
- Vanilla JavaScript

## Структура

```text
frontend/          HTML страници
css/               общ стил
js/                frontend JavaScript модули
backend/           PHP конфигурация, DB и helpers
backend/api/       JSON API endpoints
database/          schema.sql и seed.sql
```

## Инсталация

1. Стартирай Apache/MySQL през XAMPP или поне MariaDB/MySQL услугата.

2. Създай база данни:

```sql
CREATE DATABASE mail_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Импортирай SQL файловете в този ред:

```text
database/schema.sql
database/seed.sql
```

Можеш да ги импортираш през phpMyAdmin или през terminal:

```powershell
mysql -u root mail_system < database/schema.sql
mysql -u root mail_system < database/seed.sql
```

4. Провери `backend/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mail_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## Стартиране

От root папката на проекта:

```powershell
php -c .\php-dev.ini -S 127.0.0.1:8000 -t .
```

След това отвори:

```text
http://127.0.0.1:8000/frontend/login.html
```

`php-dev.ini` включва `pdo_mysql`, което е нужно за PDO връзката към MariaDB/MySQL.

## Тестови акаунти

```text
admin@example.com      / admin123
moderator@example.com  / moderator123
user1@example.com      / user123
user2@example.com      / user123
```

## Роли и достъп

`user`:
- Dashboard
- Messages
- Compose
- Topics
- My Reviews
- Rules
- Logout

`moderator`:
- всичко от `user`
- Users
- Groups
- Anonymous Boxes
- Assignments
- Admin Rules
- Moderation
- Import/Export

`admin`:
- всичко от `moderator`
- Audit Logs

## API

Всички API файлове са в:

```text
backend/api/
```

Основни endpoints:

```text
login.php
logout.php
me.php
users.php
messages.php
conversations.php
groups.php
anonymous_boxes.php
topics.php
assignments.php
rules.php
moderation.php
import.php
export.php
audit_logs.php
```

Всички JSON endpoints използват:
- `requireLogin()`
- `requireRole()` при административни действия
- prepared statements
- session потребител в `$_SESSION['user']`
- `jsonResponse()`
- `logAction()` за важни действия

## CSV Import

Import е достъпен за `moderator` и `admin` през:

```text
frontend/admin-import-export.html
```

Поддържани типове:

```text
users
groups
topics
assignments
anonymous_boxes
```

CSV файловете трябва да имат header ред.

## CSV Export

Export е достъпен за `moderator` и `admin`.

Пример:

```text
http://127.0.0.1:8000/backend/api/export.php?type=users
```

Поддържани типове:

```text
users
groups
anonymous_boxes
assignments
topics
reviews
conversations_by_topic
```

## Проверки

PHP syntax check:

```powershell
Get-ChildItem backend -Recurse -Filter *.php | ForEach-Object {
  php -c .\php-dev.ini -l $_.FullName
}
```

JavaScript syntax check:

```powershell
Get-ChildItem js -Filter *.js | ForEach-Object {
  node --check $_.FullName
}
```

## Важна SQL бележка

Ако базата е създадена от по-стара версия на `schema.sql`, увери се, че `anonymous_boxes` няма глобален unique index само върху `display_name`.

Нужният index е:

```sql
ALTER TABLE anonymous_boxes
DROP INDEX uq_anonymous_boxes_display_name,
ADD UNIQUE KEY uq_anonymous_boxes_topic_display_name (topic_id, display_name),
ADD KEY idx_anonymous_boxes_display_name (display_name);
```

Това позволява `Reviewer 1` да съществува за различни topics.

## Бележки за сигурност

- Обикновен потребител не вижда реалния потребител зад anonymous box.
- Само `moderator` и `admin` виждат реалните anonymous box връзки.
- Потребител не може да достъпва чужди conversations/messages.
- Review може да бъде изпратен само от назначен reviewer.
- Review след deadline се отказва.
- Pending/rejected anonymous messages не се показват на обикновен получател.

