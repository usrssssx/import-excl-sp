<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Bitrix24 Import</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:          #f0f2f8;
            --surface:     #ffffff;
            --surface-2:   #f7f8fc;
            --border:      #e3e8f4;
            --border-2:    #d0d8ef;
            --text:        #0d1b35;
            --text-2:      #4b5c7a;
            --text-3:      #8896b3;
            --accent:      #1a56db;
            --accent-dk:   #1342b5;
            --accent-lt:   #eef3ff;
            --success:     #0f8a4c;
            --success-bg:  #edfaf4;
            --danger:      #c7302e;
            --danger-bg:   #fef0ef;
            --warn-bg:     #fffbeb;
            --warn:        #92610a;
            --radius-sm:   6px;
            --radius:      10px;
            --radius-lg:   16px;
            --shadow:      0 1px 4px rgba(13,27,53,.06), 0 4px 18px rgba(13,27,53,.06);
            --shadow-md:   0 2px 8px rgba(13,27,53,.08), 0 8px 32px rgba(13,27,53,.08);
            --font:        'Onest', sans-serif;
            --mono:        'JetBrains Mono', monospace;
            --transition:  0.18s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        /* ── LAYOUT ── */
        .app-shell {
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: 58px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-logo {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: var(--radius-sm);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .topbar-logo svg { color: #fff; }

        .topbar-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -.2px;
        }

        .topbar-meta {
            font-size: 12px;
            color: var(--text-3);
            margin-top: 1px;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .main-content {
            padding: 24px;
            max-width: 1160px;
            width: 100%;
            margin: 0 auto;
            animation: fadeUp .3s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── CARDS ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .card + .card { margin-top: 16px; }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -.2px;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--text-2);
            margin-top: 2px;
        }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font);
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: var(--radius);
            padding: 8px 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background var(--transition), box-shadow var(--transition), transform var(--transition);
            white-space: nowrap;
            line-height: 1;
        }

        .btn:active { transform: scale(.97); }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 1px 3px rgba(26,86,219,.25);
        }
        .btn-primary:hover { background: var(--accent-dk); box-shadow: 0 2px 8px rgba(26,86,219,.35); }

        .btn-outline {
            background: var(--surface);
            color: var(--accent);
            border: 1px solid var(--border-2);
        }
        .btn-outline:hover { background: var(--accent-lt); border-color: var(--accent); }

        .btn-ghost {
            background: transparent;
            color: var(--text-2);
        }
        .btn-ghost:hover { background: var(--surface-2); color: var(--text); }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-danger:hover { background: #a82523; }

        .btn-sm { font-size: 12px; padding: 6px 10px; }

        /* ── BADGES ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 500;
            font-family: var(--mono);
        }
        .badge-blue  { background: var(--accent-lt); color: var(--accent-dk); }
        .badge-green { background: var(--success-bg); color: var(--success); }
        .badge-red   { background: var(--danger-bg); color: var(--danger); }
        .badge-gray  { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }

        /* ── STATUS CHIPS ── */
        .status { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500; }
        .status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .status-queued::before  { background: #9badc7; }
        .status-running::before { background: #e6a817; animation: pulse 1.2s ease infinite; }
        .status-completed::before { background: var(--success); }
        .status-failed::before { background: var(--danger); }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .4; }
        }

        /* ── ALERTS ── */
        .alert {
            border-radius: var(--radius);
            padding: 11px 14px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid transparent;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #b0e6cc; }
        .alert-error   { background: var(--danger-bg);  color: var(--danger);  border-color: #f6c4c3; }
        .alert-warn    { background: var(--warn-bg);    color: var(--warn);    border-color: #f2d98a; }

        /* ── PROGRESS ── */
        .progress {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: var(--border);
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            width: 0;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent) 0%, #38bdf8 100%);
            transition: width .4s cubic-bezier(.4,0,.2,1);
        }

        /* ── GRID ── */
        .grid { display: grid; gap: 14px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .grid-stats { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }

        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }

        /* ── STAT CARD ── */
        .stat-card {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -1px;
            line-height: 1;
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-3);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-3);
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            white-space: nowrap;
        }
        tbody td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-2);
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background var(--transition); }
        tbody tr:hover { background: var(--surface-2); }

        .mono { font-family: var(--mono); font-size: 12px; }

        /* ── FORM ELEMENTS ── */
        label { display: block; font-size: 13px; font-weight: 500; color: var(--text-2); margin-bottom: 5px; }

        input[type="text"],
        input[type="number"],
        input[type="file"] {
            width: 100%;
            font-family: var(--font);
            font-size: 13px;
            background: var(--surface);
            border: 1px solid var(--border-2);
            border-radius: var(--radius);
            padding: 8px 10px;
            color: var(--text);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
        }
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="file"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26,86,219,.1);
        }

        input[type="checkbox"] {
            accent-color: var(--accent);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: var(--text-2);
        }

        .form-group { margin-bottom: 12px; }

        /* ── DIVIDER ── */
        hr { border: none; border-top: 1px solid var(--border); margin: 18px 0; }

        /* ── TEXT HELPERS ── */
        .text-muted  { color: var(--text-3); }
        .text-small  { font-size: 12px; }
        .text-strong { font-weight: 600; }
        .text-mono   { font-family: var(--mono); }

        /* ── SP CARD ── */
        .sp-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: border-color var(--transition), box-shadow var(--transition);
        }
        .sp-card:hover { border-color: var(--accent); box-shadow: var(--shadow-md); }

        .sp-card-title {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -.2px;
        }

        .file-drop {
            border: 2px dashed var(--border-2);
            border-radius: var(--radius);
            padding: 14px;
            text-align: center;
            transition: border-color var(--transition), background var(--transition);
            cursor: pointer;
        }
        .file-drop:hover { border-color: var(--accent); background: var(--accent-lt); }
        .file-drop input { opacity: 0; position: absolute; pointer-events: none; }

        /* ── EMPTY STATE ── */
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-3);
        }
        .empty-icon { font-size: 36px; margin-bottom: 10px; }

        /* ── ADMIN TABLE ── */
        .permissions-table td:first-child { font-weight: 500; color: var(--text); }

        @media (max-width: 640px) {
            .topbar { padding: 0 14px; }
            .topbar-title { font-size: 14px; }
            .main-content { padding: 14px; }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="app-shell">

    <header class="topbar">
        <div class="topbar-brand">
            <div class="topbar-logo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
            </div>
            <div>
                <div class="topbar-title">Импорт в Smart Process</div>
                <div class="topbar-meta">
                    {{ $portal->domain ?? 'n/a' }}@isset($user)&nbsp;·&nbsp;{{ $user->name ?: ('ID '.$user->bitrix_user_id) }}@endisset
                </div>
            </div>
        </div>

        <nav class="topbar-nav">
            <a href="{{ route('dashboard.index') }}" class="btn btn-ghost btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Смарт-процессы
            </a>
            @isset($user)
                @if($user->canManagePermissions())
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-ghost btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                        Права доступа
                    </a>
                    <a href="{{ route('admin.app-admins.index') }}" class="btn btn-ghost btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Админы приложения
                    </a>
                @endif
            @endisset
        </nav>
    </header>

    <main class="main-content">

        @if(session('status'))
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
