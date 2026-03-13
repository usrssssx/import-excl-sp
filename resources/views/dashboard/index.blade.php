@extends('layouts.app')

@section('content')

    @if($loadError)
        <div class="alert alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Не удалось загрузить список смарт-процессов: {{ $loadError }}
        </div>
    @endif

    {{-- Smart-processes section --}}
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header">
            <div>
                <div class="card-title">Смарт-процессы</div>
                <div class="card-subtitle">Скачайте шаблон Excel и загрузите заполненный файл для импорта</div>
            </div>
            <span class="badge badge-blue">{{ count($smartProcesses) }} доступно</span>
        </div>

        @if(count($smartProcesses) === 0)
            <div class="empty">
                <div class="empty-icon">📋</div>
                <div class="text-strong" style="margin-bottom:4px;">Нет доступных смарт-процессов</div>
                <div class="text-muted text-small">Обратитесь к администратору приложения для настройки прав</div>
            </div>
        @else
            <div class="grid grid-2">
                @foreach($smartProcesses as $process)
                    <div class="sp-card">
                        <div class="flex items-center justify-between gap-2">
                            <span class="sp-card-title">{{ $process['title'] }}</span>
                            <span class="badge badge-gray mono">ID {{ $process['entityTypeId'] }}</span>
                        </div>

                        <div>
                            <a class="btn btn-outline btn-sm" href="{{ route('templates.download', $process['entityTypeId']) }}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Скачать шаблон
                            </a>
                        </div>

                        <form action="{{ route('imports.store') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="entity_type_id" value="{{ $process['entityTypeId'] }}">

                            <div class="form-group">
                                <label>Excel-файл</label>
                                <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                                <div class="text-small text-muted" style="margin-top: 6px;">
                                    Разрешены файлы .xlsx, .xls, .csv до 20 МБ.
                                </div>
                            </div>

                            <button class="btn btn-primary" type="submit" style="width:100%;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17,10 12,5 7,10"/><line x1="12" y1="5" x2="12" y2="19"/></svg>
                                Загрузить в очередь
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent imports --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Последние загрузки</div>
                <div class="card-subtitle">История импортов за последние сессии</div>
            </div>
        </div>

        @if($recentImports->isEmpty())
            <div class="empty">
                <div class="empty-icon">🗂️</div>
                <div class="text-muted text-small">Загрузок пока нет</div>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Задача</th>
                            <th>Смарт-процесс</th>
                            <th>Статус</th>
                            <th>Прогресс</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentImports as $job)
                            <tr>
                                <td>
                                    <span class="mono text-muted">{{ substr($job->uuid, 0, 8) }}…</span>
                                </td>
                                <td class="text-strong">
                                    {{ $job->entity_title ?: ('Entity #'.$job->entity_type_id) }}
                                </td>
                                <td>
                                    <span class="status status-{{ $job->status }}">{{ $job->status }}</span>
                                </td>
                                <td style="min-width:120px;">
                                    <div style="font-size:12px;color:var(--text-3);margin-bottom:4px;">
                                        {{ $job->processed_rows }} / {{ $job->total_rows ?: '—' }}
                                    </div>
                                    @if($job->total_rows)
                                        <div class="progress" style="height:5px;">
                                            <div class="progress-bar" style="width:{{ $job->total_rows ? round(($job->processed_rows / $job->total_rows) * 100) : 0 }}%;"></div>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn btn-outline btn-sm" href="{{ route('imports.show', $job) }}">Открыть</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
