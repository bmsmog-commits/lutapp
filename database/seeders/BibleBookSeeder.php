<?php

namespace Database\Seeders;

use App\Models\BibleBook;
use Illuminate\Database\Seeder;

class BibleBookSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            ['Genesis', 'Gen', 'Old Testament', 50],
            ['Exodus', 'Exo', 'Old Testament', 40],
            ['Leviticus', 'Lev', 'Old Testament', 27],
            ['Numbers', 'Num', 'Old Testament', 36],
            ['Deuteronomy', 'Deu', 'Old Testament', 34],
            ['Joshua', 'Jos', 'Old Testament', 24],
            ['Judges', 'Jdg', 'Old Testament', 21],
            ['Ruth', 'Rut', 'Old Testament', 4],
            ['1 Samuel', '1Sa', 'Old Testament', 31],
            ['2 Samuel', '2Sa', 'Old Testament', 24],
            ['1 Kings', '1Ki', 'Old Testament', 22],
            ['2 Kings', '2Ki', 'Old Testament', 25],
            ['1 Chronicles', '1Ch', 'Old Testament', 29],
            ['2 Chronicles', '2Ch', 'Old Testament', 36],
            ['Ezra', 'Ezr', 'Old Testament', 10],
            ['Nehemiah', 'Neh', 'Old Testament', 13],
            ['Esther', 'Est', 'Old Testament', 10],
            ['Job', 'Job', 'Old Testament', 42],
            ['Psalms', 'Psa', 'Old Testament', 150],
            ['Proverbs', 'Pro', 'Old Testament', 31],
            ['Ecclesiastes', 'Ecc', 'Old Testament', 12],
            ['Song of Solomon', 'Son', 'Old Testament', 8],
            ['Isaiah', 'Isa', 'Old Testament', 66],
            ['Jeremiah', 'Jer', 'Old Testament', 52],
            ['Lamentations', 'Lam', 'Old Testament', 5],
            ['Ezekiel', 'Eze', 'Old Testament', 48],
            ['Daniel', 'Dan', 'Old Testament', 12],
            ['Hosea', 'Hos', 'Old Testament', 14],
            ['Joel', 'Joe', 'Old Testament', 3],
            ['Amos', 'Amo', 'Old Testament', 9],
            ['Obadiah', 'Oba', 'Old Testament', 1],
            ['Jonah', 'Jon', 'Old Testament', 4],
            ['Micah', 'Mic', 'Old Testament', 7],
            ['Nahum', 'Nah', 'Old Testament', 3],
            ['Habakkuk', 'Hab', 'Old Testament', 3],
            ['Zephaniah', 'Zep', 'Old Testament', 3],
            ['Haggai', 'Hag', 'Old Testament', 2],
            ['Zechariah', 'Zec', 'Old Testament', 14],
            ['Malachi', 'Mal', 'Old Testament', 4],
            ['Matthew', 'Mat', 'New Testament', 28],
            ['Mark', 'Mar', 'New Testament', 16],
            ['Luke', 'Luk', 'New Testament', 24],
            ['John', 'Joh', 'New Testament', 21],
            ['Acts', 'Act', 'New Testament', 28],
            ['Romans', 'Rom', 'New Testament', 16],
            ['1 Corinthians', '1Co', 'New Testament', 16],
            ['2 Corinthians', '2Co', 'New Testament', 13],
            ['Galatians', 'Gal', 'New Testament', 6],
            ['Ephesians', 'Eph', 'New Testament', 6],
            ['Philippians', 'Php', 'New Testament', 4],
            ['Colossians', 'Col', 'New Testament', 4],
            ['1 Thessalonians', '1Th', 'New Testament', 5],
            ['2 Thessalonians', '2Th', 'New Testament', 3],
            ['1 Timothy', '1Ti', 'New Testament', 6],
            ['2 Timothy', '2Ti', 'New Testament', 4],
            ['Titus', 'Tit', 'New Testament', 3],
            ['Philemon', 'Phm', 'New Testament', 1],
            ['Hebrews', 'Heb', 'New Testament', 13],
            ['James', 'Jam', 'New Testament', 5],
            ['1 Peter', '1Pe', 'New Testament', 5],
            ['2 Peter', '2Pe', 'New Testament', 3],
            ['1 John', '1Jo', 'New Testament', 5],
            ['2 John', '2Jo', 'New Testament', 1],
            ['3 John', '3Jo', 'New Testament', 1],
            ['Jude', 'Jud', 'New Testament', 1],
            ['Revelation', 'Rev', 'New Testament', 22],
        ];

        foreach ($books as $index => [$name, $abbreviation, $testament, $chapters]) {
            BibleBook::updateOrCreate(
                ['sort_order' => $index + 1],
                [
                    'name' => $name,
                    'abbreviation' => $abbreviation,
                    'testament' => $testament,
                    'chapters_count' => $chapters,
                ]
            );
        }
    }
}
