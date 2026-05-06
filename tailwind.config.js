import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                base: '#f7f7f5',
                primary: {
                    DEFAULT: '#1f1a17',
                    foreground: '#ffffff',
                },
                secondary: {
                    DEFAULT: '#087ab2',
                    foreground: '#ffffff',
                },
                brand: {
                    base: '#f7f7f5',
                    primary: '#1f1a17',
                    secondary: '#087ab2',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
