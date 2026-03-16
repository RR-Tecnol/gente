<template>
  <div class="erp-page">
    <div class="hero">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">💰 ERP Municipal</span>
          <h1 class="hero-title">Orçamento Público</h1>
          <p class="hero-sub">PPA · LOA · Execução Orçamentária</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi blue"><span class="kl">Dotação Inicial</span><span class="kv">{{ fmt(execucao.dotacao_inicial) }}</span></div>
          <div class="kpi green"><span class="kl">Empenhado</span><span class="kv">{{ fmt(execucao.empenhado) }}</span></div>
          <div class="kpi" :class="percentual > 85 ? 'red' : 'yellow'"><span class="kl">Execução</span><span class="kv">{{ percentual }}%</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'loa' }"  @click="aba='loa'">📋 LOA {{ anoSel }}</button>
      <button class="tab" :class="{ active: aba === 'ppa' }"  @click="aba='ppa'">📅 PPA</button>
      <button class="tab" :class="{ active: aba === 'exec' }" @click="aba='exec'; carregarExecucao()">📊 Execução</button>
    </div>

    <!-- LOA -->
    <div v-if="aba === 'loa'" class="card">
      <div class="card-hdr">
        <h2>Lei Orçamentária Anual</h2>
        <div class="toolbar">
          <select v-model="anoSel" @change="carregarLoa" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="loa.itens?.length">
          <thead><tr><th>Programa</th><th>Ação</th><th>Tipo</th><th>Natureza Despesa</th><th>Dotação Aprovada</th><th>Dotação Atual</th></tr></thead>
          <tbody>
            <tr v-for="i in loa.itens" :key="i.LOA_ID">
              <td>{{ i.PROGRAMA_CODIGO }} — {{ i.PROGRAMA_NOME }}</td>
              <td>{{ i.ACAO_CODIGO }} — {{ i.ACAO_NOME }}</td>
              <td><span class="badge">{{ i.ACAO_TIPO }}</span></td>
              <td>{{ i.LOA_NATUREZA_DESPESA || '—' }}</td>
              <td class="money">{{ fmt(i.LOA_VALOR_APROVADO) }}</td>
              <td class="money green">{{ fmt(i.LOA_DOTACAO_ATUAL) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="4"><strong>TOTAL</strong></td>
              <td class="money"><strong>{{ fmt(loa.totais?.dotacao_inicial) }}</strong></td>
              <td class="money green"><strong>{{ fmt(loa.totais?.dotacao_atual) }}</strong></td>
            </tr>
          </tfoot>
        </table>
        <div v-else class="empty">📭 Nenhum item encontrado para {{ anoSel }}.</div>
      </div>
    </div>

    <!-- PPA -->
    <div v-if="aba === 'ppa'" class="card">
      <h2>Plano Plurianual — Programas</h2>
      <div class="table-wrap">
        <table class="tbl" v-if="programas.length">
          <thead><tr><th>PPA</th><th>Vigência</th><th>Código</th><th>Programa</th><th>Valor Total</th></tr></thead>
          <tbody>
            <tr v-for="p in programas" :key="p.PROGRAMA_ID">
              <td>{{ p.PPA_DESCRICAO }}</td>
              <td>{{ p.PPA_ANO_INICIO }}–{{ p.PPA_ANO_FIM }}</td>
              <td><code>{{ p.PROGRAMA_CODIGO }}</code></td>
              <td>{{ p.PROGRAMA_NOME }}</td>
              <td class="money">{{ fmt(p.PROGRAMA_VALOR_TOTAL) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhum programa cadastrado.</div>
      </div>
    </div>

    <!-- Execução -->
    <div v-if="aba === 'exec'" class="card">
      <h2>Execução por Programa — {{ anoSel }}</h2>
      <div class="table-wrap">
        <table class="tbl" v-if="exec.resumo?.length">
          <thead><tr><th>Programa</th><th>Dotação</th><th>Empenhado</th><th>Empenhos</th><th>% Exec.</th></tr></thead>
          <tbody>
            <tr v-for="r in exec.resumo" :key="r.PROGRAMA_CODIGO">
              <td>{{ r.PROGRAMA_CODIGO }} — {{ r.PROGRAMA_NOME }}</td>
              <td class="money">{{ fmt(r.dotacao_atual) }}</td>
              <td class="money green">{{ fmt(r.empenhado) }}</td>
              <td class="num">{{ r.qtd_empenhos }}</td>
              <td>
                <div class="bar-wrap"><div class="bar" :style="{ width: pct(r.empenhado, r.dotacao_atual) + '%' }"></div></div>
                <span class="pct-lbl">{{ pct(r.empenhado, r.dotacao_atual) }}%</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Sem dados de execução.</div>
      </div>
    </div>

    <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba     = ref('loa')
const loading = ref(false)
const anoSel  = ref(new Date().getFullYear())
const anos    = Array.from({ length: 5 }, (_, i) => anoSel.value - i)
const loa     = ref({})
const programas = ref([])
const exec    = ref({})
const execucao = ref({ dotacao_inicial: 0, empenhado: 0 })

const percentual = computed(() =>
  execucao.value.dotacao_inicial > 0
    ? Math.round(execucao.value.empenhado / execucao.value.dotacao_inicial * 100)
    : 0
)

async function carregarLoa() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/orcamento/loa', { params: { ano: anoSel.value } })
    loa.value = data
    execucao.value.dotacao_inicial = data.totais?.dotacao_inicial ?? 0
  } catch (e) { console.error(e) } finally { loading.value = false }
}
async function carregarPpa() {
  const { data } = await api.get('/api/v3/orcamento/ppa')
  programas.value = data.programas || []
}
async function carregarExecucao() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/orcamento/execucao', { params: { ano: anoSel.value } })
    exec.value = data
    execucao.value.empenhado = (data.resumo || []).reduce((s, r) => s + (r.empenhado || 0), 0)
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
const pct = (emp, dot) => dot > 0 ? Math.min(100, Math.round((emp || 0) / dot * 100)) : 0

onMounted(async () => { await Promise.all([carregarLoa(), carregarPpa()]) })
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero { background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 100%); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #93c5fd; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 120px; }
.kpi.blue { border-top: 3px solid #93c5fd; }
.kpi.green { border-top: 3px solid #6ee7b7; }
.kpi.yellow { border-top: 3px solid #fde68a; }
.kpi.red { border-top: 3px solid #fca5a5; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.1rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #1d4ed8; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.toolbar { display: flex; gap: .75rem; align-items: center; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .88rem; }
.inp.small { width: 100px; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .83rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.total-row td { background: #f0f9ff; padding: .75rem; }
.money { text-align: right; font-weight: 600; }
.money.green { color: #059669; }
.num { text-align: center; }
.badge { background: #dbeafe; color: #1e40af; border-radius: 4px; padding: .15rem .5rem; font-size: .7rem; font-weight: 700; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.bar-wrap { background: #e2e8f0; border-radius: 999px; height: 6px; width: 100px; overflow: hidden; display: inline-block; vertical-align: middle; }
.bar { background: linear-gradient(90deg, #1d4ed8, #3b82f6); height: 100%; transition: width .5s; }
.pct-lbl { font-size: .72rem; color: #64748b; margin-left: .5rem; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #1d4ed8; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
