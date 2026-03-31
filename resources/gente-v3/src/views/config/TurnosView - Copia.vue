<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⚙️ Configurações</span>
          <h1 class="hero-title">Gestão de Turnos</h1>
          <p class="hero-sub">{{ turnos.length }} turno(s) cadastrado(s)</p>
        </div>
        <button class="novo-btn" @click="abrirModal()">+ Novo Turno</button>
      </div>
    </div>

    <div class="table-card" :class="{ loaded }">
      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <table v-else class="cfg-table">
        <thead><tr>
          <th>Nome</th><th>Código</th><th>Entrada</th><th>Saída</th><th>Carga Horária</th><th>Status</th><th>Ações</th>
        </tr></thead>
        <tbody>
          <tr v-for="t in turnos" :key="t.id" class="cfg-row">
            <td><span class="item-nome">{{ t.nome }}</span></td>
            <td><code class="cod-chip">{{ t.codigo }}</code></td>
            <td class="text-mono">{{ t.hora_entrada || '—' }}</td>
            <td class="text-mono">{{ t.hora_saida || '—' }}</td>
            <td>{{ t.carga_horaria ? t.carga_horaria + 'h' : '—' }}</td>
            <td><span class="status-badge" :class="t.ativo ? 'badge-green' : 'badge-red'"><span class="badge-dot"></span>{{ t.ativo ? 'Ativo' : 'Inativo' }}</span></td>
            <td>
              <div class="row-actions">
                <button class="act-btn act-purple" title="Editar" @click="abrirModal(t)">✏️</button>
                <button v-if="t.ativo" class="act-btn act-red" title="Inativar" @click="inativar(t)">🚫</button>
              </div>
            </td>
          </tr>
          <tr v-if="turnos.length === 0">
            <td colspan="7" class="empty-td">Nenhum turno cadastrado.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>{{ form.id ? '✏️ Editar Turno' : '+ Novo Turno' }}</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-2"><label>Nome do Turno <span class="req">*</span></label><input v-model="form.TURNO_NOME" class="cfg-input" placeholder="Ex: Matutino" /></div>
              <div class="form-group"><label>Código <span class="req">*</span></label><input v-model="form.TURNO_CODIGO" class="cfg-input" placeholder="M" maxlength="5" /></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Hora Entrada</label><input v-model="form.TURNO_HORA_ENTRADA" type="time" class="cfg-input" /></div>
              <div class="form-group"><label>Hora Saída</label><input v-model="form.TURNO_HORA_SAIDA" type="time" class="cfg-input" /></div>
              <div class="form-group"><label>Carga Horária (h)</label><input v-model="form.TURNO_CARGA_HORARIA" type="number" min="1" max="24" class="cfg-input" placeholder="8" /></div>
            </div>
            <div class="form-group"><label>Observação</label><textarea v-model="form.TURNO_OBS" class="cfg-input cfg-ta" rows="2"></textarea></div>
            <div v-if="erroModal" class="erro-msg">⚠️ {{ erroModal }}</div>
            <div v-if="okModal" class="ok-msg">✅ {{ okModal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ form.id ? 'Salvar' : 'Criar Turno' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false); const loading = ref(true)
const turnos = ref([]); const modalAberto = ref(false)
const salvando = ref(false); const erroModal = ref(''); const okModal = ref('')
const formVazio = () => ({ id: null, TURNO_NOME: '', TURNO_CODIGO: '', TURNO_HORA_ENTRADA: '', TURNO_HORA_SAIDA: '', TURNO_CARGA_HORARIA: '', TURNO_OBS: '' })
const form = ref(formVazio())

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/turnos')
    turnos.value = data.turnos ?? []
  } catch { turnos.value = [] }
  finally { loading.value = false; setTimeout(() => { loaded.value = true }, 80) }
})

const abrirModal = (t = null) => {
  erroModal.value = ''; okModal.value = ''
  form.value = t ? { id: t.id, TURNO_NOME: t.nome, TURNO_CODIGO: t.codigo, TURNO_HORA_ENTRADA: t.hora_entrada || '', TURNO_HORA_SAIDA: t.hora_saida || '', TURNO_CARGA_HORARIA: t.carga_horaria || '', TURNO_OBS: t.obs || '' } : formVazio()
  modalAberto.value = true
}

const salvar = async () => {
  if (!form.value.TURNO_NOME || !form.value.TURNO_CODIGO) { erroModal.value = 'Nome e código são obrigatórios.'; return }
  salvando.value = true
  try {
    if (form.value.id) {
      await api.put(`/api/v3/turnos/${form.value.id}`, form.value); okModal.value = 'Turno atualizado!'
    } else {
      await api.post('/api/v3/turnos', form.value); okModal.value = 'Turno criado!'
    }
    const { data } = await api.get('/api/v3/turnos'); turnos.value = data.turnos ?? []
    setTimeout(() => { modalAberto.value = false }, 1200)
  } catch (e) { erroModal.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const inativar = async (t) => {
  if (!confirm(`Inativar "${t.nome}"?`)) return
  try { await api.delete(`/api/v3/turnos/${t.id}`); const { data } = await api.get('/api/v3/turnos'); turnos.value = data.turnos ?? [] }
  catch (e) { alert(e.response?.data?.erro || 'Erro ao inativar.') }
}
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1a1a3a); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.novo-btn { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.1s; }
.table-card.loaded { opacity: 1; transform: none; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 10px; color: #94a3b8; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cfg-table { width: 100%; border-collapse: collapse; }
.cfg-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cfg-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; }
.cfg-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.cfg-row:hover { background: #f8fafc; }
.cfg-row:last-child { border-bottom: none; }
.cfg-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.item-nome { font-weight: 700; color: #1e293b; }
.cod-chip { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; border-radius: 6px; padding: 2px 8px; font-weight: 800; font-size: 12px; }
.text-mono { font-family: monospace; font-weight: 700; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-red   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.row-actions { display: flex; gap: 4px; }
.act-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-btn:hover { transform: translateY(-1px); }
.empty-td { text-align: center; padding: 40px; font-size: 14px; color: #94a3b8; }
/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 540px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
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
.cfg-input:focus { border-color: #6366f1; }
.cfg-ta { resize: vertical; min-height: 60px; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.ok-msg { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
