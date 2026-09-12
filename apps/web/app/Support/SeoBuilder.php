<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Exam;
use App\Models\ExamNotification;
use Illuminate\Support\Str;

/**
 * Builds the SEO head for every public page.
 *
 * This class is the acquisition strategy expressed as code. Roughly 60% of Year 1 traffic
 * is expected to arrive from Google on Telugu exam queries, so the tags below are not
 * housekeeping — they are the funnel.
 *
 * Three rules that must not drift:
 *   - the canonical is always the current locale's URL, never a cross-locale one
 *   - hreflang lists every active locale plus x-default, and points at identical slugs
 *   - structured data is emitted per page type, because JobPosting is what gets a
 *     notification into Google Jobs, which competitors in this space largely ignore
 */
final class SeoBuilder
{
    /**
     * @return array{title: string, description: string, canonical: string, alternates: array<string, string>, type: string}
     */
    public static function forExam(Exam $exam): array
    {
        $year = date('Y');
        $name = (string) $exam->name;

        return [
            'title' => (string) ($exam->meta_title ?: "{$name} {$year}"),
            'description' => (string) ($exam->meta_description
                ?: Str::limit(strip_tags((string) $exam->description), 155)),
            'canonical' => route('exams.show', ['locale' => app()->getLocale(), 'slug' => $exam->slug]),
            'alternates' => self::alternates('exams.show', ['slug' => $exam->slug]),
            'type' => 'article',
        ];
    }

    /**
     * @return array{title: string, description: string, canonical: string, alternates: array<string, string>, type: string}
     */
    public static function forNotification(ExamNotification $n): array
    {
        return [
            'title' => (string) $n->title,
            'description' => Str::limit(strip_tags((string) $n->description), 155),
            'canonical' => route('notifications.show', ['locale' => app()->getLocale(), 'slug' => $n->slug]),
            'alternates' => self::alternates('notifications.show', ['slug' => $n->slug]),
            'type' => 'article',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{title: string, description: string, canonical: string, alternates: array<string, string>, type: string}
     */
    public static function forRoute(string $route, string $title, string $description, array $params = []): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => route($route, array_merge($params, ['locale' => app()->getLocale()])),
            'alternates' => self::alternates($route, $params),
            'type' => 'website',
        ];
    }

    /**
     * One URL per active locale, on the SAME slug.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private static function alternates(string $route, array $params = []): array
    {
        $out = [];

        foreach (Locale::active() as $code) {
            $out[$code] = route($route, array_merge($params, ['locale' => $code]));
        }

        return $out;
    }

    /**
     * schema.org JobPosting.
     *
     * This is what puts a notification into Google Jobs — a significant free traffic source
     * that the incumbents in this market largely ignore. Google requires validThrough; a
     * posting without it is dropped from the index once it looks stale.
     *
     * @return array<string, mixed>
     */
    public static function jobPostingSchema(ExamNotification $n): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => (string) $n->title,
            'description' => strip_tags((string) $n->description),
            'datePosted' => $n->published_at?->toIso8601String(),
            'validThrough' => $n->apply_end_date?->toIso8601String(),
            'employmentType' => 'FULL_TIME',
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => $n->organisation,
            ],
            'jobLocation' => [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressRegion' => $n->allowed_states[0] ?? 'TS',
                    'addressCountry' => 'IN',
                ],
            ],
            'inLanguage' => app()->getLocale(),
        ];

        if ($n->total_vacancies !== null) {
            $schema['totalJobOpenings'] = $n->total_vacancies;
        }

        // Only emit baseSalary when we actually hold a figure. An invented or zeroed
        // salary is worse than none — Google penalises structured data that contradicts
        // the visible page.
        if ($n->salary_min !== null) {
            $schema['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => 'INR',
                'value' => array_filter([
                    '@type' => 'QuantitativeValue',
                    'minValue' => $n->salary_min,
                    'maxValue' => $n->salary_max,
                    'unitText' => 'MONTH',
                ]),
            ];
        }

        return $schema;
    }

    /**
     * schema.org Course, for an exam hub page.
     *
     * @return array<string, mixed>
     */
    public static function courseSchema(Exam $exam): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => (string) $exam->name,
            'description' => Str::limit(strip_tags((string) $exam->description), 300),
            'provider' => [
                '@type' => 'Organization',
                'name' => $exam->conducting_body,
            ],
            'inLanguage' => app()->getLocale(),
        ];
    }

    /**
     * schema.org FAQPage — this is what wins rich results on exam queries.
     *
     * @param  array<int, array{q: string, a: string}>  $faq
     * @return array<string, mixed>|null
     */
    public static function faqSchema(array $faq): ?array
    {
        if ($faq === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ], $faq),
        ];
    }
}
