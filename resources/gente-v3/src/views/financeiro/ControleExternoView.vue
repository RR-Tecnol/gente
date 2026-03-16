<template>
  <div class="erp-page">
    <div class="hero indigo">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">🏛️ ERP Municipal</span>
          <h1 class="hero-title">Controle Externo</h1>
          <p class="hero-sub">SAGRES · SICONFI · RGF · RREO</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi" :class="rgf.alerta === 'CRITICO' ? 'red' : rgf.alerta === 'ATENCAO' ? 'yellow' : 'green'">
            <span class="kl">Desp. Pessoal / RCL</span>
            <span class="kv">{{ rgf.percentual_pessoal ?? '—' }}%</span>
          </div>
          <div class="kpi"><span class="kl">Limite legal</span><span class="kv">54%</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'envios' }" @click="aba='envios'; carregarEnvios()">📋 Histórico</button>
      <button class="tab" :class="{ active: aba === 'sagres' }" @click="aba='sagres'; carregarSagres()">📤 SAGRES</button>
      <button class="tab" :class="{ active: aba === 'rreo' }"   @click="aba='rreo'; carregarRreo()">📊 RREO</button>
      <button class="tab" :class="{ active: aba === 'rgf' }"    @click="aba='rgf'; carregarRgf()">⚖️ RGF</button>
    </div>

    <!-- Histórico de envios -->
    <div v-if="aba === 'envios'" class="card">
      <h2>Histórico de Envios</h2>
      <div class="table-wrap">
        <table class="tbl" v-if="envios.length">
          <thead>
            <tr><th>Tipo</th><th>Ano</th><th>Período</th><th>Arquivo</th><th>Status</th><th>Gerado em</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in envios" :key="e.ENVIO_ID">
              <td><span class="badge">{{ e.ENVIO_TIPO }}</span></td>
              <td>{{ e.ENVIO_ANO }}</td>
              <td>{{ periodoEnvio(e) }}</td>
              <td>{{ e.ENVIO_ARQUIVO || '—' }}</td>
              <td><span class="status-badge" :class="e.ENVIO_STATUS?.toLowerCase()">{{ e.ENVIO_STATUS }}</span></td>
              <td>{{ e.ENVIO_DT_GERACAO ? new Date(e.ENVIO_DT_GERACAO).toLocaleString('pt-BR') : '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhum envio registrado.</div>
      </div>
    </div>

    <!-- SAGRES -->
    <div v-if="aba === 'sagres'" class="card">
      <div class="card-hdr">
        <h2>Geração SAGRES / SINC-Folha</h2>
        <div class="toolbar">
          <select v-model="anoSel" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
          <select v-model="mesSel" class="inp small">
            <option v-for="m in 12" :key="m" :value="m">{{ m.toString().padStart(2,'0') }}</option>
          </select>
          <button class="btn-sec" @click="carregarSagres">🔍 Preview</button>
          <button class="btn-primary" @click="gerarSagres" :disabled="salvando">{{ salvando ? '⏳...' : '📤 Gerar XML' }}</button>
        </div>
      </div>

      <div v-if="preview.total_linhas !== undefined" class="resumo-row">
        <div class="rs-card"><span>Total de linhas</span><strong>{{ preview.total_linhas }}</strong></div>
        <div class="rs-card" :class="preview.nao_mapeados > 0 ? 'red' : 'green'">
          <span>Eventos não mapeados</span><strong>{{ preview.nao_mapeados }}</strong>
        </div>
        <div v-if="preview.nao_mapeados > 0" class="rs-card warn">
          <span>⚠️ Antes de gerar, mapeie os eventos sem código SAGRES na tabela SAGRES_EVENTO_DEPARA.</span>
        </div>
      </div>

      <div class="table-wrap" v-if="preview.linhas?.length">
        <table class="tbl">
          <thead><tr><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Evento ID</th><th>Código SAGRES</th><th>Tipo</th></tr></thead>
          <tbody>
            <tr v-for="l in preview.linhas" :key="l.DETALHE_FOLHA_ID" :class="{ 'row-warn': l.sagres_codigo === 'NAO_MAPEADO' }">
              <td>{{ l.matricula }}</td>
              <td>{{ l.nome }}</td>
              <td>{{ l.CPF }}</td>
              <td>{{ l.EVENTO_ID }}</td>
              <td><code :class="l.sagres_codigo === 'NAO_MAPEADO' ? 'err' : ''">{{ l.sagres_codigo }}</code></td>
              <td>{{ l.sagres_tipo || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="msg" class="msg-ok">{{ msg }}</div>
    </div>

    <!-- RREO -->
    <div v-if="aba === 'rreo'" class="card">
      <div class="card-hdr">
        <h2>RREO — Relatório Resumido da Execução Orçamentária</h2>
        <div class="toolbar">
          <select v-model="anoSel" @change="carregarRreo" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
          <select v-model="bimSel" @change="carregarRreo" class="inp small">
            <option v-for="b in 6" :key="b" :value="b">{{ b }}º Bimestre</option>
          </select>
        </div>
      </div>
      <div v-if="rreo.atual" class="kpi-grid">
        <div class="kpi-item"><span class="ki-l">Receita Prevista</span><span class="ki-v">{{ fmt(rreo.atual.RREO_RECEITA_PREVISTA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Receita Arrecadada</span><span class="ki-v pos">{{ fmt(rreo.atual.RREO_RECEITA_ARRECADADA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Dotação Inicial</span><span class="ki-v">{{ fmt(rreo.atual.RREO_DESP_DOTACAO_INICIAL) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Dotação Atualizada</span><span class="ki-v">{{ fmt(rreo.atual.RREO_DESP_DOTACAO_ATUALIZADA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Empenhado</span><span class="ki-v">{{ fmt(rreo.atual.RREO_DESP_EMPENHADA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Liquidado</span><span class="ki-v">{{ fmt(rreo.atual.RREO_DESP_LIQUIDADA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Pago</span><span class="ki-v pos">{{ fmt(rreo.atual.RREO_DESP_PAGA) }}</span></div>
      </div>
      <div v-else class="empty">📭 Sem dados RREO para {{ anoSel }} — {{ bimSel }}º bimestre.</div>
    </div>

    <!-- RGF -->
    <div v-if="aba === 'rgf'" class="card">
      <div class="card-hdr">
        <h2>RGF — Relatório de Gestão Fiscal</h2>
        <div class="toolbar">
          <select v-model="anoSel" @change="carregarRgf" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
          <select v-model="quadSel" @change="carregarRgf" class="inp small">
            <option v-for="q in 3" :key="q" :value="q">{{ q }}º Quadrimestre</option>
          </select>
        </div>
      </div>
      <div v-if="rgf.rgf" class="kpi-grid">
        <div class="kpi-item"><span class="ki-l">RCL</span><span class="ki-v">{{ fmt(rgf.rgf.RGF_RCL) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Despesa Pessoal Total</span><span class="ki-v">{{ fmt(rgf.rgf.RGF_DESP_PESSOAL_TOTAL) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Despesa Pessoal Líquida</span><span class="ki-v">{{ fmt(rgf.rgf.RGF_DESP_PESSOAL_LIQUIDA) }}</span></div>
        <div class="kpi-item" :class="rgf.rgf.alerta === 'CRITICO' ? 'red' : rgf.rgf.alerta === 'ATENCAO' ? 'yellow' : 'green'">
          <span class="ki-l">% do RCL</span>
          <span class="ki-v">{{ rgf.rgf.percentual_pessoal }}% <small>(limite: 54%)</small></span>
        </div>
        <div class="kpi-item"><span class="ki-l">Dívida Consolidada</span><span class="ki-v">{{ fmt(rgf.rgf.RGF_DIVIDA_CONSOLIDADA) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Op. de Crédito</span><span class="ki-v">{{ fmt(rgf.rgf.RGF_OPERACOES_CREDITO) }}</span></div>
      </div>
      <div v-else class="empty">📭 Sem dados RGF para {{ anoSel }} — {{ quadSel }}º quadrimestre.</div>
    </div>

    <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba     = ref('envios')
const loading = ref(false)
const salvando = ref(false)
const msg     = ref('')
const envios  = ref([])
const preview = ref({})
const rreo    = ref({})
const rgf     = ref({})
const anoSel  = ref(new Date().getFullYear())
const mesSel  = ref(new Date().getMonth() + 1)
const bimSel  = ref(Math.ceil((new Date().getMonth() + 1) / 2))
const quadSel = ref(Math.ceil((new Date().getMonth() + 1) / 4))
const anos    = Array.from({ length: 4 }, (_, i) => anoSel.value - i)

async function carregarEnvios() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/controle-externo/envios')
    envios.value = data.envios || []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarSagres() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/sagres/preview', { params: { ano: anoSel.value, mes: mesSel.value } })
    preview.value = data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function gerarSagres() {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/sagres/gerar', { ano: anoSel.value, mes: mesSel.value })
    if (data.ok) {
      msg.value = `✅ ${data.arquivo} gerado! ${data.aviso}`
      await carregarEnvios()
    }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function carregarRreo() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/siconfi/rreo', { params: { ano: anoSel.value, bimestre: bimSel.value } })
    rreo.value = data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarRgf() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/siconfi/rgf', { params: { ano: anoSel.value, quadrimestre: quadSel.value } })
    rgf.value = data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function periodoEnvio(e) {
  if (e.ENVIO_MES) return `${e.ENVIO_ANO}/${String(e.ENVIO_MES).padStart(2,'0')}`
  if (e.ENVIO_BIMESTRE) return `${e.ENVIO_ANO} — ${e.ENVIO_BIMESTRE}º Bimestre`
  return e.ENVIO_ANO
}

const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(carregarEnvios)
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero.indigo { background: linear-gradient(135deg, #312e81, #4338ca); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #c7d2fe; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 130px; }
.kpi.green { border-top: 3px solid #6ee7b7; }
.kpi.yellow { border-top: 3px solid #fde68a; }
.kpi.red { border-top: 3px solid #fca5a5; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.1rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; flex-wrap: wrap; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #4338ca; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: .75rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.toolbar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .85rem; }
.inp.small { width: 100px; }
.btn-primary { background: linear-gradient(135deg, #4338ca, #6366f1); color: #fff; border: none; padding: .6rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: .85rem; }
.btn-primary:disabled { opacity: .4; }
.btn-sec { background: #f1f5f9; border: none; padding: .6rem 1rem; border-radius: 8px; cursor: pointer; font-size: .82rem; }
.resumo-row { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.rs-card { background: #f8fafc; border-radius: 12px; padding: .75rem 1.25rem; }
.rs-card.green { background: #f0fdf4; }
.rs-card.red { background: #fff1f2; }
.rs-card.warn { background: #fffbeb; flex: 1; font-size: .82rem; color: #92400e; }
.rs-card span { display: block; font-size: .72rem; color: #64748b; text-transform: uppercase; margin-bottom: .25rem; }
.rs-card strong { font-size: 1.1rem; font-weight: 800; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.kpi-item { background: #f8fafc; border-radius: 12px; padding: 1rem 1.25rem; }
.kpi-item.green { background: #f0fdf4; }
.kpi-item.yellow { background: #fffbeb; }
.kpi-item.red { background: #fff1f2; }
.ki-l { display: block; font-size: .72rem; color: #64748b; text-transform: uppercase; margin-bottom: .25rem; }
.ki-v { display: block; font-size: 1rem; font-weight: 700; }
.ki-v small { font-size: .72rem; color: #64748b; font-weight: 400; }
.pos { color: #059669; }
.msg-ok { margin-top: 1rem; background: #f0fdf4; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.row-warn { background: #fffbeb; }
.badge { background: #e0e7ff; color: #3730a3; border-radius: 4px; padding: .15rem .5rem; font-size: .7rem; font-weight: 700; }
.status-badge { border-radius: 999px; padding: .15rem .55rem; font-size: .7rem; font-weight: 700; }
.status-badge.gerado   { background: #dbeafe; color: #1e40af; }
.status-badge.enviado  { background: #fef3c7; color: #92400e; }
.status-badge.aceito   { background: #dcfce7; color: #15803d; }
.status-badge.rejeitado{ background: #fee2e2; color: #991b1b; }
code.err { color: #dc2626; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #4338ca; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
