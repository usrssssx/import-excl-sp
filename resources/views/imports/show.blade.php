@extends('layouts.app')

@section('content')

    <div class="card" data-status-url="{{ route('imports.status', $importJob) }}" id="import-status-card">

        <div class="card-header">
            <div>
                <div class="card-title">Импорт #<span class="mono">{{ substr($importJob->uuid, 0, 8) }}</span></div>
                <div class="card-subtitle">{{ $importJob->entity_title ?: ('Entity #'.$importJob->entity_type_id) }}</div>
            </div>
            <span class="status status-{{ $importJob->status }}" id="status-badge" style="font-size:13px;">{{ $importJob->status }}</span>
        </div>

        {{-- Progress bar --}}
        <div style="margin-bottom: 20px;">
            <div class="flex items-center justify-between gap-2" style="margin-bottom: 8px;">
                <span class="text-small text-muted">Прогресс выполнения</span>
                <span class="text-small text-strong" id="progress-pct">0%</span>
            </div>
            <div class="progress">
                <div class="progress-bar" id="progress-bar" style="width: 0;"></div>
            </div>
        </div>

        {{-- Stats grid --}}
        <div class="grid grid-stats" style="margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-value" id="processed-text">{{ $importJob->processed_rows }}</div>
                <div class="stat-label">Обработано</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="success-text" style="color:var(--success);">{{ $importJob->success_rows }}</div>
                <div class="stat-label">Успешно</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="error-text" style="color:var(--danger);">{{ $importJob->error_rows }}</div>
                <div class="stat-label">Ошибки</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-text">{{ $importJob->total_rows ?: '—' }}</div>
                <div class="stat-label">Всего строк</div>
            </div>
        </div>

        {{-- Result actions (shown after completion) --}}
        <div id="result-actions" class="flex flex-wrap gap-2" style="display:none;">
            <a class="btn btn-primary" href="{{ route('dashboard.index') }}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Список смарт-процессов
            </a>
            <a class="btn btn-outline" href="{{ route('dashboard.index') }}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17,10 12,5 7,10"/><line x1="12" y1="5" x2="12" y2="19"/></svg>
                Загрузить ещё файл
            </a>
            <a class="btn btn-danger" id="error-report-link" href="#" style="display:none;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Скачать строки с ошибками
            </a>
        </div>

    </div>

@endsection

@push('scripts')
<script>
(() => {
    const card = document.getElementById('import-status-card');
    if (!card) return;

    const statusUrl   = card.dataset.statusUrl;
    const bar         = document.getElementById('progress-bar');
    const pct         = document.getElementById('progress-pct');
    const statusBadge = document.getElementById('status-badge');
    const processedTx = document.getElementById('processed-text');
    const successTx   = document.getElementById('success-text');
    const errorTx     = document.getElementById('error-text');
    const totalTx     = document.getElementById('total-text');
    const resultAct   = document.getElementById('result-actions');
    const errorLink   = document.getElementById('error-report-link');

    const render = (payload) => {
        const processed = payload.processed_rows ?? 0;
        const total     = payload.total_rows ?? 0;
        const pctVal    = Number.isFinite(payload.progress_percent)
            ? payload.progress_percent
            : (total > 0 ? Math.round((processed / total) * 100) : (payload.finished ? 100 : 0));

        processedTx.textContent = processed;
        successTx.textContent   = payload.success_rows ?? 0;
        errorTx.textContent     = payload.error_rows ?? 0;
        totalTx.textContent     = total || '—';
        bar.style.width         = pctVal + '%';
        pct.textContent         = pctVal + '%';

        // status badge
        statusBadge.textContent = payload.status;
        statusBadge.className   = 'status status-' + payload.status;
        statusBadge.style.fontSize = '13px';
    };

    const finish = (payload) => {
        render(payload);
        resultAct.style.display = 'flex';
        if (payload.error_report_url) {
            errorLink.style.display = '';
            errorLink.href = payload.error_report_url;
        } else {
            errorLink.style.display = 'none';
        }
    };

    const poll = async () => {
        try {
            const res  = await fetch(statusUrl);
            const data = await res.json();
            render(data);
            if (data.status === 'completed' || data.status === 'failed') {
                finish(data);
            } else {
                setTimeout(poll, 1500);
            }
        } catch {
            setTimeout(poll, 3000);
        }
    };

    const initialStatus = '{{ $importJob->status }}';
    if (initialStatus !== 'completed' && initialStatus !== 'failed') {
        poll();
    } else {
        // already done — show correct pct
        const processed = {{ $importJob->processed_rows }};
        const total     = {{ $importJob->total_rows ?? 0 }};
        const pctVal    = total > 0 ? Math.round((processed / total) * 100) : 100;
        bar.style.width   = pctVal + '%';
        pct.textContent   = pctVal + '%';
        resultAct.style.display = 'flex';
        @if($importJob->error_file_path)
            @if(!empty($errorReportUrl))
                errorLink.style.display = '';
                errorLink.href = '{{ $errorReportUrl }}';
            @else
                errorLink.style.display = 'none';
            @endif
        @else
            errorLink.style.display = 'none';
        @endif
    }
})();
</script>
@endpush
