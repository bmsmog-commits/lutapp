@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h1 class="text-3xl font-bold">{{ __('messages.my_bookmarks') }}</h1>
            <p class="text-gray-600 mt-2">{{ $bookmarks->total() }} {{ __('messages.bookmarks_saved') }}</p>
        </div>

        <!-- Bookmarks List -->
        @if($bookmarks->count() > 0)
            <div class="space-y-4">
                @foreach($bookmarks as $bookmark)
                    @php
                        $highlight = auth()->user()->bibleHighlights()
                            ->where('bible_book_id', $bookmark->bible_book_id)
                            ->where('chapter', $bookmark->chapter)
                            ->where('verse', $bookmark->verse)
                            ->where('translation_id', $bookmark->translation_id)
                            ->first();
                    @endphp
                    <div class="bg-white rounded-lg shadow p-6 {{ $highlight ? 'bg-' . $highlight->color . '-50 border-l-4 border-' . $highlight->color . '-500' : '' }}">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    <a href="{{ route('bible.index', ['book' => $bookmark->bible_book_id, 'chapter' => $bookmark->chapter, 'translation_id' => $bookmark->translation_id]) }}"
                                       class="text-blue-600 hover:underline">
                                        {{ $bookmark->book->name }} {{ $bookmark->chapter }}:{{ $bookmark->verse }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ $bookmark->translation->name }} - 
                                    {{ $bookmark->created_at->format(__('messages.date_format')) }}
                                </p>
                            </div>
                            <form action="{{ route('bible.removeBookmark') }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="bible_book_id" value="{{ $bookmark->bible_book_id }}">
                                <input type="hidden" name="chapter" value="{{ $bookmark->chapter }}">
                                <input type="hidden" name="verse" value="{{ $bookmark->verse }}">
                                <input type="hidden" name="translation_id" value="{{ $bookmark->translation_id }}">
                                <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">
                                    ✕ {{ __('messages.remove') }}
                                </button>
                            </form>
                        </div>

                        <!-- Verse Text -->
                        <p class="text-lg leading-relaxed text-gray-800 mb-3">
                            {{ __('messages.verse_text_not_available') }}
                        </p>

                        <!-- Bookmark Note -->
                        @if($bookmark->note)
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                <p class="text-sm font-semibold text-blue-900">{{ __('messages.your_note') }}:</p>
                                <p class="text-blue-800">{{ $bookmark->note }}</p>
                            </div>
                        @endif

                        <!-- Associated Highlight -->
                        @if($highlight)
                            <div class="mt-4 p-4 rounded bg-white border">
                                <p class="text-sm font-semibold text-gray-700 mb-2">
                                    ● {{ ucfirst($highlight->color) }} {{ __('messages.highlight') }}
                                </p>
                                @if($highlight->note)
                                    <p class="text-sm text-gray-600">{{ $highlight->note }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $bookmarks->links() }}
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <h2 class="text-2xl font-bold text-gray-600 mb-4">{{ __('messages.no_bookmarks_yet') }}</h2>
                <p class="text-gray-500 mb-6">{{ __('messages.create_first_bookmark') }}</p>
                <a href="{{ route('bible.index') }}" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    {{ __('messages.go_to_bible') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
