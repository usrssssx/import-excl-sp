<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitrix24 local app installation</title>
</head>
<body>
<p>Установка приложения завершается...</p>
<script>
    if (window.BX24 && typeof window.BX24.installFinish === 'function') {
        window.BX24.installFinish();
    } else {
        document.body.insertAdjacentHTML('beforeend', '<p>Не удалось завершить установку автоматически. Откройте приложение из меню Bitrix24.</p>');
    }
</script>
</body>
</html>
