@php
    use App\Services\AgentDefinitions;

    $definitions = AgentDefinitions::all();
    $currentGlobalModel  = $case->model_used ?? config('openrouter.default_model');
    $agentOverrides      = $case->agent_model_overrides ?? [];
    $llmProvider         = auth()->user()?->llm_provider ?? 'openrouter';
    $caseStatusVal       = $case->status->value ?? $case->status;
    $isProcessing        = in_array($caseStatusVal, ['phase1_processing','phase2_processing','phase3_processing']);

    // Agent execution states from DB (for status display)
    $executionsByAgent = $case->agentExecutions->keyBy('agent_number');
    $exhaustedAgentNums = $case->agentExecutions
        ->where('self_correction_exhausted', true)
        ->pluck('agent_number')
        ->toArray();

    // Determine completed / failed agents from outputs & executions
    $completedAgentNums = $case->outputs->pluck('agent_number')->unique()->toArray();
    $failedAgentNums = $case->agentExecutions
        ->where('status', 'failed')
        ->pluck('agent_number')
        ->toArray();

    // The halted agent (next to run or the one that failed)
    $haltedAtAgent = $case->halted_at_agent ?? null;
    $currentAgentNum = $case->current_agent ?? null;

    // When Puter is active, use Puter model groups from controller cache
    if ($llmProvider === 'puter') {
        $puterController = app(\App\Http\Controllers\PuterController::class);
        $puterResp = $puterController->getPuterModels();
        $puterData = json_decode($puterResp->getContent(), true);
        $puterModelList = $puterData['models'] ?? [];
        $freeModels  = array_filter($puterModelList, fn($m) => ($m['tier'] ?? '') === 'free');
        $paidModels  = array_filter($puterModelList, fn($m) => ($m['tier'] ?? '') !== 'free');
        $modelGroups = [];
        if (!empty($freeModels)) {
            $modelGroups['مجاني'] = array_combine(
                array_column($freeModels, 'id'),
                array_map(fn($m) => $m['name'] . ' — مجاني', $freeModels)
            );
        }
        if (!empty($paidModels)) {
            $modelGroups['مدفوع'] = array_combine(
                array_column($paidModels, 'id'),
                array_map(fn($m) => $m['name'], $paidModels)
            );
        }
        if (empty($modelGroups)) {
            $modelGroups = ['Puter' => ['gpt-5-nano' => 'GPT-5 Nano — مجاني']];
        }
    } else {
    // Curated model list
    $modelGroups = [
        'Anthropic' => [
            'anthropic/claude-sonnet-4.6'  => 'Claude Sonnet 4.6',
            'anthropic/claude-opus-4.6'    => 'Claude Opus 4.6',
            'anthropic/claude-sonnet-4.5'  => 'Claude Sonnet 4.5',
            'anthropic/claude-3.5-sonnet'  => 'Claude 3.5 Sonnet',
            'anthropic/claude-haiku-4.5'   => 'Claude Haiku 4.5',
        ],
        'OpenAI' => [
            'openai/gpt-4o'            => 'GPT-4o',
            'openai/gpt-4o-mini'       => 'GPT-4o Mini',
            'openai/gpt-4-turbo'       => 'GPT-4 Turbo',
            'openai/o1'                => 'o1',
            'openai/o3-mini'           => 'o3 Mini',
        ],
        'Google' => [
            'google/gemini-3-flash-preview'    => 'Gemini 3 Flash Preview',
            'google/gemini-2.5-flash'         => 'Gemini 2.5 Flash',
            'google/gemini-2.5-flash-lite'    => 'Gemini 2.5 Flash Lite',
            'google/gemini-2.5-pro'           => 'Gemini 2.5 Pro',
            'google/gemini-3.1-pro-preview'   => 'Gemini 3.1 Pro Preview',
            'google/gemini-2.0-flash-001'    => 'Gemini 2.0 Flash',
        ],
        'Meta' => [
            'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
            'meta-llama/llama-4-scout'        => 'Llama 4 Scout',
            'meta-llama/llama-4-maverick'     => 'Llama 4 Maverick',
            'meta-llama/llama-3.1-70b-instruct'=> 'Llama 3.1 70B',
        ],
        'Mistral' => [
            'mistralai/mistral-large'        => 'Mistral Large',
            'mistralai/mistral-small-2603'   => 'Mistral Small 4',
            'mistralai/codestral-2508'      => 'Codestral',
        ],
    ];
    } // end else (openrouter)

    // Provider badge colors
    $providerColors = [
        'anthropic' => 'bg-orange-100 text-orange-700',
        'openai'    => 'bg-emerald-100 text-emerald-700',
        'google'    => 'bg-blue-100 text-blue-700',
        'meta'      => 'bg-indigo-100 text-indigo-700',
        'mistral'   => 'bg-violet-100 text-violet-700',
        'qwen'      => 'bg-rose-100 text-rose-700',
    ];

    function mcModelLabel(string $modelId, array $groups): string {
        foreach ($groups as $provider => $models) {
            if (isset($models[$modelId])) return $models[$modelId];
        }
        $parts = explode('/', $modelId);
        return end($parts);
    }
    function mcProviderFromId(string $modelId): string {
        return strtolower(explode('/', $modelId)[0] ?? 'other');
    }
@endphp

{{-- ===== CENTERED MODAL OVERLAY ===== --}}
<div id="modelConfigDrawer"
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modelConfigTitle"
     dir="rtl">

    {{-- Backdrop --}}
    <div id="modelConfigBackdrop"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0"
         onclick="closeModelConfig()"></div>

    {{-- Modal panel --}}
    <div id="modelConfigPanel"
         class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col
                transition-all duration-300 scale-95 opacity-0 overflow-hidden">

        {{-- ── Header ── --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0 bg-gradient-to-l from-primary/5 to-white">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-primary text-lg">memory</span>
                </div>
                <div>
                    <h2 id="modelConfigTitle" class="font-bold text-slate-900 text-base leading-tight">إعداد نماذج الوكلاء</h2>
                    <p class="text-xs text-slate-500 mt-0.5">خصص نموذج الذكاء الاصطناعي لكل وكيل على حدة</p>
                </div>
            </div>
            <button onclick="closeModelConfig()"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                    aria-label="إغلاق">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        {{-- ── Tabs ── --}}
        <div class="flex border-b border-slate-100 flex-shrink-0 px-6 bg-slate-50/50">
            <button id="tab-global" onclick="switchTab('global')"
                    class="py-3 px-4 text-sm font-semibold border-b-2 border-primary text-primary transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-sm align-middle ml-1">public</span>
                النموذج العام
            </button>
            <button id="tab-agents" onclick="switchTab('agents')"
                    class="py-3 px-4 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-sm align-middle ml-1">dns</span>
                الوكلاء
                {{-- Badge showing count of overrides or issues --}}
                @php $issueCount = count($exhaustedAgentNums) + count($failedAgentNums); @endphp
                @if($issueCount > 0)
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold bg-red-100 text-red-700 rounded-full mr-1">{{ $issueCount }}</span>
                @elseif(count($agentOverrides) > 0)
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold bg-amber-100 text-amber-700 rounded-full mr-1">{{ count($agentOverrides) }}</span>
                @endif
            </button>
        </div>

        {{-- ── Scrollable body ── --}}
        <div class="flex-1 overflow-y-auto">

            {{-- ══════════ TAB: GLOBAL MODEL ══════════ --}}
            <div id="panel-global" class="px-6 py-5 space-y-5">

                {{-- Global Model Card --}}
                <div class="rounded-2xl border-2 border-primary/20 bg-gradient-to-br from-primary/5 to-emerald-50 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-lg">public</span>
                        <h3 class="font-bold text-slate-800 text-sm">النموذج العام للقضية</h3>
                        <span class="text-xs text-slate-400 mr-auto">يُطبَّق على جميع الوكلاء غير المخصَّصين</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <select id="globalModelSelect"
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer transition-colors"
                                    onchange="onGlobalModelChange(this.value)">
                                @foreach($modelGroups as $provider => $models)
                                    <optgroup label="{{ $provider }}">
                                        @foreach($models as $id => $label)
                                            <option value="{{ $id }}" {{ $currentGlobalModel === $id ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                                <optgroup label="أخرى / مخصص">
                                    <option value="__custom__">← أدخل معرّف نموذج مخصص</option>
                                </optgroup>
                            </select>
                        </div>
                        <button onclick="saveGlobalModel()"
                                id="saveGlobalBtn"
                                class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary/90 active:scale-95 transition-all cursor-pointer shadow-sm">
                            حفظ
                            <span class="material-symbols-outlined text-sm">save</span>
                        </button>
                    </div>
                    <div id="globalCustomInput" class="mt-2 hidden">
                        <input type="text"
                               id="globalCustomModel"
                               placeholder="مثال: anthropic/claude-3.5-sonnet"
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-primary bg-white"
                               dir="ltr">
                    </div>
                    <p id="globalModelCurrentLabel" class="mt-2 text-xs text-slate-500">
                        الحالي:
                        <span class="font-mono font-medium text-primary" id="globalModelCurrentId">{{ $currentGlobalModel }}</span>
                    </p>
                </div>

                {{-- Apply to All --}}
                <button onclick="applyModelToAll()"
                        class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl border-2 border-dashed border-slate-300 text-slate-600 text-sm font-semibold hover:border-primary hover:text-primary hover:bg-primary/5 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-base">auto_fix_high</span>
                    تطبيق النموذج العام على جميع الوكلاء وحذف التخصيصات الفردية
                </button>

                {{-- Pipeline summary stats --}}
                <div class="grid grid-cols-4 gap-3">
                    @php
                        $totalAgents = count($definitions);
                        $completedCount = count($completedAgentNums);
                        $failedCount = count($failedAgentNums);
                        $pendingCount = $totalAgents - $completedCount - $failedCount;
                    @endphp
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-center">
                        <p class="text-2xl font-black text-emerald-600">{{ $completedCount }}</p>
                        <p class="text-xs text-emerald-700 font-medium mt-0.5">مكتمل</p>
                    </div>
                    <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-center">
                        <p class="text-2xl font-black text-red-600">{{ $failedCount }}</p>
                        <p class="text-xs text-red-700 font-medium mt-0.5">فشل</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-center">
                        <p class="text-2xl font-black text-amber-600">{{ count($exhaustedAgentNums) }}</p>
                        <p class="text-xs text-amber-700 font-medium mt-0.5">تحذير دقة</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-center">
                        <p class="text-2xl font-black text-slate-500">{{ max(0, $pendingCount) }}</p>
                        <p class="text-xs text-slate-600 font-medium mt-0.5">متبقٍ</p>
                    </div>
                </div>
            </div>

            {{-- ══════════ TAB: PER-AGENT ══════════ --}}
            <div id="panel-agents" class="hidden px-6 py-5 space-y-2">

                @foreach($definitions as $def)
                @php
                    $num = $def['number'];
                    $override = $agentOverrides[(string)$num] ?? null;
                    $effective = $override ?? $currentGlobalModel;
                    $provider = mcProviderFromId($effective);
                    $badgeClass = $providerColors[$provider] ?? 'bg-slate-100 text-slate-600';
                    $phaseLabel = match($def['phase']) { 1 => 'م١', 2 => 'م٢', 3 => 'م٣', default => '' };
                    $phaseColor = match($def['phase']) {
                        1 => 'bg-blue-100 text-blue-700',
                        2 => 'bg-emerald-100 text-emerald-700',
                        3 => 'bg-indigo-100 text-indigo-700',
                    };
                    $isOverridden = !is_null($override);

                    // Agent state
                    $isCompleted  = in_array($num, $completedAgentNums);
                    $isFailed     = in_array($num, $failedAgentNums);
                    $isExhausted  = in_array($num, $exhaustedAgentNums);
                    $isHalted     = ($haltedAtAgent === $num);
                    $isNext       = (!$isCompleted && !$isFailed && $haltedAtAgent !== null && $num === $haltedAtAgent);

                    // Row styling
                    if ($isFailed) {
                        $rowBg = 'border-red-200 bg-red-50/40';
                    } elseif ($isExhausted) {
                        $rowBg = 'border-amber-200 bg-amber-50/40';
                    } elseif ($isCompleted) {
                        $rowBg = $isOverridden ? 'border-amber-200 bg-amber-50/30' : 'border-emerald-100 bg-emerald-50/20';
                    } elseif ($isNext) {
                        $rowBg = 'border-blue-200 bg-blue-50/30';
                    } else {
                        $rowBg = 'border-slate-100 bg-slate-50/40';
                    }
                @endphp

                <div class="rounded-xl border {{ $rowBg }} transition-all"
                     id="agent-model-row-{{ $num }}"
                     data-agent="{{ $num }}">
                    <div class="flex items-start gap-3 p-3.5">
                        {{-- Agent state icon --}}
                        <div class="flex-shrink-0 mt-0.5">
                            @if($isFailed)
                                <div class="w-7 h-7 rounded-full bg-red-500 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-sm">close</span>
                                </div>
                            @elseif($isExhausted)
                                <div class="w-7 h-7 rounded-full bg-amber-500 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-sm">warning</span>
                                </div>
                            @elseif($isCompleted)
                                <div class="w-7 h-7 rounded-full bg-emerald-500 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-sm">check</span>
                                </div>
                            @elseif($isNext)
                                <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-sm">play_arrow</span>
                                </div>
                            @else
                                <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-slate-400 text-sm">schedule</span>
                                </div>
                            @endif
                        </div>

                        {{-- Phase badge + Agent name --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold px-1.5 py-0.5 rounded {{ $phaseColor }}">{{ $phaseLabel }} {{ $num }}</span>
                                <p class="text-sm font-semibold text-slate-800">{{ $def['name'] }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $def['name_en'] }}</p>
                                {{-- Status tags --}}
                                @if($isFailed)
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-red-100 text-red-700 font-semibold">فشل</span>
                                @elseif($isExhausted)
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 font-semibold flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-xs">warning</span>
                                        دقة غير مضمونة
                                    </span>
                                @elseif($isCompleted)
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-700 font-semibold">مكتمل</span>
                                @elseif($isNext)
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-blue-100 text-blue-700 font-semibold">التالي</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500">في الانتظار</span>
                                @endif
                            </div>

                            {{-- Current effective model --}}
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <span class="text-xs px-1.5 py-0.5 rounded-md font-medium {{ $badgeClass }}" id="badge-{{ $num }}">
                                    {{ ucfirst(mcProviderFromId($effective)) }}
                                </span>
                                <span class="text-xs font-mono text-slate-500" id="effective-model-{{ $num }}">
                                    {{ mcModelLabel($effective, $modelGroups) }}
                                </span>
                                @if($isOverridden)
                                    <span class="text-xs px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 font-semibold flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-xs">tune</span>
                                        مخصص
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Override indicator dot --}}
                        <div class="flex-shrink-0 w-2 h-2 rounded-full mt-2 {{ $isOverridden ? 'bg-amber-400' : 'bg-slate-200' }}"
                             id="override-dot-{{ $num }}"></div>
                    </div>

                    {{-- Inline model editor (collapsed by default) --}}
                    <div class="px-3.5 pb-3.5 pt-0 hidden" id="agent-model-editor-{{ $num }}">
                        <div class="border-t border-slate-100 pt-3">
                            <div class="flex items-center gap-2">
                                <select id="agent-model-select-{{ $num }}"
                                        class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-primary appearance-none cursor-pointer"
                                        onchange="onAgentModelSelectChange({{ $num }}, this.value)">
                                    <option value="">— استخدام النموذج العام —</option>
                                    @foreach($modelGroups as $prov => $models)
                                        <optgroup label="{{ $prov }}">
                                            @foreach($models as $id => $label)
                                                <option value="{{ $id }}" {{ ($override ?? '') === $id ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                    <optgroup label="أخرى / مخصص">
                                        <option value="__custom__">← أدخل معرّف مخصص</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div id="agent-custom-input-{{ $num }}" class="mt-1.5 hidden">
                                <input type="text"
                                       id="agent-custom-model-{{ $num }}"
                                       placeholder="مثال: openai/gpt-4o"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary"
                                       dir="ltr">
                            </div>

                            {{-- Action buttons --}}
                            <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                                <button onclick="saveAgentModel({{ $num }})"
                                        class="flex items-center gap-1 px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 active:scale-95 transition-all cursor-pointer shadow-sm">
                                    <span class="material-symbols-outlined text-sm">save</span>
                                    حفظ التغيير
                                </button>
                                <button onclick="rerunAgentWithModel({{ $num }})"
                                        class="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer shadow-sm">
                                    <span class="material-symbols-outlined text-sm">play_arrow</span>
                                    حفظ وتشغيل
                                </button>
                                @if($isOverridden)
                                <button onclick="clearAgentModel({{ $num }})"
                                        id="clear-btn-{{ $num }}"
                                        class="flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg hover:bg-slate-200 active:scale-95 transition-all cursor-pointer">
                                    إزالة التخصيص
                                    <span class="material-symbols-outlined text-sm">restart_alt</span>
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Toggle editor button --}}
                    <button onclick="toggleAgentEditor({{ $num }})"
                            id="toggle-editor-{{ $num }}"
                            class="w-full flex items-center justify-center gap-1 py-1.5 text-xs text-slate-400 hover:text-primary hover:bg-primary/5 rounded-b-xl transition-all cursor-pointer group border-t border-slate-100">
                        <span class="material-symbols-outlined text-sm group-hover:text-primary transition-colors">edit</span>
                        <span id="toggle-editor-label-{{ $num }}">تعديل النموذج</span>
                        <span class="material-symbols-outlined text-xs transition-transform" id="toggle-editor-arrow-{{ $num }}">expand_more</span>
                    </button>
                </div>
                @endforeach
            </div>

        </div>

        {{-- ── Footer ── --}}
        <div class="border-t border-slate-100 px-6 py-3.5 flex-shrink-0 bg-slate-50/80 flex items-center justify-between">
            <p class="text-xs text-slate-500">
                <span class="material-symbols-outlined text-xs align-middle">info</span>
                التغييرات تُحفظ فور الضغط على "حفظ"
            </p>
            <button onclick="closeModelConfig()"
                    class="px-4 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                إغلاق
            </button>
        </div>
    </div>
</div>

{{-- ── Toast notification ── --}}
<div id="modelConfigToast"
     class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] hidden"
     role="alert">
    <div class="flex items-center gap-2.5 bg-slate-900 text-white text-sm font-medium px-5 py-3 rounded-2xl shadow-xl">
        <span class="material-symbols-outlined text-base" id="toastIcon">check_circle</span>
        <span id="toastMessage">تم الحفظ</span>
    </div>
</div>

<script>
// ─────────────────────────────────────────────
// MODEL CONFIG MODAL — STATE & HELPERS
// ─────────────────────────────────────────────
const MODEL_CONFIG_CASE_ID = '{{ $case->id }}';
const MODEL_CONFIG_CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || '';
let   mcGlobalModel        = '{{ $currentGlobalModel }}';
const mcAgentOverrides     = @json($agentOverrides);

// Currently selected value per agent (mirrors the select element)
const mcAgentSelected = {};
@foreach($definitions as $def)
mcAgentSelected[{{ $def['number'] }}] = '{{ $agentOverrides[(string)$def['number']] ?? '' }}';
@endforeach

// ── Tab switching ──────────────────────────────
let mcActiveTab = 'global';

function switchTab(tab) {
    mcActiveTab = tab;
    ['global', 'agents'].forEach(t => {
        const btn   = document.getElementById(`tab-${t}`);
        const panel = document.getElementById(`panel-${t}`);
        if (t === tab) {
            btn.classList.add('border-primary', 'text-primary');
            btn.classList.remove('border-transparent', 'text-slate-500');
            panel.classList.remove('hidden');
        } else {
            btn.classList.remove('border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-slate-500');
            panel.classList.add('hidden');
        }
    });
}

// ── Open / Close ──────────────────────────────
function openModelConfig() {
    const drawer   = document.getElementById('modelConfigDrawer');
    const panel    = document.getElementById('modelConfigPanel');
    const backdrop = document.getElementById('modelConfigBackdrop');
    drawer.classList.remove('hidden');
    drawer.classList.add('flex');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
    });
}

function closeModelConfig() {
    const drawer   = document.getElementById('modelConfigDrawer');
    const panel    = document.getElementById('modelConfigPanel');
    const backdrop = document.getElementById('modelConfigBackdrop');
    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    panel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        drawer.classList.add('hidden');
        drawer.classList.remove('flex');
    }, 300);
}

// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModelConfig(); });

// ── Toast ─────────────────────────────────────
function showToast(message, type = 'success') {
    const toast   = document.getElementById('modelConfigToast');
    const icon    = document.getElementById('toastIcon');
    const msgEl   = document.getElementById('toastMessage');
    icon.textContent  = type === 'error' ? 'error' : 'check_circle';
    msgEl.textContent = message;
    toast.classList.remove('hidden');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.add('hidden'), 3000);
}

// ── Global model logic ────────────────────────
function onGlobalModelChange(value) {
    const customWrap = document.getElementById('globalCustomInput');
    if (value === '__custom__') {
        customWrap.classList.remove('hidden');
    } else {
        customWrap.classList.add('hidden');
        mcGlobalModel = value;
    }
}

function getGlobalModelValue() {
    const sel = document.getElementById('globalModelSelect');
    if (sel.value === '__custom__') {
        return document.getElementById('globalCustomModel').value.trim();
    }
    return sel.value;
}

function saveGlobalModel() {
    const model = getGlobalModelValue();
    if (!model) return;
    const btn = document.getElementById('saveGlobalBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span>';

    fetch(`/cases/${MODEL_CONFIG_CASE_ID}/model-config`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MODEL_CONFIG_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ global_model: model })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mcGlobalModel = model;
            document.getElementById('globalModelCurrentId').textContent = model;
            showToast('تم حفظ النموذج العام');
            refreshInheritingRows();
        } else { showToast('حدث خطأ', 'error'); }
    })
    .catch(() => showToast('تعذر الاتصال بالخادم', 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> حفظ';
    });
}

function applyModelToAll() {
    const model = getGlobalModelValue();
    if (!model) return;
    if (!confirm('هل تريد تطبيق النموذج "' + model + '" على جميع الوكلاء وحذف أي تخصيصات فردية؟')) return;

    fetch(`/cases/${MODEL_CONFIG_CASE_ID}/model-config`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MODEL_CONFIG_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ global_model: model, agent_overrides: buildClearAllOverrides() })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mcGlobalModel = model;
            document.getElementById('globalModelCurrentId').textContent = model;
            showToast('تم تطبيق النموذج على جميع الوكلاء');
            refreshAllRows(model);
        } else { showToast('حدث خطأ', 'error'); }
    })
    .catch(() => showToast('تعذر الاتصال بالخادم', 'error'));
}

function buildClearAllOverrides() {
    const obj = {};
    document.querySelectorAll('[data-agent]').forEach(el => {
        obj[el.dataset.agent] = '';
    });
    return obj;
}

// ── Per-agent logic ────────────────────────────
function toggleAgentEditor(num) {
    const editor  = document.getElementById(`agent-model-editor-${num}`);
    const arrow   = document.getElementById(`toggle-editor-arrow-${num}`);
    const label   = document.getElementById(`toggle-editor-label-${num}`);
    const isOpen  = !editor.classList.contains('hidden');
    editor.classList.toggle('hidden', isOpen);
    arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
    label.textContent = isOpen ? 'تعديل النموذج' : 'إخفاء';
}

function onAgentModelSelectChange(num, value) {
    const customWrap = document.getElementById(`agent-custom-input-${num}`);
    if (value === '__custom__') {
        customWrap.classList.remove('hidden');
    } else {
        customWrap.classList.add('hidden');
        mcAgentSelected[num] = value;
    }
}

function getAgentModelValue(num) {
    const sel = document.getElementById(`agent-model-select-${num}`);
    if (sel.value === '__custom__') {
        return document.getElementById(`agent-custom-model-${num}`).value.trim();
    }
    return sel.value; // '' means "use global"
}

function saveAgentModel(num) {
    const model = getAgentModelValue(num);
    doSaveAgentOverride(num, model, false);
}

function clearAgentModel(num) {
    doSaveAgentOverride(num, '', false);
}

function rerunAgentWithModel(num) {
    const model = getAgentModelValue(num);
    if (!model) {
        showToast('يرجى اختيار نموذج أولاً', 'error');
        return;
    }
    if (!confirm(`هل تريد إعادة تشغيل الوكيل ${num} باستخدام النموذج "${model}"؟\n\nسيُحذف المخرج السابق ويُعاد توليده.`)) return;
    doRerunWithModel(num, model);
}

function doSaveAgentOverride(num, model, andRun) {
    const overrides = {};
    overrides[num] = model;

    fetch(`/cases/${MODEL_CONFIG_CASE_ID}/model-config`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MODEL_CONFIG_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ agent_overrides: overrides })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateRowUI(num, model || null);
            showToast(model ? `تم تخصيص نموذج الوكيل ${num}` : `تمت إزالة تخصيص الوكيل ${num}`);
        } else { showToast('حدث خطأ', 'error'); }
    })
    .catch(() => showToast('تعذر الاتصال بالخادم', 'error'));
}

function doRerunWithModel(num, model) {
    fetch(`/cases/${MODEL_CONFIG_CASE_ID}/rerun-agent-with-model`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MODEL_CONFIG_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ agent_number: num, model: model })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateRowUI(num, model);
            showToast(`جارٍ تشغيل الوكيل ${num} بـ ${model}`);
            closeModelConfig();
            setTimeout(() => { if (typeof startSSE === 'function') startSSE(); }, 800);
        } else { showToast('حدث خطأ أثناء التشغيل', 'error'); }
    })
    .catch(() => showToast('تعذر الاتصال بالخادم', 'error'));
}

// ── UI update helpers ──────────────────────────
const PROVIDER_COLORS = @json($providerColors);

function providerFromModelId(id) {
    if (!id) return 'other';
    return (id.split('/')[0] || 'other').toLowerCase();
}

function shortModelLabel(id) {
    if (!id) return '';
    const parts = id.split('/');
    return parts[parts.length - 1] || id;
}

function updateRowUI(num, override) {
    const effective  = override || mcGlobalModel;
    const provider   = providerFromModelId(effective);
    const badge      = document.getElementById(`badge-${num}`);
    const label      = document.getElementById(`effective-model-${num}`);
    const dot        = document.getElementById(`override-dot-${num}`);
    const row        = document.getElementById(`agent-model-row-${num}`);

    if (badge) {
        const cls = PROVIDER_COLORS[provider] || 'bg-slate-100 text-slate-600';
        badge.className = `text-xs px-1.5 py-0.5 rounded-md font-medium ${cls}`;
        badge.textContent = provider.charAt(0).toUpperCase() + provider.slice(1);
    }
    if (label) label.textContent = shortModelLabel(effective);
    if (dot)   dot.className     = `flex-shrink-0 w-2 h-2 rounded-full mt-2 ${override ? 'bg-amber-400' : 'bg-slate-200'}`;

    // Update "remove override" button visibility
    const clearBtn = document.getElementById(`clear-btn-${num}`);
    if (clearBtn) {
        clearBtn.style.display = override ? '' : 'none';
    }
}

function refreshInheritingRows() {
    @foreach($definitions as $def)
    if (!mcAgentOverrides[{{ $def['number'] }}]) {
        updateRowUI({{ $def['number'] }}, null);
    }
    @endforeach
}

function refreshAllRows(model) {
    @foreach($definitions as $def)
    updateRowUI({{ $def['number'] }}, null);
    { const sel = document.getElementById('agent-model-select-{{ $def['number'] }}'); if (sel) sel.value = ''; }
    @endforeach
}

// ── openModelConfigForAgent: open modal on agents tab, scrolled to agent ──
function openModelConfigForAgent(agentNumber) {
    openModelConfig();
    // Switch to agents tab
    setTimeout(() => {
        switchTab('agents');
        // Scroll to and expand target agent row
        setTimeout(() => {
            const row = document.getElementById(`agent-model-row-${agentNumber}`);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const editor = document.getElementById(`agent-model-editor-${agentNumber}`);
                if (editor && editor.classList.contains('hidden')) {
                    toggleAgentEditor(agentNumber);
                }
            }
        }, 100);
    }, 320);
}
</script>
