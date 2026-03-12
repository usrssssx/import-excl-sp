<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Контекст Bitrix24 не найден</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Onest', sans-serif;
            background: #f0f2f8;
            color: #0d1b35;
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        .box {
            background: #fff;
            border: 1px solid #e3e8f4;
            border-radius: 16px;
            padding: 36px 32px;
            width: min(480px, 100%);
            box-shadow: 0 4px 24px rgba(13,27,53,.07);
            text-align: center;
        }
        .icon {
            width: 52px; height: 52px;
            background: #fef0ef;
            border-radius: 50%;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
        }
        h2 { font-size: 18px; font-weight: 600; margin-bottom: 10px; letter-spacing: -.2px; }
        p  { font-size: 14px; color: #4b5c7a; line-height: 1.6; }
        code {
            font-family: 'JetBrains Mono', monospace;
            background: #f0f2f8;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 13px;
            color: #1a56db;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c7302e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h2>Контекст Bitrix24 не найден</h2>
        <p id="status-text">Пробуем получить контекст через Bitrix24 JS API...</p>
        <p style="margin-top: 10px;">Если автоматически не сработает, откройте приложение из интерфейса Bitrix24, чтобы передались параметры <code>AUTH_ID</code>, <code>DOMAIN</code> и <code>USER_ID</code>.</p>
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

        const finalizeWithUserId = (userId) => {
            const params = new URLSearchParams(window.location.search);
            params.set('AUTH_ID', auth.access_token);
            if (auth.refresh_token) params.set('REFRESH_ID', auth.refresh_token);
            if (auth.expires) params.set('AUTH_EXPIRES', String(auth.expires));
            if (auth.member_id) params.set('member_id', auth.member_id);
            if (userId && Number(userId) > 0) params.set('USER_ID', String(userId));
            if (typeof BX24.getDomain === 'function' && BX24.getDomain()) {
                params.set('DOMAIN', BX24.getDomain());
            }

            params.set('__ctx', '1');
            writeStatus('Контекст получен, перезагружаем приложение...');
            window.location.replace(`${window.location.pathname}?${params.toString()}`);
        };

        if (auth.user_id && Number(auth.user_id) > 0) {
            finalizeWithUserId(auth.user_id);
            return;
        }

        if (typeof BX24.callMethod === 'function') {
            BX24.callMethod('user.current', {}, (result) => {
                const userId = Number(result?.data?.()?.ID || result?.data?.()?.id || 0);
                finalizeWithUserId(userId);
            });
            return;
        }

        finalizeWithUserId(0);
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
