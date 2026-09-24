import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Brand / core UI
                sans: ['Urbanist', 'Manrope', ...defaultTheme.fontFamily.sans],
                // Supporting accents (labels, dense UI)
                display: ['Urbanist', 'Manrope', ...defaultTheme.fontFamily.sans],
                brand: ['Urbanist', 'Manrope', ...defaultTheme.fontFamily.sans],
                ui: ['Manrope', 'Urbanist', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                tb: {
                    DEFAULT: '#FF5000',
                    hover: '#F04000',
                    soft: '#FFF1EB',
                    red: '#FF0036',
                    green: '#00A26A',
                    muted: '#999999',
                    text: '#333333',
                    border: '#EEEEEE',
                    page: '#F5F5F5',
                    card: '#F7F7F7',
                },
                primary: {
                    50: '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#FF5000',
                    600: '#FF5000',
                    700: '#E04800',
                    800: '#9a3412',
                    900: '#7c2d12',
                },
            },
            maxWidth: {
                marketplace: '1400px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(0,0,0,0.04)',
                lift: '0 2px 8px rgba(0,0,0,0.08)',
            },
        },
    },

    plugins: [forms],
};
