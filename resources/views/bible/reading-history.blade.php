@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h1 class="text-3xl font-bold">{{ __('messages.reading_history') }}</h1>
            <p class="text-gray-600 mt-2">{{ $history->total() }} {{ __('messages.chapters_read') }}</p>
        </div>

        <!-- Reading History List -->
        @if($history->count() > 0)
            <div class="space-y-4">
                @foreach($history as $record)
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                        <div class="flex items-start justify-between">
                            <div class="flex-grow">
                                <h3 class="text-lg font-semibold mb-2">
                                    <a href="{{ route('bible.index', ['book' => $record->bible_book_id, 'chapter' => $record->chapter, 'translation_id' => $record->translation_id]) }}"
                                       class="text-blue-600 hover:underline">
                                        {{ $record->book->name }} {{ $record->chapter }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600 mb-2">
                                    {{ $record->translation->name }} ({{ $record->translation->code }})
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ __('messages.last_read') }}: 
                                    <strong>{{ $record->last_read_at->format(__('messages.datetime_format')) }}</strong>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-4 py-2 bg-blue-100 text-blue-900 rounded-full text-sm font-semibold">
                                    {{ __('messages.chapter') }} {{ $record->chapter }}
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar (if this book has multiple chapters) -->
                        @if($record->book->chapters_count > 1)
                            <div class="mt-4 pt-4 border-t">
                                <p class="text-xs text-gray-600 mb-2">
                                    {{ __('messages.progress') }}: {{ $record->chapter }} / {{ $record->book->chapters_count }}
                                </p>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($record->chapter / $record->book->chapters_count * 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $history->links() }}
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <h2 class="text-2xl font-bold text-gray-600 mb-4">{{ __('messages.no_reading_history') }}</h2>
                <p class="text-gray-500 mb-6">{{ __('messages.start_reading_to_track') }}</p>
                <a href="{{ route('bible.index') }}" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    {{ __('messages.start_reading') }}
                </a>
            </div>
        @endif

        <!-- Quick Stats -->
        @if($history->count() > 0)
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-3xl font-bold text-blue-600">{{ $history->total() }}</p>
                    <p class="text-gray-600 mt-2">{{ __('messages.chapters_read') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-3xl font-bold text-green-600">{{ auth()->user()->bibleBookmarks()->count() }}</p>
                    <p class="text-gray-600 mt-2">{{ __('messages.bookmarks') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-3xl font-bold text-purple-600">{{ auth()->user()->bibleHighlights()->count() }}</p>
                    <p class="text-gray-600 mt-2">{{ __('messages.highlights') }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
