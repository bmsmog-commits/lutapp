@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar: Books and Translations -->
        <div class="lg:col-span-1 bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">{{ __('messages.bible_books') }}</h2>
            
            <!-- Translation Switcher -->
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">{{ __('messages.translation') }}</label>
                <form method="GET" class="space-y-2">
                    @foreach($translations as $translation)
                        <label class="flex items-center cursor-pointer hover:bg-gray-100 p-2 rounded">
                            <input type="radio" name="translation_id" value="{{ $translation->id }}"
                                {{ $selectedTranslation->id === $translation->id ? 'checked' : '' }}
                                onchange="this.form.submit()"
                                class="mr-2">
                            <span class="text-sm">{{ $translation->name }}</span>
                            <span class="text-xs text-gray-500 ml-auto">{{ $translation->language }}</span>
                        </label>
                    @endforeach
                </form>
            </div>

            <hr class="mb-4">

            <!-- Books List -->
            <div class="space-y-1 max-h-96 overflow-y-auto">
                @php
                    $currentBook = request('book') ? \App\Models\BibleBook::find(request('book')) : null;
                @endphp
                @foreach($books as $book)
                    <a href="{{ route('bible.index', ['book' => $book->id, 'chapter' => 1, 'translation_id' => $selectedTranslation->id]) }}"
                       class="block px-3 py-2 rounded text-sm {{ $currentBook?->id === $book->id ? 'bg-blue-100 text-blue-900 font-semibold' : 'hover:bg-gray-100' }}">
                        {{ $book->name }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Main Content: Bible Reader -->
        <div class="lg:col-span-3">
            @if($currentBook)
                <div class="bg-white rounded-lg shadow p-6">
                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold mb-2">{{ $currentBook->name }}</h1>
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>{{ $selectedTranslation->name }} ({{ $selectedTranslation->code }})</span>
                            <span>{{ $selectedTranslation->language }}</span>
                        </div>
                    </div>

                    <!-- Chapter Navigation -->
                    <div class="mb-6 pb-6 border-b">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-2xl font-semibold">{{ __('messages.chapter') }} {{ $currentChapter }}</h2>
                            <div class="flex gap-2">
                                @if($currentChapter > 1)
                                    <a href="{{ route('bible.index', ['book' => $currentBook->id, 'chapter' => $currentChapter - 1, 'translation_id' => $selectedTranslation->id]) }}"
                                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">
                                        ← {{ __('messages.previous') }}
                                    </a>
                                @endif
                                @if($currentChapter < $currentBook->chapters_count)
                                    <a href="{{ route('bible.index', ['book' => $currentBook->id, 'chapter' => $currentChapter + 1, 'translation_id' => $selectedTranslation->id]) }}"
                                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">
                                        {{ __('messages.next') }} →
                                    </a>
                                @endif
                            </div>
                        </div>

                        <!-- Chapter Selector -->
                        <select onchange="window.location = '{{ route('bible.index', ['book' => $currentBook->id, 'translation_id' => $selectedTranslation->id]) }}?chapter=' + this.value"
                                class="w-full px-3 py-2 border rounded">
                            @for($ch = 1; $ch <= $currentBook->chapters_count; $ch++)
                                <option value="{{ $ch }}" {{ $ch == $currentChapter ? 'selected' : '' }}>
                                    {{ __('messages.chapter') }} {{ $ch }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <!-- Verses -->
                    <div class="space-y-6" style="font-size: {{ ['small' => '14px', 'large' => '19px'][$bibleFontSize ?? 'medium'] ?? '16px' }};">
                        @forelse($verses as $verse)
                            @php
                                $isBookmarked = $userBookmarks->where('verse', $verse->verse)->first();
                                $highlight = $userHighlights->where('verse', $verse->verse)->first();
                            @endphp
                            <div class="p-4 rounded {{ $highlight ? 'bg-' . $highlight->color . '-100 border-l-4 border-' . $highlight->color . '-500' : 'bg-gray-50' }}">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0">
                                        <span class="font-semibold text-gray-700 text-sm">{{ $verse->verse }}</span>
                                    </div>
                                    <div class="flex-grow">
                                        <p class="text-lg leading-relaxed text-gray-800">{{ $verse->text }}</p>
                                        @if($highlight && $highlight->note)
                                            <p class="mt-2 text-sm italic text-gray-600">
                                                📝 {{ $highlight->note }}
                                            </p>
                                        @elseif($isBookmarked && $isBookmarked->note)
                                            <p class="mt-2 text-sm italic text-gray-600">
                                                📝 {{ $isBookmarked->note }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 flex gap-2">
                                        <!-- Bookmark Button -->
                                        <form action="{{ route('bible.bookmark') }}" method="POST" class="inline bookmark-form">
                                            @csrf
                                            <input type="hidden" name="bible_book_id" value="{{ $currentBook->id }}">
                                            <input type="hidden" name="chapter" value="{{ $currentChapter }}">
                                            <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                            <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
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
                                                        <input type="hidden" name="bible_book_id" value="{{ $currentBook->id }}">
                                                        <input type="hidden" name="chapter" value="{{ $currentChapter }}">
                                                        <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                                        <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
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
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-8">{{ __('messages.no_verses_found') }}</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <h2 class="text-2xl font-bold text-gray-600 mb-4">{{ __('messages.welcome_to_bible') }}</h2>
                    <p class="text-gray-500 mb-6">{{ __('messages.select_book_to_start') }}</p>
                    <p class="text-sm text-gray-400">{{ __('messages.choose_translation_and_book') }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Search Bar at Bottom -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <form action="{{ route('bible.search') }}" method="GET" class="flex gap-4">
            <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
            <input type="text" name="q" placeholder="{{ __('messages.search_verses') }}"
                   class="flex-grow px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   minlength="3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                {{ __('messages.search') }}
            </button>
        </form>
        </div>
</div>

<script>
    // Handle bookmark removal on click if already bookmarked
    document.querySelectorAll('.bookmark-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button');

            if (submitBtn.classList.contains('text-yellow-500')) {
                e.preventDefault();

                // Change form action to remove bookmark
                this.action = '{{ route('bible.removeBookmark') }}';
                this.submit();
            }
        });
    });
</script>

@endsection