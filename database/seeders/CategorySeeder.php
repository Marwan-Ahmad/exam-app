<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            1 => 'إعلام',
            2 => 'قانون',
            3 => 'هندسة',
            4 => 'اقتصاد',
            5 => 'تربية',
            6 => 'طب',
            7 => 'إنكليزية',
            8 => 'ماجستير',
            9 => 'علوم صحية',
        ];

        foreach ($categories as $id => $name) {
            Category::updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
