import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: 'rgb(var(--lp-ink-rgb, 10 10 10) / <alpha-value>)',
                surface: 'rgb(var(--lp-surface-rgb, 255 255 255) / <alpha-value>)',
                canvas: 'rgb(var(--lp-canvas-rgb, 245 245 245) / <alpha-value>)',
                border: 'rgb(var(--lp-border-rgb, 229 231 235) / <alpha-value>)',
                muted: 'rgb(var(--lp-muted-rgb, 107 114 128) / <alpha-value>)',
                accent: 'rgb(var(--lp-accent-rgb, 17 24 39) / <alpha-value>)',
                primary: 'rgb(var(--lp-primary-rgb, 17 24 39) / <alpha-value>)',
                'primary-hover': 'color-mix(in srgb, rgb(var(--lp-primary-rgb, 17 24 39)) 85%, black)',
                success: '#16A34A',
                warning: '#F59E0B',
                danger: '#DC2626',
                info: '#3B82F6',
            },
            borderRadius: {
                card: '1rem',
                pill: '9999px',
            },
        },
    },

    plugins: [forms],
};
