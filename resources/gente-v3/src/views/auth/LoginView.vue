<template>
  <div class="login-page">

    <!-- Fundo animado -->
    <div class="bg-mesh"></div>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
    <div class="grid-overlay"></div>

    <!-- Card central -->
    <div class="login-card">

      <!-- Lado esquerdo: branding -->
      <div class="side-left">
        <div class="brand-top">
          <div class="brand-logo">
            <img src="/logo.png" alt="GENTE - Gestão de Pessoas" class="logo-img" />
          </div>
        </div>

        <div class="brand-headline">
          <h1>Capital<br />Humano<br /><span class="accent">Inteligente.</span></h1>
          <p class="brand-desc">
            Plataforma de gestão de pessoas com tecnologia de ponta.
            Segurança, performance e conformidade eSocial em um só lugar.
          </p>
        </div>

        <div class="brand-footer">
          <div class="status-pill">
            <span class="status-dot"></span>
            <span>Plataforma Online</span>
          </div>
          <p class="brand-credit">Tecnologia by <strong>RR Tecnol</strong></p>
        </div>
      </div>

      <!-- Lado direito: formulário -->
      <div class="side-right">
        <div class="form-wrapper">

          <div class="form-header">
            <div class="form-icon-wrap">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
            <h2>Área Restrita</h2>
            <p>Identifique-se para acessar o painel de RH</p>
          </div>

          <!-- Alerta de erro -->
          <transition name="fade-up">
            <div v-if="errorMessage" class="error-alert">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span>{{ errorMessage }}</span>
            </div>
          </transition>

          <form @submit.prevent="handleLogin" class="login-form">

            <!-- CPF / Login -->
            <div class="field-group" :class="{ focused: focusCpf }">
              <label>Credencial do Servidor</label>
              <div class="input-wrap">
                <svg class="input-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 3v4M8 3v4M2 10h20"/>
                </svg>
                <input
                  v-model="credentials.cpf"
                  type="text"
                  placeholder="Login ou CPF"
                  required
                  @focus="focusCpf = true"
                  @blur="focusCpf = false"
                />
              </div>
            </div>

            <!-- Senha -->
            <div class="field-group" :class="{ focused: focusPwd }">
              <div class="label-row">
                <label>Senha</label>
                <a href="#" class="link-forgot">Recuperar acesso</a>
              </div>
              <div class="input-wrap">
                <svg class="input-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                <input
                  v-model="credentials.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="••••••••"
                  required
                  @focus="focusPwd = true"
                  @blur="focusPwd = false"
                />
                <button type="button" class="eye-btn" @click="showPassword = !showPassword" tabindex="-1">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <template v-if="showPassword">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                      <line x1="1" y1="1" x2="23" y2="23"/>
                    </template>
                    <template v-else>
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </template>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Botão Entrar -->
            <button type="submit" class="btn-login" :disabled="loading" :class="{ loading }">
              <span v-if="!loading" class="btn-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                  <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                </svg>
                Entrar no Sistema
              </span>
              <svg v-else class="spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.2"/>
                <path d="M12 2a10 10 0 0110 10" stroke-linecap="round"/>
              </svg>
            </button>

          </form>

          <p class="form-footer-credit">RR Tecnol &copy; {{ new Date().getFullYear() }}</p>
        </div>
      </div>

    </div>

    <!-- Modal de Troca de Senha -->
    <div v-if="showChangePasswordModal" class="login-modal-overlay">
      <div class="login-modal">
        <div class="form-header" style="margin-bottom: 20px;">
          <h2>Criar Nova Senha</h2>
          <p>Por medida de segurança, você precisa atualizar sua senha</p>
        </div>

        <transition name="fade-up">
          <div v-if="changePasswordError" class="error-alert">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>{{ changePasswordError }}</span>
          </div>
        </transition>

        <form @submit.prevent="submitChangePassword" class="login-form">
          <div class="field-group">
            <label>Nova Senha</label>
            <div class="input-wrap">
              <input v-model="newPasswordForm.new" type="password" required />
            </div>
          </div>

          <div class="pwd-strength" v-if="newPasswordForm.new.length > 0">
            <div class="strength-bar">
              <div class="strength-fill" :style="{ width: (pwdCriteria.score * 20) + '%', backgroundColor: pwdStrengthColor }"></div>
            </div>
            <ul class="strength-criteria">
              <li :class="{ met: pwdCriteria.length }">Mínimo 8 caracteres</li>
              <li :class="{ met: pwdCriteria.upper }">Uma letra maiúscula</li>
              <li :class="{ met: pwdCriteria.num }">Pelo menos um número</li>
              <li :class="{ met: pwdCriteria.spec }">Um caractere especial (!@#$%)</li>
              <li :class="{ met: pwdCriteria.noRepeat }">Sem repetição consecutiva (ex: aaaa)</li>
            </ul>
          </div>

          <div class="field-group" style="margin-top: 10px;">
            <label>Confirmar Senha</label>
            <div class="input-wrap">
              <input v-model="newPasswordForm.confirm" type="password" required />
            </div>
          </div>

          <button type="submit" class="btn-login" :disabled="changingPassword || pwdCriteria.score < 5">
            <span v-if="!changingPassword">Atualizar Senha e Entrar</span>
            <svg v-else class="spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.2"/>
              <path d="M12 2a10 10 0 0110 10" stroke-linecap="round"/>
            </svg>
          </button>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const showPassword = ref(false)
const errorMessage = ref('')
const focusCpf = ref(false)
const focusPwd = ref(false)

const credentials = reactive({ cpf: '', password: '' })

// SEC-PROD-04: Variáveis para troca de senha
const showChangePasswordModal = ref(false)
const newPasswordForm = reactive({ current: '', new: '', confirm: '' })
const changePasswordError = ref('')
const changingPassword = ref(false)

const pwdCriteria = computed(() => {
  const pwd = newPasswordForm.new || ''
  const criteria = {
    length: pwd.length >= 8,
    upper: /[A-Z]/.test(pwd),
    num: /[0-9]/.test(pwd),
    spec: /[!@#$%^&*]/.test(pwd),
    noRepeat: !/^(.)\1+$/.test(pwd) && pwd.length > 0
  }
  const score = Object.values(criteria).filter(Boolean).length
  return { ...criteria, score }
})

const pwdStrengthColor = computed(() => {
  const score = pwdCriteria.value.score
  if (score <= 2) return '#ef4444' // vermelho
  if (score <= 4) return '#eab308' // amarelo
  return '#22c55e' // verde
})

const submitChangePassword = async () => {
  if (newPasswordForm.new !== newPasswordForm.confirm) {
    changePasswordError.value = 'A confirmação não confere'
    return
  }
  if (pwdCriteria.value.score < 5) {
    changePasswordError.value = 'A senha não atende a todos os critérios'
    return
  }
  
  changingPassword.value = true
  changePasswordError.value = ''
  try {
    await api.post('/api/auth/change-password', {
      senha_atual: newPasswordForm.current,
      senha_nova: newPasswordForm.new
    })
    showChangePasswordModal.value = false
    authStore.clearCache()
    router.push('/dashboard')
  } catch (err) {
    changePasswordError.value = err.response?.data?.error || 'Erro ao alterar a senha'
  } finally {
    changingPassword.value = false
  }
}

const authStore = useAuthStore()

onMounted(() => {
  if (route.query.sessao_expirada) {
    errorMessage.value = 'Sessão expirada. Por favor, faça login novamente.'
  }
  const script = document.createElement('script')
  script.src = `https://www.google.com/recaptcha/api.js?render=${import.meta.env.VITE_RECAPTCHA_SITE_KEY}`
  document.head.appendChild(script)
})

const handleLogin = async () => {
  if (!credentials.cpf || !credentials.password) return
  loading.value = true
  errorMessage.value = ''
  try {
    const token = await new Promise(resolve =>
      window.grecaptcha.ready(() =>
        window.grecaptcha.execute(import.meta.env.VITE_RECAPTCHA_SITE_KEY, { action: 'login' })
          .then(resolve)
      )
    )

    await api.get('/csrf-cookie')
    const resp = await api.post('/api/auth/login', {
      USUARIO_LOGIN: credentials.cpf,
      USUARIO_SENHA: credentials.password,
      recaptcha_token: token
    })

    if (resp.data.user?.alterar_senha) {
      newPasswordForm.current = credentials.password
      showChangePasswordModal.value = true
      return
    }

    authStore.clearCache() // BUG-03: invalida cache para forçar fetchUser no guard
    router.push('/dashboard')
  } catch (err) {
    if (err.response?.status === 422 || err.response?.status === 401) {
      errorMessage.value = 'Credenciais incorretas ou conta inativa.'
    } else {
      errorMessage.value = 'Falha na conexão com o servidor. Tente novamente.'
    }
  } finally {
    loading.value = false
  }
}

</script>

<style scoped>
/* ═══════════════════════════════════════
   PÁGINA INTEIRA
══════════════════════════════════════════*/
.login-page {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ── Fundo ─────────────────────────────*/
.bg-mesh {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #0a0f1e 0%, #0d1f4a 40%, #0a0f1e 100%);
  z-index: 0;
}
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.25;
  pointer-events: none;
  z-index: 0;
}
.orb1 { width: 500px; height: 500px; background: #22c55e; top: -150px; left: -150px; animation: drift 14s ease-in-out infinite; }
.orb2 { width: 400px; height: 400px; background: #6366f1; top: 20%; right: -120px; animation: drift 18s ease-in-out infinite reverse; }
.orb3 { width: 600px; height: 600px; background: #0ea5e9; bottom: -200px; left: 30%; animation: drift 22s ease-in-out infinite; }
@keyframes drift { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px,-20px) scale(1.05); } 66% { transform: translate(-20px,30px) scale(0.96); } }

.grid-overlay {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
  background-size: 40px 40px;
}

/* ═══════════════════════════════════════
   CARD CENTRAL
══════════════════════════════════════════*/
.login-card {
  position: relative;
  z-index: 10;
  display: flex;
  width: min(1000px, calc(100vw - 32px));
  height: min(620px, calc(100vh - 48px));
  border-radius: 28px;
  overflow: hidden;
  box-shadow:
    0 0 0 1px rgba(255,255,255,0.08),
    0 40px 80px rgba(0,0,0,0.55),
    0 0 80px rgba(34,197,94,0.08);
  background: rgba(10, 16, 30, 0.7);
  backdrop-filter: blur(32px);
}

/* ═══════════════════════════════════════
   LADO ESQUERDO — BRANDING
══════════════════════════════════════════*/
.side-left {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 44%;
  padding: 40px 44px;
  border-right: 1px solid rgba(255,255,255,0.07);
  background: linear-gradient(160deg, rgba(255,255,255,0.04) 0%, transparent 60%);
  position: relative;
  overflow: hidden;
}
.side-left::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, #22c55e, transparent);
}

/* Logo topo */
.brand-top {
  display: flex;
  align-items: center;
}
.brand-logo {
  background: #fff;
  border-radius: 18px;
  padding: 12px 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.logo-img {
  height: 72px;
  width: auto;
  object-fit: contain;
  display: block;
}

/* Headline central */
.brand-headline {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 24px 0;
}
.brand-headline h1 {
  font-size: clamp(32px, 4vw, 46px);
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: #fff;
  margin: 0 0 18px;
}
.brand-headline h1 .accent {
  background: linear-gradient(90deg, #22c55e, #34d399);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.brand-desc {
  font-size: 13px;
  line-height: 1.7;
  color: rgba(255,255,255,0.45);
  margin: 0;
  max-width: 280px;
}

/* Rodapé esquerdo */
.brand-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(34,197,94,0.1);
  border: 1px solid rgba(34,197,94,0.25);
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #4ade80;
  width: fit-content;
}
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34,197,94,0.3);
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 2px rgba(34,197,94,0.3); } 50% { box-shadow: 0 0 0 5px rgba(34,197,94,0.1); } }
.brand-credit {
  font-size: 11px;
  color: rgba(255,255,255,0.25);
  margin: 0;
  letter-spacing: 0.05em;
}
.brand-credit strong { color: rgba(255,255,255,0.5); }

/* ═══════════════════════════════════════
   LADO DIREITO — FORMULÁRIO
══════════════════════════════════════════*/
.side-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 44px;
  background: rgba(0,0,0,0.2);
}
.form-wrapper {
  width: 100%;
  max-width: 340px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Cabeçalho do form */
.form-header {
  text-align: center;
  margin-bottom: 28px;
}
.form-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: rgba(255,255,255,0.7);
}
.form-header h2 {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  margin: 0 0 6px;
  letter-spacing: -0.02em;
}
.form-header p {
  font-size: 13px;
  color: rgba(255,255,255,0.4);
  margin: 0;
}

/* Alerta de erro */
.error-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.25);
  border-radius: 12px;
  padding: 10px 14px;
  margin-bottom: 18px;
  font-size: 13px;
  color: #fca5a5;
  line-height: 1.4;
}
.fade-up-enter-active, .fade-up-leave-active { transition: all 0.3s ease; }
.fade-up-enter-from, .fade-up-leave-to { opacity: 0; transform: translateY(-8px); }

/* Formulário */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

/* Grupos de campo */
.field-group { display: flex; flex-direction: column; gap: 7px; }
.field-group label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.4);
  transition: color 0.2s;
}
.field-group.focused label { color: #22c55e; }
.label-row { display: flex; align-items: center; justify-content: space-between; }
.link-forgot {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.3);
  text-decoration: none;
  transition: color 0.2s;
}
.link-forgot:hover { color: #22c55e; }

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  background: rgba(255,255,255,0.05);
  border: 1.5px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  overflow: hidden;
}
.field-group.focused .input-wrap {
  border-color: rgba(34,197,94,0.5);
  background: rgba(34,197,94,0.05);
  box-shadow: 0 0 0 3px rgba(34,197,94,0.08);
}
.input-ico {
  flex-shrink: 0;
  margin-left: 14px;
  color: rgba(255,255,255,0.3);
  transition: color 0.2s;
}
.field-group.focused .input-ico { color: #22c55e; }
.input-wrap input {
  flex: 1;
  padding: 13px 14px;
  background: transparent;
  border: none;
  outline: none;
  font-size: 14px;
  color: #fff;
  font-family: inherit;
}
.input-wrap input::placeholder { color: rgba(255,255,255,0.2); }
.eye-btn {
  flex-shrink: 0;
  margin-right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  color: rgba(255,255,255,0.3);
  display: flex;
  align-items: center;
  padding: 4px;
  transition: color 0.2s;
}
.eye-btn:hover { color: rgba(255,255,255,0.7); }

/* Botão principal */
.btn-login {
  margin-top: 6px;
  width: 100%;
  padding: 14px;
  border-radius: 14px;
  border: none;
  cursor: pointer;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #fff;
  background: linear-gradient(135deg, #16a34a, #22c55e 50%, #4ade80);
  background-size: 200% auto;
  box-shadow: 0 4px 24px rgba(34,197,94,0.35);
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.btn-login:hover:not(:disabled) {
  background-position: right center;
  box-shadow: 0 8px 32px rgba(34,197,94,0.5);
  transform: translateY(-1px);
}
.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-text {
  display: flex;
  align-items: center;
  gap: 9px;
}

.spin { animation: spin 0.85s linear infinite; transform-origin: center; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Crédito rodapé form */
.form-footer-credit {
  text-align: center;
  font-size: 11px;
  color: rgba(255,255,255,0.2);
  margin: 0;
  letter-spacing: 0.05em;
}

/* ═══════════════════════════════════════
   RESPONSIVO
══════════════════════════════════════════*/
@media (max-width: 700px) {
  .login-card {
    flex-direction: column;
    height: auto;
    min-height: 0;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
  }
  .side-left {
    width: 100%;
    padding: 28px 28px 20px;
    border-right: none;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .brand-headline { padding: 12px 0; }
  .brand-headline h1 { font-size: 28px; }
  .side-right { padding: 28px 28px 32px; }
}

.login-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.8);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
.login-modal {
  width: 100%;
  max-width: 400px;
  background: rgba(20, 26, 40, 0.95);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.6);
  border: 1px solid rgba(255,255,255,0.08);
}
.pwd-strength { margin-top: 6px; }
.strength-bar {
  height: 4px; border-radius: 2px;
  background: rgba(255,255,255,0.1); overflow: hidden;
  margin-bottom: 8px;
}
.strength-fill { height: 100%; transition: width 0.3s ease, background-color 0.3s ease; }
.strength-criteria {
  list-style: none; padding: 0; margin: 0;
  font-size: 11px; color: rgba(255,255,255,0.4);
}
.strength-criteria li { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.strength-criteria li::before {
  content: ''; display: inline-block; width: 6px; height: 6px;
  border-radius: 50%; border: 1px solid rgba(255,255,255,0.3);
}
.strength-criteria li.met { color: #22c55e; }
.strength-criteria li.met::before { background: #22c55e; border-color: #22c55e; }

</style>
