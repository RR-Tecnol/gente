<template>
  <div class="erp-page">
    <div class="hero teal">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">📒 ERP Municipal</span>
          <h1 class="hero-title">Contabilidade Pública</h1>
          <p class="hero-sub">PCASP · Lançamentos · Balancete Mensal</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi"><span class="kl">Total Débitos</span><span class="kv">{{ fmt(balancete.total_debitos) }}</span></div>
          <div class="kpi"><span class="kl">Total Créditos</span><span class="kv">{{ fmt(balancete.total_creditos) }}</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'balancete' }" @click="aba='balancete'">📊 Balancete</button>
      <button class="tab" :class="{ active: aba === 'pcasp' }" @click="aba='pcasp'; carregarPcasp()">📋 Plano de Contas</button>
      <button class="tab" :class="{ active: aba === 'lancar' }" @click="aba='lancar'">➕ Lançamento</button>
    </div>

    <!-- Balancete -->
    <div v-if="aba === 'balancete'" class="card">
      <div class="card-hdr">
        <h2>Balancete Mensal</h2>
        <div class="toolbar">
          <select v-model="mesSel" @change="carregarBalancete" class="inp small">
            <option v-for="m in 12" :key="m" :value="m">{{ m.toString().padStart(2,'0') }}</option>
          </select>
          <select v-model="anoSel" @change="carregarBalancete" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
          <button class="btn-export" @click="exportar">⬇️ Excel</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="balancete.balancete?.length">
          <thead>
            <tr><th>Código</th><th>Conta</th><th>Natureza</th><th>Grupo</th><th class="money-h">Débito</th><th class="money-h">Crédito</th><th class="money-h">Saldo</th></tr>
          </thead>
          <tbody>
            <tr v-for="c in balancete.balancete" :key="c.conta_codigo">
              <td><code>{{ c.conta_codigo }}</code></td>
              <td>{{ c.conta_nome }}</td>
              <td><span class="badge" :class="c.conta_natureza.toLowerCase()">{{ c.conta_natureza }}</span></td>
              <td>{{ c.conta_grupo.replace('_', ' ') }}</td>
              <td class="money">{{ fmt(c.total_debito) }}</td>
              <td class="money">{{ fmt(c.total_credito) }}</td>
              <td class="money" :class="c.saldo >= 0 ? 'pos' : 'neg'">{{ fmt(Math.abs(c.saldo)) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="4"><strong>TOTAL</strong></td>
              <td class="money"><strong>{{ fmt(balancete.total_debitos) }}</strong></td>
              <td class="money"><strong>{{ fmt(balancete.total_creditos) }}</strong></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <div v-else class="empty">📭 Sem lançamentos no período.</div>
      </div>
    </div>

    <!-- PCASP -->
    <div v-if="aba === 'pcasp'" class="card">
      <h2>Plano de Contas — PCASP</h2>
      <div class="table-wrap">
        <table class="tbl" v-if="contas.length">
          <thead><tr><th>Código</th><th>Nome</th><th>Natureza</th><th>Tipo</th><th>Grupo</th></tr></thead>
          <tbody>
            <tr v-for="c in contas" :key="c.CONTA_ID">
              <td><code>{{ c.CONTA_CODIGO }}</code></td>
              <td>{{ c.CONTA_NOME }}</td>
              <td><span class="badge" :class="c.CONTA_NATUREZA.toLowerCase()">{{ c.CONTA_NATUREZA }}</span></td>
              <td>{{ c.CONTA_TIPO }}</td>
              <td>{{ c.CONTA_GRUPO }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhuma conta cadastrada.</div>
      </div>
    </div>

    <!-- Lançamento -->
    <div v-if="aba === 'lancar'" class="card">
      <h2>➕ Lançamento Contábil</h2>
      <div class="form-grid">
        <div class="fg"><label>Data</label><input type="date" v-model="form.lancamento_data" class="inp" /></div>
        <div class="fg"><label>Valor (R$)</label><input type="number" step="0.01" v-model="form.lancamento_valor" class="inp" /></div>
        <div class="fg"><label>Conta Débito (CONTA_ID)</label><input type="number" v-model="form.conta_debito_id" class="inp" /></div>
        <div class="fg"><label>Conta Crédito (CONTA_ID)</label><input type="number" v-model="form.conta_credito_id" class="inp" /></div>
        <div class="fg" style="grid-column:span 2"><label>Histórico</label><input v-model="form.lancamento_historico" class="inp" /></div>
      </div>
      <div class="form-actions"><button class="btn-primary" @click="lancar" :disabled="salvando">{{ salvando ? '⏳...' : '💾 Registrar Lançamento' }}</button></div>
      <div v-if="msg" class="msg-ok">{{ msg }}</div>
    </div>

    <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba = ref('balancete')
const loading = ref(false)
const salvando = ref(false)
const msg = ref('')
const balancete = ref({})
const contas = ref([])
const mesSel = ref(new Date().getMonth() + 1)
const anoSel = ref(new Date().getFullYear())
const anos = Array.from({ length: 4 }, (_, i) => anoSel.value - i)
const form = ref({ lancamento_data: new Date().toISOString().slice(0,10), lancamento_valor: '', conta_debito_id: '', conta_credito_id: '', lancamento_historico: '' })

async function carregarBalancete() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/balancete', { params: { mes: mesSel.value, ano: anoSel.value } })
    balancete.value = data
  } catch (e) { console.error(e) } finally { loading.value = false }
}
async function carregarPcasp() {
  const { data } = await api.get('/api/v3/pcasp')
  contas.value = data.contas || []
}
async function lancar() {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/lancamentos', form.value)
    if (data.ok) { msg.value = '✅ Lançamento registrado!'; aba.value = 'balancete'; await carregarBalancete() }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}
function exportar() {
  const rows = [['Código','Conta','Natureza','Débito','Crédito','Saldo'], ...(balancete.value.balancete || []).map(c => [c.conta_codigo, c.conta_nome, c.conta_natureza, c.total_debito, c.total_credito, c.saldo])]
  const csv = rows.map(r => r.join(';')).join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF'+csv], {type:'text/csv'})); a.download = `balancete_${anoSel.value}_${mesSel.value}.csv`; a.click()
}
const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(carregarBalancete)
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero.teal { background: linear-gradient(135deg, #134e4a, #0f766e); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #99f6e4; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 140px; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.05rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #0f766e; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.toolbar { display: flex; gap: .75rem; align-items: center; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .88rem; width: 100%; }
.inp.small { width: 80px; }
.btn-export { background: #f1f5f9; border: none; padding: .5rem 1rem; border-radius: 8px; cursor: pointer; font-size: .82rem; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.fg { display: flex; flex-direction: column; gap: .3rem; }
.fg label { font-size: .75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
.form-actions { margin-top: .5rem; }
.btn-primary { background: linear-gradient(135deg, #0f766e, #0d9488); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; }
.btn-primary:disabled { opacity: .4; }
.msg-ok { margin-top: 1rem; background: #f0fdf4; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.money-h { text-align: right; }
.money { text-align: right; font-weight: 600; }
.pos { color: #059669; }
.neg { color: #dc2626; }
.total-row td { background: #f0fdf4; }
.badge.devedora { background: #dbeafe; color: #1e40af; border-radius: 4px; padding: .15rem .5rem; font-size: .7rem; font-weight: 700; }
.badge.credora  { background: #fce7f3; color: #9d174d; border-radius: 4px; padding: .15rem .5rem; font-size: .7rem; font-weight: 700; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #0f766e; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
