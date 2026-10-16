<?php

namespace App\Services\Bible\ApiBible;

/**
 * One verse returned by API.Bible's search endpoint, exactly as served.
 */
final class RemoteSearchHit
{
    public function __construct(
        public readonly string $usfmBook,
        public readonly int $chapter,
        public readonly int $verse,
        public readonly string $reference,
        public readonly string $text,
    ) {
    }

    /**
     * Parse an API.Bible verse ID such as "JHN.3.16" (or the first verse of a
     * range such as "JHN.3.16-JHN.3.17").
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    public static function parseVerseId(string $verseId): ?array
    {
        if (! preg_match('/^([1-3A-Z]{3})\.(\d+)\.(\d+)/', $verseId, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2], (int) $m[3]];
    }
}
