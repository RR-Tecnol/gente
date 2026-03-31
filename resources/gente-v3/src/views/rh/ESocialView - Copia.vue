<template>
  <div class="es-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🇧🇷 eSocial</span>
          <h1 class="hero-title">Painel eSocial</h1>
          <p class="hero-sub">Geração, rastreamento e envio de eventos ao governo federal</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card yellow"><span class="kpi-label">Pendentes</span><span class="kpi-val">{{ stats.pendentes ?? 0 }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Gerados</span><span class="kpi-val">{{ stats.gerados ?? 0 }}</span></div>
          <div class="kpi-card green"><span class="kpi-label">Processados</span><span class="kpi-val">{{ stats.processados ?? 0 }}</span></div>
          <div class="kpi-card red"><span class="kpi-label">Rejeitados</span><span class="kpi-val">{{ stats.rejeitados ?? 0 }}</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'eventos' }"    @click="aba = 'eventos'">📋 Eventos</button>
      <button class="tab-btn" :class="{ active: aba === 'pendencias' }" @click="aba = 'pendencias'; carregarPendencias()">⚠️ Pendências</button>
      <button class="tab-btn" :class="{ active: aba === 'gerar' }"      @click="aba = 'gerar'">➕ Gerar Evento</button>
    </div>

    <!-- LISTA EVENTOS -->
    <div v-if="aba === 'eventos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Eventos eSocial</h2>
        <div class="toolbar-right">
          <select v-model="filtros.tipo_evento" @change="carregarEventos" class="form-input small">
            <option value="">Todos os tipos</option>
            <option v-for="t in tiposEvento" :key="t" :value="t">{{ t }}</option>
          </select>
          <select v-model="filtros.status" @change="carregarEventos" class="form-input small">
            <option value="">Todos os status</option>
            <option value="PENDENTE">Pendente</option>
            <option value="GERADO">Gerado</option>
            <option value="ENVIADO">Enviado</option>
            <option value="PROCESSADO">Processado</option>
            <option value="REJEITADO">Rejeitado</option>
          </select>
          <input type="month" v-model="filtros.competencia" @change="carregarEventos" class="form-input small" />
        </div>
      </div>
      <div class="table-scroll">
        <table class="es-table" v-if="eventos.length">
          <thead>
            <tr><th>Tipo</th><th>Servidor</th><th>Competência</th><th>Data Ref.</th><th>Status</th><th>Recibo</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in eventos" :key="e.EVENTO_ID">
              <td><span class="tipo-badge" :class="tipoCss(e.TIPO_EVENTO)">{{ e.TIPO_EVENTO }}</span></td>
              <td><span class="nome-cell">{{ e.nome }}</span><span class="sub">{{ e.matricula }}</span></td>
              <td>{{ e.COMPETENCIA || '—' }}</td>
              <td>{{ formatDate(e.DATA_REFERENCIA) }}</td>
              <td><span class="status-badge" :class="e.STATUS?.toLowerCase()">{{ e.STATUS }}</span></td>
              <td class="mono">{{ e.NUMERO_RECIBO || '—' }}</td>
              <td>
                <button v-if="e.STATUS === 'GERADO'" class="act-btn blue" @click="marcarEnviado(e.EVENTO_ID)" title="Marcar como Enviado">📤</button>
                <button v-if="e.STATUS === 'REJEITADO'" class="act-btn orange" @click="reprocessar(e.EVENTO_ID)" title="Reprocessar">🔄</button>
                <button v-if="e.STATUS === 'ENVIADO'"   class="act-btn green"  @click="marcarProcessado(e.EVENTO_ID)" title="Confirmar Processado">✅</button>
                <button class="act-btn gray" @click="verDetalhe(e)" title="Ver XML">📄</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="empty-state">📭 Nenhum evento encontrado com os filtros aplicados.</div>
        <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
      </div>
      <!-- XML Modal -->
      <div v-if="eventoDetalhe" class="modal-overlay" @click.self="eventoDetalhe = null">
        <div class="modal">
          <div class="modal-hdr"><h3>📄 XML — {{ eventoDetalhe.TIPO_EVENTO }} | {{ eventoDetalhe.nome }}</h3><button class="modal-close" @click="eventoDetalhe = null">✕</button></div>
          <pre class="xml-pre">{{ eventoDetalhe.XML_GERADO || '(XML não gerado)' }}</pre>
          <div v-if="eventoDetalhe.MOTIVO_ERRO" class="error-msg">❌ {{ eventoDetalhe.MOTIVO_ERRO }}</div>
          <div v-if="eventoDetalhe.RETORNO_GOVERNO" class="retorno-box"><strong>Retorno:</strong> {{ eventoDetalhe.RETORNO_GOVERNO }}</div>
        </div>
      </div>
    </div>

    <!-- PENDÊNCIAS -->
    <div v-if="aba === 'pendencias'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">⚠️ Pendências eSocial</h2>
        <div class="toolbar-right">
          <input type="month" v-model="pendCompetencia" @change="carregarPendencias" class="form-input small" />
          <span class="total-badge">{{ pendencias.total_pendencias ?? 0 }} pendências</span>
        </div>
      </div>

      <div v-if="pendencias.admissoes_sem_evento?.length" class="pend-section">
        <h3 class="pend-title">🟡 Admissões sem S-2200 ({{ pendencias.admissoes_sem_evento.length }})</h3>
        <div class="pend-actions">
          <button class="btn-primary small" @click="gerarLote('S-2200', pendencias.admissoes_sem_evento)">▶ Gerar S-2200 em Lote</button>
        </div>
        <table class="es-table sm">
          <thead><tr><th>Servidor</th><th>Admissão</th><th>Evento</th></tr></thead>
          <tbody>
            <tr v-for="a in pendencias.admissoes_sem_evento" :key="a.FUNCIONARIO_ID">
              <td>{{ a.PESSOA_NOME }}</td>
              <td>{{ formatDate(a.FUNCIONARIO_DATA_ADMISSAO) }}</td>
              <td><span class="tipo-badge s2200">S-2200</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pendencias.demissoes_sem_evento?.length" class="pend-section">
        <h3 class="pend-title">🔴 Desligamentos sem S-2299 ({{ pendencias.demissoes_sem_evento.length }})</h3>
        <div class="pend-actions">
          <button class="btn-primary small red" @click="gerarLote('S-2299', pendencias.demissoes_sem_evento)">▶ Gerar S-2299 em Lote</button>
        </div>
        <table class="es-table sm">
          <thead><tr><th>Servidor</th><th>Desligamento</th><th>Evento</th></tr></thead>
          <tbody>
            <tr v-for="d in pendencias.demissoes_sem_evento" :key="d.FUNCIONARIO_ID">
              <td>{{ d.PESSOA_NOME }}</td>
              <td>{{ formatDate(d.FUNCIONARIO_DATA_FIM) }}</td>
              <td><span class="tipo-badge s2299">S-2299</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pendencias.eventos_rejeitados?.length" class="pend-section">
        <h3 class="pend-title">❌ Eventos Rejeitados ({{ pendencias.eventos_rejeitados.length }})</h3>
        <table class="es-table sm">
          <thead><tr><th>Tipo</th><th>Servidor</th><th>Motivo</th></tr></thead>
          <tbody>
            <tr v-for="r in pendencias.eventos_rejeitados" :key="r.EVENTO_ID">
              <td><span class="tipo-badge" :class="tipoCss(r.TIPO_EVENTO)">{{ r.TIPO_EVENTO }}</span></td>
              <td>{{ r.nome }}</td>
              <td class="erro-txt">{{ r.MOTIVO_ERRO }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!pendencias.total_pendencias && !loadingPend" class="empty-state success">🎉 Nenhuma pendência eSocial para esta competência!</div>
      <div v-if="msgLote" class="success-msg">{{ msgLote }}</div>
    </div>

    <!-- GERAR EVENTO -->
    <div v-if="aba === 'gerar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">➕ Gerar Evento Manualmente</h2>
      <div class="form-grid" style="max-width: 600px;">
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
          <label>Tipo de Evento</label>
          <select v-model="formGerar.tipo_evento" class="form-input">
            <option value="">Selecione...</option>
            <option v-for="t in tiposEvento" :key="t" :value="t">{{ t }} — {{ descEvento(t) }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência (para S-1200)</label>
          <input type="month" v-model="formGerar.competencia" class="form-input" />
        </div>
        <div class="form-group">
          <label>Data de Referência</label>
          <input type="date" v-model="formGerar.data_referencia" class="form-input" />
        </div>
      </div>
      <div v-if="servidor" class="servidor-selecionado">
        <span class="srv-nome">{{ servidor.nome }}</span>
        <span class="sub">Matrícula: {{ servidor.matricula }}</span>
      </div>
      <div class="form-actions">
        <button class="btn-primary" @click="gerarEvento" :disabled="!servidor || !formGerar.tipo_evento || salvando">
          {{ salvando ? '⏳ Gerando...' : '⚡ Gerar Evento' }}
        </button>
      </div>
      <div v-if="msgGerar"  class="success-msg">{{ msgGerar }}</div>
      <div v-if="erroGerar" class="error-msg">{{ erroGerar }}</div>

      <!-- Tabela de referência -->
      <div class="ref-table">
        <h3 class="ref-title">📚 Guia de Eventos eSocial — Folha e Pessoal</h3>
        <table class="es-table sm">
          <thead><tr><th>Evento</th><th>Quando Usar</th><th>Prazo</th></tr></thead>
          <tbody>
            <tr v-for="ev in guiaEventos" :key="ev.tipo">
              <td><span class="tipo-badge" :class="tipoCss(ev.tipo)">{{ ev.tipo }}</span></td>
              <td>{{ ev.desc }}</td>
              <td><span class="prazo-badge" :class="ev.urgente ? 'red' : 'blue'">{{ ev.prazo }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba = ref('eventos'), loaded = ref(false), loading = ref(false), loadingPend = ref(false)
const eventos = ref([]), stats = ref({}), eventoDetalhe = ref(null)
const filtros = ref({ tipo_evento: '', status: '', competencia: '' })
const pendencias = ref({}), pendCompetencia = ref(new Date().toISOString().slice(0, 7)), msgLote = ref('')
const busca = ref(''), resultados = ref([]), servidor = ref(null), salvando = ref(false)
const msgGerar = ref(''), erroGerar = ref('')
const formGerar = ref({ tipo_evento: '', competencia: '', data_referencia: new Date().toISOString().slice(0, 10) })

const tiposEvento = ['S-2200', 'S-2206', 'S-2230', 'S-2240', 'S-2299', 'S-1200', 'S-1210']
const guiaEventos = [
  { tipo: 'S-2200', desc: 'Admissão / cadastramento inicial de vínculo', prazo: 'Até o 1º dia do trabalho', urgente: true },
  { tipo: 'S-2206', desc: 'Alteração de contrato — cargo, salário, jornada', prazo: 'Até o dia 07 do mês seguinte', urgente: false },
  { tipo: 'S-2230', desc: 'Afastamento temporário — licença, férias, CID', prazo: 'Até o 1º dia do afastamento', urgente: true },
  { tipo: 'S-2240', desc: 'Condições ambientais do trabalho — insalubridade', prazo: '1× ao ano ou na mudança', urgente: false },
  { tipo: 'S-2299', desc: 'Desligamento / exoneração', prazo: 'Até 10 dias após o desligamento', urgente: true },
  { tipo: 'S-1200', desc: 'Remuneração do trabalhador no mês', prazo: 'Até dia 07 do mês seguinte', urgente: false },
  { tipo: 'S-1210', desc: 'Pagamentos de rendimentos — 13° e férias', prazo: 'Antes do pagamento', urgente: false },
]
const descEvento = t => guiaEventos.find(e => e.tipo === t)?.desc || ''
const tipoCss = t => {
  const m = { 'S-2200': 's2200', 'S-2206': 's2206', 'S-2230': 's2230', 'S-2299': 's2299', 'S-1200': 's1200', 'S-1210': 's1210', 'S-2240': 's2240' }
  return m[t] || ''
}
const formatDate = d => d ? new Date(d + 'T12:00:00').toLocaleDateString('pt-BR') : '—'

async function carregarEventos() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filtros.value).filter(([, v]) => v))
    const { data } = await api.get('/api/v3/esocial/eventos', { params })
    eventos.value = data.eventos || []; stats.value = data.stats || {}
  } catch (e) { console.error(e) } finally { loading.value = false }
}
async function carregarPendencias() {
  loadingPend.value = true
  try {
    const { data } = await api.get('/api/v3/esocial/pendencias', { params: { competencia: pendCompetencia.value } })
    pendencias.value = data
  } catch (e) { console.error(e) } finally { loadingPend.value = false }
}
async function marcarEnviado(id) {
  await api.patch(`/api/v3/esocial/eventos/${id}`, { status: 'ENVIADO' })
  await carregarEventos()
}
async function marcarProcessado(id) {
  await api.patch(`/api/v3/esocial/eventos/${id}`, { status: 'PROCESSADO' })
  await carregarEventos()
}
async function reprocessar(id) {
  await api.patch(`/api/v3/esocial/eventos/${id}`, { status: 'GERADO', motivo_erro: null })
  await carregarEventos()
}
function verDetalhe(e) { eventoDetalhe.value = e }

async function gerarLote(tipo, lista) {
  msgLote.value = ''
  const ids = lista.map(i => i.FUNCIONARIO_ID)
  const { data } = await api.post('/api/v3/esocial/gerar-lote', { tipo_evento: tipo, funcionario_ids: ids })
  msgLote.value = `✅ ${data.gerados} eventos ${tipo} gerados com sucesso!`
  await carregarPendencias()
}

let t = null
function buscarServidor() {
  clearTimeout(t)
  if (busca.value.length < 2) { resultados.value = []; return }
  t = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/v3/servidores/buscar', { params: { q: busca.value } })
      resultados.value = data.servidores || []
    } catch (e) { resultados.value = [] }
  }, 300)
}
function selecionarServidor(s) { servidor.value = s; busca.value = s.nome; resultados.value = [] }
async function gerarEvento() {
  if (!servidor.value) return
  salvando.value = true; erroGerar.value = ''
  try {
    const { data } = await api.post('/api/v3/esocial/eventos', { funcionario_id: servidor.value.id, ...formGerar.value })
    if (data.ok) { msgGerar.value = `✅ Evento ${formGerar.value.tipo_evento} gerado (ID: ${data.evento_id})`; servidor.value = null; busca.value = '' }
    else erroGerar.value = data.erro || 'Erro ao gerar evento.'
  } catch (e) { erroGerar.value = 'Erro de rede.' } finally { salvando.value = false }
}
onMounted(async () => { await carregarEventos(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.es-page { display:flex; flex-direction:column; gap:1.5rem; padding:1.5rem; max-width:1200px; margin:0 auto; }
.hero { background:linear-gradient(135deg,#0c4a6e,#0369a1,#0284c7); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-16px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes { position:absolute; inset:0; pointer-events:none; }
.hs { position:absolute; border-radius:50%; opacity:.12; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#38bdf8; }
.hs2 { width:100px; height:100px; bottom:-20px; left:60px; background:#7dd3fc; }
.hero-eyebrow { font-size:.75rem; font-weight:800; letter-spacing:.1em; color:#bae6fd; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .5rem; }
.hero-sub { opacity:.8; font-size:.88rem; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.hero-kpis { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.75rem 1.25rem; text-align:center; min-width:90px; }
.kpi-card.yellow { border-top:3px solid #fbbf24; }
.kpi-card.blue   { border-top:3px solid #38bdf8; }
.kpi-card.green  { border-top:3px solid #4ade80; }
.kpi-card.red    { border-top:3px solid #f87171; }
.kpi-label { display:block; font-size:.68rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
.kpi-val { display:block; font-size:1.3rem; font-weight:800; }
.tabs-bar { display:flex; gap:.5rem; flex-wrap:wrap; opacity:0; transform:translateY(-8px); transition:opacity .4s .15s,transform .4s .15s; }
.tabs-bar.loaded { opacity:1; transform:none; }
.tab-btn { padding:.6rem 1.1rem; border-radius:8px; border:none; cursor:pointer; background:#f1f5f9; color:#475569; font-weight:600; font-size:.83rem; transition:all .2s; }
.tab-btn.active { background:linear-gradient(135deg,#0369a1,#0284c7); color:#fff; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(12px); transition:opacity .4s .2s,transform .4s .2s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:1.1rem; font-weight:700; color:#1e293b; margin-bottom:1.25rem; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.toolbar-right { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
.total-badge { font-size:.88rem; color:#64748b; font-weight:600; }
.table-scroll { overflow-x:auto; }
.es-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.es-table.sm td,.es-table.sm th { padding:.45rem .6rem; }
.es-table th { text-align:left; padding:.6rem .75rem; background:#f8fafc; font-size:.72rem; text-transform:uppercase; color:#64748b; }
.es-table td { padding:.55rem .75rem; border-bottom:1px solid #f1f5f9; }
.nome-cell { display:block; font-weight:600; color:#1e293b; }
.sub { font-size:.72rem; color:#94a3b8; }
.mono { font-family:monospace; font-size:.78rem; color:#64748b; }
.tipo-badge { display:inline-block; border-radius:4px; padding:.18rem .55rem; font-size:.72rem; font-weight:800; letter-spacing:.03em; }
.tipo-badge.s2200 { background:#dcfce7; color:#15803d; }
.tipo-badge.s2206 { background:#dbeafe; color:#1e40af; }
.tipo-badge.s2230 { background:#fef3c7; color:#92400e; }
.tipo-badge.s2240 { background:#ede9fe; color:#5b21b6; }
.tipo-badge.s2299 { background:#fee2e2; color:#991b1b; }
.tipo-badge.s1200 { background:#e0f2fe; color:#0369a1; }
.tipo-badge.s1210 { background:#f0fdf4; color:#166534; }
.status-badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.status-badge.pendente    { background:#fef3c7; color:#92400e; }
.status-badge.gerado      { background:#dbeafe; color:#1e40af; }
.status-badge.enviado     { background:#e0f2fe; color:#0369a1; }
.status-badge.processado  { background:#dcfce7; color:#15803d; }
.status-badge.rejeitado   { background:#fee2e2; color:#991b1b; }
.act-btn { border:none; border-radius:6px; padding:.3rem .55rem; font-size:.78rem; cursor:pointer; margin-right:.2rem; transition:opacity .2s; }
.act-btn:hover { opacity:.75; }
.act-btn.blue   { background:#dbeafe; color:#1e40af; }
.act-btn.green  { background:#dcfce7; color:#15803d; }
.act-btn.orange { background:#fef3c7; color:#92400e; }
.act-btn.gray   { background:#f1f5f9; color:#475569; }
.empty-state { text-align:center; padding:3rem; color:#94a3b8; font-size:.9rem; }
.empty-state.success { color:#16a34a; }
.spinner-wrap { display:flex; justify-content:center; padding:2rem; }
.spinner { width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#0284c7; border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
/* Pendências */
.pend-section { margin-bottom:1.5rem; }
.pend-title { font-size:.9rem; font-weight:700; color:#374151; margin-bottom:.5rem; }
.pend-actions { margin-bottom:.5rem; }
.erro-txt { font-size:.78rem; color:#dc2626; }
/* Gerar */
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.78rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; }
.form-input.small { width:160px; }
.search-wrap { position:relative; }
.autocomplete-list { position:absolute; top:100%; left:0; right:0; background:#fff; border:1.5px solid #e2e8f0; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:20; list-style:none; margin:0; padding:.25rem 0; }
.autocomplete-list li { padding:.6rem 1rem; cursor:pointer; display:flex; flex-direction:column; }
.autocomplete-list li:hover { background:#f8fafc; }
.servidor-selecionado { display:flex; align-items:center; gap:.75rem; background:#f0f9ff; border-radius:8px; padding:.6rem 1rem; margin-bottom:.75rem; font-weight:600; }
.srv-nome { color:#0369a1; }
.form-actions { display:flex; gap:.75rem; margin-top:.5rem; }
.btn-primary { background:linear-gradient(135deg,#0369a1,#0284c7); color:#fff; border:none; padding:.65rem 1.5rem; border-radius:8px; font-weight:700; cursor:pointer; }
.btn-primary.small { padding:.45rem .9rem; font-size:.8rem; }
.btn-primary.red { background:linear-gradient(135deg,#dc2626,#ef4444); }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.success-msg { margin-top:.75rem; background:#dcfce7; color:#15803d; border-radius:8px; padding:.75rem 1rem; font-weight:600; font-size:.88rem; }
.error-msg { margin-top:.75rem; background:#fee2e2; color:#991b1b; border-radius:8px; padding:.75rem 1rem; font-weight:600; font-size:.88rem; }
/* Ref table */
.ref-table { margin-top:2rem; background:#f8fafc; border-radius:12px; padding:1.25rem; }
.ref-title { font-size:.9rem; font-weight:700; color:#374151; margin-bottom:.75rem; }
.prazo-badge { display:inline-block; padding:.18rem .55rem; border-radius:4px; font-size:.72rem; font-weight:600; }
.prazo-badge.red  { background:#fee2e2; color:#991b1b; }
.prazo-badge.blue { background:#dbeafe; color:#1e40af; }
/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:50; display:flex; align-items:center; justify-content:center; }
.modal { background:#fff; border-radius:16px; padding:1.5rem; min-width:520px; max-width:90vw; max-height:80vh; overflow-y:auto; }
.modal-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-hdr h3 { font-size:.95rem; font-weight:700; }
.modal-close { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b; }
.xml-pre { background:#0f172a; color:#a5f3fc; font-size:.78rem; border-radius:8px; padding:1rem; overflow-x:auto; white-space:pre-wrap; }
.retorno-box { background:#f1f5f9; border-radius:8px; padding:.75rem 1rem; font-size:.8rem; color:#374151; margin-top:.5rem; }
</style>
