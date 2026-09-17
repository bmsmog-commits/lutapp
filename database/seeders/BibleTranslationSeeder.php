<?php

namespace Database\Seeders;

use App\Models\BibleTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;

class BibleTranslationSeeder extends Seeder
{
    /**
     * Phase 4 licensing audit. Nothing here is asserted from a scrape or guess —
     * where the exact edition/publisher was not specified in the original seed
     * data (Yoruba, German), licensing is marked UNVERIFIED rather than assumed.
     * `redistributable` is the single flag the rest of the app must check before
     * importing or displaying verse text for a translation — see
     * BibleTranslation::isLegallyRedistributable().
     */
    public function run(): void
    {
        $translations = [
            [
                'code' => 'KJV',
                'name' => 'King James Version',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'The King James Version is a translation of the Bible into Early Modern English, originally published in 1611.',
                'license' => 'Public Domain',
                'public_domain' => true,
                'redistributable' => true,
                'source_name' => 'Public domain text (pre-1769 standardized edition)',
                'attribution' => 'Public domain worldwide. Note: the Crown holds a perpetual patent restricting commercial printing of the KJV within the United Kingdom specifically — this does not affect public-domain status elsewhere.',
                'is_active' => true,
            ],
            [
                'code' => 'NIV',
                'name' => 'New International Version',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'The New International Version is an English Bible translation that emphasizes both word-for-word accuracy and readability.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'Biblica, Inc.',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. Verse text must not be imported or displayed until a distribution license is obtained from Biblica.',
                'is_active' => true,
            ],
            [
                'code' => 'ESV',
                'name' => 'English Standard Version',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'The English Standard Version is a translation of the Bible designed to combine word-for-word translation with modern readability.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'Crossway',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. Verse text must not be imported or displayed until a distribution license is obtained from Crossway.',
                'is_active' => true,
            ],
            [
                'code' => 'NASB',
                'name' => 'New American Standard Bible',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'The NASB is known for its literal, word-for-word translation approach.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'The Lockman Foundation',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. Verse text must not be imported or displayed until a distribution license is obtained from The Lockman Foundation.',
                'is_active' => true,
            ],
            [
                'code' => 'YOR',
                'name' => 'Bibeli Mimo Inu (Yoruba Bible)',
                'language' => 'Yoruba',
                'language_code' => 'yo',
                'description' => 'The Yoruba Bible translation for native Yoruba speakers in Nigeria and diaspora.',
                'license' => 'Unverified',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => null,
                'attribution' => 'LICENSE STATUS: UNVERIFIED. The original seed data claimed "Public Domain" but did not specify a publisher or edition/year, so this could not be confirmed. Phase 5 research found a promising but unconfirmed lead: a "Yoruba (1900) Bible" on the Internet Archive (archive.org/details/YOROLD_DBS_HS), where the hosting body states only that it is "unaware of any copyright restrictions" — a disclaimer, not a legal clearance, so it is not sufficient to mark this redistributable. Do not import until an affirmative public-domain or licensed status is independently confirmed.',
                'is_active' => true,
            ],
            [
                'code' => 'FR',
                'name' => 'Bible Segond 21',
                'language' => 'French',
                'language_code' => 'fr',
                'description' => 'A modern French Bible translation emphasizing clarity and readability.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'Société Biblique de Genève',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. The Segond 21 revision is copyrighted; only the original 1910 Segond translation is public domain, and this is not that edition.',
                'is_active' => true,
            ],
            [
                'code' => 'DE',
                'name' => 'Lutherbibel',
                'language' => 'German',
                'language_code' => 'de',
                'description' => 'The German Bible translation based on Martin Luther\'s original translation.',
                'license' => 'Unverified',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => null,
                'attribution' => 'LICENSE STATUS: UNVERIFIED. "Lutherbibel" commonly refers to the modern revised edition (copyrighted by the Deutsche Bibelgesellschaft), not Luther\'s 1545 public-domain original, but no specific edition/year was recorded here. Treated conservatively as not redistributable.',
                'is_active' => true,
            ],
        ];

        foreach ($translations as $translation) {
            $languageCode = $translation['language_code'];
            unset($translation['language_code']);

            $translation['language_id'] = Language::where('code', $languageCode)->value('id');

            BibleTranslation::updateOrCreate(
                ['code' => $translation['code']],
                $translation
            );
        }
    }
}
