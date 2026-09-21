<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class OrganizationDirectoryController extends Controller
{
    // Only ever the radii the spec lists — an arbitrary client-supplied radius
    // would let a caller probe distance boundaries more precisely than the
    // product wants to support.
    private const NEARBY_RADII_KM = [5, 10, 25];

    public function index(Request $request): View
    {
        $query = Organization::query()->inPublicDirectory()->with('logo');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        foreach (['country', 'state', 'city'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        if ($request->query('has_location') === '1') {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        } elseif ($request->query('has_location') === '0') {
            $query->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'));
        }

        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $perPage = 12;

        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $radius = in_array((int) $request->query('radius_km'), self::NEARBY_RADII_KM, true)
                ? (int) $request->query('radius_km')
                : 10;

            // The bounding box only narrows candidates; exact distance and the
            // real radius cutoff happen here in PHP (see Organization::scopeNearby).
            $withinRadius = $query->nearby($lat, $lng, $radius)->get()
                ->map(function (Organization $organization) use ($lat, $lng) {
                    $organization->distance_km = $organization->distanceInKmFrom($lat, $lng);

                    return $organization;
                })
                ->filter(fn (Organization $organization) => $organization->distance_km <= $radius)
                ->sortBy('distance_km')
                ->values();

            $page = (int) ($request->query('page', 1));
            $organizations = new LengthAwarePaginator(
                $withinRadius->forPage($page, $perPage)->values(),
                $withinRadius->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $organizations = $query->orderBy('name')->paginate($perPage)->withQueryString();
        }

        return view('directory.index', [
            'organizations' => $organizations,
            'types' => Organization::TYPES,
            'radii' => self::NEARBY_RADII_KM,
            'filters' => $request->only(['q', 'type', 'country', 'state', 'city', 'has_location', 'lat', 'lng', 'radius_km']),
        ]);
    }

    public function show(Request $request, Organization $organization): View
    {
        $this->authorize('view', $organization);

        return view('directory.show', [
            'organization' => $organization->load('logo'),
        ]);
    }
}
