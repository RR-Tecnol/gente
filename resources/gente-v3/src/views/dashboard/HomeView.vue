<template>
  <div class="dashboard-home">

    <!-- ═══ BOAS VINDAS ════════════════════════════════════════════ -->
    <div class="welcome-section" :class="{ visible: loaded }">
      <div class="welcome-text">
        <span class="welcome-time">{{ getGreeting() }}</span>
        <h1 class="welcome-title">{{ userName }}</h1>
        <p class="welcome-sub">{{ dateStr }}</p>
      </div>
      <div class="welcome-badge">
        <AppIcon name="check" :size="14" />
        Sistema Online
      </div>
    </div>

    <!-- ═══ KPI CARDS ═════════════════════════════════════════════ -->
    <div class="kpi-grid">
      <div
        class="kpi-card"
        v-for="(kpi, i) in kpis"
        :key="i"
        :class="{ visible: loaded }"
        :style="{ '--delay': `${i * 60}ms`, '--color': kpi.color, '--colorLight': kpi.colorLight }"
      >
        <div class="kpi-icon-wrap">
          <AppIcon :name="kpi.icon" :size="20" :color="kpi.color" />
        </div>
        <div class="kpi-info">
          <span class="kpi-label">{{ kpi.label }}</span>
          <span class="kpi-value">{{ kpi.value }}</span>
        </div>
        <div class="kpi-trend" :class="kpi.trend > 0 ? 'up' : 'neutral'">
          <AppIcon :name="kpi.trend > 0 ? 'trending' : 'chart'" :size="12" />
          {{ kpi.trendLabel }}
        </div>
      </div>
    </div>

    <!-- ═══ ACESSO RÁPIDO ════════════════════════════════════════ -->
    <div class="section-header" :class="{ visible: loaded }">
      <h2 class="section-title">
        <AppIcon name="trending" :size="18" color="#3b82f6" style="margin-right:8px" />
        Acesso Rápido
      </h2>
    </div>

    <div class="modules-grid">
      <div
        v-for="(card, i) in atalhos"
        :key="i"
        class="module-card"
        :class="{ visible: loaded }"
        :style="{
          '--delay': `${(i * 70) + 200}ms`,
          '--gradient-from': card.gradFrom,
          '--gradient-to': card.gradTo
        }"
        @click="irPara(card)"
      >
        <div class="module-card-bg"></div>
        <div class="module-icon-wrap">
          <AppIcon :name="card.icon" :size="26" color="white" />
        </div>
        <div class="module-text">
          <h3 class="module-title">{{ card.title }}</h3>
          <p class="module-desc">{{ card.desc }}</p>
        </div>
        <div class="module-arrow">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
      </div>
    </div>

    <!-- ═══ MURAL ══════════════════════════════════════════════════ -->
    <div class="mural-card" :class="{ visible: loaded }">
      <div class="mural-icon">📌</div>
      <div class="mural-content">
        <h3 class="mural-title">Mural de Avisos</h3>
        <p class="mural-text" v-if="kpiData?.abonos_pendentes > 0">
          Há <strong>{{ kpiData.abonos_pendentes }} abono(s) de falta</strong> aguardando aprovação.
          Acesse <em>Faltas e Atrasos</em> para tratar.
        </p>
        <p class="mural-text" v-else>
          Folha do mês corrente será fechada no dia <strong>25</strong>.
          Regularizem escalas pendentes e tratem os cartões de ponto.
        </p>
      </div>
      <button class="mural-btn" @click="irPara({ url: '/faltas-atrasos', isVue: true })">
        Ver Abonos
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import AppIcon from '@/components/AppIcon.vue'
import api from '@/plugins/axios'

const router = useRouter()
const authStore = useAuthStore()
const loaded = ref(false)
const kpiData = ref(null)

const userName = computed(() => authStore.user?.nome?.split(' ')[0] || authStore.perfilLabel || '')
const dateStr = computed(() => {
  return new Date().toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const getGreeting = () => {
  const h = new Date().getHours()
  if (h < 12) return 'Bom dia,'
  if (h < 18) return 'Boa tarde,'
  return 'Boa noite,'
}

const kpis = computed(() => [
  {
    label: 'Funcionários Ativos',
    value: kpiData.value ? String(kpiData.value.total_funcionarios) : '…',
    icon: 'users', color: '#3b82f6', colorLight: '#eff6ff',
    trend: 0, trendLabel: 'no quadro'
  },
  {
    label: 'Status da Folha',
    value: kpiData.value?.folha_status ?? '…',
    icon: 'contract', color: '#10b981', colorLight: '#f0fdf4',
    trend: 1, trendLabel: kpiData.value?.folha_competencia ?? ''
  },
  {
    label: 'Abonos Pendentes',
    value: kpiData.value ? String(kpiData.value.abonos_pendentes) : '…',
    icon: 'check', color: kpiData.value?.abonos_pendentes > 0 ? '#f59e0b' : '#10b981',
    colorLight: kpiData.value?.abonos_pendentes > 0 ? '#fffbeb' : '#f0fdf4',
    trend: kpiData.value?.abonos_pendentes > 0 ? 1 : 0,
    trendLabel: kpiData.value?.abonos_pendentes > 0 ? 'aguardando aprovação' : 'todos tratados'
  },
  {
    label: 'Competência',
    value: new Date().toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' }).replace('.', '').toUpperCase(),
    icon: 'calendar', color: '#8b5cf6', colorLight: '#f5f3ff',
    trend: 0, trendLabel: 'em andamento'
  },
])

const atalhos = [
  { title: 'Funcionários',        desc: 'Cadastro, vínculos e histórico funcional',      icon: 'users',         url: '/funcionarios',    isVue: true, gradFrom: '#5b21b6', gradTo: '#8b5cf6' },
  { title: 'Ponto Eletrônico',    desc: 'Apuração mensal de frequência',                 icon: 'clock',         url: '/ponto',           isVue: true, gradFrom: '#c2410c', gradTo: '#f97316' },
  { title: 'Meus Holerites',      desc: 'Contracheque digital em PDF',                   icon: 'money',         url: '/holerites',       isVue: true, gradFrom: '#065f46', gradTo: '#10b981' },
  { title: 'Escalas de Trabalho', desc: 'Matrizes, plantões e escalas hospitalares',     icon: 'calendar-week', url: '/escala',          isVue: true, gradFrom: '#1d4ed8', gradTo: '#3b82f6' },
  { title: 'Folha de Pagamento',  desc: 'Cálculo, rubricas e remessa CNAB',              icon: 'credit-card',   url: '/folha',           isVue: true, gradFrom: '#0f766e', gradTo: '#14b8a6' },
  { title: 'Faltas e Atrasos',    desc: 'Aprovar ou descontar abonos pendentes',         icon: 'check',         url: '/faltas-atrasos',  isVue: true, gradFrom: '#b45309', gradTo: '#f59e0b' },
]

const irPara = (card) => {
  if (card.isVue) router.push(card.url)
  else window.location.href = card.url
}

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/dashboard')
    kpiData.value = data
  } catch { /* usa valores padrão */ }
  setTimeout(() => { loaded.value = true }, 80)
})
</script>

<style scoped>
.dashboard-home {
  display: flex;
  flex-direction: column;
  gap: 28px;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ═══ BOAS VINDAS ════════════════════════════════════════════════ */
.welcome-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28px 32px;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  border-radius: 20px;
  opacity: 0;
  transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.welcome-section.visible { opacity: 1; transform: translateY(0); }

.welcome-time {
  display: block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #60a5fa;
  margin-bottom: 6px;
}
.welcome-title {
  font-size: 28px;
  font-weight: 900;
  color: #fff;
  margin: 0 0 4px;
  letter-spacing: -0.02em;
}
.wave { display: inline-block; animation: wave 1.8s ease-in-out infinite; transform-origin: 70% 70%; }
@keyframes wave {
  0%, 60%, 100% { transform: rotate(0deg); }
  10%, 30% { transform: rotate(18deg); }
  20% { transform: rotate(-8deg); }
  40% { transform: rotate(-4deg); }
  50% { transform: rotate(8deg); }
}
.welcome-sub { font-size: 13px; color: #94a3b8; margin: 0; text-transform: capitalize; }

.welcome-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(16,185,129,0.15);
  border: 1px solid rgba(16,185,129,0.3);
  border-radius: 999px;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 700;
  color: #34d399;
  white-space: nowrap;
}

/* ═══ KPI CARDS ══════════════════════════════════════════════════ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #f1f5f9;
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
  opacity: 0;
  transform: translateY(16px);
  transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1) var(--delay);
}
.kpi-card.visible { opacity: 1; transform: translateY(0); }
.kpi-card:hover { box-shadow: 0 8px 30px -8px rgba(0,0,0,0.12); transform: translateY(-2px); }

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--colorLight);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.kpi-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  line-height: 1.2;
  margin-bottom: 4px;
}
.kpi-value {
  display: block;
  font-size: 16px;
  font-weight: 800;
  color: #1e293b;
  letter-spacing: -0.02em;
  line-height: 1.2;
  word-break: break-word;
  text-transform: capitalize;
}
.kpi-trend {
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 4px;
  flex-wrap: wrap;
}
.kpi-trend.up { color: #10b981; }

/* ═══ SECTION HEADER ═════════════════════════════════════════════ */
.section-header {
  opacity: 0;
  transform: translateY(8px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.section-header.visible { opacity: 1; transform: translateY(0); }
.section-title {
  font-size: 17px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
}

/* ═══ MODULES GRID ════════════════════════════════════════════════ */
.modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
}

.module-card {
  position: relative;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  cursor: pointer;
  overflow: hidden;
  opacity: 0;
  transform: translateY(16px);
  transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1) var(--delay);
}
.module-card.visible { opacity: 1; transform: translateY(0); }
.module-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 40px -8px rgba(0,0,0,0.15);
  border-color: transparent;
}
.module-card:hover .module-card-bg { opacity: 1; }
.module-card:hover .module-icon-wrap { transform: scale(1.08); }
.module-card:hover .module-arrow { opacity: 1; transform: translate(0, -50%); }

.module-card-bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, var(--gradient-from), var(--gradient-to));
  opacity: 0;
  transition: opacity 0.3s;
}
.module-card:hover .module-title,
.module-card:hover .module-desc { color: #fff !important; }

.module-icon-wrap {
  position: relative;
  z-index: 1;
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, var(--gradient-from), var(--gradient-to));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  transition: transform 0.2s;
}
.module-text { position: relative; z-index: 1; flex: 1; min-width: 0; }
.module-title { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 3px; transition: color 0.3s; }
.module-desc { font-size: 12px; color: #64748b; margin: 0; transition: color 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.module-arrow {
  position: relative;
  z-index: 1;
  opacity: 0;
  transform: translate(-8px, -50%);
  color: #fff;
  transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
  flex-shrink: 0;
}

/* ═══ MURAL ══════════════════════════════════════════════════════ */
.mural-card {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border: 1px solid #fcd34d;
  border-radius: 18px;
  padding: 22px 28px;
  display: flex;
  align-items: center;
  gap: 18px;
  opacity: 0;
  transform: translateY(10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.4s;
}
.mural-card.visible { opacity: 1; transform: translateY(0); }
.mural-icon { font-size: 28px; flex-shrink: 0; }
.mural-content { flex: 1; }
.mural-title { font-size: 15px; font-weight: 800; color: #92400e; margin: 0 0 5px; }
.mural-text { font-size: 13px; color: #78350f; margin: 0; line-height: 1.5; }
.mural-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #d97706;
  color: white;
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s;
  flex-shrink: 0;
}
.mural-btn:hover { background: #b45309; }

/* ═══ RESPONSIVE MOBILE ══════════════════════════════════════════ */
@media (max-width: 1024px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
  .dashboard-home { gap: 16px; }

  /* Welcome section empilha */
  .welcome-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    padding: 16px 18px;
  }
  .welcome-title { font-size: 20px; }
  .welcome-badge { align-self: flex-start; }

  /* KPI em 2 colunas pequenas */
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .kpi-card { padding: 14px; }
  .kpi-icon-wrap { width: 36px; height: 36px; }
  .kpi-value { font-size: 14px; }

  /* Módulos em 1 coluna */
  .modules-grid { grid-template-columns: 1fr; gap: 10px; }
  .module-card { padding: 14px 16px; }

  /* Mural empilha */
  .mural-card { flex-direction: column; gap: 12px; padding: 16px; }
  .mural-btn { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
  .kpi-card { padding: 12px; gap: 10px; }
}
</style>
