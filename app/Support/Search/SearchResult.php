<?php

namespace App\Support\Search;

// The one shape every search provider normalizes into, so the UI can render
// heterogeneous result types (a Job, an Organization, a Bible verse, ...)
// through a single Blade partial without knowing about each model.
final class SearchResult
{
    public function __construct(
        public readonly string $type,
        public readonly int|string $id,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly ?string $url,
        public readonly ?string $image,
        public readonly ?string $category,
    ) {
    }
}
