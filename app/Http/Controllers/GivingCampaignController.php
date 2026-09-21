<?php

namespace App\Http\Controllers;

use App\Models\GivingCampaign;
use App\Models\Organization;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GivingCampaignController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $campaigns = GivingCampaign::query()
            ->where('organization_id', $organization->id)
            ->visibleTo($request->user())
            ->latest()
            ->paginate(12);

        return view('giving.index', ['organization' => $organization, 'campaigns' => $campaigns]);
    }

    public function create(Request $request, Organization $organization): View
    {
        $this->authorize('create', [GivingCampaign::class, $organization]);

        return view('giving.create', [
            'organization' => $organization,
            'statuses' => GivingCampaign::STATUSES,
            'currencies' => config('payments.currencies'),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', [GivingCampaign::class, $organization]);

        $data = $this->validateCampaign($request);
        $data['organization_id'] = $organization->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = 'draft';

        $campaign = GivingCampaign::create($data);

        return redirect()->route('giving.show', [$organization, $campaign])->with('status', 'Campaign saved as a draft.');
    }

    public function show(Request $request, Organization $organization, GivingCampaign $campaign): View
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('view', $campaign);

        return view('giving.show', [
            'organization' => $organization,
            'campaign' => $campaign,
            'canManage' => $request->user()?->can('update', $campaign) ?? false,
            'raisedAmount' => $campaign->raisedAmountMinorUnits(),
            'currencies' => config('payments.currencies'),
        ]);
    }

    public function edit(Request $request, Organization $organization, GivingCampaign $campaign): View
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('update', $campaign);

        return view('giving.edit', ['organization' => $organization, 'campaign' => $campaign, 'currencies' => config('payments.currencies')]);
    }

    public function update(Request $request, Organization $organization, GivingCampaign $campaign): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('update', $campaign);

        $campaign->update($this->validateCampaign($request));

        return redirect()->route('giving.show', [$organization, $campaign])->with('status', 'Campaign updated.');
    }

    public function publish(Request $request, Organization $organization, GivingCampaign $campaign): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('update', $campaign);

        $campaign->update(['status' => 'published']);

        return redirect()->route('giving.show', [$organization, $campaign])->with('status', 'Campaign published.');
    }

    public function close(Request $request, Organization $organization, GivingCampaign $campaign): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('update', $campaign);

        $campaign->update(['status' => 'closed']);

        return redirect()->route('giving.show', [$organization, $campaign])->with('status', 'Campaign closed.');
    }

    public function cancel(Request $request, Organization $organization, GivingCampaign $campaign): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('update', $campaign);

        $campaign->update(['status' => 'cancelled']);

        return redirect()->route('giving.show', [$organization, $campaign])->with('status', 'Campaign cancelled.');
    }

    public function history(Request $request, Organization $organization, GivingCampaign $campaign): View
    {
        $this->assertBelongsToOrganization($organization, $campaign);
        $this->authorize('viewGivingHistory', $campaign);

        $donations = $campaign->donations()->with('donor')->latest()->paginate(20);

        return view('giving.history', ['organization' => $organization, 'campaign' => $campaign, 'donations' => $donations]);
    }

    private function validateCampaign(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(config('payments.currencies'))],
            'visibility' => ['required', Rule::in(GivingCampaign::VISIBILITIES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['target_amount'] = $request->filled('target_amount')
            ? Money::toMinorUnits($request->input('target_amount'))
            : null;

        return $data;
    }

    private function assertBelongsToOrganization(Organization $organization, GivingCampaign $campaign): void
    {
        abort_unless($campaign->organization_id === $organization->id, 404);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        $suffix = 1;

        while (GivingCampaign::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
