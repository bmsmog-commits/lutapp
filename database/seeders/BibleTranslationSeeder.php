<?php

namespace Database\Seeders;

use App\Models\BibleTranslation;
use Illuminate\Database\Seeder;

class BibleTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'code' => 'KJV',
                'name' => 'King James Version',
                'language' => 'English',
                'description' => 'The King James Version is a translation of the Bible into Early Modern English, originally published in 1611.',
                'license' => 'Public Domain',
                'is_active' => true,
            ],
            [
                'code' => 'NIV',
                'name' => 'New International Version',
                'language' => 'English',
                'description' => 'The New International Version is an English Bible translation that emphasizes both word-for-word accuracy and readability.',
                'license' => 'Proprietary',
                'is_active' => true,
            ],
            [
                'code' => 'ESV',
                'name' => 'English Standard Version',
                'language' => 'English',
                'description' => 'The English Standard Version is a translation of the Bible designed to combine word-for-word translation with modern readability.',
                'license' => 'Proprietary',
                'is_active' => true,
            ],
            [
                'code' => 'NASB',
                'name' => 'New American Standard Bible',
                'language' => 'English',
                'description' => 'The NASB is known for its literal, word-for-word translation approach.',
                'license' => 'Proprietary',
                'is_active' => true,
            ],
            [
                'code' => 'YOR',
                'name' => 'Bibeli Mimo Inu (Yoruba Bible)',
                'language' => 'Yoruba',
                'description' => 'The Yoruba Bible translation for native Yoruba speakers in Nigeria and diaspora.',
                'license' => 'Public Domain',
                'is_active' => true,
            ],
            [
                'code' => 'FR',
                'name' => 'Bible Segond 21',
                'language' => 'French',
                'description' => 'A modern French Bible translation emphasizing clarity and readability.',
                'license' => 'Proprietary',
                'is_active' => true,
            ],
            [
                'code' => 'DE',
                'name' => 'Lutherbibel',
                'language' => 'German',
                'description' => 'The German Bible translation based on Martin Luther\'s original translation.',
                'license' => 'Proprietary',
                'is_active' => true,
            ],
        ];

        foreach ($translations as $translation) {
            BibleTranslation::updateOrCreate(
                ['code' => $translation['code']],
                $translation
            );
        }
    }
}
