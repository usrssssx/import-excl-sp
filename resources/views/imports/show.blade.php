@extends('layouts.app')

@section('content')
    <div class="card" data-status-url="{{ route('imports.status', $importJob) }}" id="import-status-card">
        <h2>Импорт #{{ $importJob->uuid }}</h2>
        <p class="muted">Смарт-процесс: {{ $importJob->entity_title ?: ('Entity #'.$importJob->entity_type_id) }}</p>

        <div class="progress" style="margin-bottom: 10px;">
            <div class="progress-bar" id="progress-bar" style="width: 0;"></div>
        </div>

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 14px;">
            <div class="card"><strong id="status-text">{{ $importJob->status }}</strong><br><span class="muted small">Статус</span></div>
            <div class="card"><strong id="processed-text">{{ $importJob->processed_rows }}</strong><br><span class="muted small">Обработано</span></div>
            <div class="card"><strong id="success-text">{{ $importJob->success_rows }}</strong><br><span class="muted small">Успешно</span></div>
            <div class="card"><strong id="error-text">{{ $importJob->error_rows }}</strong><br><span class="muted small">С ошибками</span></div>
            <div class="card"><strong id="total-text">{{ $importJob->total_rows }}</strong><br><span class="muted small">Всего строк</span></div>
        </div>

        <div id="result-actions" class="inline" style="display:none;">
            <a class="btn" href="{{ route('dashboard.index') }}">Выбрать другой смарт-процесс</a>
            <a class="btn btn-outline" href="{{ route('dashboard.index') }}">Загрузить еще файл</a>
            <a class="btn btn-danger" id="error-report-link" href="#" style="display:none;">Скачать ошибки</a>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const card = document.getElementById('import-status-card');
    if (!card) {
        return;
    }

    const statusUrl = card.dataset.statusUrl;
    const bar = document.getElementById('progress-bar');
    const statusText = document.getElementById('status-text');
    const processedText = document.getElementById('processed-text');
    const successText = document.getElementById('success-text');
    const errorText = document.getElementById('error-text');
    const totalText = document.getElementById('total-text');
    const resultActions = document.getElementById('result-actions');
    const errorReportLink = document.getElementById('error-report-link');

    const render = (payload) => {
        statusText.textContent = payload.status;
        processedText.textContent = payload.processed_rows;
        successText.textContent = payload.success_rows;
        errorText.textContent = payload.error_rows;
        totalText.textContent = payload.total_rows;
        bar.style.width = `${payload.progress_percent}%`;

        if (payload.finished) {
            resultActions.style.display = 'flex';

            if (payload.error_report_url) {
                errorReportLink.href = payload.error_report_url;
                errorReportLink.style.display = 'inline-block';
            }
        }
    };

    const poll = async () => {
        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            render(payload);

            if (!payload.finished) {
                setTimeout(poll, 2000);
            }
        } catch (e) {
            setTimeout(poll, 3000);
        }
    };

    poll();
})();
</script>
@endpush
