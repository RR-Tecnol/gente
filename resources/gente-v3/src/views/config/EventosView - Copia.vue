<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">💰 Folha</span>
          <h1 class="hero-title">Eventos de Folha</h1>
          <p class="hero-sub">Verbas, proventos e descontos da folha de pagamento</p>
        </div>
        <button class="novo-btn" @click="abrirModal()">+ Novo Evento</button>
      </div>
    </div>

    <!-- ABAS: Proventos / Descontos -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'P' }" @click="aba = 'P'">💚 Proventos</button>
      <button class="tab-btn" :class="{ active: aba === 'D' }" @click="aba = 'D'">🔴 Descontos</button>
      <button class="tab-btn" :class="{ active: aba === '' }" @click="aba = ''">📋 Todos</button>
    </div>

    <div class="table-card" :class="{ loaded }">
      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <table v-else class="cfg-table">
        <thead><tr><th>Código</th><th>Descrição</th><th>Tipo</th><th>Incide INSS</th><th>Incide IRRF</th><th>Incide FGTS</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-for="e in eventosFiltrados" :key="e.id" class="cfg-row">
            <td><code class="cod-chip">{{ e.codigo }}</code></td>
            <td><span class="item-nome">{{ e.nome }}</span></td>
            <td><span :class="e.tipo === 'P' ? 'badge-prov' : 'badge-desc'">{{ e.tipo === 'P' ? '💚 Provento' : '🔴 Desconto' }}</span></td>
            <td><span :class="e.inss ? 'check-sim' : 'check-nao'">{{ e.inss ? '✓' : '–' }}</span></td>
            <td><span :class="e.irrf ? 'check-sim' : 'check-nao'">{{ e.irrf ? '✓' : '–' }}</span></td>
            <td><span :class="e.fgts ? 'check-sim' : 'check-nao'">{{ e.fgts ? '✓' : '–' }}</span></td>
            <td><span class="status-badge" :class="e.ativo ? 'badge-green' : 'badge-red'"><span class="badge-dot"></span>{{ e.ativo ? 'Ativo' : 'Inativo' }}</span></td>
            <td><div class="row-actions">
              <button class="act-btn" @click="abrirModal(e)" title="Editar">✏️</button>
              <button v-if="e.ativo" class="act-btn act-red" @click="inativar(e)" title="Inativar">🚫</button>
            </div></td>
          </tr>
          <tr v-if="eventosFiltrados.length === 0"><td colspan="8" class="empty-td">Nenhum evento cadastrado.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>{{ form.id ? '✏️ Editar Evento' : '+ Novo Evento de Folha' }}</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group"><label>Código <span class="req">*</span></label><input v-model="form.EVENTO_CODIGO" class="cfg-input" placeholder="001" /></div>
              <div class="form-group"><label>Tipo <span class="req">*</span></label>
                <select v-model="form.EVENTO_TIPO" class="cfg-input">
                  <option value="P">Provento</option>
                  <option value="D">Desconto</option>
                </select>
              </div>
            </div>
            <div class="form-group"><label>Descrição / Nome <span class="req">*</span></label><input v-model="form.EVENTO_NOME" class="cfg-input" placeholder="Ex: Salário Base" /></div>
            <div class="form-row">
              <div class="form-group"><label><input type="checkbox" v-model="form.EVENTO_INSS" style="margin-right:6px" /> Incide INSS</label></div>
              <div class="form-group"><label><input type="checkbox" v-model="form.EVENTO_IRRF" style="margin-right:6px" /> Incide IRRF</label></div>
              <div class="form-group"><label><input type="checkbox" v-model="form.EVENTO_FGTS" style="margin-right:6px" /> Incide FGTS</label></div>
            </div>
            <div v-if="erroModal" class="erro-msg">⚠️ {{ erroModal }}</div>
            <div v-if="okModal" class="ok-msg">✅ {{ okModal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" :class="form.EVENTO_TIPO === 'D' ? 'btn-red' : ''" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ form.id ? 'Salvar' : 'Criar Evento' }}</template>
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

const loaded = ref(false); const loading = ref(true)
const eventos = ref([]); const aba = ref('P')
const modalAberto = ref(false); const salvando = ref(false)
const erroModal = ref(''); const okModal = ref('')

const eventosFiltrados = computed(() => aba.value ? eventos.value.filter(e => e.tipo === aba.value) : eventos.value)

const formVazio = () => ({ id: null, EVENTO_CODIGO: '', EVENTO_NOME: '', EVENTO_TIPO: 'P', EVENTO_INSS: false, EVENTO_IRRF: false, EVENTO_FGTS: false })
const form = ref(formVazio())

onMounted(async () => {
  try { const { data } = await api.get('/api/v3/eventos'); eventos.value = data.eventos ?? [] }
  catch { eventos.value = [] }
  finally { loading.value = false; setTimeout(() => { loaded.value = true }, 80) }
})

const abrirModal = (e = null) => {
  erroModal.value = ''; okModal.value = ''
  form.value = e ? { id: e.id, EVENTO_CODIGO: e.codigo, EVENTO_NOME: e.nome, EVENTO_TIPO: e.tipo, EVENTO_INSS: !!e.inss, EVENTO_IRRF: !!e.irrf, EVENTO_FGTS: !!e.fgts } : formVazio()
  modalAberto.value = true
}

const salvar = async () => {
  if (!form.value.EVENTO_CODIGO || !form.value.EVENTO_NOME) { erroModal.value = 'Código e nome são obrigatórios.'; return }
  salvando.value = true
  try {
    if (form.value.id) { await api.put(`/api/v3/eventos/${form.value.id}`, form.value); okModal.value = 'Atualizado!' }
    else { await api.post('/api/v3/eventos', form.value); okModal.value = 'Evento criado!' }
    const { data } = await api.get('/api/v3/eventos'); eventos.value = data.eventos ?? []
    setTimeout(() => { modalAberto.value = false }, 1200)
  } catch (e) { erroModal.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const inativar = async (e) => {
  if (!confirm(`Inativar "${e.nome}"?`)) return
  try { await api.delete(`/api/v3/eventos/${e.id}`); const { data } = await api.get('/api/v3/eventos'); eventos.value = data.eventos ?? [] }
  catch (err) { alert(err.response?.data?.erro || 'Erro.') }
}
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1f1030); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #86efac; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.novo-btn { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(34,197,94,0.4); }
.tabs-bar { display: flex; gap: 4px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 6px; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.06s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: 8px 18px; border-radius: 10px; border: none; background: transparent; font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.tab-btn:hover { background: #f8fafc; color: #1e293b; }
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
.badge-prov { display: inline-block; background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: 8px; padding: 3px 10px; font-size: 11px; font-weight: 700; }
.badge-desc { display: inline-block; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 8px; padding: 3px 10px; font-size: 11px; font-weight: 700; }
.check-sim { color: #16a34a; font-size: 15px; font-weight: 900; }
.check-nao { color: #cbd5e1; font-size: 15px; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-red   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.row-actions { display: flex; gap: 4px; }
.act-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-btn:hover { transform: translateY(-1px); }
.empty-td { text-align: center; padding: 40px; font-size: 14px; color: #94a3b8; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
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
.modal-submit.btn-red { background: linear-gradient(135deg, #ef4444, #dc2626) !important; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
