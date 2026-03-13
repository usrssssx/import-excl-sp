# Operations Guide

Краткая инструкция по деплою и сопровождению.

## 1. Релизный процесс

1. Разработка в `test`.
2. Проверка smoke (`docs/acceptance.md`).
3. Merge `test -> main`.
4. Деплой только из `main`.

Пример:

```bash
git checkout main
git merge --no-ff test -m "Релиз: merge test в main"
git push origin main
```

## 2. Деплой (Laravel)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Права:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

## 3. Очередь

Импорт работает через `QUEUE_CONNECTION=database`.

- Запуск вручную:

```bash
php artisan queue:work --tries=3 --timeout=120
```

- Рекомендуется `systemd`-сервис (например, `bitrix-import-queue.service`) с `Restart=always`.

## 4. Бэкапы

Рекомендуемые объекты:

- БД MySQL приложения.
- Папка `storage/` (включая `storage/app/private` и логи).

Рекомендуемая частота:

- БД: ежедневно ночью.
- `storage`: ежедневно ночью.
- Retention: минимум 14 дней.

## 5. Мониторинг

Минимум:

- Статус queue-сервиса.
- Количество `jobs` и `failed_jobs`.
- Ошибки в `storage/logs/laravel.log`.
- Свободное место на диске.

Рекомендуется Telegram alerting:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

Фактические пути на сервере:

- Laravel лог: `/var/www/app-for-bitrix24/storage/logs/laravel.log`
- Мониторинг: `/var/log/bitrix24-monitor.log`
- Бэкап: `/var/log/bitrix24-backup.log`
- Скрипт мониторинга: `/usr/local/bin/bitrix24-monitor.sh`
- Скрипт бэкапа: `/usr/local/bin/bitrix24-backup.sh`

Проверка очереди:

```bash
systemctl is-active bitrix-import-queue.service
systemctl status --no-pager bitrix-import-queue.service
php artisan queue:failed
```

## 6. Быстрый post-deploy smoke

1. Открыть приложение из Bitrix24 меню.
2. Скачать шаблон выбранного СП.
3. Импортировать 1-2 валидные строки.
4. Проверить создание элементов в CRM.
5. Загрузить файл с ошибкой и скачать `xlsx` ошибок.

Команды smoke-check после деплоя:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
systemctl restart bitrix-import-queue.service
```

## 7. Инцидент: импорт не идёт

Проверить:

```bash
php artisan queue:failed
php artisan queue:retry all
tail -n 200 storage/logs/laravel.log
```

Если очередь не активна, восстановить сервис и повторить импорт.

## 8. Восстановление из бэкапа

Бэкапы по умолчанию:

- БД: `/var/backups/bitrix24/db/*.sql.gz`
- Storage: `/var/backups/bitrix24/storage/*.tar.gz`

Пример восстановления БД:

```bash
gunzip -c /var/backups/bitrix24/db/db_<name>_<timestamp>.sql.gz | mysql -u <user> -p <db_name>
```

Пример восстановления `storage`:

```bash
tar -xzf /var/backups/bitrix24/storage/storage_<timestamp>.tar.gz -C /var/www/app-for-bitrix24
chown -R www-data:www-data /var/www/app-for-bitrix24/storage
```
