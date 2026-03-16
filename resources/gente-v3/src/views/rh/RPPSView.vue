<template>
  <div class="rpps-page">
    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏦 IPAM — Previdência Municipal</span>
          <h1 class="hero-title">RPPS / IPAM</h1>
          <p class="hero-sub">Contribuições previdenciárias — servidores ativos e patronal</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card blue">
            <span class="kpi-label">Alíquota Servidor</span>
            <span class="kpi-val">14%</span>
          </div>
          <div class="kpi-card purple">
            <span class="kpi-label">Alíquota Patronal</span>
            <span class="kpi-val">28%</span>
          </div>
          <div class="kpi-card green">
            <span class="kpi-label">Total Servidor</span>
            <span class="kpi-val">{{ fmtShort(dash.total_servidor) }}</span>
          </div>
          <div class="kpi-card teal">
            <span class="kpi-label">Total Patronal</span>
            <span class="kpi-val">{{ fmtShort(dash.total_patronal) }}</span>
          </div>
          <div class="kpi-card orange">
            <span class="kpi-label">Total Geral</span>
            <span class="kpi-val">{{ fmtShort(dash.total_geral) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- FILTRO COMPETÊNCIA -->
    <div class="filter-row" :class="{ loaded }">
      <label class="filter-label">Competência:</label>
      <input type="month" v-model="competencia" class="filter-input" @change="carregar" />
      <button class="btn-calc" @click="calcular" :disabled="calculando">
        {{ calculando ? '⏳ Calculando...' : '🧮 Calcular Contribuições' }}
      </button>
      <button class="btn-export" @click="exportarCadprev" :disabled="exportando">
        {{ exportando ? '⏳...' : '📤 Exportar CADPREV' }}
      </button>
    </div>

    <!-- GRÁFICO DE EVOLUÇÃO -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📊 Evolução — Últimos 12 meses</h2>
      <div class="bars-wrap">
        <div v-for="h in historico" :key="h.COMPETENCIA" class="bar-col">
          <span class="bar-val">{{ fmtShort(h.servidor + h.patronal) }}</span>
          <div class="bar-track">
            <div class="bar-fill servidor" :style="{ height: h.pct_s + '%' }" />
            <div class="bar-fill patronal" :style="{ height: h.pct_p + '%' }" />
          </div>
          <span class="bar-label">{{ fmtComp(h.COMPETENCIA) }}</span>
        </div>
      </div>
      <div class="legend-row">
        <span class="leg leg-blue">■ Servidor (14%)</span>
        <span class="leg leg-purple">■ Patronal (28%)</span>
      </div>
    </div>

    <!-- TABS: BENEFICIÁRIOS / EXPORTAÇÕES -->
    <div class="section-card" :class="{ loaded }">
      <div class="tabs-row">
        <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tab === t.id }" @click="tab = t.id">
          {{ t.label }}
        </button>
      </div>

      <!-- Tab: Beneficiários -->
      <div v-if="tab === 'beneficiarios'">
        <div class="table-scroll">
          <table class="data-table">
            <thead><tr>
              <th>Servidor</th><th>Matrícula</th><th>Tipo</th>
              <th>Status</th><th>Data Início</th>
            </tr></thead>
            <tbody>
              <tr v-if="!beneficiarios.length"><td colspan="5" class="empty-td">📭 Nenhum beneficiário encontrado</td></tr>
              <tr v-for="b in beneficiarios" :key="b.BENEFICIARIO_ID">
                <td>{{ b.nome ?? '—' }}</td>
                <td class="mono">{{ b.matricula ?? '—' }}</td>
                <td><span class="badge" :class="tipoCls(b.TIPO)">{{ b.TIPO }}</span></td>
                <td><span class="badge" :class="b.STATUS === 'ATIVO' ? 'ok' : 'inativo'">{{ b.STATUS }}</span></td>
                <td>{{ fmtDate(b.DATA_INICIO) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Contribuições -->
      <div v-if="tab === 'contribuicoes'">
        <p class="comp-info">Competência: <strong>{{ competencia }}</strong> · {{ dash.qtd_servidores ?? 0 }} servidores</p>
        <div class="totais-strip">
          <div class="tot-item">
            <span class="tot-label">Total Servidor (14%)</span>
            <span class="tot-val blue">{{ fmtMoney(dash.total_servidor) }}</span>
          </div>
          <div class="tot-item">
            <span class="tot-label">Total Patronal (28%)</span>
            <span class="tot-val purple">{{ fmtMoney(dash.total_patronal) }}</span>
          </div>
          <div class="tot-item">
            <span class="tot-label">Total a Recolher ao IPAM</span>
            <span class="tot-val dark">{{ fmtMoney(dash.total_geral) }}</span>
          </div>
        </div>
        <div class="info-box">
          <h4>📋 Regras RPPS — Lei Federal nº 9.717/1998 + IPAM São Luís</h4>
          <ul>
            <li><strong>Servidor ativo:</strong> 14% sobre a base de cálculo (remuneração previdenciária)</li>
            <li><strong>Patronal (PMSLz):</strong> mínimo 2× a contribuição do servidor = 28%</li>
            <li><strong>Base de cálculo:</strong> remuneração previdenciária (exclui verbas indenizatórias)</li>
            <li><strong>Prazo de recolhimento:</strong> até o dia 20 do mês seguinte (Portaria MTP 1.467/2022)</li>
            <li><strong>DRAA:</strong> Demonstrativo Atuarial — envio anual ao CADPREV</li>
          </ul>
        </div>
      </div>

      <!-- Tab: Exportações -->
      <div v-if="tab === 'exportacoes'">
        <div class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Tipo</th><th>Competência</th><th>Status</th><th>Gerado em</th></tr></thead>
            <tbody>
              <tr v-if="!exportacoes.length"><td colspan="4" class="empty-td">📭 Nenhuma exportação</td></tr>
              <tr v-for="e in exportacoes" :key="e.EXPORTACAO_ID">
                <td><span class="badge blue">{{ e.TIPO }}</span></td>
                <td>{{ e.COMPETENCIA }}</td>
                <td><span class="badge" :class="e.STATUS === 'ENVIADO' ? 'ok' : 'warn'">{{ e.STATUS }}</span></td>
                <td>{{ fmtDate(e.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded      = ref(false)
const competencia = ref(new Date().toISOString().slice(0, 7))
const calculando  = ref(false)
const exportando  = ref(false)
const tab         = ref('contribuicoes')
const toast       = ref({ visible: false, msg: '' })

const dash         = ref({ total_servidor: 0, total_patronal: 0, total_geral: 0, qtd_servidores: 0 })
const historico    = ref([])
const beneficiarios = ref([])
const exportacoes  = ref([])

const tabs = [
  { id: 'contribuicoes', label: '📊 Contribuições' },
  { id: 'beneficiarios', label: '👥 Beneficiários' },
  { id: 'exportacoes',   label: '📤 Exportações CADPREV' },
]

const MOCK_HIST = Array.from({ length: 6 }, (_, i) => {
  const d = new Date(); d.setMonth(d.getMonth() - (5 - i))
  const comp = d.toISOString().slice(0, 7)
  const srv = 180000 + i * 3000; const pat = srv * 2
  return { COMPETENCIA: comp, servidor: srv, patronal: pat }
})

async function carregar() {
  try {
    const { data } = await api.get(`/api/v3/rpps/dashboard?competencia=${competencia.value}`)
    if (data.fallback) throw new Error()
    dash.value = data
    if (data.historico?.length) {
      const max = Math.max(...data.historico.map(h => h.servidor + h.patronal), 1)
      historico.value = data.historico.map(h => ({
        ...h,
        pct_s: Math.round((h.servidor / max) * 60),
        pct_p: Math.round((h.patronal / max) * 40),
      }))
    } else {
      setMockHistorico()
    }
  } catch {
    dash.value = { total_servidor: 210000, total_patronal: 420000, total_geral: 630000, qtd_servidores: 87 }
    setMockHistorico()
  }

  try {
    const { data } = await api.get('/api/v3/rpps/beneficiarios')
    beneficiarios.value = data.beneficiarios?.data ?? []
  } catch { beneficiarios.value = [] }
}

function setMockHistorico() {
  const max = Math.max(...MOCK_HIST.map(h => h.servidor + h.patronal))
  historico.value = MOCK_HIST.map(h => ({
    ...h,
    pct_s: Math.round((h.servidor / max) * 55),
    pct_p: Math.round((h.patronal / max) * 35),
  }))
}

async function calcular() {
  calculando.value = true
  try {
    const { data } = await api.post('/api/v3/rpps/calcular', { competencia: competencia.value })
    showToast(`✅ ${data.mensagem}`)
    await carregar()
  } catch { showToast('⚠️ Erro ao calcular — verifique se a folha da competência existe.') }
  finally { calculando.value = false }
}

async function exportarCadprev() {
  exportando.value = true
  try {
    await api.post('/api/v3/rpps/exportar-cadprev', { competencia: competencia.value })
    showToast('✅ Exportação CADPREV registrada com sucesso!')
  } catch { showToast('⚠️ Erro ao registrar exportação.') }
  finally { exportando.value = false }
}

function showToast(msg) {
  toast.value = { visible: true, msg }
  setTimeout(() => toast.value.visible = false, 3500)
}

const fmtMoney = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const fmtShort = v => { if (!v) return 'R$ 0'; if (v >= 1e6) return `R$ ${(v/1e6).toFixed(1)}M`; return `R$ ${(v/1e3).toFixed(0)}K` }
const fmtDate  = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'
const fmtComp  = c => { if (!c) return '—'; const [y, m] = c.split('-'); const ms = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; return `${ms[+m]}/${y.slice(2)}` }
const tipoCls  = t => ({ ATIVO: 'ok', APOSENTADO: 'blue', PENSIONISTA: 'purple' })[t] ?? ''

onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.rpps-page { display: flex; flex-direction: column; gap: 1.25rem; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0c4a6e, #1e3a5f, #4c1d95); border-radius: 20px; padding: 2rem; color: #fff; position: relative; overflow: hidden; opacity: 0; transform: translateY(-12px); transition: opacity .5s, transform .5s; }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; opacity: .1; }
.hs1 { width: 200px; height: 200px; top: -60px; right: -40px; background: #818cf8; }
.hs2 { width: 120px; height: 120px; bottom: -30px; left: 60px; background: #38bdf8; }
.hero-eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #c7d2fe; text-transform: uppercase; }
.hero-title { font-size: 1.8rem; font-weight: 800; margin: .25rem 0 .4rem; }
.hero-sub { font-size: .88rem; opacity: .8; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; position: relative; }
.kpi-strip { display: flex; gap: .75rem; flex-wrap: wrap; }
.kpi-card { background: rgba(255,255,255,.1); border-radius: 12px; padding: .65rem 1rem; min-width: 90px; text-align: center; border-top: 2px solid rgba(255,255,255,.25); }
.kpi-card.blue   { border-top-color: #60a5fa; }
.kpi-card.purple { border-top-color: #a78bfa; }
.kpi-card.green  { border-top-color: #4ade80; }
.kpi-card.teal   { border-top-color: #2dd4bf; }
.kpi-card.orange { border-top-color: #fb923c; }
.kpi-label { display: block; font-size: .65rem; opacity: .75; text-transform: uppercase; letter-spacing: .05em; }
.kpi-val   { display: block; font-size: 1.2rem; font-weight: 800; }
.filter-row { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: opacity .4s .1s, transform .4s .1s; }
.filter-row.loaded { opacity: 1; transform: none; }
.filter-label { font-size: .82rem; font-weight: 700; color: #334155; }
.filter-input { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .88rem; }
.btn-calc  { padding: .6rem 1.25rem; border-radius: 10px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-weight: 700; font-size: .85rem; cursor: pointer; }
.btn-calc:disabled { opacity: .4; cursor: not-allowed; }
.btn-export { padding: .6rem 1.25rem; border-radius: 10px; border: 1.5px solid #6366f1; color: #6366f1; background: #fff; font-weight: 700; font-size: .85rem; cursor: pointer; }
.btn-export:disabled { opacity: .4; cursor: not-allowed; }
.section-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; opacity: 0; transform: translateY(10px); transition: opacity .4s .15s, transform .4s .15s; }
.section-card.loaded { opacity: 1; transform: none; }
.section-title { font-size: 1rem; font-weight: 800; color: #1e293b; margin-bottom: 1rem; }
.bars-wrap { display: flex; gap: 10px; align-items: flex-end; height: 130px; padding-top: 20px; }
.bar-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.bar-track { width: 100%; flex: 1; background: #f1f5f9; border-radius: 6px; display: flex; flex-direction: column; justify-content: flex-end; overflow: hidden; }
.bar-fill { width: 100%; min-height: 3px; transition: height .8s cubic-bezier(.22,1,.36,1); }
.bar-fill.servidor { background: #6366f1; }
.bar-fill.patronal  { background: #818cf8; opacity: .6; }
.bar-val   { font-size: .7rem; font-weight: 700; color: #6366f1; white-space: nowrap; }
.bar-label { font-size: .68rem; font-weight: 600; color: #94a3b8; }
.legend-row { display: flex; gap: 1rem; margin-top: .75rem; }
.leg { font-size: .75rem; font-weight: 600; color: #64748b; }
.leg-blue   { color: #6366f1; }
.leg-purple { color: #a78bfa; }
.tabs-row { display: flex; gap: .5rem; border-bottom: 2px solid #f1f5f9; margin-bottom: 1.25rem; }
.tab-btn  { padding: .65rem 1.25rem; border: none; background: none; font-size: .85rem; font-weight: 700; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color .15s; }
.tab-btn.active { color: #6366f1; border-bottom-color: #6366f1; }
.comp-info { font-size: .85rem; color: #64748b; margin-bottom: 1rem; }
.totais-strip { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.tot-item { background: #f8fafc; border-radius: 12px; padding: 1rem 1.25rem; flex: 1; min-width: 180px; }
.tot-label { display: block; font-size: .72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: .4rem; }
.tot-val   { display: block; font-size: 1.35rem; font-weight: 900; }
.tot-val.blue   { color: #6366f1; }
.tot-val.purple { color: #8b5cf6; }
.tot-val.dark   { color: #1e293b; }
.info-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 1rem 1.25rem; }
.info-box h4 { font-size: .88rem; font-weight: 800; color: #0369a1; margin: 0 0 .75rem; }
.info-box ul { margin: 0; padding-left: 1.25rem; }
.info-box li { font-size: .82rem; color: #334155; margin-bottom: .35rem; line-height: 1.5; }
.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.data-table th { text-align: left; padding: .55rem .75rem; background: #f8fafc; font-size: .7rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: .55rem .75rem; border-bottom: 1px solid #f8fafc; }
.badge { display: inline-block; padding: .2rem .65rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.badge.ok     { background: #dcfce7; color: #166534; }
.badge.warn   { background: #fef3c7; color: #92400e; }
.badge.blue   { background: #dbeafe; color: #1e40af; }
.badge.purple { background: #ede9fe; color: #6d28d9; }
.badge.inativo { background: #f1f5f9; color: #64748b; }
.mono { font-family: monospace; font-size: .78rem; color: #64748b; }
.empty-td { text-align: center; padding: 2.5rem; color: #94a3b8; font-size: .85rem; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: .8rem 1.5rem; border-radius: 12px; font-weight: 600; font-size: .88rem; z-index: 200; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
</style>
