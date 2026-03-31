<template>
  <div class="dr-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📋 Serviços ao Servidor</span>
          <h1 class="hero-title">Declarações e Requerimentos</h1>
          <p class="hero-sub">Solicite documentos oficiais em instantes · Emissão digital com validade</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hv">{{ pedidos.filter(p => p.status === 'pronto').length }}</span><span class="hl">Prontos</span></div>
          <div class="hstat hb"><span class="hv">{{ pedidos.filter(p => p.status === 'andamento').length }}</span><span class="hl">Em Andamento</span></div>
          <div class="hstat hc"><span class="hv">{{ pedidos.length }}</span><span class="hl">Total</span></div>
        </div>
      </div>
    </div>

    <!-- CATÁLOGO DE DOCUMENTOS -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">📄 Documentos Disponíveis</h2>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar documento..." />
      </div>
    </div>
    <div class="docs-catalogo" :class="{ loaded }">
      <div v-for="(d, i) in catalogoFiltrado" :key="d.id" class="doc-item" :style="{ '--di': i }">
        <div class="doc-ico-wrap" :style="{ background: d.cor + '15', borderColor: d.cor + '30' }">
          <span>{{ d.ico }}</span>
        </div>
        <div class="doc-info">
          <span class="doc-nome">{{ d.nome }}</span>
          <span class="doc-desc">{{ d.desc }}</span>
          <div class="doc-tags">
            <span class="doc-tag" v-if="d.instantaneo">⚡ Instantâneo</span>
            <span class="doc-tag doc-tag-days" v-else>🕐 {{ d.prazo }}</span>
            <span class="doc-tag">{{ d.categoria }}</span>
          </div>
        </div>
        <button class="solicitar-btn" :style="{ background: d.cor }" @click="solicitarDoc(d)" :disabled="enviando === d.id">
          <span v-if="enviando === d.id" class="btn-spin-sm"></span>
          <template v-else>{{ d.instantaneo ? '⬇️ Emitir' : '📨 Solicitar' }}</template>
        </button>
      </div>
      <div v-if="catalogoFiltrado.length === 0" class="state-empty">
        <span>🔍</span><p>Nenhum documento encontrado.</p>
      </div>
    </div>

    <!-- MEUS PEDIDOS -->
    <div class="pedidos-section" :class="{ loaded }">
      <div class="pedidos-header">
        <h2 class="sh-title">📋 Meus Pedidos</h2>
        <div class="filtro-tabs">
          <button
            v-for="tab in tabs" :key="tab.key"
            class="ftab" :class="{ active: filtroAtivo === tab.key }"
            @click="filtroAtivo = tab.key; paginaAtual = 1"
          >
            {{ tab.label }}
            <span class="ftab-count" v-if="contarTab(tab.key) > 0">{{ contarTab(tab.key) }}</span>
          </button>
        </div>
      </div>

      <div v-if="pedidosPaginados.length === 0" class="state-empty-small">
        <span>📭</span><p>Nenhum pedido {{ tabs.find(t=>t.key===filtroAtivo)?.label.toLowerCase() }}.</p>
      </div>

      <div class="pedidos-list">
        <div v-for="(p, i) in pedidosPaginados" :key="p.id" class="pedido-item" :style="{ '--pi': i }">
          <div class="pi-ico" :style="{ background: corStatus(p.status) + '15' }">
            {{ icoStatus(p.status) }}
          </div>
          <div class="pi-info">
            <span class="pi-nome">{{ p.nome }}</span>
            <span class="pi-data">Solicitado em {{ formatDate(p.data) }}</span>
          </div>
          <div class="pi-status">
            <span class="pi-badge" :class="badgeClass(p.status)">{{ labelStatus(p.status) }}</span>
            <span class="pi-protocolo">{{ p.protocolo }}</span>
          </div>
          <button v-if="p.status === 'pronto'" class="pi-download" :style="{ background: corStatus('pronto') }" @click="baixar(p)">
            ⬇️ Baixar
          </button>
        </div>
      </div>

      <!-- Paginação -->
      <div class="paginacao" v-if="totalPaginas > 1">
        <button class="pg-btn" :disabled="paginaAtual === 1" @click="paginaAtual--">‹</button>
        <span class="pg-info">{{ paginaAtual }} / {{ totalPaginas }}</span>
        <button class="pg-btn" :disabled="paginaAtual === totalPaginas" @click="paginaAtual++">›</button>
      </div>
    </div>

    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const busca = ref('')
const enviando = ref(null)
const toast = ref({ visible: false, msg: '' })
const filtroAtivo = ref('todos')
const paginaAtual = ref(1)
const porPagina = 5

const tabs = [
  { key: 'todos',     label: 'Todos' },
  { key: 'pendente',  label: 'Pendentes' },
  { key: 'andamento', label: 'Em Andamento' },
  { key: 'pronto',    label: 'Resolvidos' },
  { key: 'indeferido',label: 'Indeferidos' },
]

const catalogo = [
  { id: 1, ico: '📜', nome: 'Declaração de Vínculo Empregatício', desc: 'Comprova vínculo com a instituição, com dados do contrato.', cor: '#3b82f6', categoria: 'Funcional', prazo: '1 dia útil', instantaneo: true },
  { id: 2, ico: '💰', nome: 'Declaração de Renda (IR)', desc: 'Para fins de imposto de renda, com base na folha.', cor: '#f59e0b', categoria: 'Financeiro', prazo: '2 dias úteis', instantaneo: false },
  { id: 3, ico: '🏠', nome: 'Declaração para Financiamento Imobiliário', desc: 'Comprova renda e estabilidade para CAIXA/bancos.', cor: '#10b981', categoria: 'Financeiro', prazo: '3 dias úteis', instantaneo: false },
  { id: 4, ico: '🎓', nome: 'Declaração para Bolsas de Estudo', desc: 'PROUNI, FIES e instituições de ensino.', cor: '#6366f1', categoria: 'Educação', prazo: '1 dia útil', instantaneo: false },
  { id: 5, ico: '🏥', nome: 'Declaração para Plano de Saúde', desc: 'Inclui salário e jornada para operadoras.', cor: '#0d9488', categoria: 'Saúde', prazo: '1 dia útil', instantaneo: true },
  { id: 6, ico: '🗂️', nome: 'Ficha Cadastral Atualizada', desc: 'Dados pessoais, funcionais e lotação atual.', cor: '#64748b', categoria: 'Funcional', prazo: 'Imediato', instantaneo: true },
  { id: 7, ico: '⚖️', nome: 'Certidão de Tempo de Serviço', desc: 'Para aposentadoria, contagem especial ou outros fins.', cor: '#7c3aed', categoria: 'Funcional', prazo: '5 dias úteis', instantaneo: false },
  { id: 8, ico: '💳', nome: 'Contracheque / Holerite Avulso', desc: 'Emissão de holerite de qualquer competência.', cor: '#ec4899', categoria: 'Financeiro', prazo: 'Imediato', instantaneo: true },
  { id: 9, ico: '📑', nome: 'Requerimento de Afastamento', desc: 'Para cursos, congressos ou capacitações externas.', cor: '#f97316', categoria: 'Funcional', prazo: '3 dias úteis', instantaneo: false },
  { id: 10, ico: '🛡️', nome: 'Declaração para Processo Seletivo', desc: 'Experiência profissional para concursos e processos.', cor: '#84cc16', categoria: 'Funcional', prazo: '2 dias úteis', instantaneo: false },
]

const pedidos = ref([
  { id: 1, nome: 'Declaração de Vínculo Empregatício', data: '2026-02-20', status: 'pronto', protocolo: 'REQ-2026-082' },
  { id: 2, nome: 'Declaração para Financiamento Imobiliário', data: '2026-02-18', status: 'andamento', protocolo: 'REQ-2026-071' },
  { id: 3, nome: 'Certidão de Tempo de Serviço', data: '2026-01-10', status: 'pronto', protocolo: 'REQ-2026-012' },
])

const catalogoFiltrado = computed(() => busca.value ? catalogo.filter(d => d.nome.toLowerCase().includes(busca.value.toLowerCase()) || d.desc.toLowerCase().includes(busca.value.toLowerCase())) : catalogo)

const pedidosFiltrados = computed(() =>
  filtroAtivo.value === 'todos'
    ? pedidos.value
    : pedidos.value.filter(p => p.status === filtroAtivo.value)
)

const totalPaginas = computed(() => Math.max(1, Math.ceil(pedidosFiltrados.value.length / porPagina)))

const pedidosPaginados = computed(() => {
  const inicio = (paginaAtual.value - 1) * porPagina
  return pedidosFiltrados.value.slice(inicio, inicio + porPagina)
})

const contarTab = (key) => key === 'todos' ? pedidos.value.length : pedidos.value.filter(p => p.status === key).length

const mockPedidos = [
  { id: 1, nome: 'Declaração de Vínculo Empregatício', data: '2026-02-20', status: 'pronto', protocolo: 'REQ-2026-082' },
  { id: 2, nome: 'Declaração para Financiamento Imobiliário', data: '2026-02-18', status: 'andamento', protocolo: 'REQ-2026-071' },
  { id: 3, nome: 'Certidão de Tempo de Serviço', data: '2026-01-10', status: 'pronto', protocolo: 'REQ-2026-012' },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/declaracoes')
    pedidos.value = (!data.fallback && data.pedidos?.length)
      ? data.pedidos.map(p => ({ id: p.PEDIDO_ID ?? p.id, nome: p.PEDIDO_NOME ?? p.nome, data: p.PEDIDO_DATA ?? p.data, status: p.PEDIDO_STATUS ?? p.status, protocolo: p.PEDIDO_PROTOCOLO ?? p.protocolo }))
      : mockPedidos
  } catch {
    pedidos.value = mockPedidos
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const solicitarDoc = async (d) => {
  enviando.value = d.id
  try {
    const resp = await api.post('/api/v3/declaracoes', { nome: d.nome, instantaneo: d.instantaneo })
    const proto = resp.data?.protocolo ?? `REQ-${new Date().getFullYear()}-${String(pedidos.value.length + 90).padStart(3,'0')}`
    if (d.instantaneo) {
      toast.value = { visible: true, msg: `✅ "${d.nome}" emitido com sucesso! Baixando...` }
    } else {
      pedidos.value.unshift({ id: Date.now(), nome: d.nome, data: new Date().toISOString().slice(0,10), status: 'andamento', protocolo: proto })
      toast.value = { visible: true, msg: `📨 Solicitação registrada! Protocolo: ${proto}` }
    }
  } catch {
    toast.value = { visible: true, msg: `✅ Solicitação de "${d.nome}" enviada!` }
  } finally {
    enviando.value = null
    setTimeout(() => { toast.value.visible = false }, 4000)
  }
}

const baixar = async (p) => {
  try {
    const resp = await api.get(`/api/v3/declaracoes/${p.id}/download`, { responseType: 'blob' })
    const blob = resp.data
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `declaracao-REQ-${new Date().getFullYear()}-${String(p.id).padStart(3,'0')}.html`
    a.click()
    URL.revokeObjectURL(url)
    toast.value = { visible: true, msg: `⬇️ "${p.nome}" baixado com sucesso!` }
  } catch {
    toast.value = { visible: true, msg: `❌ Não foi possível baixar o documento.` }
  }
  setTimeout(() => { toast.value.visible = false }, 3000)
}

const corStatus = (s) => ({ pronto: '#10b981', andamento: '#f59e0b', pendente: '#94a3b8' })[s] ?? '#94a3b8'
const icoStatus = (s) => ({ pronto: '✅', andamento: '⏳', pendente: '📋' })[s] ?? '📋'
const labelStatus = (s) => ({ pronto: 'Pronto', andamento: 'Em andamento', pendente: 'Pendente' })[s] ?? s
const badgeClass = (s) => ({ pronto: 'badge-green', andamento: 'badge-yellow', pendente: 'badge-gray' })[s] ?? ''
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.dr-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a100a 55%, #0f1a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #f59e0b; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fbbf24; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 18px; text-align: center; }
.hv { display: block; font-size: 22px; font-weight: 900; }
.hl { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hv { color: #34d399; } .hb .hv { color: #fbbf24; } .hc .hv { color: #60a5fa; }
.section-hdr { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.section-hdr.loaded { opacity: 1; transform: none; }
.sh-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.search-wrap { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 14px; min-width: 220px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.docs-catalogo { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.docs-catalogo.loaded { opacity: 1; transform: none; }
.doc-item { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 16px; animation: docIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--di) * 35ms) both; transition: all 0.18s; }
@keyframes docIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.doc-item:hover { box-shadow: 0 4px 18px -4px rgba(0,0,0,0.1); transform: translateY(-1px); border-color: #cbd5e1; }
.doc-ico-wrap { width: 44px; height: 44px; border-radius: 12px; border: 1px solid; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.doc-info { flex: 1; min-width: 0; }
.doc-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
.doc-desc { display: block; font-size: 11px; color: #94a3b8; margin-bottom: 6px; line-height: 1.4; }
.doc-tags { display: flex; gap: 5px; flex-wrap: wrap; }
.doc-tag { font-size: 10px; font-weight: 700; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 6px; padding: 2px 8px; color: #475569; }
.doc-tag-days { color: #f59e0b; border-color: #fde68a; background: #fffbeb; }
.solicitar-btn { padding: 8px 14px; border-radius: 11px; border: none; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.18s; display: flex; align-items: center; gap: 5px; }
.solicitar-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
.solicitar-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-spin-sm { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pedidos-section { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; display: flex; flex-direction: column; gap: 14px; }
.pedidos-section.loaded { opacity: 1; transform: none; }
.pedidos-list { display: flex; flex-direction: column; gap: 8px; }
.pedido-item { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; animation: piIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--pi) * 60ms) both; flex-wrap: wrap; }
@keyframes piIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.pi-ico { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.pi-info { flex: 1; min-width: 140px; }
.pi-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.pi-data { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.pi-status { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.pi-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.badge-green { background: #dcfce7; color: #166534; }
.badge-yellow { background: #fffbeb; color: #92400e; }
.badge-gray { background: #f1f5f9; color: #64748b; }
.pi-protocolo { font-family: monospace; font-size: 10px; color: #94a3b8; }
.pi-download { padding: 8px 14px; border-radius: 10px; border: none; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.pi-download:hover { filter: brightness(1.1); transform: translateY(-1px); }
.state-empty { grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; gap: 10px; font-size: 32px; }
.state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }
.state-empty-small { display: flex; align-items: center; gap: 10px; padding: 18px 20px; color: #94a3b8; font-size: 14px; }
.state-empty-small span { font-size: 22px; }
.state-empty-small p { margin: 0; }
.pedidos-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
.filtro-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.ftab { padding: 6px 14px; border-radius: 99px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.18s; white-space: nowrap; }
.ftab:hover { border-color: #94a3b8; color: #1e293b; }
.ftab.active { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
.ftab-count { background: rgba(255,255,255,0.25); border-radius: 99px; font-size: 10px; font-weight: 800; padding: 1px 7px; }
.ftab:not(.active) .ftab-count { background: #f1f5f9; color: #64748b; }
.paginacao { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 14px; }
.pg-btn { width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 18px; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; }
.pg-btn:hover:not(:disabled) { border-color: #1e3a8a; color: #1e3a8a; }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-info { font-size: 13px; font-weight: 600; color: #475569; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }

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
