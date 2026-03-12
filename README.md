# Bitrix24 Excel Import (Laravel)

Приложение для Bitrix24 (формат `local app`, iframe + внешний backend), которое загружает данные из Excel в элементы смарт-процессов (Dynamic Entity / CRM 2.0).

## Что реализовано

- Работа в формате Bitrix24 local app:
  - endpoint первичной установки
  - endpoint основного обработчика iframe
  - сохранение контекста портала и токенов (`AUTH_ID`, `REFRESH_ID`, `DOMAIN`, `member_id`, `USER_ID`)
- Админ-раздел прав доступа:
  - включение/выключение каждого смарт-процесса
  - доступ для всех пользователей
  - доступ по `user ID`
  - доступ по `department ID`
- Пользовательский раздел:
  - список доступных СП
  - скачивание Excel-шаблона по выбранному СП
  - загрузка Excel-файла
- Импорт:
  - парсинг Excel (первая вкладка)
  - валидация строк
  - создание элементов через `batch` + `crm.item.add`
  - прогресс загрузки (queued/running/completed/failed)
  - логирование ошибок по строкам
  - генерация отдельного Excel с ошибочными строками и колонкой `Ошибка`
- Формат кодов пользовательских полей в шаблоне: `ufCrm_XX_YYYYYY`.

## Технологии

- PHP 8.5+
- Laravel 12
- MySQL (рекомендуется для production; локально можно sqlite)
- PhpSpreadsheet
- Bitrix24 REST API

## Быстрый старт

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Настройте БД в `.env`, затем:

```bash
php artisan migrate
php artisan queue:work
php artisan serve
```

Приложение использует очередь (`QUEUE_CONNECTION=database`) для импорта файлов и отображения прогресса.

## Настройки окружения

```env
BITRIX24_CLIENT_ID=
BITRIX24_CLIENT_SECRET=
BITRIX24_OAUTH_SERVER=https://oauth.bitrix.info/oauth/token/
BITRIX24_INTEGRATOR_USER_IDS=5,12
BITRIX24_BATCH_SIZE=50
```

## Настройка local app в Bitrix24

В карточке local application укажите:

- `Path your handler`: `https://<your-domain>/bitrix/local/app`
- `Path to initial installation handler`: `https://<your-domain>/bitrix/local/install`

Важно:

- local app устанавливается администратором портала;
- открывать приложение нужно из интерфейса Bitrix24 (чтобы передавался контекст `AUTH_ID/DOMAIN/USER_ID`).

Для локальной разработки используйте туннель (например, ngrok/cloudflared), чтобы Bitrix24 мог открыть ваш локальный сервер.

## Основные маршруты

- `GET|POST /bitrix/local/install` — install handler
- `GET|POST /bitrix/local/app` — iframe entry handler
- `GET /app` — дашборд доступных смарт-процессов
- `GET /app/admin/permissions` — админ-настройка прав
- `GET /app/templates/{entityTypeId}` — скачать шаблон
- `POST /app/imports` — загрузить Excel в очередь
- `GET /app/imports/{uuid}` — прогресс задачи
- `GET /app/imports/{uuid}/errors.xlsx` — Excel с ошибками

## Git flow (обязательно)

Рабочая схема веток:

- `test` — разработка и первичные коммиты
- `main` — стабильная ветка для production

Рекомендуемый процесс:

1. Вести разработку в `test`.
2. Проверять в тестовом окружении.
3. Делать merge `test -> main`.
4. Деплоить в production только из `main`.

## Ограничения MVP

- Проверка подписи входящего запроса Bitrix24 не включена (можно добавить отдельно).
- Поддержка сложных составных типов полей (файлы, привязки к сущностям в расширенном режиме) минимальная.
- Для стабильной работы в проде нужен постоянно запущенный worker очереди.
