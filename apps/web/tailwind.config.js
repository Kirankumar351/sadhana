/**
 * Design tokens, taken verbatim from the UI Specification.
 *
 * Nine colours, no gradients. Every state a user can be in maps to exactly one of them.
 *
 * Deep officialdom green carries authority without the coldness of civic blue. Marigold is
 * the accent because it is the colour of exam-morning temple visits and of a deadline that
 * matters — warm urgency rather than alarm.
 *
 * RED IS RESERVED STRICTLY for the last three days before a deadline, and for errors.
 * Use it anywhere else and it stops meaning anything, which costs a student the one signal
 * that was supposed to make them act.
 */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/Filament/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                ink: {
                    DEFAULT: '#12211C',   // body text, headers
                    soft: '#33463E',      // secondary text, labels
                },
                muted: '#6B7C74',         // meta, captions, placeholders
                green: {
                    DEFAULT: '#0F6B4F',   // primary action, eligible, active tab
                    wash: '#E3F0EA',      // eligible badge, correct answer
                },
                marigold: {
                    DEFAULT: '#E8A33D',   // streaks, partial match, quiz CTA
                    wash: '#FCF1DE',      // partial badge, your leaderboard row
                },
                danger: '#C4362B',        // deadline under 3 days, errors only
                paper: '#F7F8F5',         // app background: reads as document, not warmth
            },

            fontFamily: {
                // Gabarito for headings: its round terminals sit comfortably beside
                // Telugu's open bowls. Noto Sans for body because it shares metrics with
                // Noto Sans Telugu, so a bilingual line never jumps in weight or height.
                display: ['Gabarito', 'system-ui', 'sans-serif'],
                sans: ['"Noto Sans"', 'system-ui', '-apple-system', 'sans-serif'],
                telugu: ['"Noto Sans Telugu"', '"Noto Sans"', 'system-ui', 'sans-serif'],
            },

            fontSize: {
                // The scale from the specification, with line-height baked in.
                'display': ['32px', { lineHeight: '1.25', fontWeight: '800' }],
                'screen-title': ['20px', { lineHeight: '1.35', fontWeight: '700' }],
                'card-title': ['15px', { lineHeight: '1.4', fontWeight: '600' }],
                'body': ['14px', { lineHeight: '1.6' }],
                'meta': ['12px', { lineHeight: '1.5', fontWeight: '500' }],
            },

            borderRadius: {
                // Radius encodes hierarchy rather than decorating.
                'pill': '99px',
                'control': '9px',
                'card': '11px',
                'sheet': '20px',
            },

            spacing: {
                // Four-point scale. 'tap' is the minimum interactive target: one-handed
                // use on a moving bus, so nothing smaller, anywhere.
                'tap': '48px',
            },

            maxWidth: {
                'reading': '68ch',
            },
        },
    },

    plugins: [],
};
