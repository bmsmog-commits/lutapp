<?php

namespace App\Services\Bible\ApiBible;

use Illuminate\Support\Collection;

/**
 * A chapter as served by API.Bible, with the publisher's copyright notice and
 * the FUMS token that must be reported when the text is displayed.
 *
 * `fetchedAt` is when API.Bible actually served the text (and issued the
 * token); `fromCache` says whether this instance came from the local cache.
 */
final class RemoteChapter
{
    /**
     * @param  Collection<int, RemoteVerse>  $verses
     */
    public function __construct(
        public readonly string $bibleId,
        public readonly string $chapterId,
        public readonly string $reference,
        public readonly Collection $verses,
        public readonly string $copyright,
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
            'chapter_id' => $this->chapterId,
            'reference' => $this->reference,
            'verses' => $this->verses->map(fn (RemoteVerse $v) => [$v->verse, $v->text])->all(),
            'copyright' => $this->copyright,
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
            $data['chapter_id'],
            $data['reference'],
            collect($data['verses'])->map(fn (array $v) => new RemoteVerse($v[0], $v[1]))->values(),
            $data['copyright'],
            $data['fums_token'] ?? null,
            (int) $data['fetched_at'],
            true,
        );
    }
}
