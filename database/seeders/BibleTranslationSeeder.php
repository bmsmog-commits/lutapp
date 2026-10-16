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
     *
     * Phase 27.6 adds NLT and CSB as CATALOG METADATA ONLY (code, name,
     * language, publisher, licence URL, availability_status), plus the
     * license_type / copyright_holder / availability_status classification for
     * the pre-existing rows. ZERO verse text is added for any translation here
     * or anywhere else — the licensed entries exist purely so the reader can
     * honestly show "licence required" instead of silently omitting them.
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
            [
                'code' => 'NLT',
                'name' => 'New Living Translation',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'A thought-for-thought English translation emphasising clarity for contemporary readers.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'Tyndale House Foundation',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. No verse text is imported or stored. Phase 27.8: access via API.Bible (Bible ID d6e14a625393b4da-01) was verified on 2026-10-16, but it is only displayed when API_BIBLE_ALLOW_COPYRIGHTED=true — API.Bible\'s Starter plan covers non-commercial use only. Required notice: "Holy Bible, New Living Translation, copyright © 1996, 2004, 2015 by Tyndale House Foundation."',
                'api_bible_id' => 'd6e14a625393b4da-01',
                'is_active' => true,
            ],
            // Phase 27.8 — served remotely from API.Bible. Bible IDs and
            // copyright notices were verified against the live API on
            // 2026-10-16. Text is never imported into bible_verses.
            [
                'code' => 'WEB',
                'name' => 'World English Bible',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'A modern-English update of the American Standard Version, dedicated to the public domain.',
                'license' => 'Public Domain',
                'public_domain' => true,
                'redistributable' => true,
                'source_name' => 'API.Bible (eBible.org)',
                'source_url' => 'https://worldenglish.bible/',
                'attribution' => 'API.Bible copyright field: "PUBLIC DOMAIN". Served via API.Bible; cached for at most 14 days and never stored in bible_verses.',
                'api_bible_id' => '9879dbb7cfe39e4d-01',
                'is_active' => true,
            ],
            [
                'code' => 'BSB',
                'name' => 'Berean Standard Bible',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'A modern English translation dedicated to the public domain in 2023.',
                'license' => 'Public Domain',
                'public_domain' => true,
                'redistributable' => true,
                'source_name' => 'API.Bible (Berean Bible Translation Committee)',
                'source_url' => 'https://berean.bible/',
                'attribution' => 'API.Bible copyright field: "The Holy Bible, Berean Standard Bible, BSB is produced in cooperation with Bible Hub, Discovery Bible, OpenBible.com, and the Berean Bible Translation Committee. This text of God\'s Word has been dedicated to the public domain." Served via API.Bible; never stored in bible_verses.',
                'api_bible_id' => 'bba9f40183526463-01',
                'is_active' => true,
            ],
            // Phase 27.10 — verified 2026-10-16: the only ASV in the catalog;
            // edition 1901 (eBible.org eng-asv); API.Bible copyright field
            // "PUBLIC DOMAIN"; John 3 and Genesis 1 retrieved with FUMS
            // tokens. Held behind API_BIBLE_PENDING_VERIFICATION (default
            // "ASV") until the API.Bible plan and Lutapp's intended use
            // (Starter = non-commercial only) are confirmed by an operator.
            [
                'code' => 'ASV',
                'name' => 'American Standard Version (1901)',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'The 1901 American Standard Version, a revision of the King James Version; public domain.',
                'license' => 'Public Domain',
                'public_domain' => true,
                'redistributable' => true,
                'source_name' => 'API.Bible (eBible.org)',
                'source_url' => 'https://eBible.org/find/details.php?id=eng-asv',
                'attribution' => 'API.Bible copyright field: "PUBLIC DOMAIN". Served via API.Bible; cached for at most 14 days and never stored in bible_verses.',
                'api_bible_id' => '06125adad2d5898a-01',
                'is_active' => true,
            ],
            [
                'code' => 'CSB',
                'name' => 'Christian Standard Bible',
                'language' => 'English',
                'language_code' => 'en',
                'description' => 'An English translation balancing literal accuracy with readability.',
                'license' => 'Proprietary',
                'public_domain' => false,
                'redistributable' => false,
                'source_name' => 'Holman Bible Publishers',
                'attribution' => 'LICENSE STATUS: proprietary, confirmed. Catalog metadata only — no verse text has been imported. A distribution licence from Holman Bible Publishers is required before any CSB text may be stored or displayed.',
                'is_active' => true,
            ],
        ];

        // Phase 27.6 licence classification + surfaced availability state.
        // 'available' is deliberately never asserted here: it can only ever be
        // computed from real verse rows (BibleTranslation::availabilityState).
        $classification = [
            'KJV' => ['public_domain', null, null],
            'WEB' => ['public_domain', null, null],
            'BSB' => ['public_domain', null, null],
            'ASV' => ['public_domain', null, null],
            'NIV' => ['licensed_remote', 'Biblica, Inc.', 'https://www.biblica.com/terms-of-use/'],
            'ESV' => ['licensed_remote', 'Crossway', 'https://www.crossway.org/permissions/'],
            'NASB' => ['licensed_remote', 'The Lockman Foundation', 'https://www.lockman.org/permission-to-quote/'],
            'NLT' => ['licensed_remote', 'Tyndale House Foundation', 'https://tyndale.com/permissions'],
            'CSB' => ['licensed_remote', 'Holman Bible Publishers', 'https://csbible.com/about-the-csb/permissions/'],
            'FR' => ['licensed_remote', 'Société Biblique de Genève', null],
            'YOR' => ['unverified', null, null],
            'DE' => ['unverified', null, null],
        ];

        // Phase 27.9 — copyrighted translations verified ABSENT from this
        // API.Bible key's unfiltered catalog (251 Bibles, checked 2026-10-16).
        // They remain copyrighted; "not available to this account" is simply
        // the more precise reason. Re-check with:
        //   php artisan bible:api-bible-catalog --language=eng
        $notInApiBibleAccount = ['NIV', 'ESV', 'NASB', 'CSB', 'FR'];

        foreach ($translations as $translation) {
            $languageCode = $translation['language_code'];
            unset($translation['language_code']);

            $translation['language_id'] = Language::where('code', $languageCode)->value('id');

            [$licenseType, $copyrightHolder, $licenseUrl] = $classification[$translation['code']]
                ?? ['unverified', null, null];

            $translation['license_type'] = $licenseType;
            $translation['copyright_holder'] = $copyrightHolder;
            $translation['availability_status'] = match (true) {
                $licenseType === 'public_domain' => null, // computed from real verse rows
                in_array($translation['code'], $notInApiBibleAccount, true) => 'not_in_account',
                $licenseType === 'licensed_remote' => 'license_required',
                default => 'not_available',
            };

            if ($licenseUrl !== null) {
                $translation['license_url'] = $licenseUrl;
            }

            BibleTranslation::updateOrCreate(
                ['code' => $translation['code']],
                $translation
            );
        }
    }
}
