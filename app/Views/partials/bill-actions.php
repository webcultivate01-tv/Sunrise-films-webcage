<?php

/**
 * The action bar that sits above a printable document (a payment receipt or a
 * project invoice) and is hidden when the page is printed.
 *
 * "Send on WhatsApp" downloads the PDF and opens the photographer's chat in
 * one go. WhatsApp's click-to-chat link cannot carry the file itself, so the
 * download starts first (in a hidden iframe, which leaves this page where it
 * is) and the admin attaches it in the chat that opens. The link is built from
 * the photographer's number in international form, so the chat opens whether or
 * not that number is saved on the admin's phone - and when the number on record
 * cannot be read as a dialable one, the button is left out entirely rather than
 * opening WhatsApp on nothing.
 *
 * @var string  $backUrl
 * @var string  $downloadUrl
 * @var ?string $whatsappUrl   Null when the photographer has no usable number.
 * @var ?string $whatsappName  Who the chat will open with.
 */
$backLabel = $backLabel ?? 'Back';
?>
<div class="mb-6 print:hidden">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="<?= e($backUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; <?= e($backLabel) ?></a>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Print
            </button>

            <?php if ($whatsappUrl !== null): ?>
                <a id="bill-whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener"
                   data-download-url="<?= e($downloadUrl) ?>"
                   class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#25D366]/40 focus:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.885-9.885 9.885M20.52 3.449A11.8 11.8 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.463-8.412"/>
                    </svg>
                    Download &amp; Send on WhatsApp
                </a>
            <?php endif; ?>

            <a href="<?= e($downloadUrl) ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>
                </svg>
                Download PDF
            </a>
        </div>
    </div>

    <?php if ($whatsappUrl !== null): ?>
        <p class="mt-2.5 text-right text-xs text-slate-500">
            The PDF downloads first, then the chat with
            <span class="font-medium text-slate-600"><?= e($whatsappName ?? 'the photographer') ?></span>
            opens - attach the downloaded file there. The number does not need to be saved on your phone.
        </p>
    <?php else: ?>
        <p class="mt-2.5 text-right text-xs text-slate-500">
            No usable WhatsApp number on record for this photographer, so the bill cannot be sent from here.
        </p>
    <?php endif; ?>
</div>

<?php if ($whatsappUrl !== null): ?>
<script>
    /**
     * Start the PDF download, then let the browser follow the link to
     * WhatsApp as normal. The download runs in a hidden iframe so this page
     * is never navigated away from, and the link is a real anchor with a real
     * href - so if this script never runs, the WhatsApp chat still opens and
     * the "Download PDF" button is still right there.
     */
    (function () {
        var link = document.getElementById('bill-whatsapp');

        if (!link) {
            return;
        }

        link.addEventListener('click', function () {
            var frame = document.createElement('iframe');

            frame.style.display = 'none';
            frame.src = link.getAttribute('data-download-url');
            document.body.appendChild(frame);
        });
    })();
</script>
<?php endif; ?>
