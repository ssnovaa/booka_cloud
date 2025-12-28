@extends('layouts.app')
@section('title','Обране')

@section('content')
<div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Обране</h1>
    </div>

    @if($favorites->count())
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-5">
            @foreach($favorites as $b)
                @php $active = in_array($b->id, $favoriteIds ?? []); @endphp
                @include('cabinet.partials.book-card', [
                    'id' => $b->id, 'title' => $b->title, 'cover' => $b->cover_url, 'active' => $active
                ])
            @endforeach
        </div>

        <div class="mt-6">{{ $favorites->links() }}</div>
    @else
        <div class="text-gray-500">Порожньо.</div>
    @endif
</div>

<script>
// тот же обработчик кликов, общий с дашбордом
document.addEventListener('click', async (e)=>{
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const btn = e.target.closest('.js-fav-toggle'); if(!btn) return;
  e.preventDefault();
  try{
    const r = await fetch(`/abooks/${btn.dataset.bookId}/favorite`, {
      method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': token ?? '' }
    });
    if(r.ok){
      const on = !btn.classList.contains('is-active');
      // визуал
      btn.classList.toggle('is-active', on);
      btn.classList.toggle('bg-pink-600', on);
      btn.classList.toggle('text-white', on);
      btn.classList.toggle('hover:bg-pink-700', on);
      btn.classList.toggle('bg-white', !on);
      btn.classList.toggle('text-gray-700', !on);
      btn.classList.toggle('hover:bg-pink-50', !on);
      btn.querySelector('.fav-icon--off')?.classList.toggle('hidden', on);
      btn.querySelector('.fav-icon--on')?.classList.toggle('hidden', !on);
      const badge = btn.closest('[data-book-id]')?.querySelector('.fav-badge');
      if(badge) badge.classList.toggle('hidden', !on);
    } else { alert('Не вдалося змінити обране'); }
  }catch{ alert('Помилка мережі'); }
});
</script>
@endsection
