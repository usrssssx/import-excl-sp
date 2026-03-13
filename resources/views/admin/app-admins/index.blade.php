@extends('layouts.app')

@section('content')
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header">
            <div>
                <div class="card-title">Админы приложения</div>
                <div class="card-subtitle">Добавляйте админов по Bitrix User ID без изменения .env.</div>
            </div>
            <span class="badge badge-blue">{{ $appAdmins->count() }} вручную добавлено</span>
        </div>

        <form action="{{ route('admin.app-admins.store') }}" method="post" class="flex gap-2 flex-wrap" style="align-items: flex-end;">
            @csrf
            <div class="form-group" style="margin:0; min-width: 260px;">
                <label for="bitrix_user_id">Bitrix User ID</label>
                <input id="bitrix_user_id" type="number" min="1" name="bitrix_user_id" placeholder="Например: 12" required>
            </div>
            <button class="btn btn-primary" type="submit">Добавить админа</button>
        </form>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header">
            <div>
                <div class="card-title">Текущие админы приложения</div>
                <div class="card-subtitle">Эти пользователи получают доступ к настройкам прав внутри приложения.</div>
            </div>
        </div>

        @if($appAdmins->isEmpty())
            <div class="empty">
                <div class="empty-icon">👤</div>
                <div class="text-muted text-small">Список пуст. Добавьте первого администратора по User ID.</div>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Имя в портале</th>
                        <th>Кто выдал</th>
                        <th>Добавлен</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($appAdmins as $admin)
                        @php
                            $adminUser = $knownUsers->get((int) $admin->bitrix_user_id);
                            $grantedByUser = $admin->granted_by_bitrix_user_id
                                ? $knownUsers->get((int) $admin->granted_by_bitrix_user_id)
                                : null;
                        @endphp
                        <tr>
                            <td class="mono">ID {{ $admin->bitrix_user_id }}</td>
                            <td>{{ $adminUser?->name ?: 'Неизвестно (пользователь ещё не заходил в приложение)' }}</td>
                            <td>
                                @if($admin->granted_by_bitrix_user_id)
                                    <span class="mono">ID {{ $admin->granted_by_bitrix_user_id }}</span>
                                    @if($grantedByUser?->name)
                                        · {{ $grantedByUser->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $admin->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                <form action="{{ route('admin.app-admins.destroy', $admin->id) }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Системные менеджеры</div>
                <div class="card-subtitle">Пользователи с ролью администратора Bitrix24 или интегратора из .env.</div>
            </div>
            <span class="badge badge-gray">{{ $systemManagers->count() }} системных</span>
        </div>

        @if($systemManagers->isEmpty())
            <div class="text-muted text-small">Пока нет данных. Список наполняется при входе пользователей в приложение.</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Имя</th>
                        <th>Bitrix Admin</th>
                        <th>Integrator (.env)</th>
                        <th>Последняя активность</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($systemManagers as $manager)
                        <tr>
                            <td class="mono">ID {{ $manager->bitrix_user_id }}</td>
                            <td>{{ $manager->name ?: 'Без имени' }}</td>
                            <td>{{ $manager->is_admin ? 'Да' : 'Нет' }}</td>
                            <td>{{ $manager->is_integrator ? 'Да' : 'Нет' }}</td>
                            <td>{{ $manager->last_seen_at?->format('d.m.Y H:i') ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
