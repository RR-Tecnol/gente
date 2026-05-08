import { createApp } from 'vue'
import './style.css'
import './responsive.css'
import App from './App.vue'
import router from './router'
import pinia from './store'
import vuetify from './plugins/vuetify'
import { setGenteSigningUserGetter } from '@/lib/genteSigningBridge'
import { setGenteSudoGlobalGetter } from '@/lib/genteSudoGlobalBridge'
import { setGenteFuncionarioContextGetter } from '@/lib/genteFuncionarioContextBridge'
import { useAuthStore } from '@/store/auth'

const app = createApp(App)

app.use(router)
app.use(pinia)
const auth = useAuthStore()
setGenteSigningUserGetter(() => auth.user)
setGenteSudoGlobalGetter(() => {
  return {
    canBypassTenant: !!auth.user?.can_bypass_tenant,
    isGlobalViewActive: auth.isGlobalViewActive,
    headerName: (auth.user && auth.user.sudo_global_view_header) || 'X-Gente-Global-View',
  }
})
setGenteFuncionarioContextGetter(() => ({
  headerName: auth.funcionarioContextHeader,
  funcionarioContextId: auth.funcionarioContextId,
}))
app.use(vuetify)

app.mount('#app')
