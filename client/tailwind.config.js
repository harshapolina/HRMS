/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f4f6fa',
          100: '#e9eef5',
          200: '#cbdbe9',
          300: '#9dbcd8',
          400: '#6898c1',
          500: '#477ca9',
          600: '#36618c',
          700: '#2d4f72',
          800: '#28435f',
          900: '#253a51',
          950: '#192637',
        }
      },
      fontFamily: {
        sans: ['"Lexend Deca"', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
