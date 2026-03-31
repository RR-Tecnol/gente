<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏦 Gestão Financeira</span>
          <h1 class="hero-title">Consignações em Folha</h1>
          <p class="hero-sub">Controle de empréstimos e convênios — margem 30% empréstimos + 10% cartão (Decreto 57.477/2021)</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card blue"><span class="kpi-label">Contratos Ativos</span><span class="kpi-val">{{ totais.contratos_ativos ?? 0 }}</span></div>
          <div class="kpi-card purple"><span class="kpi-label">Total Descontos</span><span class="kpi-val">{{ formatMoney(totais.total_descontos) }}</span></div>
          <div class="kpi-card indigo"><span class="kpi-label">Saldo Devedor</span><span class="kpi-val">{{ formatMoney(totais.total_saldo) }}</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'contratos' }" @click="aba = 'contratos'">📋 Contratos</button>
      <button class="tab-btn" :class="{ active: aba === 'novo' }"      @click="aba = 'novo'">➕ Novo Contrato</button>
      <button class="tab-btn" :class="{ active: aba === 'margem' }"    @click="aba = 'margem'">📊 Margem</button>
      <button class="tab-btn" :class="{ active: aba === 'relatorio' }" @click="aba = 'relatorio'; carregarRelatorio()">📈 Relatório</button>
    </div>

    <!-- LISTA -->
    <div v-if="aba === 'contratos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Contratos de Consignação</h2>
        <div class="toolbar-right">
          <select v-model="filtros.status" @change="carregarContratos" class="form-input small">
            <option value="">Todos</option>
            <option value="ATIVO">Ativo</option>
            <option value="SUSPENSO">Suspenso</option>
            <option value="QUITADO">Quitado</option>
          </select>
        </div>
      </div>
      <div class="table-scroll">
        <table class="cs-table" v-if="contratos.length">
          <thead><tr><th>Servidor</th><th>Convênio</th><th>Parcela</th><th>Progresso</th><th>Saldo</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-for="c in contratos" :key="c.CONTRATO_ID">
              <td><span class="nome-cell">{{ c.nome }}</span><span class="sub">{{ c.matricula }}</span></td>
              <td><span class="tipo-badge" :class="c.convenio_tipo?.toLowerCase()">{{ c.convenio_nome }}</span></td>
              <td class="money">{{ formatMoney(c.VALOR_PARCELA) }}/mês</td>
              <td>
                <div class="progresso-wrap">
                  <div class="progresso-bar"><div class="progresso-fill" :style="{ width: Math.round((c.PARCELAS_PAGAS / c.PRAZO_MESES) * 100) + '%' }"></div></div>
                  <span class="progresso-txt">{{ c.PARCELAS_PAGAS }}/{{ c.PRAZO_MESES }}</span>
                </div>
              </td>
              <td class="money red">{{ formatMoney(c.SALDO_DEVEDOR) }}</td>
              <td><span class="status-badge" :class="c.STATUS?.toLowerCase()">{{ c.STATUS }}</span></td>
              <td>
                <button v-if="c.STATUS === 'ATIVO'"     class="act-btn orange" @click="mudarStatus(c.CONTRATO_ID,'SUSPENSO')">⏸️</button>
                <button v-if="c.STATUS === 'SUSPENSO'"  class="act-btn blue"   @click="mudarStatus(c.CONTRATO_ID,'ATIVO')">▶️</button>
                <button class="act-btn gray" @click="verParcelas(c)">📄</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="empty-state">📭 Nenhum contrato encontrado.</div>
        <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
      </div>
      <div v-if="contratoDetalhe" class="modal-overlay" @click.self="contratoDetalhe = null">
        <div class="modal">
          <div class="modal-hdr"><h3>📄 Parcelas — {{ contratoDetalhe.nome }}</h3><button class="modal-close" @click="contratoDetalhe = null">✕</button></div>
          <table class="cs-table">
            <thead><tr><th>#</th><th>Competência</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="p in parcelas" :key="p.PARCELA_ID">
                <td>{{ p.NUMERO_PARCELA }}</td><td>{{ p.COMPETENCIA }}</td>
                <td class="money">{{ formatMoney(p.VALOR_PARCELA) }}</td>
                <td><span class="status-badge" :class="p.STATUS?.toLowerCase()">{{ p.STATUS }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- NOVO CONTRATO -->
    <div v-if="aba === 'novo'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">➕ Novo Contrato de Consignação</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>Servidor</label>
          <div class="search-wrap">
            <input v-model="busca" @input="buscarServidor" placeholder="🔍 Nome ou matrícula..." class="form-input" />
            <ul v-if="resultados.length" class="autocomplete-list">
              <li v-for="s in resultados" :key="s.id" @click="selecionarServidor(s)"><strong>{{ s.nome }}</strong><span class="sub">{{ s.matricula }}</span></li>
            </ul>
          </div>
        </div>
        <div class="form-group">
          <label>Convênio</label>
          <select v-model="form.convenio_id" class="form-input">
            <option value="">Selecione...</option>
            <option v-for="c in convenios" :key="c.CONVENIO_ID" :value="c.CONVENIO_ID">{{ c.CONVENIO_NOME }}</option>
          </select>
        </div>
        <div class="form-group"><label>N° Contrato</label><input v-model="form.numero_contrato" class="form-input" /></div>
        <div class="form-group"><label>Data Início</label><input type="date" v-model="form.data_inicio" class="form-input" /></div>
        <div class="form-group"><label>Valor Total (R$)</label><input type="number" step="0.01" v-model="form.valor_total" class="form-input" /></div>
        <div class="form-group"><label>Valor Parcela (R$)</label><input type="number" step="0.01" v-model="form.valor_parcela" class="form-input" /></div>
        <div class="form-group"><label>Prazo (meses)</label><input type="number" v-model="form.prazo_meses" class="form-input" /></div>
        <div class="form-group"><label>Juros (% a.m.)</label><input type="number" step="0.01" v-model="form.taxa_juros" class="form-input" /></div>
      </div>
      <div v-if="margem.liquido !== undefined" class="margem-box">
        <!-- Barra de Empréstimos (30%) -->
        <div class="margem-label">🏦 Empréstimos (30%)</div>
        <div class="margem-grid">
          <div class="mg-item"><span class="mg-lbl">Limite</span><span class="mg-val">{{ formatMoney(margem.margem_emp_total) }}</span></div>
          <div class="mg-item"><span class="mg-lbl">Usado</span><span class="mg-val" style="color:#dc2626">{{ formatMoney(margem.margem_emp_usada) }}</span></div>
          <div class="mg-item"><span class="mg-lbl">Disponível</span><span class="mg-val" style="color:#16a34a">{{ formatMoney(margem.margem_emp_disponivel) }}</span></div>
        </div>
        <div class="margem-bar-wrap">
          <div class="margem-bar"><div class="margem-fill" :class="{ danger: pctEmp > 90 }" :style="{ width: pctEmp + '%' }"></div></div>
          <span class="margem-pct">{{ pctEmp }}%</span>
        </div>
        <!-- Barra de Cartão (5%) -->
        <div class="margem-label" style="margin-top:.75rem">💳 Cartão (10%)</div>
        <div class="margem-grid">
          <div class="mg-item"><span class="mg-lbl">Limite</span><span class="mg-val">{{ formatMoney(margem.margem_cartao_total) }}</span></div>
          <div class="mg-item"><span class="mg-lbl">Usado</span><span class="mg-val" style="color:#dc2626">{{ formatMoney(margem.margem_cartao_usada) }}</span></div>
          <div class="mg-item"><span class="mg-lbl">Disponível</span><span class="mg-val" style="color:#16a34a">{{ formatMoney(margem.margem_cartao_disponivel) }}</span></div>
        </div>
        <div class="margem-bar-wrap">
          <div class="margem-bar"><div class="margem-fill cartao" :class="{ danger: pctCartao > 90 }" :style="{ width: pctCartao + '%' }"></div></div>
          <span class="margem-pct">{{ pctCartao }}%</span>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-primary" @click="registrarContrato" :disabled="!servidor || !form.convenio_id || salvando">{{ salvando ? '⏳ Salvando...' : '💾 Registrar' }}</button>
        <button v-if="servidor" class="btn-secondary" @click="consultarMargem">📊 Ver Margem</button>
      </div>
      <div v-if="msgNovo"  class="success-msg">{{ msgNovo }}</div>
      <div v-if="erroNovo" class="error-msg">{{ erroNovo }}</div>
    </div>

    <!-- MARGEM -->
    <div v-if="aba === 'margem'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📊 Consultar Margem Consignável</h2>
      <div class="form-group" style="max-width:360px">
        <label>Servidor</label>
        <div class="search-wrap">
          <input v-model="buscaMargem" @input="buscarServidorMargem" placeholder="🔍 Nome ou matrícula..." class="form-input" />
          <ul v-if="resultadosMargem.length" class="autocomplete-list">
            <li v-for="s in resultadosMargem" :key="s.id" @click="consultarMargemDireto(s)"><strong>{{ s.nome }}</strong><span class="sub">{{ s.matricula }}</span></li>
          </ul>
        </div>
      </div>
      <div v-if="margemConsulta.nome" class="margem-resultado">
        <h3 class="mr-nome">{{ margemConsulta.nome }}</h3>
        <div class="mr-kpis">
          <div class="mr-kpi"><span>Bruto</span><strong>{{ formatMoney(margemConsulta.bruto) }}</strong></div>
          <div class="mr-kpi"><span>Líquido</span><strong>{{ formatMoney(margemConsulta.liquido) }}</strong></div>
        </div>
        <div class="margem-label" style="margin:.75rem 0 .25rem">🏦 Empréstimos (30%)</div>
        <div class="mr-kpis">
          <div class="mr-kpi blue"><span>Limite</span><strong>{{ formatMoney(margemConsulta.margem_emp_total) }}</strong></div>
          <div class="mr-kpi red"><span>Usado</span><strong>{{ formatMoney(margemConsulta.margem_emp_usada) }}</strong></div>
          <div class="mr-kpi green"><span>Disponível</span><strong>{{ formatMoney(margemConsulta.margem_emp_disponivel) }}</strong></div>
        </div>
        <div class="margem-label" style="margin:.75rem 0 .25rem">💳 Cartão (10%)</div>
        <div class="mr-kpis">
          <div class="mr-kpi blue"><span>Limite</span><strong>{{ formatMoney(margemConsulta.margem_cartao_total) }}</strong></div>
          <div class="mr-kpi red"><span>Usado</span><strong>{{ formatMoney(margemConsulta.margem_cartao_usada) }}</strong></div>
          <div class="mr-kpi green"><span>Disponível</span><strong>{{ formatMoney(margemConsulta.margem_cartao_disponivel) }}</strong></div>
        </div>
      </div>
    </div>

    <!-- RELATÓRIO -->
    <div v-if="aba === 'relatorio'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📈 Relatório por Competência</h2>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <input type="month" v-model="relCompetencia" @change="carregarRelatorio" class="form-input small" />
          <button class="btn-secondary" @click="exportarCSV" title="Exportar relatório analítico para TCE-MA">📥 CSV TCE-MA</button>
        </div>
      </div>
      <div class="rel-grid" v-if="relatorio.por_convenio?.length">
        <div v-for="c in relatorio.por_convenio" :key="c.CONVENIO_ID" class="rel-card">
          <span class="tipo-badge" :class="c.CONVENIO_TIPO?.toLowerCase()">{{ c.CONVENIO_TIPO }}</span>
          <div class="rc-nome">{{ c.CONVENIO_NOME }}</div>
          <div class="rc-stats">👥 {{ c.qtd_servidores }}</div>
          <div class="rc-valor">{{ formatMoney(c.total) }}</div>
        </div>
      </div>
      <div v-if="relatorio.totais" class="rel-totais">
        <span>Total descontado: <strong>{{ formatMoney(relatorio.totais?.total_descontado) }}</strong></span>
        <span>Pendente: <strong>{{ formatMoney(relatorio.totais?.total_pendente) }}</strong></span>
        <span>Servidores: <strong>{{ relatorio.totais?.qtd_servidores }}</strong></span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba = ref('contratos'), loaded = ref(false), loading = ref(false)
const contratos = ref([]), convenios = ref([]), totais = ref({})
const parcelas = ref([]), contratoDetalhe = ref(null)
const filtros = ref({ status: '' })
const relatorio = ref({}), relCompetencia = ref(new Date().toISOString().slice(0, 7))
const busca = ref(''), resultados = ref([]), servidor = ref(null), salvando = ref(false)
const msgNovo = ref(''), erroNovo = ref(''), margem = ref({})
const form = ref({ convenio_id: '', numero_contrato: '', data_inicio: new Date().toISOString().slice(0, 10), valor_total: '', valor_parcela: '', prazo_meses: 12, taxa_juros: 0 })
const buscaMargem = ref(''), resultadosMargem = ref([]), margemConsulta = ref({})

// Percentuais de uso de margem — separados por tipo (CONSIG-01)
const pctEmp    = computed(() => margem.value.margem_emp_total > 0 ? Math.min(100, Math.round((margem.value.margem_emp_usada    / margem.value.margem_emp_total)    * 100)) : 0)
const pctCartao = computed(() => margem.value.margem_cartao_total > 0 ? Math.min(100, Math.round((margem.value.margem_cartao_usada / margem.value.margem_cartao_total) * 100)) : 0)

async function carregarContratos() {
  loading.value = true
  try {
    const q = new URLSearchParams(Object.entries(filtros.value).filter(([, v]) => v))
    const d = (await api.get(`/api/v3/consignacao?${q}`)).data
    contratos.value = d.contratos || []; convenios.value = d.convenios || []; totais.value = d.totais || {}
  } catch (e) { console.error(e) } finally { loading.value = false }
}
async function carregarRelatorio() {
  try { relatorio.value = (await api.get(`/api/v3/consignacao/relatorio?competencia=${relCompetencia.value}`)).data }
  catch (e) { console.error(e) }
}
async function verParcelas(c) {
  contratoDetalhe.value = c
  parcelas.value = (await api.get(`/api/v3/consignacao/${c.CONTRATO_ID}/parcelas`)).data.parcelas || []
}
async function mudarStatus(id, status) {
  await api.patch(`/api/v3/consignacao/${id}/status`, { status })
  await carregarContratos()
}
let t = null
function buscarServidor() {
  clearTimeout(t)
  if (busca.value.length < 2) { resultados.value = []; return }
  t = setTimeout(async () => {
    resultados.value = (await api.get(`/api/v3/servidores/buscar?q=${encodeURIComponent(busca.value)}`)).data.servidores || []
  }, 300)
}
function selecionarServidor(s) { servidor.value = s; busca.value = s.nome; resultados.value = []; margem.value = {} }
async function consultarMargem() {
  if (!servidor.value) return
  margem.value = (await api.get(`/api/v3/consignacao/margem/${servidor.value.id}`)).data
}
async function registrarContrato() {
  salvando.value = true; erroNovo.value = ''
  try {
    const d = (await api.post('/api/v3/consignacao', { funcionario_id: servidor.value.id, ...form.value })).data
    if (d.ok) { msgNovo.value = `✅ Contrato #${d.contrato_id} registrado!`; servidor.value = null; busca.value = '' }
    else erroNovo.value = d.aviso || d.erro || 'Erro ao registrar.'
  } catch (e) { erroNovo.value = e.response?.data?.aviso || e.response?.data?.erro || 'Erro de rede.' } finally { salvando.value = false }
}
let t2 = null
function buscarServidorMargem() {
  clearTimeout(t2)
  if (buscaMargem.value.length < 2) { resultadosMargem.value = []; return }
  t2 = setTimeout(async () => {
    resultadosMargem.value = (await api.get(`/api/v3/servidores/buscar?q=${encodeURIComponent(buscaMargem.value)}`)).data.servidores || []
  }, 300)
}
async function consultarMargemDireto(s) {
  buscaMargem.value = s.nome; resultadosMargem.value = []
  margemConsulta.value = { nome: s.nome, ...(await api.get(`/api/v3/consignacao/margem/${s.id}`)).data }
}
// CONSIG-05: Exportar CSV analítico para TCE-MA (BOM UTF-8, separador ;)
async function exportarCSV() {
  try {
    const { data } = await api.get(`/api/v3/consignacao/relatorio-analitico?competencia=${relCompetencia.value}`)
    const cols = ['Nome','Matrícula','CPF','Credor','Tipo','Nº Contrato','Parcela','Prazo','Valor Desconto','Saldo Devedor','Status']
    const rows = (data.servidores || []).map(s => [
      s.nome, s.matricula, s.cpf, s.credor, s.tipo,
      s.NUMERO_CONTRATO, s.NUMERO_PARCELA, s.PRAZO_MESES,
      s.valor_desconto, s.SALDO_DEVEDOR, s.STATUS
    ])
    const csv = [cols, ...rows].map(l => l.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';')).join('\n')
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `consignacoes_${relCompetencia.value}.csv`
    a.click()
  } catch (e) { alert('Erro ao exportar: ' + (e.response?.data?.erro || e.message)) }
}
const formatMoney = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(async () => { await carregarContratos(); setTimeout(() => loaded.value = true, 80) })
</script>


<style scoped>
.cs-page { display:flex; flex-direction:column; gap:1.5rem; padding:1.5rem; max-width:1200px; margin:0 auto; }
.hero { background:linear-gradient(135deg,#1e1b4b,#312e81,#4338ca); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-16px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes { position:absolute; inset:0; pointer-events:none; }
.hs { position:absolute; border-radius:50%; opacity:.12; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#818cf8; }
.hs2 { width:100px; height:100px; bottom:-20px; left:60px; background:#c7d2fe; }
.hero-eyebrow { font-size:.75rem; font-weight:800; letter-spacing:.1em; color:#a5b4fc; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .5rem; }
.hero-sub { opacity:.8; font-size:.88rem; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.hero-kpis { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.75rem 1.25rem; text-align:center; min-width:110px; }
.kpi-card.blue   { border-top:3px solid #60a5fa; }
.kpi-card.purple { border-top:3px solid #c084fc; }
.kpi-card.indigo { border-top:3px solid #818cf8; }
.kpi-label { display:block; font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
.kpi-val { display:block; font-size:1.25rem; font-weight:800; }
.tabs-bar { display:flex; gap:.5rem; flex-wrap:wrap; opacity:0; transform:translateY(-8px); transition:opacity .4s .15s,transform .4s .15s; }
.tabs-bar.loaded { opacity:1; transform:none; }
.tab-btn { padding:.6rem 1.1rem; border-radius:8px; border:none; cursor:pointer; background:#f1f5f9; color:#475569; font-weight:600; font-size:.83rem; transition:all .2s; }
.tab-btn.active { background:linear-gradient(135deg,#4338ca,#6366f1); color:#fff; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(12px); transition:opacity .4s .2s,transform .4s .2s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:1.1rem; font-weight:700; color:#1e293b; margin-bottom:1.25rem; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.toolbar-right { display:flex; align-items:center; gap:.75rem; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.78rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; }
.form-input.small { width:170px; }
.search-wrap { position:relative; }
.autocomplete-list { position:absolute; top:100%; left:0; right:0; background:#fff; border:1.5px solid #e2e8f0; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:20; list-style:none; margin:0; padding:.25rem 0; }
.autocomplete-list li { padding:.6rem 1rem; cursor:pointer; display:flex; flex-direction:column; }
.autocomplete-list li:hover { background:#f8fafc; }
.sub { font-size:.72rem; color:#94a3b8; }
.form-actions { display:flex; gap:.75rem; margin-top:1rem; }
.btn-primary { background:linear-gradient(135deg,#4338ca,#6366f1); color:#fff; border:none; padding:.65rem 1.5rem; border-radius:8px; font-weight:700; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { background:#f1f5f9; color:#475569; border:none; padding:.6rem 1rem; border-radius:8px; font-weight:600; cursor:pointer; }
.success-msg { margin-top:.75rem; background:#dcfce7; color:#15803d; border-radius:8px; padding:.75rem 1rem; font-weight:600; font-size:.88rem; }
.error-msg { margin-top:.75rem; background:#fee2e2; color:#991b1b; border-radius:8px; padding:.75rem 1rem; font-weight:600; font-size:.88rem; }
.margem-box { background:#f5f3ff; border-radius:12px; padding:1.25rem; margin:.75rem 0; border-left:4px solid #6366f1; }
.margem-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:.5rem; margin-bottom:.75rem; }
.mg-item { background:#fff; border-radius:8px; padding:.6rem .75rem; text-align:center; }
.mg-lbl { display:block; font-size:.7rem; color:#64748b; text-transform:uppercase; }
.mg-val { display:block; font-size:.95rem; font-weight:700; color:#1e293b; }
.margem-bar-wrap { display:flex; align-items:center; gap:.75rem; }
.margem-bar { flex:1; height:12px; background:#e2e8f0; border-radius:999px; overflow:hidden; }
.margem-fill { height:100%; background:#6366f1; border-radius:999px; transition:width .4s; }
.margem-fill.danger { background:#dc2626; }
.margem-pct { font-size:.8rem; font-weight:700; color:#64748b; white-space:nowrap; }
.margem-resultado { background:#f8fafc; border-radius:12px; padding:1.25rem; margin-top:1rem; }
.mr-nome { font-size:1.1rem; font-weight:700; color:#1e293b; margin-bottom:1rem; }
.mr-kpis { display:flex; gap:.75rem; flex-wrap:wrap; }
.mr-kpi { background:#fff; border-radius:10px; padding:.75rem 1rem; text-align:center; min-width:100px; border-bottom:3px solid #e2e8f0; }
.mr-kpi.blue  { border-bottom-color:#6366f1; }
.mr-kpi.red   { border-bottom-color:#dc2626; }
.mr-kpi.green { border-bottom-color:#16a34a; }
.mr-kpi span { display:block; font-size:.7rem; color:#64748b; text-transform:uppercase; }
.mr-kpi strong { display:block; font-size:.9rem; font-weight:800; }
.table-scroll { overflow-x:auto; }
.cs-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.cs-table th { text-align:left; padding:.6rem .75rem; background:#f8fafc; font-size:.72rem; text-transform:uppercase; color:#64748b; }
.cs-table td { padding:.55rem .75rem; border-bottom:1px solid #f1f5f9; }
.nome-cell { display:block; font-weight:600; color:#1e293b; }
.money { text-align:right; font-weight:600; }
.money.red { color:#dc2626; }
.tipo-badge { display:inline-block; border-radius:4px; padding:.15rem .5rem; font-size:.7rem; font-weight:700; }
.tipo-badge.banco { background:#dbeafe; color:#1e40af; }
.tipo-badge.sindicato { background:#fef3c7; color:#92400e; }
.tipo-badge.cooperativa { background:#dcfce7; color:#15803d; }
.tipo-badge.cartao { background:#fce7f3; color:#9d174d; }
.tipo-badge.outros { background:#f1f5f9; color:#475569; }
.status-badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.status-badge.ativo     { background:#dcfce7; color:#15803d; }
.status-badge.suspenso  { background:#fef3c7; color:#92400e; }
.status-badge.quitado   { background:#dbeafe; color:#1e40af; }
.status-badge.cancelado { background:#fee2e2; color:#991b1b; }
.status-badge.pendente  { background:#f1f5f9; color:#64748b; }
.status-badge.descontada { background:#dcfce7; color:#15803d; }
.progresso-wrap { display:flex; align-items:center; gap:.5rem; min-width:120px; }
.progresso-bar { flex:1; height:8px; background:#e2e8f0; border-radius:999px; overflow:hidden; }
.progresso-fill { height:100%; background:#6366f1; border-radius:999px; }
.progresso-txt { font-size:.72rem; color:#64748b; white-space:nowrap; }
.act-btn { border:none; border-radius:6px; padding:.3rem .55rem; font-size:.78rem; cursor:pointer; margin-right:.2rem; }
.act-btn.orange { background:#fef3c7; color:#92400e; }
.act-btn.blue   { background:#dbeafe; color:#1e40af; }
.act-btn.gray   { background:#f1f5f9; color:#475569; }
.empty-state { text-align:center; padding:3rem; color:#94a3b8; font-size:.9rem; }
.spinner-wrap { display:flex; justify-content:center; padding:2rem; }
.spinner { width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#6366f1; border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.rel-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem; }
.rel-card { background:#f5f3ff; border-radius:12px; padding:1.25rem; border-left:4px solid #6366f1; }
.rc-nome { font-size:.95rem; font-weight:700; color:#1e293b; margin:.4rem 0; }
.rc-stats { font-size:.8rem; color:#64748b; margin-bottom:.4rem; }
.rc-valor { font-size:1.2rem; font-weight:800; color:#4338ca; }
.rel-totais { margin-top:1rem; padding:.75rem 1rem; background:#f8fafc; border-radius:8px; display:flex; gap:1.5rem; flex-wrap:wrap; font-size:.83rem; color:#475569; }
.rel-totais strong { color:#1e293b; }
.margem-label { font-size:.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
.margem-fill.cartao { background:#ec4899; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:50; display:flex; align-items:center; justify-content:center; }
.modal { background:#fff; border-radius:16px; padding:1.5rem; min-width:480px; max-width:90vw; max-height:80vh; overflow-y:auto; }
.modal-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-hdr h3 { font-size:1rem; font-weight:700; }
.modal-close { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b; }
</style>
