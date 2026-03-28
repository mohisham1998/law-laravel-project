@props(['case'])
@php
    $status = $case->status->value ?? $case->status;
    $enabled = in_array($status, ['phase2_completed', 'phase3_completed', 'completed_with_warnings'], true);
    $pdfUrl = route('cases.pdf', $case);
@endphp
<div id="pdfExportBtnContainer" data-pdf-url="{{ $pdfUrl }}">
    @if($enabled)
        <button type="button"
                onclick="handlePdfExport(this, '{{ $pdfUrl }}')"
                class="w-full flex items-center gap-3 p-3 bg-primary/10 rounded-xl hover:bg-primary/20 transition-colors text-primary font-semibold"
                id="pdfExportBtn">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            <span class="text-sm">تصدير PDF</span>
        </button>
    @else
        <span class="w-full flex items-center gap-3 p-3 bg-slate-100 rounded-xl cursor-not-allowed text-slate-400"
              title="يتوفر تصدير PDF بعد اكتمال المعالجة">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            <span class="text-sm">تصدير PDF (غير متاح)</span>
        </span>
    @endif
</div>

<script>
async function handlePdfExport(btn, url) {
    if (btn.disabled) return;

    var originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('opacity-60');
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span><span class="text-sm">جارٍ التحضير...</span>';

    try {
        var response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/pdf,text/plain;q=0.9,*/*;q=0.8',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        var contentType = (response.headers.get('Content-Type') || '').toLowerCase();
        if (!response.ok || !contentType.includes('application/pdf')) {
            var errorText = await response.text();
            alert(errorText || 'تعذر إنشاء ملف PDF. حاول مرة أخرى.');
            return;
        }

        var blob = await response.blob();
        var objectUrl = window.URL.createObjectURL(blob);

        var filename = 'legal-brief-{{ now()->format("Y-m-d") }}.pdf';
        var disposition = response.headers.get('Content-Disposition') || '';
        var utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
        var asciiMatch = disposition.match(/filename="?([^";]+)"?/i);
        if (utf8Match && utf8Match[1]) {
            filename = decodeURIComponent(utf8Match[1]);
        } else if (asciiMatch && asciiMatch[1]) {
            filename = asciiMatch[1];
        }

        var a = document.createElement('a');
        a.href = objectUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(objectUrl);
    } catch (err) {
        alert('تعذر إنشاء ملف PDF. تحقق من البيانات وحاول مرة أخرى.');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-60');
        btn.innerHTML = originalHTML;
    }
}
</script>
