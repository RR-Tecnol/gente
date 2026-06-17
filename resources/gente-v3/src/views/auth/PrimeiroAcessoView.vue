<template>
  <div v-if="authStore.isAuthenticated" class="pa-page">
    <div class="pa-mesh" />
    <div class="pa-card">
      <div class="pa-hdr">
        <h1>Definir nova senha</h1>
        <p>Por segurança, altere a senha do seu primeiro acesso antes de continuar.</p>
      </div>

      <div v-if="errorMessage" class="error-alert">
        <span>{{ errorMessage }}</span>
      </div>

      <form class="pa-form" @submit.prevent="submit">
        <div class="field">
          <label>Senha atual</label>
          <input v-model="form.current" type="password" autocomplete="current-password" required />
        </div>
        <div class="field">
          <label>Nova senha</label>
          <input v-model="form.nova" type="password" autocomplete="new-password" required />
        </div>
        <div v-if="form.nova.length > 0" class="crit">
          <ul>
            <li :class="{ ok: crit.length }">Mínimo 8 caracteres</li>
            <li :class="{ ok: crit.upper }">Uma letra maiúscula</li>
            <li :class="{ ok: crit.num }">Pelo menos um número</li>
            <li :class="{ ok: crit.spec }">Um caractere especial (!@#$%^&*)</li>
            <li :class="{ ok: crit.noRepeat }">Sem repetição consecutiva do mesmo caractere</li>
          </ul>
        </div>
        <div class="field">
          <label>Confirmar nova senha</label>
          <input v-model="form.confirm" type="password" autocomplete="new-password" required />
        </div>
        <button type="submit" class="btn" :disabled="saving || score < 5">
          {{ saving ? 'Salvando…' : 'Salvar e continuar' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
  if (!authStore.isAuthenticated) {
    router.replace({ name: 'Login' })
  }
})
const saving = ref(false)
const errorMessage = ref('')
const form = reactive({ current: '', nova: '', confirm: '' })

const crit = computed(() => {
  const pwd = form.nova || ''
  return {
    length: pwd.length >= 8,
    upper: /[A-Z]/.test(pwd),
    num: /[0-9]/.test(pwd),
    spec: /[!@#$%^&*]/.test(pwd),
    noRepeat: !/^(.)\1+$/.test(pwd) && pwd.length > 0
  }
})

const score = computed(() => Object.values(crit.value).filter(Boolean).length)

async function submit() {
  errorMessage.value = ''
  if (form.nova !== form.confirm) {
    errorMessage.value = 'A confirmação não confere com a nova senha.'
    return
  }
  if (score.value < 5) {
    errorMessage.value = 'A senha não atende a todos os critérios de segurança.'
    return
  }
  saving.value = true
  try {
    const { data } = await api.post('/api/auth/change-password', {
      senha_atual: form.current,
      senha_nova: form.nova
    })
    if (data.user) {
      authStore.setSessionUser(data.user)
    } else {
      await authStore.fetchUser(true)
    }
    await router.replace({ name: 'Dashboard' })
  } catch (e) {
    errorMessage.value = e.response?.data?.error || 'Não foi possível alterar a senha.'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.pa-page {
  min-height: 100vh;
  min-height: 100dvh;
  width: 100vw;
  max-width: none;
  margin: 0;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  font-family: 'Inter', system-ui, sans-serif;
  position: relative;
  background: #0a0f1e;
}
.pa-mesh {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #0a0f1e 0%, #0d1f4a 50%, #0a0f1e 100%);
  z-index: 0;
}
.pa-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  background: rgba(10, 16, 30, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 28px 32px 32px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
}
.pa-hdr h1 {
  margin: 0 0 8px;
  font-size: 1.35rem;
  color: #f1f5f9;
}
.pa-hdr p {
  margin: 0 0 20px;
  font-size: 0.9rem;
  color: #94a3b8;
  line-height: 1.45;
}
.error-alert {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(248, 113, 113, 0.3);
  color: #fecaca;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 0.88rem;
  margin-bottom: 16px;
}
.field {
  margin-bottom: 14px;
}
.field label {
  display: block;
  font-size: 0.8rem;
  color: #94a3b8;
  margin-bottom: 4px;
}
.field input {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(15, 23, 42, 0.6);
  color: #e2e8f0;
  font-size: 0.95rem;
}
.crit {
  font-size: 0.75rem;
  color: #64748b;
  margin: -4px 0 12px;
}
.crit ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
.crit li {
  padding: 2px 0;
  padding-left: 14px;
  position: relative;
}
.crit li::before {
  content: '○';
  position: absolute;
  left: 0;
  opacity: 0.6;
}
.crit li.ok {
  color: #86efac;
}
.crit li.ok::before {
  content: '✓';
}
.btn {
  width: 100%;
  margin-top: 8px;
  padding: 12px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
