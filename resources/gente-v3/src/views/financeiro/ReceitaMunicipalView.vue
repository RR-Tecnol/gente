<template>
  <div class="erp-page">
    <div class="hero orange">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">💵 ERP Municipal</span>
          <h1 class="hero-title">Receita Municipal</h1>
          <p class="hero-sub">Arrecadação · Previsão · Dívida Ativa</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi"><span class="kl">Previsto {{ anoSel }}</span><span class="kv">{{ fmt(resumo.previsto) }}</span></div>
          <div class="kpi green"><span class="kl">Arrecadado</span><span class="kv">{{ fmt(resumo.arrecadado) }}</span></div>
          <div class="kpi" :class="resumo.percentual >= 100 ? 'green' : resumo.percentual >= 80 ? 'yellow' : 'red'">
            <span class="kl">Execução</span><span class="kv">{{ resumo.percentual }}%</span>
          </div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'lista' }" @click="aba='lista'">📋 Lançamentos</button>
      <button class="tab" :class="{ active: aba === 'tipo' }"  @click="aba='tipo'; carregarPorTipo()">📊 Por Natureza</button>
      <button class="tab" :class="{ active: aba === 'divida' }" @click="aba='divida'; carregarDivida()">⚖️ Dívida Ativa</button>
      <button class="tab" :class="{ active: aba === 'lancar' }" @click="aba='lancar'">➕ Lançar</button>
    </div>

    <!-- Lista -->
    <div v-if="aba === 'lista'" class="card">
      <div class="card-hdr">
        <h2>Receitas {{ anoSel }}</h2>
        <div class="toolbar">
          <select v-model="anoSel" @change="carregar" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="receitas.length">
          <thead><tr><th>Data</th><th>Código</th><th>Descrição</th><th>Tipo</th><th>Previsto</th><th>Arrecadado</th></tr></thead>
          <tbody>
            <tr v-for="r in receitas" :key="r.RECEITA_ID">
              <td>{{ r.RECEITA_DATA }}</td>
              <td><code>{{ r.RECEITA_CODIGO_NATUREZA }}</code></td>
              <td>{{ r.RECEITA_DESCRICAO }}</td>
              <td><span class="tipo-badge">{{ r.RECEITA_TIPO.replace('_', ' ') }}</span></td>
              <td class="money">{{ fmt(r.RECEITA_VALOR_PREVISTO) }}</td>
              <td class="money pos">{{ fmt(r.RECEITA_VALOR_ARRECADADO) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhum lançamento de receita.</div>
      </div>
    </div>

    <!-- Por tipo -->
    <div v-if="aba === 'tipo'" class="card">
      <h2>Receitas por Natureza — {{ anoSel }}</h2>
      <div class="tipo-cards">
        <div class="tc" v-for="t in porTipo" :key="t.RECEITA_TIPO">
          <span class="tc-nome">{{ t.RECEITA_TIPO.replace('_', ' ') }}</span>
          <span class="tc-qtd">{{ t.qtd }} lançamentos</span>
          <span class="tc-pct">{{ t.arrecadado > 0 && t.previsto > 0 ? Math.round(t.arrecadado/t.previsto*100): 0 }}% exec.</span>
          <span class="tc-val">{{ fmt(t.arrecadado) }}</span>
        </div>
      </div>
    </div>

    <!-- Dívida Ativa -->
    <div v-if="aba === 'divida'" class="card">
      <div class="card-hdr">
        <h2>Dívida Ativa Municipal</h2>
        <div class="kpi-row">
          <span class="da-stat">{{ totaisDivida.ativo }} inscritos</span>
          <span class="da-val">{{ fmt(totaisDivida.valor_total) }}</span>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="dividas.length">
          <thead><tr><th>Inscrição</th><th>Devedor</th><th>CPF/CNPJ</th><th>Data</th><th>Principal</th><th>Multa</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="d in dividas" :key="d.DA_ID">
              <td><code>{{ d.DA_INSCRICAO }}</code></td>
              <td>{{ d.DA_DEVEDOR }}</td>
              <td>{{ d.DA_CPFCNPJ || '—' }}</td>
              <td>{{ d.DA_DATA_INSCRICAO }}</td>
              <td class="money">{{ fmt(d.DA_VALOR_PRINCIPAL) }}</td>
              <td class="money">{{ fmt(d.DA_MULTA) }}</td>
              <td class="money pos">{{ fmt(+d.DA_VALOR_PRINCIPAL + +d.DA_MULTA + +d.DA_JUROS + +d.DA_HONORARIO) }}</td>
              <td><span class="status-badge" :class="d.DA_STATUS.toLowerCase()">{{ d.DA_STATUS }}</span></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhuma inscrição em dívida ativa.</div>
      </div>
    </div>

    <!-- Lançar -->
    <div v-if="aba === 'lancar'" class="card">
      <h2>➕ Lançar Receita</h2>
      <div class="form-grid">
        <div class="fg"><label>Data</label><input type="date" v-model="form.receita_data" class="inp" /></div>
        <div class="fg"><label>Código Natureza</label><input v-model="form.receita_codigo_natureza" class="inp" placeholder="1.1.1.00.00" /></div>
        <div class="fg" style="grid-column:span 2"><label>Descrição</label><input v-model="form.receita_descricao" class="inp" /></div>
        <div class="fg"><label>Tipo</label>
          <select v-model="form.receita_tipo" class="inp">
            <option>TRIBUTARIA</option><option>CONTRIBUICOES</option><option>PATRIMONIAL</option>
            <option>TRANSFERENCIAS_CORRENTES</option><option>OUTRAS_CORRENTES</option><option>CAPITAL</option>
          </select>
        </div>
        <div class="fg"><label>Previsto (R$)</label><input type="number" step="0.01" v-model="form.receita_valor_previsto" class="inp" /></div>
        <div class="fg"><label>Arrecadado (R$)</label><input type="number" step="0.01" v-model="form.receita_valor_arrecadado" class="inp" /></div>
      </div>
      <div class="form-actions"><button class="btn-primary" @click="lancar" :disabled="salvando">{{ salvando ? '⏳...' : '💾 Registrar' }}</button></div>
      <div v-if="msg" class="msg-ok">{{ msg }}</div>
    </div>

    <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba = ref('lista')
const loading = ref(false)
const salvando = ref(false)
const msg = ref('')
const receitas = ref([])
const resumo = ref({ previsto: 0, arrecadado: 0, percentual: 0 })
const porTipo = ref([])
const dividas = ref([])
const totaisDivida = ref({ ativo: 0, valor_total: 0 })
const anoSel = ref(new Date().getFullYear())
const anos = Array.from({ length: 4 }, (_, i) => anoSel.value - i)
const form = ref({ receita_data: new Date().toISOString().slice(0,10), receita_codigo_natureza: '', receita_descricao: '', receita_tipo: 'TRIBUTARIA', receita_valor_previsto: 0, receita_valor_arrecadado: 0 })

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/receita', { params: { ano: anoSel.value } })
    receitas.value = data.receitas || []
    resumo.value = data.resumo || {}
  } catch (e) { console.error(e) } finally { loading.value = false }
}
async function carregarPorTipo() {
  const { data } = await api.get('/api/v3/receita/por-tipo', { params: { ano: anoSel.value } })
  porTipo.value = data.por_tipo || []
}
async function carregarDivida() {
  const { data } = await api.get('/api/v3/receita/divida-ativa')
  dividas.value = data.dividas || []
  totaisDivida.value = data.totais || {}
}
async function lancar() {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/receita', form.value)
    if (data.ok) { msg.value = '✅ Receita registrada!'; aba.value = 'lista'; await carregar() }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}
const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(carregar)
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero.orange { background: linear-gradient(135deg, #7c2d12, #c2410c); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #fed7aa; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 120px; }
.kpi.green { border-top: 3px solid #6ee7b7; }
.kpi.yellow { border-top: 3px solid #fde68a; }
.kpi.red { border-top: 3px solid #fca5a5; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.1rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; flex-wrap: wrap; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #c2410c; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: .75rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.toolbar { display: flex; gap: .5rem; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .88rem; width: 100%; }
.inp.small { width: 100px; }
.tipo-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
.tc { background: #fff7ed; border-radius: 12px; padding: 1rem; border-left: 3px solid #c2410c; }
.tc-nome { display: block; font-weight: 700; color: #7c2d12; font-size: .82rem; text-transform: uppercase; }
.tc-qtd, .tc-pct { display: block; font-size: .72rem; color: #64748b; }
.tc-val { display: block; font-size: 1.1rem; font-weight: 800; color: #c2410c; margin-top: .5rem; }
.kpi-row { display: flex; gap: 1rem; align-items: center; }
.da-stat { font-size: .85rem; color: #64748b; }
.da-val { font-size: 1.05rem; font-weight: 700; color: #dc2626; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.fg { display: flex; flex-direction: column; gap: .3rem; }
.fg label { font-size: .75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
.form-actions { margin-top: .5rem; }
.btn-primary { background: linear-gradient(135deg, #c2410c, #ea580c); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; }
.btn-primary:disabled { opacity: .4; }
.msg-ok { margin-top: 1rem; background: #f0fdf4; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.money { text-align: right; font-weight: 600; }
.pos { color: #059669; }
.tipo-badge { background: #ffedd5; color: #7c2d12; border-radius: 4px; padding: .15rem .5rem; font-size: .7rem; font-weight: 700; }
.status-badge { border-radius: 999px; padding: .15rem .55rem; font-size: .7rem; font-weight: 700; }
.status-badge.ativa     { background: #fee2e2; color: #991b1b; }
.status-badge.quitada   { background: #dcfce7; color: #15803d; }
.status-badge.parcelada { background: #fef3c7; color: #92400e; }
.status-badge.ajuizada  { background: #f3e8ff; color: #6b21a8; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #c2410c; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
