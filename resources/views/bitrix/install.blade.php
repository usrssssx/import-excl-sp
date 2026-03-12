<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitrix24 local app installation</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
</head>
<body>
<p>Установка приложения завершается...</p>
<script>
    const showFallbackMessage = () => {
        document.body.insertAdjacentHTML('beforeend', '<p>Не удалось завершить установку автоматически. Откройте приложение из меню Bitrix24.</p>');
    };

    const tryInstallFinish = () => {
        if (window.BX24 && typeof window.BX24.installFinish === 'function') {
            window.BX24.installFinish();
            return true;
        }

        if (window.parent && window.parent.BX24 && typeof window.parent.BX24.installFinish === 'function') {
            window.parent.BX24.installFinish();
            return true;
        }

        if (window.top && window.top.BX24 && typeof window.top.BX24.installFinish === 'function') {
            window.top.BX24.installFinish();
            return true;
        }

        return false;
    };

    if (window.BX24 && typeof window.BX24.init === 'function') {
        window.BX24.init(() => {
            if (!tryInstallFinish()) {
                showFallbackMessage();
            }
        });
    } else if (!tryInstallFinish()) {
        setTimeout(() => {
            if (!tryInstallFinish()) {
                showFallbackMessage();
            }
        }, 1200);
    }
</script>
</body>
</html>
