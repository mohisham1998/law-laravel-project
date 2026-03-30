
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['failed', 'paused', 'halted', 'timed_out'], true)): ?>
<?php
    $haltedAtAgent = $case->halted_at_agent ?? $case->current_agent ?? null;
    $canResume = $haltedAtAgent && $haltedAtAgent > 1;
    $statusLabel = match($statusVal) {
        'paused'    => 'القضية متوقفة',
        'halted'    => 'توقف خط أنابيب المعالجة',
        'timed_out' => 'انتهت مهلة المعالجة',
        default     => 'فشل تحليل القضية',
    };
?>
    <div class="mt-4 p-5 rounded-xl bg-red-50 border-2 border-red-300 shadow-md">
        <div class="flex items-center gap-3 mb-3">
            <span class="material-symbols-outlined text-red-600 text-3xl">error</span>
            <div>
                <p class="font-bold text-red-800 text-lg"><?php echo e($statusLabel); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($haltedAtAgent): ?>
                    <p class="text-xs text-red-600">توقف عند الوكيل رقم <?php echo e($haltedAtAgent); ?></p>
                <?php elseif($case->last_failed_phase): ?>
                    <p class="text-xs text-red-600">المرحلة: <?php echo e($case->last_failed_phase); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->last_error_message): ?>
            <div class="mb-4">
                <p class="text-xs text-red-600 font-semibold mb-2">تفاصيل الخطأ:</p>
                <p class="text-sm text-red-700 bg-red-100 p-3 rounded-lg font-mono break-all"><?php echo e(\Str::limit($case->last_error_message, 200)); ?></p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_contains($case->last_error_message, 'Insufficient credits') || str_contains($case->last_error_message, 'credits')): ?>
                <div class="mb-4 p-3 bg-amber-50 border border-amber-300 rounded-lg">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-amber-600 text-sm">info</span>
                        <p class="text-sm font-bold text-amber-800">الحل المطلوب:</p>
                    </div>
                    <p class="text-sm text-amber-700">
                        1. أضف رصيد في <a href="https://openrouter.ai/settings/credits" target="_blank" class="underline font-bold hover:text-amber-900">OpenRouter</a><br>
                        2. انقر على زر "استئناف" أدناه
                    </p>
                </div>
            <?php elseif(str_contains($case->last_error_message, 'has been attempted too many times') || str_contains($case->last_error_message, 'MaxAttempts')): ?>
                <div class="mb-4 p-3 bg-amber-50 border border-amber-300 rounded-lg">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-amber-600 text-sm">info</span>
                        <p class="text-sm font-bold text-amber-800">سبب الفشل:</p>
                    </div>
                    <p class="text-sm text-amber-700">واجه النظام صعوبة في معالجة القضية بعد عدة محاولات.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-red-600 mb-4">حدث خطأ أثناء المعالجة.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="flex flex-wrap gap-3">
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canResume): ?>
            <form action="<?php echo e(route('cases.resume', $case)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <button type="submit"
                        class="flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 active:scale-95 transition-all shadow-lg hover:shadow-xl">
                    <span class="material-symbols-outlined">play_arrow</span>
                    <span>استئناف من الوكيل <?php echo e($haltedAtAgent); ?></span>
                </button>
            </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <form action="<?php echo e(route('cases.retry-agent', $case)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <button type="submit"
                        class="flex items-center gap-2 px-6 py-3 <?php echo e($canResume ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : 'bg-red-600 text-white hover:bg-red-700'); ?> font-bold rounded-xl active:scale-95 transition-all shadow-md">
                    <span class="material-symbols-outlined">refresh</span>
                    <span>إعادة المحاولة من البداية</span>
                </button>
            </form>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canResume): ?>
        <p class="text-xs text-slate-500 mt-3">
            <span class="material-symbols-outlined text-xs align-middle">info</span>
            الاستئناف يحافظ على مخرجات الوكلاء <?php echo e($haltedAtAgent - 1); ?> السابقين ويعيد تشغيل الوكيل <?php echo e($haltedAtAgent); ?> وما بعده فقط.
        </p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/html/resources/views/pages/cases/show-retry-section.blade.php ENDPATH**/ ?>