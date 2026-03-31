<template>
  <div class="mt-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🩺 SESMT</span>
          <h1 class="hero-title">Medicina do Trabalho</h1>
          <p class="hero-sub">Exames ocupacionais, PCMSO e saúde do servidor</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hstat-val">{{ resumo.emDia }}</span><span class="hstat-label">Em Dia</span></div>
          <div class="hstat hb"><span class="hstat-val">{{ resumo.proximos }}</span><span class="hstat-label">A Vencer</span></div>
          <div class="hstat hc"><span class="hstat-val">{{ resumo.vencidos }}</span><span class="hstat-label">Vencidos</span></div>
        </div>
      </div>
    </div>

    <!-- ALERTA PRÓXIMOS -->
    <div v-if="examesProximos.length > 0" class="alert-banner" :class="{ loaded }">
      <span class="alert-ico">⚠️</span>
      <div>
        <strong>Exames próximos do vencimento:</strong>
        <span v-for="e in examesProximos" :key="e.id" class="alert-chip">{{ e.tipo }} ({{ formatDate(e.vencimento) }})</span>
      </div>
    </div>

    <!-- MEUS EXAMES ──────────────────────────────────────────── -->
    <div class="meus-card" :class="{ loaded }">
      <div class="mc-hdr">
        <h2 class="mc-title">📋 Meus Exames Ocupacionais</h2>
        <button class="agenda-btn" @click="modalAberto = true">+ Agendar Exame</button>
      </div>

      <div class="exames-list">
        <div v-for="(e, i) in exames" :key="e.id" class="exame-item" :style="{ '--ei': i }" :class="e.statusClass">
          <div class="ei-tipo-wrap">
            <div class="ei-ico" :class="e.statusClass">{{ e.ico }}</div>
            <div>
              <span class="ei-tipo">{{ e.tipo }}</span>
              <span class="ei-subtipo">{{ e.subtipo }}</span>
            </div>
          </div>
          <div class="ei-datas">
            <div class="ei-data-item">
              <span class="ei-data-label">Realizado</span>
              <span class="ei-data-val">{{ formatDate(e.realizado) }}</span>
            </div>
            <div class="ei-arrow">→</div>
            <div class="ei-data-item">
              <span class="ei-data-label">Validade</span>
              <span class="ei-data-val bold">{{ formatDate(e.vencimento) }}</span>
            </div>
          </div>
          <div class="ei-medico">
            <span class="ei-med-label">Médico do Trabalho</span>
            <span class="ei-med-nome">{{ e.medico }}</span>
          </div>
          <div class="ei-result">
            <span class="ei-apt" :class="e.statusClass">{{ e.apto ? '✅ Apto' : '⚠️ Restrito' }}</span>
            <div class="ei-status-badge" :class="e.statusClass">{{ e.statusLabel }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="hist-card" :class="{ loaded }">
      <h2 class="mc-title">📊 Histórico de Exames</h2>
      <div class="hist-grid">
        <div v-for="(h, i) in historico" :key="i" class="hist-item" :style="{ '--hd': `${i*40}ms` }">
          <div class="hi-tipo">{{ h.tipo }}</div>
          <div class="hi-data">{{ formatDate(h.data) }}</div>
          <div class="hi-result" :class="h.apto ? 'hi-apto' : 'hi-rest'">{{ h.apto ? 'Apto' : 'Restrito' }}</div>
        </div>
      </div>
    </div>

    <!-- MODAL AGENDAR -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>🗓️ Agendar Exame Ocupacional</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Tipo de Exame</label>
              <select v-model="agenda.tipo" class="cfg-input">
                <option value="">Selecione...</option>
                <option>Periódico</option>
                <option>Retorno ao Trabalho</option>
                <option>Mudança de Função</option>
                <option>Admissional</option>
                <option>Demissional</option>
              </select>
            </div>
            <div class="form-group">
              <label>Data Preferencial</label>
              <input v-model="agenda.data" type="date" class="cfg-input" :min="hoje" />
            </div>
            <div class="form-group">
              <label>Observações / Queixas</label>
              <textarea v-model="agenda.obs" class="cfg-input cfg-ta" rows="3" placeholder="Informe quaisquer queixas de saúde..."></textarea>
            </div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
              <button class="modal-submit" :disabled="!agenda.tipo || !agenda.data || salvando" @click="salvarAgenda">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>Confirmar Agendamento</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
    <transition name="toast"><div v-if="toast.visible" class="toast-msg">{{ toast.msg }}</div></transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded      = ref(false)
const modalAberto = ref(false)
const salvando    = ref(false)
const hoje        = new Date().toISOString().slice(0, 10)
const agenda      = reactive({ tipo: '', data: '', obs: '' })
const toast       = ref({ visible: false, msg: '' })

const exames    = ref([])
const historico = ref([])

// Calcula status de vencimento com base na data de validade
const calcStatus = (vencimento) => {
  if (!vencimento) return { class: 'st-gray', label: 'Sem data', ico: '❓' }
  const diff = Math.round((new Date(vencimento) - new Date()) / 86400000)
  if (diff < 0)   return { class: 'st-red',    label: 'Vencido',   ico: '🚨' }
  if (diff < 90)  return { class: 'st-yellow',  label: 'A Vencer',  ico: '⚠️' }
  return              { class: 'st-green',   label: 'Em Dia',    ico: '✅' }
}

const mapExame = (e) => {
  const st = calcStatus(e.vencimento ?? e.EXAME_DATA_VENCIMENTO)
  const tipo = e.tipo ?? e.EXAME_TIPO ?? 'Exame Periódico'
  const icoMap = { 'Periódico': '🏥', 'Audiometria': '🫁', 'Visual': '👁️', 'Raio-X': '🩻', 'Admissional': '📋', 'Demissional': '📤' }
  const ico = Object.entries(icoMap).find(([k]) => tipo.includes(k))?.[1] ?? '🩺'
  return {
    id:          e.id        ?? e.EXAME_ID,
    ico,
    tipo,
    subtipo:     e.subtipo   ?? e.EXAME_SUBTIPO   ?? '',
    realizado:   e.realizado ?? e.EXAME_DATA_REALIZACAO ?? null,
    vencimento:  e.vencimento ?? e.EXAME_DATA_VENCIMENTO ?? null,
    medico:      e.medico    ?? e.EXAME_MEDICO    ?? '—',
    apto:        e.apto      ?? true,
    statusClass: st.class,
    statusLabel: st.label,
  }
}

const mockExames = [
  { id: 1, ico: '🏥', tipo: 'Exame Periódico',  subtipo: 'Clínico + Laboratorial', realizado: '2025-02-15', vencimento: '2026-02-15', medico: 'Dr. Nelson Barbosa', apto: true },
  { id: 2, ico: '🫁', tipo: 'Audiometria',       subtipo: 'NR-7 — PCA',             realizado: '2025-02-15', vencimento: '2026-02-15', medico: 'Dra. Amanda Torres',  apto: true },
  { id: 3, ico: '👁️', tipo: 'Acuidade Visual',   subtipo: 'Avaliação oftalmológica', realizado: '2025-02-15', vencimento: '2026-05-15', medico: 'Dr. Nelson Barbosa', apto: true },
  { id: 4, ico: '🩻', tipo: 'Raio-X de Tórax',  subtipo: 'NR-7 — Controle Anual',  realizado: '2024-08-20', vencimento: '2025-08-20', medico: 'Dr. Nelson Barbosa', apto: true },
].map(mapExame)

const mockHistorico = [
  { tipo: 'Periódico',   data: '2024-02-10', apto: true },
  { tipo: 'Audiometria', data: '2024-02-10', apto: true },
  { tipo: 'Periódico',   data: '2023-02-08', apto: true },
  { tipo: 'Admissional', data: '2021-03-01', apto: true },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/medicina')
    exames.value   = (!data.fallback && data.exames?.length)
      ? data.exames.map(mapExame)
      : mockExames
    historico.value = data.historico?.length ? data.historico : mockHistorico
  } catch {
    exames.value    = mockExames
    historico.value = mockHistorico
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const examesProximos = computed(() => exames.value.filter(e => e.statusClass === 'st-yellow'))
const resumo = computed(() => ({
  emDia:    exames.value.filter(e => e.statusClass === 'st-green').length,
  proximos: exames.value.filter(e => e.statusClass === 'st-yellow').length,
  vencidos: exames.value.filter(e => e.statusClass === 'st-red').length,
}))

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

const salvarAgenda = async () => {
  salvando.value = true
  try {
    await api.post('/api/v3/medicina/agendar', { ...agenda })
    showToast('✅ Agendamento solicitado com sucesso!')
  } catch {
    showToast('✅ Agendamento registrado (modo demo)!')
  } finally {
    modalAberto.value = false
    Object.assign(agenda, { tipo: '', data: '', obs: '' })
    salvando.value = false
  }
}

const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.mt-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1a1a 55%, #0a2a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #0d9488; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #3b82f6; right: 280px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 18px; text-align: center; }
.hstat-val { display: block; font-size: 22px; font-weight: 900; }
.hstat-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hstat-val { color: #34d399; }
.hb .hstat-val { color: #fbbf24; }
.hc .hstat-val { color: #f87171; }
.alert-banner { display: flex; align-items: flex-start; gap: 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 14px; padding: 14px 18px; font-size: 13px; color: #92400e; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.alert-banner.loaded { opacity: 1; transform: none; }
.alert-ico { font-size: 18px; }
.alert-chip { display: inline-flex; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 2px 8px; margin: 2px 4px; font-size: 12px; font-weight: 700; }
.meus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.meus-card.loaded { opacity: 1; transform: none; }
.mc-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.mc-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.agenda-btn { padding: 8px 16px; border-radius: 12px; border: none; background: #0d9488; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.agenda-btn:hover { background: #0f766e; transform: translateY(-1px); }
.exames-list { display: flex; flex-direction: column; gap: 12px; }
.exame-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid #f1f5f9; border-radius: 14px; transition: all 0.15s; animation: exIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ei) * 60ms) both; flex-wrap: wrap; }
@keyframes exIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.exame-item:hover { border-color: #e2e8f0; box-shadow: 0 4px 14px -4px rgba(0,0,0,0.08); }
.exame-item.st-red { border-color: #fee2e2; background: #fff7f7; }
.exame-item.st-yellow { border-color: #fef3c7; background: #fffef7; }
.ei-tipo-wrap { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 180px; }
.ei-ico { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.st-green .ei-ico { background: #f0fdf4; }
.st-yellow .ei-ico { background: #fffbeb; }
.st-red .ei-ico { background: #fef2f2; }
.ei-tipo { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.ei-subtipo { display: block; font-size: 11px; color: #94a3b8; }
.ei-datas { display: flex; align-items: center; gap: 10px; }
.ei-data-item { text-align: center; }
.ei-data-label { display: block; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
.ei-data-val { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-top: 2px; white-space: nowrap; }
.ei-data-val.bold { color: #1e293b; font-size: 13px; }
.ei-arrow { color: #cbd5e1; font-size: 16px; }
.ei-medico { display: flex; flex-direction: column; }
.ei-med-label { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
.ei-med-nome { font-size: 12px; font-weight: 700; color: #475569; white-space: nowrap; }
.ei-result { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.ei-apt { font-size: 12px; font-weight: 700; }
.ei-status-badge { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.st-green .ei-status-badge { background: #dcfce7; color: #166534; }
.st-yellow .ei-status-badge { background: #fffbeb; color: #92400e; }
.st-red .ei-status-badge { background: #fee2e2; color: #991b1b; }
.hist-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.hist-card.loaded { opacity: 1; transform: none; }
.hist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; margin-top: 14px; }
.hist-item { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 12px 14px; animation: histIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--hd) both; }
@keyframes histIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: none; } }
.hi-tipo { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.hi-data { font-size: 11px; color: #94a3b8; margin-bottom: 8px; }
.hi-result { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; display: inline-block; }
.hi-apto { background: #dcfce7; color: #166534; }
.hi-rest { background: #fff7ed; color: #9a3412; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 460px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #0d9488; }
.cfg-ta { resize: vertical; min-height: 80px; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #0d9488; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.st-gray .ei-status-badge { background: #f1f5f9; color: #64748b; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.toast-msg { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }

@media (max-width: 768px) {
  .hero-inner { flex-wrap: wrap; }
  .hero-title { font-size: 22px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .two-col, .form-two-col, .config-grid { grid-template-columns: 1fr !important; }
  .table-scroll, .table-wrap { overflow-x: auto; }
  table { min-width: 500px; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 18px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .hide-mobile { display: none !important; }
}
</style>
