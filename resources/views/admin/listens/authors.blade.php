<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Звіт по авторам прослуховувань</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;margin:16px;color:#111}
        .grid{display:grid;gap:16px}
        .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px}
        .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        label{font-size:14px;color:#374151}
        input[type="date"],select,input[type="number"],input[type="text"]{padding:8px 10px;border:1px solid #d1d5db;border-radius:8px}
        button, .btn{display:inline-block;padding:9px 14px;border-radius:8px;border:1px solid #111;background:#111;color:#fff;text-decoration:none}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
        th{background:#fafafa}
        .muted{color:#6b7280}
        .right{text-align:right}
        .two{display:grid;grid-template-columns:1fr;gap:16px}
        @media (min-width: 1100px){ .two{grid-template-columns:1fr} }
        .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .spacer{flex:1}
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="btn" href="{{ route('admin.listens.stats', ['from'=>request('from'), 'to'=>request('to'), 'group'=>request('group','day'), 'user_id'=>request('user_id')]) }}">Повернутися до загальної статистики</a>
        <div class="spacer"></div>
        <h1 style="margin:0">Звіт по авторам прослуховувань</h1>
    </div>

    <form method="get" class="card grid">
        <div class="row">
            <label>Дата початку:
                <input type="date" name="from" value="{{ $filters['from'] }}">
            </label>
            <label>Дата завершення:
                <input type="date" name="to" value="{{ $filters['to'] }}">
            </label>
            <label>Ідентифікатор користувача (необов’язково):
                <input type="number" name="user_id" value="{{ $filters['user_id'] ?? '' }}">
            </label>
            <label>Пошук за ім’ям автора:
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Введіть ім’я автора">
            </label>
            <label>Сортування:
                <select name="sort">
                    <option value="seconds_desc" @selected(($filters['sort'] ?? '')==='seconds_desc')>за часом прослуховування (спочатку найбільше)</option>
                    <option value="seconds_asc"  @selected(($filters['sort'] ?? '')==='seconds_asc')>за часом прослуховування (спочатку найменше)</option>
                    <option value="name"         @selected(($filters['sort'] ?? '')==='name')>за іменем автора (по абетці)</option>
                </select>
            </label>

            <button type="submit">Застосувати фільтри</button>
            <a class="btn" href="{{ route('admin.listens.authors.export', request()->query()) }}">Експорт звіту по авторам</a>
        </div>

        <div class="row muted">
            <div>Обраний діапазон: {{ $filters['from'] }} — {{ $filters['to'] }}</div>
            @if(!empty($filters['user_id']))
                <div>•</div>
                <div>Фільтр за ідентифікатором користувача: {{ $filters['user_id'] }}</div>
            @endif
            <div>•</div>
            <div>Всього зараховано за період: <strong>{{ number_format($totalSeconds, 0, '.', ' ') }}</strong> секунд</div>
            @php
                $sec = (int)$totalSeconds;
                $h = intdiv($sec, 3600);
                $m = intdiv($sec % 3600, 60);
                $s = $sec % 60;
                $humanTotal = $h > 0
                    ? $h . ' годин ' . $m . ' хвилин'
                    : ($m > 0 ? $m . ' хвилин ' . $s . ' секунд' : $s . ' секунд');
            @endphp
            <div class="muted">({{ $humanTotal }})</div>
        </div>
    </form>

    <div class="two">
        <div class="card">
            <h2>Автори та сумарний час прослуховування за обраний період</h2>
            <table>
                <thead>
                <tr>
                    <th>Ідентифікатор автора</th>
                    <th>Автор</th>
                    <th class="right">Секунди зарахованого прослуховування</th>
                    <th>Зручне відображення часу</th>
                    <th class="right">Кількість книг з прослуховуванням</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    @php
                        $sec = (int)$r['seconds'];
                        $h = intdiv($sec, 3600);
                        $m = intdiv($sec % 3600, 60);
                        $s = $sec % 60;
                        $human = $h > 0
                            ? $h . ' годин ' . $m . ' хвилин'
                            : ($m > 0 ? $m . ' хвилин ' . $s . ' секунд' : $s . ' секунд');
                    @endphp
                    <tr>
                        <td>{{ $r['author_id'] }}</td>
                        <td>{{ $r['author_name'] }}</td>
                        <td class="right">{{ number_format($r['seconds'], 0, '.', ' ') }}</td>
                        <td>{{ $human }}</td>
                        <td class="right">{{ number_format($r['books'], 0, '.', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Немає даних за обраний період.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
