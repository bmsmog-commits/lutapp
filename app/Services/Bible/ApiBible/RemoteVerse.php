<?php

namespace App\Services\Bible\ApiBible;

/**
 * One verse of remotely served Scripture. Shaped like BibleVerse's `verse` and
 * `text` attributes so the reader templates render it unchanged. Never saved.
 */
final class RemoteVerse
{
    public function __construct(
        public readonly int $verse,
        public readonly string $text,
    ) {
    }
}
