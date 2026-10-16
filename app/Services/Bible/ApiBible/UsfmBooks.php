<?php

namespace App\Services\Bible\ApiBible;

/**
 * Maps Lutapp's canonical 66-book order (bible_books.sort_order) to the USFM
 * book codes API.Bible uses in chapter and verse IDs (e.g. "JHN.3").
 */
final class UsfmBooks
{
    private const CODES = [
        1 => 'GEN', 'EXO', 'LEV', 'NUM', 'DEU', 'JOS', 'JDG', 'RUT', '1SA', '2SA',
        '1KI', '2KI', '1CH', '2CH', 'EZR', 'NEH', 'EST', 'JOB', 'PSA', 'PRO',
        'ECC', 'SNG', 'ISA', 'JER', 'LAM', 'EZK', 'DAN', 'HOS', 'JOL', 'AMO',
        'OBA', 'JON', 'MIC', 'NAM', 'HAB', 'ZEP', 'HAG', 'ZEC', 'MAL',
        'MAT', 'MRK', 'LUK', 'JHN', 'ACT', 'ROM', '1CO', '2CO', 'GAL', 'EPH',
        'PHP', 'COL', '1TH', '2TH', '1TI', '2TI', 'TIT', 'PHM', 'HEB', 'JAS',
        '1PE', '2PE', '1JN', '2JN', '3JN', 'JUD', 'REV',
    ];

    public static function forSortOrder(int $sortOrder): ?string
    {
        return self::CODES[$sortOrder] ?? null;
    }

    public static function sortOrderFor(string $usfmCode): ?int
    {
        $sortOrder = array_search(strtoupper($usfmCode), self::CODES, true);

        return $sortOrder === false ? null : $sortOrder;
    }
}
