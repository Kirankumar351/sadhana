<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Plain text out of whatever a government site actually serves.
 *
 * Half the notifications that matter never exist as a web page. TGPSC, APPSC and RRB
 * publish the notification itself as a PDF, and the dates, vacancies and age limits live
 * only inside it. A scraper that reads HTML alone sees a title and nothing a student can
 * act on.
 *
 * Encoding is normalised first because these sites are not consistent about it: TGPSC
 * serves ISO-8859-1, and one invalid byte makes every multibyte regex downstream fail
 * silently by returning null.
 */
final class DocumentText
{
    /** The facts sit in the first few pages; the rest is annexures and application forms. */
    private const PDF_PAGES = 6;

    private const MAX_CHARS = 20000;

    /** Beyond this a "notification" is a scanned image with no text layer to read anyway. */
    private const MAX_PDF_BYTES = 25 * 1024 * 1024;

    public function from(string $body, ?string $contentType, string $url): string
    {
        return $this->isPdf($body, $contentType, $url)
            ? $this->fromPdf($body)
            : $this->fromHtml($body);
    }

    /**
     * Decided by the bytes, never by the address or the header.
     *
     * Government servers routinely answer a .pdf URL with an HTML error page, and label
     * both with whatever content type they like. Trusting either sends an error page to the
     * PDF parser, which throws, and every fact on the page is silently discarded. A real PDF
     * must carry its `%PDF-` header within the first 1024 bytes, so that is the only test.
     */
    public function isPdf(string $body, ?string $contentType = null, string $url = ''): bool
    {
        return str_contains(substr($body, 0, 1024), '%PDF-');
    }

    /**
     * Text from the first pages of a PDF.
     *
     * Poppler's pdftotext first, where the server has it: it reads only the pages it is
     * asked for, and a 70-page RRB notification takes about 130 ms. The PHP parser is the
     * fallback, and it loads the whole document before it can return a single page. On the
     * first live pull one notification exhausted a 512 MB memory limit inside it, and the
     * fatal error took every board queued after it down too.
     */
    public function fromPdf(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_PDF_BYTES) {
            return '';
        }

        return $this->tidy($this->viaPdftotext($bytes) ?? $this->viaParser($bytes));
    }

    public function fromHtml(string $html): string
    {
        $html = $this->utf8($html);
        $html = preg_replace('/<(script|style|nav|footer)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        // Content inside comments is invisible in a browser. RRB keeps years of withdrawn
        // rows commented out, and reading them would resurrect notices nobody can apply to.
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;

        return $this->tidy(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function utf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8')
            ? $text
            : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * `-layout` keeps each table row on one line. Without it pdftotext emits a table column
     * by column, so TGPSC's "Last Date & Time of submission of Online Application" and its
     * date end up paragraphs apart and the closing date is never found.
     */
    private function viaPdftotext(string $bytes): ?string
    {
        $binary = (string) config('services.pdftotext.path');
        $file = $binary === '' ? false : tempnam(sys_get_temp_dir(), 'notification');

        if ($file === false) {
            return null;
        }

        try {
            file_put_contents($file, $bytes);

            $result = Process::timeout(60)->run([
                $binary, '-f', '1', '-l', (string) self::PDF_PAGES, '-layout', '-enc', 'UTF-8', $file, '-',
            ]);

            return $result->successful() && trim($result->output()) !== '' ? $result->output() : null;
        } catch (Throwable) {
            // Not installed, not executable, or too slow. The PHP parser still gets its turn.
            return null;
        } finally {
            @unlink($file);
        }
    }

    private function viaParser(string $bytes): string
    {
        $config = new Config;

        // Images are most of a notification's bytes and none of its text.
        $config->setRetainImageContent(false);

        // Caps the memory any one compressed stream may expand into, so an oversized object
        // fails on its own instead of taking the process with it.
        $config->setDecodeMemoryLimit(32 * 1024 * 1024);

        try {
            $pages = (new Parser([], $config))->parseContent($bytes)->getPages();
        } catch (Throwable $e) {
            // Scanned and malformed PDFs are common, and not a reason to fail the run. The
            // draft still lands with its link, and a person reads the original.
            report($e);

            return '';
        }

        $text = '';

        foreach (array_slice($pages, 0, self::PDF_PAGES) as $page) {
            try {
                $text .= $page->getText()."\n";
            } catch (Throwable) {
                continue;
            }
        }

        return $text;
    }

    private function tidy(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $this->utf8($text)) ?? $text;

        return Str::limit(trim($text), self::MAX_CHARS, '');
    }
}
