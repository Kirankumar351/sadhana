<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Material;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The material library.
 *
 * COPYRIGHT IS THE EXISTENTIAL RISK IN THIS MODULE. Every Telugu exam-prep Telegram channel
 * distributes scanned coaching books; it is normal, it is widespread, and it is illegal.
 * One publisher notice ends this company. Decision D6, and it does not bend.
 *
 * We host only: government-published documents, content we wrote, and user notes the
 * uploader personally authored and warranted. Nothing else, at any traffic cost.
 */
class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        $materials = Material::query()
            ->where('status', 'published')
            ->with('exam:id,slug,name')
            ->when($request->filled('exam'), fn ($q) => $q->whereHas('exam', fn ($q) => $q->where('slug', $request->string('exam'))))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject', $request->string('subject')))
            ->orderByDesc('download_count')
            ->paginate(20)
            ->withQueryString();

        return view('material.index', [
            'seo' => SeoBuilder::forRoute(
                'material.index',
                __('Free study material'),
                __('Syllabus PDFs, previous papers and student notes for every major government exam, free to download.'),
            ),
            'materials' => $materials,
            'exams' => Exam::query()->active()->orderBy('short_name')->get(['id', 'slug', 'name', 'short_name']),
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $material = Material::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('exam:id,slug,name', 'editor:id,name')
            ->firstOrFail();

        return view('material.show', [
            'material' => $material,
            'seo' => SeoBuilder::forRoute(
                'material.show',
                (string) $material->title,
                Str::limit(strip_tags((string) $material->description), 155),
                ['slug' => $material->slug],
            ),
        ]);
    }

    /**
     * Downloads are served through the application, never as a direct object URL.
     *
     * Three reasons, in order of importance: a taken-down file must stop being reachable
     * immediately, download counts are how contributors get credited, and an unlisted R2
     * URL that leaks is public forever.
     */
    public function download(string $locale, string $slug): RedirectResponse
    {
        $material = Material::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        abort_if($material->file_path === null, 404);

        $material->incrementQuietly('download_count');

        // A short-lived signed URL. Long enough to download a 25 MB PDF on 3G, short
        // enough that a shared link stops working.
        return redirect()->away(
            Storage::disk(config('filesystems.default'))->temporaryUrl($material->file_path, now()->addMinutes(15))
        );
    }

    public function create(): View
    {
        return view('material.create', [
            'seo' => SeoBuilder::forRoute('material.create', __('Share your notes'), __('Upload notes you wrote yourself.')),
            'exams' => Exam::query()->active()->orderBy('short_name')->get(['id', 'slug', 'name', 'short_name']),
        ]);
    }
}
