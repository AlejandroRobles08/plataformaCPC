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
                 poppins: ['Poppins', 'sans-serif'],
            },
            // Paleta institucional del Gobierno de la Ciudad de Puebla (pueblacapital.gob.mx)
            colors: {
                puebla: {
                    guinda: '#7d2447',
                    'guinda-oscuro': '#6e2747',
                    verde: '#41504d',
                    'verde-oscuro': '#2d3a37',
                    arena: '#d4c5b2',
                    'arena-claro': '#ebe3d8',
                    dorado: '#c2ba98',
                    naranja: '#db9935',
                    gris: '#474345',
                    'gris-medio': '#636569',
                },
            },
        },
    },

    plugins: [forms],
};
