<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamNotification;
use App\Support\Locale;
use Illuminate\Console\Command;

/**
 * Generate one sitemap per locale per content type, behind an index.
 *
 * Split by locale on purpose: each one is submitted separately in Search Console, which is
 * the only practical way to see whether Telugu is being indexed at the rate English is.
 * A single combined sitemap hides exactly the problem we most need to watch.
 *
 * Expired notifications stay in the sitemap. They still rank, they still answer "did I
 * miss it?", and dropping a URL that Google has indexed for months is a needless loss of
 * accumulated position.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Build per-locale sitemaps and the sitemap index';

    public function handle(): int
    {
        $written = [];

        foreach (Locale::active() as $locale) {
            $written[] = $this->write("sitemap-exams-{$locale}.xml", $this->examUrls($locale));
            $written[] = $this->write("sitemap-notifications-{$locale}.xml", $this->notificationUrls($locale));
        }

        $this->writeIndex($written);

        $this->info('Wrote '.(count($written) + 1).' sitemap files to public/.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{loc: string, lastmod: string, priority: string}>
     */
    private function examUrls(string $locale): array
    {
        return Exam::query()->where('is_active', true)->get()
            ->map(fn (Exam $e): array => [
                'loc' => route('exams.show', ['locale' => $locale, 'slug' => $e->slug]),
                'lastmod' => $e->updated_at?->toAtomString() ?? now()->toAtomString(),
                // Exam hubs are the SEO engine and they are permanent. Highest priority.
                'priority' => '0.9',
            ])->all();
    }

    /**
     * @return list<array{loc: string, lastmod: string, priority: string}>
     */
    private function notificationUrls(string $locale): array
    {
        return ExamNotification::query()
            ->whereIn('status', ['published', 'expired'])
            ->get()
            ->map(fn (ExamNotification $n): array => [
                'loc' => route('notifications.show', ['locale' => $locale, 'slug' => $n->slug]),
                'lastmod' => $n->updated_at?->toAtomString() ?? now()->toAtomString(),
                'priority' => $n->status === 'published' ? '0.8' : '0.4',
            ])->all();
    }

    /**
     * @param  list<array{loc: string, lastmod: string, priority: string}>  $urls
     */
    private function write(string $filename, array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                .'    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'."\n"
                .'    <lastmod>'.$url['lastmod'].'</lastmod>'."\n"
                .'    <priority>'.$url['priority'].'</priority>'."\n"
                ."  </url>\n";
        }

        $xml .= '</urlset>';

        file_put_contents(public_path($filename), $xml);

        return $filename;
    }

    /**
     * @param  list<string>  $files
     */
    private function writeIndex(array $files): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($files as $file) {
            $xml .= "  <sitemap>\n"
                .'    <loc>'.url($file).'</loc>'."\n"
                .'    <lastmod>'.now()->toAtomString().'</lastmod>'."\n"
                ."  </sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        file_put_contents(public_path('sitemap.xml'), $xml);
    }
}
