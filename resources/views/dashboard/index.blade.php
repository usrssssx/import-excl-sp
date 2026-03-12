@extends('layouts.app')

@section('content')
    @if($loadError)
        <div class="alert alert-error">
            Не удалось загрузить список смарт-процессов: {{ $loadError }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h2>Доступные смарт-процессы</h2>
        <p class="muted">Для каждого СП можно скачать шаблон Excel и сразу отправить заполненный файл в загрузку.</p>

        @if(count($smartProcesses) === 0)
            <div class="alert alert-error">Нет доступных смарт-процессов для загрузки. Обратитесь к администратору приложения.</div>
        @else
            <div class="grid">
                @foreach($smartProcesses as $process)
                    <div class="card" style="padding: 14px;">
                        <div class="inline" style="justify-content: space-between; margin-bottom: 10px;">
                            <h3 style="margin: 0;">{{ $process['title'] }}</h3>
                            <span class="badge">entityTypeId: {{ $process['entityTypeId'] }}</span>
                        </div>

                        <div class="inline" style="margin-bottom: 10px;">
                            <a class="btn btn-outline" href="{{ route('templates.download', $process['entityTypeId']) }}">Скачать шаблон</a>
                        </div>

                        <form action="{{ route('imports.store') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="entity_type_id" value="{{ $process['entityTypeId'] }}">
                            <label>
                                Excel-файл
                                <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                            </label>
                            <div style="margin-top: 10px;">
                                <button class="btn" type="submit">Загрузить в очередь</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Последние загрузки</h2>
        @if($recentImports->isEmpty())
            <p class="muted">Пока нет загрузок.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>ID задачи</th>
                    <th>Смарт-процесс</th>
                    <th>Статус</th>
                    <th>Прогресс</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($recentImports as $job)
                    <tr>
                        <td>{{ $job->uuid }}</td>
                        <td>{{ $job->entity_title ?: ('Entity #'.$job->entity_type_id) }}</td>
                        <td>{{ $job->status }}</td>
                        <td>{{ $job->processed_rows }}/{{ $job->total_rows }}</td>
                        <td>
                            <a class="btn btn-outline" href="{{ route('imports.show', $job) }}">Открыть</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
