<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Контекст Bitrix24 не найден</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f7fb;
            display: grid;
            place-items: center;
            min-height: 100vh;
            margin: 0;
            color: #173152;
        }
        .box {
            background: #fff;
            border: 1px solid #d9e3f0;
            border-radius: 12px;
            padding: 20px;
            width: min(560px, 92vw);
        }
        code { background: #eef3fa; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Не найден контекст Bitrix24</h2>
        <p id="status-text">Пробуем получить контекст через Bitrix24 JS API...</p>
        <p>Если не сработает автоматически, откройте приложение из меню Bitrix24 и обновите страницу.</p>
    </div>
<script>
(() => {
    const statusText = document.getElementById('status-text');

    const writeStatus = (text) => {
        if (statusText) {
            statusText.textContent = text;
        }
    };

    const bootstrapFromBitrix = () => {
        if (!window.BX24 || typeof window.BX24.getAuth !== 'function') {
            writeStatus('Bitrix24 JS API недоступен. Откройте страницу из интерфейса Bitrix24.');
            return;
        }

        const auth = BX24.getAuth();

        if (!auth || !auth.access_token) {
            writeStatus('Не удалось получить access token из Bitrix24. Откройте приложение заново из меню Bitrix24.');
            return;
        }

        const params = new URLSearchParams(window.location.search);
        params.set('AUTH_ID', auth.access_token);
        if (auth.refresh_token) params.set('REFRESH_ID', auth.refresh_token);
        if (auth.expires) params.set('AUTH_EXPIRES', String(auth.expires));
        if (auth.member_id) params.set('member_id', auth.member_id);
        if (auth.user_id) params.set('USER_ID', String(auth.user_id));
        if (typeof BX24.getDomain === 'function' && BX24.getDomain()) {
            params.set('DOMAIN', BX24.getDomain());
        }

        params.set('__ctx', '1');
        writeStatus('Контекст получен, перезагружаем приложение...');
        window.location.replace(`${window.location.pathname}?${params.toString()}`);
    };

    if (window.BX24 && typeof window.BX24.init === 'function') {
        BX24.init(bootstrapFromBitrix);
    } else {
        setTimeout(bootstrapFromBitrix, 1200);
    }
})();
</script>
</body>
</html>
