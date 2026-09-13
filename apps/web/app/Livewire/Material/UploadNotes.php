<?php

declare(strict_types=1);

namespace App\Livewire\Material;

use App\Models\Material;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Uploading notes.
 *
 * THE COPYRIGHT WARRANTY IS THE POINT OF THIS SCREEN. Everything else is a form.
 *
 * The checkbox is mandatory, its wording is specific rather than a generic terms link, and
 * the acceptance is recorded with a timestamp and the uploader's IP. That record is what
 * turns "we asked them not to" into something defensible if a publisher ever writes to us.
 *
 * Nothing published here goes live without a human looking at it. At 500 uploads a day
 * that is roughly two hours of one person's time, and it is the cheapest legal insurance
 * available to this business.
 */
class UploadNotes extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public ?int $exam_id = null;

    public string $subject = '';

    public string $locale = 'te';

    public $file = null;

    public bool $copyright_confirmed = false;

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:6', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'exam_id' => ['required', 'exists:exams,id'],
            'subject' => ['nullable', 'string', 'max:80'],
            'locale' => ['required', 'in:te,en'],
            // 25 MB. A scanned notebook runs large, and rejecting a genuine contribution
            // over file size would lose exactly the content we want.
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:25600'],
            'copyright_confirmed' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'copyright_confirmed.accepted' => __('You must confirm this is your own work. We cannot publish anything else.'),
            'file.max' => __('Files must be under 25 MB.'),
            'file.mimes' => __('Upload a PDF or a photo.'),
        ];
    }

    public function submit(): void
    {
        $validated = $this->validate();

        Material::create([
            'exam_id' => $validated['exam_id'],
            'uploaded_by' => auth()->id(),
            'slug' => $this->uniqueSlug($validated['title']),
            'title' => [$validated['locale'] => $validated['title']],
            'description' => $validated['description'] ? [$validated['locale'] => $validated['description']] : null,
            'subject' => $validated['subject'] ?: null,
            'locale' => $validated['locale'],

            /**
             * Uploaded to a quarantine prefix, NOT the public one.
             *
             * Nothing a user uploaded is reachable until a person has approved it. An
             * unmoderated public prefix is how a pirated textbook ends up served from our
             * own domain before anyone notices.
             */
            'file_path' => $this->file->store('quarantine/materials', config('filesystems.default')),
            'file_size_kb' => (int) round($this->file->getSize() / 1024),
            'file_type' => $this->file->getClientOriginalExtension() === 'pdf' ? 'pdf' : 'image',

            'source_type' => 'user_notes',

            // The warranty, recorded. This is the evidence, not the checkbox.
            'copyright_confirmed' => true,
            'copyright_ip' => request()->ip(),
            'copyright_confirmed_at' => now(),

            'status' => 'pending_review',
        ]);

        $this->submitted = true;
        $this->reset(['title', 'description', 'subject', 'file', 'copyright_confirmed']);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 150, ''));
        $slug = $base;
        $i = 2;

        while (Material::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.material.upload-notes');
    }
}
