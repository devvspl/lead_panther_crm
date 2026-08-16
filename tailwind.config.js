import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
                ink: '#0A0A0A',
                surface: '#FFFFFF',
                canvas: '#F5F5F5',
                border: '#E5E7EB',
                muted: '#6B7280',
                accent: '#111827',
                primary: '#111827',
                'primary-hover': '#000000',
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
