<template>
  <div class="app-shell" :class="{ 'drawer-open': drawer }">

    <!-- ═══ OVERLAY (mobile) ══════════════════════════════════════ -->
    <div class="overlay" :class="{ active: drawer }" @click="drawer = false"></div>

    <!-- ═══ SIDEBAR ═══════════════════════════════════════════════ -->
    <aside class="sidebar" :class="{ open: drawer }">

      <!-- Logo -->
      <div class="sidebar-logo">
        <div class="logo-box">
          <img src="/logo.png" alt="GENTE" class="logo-img" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 80%22><rect width=%2280%22 height=%2280%22 rx=%2216%22 fill=%22%233b82f6%22/><text y=%2250%22 x=%2240%22 text-anchor=%22middle%22 font-size=%2228%22 fill=%22white%22 font-weight=%22bold%22>G</text></svg>'"/>
        </div>
        <div class="logo-text">
          <span class="logo-name">GENTE</span>
          <span class="logo-sub">Gestão de Pessoas</span>
        </div>
        <button class="sidebar-close-btn" @click="drawer = false" aria-label="Fechar menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <!-- Avatar -->
      <div class="sidebar-profile" @click="$router.push('/meu-perfil')" style="cursor:pointer">
        <div class="avatar">{{ userInitials }}</div>
        <div class="profile-info">
          <span class="profile-name">{{ userName }}</span>
          <span class="profile-role">{{ authStore.perfilLabel }}</span>
        </div>
        <div class="profile-status">
          <div class="status-dot"></div>
        </div>
      </div>

      <!-- Busca na sidebar -->
      <div class="sidebar-search">
        <svg class="sidebar-search-ico" viewBox="0 0 24 24" fill="none" width="14" height="14">
          <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
          <path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <input
          v-model="sidebarBusca"
          class="sidebar-search-input"
          placeholder="Buscar módulo..."
          @keydown.escape="sidebarBusca = ''"
        />
        <button v-if="sidebarBusca" class="sidebar-search-clear" @click="sidebarBusca = ''">✕</button>
      </div>

      <!-- Navegação -->
      <nav class="sidebar-nav">
        <div v-if="!sidebarBusca" class="nav-section-label">Menu Principal</div>

        <template v-for="item in navItemsFiltrados" :key="item.label">
          <!-- Separador de seção -->
          <div v-if="item.type === 'section'" class="nav-section-label nav-section-sep">
            {{ item.label }}
          </div>
          <!-- Link de navegação -->
          <router-link
            v-else
            :to="item.to"
            class="nav-item"
            active-class="nav-active"
          >
            <span class="nav-icon"><AppIcon :name="item.icon" :size="18" /></span>
            <span class="nav-label">{{ item.label }}</span>
            <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
          </router-link>
        </template>


      </nav>

      <!-- Logout -->
      <div class="sidebar-footer">
        <button class="logout-btn" @click="handleLogout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Sair do Sistema
        </button>
        <p class="sidebar-credit">Developed by <strong>RR Tecnol</strong></p>
      </div>
    </aside>

    <!-- ═══ CONTEÚDO PRINCIPAL ════════════════════════════════════ -->
    <div class="main-content">

      <!-- ─── HEADER / TOPBAR ─────────────────────────────────── -->
      <header class="topbar">
        <button class="hamburger" @click="drawer = !drawer" :class="{ active: drawer }">
          <span></span><span></span><span></span>
        </button>

        <div class="topbar-breadcrumb">
          <span class="breadcrumb-icon"><AppIcon :name="currentRoute.icon" :size="16" color="#64748b" /></span>
          <span class="breadcrumb-label">{{ currentRoute.label }}</span>
        </div>

        <div class="topbar-actions">

          <!-- Sudo: visão global (cabeçalho HTTP) — topbar, sempre visível para quem tem can_bypass_tenant -->
          <div
            v-if="authStore.canBypassTenant"
            class="sudo-global-pill"
            :class="{ 'sudo-on': globalViewSudoModel }"
            :title="sudoGlobalTitle"
          >
            <label class="sudo-global-label">
              <input
                v-model="globalViewSudoModel"
                type="checkbox"
                class="sudo-global-input"
                aria-label="Ativar visão global (Sudo) para dados de toda a prefeitura; uso auditado"
              />
              <span class="sudo-global-ico" aria-hidden="true">⛨</span>
              <span class="sudo-global-text">Visão global</span>
            </label>
          </div>

          <!-- Fase 8A: acúmulo — escolher matrícula / vínculo activo (cabeçalho nas APIs) -->
          <div
            v-if="authStore.temMultiplosVinculosFuncionario"
            class="fnctx-pill"
            :title="fnctxTitle"
          >
            <label class="fnctx-label">
              <span class="fnctx-ico" aria-hidden="true">👤</span>
              <span class="fnctx-text">Vínculo</span>
              <select
                class="fnctx-select"
                :value="String(authStore.funcionarioContextId || '')"
                aria-label="Seleccionar vínculo ou matrícula activa"
                @change="onFuncionarioContextChange($event)"
              >
                <option
                  v-for="v in authStore.funcionarioVinculos"
                  :key="v.id"
                  :value="String(v.id)"
                >
                  {{ (v.matricula || v.id) + (v.nome ? ' — ' + v.nome : '') }}
                </option>
              </select>
            </label>
          </div>

          <!-- ── Sininho de Notificações ─────────────────────────── -->
          <div class="notif-wrap" ref="notifWrap">
            <button class="action-btn" title="Notificações" @click="toggleNotifPanel">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M15 17H20L18.6 15.6A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 00-4 0v.34A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
              <span v-if="naoLidas > 0" class="notif-badge">{{ naoLidas > 9 ? '9+' : naoLidas }}</span>
            </button>

            <!-- Dropdown -->
            <transition name="notif-drop">
              <div v-if="notifPanelOpen" class="notif-panel">
                <div class="np-header">
                  <span class="np-title">🔔 Notificações</span>
                  <button v-if="naoLidas > 0" class="np-read-all" @click="marcarTodasLidas">✓ Marcar todas</button>
                </div>

                <div class="np-list" v-if="notificacoes.length">
                  <div
                    v-for="n in notificacoes.slice(0,8)"
                    :key="n.id"
                    class="np-item"
                    :class="{ 'np-unread': !n.lida }"
                    @click="abrirNotif(n)"
                  >
                    <div class="np-icone" :class="'np-' + n.tipo">{{ n.icone }}</div>
                    <div class="np-body">
                      <div class="np-item-titulo">{{ n.titulo }}</div>
                      <div class="np-item-body" v-if="n.body">{{ n.body }}</div>
                      <div class="np-item-tempo">{{ tempoRelativo(n.criada_em) }}</div>
                    </div>
                    <div v-if="!n.lida" class="np-unread-dot"></div>
                  </div>
                </div>
                <div v-else class="np-empty">Nenhuma notificação 🎉</div>
                <div v-if="notifErro" class="np-empty" style="color:#b91c1c;border-top:1px solid #fee2e2;background:#fef2f2;">
                  {{ notifErro }}
                </div>

                <div class="np-footer">
                  <router-link to="/notificacoes" class="np-ver-todas" @click="notifPanelOpen = false">Ver todas →</router-link>
                </div>
              </div>
            </transition>
          </div>

          <button class="action-btn" title="Configurações" @click="$router.push('/configuracoes')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M10.3 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v5M12 12a3 3 0 100-6 3 3 0 000 6zM19.7 17l-1.4-1.4M21 19a2 2 0 11-4 0 2 2 0 014 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </button>
          <div class="topbar-avatar" :title="userName" @click="$router.push('/meu-perfil')" style="cursor:pointer">{{ userInitials }}</div>
        </div>
      </header>

      <!-- Banner SEMAD (trava de edição na manta; chapéu duplo TI+SEMAD não exibe) -->
      <div
        v-if="authStore.semadMantaUiReadonlyForShell"
        class="semad-audit-banner"
        role="status"
      >
        <span class="semad-audit-ico" aria-hidden="true">🛡️</span>
        <span><strong>Modo Auditoria:</strong> somente leitura — operações de criação, edição e exclusão estão desactivadas nas áreas protegidas.</span>
      </div>
      <div v-if="rbacDeniedToast" class="rbac-denied-toast" role="alert">
        {{ rbacDeniedToast }}
      </div>

      <!-- ─── PÁGINA ──────────────────────────────────────────── -->
      <main class="page-content">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/store/auth.js'
import AppIcon from '@/components/AppIcon.vue'
import api from '@/plugins/axios'
import { NAV_MANIFEST, canAccessNavEntry, canAccessNavSection } from '@/navigation/navManifest.js'
import { registerMobileDrawerClose } from '@/navigation/mobileDrawerBus.js'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const rbacDeniedToast = ref('')

const globalViewSudoModel = computed({
  get: () => authStore.isGlobalViewActive,
  set: (v) => authStore.setGlobalViewActive(v)
})

const sudoGlobalTitle = computed(() =>
  globalViewSudoModel.value
    ? 'Visão global (Sudo) ativa: as requisições enviam o cabeçalho; o acesso amplo pode ser auditado no servidor.'
    : 'Ligar a visão global: lista completa de setores/organograma. O backend exige o cabeçalho; uso registrado em auditoria.'
)

const fnctxTitle = 'Vínculo activo: lotação e dados de contexto seguem esta matrícula. O servidor valida que o vínculo pertence ao seu utilizador.'

async function onFuncionarioContextChange(ev) {
  const v = ev?.target?.value
  if (v == null || v === '') {
    return
  }
  await authStore.setFuncionarioContext(v)
}

// ── Detecção de mobile reativa ────────────────────────────────────
// Usa matchMedia para reagir ao resize do browser corretamente
const mobileQuery = typeof window !== 'undefined'
  ? window.matchMedia('(max-width: 767px)')
  : null
const isMobileNow = () => mobileQuery?.matches ?? false

// Sidebar: fechado em mobile, aberto em desktop
const drawer = ref(!isMobileNow())

// Atualiza o drawer quando o viewport muda (resize, DevTools, rotação)
const onViewportChange = (e) => {
  // Em mobile → fecha; em desktop → abre automaticamente
  drawer.value = !e.matches
}
if (mobileQuery) mobileQuery.addEventListener('change', onViewportChange)

onUnmounted(() => {
  if (mobileQuery) mobileQuery.removeEventListener('change', onViewportChange)
})

// Fecha o sidebar ao navegar (mobile)
watch(() => route.path, () => {
  if (isMobileNow()) drawer.value = false
})

watch(
  () => route.query,
  (query) => {
    if (query?.denied !== 'rbac') return
    const code = String(query?.code || '')
    rbacDeniedToast.value =
      code === 'required_slug_missing'
        ? 'Acesso negado: o seu perfil não possui o passaporte RBAC necessário para esse módulo.'
        : 'Acesso negado: o seu perfil não possui permissão para esse módulo.'
    setTimeout(() => {
      rbacDeniedToast.value = ''
    }, 4500)
    const cleanedQuery = { ...route.query }
    delete cleanedQuery.denied
    delete cleanedQuery.code
    router.replace({ query: cleanedQuery })
  },
  { immediate: true }
)

// Sempre busca o perfil atualizado do servidor ao montar o layout
// (garante que mudanças de perfil sejam refletidas sem logout)
if (!authStore.user) authStore.fetchUser()

// ── Notificações ────────────────────────────────────────────────
const notifPanelOpen = ref(false)
const notifWrap = ref(null)
const notificacoes = ref([])
const naoLidas = ref(0)
const notifErro = ref('')
const pendenciasSubstituicao = ref(0)
let notifInterval = null
let pendenciasInterval = null

const fetchNotif = async () => {
  try {
    const { data } = await api.get('/api/v3/notificacoes')
    notificacoes.value = data.notificacoes ?? []
    naoLidas.value = data.nao_lidas ?? 0
    notifErro.value = ''
  } catch (e) {
    notifErro.value = e?.response?.data?.erro || 'Falha ao atualizar notificações.'
  }
}

const fetchPendenciasSubstituicao = async () => {
  try {
    const { data } = await api.get('/api/v3/substituicoes/minhas')
    pendenciasSubstituicao.value = Array.isArray(data?.pendentes) ? data.pendentes.length : 0
  } catch {
    pendenciasSubstituicao.value = 0
  }
}

const toggleNotifPanel = async () => {
  notifPanelOpen.value = !notifPanelOpen.value
  if (notifPanelOpen.value) await fetchNotif()
}

const abrirNotif = async (n) => {
  if (!n.lida) {
    try {
      await api.put(`/api/v3/notificacoes/${n.id}/lida`)
      n.lida = true
      naoLidas.value = Math.max(0, naoLidas.value - 1)
      notifErro.value = ''
    } catch (e) {
      notifErro.value = e?.response?.data?.erro || 'Falha ao marcar notificação como lida.'
    }
  }
  if (n.url) { notifPanelOpen.value = false; router.push(n.url) }
}

const marcarTodasLidas = async () => {
  try {
    await api.put('/api/v3/notificacoes/lidas')
    notificacoes.value.forEach(n => n.lida = true)
    naoLidas.value = 0
    notifErro.value = ''
  } catch (e) {
    notifErro.value = e?.response?.data?.erro || 'Falha ao marcar todas as notificações.'
  }
}

const tempoRelativo = (iso) => {
  if (!iso) return ''
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'agora'
  if (diff < 3600) return `${Math.floor(diff/60)}min`
  if (diff < 86400) return `${Math.floor(diff/3600)}h`
  return `${Math.floor(diff/86400)}d`
}

// Fechar ao clicar fora
const clickFora = (e) => { if (notifWrap.value && !notifWrap.value.contains(e.target)) notifPanelOpen.value = false }

onMounted(async () => {
  registerMobileDrawerClose(() => {
    if (isMobileNow()) drawer.value = false
  })
  // Re-busca o perfil do servidor (para pegar mudanças de perfil sem logout)
  await authStore.fetchUser()
  fetchNotif()
  fetchPendenciasSubstituicao()
  notifInterval = setInterval(() => { if (!document.hidden) fetchNotif() }, 60_000) // pausa em aba background
  pendenciasInterval = setInterval(() => { if (!document.hidden) fetchPendenciasSubstituicao() }, 90_000)
  document.addEventListener('click', clickFora)
})
onUnmounted(() => {
  registerMobileDrawerClose(null)
  clearInterval(notifInterval)
  clearInterval(pendenciasInterval)
  document.removeEventListener('click', clickFora)
})

const userName = computed(() => authStore.user?.nome || 'Usuário')
const userInitials = computed(() => {
  const w = userName.value.split(' ')
  return w.length >= 2 ? (w[0][0] + w[1][0]).toUpperCase() : w[0].substring(0, 2).toUpperCase()
})

const sidebarBusca = ref('')

/** Itens com link na sidebar (exclui entradas só para gate do router). */
const SIDEBAR_NAV_ITEMS = NAV_MANIFEST.filter(
  (row) => row.type !== 'item' || (row.to && row.sidebar !== false)
)

const navItemsFiltrados = computed(() => {
  const result = []
  let lastSection = null

  for (const item of SIDEBAR_NAV_ITEMS) {
    if (item.type === 'section') {
      lastSection = canAccessNavSection(authStore, item) ? item : null
    } else if (item.to && canAccessNavEntry(authStore, item)) {
      if (lastSection) {
        result.push(lastSection)
        lastSection = null
      }
      const itemComBadge = { ...item }
      if (item.to === '/minhas-substituicoes') {
        itemComBadge.badge = pendenciasSubstituicao.value > 0
          ? (pendenciasSubstituicao.value > 99 ? '99+' : String(pendenciasSubstituicao.value))
          : ''
      }
      result.push(itemComBadge)
    }
  }

  if (sidebarBusca.value.trim()) {
    const termo = sidebarBusca.value.toLowerCase().trim()
    const filtrado = []
    let secaoAtual = null
    for (const item of result) {
      if (item.type === 'section') {
        secaoAtual = item
      } else if (item.label?.toLowerCase().includes(termo)) {
        if (secaoAtual) { filtrado.push(secaoAtual); secaoAtual = null }
        filtrado.push(item)
      }
    }
    return filtrado
  }

  return result
})


const routeMap = {
  '/dashboard':                  { label: 'Dashboard',                  icon: 'dashboard' },
  '/funcionarios':               { label: 'Funcionários',               icon: 'users' },
  '/autocadastro-gestao':        { label: 'Autocadastro',               icon: 'user-plus' },
  '/organograma':                { label: 'Estrutura Organizacional',  icon: 'organogram' },
  '/cargos-salarios':            { label: 'Cargos e Salários',          icon: 'briefcase' },
  '/contratos-vinculos':         { label: 'Contratos e Vínculos',       icon: 'contract' },
  '/progressao-funcional':       { label: 'Minha Progressão',           icon: 'trending' },
  '/progressao-admin':           { label: 'Gerir Progressões',          icon: 'badge' },
  '/avaliacao-desempenho':       { label: 'Avaliação de Desempenho',    icon: 'star' },
  '/treinamentos':               { label: 'Treinamentos',               icon: 'school' },
  '/medicina-trabalho':          { label: 'Medicina do Trabalho',       icon: 'stethoscope' },
  '/seguranca-trabalho':         { label: 'Segurança do Trabalho',      icon: 'shield' },
  '/avaliacao-gestor':           { label: 'Avaliações da Equipe',       icon: 'star' },
  '/beneficios':                 { label: 'Gestão de Benefícios',       icon: 'zap' },
  '/beneficios-admin':            { label: 'Benefícios (Financeiro)',   icon: 'gift' },
  '/treinamentos-admin':         { label: 'Gestão de Treinamentos',     icon: 'school' },
  '/medicina-admin':             { label: 'Gestão SESMT',               icon: 'stethoscope' },
  '/seguranca-admin':            { label: 'Segurança SESMT',            icon: 'shield' },
  '/ponto':                      { label: 'Ponto Eletrônico',           icon: 'clock' },
  '/banco-horas':                { label: 'Banco de Horas',             icon: 'hourglass' },
  '/meus-holerites':             { label: 'Meus Holerites',             icon: 'money' },
  '/abono-faltas':               { label: 'Abono de Faltas',            icon: 'check' },
  '/atestados-medicos':          { label: 'Atestados Médicos',          icon: 'hospital' },
  '/faltas-atrasos':             { label: 'Faltas e Atrasos',           icon: 'warning' },
  '/ferias-licencas':            { label: 'Férias e Licenças',          icon: 'beach' },
  '/frequencia':                 { label: 'Controle de Frequência',     icon: 'clipboard-check' },
  '/remessa-cnab':               { label: 'Remessa CNAB 240',           icon: 'bank' },
  '/escala-trabalho':            { label: 'Escalas',                    icon: 'calendar-week' },
  '/escala-matriz-v3':           { label: 'Escalas',                    icon: 'calendar-week' },
  '/substituicoes':              { label: 'Gestão de Substituições',            icon: 'swap' },
  '/minhas-substituicoes':       { label: 'Minhas Substituições',   icon: 'swap' },
  '/escala-sobreaviso':          { label: 'Sobreaviso',                 icon: 'phone' },
  '/plantoes-extras':            { label: 'Horas / Plantões Extras',    icon: 'plus' },
  '/exoneracao':                 { label: 'Exoneração / Rescisão',      icon: 'exit' },
  '/hora-extra':                 { label: 'Hora Extra',                 icon: 'clock' },
  '/folha-pagamento':            { label: 'Folha de Pagamento',         icon: 'credit-card' },
  '/agenda':                     { label: 'Agenda',                     icon: 'agenda' },
  '/relatorios':                 { label: 'Relatórios',                 icon: 'chart' },
  '/portal-gestor':              { label: 'Portal do Gestor',           icon: 'tie-person' },
  '/comunicados':                { label: 'Comunicados',                icon: 'megaphone' },
  '/pesquisa-satisfacao':        { label: 'Pesquisa de Satisfação',     icon: 'poll' },
  '/pesquisa-admin':             { label: 'Gerenciar Pesquisas',        icon: 'edit' },
  '/ouvidoria':                  { label: 'Ouvidoria',                  icon: 'comment' },
  '/ouvidoria-admin':            { label: 'Painel Ouvidoria',           icon: 'shield' },
  '/notificacoes':               { label: 'Notificações',               icon: 'bell' },
  '/configuracoes':              { label: 'Configurações Gerais',       icon: 'settings' },
  '/meu-perfil':                 { label: 'Meu Perfil',                 icon: 'user' },
  '/declaracoes-requerimentos':  { label: 'Declarações e Requerimentos', icon: 'doc' },
  '/gestao-declaracoes':         { label: 'Gestão de Declarações',      icon: 'clipboard' },
  // Módulos antes ausentes da sidebar
  '/rpps':                       { label: 'RPPS / IPAM',                icon: 'bank' },
  '/diarias':                    { label: 'Diárias',                    icon: 'map-pin' },
  '/acumulacao-cargos':          { label: 'Acumulação de Cargos',       icon: 'layers' },
  '/transparencia':              { label: 'Transparência Pública',      icon: 'eye' },
  '/pss':                        { label: 'PSS / Concurso',             icon: 'school' },
  '/estagiarios':                { label: 'Estagiários',                icon: 'student' },
  '/terceirizados':              { label: 'Terceirizados',              icon: 'briefcase' },
  '/sagres-tce':                 { label: 'SAGRES / TCE-MA',            icon: 'chart' },
  // Configurações antes ausentes
  '/configuracao-sistema':       { label: 'Motor de Folha',             icon: 'cpu' },
  '/parametros-financeiros':     { label: 'Parâmetros Financeiros',     icon: 'sliders' },
  '/vinculos':                   { label: 'Vínculos',                   icon: 'link' },
  '/turnos':                     { label: 'Turnos',                     icon: 'clock' },
  '/feriados':                   { label: 'Feriados e Folgas',          icon: 'calendar' },
  '/tabelas-auxiliares':         { label: 'Tabelas Auxiliares',         icon: 'table' },
  '/eventos-folha':              { label: 'Eventos de Folha',           icon: 'list' },
  '/oss':                        { label: 'Monitor OSS',                icon: 'activity' },
}
const currentRoute = computed(() => routeMap[route.path] || { label: 'Módulo', icon: 'mdi-circle' })

const handleLogout = async () => {
  await authStore.logout?.()
  router.push('/login')
}
</script>

<style scoped>
/* ═══ LAYOUT SHELL ══════════════════════════════════════════════ */
.app-shell {
  /* Tokens visuais «São Luís premium» — aplicados incrementalmente ao shell */
  --gente-sl-ocean-deep: #0a1f33;
  --gente-sl-ocean-mid: #0f2d4a;
  --gente-sl-ocean-soft: #164e63;
  --gente-sl-sand-warm: #e8dcc4;
  --gente-sl-palm: #166534;
  --gente-sl-sky: #38bdf8;
  --gente-sl-shell-bg: #f4f8fb;

  display: flex;
  min-height: 100vh;
  background: var(--gente-sl-shell-bg);
  font-family: 'Inter', system-ui, sans-serif;
}

/* ═══ OVERLAY MOBILE ═════════════════════════════════════════════ */
.overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  z-index: 40;
  opacity: 0;
  pointer-events: none;   /* impede captura de cliques/toques quando o drawer está fechado */
  transition: opacity 0.3s;
}

/* ═══ SIDEBAR ════════════════════════════════════════════════════ */
.sidebar {
  width: 260px;
  min-width: 260px;
  background: linear-gradient(
    180deg,
    var(--gente-sl-ocean-deep) 0%,
    var(--gente-sl-ocean-mid) 52%,
    var(--gente-sl-ocean-soft) 100%
  );
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  z-index: 50;
  transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  overflow: hidden;
}

/* Efeito de brilho sutil no topo */
.sidebar::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(99,102,241,0.6), transparent);
}

/* ─── LOGO ─────────────────────────────────────────────────────── */
.sidebar-close-btn {
  display: none;
  align-items: center;
  justify-content: center;
  margin-left: auto;
  width: 32px;
  height: 32px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  color: rgba(255,255,255,0.7);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
}
.sidebar-close-btn:hover { background: rgba(255,255,255,0.15); }

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 20px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.logo-box {
  width: 44px;
  height: 44px;
  background: rgba(255,255,255,0.95);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.logo-img { width: 32px; height: 32px; object-fit: contain; }
.logo-name {
  display: block;
  font-size: 16px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 0.06em;
  line-height: 1;
}
.logo-sub { display: block; font-size: 10px; color: var(--gente-sl-sky); font-weight: 600; margin-top: 2px; }

/* ─── PROFILE ──────────────────────────────────────────────────── */
.sidebar-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  margin: 12px 12px 4px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  backdrop-filter: blur(4px);
}
.avatar {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--gente-sl-sky), #6366f1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}
.profile-name { display: block; font-size: 13px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.profile-role { display: block; font-size: 10px; color: #60a5fa; font-weight: 600; margin-top: 1px; }
.profile-status { margin-left: auto; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

/* ─── NAV ──────────────────────────────────────────────────────── */
.sidebar-nav { flex: 1; padding: 8px 12px; overflow-y: auto; scrollbar-width: none; }
.sidebar-nav::-webkit-scrollbar { display: none; }

.nav-section-label {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #475569;
  padding: 12px 8px 6px;
}
.nav-section-sep {
  margin-top: 6px;
  padding-top: 14px;
  border-top: 1px solid rgba(255,255,255,0.06);
  position: relative;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  margin-bottom: 2px;
  cursor: pointer;
  text-decoration: none;
  color: #94a3b8;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.18s;
  position: relative;
}
.nav-item:hover {
  background: rgba(255,255,255,0.07);
  color: #e2e8f0;
}
.nav-active {
  background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(99,102,241,0.15)) !important;
  color: #fff !important;
  font-weight: 700 !important;
}
.nav-active .nav-icon { color: #60a5fa !important; }
.nav-active::before {
  content: '';
  position: absolute;
  left: 0; top: 6px; bottom: 6px;
  width: 3px;
  background: linear-gradient(180deg, #3b82f6, #6366f1);
  border-radius: 0 3px 3px 0;
}
.nav-icon { display: flex; align-items: center; flex-shrink: 0; }
.nav-label { flex: 1; }
.nav-badge {
  background: #3b82f6;
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
}
.nav-external { color: #475569; margin-left: auto; }
.nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 8px 0; }

/* ─── FOOTER ───────────────────────────────────────────────────── */
.sidebar-footer {
  padding: 16px 12px;
  border-top: 1px solid rgba(255,255,255,0.06);
}
.logout-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 14px;
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.2);
  border-radius: 12px;
  color: #f87171;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.logout-btn:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }
.sidebar-credit { font-size: 10px; color: #475569; text-align: center; margin: 10px 0 0; }
.sidebar-credit strong { color: #64748b; }

/* ═══ MAIN CONTENT ═══════════════════════════════════════════════ */
.main-content {
  flex: 1;
  min-width: 0; /* permite encolher abaixo do min-content da grade larga → scroll interno */
  margin-left: 260px;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  transition: margin-left 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

/* ─── TOPBAR ─────────────────────────────────────────────────────── */
.topbar {
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 28px;
  height: 60px;
  background: rgba(248, 250, 252, 0.9);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid #e2e8f0;
}

.hamburger {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
  border: none;
  background: none;
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  transition: background 0.2s;
}
.hamburger:hover { background: #f1f5f9; }
.hamburger span {
  display: block;
  width: 20px;
  height: 2px;
  background: #64748b;
  border-radius: 2px;
  transition: all 0.25s;
}
.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(4px, 4px); }
.hamburger.active span:nth-child(2) { opacity: 0; }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(4px, -4px); }

.topbar-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}
.breadcrumb-label {
  font-size: 15px;
  font-weight: 700;
  color: #334155;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
/* Sudo: visão global (topbar — ponto operacional, sem ocupar a sidebar) */
.sudo-global-pill {
  display: flex;
  align-items: center;
  margin-right: 4px;
  padding: 0 2px 0 6px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  transition: border-color 0.15s, background 0.15s;
}
.sudo-global-pill.sudo-on {
  border-color: #a78bfa;
  background: #f5f3ff;
}
.sudo-global-label {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  user-select: none;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  padding: 4px 6px 4px 2px;
  white-space: nowrap;
}
.sudo-on .sudo-global-label { color: #5b21b6; }
.sudo-global-input {
  width: 16px;
  height: 16px;
  accent-color: #7c3aed;
  cursor: pointer;
}
.sudo-global-ico {
  font-size: 13px;
  line-height: 1;
  opacity: 0.75;
}
@media (max-width: 767px) {
  .sudo-global-text { display: none; }
  .sudo-global-pill { padding: 0 2px; }
  .sudo-global-label { padding: 6px 4px; }
}

.fnctx-pill {
  display: flex;
  align-items: center;
  margin-right: 4px;
  padding: 0 4px 0 6px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}
.fnctx-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  padding: 2px 2px 2px 0;
  white-space: nowrap;
}
.fnctx-ico { font-size: 13px; line-height: 1; opacity: 0.8; }
.fnctx-select {
  max-width: 200px;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 4px 8px;
  background: #fff;
  cursor: pointer;
}
@media (max-width: 767px) {
  .fnctx-text { display: none; }
  .fnctx-select { max-width: 140px; }
}

.action-btn {
  position: relative;
  width: 36px;
  height: 36px;
  border: none;
  background: none;
  cursor: pointer;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  transition: all 0.18s;
}
.action-btn:hover { background: #f1f5f9; color: #334155; }

/* ── NOTIFICAÇÕES DROPDOWN ─────────────────────────────────────── */
.notif-wrap { position: relative; }
.notif-badge {
  position: absolute; top: 5px; right: 5px;
  min-width: 16px; height: 16px; padding: 0 4px;
  background: #ef4444; color: #fff;
  border-radius: 99px; border: 2px solid #f8fafc;
  font-size: 9px; font-weight: 800; line-height: 12px;
  text-align: center;
}
.notif-panel {
  position: absolute; top: calc(100% + 12px); right: 0;
  width: 320px; background: #fff;
  border: 1px solid #e2e8f0; border-radius: 18px;
  box-shadow: 0 24px 64px -12px rgba(15,23,42,0.2);
  z-index: 200; overflow: hidden;
}
.np-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px 10px; border-bottom: 1px solid #f1f5f9;
}
.np-title { font-size: 13px; font-weight: 800; color: #1e293b; }
.np-read-all {
  font-size: 11px; font-weight: 700; color: #3b82f6;
  border: none; background: none; cursor: pointer; padding: 4px 8px;
  border-radius: 8px; transition: background 0.15s;
}
.np-read-all:hover { background: #eff6ff; }
.np-list { max-height: 340px; overflow-y: auto; }
.np-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 14px; cursor: pointer; transition: background 0.12s;
  border-bottom: 1px solid #f8fafc;
}
.np-item:hover { background: #f8fafc; }
.np-unread { background: #fafeff; }
.np-icone {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.np-info    { background: #eff6ff; }
.np-success { background: #f0fdf4; }
.np-warning { background: #fffbeb; }
.np-error   { background: #fef2f2; }
.np-body { flex: 1; min-width: 0; }
.np-item-titulo { font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.np-item-body   { font-size: 11px; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.np-item-tempo  { font-size: 10px; color: #94a3b8; margin-top: 3px; font-weight: 600; }
.np-unread-dot  { width: 7px; height: 7px; background: #3b82f6; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.np-empty { text-align: center; padding: 32px 16px; color: #94a3b8; font-size: 13px; }
.np-footer { border-top: 1px solid #f1f5f9; padding: 10px 16px; }
.np-ver-todas {
  display: block; text-align: center; font-size: 12px; font-weight: 700;
  color: #3b82f6; text-decoration: none; padding: 4px;
  border-radius: 8px; transition: background 0.15s;
}
.np-ver-todas:hover { background: #eff6ff; }
/* Animação do dropdown */
.notif-drop-enter-active, .notif-drop-leave-active { transition: all 0.2s cubic-bezier(0.22,1,0.36,1); }
.notif-drop-enter-from, .notif-drop-leave-to { opacity: 0; transform: translateY(-8px) scale(0.97); }
.topbar-avatar {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: white;
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  margin-left: 4px;
}

/* Banner auditoria SEMAD */
.semad-audit-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 0 28px;
  padding: 10px 14px;
  border-radius: 10px;
  background: linear-gradient(90deg, #fef3c7, #fffbeb);
  border: 1px solid #f59e0b;
  color: #78350f;
  font-size: 13px;
  line-height: 1.45;
}
.semad-audit-ico { flex-shrink: 0; }
.rbac-denied-toast {
  margin: 10px 28px 0;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #fecaca;
  background: #fff1f2;
  color: #9f1239;
  font-size: 13px;
  font-weight: 600;
}

/* ─── PAGE CONTENT ─────────────────────────────────────────────── */
.page-content {
  flex: 1;
  min-width: 0;
  padding: 28px;
  overflow-x: auto;
  overflow-y: auto;
  width: 100%;
  max-width: 1680px;
  margin: 0 auto;
  box-sizing: border-box;
}

/* ═══ RESPONSIVE ═════════════════════════════════════════════════ */
@media (max-width: 768px) {
  /* Sidebar: largura fluida em vez de 260px fixo */
  .sidebar {
    width: min(300px, 85vw);
    min-width: unset;
    transform: translateX(-100%);
  }
  .sidebar.open { transform: translateX(0); }

  /* Mostra o botão fechar */
  .sidebar-close-btn { display: flex; }

  .main-content { margin-left: 0 !important; }
  .overlay { display: block; }
  .overlay.active { opacity: 1; pointer-events: all; }

  /* Topbar compacta */
  .topbar { padding: 0 14px; height: 52px; }
  .breadcrumb-label { font-size: 13px; }

  /* Page content com menos padding */
  .page-content { padding: 14px; }

  /* Notif panel ocupa mais espaço em telas pequenas */
  .notif-panel { width: calc(100vw - 28px); right: -14px; }

  /* Banner auditoria */
  .semad-audit-banner { margin: 0 12px; font-size: 12px; }
  .rbac-denied-toast { margin: 8px 12px 0; font-size: 12px; }
}

@media (max-width: 480px) {
  .page-content { padding: 10px; }
  .topbar { padding: 0 10px; }

  /* Avatar no topbar some em telas muito pequenas */
  .topbar-avatar { display: none; }
}

/* ── Busca na sidebar ───────────────────────────────────────────── */
.sidebar-search {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 12px 8px;
  padding: 7px 10px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  transition: border-color 0.2s;
}
.sidebar-search:focus-within {
  border-color: rgba(99, 102, 241, 0.6);
  background: rgba(255, 255, 255, 0.1);
}
.sidebar-search-ico { color: rgba(255,255,255,0.4); flex-shrink: 0; }
.sidebar-search-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 12px;
  color: rgba(255,255,255,0.85);
  font-family: inherit;
}
.sidebar-search-input::placeholder { color: rgba(255,255,255,0.35); }
.sidebar-search-clear {
  background: none;
  border: none;
  color: rgba(255,255,255,0.4);
  cursor: pointer;
  font-size: 11px;
  padding: 0;
  line-height: 1;
  transition: color 0.15s;
}
.sidebar-search-clear:hover { color: rgba(255,255,255,0.8); }
</style>
