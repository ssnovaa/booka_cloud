<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Статистика прослуховувань</title>
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
        .cover{width:40px;height:56px;border-radius:6px;object-fit:cover;background:#f3f4f6}
        .two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media (max-width: 1100px){ .two{grid-template-columns:1fr} }
        .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .spacer{flex:1}
    </style>
</head>
<body>
    <h1>Статистика прослуховувань</h1>

    <form method="get" class="card grid">
        <div class="row">
            <label>Від:
                <input type="date" name="from" value="{{ $filters['from'] }}">
            </label>
            <label>До:
                <input type="date" name="to" value="{{ $filters['to'] }}">
            </label>
            <label>Групування:
                <select name="group">
                    <option value="day"   @selected($group==='day')>по днях</option>
                    <option value="week"  @selected($group==='week')>по тижнях</option>
                    <option value="month" @selected($group==='month')>по місяцях</option>
                </select>
            </label>
            <label>Ідентифікатор користувача:
                <input type="number" name="user_id" value="{{ $filters['user_id'] }}">
            </label>
            <label>Пошук за назвою або автором:
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Введіть назву книги або автора">
            </label>
            <label>Сортування:
                <select name="sort">
                    <option value="seconds_desc" @selected($filters['sort']==='seconds_desc')>за часом прослуховування (спочатку найбільше)</option>
                    <option value="seconds_asc"  @selected($filters['sort']==='seconds_asc')>за часом прослуховування (спочатку найменше)</option>
                    <option value="title"        @selected($filters['sort']==='title')>за назвою книги (по абетці)</option>
                    <option value="author"       @selected($filters['sort']==='author')>за автором (по абетці)</option>
                </select>
            </label>

            <button type="submit">Застосувати</button>
            <a class="btn" href="{{ route('admin.listens.stats.export', request()->query()) }}">Експорт агрегованих інтервалів</a>
            <a class="btn" href="{{ route('admin.listens.stats.export.books', request()->query()) }}">Експорт списку книг</a>
            <a class="btn" href="{{ route('admin.listens.authors', request()->query()) }}">Звіт по авторам</a>
        </div>

        <div class="row muted">
            <div>Діапазон: {{ $from }} — {{ $to }}</div>
            <div>•</div>
            <div>Всього зараховано: <strong>{{ number_format($totalSeconds, 0, '.', ' ') }}</strong> секунди</div>
        </div>
    </form>

    <div class="two">
        <div class="card">
            <h2>Агреговані інтервали за обраним групуванням</h2>
            <table>
                <thead>
                <tr>
                    <th>Початок інтервалу</th>
                    <th>Кінець інтервалу</th>
                    <th>Секунди зарахованого прослуховування</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r['from'] }}</td>
                        <td>{{ $r['to'] }}</td>
                        <td>{{ number_format($r['seconds'], 0, '.', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Немає даних за обраний період.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Книги за обраний період</h2>
            <table>
                <thead>
                <tr>
                    <th>Книга</th>
                    <th>Автор</th>
                    <th>Секунди зарахованого прослуховування</th>
                    <th>Зручне відображення часу</th>
                </tr>
                </thead>
                <tbody>
                @forelse($perBook as $b)
                    <tr>
                        <td>
                            <div class="row">
                                <img class="cover" src="{{ $b['cover_url'] }}" alt="">
                                <div>
                                    <div>
                                        <a href="{{ route('admin.listens.book', ['a_book_id'=>$b['a_book_id']] + request()->query()) }}">
                                            {{ $b['title'] }}
                                        </a>
                                    </div>
                                    <div class="muted">Ідентифікатор книги: {{ $b['a_book_id'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $b['author'] }}</td>
                        <td>{{ number_format($b['seconds'], 0, '.', ' ') }}</td>
                        <td>
                            @php
                                $sec = (int)$b['seconds'];
                                $h = intdiv($sec, 3600);
                                $m = intdiv($sec % 3600, 60);
                                $s = $sec % 60;
                                echo $h > 0
                                    ? $h . ' годин ' . $m . ' хвилин'
                                    : ($m > 0 ? $m . ' хвилин ' . $s . ' секунд' : $s . ' секунд');
                            @endphp
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Немає даних за обраний період.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
