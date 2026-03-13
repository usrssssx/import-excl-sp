@extends('layouts.app')

@push('head')
    <script src="https://api.bitrix24.com/api/v1/"></script>
@endpush

@section('content')
    <div class="card" style="max-width:480px;margin:40px auto;text-align:center;padding:32px;">
        <div style="width:52px;height:52px;background:var(--success-bg);border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
        </div>
        <div class="card-title" style="font-size:18px;margin-bottom:8px;">Приложение установлено</div>
        <p id="install-status-text" class="text-muted text-small" style="margin-bottom:24px;">Установка приложения завершается...</p>
        <a class="btn btn-primary" href="{{ route('dashboard.index') }}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Перейти в приложение
        </a>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const statusText = document.getElementById('install-status-text');

    const writeStatus = (text) => {
        if (statusText) {
            statusText.textContent = text;
        }
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

    const showFallbackMessage = () => {
        writeStatus('Не удалось завершить установку автоматически. Откройте приложение из меню Bitrix24.');
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
})();
</script>
@endpush
