<template>
  <div class="notif-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🔔 Central de Alertas</span>
          <h1 class="hero-title">Notificações</h1>
          <p class="hero-sub">{{ naoLidas }} não lida{{ naoLidas !== 1 ? 's' : '' }} · {{ notificacoes.length }} total</p>
        </div>
        <div class="hero-actions">
          <button class="ha-btn" @click="marcarTodasLidas" :disabled="naoLidas === 0">✅ Marcar todas lidas</button>
          <button class="ha-btn ha-red" @click="limparLidas">🗑️ Limpar lidas</button>
        </div>
      </div>
    </div>

    <!-- FILTRO ──────────────────────────────────────────────── -->
    <div class="filter-bar" :class="{ loaded }">
      <button v-for="f in filtros" :key="f.val" class="filter-pill"
        :class="{ active: filtroAtivo === f.val }" @click="filtroAtivo = f.val">
        {{ f.ico }} {{ f.label }}
        <span class="pill-count" v-if="countFiltro(f.val) > 0">{{ countFiltro(f.val) }}</span>
      </button>
    </div>

    <!-- LISTA ──────────────────────────────────────────────── -->
    <div class="notif-list" :class="{ loaded }">
      <div
        v-for="(n, i) in notificacoesFiltradas"
        :key="n.id"
        class="notif-item"
        :class="{ 'notif-unread': !n.lida }"
        :style="{ '--ni': i }"
        @click="marcarLida(n)"
      >
        <div class="notif-cat-dot" :style="{ background: catCor(n.categoria) }"></div>
        <div class="notif-ico-wrap" :style="{ '--nc': catCor(n.categoria) }">{{ n.ico }}</div>
        <div class="notif-body">
          <div class="notif-top">
            <span class="n-titulo">{{ n.titulo }}</span>
            <span class="n-tempo">{{ formatRel(n.criado_em) }}</span>
          </div>
          <p class="n-desc">{{ n.descricao }}</p>
          <div v-if="n.link" class="n-link-wrap">
            <a :href="n.link" class="n-link" @click.stop>Ver detalhes →</a>
          </div>
        </div>
        <div class="unread-dot" v-if="!n.lida" title="Não lida"></div>
      </div>

      <div v-if="notificacoesFiltradas.length === 0" class="state-empty">
        <span>🎉</span>
        <h3>Tudo em dia!</h3>
        <p>Nenhuma notificação {{ filtroAtivo !== 'todas' ? 'nesta categoria' : '' }}.</p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const loaded = ref(false)
const filtroAtivo = ref('todas')
const notificacoes = ref([])

const filtros = [
  { val: 'todas', ico: '📋', label: 'Todas' },
  { val: 'ponto', ico: '⏱️', label: 'Ponto' },
  { val: 'escala', ico: '📅', label: 'Escalas' },
  { val: 'financeiro', ico: '💰', label: 'Financeiro' },
  { val: 'rh', ico: '👥', label: 'RH' },
]

const catCores = { ponto: '#f59e0b', escala: '#0d9488', financeiro: '#10b981', rh: '#6366f1', sistema: '#94a3b8' }
const catCor = (c) => catCores[c] ?? '#94a3b8'

onMounted(() => {
  notificacoes.value = [
    { id: 1, titulo: 'Abono de falta deferido', descricao: 'Sua justificativa de 10/02 foi aprovada pelo gestor. A ocorrência foi removida do seu ponto.', categoria: 'ponto', ico: '✅', criado_em: new Date(Date.now() - 1800000), lida: false, link: '/abono-faltas' },
    { id: 2, titulo: 'Plantão de amanhã confirmado', descricao: 'Lembrete: você tem plantão noturno (19h–07h) amanhã na UTI Adulto.', categoria: 'escala', ico: '📅', criado_em: new Date(Date.now() - 3600000), lida: false, link: '/escala-matriz-v3' },
    { id: 3, titulo: 'Holerite de Janeiro/2026 disponível', descricao: 'Seu contra-cheque de competência 01/2026 já está disponível para visualização e download.', categoria: 'financeiro', ico: '💰', criado_em: new Date(Date.now() - 86400000), lida: false, link: '/meus-holerites' },
    { id: 4, titulo: 'Solicitação de substituição recebida', descricao: 'Carlos Lima solicitou que você o substitua no plantão de 28/02 (Noturno, UTI Adulto).', categoria: 'escala', ico: '🔄', criado_em: new Date(Date.now() - 86400000 * 2), lida: true, link: '/substituicoes' },
    { id: 5, titulo: 'Inconsistência de ponto detectada', descricao: 'Faltam registros de saída nos dias 15 e 16/02. Verifique e entre em contato com o RH.', categoria: 'ponto', ico: '⚠️', criado_em: new Date(Date.now() - 86400000 * 3), lida: true, link: '/ponto' },
    { id: 6, titulo: 'Férias aprovadas', descricao: 'Sua solicitação de férias para Jul/2026 foi aprovada. 10 dias a partir de 07/07/2026.', categoria: 'rh', ico: '🏖️', criado_em: new Date(Date.now() - 86400000 * 4), lida: true, link: '/ferias-licencas' },
    { id: 7, titulo: 'Remessa CNAB gerada', descricao: 'O arquivo CNAB 240 de Fevereiro/2026 foi gerado com sucesso. 87 créditos processados.', categoria: 'financeiro', ico: '🏦', criado_em: new Date(Date.now() - 86400000 * 5), lida: true, link: '/remessa-cnab' },
    { id: 8, titulo: 'Falta registrada — 13/02', descricao: 'Uma falta foi registrada no dia 13/02. Se necessário, solicite abono em até 5 dias úteis.', categoria: 'ponto', ico: '🚫', criado_em: new Date(Date.now() - 86400000 * 6), lida: true, link: '/faltas-atrasos' },
  ]
  setTimeout(() => { loaded.value = true }, 80)
})

const naoLidas = computed(() => notificacoes.value.filter(n => !n.lida).length)

const notificacoesFiltradas = computed(() => {
  if (filtroAtivo.value === 'todas') return notificacoes.value
  return notificacoes.value.filter(n => n.categoria === filtroAtivo.value)
})

const countFiltro = (val) => {
  if (val === 'todas') return notificacoes.value.filter(n => !n.lida).length
  return notificacoes.value.filter(n => n.categoria === val && !n.lida).length
}

const marcarLida = (n) => { n.lida = true }
const marcarTodasLidas = () => notificacoes.value.forEach(n => { n.lida = true })
const limparLidas = () => { notificacoes.value = notificacoes.value.filter(n => !n.lida) }

const formatRel = (d) => {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return 'Agora'
  if (diff < 3600) return `${Math.floor(diff / 60)}min atrás`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h atrás`
  return `${Math.floor(diff / 86400)}d atrás`
}
</script>

<style scoped>
.notif-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 24px 32px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a2a3a 60%, #0f2a1e 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 280px; height: 280px; background: #6366f1; right: 50px; top: -80px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 6px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.ha-btn { padding: 9px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.08); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.ha-btn:hover:not(:disabled) { background: rgba(255,255,255,0.15); }
.ha-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.ha-red { border-color: rgba(239,68,68,0.3); color: #f87171; }
.ha-red:hover:not(:disabled) { background: rgba(239,68,68,0.15); }
.filter-bar { display: flex; gap: 8px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.filter-bar.loaded { opacity: 1; transform: none; }
.filter-pill { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; }
.filter-pill.active { background: #eff6ff; border-color: #6366f1; color: #4f46e5; }
.pill-count { background: #fee2e2; color: #dc2626; border-radius: 99px; padding: 1px 7px; font-size: 11px; font-weight: 900; }
.filter-pill.active .pill-count { background: #e0e7ff; color: #4f46e5; }
.notif-list { display: flex; flex-direction: column; gap: 8px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.notif-list.loaded { opacity: 1; transform: none; }
.notif-item { position: relative; display: flex; align-items: flex-start; gap: 14px; background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 16px 18px; cursor: pointer; transition: all 0.18s; animation: itemIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ni) * 40ms) both; }
@keyframes itemIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.notif-item:hover { border-color: #e2e8f0; box-shadow: 0 4px 16px -4px rgba(0,0,0,0.08); transform: translateX(3px); }
.notif-item.notif-unread { background: linear-gradient(to right, #fafffe, #fff); border-color: #e2e8f0; }
.notif-cat-dot { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; border-radius: 3px 0 0 3px; }
.notif-ico-wrap { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: color-mix(in srgb, var(--nc) 10%, white); border: 1px solid color-mix(in srgb, var(--nc) 15%, transparent); flex-shrink: 0; }
.notif-body { flex: 1; min-width: 0; }
.notif-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 4px; }
.n-titulo { font-size: 14px; font-weight: 700; color: #1e293b; }
.n-tempo { font-size: 11px; color: #94a3b8; white-space: nowrap; flex-shrink: 0; }
.n-desc { font-size: 13px; color: #64748b; margin: 0 0 6px; line-height: 1.5; }
.n-link { font-size: 12px; font-weight: 700; color: #6366f1; text-decoration: none; }
.n-link:hover { text-decoration: underline; }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; flex-shrink: 0; margin-top: 6px; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.state-empty { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; }
.state-empty span { font-size: 48px; }
.state-empty h3 { font-size: 20px; font-weight: 800; color: #1e293b; margin: 0; }
.state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }

@media (max-width: 768px) {
  .hero-inner { flex-wrap: wrap; }
  .hero-title { font-size: 22px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .two-col, .form-two-col, .config-grid { grid-template-columns: 1fr !important; }
  .table-scroll, .table-wrap { overflow-x: auto; }
  table { min-width: 500px; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 18px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .hide-mobile { display: none !important; }
}
</style>
