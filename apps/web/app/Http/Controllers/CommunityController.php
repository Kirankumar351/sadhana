<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Post;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The doubt community.
 *
 * Every answered doubt is a permanent asset: it helps the person who asked, it answers the
 * next thousand people who search the same thing, and it adds unique long-tail indexable
 * text that an exam page cannot generate on its own. Vol 1 puts the replication cost of
 * this archive at two to three years, and only with our traffic.
 *
 * SEQUENCING RULE (Decision D8): this does not open until 5,000+ DAU, seeded with 50 real
 * questions answered by real people. An empty forum signals a dead product and does more
 * damage to a first impression than having no forum at all.
 */
class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();

        $posts = Post::query()
            ->where('status', 'published')
            ->with(['user:id,name,reputation,is_verified_selected', 'exam:id,slug,name'])
            ->when($request->filled('exam'), fn ($q) => $q->whereHas('exam', fn ($q) => $q->where('slug', $request->string('exam'))))
            // Unanswered first is the default on purpose. A question nobody answered is
            // the one failing the person who asked it, and it is also the cheapest place
            // for a knowledgeable user to add value.
            ->when($filter === 'unanswered', fn ($q) => $q->where('answer_count', 0))
            ->when($filter === 'top', fn ($q) => $q->orderByDesc('upvotes'))
            ->when($filter !== 'top', fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString();

        return view('community.index', [
            'seo' => SeoBuilder::forRoute(
                'community.index',
                __('Doubts and answers'),
                __('Ask a doubt in Telugu and get an answer from people who have actually written the exam.'),
            ),
            'posts' => $posts,
            'filter' => $filter,
            'exams' => Exam::query()->active()->orderBy('short_name')->get(['id', 'slug', 'name', 'short_name']),
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'user:id,name,reputation,is_verified_selected',
                'exam:id,slug,name',
                // AI answers always sort below human ones, regardless of votes. A verified
                // selected candidate's answer must never sit under a machine's.
                'answers' => fn ($q) => $q->where('status', 'published')
                    ->with('user:id,name,reputation,is_verified_selected')
                    ->orderBy('is_ai')
                    ->orderByDesc('is_best')
                    ->orderByDesc('upvotes'),
            ])
            ->firstOrFail();

        dispatch(fn () => $post->incrementQuietly('view_count'))->afterResponse();

        return view('community.show', [
            'post' => $post,
            'seo' => SeoBuilder::forRoute(
                'community.show',
                $post->title,
                Str::limit(strip_tags($post->body), 155),
                ['slug' => $post->slug],
            ),
        ]);
    }

    public function create(): View
    {
        return view('community.create', [
            'seo' => SeoBuilder::forRoute('community.create', __('Ask a doubt'), __('Ask a doubt in Telugu.')),
            'exams' => Exam::query()->active()->orderBy('short_name')->get(['id', 'slug', 'name', 'short_name']),
        ]);
    }

    /**
     * Serve an attachment on a doubt.
     *
     * Through the application, never as a direct object URL, for the same reason material
     * downloads are: a taken-down file must stop being reachable the moment the post is
     * unpublished. A permanently public prefix keeps serving the file from our own domain
     * long after the row is gone, and these attachments are photographs of textbook pages
     * as often as they are anything else.
     *
     * Only two kinds exist, and the type is matched rather than taken from the URL — a path
     * segment a visitor controls must never decide which directory we read from.
     */
    public function attachment(string $locale, string $slug, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['image', 'audio'], true), 404);

        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $path = $type === 'image' ? $post->image_path : $post->audio_path;

        abort_if(blank($path), 404);

        $disk = Storage::disk(config('filesystems.default'));

        // The row can outlive the file — a purge, a failed upload, a restore from backup.
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            // Cacheable, because the file never changes while the post is up, but private
            // so a CDN edge does not keep serving it after a takedown.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
