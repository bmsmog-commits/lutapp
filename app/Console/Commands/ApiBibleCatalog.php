<?php

namespace App\Console\Commands;

use App\Models\BibleTranslation;
use App\Services\Bible\ApiBible\ApiBibleClient;
use App\Services\Bible\ApiBible\ApiBibleException;
use Illuminate\Console\Command;

/**
 * Phase 27.8 — verify, against the live API, which Bibles this API.Bible key
 * can access and whether each linked translation actually returns a chapter.
 * Read-only: nothing is imported or stored.
 */
class ApiBibleCatalog extends Command
{
    protected $signature = 'bible:api-bible-catalog
        {--language= : Only list Bibles whose language ID matches, e.g. eng}
        {--check-linked : Fetch John 3 for every translation linked to an API.Bible ID}';

    protected $description = 'List the API.Bible catalog available to the configured key and verify linked translations.';

    public function handle(ApiBibleClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('API.Bible is not configured (API_BIBLE_API_KEY / API_BIBLE_BASE_URL).');

            return self::FAILURE;
        }

        try {
            $bibles = $client->bibles();
        } catch (ApiBibleException $e) {
            $this->error("Catalog request failed: {$e->reason}");

            return self::FAILURE;
        }

        $language = $this->option('language');
        $rows = collect($bibles)
            ->filter(fn (array $b) => ! $language || ($b['language']['id'] ?? null) === $language)
            ->map(fn (array $b) => [$b['id'], $b['abbreviationLocal'] ?? $b['abbreviation'] ?? '', $b['name'] ?? '', $b['language']['id'] ?? ''])
            ->values();

        $this->info(count($bibles).' Bibles available to this key'.($language ? ", {$rows->count()} in '{$language}'" : '').'.');
        $this->table(['Bible ID', 'Abbr.', 'Name', 'Lang'], $rows->all());

        if (! $this->option('check-linked')) {
            return self::SUCCESS;
        }

        $failed = false;
        foreach (BibleTranslation::whereNotNull('api_bible_id')->orderBy('code')->get() as $translation) {
            try {
                $chapter = $client->chapter($translation->api_bible_id, 'JHN', 3);
                $this->line("  ✓ {$translation->code} ({$translation->api_bible_id}): {$chapter->reference}, {$chapter->verses->count()} verses");
            } catch (ApiBibleException $e) {
                $failed = true;
                $this->line("  ✗ {$translation->code} ({$translation->api_bible_id}): {$e->reason}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
