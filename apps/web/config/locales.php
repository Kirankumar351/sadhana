<?php

declare(strict_types=1);

/**
 * Supported locales.
 *
 * Adding a language later is a config flip plus content, not a code change. That is the
 * entire point of doing this properly at the start.
 *
 * Decision D7: two languages at launch, not eight. Half-empty language versions produce
 * thin pages, Google penalises thin pages, and the SEO engine is the whole acquisition
 * model. A locale is switched to `active => true` only when its content pipeline is proven.
 */
return [
    'supported' => [
        'te' => ['name' => 'Telugu',   'native' => 'తెలుగు',  'dir' => 'ltr', 'active' => true],
        'en' => ['name' => 'English',  'native' => 'English', 'dir' => 'ltr', 'active' => true],
        'hi' => ['name' => 'Hindi',    'native' => 'हिन्दी',   'dir' => 'ltr', 'active' => false],
        'ta' => ['name' => 'Tamil',    'native' => 'தமிழ்',   'dir' => 'ltr', 'active' => false],
        'kn' => ['name' => 'Kannada',  'native' => 'ಕನ್ನಡ',   'dir' => 'ltr', 'active' => false],
        'mr' => ['name' => 'Marathi',  'native' => 'मराठी',   'dir' => 'ltr', 'active' => false],
        'bn' => ['name' => 'Bengali',  'native' => 'বাংলা',   'dir' => 'ltr', 'active' => false],
    ],

    'default'  => 'te',
    'fallback' => 'en',

    /**
     * Typography per script. Telugu glyphs are taller and its strings run 15-30% longer
     * than the English equivalent, so line-height and layout must be tested against Telugu,
     * never against English.
     */
    'typography' => [
        'te' => ['line_height' => 1.85, 'font_stack' => "'Noto Sans Telugu', system-ui, sans-serif"],
        'en' => ['line_height' => 1.60, 'font_stack' => 'system-ui, -apple-system, sans-serif'],
    ],

    // Numerals stay Latin in every locale. 2026, Rs 28,940, 16,614.
    // Telugu numerals are not read fluently by this audience.
    'latin_numerals_always' => true,
];
