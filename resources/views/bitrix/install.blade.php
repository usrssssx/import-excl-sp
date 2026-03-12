@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Приложение установлено</h2>
        <p class="muted">Контекст портала сохранен. Можно открывать рабочий интерфейс загрузки.</p>
        <a class="btn" href="{{ route('dashboard.index') }}">Перейти в приложение</a>
    </div>
@endsection
