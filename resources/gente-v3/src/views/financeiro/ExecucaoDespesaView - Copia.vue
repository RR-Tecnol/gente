<template>
  <div class="erp-page">
    <div class="hero purple">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">📑 ERP Municipal</span>
          <h1 class="hero-title">Execução da Despesa</h1>
          <p class="hero-sub">Empenho · Liquidação · Pagamento</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi"><span class="kl">Emitidos</span><span class="kv">{{ fmt(stats.total_emitido) }}</span></div>
          <div class="kpi"><span class="kl">Liquidados</span><span class="kv">{{ fmt(stats.total_liquidado) }}</span></div>
          <div class="kpi green"><span class="kl">Pagos</span><span class="kv">{{ fmt(stats.total_pago) }}</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'lista' }" @click="aba='lista'; carregar()">📋 Empenhos</button>
      <button class="tab" :class="{ active: aba === 'emitir' }" @click="aba='emitir'">➕ Emitir</button>
    </div>

    <!-- Lista -->
    <div v-if="aba === 'lista'" class="card">
      <div class="card-hdr">
        <h2>Empenhos {{ anoSel }}</h2>
        <div class="toolbar">
          <select v-model="anoSel" @change="carregar" class="inp small">
            <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
          </select>
          <select v-model="filtroStatus" @change="carregar" class="inp small">
            <option value="">Todos os status</option>
            <option>EMITIDO</option><option>LIQUIDADO</option><option>PAGO</option><option>ANULADO</option>
          </select>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="empenhos.length">
          <thead>
            <tr><th>Número</th><th>Data</th><th>Credor</th><th>Ação</th><th>Natureza</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in empenhos" :key="e.EMPENHO_ID">
              <td><code>{{ e.EMPENHO_NUMERO }}</code></td>
              <td>{{ e.EMPENHO_DATA }}</td>
              <td>{{ e.EMPENHO_CREDOR }}<br><span class="sub">{{ e.EMPENHO_CPFCNPJ }}</span></td>
              <td>{{ e.ACAO_NOME }}</td>
              <td>{{ e.LOA_NATUREZA_DESPESA || '—' }}</td>
              <td class="money">{{ fmt(e.EMPENHO_VALOR) }}</td>
              <td><span class="status-badge" :class="e.EMPENHO_STATUS?.toLowerCase()">{{ e.EMPENHO_STATUS }}</span></td>
              <td class="actions">
                <button v-if="e.EMPENHO_STATUS === 'EMITIDO'" class="act-btn blue" @click="liquidar(e)">📥 Liquidar</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Sem empenhos.</div>
      </div>
    </div>

    <!-- Emitir -->
    <div v-if="aba === 'emitir'" class="card">
      <h2>➕ Emitir Empenho</h2>
      <div class="form-grid">
        <div class="fg"><label>Nº Empenho</label><input v-model="form.empenho_numero" class="inp" /></div>
        <div class="fg"><label>Data</label><input type="date" v-model="form.empenho_data" class="inp" /></div>
        <div class="fg"><label>Credor</label><input v-model="form.empenho_credor" class="inp" /></div>
        <div class="fg"><label>CPF / CNPJ</label><input v-model="form.empenho_cpfcnpj" class="inp" /></div>
        <div class="fg"><label>Valor (R$)</label><input type="number" step="0.01" v-model="form.empenho_valor" class="inp" /></div>
        <div class="fg"><label>Tipo</label>
          <select v-model="form.empenho_tipo" class="inp"><option>ORDINARIO</option><option>ESTIMATIVO</option><option>GLOBAL</option></select>
        </div>
        <div class="fg" style="grid-column:span 2"><label>Histórico</label><input v-model="form.empenho_historico" class="inp" /></div>
      </div>
      <div class="form-actions">
        <button class="btn-primary" @click="emitir" :disabled="salvando">{{ salvando ? '⏳...' : '💾 Emitir Empenho' }}</button>
      </div>
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
const empenhos = ref([])
const stats = ref({ total_emitido: 0, total_liquidado: 0, total_pago: 0 })
const anoSel = ref(new Date().getFullYear())
const filtroStatus = ref('')
const anos = Array.from({ length: 4 }, (_, i) => anoSel.value - i)
const form = ref({ empenho_numero: '', empenho_data: new Date().toISOString().slice(0,10), empenho_credor: '', empenho_cpfcnpj: '', empenho_valor: '', empenho_tipo: 'ORDINARIO', empenho_historico: '' })

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/empenho', { params: { ano: anoSel.value, status: filtroStatus.value } })
    empenhos.value = data.empenhos || []
    stats.value = data.stats || {}
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function emitir() {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/empenho', { loa_id: 1, ...form.value })
    if (data.ok) { msg.value = '✅ Empenho emitido!'; aba.value = 'lista'; await carregar() }
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function liquidar(e) {
  if (!confirm(`Liquidar empenho ${e.EMPENHO_NUMERO}?`)) return
  try {
    await api.post(`/api/v3/empenho/${e.EMPENHO_ID}/liquidar`, { liquidacao_valor: e.EMPENHO_VALOR })
    await carregar()
  } catch (ex) { console.error(ex) }
}

const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(carregar)
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero.purple { background: linear-gradient(135deg, #4c1d95, #7c3aed); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #e9d5ff; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 110px; }
.kpi.green { border-top: 3px solid #6ee7b7; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.1rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #7c3aed; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.toolbar { display: flex; gap: .75rem; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .88rem; width: 100%; }
.inp.small { width: 110px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.fg { display: flex; flex-direction: column; gap: .3rem; }
.fg label { font-size: .75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
.form-actions { margin-top: .5rem; }
.btn-primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; border: none; padding: .65rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; }
.btn-primary:disabled { opacity: .4; }
.msg-ok { margin-top: 1rem; background: #f0fdf4; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .83rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.sub { font-size: .72rem; color: #94a3b8; }
.money { text-align: right; font-weight: 600; }
.actions { white-space: nowrap; }
.act-btn { border: none; border-radius: 6px; padding: .3rem .7rem; font-size: .76rem; cursor: pointer; font-weight: 600; }
.act-btn.blue { background: #dbeafe; color: #1e40af; }
.status-badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.status-badge.emitido   { background: #fef3c7; color: #92400e; }
.status-badge.liquidado { background: #dbeafe; color: #1e40af; }
.status-badge.pago      { background: #dcfce7; color: #15803d; }
.status-badge.anulado   { background: #fee2e2; color: #991b1b; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
