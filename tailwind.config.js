import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                tema: {
                    sand: '#FAF6F0',
                    dark: '#1A2E2B',
                    green: '#2D6A4F',
                    'green-light': '#3D8A68',
                    terracotta: '#E07A5F',
                    ocre: '#D4A373',
                    brick: '#C44536',
                },
            },
        },
    },

    plugins: [forms],
};