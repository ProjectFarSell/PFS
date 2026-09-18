import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // ── Semantic Accent (Dynamic: changes automatically in .dark) ──
                accent: {
                    DEFAULT: 'rgb(var(--color-accent) / <alpha-value>)',
                    hover:   'rgb(var(--color-accent-hover) / <alpha-value>)',
                    subtle:  'rgb(var(--color-accent-subtle) / <alpha-value>)',
                    text:    'rgb(var(--color-accent-text) / <alpha-value>)',

                    // Static numeric scale retained for specific overrides
                    50:  '#F5F3FF',
                    100: '#EDE9FE',
                    200: '#DDD6FE',
                    300: '#C4B5FD',
                    400: '#A78BFA',
                    500: '#8B5CF6',
                    600: '#7C3AED',
                    700: '#6D28D9',
                    800: '#5B21B6',
                    900: '#4C1D95',
                },

                // ── Surfaces & Text ───────────────────────────────────────────
                surface:          'rgb(var(--color-surface) / <alpha-value>)',
                'on-surface':     'rgb(var(--color-on-surface) / <alpha-value>)',
                'surface-muted':  'rgb(var(--color-surface-muted) / <alpha-value>)',
                'surface-border': 'rgb(var(--color-surface-border) / <alpha-value>)',
                'text-base':      'rgb(var(--color-text-base) / <alpha-value>)',
                'text-muted':     'rgb(var(--color-text-muted) / <alpha-value>)',

                // ── Status & Footer Tokens ────────────────────────────────────
                success: 'rgb(var(--color-success) / <alpha-value>)',
                error:   'rgb(var(--color-error) / <alpha-value>)',
                footer: {
                    bg:   'rgb(var(--color-footer-bg) / <alpha-value>)',
                    text: 'rgb(var(--color-footer-text) / <alpha-value>)',
                },
            },

            boxShadow: {
                card: '0 1px 4px 0 rgb(0 0 0 / .06), 0 1px 2px -1px rgb(0 0 0 / .06)',
                'card-md': '0 4px 14px 0 rgb(0 0 0 / .08)',
                'accent-glow': '0 4px 18px 0 rgb(var(--color-accent) / .35)',
            },

            keyframes: {
                'fade-up': {
                    '0%':   { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in': {
                    '0%':   { opacity: '0', transform: 'translateX(-8px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },

            animation: {
                'fade-up':  'fade-up 0.25s ease-out',
                'slide-in': 'slide-in 0.2s ease-out',
            },
        },
    },

    plugins: [forms],
};
