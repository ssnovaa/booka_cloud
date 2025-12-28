<div class="group relative card card-dark p-2" data-book-id="{{ $id }}">
    <a href="{{ url('/abooks/'.$id) }}" class="block">
        <div class="relative aspect-[3/4] rounded-xl overflow-hidden">
            <img src="{{ $cover }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300">
            @if(!empty($active))
                <span class="fav-badge absolute left-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-pink-600/90 text-white px-2 py-0.5 text-xs">
                    В обраному
                </span>
            @else
                <span class="fav-badge hidden absolute left-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-pink-600/90 text-white px-2 py-0.5 text-xs">
                    В обраному
                </span>
            @endif
        </div>
        <div class="mt-2 text-sm font-medium line-clamp-2">{{ $title }}</div>
    </a>

    <button
        type="button"
        class="absolute top-2 right-2 rounded-full shadow px-2.5 py-2 text-sm js-fav-toggle transition
               {{ !empty($active) ? 'bg-pink-600 text-white hover:bg-pink-700 is-active' : 'bg-white text-gray-700 hover:bg-pink-50' }}"
        data-book-id="{{ $id }}"
        data-book-title="{{ e($title) }}"
        data-book-cover="{{ $cover }}"
        aria-pressed="{{ !empty($active) ? 'true' : 'false' }}"
        title="{{ !empty($active) ? 'У обраному' : 'Додати в обране' }}"
    >
        <svg class="fav-icon--off {{ !empty($active) ? 'hidden' : '' }} w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <svg class="fav-icon--on {{ !empty($active) ? '' : 'hidden' }} w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 21s-6.716-4.63-9.193-7.107A5.5 5.5 0 1 1 12 6.586a5.5 5.5 0 1 1 9.193 7.307C18.716 16.37 12 21 12 21z"/>
        </svg>
        <span class="sr-only">{{ !empty($active) ? 'У обраному' : 'Додати в обране' }}</span>
    </button>
</div>
