<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">💰 Financeiro</span>
          <h1 class="hero-title">Parâmetros Financeiros</h1>
          <p class="hero-sub">INSS, IRRF, FGTS e tabelas de cálculo da folha</p>
        </div>
        <button class="save-btn" @click="abrirModal()">+ Nova Tabela</button>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button v-for="t in abas" :key="t.id" class="tab-btn" :class="{ active: aba === t.id }" @click="aba = t.id">
        {{ t.ico }} {{ t.nome }}
      </button>
    </div>

    <!-- TABELA -->
    <div class="table-card" :class="{ loaded }">
      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <table v-else class="cfg-table">
        <thead><tr>
          <th>Competência</th><th>Descrição</th><th>Valor / Alíquota</th><th>Vigência</th><th>Tipo</th><th>Ações</th>
        </tr></thead>
        <tbody>
          <tr v-for="p in parametrosFiltrados" :key="p.id" class="cfg-row">
            <td><code class="cod-chip">{{ formatCompetencia(p.competencia) }}</code></td>
            <td><span class="item-nome">{{ p.descricao }}</span></td>
            <td><span class="valor-chip">{{ formatValor(p.valor, p.tipo_valor) }}</span></td>
            <td class="text-mono">{{ formatDate(p.vigencia_inicio) }} <span v-if="p.vigencia_fim">→ {{ formatDate(p.vigencia_fim) }}</span></td>
            <td><span class="tipo-badge" :class="`tipo-${p.tipo}`">{{ p.tipo }}</span></td>
            <td>
              <div class="row-actions">
                <button class="act-btn" @click="abrirModal(p)" title="Editar">✏️</button>
                <button class="act-btn act-red" @click="remover(p)" title="Remover">🗑️</button>
              </div>
            </td>
          </tr>
          <tr v-if="parametrosFiltrados.length === 0"><td colspan="6" class="empty-td">Nenhum parâmetro cadastrado para esta categoria.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>{{ form.id ? '✏️ Editar Parâmetro' : '+ Novo Parâmetro Financeiro' }}</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group">
                <label>Tipo <span class="req">*</span></label>
                <select v-model="form.tipo" class="cfg-input">
                  <option v-for="a in abas" :key="a.id" :value="a.id">{{ a.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Competência (MMAAAA)</label>
                <input v-model="form.competencia" class="cfg-input" placeholder="032026" maxlength="6" />
              </div>
            </div>
            <div class="form-group">
              <label>Descrição <span class="req">*</span></label>
              <input v-model="form.descricao" class="cfg-input" placeholder="Ex: Alíquota INSS Faixa 1" />
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Valor <span class="req">*</span></label>
                <input v-model="form.valor" type="number" step="0.01" class="cfg-input" placeholder="0.00" />
              </div>
              <div class="form-group">
                <label>Tipo de Valor</label>
                <select v-model="form.tipo_valor" class="cfg-input">
                  <option value="ALIQUOTA">Alíquota (%)</option>
                  <option value="VALOR">Valor (R$)</option>
                  <option value="DEDUCAO">Dedução (R$)</option>
                  <option value="FAIXA">Faixa salarial</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Vigência Início</label><input v-model="form.vigencia_inicio" type="date" class="cfg-input" /></div>
              <div class="form-group"><label>Vigência Fim</label><input v-model="form.vigencia_fim" type="date" class="cfg-input" /></div>
            </div>
            <div v-if="erroModal" class="erro-msg">⚠️ {{ erroModal }}</div>
            <div v-if="okModal" class="ok-msg">✅ {{ okModal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ form.id ? 'Salvar' : 'Cadastrar' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false); const loading = ref(true); const aba = ref('INSS')
const params = ref([]); const modalAberto = ref(false)
const salvando = ref(false); const erroModal = ref(''); const okModal = ref('')

const abas = [
  { id: 'INSS', ico: '🏥', nome: 'INSS' },
  { id: 'IRRF', ico: '📋', nome: 'IRRF' },
  { id: 'FGTS', ico: '🏦', nome: 'FGTS' },
  { id: 'SALARIO_MINIMO', ico: '💵', nome: 'Salário Mínimo' },
  { id: 'OUTROS', ico: '📌', nome: 'Outros' },
]

const formVazio = () => ({ id: null, tipo: 'INSS', competencia: '', descricao: '', valor: '', tipo_valor: 'ALIQUOTA', vigencia_inicio: '', vigencia_fim: '' })
const form = ref(formVazio())

const parametrosFiltrados = computed(() => params.value.filter(p => p.tipo === aba.value))

onMounted(async () => {
  try { const { data } = await api.get('/api/v3/parametros-financeiros'); params.value = data.parametros ?? [] }
  catch { params.value = [] }
  finally { loading.value = false; setTimeout(() => { loaded.value = true }, 80) }
})

const abrirModal = (p = null) => {
  erroModal.value = ''; okModal.value = ''
  form.value = p ? { id: p.id, tipo: p.tipo, competencia: p.competencia || '', descricao: p.descricao, valor: p.valor, tipo_valor: p.tipo_valor || 'ALIQUOTA', vigencia_inicio: p.vigencia_inicio || '', vigencia_fim: p.vigencia_fim || '' } : formVazio()
  modalAberto.value = true
}

const salvar = async () => {
  if (!form.value.descricao || form.value.valor === '') { erroModal.value = 'Descrição e valor são obrigatórios.'; return }
  salvando.value = true
  try {
    if (form.value.id) { await api.put(`/api/v3/parametros-financeiros/${form.value.id}`, form.value); okModal.value = 'Atualizado!' }
    else { await api.post('/api/v3/parametros-financeiros', form.value); okModal.value = 'Criado!' }
    const { data } = await api.get('/api/v3/parametros-financeiros'); params.value = data.parametros ?? []
    setTimeout(() => { modalAberto.value = false }, 1200)
  } catch (e) { erroModal.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const remover = async (p) => {
  if (!confirm(`Remover "${p.descricao}"?`)) return
  try { await api.delete(`/api/v3/parametros-financeiros/${p.id}`); params.value = params.value.filter(x => x.id !== p.id) }
  catch (e) { alert(e.response?.data?.erro || 'Erro.') }
}

const formatCompetencia = (c) => { if (!c) return '—'; const s = String(c).padStart(6,'0'); const m = {  '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }; return `${m[s.slice(0,2)] || s.slice(0,2)}/${s.slice(2)}` }
const formatDate = (d) => { try { const [y,mo,dy] = String(d).slice(0,10).split('-'); return `${dy}/${mo}/${y}` } catch { return d || '—' } }
const formatValor = (v, t) => { if (!v) return '—'; const n = Number(v); return t === 'ALIQUOTA' ? `${n.toFixed(2)}%` : `R$ ${n.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1a1f10); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #86efac; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.save-btn { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.save-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(34,197,94,0.4); }
.tabs-bar { display: flex; gap: 4px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 6px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.06s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: 8px 16px; border-radius: 10px; border: none; background: transparent; font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.tab-btn:hover { background: #f8fafc; }
.tab-btn.active { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.1s; }
.table-card.loaded { opacity: 1; transform: none; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 10px; color: #94a3b8; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #22c55e; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cfg-table { width: 100%; border-collapse: collapse; }
.cfg-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cfg-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; }
.cfg-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.cfg-row:hover { background: #f8fafc; }
.cfg-row:last-child { border-bottom: none; }
.cfg-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.item-nome { font-weight: 700; color: #1e293b; }
.cod-chip { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 6px; padding: 2px 8px; font-weight: 800; font-size: 12px; }
.valor-chip { background: #f0fdf4; color: #166534; border: 1px solid #86efac; border-radius: 8px; padding: 3px 10px; font-weight: 800; font-size: 12px; font-family: monospace; }
.tipo-badge { display: inline-block; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.text-mono { font-family: monospace; font-size: 12px; }
.row-actions { display: flex; gap: 4px; }
.act-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-btn:hover { transform: translateY(-1px); }
.empty-td { text-align: center; padding: 40px; font-size: 14px; color: #94a3b8; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 540px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #22c55e; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.ok-msg { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
