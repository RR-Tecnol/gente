import { createApp } from 'vue'
import './style.css'
import './responsive.css'
import App from './App.vue'
import router from './router'
import pinia from './store'
import vuetify from './plugins/vuetify'

const app = createApp(App)

app.use(router)
app.use(pinia)
app.use(vuetify)

app.mount('#app')
