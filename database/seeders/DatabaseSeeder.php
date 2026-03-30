<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LegalCase;
use Database\Seeders\LawLibrarySeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Seed default Saudi laws (RAG) so cases can match valid legislation
        $this->call(LawLibrarySeeder::class);

        // 2. Get or create test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'أحمد المنصور',
                'password' => Hash::make('password'),
            ]
        );

        // Default required fields for all cases
        $defaults = [
            'user_id' => $user->id,
            'skill_version' => '1.0.0',
            'skill_hash' => md5('1.0.0'),
            'model_used' => 'gpt-4',
        ];

        // Create sample legal cases
        $casesData = [
            [
                'title' => 'نزاع ملكية عقارية - الشيخ زايد',
                'intake_text' => 'قضية نزاع حول ملكية عقار في منطقة الشيخ زايد بين شركة الواحة للتطوير وطرف آخر.',
                'status' => 'phase2_processing',
                'phase' => 2,
            ],
            [
                'title' => 'مراجعة عقد شراكة دولية',
                'intake_text' => 'مراجعة وصياغة عقد شراكة بين شركة محلية وشركة دولية في مجال التكنولوجيا.',
                'status' => 'phase3_processing',
                'phase' => 3,
            ],
            [
                'title' => 'تأسيس شركة مساهمة',
                'intake_text' => 'إجراءات تأسيس شركة مساهمة جديدة لمجموعة الاستثمار المتحدة.',
                'status' => 'phase3_completed',
                'phase' => 3,
            ],
            [
                'title' => 'قضية عمالية - فصل تعسفي',
                'intake_text' => 'قضية فصل تعسفي لموظف في شركة خاصة، المطالبة بالتعويض وإعادة التوظيف.',
                'status' => 'phase1_pending',
                'phase' => 1,
            ],
            [
                'title' => 'نزاع تجاري - عقد توريد',
                'intake_text' => 'نزاع حول عقد توريد بين شركتين بسبب عدم الالتزام بشروط التسليم.',
                'status' => 'phase2_processing',
                'phase' => 2,
            ],
            [
                'title' => 'قضية أحوال شخصية - نفقة',
                'intake_text' => 'قضية نفقة وحضانة أطفال بعد الطلاق.',
                'status' => 'phase1_pending',
                'phase' => 1,
            ],
            [
                'title' => 'مراجعة عقد إيجار تجاري',
                'intake_text' => 'مراجعة وتعديل عقد إيجار محل تجاري في مركز تجاري كبير.',
                'status' => 'phase3_completed',
                'phase' => 3,
            ],
            [
                'title' => 'قضية جنائية - احتيال مالي',
                'intake_text' => 'قضية احتيال مالي ضد شركة استثمارية وهمية.',
                'status' => 'phase2_processing',
                'phase' => 2,
            ],
        ];

        foreach ($casesData as $caseData) {
            LegalCase::create(array_merge($defaults, $caseData));
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('📧 Email: test1@example.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('📊 Cases created: ' . LegalCase::count());
    }
}
