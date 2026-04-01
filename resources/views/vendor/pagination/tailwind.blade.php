@if ($paginator->hasPages())
<nav dir="rtl" role="navigation" aria-label="Pagination" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

    {{-- Results summary --}}
    <p class="text-sm text-slate-500 order-2 sm:order-1">
        @if ($paginator->firstItem())
            عرض
            <span class="font-semibold text-slate-700">{{ number_format($paginator->firstItem()) }}</span>
            –
            <span class="font-semibold text-slate-700">{{ number_format($paginator->lastItem()) }}</span>
            من إجمالي
            <span class="font-semibold text-slate-700">{{ number_format($paginator->total()) }}</span>
            نتيجة
        @else
            {{ number_format($paginator->count()) }} نتيجة
        @endif
    </p>

    {{-- Page buttons --}}
    <div class="flex items-center gap-1 order-1 sm:order-2">

        {{-- Previous (→ in RTL = go to earlier page) --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors duration-150 cursor-pointer"
               aria-label="{{ __('pagination.previous') }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex items-center justify-center w-9 h-9 text-sm text-slate-400 select-none">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-bold text-white bg-primary border border-primary shadow-sm shadow-primary/20">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}"
                           class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors duration-150 cursor-pointer"
                           aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next (← in RTL = go to later page) --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors duration-150 cursor-pointer"
               aria-label="{{ __('pagination.next') }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
        @else
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="15 18 9 12 15 6"/></svg>
            </span>
        @endif

    </div>
</nav>
@endif
