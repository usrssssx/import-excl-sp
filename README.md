# Bitrix24 Excel Import (Laravel)

Веб-приложение в формате Bitrix24 `local app` (`iframe + внешний backend`) для загрузки Excel-строк в смарт-процессы (CRM Dynamic Entities).

## MVP-статус

- Матрица требований и статус реализации: `docs/acceptance.md`
- Эксплуатация (деплой, очередь, бэкапы, мониторинг): `docs/ops.md`

## Реализовано

- Контекст Bitrix24 (`AUTH_ID`, `REFRESH_ID`, `DOMAIN`, `member_id`, `USER_ID`) сохраняется в БД и сессии.
- Список смарт-процессов через `crm.type.list` с фильтрацией по правам.
- Админка прав:
  - вкл/выкл СП;
  - доступ для всех пользователей;
  - доступ по `user ID`;
  - доступ по `department ID`.
- Отдельная админка `Админы приложения` (без правки `.env`).
- Генерация Excel-шаблона по полям `crm.item.fields`.
- Нормализация UF-кодов в шаблоне: `UF_CRM_*` -> `ufCrm_*`.
- Импорт через очередь:
  - чтение/валидация Excel;
  - пакетная отправка в Bitrix (`batch` + `crm.item.add`);
  - прогресс и итоговая статистика;
  - лог ошибок по строкам;
  - выгрузка Excel с ошибками (`Ошибка`).

## Технологии

- PHP 8.3+
- Laravel 12
- MySQL
- PhpSpreadsheet
- Bitrix24 REST API

## Быстрый старт (локально)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Заполните в `.env`:

- `DB_*`
- `APP_URL` (для внешнего доступа)
- `BITRIX24_CLIENT_ID`
- `BITRIX24_CLIENT_SECRET`

Дальше:

```bash
php artisan migrate
php artisan queue:work
php artisan serve
```

## Настройки Bitrix24 Local App

В настройках приложения Bitrix24:

- `Path your handler`: `https://<your-domain>/bitrix/local/app`
- `Path to initial installation handler`: `https://<your-domain>/bitrix/local/install`

Важно:

- открывать приложение только из интерфейса Bitrix24;
- при прямом открытии URL без контекста будет ошибка `Контекст Bitrix24 не найден`.

## Ключевые env-переменные

```env
BITRIX24_CLIENT_ID=
BITRIX24_CLIENT_SECRET=
BITRIX24_OAUTH_SERVER=https://oauth.bitrix.info/oauth/token/
BITRIX24_INTEGRATOR_USER_IDS=
BITRIX24_BATCH_SIZE=50
```

## Маршруты

- `GET|POST /bitrix/local/install` — установка local app
- `GET|POST /bitrix/local/app` — вход в iframe
- `GET /app` — дашборд
- `GET /app/templates/{entityTypeId}` — скачать шаблон
- `POST /app/imports` — создать задачу импорта
- `GET /app/imports/{importJob}` — экран прогресса/результата
- `GET /public/import-errors/{importJobUuid}` — публичная signed-ссылка на файл ошибок
- `GET /app/admin/permissions` — права на СП
- `GET /app/admin/app-admins` — админы приложения

## Git flow

- Разработка: ветка `test`
- Релиз/деплой: ветка `main`

Минимальный процесс:

1. Коммиты в `test`.
2. Проверка smoke-чеков.
3. `merge test -> main`.
4. Деплой только из `main`.

## Ограничения MVP

- Нет проверки подписи входящих запросов Bitrix24 по `application_token`.
- Не покрыты сложные типы полей (например, upload `file` и расширенные привязки).
- Для продакшена обязательны постоянный queue worker, бэкапы и мониторинг.
