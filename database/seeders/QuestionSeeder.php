<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $categoryMap = [
            'إعلام' => 1,
            'قانون' => 2,
            'هندسة' => 3,
            'اقتصاد' => 4,
            'تربية' => 5,
            'طب' => 6,
        ];

        $files = [
            ['name' => 'إعلام', 'path' => database_path('seeders/data/media.php')],
            ['name' => 'قانون', 'path' => database_path('seeders/data/law.php')],
            ['name' => 'هندسة', 'path' => database_path('seeders/data/engineering.php')],
            ['name' => 'اقتصاد', 'path' => database_path('seeders/data/economy.php')],
            ['name' => 'تربية', 'path' => database_path('seeders/data/education.php')],
            ['name' => 'طب', 'path' => database_path('seeders/data/medicine.php')], // الأسئلة القديمة
        ];

        $allQuestions = [];
        foreach ($files as $file) {
            $qs = $this->loadQuestionsFromFile($file['path']);
            foreach ($qs as &$q) {
                // نضمن أن كل سؤال يحمل التخصص من الملف
                $q['category'] = $file['name'];
            }
            $allQuestions = array_merge($allQuestions, $qs);
        }

        foreach ($allQuestions as $q) {
            $categoryName = $q['category'] ?? 'طب';
            $categoryId = $categoryMap[$categoryName] ?? $categoryMap['طب'];

            $question = Question::create([
                'category_id' => $categoryId,
                'category' => $categoryName,
                'question_text' => $q['question_text'],
                'correct_option' => $q['correct_option'],
            ]);

            foreach ($q['options'] as $index => $text) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_number' => $index + 1,
                    'option_text' => $text,
                ]);
            }
        }
    }

    protected function loadQuestionsFromFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $questions = [];
        include $path; // يجب أن يعرّف المتغير $questions
        return $questions ?? [];
    }
}
