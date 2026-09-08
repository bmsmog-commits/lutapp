@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form action="{{ route('bible.search') }}" method="GET" class="flex gap-4 mb-4">
                <input type="text" name="q" value="{{ $query }}"
                       placeholder="{{ __('messages.search_verses') }}"
                       class="flex-grow px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       minlength="3">
                <input type="hidden" name="translation_id" value="{{ $translation->id }}">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    {{ __('messages.search') }}
                </button>
            </form>
            <p class="text-sm text-gray-600">
                {{ __('messages.translation') }}: <strong>{{ $translation->name }}</strong>
            </p>
        </div>

        <!-- Results -->
        @if($query)
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">
                    {{ count($verses) }} {{ __('messages.results_for') }} "{{ $query }}"
                </h2>

                @if(count($verses) > 0)
                    <div class="space-y-6">
                        @foreach($verses as $verse)
                            @php
                                $isBookmarked = auth()->user()->bibleBookmarks()
                                    ->where('bible_book_id', $verse->bible_book_id)
                                    ->where('chapter', $verse->chapter)
                                    ->where('verse', $verse->verse)
                                    ->where('translation_id', $verse->translation_id)
                                    ->first();
                                $highlight = auth()->user()->bibleHighlights()
                                    ->where('bible_book_id', $verse->bible_book_id)
                                    ->where('chapter', $verse->chapter)
                                    ->where('verse', $verse->verse)
                                    ->where('translation_id', $verse->translation_id)
                                    ->first();
                            @endphp
                            <div class="p-4 rounded border {{ $highlight ? 'bg-' . $highlight->color . '-100 border-' . $highlight->color . '-500' : 'bg-gray-50 border-gray-200' }}">
                                <div class="flex items-start justify-between mb-3">
                                    <a href="{{ route('bible.index', ['book' => $verse->bible_book_id, 'chapter' => $verse->chapter, 'translation_id' => $verse->translation_id]) }}"
                                       class="font-bold text-blue-600 hover:underline">
                                        {{ $verse->book->name }} {{ $verse->chapter }}:{{ $verse->verse }}
                                    </a>
                                    <div class="flex gap-2">
                                        <!-- Bookmark Button -->
                                        <form action="{{ route('bible.bookmark') }}" method="POST" class="inline bookmark-form-search">
                                            @csrf
                                            <input type="hidden" name="bible_book_id" value="{{ $verse->bible_book_id }}">
                                            <input type="hidden" name="chapter" value="{{ $verse->chapter }}">
                                            <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                            <input type="hidden" name="translation_id" value="{{ $verse->translation_id }}">
                                            <button type="submit" class="p-2 rounded hover:bg-gray-200 {{ $isBookmarked ? 'text-yellow-500' : 'text-gray-400' }}">
                                                ⭐
                                            </button>
                                        </form>

                                        <!-- Highlight Dropdown -->
                                        <div class="relative group">
                                            <button class="p-2 rounded hover:bg-gray-200 text-gray-400">
                                                🎨
                                            </button>
                                            <div class="hidden group-hover:block absolute right-0 mt-2 w-48 bg-white rounded shadow-lg p-2 z-10">
                                                @foreach(['yellow', 'red', 'blue', 'green', 'orange', 'purple'] as $color)
                                                    <form action="{{ route('bible.highlight') }}" method="POST" class="block">
                                                        @csrf
                                                        <input type="hidden" name="bible_book_id" value="{{ $verse->bible_book_id }}">
                                                        <input type="hidden" name="chapter" value="{{ $verse->chapter }}">
                                                        <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                                        <input type="hidden" name="translation_id" value="{{ $verse->translation_id }}">
                                                        <input type="hidden" name="color" value="{{ $color }}">
                                                        <button type="submit" class="w-full text-left px-3 py-2 hover:bg-gray-100 rounded text-sm capitalize">
                                                            ● {{ $color }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-lg leading-relaxed text-gray-800">{{ $verse->text }}</p>
                                @if($highlight && $highlight->note)
                                    <p class="mt-2 text-sm italic text-gray-600">
                                        📝 {{ $highlight->note }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">{{ __('messages.no_verses_found') }}</p>
                @endif
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <h2 class="text-2xl font-bold text-gray-600 mb-4">{{ __('messages.search_bible') }}</h2>
                <p class="text-gray-500">{{ __('messages.enter_search_term') }}</p>
            </div>
        @endif
    </div>
</div>

<script>
    // Handle bookmark removal on click if already bookmarked
    document.querySelectorAll('.bookmark-form-search').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button');
            if (submitBtn.classList.contains('text-yellow-500')) {
                e.preventDefault();
                this.action = '{{ route('bible.removeBookmark') }}';
                this.submit();
            }
        });
    });
</script>
@endsection
