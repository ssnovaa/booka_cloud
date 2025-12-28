@extends('layouts.app')
@section('title','Кабінет')

@section('content')
<style>
/* утилиты, если нет плагина line-clamp */
.line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}

/* card-компоненты без @apply (Tailwind тут не работает) */
.card{
  border-radius: 1rem;
  background: rgba(255,255,255,.7);
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
  border: 1px solid #e5e7eb; /* gray-200 */
  box-shadow: 0 1px 2px rgba(0,0,0,.04);
}
@media (prefers-color-scheme: dark){
  .card-dark{
    background: rgba(2,6,23,.6); /* slate-950/60 */
    border-color: #0f172a;       /* slate-900 */
  }
}
.btn-soft{
  border-radius: 9999px;
  padding: .25rem .75rem;
  font-size: .875rem;
  font-weight: 500;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.progress{ height: .5rem; width: 100%; border-radius: 9999px; background: #e5e7eb; overflow: hidden; }
.progress > span{ display:block; height:100%; border-radius: inherit; }
</style>

<div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">

    {{-- Шапка --}}
    <div class="relative overflow-hidden rounded-3xl">
        <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600"></div>
        <div class="relative px-6 sm:px-10 py-8 sm:py-12 text-white flex items-center gap-6">
            <div class="flex-shrink-0">
                @php $initial = strtoupper(mb_substr($user->name ?? 'U', 0, 1)); @endphp
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/20 flex items-center justify-center text-2xl sm:text-3xl font-semibold">
                    {{ $initial }}
                </div>
            </div>
            <div class="flex-1">
                <div class="text-2xl sm:text-3xl font-semibold">{{ $user->name ?? 'Користувач' }}</div>
                <div class="opacity-90">{{ $user->email }}</div>
                <div class="mt-3 flex items-center gap-2">
                    @if($is_paid)
                        <span class="btn-soft bg-white/20 hover:bg-white/30">Преміум</span>
                    @else
                        <a href="{{ url('/billing') }}" class="btn-soft bg-white text-indigo-700 hover:bg-white/90">Активувати Преміум</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Сетка: Continue + счётчики --}}
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Продовжити слухати --}}
        <div class="lg:col-span-2 card card-dark p-0 overflow-hidden">
            <div class="p-5 sm:p-6 flex items-start gap-4">
                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden ring-1 ring-black/5 flex-shrink-0">
                    @if(!empty($currentListen))
                        <img class="w-full h-full object-cover"
                             src="{{ $currentListen['book']['cover_url'] }}"
                             alt="{{ $currentListen['book']['title'] }}">
                    @else
                        <div class="w-full h-full bg-gray-100"></div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-sm uppercase tracking-wide text-gray-500">Продовжити слухати</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ $currentListen['book']['title'] ?? 'Ще нічого не слухали' }}
                    </div>

                    @if(!empty($currentListen))
                        <div class="text-sm text-gray-500">
                            Глава: {{ $currentListen['chapter']['title'] ?? '—' }}
                        </div>

                        @php
                            $dur = max(1, (int)($currentListen['chapter']['duration'] ?? 1));
                            $pos = min($dur, (int)($currentListen['position'] ?? 0));
                            $pct = isset($currentListen['percent'])
                                   ? (int)$currentListen['percent']
                                   : (int) round($pos * 100 / $dur);
                        @endphp
                        <div class="mt-3 progress" aria-label="Progress">
                            <span class="bg-indigo-600" style="width: {{ $pct }}%"></span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">{{ $pct }}% прослухано</div>
                    @else
                        <div class="mt-2 text-gray-500">Відкрийте будь-яку аудіокнигу і натисніть «Play»</div>
                    @endif
                </div>

                <div class="flex-shrink-0">
                    @if(!empty($currentListen))
                        <a href="{{ url('/abooks/'.$currentListen['book']['id']).'?chapter='.$currentListen['chapter']['id'].'&position='.$currentListen['position'] }}"
                           class="inline-flex items-center gap-2 btn-soft bg-indigo-600 text-white hover:bg-indigo-700">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            Продовжити
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Быстрые счётчики --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-1 gap-6">
            <div class="card card-dark p-5">
                <div class="text-xs uppercase tracking-wide text-gray-500">Обране</div>
                <div class="mt-1 text-3xl font-semibold">{{ ($favorites->count() ?? 0) }}</div>
                <a href="{{ route('cabinet.favorites') }}" class="inline-flex items-center gap-1 mt-3 text-indigo-600 hover:underline">
                    Відкрити
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13 7h5v5h-2V9.41l-6.29 6.3-1.42-1.42 6.3-6.29H13V7z"/><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7z"/></svg>
                </a>
            </div>
            <div class="card card-dark p-5">
                <div class="text-xs uppercase tracking-wide text-gray-500">Прослухані</div>
                <div class="mt-1 text-3xl font-semibold">{{ ($listenedBooks->count() ?? 0) }}</div>
                <a href="{{ route('cabinet.listened') }}" class="inline-flex items-center gap-1 mt-3 text-indigo-600 hover:underline">
                    Відкрити
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13 7h5v5h-2V9.41l-6.29 6.3-1.42-1.42 6.3-6.29H13V7z"/><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7z"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Обране --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Обране</h2>
            <a href="{{ route('cabinet.favorites') }}" class="text-sm text-indigo-600 hover:underline">Дивитися все</a>
        </div>

        @if($favorites->isNotEmpty())
            <div id="fav-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-5">
                @foreach($favorites as $b)
                    @php $active = in_array($b['id'], $favoriteIds ?? []); @endphp
                    @include('cabinet.partials.book-card', [
                        'id' => $b['id'],
                        'title' => $b['title'],
                        'cover' => $b['cover_url'],
                        'active' => $active
                    ])
                @endforeach
            </div>
            <div id="fav-empty" class="hidden text-gray-500">Порожньо.</div>
        @else
            <div id="fav-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-5"></div>
            <div id="fav-empty" class="text-gray-500">Порожньо.</div>
        @endif
    </div>

    {{-- Прослухані --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Прослухані</h2>
            <a href="{{ route('cabinet.listened') }}" class="text-sm text-indigo-600 hover:underline">Дивитися все</a>
        </div>

        @if($listenedBooks->isNotEmpty())
            <div id="listened-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-5">
                @foreach($listenedBooks as $b)
                    @php $active = in_array($b['id'], $favoriteIds ?? []); @endphp
                    @include('cabinet.partials.book-card', [
                        'id' => $b['id'],
                        'title' => $b['title'],
                        'cover' => $b['cover_url'],
                        'active' => $active
                    ])
                @endforeach
            </div>
        @else
            <div class="text-gray-500">Тут з’являться книги, які ви вже слухали.</div>
        @endif
    </div>

</div>

{{-- JS: toggle + синхронизация секции "Обране" без перезагрузки --}}
<script>
(function(){
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  function setFavVisual(btn, on){
    if(!btn) return;
    btn.classList.toggle('is-active', on);
    btn.classList.toggle('bg-pink-600', on);
    btn.classList.toggle('text-white', on);
    btn.classList.toggle('hover:bg-pink-700', on);
    btn.classList.toggle('bg-white', !on);
    btn.classList.toggle('text-gray-700', !on);
    btn.classList.toggle('hover:bg-pink-50', !on);
    const off = btn.querySelector('.fav-icon--off');
    const onIcon = btn.querySelector('.fav-icon--on');
    if(off && onIcon){ off.classList.toggle('hidden', on); onIcon.classList.toggle('hidden', !on); }
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.setAttribute('title', on ? 'У обраному' : 'Додати в обране');

    const card = btn.closest('[data-book-id]');
    const badge = card?.querySelector('.fav-badge');
    if(badge){ badge.classList.toggle('hidden', !on); }
  }

  function buildFavCard(id, title, cover, active){
    const tpl = document.getElementById('book-card-template');
    if(!tpl) return null;
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.setAttribute('data-book-id', id);
    const a   = node.querySelector('a'); if(a) a.href = `/abooks/${id}`;
    const img = node.querySelector('img'); if(img){ img.src = cover; img.alt = title; }
    const t   = node.querySelector('.book-title'); if(t) t.textContent = title;

    const btn = node.querySelector('.js-fav-toggle');
    if(btn){
      btn.dataset.bookId = id;
      btn.dataset.bookTitle = title;
      btn.dataset.bookCover = cover;
      if(active){
        btn.classList.add('is-active','bg-pink-600','text-white','hover:bg-pink-700');
        btn.classList.remove('bg-white','text-gray-700','hover:bg-pink-50');
        btn.setAttribute('aria-pressed','true');
        node.querySelector('.fav-icon--off')?.classList.add('hidden');
        node.querySelector('.fav-icon--on')?.classList.remove('hidden');
        node.querySelector('.fav-badge')?.classList.remove('hidden');
      }
    }
    return node;
  }

  function syncFavSection(btn, nowActive){
    const id    = btn?.dataset.bookId;
    if(!id) return;
    const title = btn.dataset.bookTitle || '';
    const cover = btn.dataset.bookCover || '';
    const grid  = document.getElementById('fav-grid');
    const empty = document.getElementById('fav-empty');
    if(!grid) return;

    const existing = grid.querySelector(`[data-book-id="${id}"]`);
    if(nowActive){
      if(!existing){
        const node = buildFavCard(id, title, cover, true);
        if(node){
          grid.prepend(node);
          const items = grid.querySelectorAll('[data-book-id]');
          if(items.length > 12){ items[items.length-1].remove(); }
        }
      }
      empty?.classList.add('hidden');
    } else {
      existing?.remove();
      if(empty && grid.querySelectorAll('[data-book-id]').length === 0){
        empty.classList.remove('hidden');
      }
    }
  }

  async function toggleFav(btn){
    const id = btn.getAttribute('data-book-id');
    if(!id) return;
    try{
      const r = await fetch(`/abooks/${id}/favorite`, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': token ?? '' }
      });
      if(r.ok){
        const nowActive = !btn.classList.contains('is-active');
        setFavVisual(btn, nowActive);
        syncFavSection(btn, nowActive);
      } else {
        alert('Не вдалося змінити обране');
      }
    }catch{ alert('Помилка мережі'); }
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.js-fav-toggle');
    if(!btn) return;
    e.preventDefault();
    toggleFav(btn);
  });
})();
</script>

{{-- Шаблон карточки (для динамического добавления) --}}
<template id="book-card-template">
  <div class="group relative card card-dark p-2" data-book-id="">
    <a href="#" class="block">
      <div class="relative aspect-[3/4] rounded-xl overflow-hidden">
        <img src="" alt="" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300">
        <span class="fav-badge hidden absolute left-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-pink-600/90 text-white px-2 py-0.5 text-xs">
          В обраному
        </span>
      </div>
      <div class="mt-2 text-sm font-medium book-title line-clamp-2"></div>
    </a>
    <button type="button"
            class="absolute top-2 right-2 rounded-full bg-white text-gray-700 hover:bg-pink-50 shadow px-2.5 py-2 text-sm js-fav-toggle transition"
            data-book-id="" data-book-title="" data-book-cover=""
            aria-pressed="false" title="Додати в обране">
        <svg class="fav-icon--off w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <svg class="fav-icon--on hidden w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 21s-6.716-4.63-9.193-7.107A5.5 5.5 0 1 1 12 6.586a5.5 5.5 0 1 1 9.193 7.307C18.716 16.37 12 21 12 21z"/>
        </svg>
        <span class="sr-only">Додати в обране</span>
    </button>
  </div>
</template>
@endsection
