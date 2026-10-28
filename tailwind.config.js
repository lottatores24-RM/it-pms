import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        // Enums such as RiskLevel::cellClass() return Tailwind classes.
        './app/Enums/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['"Geist Mono"', ...defaultTheme.fontFamily.mono],
            },
            // "Midnight Navy" surfaces for the personal dashboards (layout theme="night").
            colors: {
                night: {
                    950: '#070c18',
                    900: '#0b1220',
                    850: '#101a2e',
                    800: '#16213a',
                    700: '#1c2842',
                    600: '#2a3a5c',
                    400: '#8593b0',
                    200: '#c9d3e6',
                    100: '#e6ecf7',
                },
            },
            // Dashboard motion: short, eased entrances only. Always used behind
            // `motion-safe:` so "reduce motion" users get a static page. Fill mode
            // "backwards" hides elements during their delay but hands the final
            // state back to the element, so hover transforms keep working.
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'grow-x': {
                    '0%': { transform: 'scaleX(0)' },
                    '100%': { transform: 'scaleX(1)' },
                },
                'draw-ring': {
                    '0%': { strokeDashoffset: '100' },
                },
                pop: {
                    '0%': { opacity: '0', transform: 'scale(0.4)' },
                    '70%': { opacity: '1', transform: 'scale(1.15)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards',
                'grow-x': 'grow-x 0.9s cubic-bezier(0.16, 1, 0.3, 1) backwards',
                'draw-ring': 'draw-ring 1.1s cubic-bezier(0.16, 1, 0.3, 1) backwards',
                pop: 'pop 0.5s cubic-bezier(0.16, 1, 0.3, 1) backwards',
            },
        },
    },

    plugins: [forms],
};
