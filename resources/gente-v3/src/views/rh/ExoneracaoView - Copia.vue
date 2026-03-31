<template>
  <div class="exon-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/><div class="hs hs3"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📋 Gestão de Pessoal</span>
          <h1 class="hero-title">Exoneração e Verbas Rescisórias</h1>
          <p class="hero-sub">Registro formal de desligamento e cálculo automático de verbas rescisórias</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card red">
            <span class="kpi-label">Pendentes</span>
            <span class="kpi-val">{{ elegiveis.length }}</span>
          </div>
          <div class="kpi-card orange">
            <span class="kpi-label">Total Rescisório</span>
            <span class="kpi-val">{{ formatMoney(totalRescisorio) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ABAS ──────────────────────────────────────────────────── -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'registrar' }" @click="aba = 'registrar'">
        ✍️ Registrar Exoneração
      </button>
      <button class="tab-btn" :class="{ active: aba === 'elegiveis' }" @click="aba = 'elegiveis'; carregarElegiveis()">
        📬 Elegíveis para Folha Rescisória
        <span v-if="elegiveis.length" class="badge-count">{{ elegiveis.length }}</span>
      </button>
    </div>

    <!-- ── ABA: REGISTRAR ───────────────────────────────────── -->
    <div v-if="aba === 'registrar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📋 Registrar Exoneração / Desligamento</h2>

      <!-- Busca de servidor -->
      <div class="form-group">
        <label>Servidor</label>
        <div class="search-wrap">
          <input v-model="busca" @input="buscarServidor" placeholder="🔍 Nome ou matrícula..." class="form-input" />
          <ul v-if="resultados.length" class="autocomplete-list">
            <li v-for="s in resultados" :key="s.id" @click="selecionarServidor(s)">
              <strong>{{ s.nome }}</strong>
              <span class="sub">{{ s.matricula }} — {{ s.cargo }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Dados do servidor selecionado -->
      <div v-if="servidor" class="servidor-card">
        <div class="sv-info">
          <span class="sv-nome">{{ servidor.nome }}</span>
          <span class="sv-det">Mat. {{ servidor.matricula }} · {{ servidor.cargo }}</span>
          <span class="sv-det">Admissão: {{ formatData(servidor.admissao) }}</span>
          <span class="sv-regime" :class="servidor.regime_prev === 'RGPS' ? 'rgps' : 'rpps'">
            {{ servidor.regime_prev === 'RGPS' ? '🟢 RGPS — INSS' : '🏦 RPPS — IPAM' }}
          </span>
        </div>
      </div>

      <!-- Formulário de exoneração -->
      <div v-if="servidor" class="form-grid">
        <div class="form-group">
          <label>Motivo da Saída</label>
          <select v-model="form.motivo_saida" class="form-input">
            <option value="EXONERACAO">Exoneração</option>
            <option value="DEMISSAO">Demissão</option>
            <option value="APOSENTADORIA">Aposentadoria</option>
            <option value="FALECIMENTO">Falecimento</option>
            <option value="TRANSFERENCIA">Transferência</option>
          </select>
        </div>
        <div class="form-group">
          <label>Data do Ato ★</label>
          <input type="date" v-model="form.data_exoneracao" @change="calcularPreview" class="form-input" />
        </div>
        <div class="form-group">
          <label>Nº da Portaria</label>
          <input v-model="form.portaria_num" placeholder="Ex.: Portaria nº 123/2026" class="form-input" />
        </div>
      </div>

      <!-- Preview do cálculo ─────────────────────────────────── -->
      <div v-if="calculo" class="calculo-card" :class="{ loaded }">
        <h3 class="calc-title">🧮 Cálculo Automático de Verbas Rescisórias</h3>

        <div class="calc-meta">
          <span>Salário base: <strong>{{ formatMoney(calculo.salario_base) }}</strong></span>
          <span>Anos de serviço: <strong>{{ calculo.anos_servico }}</strong></span>
          <span>Meses p.aquisitivo: <strong>{{ calculo.meses_periodo_aquisitivo }}/12</strong></span>
          <span class="regime-tag" :class="calculo.regime">
            {{ calculo.regime === 'RGPS' ? 'RGPS (CLT/PSS)' : 'RPPS (Estatutário)' }}
          </span>
        </div>

        <table class="verbas-table">
          <thead>
            <tr><th>Verba</th><th>Incide IR</th><th>Valor</th></tr>
          </thead>
          <tbody>
            <tr v-if="calculo.saldo_salario > 0">
              <td>Saldo de Salário ({{ calculo.dias_saldo }} dias)</td><td>✅</td>
              <td class="money green">{{ formatMoney(calculo.saldo_salario) }}</td>
            </tr>
            <tr v-if="calculo.ferias_prop > 0">
              <td>Férias Proporcionais ({{ calculo.meses_periodo_aquisitivo }}/12)</td><td>❌</td>
              <td class="money green">{{ formatMoney(calculo.ferias_prop) }}</td>
            </tr>
            <tr v-if="calculo.ferias_prop_tercio > 0">
              <td>&nbsp;&nbsp;+ 1/3 Constitucional s/ Férias Prop.</td><td>❌</td>
              <td class="money green">{{ formatMoney(calculo.ferias_prop_tercio) }}</td>
            </tr>
            <tr v-if="calculo.ferias_vencidas > 0">
              <td>Férias Vencidas (períodos não gozados)</td><td>✅</td>
              <td class="money green">{{ formatMoney(calculo.ferias_vencidas) }}</td>
            </tr>
            <tr v-if="calculo.ferias_vencidas_tercio > 0">
              <td>&nbsp;&nbsp;+ 1/3 Constitucional s/ Vencidas</td><td>✅</td>
              <td class="money green">{{ formatMoney(calculo.ferias_vencidas_tercio) }}</td>
            </tr>
            <tr v-if="calculo.decimo_terceiro_prop > 0">
              <td>13º Salário Proporcional ({{ new Date().getMonth() + 1 }}/12)</td><td>✅</td>
              <td class="money green">{{ formatMoney(calculo.decimo_terceiro_prop) }}</td>
            </tr>
            <tr v-if="calculo.fgts_multa > 0" class="row-fgts">
              <td>FGTS + Multa 40% (apenas RGPS)</td><td>❌</td>
              <td class="money green">{{ formatMoney(calculo.fgts_multa) }}</td>
            </tr>
            <tr class="row-total">
              <td><strong>TOTAL BRUTO</strong></td><td></td>
              <td class="money"><strong>{{ formatMoney(calculo.total_bruto) }}</strong></td>
            </tr>
            <tr class="row-desconto" v-if="calculo.desconto_irrf > 0">
              <td>(−) IRRF estimado</td><td></td>
              <td class="money red">{{ formatMoney(calculo.desconto_irrf) }}</td>
            </tr>
            <tr class="row-liquido">
              <td><strong>TOTAL LÍQUIDO</strong></td><td></td>
              <td class="money liquido"><strong>{{ formatMoney(calculo.total_liquido) }}</strong></td>
            </tr>
          </tbody>
        </table>

        <p class="aviso-rpps" v-if="calculo.regime === 'RPPS'">
          ℹ️ Servidor estatutário RPPS/IPAM — <strong>sem FGTS nem multa de 40%</strong>
        </p>
      </div>

      <div v-if="servidor && form.data_exoneracao" class="form-actions">
        <button class="btn-secondary" @click="calcularPreview" :disabled="loadingCalc">
          {{ loadingCalc ? '⏳ Calculando...' : '🧮 Recalcular' }}
        </button>
        <button class="btn-primary" @click="registrarExoneracao" :disabled="!calculo || salvando">
          {{ salvando ? '⏳ Registrando...' : '✅ Confirmar Exoneração' }}
        </button>
      </div>

      <div v-if="sucesso" class="success-msg">
        ✅ Exoneração registrada! O servidor aparecerá na fila de elegíveis para inclusão em folha rescisória.
        <button @click="aba = 'elegiveis'; carregarElegiveis()" class="link-btn">Ver elegíveis →</button>
      </div>
    </div>

    <!-- ── ABA: ELEGÍVEIS ──────────────────────────────────── -->
    <div v-if="aba === 'elegiveis'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📬 Elegíveis para Folha Rescisória</h2>
        <div class="toolbar-right">
          <select v-model="filtroUnidade" @change="carregarElegiveis" class="form-input small">
            <option value="">Todas as secretarias</option>
            <option v-for="u in unidades" :key="u.id" :value="u.id">{{ u.nome }}</option>
          </select>
          <button class="btn-lote" @click="despacharLote" :disabled="selecionados.length === 0">
            ☑️ Incluir {{ selecionados.length > 0 ? `(${selecionados.length})` : '' }} em Folha Rescisória
          </button>
        </div>
      </div>

      <!-- Controles de seleção ─────────────────────────────── -->
      <div class="sel-controls">
        <label class="cb-label">
          <input type="checkbox" :checked="todosSelecionados" @change="toggleTodos" />
          <strong>Marcar todos</strong>
          <span v-if="filtroUnidade"> da secretaria selecionada</span>
        </label>
        <span class="sel-count" v-if="selecionados.length > 0">
          {{ selecionados.length }} selecionado(s) — Total: {{ formatMoney(totalSelecionados) }}
        </span>
      </div>

      <!-- Tabela de elegíveis ──────────────────────────────── -->
      <div class="table-scroll">
        <table class="eleg-table" v-if="elegiveis.length">
          <thead>
            <tr>
              <th>☐</th>
              <th>Servidor</th>
              <th>Matrícula</th>
              <th>Secretaria</th>
              <th>Data Exon.</th>
              <th>Motivo</th>
              <th>Líquido</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in elegiveis" :key="e.rescisao_id" :class="{ 'row-sel': selecionados.includes(e.rescisao_id) }">
              <td>
                <input type="checkbox"
                  :checked="selecionados.includes(e.rescisao_id)"
                  @change="toggleSelecao(e.rescisao_id)" />
              </td>
              <td>
                <span class="nome-cell">{{ e.nome }}</span>
                <span class="sub">{{ e.cargo }}</span>
              </td>
              <td>{{ e.matricula }}</td>
              <td>
                <span class="sec-badge">{{ e.secretaria || '—' }}</span>
                <span class="sub">{{ e.setor }}</span>
              </td>
              <td>{{ formatData(e.data_exoneracao) }}</td>
              <td><span class="motivo-badge" :class="e.motivo?.toLowerCase()">{{ e.motivo }}</span></td>
              <td class="money liquido">{{ formatMoney(e.total_liquido) }}</td>
              <td>
                <button class="act-btn" title="Incluir individualmente" @click="incluirIndividual(e)">
                  👤 Despachar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loadingEleg" class="empty-state">
          🎉 Nenhum servidor elegível pendente para folha rescisória.
        </div>
        <div v-if="loadingEleg" class="spinner-wrap"><div class="spinner"></div></div>
      </div>

      <div v-if="msgLote" class="success-msg">{{ msgLote }}</div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba          = ref('registrar')
const loaded       = ref(false)

// ── Registrar ─────────────────────────────────────────
const busca        = ref('')
const resultados   = ref([])
const servidor     = ref(null)
const form         = ref({ motivo_saida: 'EXONERACAO', data_exoneracao: '', portaria_num: '' })
const calculo      = ref(null)
const loadingCalc  = ref(false)
const salvando     = ref(false)
const sucesso      = ref(false)

// ── Elegíveis ─────────────────────────────────────────
const elegiveis    = ref([])
const unidades     = ref([])
const selecionados = ref([])
const filtroUnidade = ref('')
const loadingEleg  = ref(false)
const msgLote      = ref('')

// ── KPIs ──────────────────────────────────────────────
const totalRescisorio = computed(() => elegiveis.value.reduce((s, e) => s + (e.total_liquido || 0), 0))
const totalSelecionados = computed(() => {
  return elegiveis.value
    .filter(e => selecionados.value.includes(e.rescisao_id))
    .reduce((s, e) => s + (e.total_liquido || 0), 0)
})
const todosSelecionados = computed(() =>
  elegiveis.value.length > 0 && elegiveis.value.every(e => selecionados.value.includes(e.rescisao_id)))

let buscarTimer = null
function buscarServidor() {
  clearTimeout(buscarTimer)
  if (busca.value.length < 2) { resultados.value = []; return }
  buscarTimer = setTimeout(async () => {
    resultados.value = (await api.get(`/api/v3/servidores/buscar?q=${encodeURIComponent(busca.value)}`)).data.servidores || []
  }, 300)
}

function selecionarServidor(s) {
  servidor.value = s
  busca.value = s.nome
  resultados.value = []
  calculo.value = null
  sucesso.value = false
}

async function calcularPreview() {
  if (!servidor.value || !form.value.data_exoneracao) return
  loadingCalc.value = true
  calculo.value = null
  try {
    const d = (await api.post('/api/v3/exoneracao/preview', {
      funcionario_id: servidor.value.id,
      data_exoneracao: form.value.data_exoneracao
    })).data
    if (d.calculo) calculo.value = d.calculo
  } catch (e) { console.error(e) } finally { loadingCalc.value = false }
}

async function registrarExoneracao() {
  salvando.value = true
  try {
    const d = (await api.post('/api/v3/exoneracao/registrar', { funcionario_id: servidor.value.id, ...form.value })).data
    if (d.ok) {
      sucesso.value = true
      calculo.value = null
      servidor.value = null
      busca.value = ''
      form.value = { motivo_saida: 'EXONERACAO', data_exoneracao: '', portaria_num: '' }
      await carregarElegiveis()
    }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function carregarElegiveis() {
  loadingEleg.value = true
  selecionados.value = []
  try {
    const u = filtroUnidade.value ? `?unidade_id=${filtroUnidade.value}` : ''
    const d = (await api.get(`/api/v3/exoneracao/elegiveis${u}`)).data
    elegiveis.value = d.elegiveis || []
    unidades.value = d.unidades || []
  } catch (e) { console.error(e) } finally { loadingEleg.value = false }
}

function toggleSelecao(id) {
  const i = selecionados.value.indexOf(id)
  if (i >= 0) selecionados.value.splice(i, 1)
  else selecionados.value.push(id)
}

function toggleTodos() {
  if (todosSelecionados.value) selecionados.value = []
  else selecionados.value = elegiveis.value.map(e => e.rescisao_id)
}

async function despacharLote() {
  if (!selecionados.value.length) return
  const competencia = new Date().toISOString().slice(0, 7)
  try {
    const d = (await api.post('/api/v3/exoneracao/incluir-folha', { rescisao_ids: selecionados.value, competencia })).data
    if (d.ok) {
      msgLote.value = `✅ ${d.incluidos} servidor(es) incluído(s) na Folha Rescisória ${d.competencia}. ID da Folha: #${d.folha_id}`
      await carregarElegiveis()
    }
  } catch (e) { console.error(e) }
}

async function incluirIndividual(e) {
  const competencia = new Date().toISOString().slice(0, 7)
  await api.post('/api/v3/exoneracao/incluir-folha', { rescisao_ids: [e.rescisao_id], competencia })
  msgLote.value = `✅ ${e.nome} incluído(a) em folha rescisória.`
  await carregarElegiveis()
}

function formatMoney(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
}
function formatData(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR')
}

onMounted(async () => {
  await carregarElegiveis()
  setTimeout(() => loaded.value = true, 80)
})
</script>

<style scoped>
.exon-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

/* HERO */
.hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); border-radius: 20px; padding: 2rem; color: #fff; position: relative; overflow: hidden; opacity: 0; transform: translateY(-16px); transition: opacity .5s, transform .5s; }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; opacity: .08; background: #e74c3c; }
.hs1 { width: 200px; height: 200px; top: -60px; right: -40px; }
.hs2 { width: 120px; height: 120px; bottom: -30px; left: 60px; background: #f39c12; }
.hs3 { width: 80px; height: 80px; top: 30px; left: 40%; background: #fff; }
.hero-eyebrow { font-size: .75rem; font-weight: 700; letter-spacing: .1em; color: #e74c3c; text-transform: uppercase; }
.hero-title { font-size: 2rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .9rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; position: relative; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi-card { background: rgba(255,255,255,.1); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 100px; }
.kpi-card.red { border-top: 3px solid #e74c3c; }
.kpi-card.orange { border-top: 3px solid #f39c12; }
.kpi-label { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; letter-spacing: .05em; }
.kpi-val { display: block; font-size: 1.4rem; font-weight: 800; }

/* TABS */
.tabs-bar { display: flex; gap: .5rem; opacity: 0; transform: translateY(-8px); transition: opacity .4s .15s, transform .4s .15s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: .6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .85rem; transition: all .2s; position: relative; }
.tab-btn.active { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
.badge-count { position: absolute; top: -6px; right: -6px; background: #e74c3c; color: #fff; border-radius: 999px; padding: 0 5px; font-size: .65rem; font-weight: 800; }

/* CARDS */
.section-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; opacity: 0; transform: translateY(12px); transition: opacity .4s .2s, transform .4s .2s; }
.section-card.loaded { opacity: 1; transform: none; }
.section-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; }
.section-hdr { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.toolbar-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* FORMS */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: .35rem; }
.form-group label { font-size: .78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .6rem .9rem; font-size: .9rem; color: #1e293b; width: 100%; transition: border-color .2s; }
.form-input:focus { outline: none; border-color: #e74c3c; }
.form-input.small { width: 220px; }
.search-wrap { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.1); z-index: 20; list-style: none; margin: 0; padding: .25rem 0; }
.autocomplete-list li { padding: .6rem 1rem; cursor: pointer; display: flex; flex-direction: column; gap: .1rem; }
.autocomplete-list li:hover { background: #f8fafc; }
.autocomplete-list li .sub { font-size: .75rem; color: #94a3b8; }

/* Servidor card */
.servidor-card { background: #f8fafc; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; border-left: 4px solid #e74c3c; }
.sv-info { display: flex; flex-direction: column; gap: .2rem; }
.sv-nome { font-size: 1rem; font-weight: 700; color: #1e293b; }
.sv-det { font-size: .8rem; color: #64748b; }
.sv-regime { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; margin-top: .25rem; }
.sv-regime.rpps { background: #dbeafe; color: #1e40af; }
.sv-regime.rgps { background: #dcfce7; color: #15803d; }

/* Cálculo */
.calculo-card { background: #f0fdf4; border-radius: 12px; padding: 1.25rem; margin: 1rem 0; border: 1.5px solid #bbf7d0; }
.calc-title { font-size: 1rem; font-weight: 700; color: #166534; margin-bottom: .75rem; }
.calc-meta { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; font-size: .82rem; color: #374151; }
.regime-tag { padding: .2rem .6rem; border-radius: 999px; font-weight: 700; }
.regime-tag.RPPS { background: #dbeafe; color: #1e40af; }
.regime-tag.RGPS { background: #dcfce7; color: #15803d; }
.verbas-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.verbas-table th { text-align: left; padding: .5rem .75rem; background: rgba(0,0,0,.04); font-size: .72rem; text-transform: uppercase; color: #64748b; letter-spacing: .05em; }
.verbas-table td { padding: .45rem .75rem; border-bottom: 1px solid rgba(0,0,0,.05); }
.verbas-table .row-fgts { background: #effff5; }
.verbas-table .row-total td { font-weight: 700; background: rgba(0,0,0,.03); border-top: 2px solid #e2e8f0; }
.verbas-table .row-desconto td { color: #dc2626; }
.verbas-table .row-liquido td { border-top: 2px solid #10b981; background: #f0fdf4; }
.money { text-align: right; font-variant-numeric: tabular-nums; }
.money.green { color: #16a34a; font-weight: 600; }
.money.red { color: #dc2626; font-weight: 600; }
.money.liquido { color: #0f766e; font-size: 1rem; font-weight: 700; }
.aviso-rpps { margin-top: .75rem; font-size: .8rem; color: #1e40af; background: #dbeafe; padding: .5rem .75rem; border-radius: 6px; }

/* Ações */
.form-actions { display: flex; gap: .75rem; margin-top: 1rem; flex-wrap: wrap; }
.btn-primary { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: opacity .2s; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: .65rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
.link-btn { background: none; border: none; color: #e74c3c; font-weight: 700; cursor: pointer; text-decoration: underline; }
.btn-lote { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: .6rem 1.2rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: opacity .2s; }
.btn-lote:disabled { opacity: .4; cursor: not-allowed; }
.success-msg { margin-top: 1rem; background: #dcfce7; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; font-size: .88rem; }

/* Elegíveis */
.sel-controls { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; padding: .75rem; background: #f8fafc; border-radius: 8px; }
.cb-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; font-size: .88rem; }
.sel-count { font-size: .82rem; color: #10b981; font-weight: 600; }
.table-scroll { overflow-x: auto; }
.eleg-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.eleg-table th { text-align: left; padding: .6rem .75rem; background: #f1f5f9; font-size: .72rem; text-transform: uppercase; color: #64748b; }
.eleg-table td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.eleg-table .row-sel { background: #f0fdf4; }
.nome-cell { display: block; font-weight: 600; color: #1e293b; }
.sub { display: block; font-size: .72rem; color: #94a3b8; }
.sec-badge { display: inline-block; background: #eff6ff; color: #1d4ed8; border-radius: 4px; padding: .15rem .5rem; font-size: .72rem; font-weight: 600; }
.motivo-badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #fee2e2; color: #991b1b; }
.motivo-badge.aposentadoria { background: #fef3c7; color: #92400e; }
.motivo-badge.demissao { background: #fce7f3; color: #9d174d; }
.act-btn { background: #f1f5f9; border: none; border-radius: 6px; padding: .35rem .7rem; font-size: .78rem; cursor: pointer; font-weight: 600; transition: background .2s; }
.act-btn:hover { background: #e0e7ef; }
.empty-state { text-align: center; padding: 3rem; color: #94a3b8; font-size: .95rem; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #e74c3c; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
