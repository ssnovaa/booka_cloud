<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Админ · Отправка пушей</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
         max-width:900px;margin:24px auto;padding:0 16px}
    .row{display:flex;gap:16px;flex-wrap:wrap}
    .col{flex:1 1 260px}
    label{display:block;font-weight:600;margin:12px 0 6px}
    input[type=text], textarea, select{
      width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px
    }
    textarea{min-height:100px}
    .actions{margin-top:16px;display:flex;gap:12px;align-items:center}
    .btn{background:#4f46e5;color:#fff;padding:10px 16px;border:none;border-radius:8px;cursor:pointer}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .note{color:#555;font-size:13px}
    .flash{background:#ecfdf5;border:1px solid #34d399;color:#065f46;padding:12px 14px;border-radius:8px;margin-bottom:16px}
  </style>
</head>
<body>
  <h2>Отправка пуш-уведомлений</h2>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  <form method="post" action="{{ route('admin.push.store') }}">
    @csrf

    <div class="row">
      <div class="col">
        <label>Заголовок *</label>
        <input type="text" name="title" value="{{ old('title') }}" required maxlength="120">
        @error('title')<div class="note" style="color:#b91c1c">{{ $message }}</div>@enderror
      </div>
      <div class="col">
        <label>Платформа</label>
        <select name="platform">
          <option value="all" {{ old('platform','all')==='all'?'selected':'' }}>Все</option>
          <option value="android" {{ old('platform')==='android'?'selected':'' }}>Android</option>
          <option value="ios" {{ old('platform')==='ios'?'selected':'' }}>iOS</option>
        </select>
      </div>
    </div>

    <label>Текст *</label>
    <textarea name="body" required maxlength="500">{{ old('body') }}</textarea>
    @error('body')<div class="note" style="color:#b91c1c">{{ $message }}</div>@enderror

    <div class="row">
      <div class="col">
        <label>Диплинк route (опц.)</label>
        <input type="text" name="route" value="{{ old('route') }}" placeholder="/book или /profile">
      </div>
      <div class="col">
        <label>book_id (опц.)</label>
        <input type="text" name="book_id" value="{{ old('book_id') }}" placeholder="например: 123">
      </div>
    </div>

    <div class="actions">
      <button class="btn" type="submit">Отправить всем</button>
      <label class="note"><input type="checkbox" name="dry_run" value="1" {{ old('dry_run')?'checked':'' }}> пробный прогон (без отправки)</label>
    </div>

    <p class="note">Подсказка: если указать <strong>route</strong>=<code>/book</code> и <strong>book_id</strong>=<code>123</code>, приложение откроет экран книги 123 при тапе по уведомлению.</p>
  </form>
</body>
</html>
