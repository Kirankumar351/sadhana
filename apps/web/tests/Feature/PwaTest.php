<?php

declare(strict_types=1);

/**
 * PWA and performance.
 *
 * The target device is a Rs 8,000 Android on a patchy 3G connection, often on a bus. These
 * tests protect the constraints that make the product usable on it — the ones that are
 * easy to break by accident and invisible on a developer's laptop.
 */
it('serves the manifest', function (): void {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/te')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['theme_color'])->toBe('#0F6B4F');
});

/**
 * The app opens in Telugu, not English.
 *
 * `start_url` is what an installed icon launches. Defaulting it to English would mean
 * every user who installs the app lands in the wrong language every single time, which is
 * a strange thing to get wrong in a Telugu-first product and very easy to do.
 */
it('opens in Telugu when installed', function (): void {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['lang'])->toBe('te')
        ->and($manifest['start_url'])->toStartWith('/te');
});

it('serves the offline page with no locale prefix', function (): void {
    $this->get('/offline')->assertSuccessful();
});

/**
 * The offline page must not depend on the stylesheet, because the stylesheet is exactly
 * what may not have arrived.
 */
it('renders the offline page without external css', function (): void {
    $html = $this->get('/offline')->getContent();

    expect($html)->toContain('<style>')
        ->and($html)->not->toContain('@vite');
});

describe('service worker', function (): void {
    beforeEach(function (): void {
        $this->sw = file_get_contents(public_path('sw.js'));
    });

    /**
     * A CACHED DEADLINE IS A LIE.
     *
     * Serving a stale last date from cache would do precisely the harm the whole product
     * exists to prevent. Notification pages must be network-first, with cache only as a
     * genuine-offline fallback.
     */
    it('never serves notifications cache-first', function (): void {
        expect($this->sw)->toContain('networkFirst');

        // The asset branch is the only cache-first path, and it is matched on file
        // extension — so no HTML route can fall into it.
        expect($this->sw)->toMatch('/woff2\?\|css\|js\|png\|jpg\|svg\|ico.*cacheFirst/s');
    });

    /**
     * Without a timeout, a 3G connection that has stalled rather than failed leaves the
     * user staring at a blank screen for thirty seconds.
     */
    it('times out a stalled network request', function (): void {
        expect($this->sw)->toContain('timeoutMs = 6000');
    });

    /**
     * A cached admin page could show one staff member another's session state, and a
     * cached OTP screen is worse.
     */
    it('refuses to cache admin and auth routes', function (): void {
        expect($this->sw)->toContain('admin|livewire')
            ->and($this->sw)->toContain('sign-in|verify|sign-out');
    });

    it('only caches GET requests from our own origin', function (): void {
        expect($this->sw)->toContain("request.method !== 'GET'")
            ->and($this->sw)->toContain('url.origin !== self.location.origin');
    });
});

/**
 * Under 100 KB of first-load JavaScript, enforced by scripts/check-bundle-size.js in CI.
 * This test asserts the enforcement still exists, because a budget nobody checks is a
 * budget that is exceeded within two sprints.
 */
it('keeps the bundle budget enforced in the build', function (): void {
    $package = json_decode(file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($package['scripts'])->toHaveKey('budget')
        ->and(file_exists(base_path('scripts/check-bundle-size.js')))->toBeTrue();
});
