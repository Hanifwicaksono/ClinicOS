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
                display: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    primary: '#287F78',
                    'primary-hover': '#206A64',
                    secondary: '#3D5F5C',
                    accent: '#D89B68',
                    background: '#F7F8F5',
                    surface: '#FFFFFF',
                    text: '#253331',
                    border: '#DDE5E1',
                },
                clinic: {
                    50: '#F7F8F5',
                    100: '#EAF2F0',
                    200: '#DDE5E1',
                    500: '#287F78',
                    600: '#206A64',
                    700: '#1B5651',
                    950: '#253331',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px rgba(15, 23, 42, 0.05)',
            },
        },
    },

    plugins: [forms],
};
