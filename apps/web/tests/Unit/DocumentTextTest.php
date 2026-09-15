<?php

declare(strict_types=1);

use App\Services\Ingestion\DocumentText;
use Illuminate\Support\Facades\Process;

/**
 * Getting text out of what government sites serve.
 *
 * The failures here are silent by nature: a misdetected PDF, or a table read column by
 * column, produces a draft with no closing date — which then sits in the review queue
 * looking open when the application window shut weeks ago.
 */
it('decides a document is a PDF by its header bytes, not its address', function (): void {
    $documents = new DocumentText;

    // An HTML error page served at a .pdf address, labelled application/pdf.
    expect($documents->isPdf('<html><body>404 Not Found</body></html>', 'application/pdf', 'https://example.gov.in/n.pdf'))->toBeFalse()
        // A real PDF served with a generic type from an address with no extension.
        ->and($documents->isPdf("%PDF-1.7\n%\xE2\xE3\xCF\xD3", 'application/octet-stream', 'https://example.gov.in/preview/abc'))->toBeTrue();
});

it('reads PDFs with pdftotext in layout mode, first pages only', function (): void {
    config(['services.pdftotext.path' => 'pdftotext']);

    Process::fake(['*' => Process::result(output: 'Last Date & Time of submission of   22/08/2026 at 5:00 PM')]);

    $text = (new DocumentText)->fromPdf("%PDF-1.7\nnot a real document");

    expect($text)->toBe('Last Date & Time of submission of 22/08/2026 at 5:00 PM');

    // Layout mode keeps a table row on one line; without it the label and its date separate.
    Process::assertRan(fn ($process): bool => in_array('-layout', (array) $process->command, true)
        && in_array('-l', (array) $process->command, true));
});

it('strips content a browser would never show', function (): void {
    $text = (new DocumentText)->fromHtml('<nav>Menu</nav><p>Open notice</p><!-- <p>Withdrawn notice</p> --><script>track()</script>');

    expect($text)->toBe('Open notice');
});

it('refuses to parse a PDF too large to be anything but a scan', function (): void {
    Process::fake();

    expect((new DocumentText)->fromPdf('%PDF-'.str_repeat('x', 26 * 1024 * 1024)))->toBe('');

    Process::assertNothingRan();
});
