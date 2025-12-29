{{-- resources/views/abooks/show.blade.php --}}
@extends('layouts.app')
@section('title', $book->title ?? 'Аудіокнига')

@section('content')
@php
  // Подготовим главы в удобном виде и сразу отсортируем
  $chaptersSorted = $book->chapters->sortBy('order')->values();
  $playerChapters = $chaptersSorted->map(fn($ch) => [
      'id'       => (int) $ch->id,
      'title'    => (string) $ch->title,
      'duration' => (int) ($ch->duration ?? 0),
      'url'      => route('audio.stream', $ch->id),
  ]);

  $initChapterId = (int) request('chapter', $playerChapters->first()['id'] ?? 0);
  $initPosition  = (int) request('position', 0);
@endphp

<div class="container mx-auto p-4"
     x-data='audioPlayer(@json($playerChapters), {{ (int)$book->id }}, {{ $initChapterId }}, {{ $initPosition }}, @json(route("listen.get")), @json(route("listen.update")))'
     x-init="init()">

  {{-- Заголовок + админ-редактирование --}}
  <div class="flex items-center justify-between mb-2">
      <h1 class="text-3xl font-bold">{{ $book->title }}</h1>
      @auth
        @if(auth()->user()?->is_admin)
          <a href="{{ route('admin.abooks.edit', $book->id) }}"
             class="bg-yellow-400 text-black px-3 py-1 rounded hover:bg-yellow-500 shadow">
            ✏️ Редактировать
          </a>
        @endif
      @endauth
  </div>

  <p class="text-sm text-gray-600 mb-1">Автор: {{ $book->author->name ?? 'Автор не указан' }}</p>

  {{-- Серия (кликабельная) --}}
  @if($book->series)
    <div class="mb-3">
      <span class="text-sm text-gray-500">Серия:</span>
      <a href="{{ route('series.show', $book->series->id) }}"
         class="font-semibold text-blue-700 hover:underline">
        {{ $book->series->title }}
      </a>
    </div>
  @endif

  {{-- Жанры --}}
  @if($book->genres && $book->genres->count())
    <div class="mb-4 flex flex-wrap gap-2">
      @foreach($book->genres as $genre)
        <span class="text-xs bg-gray-200 rounded px-2 py-0.5">{{ $genre->name }}</span>
      @endforeach
    </div>
  @endif

  <p class="mb-6">{{ $book->description }}</p>

  {{-- Обложка --}}
  @if($book->cover_url)
    <img src="{{ asset('storage/'.$book->cover_url) }}"
         alt="Обложка" class="w-64 mb-6 rounded shadow">
  @endif

  {{-- Избранное (как у тебя) --}}
  @auth
    <form method="POST" action="{{ route('favorites.toggle', $book->id) }}" class="my-4">
      @csrf
      @if (auth()->user()->favoriteBooks->contains($book->id))
        <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded">Убрать из избранного ❤️</button>
      @else
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Добавить в избранное 🤍</button>
      @endif
    </form>
  @endauth

  {{-- --- Админ: управление главами --- --}}
  @auth
    @if(auth()->user()?->is_admin)
      <div class="mb-4 flex flex-wrap items-center gap-4">
        <a href="{{ route('admin.chapters.create', $book->id) }}"
           class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm shadow">
          ➕ Добавить главу
        </a>
      </div>
    @endif
  @endauth
  {{-- --- /Админ --- --}}

  {{-- Список глав --}}
  <div class="mb-6">
    <h2 class="text-xl font-semibold mb-2">Главы</h2>
    <ul class="space-y-2">
      @foreach ($playerChapters as $idx => $ch)
        <li class="flex items-center gap-2">
          <button
            @click="playById({{ $ch['id'] }})"
            class="px-4 py-2 rounded w-full text-left transition"
            :class="{ 'bg-blue-100 font-semibold ring-1 ring-blue-300': current?.id === {{ $ch['id'] }} }">
            <span class="block">{{ $idx+1 }}. {{ $ch['title'] }}</span>
            <span class="mt-0.5 block text-xs text-gray-500">
              <span :id="'st-'+{{ $ch['id'] }}">Перевіряю доступ…</span>
              <span class="ml-2 tabular-nums">{{ $ch['duration'] ? gmdate(($ch['duration']>=3600?'H:i:s':'i:s'), $ch['duration']) : '' }}</span>
            </span>
          </button>

          @auth
            @if(auth()->user()?->is_admin)
              <a href="{{ route('admin.chapters.edit', [$book->id, $ch['id']]) }}"
                 class="text-yellow-600 hover:text-yellow-900 px-2">✏️</a>
              <form action="{{ route('admin.chapters.destroy', [$book->id, $ch['id']]) }}"
                    method="POST" class="inline"
                    onsubmit="return confirm('Удалить главу?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 hover:text-red-800 px-2">🗑️</button>
              </form>
            @endif
          @endauth
        </li>
      @endforeach
    </ul>
  </div>

  {{-- Плеер --}}
  <div class="bg-white shadow rounded p-4" x-show="current" x-cloak>
    <p class="font-semibold mb-2">
      Сейчас играет: <span x-text="current?.title || ''"></span>
    </p>

    <audio x-ref="audio" preload="metadata" crossorigin="use-credentials" @ended="onEnded" class="w-full mb-3"></audio>

    {{-- Прогресс --}}
    <div class="flex items-center gap-3 mb-3">
      <div class="w-14 text-xs tabular-nums text-gray-500" x-text="fmt(time)"></div>
      <input x-ref="seek" type="range" min="0" max="100" step="0.1"
             class="flex-1 appearance-none h-1.5 rounded bg-gray-200"
             @input="onSeek($event)">
      <div class="w-14 text-xs tabular-nums text-right text-gray-500" x-text="fmt(duration)"></div>
    </div>

    {{-- Кнопки --}}
    <div class="flex flex-wrap items-center gap-2">
      <button @click="prev" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300">⏮</button>
      <button @click="seekBy(-10)" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300">« 10с</button>
      <button @click="toggle" class="px-4 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700" x-text="playing ? 'Пауза' : 'Играть'"></button>
      <button @click="seekBy(30)" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300">30с »</button>
      <button @click="next" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300">⏭</button>

      <label class="ml-3 flex items-center gap-2">
        <span class="text-sm text-gray-600">Скорость</span>
        <select x-model.number="rate" @change="applyRate"
                class="border rounded px-2 py-1 text-sm">
          <option value="0.75">0.75×</option>
          <option value="1">1×</option>
          <option value="1.25">1.25×</option>
          <option value="1.5">1.5×</option>
          <option value="1.75">1.75×</option>
          <option value="2">2×</option>
        </select>
      </label>

      <div class="ml-3 flex items-center gap-2">
        <button @click="toggleMute" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300" x-text="muted ? '🔇' : '🔈'"></button>
        <input type="range" min="0" max="1" step="0.01" x-model.number="volume"
               @input="applyVolume" class="w-28 h-1.5 rounded bg-gray-200">
      </div>
    </div>

    {{-- Оверлей, если глава закрыта --}}
    <div x-show="locked" x-cloak
         class="mt-4 p-4 rounded bg-rose-50 border border-rose-200 text-rose-700">
      Ця глава доступна лише для авторизованих / передплатників.
      <div class="mt-2 flex items-center gap-3">
        @guest
          <a href="{{ route('login') }}" class="px-3 py-1.5 rounded bg-rose-600 text-white hover:bg-rose-700">Увійти</a>
        @endguest
        <a href="{{ url('/billing') }}" class="px-3 py-1.5 rounded bg-gray-800 text-white hover:bg-black">Оформити Преміум</a>
      </div>
    </div>
  </div>
</div>

<script>
function audioPlayer(chapters, bookId, initChapterId, initPos, listenGetUrl, listenUpdateUrl){
  return {
    chapters, bookId,
    current: null,
    playing: false,
    time: 0,
    duration: 0,
    rate: 1,
    volume: 1,
    muted: false,
    locked: false,
    lastSaved: 0,
    headCache: new Map(),

    fmt(s){ s=Math.max(0,Math.round(+s||0)); const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), x=s%60; return (h?h+':':'')+String(m).padStart(h?2:1,'0')+':'+String(x).padStart(2,'0'); },

    async init(){
      // восстановим настройки
      try{
        const sv = localStorage.getItem('player_speed'); if(sv){ this.rate=+sv; }
        const vv = localStorage.getItem('player_volume'); if(vv){ this.volume=+vv; }
      }catch(e){}

      const audio = this.$refs.audio;
      audio.playbackRate = this.rate;
      audio.volume = this.volume;

      // события
      audio.addEventListener('loadedmetadata', ()=>{
        this.duration = isFinite(audio.duration) ? audio.duration : (this.current?.duration || 0);
        if (initPos>0) { try{ audio.currentTime = initPos; }catch(e){} }
      });
      audio.addEventListener('timeupdate', ()=>{
        this.time = audio.currentTime || 0;
        const tot = isFinite(audio.duration) ? audio.duration : (this.current?.duration || 1);
        this.duration = tot;
        const pct = (this.time / (tot||1))*100;
        this.$refs.seek.value = pct;
        this.saveProgressThrottled();
      });

      // хоткеи
      document.addEventListener('keydown', (e)=>{
        const t = e.target?.tagName?.toLowerCase();
        if (t==='input' || t==='textarea' || e.target?.isContentEditable) return;
        if (e.code==='Space'){ e.preventDefault(); this.toggle(); }
        else if (e.code==='ArrowLeft'){ e.preventDefault(); this.seekBy(-10); }
        else if (e.code==='ArrowRight'){ e.preventDefault(); this.seekBy(+30); }
        else if ((e.key||'').toLowerCase()==='n'){ e.preventDefault(); this.next(); }
        else if ((e.key||'').toLowerCase()==='p'){ e.preventDefault(); this.prev(); }
        else if ((e.key||'').toLowerCase()==='m'){ e.preventDefault(); this.toggleMute(); }
      });

      // начальная глава
      const idx = this.chapters.findIndex(c => c.id === initChapterId);
      await this.setChapter(Math.max(0, idx));
      if (initPos > 0) this.play();
    },

    async headPlayable(id){
      if (this.headCache.has(id)) return this.headCache.get(id);
      try{
        const r = await fetch(`/audio/${id}`, {method:'HEAD', credentials:'same-origin'});
        const ok = r.ok;
        this.headCache.set(id, ok);
        // подпишем статус в списке, если есть элемент
        const st = document.getElementById('st-'+id);
        if (st) st.textContent = ok ? 'Доступно' : 'Заблоковано';
        return ok;
      }catch{ return false; }
    },

    async setChapter(index){
      index = Math.min(Math.max(0, index), this.chapters.length-1);
      const ch = this.chapters[index]; if (!ch) return;
      this.current = ch;
      this.locked = false;
      this.playing = false;
      this.time = 0;
      this.duration = ch.duration || 0;
      this.$refs.seek.value = 0;

      // Обновим URL (без перезагрузки)
      const url = new URL(location.href);
      url.searchParams.set('chapter', ch.id);
      url.searchParams.set('position', 0);
      history.replaceState(null, '', url.toString());

      // Проверим доступ
      const can = await this.headPlayable(ch.id);
      if (!can){
        this.locked = true;
        this.$refs.audio.removeAttribute('src');
        this.$refs.audio.load();
        return;
      }

      // Установим источник и подтянем позицию
      this.$refs.audio.src = ch.url;
      this.$refs.audio.load();

      try{
        const r = await fetch(`${listenGetUrl}?a_book_id=${this.bookId}&a_chapter_id=${ch.id}`, {credentials:'same-origin'});
        if (r.ok) {
          const j = await r.json();
          if (typeof j.position === 'number' && !Number.isNaN(j.position)) {
            this.$refs.audio.currentTime = j.position;
          }
        }
      }catch(e){}
    },

    async playById(id){
      const idx = this.chapters.findIndex(c => c.id === id);
      await this.setChapter(Math.max(0, idx));
      this.play();
    },

    play(){
      if (!this.current || this.locked) return;
      this.$refs.audio.play().then(()=>{ this.playing = true; }).catch(()=>{});
    },
    pause(){ this.$refs.audio.pause(); this.playing = false; },
    toggle(){ this.playing ? this.pause() : this.play(); },

    seekBy(delta){
      if (!this.current || this.locked) return;
      this.$refs.audio.currentTime = Math.max(0, (this.$refs.audio.currentTime || 0) + delta);
    },
    onSeek(e){
      if (!this.current || this.locked) return;
      const pct = +e.target.value;
      const tot = this.duration || this.current.duration || 1;
      this.$refs.audio.currentTime = (pct/100) * tot;
    },

    prev(){
      const i = this.chapters.findIndex(c => c.id === (this.current?.id));
      if (i > 0) this.setChapter(i-1).then(()=> this.play());
    },
    next(){
      const i = this.chapters.findIndex(c => c.id === (this.current?.id));
      if (i < this.chapters.length-1) this.setChapter(i+1).then(()=> this.play());
    },
    onEnded(){ this.saveNow(true).then(()=> this.next()); },

    applyRate(){
      this.$refs.audio.playbackRate = this.rate;
      try{ localStorage.setItem('player_speed', String(this.rate)); }catch(e){}
    },
    applyVolume(){
      this.$refs.audio.volume = this.volume;
      try{ localStorage.setItem('player_volume', String(this.volume)); }catch(e){}
    },
    toggleMute(){ this.muted = !this.muted; this.$refs.audio.muted = this.muted; },

    saveProgressThrottled(){
      const cur = this.$refs.audio.currentTime || 0;
      if (Math.abs(cur - this.lastSaved) < 5) return; // ~каждые 5с
      this.lastSaved = cur;
      this.saveNow(false);
    },
    async saveNow(ended){
      if (!this.current) return;
      const pos = ended ? (this.current.duration || this.$refs.audio.currentTime || 0) : (this.$refs.audio.currentTime || 0);
      try{
        await fetch(listenUpdateUrl, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          credentials:'same-origin',
          body: JSON.stringify({
            a_book_id: this.bookId,
            a_chapter_id: this.current.id,
            position: Math.floor(pos)
          })
        });
      }catch(e){}
    }
  }
}
</script>
@endsection
