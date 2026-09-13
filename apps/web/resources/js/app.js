/**
 * First-load JavaScript.
 *
 * Deliberately almost empty. The budget is 100 KB gzipped on a Rs 8,000 Android over 3G,
 * and Livewire 3 already ships Alpine, so there is nothing here to add. axios is not
 * installed on purpose — Livewire makes its own requests and pulling in an HTTP client for
 * the handful of fetches below would cost more than it saves.
 *
 * Anything heavier than this belongs on the server. That is the whole reason the stack is
 * Livewire and not an SPA.
 */

// Per-question timing for the daily quiz. Feeds quiz_attempts.answers[].t, which is what
// makes the post-quiz analytics useful: a wrong answer in 4 seconds is a guess, the same
// wrong answer in 90 seconds is a misunderstanding, and they need different advice.
let questionShownAt = Date.now();

document.addEventListener('livewire:navigated', () => {
    questionShownAt = Date.now();
});

window.addEventListener('quiz:question-changed', () => {
    questionShownAt = Date.now();
});

window.secondsOnQuestion = () => Math.round((Date.now() - questionShownAt) / 1000);

// Service worker: app shell, saved material and the last-seen feed, so the product still
// opens on a train with no signal. Registered after load so it never competes with the
// first paint.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // A failed service worker must never break the page. Offline is a bonus here,
            // not a dependency.
        });
    });
}

/**
 * Install prompt.
 *
 * Deliberately NOT shown on first visit. A prompt to install an app the visitor has not
 * yet decided they want is the fastest way to get a permanent dismissal, and the browser
 * only gives one chance. It is stored and offered later, from a button the user can find
 * when they already know the product is useful.
 */
let deferredInstall = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstall = event;
    document.dispatchEvent(new CustomEvent('sadhana:installable'));
});

window.promptInstall = async () => {
    if (!deferredInstall) return false;

    deferredInstall.prompt();
    const { outcome } = await deferredInstall.userChoice;
    deferredInstall = null;

    return outcome === 'accepted';
};
