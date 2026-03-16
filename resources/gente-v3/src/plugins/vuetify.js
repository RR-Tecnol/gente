// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// Vuetify
import { createVuetify } from 'vuetify'

const genteTheme = {
    dark: false,
    colors: {
        background: '#F8FAFC', // light gray
        surface: '#FFFFFF',
        primary: '#0F172A', // Navy Blue
        'primary-darken-1': '#1E3A8A',
        secondary: '#22C55E', // Leaf Green
        'secondary-darken-1': '#A3E635',
        error: '#B00020',
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#FB8C00',
    }
}

export default createVuetify({
    theme: {
        defaultTheme: 'genteTheme',
        themes: {
            genteTheme,
        }
    }
})
