/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './assets/js/**/*.vue',
  ],
  theme: {
    extend: {
      fontFamily: {
        'satoshi': ['satoshi'],
      },
      colors: {
        primary: {
          "30": "#e1daff",
          "50": "#5a4d8e",
          "70": "rgb(50, 36, 97)",
          "100": "#7859ff"
        },
        green: {
          "100": "#539d17",
        },
        secondary: {
          "50": "rgba(33, 33, 33)",
        },
        red: {
          "300": "rgb(235, 71, 53)",
        }
      }
    },
  },
  plugins: []
}