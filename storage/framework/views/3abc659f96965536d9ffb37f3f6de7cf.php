<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['case']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['case']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $m = $case->metrics;
    $executions = $case->agentExecutions;

    // Duration: prefer metrics, fallback to sum of execution durations
    $duration = $m ? (int) $m->total_duration_seconds : 0;
    if ($duration === 0 && $executions->count() > 0) {
        $duration = (int) round($executions->sum('duration_ms') / 1000);
    }

    // Tokens: prefer metrics, fallback to sum of execution tokens
    $tokens = $m ? (int) $m->total_tokens : 0;
    if ($tokens === 0 && $executions->count() > 0) {
        $tokens = (int) $executions->sum('total_tokens');
    }

    // Statutes matched: count from statute matcher output (agent 6)
    $statutesFromMetrics = $m ? (int) $m->statutes_matched : 0;
    if ($statutesFromMetrics === 0) {
        $statuteOutput = $case->outputs->where('agent_number', 6)->where('filename', '06_accepted_matches.md')->first();
        $statutesFromMetrics = $statuteOutput && !empty($statuteOutput->content)
            ? max(1, substr_count($statuteOutput->content, '##') + substr_count($statuteOutput->content, 'المادة'))
            : 0;
    }

    // Corrections: prefer metrics, fallback to sum from executions
    $corrections = $m ? (int) $m->corrections_count : 0;
    if ($corrections === 0 && $executions->count() > 0) {
        $corrections = (int) $executions->sum('corrections_count');
    }

    // Confidence: compute from completed execution ratio + output quality
    $confidence = $m ? (float) $m->average_confidence : 0;
    if ($confidence == 0 && $executions->count() > 0) {
        $completedCount = $executions->filter(fn($e) => ($e->status instanceof \BackedEnum ? $e->status->value : $e->status) === 'completed')->count();
        $totalExpected = $executions->count();
        $failedCount = $executions->filter(fn($e) => ($e->status instanceof \BackedEnum ? $e->status->value : $e->status) === 'failed')->count();
        // Base confidence on completion ratio and correction ratio
        $completionRatio = $totalExpected > 0 ? $completedCount / $totalExpected : 0;
        $correctionPenalty = $totalExpected > 0 ? min(0.2, ($corrections / $totalExpected) * 0.1) : 0;
        $confidence = max(0, min(1, $completionRatio - $correctionPenalty));
    }

    $itemsForReview = $m && is_array($m->items_for_review) ? $m->items_for_review : [];

    // Completed agents count
    $completedAgents = $executions->filter(fn($e) => ($e->status instanceof \BackedEnum ? $e->status->value : $e->status) === 'completed')->count();
    $totalAgents = 13;

    // Total output files generated
    $outputFiles = $case->outputs->count();
?>
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
    <h3 class="font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">insights</span>
        رؤى القضية
    </h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">وقت المعالجة</p>
            <p class="font-bold text-primary text-lg"><?php echo e($duration > 60 ? round($duration / 60, 1) . ' د' : $duration . ' ث'); ?></p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">الوكلاء المكتملون</p>
            <p class="font-bold text-primary text-lg"><?php echo e($completedAgents); ?> / <?php echo e($totalAgents); ?></p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">المواد المطابقة</p>
            <p class="font-bold text-primary text-lg"><?php echo e($statutesFromMetrics); ?></p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">التصحيحات</p>
            <p class="font-bold text-primary text-lg"><?php echo e($corrections); ?></p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">الملفات المنتجة</p>
            <p class="font-bold text-primary text-lg"><?php echo e($outputFiles); ?></p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">الثقة</p>
            <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all" style="width: <?php echo e(min(100, (int) ($confidence * 100))); ?>%"></div>
                </div>
                <span class="font-bold text-sm text-primary"><?php echo e(number_format($confidence * 100, 0)); ?>%</span>
            </div>
        </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tokens > 0): ?>
        <p class="text-xs text-slate-400 mt-3">إجمالي الرموز: <?php echo e(number_format($tokens)); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($itemsForReview) > 0): ?>
        <div class="mt-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">عناصر للمراجعة</p>
            <ul class="list-disc list-inside text-sm text-slate-600 space-y-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_slice($itemsForReview, 0, 10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <li><?php echo e(is_array($item) ? ($item['label'] ?? json_encode($item)) : $item); ?></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/components/case-insights.blade.php ENDPATH**/ ?>