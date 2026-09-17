<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'en-US', 'name' => 'English (United States)', 'native_name' => 'English (US)', 'region' => 'United States', 'direction' => 'ltr'],
            ['code' => 'en-GB', 'name' => 'English (United Kingdom)', 'native_name' => 'English (UK)', 'region' => 'United Kingdom', 'direction' => 'ltr'],
            ['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'region' => null, 'direction' => 'rtl'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'sw', 'name' => 'Swahili', 'native_name' => 'Kiswahili', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'ha', 'name' => 'Hausa', 'native_name' => 'Hausa', 'region' => null, 'direction' => 'ltr'],
            ['code' => 'ig', 'name' => 'Igbo', 'native_name' => 'Igbo', 'region' => null, 'direction' => 'ltr'],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
