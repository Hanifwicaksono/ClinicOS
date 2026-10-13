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
                    50: '#eaf2f2',
                    100: '#d4e5e4',
                    200: '#a9ccc9',
                    300: '#7eaca9',
                    400: '#538d87',
                    500: '#287f78',
                    600: '#206a64',
                    700: '#3d5f5c',
                    800: '#314c49',
                    900: '#293f3d',
                    950: '#253331',
                    primary: '#287f78',
                    hover: '#206a64',
                    secondary: '#3d5f5c',
                    accent: '#d89b68',
                    background: '#f7f8f5',
                    surface: '#ffffff',
                    text: '#253331',
                    border: '#dde5e1',
                },
                slate: {
                    50: '#f7f8f5',
                    100: '#eff3f1',
                    200: '#dde5e1',
                    300: '#c7d3cf',
                    400: '#91a29e',
                    500: '#667976',
                    600: '#3d5f5c',
                    700: '#314b48',
                    800: '#293d3a',
                    900: '#253331',
                    950: '#182421',
                },
            },
            boxShadow: {
                card: '0 2px 8px -2px rgba(15, 23, 42, 0.04)',
                elevated: '0 20px 50px -20px rgba(7, 21, 16, 0.28)',
            },
        },
    },

    plugins: [forms],
};
