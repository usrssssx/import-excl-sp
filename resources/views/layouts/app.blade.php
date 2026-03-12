<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Bitrix24 Import</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #15253d;
            --muted: #6b7b93;
            --border: #dde6f2;
            --primary: #0b6bcb;
            --primary-dark: #084f97;
            --danger: #c73a3a;
            --success: #1f8f54;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef3fa 100%);
            color: var(--text);
        }
        .container {
            width: min(1100px, 95vw);
            margin: 24px auto;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 22px rgba(19, 42, 80, 0.05);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }
        h1, h2, h3 { margin-top: 0; }
        .muted { color: var(--muted); }
        .btn {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 9px;
            text-decoration: none;
            padding: 9px 12px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn:hover { background: var(--primary-dark); }
        .btn-outline {
            background: #fff;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        .btn-danger {
            background: var(--danger);
        }
        .badge {
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #edf4ff;
            color: var(--primary);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid var(--border);
            padding: 8px;
            vertical-align: top;
            text-align: left;
        }
        input[type="text"], input[type="file"], input[type="number"] {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            background: #fff;
        }
        label { font-size: 14px; }
        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 14px;
            border: 1px solid transparent;
        }
        .alert-success {
            background: #eaf8ef;
            color: #165734;
            border-color: #b9e8cb;
        }
        .alert-error {
            background: #fdeeee;
            color: #822727;
            border-color: #f4c4c4;
        }
        .progress {
            width: 100%;
            height: 16px;
            border-radius: 99px;
            background: #e7edf6;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #1790ff, #1ec27b);
            transition: width 0.3s ease;
        }
        .inline {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .small { font-size: 13px; }
        @media (max-width: 700px) {
            .container { width: min(640px, 96vw); }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <h1 style="margin-bottom: 4px;">Импорт Excel в Smart Process</h1>
            <div class="muted small">
                Портал: {{ $portal->domain ?? 'n/a' }}
                @isset($user)
                    | Пользователь: {{ $user->name ?: ('ID '.$user->bitrix_user_id) }}
                @endisset
            </div>
        </div>
        <div class="inline">
            <a href="{{ route('dashboard.index') }}" class="btn btn-outline">Список СП</a>
            @isset($user)
                @if($user->canManagePermissions())
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline">Права доступа</a>
                @endif
            @endisset
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
