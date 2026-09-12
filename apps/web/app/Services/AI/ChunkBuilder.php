<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiChunk;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamNotification;
use App\Models\Material;
use App\Models\NewsItem;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns an owner row into retrieval chunks.
 *
 * THE CORPUS IS DERIVED, NEVER AUTHORED. Nobody edits a chunk. Every chunk is rebuilt from
 * the table that owns the fact, which means a correction made once in the admin panel
 * reaches every AI answer within a minute — and it means nobody can "fix" an AI answer by
 * editing something the rest of the product cannot see.
 *
 * Chunks are written per locale. A Telugu question retrieves Telugu chunks first, and
 * mixing languages inside one chunk would make both embeddings worse than either alone.
 *
 * The checksum exists to avoid re-embedding text that has not changed. Embedding is the
 * expensive part of a reindex, and most saves touch a field the corpus does not care about.
 */
final class ChunkBuilder
{
    /** Roughly 300 words. Small enough to be precise, large enough to carry context. */
    private const MAX_CHARS = 1800;

    /**
     * Rebuild every chunk for one record, in every active locale.
     *
     * Existing chunks are replaced rather than appended, and chunks beyond the new count
     * are deleted — otherwise shortening a syllabus would leave the old tail in the corpus
     * and the assistant would keep quoting a section that no longer exists.
     *
     * @return list<int> the chunk ids that now need embedding
     */
    public function rebuild(Model $record): array
    {
        $sourceType = $this->sourceTypeFor($record);

        if ($sourceType === null) {
            return [];
        }

        $stale = [];

        foreach (Locale::active() as $locale) {
            $texts = $this->textsFor($record, $locale);

            foreach ($texts as $index => $text) {
                $checksum = hash('sha256', $text);

                $chunk = AiChunk::updateOrCreate(
                    [
                        'source_type' => $sourceType,
                        'source_id' => $record->getKey(),
                        'source_locale' => $locale,
                        'chunk_index' => $index,
                    ],
                    [
                        'content' => $text,
                        'token_count' => (int) ceil(mb_strlen($text) / 4),
                        'checksum' => $checksum,
                        'metadata' => $this->metadataFor($record, $sourceType, $locale),
                    ],
                );

                // Only mark stale when the text actually changed. Re-embedding unchanged
                // text is the single easiest way to waste the AI budget.
                if ($chunk->wasRecentlyCreated || $chunk->getOriginal('checksum') !== $checksum) {
                    $chunk->update(['is_stale' => true]);
                    $stale[] = $chunk->id;
                }
            }

            AiChunk::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $record->getKey())
                ->where('source_locale', $locale)
                ->where('chunk_index', '>=', count($texts))
                ->delete();
        }

        return $stale;
    }

    public function sourceTypeFor(Model $record): ?string
    {
        return match ($record::class) {
            ExamNotification::class => 'notification',
            Exam::class => 'exam',
            Material::class => 'material',
            NewsItem::class => 'news_item',
            Answer::class => 'answer',
            default => null,
        };
    }

    /**
     * The text that should be retrievable, per record type.
     *
     * Note what is NOT included for a notification: the eligibility criteria are
     * deliberately summarised as plain facts rather than left implicit, because the
     * assistant must be able to quote "the age limit is 18-30 as on 1 July 2026" verbatim
     * — but it must never compute an outcome from it. The intent router sends "am I
     * eligible" to the deterministic engine before retrieval is ever reached.
     *
     * @return list<string>
     */
    private function textsFor(Model $record, string $locale): array
    {
        $parts = match (true) {
            $record instanceof ExamNotification => $this->notificationParts($record, $locale),
            $record instanceof Exam => $this->examParts($record, $locale),
            $record instanceof Material => array_filter([
                (string) $record->getTranslation('title', $locale, true),
                strip_tags((string) $record->html_content),
            ]),
            $record instanceof NewsItem => array_filter([
                (string) $record->getTranslation('title', $locale, true),
                (string) $record->getTranslation('summary', $locale, true),
                (string) $record->getTranslation('why_it_matters', $locale, true),
            ]),
            // Only a best answer joins the corpus. An unvetted answer is a stranger's
            // opinion, and the whole point of grounding is that the source is trustworthy.
            $record instanceof Answer => $record->is_best && ! $record->is_ai
                ? [strip_tags($record->body)]
                : [],
            default => [],
        };

        $joined = trim(implode("\n\n", array_filter($parts)));

        return $joined === '' ? [] : $this->split($joined);
    }

    /**
     * @return list<string>
     */
    private function notificationParts(ExamNotification $n, string $locale): array
    {
        $facts = array_filter([
            $n->total_vacancies ? "Vacancies: {$n->total_vacancies}" : null,
            $n->min_qualification ? "Minimum qualification: {$n->min_qualification}" : null,
            $n->min_age ? "Age limit: {$n->min_age} to {$n->max_age}" : null,
            $n->age_reference_date ? 'Age calculated as on: '.$n->age_reference_date->format('d M Y') : null,
            $n->apply_end_date ? 'Last date to apply: '.$n->apply_end_date->format('d M Y') : null,
            $n->allowed_states ? 'Only for: '.implode(', ', $n->allowed_states) : null,
        ]);

        return [
            (string) $n->getTranslation('title', $locale, true),
            $n->organisation,
            strip_tags((string) $n->getTranslation('description', $locale, true)),
            implode("\n", $facts),
        ];
    }

    /**
     * @return list<string>
     */
    private function examParts(Exam $exam, string $locale): array
    {
        $parts = [
            (string) $exam->getTranslation('name', $locale, true),
            $exam->conducting_body,
            strip_tags((string) $exam->getTranslation('description', $locale, true)),
        ];

        foreach ($exam->syllabusSections($locale) as $section) {
            $parts[] = ($section['title'] ?? '')."\n".implode("\n", $section['topics'] ?? []);
        }

        // Cutoff history, so the assistant can show real numbers when it refuses to
        // predict one. Refusing is only useful if something better is offered instead.
        foreach ($exam->cutoffs as $cutoff) {
            $parts[] = "Cutoff {$cutoff->year} {$cutoff->category}: {$cutoff->cutoff_marks} of {$cutoff->total_marks}";
        }

        return $parts;
    }

    /**
     * Split on paragraph boundaries, never mid-sentence.
     *
     * A chunk that begins halfway through a sentence embeds badly and reads worse when it
     * is shown to a student as a source card.
     *
     * @return list<string>
     */
    private function split(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split('/\n\s*\n/u', $text) ?: [$text] as $paragraph) {
            if (mb_strlen($current) + mb_strlen($paragraph) > self::MAX_CHARS && $current !== '') {
                $chunks[] = trim($current);
                $current = '';
            }

            $current .= $paragraph."\n\n";
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Qdrant payload — the fields a scoped search filters on.
     *
     * @return array<string, mixed>
     */
    private function metadataFor(Model $record, string $sourceType, string $locale): array
    {
        $meta = ['source_type' => $sourceType, 'source_id' => $record->getKey(), 'locale' => $locale];

        if ($record instanceof ExamNotification) {
            $meta['exam_id'] = $record->exam_id;
            $meta['title'] = (string) $record->getTranslation('title', $locale, true);
            $meta['url'] = route('notifications.show', ['locale' => $locale, 'slug' => $record->slug]);
        }

        if ($record instanceof Exam) {
            $meta['exam_id'] = $record->id;
            $meta['title'] = (string) $record->getTranslation('name', $locale, true);
            $meta['url'] = route('exams.show', ['locale' => $locale, 'slug' => $record->slug]);
        }

        return $meta;
    }
}
