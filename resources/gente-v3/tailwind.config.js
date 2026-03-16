/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                navy: {
                    blue: '#1E3A8A',
                    DEFAULT: '#0F172A',
                },
                leaf: {
                    DEFAULT: '#22C55E',
                    green: '#A3E635',
                }
            }
        },
    },
    plugins: [],
}
