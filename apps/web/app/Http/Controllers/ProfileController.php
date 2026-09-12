<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Profile — the input to the eligibility engine.
 *
 * DATA MINIMISATION IS A LEGAL REQUIREMENT, not a preference. The DPDP Act 2023 limits
 * collection to what the stated purpose needs, and the stated purpose here is eligibility
 * matching. That is why there is no Aadhaar field, no caste certificate upload, and no
 * address beyond district: none of them are needed to answer "can I apply for this?"
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'seo' => SeoBuilder::forRoute('profile.edit', __('Your details'), __('Used only to check which jobs you qualify for.')),
            'profile' => $request->user()->profile ?? new Profile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'category' => ['nullable', Rule::in(['general', 'obc', 'sc', 'st', 'ews'])],
            'is_pwd' => ['boolean'],
            'is_ex_serviceman' => ['boolean'],
            'highest_qualification' => ['nullable', Rule::in([
                '10th', '12th', 'iti', 'diploma', 'degree', 'pg', 'btech', 'mbbs', 'phd',
            ])],
            'qualification_stream' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'district' => ['nullable', 'string', 'max:60'],
        ]);

        $validated['is_pwd'] = $request->boolean('is_pwd');
        $validated['is_ex_serviceman'] = $request->boolean('is_ex_serviceman');

        $request->user()->profile()->updateOrCreate([], $validated);

        // A changed qualification changes every eligibility result on the feed, so the
        // cached answers have to go with it.
        cache()->forget("eligibility:{$request->user()->id}");

        return redirect()
            ->route('profile.edit')
            ->with('status', __('Saved. We will now show which jobs you qualify for.'));
    }
}
