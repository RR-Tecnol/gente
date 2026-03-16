<template>
  <div class="erp-page">
    <div class="hero teal">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">🏦 ERP Municipal</span>
          <h1 class="hero-title">Tesouraria</h1>
          <p class="hero-sub">Fluxo de Caixa · Contas Bancárias · Conciliação</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi"><span class="kl">Saldo Total</span><span class="kv">{{ fmt(resumo.saldo_total) }}</span></div>
          <div class="kpi"><span class="kl">Entradas hoje</span><span class="kv pos">{{ fmt(resumo.entradas_hoje) }}</span></div>
          <div class="kpi"><span class="kl">Saídas hoje</span><span class="kv neg">{{ fmt(resumo.saidas_hoje) }}</span></div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button class="tab" :class="{ active: aba === 'contas' }"  @click="aba='contas'; carregarContas()">🏦 Contas Bancárias</button>
      <button class="tab" :class="{ active: aba === 'fluxo' }"  @click="aba='fluxo'; carregarFluxo()">📊 Fluxo de Caixa</button>
      <button class="tab" :class="{ active: aba === 'movtos' }" @click="aba='movtos'; carregarMovimentos()">📋 Movimentações</button>
    </div>

    <!-- Contas Bancárias -->
    <div v-if="aba === 'contas'" class="card">
      <div class="card-hdr">
        <h2>Contas Bancárias</h2>
        <button class="btn-primary" @click="modalConta = true">+ Nova Conta</button>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="contas.length">
          <thead><tr><th>Banco</th><th>Agência</th><th>Conta</th><th>Tipo</th><th>Saldo</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="c in contas" :key="c.CONTA_ID">
              <td><strong>{{ c.BANCO_NOME }}</strong><br><small>{{ c.BANCO_CODIGO }}</small></td>
              <td>{{ c.AGENCIA }}</td>
              <td class="mono">{{ c.NUMERO_CONTA }}-{{ c.DIGITO }}</td>
              <td><span class="badge">{{ c.TIPO_CONTA }}</span></td>
              <td :class="c.SALDO_ATUAL >= 0 ? 'pos' : 'neg'">{{ fmt(c.SALDO_ATUAL) }}</td>
              <td><span class="status-badge" :class="c.STATUS?.toLowerCase()">{{ c.STATUS }}</span></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhuma conta bancária cadastrada.</div>
      </div>
    </div>

    <!-- Fluxo de Caixa -->
    <div v-if="aba === 'fluxo'" class="card">
      <div class="card-hdr">
        <h2>Fluxo de Caixa</h2>
        <div class="toolbar">
          <select v-model="periodoFluxo" @change="carregarFluxo" class="inp small">
            <option value="7">Últimos 7 dias</option>
            <option value="30">Últimos 30 dias</option>
            <option value="90">Último trimestre</option>
          </select>
        </div>
      </div>
      <div class="kpi-grid">
        <div class="kpi-item"><span class="ki-l">Entradas no período</span><span class="ki-v pos">{{ fmt(fluxo.total_entradas) }}</span></div>
        <div class="kpi-item"><span class="ki-l">Saídas no período</span><span class="ki-v neg">{{ fmt(fluxo.total_saidas) }}</span></div>
        <div class="kpi-item" :class="(fluxo.saldo_periodo ?? 0) >= 0 ? 'green' : 'red'">
          <span class="ki-l">Saldo do período</span>
          <span class="ki-v">{{ fmt(fluxo.saldo_periodo) }}</span>
        </div>
      </div>
      <div v-if="fluxo.dias?.length" class="table-wrap" style="margin-top:1rem">
        <table class="tbl">
          <thead><tr><th>Data</th><th>Entradas</th><th>Saídas</th><th>Saldo</th></tr></thead>
          <tbody>
            <tr v-for="d in fluxo.dias" :key="d.data">
              <td>{{ new Date(d.data+'T12:00').toLocaleDateString('pt-BR') }}</td>
              <td class="pos">{{ fmt(d.entradas) }}</td>
              <td class="neg">{{ fmt(d.saidas) }}</td>
              <td :class="d.saldo >= 0 ? 'pos' : 'neg'">{{ fmt(d.saldo) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty">📭 Sem dados de fluxo para o período.</div>
    </div>

    <!-- Movimentações -->
    <div v-if="aba === 'movtos'" class="card">
      <div class="card-hdr">
        <h2>Movimentações Bancárias</h2>
        <div class="toolbar">
          <select v-model="contaSel" @change="carregarMovimentos" class="inp">
            <option value="">Todas as contas</option>
            <option v-for="c in contas" :key="c.CONTA_ID" :value="c.CONTA_ID">{{ c.BANCO_NOME }} — {{ c.NUMERO_CONTA }}</option>
          </select>
          <button class="btn-primary" @click="modalMovto = true">+ Novo Lançamento</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" v-if="movimentos.length">
          <thead><tr><th>Data</th><th>Histórico</th><th>Tipo</th><th>Valor</th><th>Conta</th></tr></thead>
          <tbody>
            <tr v-for="m in movimentos" :key="m.MOVIM_ID">
              <td>{{ new Date(m.MOVIM_DATA+'T12:00').toLocaleDateString('pt-BR') }}</td>
              <td>{{ m.MOVIM_HISTORICO }}</td>
              <td><span class="badge" :class="m.MOVIM_TIPO === 'C' ? 'green' : 'red'">{{ m.MOVIM_TIPO === 'C' ? 'Crédito' : 'Débito' }}</span></td>
              <td :class="m.MOVIM_TIPO === 'C' ? 'pos' : 'neg'">{{ fmt(m.MOVIM_VALOR) }}</td>
              <td>{{ m.banco_nome || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">📭 Nenhuma movimentação encontrada.</div>
      </div>
    </div>

    <!-- Modal Nova Conta -->
    <div v-if="modalConta" class="modal-overlay" @click.self="modalConta = false">
      <div class="modal">
        <div class="modal-hdr"><h3>Nova Conta Bancária</h3><button @click="modalConta = false">✕</button></div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="fg"><label>Banco</label><input class="inp" v-model="formConta.BANCO_NOME" placeholder="Ex: Banco do Brasil" /></div>
            <div class="fg"><label>Código Banco</label><input class="inp" v-model="formConta.BANCO_CODIGO" placeholder="001" /></div>
            <div class="fg"><label>Agência</label><input class="inp" v-model="formConta.AGENCIA" placeholder="0001-X" /></div>
            <div class="fg"><label>Número</label><input class="inp" v-model="formConta.NUMERO_CONTA" /></div>
            <div class="fg"><label>Dígito</label><input class="inp" v-model="formConta.DIGITO" /></div>
            <div class="fg"><label>Tipo</label>
              <select class="inp" v-model="formConta.TIPO_CONTA">
                <option value="CORRENTE">Corrente</option>
                <option value="POUPANCA">Poupança</option>
                <option value="INVESTIMENTO">Investimento</option>
              </select>
            </div>
          </div>
          <div class="modal-actions">
            <button class="btn-sec" @click="modalConta = false">Cancelar</button>
            <button class="btn-primary" @click="salvarConta" :disabled="salvando">{{ salvando ? '⏳...' : '💾 Salvar' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Novo Lançamento -->
    <div v-if="modalMovto" class="modal-overlay" @click.self="modalMovto = false">
      <div class="modal">
        <div class="modal-hdr"><h3>Novo Lançamento</h3><button @click="modalMovto = false">✕</button></div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="fg"><label>Conta</label>
              <select class="inp" v-model="formMovto.CONTA_ID">
                <option v-for="c in contas" :key="c.CONTA_ID" :value="c.CONTA_ID">{{ c.BANCO_NOME }} — {{ c.NUMERO_CONTA }}</option>
              </select>
            </div>
            <div class="fg"><label>Data</label><input class="inp" type="date" v-model="formMovto.MOVIM_DATA" /></div>
            <div class="fg"><label>Tipo</label>
              <select class="inp" v-model="formMovto.MOVIM_TIPO">
                <option value="C">Crédito (Entrada)</option>
                <option value="D">Débito (Saída)</option>
              </select>
            </div>
            <div class="fg"><label>Valor</label><input class="inp" type="number" step="0.01" v-model="formMovto.MOVIM_VALOR" /></div>
            <div class="fg full"><label>Histórico</label><input class="inp" v-model="formMovto.MOVIM_HISTORICO" /></div>
          </div>
          <div class="modal-actions">
            <button class="btn-sec" @click="modalMovto = false">Cancelar</button>
            <button class="btn-primary" @click="salvarMovto" :disabled="salvando">{{ salvando ? '⏳...' : '💾 Salvar' }}</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="msg" class="msg-ok">{{ msg }}</div>
    <div v-if="loading" class="spinner-wrap"><div class="spinner"></div></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const aba          = ref('contas')
const loading      = ref(false)
const salvando     = ref(false)
const msg          = ref('')
const contas       = ref([])
const fluxo        = ref({})
const movimentos   = ref([])
const resumo       = ref({ saldo_total: 0, entradas_hoje: 0, saidas_hoje: 0 })
const periodoFluxo = ref('30')
const contaSel     = ref('')
const modalConta   = ref(false)
const modalMovto   = ref(false)

const formConta = ref({ BANCO_NOME: '', BANCO_CODIGO: '', AGENCIA: '', NUMERO_CONTA: '', DIGITO: '', TIPO_CONTA: 'CORRENTE' })
const formMovto = ref({ CONTA_ID: null, MOVIM_DATA: new Date().toISOString().slice(0, 10), MOVIM_TIPO: 'C', MOVIM_VALOR: '', MOVIM_HISTORICO: '' })

async function carregarContas() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/tesouraria/contas')
    contas.value = data.contas || []
    resumo.value = data.resumo || resumo.value
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarFluxo() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/tesouraria/fluxo', { params: { dias: periodoFluxo.value } })
    fluxo.value = data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function carregarMovimentos() {
  loading.value = true
  try {
    const params = contaSel.value ? { conta_id: contaSel.value } : {}
    const { data } = await api.get('/api/v3/tesouraria/movimentos', { params })
    movimentos.value = data.movimentos || []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function salvarConta() {
  salvando.value = true
  try {
    await api.post('/api/v3/tesouraria/conta', formConta.value)
    msg.value = '✅ Conta bancária cadastrada!'
    modalConta.value = false
    Object.assign(formConta.value, { BANCO_NOME: '', BANCO_CODIGO: '', AGENCIA: '', NUMERO_CONTA: '', DIGITO: '', TIPO_CONTA: 'CORRENTE' })
    await carregarContas()
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

async function salvarMovto() {
  salvando.value = true
  try {
    await api.post('/api/v3/tesouraria/movimentacao', formMovto.value)
    msg.value = '✅ Lançamento registrado!'
    modalMovto.value = false
    await carregarMovimentos()
  } catch (e) { console.error(e) } finally { salvando.value = false }
}

const fmt = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
onMounted(carregarContas)
</script>

<style scoped>
.erp-page { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; max-width: 1300px; margin: 0 auto; }
.hero.teal { background: linear-gradient(135deg, #134e4a, #0f766e); color: #fff; border-radius: 20px; padding: 2rem; }
.hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .1em; color: #99f6e4; text-transform: uppercase; }
.hero-title { font-size: 1.9rem; font-weight: 800; margin: .25rem 0 .5rem; }
.hero-sub { opacity: .75; font-size: .88rem; }
.hero-kpis { display: flex; gap: .75rem; flex-wrap: wrap; }
.kpi { background: rgba(255,255,255,.12); border-radius: 12px; padding: .75rem 1.25rem; text-align: center; min-width: 130px; }
.kl { display: block; font-size: .7rem; opacity: .7; text-transform: uppercase; }
.kv { display: block; font-size: 1.1rem; font-weight: 800; }
.tabs-bar { display: flex; gap: .5rem; flex-wrap: wrap; }
.tab { padding: .6rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; background: #f1f5f9; color: #475569; font-weight: 600; font-size: .82rem; }
.tab.active { background: #0f766e; color: #fff; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 1.5rem; }
.card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: .75rem; }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
.card-hdr h2 { margin-bottom: 0; }
.toolbar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.inp { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .55rem .9rem; font-size: .85rem; width: 100%; }
.inp.small { width: 180px; }
.btn-primary { background: linear-gradient(135deg, #0f766e, #0d9488); color: #fff; border: none; padding: .6rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: .85rem; white-space: nowrap; }
.btn-primary:disabled { opacity: .4; }
.btn-sec { background: #f1f5f9; border: none; padding: .6rem 1rem; border-radius: 8px; cursor: pointer; font-size: .82rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
.kpi-item { background: #f8fafc; border-radius: 12px; padding: 1rem 1.25rem; }
.kpi-item.green { background: #f0fdf4; }
.kpi-item.red { background: #fff1f2; }
.ki-l { display: block; font-size: .72rem; color: #64748b; text-transform: uppercase; margin-bottom: .25rem; }
.ki-v { display: block; font-size: 1rem; font-weight: 700; }
.pos { color: #059669; }
.neg { color: #dc2626; }
.badge { display: inline-block; padding: .15rem .5rem; border-radius: 4px; font-size: .7rem; font-weight: 700; background: #e0e7ff; color: #3730a3; }
.badge.green { background: #dcfce7; color: #15803d; }
.badge.red { background: #fee2e2; color: #991b1b; }
.status-badge { border-radius: 999px; padding: .15rem .55rem; font-size: .7rem; font-weight: 700; background: #f1f5f9; color: #475569; }
.status-badge.ativa { background: #dcfce7; color: #15803d; }
.status-badge.inativa { background: #fee2e2; color: #991b1b; }
.msg-ok { background: #f0fdf4; color: #15803d; border-radius: 8px; padding: .75rem 1rem; font-weight: 600; }
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tbl th { text-align: left; padding: .6rem .75rem; background: #f8fafc; font-size: .72rem; color: #64748b; text-transform: uppercase; }
.tbl td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; }
.mono { font-family: monospace; }
.empty { text-align: center; padding: 3rem; color: #94a3b8; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 50; display: flex; align-items: center; justify-content: center; }
.modal { background: #fff; border-radius: 16px; padding: 1.5rem; min-width: 420px; max-width: 90vw; max-height: 85vh; overflow-y: auto; }
.modal-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.modal-hdr h3 { font-size: .95rem; font-weight: 700; }
.modal-hdr button { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b; }
.modal-body { display: flex; flex-direction: column; gap: .9rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.fg { display: flex; flex-direction: column; gap: .3rem; }
.fg.full { grid-column: 1 / -1; }
.fg label { font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: .5rem; }
.spinner-wrap { display: flex; justify-content: center; padding: 2rem; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #0f766e; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
