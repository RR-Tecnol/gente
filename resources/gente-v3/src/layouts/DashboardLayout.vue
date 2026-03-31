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
      </div>

      <!-- Avatar -->
      <div class="sidebar-profile">
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

                <div class="np-footer">
                  <router-link to="/notificacoes" class="np-ver-todas" @click="notifPanelOpen = false">Ver todas →</router-link>
                </div>
              </div>
            </transition>
          </div>

          <button class="action-btn" title="Configurações" @click="$router.push('/configuracoes')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M10.3 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v5M12 12a3 3 0 100-6 3 3 0 000 6zM19.7 17l-1.4-1.4M21 19a2 2 0 11-4 0 2 2 0 014 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </button>
          <div class="topbar-avatar" :title="userName">{{ userInitials }}</div>
        </div>
      </header>

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

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

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

// Sempre busca o perfil atualizado do servidor ao montar o layout
// (garante que mudanças de perfil sejam refletidas sem logout)
if (!authStore.user) authStore.fetchUser()

// ── Notificações ────────────────────────────────────────────────
const notifPanelOpen = ref(false)
const notifWrap = ref(null)
const notificacoes = ref([])
const naoLidas = ref(0)
let notifInterval = null

const fetchNotif = async () => {
  try {
    const { data } = await api.get('/api/v3/notificacoes')
    notificacoes.value = data.notificacoes ?? []
    naoLidas.value = data.nao_lidas ?? 0
  } catch { /* silencia falhas de polling */ }
}

const toggleNotifPanel = async () => {
  notifPanelOpen.value = !notifPanelOpen.value
  if (notifPanelOpen.value) await fetchNotif()
}

const abrirNotif = async (n) => {
  if (!n.lida) {
    try { await api.put(`/api/v3/notificacoes/${n.id}/lida`); n.lida = true; naoLidas.value = Math.max(0, naoLidas.value - 1) } catch {}
  }
  if (n.url) { notifPanelOpen.value = false; router.push(n.url) }
}

const marcarTodasLidas = async () => {
  try {
    await api.put('/api/v3/notificacoes/lidas')
    notificacoes.value.forEach(n => n.lida = true)
    naoLidas.value = 0
  } catch {}
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
  // Re-busca o perfil do servidor (para pegar mudanças de perfil sem logout)
  await authStore.fetchUser()
  fetchNotif()
  notifInterval = setInterval(() => { if (!document.hidden) fetchNotif() }, 60_000) // pausa em aba background
  document.addEventListener('click', clickFora)
})
onUnmounted(() => {
  clearInterval(notifInterval)
  document.removeEventListener('click', clickFora)
})

const userName = computed(() => authStore.user?.nome || 'Usuário')
const userInitials = computed(() => {
  const w = userName.value.split(' ')
  return w.length >= 2 ? (w[0][0] + w[1][0]).toUpperCase() : w[0].substring(0, 2).toUpperCase()
})

// Mapa de roles para cada item do menu:
// roles vazio [] = visível para todos os usuários autenticados
// ['admin'] = só admin, ['admin','rh'] = admin e rh, etc.
const sidebarBusca = ref('')

const ALL_NAV_ITEMS = [

  // ═══════════════════════════════════════════════════════════════
  // VISÃO GERAL — todos os perfis
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Visão Geral' },
  { type: 'item', to: '/dashboard',
    label: 'Dashboard', icon: 'dashboard', roles: [] },

  // ═══════════════════════════════════════════════════════════════
  // MINHA ÁREA — o que o funcionário vê sobre si mesmo
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Minha Área' },
  { type: 'item', to: '/meu-perfil',
    label: 'Meu Perfil', icon: 'user', roles: [] },
  { type: 'item', to: '/ponto',
    label: 'Ponto Eletrônico', icon: 'clock', roles: [] },
  { type: 'item', to: '/meus-holerites',
    label: 'Meus Holerites', icon: 'money', roles: [] },
  { type: 'item', to: '/ferias-licencas',
    label: 'Férias e Licenças', icon: 'beach', roles: [] },
  { type: 'item', to: '/banco-horas',
    label: 'Banco de Horas', icon: 'hourglass', roles: [] },
  { type: 'item', to: '/declaracoes-requerimentos',
    label: 'Declarações', icon: 'doc', roles: [] },
  { type: 'item', to: '/progressao-funcional',
    label: 'Minha Progressão', icon: 'trending', roles: [] },

  // ═══════════════════════════════════════════════════════════════
  // MINHA EQUIPE — gestor de setor
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Minha Equipe',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/portal-gestor',
    label: 'Portal do Gestor', icon: 'tie-person',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/organograma',
    label: 'Organograma', icon: 'organogram',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-trabalho',
    label: 'Escala de Trabalho', icon: 'calendar',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-matriz-v3',
    label: 'Escalas Hospitalares', icon: 'calendar-week',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/substituicoes',
    label: 'Substituições', icon: 'swap',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/escala-sobreaviso',
    label: 'Sobreaviso', icon: 'phone',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/hora-extra',
    label: 'Hora Extra', icon: 'clock',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/plantoes-extras',
    label: 'Plantões Extras', icon: 'plus',
    roles: ['admin', 'rh', 'gestor'] },

  // ═══════════════════════════════════════════════════════════════
  // RECURSOS HUMANOS — cadastros e gestão de pessoal
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Recursos Humanos',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/funcionarios',
    label: 'Funcionários', icon: 'users',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/autocadastro-gestao',
    label: 'Autocadastro', icon: 'user-plus',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/cargos-salarios',
    label: 'Cargos e Salários', icon: 'briefcase',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/contratos-vinculos',
    label: 'Contratos e Vínculos', icon: 'contract',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/progressao-admin',
    label: 'Gerir Progressões', icon: 'badge',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/exoneracao',
    label: 'Exoneração / Rescisão', icon: 'exit',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pss',
    label: 'PSS / Concurso', icon: 'school',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/estagiarios',
    label: 'Estagiários', icon: 'student',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/terceirizados',
    label: 'Terceirizados', icon: 'briefcase',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/acumulacao-cargos',
    label: 'Acumulação de Cargos', icon: 'layers',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/diarias',
    label: 'Diárias', icon: 'map-pin',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/avaliacao-gestor',
    label: 'Avaliações da Equipe', icon: 'star',
    roles: ['admin', 'rh', 'gestor'] },
  { type: 'item', to: '/beneficios',
    label: 'Gestão de Benefícios', icon: 'zap',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/treinamentos-admin',
    label: 'Gestão de Treinamentos', icon: 'school',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/medicina-admin',
    label: 'Gestão SESMT (Med.', icon: 'stethoscope',
    roles: ['admin', 'rh', 'sesmt'] },
  { type: 'item', to: '/seguranca-admin',
    label: 'Gestão SESMT (Seg.', icon: 'shield',
    roles: ['admin', 'rh', 'sesmt'] },

  // ── Frequência ──────────────────────────────────────────────────
  { type: 'section', label: 'Frequência',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/faltas-atrasos',
    label: 'Faltas e Atrasos', icon: 'warning',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/abono-faltas',
    label: 'Abono de Faltas', icon: 'check',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/atestados-medicos',
    label: 'Atestados Médicos', icon: 'hospital',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/frequencia',
    label: 'Controle de Frequência', icon: 'clipboard-check',
    roles: ['admin', 'rh', 'gestor'] },

  // ── Saúde Ocupacional ───────────────────────────────────────────
  { type: 'section', label: 'Saúde Ocupacional',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/medicina-trabalho',
    label: 'Medicina do Trabalho', icon: 'stethoscope',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/seguranca-trabalho',
    label: 'Segurança do Trabalho', icon: 'shield',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // FINANCEIRO E FOLHA
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Financeiro e Folha',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/folha-pagamento',
    label: 'Folha de Pagamento', icon: 'credit-card',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/consignacao',
    label: 'Consignações', icon: 'account-balance',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/consignatarias',
    label: 'Consignatárias', icon: 'building-bank',
    roles: ['admin'] },
  { type: 'item', to: '/verba-indenizatoria',
    label: 'Verbas Indenizatórias', icon: 'money',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/beneficios',
    label: 'Benefícios', icon: 'gift',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/rpps',
    label: 'RPPS / IPAM', icon: 'bank',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/remessa-cnab',
    label: 'Remessa CNAB', icon: 'bank',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/gestao-declaracoes',
    label: 'Gestão de Declarações', icon: 'clipboard',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // SAÚDE
  { type: 'section', label: 'Saúde', roles: ['admin'] },
  { type: 'item', to: '/oss',
    label: 'Monitor OSS', icon: 'activity',
    roles: ['admin'] },

  // ═══════════════════════════════════════════════════════════════
  // ADMINISTRATIVO
  { type: 'section', label: 'Administrativo', roles: ['admin'] },
  { type: 'item', to: '/compras',
    label: 'Compras e Licitações', icon: 'shopping-cart',
    roles: ['admin'] },
  { type: 'item', to: '/almoxarifado',
    label: 'Almoxarifado', icon: 'package',
    roles: ['admin'] },
  { type: 'item', to: '/patrimonio',
    label: 'Patrimônio', icon: 'building',
    roles: ['admin'] },
  { type: 'item', to: '/contratos-admin',
    label: 'Contratos', icon: 'file-text',
    roles: ['admin'] },
  { type: 'item', to: '/frotas',
    label: 'Frotas', icon: 'car',
    roles: ['admin'] },

  // ═══════════════════════════════════════════════════════════════
  // COMPLIANCE — obrigações legais
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Compliance',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/esocial',
    label: 'eSocial', icon: 'cloud-upload',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/sagres-tce',
    label: 'SAGRES / TCE-MA', icon: 'chart',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/transparencia',
    label: 'Transparência Pública', icon: 'eye',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // DESENVOLVIMENTO — pessoas e capacitação
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Desenvolvimento',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/avaliacao-desempenho',
    label: 'Avaliação de Desempenho', icon: 'star',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/treinamentos',
    label: 'Treinamentos', icon: 'school',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pesquisa-satisfacao',
    label: 'Pesquisa de Satisfação', icon: 'poll',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/pesquisa-admin',
    label: 'Gerenciar Pesquisas', icon: 'edit',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // COMUNICAÇÃO — todos os perfis
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Comunicação' },
  { type: 'item', to: '/agenda',
    label: 'Agenda', icon: 'agenda', roles: [] },
  { type: 'item', to: '/comunicados',
    label: 'Comunicados', icon: 'megaphone', roles: [] },
  { type: 'item', to: '/ouvidoria',
    label: 'Ouvidoria', icon: 'comment', roles: [] },
  { type: 'item', to: '/ouvidoria-admin',
    label: 'Painel Ouvidoria', icon: 'shield',
    roles: ['admin', 'rh'] },
  { type: 'item', to: '/relatorios',
    label: 'Relatórios', icon: 'chart',
    roles: ['admin', 'rh'] },

  // ═══════════════════════════════════════════════════════════════
  // CONFIGURAÇÕES DO SISTEMA — admin apenas
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'Configurações',
    roles: ['admin'] },
  { type: 'item', to: '/configuracoes',
    label: 'Configurações Gerais', icon: 'settings',
    roles: ['admin'] },
  { type: 'item', to: '/configuracao-sistema',
    label: 'Motor de Folha', icon: 'cpu',
    roles: ['admin'] },
  { type: 'item', to: '/parametros-financeiros',
    label: 'Parâmetros Financeiros', icon: 'sliders',
    roles: ['admin'] },
  { type: 'item', to: '/vinculos',
    label: 'Vínculos', icon: 'link',
    roles: ['admin'] },
  { type: 'item', to: '/turnos',
    label: 'Turnos', icon: 'clock',
    roles: ['admin'] },
  { type: 'item', to: '/feriados',
    label: 'Feriados', icon: 'calendar',
    roles: ['admin'] },
  { type: 'item', to: '/tabelas-auxiliares',
    label: 'Tabelas Auxiliares', icon: 'table',
    roles: ['admin'] },
  { type: 'item', to: '/eventos-folha',
    label: 'Eventos de Folha', icon: 'list',
    roles: ['admin'] },

  // ═══════════════════════════════════════════════════════════════
  // ERP / FISCAL — pós-contrato, admin apenas
  // ═══════════════════════════════════════════════════════════════
  { type: 'section', label: 'ERP / Fiscal',
    roles: ['admin'] },
  { type: 'item', to: '/orcamento',
    label: 'Orçamento (PPA/LOA)', icon: 'budget',
    roles: ['admin'] },
  { type: 'item', to: '/execucao-despesa',
    label: 'Execução da Despesa', icon: 'pay',
    roles: ['admin'] },
  { type: 'item', to: '/contabilidade',
    label: 'Contabilidade (PCASP)', icon: 'book',
    roles: ['admin'] },
  { type: 'item', to: '/tesouraria',
    label: 'Tesouraria', icon: 'bank',
    roles: ['admin'] },
  { type: 'item', to: '/receita-municipal',
    label: 'Receita Municipal', icon: 'credit-card',
    roles: ['admin'] },
  { type: 'item', to: '/controle-externo',
    label: 'Controle Externo', icon: 'chart',
    roles: ['admin'] },
]

// Hierarquia de roles (índice menor = mais privilegiado)
const ROLE_HIERARCHY = ['admin', 'rh', 'gestor', 'funcionario']

function userRoleLevel(perfil) {
  if (!perfil) return 3 // funcionario por padrão
  const p = perfil.toLowerCase().trim().replace(/[^a-záéíóúàâêôãõç\s]/g, '')

  // admin — 15 perfis reais do banco
  const adminPerfis = [
    'admin', 'administrador', 'administrator', 'desenvolvedor',
    'manutencao', 'manutenção', 'equipe sisgep',
  ]
  if (adminPerfis.some(x => p.includes(x))) return 0

  // rh — operacional, folha, unidade, aps, rede, direitos, recrutador
  const rhPerfis = [
    'rh', 'recursos humanos', 'rh folha', 'rh unidade', 'rh aps',
    'rh rede', 'operacional', 'direitos e deveres', 'recrutador',
    'recursoshumanos',
  ]
  if (rhPerfis.some(x => p.includes(x))) return 1

  // gestor — coordenador, diretor, gestor de unidade
  const gestorPerfis = [
    'gestor', 'gestão', 'gestao', 'coordenador', 'diretor',
    'gestor de setor', 'diretor gestor', 'coordenador de setor',
  ]
  if (gestorPerfis.some(x => p.includes(x))) return 2

  return 3 // funcionario / externo / outros
}

function itemVisivel(item, perfil) {
  const roles = item.roles
  // roles vazio ou ausente = visível para todos
  if (!roles || roles.length === 0) return true
  const userLevel = userRoleLevel(perfil)
  // Item visível se o usuário tem um nível MENOR OU IGUAL ao exigido
  // (nível 0 = admin vê tudo; nível 3 = funcionário só vê o que está liberado para ele)
  return roles.some(r => {
    const requiredLevel = ROLE_HIERARCHY.indexOf(r)
    return requiredLevel !== -1 && userLevel <= requiredLevel
  })
}


// Remove seções que ficam sem nenhum item visível após o filtro
const navItemsFiltrados = computed(() => {
  const perfil = authStore.user?.perfil ?? ''
  const result = []
  let lastSection = null

  for (const item of ALL_NAV_ITEMS) {
    if (item.type === 'section') {
      // Guarda a seção, mas só insere se houver itens visíveis a seguir
      lastSection = item
    } else if (itemVisivel(item, perfil)) {
      if (lastSection) {
        result.push(lastSection)
        lastSection = null
      }
      result.push(item)
    }
  }

  // Filtro de busca textual
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
  '/organograma':                { label: 'Organograma',                icon: 'organogram' },
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
  '/escala-trabalho':            { label: 'Escala de Trabalho',         icon: 'calendar' },
  '/escala-matriz-v3':           { label: 'Escalas Hospitalares',       icon: 'calendar-week' },
  '/substituicoes':              { label: 'Substituições de Plantão',   icon: 'swap' },
  '/escala-sobreaviso':          { label: 'Sobreaviso',                 icon: 'phone' },
  '/plantoes-extras':            { label: 'Plantões Extras',            icon: 'plus' },
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
  '/feriados':                   { label: 'Feriados',                   icon: 'calendar' },
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
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
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
  transition: opacity 0.3s;
}

/* ═══ SIDEBAR ════════════════════════════════════════════════════ */
.sidebar {
  width: 260px;
  min-width: 260px;
  background: linear-gradient(180deg, #0f172a 0%, #132037 60%, #0f2044 100%);
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
.logo-sub { display: block; font-size: 10px; color: #60a5fa; font-weight: 600; margin-top: 2px; }

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
  background: linear-gradient(135deg, #3b82f6, #6366f1);
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

/* ─── PAGE CONTENT ─────────────────────────────────────────────── */
.page-content {
  flex: 1;
  padding: 28px;
  overflow-y: auto;
}

/* ═══ RESPONSIVE ═════════════════════════════════════════════════ */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
  }
  .sidebar.open {
    transform: translateX(0);
  }
  .main-content {
    margin-left: 0 !important;
  }
  .overlay { display: block; }
  .overlay.active { opacity: 1; pointer-events: all; }

  /* Topbar compacta */
  .topbar {
    padding: 0 14px;
    height: 52px;
  }
  .breadcrumb-label { font-size: 13px; }

  /* Page content com menos padding */
  .page-content {
    padding: 14px;
  }

  /* Notif panel ocupa mais espaço em telas pequenas */
  .notif-panel {
    width: calc(100vw - 28px);
    right: -14px;
  }
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
