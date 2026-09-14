<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

/**
 * The design system, held to the specification.
 *
 * Two failures this catches, both of which had already happened:
 *
 *   1. A CLASS THAT DOES NOT EXIST. Tailwind drops an unknown utility silently — no error,
 *      no warning, just an element that renders with no colour. `text-marigold-ink` was
 *      used on the mock-interview feedback markers while the theme defined no such token,
 *      so the warning icons rendered in inherited body text and nobody would have noticed
 *      until someone compared the screen to the prototype.
 *
 *   2. A COLOUR FROM OUTSIDE THE PALETTE. The AI surfaces reached for Tailwind's stock
 *      violet because the theme had no AI colour, even though the specification defines a
 *      complete one. It looked close enough to pass, which is exactly why it survived.
 *
 * The palette is nine colours plus an AI family. That constraint is the design — a product
 * where every state maps to one of nine colours is legible on a cheap screen in sunlight,
 * and one where colours accumulate is not.
 */
function themeSource(): string
{
    return file_get_contents(base_path('tailwind.config.js'));
}

it('carries every colour the specification defines', function (string $token, string $hex): void {
    // toContain takes further arguments as ADDITIONAL needles, not as a failure message,
    // so the hex is asserted alone and the token name lives in the dataset label.
    expect(strtoupper(themeSource()))->toContain(strtoupper($hex));
})->with([
    // Taken verbatim from SADHANA-UI-Specification.html.
    ['ink', '#12211C'],
    ['ink-soft', '#33463E'],
    ['ink-faint', '#9AA8A1'],
    ['muted', '#6B7C74'],
    ['green', '#0F6B4F'],
    ['green-deep', '#0A4E39'],
    ['green-wash', '#E3F0EA'],
    ['marigold', '#E8A33D'],
    ['marigold-ink', '#B67A1F'],
    ['marigold-wash', '#FCF1DE'],
    ['danger', '#C4362B'],
    ['danger-wash', '#FBE7E4'],
    ['paper', '#F7F8F5'],
    ['line', '#DDE3DF'],
    ['info', '#2C5F8A'],
    ['info-wash', '#E4EDF4'],
    // The AI family, from SADHANA-AI-Layer.html.
    ['ai', '#5B4B8A'],
    ['ai-ink', '#40356A'],
    ['ai-wash', '#EEEAF7'],
    ['ai-border', '#C9BEE6'],
]);

it('keeps the typefaces the specification chose', function (): void {
    // Gabarito beside Noto Sans Telugu is not decoration: they share metrics, so a
    // bilingual line does not jump in weight or height mid-sentence.
    expect(themeSource())
        ->toContain('Gabarito')
        ->toContain('Noto Sans Telugu');
});

it('uses no colour outside the palette in any view', function (): void {
    $theme = themeSource();

    // Colour names the theme actually defines.
    preg_match_all('/^\s{16}([a-z]+):/m', $theme, $m);
    $defined = array_merge($m[1], [
        'ink', 'muted', 'green', 'marigold', 'danger', 'paper', 'line', 'ai', 'info',
        // Neutrals Tailwind ships that carry no product meaning.
        'white', 'black', 'transparent', 'current', 'inherit',
    ]);

    $offPalette = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        // Filament pages use Filament's own palette, which is a separate system.
        if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'filament'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $contents = $file->getContents();

        // Any Tailwind stock colour scale used on a text/bg/border utility.
        preg_match_all(
            '/\b(?:text|bg|border|ring|fill|from|via|to|divide)-(slate|zinc|neutral|stone|red|orange|amber|yellow|lime|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|gray)-\d{2,3}\b/',
            $contents,
            $hits,
        );

        foreach ($hits[0] as $hit) {
            $offPalette[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname()).' -> '.$hit;
        }
    }

    expect($offPalette)->toBe([]);
});

it('keeps the tap target at the size a moving bus requires', function (): void {
    // 48px minimum, everywhere. One-handed use on a moving bus is the design constraint,
    // not an accessibility checkbox.
    expect(themeSource())->toContain("'tap': '48px'");
});
