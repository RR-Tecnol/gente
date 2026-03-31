<template>
  <div class="diarias-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">✈️ Gestão de Pessoas</span>
          <h1 class="hero-title">Diárias e Missões</h1>
          <p class="hero-sub">Solicitação, aprovação e prestação de contas de diárias de viagem</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card yellow"><span class="kpi-label">Aguardando</span><span class="kpi-val">{{ stats.pendentes }}</span></div>
          <div class="kpi-card green"><span class="kpi-label">Aprovadas</span><span class="kpi-val">{{ stats.aprovadas }}</span></div>
          <div class="kpi-card red"><span class="kpi-label">Prest. Pendente</span><span class="kpi-val">{{ stats.prestacao }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Total mês</span><span class="kpi-val">{{ fmtShort(stats.total_mes) }}</span></div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-card" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
    </div>

    <!-- Tab: LISTA -->
    <div v-if="tab === 'lista'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Solicitações</h2>
        <div class="toolbar">
          <select v-model="filtroStatus" class="filter-sel">
            <option value="">Todos os status</option>
            <option value="PENDENTE">Pendentes</option>
            <option value="APROVADA">Aprovadas</option>
            <option value="PRESTACAO_PENDENTE">Prest. Pendente</option>
            <option value="CONCLUIDA">Concluídas</option>
          </select>
          <button class="btn-primary" @click="tab = 'nova'">+ Nova Solicitação</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Servidor</th><th>Destino</th><th>Período</th>
            <th>Diárias</th><th>Valor Total</th><th>Status</th><th>Ações</th>
          </tr></thead>
          <tbody>
            <tr v-if="!listaFiltrada.length"><td colspan="7" class="empty-td">📭 Nenhuma solicitação encontrada</td></tr>
            <tr v-for="d in listaFiltrada" :key="d.SOLICITACAO_ID">
              <td>
                <span class="nome">{{ d.nome ?? '—' }}</span>
                <span class="sub">{{ d.matricula ?? '' }}</span>
              </td>
              <td>
                <span class="destino">{{ d.DESTINO }}</span>
                <span class="sub">{{ tipoLabel(d.DESTINO_TIPO) }}</span>
              </td>
              <td class="mono">{{ fmtDate(d.DATA_IDA) }} → {{ fmtDate(d.DATA_VOLTA) }}</td>
              <td class="center bold">{{ d.QTDE_DIARIAS }}d</td>
              <td class="money">{{ fmtMoney(d.VALOR_TOTAL) }}</td>
              <td><span class="badge" :class="statusCls(d.STATUS)">{{ statusLabel(d.STATUS) }}</span></td>
              <td>
                <div class="row-actions">
                  <button v-if="d.STATUS === 'PENDENTE'" class="act-btn ok" @click="aprovar(d.SOLICITACAO_ID)" title="Aprovar">✅</button>
                  <button v-if="d.STATUS === 'PENDENTE'" class="act-btn red" @click="reprovar(d.SOLICITACAO_ID)" title="Reprovar">❌</button>
                  <button v-if="d.STATUS === 'APROVADA'" class="act-btn blue" @click="abrirPrestacao(d)" title="Registrar Prestação">📄</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: NOVA SOLICITAÇÃO -->
    <div v-if="tab === 'nova'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">✈️ Nova Solicitação de Diária</h2>
      <div class="form-grid">
        <div class="form-group full">
          <label>Servidor</label>
          <input class="form-input" v-model="form.servidor_nome" placeholder="Digite o nome ou matrícula para buscar..." @input="buscarServidor" />
          <div v-if="sugestoes.length" class="sugestoes">
            <div v-for="s in sugestoes" :key="s.id" class="sug-item" @click="selecionarServidor(s)">
              <strong>{{ s.nome }}</strong><span> · {{ s.matricula }}</span>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Destino</label>
          <input class="form-input" v-model="form.destino" placeholder="Ex: São Paulo/SP" />
        </div>
        <div class="form-group">
          <label>Tipo de Destino</label>
          <select class="form-input" v-model="form.destino_tipo">
            <option v-for="t in tiposDestino" :key="t.val" :value="t.val">{{ t.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Data de Ida</label>
          <input class="form-input" type="date" v-model="form.data_ida" />
        </div>
        <div class="form-group">
          <label>Data de Volta</label>
          <input class="form-input" type="date" v-model="form.data_volta" />
        </div>
        <div class="form-group full">
          <label>Objetivo da Missão</label>
          <textarea class="form-input ta" rows="3" v-model="form.objetivo" placeholder="Descreva o objetivo da viagem..." />
        </div>
      </div>
      <!-- Preview de valor -->
      <div v-if="previewDiarias > 0" class="preview-box">
        <span>🗓️ <strong>{{ previewDiarias }} diárias</strong> de {{ fmtMoney(valorUnitario) }} =</span>
        <span class="pv-total">{{ fmtMoney(previewDiarias * valorUnitario) }}</span>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" @click="tab = 'lista'">Cancelar</button>
        <button class="btn-primary" :disabled="!formValido || enviando" @click="enviarSolicitacao">
          {{ enviando ? '⏳ Enviando...' : '✅ Enviar Solicitação' }}
        </button>
      </div>
    </div>

    <!-- Tab: TABELA DE VALORES -->
    <div v-if="tab === 'tabela'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">💰 Tabela de Diárias — PMSLz</h2>
      <div class="tabela-grid">
        <div v-for="t in tabelaValores" :key="t.DESTINO_TIPO" class="tabela-card">
          <span class="tabela-ico">{{ destinoIco(t.DESTINO_TIPO) }}</span>
          <div>
            <span class="tabela-dest">{{ tipoLabel(t.DESTINO_TIPO) }}</span>
            <span class="tabela-val">{{ fmtMoney(t.VALOR_DIARIA) }}<small>/diária</small></span>
          </div>
        </div>
        <!-- fallback se tabela vazia -->
        <template v-if="!tabelaValores.length">
          <div v-for="t in mockTabela" :key="t.dest" class="tabela-card">
            <span class="tabela-ico">{{ t.ico }}</span>
            <div>
              <span class="tabela-dest">{{ t.label }}</span>
              <span class="tabela-val">{{ fmtMoney(t.valor) }}<small>/diária</small></span>
            </div>
          </div>
        </template>
      </div>
      <p class="tabela-obs">* Valores conforme Tabela de Diárias PMSLz. Atualização via Decreto Municipal.</p>
    </div>

    <!-- Modal Prestação de Contas -->
    <transition name="modal">
      <div v-if="modalPrestacao" class="modal-overlay" @click.self="modalPrestacao = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>📄 Prestação de Contas</h3>
            <button class="modal-close" @click="modalPrestacao = false">✕</button>
          </div>
          <div class="modal-body">
            <p class="modal-sub">Solicitação: <strong>{{ prestacaoItem?.DESTINO }}</strong></p>
            <div class="form-group">
              <label>Valor Efetivamente Gasto</label>
              <input class="form-input" type="number" step="0.01" v-model="prestacao.valor_gasto" />
            </div>
            <div class="form-group">
              <label>Saldo a Devolver (se houver)</label>
              <input class="form-input" type="number" step="0.01" v-model="prestacao.saldo_devolvido" />
            </div>
            <div class="form-group">
              <label>Observações</label>
              <textarea class="form-input ta" rows="2" v-model="prestacao.observacao" />
            </div>
            <div class="modal-actions">
              <button class="btn-secondary" @click="modalPrestacao = false">Cancelar</button>
              <button class="btn-primary" @click="salvarPrestacao">✅ Registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tab = ref('lista')
const filtroStatus = ref('')
const enviando = ref(false)
const modalPrestacao = ref(false)
const prestacaoItem = ref(null)
const sugestoes = ref([])
const tabelaValores = ref([])
const toast = ref({ visible: false, msg: '' })

const diarias = ref([])
const stats = ref({ pendentes: 0, aprovadas: 0, prestacao: 0, total_mes: 0 })

const form = ref({ funcionario_id: null, servidor_nome: '', destino: '', destino_tipo: 'CAPITAL_MA', data_ida: '', data_volta: '', objetivo: '' })
const prestacao = ref({ valor_gasto: 0, saldo_devolvido: 0, observacao: '' })

const tabs = [
  { id: 'lista', label: '📋 Solicitações' },
  { id: 'nova',  label: '+ Nova Diária' },
  { id: 'tabela', label: '💰 Tabela de Valores' },
]

const tiposDestino = [
  { val: 'CAPITAL_MA',   label: 'São Luís (capital MA)' },
  { val: 'OUTRA_CAPITAL', label: 'Outra capital estadual' },
  { val: 'INTERIOR_MA',  label: 'Interior do Maranhão' },
  { val: 'FORA_MA',     label: 'Fora do Maranhão' },
  { val: 'EXTERIOR',    label: 'Exterior' },
]

const mockTabela = [
  { dest: 'INTERIOR_MA',   ico: '🌎', label: 'Interior do Maranhão', valor: 180 },
  { dest: 'CAPITAL_MA',    ico: '🏙️', label: 'São Luís',             valor: 220 },
  { dest: 'OUTRA_CAPITAL', ico: '✈️', label: 'Outra capital',        valor: 340 },
  { dest: 'FORA_MA',      ico: '🗺️', label: 'Fora do Maranhão',     valor: 420 },
  { dest: 'EXTERIOR',     ico: '🌍', label: 'Exterior',             valor: 800 },
]

const MOCK = [
  { SOLICITACAO_ID: 1, nome: 'Maria Silva', matricula: '20250001', DESTINO: 'Brasília/DF', DESTINO_TIPO: 'FORA_MA', DATA_IDA: '2026-03-05', DATA_VOLTA: '2026-03-07', QTDE_DIARIAS: 3, VALOR_TOTAL: 1260, STATUS: 'APROVADA' },
  { SOLICITACAO_ID: 2, nome: 'João Costa', matricula: '20250002', DESTINO: 'Caxias/MA', DESTINO_TIPO: 'INTERIOR_MA', DATA_IDA: '2026-03-10', DATA_VOLTA: '2026-03-11', QTDE_DIARIAS: 2, VALOR_TOTAL: 360, STATUS: 'PENDENTE' },
  { SOLICITACAO_ID: 3, nome: 'Ana Ferreira', matricula: '20240045', DESTINO: 'Fortaleza/CE', DESTINO_TIPO: 'FORA_MA', DATA_IDA: '2026-02-20', DATA_VOLTA: '2026-02-22', QTDE_DIARIAS: 3, VALOR_TOTAL: 1260, STATUS: 'PRESTACAO_PENDENTE' },
]

const listaFiltrada = computed(() =>
  filtroStatus.value ? diarias.value.filter(d => d.STATUS === filtroStatus.value) : diarias.value
)

const previewDiarias = computed(() => {
  if (!form.value.data_ida || !form.value.data_volta) return 0
  const d = Math.ceil((new Date(form.value.data_volta) - new Date(form.value.data_ida)) / 86400000) + 1
  return d > 0 ? d : 0
})

const valorUnitario = computed(() => {
  const t = tabelaValores.value.find(t => t.DESTINO_TIPO === form.value.destino_tipo)
  if (t) return parseFloat(t.VALOR_DIARIA)
  return mockTabela.find(t => t.dest === form.value.destino_tipo)?.valor ?? 0
})

const formValido = computed(() =>
  form.value.funcionario_id && form.value.destino && form.value.data_ida && form.value.data_volta && form.value.objetivo
)

async function carregar() {
  try {
    const [rList, rTab] = await Promise.all([
      api.get('/api/v3/diarias'),
      api.get('/api/v3/diarias/tabela-valores'),
    ])
    diarias.value = rList.data.diarias?.data ?? diarias.value
    tabelaValores.value = rTab.data.tabela ?? []
  } catch {
    diarias.value = MOCK
  }
  stats.value = {
    pendentes: diarias.value.filter(d => d.STATUS === 'PENDENTE').length,
    aprovadas: diarias.value.filter(d => d.STATUS === 'APROVADA').length,
    prestacao: diarias.value.filter(d => d.STATUS === 'PRESTACAO_PENDENTE').length,
    total_mes: diarias.value.reduce((a, d) => a + parseFloat(d.VALOR_TOTAL || 0), 0),
  }
}

let buscarTimer = null
function buscarServidor() {
  clearTimeout(buscarTimer)
  const q = form.value.servidor_nome
  if (q.length < 3) { sugestoes.value = []; return }
  buscarTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/v3/servidores/buscar', { params: { q } })
      sugestoes.value = data.servidores ?? []
    } catch { sugestoes.value = [] }
  }, 300)
}

function selecionarServidor(s) {
  form.value.funcionario_id = s.id
  form.value.servidor_nome = s.nome
  sugestoes.value = []
}

async function enviarSolicitacao() {
  enviando.value = true
  try {
    await api.post('/api/v3/diarias', {
      funcionario_id: form.value.funcionario_id,
      destino: form.value.destino,
      destino_tipo: form.value.destino_tipo,
      objetivo: form.value.objetivo,
      data_ida: form.value.data_ida,
      data_volta: form.value.data_volta,
    })
    showToast('✅ Solicitação de diária enviada para aprovação!')
    Object.assign(form.value, { funcionario_id: null, servidor_nome: '', destino: '', destino_tipo: 'CAPITAL_MA', data_ida: '', data_volta: '', objetivo: '' })
    tab.value = 'lista'
    await carregar()
  } catch { showToast('⚠️ Erro ao enviar solicitação.') }
  finally { enviando.value = false }
}

async function aprovar(id) {
  try {
    await api.patch(`/api/v3/diarias/${id}/status`, { status: 'APROVADA' })
    showToast('✅ Diária aprovada!')
    await carregar()
  } catch { showToast('⚠️ Erro ao aprovar.') }
}

async function reprovar(id) {
  try {
    await api.patch(`/api/v3/diarias/${id}/status`, { status: 'NEGADA' })
    showToast('❌ Diária negada.')
    await carregar()
  } catch { showToast('⚠️ Erro ao reprovar.') }
}

function abrirPrestacao(d) {
  prestacaoItem.value = d
  prestacao.value = { valor_gasto: d.VALOR_TOTAL, saldo_devolvido: 0, observacao: '' }
  modalPrestacao.value = true
}

async function salvarPrestacao() {
  try {
    await api.post(`/api/v3/diarias/${prestacaoItem.value.SOLICITACAO_ID}/prestacao`, prestacao.value)
    showToast('✅ Prestação de contas registrada!')
    modalPrestacao.value = false
    await carregar()
  } catch { showToast('⚠️ Erro ao registrar prestação.') }
}

const tipoLabel   = t => tiposDestino.find(x => x.val === t)?.label ?? t
const destinoIco  = t => mockTabela.find(x => x.dest === t)?.ico ?? '📍'
const statusLabel = s => ({ PENDENTE:'Pendente', APROVADA:'Aprovada', NEGADA:'Negada', PAGA:'Paga', PRESTACAO_PENDENTE:'Prest. Pendente', CONCLUIDA:'Concluída' })[s] ?? s
const statusCls   = s => ({ PENDENTE:'warn', APROVADA:'ok', NEGADA:'red', PAGA:'ok', PRESTACAO_PENDENTE:'orange', CONCLUIDA:'gray' })[s] ?? ''
const fmtMoney    = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const fmtShort    = v => v >= 1e3 ? `R$ ${(v/1e3).toFixed(0)}K` : `R$ ${v}`
const fmtDate     = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'

function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.diarias-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#164e63,#1e3a5f,#7c3aed); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; inset:0; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#38bdf8; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#a78bfa; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#bae6fd; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.yellow { border-top:2px solid #fbbf24; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.red    { border-top:2px solid #f87171; }
.kpi-card.blue   { border-top:2px solid #60a5fa; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; letter-spacing:.05em; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.tabs-card { display:flex; gap:.5rem; opacity:0; transition:opacity .4s .1s; }
.tabs-card.loaded { opacity:1; }
.tab-btn { padding:.65rem 1.25rem; border-radius:10px; border:1px solid #e2e8f0; background:#fff; font-size:.85rem; font-weight:700; color:#64748b; cursor:pointer; transition:all .15s; }
.tab-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.toolbar { display:flex; gap:.75rem; align-items:center; }
.filter-sel { border:1.5px solid #e2e8f0; border-radius:8px; padding:.55rem .9rem; font-size:.85rem; }
.btn-primary { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; vertical-align:middle; }
.nome { display:block; font-weight:700; color:#1e293b; }
.destino { display:block; font-weight:600; color:#1e293b; }
.sub  { display:block; font-size:.7rem; color:#94a3b8; }
.mono { font-family:monospace; font-size:.78rem; color:#64748b; white-space:nowrap; }
.money { font-family:monospace; font-weight:700; color:#1e293b; }
.bold { font-weight:800; }
.center { text-align:center; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok     { background:#dcfce7; color:#166534; }
.badge.warn   { background:#fef3c7; color:#92400e; }
.badge.red    { background:#fee2e2; color:#991b1b; }
.badge.orange { background:#ffedd5; color:#9a3412; }
.badge.gray   { background:#f1f5f9; color:#64748b; }
.row-actions { display:flex; gap:.4rem; }
.act-btn { padding:.3rem .55rem; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:.85rem; transition:all .15s; }
.act-btn.ok:hover   { background:#dcfce7; border-color:#86efac; }
.act-btn.red:hover  { background:#fee2e2; border-color:#fca5a5; }
.act-btn.blue:hover { background:#dbeafe; border-color:#bfdbfe; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group.full { grid-column:1/-1; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.ta { resize:vertical; min-height:70px; font-family:inherit; }
.sugestoes { background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-top:.25rem; box-shadow:0 6px 20px rgba(0,0,0,.1); position:absolute; z-index:50; min-width:300px; }
.sug-item { padding:.65rem 1rem; cursor:pointer; font-size:.85rem; }
.sug-item:hover { background:#f8fafc; }
.form-group { position:relative; }
.preview-box { display:flex; align-items:center; gap:.75rem; background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:.75rem 1rem; margin-bottom:1rem; font-size:.88rem; color:#0369a1; }
.pv-total { font-size:1.2rem; font-weight:900; color:#0369a1; }
.form-actions { display:flex; gap:.75rem; justify-content:flex-end; }
.tabela-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; margin-bottom:1rem; }
.tabela-card { display:flex; align-items:center; gap:.75rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; }
.tabela-ico  { font-size:1.75rem; flex-shrink:0; }
.tabela-dest { display:block; font-size:.82rem; font-weight:700; color:#1e293b; margin-bottom:.25rem; }
.tabela-val  { display:block; font-size:1.2rem; font-weight:900; color:#7c3aed; }
.tabela-val small { font-size:.7rem; font-weight:400; color:#64748b; }
.tabela-obs { font-size:.75rem; color:#94a3b8; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); z-index:100; display:flex; align-items:center; justify-content:center; padding:1rem; }
.modal-card { background:#fff; border-radius:20px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.2); }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.modal-hdr h3 { font-size:1rem; font-weight:800; color:#1e293b; margin:0; }
.modal-close { border:none; background:#f1f5f9; border-radius:8px; width:28px; height:28px; cursor:pointer; color:#64748b; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:.9rem; }
.modal-sub { font-size:.85rem; color:#64748b; margin:0; }
.modal-actions { display:flex; gap:.75rem; margin-top:.5rem; }
.modal-enter-active,.modal-leave-active { transition:opacity .3s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; box-shadow:0 8px 32px rgba(0,0,0,.2); }
</style>
