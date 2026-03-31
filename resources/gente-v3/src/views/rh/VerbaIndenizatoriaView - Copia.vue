<template>
  <div class="vi-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/><div class="hs hs3"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">💰 Gestão de Pessoal</span>
          <h1 class="hero-title">Verbas Indenizatórias</h1>
          <p class="hero-sub">Auxílio-alimentação, transporte, moradia, diárias e outros benefícios mensais</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card green">
            <span class="kpi-label">Lançamentos</span>
            <span class="kpi-val">{{ lista.length }}</span>
          </div>
          <div class="kpi-card teal">
            <span class="kpi-label">Total Competência</span>
            <span class="kpi-val">{{ formatMoney(totalGeral) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'lista' }" @click="aba = 'lista'">📋 Lançamentos</button>
      <button class="tab-btn" :class="{ active: aba === 'lancar' }" @click="aba = 'lancar'">➕ Lançar Verba</button>
      <button class="tab-btn" :class="{ active: aba === 'lote' }" @click="aba = 'lote'">☑️ Lançamento em Lote</button>
      <button class="tab-btn" :class="{ active: aba === 'relatorio' }" @click="aba = 'relatorio'; carregarRelatorio()">📊 Relatório</button>
      <button class="tab-btn" :class="{ active: aba === 'tipos' }" @click="aba = 'tipos'; carregarTipos()">⚙️ Tipos</button>
    </div>

    <!-- ── LISTA DE LANÇAMENTOS ──────────────────────────────── -->
    <div v-if="aba === 'lista'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Lançamentos da Competência</h2>
        <div class="toolbar-right">
          <input type="month" v-model="filtros.competencia" @change="carregarLista" class="form-input small" />
          <select v-model="filtros.unidade_id" @change="carregarLista" class="form-input small">
            <option value="">Todas as secretarias</option>
            <option v-for="u in unidades" :key="u.id" :value="u.id">{{ u.nome }}</option>
          </select>
          <select v-model="filtros.tipo_id" @change="carregarLista" class="form-input small">
            <option value="">Todos os tipos</option>
            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
          </select>
        </div>
      </div>

      <!-- Cards por tipo (resumo) -->
      <div class="tipo-cards" v-if="Object.keys(porTipo).length">
        <div v-for="(stat, nome) in porTipo" :key="nome" class="tc-card">
          <span class="tc-nome">{{ nome }}</span>
          <span class="tc-qtd">{{ stat.qtd }} servidor(es)</span>
          <span class="tc-valor">{{ formatMoney(stat.total) }}</span>
        </div>
      </div>

      <!-- Tabela -->
      <div class="table-scroll">
        <table class="vi-table" v-if="lista.length">
          <thead>
            <tr>
              <th>Servidor</th><th>Verba</th><th>Secretaria</th>
              <th>Competência</th><th>Valor</th><th>IR</th><th>INSS</th><th>Status</th><th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in lista" :key="l.VERBA_LANCAMENTO_ID" :class="{ 'row-pend': l.STATUS === 'PENDENTE' }">
              <td>
                <span class="nome-cell">{{ l.nome }}</span>
                <span class="sub">{{ l.matricula }}</span>
              </td>
              <td>
                <span class="tipo-badge">{{ l.verba_nome }}</span>
                <span v-if="l.requer_comprovante" class="comprov-ico" title="Requer comprovante">📎</span>
              </td>
              <td>{{ l.secretaria || '—' }}</td>
              <td>{{ l.COMPETENCIA }}</td>
              <td class="money green">{{ formatMoney(l.VALOR) }}</td>
              <td><span class="bool-badge" :class="l.incide_ir ? 'sim' : 'nao'">{{ l.incide_ir ? 'IR' : '—' }}</span></td>
              <td><span class="bool-badge" :class="l.incide_inss ? 'sim' : 'nao'">{{ l.incide_inss ? 'INSS' : '—' }}</span></td>
              <td><span class="status-badge" :class="l.STATUS?.toLowerCase()">{{ l.STATUS }}</span></td>
              <td class="actions-cell">
                <button v-if="l.STATUS === 'PENDENTE'" class="act-btn green" @click="aprovar(l.VERBA_LANCAMENTO_ID)">✅</button>
                <button v-if="l.STATUS === 'PENDENTE'" class="act-btn red" @click="cancelar(l.VERBA_LANCAMENTO_ID)">🗑️</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="empty-state">📭 Nenhum lançamento para esta competência.</div>
        <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
      </div>
    </div>

    <!-- ── LANÇAR INDIVIDUAL ──────────────────────────────────── -->
    <div v-if="aba === 'lancar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">➕ Lançar Verba Indenizatória</h2>
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
          <label>Tipo de Verba</label>
          <select v-model="form.verba_tipo_id" class="form-input" @change="onTipoChange">
            <option value="">Selecione...</option>
            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="month" v-model="form.competencia" class="form-input" />
        </div>
        <div class="form-group">
          <label>Valor (R$)</label>
          <input type="number" step="0.01" v-model="form.valor" class="form-input" placeholder="0,00" />
        </div>
        <div class="form-group" style="grid-column: span 2;">
          <label>Justificativa / Observação</label>
          <input v-model="form.justificativa" class="form-input" placeholder="Informe o motivo do lançamento..." />
        </div>
      </div>

      <!-- Info da verba selecionada -->
      <div v-if="tipoSelecionado" class="verba-info-box">
        <div class="vib-row">
          <span class="vib-lbl">Incide IR</span>
          <span class="vib-val" :class="tipoSelecionado.incide_ir ? 'sim' : 'nao'">
            {{ tipoSelecionado.incide_ir ? '✅ Sim' : '❌ Não (indenizatória)' }}
          </span>
        </div>
        <div class="vib-row">
          <span class="vib-lbl">Incide INSS</span>
          <span class="vib-val" :class="tipoSelecionado.incide_inss ? 'sim' : 'nao'">
            {{ tipoSelecionado.incide_inss ? '✅ Sim' : '❌ Não (indenizatória)' }}
          </span>
        </div>
        <div class="vib-row" v-if="tipoSelecionado.requer_comprovante">
          <span class="vib-lbl">⚠️ Comprovante</span>
          <span class="vib-val sim">Necessário (atache via eSocial)</span>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" @click="lancar" :disabled="!servidor || !form.verba_tipo_id || !form.valor || salvando">
          {{ salvando ? '⏳ Salvando...' : '💾 Lançar Verba' }}
        </button>
      </div>
      <div v-if="msgLancar" class="success-msg">{{ msgLancar }}</div>
    </div>

    <!-- ── LANÇAMENTO EM LOTE ─────────────────────────────────── -->
    <div v-if="aba === 'lote'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">☑️ Lançamento em Lote</h2>
      <p class="lote-desc">Defina o tipo de verba e a competência. Depois preencha o valor para cada servidor ativo.</p>
      <div class="form-grid lote-top">
        <div class="form-group">
          <label>Tipo de Verba</label>
          <select v-model="lote.verba_tipo_id" class="form-input" @change="carregarServidoresLote">
            <option value="">Selecione...</option>
            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="month" v-model="lote.competencia" class="form-input" />
        </div>
        <div class="form-group">
          <label>Valor padrão (aplicar a todos)</label>
          <input type="number" step="0.01" v-model="lote.valorPadrao" class="form-input" placeholder="0,00" />
        </div>
        <div class="form-group" style="align-self: end;">
          <button class="btn-secondary" @click="aplicarValorPadrao">📋 Aplicar para todos</button>
        </div>
      </div>

      <div class="table-scroll" v-if="lote.servidores.length">
        <table class="vi-table lote-table">
          <thead>
            <tr><th>☐</th><th>Servidor</th><th>Secretaria</th><th>Valor (R$)</th><th>Justificativa</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in lote.servidores" :key="s.id">
              <td><input type="checkbox" v-model="s.selecionado" /></td>
              <td><span class="nome-cell">{{ s.nome }}</span><span class="sub">{{ s.matricula }}</span></td>
              <td>{{ s.secretaria || '—' }}</td>
              <td><input type="number" step="0.01" v-model="s.valor" class="form-input micro" /></td>
              <td><input v-model="s.justificativa" class="form-input micro" placeholder="Opcional..." /></td>
            </tr>
          </tbody>
        </table>
        <div class="lote-footer">
          <span class="lote-sel">{{ lote.servidores.filter(s => s.selecionado).length }} selecionado(s)</span>
          <button class="btn-primary" @click="lancarLote" :disabled="salvando || !lote.verba_tipo_id">
            {{ salvando ? '⏳ Salvando...' : `💾 Lançar para ${lote.servidores.filter(s => s.selecionado).length} servidor(es)` }}
          </button>
        </div>
      </div>
      <div v-if="msgLote" class="success-msg">{{ msgLote }}</div>
    </div>

    <!-- ── RELATÓRIO ──────────────────────────────────────────── -->
    <div v-if="aba === 'relatorio'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📊 Relatório por Tipo e Secretaria</h2>
        <div class="toolbar-right">
          <input type="month" v-model="relCompetencia" @change="carregarRelatorio" class="form-input small" />
          <span class="total-geral">Total: <strong>{{ formatMoney(relatorio.total) }}</strong></span>
        </div>
      </div>

      <div class="rel-two-col" v-if="relatorio.por_tipo || relatorio.por_secretaria">
        <!-- Por tipo -->
        <div>
          <h3 class="sub-title">Por tipo de verba</h3>
          <table class="rel-table" v-if="relatorio.por_tipo?.length">
            <thead><tr><th>Verba</th><th>Qtd</th><th>IR</th><th>INSS</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="r in relatorio.por_tipo" :key="r.VERBA_TIPO_ID">
                <td>{{ r.verba }}</td>
                <td>{{ r.qtd }}</td>
                <td><span class="bool-badge" :class="r.incide_ir ? 'sim' : 'nao'">{{ r.incide_ir ? 'IR' : '—' }}</span></td>
                <td><span class="bool-badge" :class="r.incide_inss ? 'sim' : 'nao'">{{ r.incide_inss ? 'INSS' : '—' }}</span></td>
                <td class="money green">{{ formatMoney(r.total) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty-state small">Sem dados.</div>
        </div>
        <!-- Por secretaria -->
        <div>
          <h3 class="sub-title">Por secretaria</h3>
          <table class="rel-table" v-if="relatorio.por_secretaria?.length">
            <thead><tr><th>Secretaria</th><th>Servidores</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="r in relatorio.por_secretaria" :key="r.UNIDADE_ID">
                <td>{{ r.secretaria }}</td>
                <td>{{ r.qtd_servidores }}</td>
                <td class="money green">{{ formatMoney(r.total) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty-state small">Sem dados.</div>
        </div>
      </div>
    </div>

    <!-- ── TIPOS DE VERBA ─────────────────────────────────────── -->
    <div v-if="aba === 'tipos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">⚙️ Tipos de Verba Configuráveis</h2>
        <button class="btn-primary small" @click="novoTipo()">+ Novo Tipo</button>
      </div>
      <table class="vi-table" v-if="todosOsTipos.length">
        <thead>
          <tr><th>Nome</th><th>Grupo</th><th>Incide IR</th><th>Incide INSS</th><th>Comprovante</th><th>Ativo</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in todosOsTipos" :key="t.VERBA_TIPO_ID">
            <td><strong>{{ t.VERBA_NOME }}</strong></td>
            <td><span class="grupo-badge" :class="t.VERBA_GRUPO?.toLowerCase()">{{ t.VERBA_GRUPO }}</span></td>
            <td><span class="bool-badge" :class="t.INCIDE_IR ? 'sim' : 'nao'">{{ t.INCIDE_IR ? 'Sim' : 'Não' }}</span></td>
            <td><span class="bool-badge" :class="t.INCIDE_INSS ? 'sim' : 'nao'">{{ t.INCIDE_INSS ? 'Sim' : 'Não' }}</span></td>
            <td>{{ t.REQUER_COMPROVANTE ? '📎 Sim' : '—' }}</td>
            <td><span class="bool-badge" :class="t.ATIVO ? 'sim' : 'nao'">{{ t.ATIVO ? 'Ativo' : 'Inativo' }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba         = ref('lista')
const loaded      = ref(false)
const loading     = ref(false)
const lista       = ref([])
const tipos       = ref([])
const todosOsTipos = ref([])
const unidades    = ref([])
const porTipo     = ref({})
const totalGeral  = ref(0)
const filtros     = ref({ competencia: new Date().toISOString().slice(0, 7), unidade_id: '', tipo_id: '' })
const relCompetencia = ref(new Date().toISOString().slice(0, 7))
const relatorio   = ref({})

// Lançar individual
const busca       = ref('')
const resultados  = ref([])
const servidor    = ref(null)
const salvando    = ref(false)
const msgLancar   = ref('')
const form        = ref({ verba_tipo_id: '', competencia: new Date().toISOString().slice(0, 7), valor: '', justificativa: '' })
const tipoSelecionado = computed(() => todosOsTipos.value.find(t => String(t.VERBA_TIPO_ID) === String(form.value.verba_tipo_id)))
function onTipoChange() {}

// Lançamento em lote
const msgLote     = ref('')
const lote = ref({ verba_tipo_id: '', competencia: new Date().toISOString().slice(0, 7), valorPadrao: '', servidores: [] })

async function carregarLista() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filtros.value).filter(([, v]) => v))
    const { data } = await api.get('/api/v3/verba-indenizatoria', { params })
    lista.value    = data.lista || []
    porTipo.value  = data.por_tipo || {}
    totalGeral.value = data.total_geral || 0
    unidades.value = data.unidades || []
    tipos.value    = data.tipos || []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarTipos() {
  const { data } = await api.get('/api/v3/verba-indenizatoria/tipos')
  todosOsTipos.value = data.tipos || []
}

async function carregarRelatorio() {
  const { data } = await api.get('/api/v3/verba-indenizatoria/relatorio', { params: { competencia: relCompetencia.value } })
  relatorio.value = data
}

async function carregarServidoresLote() {
  if (!lote.value.verba_tipo_id) return
  const { data } = await api.get('/api/v3/servidores/buscar', { params: { q: '' } })
  lote.value.servidores = (data.servidores || []).map(s => ({
    ...s, valor: lote.value.valorPadrao || '', justificativa: '', selecionado: true
  }))
}

function aplicarValorPadrao() {
  lote.value.servidores.forEach(s => s.valor = lote.value.valorPadrao)
}

let buscarTimer = null
function buscarServidor() {
  clearTimeout(buscarTimer)
  if (busca.value.length < 2) { resultados.value = []; return }
  buscarTimer = setTimeout(async () => {
    const { data } = await api.get('/api/v3/servidores/buscar', { params: { q: busca.value } })
    resultados.value = data.servidores || []
  }, 300)
}
function selecionarServidor(s) { servidor.value = s; busca.value = s.nome; resultados.value = [] }

async function lancar() {
  if (!servidor.value) return
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/verba-indenizatoria', {
      funcionario_id: servidor.value.id, ...form.value
    })
    if (data.ok) {
      msgLancar.value = '✅ Verba lançada com sucesso!'
      servidor.value = null; busca.value = ''
      form.value = { verba_tipo_id: '', competencia: new Date().toISOString().slice(0, 7), valor: '', justificativa: '' }
      await carregarLista()
    }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function lancarLote() {
  salvando.value = true
  const sel = lote.value.servidores.filter(s => s.selecionado && s.valor > 0)
  try {
    const { data } = await api.post('/api/v3/verba-indenizatoria/lote', {
      verba_tipo_id: lote.value.verba_tipo_id,
      competencia:   lote.value.competencia,
      servidores:    sel.map(s => ({ funcionario_id: s.id, valor: s.valor, justificativa: s.justificativa })),
    })
    if (data.ok) msgLote.value = `✅ ${data.incluidos} lançamento(s) realizados!`
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function aprovar(id) {
  await api.patch(`/api/v3/verba-indenizatoria/${id}/status`, { status: 'APROVADO' })
  await carregarLista()
}
async function cancelar(id) {
  if (!confirm('Cancelar este lançamento?')) return
  await api.delete(`/api/v3/verba-indenizatoria/${id}`)
  await carregarLista()
}
function novoTipo() { alert('Funcionalidade de criar tipo disponível na próxima versão.') }

function formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0) }

onMounted(async () => {
  await Promise.all([carregarLista(), carregarTipos()])
  setTimeout(() => loaded.value = true, 80)
})
</script>

<style scoped>
.vi-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

/* HERO */
.hero { background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%); border-radius: 20px; padding: 2rem; color: #fff; position: relative; overflow: hidden; opacity: 0; transform: translateY(-16px); transition: opacity .5s, transform .5s; }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; opacity: .1; }
.hs1 { width: 200px; height: 200px; top: -60px; right: -40px; background: #10b981; }
.hs2 { width: 120px; height: 120px; bottom: -30px; left: 60px; background: #34d399; }
.hs3 { width: 80px; height: 80px; top: 30px; left: 40%; background: #fff; }
.hero-eyebrow { font-size: .75rem; font-weight: 800; letter-spacing: .1em; color: #6ee7b7; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .8; font-size: .88rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; position: relative; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi-card { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 110px; }
.kpi-card.green { border-top: 3px solid #34d399; }
.kpi-card.teal { border-top: 3px solid #5eead4; }
.kpi-label { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; letter-spacing: .05em; }
.kpi-val { display: block; font-size: 1.3rem; font-weight: 800; }

/* TABS */
.tabs-bar { display: flex; gap: .5rem; flex-wrap: wrap; opacity: 0; transform: translateY(-8px); transition: opacity .4s .15s, transform .4s .15s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; transition: all .2s; }
.tab-btn.active { background: linear-gradient(135deg, #059669, #047857); color: #fff; }

/* CARD */
.section-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; opacity: 0; transform: translateY(12px); transition: opacity .4s .2s, transform .4s .2s; }
.section-card.loaded { opacity: 1; transform: none; }
.section-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; }
.section-hdr { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.toolbar-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.total-geral { font-size: .88rem; color: #64748b; }

/* Tipos cards (resumo) */
.tipo-cards { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.tc-card { background: #f0fdf4; border-radius: 10px; padding: .75rem 1rem; border-left: 3px solid #10b981; min-width: 160px; }
.tc-nome { display: block; font-size: .78rem; font-weight: 700; color: #065f46; }
.tc-qtd  { display: block; font-size: .72rem; color: #64748b; }
.tc-valor { display: block; font-size: 1rem; font-weight: 800; color: #059669; }

/* FORM */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.lote-top { grid-template-columns: 1fr 140px 160px auto; align-items: end; }
.form-group { display: flex; flex-direction: column; gap: .35rem; }
.form-group label { font-size: .78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .6rem .9rem; font-size: .88rem; width: 100%; }
.form-input.small { width: 170px; }
.form-input.micro { padding: .4rem .6rem; font-size: .82rem; }
.search-wrap { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.1); z-index: 20; list-style: none; margin: 0; padding: .25rem 0; }
.autocomplete-list li { padding: .6rem 1rem; cursor: pointer; display: flex; flex-direction: column; }
.autocomplete-list li:hover { background: #f8fafc; }
.autocomplete-list li .sub { font-size: .75rem; color: #94a3b8; }

/* Verba info box */
.verba-info-box { background: #f0fdf4; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; gap: 1.5rem; flex-wrap: wrap; }
.vib-row { display: flex; flex-direction: column; gap: .2rem; }
.vib-lbl { font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.vib-val.sim { color: #15803d; font-weight: 600; }
.vib-val.nao { color: #94a3b8; }

/* FORM ACTIONS */
.form-actions { margin-top: 1rem; }
.btn-primary { background: linear-gradient(135deg, #059669, #047857); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: opacity .2s; }
.btn-primary:disabled { opacity: .4; cursor: not-allowed; }
.btn-primary.small { padding: .5rem 1rem; font-size: .82rem; }
.btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: .6rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
.success-msg { margin-top: 1rem; background: #dcfce7; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; font-size: .88rem; }
.lote-desc { font-size: .85rem; color: #64748b; margin-bottom: 1rem; }
.lote-footer { display: flex; align-items: center; justify-content: space-between; padding: .75rem 0; flex-wrap: wrap; gap: .5rem; }
.lote-sel { font-size: .85rem; color: #64748b; }

/* TABLE */
.table-scroll { overflow-x: auto; }
.vi-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.vi-table th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; text-transform: uppercase; color: #64748b; }
.vi-table td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.vi-table .row-pend { background: #fffbeb; }
.nome-cell { display: block; font-weight: 600; color: #1e293b; }
.sub { display: block; font-size: .72rem; color: #94a3b8; }
.tipo-badge { display: inline-block; background: #ecfdf5; color: #065f46; border-radius: 4px; padding: .15rem .5rem; font-size: .72rem; font-weight: 700; }
.comprov-ico { margin-left: .25rem; font-size: .8rem; }
.bool-badge { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .69rem; font-weight: 700; }
.bool-badge.sim { background: #dcfce7; color: #15803d; }
.bool-badge.nao { background: #f1f5f9; color: #94a3b8; }
.status-badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.status-badge.pendente { background: #fef3c7; color: #92400e; }
.status-badge.aprovado { background: #dcfce7; color: #15803d; }
.status-badge.incluido_folha { background: #dbeafe; color: #1e40af; }
.money.green { text-align: right; color: #059669; font-weight: 700; }
.actions-cell { white-space: nowrap; }
.act-btn { border: none; border-radius: 6px; padding: .3rem .65rem; font-size: .78rem; cursor: pointer; font-weight: 600; margin-right: .25rem; }
.act-btn.green { background: #dcfce7; color: #15803d; }
.act-btn.red { background: #fee2e2; color: #991b1b; }
.grupo-badge { display: inline-block; padding: .2rem .6rem; border-radius: 4px; font-size: .7rem; font-weight: 700; }
.grupo-badge.mensal { background: #ecfdf5; color: #065f46; }
.grupo-badge.rescisoria { background: #fee2e2; color: #991b1b; }
.empty-state { text-align: center; padding: 2.5rem; color: #94a3b8; font-size: .9rem; }
.empty-state.small { padding: 1rem; font-size: .82rem; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #059669; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* RELATÓRIO */
.rel-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.sub-title { font-size: .88rem; font-weight: 700; color: #374151; margin-bottom: .75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: .5rem; }
.rel-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.rel-table th { text-align: left; padding: .5rem .6rem; background: #f8fafc; font-size: .7rem; text-transform: uppercase; color: #64748b; }
.rel-table td { padding: .45rem .6rem; border-bottom: 1px solid #f1f5f9; }
</style>
