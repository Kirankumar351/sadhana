<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The admin portal.
 *
 * "Everything here is logged." Every notification published from this panel reaches lakhs
 * of people, and a wrong date costs someone a year of their life. That is why the panel
 * runs on its own path behind mandatory authentication, why every user-data read is
 * audited, and why the publish action carries an explicit verification checklist rather
 * than a single confident button.
 *
 * Navigation groups mirror the corrected sidebar from the Integration Map. The AI group is
 * separate from Content deliberately: the permission boundary is different. A content
 * editor may draft a notification but must not be able to change a prompt or read the cost
 * dashboard, and grouping the pipelines makes that one rule instead of five.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            // Brand colours from the UI Specification, so the admin portal and the
            // student portal are visibly the same product.
            ->colors([
                'primary' => Color::hex('#0F6B4F'),
                'warning' => Color::hex('#E8A33D'),
                'danger' => Color::hex('#C4362B'),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->pages([Pages\Dashboard::class])

            ->navigationGroups([
                NavigationGroup::make('Content'),
                NavigationGroup::make('Quiz'),
                NavigationGroup::make('AI'),
                NavigationGroup::make('Community'),
                NavigationGroup::make('Localisation'),
                NavigationGroup::make('Money'),
                NavigationGroup::make('People'),
                NavigationGroup::make('System'),
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            // Session length matters here: an unattended admin session is a route to
            // someone publishing under another person's name, and the audit log would
            // record the wrong author.
            ->sidebarCollapsibleOnDesktop();
    }
}
