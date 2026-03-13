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

## 6. Быстрый post-deploy smoke

1. Открыть приложение из Bitrix24 меню.
2. Скачать шаблон выбранного СП.
3. Импортировать 1-2 валидные строки.
4. Проверить создание элементов в CRM.
5. Загрузить файл с ошибкой и скачать `xlsx` ошибок.

## 7. Инцидент: импорт не идёт

Проверить:

```bash
php artisan queue:failed
php artisan queue:retry all
tail -n 200 storage/logs/laravel.log
```

Если очередь не активна, восстановить сервис и повторить импорт.
