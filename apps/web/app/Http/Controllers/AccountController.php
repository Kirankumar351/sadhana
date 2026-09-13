<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;

class AccountController extends Controller
{
    public function settings(): View
    {
        return view('account.settings', [
            'seo' => SeoBuilder::forRoute(
                'account.settings',
                __('Settings'),
                __('Your name, your language, and which notifications you want.'),
            ),
        ]);
    }
}
