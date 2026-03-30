<?php
/**
 * Create a new test case for RAG testing
 */
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find first user
$user = App\Models\User::first();
if (!$user) {
    echo "No user found\n";
    exit(1);
}
echo "User: " . $user->email . "\n";

// Get relevant laws
$laws = App\Models\LawRegistry::take(3)->get();
echo "Laws: " . $laws->pluck("name")->implode(", ") . "\n";

// Case description: Criminal/Evidence case invoking seeded laws
$description = "يطلب موكلنا عبدالرحمن محمد السالم إثبات براءته من تهمة الاحتيال التجاري المنسوبة إليه من قِبل شريكه التجاري السابق أحمد خالد الزهراني، الذي يدّعي أن موكلنا اختلس مبلغ مليوني ريال سعودي من الشركة المشتركة خلال الفترة من 2022 إلى 2023. يمتلك موكلنا وثائق وسجلات محاسبية مدعومة بشهادات شهود ثقات تثبت أن جميع المبالغ المالية صُرفت بموافقة الشريك وفق العقود الموقعة. يطلب موكلنا تطبيق أحكام نظام الإثبات السعودي في إثبات البيانات المالية والعقود، واستخدام نظام المرافعات الشرعية في ضمان حق الدفاع الكامل. كما يطالب موكلنا بحفظ الدعوى الجزائية وإلزام المدعي بالتعويض عن الضرر المادي والمعنوي الذي لحق به جراء هذه الاتهامات الباطلة.";

$case = App\Models\LegalCase::create([
    'title' => 'دعوى جزائية - احتيال تجاري ومطالبة بالإثبات',
    'description' => $description,
    'intake_text' => $description,
    'user_id' => $user->id,
    'status' => App\Enums\CaseStatus::Phase1Pending,
    'phase' => 1,
    'model_used' => 'openrouter/free',
    'skill_version' => '1.0',
    'skill_hash' => md5('test'),
    'retry_budget_max' => 5,
    'retry_budget_used' => 0,
]);

// Attach laws via RequiredLaw records
foreach ($laws as $law) {
    App\Models\RequiredLaw::create([
        'case_id' => $case->id,
        'law_name' => $law->name,
        'reason' => 'متعلق بالقضية',
        'is_uploaded' => false,
        'law_registry_id' => $law->id,
    ]);
}

echo "\nCreated case: " . $case->id . "\n";
echo "Title: " . $case->title . "\n";
echo "Laws: " . $case->requiredLaws()->count() . "\n";

// Now dispatch Phase 1
$case->update(['status' => App\Enums\CaseStatus::Phase1Processing]);
App\Jobs\ProcessPhase1Job::dispatch($case, '');
echo "Phase 1 job dispatched!\n";
echo "\nCase URL: http://localhost:8000/cases/" . $case->id . "\n";
