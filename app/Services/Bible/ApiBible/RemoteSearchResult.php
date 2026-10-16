<?php

namespace App\Services\Bible\ApiBible;

use Illuminate\Support\Collection;

/**
 * A page of API.Bible search results. `passageReferences` is filled when
 * API.Bible interprets the query as a reference (e.g. "John 3:16").
 */
final class RemoteSearchResult
{
    /**
     * @param  Collection<int, RemoteSearchHit>  $hits
     * @param  Collection<int, array{reference: string, usfm_book: string, chapter: int}>  $passageReferences
     */
    public function __construct(
        public readonly string $bibleId,
        public readonly string $query,
        public readonly int $total,
        public readonly int $offset,
        public readonly int $limit,
        public readonly Collection $hits,
        public readonly Collection $passageReferences,
        public readonly ?string $fumsToken,
        public readonly int $fetchedAt,
        public readonly bool $fromCache = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toCache(): array
    {
        return [
            'bible_id' => $this->bibleId,
            'query' => $this->query,
            'total' => $this->total,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'hits' => $this->hits->map(fn (RemoteSearchHit $h) => [$h->usfmBook, $h->chapter, $h->verse, $h->reference, $h->text])->all(),
            'passages' => $this->passageReferences->all(),
            'fums_token' => $this->fumsToken,
            'fetched_at' => $this->fetchedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCache(array $data): self
    {
        return new self(
            $data['bible_id'],
            $data['query'],
            (int) $data['total'],
            (int) $data['offset'],
            (int) $data['limit'],
            collect($data['hits'])->map(fn (array $h) => new RemoteSearchHit(...$h))->values(),
            collect($data['passages']),
            $data['fums_token'] ?? null,
            (int) $data['fetched_at'],
            true,
        );
    }
}
