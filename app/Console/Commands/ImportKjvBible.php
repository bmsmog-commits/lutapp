<?php

namespace App\Console\Commands;

use App\Models\BibleTranslation;
use App\Services\Bible\BibleImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports the full King James Version text from the aruljohn/Bible-kjv dataset
 * (https://github.com/aruljohn/Bible-kjv, MIT-licensed repository structure;
 * the underlying KJV text itself is independently public domain worldwide,
 * except for the UK Crown printing patent, which does not affect this use).
 *
 * Per Phase 5 policy, this corpus is never bundled into the repository — it is
 * fetched on demand so the codebase stays free of large third-party content.
 * Always run with --dry-run first (see Rule 7 of the Phase 5 spec).
 */
class ImportKjvBible extends Command
{
    protected $signature = 'bible:import-kjv {--dry-run : Validate without writing to the database}';

    protected $description = 'Fetch and import the complete public-domain KJV text into the KJV translation.';

    private const SOURCE_BASE = 'https://raw.githubusercontent.com/aruljohn/Bible-kjv/master/';

    public function handle(BibleImportService $importer): int
    {
        $translation = BibleTranslation::where('code', 'KJV')->first();

        if (! $translation) {
            $this->error('No KJV translation record found. Run BibleTranslationSeeder first.');

            return self::FAILURE;
        }

        if (! $translation->isLegallyRedistributable()) {
            $this->error('KJV is not marked redistributable — refusing to import. Check translation licensing metadata.');

            return self::FAILURE;
        }

        $books = json_decode(Http::timeout(15)->get(self::SOURCE_BASE.'Books.json')->body(), true);

        if (! is_array($books) || count($books) !== 66) {
            $this->error('Could not fetch a valid 66-book list from the source. Aborting — no partial import.');

            return self::FAILURE;
        }

        $this->info('Fetching '.count($books).' books from source...');
        $rows = [];
        $fetchErrors = [];

        foreach ($books as $book) {
            $filename = str_replace(' ', '', $book).'.json';
            $response = Http::timeout(15)->get(self::SOURCE_BASE.$filename);

            if (! $response->ok()) {
                $fetchErrors[] = "{$book}: HTTP {$response->status()}";

                continue;
            }

            $data = $response->json();

            if (! isset($data['book'], $data['chapters']) || $data['book'] !== $book) {
                $fetchErrors[] = "{$book}: unexpected structure or book-name mismatch.";

                continue;
            }

            foreach ($data['chapters'] as $chapter) {
                foreach ($chapter['verses'] as $verse) {
                    $rows[] = [
                        'book' => $book,
                        'chapter' => (int) $chapter['chapter'],
                        'verse' => (int) $verse['verse'],
                        'text' => $verse['text'],
                    ];
                }
            }
        }

        if (! empty($fetchErrors)) {
            $this->error('Aborting: '.count($fetchErrors)." book(s) failed to fetch or validate:\n".implode("\n", $fetchErrors));

            return self::FAILURE;
        }

        $this->info('Fetched '.count($rows).' verse rows across 66 books.');

        $dryRun = (bool) $this->option('dry-run');
        $result = $importer->import($translation, $rows, dryRun: $dryRun);

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Imported: {$result['imported']}, Skipped: {$result['skipped']}");

        if (! empty($result['errors'])) {
            $this->warn('Errors:');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line("  - {$error}");
            }
            if (count($result['errors']) > 20) {
                $this->line('  ... and '.(count($result['errors']) - 20).' more.');
            }
        }

        if ($result['skipped'] > 0) {
            $this->error('Import had skipped rows — review before trusting this as a complete import.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
