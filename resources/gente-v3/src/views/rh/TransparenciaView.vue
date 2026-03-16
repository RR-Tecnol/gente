<template>
  <div class="transp-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🔍 Lei nº 12.527/2011 — LAI</span>
          <h1 class="hero-title">Transparência Pública</h1>
          <p class="hero-sub">Exportação de dados de pessoal para o Portal da Transparência</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card green"><span class="kpi-label">Último envio</span><span class="kpi-val">{{ stats.ultimoEnvio }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Servidores</span><span class="kpi-val">{{ stats.servidores }}</span></div>
          <div class="kpi-card orange"><span class="kpi-label">Status Portal</span><span class="kpi-val">{{ stats.status }}</span></div>
        </div>
      </div>
    </div>

    <!-- EXPORTADOR -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📤 Gerar Arquivo de Transparência</h2>
      <div class="exp-grid">
        <div class="form-group"><label>Competência / Mês Ref.</label><input class="form-input" type="month" v-model="comp" /></div>
        <div class="form-group">
          <label>Tipo de Arquivo</label>
          <select class="form-input" v-model="tipo">
            <option value="servidores">Servidores Ativos</option>
            <option value="folha">Folha de Pagamento</option>
            <option value="diarias">Diárias e Passagens</option>
            <option value="estagiarios">Estagiários</option>
          </select>
        </div>
        <div class="form-group">
          <label>Formato</label>
          <select class="form-input" v-model="formato">
            <option value="json">JSON (Portal MA)</option>
            <option value="csv">CSV (planilha)</option>
          </select>
        </div>
      </div>
      <div class="btn-row">
        <button class="btn-primary" @click="gerar" :disabled="gerando">
          {{ gerando ? '⏳ Gerando...' : '📤 Gerar e Exportar' }}
        </button>
        <button class="btn-secondary" @click="gerarLocal">
          💾 Download Local
        </button>
      </div>
    </div>

    <!-- CAMPO A CAMPO -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📋 Campos Obrigatórios — LAI / Portal MA</h2>
      <div class="campos-grid">
        <div v-for="c in campos" :key="c.label" class="campo-card">
          <span class="campo-ico">{{ c.ico }}</span>
          <span class="campo-label">{{ c.label }}</span>
          <span class="badge" :class="c.req ? 'ok' : 'warn'">{{ c.req ? 'Obrigatório' : 'Opcional' }}</span>
        </div>
      </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📋 Histórico de Exportações</h2>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Competência</th><th>Tipo</th><th>Formato</th><th>Status</th><th>Gerado em</th></tr></thead>
          <tbody>
            <tr v-if="!historico.length"><td colspan="5" class="empty-td">📭 Nenhuma exportação registrada</td></tr>
            <tr v-for="h in historico" :key="h.id">
              <td class="mono bold">{{ h.comp }}</td>
              <td>{{ h.tipo }}</td>
              <td><span class="badge blue">{{ h.formato.toUpperCase() }}</span></td>
              <td><span class="badge" :class="h.status === 'OK' ? 'ok' : 'warn'">{{ h.status }}</span></td>
              <td>{{ h.data }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded  = ref(false)
const comp    = ref(new Date().toISOString().slice(0, 7))
const tipo    = ref('servidores')
const formato = ref('json')
const gerando = ref(false)
const toast   = ref({ visible: false, msg: '' })
const historico = ref([])
const stats   = ref({ ultimoEnvio: '—', servidores: '—', status: 'N/A' })

const campos = [
  { ico: '👤', label: 'Nome completo', req: true },
  { ico: '🪪', label: 'CPF (parcialmente mascarado)', req: true },
  { ico: '🏢', label: 'Cargo / Função', req: true },
  { ico: '📍', label: 'Lotação / Secretaria', req: true },
  { ico: '💰', label: 'Remuneração Bruta', req: true },
  { ico: '💸', label: 'Descontos', req: true },
  { ico: '💳', label: 'Remuneração Líquida', req: true },
  { ico: '📅', label: 'Data de admissão', req: true },
  { ico: '🔗', label: 'Tipo de vínculo', req: true },
  { ico: '🏦', label: 'Banco/Agência/Conta', req: false },
]

const MOCK_HIST = [
  { id: 1, comp: '2026-02', tipo: 'Servidores Ativos', formato: 'json', status: 'OK', data: '01/03/2026' },
  { id: 2, comp: '2026-01', tipo: 'Folha de Pagamento', formato: 'csv',  status: 'OK', data: '01/02/2026' },
]

async function gerar() {
  gerando.value = true
  try {
    await api.post('/api/v3/transparencia/exportar', { competencia: comp.value, tipo: tipo.value, formato: formato.value })
    showToast(`✅ Exportação de transparência gerada para ${comp.value}!`)
    historico.value = [{ id: Date.now(), comp: comp.value, tipo, formato: formato.value, status: 'OK', data: new Date().toLocaleDateString('pt-BR') }, ...historico.value]
  } catch { showToast('⚠️ Erro ao gerar exportação.') }
  finally { gerando.value = false }
}

function gerarLocal() {
  const dados = [
    { cpf: '***156.789-**', nome: 'Maria Silva', cargo: 'Enfermeira', lotacao: 'SEMUS', bruto: 8800.00, descontos: 1800.00, liquido: 7000.00, admissao: '2015-03-01', vinculo: 'ESTATUTARIO' },
    { cpf: '***543.210-**', nome: 'João Costa', cargo: 'Professor', lotacao: 'SEMED', bruto: 5500.00, descontos: 1100.00, liquido: 4400.00, admissao: '2018-08-01', vinculo: 'ESTATUTARIO' },
  ]
  let content = ''
  if (formato.value === 'json') {
    content = JSON.stringify({ competencia: comp.value, tipo: tipo.value, dados }, null, 2)
    download(content, `transparencia_${comp.value}.json`, 'application/json')
  } else {
    const cols = Object.keys(dados[0]).join(';')
    const rows = dados.map(d => Object.values(d).join(';')).join('\n')
    content = cols + '\n' + rows
    download(content, `transparencia_${comp.value}.csv`, 'text/csv')
  }
  showToast(`✅ Arquivo de transparência (${formato.value.toUpperCase()}) gerado localmente!`)
}

function download(content, filename, mime) {
  const blob = new Blob([content], { type: mime })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = filename; a.click()
  URL.revokeObjectURL(url)
}

function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

onMounted(() => {
  historico.value = MOCK_HIST
  stats.value = { ultimoEnvio: '01/03/2026', servidores: '1.247', status: '✅ OK' }
  setTimeout(() => loaded.value = true, 80)
})
</script>

<style scoped>
.transp-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#0c4a6e,#1e3a5f,#0f766e); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#2dd4bf; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#38bdf8; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#99f6e4; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.blue   { border-top:2px solid #60a5fa; }
.kpi-card.orange { border-top:2px solid #fb923c; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1rem; font-weight:800; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.exp-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.btn-row { display:flex; gap:.75rem; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#0f766e,#0c4a6e); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.campos-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.65rem; }
.campo-card { display:flex; align-items:center; gap:.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.65rem .9rem; }
.campo-ico   { font-size:1.2rem; flex-shrink:0; }
.campo-label { font-size:.8rem; font-weight:600; color:#334155; flex:1; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; }
.mono  { font-family:monospace; font-size:.78rem; color:#64748b; }
.bold  { font-weight:700; color:#1e293b; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok   { background:#dcfce7; color:#166534; }
.badge.blue { background:#dbeafe; color:#1e40af; }
.badge.warn { background:#fef3c7; color:#92400e; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>
