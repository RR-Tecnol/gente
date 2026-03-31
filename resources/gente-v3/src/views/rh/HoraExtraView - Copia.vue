<template>
  <div class="he-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⏱️ Gestão de Ponto</span>
          <h1 class="hero-title">Hora Extra e Plantão Extra</h1>
          <p class="hero-sub">Registro, aprovação e consolidação por secretaria</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card">
            <span class="kpi-label">Total Horas</span>
            <span class="kpi-val">{{ totalHoras }}h</span>
          </div>
          <div class="kpi-card amber">
            <span class="kpi-label">Total Valor</span>
            <span class="kpi-val">{{ formatMoney(totalValor) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'lista' }" @click="aba = 'lista'">📋 Lista de Horas Extras</button>
      <button class="tab-btn" :class="{ active: aba === 'lancar' }" @click="aba = 'lancar'">➕ Lançar Hora Extra</button>
      <button class="tab-btn" :class="{ active: aba === 'relatorio' }" @click="aba = 'relatorio'; carregarRelatorio()">📊 Relatório por Secretaria</button>
    </div>

    <!-- LISTA -->
    <div v-if="aba === 'lista'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Horas Extras Registradas</h2>
        <div class="toolbar-right">
          <input v-model="filtros.competencia" type="month" class="form-input small" @change="carregarLista" />
          <select v-model="filtros.unidade_id" class="form-input small" @change="carregarLista">
            <option value="">Todas as secretarias</option>
            <option v-for="u in unidades" :key="u.id" :value="u.id">{{ u.nome }}</option>
          </select>
        </div>
      </div>
      <div class="table-scroll">
        <table class="he-table" v-if="lista.length">
          <thead>
            <tr>
              <th>Servidor</th><th>Secretaria</th><th>Data</th>
              <th>Horas</th><th>Tipo</th><th>Valor</th><th>Status</th><th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in lista" :key="h.HORA_EXTRA_ID">
              <td>
                <span class="nome-cell">{{ h.nome }}</span>
                <span class="sub">{{ h.matricula }}</span>
              </td>
              <td>{{ h.secretaria || '—' }}</td>
              <td>{{ formatData(h.DATA_REALIZACAO) }}</td>
              <td><strong>{{ h.TOTAL_HORAS }}h</strong></td>
              <td><span class="tipo-badge">{{ h.TIPO_HORA_EXTRA?.replace('_PORCENTO', '%') }}</span></td>
              <td class="money amber">{{ formatMoney(h.VALOR_CALCULADO) }}</td>
              <td><span class="status-badge" :class="h.STATUS?.toLowerCase()">{{ h.STATUS }}</span></td>
              <td>
                <button v-if="h.STATUS === 'PENDENTE'" class="act-btn green" @click="alterarStatus(h.HORA_EXTRA_ID, 'APROVADA')">✅ Aprovar</button>
                <button v-if="h.STATUS === 'PENDENTE'" class="act-btn red" @click="alterarStatus(h.HORA_EXTRA_ID, 'REJEITADA')">❌ Rejeitar</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="empty-state">📭 Nenhuma hora extra registrada para os filtros selecionados.</div>
        <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
      </div>
    </div>

    <!-- LANÇAR -->
    <div v-if="aba === 'lancar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">➕ Lançar Hora Extra</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>Servidor</label>
          <div class="search-wrap">
            <input v-model="busca" @input="buscarServidor" placeholder="🔍 Nome ou matrícula..." class="form-input" />
            <ul v-if="resultados.length" class="autocomplete-list">
              <li v-for="s in resultados" :key="s.id" @click="selecionarServidor(s)">
                <strong>{{ s.nome }}</strong><span class="sub">{{ s.matricula }} — {{ s.cargo }}</span>
              </li>
            </ul>
          </div>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="month" v-model="form.competencia" class="form-input" />
        </div>
        <div class="form-group">
          <label>Data de Realização</label>
          <input type="date" v-model="form.data_realizacao" class="form-input" />
        </div>
        <div class="form-group">
          <label>Hora Início</label>
          <input type="time" v-model="form.hora_inicio" class="form-input" />
        </div>
        <div class="form-group">
          <label>Hora Fim</label>
          <input type="time" v-model="form.hora_fim" @change="calcularHoras" class="form-input" />
        </div>
        <div class="form-group">
          <label>Total de Horas</label>
          <input type="number" step="0.5" v-model="form.total_horas" class="form-input" />
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <select v-model="form.tipo_hora_extra" class="form-input">
            <option value="50_PORCENTO">50% — Hora Extra Normal</option>
            <option value="100_PORCENTO">100% — Feriado/Folga</option>
            <option value="FERIADO">Feriado Municipal</option>
          </select>
        </div>
        <div class="form-group">
          <label>Percentual (%)</label>
          <input type="number" v-model="form.percentual" class="form-input" />
        </div>
        <div class="form-group">
          <label>Observação</label>
          <input v-model="form.observacao" class="form-input" placeholder="Motivo, autorização..." />
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-primary" @click="lancarHoraExtra" :disabled="!servidor || salvando">
          {{ salvando ? '⏳ Salvando...' : '💾 Registrar Hora Extra' }}
        </button>
      </div>
      <div v-if="msgLancar" class="success-msg">{{ msgLancar }}</div>
    </div>

    <!-- RELATÓRIO -->
    <div v-if="aba === 'relatorio'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📊 Relatório por Secretaria</h2>
        <input v-model="relCompetencia" type="month" class="form-input small" @change="carregarRelatorio" />
      </div>
      <div class="rel-grid" v-if="relatorio.length">
        <div v-for="r in relatorio" :key="r.UNIDADE_ID" class="rel-card">
          <div class="rc-nome">{{ r.secretaria }}</div>
          <div class="rc-stats">
            <span>👥 {{ r.qtd_servidores }} servidores</span>
            <span>⏱️ {{ r.total_horas }}h total</span>
          </div>
          <div class="rc-valor">{{ formatMoney(r.total_valor) }}</div>
          <div class="rc-breakdown">
            <span>50%: {{ r.horas_50 }}h</span>
            <span>100%: {{ r.horas_100 }}h</span>
            <span>Feriado: {{ r.horas_feriado }}h</span>
          </div>
        </div>
      </div>
      <div v-else class="empty-state">📭 Nenhum dado para a competência selecionada.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba           = ref('lista')
const loaded        = ref(false)
const loading       = ref(false)
const lista         = ref([])
const unidades      = ref([])
const relatorio     = ref([])
const filtros       = ref({ competencia: new Date().toISOString().slice(0, 7), unidade_id: '' })
const relCompetencia = ref(new Date().toISOString().slice(0, 7))
const totalHoras    = computed(() => lista.value.reduce((s, h) => s + (h.TOTAL_HORAS || 0), 0).toFixed(1))
const totalValor    = computed(() => lista.value.reduce((s, h) => s + (h.VALOR_CALCULADO || 0), 0))

// Lançar
const busca     = ref('')
const resultados = ref([])
const servidor  = ref(null)
const salvando  = ref(false)
const msgLancar = ref('')
const form      = ref({ competencia: new Date().toISOString().slice(0, 7), data_realizacao: '', hora_inicio: '', hora_fim: '', total_horas: 0, tipo_hora_extra: '50_PORCENTO', percentual: 50, observacao: '' })

async function carregarLista() {
  loading.value = true
  try {
    const q = new URLSearchParams(Object.entries(filtros.value).filter(([, v]) => v))
    const d = (await api.get(`/api/v3/hora-extra?${q}`)).data
    lista.value    = d.lista || []
    unidades.value = d.unidades || []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarRelatorio() {
  try {
    relatorio.value = (await api.get(`/api/v3/hora-extra/relatorio-secretaria?competencia=${relCompetencia.value}`)).data.dados || []
  } catch (e) { console.error(e) }
}

function calcularHoras() {
  if (!form.value.hora_inicio || !form.value.hora_fim) return
  const [hi, mi] = form.value.hora_inicio.split(':').map(Number)
  const [hf, mf] = form.value.hora_fim.split(':').map(Number)
  const mins = (hf * 60 + mf) - (hi * 60 + mi)
  form.value.total_horas = Math.max(0, mins / 60)
}

let buscarTimer = null
function buscarServidor() {
  clearTimeout(buscarTimer)
  if (busca.value.length < 2) { resultados.value = []; return }
  buscarTimer = setTimeout(async () => {
    resultados.value = (await api.get(`/api/v3/servidores/buscar?q=${encodeURIComponent(busca.value)}`)).data.servidores || []
  }, 300)
}
function selecionarServidor(s) { servidor.value = s; busca.value = s.nome; resultados.value = [] }

async function lancarHoraExtra() {
  if (!servidor.value) return
  salvando.value = true
  try {
    const d = (await api.post('/api/v3/hora-extra', { funcionario_id: servidor.value.id, ...form.value })).data
    if (d.ok) {
      msgLancar.value = `✅ Registrado! Valor calculado: ${formatMoney(d.valor_calculado)}`
      servidor.value = null; busca.value = ''
    }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function alterarStatus(id, status) {
  await api.patch(`/api/v3/hora-extra/${id}/status`, { status })
  await carregarLista()
}

function formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0) }
function formatData(d) { if (!d) return '—'; return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') }

onMounted(async () => { await carregarLista(); setTimeout(() => loaded.value = true, 80) })
</script>


<style scoped>
.he-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
.hero { background: linear-gradient(135deg, #1a1a2e, #0f3460); border-radius: 20px; padding: 2rem; color: #fff; position: relative; overflow: hidden; opacity: 0; transform: translateY(-16px); transition: opacity .5s, transform .5s; }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; opacity: .1; }
.hs1 { width: 200px; height: 200px; top: -60px; right: -40px; background: #f39c12; }
.hs2 { width: 100px; height: 100px; bottom: -20px; left: 60px; background: #fff; }
.hero-eyebrow { font-size: .75rem; font-weight: 800; letter-spacing: .1em; color: #f39c12; text-transform: uppercase; }
.hero-title { font-size: 1.8rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .9rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; position: relative; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi-card { background: rgba(255,255,255,.1); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 100px; border-top: 3px solid #3498db; }
.kpi-card.amber { border-top-color: #f39c12; }
.kpi-label { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; letter-spacing: .05em; }
.kpi-val { display: block; font-size: 1.4rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; opacity: 0; transform: translateY(-8px); transition: opacity .4s .15s, transform .4s .15s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: .6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .85rem; transition: all .2s; }
.tab-btn.active { background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; }
.section-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; opacity: 0; transform: translateY(12px); transition: opacity .4s .2s, transform .4s .2s; }
.section-card.loaded { opacity: 1; transform: none; }
.section-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; }
.section-hdr { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.toolbar-right { display: flex; align-items: center; gap: .75rem; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: .35rem; }
.form-group label { font-size: .78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .6rem .9rem; font-size: .9rem; width: 100%; }
.form-input.small { width: 180px; }
.search-wrap { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.1); z-index: 20; list-style: none; margin: 0; padding: .25rem 0; }
.autocomplete-list li { padding: .6rem 1rem; cursor: pointer; display: flex; flex-direction: column; gap: .1rem; }
.autocomplete-list li:hover { background: #f8fafc; }
.autocomplete-list li .sub { font-size: .75rem; color: #94a3b8; }
.form-actions { margin-top: 1rem; }
.btn-primary { background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.success-msg { margin-top: 1rem; background: #dcfce7; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; font-size: .88rem; }
.table-scroll { overflow-x: auto; }
.he-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.he-table th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; text-transform: uppercase; color: #64748b; }
.he-table td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.nome-cell { display: block; font-weight: 600; color: #1e293b; }
.sub { display: block; font-size: .72rem; color: #94a3b8; }
.tipo-badge { display: inline-block; background: #eff6ff; color: #1e40af; border-radius: 4px; padding: .15rem .5rem; font-size: .72rem; font-weight: 700; }
.status-badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.status-badge.pendente { background: #fef3c7; color: #92400e; }
.status-badge.aprovada { background: #dcfce7; color: #15803d; }
.status-badge.rejeitada { background: #fee2e2; color: #991b1b; }
.money.amber { color: #b45309; font-weight: 600; text-align: right; }
.act-btn { border: none; border-radius: 6px; padding: .3rem .65rem; font-size: .75rem; cursor: pointer; font-weight: 600; margin-right: .25rem; }
.act-btn.green { background: #dcfce7; color: #15803d; }
.act-btn.red { background: #fee2e2; color: #991b1b; }
.empty-state { text-align: center; padding: 3rem; color: #94a3b8; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #f39c12; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.rel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.rel-card { background: #f8fafc; border-radius: 12px; padding: 1.25rem; border-left: 4px solid #f39c12; }
.rc-nome { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: .5rem; }
.rc-stats { display: flex; gap: 1rem; font-size: .82rem; color: #64748b; margin-bottom: .5rem; }
.rc-valor { font-size: 1.3rem; font-weight: 800; color: #b45309; margin-bottom: .5rem; }
.rc-breakdown { display: flex; gap: .75rem; font-size: .75rem; color: #94a3b8; flex-wrap: wrap; }
</style>
