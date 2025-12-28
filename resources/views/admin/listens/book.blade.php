<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Деталізація книги — {{ $book->title }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;margin:16px;color:#111}
        .grid{display:grid;gap:16px}
        .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px}
        .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        label{font-size:14px;color:#374151}
        input[type="date"],select,input[type="number"]{padding:8px 10px;border:1px solid #d1d5db;border-radius:8px}
        button,.btn{display:inline-block;padding:9px 14px;border-radius:8px;border:1px solid #111;background:#111;color:#fff;text-decoration:none}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
        th{background:#fafafa}
        .muted{color:#6b7280}
        .cover{width:56px;height:80px;border-radius:6px;object-fit:cover;background:#f3f4f6}
        .two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media (max-width: 900px){ .two{grid-template-columns:1fr} }
    </style>
</head>
<body>
    <a href="{{ route('admin.listens.stats', ['from'=>$from,'to'=>$to,'group'=>$group,'user_id'=>$user_id]) }}" class="btn">Повернутися до загальної статистики</a>

    <div class="card">
        <div class="row">
            <img class="cover" src="{{ $cover }}" alt="Обкладинка книги">
            <div>
                <h1 style="margin:0">{{ $book->title }}</h1>
                <div class="muted">
                    {{ $book->author->name ?? 'Невідомий автор' }}
                    • Ідентифікатор книги: {{ $book->id }}
                </div>
                <div class="muted" style="margin-top:4px;">
                    Всього зараховано за вибраний період:
                    <strong>{{ number_format($totalSeconds, 0, '.', ' ') }}</strong> секунд
                    @php
                        $sec = (int)$totalSeconds;
                        $h = intdiv($sec, 3600);
                        $m = intdiv($sec % 3600, 60);
                        $s = $sec % 60;
                        $human = $h > 0
                            ? $h . ' годин ' . $m . ' хвилин'
                            : ($m > 0 ? $m . ' хвилин ' . $s . ' секунд' : $s . ' секунд');
                    @endphp
                    <span class="muted">({{ $human }})</span>
                </div>
            </div>
        </div>
    </div>

    <form method="get" class="card grid">
        <div class="row">
            <label>Дата початку:
                <input type="date" name="from" value="{{ $from }}">
            </label>
            <label>Дата завершення:
                <input type="date" name="to" value="{{ $to }}">
            </label>
            <label>Групування інтервалів:
                <select name="group">
                    <option value="day"   @selected($group==='day')>за днями</option>
                    <option value="week"  @selected($group==='week')>за тижнями</option>
                    <option value="month" @selected($group==='month')>за місяцями</option>
                </select>
            </label>
            <label>Ідентифікатор користувача (необов’язково):
                <input type="number" name="user_id" value="{{ $user_id }}">
            </label>
            <button type="submit">Застосувати фільтри</button>
            <a class="btn" href="{{ route('admin.listens.book.export.series', ['a_book_id'=>$book->id] + request()->query()) }}">Експорт таймсерії у CSV</a>
            <a class="btn" href="{{ route('admin.listens.book.export.chapters', ['a_book_id'=>$book->id] + request()->query()) }}">Експорт глав у CSV</a>
        </div>
        <div class="row muted">
            <div>Обраний діапазон: {{ $from }} — {{ $to }}</div>
            @if($user_id)
                <div>•</div>
                <div>Фільтр за користувачем: {{ $user_id }}</div>
            @endif
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
                    <th>Зручне відображення часу</th>
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
                        <td>{{ $r['from'] }}</td>
                        <td>{{ $r['to'] }}</td>
                        <td>{{ number_format($r['seconds'], 0, '.', ' ') }}</td>
                        <td>{{ $human }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Немає даних за обраний період.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Глави книги за вибраний період</h2>
            <table>
                <thead>
                <tr>
                    <th>Порядок у книзі</th>
                    <th>Ідентифікатор глави та назва</th>
                    <th>Тривалість, секунд</th>
                    <th>Зараховано, секунд</th>
                    <th>Прогрес, відсотків</th>
                    <th>Зручне відображення часу</th>
                </tr>
                </thead>
                <tbody>
                @forelse($perChapter as $c)
                    @php
                        $sec = (int)$c['seconds'];
                        $h = intdiv($sec, 3600);
                        $m = intdiv($sec % 3600, 60);
                        $s = $sec % 60;
                        $human = $h > 0
                            ? $h . ' годин ' . $m . ' хвилин'
                            : ($m > 0 ? $m . ' хвилин ' . $s . ' секунд' : $s . ' секунд');
                    @endphp
                    <tr>
                        <td>{{ $c['order'] }}</td>
                        <td>Ідентифікатор глави: {{ $c['a_chapter_id'] }} — {{ $c['title'] }}</td>
                        <td>{{ number_format($c['duration'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($c['seconds'], 0, '.', ' ') }}</td>
                        <td>{{ $c['percent'] !== null ? $c['percent'] : 'Невідомо' }}</td>
                        <td>{{ $human }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Немає даних за обраний період.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
