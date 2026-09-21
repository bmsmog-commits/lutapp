<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $query = trim((string) $request->query('q', ''));
        $type = $request->query('type', 'all');
        $viewer = $request->user();

        $filters = $request->only([
            'category', 'work_mode', 'location_mode', 'organization_id', 'city',
            'country', 'state', 'language_id', 'language', 'creator', 'date', 'translation_id', 'book_id',
        ]);

        // 'type_filter' rather than 'type' in the query string, since 'type'
        // is already the search-tab selector (jobs/events/organizations/...).
        if ($request->filled('type_filter')) {
            $filters['type'] = $request->query('type_filter');
        }

        if ($type !== 'all' && $search->provider($type)) {
            $results = $search->searchType($type, $query, $viewer, $filters);
            $grouped = null;
        } else {
            $type = 'all';
            $results = null;
            $grouped = $query !== '' ? $search->globalSearch($query, $viewer) : [];
        }

        // The viewer's own following list, never anyone else's — a search
        // result only ever reveals "am I following this person," not any
        // other private relationship data.
        $followingIds = $viewer ? $viewer->following()->pluck('users.id')->all() : [];

        return view('search.index', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'grouped' => $grouped,
            'providerKeys' => $search->providerKeys(),
            'labels' => collect($search->providerKeys())->mapWithKeys(fn ($key) => [$key => $search->provider($key)->label()]),
            'followingIds' => $followingIds,
        ]);
    }
}
