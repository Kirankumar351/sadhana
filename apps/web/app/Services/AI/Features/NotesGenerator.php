<?php

declare(strict_types=1);

namespace App\Services\AI\Features;

use App\Models\AiChunk;
use App\Models\Exam;
use App\Models\GeneratedNote;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\AiResult;

/**
 * Notes generator — AI Layer feature 05.
 *
 * Any syllabus topic turned into structured notes with headings, key facts and a dates
 * table, in the language the student studies in.
 *
 * IT IS ASSEMBLED FROM OUR OWN CORPUS AND NOTHING ELSE — our structured syllabus, our past
 * papers, our approved material. Never a coaching book, never open-web scraping. That is
 * what keeps it legal and what keeps it accurate to the actual paper, and it is enforced
 * upstream: the gateway refuses to generate when retrieval returns nothing.
 *
 * THE HONEST FAILURE IS THE POINT. Generation quality follows corpus depth, so a topic with
 * thin indexed material produces thin notes. Rather than let the model pad them out into
 * something confident and hollow, a topic below the passage floor returns no_sources and the
 * screen says so — and the admin sees that topic on the weak-coverage list.
 */
final class NotesGenerator
{
    /** Below this many indexed passages the notes would be padding, not notes. */
    private const THIN_COVERAGE_PASSAGES = 4;

    public function __construct(private readonly AiGateway $gateway) {}

    /**
     * @param  'exam_focused'|'detailed'|'revision'  $depth
     * @param  'te'|'en'|'both'  $language
     */
    public function generate(
        User $user,
        string $topic,
        string $depth = 'exam_focused',
        string $language = 'te',
        ?Exam $exam = null,
        ?string $paper = null,
    ): AiResult {
        return $this->gateway->ask(
            question: $this->question($topic, $depth, $paper),
            user: $user,
            feature: 'notes',
            context: array_filter([
                'exam_id' => $exam?->id,
                'paper' => $paper,
                'topic' => $topic,
                'depth' => $depth,
            ]),
            // 'both' generates in Telugu and asks for the English alongside, because a
            // student who studies in Telugu often sits the paper in English.
            locale: $language === 'en' ? 'en' : 'te',
        );
    }

    /**
     * Keep the note.
     *
     * The body is snapshotted rather than referenced — see the migration. A saved note must
     * keep saying what it said when it was saved, whatever happens to the prompt or the
     * cache afterwards.
     */
    public function save(
        User $user,
        AiResult $result,
        string $topic,
        string $depth,
        string $language,
        ?Exam $exam = null,
        ?string $paper = null,
    ): GeneratedNote {
        return GeneratedNote::create([
            'user_id' => $user->id,
            'exam_id' => $exam?->id,
            'paper' => $paper,
            'topic' => $topic,
            'depth' => $depth,
            'locale' => $language,
            'title' => $this->titleFrom($result->text, $topic),
            'body' => $result->text,
            'sources' => array_values(array_unique(array_filter(array_map(
                static fn ($passage) => $passage->title,
                $result->sources,
            )))),
            'confidence' => $result->confidence,
            'is_saved' => true,
        ]);
    }

    /**
     * Whether the corpus can actually support notes on this topic.
     *
     * Surfaced to the student before they spend one of their monthly generations, and to
     * the admin as the weak-coverage list. Knowing a topic is thin is more useful than
     * receiving thin notes about it.
     */
    public function coverageIsThin(string $topic, ?Exam $exam = null, string $locale = 'te'): bool
    {
        return AiChunk::query()
            ->where('source_locale', $locale)
            // exam_id lives in the metadata json, where it is also used as a Qdrant filter.
            ->when($exam !== null, fn ($query) => $query->where('metadata->exam_id', $exam->id))
            ->where('content', 'like', '%'.$topic.'%')
            ->count() < self::THIN_COVERAGE_PASSAGES;
    }

    /**
     * The instruction, which is where the three depths actually differ.
     *
     * Depth is not a length slider. "Exam-focused" wants dates and names in a table;
     * "revision" wants one-liners that can be read in four minutes the night before. Asking
     * for the same notes and trimming them produces neither.
     */
    private function question(string $topic, string $depth, ?string $paper): string
    {
        $shape = match ($depth) {
            'detailed' => 'Write full notes with context and causes. Explain why each event '
                .'happened, not only that it did. Include a dates table and a short summary.',
            'revision' => 'Write revision one-liners only. One fact per line, no prose, no '
                .'introduction. This is read the night before the exam, not studied from.',
            default => 'Write exam-focused notes: headings, the key facts and dates as a table, '
                .'and a short summary. Prefer what has actually been asked in past papers.',
        };

        return trim(implode("\n", array_filter([
            $paper !== null ? "Paper: {$paper}" : null,
            "Topic: {$topic}",
            $shape,
            // The instruction that matters most, repeated here because the prompt is the
            // weakest layer and this is the one the guard cannot fully check.
            'Use only the supplied passages. If a date or figure is not in them, leave it out.',
        ])));
    }

    private function titleFrom(string $body, string $fallback): string
    {
        foreach (explode("\n", $body) as $line) {
            $line = trim(ltrim(trim($line), '#'));

            if ($line !== '') {
                return mb_substr($line, 0, 290);
            }
        }

        return mb_substr($fallback, 0, 290);
    }
}
