@props(['case'])
{{-- Case Output Modal — professional Arabic legal brief viewer with Word export --}}

<style>
/* ── Output Modal Content Styles ──────────────────────────────────────────── */
#outputModalContent {
    font-family: 'Cairo', sans-serif;
    color: #1e293b;
    line-height: 2;
}
#outputModalContent h1 {
    font-size: 1.6rem;
    font-weight: 900;
    color: #0f172a;
    text-align: center;
    padding-bottom: 0.75rem;
    margin: 0 0 1.75rem;
    border-bottom: 3px solid #006b34;
}
#outputModalContent h2 {
    font-size: 1.25rem;
    font-weight: 800;
    color: #006b34;
    padding-right: 0.85rem;
    margin: 2rem 0 0.9rem;
    border-right: 4px solid #006b34;
    line-height: 1.5;
}
#outputModalContent h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 1.5rem 0 0.6rem;
    padding-right: 0.6rem;
    border-right: 3px solid #2faf74;
    line-height: 1.5;
}
#outputModalContent h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #334155;
    margin: 1.2rem 0 0.5rem;
}
#outputModalContent p {
    color: #334155;
    margin: 0 0 0.9rem;
    text-align: justify;
}
#outputModalContent ul {
    margin: 0 1.75rem 0.9rem;
    list-style-type: disc;
}
#outputModalContent ol {
    margin: 0 1.75rem 0.9rem;
    list-style-type: decimal;
}
#outputModalContent li {
    color: #334155;
    margin-bottom: 0.35rem;
    padding-right: 0.25rem;
}
#outputModalContent li::marker {
    color: #006b34;
    font-weight: 700;
}
#outputModalContent strong, #outputModalContent b {
    font-weight: 700;
    color: #0f172a;
}
#outputModalContent em {
    font-style: italic;
    color: #475569;
}
#outputModalContent hr {
    border: none;
    border-top: 2px solid #e2e8f0;
    margin: 2rem 0;
}
/* Tables */
#outputModalContent table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    font-size: 0.9rem;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 0 0 1px #cbd5e1;
}
#outputModalContent thead {
    background: #006b3415;
}
#outputModalContent th {
    border: 1px solid #cbd5e1;
    padding: 0.65rem 0.85rem;
    font-weight: 700;
    color: #0f172a;
    font-size: 0.875rem;
}
#outputModalContent td {
    border: 1px solid #e2e8f0;
    padding: 0.6rem 0.85rem;
    color: #334155;
}
#outputModalContent tr:nth-child(even) td {
    background: #f8fafc;
}
/* Blockquotes */
#outputModalContent blockquote {
    border-right: 4px solid #006b3450;
    background: #f0fdf4;
    margin: 1.25rem 0;
    padding: 0.75rem 1rem;
    color: #166534;
    border-radius: 0 0.5rem 0.5rem 0;
}
/* Date bolding */
#outputModalContent .brief-date {
    font-weight: 700;
    color: #0f172a;
}
/* Bism'Allah opening */
#outputModalContent > p:first-child,
#outputModalContent > h1:first-child {
    text-align: center;
    font-size: 1.1rem;
    color: #006b34;
    font-weight: 700;
    margin-bottom: 1.5rem;
}
</style>

<div id="caseOutputModal"
     class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="outputModalTitle">

    {{-- Modal panel --}}
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl max-h-[92vh] flex flex-col" dir="rtl">

        {{-- Sticky header --}}
        <div class="flex-shrink-0 rounded-t-2xl border-b border-slate-100 px-6 py-4
                    bg-gradient-to-l from-slate-50 to-white flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-primary text-xl">gavel</span>
            </div>
            <div class="flex-1 min-w-0">
                <h2 id="outputModalTitle" class="text-base font-black text-slate-900 leading-tight">
                    المذكرة القانونية النهائية
                </h2>
                <p class="text-xs text-slate-400 truncate mt-0.5">{{ $case->title ?? '' }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                {{-- Export to Word --}}
                <button onclick="exportBriefToWord()"
                        type="button"
                        title="تصدير إلى Word"
                        class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-bold
                               rounded-xl hover:bg-blue-700 active:scale-95 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-sm">description</span>
                    <span>تصدير Word</span>
                </button>
                {{-- Close --}}
                <button id="outputModalCloseBtn"
                        onclick="closeOutputModal()"
                        type="button"
                        aria-label="إغلاق"
                        class="w-9 h-9 flex items-center justify-center rounded-xl text-slate-400
                               hover:text-slate-700 hover:bg-slate-100 transition-colors text-xl font-bold">
                    ×
                </button>
            </div>
        </div>

        {{-- Scrollable content --}}
        <div id="outputModalContent"
             dir="rtl"
             class="overflow-y-auto flex-1 px-8 py-8">
            {{-- Populated by openOutputModal() --}}
        </div>

    </div>
</div>

{{-- marked.js Markdown renderer --}}
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
// ── Output Modal ────────────────────────────────────────────────────────────

function openOutputModal() {
    var modal = document.getElementById('caseOutputModal');
    if (!modal) return;

    var contentEl = document.getElementById('outputModalContent');

    // Primary source: server-composed final brief (injected by show.blade.php)
    var finalBrief = (typeof window.finalBriefContent !== 'undefined') ? window.finalBriefContent : '';

    if (!finalBrief || !finalBrief.trim()) {
        // Fallback: collect Phase-2 agent markdown outputs (legacy path for in-progress cases)
        var outputs = [];
        for (var i = 1; i <= 9; i++) {
            if (typeof dbOutputsByAgent !== 'undefined' &&
                dbOutputsByAgent[i] && dbOutputsByAgent[i].length > 0) {
                var content = dbOutputsByAgent[i]
                    .map(function(o) { return o.content || ''; })
                    .join('\n\n');
                if (content.trim()) { outputs.push(content.trim()); continue; }
            }
            var streamEl = document.getElementById('agent-stream-' + i);
            if (streamEl && streamEl.textContent.trim()) {
                outputs.push(streamEl.textContent.trim());
            }
        }
        finalBrief = outputs.join('\n\n---\n\n');
    }

    if (!finalBrief || !finalBrief.trim()) {
        contentEl.innerHTML =
            '<p style="text-align:center;color:#94a3b8;padding:4rem 0;font-size:1rem;">لا توجد نتائج متاحة</p>';
    } else {
        var html = '';
        if (typeof marked !== 'undefined') {
            marked.setOptions({ breaks: true, gfm: true });
            html = marked.parse(finalBrief);
        } else {
            var escaped = finalBrief
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            html = '<pre style="white-space:pre-wrap;font-size:0.9rem;line-height:1.8;">' + escaped + '</pre>';
        }
        contentEl.innerHTML = boldDates(html);
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    contentEl.scrollTop = 0;
}

function closeOutputModal() {
    var modal = document.getElementById('caseOutputModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

/**
 * Wrap Gregorian and Hijri date patterns in <strong> for visual emphasis.
 * Avoids double-wrapping already-bolded text.
 */
function boldDates(html) {
    // Gregorian ISO: 2024-01-15
    html = html.replace(/(?<![>"])(\b\d{4}-\d{2}-\d{2}\b)/g, '<strong class="brief-date">$1</strong>');
    // Gregorian slash: 15/01/2024
    html = html.replace(/(?<![>"])(\b\d{1,2}\/\d{1,2}\/\d{4}\b)/g, '<strong class="brief-date">$1</strong>');
    // Hijri months
    var hijriMonths = 'محرم|صفر|ربيع الأول|ربيع الثاني|ربيع الآخر|جمادى الأولى|جمادى الثانية|جمادى الآخرة|رجب|شعبان|رمضان|شوال|ذو القعدة|ذو الحجة|ذي القعدة|ذي الحجة';
    var hijriRx = new RegExp(
        '([\u0660-\u0669\\d]{1,2}\\s+(?:' + hijriMonths + ')\\s+[\u0660-\u0669\\d]{4}\\s*هـ?)',
        'g'
    );
    html = html.replace(hijriRx, '<strong class="brief-date">$1</strong>');
    return html;
}

/**
 * Activate the "عرض النتائج" button (called by SSE on pipeline completion).
 */
function activateOutputButton() {
    var container = document.getElementById('outputModalBtnContainer');
    if (!container) return;
    container.innerHTML =
        '<button type="button" onclick="openOutputModal()" id="outputModalBtnEl"' +
        ' class="w-full flex items-center gap-3 p-3 bg-primary/10 rounded-xl' +
        ' hover:bg-primary/20 transition-colors text-primary font-semibold">' +
        '<span class="material-symbols-outlined">article</span>' +
        '<span class="text-sm">عرض النتائج</span>' +
        '</button>';
}

/**
 * Export the rendered brief to a Word-compatible .doc file.
 */
function exportBriefToWord() {
    var title = (typeof window.caseTitle !== 'undefined' && window.caseTitle)
        ? window.caseTitle
        : 'مذكرة قانونية';
    var content = document.getElementById('outputModalContent').innerHTML;
    var date = new Date().toLocaleDateString('ar-SA');

    var wordHtml =
        '<!DOCTYPE html>' +
        '<html xmlns:o="urn:schemas-microsoft-com:office:office"' +
        '      xmlns:w="urn:schemas-microsoft-com:office:word"' +
        '      xmlns="http://www.w3.org/TR/REC-html40">' +
        '<head><meta charset="utf-8"><title>' + title + '</title>' +
        '<style>' +
        'body{font-family:Arial,sans-serif;direction:rtl;font-size:14pt;line-height:2;margin:2.5cm;}' +
        'h1{font-size:22pt;font-weight:bold;text-align:center;margin:0 0 12pt;border-bottom:2pt solid #006b34;padding-bottom:6pt;}' +
        'h2{font-size:18pt;font-weight:bold;color:#006b34;margin:18pt 0 8pt;border-right:4pt solid #006b34;padding-right:8pt;}' +
        'h3{font-size:15pt;font-weight:bold;margin:14pt 0 6pt;}' +
        'h4{font-size:13pt;font-weight:bold;margin:10pt 0 4pt;}' +
        'p{margin:0 0 8pt;text-align:justify;}' +
        'ul,ol{margin:0 24pt 8pt;}' +
        'li{margin-bottom:4pt;}' +
        'table{border-collapse:collapse;width:100%;margin:12pt 0;}' +
        'td,th{border:1pt solid #888;padding:6pt 8pt;font-size:13pt;}' +
        'th{background:#e8f5ee;font-weight:bold;}' +
        'tr:nth-child(even) td{background:#f9f9f9;}' +
        'blockquote{border-right:4pt solid #006b34;background:#f0fdf4;margin:10pt 0;padding:6pt 10pt;}' +
        'hr{border:none;border-top:1pt solid #ccc;margin:14pt 0;}' +
        'strong,b{font-weight:bold;}' +
        '</style></head>' +
        '<body dir="rtl">' +
        '<div style="text-align:center;margin-bottom:24pt;">' +
        '<p style="font-size:13pt;color:#006b34;font-weight:bold;margin:0 0 4pt;">بسم الله الرحمن الرحيم</p>' +
        '<h1 style="font-size:20pt;">' + title + '</h1>' +
        '<p style="color:#666;font-size:11pt;margin:4pt 0 0;">' + date + '</p>' +
        '<hr style="border-top:2pt solid #006b34;width:50%;margin:12pt auto 0;">' +
        '</div>' +
        content +
        '</body></html>';

    var blob = new Blob(['\ufeff', wordHtml], { type: 'application/msword' });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href   = url;
    a.download = 'مذكرة_قانونية.doc';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
}

// Close on backdrop click
document.getElementById('caseOutputModal').addEventListener('click', function(e) {
    if (e.target === this) closeOutputModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('caseOutputModal');
        if (modal && !modal.classList.contains('hidden')) closeOutputModal();
    }
});

// Auto-open on page load for completed cases (handles page refresh scenario)
(function() {
    if (window.finalBriefAutoOpen && window.finalBriefContent && window.finalBriefContent.trim()) {
        setTimeout(openOutputModal, 350);
    }
})();
</script>
