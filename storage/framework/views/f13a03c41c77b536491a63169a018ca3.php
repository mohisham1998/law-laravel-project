<?php $__env->startSection('title', 'تحليل الذكاء الاصطناعي - المستشار القانوني الذكي'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="aiAnalysis()" x-init="init()" class="space-y-6">

    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">سير عملية التحليل بالذكاء الاصطناعي</h1>
            <p class="text-slate-500 text-sm mt-1" x-text="selectedCase ? selectedCase.title : 'اختر قضية لمتابعة مراحل التحليل القانوني التلقائي'"></p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            
            <div class="relative min-w-[220px]">
                <select
                    class="w-full pr-9 pl-3 py-2.5 bg-white border border-slate-200 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary appearance-none cursor-pointer"
                    @change="selectCase($event.target.value)"
                >
                    <option value="">-- اختر قضية --</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allCases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <option value="<?php echo e($c->id); ?>"
                            <?php echo e(($case && $case['id'] === $c->id) ? 'selected' : ''); ?>>
                            <?php echo e(Str::limit($c->title, 35)); ?>

                        </option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-base">expand_more</span>
            </div>

            
            <template x-if="selectedCase">
                <div class="flex gap-2">
                    <template x-if="isProcessing">
                        <button @click="togglePause()" class="bg-amber-500 text-white px-4 py-2.5 rounded-xl font-bold flex items-center gap-2 hover:brightness-110 transition-all text-sm">
                            <span class="material-symbols-outlined text-base">pause</span>
                            إيقاف مؤقت
                        </button>
                    </template>
                    <template x-if="isPaused">
                        <button @click="togglePause()" class="bg-emerald-500 text-white px-4 py-2.5 rounded-xl font-bold flex items-center gap-2 hover:brightness-110 transition-all text-sm">
                            <span class="material-symbols-outlined text-base">play_arrow</span>
                            استئناف
                        </button>
                    </template>
                    <a :href="caseUrl" class="bg-white border border-slate-200 px-4 py-2.5 rounded-xl font-bold flex items-center gap-1.5 text-slate-700 hover:bg-slate-50 text-sm transition-colors">
                        <span class="material-symbols-outlined text-base">open_in_new</span>
                        فتح القضية
                    </a>
                </div>
            </template>

            
            <button @click="refresh()" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-colors" title="تحديث">
                <span class="material-symbols-outlined" :class="loading ? 'animate-spin' : ''">refresh</span>
            </button>
        </div>
    </div>

    
    <template x-if="!selectedCase">
        <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-4 block">psychology</span>
            <h3 class="text-lg font-bold text-slate-700 mb-2">اختر قضية لمتابعة التحليل</h3>
            <p class="text-slate-500 text-sm mb-6">اختر قضية من القائمة أعلاه لمتابعة مراحل التحليل الذكي في الوقت الفعلي</p>
            <a href="<?php echo e(route('cases.create')); ?>" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:brightness-110 transition-all">
                <span class="material-symbols-outlined text-base">add</span>
                إنشاء قضية جديدة
            </a>
        </div>
    </template>

    
    <template x-if="selectedCase">
        <div class="space-y-6">

            
            <div class="bg-white p-5 rounded-2xl border border-primary/10 shadow-sm">
                <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <span class="material-symbols-outlined text-primary text-xl">auto_awesome</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">إجمالي التقدم</h3>
                            <p class="text-xs text-slate-500" x-text="statusLabel"></p>
                        </div>
                    </div>
                    <span class="text-2xl font-black" :class="progressColor" x-text="(selectedCase.progress_percentage ?? 0) + '%'"></span>
                </div>
                <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all duration-700 relative"
                         :style="'width: ' + (selectedCase.progress_percentage ?? 0) + '%'"
                         :class="isProcessing ? 'after:absolute after:inset-0 after:bg-white/20 after:animate-pulse' : ''">
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-3 text-xs text-slate-500">
                    <span x-show="selectedCase.phase">المرحلة: <strong x-text="selectedCase.phase"></strong></span>
                    <span x-show="selectedCase.current_agent !== null && selectedCase.current_agent !== undefined">الوكيل الحالي: <strong x-text="'#' + selectedCase.current_agent"></strong></span>
                    <span x-show="isProcessing" class="flex items-center gap-1 text-primary">
                        <span class="w-2 h-2 bg-primary rounded-full animate-pulse inline-block"></span>
                        جارٍ المعالجة...
                    </span>
                    <span x-show="isCompleted" class="flex items-center gap-1 text-emerald-600">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        مكتملة
                    </span>
                </div>
            </div>

            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $agentDefinitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php
                    $phaseColors = [1 => 'blue', 2 => 'primary', 3 => 'purple'];
                    $phaseColor = $phaseColors[$agent['phase']] ?? 'primary';
                    $phaseLabel = ['1' => 'المرحلة الأولى', '2' => 'المرحلة الثانية', '3' => 'المرحلة الثالثة'][$agent['phase']] ?? '';
                    $icons = [
                        0 => 'search', 1 => 'gavel', 2 => 'inventory',
                        3 => 'link', 4 => 'timeline', 5 => 'balance',
                        6 => 'library_books', 7 => 'shield', 8 => 'edit_document',
                        9 => 'verified', 10 => 'account_balance', 11 => 'person_search', 12 => 'military_tech'
                    ];
                    $icon = $icons[$agent['number']] ?? 'psychology';
                ?>
                <div
                    class="bg-white rounded-xl border transition-all duration-300"
                    :class="getAgentCardClass(<?php echo e($agent['number']); ?>)"
                    x-data="{}"
                >
                    <div class="p-4">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
                                 :class="getAgentIconClass(<?php echo e($agent['number']); ?>)">
                                <span class="material-symbols-outlined text-base"><?php echo e($icon); ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-sm text-slate-900 truncate"><?php echo e($agent['name']); ?></p>
                                    <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full shrink-0">#<?php echo e($agent['number']); ?></span>
                                </div>
                                <p class="text-[11px] text-slate-400"><?php echo e($agent['name_en']); ?></p>
                            </div>
                            <div class="shrink-0">
                                <template x-if="getAgentStatus(<?php echo e($agent['number']); ?>) === 'completed'">
                                    <span class="material-symbols-outlined text-emerald-500">check_circle</span>
                                </template>
                                <template x-if="getAgentStatus(<?php echo e($agent['number']); ?>) === 'running'">
                                    <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                                </template>
                                <template x-if="getAgentStatus(<?php echo e($agent['number']); ?>) === 'failed'">
                                    <span class="material-symbols-outlined text-red-500">error</span>
                                </template>
                                <template x-if="getAgentStatus(<?php echo e($agent['number']); ?>) === 'pending'">
                                    <span class="material-symbols-outlined text-slate-300">hourglass_empty</span>
                                </template>
                            </div>
                        </div>

                        
                        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden mb-2">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="getAgentProgressColor(<?php echo e($agent['number']); ?>)"
                                 :style="'width: ' + getAgentProgress(<?php echo e($agent['number']); ?>) + '%'">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-medium" :class="getAgentTextColor(<?php echo e($agent['number']); ?>)" x-text="getAgentLabel(<?php echo e($agent['number']); ?>)"></span>
                            <span class="text-[10px] text-slate-400"><?php echo e($phaseLabel); ?></span>
                        </div>

                        
                        <template x-if="getAgentOutput(<?php echo e($agent['number']); ?>)">
                            <div class="mt-2 bg-slate-50 rounded-lg p-2 border border-slate-100">
                                <p class="text-[11px] text-slate-600 font-mono leading-relaxed line-clamp-2"
                                   x-text="getAgentOutput(<?php echo e($agent['number']); ?>)"></p>
                            </div>
                        </template>

                        
                        <template x-if="getAgentError(<?php echo e($agent['number']); ?>)">
                            <div class="mt-2 bg-red-50 rounded-lg p-2 border border-red-100">
                                <p class="text-[11px] text-red-600 line-clamp-2" x-text="getAgentError(<?php echo e($agent['number']); ?>)"></p>
                            </div>
                        </template>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-2xl text-white shadow-lg overflow-hidden relative">
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined">lightbulb</span>
                        <span class="font-bold">رؤى الذكاء الاصطناعي</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white/10 p-4 rounded-xl text-center">
                            <p class="text-3xl font-black" x-text="selectedCase.documents_count ?? '-'"></p>
                            <p class="text-sm opacity-80">مستند محلَّل</p>
                        </div>
                        <div class="bg-white/10 p-4 rounded-xl text-center">
                            <p class="text-3xl font-black" x-text="selectedCase.facts_count ?? '-'"></p>
                            <p class="text-sm opacity-80">وقائع مستخرجة</p>
                        </div>
                        <div class="bg-white/10 p-4 rounded-xl text-center">
                            <p class="text-3xl font-black" x-text="selectedCase.laws_count ?? '-'"></p>
                            <p class="text-sm opacity-80">نظام مطابق</p>
                        </div>
                        <div class="bg-white/10 p-4 rounded-xl text-center">
                            <p class="text-3xl font-black" x-text="agentStates.filter(a => a.status === 'completed').length + '/13'"></p>
                            <p class="text-sm opacity-80">وكيل مكتمل</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-8 -left-8 opacity-10">
                    <span class="material-symbols-outlined text-[150px]">psychology</span>
                </div>
            </div>

        </div>
    </template>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function aiAnalysis() {
    return {
        // ── State ──────────────────────────────────────────────────────
        selectedCase: <?php echo json_encode($case, 15, 512) ?>,
        allCases: <?php echo json_encode($allCases, 15, 512) ?>,
        agentDefinitions: <?php echo json_encode($agentDefinitions, 15, 512) ?>,
        agentStates: [],   // [{number, status, progress, output, error}]
        loading: false,
        eventSource: null,
        sseRetryTimer: null,

        // ── Init ───────────────────────────────────────────────────────
        init() {
            // Pre-populate from server-rendered data
            <?php if($case): ?>
            this.initAgentStates();
            <?php $__currentLoopData = $agentDefinitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $exec = $executions->get($agent['number']);
                if ($exec && $exec->isNotEmpty()) {
                    $e = $exec->first();
                    $status = $e->status instanceof \BackedEnum ? $e->status->value : (string)$e->status;
                    echo "this.setAgentState({$agent['number']}, '{$status}', null, null);\n";
                }
            ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            this.connectSSE();
            <?php endif; ?>
        },

        // ── Case selection ─────────────────────────────────────────────
        selectCase(caseId) {
            if (!caseId) {
                this.selectedCase = null;
                this.disconnectSSE();
                return;
            }
            window.location.href = '/ai-analysis/' + caseId;
        },

        get caseUrl() {
            return this.selectedCase ? '/cases/' + this.selectedCase.id : '#';
        },

        // ── Computed status flags ──────────────────────────────────────
        get isProcessing() {
            if (!this.selectedCase) return false;
            const s = this.selectedCase.status;
            return ['phase1_processing', 'phase2_processing', 'phase3_processing',
                    'phase1_pending', 'phase2_pending', 'phase3_pending'].includes(s);
        },
        get isPaused() {
            return this.selectedCase?.status === 'paused';
        },
        get isCompleted() {
            if (!this.selectedCase) return false;
            const s = this.selectedCase.status;
            return ['phase3_completed', 'completed_with_warnings'].includes(s);
        },
        get progressColor() {
            if (this.isCompleted) return 'text-emerald-600';
            if (this.selectedCase?.status === 'failed') return 'text-red-600';
            return 'text-primary';
        },
        get statusLabel() {
            const labels = {
                'phase1_pending': 'في انتظار بدء المعالجة',
                'phase1_processing': 'جاري استخراج الوقائع القانونية',
                'phase1_completed': 'اكتمل تحليل المستندات',
                'awaiting_laws': 'في انتظار الأنظمة والقوانين',
                'phase2_pending': 'في انتظار التحليل القانوني',
                'phase2_processing': 'جاري التحليل القانوني التفصيلي',
                'phase2_completed': 'اكتمل التحليل القانوني',
                'phase3_pending': 'في انتظار المراجعة النهائية',
                'phase3_processing': 'جاري التحكيم القضائي',
                'phase3_completed': 'اكتملت المعالجة بنجاح',
                'completed_with_warnings': 'مكتملة مع ملاحظات',
                'failed': 'فشلت المعالجة',
                'paused': 'متوقف مؤقتاً',
            };
            return labels[this.selectedCase?.status] ?? 'جارٍ...';
        },

        // ── Agent State Management ─────────────────────────────────────
        initAgentStates() {
            this.agentStates = this.agentDefinitions.map(a => ({
                number: a.number,
                status: 'pending',
                progress: 0,
                output: '',
                error: null,
            }));
        },

        setAgentState(number, status, output, error) {
            const idx = this.agentStates.findIndex(a => a.number === number);
            if (idx === -1) return;
            const progress = status === 'completed' ? 100 : (status === 'running' ? 50 : 0);
            this.agentStates[idx] = { ...this.agentStates[idx], status, progress, error: error ?? this.agentStates[idx].error };
            if (output) {
                this.agentStates[idx].output = (this.agentStates[idx].output + output).slice(-300);
            }
        },

        getAgentState(number) {
            return this.agentStates.find(a => a.number === number) ?? { status: 'pending', progress: 0, output: '', error: null };
        },
        getAgentStatus(number)   { return this.getAgentState(number).status; },
        getAgentProgress(number) { return this.getAgentState(number).progress; },
        getAgentOutput(number)   { const o = this.getAgentState(number).output; return o ? o.trim().slice(-200) : null; },
        getAgentError(number)    { return this.getAgentState(number).error; },

        getAgentLabel(number) {
            const s = this.getAgentStatus(number);
            return { completed: 'مكتمل', running: 'جاري التنفيذ...', failed: 'فشل', pending: 'في الانتظار' }[s] ?? s;
        },

        getAgentCardClass(number) {
            const s = this.getAgentStatus(number);
            if (s === 'completed') return 'border-emerald-200 bg-emerald-50/30';
            if (s === 'running')   return 'border-primary ring-1 ring-primary/30 shadow-md shadow-primary/10';
            if (s === 'failed')    return 'border-red-200 bg-red-50/30';
            return 'border-slate-200 opacity-70';
        },
        getAgentIconClass(number) {
            const s = this.getAgentStatus(number);
            if (s === 'completed') return 'bg-emerald-100 text-emerald-600';
            if (s === 'running')   return 'bg-primary/10 text-primary animate-pulse';
            if (s === 'failed')    return 'bg-red-100 text-red-600';
            return 'bg-slate-100 text-slate-400';
        },
        getAgentProgressColor(number) {
            const s = this.getAgentStatus(number);
            if (s === 'completed') return 'bg-emerald-500';
            if (s === 'running')   return 'bg-primary';
            if (s === 'failed')    return 'bg-red-500';
            return 'bg-slate-300';
        },
        getAgentTextColor(number) {
            const s = this.getAgentStatus(number);
            if (s === 'completed') return 'text-emerald-600';
            if (s === 'running')   return 'text-primary';
            if (s === 'failed')    return 'text-red-600';
            return 'text-slate-400';
        },

        // ── SSE Connection ─────────────────────────────────────────────
        connectSSE() {
            if (!this.selectedCase) return;
            this.disconnectSSE();
            const url = '/cases/' + this.selectedCase.id + '/stream';
            this.eventSource = new EventSource(url);

            this.eventSource.onmessage = (e) => {
                try {
                    const event = JSON.parse(e.data);
                    this.handleSSEEvent(event);
                } catch (_) {}
            };

            this.eventSource.onerror = () => {
                this.disconnectSSE();
                // Reconnect after 3s if still processing
                if (this.isProcessing) {
                    this.sseRetryTimer = setTimeout(() => this.connectSSE(), 3000);
                }
            };
        },

        disconnectSSE() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            if (this.sseRetryTimer) {
                clearTimeout(this.sseRetryTimer);
                this.sseRetryTimer = null;
            }
        },

        handleSSEEvent(event) {
            const type = event.event_type;
            const agentNum = event.agent_number;

            if (type === 'agent.started') {
                this.setAgentState(agentNum, 'running', null, null);
            } else if (type === 'agent.output') {
                this.setAgentState(agentNum, 'running', event.content, null);
            } else if (type === 'agent.completed') {
                this.setAgentState(agentNum, 'completed', null, null);
                const completedCount = this.agentStates.filter(a => a.status === 'completed').length;
                if (this.selectedCase) {
                    this.selectedCase.progress_percentage = Math.round((completedCount / 13) * 100);
                }
            } else if (type === 'agent.failed') {
                this.setAgentState(agentNum, 'failed', null, event.error ?? 'فشل الوكيل');
            } else if (type === 'case.status_changed') {
                if (this.selectedCase) {
                    this.selectedCase.status = event.status;
                    if (['phase3_completed', 'completed_with_warnings', 'failed'].includes(event.status)) {
                        this.disconnectSSE();
                        this.refresh();
                    }
                }
            } else if (type === 'connection.timeout') {
                this.disconnectSSE();
                if (this.isProcessing) {
                    this.sseRetryTimer = setTimeout(() => this.connectSSE(), 2000);
                }
            }
        },

        // ── Actions ────────────────────────────────────────────────────
        async togglePause() {
            if (!this.selectedCase) return;
            const url = this.isPaused
                ? '/cases/' + this.selectedCase.id + '/resume'
                : '/cases/' + this.selectedCase.id + '/pause';
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Content-Type': 'application/json' }
                });
                const d = await r.json();
                if (d.success || r.ok) window.location.reload();
            } catch (e) { console.error(e); }
        },

        async refresh() {
            if (!this.selectedCase) return;
            this.loading = true;
            try {
                const r = await fetch('/cases/' + this.selectedCase.id + '/progress-json', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' }
                });
                if (!r.ok) return;
                const d = await r.json();
                const data = d.data;
                if (data) {
                    this.selectedCase = {
                        ...this.selectedCase,
                        status: data.status,
                        phase: data.phase,
                        progress_percentage: data.progress_percentage,
                        current_agent: data.current_agent,
                        documents_count: data.counts?.documents ?? this.selectedCase.documents_count,
                        facts_count: data.counts?.facts ?? this.selectedCase.facts_count,
                        laws_count: data.counts?.laws ?? this.selectedCase.laws_count,
                    };
                    // Update per-agent states from server
                    if (data.agent_states) {
                        data.agent_states.forEach(a => {
                            // Don't overwrite a running state with stale data
                            if (this.getAgentStatus(a.number) !== 'running') {
                                this.setAgentState(a.number, a.status, null, null);
                            }
                        });
                    }
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/pages/ai-analysis.blade.php ENDPATH**/ ?>