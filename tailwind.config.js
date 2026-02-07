import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './node_modules/flowbite/**/*.js',
        './node_modules/flowbite-vue/**/*.{js,jsx,ts,tsx,vue}',
    ],

    theme: {
        extend: {
            colors: {
                heading: 'var(--color-surface-text)',
                neutral: {
                    secondary: {
                        medium: 'var(--color-surface-muted)',
                    },
                },
                default: {
                    medium: 'var(--color-surface-border)',
                },
                'neutral-tertiary': 'var(--color-surface-border)',
                body: 'var(--color-surface-text-muted)',
                brand: {
                    DEFAULT: 'var(--color-brand-500)',
                    50: 'var(--color-brand-50)',
                    100: 'var(--color-brand-100)',
                    200: 'var(--color-brand-200)',
                    300: 'var(--color-brand-300)',
                    400: 'var(--color-brand-400)',
                    500: 'var(--color-brand-500)',
                    600: 'var(--color-brand-600)',
                    700: 'var(--color-brand-700)',
                    800: 'var(--color-brand-800)',
                    900: 'var(--color-brand-900)',
                },
                surface: {
                    base: 'var(--color-surface-base)',
                    muted: 'var(--color-surface-muted)',
                    border: 'var(--color-surface-border)',
                    text: 'var(--color-surface-text)',
                    'text-muted': 'var(--color-surface-text-muted)',
                },
            },
            borderRadius: {
                base: 'var(--radius-md)',
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgba(15, 23, 42, 0.06)',
            },
            spacing: {
                xs: 'var(--spacing-xs)',
                sm: 'var(--spacing-sm)',
                md: 'var(--spacing-md)',
                lg: 'var(--spacing-lg)',
                xl: 'var(--spacing-xl)',
            },
            fontFamily: {
                sans: ['Figtree'],
            },
        },
    },

    plugins: [forms, flowbitePlugin],
};
