<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🗓️ Utopian Calendar</span>
          <h1 class="hero-title">Gerenciamento de Feriados e Folgas</h1>
          <p class="hero-sub">{{ feriados.length }} registro(s) em {{ ano }}</p>
        </div>
        <div class="hero-actions">
          <select v-model.number="ano" class="cfg-input ano-select" @change="carregar">
            <option v-for="a in anosDisponiveis" :key="a" :value="a">{{ a }}</option>
          </select>
          <button class="novo-btn" @click="abrirModal()">+ Novo Override/Folga</button>
        </div>
      </div>
    </div>

    <div class="table-card" :class="{ loaded }">
      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <table v-else class="cfg-table">
        <thead><tr>
          <th>Data</th><th>Nome</th><th>Escopo</th><th>Banco de Horas</th><th>Origem</th><th>Ações</th>
        </tr></thead>
        <tbody>
          <tr v-for="f in feriadosOrdenados" :key="f.id" class="cfg-row">
            <td><span class="data-chip">{{ formatDate(f.date) }}</span></td>
            <td><span class="item-nome">{{ f.holiday_name }}</span></td>
            <td><span class="tipo-badge" :class="`tipo-${f.scope}`">{{ scopeLabel(f.scope) }}</span></td>
            <td>
              <span :class="f.impacts_bank_of_hours ? 'badge-green status-badge' : 'badge-gray status-badge'">
                <span class="badge-dot"></span>{{ f.impacts_bank_of_hours ? '0h esperada / extra 100%' : 'Sem impacto' }}
              </span>
            </td>
            <td>
              <span class="tipo-badge" :class="f.source === 'base' ? 'tipo-base' : 'tipo-override'">
                {{ f.source === 'base' ? 'Padrão 2026' : 'Override' }}
              </span>
            </td>
            <td>
              <div class="row-actions">
                <button class="act-btn act-purple" :disabled="f.source === 'base'" @click="abrirModal(f)" title="Editar">✏️</button>
                <button class="act-btn act-red" :disabled="f.source === 'base'" @click="remover(f)" title="Remover">🗑️</button>
              </div>
            </td>
          </tr>
          <tr v-if="feriados.length === 0"><td colspan="6" class="empty-td">Nenhum registro encontrado.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>{{ form.id ? '✏️ Editar Feriado' : '🗓️ Novo Feriado' }}</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-2"><label>Nome <span class="req">*</span></label><input v-model="form.holiday_name" class="cfg-input" placeholder="Ex: Folga remunerada UTI" /></div>
              <div class="form-group"><label>Data <span class="req">*</span></label><input v-model="form.date" type="date" class="cfg-input" /></div>
              <div class="form-group"><label>Escopo</label>
                <select v-model="form.FERIADO_TIPO" class="cfg-input">
                  <option value="global">Todo o Hospital (Global)</option>
                  <option value="sector">Setor Específico</option>
                  <option value="user">Funcionário Específico</option>
                </select>
              </div>
            </div>

            <div class="form-row" v-if="form.FERIADO_TIPO === 'sector' || form.FERIADO_TIPO === 'user'">
              <div class="form-group">
                <label>{{ form.FERIADO_TIPO === 'sector' ? 'Setor' : 'Funcionário' }}</label>
                <select v-model.number="form.target_id" class="cfg-input">
                  <option :value="null">Selecione...</option>
                  <option v-for="item in (form.FERIADO_TIPO === 'sector' ? setores : funcionarios)" :key="item.id" :value="item.id">
                    {{ item.nome }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Observação</label>
                <input v-model="form.note" class="cfg-input" placeholder="Ex: Cobertura reduzida por escala de plantão" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label><input type="checkbox" v-model="form.impacts_bank_of_hours" style="margin-right:6px" /> Impacta Banco de Horas</label>
              </div>
              <div class="form-group">
                <label><input type="checkbox" v-model="form.is_point_facultative" style="margin-right:6px" /> Ponto Facultativo</label>
              </div>
            </div>
            <div class="form-group">
              <label>Multiplicador de pagamento (se trabalhar)</label>
              <input v-model.number="form.pay_multiplier" type="number" min="1" step="0.1" max="5" class="cfg-input" />
            </div>
            <div v-if="erroModal" class="erro-msg">⚠️ {{ erroModal }}</div>
            <div v-if="okModal" class="ok-msg">✅ {{ okModal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ form.id ? 'Salvar' : 'Cadastrar Feriado' }}</template>
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

const loaded = ref(false)
const loading = ref(true)
const feriados = ref([])
const modalAberto = ref(false)
const salvando = ref(false)
const erroModal = ref('')
const okModal = ref('')
const ano = ref(2026)
const anosDisponiveis = computed(() => [2026, 2027, 2028])
const setores = ref([])
const funcionarios = ref([])

const feriadosOrdenados = computed(() => [...feriados.value].sort((a,b) => String(a.date).localeCompare(String(b.date))))

const formVazio = () => ({
  id: null,
  holiday_name: '',
  date: '',
  FERIADO_TIPO: 'global',
  is_point_facultative: false,
  impacts_bank_of_hours: true,
  target_id: null,
  type: 'holiday',
  pay_multiplier: 2.0,
  note: ''
})
const form = ref(formVazio())

const carregar = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/feriados/manager', { params: { ano: ano.value } })
    feriados.value = data.feriados ?? []
  } catch {
    feriados.value = []
  } finally {
    loading.value = false
  }
}

const carregarBasesOverride = async () => {
  try {
    const [setoresRes, funcionariosRes] = await Promise.all([
      api.get('/api/v3/setores').catch(() => ({ data: { setores: [] } })),
      api.get('/api/v3/funcionarios').catch(() => ({ data: { funcionarios: [] } })),
    ])
    setores.value = (setoresRes.data?.setores ?? []).map(s => ({ id: s.id, nome: s.nome }))
    funcionarios.value = (funcionariosRes.data?.funcionarios ?? []).map(f => ({ id: f.id, nome: f.nome }))
  } catch {
    setores.value = []
    funcionarios.value = []
  }
}

onMounted(async () => {
  await Promise.all([carregar(), carregarBasesOverride()])
  setTimeout(() => { loaded.value = true }, 80)
})

const abrirModal = (f = null) => {
  erroModal.value = ''
  okModal.value = ''
  form.value = f
    ? {
      id: Number(String(f.id).replace('ovr-', '')),
      holiday_name: f.holiday_name,
      date: f.date,
      FERIADO_TIPO: f.scope || 'global',
      type: f.type || 'holiday',
      is_point_facultative: !!f.is_point_facultative,
      impacts_bank_of_hours: !!f.impacts_bank_of_hours,
      target_id: f.target_id ?? null,
      pay_multiplier: Number(f.pay_multiplier ?? 2.0),
      note: f.note ?? ''
    }
    : formVazio()
  modalAberto.value = true
}

const salvar = async () => {
  if (!form.value.holiday_name || !form.value.date) {
    erroModal.value = 'Nome e data são obrigatórios.'
    return
  }
  if ((form.value.FERIADO_TIPO === 'sector' || form.value.FERIADO_TIPO === 'user') && !form.value.target_id) {
    erroModal.value = 'Selecione o alvo do override.'
    return
  }
  salvando.value = true
  try {
    const payload = {
      holiday_name: form.value.holiday_name,
      date: form.value.date,
      scope: form.value.FERIADO_TIPO,
      type: form.value.type || (form.value.FERIADO_TIPO === 'sector' ? 'sector_off' : (form.value.FERIADO_TIPO === 'user' ? 'individual_off' : 'holiday')),
      is_point_facultative: !!form.value.is_point_facultative,
      impacts_bank_of_hours: !!form.value.impacts_bank_of_hours,
      target_id: form.value.target_id,
      pay_multiplier: Number(form.value.pay_multiplier || 2),
      note: form.value.note || null,
    }
    if (form.value.id) {
      await api.put(`/api/v3/feriados/manager/${form.value.id}`, payload)
      okModal.value = 'Atualizado!'
    } else {
      await api.post('/api/v3/feriados/manager', payload)
      okModal.value = 'Override/Folga cadastrado!'
    }
    await carregar()
    setTimeout(() => { modalAberto.value = false }, 1200)
  } catch (e) {
    erroModal.value = e.response?.data?.erro || 'Erro ao salvar.'
  } finally {
    salvando.value = false
  }
}

const remover = async (f) => {
  if (f.source === 'base') return
  if (!confirm(`Remover "${f.holiday_name}"?`)) return
  try {
    await api.delete(`/api/v3/feriados/manager/${Number(String(f.id).replace('ovr-', ''))}`)
    feriados.value = feriados.value.filter(x => x.id !== f.id)
  } catch (e) {
    alert(e.response?.data?.erro || 'Erro ao remover.')
  }
}

const scopeLabel = (s) => ({
  national: 'Nacional',
  state: 'Estadual (MA)',
  municipal: 'Municipal (SLZ)',
  global: 'Hospital (Global)',
  sector: 'Setor',
  user: 'Funcionário',
})[s] || s
const formatDate = (d) => { try { const [y,m,day] = String(d).slice(0,10).split('-'); return `${day}/${m}/${y}` } catch { return d } }
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1a2a1a); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6ee7b7; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-actions { display: flex; align-items: center; gap: 10px; }
.ano-select { min-width: 110px; background: #fff; }
.novo-btn { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981, #0d9488); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(16,185,129,0.4); }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.1s; }
.table-card.loaded { opacity: 1; transform: none; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 10px; color: #94a3b8; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #10b981; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cfg-table { width: 100%; border-collapse: collapse; }
.cfg-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cfg-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; }
.cfg-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.cfg-row:hover { background: #f8fafc; }
.cfg-row:last-child { border-bottom: none; }
.cfg-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.item-nome { font-weight: 700; color: #1e293b; }
.data-chip { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 8px; padding: 3px 10px; font-weight: 800; font-size: 12px; font-family: monospace; }
.tipo-badge { display: inline-block; padding: 3px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; border: 1px solid; }
.tipo-national { background: #dcfce7; color: #166534; border-color: #86efac; }
.tipo-state { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.tipo-municipal { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.tipo-global, .tipo-sector, .tipo-user { background: #f5f3ff; color: #7c3aed; border-color: #ddd6fe; }
.tipo-base { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
.tipo-override { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-gray  { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.row-actions { display: flex; gap: 4px; }
.act-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-btn:hover { transform: translateY(-1px); }
.act-btn:disabled { opacity: .45; cursor: not-allowed; transform: none !important; }
.empty-td { text-align: center; padding: 40px; font-size: 14px; color: #94a3b8; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.col-2 { grid-column: span 2; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #10b981; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.ok-msg { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981, #0d9488); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
