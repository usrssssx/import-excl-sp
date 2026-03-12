@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Права доступа к смарт-процессам</div>
                <div class="card-subtitle">Включайте или отключайте загрузку по СП и задавайте доступ: всем, по user ID или по ID отделов.</div>
            </div>
            <span class="badge badge-blue">{{ count($smartProcesses) }} СП</span>
        </div>

        <div class="table-wrap">
            <table class="permissions-table">
                <thead>
                <tr>
                    <th>Смарт-процесс</th>
                    <th>Включен</th>
                    <th>Всем пользователям</th>
                    <th>User IDs (через запятую)</th>
                    <th>Department IDs (через запятую)</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($smartProcesses as $process)
                    @php
                        $permission = $permissionMap->get($process['entityTypeId']);
                        $userIds = $permission ? implode(',', $permission->users->pluck('bitrix_user_id')->all()) : '';
                        $departmentIds = $permission ? implode(',', $permission->departments->pluck('department_id')->all()) : '';
                    @endphp
                    <tr>
                        <form action="{{ route('admin.permissions.update', $process['entityTypeId']) }}" method="post">
                            @csrf
                            <input type="hidden" name="title" value="{{ $process['title'] }}">
                            <td>
                                <div class="text-strong">{{ $process['title'] }}</div>
                                <div class="text-muted text-small">entityTypeId: {{ $process['entityTypeId'] }}</div>
                            </td>
                            <td>
                                <label class="checkbox-row">
                                    <input type="hidden" name="is_enabled" value="0">
                                    <input type="checkbox" name="is_enabled" value="1" {{ $permission?->is_enabled ? 'checked' : '' }}>
                                    <span>Да</span>
                                </label>
                            </td>
                            <td>
                                <label class="checkbox-row">
                                    <input type="hidden" name="allow_all_users" value="0">
                                    <input type="checkbox" name="allow_all_users" value="1" {{ $permission?->allow_all_users ? 'checked' : '' }}>
                                    <span>Да</span>
                                </label>
                            </td>
                            <td>
                                <input type="text" name="user_ids" value="{{ $userIds }}" placeholder="5,12,28">
                            </td>
                            <td>
                                <input type="text" name="department_ids" value="{{ $departmentIds }}" placeholder="1,9">
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
                            </td>
                        </form>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">Смарт-процессы не найдены.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
