/**
 * Service worker.
 *
 * The target is a Rs 8,000 Android on a patchy 3G connection, often on a bus or a train
 * where signal comes and goes. Offline is not a bonus feature here — it is the difference
 * between a product that works during a commute and one that does not.
 *
 * THREE STRATEGIES, CHOSEN BY WHAT THE CONTENT COSTS IF IT IS STALE:
 *
 *   network-first   Notifications and the feed. A CACHED DEADLINE IS A LIE. Serving a
 *                   stale last date would do exactly the harm the whole product exists to
 *                   prevent, so the network always wins when it is available and the cache
 *                   is only a fallback for genuine offline.
 *
 *   cache-first     Fonts, CSS, JS, icons. Immutable, hashed by the build, and the Telugu
 *                   font subset is 60 KB that nobody should download twice.
 *
 *   stale-while-revalidate   Exam hub pages. They change a few times a month and are read
 *                   constantly, so showing yesterday's syllabus instantly while fetching
 *                   today's in the background is the right trade.
 */

const VERSION = 'v1';
const SHELL_CACHE = `sadhana-shell-${VERSION}`;
const PAGE_CACHE = `sadhana-pages-${VERSION}`;
const ASSET_CACHE = `sadhana-assets-${VERSION}`;

/**
 * Deliberately tiny. Pre-caching many pages on install costs a user real money out of a
 * limited data pack before they have decided they want the product.
 */
const SHELL = ['/te', '/offline', '/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            // Individually, so one 404 does not fail the whole install and leave the user
            // with no service worker at all.
            .then((cache) => Promise.allSettled(SHELL.map((url) => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL_CACHE, PAGE_CACHE, ASSET_CACHE];

    event.waitUntil(
        caches
            .keys()
            .then((names) => Promise.all(names.filter((n) => !keep.includes(n)).map((n) => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;

    // Never cache the admin panel, auth, or Livewire round-trips. A cached admin page
    // could show one staff member another's session state.
    if (/^\/(admin|livewire|_debugbar)/.test(url.pathname)) return;
    if (/\/(sign-in|verify|sign-out)/.test(url.pathname)) return;

    if (/\.(woff2?|css|js|png|jpg|svg|ico)$/.test(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (/\/exams(\/|$)/.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    event.respondWith(networkFirst(request));
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) (await caches.open(ASSET_CACHE)).put(request, response.clone());
        return response;
    } catch {
        return new Response('', { status: 504 });
    }
}

/**
 * Network first, with a timeout.
 *
 * Without the timeout a 3G connection that has technically not failed — just stalled —
 * leaves the user staring at a blank screen for thirty seconds. Six seconds then falling
 * back to cache is far better than a correct answer nobody waited for.
 */
async function networkFirst(request, timeoutMs = 6000) {
    const cache = await caches.open(PAGE_CACHE);

    try {
        const response = await Promise.race([
            fetch(request),
            new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), timeoutMs)),
        ]);

        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        const cached = await cache.match(request);
        if (cached) return cached;

        return (await caches.match('/offline')) || new Response('Offline', { status: 503 });
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(PAGE_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        })
        .catch(() => cached);

    return cached || network;
}

/**
 * Push notifications.
 *
 * The tag means a second notification of the same type REPLACES the first in the tray
 * rather than stacking. Three unread job alerts read as spam; one that says "3 new" does not.
 */
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch {
        return;
    }

    const { title, body, icon, tag, url } = {
        icon: '/icons/icon-192.png',
        ...(payload.notification || {}),
        ...(payload.data || {}),
    };

    event.waitUntil(
        self.registration.showNotification(title || 'Sadhana', {
            body,
            icon,
            badge: '/icons/badge-72.png',
            tag: tag || 'general',
            renotify: false,
            data: { url: url || '/te' },
        })
    );
});

/**
 * Focus an already-open tab rather than opening a second one. Someone who taps a
 * notification while the app is open wants the page, not another copy of the app.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url || '/te';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
