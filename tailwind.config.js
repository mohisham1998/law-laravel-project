import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#e6f5ed',
                    100: '#ccebdb',
                    200: '#99d7b7',
                    300: '#66c393',
                    400: '#33af6f',
                    500: '#006b34',
                    600: '#00562a',
                    700: '#004020',
                    800: '#002b15',
                    900: '#00150b',
                },
                'secondary-green': '#2FAF74',
                'background-light': '#f5f8f7',
                'background-dark': '#0f2319',
                'border-gray': '#E5E7EB',
            },
            fontFamily: {
                sans: ['Cairo', 'sans-serif'],
                display: ['Cairo', 'sans-serif'],
            },
            borderRadius: {
                DEFAULT: '0.5rem',
                lg: '1rem',
                xl: '1.5rem',
            },
        },
    },
}
