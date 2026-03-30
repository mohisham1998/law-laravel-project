<div id="phase2ApprovalModal" 
     class="fixed inset-0 z-50 flex items-center justify-center p-4 " 
     style="display: none;"
     data-passing-threshold="<?php echo e(config('legal.audit_passing_threshold', 70)); ?>"
     data-soft-timeout="<?php echo e(config('legal.audit_soft_timeout_seconds', 10)); ?>"
     data-hard-timeout="<?php echo e(config('legal.audit_hard_timeout_seconds', 30)); ?>"
     data-audit-url="<?php echo e(route('cases.audit', $case)); ?>"
     data-csrf-token="<?php echo e(csrf_token()); ?>">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeApprovalModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-8 animate-fade-in max-h-[90vh] overflow-y-auto">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-4xl">gavel</span>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-900">المرحلة الأولى مكتملة</h2>
                <p class="text-slate-600">تم تحليل القضية وتحديد الأنظمة المطلوبة - يرجى المراجعة قبل المتابعة</p>
            </div>
        </div>

        <!-- Case Summary Section -->
        <div class="bg-slate-50 rounded-xl p-6 mb-6">
            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">article</span>
                ملخص القضية
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">عنوان القضية</label>
                    <p class="text-sm font-medium text-slate-900"><?php echo e($case->title ?? 'غير محدد'); ?></p>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">نص الدعوى/الطلب</label>
                    <div class="text-sm text-slate-700 bg-white p-3 rounded-lg border border-slate-200 max-h-40 overflow-y-auto">
                        <?php echo e($case->intake_text ?? 'غير محدد'); ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Score Bar Area -->
        <div id="auditScoreBar" class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 mb-6 border border-green-200">
            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-600">verified</span>
                تقييم اكتمال المدخلات
            </h3>
            
            <!-- Skeleton Loading State -->
            <div id="auditSkeleton" class="space-y-3">
                <div class="flex justify-between items-center mb-2">
                    <div class="h-4 bg-slate-200 rounded animate-pulse w-24"></div>
                    <div class="h-4 bg-slate-200 rounded animate-pulse w-16"></div>
                </div>
                <div class="h-8 bg-slate-200 rounded animate-pulse w-full"></div>
            </div>

            <!-- Soft Timeout State -->
            <div id="auditSoftTimeout" class="hidden text-center py-2">
                <p class="text-amber-700 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined animate-spin">sync</span>
                    جاري التحليل...
                </p>
            </div>

            <!-- Score Display (loaded state) -->
            <div id="auditScoreDisplay" class="hidden">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-bold text-slate-700">درجة الاكتمال</span>
                    <div class="flex items-center gap-2">
                        <span id="currentScoreLabel" class="text-lg font-black text-green-700">0</span>
                        <span class="text-slate-400">→</span>
                        <span id="projectedScoreLabel" class="text-lg font-bold text-green-600/70">0</span>
                    </div>
                </div>
                <!-- RTL-aware progress bar (fills right-to-left) -->
                <div class="relative h-8 bg-slate-200 rounded-full overflow-hidden">
                    <div id="currentScoreBar" class="absolute right-0 top-0 h-full bg-[#006b34] transition-all duration-800 ease-out" style="width: 0%"></div>
                    <div id="projectedScoreBar" class="absolute right-0 top-0 h-full bg-green-400/50 transition-all duration-800 ease-out" style="width: 0%"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span id="scorePercentage" class="text-white font-bold text-shadow">0%</span>
                    </div>
                </div>
            </div>

            <!-- Fallback State -->
            <div id="auditFallback" class="hidden text-center py-4">
                <p class="text-slate-600 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-slate-400">info</span>
                    التدقيق غير متاح — يمكنك المتابعة بشكل طبيعي
                </p>
            </div>
        </div>

        <!-- Summary Assessment Section -->
        <div id="auditSummary" class="hidden bg-blue-50 rounded-xl p-4 mb-6 border border-blue-200">
            <p id="summaryText" class="text-sm text-blue-900 leading-relaxed"></p>
        </div>

        <!-- Feedback Panel Area -->
        <div id="feedbackPanel" class="hidden mb-6">
            <!-- Required Feedback -->
            <div id="requiredFeedback" class="hidden mb-4">
                <div class="bg-red-50 rounded-xl p-4 border-l-4 border-red-600">
                    <h4 class="font-bold text-red-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-600">error</span>
                        مطلوب
                    </h4>
                    <ul id="requiredFeedbackList" class="space-y-3"></ul>
                </div>
            </div>

            <!-- Recommended Feedback -->
            <div id="recommendedFeedback" class="hidden mb-4">
                <div class="bg-amber-50 rounded-xl p-4 border-l-4 border-amber-600">
                    <h4 class="font-bold text-amber-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600">warning</span>
                        موصى به
                    </h4>
                    <ul id="recommendedFeedbackList" class="space-y-3"></ul>
                </div>
            </div>

            <!-- Optional Feedback -->
            <div id="optionalFeedback" class="hidden mb-4">
                <div class="bg-green-50 rounded-xl p-4 border-l-4 border-green-600">
                    <h4 class="font-bold text-green-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-green-600">check_circle</span>
                        اختياري
                    </h4>
                    <ul id="optionalFeedbackList" class="space-y-3"></ul>
                </div>
            </div>
        </div>

        <!-- Inline Inputs Area -->
        <div id="inlineInputsArea" class="hidden mb-6"></div>

        <!-- Required Laws Section -->
        <div class="bg-slate-50 rounded-xl p-6 mb-6">
            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">balance</span>
                الأنظمة والقوانين المطلوبة للتحليل
            </h3>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->requiredLaws && $case->requiredLaws->count()): ?>
                <ul class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $case->requiredLaws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $law): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <li class="flex items-start gap-2 text-sm">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($law->law_registry_id): ?>
                                <span class="material-symbols-outlined text-green-600 text-base mt-0.5">check_circle</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-amber-500 text-base mt-0.5">warning</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div>
                                <span class="font-medium"><?php echo e($law->law_name); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($law->subject_area): ?>
                                    <span class="text-xs text-slate-500 mr-2">(<?php echo e($law->subject_area); ?>)</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($law->reason): ?>
                                    <p class="text-xs text-slate-500 mt-0.5"><?php echo e($law->reason); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($law->law_registry_id)): ?>
                                    <p class="text-xs text-amber-600 mt-0.5">⚠️ هذا النظام غير موجود في قاعدة البيانات</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->requiredLaws->whereNull('law_registry_id')->count() > 0): ?>
                    <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                        <span class="material-symbols-outlined text-sm align-middle">warning</span>
                        بعض الأنظمة المطلوبة غير موجودة في قاعدة بيانات RAG. قد يؤثر ذلك على جودة التحليل.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <p class="text-sm text-slate-600">سيتم استخدام جميع الأنظمة المتوفرة في مكتبة الأنظمة والقوانين (RAG).</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>



        <!-- Agent Control Panel -->
        <?php
            use App\Services\AgentDefinitions;
            $allAgentDefs = collect(AgentDefinitions::all())->filter(fn($d) => ($d['number'] ?? 0) >= 1)->values();
            $caseModelOverrides = $case->agent_model_overrides ?? [];
            $globalModel = $case->model_used ?? config('openrouter.default_model', 'anthropic/claude-sonnet-4.6');
            $agentModelGroups = [
                'Anthropic' => ['anthropic/claude-sonnet-4.6' => 'Claude Sonnet 4.6', 'anthropic/claude-opus-4.6' => 'Claude Opus 4.6', 'anthropic/claude-haiku-4.5' => 'Claude Haiku 4.5'],
                'OpenAI'    => ['openai/gpt-4o' => 'GPT-4o', 'openai/gpt-4o-mini' => 'GPT-4o Mini', 'openai/o3-mini' => 'o3 Mini'],
                'Google'    => ['google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'google/gemini-2.5-pro' => 'Gemini 2.5 Pro'],
                'Meta'      => ['meta-llama/llama-4-scout' => 'Llama 4 Scout', 'meta-llama/llama-4-maverick' => 'Llama 4 Maverick'],
            ];
        ?>
        <div class="mb-6">
            <button type="button" onclick="toggleAgentPanel()" class="w-full flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">smart_toy</span>
                    <div class="text-right">
                        <span class="font-bold text-slate-900 text-sm">تخصيص الوكلاء</span>
                        <p class="text-xs text-slate-500">تعديل رسائل النظام ونماذج الذكاء الاصطناعي لكل وكيل</p>
                    </div>
                </div>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200" id="agentPanelChevron">expand_more</span>
            </button>

            <div id="agentControlPanel" class="hidden mt-3 border border-slate-200 rounded-xl overflow-hidden">
                <!-- Panel Header -->
                <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 uppercase"><?php echo e($allAgentDefs->count()); ?> وكيل</span>
                    <span class="text-xs text-slate-400">النموذج الافتراضي: <?php echo e($globalModel); ?></span>
                </div>

                <!-- Agent Rows -->
                <div class="divide-y divide-slate-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allAgentDefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $def): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php
                    $agentNum = $def['number'];
                    $agentPhase = $def['phase'] ?? 2;
                    $currentModel = $caseModelOverrides[$agentNum] ?? null;
                ?>
                <div class="p-4 hover:bg-slate-50/50 transition-colors" id="agentRow<?php echo e($agentNum); ?>">
                    <!-- Agent Header Row -->
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 <?php echo e($agentPhase >= 3 ? 'bg-indigo-100 text-indigo-700' : 'bg-primary/10 text-primary'); ?> text-xs font-black">
                            <?php echo e($agentNum); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-900"><?php echo e($def['name']); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($agentPhase >= 3): ?>
                                    <span class="text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-medium">مرحلة ٣</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <span class="text-xs text-slate-500"><?php echo e($def['name_en'] ?? ''); ?></span>
                        </div>
                        <!-- Model Badge -->
                        <div class="flex-shrink-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentModel): ?>
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">مخصص</span>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">افتراضي</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <!-- Expand Button -->
                        <button type="button" onclick="toggleAgentRow(<?php echo e($agentNum); ?>)"
                                class="flex-shrink-0 p-1 rounded hover:bg-slate-200 transition-colors">
                            <span class="material-symbols-outlined text-slate-400 text-base transition-transform duration-200" id="agentRowChevron<?php echo e($agentNum); ?>">tune</span>
                        </button>
                    </div>

                    <!-- Expandable Controls -->
                    <div id="agentRowControls<?php echo e($agentNum); ?>" class="hidden space-y-3 pl-11">
                        <!-- Model Selector -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">النموذج</label>
                            <div class="flex gap-2">
                                <select id="agentModelSelect<?php echo e($agentNum); ?>"
                                        class="flex-1 text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                                    <option value="">— افتراضي (<?php echo e($globalModel); ?>) —</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $agentModelGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $models): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <optgroup label="<?php echo e($groupName); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $models; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $modelId => $modelLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($modelId); ?>" <?php echo e($currentModel === $modelId ? 'selected' : ''); ?>><?php echo e($modelLabel); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </optgroup>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                                <button type="button" onclick="saveModalAgentModel(<?php echo e($agentNum); ?>)"
                                        class="text-xs px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium whitespace-nowrap">
                                    حفظ
                                </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentModel): ?>
                                <button type="button" onclick="resetModalAgentModel(<?php echo e($agentNum); ?>)"
                                        class="text-xs px-3 py-2 bg-slate-200 text-slate-600 rounded-lg hover:bg-slate-300 transition-colors">
                                    إعادة تعيين
                                </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <p id="agentModelStatus<?php echo e($agentNum); ?>" class="text-xs text-slate-400 mt-1 hidden"></p>
                        </div>

                        <!-- System Message Editor -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-600">رسالة النظام</label>
                                <button type="button" onclick="loadAgentSystemMsg(<?php echo e($agentNum); ?>)"
                                        class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">refresh</span> تحميل
                                </button>
                            </div>
                            <textarea id="agentSysMsgText<?php echo e($agentNum); ?>"
                                      rows="4"
                                      placeholder="انقر تحميل لجلب رسالة النظام الحالية..."
                                      class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none resize-y font-mono text-right direction-rtl"></textarea>
                            <div class="flex items-center justify-between mt-1.5">
                                <span id="agentSysMsgStatus<?php echo e($agentNum); ?>" class="text-xs text-slate-400"></span>
                                <button type="button" onclick="saveAgentSystemMsg(<?php echo e($agentNum); ?>)"
                                        class="text-xs px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium">
                                    حفظ الرسالة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Warning Note -->
        <div class="bg-amber-50 rounded-xl p-4 mb-6 border border-amber-200">
            <p class="text-sm text-amber-900">
                <span class="material-symbols-outlined text-amber-600 text-base align-middle">info</span>
                <strong>ملاحظة:</strong> ستبدأ المرحلة الثانية التي تتضمن 9 وكلاء ذكاء اصطناعي. قد تستغرق العملية عدة دقائق. يمكن إيقاف المعالجة في أي وقت.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
            <form id="startPhase2Form" method="POST" action="<?php echo e(route('cases.start-phase2', $case)); ?>" class="flex-1">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="puter_token" id="startPhase2PuterToken" value="">
                <button id="proceedButton" type="submit" class="w-full px-6 py-4 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 shadow-lg hover:shadow-xl" disabled>
                    <span class="material-symbols-outlined">play_arrow</span>
                    <span id="proceedButtonText">جاري التحميل...</span>
                </button>
            </form>
            
            <!-- Request Changes Button -->
            <button onclick="showRequestChangesForm()" class="px-6 py-4 bg-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-300 transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">edit_note</span>
                طلب تعديلات
            </button>
            
            <a href="<?php echo e(route('cases.index')); ?>" class="px-6 py-4 bg-slate-100 text-slate-500 font-bold rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center">
                إلغاء
            </a>
        </div>

        <!-- Request Changes Form (Hidden by default) -->
        <div id="requestChangesForm" class="hidden mt-6 pt-6 border-t border-slate-200">
            <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">edit_note</span>
                طلب تعديلات على القضية
            </h4>
            <form method="POST" action="<?php echo e(route('cases.request-changes', $case)); ?>">
                <?php echo csrf_field(); ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">ما الذي تريد تعديله؟</label>
                        <select name="change_type" class="w-full p-3 border border-slate-300 rounded-lg" required>
                            <option value="">اختر نوع التعديل...</option>
                            <option value="title">تعديل عنوان القضية</option>
                            <option value="intake_text">تعديل نص الدعوى</option>
                            <option value="add_evidence">إضافة أدلة/مستندات</option>
                            <option value="remove_evidence">إزالة أدلة/مستندات</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">التفاصيل</label>
                        <textarea name="change_details" rows="4" class="w-full p-3 border border-slate-300 rounded-lg text-right" required placeholder="اشرح بالتفصيل ما تريد تعديله..."></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="px-6 py-3 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600">
                            إرسال طلب التعديل
                        </button>
                        <button type="button" onclick="hideRequestChangesForm()" class="px-6 py-3 bg-slate-200 text-slate-700 font-bold rounded-xl">
                            إلغاء
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal State Management
const modal = document.getElementById('phase2ApprovalModal');
let modalClosed = false; // Track if modal was explicitly closed
let modalCaseStatus = '<?php echo e($case->status->value ?? $case->status); ?>'; // Current status, updated via SSE (prefixed to avoid conflict with timeline's currentCaseStatus)
// Track the statuses that have already passed the awaiting_laws gate — modal should never reopen for these
const modalPostApprovalStatuses = ['phase2_pending', 'phase2_processing', 'phase2_completed', 'phase3_pending', 'phase3_processing', 'phase3_completed', 'completed', 'completed_with_warnings', 'failed', 'paused', 'cancelled', 'halted', 'timed_out'];
// If page was loaded with a post-approval status, permanently suppress the modal
let modalSuppressed = modalPostApprovalStatuses.includes(modalCaseStatus);
const state = {
    phase: 'loading', // loading, soft-timeout, loaded, error, fallback
    score: null,
    projectedScore: null,
    summary: null,
    feedback: null,
    inlineInputs: {
        text: {},
        files: [],
        selections: {}
    },
    passingThreshold: parseInt(modal.dataset.passingThreshold) || 70,
    softTimeout: parseInt(modal.dataset.softTimeout) || 10,
    hardTimeout: parseInt(modal.dataset.hardTimeout) || 30,
    abortController: null,
    softTimeoutTimer: null,
    hardTimeoutTimer: null,
    debounceTimer: null,
    isProceeding: false
};

const auditUrl = modal.dataset.auditUrl;
const csrfToken = modal.dataset.csrfToken;

// Initialize audit on modal display
document.addEventListener('DOMContentLoaded', function() {
    // Show modal immediately if status is already awaiting_laws (page loaded with this status)
    // Don't show if case is already in a different status (completed, processing, etc.)
    if (modalCaseStatus === 'awaiting_laws' && !modalSuppressed) {
        const modal = document.getElementById('phase2ApprovalModal');
        if (modal) {
            modal.style.display = 'flex';
            startAudit();
        }
    }

    // Listen for SSE events to show modal in real-time when status changes to awaiting_laws
    window.addEventListener('sse:case.status_changed', function(event) {
        const newStatus = event.detail?.status;

        // If we receive a post-approval status, permanently suppress the modal
        if (modalPostApprovalStatuses.includes(newStatus)) {
            modalSuppressed = true;
            modalCaseStatus = newStatus;
            return;
        }

        // Update current status
        modalCaseStatus = newStatus;

        // Only show modal if:
        // 1. Status changed to awaiting_laws
        // 2. Modal wasn't explicitly closed
        // 3. Modal hasn't been suppressed (page was loaded in a post-approval state)
        if (newStatus === 'awaiting_laws' && !modalClosed && !modalSuppressed) {
            const modal = document.getElementById('phase2ApprovalModal');
            if (modal) {
                modal.style.display = 'flex';
                startAudit();
            }
        }
    });
});

function startAudit() {
    if (state.isProceeding) {
        return;
    }

    // Create AbortController for cancellation
    state.abortController = new AbortController();
    
    // Set up timeout timers
    state.softTimeoutTimer = setTimeout(() => {
        if (state.phase === 'loading') {
            state.phase = 'soft-timeout';
            renderState();
        }
    }, state.softTimeout * 1000);
    
    state.hardTimeoutTimer = setTimeout(() => {
        if (state.phase === 'loading' || state.phase === 'soft-timeout') {
            state.phase = 'fallback';
            renderState();
        }
    }, state.hardTimeout * 1000);

    // Fire audit request
    fetch(auditUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({}),
        signal: state.abortController.signal
    })
    .then(response => {
        if (!response.ok) {
            const error = new Error('Audit request failed');
            error.status = response.status;
            throw error;
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            state.phase = 'loaded';
            state.score = data.data.score;
            state.projectedScore = data.data.projected_score;
            state.summary = data.data.summary;
            state.feedback = data.data.feedback;
        } else {
            state.phase = 'fallback';
        }
        renderState();
    })
    .catch(error => {
        if (error.name !== 'AbortError' && !state.isProceeding && error.status !== 422) {
            console.error('Audit error:', error);
            state.phase = 'fallback';
            renderState();
        }
    });
}

function renderState() {
    // Clear timeout timers
    clearTimeout(state.softTimeoutTimer);
    clearTimeout(state.hardTimeoutTimer);
    
    const skeleton = document.getElementById('auditSkeleton');
    const softTimeout = document.getElementById('auditSoftTimeout');
    const scoreDisplay = document.getElementById('auditScoreDisplay');
    const fallback = document.getElementById('auditFallback');
    const summary = document.getElementById('auditSummary');
    const feedbackPanel = document.getElementById('feedbackPanel');
    const proceedButton = document.getElementById('proceedButton');
    const proceedButtonText = document.getElementById('proceedButtonText');
    
    // Hide all states first
    skeleton.classList.add('hidden');
    softTimeout.classList.add('hidden');
    scoreDisplay.classList.add('hidden');
    fallback.classList.add('hidden');
    
    switch (state.phase) {
        case 'loading':
            skeleton.classList.remove('hidden');
            proceedButton.disabled = true;
            proceedButtonText.textContent = 'جاري التحميل...';
            break;
            
        case 'soft-timeout':
            softTimeout.classList.remove('hidden');
            proceedButton.disabled = false;
            proceedButtonText.textContent = 'المتابعة على أي حال';
            proceedButton.className = 'w-full px-6 py-4 bg-slate-400 text-white font-bold rounded-xl flex items-center justify-center gap-2';
            break;
            
        case 'loaded':
            scoreDisplay.classList.remove('hidden');
            
            // Update score bars with animation
            document.getElementById('currentScoreBar').style.width = state.score + '%';
            document.getElementById('projectedScoreBar').style.width = state.projectedScore + '%';
            document.getElementById('currentScoreLabel').textContent = state.score;
            document.getElementById('projectedScoreLabel').textContent = state.projectedScore;
            document.getElementById('scorePercentage').textContent = state.score + '%';
            
            // Update summary
            if (state.summary) {
                summary.classList.remove('hidden');
                document.getElementById('summaryText').textContent = state.summary;
            }
            
            // Update feedback
            if (state.feedback) {
                feedbackPanel.classList.remove('hidden');
                renderFeedback(state.feedback);
            }
            
            updateCTA();
            break;
            
        case 'fallback':
            fallback.classList.remove('hidden');
            proceedButton.disabled = false;
            proceedButtonText.textContent = 'موافقة والمتابعة (9 وكلاء)';
            proceedButton.className = 'w-full px-6 py-4 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 shadow-lg hover:shadow-xl';
            break;
    }
}

function renderFeedback(feedback) {
    // Render required feedback
    const requiredSection = document.getElementById('requiredFeedback');
    const requiredList = document.getElementById('requiredFeedbackList');
    if (feedback.required && feedback.required.length > 0) {
        requiredSection.classList.remove('hidden');
        requiredList.innerHTML = feedback.required.map(item => createFeedbackItemHTML(item)).join('');
    } else {
        requiredSection.classList.add('hidden');
    }
    
    // Render recommended feedback
    const recommendedSection = document.getElementById('recommendedFeedback');
    const recommendedList = document.getElementById('recommendedFeedbackList');
    if (feedback.recommended && feedback.recommended.length > 0) {
        recommendedSection.classList.remove('hidden');
        recommendedList.innerHTML = feedback.recommended.map(item => createFeedbackItemHTML(item)).join('');
    } else {
        recommendedSection.classList.add('hidden');
    }
    
    // Render optional feedback
    const optionalSection = document.getElementById('optionalFeedback');
    const optionalList = document.getElementById('optionalFeedbackList');
    if (feedback.optional && feedback.optional.length > 0) {
        optionalSection.classList.remove('hidden');
        optionalList.innerHTML = feedback.optional.map(item => createFeedbackItemHTML(item)).join('');
    } else {
        optionalSection.classList.add('hidden');
    }
}

function createFeedbackItemHTML(item) {
    let inputHTML = '';
    
    if (item.input_type === 'text') {
        inputHTML = `
            <div class="mt-2">
                <input type="text" 
                       data-label="${item.label}"
                       class="inline-input w-full p-2 border border-slate-300 rounded-lg text-sm bg-background-light focus:ring-2 focus:ring-primary"
                       placeholder="أدخل القيمة..."
                       oninput="handleInlineInput('text', '${item.label}', this.value)">
            </div>
        `;
    } else if (item.input_type === 'file') {
        inputHTML = `
            <div class="mt-2">
                <input type="file" 
                       data-label="${item.label}"
                       class="inline-input text-sm"
                       onchange="handleInlineFile('${item.label}', this)">
            </div>
        `;
    } else if (item.input_type === 'selection' && item.options) {
        const optionsHTML = item.options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
        inputHTML = `
            <div class="mt-2">
                <select data-label="${item.label}"
                        class="inline-input w-full p-2 border border-slate-300 rounded-lg text-sm appearance-none bg-background-light"
                        onchange="handleInlineSelection('${item.label}', this.value)">
                    <option value="">اختر...</option>
                    ${optionsHTML}
                </select>
            </div>
        `;
    }
    
    return `
        <li class="text-sm">
            <div class="font-bold">${item.label}</div>
            <div class="text-slate-600">${item.reason}</div>
            ${inputHTML}
        </li>
    `;
}

function handleInlineInput(label, value) {
    state.inlineInputs.text[label] = value;
    debounceAudit();
}

function handleInlineFile(label, input) {
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    
    fetch('<?php echo e(route("cases.audit-upload", $case)); ?>', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            state.inlineInputs.files.push(data.document.id);
            // Show filename badge
            const badge = document.createElement('span');
            badge.className = 'inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded mt-1';
            badge.textContent = data.document.filename;
            input.parentNode.appendChild(badge);
            debounceAudit();
        } else {
            alert('فشل في رفع الملف');
        }
    })
    .catch(error => {
        console.error('File upload error:', error);
        alert('فشل في رفع الملف');
    });
}

function handleInlineSelection(label, value) {
    state.inlineInputs.selections[label] = value;
    debounceAudit();
}

function debounceAudit() {
    if (state.debounceTimer) {
        clearTimeout(state.debounceTimer);
    }
    
    state.debounceTimer = setTimeout(() => {
        reAudit();
    }, 800);
}

function reAudit() {
    if (state.isProceeding) {
        return;
    }

    // Abort any in-flight request
    if (state.abortController) {
        state.abortController.abort();
    }
    
    state.abortController = new AbortController();
    state.phase = 'loading';
    renderState();
    
    fetch(auditUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            inline_inputs: state.inlineInputs
        }),
        signal: state.abortController.signal
    })
    .then(response => {
        if (!response.ok) {
            const error = new Error('Audit request failed');
            error.status = response.status;
            throw error;
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            state.phase = 'loaded';
            state.score = data.data.score;
            state.projectedScore = data.data.projected_score;
            state.summary = data.data.summary;
            state.feedback = data.data.feedback;
        } else {
            state.phase = 'fallback';
        }
        renderState();
    })
    .catch(error => {
        if (error.name !== 'AbortError' && !state.isProceeding && error.status !== 422) {
            console.error('Re-audit error:', error);
            state.phase = 'fallback';
            renderState();
        }
    });
}

function updateCTA() {
    const proceedButton = document.getElementById('proceedButton');
    const proceedButtonText = document.getElementById('proceedButtonText');
    
    if (state.phase === 'loading' || state.phase === 'soft-timeout') {
        proceedButton.disabled = state.phase === 'loading';
        return;
    }
    
    if (state.phase === 'fallback') {
        proceedButton.disabled = false;
        proceedButtonText.textContent = 'موافقة والمتابعة (9 وكلاء)';
        proceedButton.className = 'w-full px-6 py-4 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 shadow-lg hover:shadow-xl';
        return;
    }
    
    if (state.score >= state.passingThreshold) {
        proceedButton.disabled = false;
        proceedButtonText.textContent = 'موافقة والمتابعة (9 وكلاء)';
        proceedButton.className = 'w-full px-6 py-4 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 shadow-lg hover:shadow-xl';
    } else {
        proceedButton.disabled = false;
        proceedButtonText.textContent = 'المتابعة على أي حال';
        proceedButton.className = 'w-full px-6 py-4 bg-white border-2 border-amber-500 text-amber-700 font-bold rounded-xl hover:bg-amber-50 transition-colors flex items-center justify-center gap-2';
        
        // Add warning text below button
        let warningEl = document.getElementById('scoreWarning');
        if (!warningEl) {
            warningEl = document.createElement('p');
            warningEl.id = 'scoreWarning';
            warningEl.className = 'text-xs text-amber-600 mt-2 text-center';
            warningEl.textContent = 'قد تتأثر جودة المخرجات';
            proceedButton.parentNode.appendChild(warningEl);
        }
    }
}

// Handle form submission with inline inputs persistence
document.getElementById('startPhase2Form').addEventListener('submit', function(e) {
    state.isProceeding = true;

    // Inject Puter token if available
    try {
        if (typeof puter !== 'undefined' && puter.authToken) {
            document.getElementById('startPhase2PuterToken').value = puter.authToken;
        }
    } catch(err) { /* ignore */ }

    // Cancel background audit requests before moving to phase 2.
    if (state.abortController) {
        state.abortController.abort();
    }

    // If there are inline text inputs, persist them first
    const hasInlineText = Object.keys(state.inlineInputs.text).length > 0;
    const hasInlineSelections = Object.keys(state.inlineInputs.selections).length > 0;
    
    if (hasInlineText || hasInlineSelections) {
        e.preventDefault();
        
        // Build additional info text
        let additionalInfo = '';
        if (hasInlineText) {
            additionalInfo += '\n--- معلومات إضافية من التدقيق ---\n';
            for (const [label, value] of Object.entries(state.inlineInputs.text)) {
                additionalInfo += `${label}: ${value}\n`;
            }
        }
        if (hasInlineSelections) {
            additionalInfo += '\n--- خيارات محددة ---\n';
            for (const [label, value] of Object.entries(state.inlineInputs.selections)) {
                additionalInfo += `${label}: ${value}\n`;
            }
        }
        
        // Submit to update-missing-info first
        fetch('<?php echo e(route("cases.update-missing-info", $case)); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ additional_info: additionalInfo })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Then submit the form
                this.submit();
            }
        })
        .catch(error => {
            console.error('Error saving inline inputs:', error);
            // Still submit the form
            this.submit();
        });
    }
});

function closeApprovalModal() {
    // Mark modal as explicitly closed to prevent it from reopening via SSE
    modalClosed = true;
    
    // Abort any in-flight requests
    if (state.abortController) {
        state.abortController.abort();
    }
    
    // Clear all timers
    clearTimeout(state.softTimeoutTimer);
    clearTimeout(state.hardTimeoutTimer);
    clearTimeout(state.debounceTimer);
    
    const modal = document.getElementById('phase2ApprovalModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showRequestChangesForm() {
    document.getElementById('requestChangesForm').classList.remove('hidden');
}

function hideRequestChangesForm() {
    document.getElementById('requestChangesForm').classList.add('hidden');
}

// ============================================
// AGENT CONTROL PANEL
// ============================================
const MODAL_CASE_ID = '<?php echo e($case->id); ?>';
const MODAL_CSRF = '<?php echo e(csrf_token()); ?>';

function toggleAgentPanel() {
    const panel = document.getElementById('agentControlPanel');
    const chevron = document.getElementById('agentPanelChevron');
    panel.classList.toggle('hidden');
    chevron.style.transform = panel.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function toggleAgentRow(num) {
    const controls = document.getElementById(`agentRowControls${num}`);
    controls.classList.toggle('hidden');
}

async function loadAgentSystemMsg(num) {
    const ta = document.getElementById(`agentSysMsgText${num}`);
    const status = document.getElementById(`agentSysMsgStatus${num}`);
    ta.value = 'جارٍ التحميل...';
    ta.disabled = true;
    try {
        const res = await fetch(`/api/agents/${num}/system-message`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        ta.value = data.system_message ?? data.data?.system_message ?? '';
        status.textContent = `${ta.value.length} حرف`;
        status.classList.remove('hidden');
    } catch (e) {
        ta.value = '';
        status.textContent = 'تعذر التحميل';
    } finally {
        ta.disabled = false;
    }
}

async function saveAgentSystemMsg(num) {
    const ta = document.getElementById(`agentSysMsgText${num}`);
    const status = document.getElementById(`agentSysMsgStatus${num}`);
    const text = ta.value.trim();
    if (!text) { status.textContent = 'الرسالة فارغة'; return; }
    status.textContent = 'جارٍ الحفظ...';
    try {
        const res = await fetch(`/api/agents/${num}/system-message`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': MODAL_CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ system_message: text }),
        });
        status.textContent = res.ok ? '✓ تم الحفظ' : 'فشل الحفظ';
    } catch (e) {
        status.textContent = 'خطأ في الاتصال';
    }
}

async function saveModalAgentModel(num) {
    const sel = document.getElementById(`agentModelSelect${num}`);
    const statusEl = document.getElementById(`agentModelStatus${num}`);
    const model = sel.value;
    statusEl.textContent = 'جارٍ الحفظ...';
    statusEl.classList.remove('hidden');
    try {
        const body = new URLSearchParams();
        body.append('_method', 'PATCH');
        body.append('_token', MODAL_CSRF);
        body.append(`agent_overrides[${num}]`, model); // empty string removes override
        const res = await fetch(`/cases/${MODAL_CASE_ID}/model-config`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': MODAL_CSRF },
            body,
        });
        statusEl.textContent = res.ok ? (model ? `✓ محفوظ` : '✓ أعيد للافتراضي') : 'فشل الحفظ';
    } catch (e) {
        statusEl.textContent = 'خطأ';
    }
}

async function resetModalAgentModel(num) {
    document.getElementById(`agentModelSelect${num}`).value = '';
    await saveModalAgentModel(num);
}
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}
.text-shadow {
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
</style>
<?php /**PATH /var/www/html/resources/views/components/phase2-approval-modal.blade.php ENDPATH**/ ?>