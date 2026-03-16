<template>
  <div class="oadm-page">
    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🛡️ Gestão RH</span>
          <h1 class="hero-title">Painel da Ouvidoria</h1>
          <p class="hero-sub">Gerencie manifestações recebidas, responda e atualize status</p>
        </div>
        <div class="hero-kpis">
          <div class="hk hk-blue"><span class="hk-val">{{ total }}</span><span class="hk-label">Total</span></div>
          <div class="hk hk-yellow"><span class="hk-val">{{ pendentes }}</span><span class="hk-label">Pendentes</span></div>
          <div class="hk hk-orange"><span class="hk-val">{{ emAnalise }}</span><span class="hk-label">Em Análise</span></div>
          <div class="hk hk-green"><span class="hk-val">{{ respondidas }}</span><span class="hk-label">Respondidas</span></div>
        </div>
      </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-bar" :class="{ loaded }">
      <div class="search-wrap">
        <span class="search-ico">🔍</span>
        <input v-model="busca" class="search-input" placeholder="Buscar por assunto, área ou protocolo..." />
      </div>
      <div class="filtros-chips">
        <button v-for="f in filtrosStatus" :key="f.val"
          class="fchip" :class="{ active: filtroStatus === f.val, [`fchip-${f.cor}`]: filtroStatus === f.val }"
          @click="filtroStatus = filtroStatus === f.val ? '' : f.val">
          {{ f.ico }} {{ f.nome }}
        </button>
      </div>
      <div class="filtros-chips">
        <button v-for="t in tipos" :key="t.val"
          class="fchip" :class="{ active: filtroTipo === t.val }"
          :style="filtroTipo === t.val ? { background: t.cor + '18', borderColor: t.cor, color: t.cor } : {}"
          @click="filtroTipo = filtroTipo === t.val ? '' : t.val">
          {{ t.ico }} {{ t.nome }}
        </button>
      </div>
    </div>

    <!-- LISTA -->
    <div class="lista-wrap" :class="{ loaded }">
      <div v-if="carregando" class="state-loading">
        <div class="spinner"></div><p>Carregando manifestações...</p>
      </div>
      <div v-else-if="itensFiltrados.length === 0" class="state-empty">
        <span>📭</span><p>Nenhuma manifestação encontrada</p>
      </div>
      <div v-else class="ouv-table">
        <div class="ouv-thead">
          <span>Protocolo</span><span>Tipo</span><span>Área</span><span>Autor</span>
          <span>Data</span><span>Status</span><span>Ação</span>
        </div>
        <div v-for="(m, i) in itensFiltrados" :key="m.id" class="ouv-row" :style="{ '--ri': i }">
          <span class="ouv-proto">{{ m.protocolo ?? '—' }}</span>
          <span class="ouv-tipo-chip" :style="{ background: tipoCor(m.tipo) + '18', color: tipoCor(m.tipo), borderColor: tipoCor(m.tipo) }">
            {{ tipoIco(m.tipo) }} {{ tipoNome(m.tipo) }}
          </span>
          <span class="ouv-area">{{ m.area ?? '—' }}</span>
          <span class="ouv-autor">
            <span v-if="m.anonimo" class="anon-badge">🔒 Anônimo</span>
            <span v-else>{{ m.autor ?? 'Não identificado' }}</span>
          </span>
          <span class="ouv-data">{{ fmtData(m.data) }}</span>
          <span class="ouv-status-chip" :class="`sc-${m.status}`">{{ statusLabel(m.status) }}</span>
          <button class="btn-detalhe" @click="abrirDetalhe(m)">Ver / Responder</button>
        </div>
      </div>
    </div>

    <!-- MODAL DETALHE / RESPOSTA -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="fecharModal">
        <div class="modal-card">
          <div class="modal-hdr">
            <div>
              <span class="modal-proto">{{ detalhe.protocolo }}</span>
              <h3>{{ detalhe.titulo ?? detalhe.descricao?.substring(0, 50) ?? 'Manifestação' }}</h3>
            </div>
            <button class="modal-close" @click="fecharModal">✕</button>
          </div>
          <div class="modal-body">
            <!-- Info -->
            <div class="detalhe-grid">
              <div class="dg-item"><span class="dg-label">Tipo</span><span>{{ tipoIco(detalhe.tipo) }} {{ tipoNome(detalhe.tipo) }}</span></div>
              <div class="dg-item"><span class="dg-label">Área</span><span>{{ detalhe.area ?? '—' }}</span></div>
              <div class="dg-item"><span class="dg-label">Urgência</span><span :class="`urg-${detalhe.urgencia}`">{{ detalhe.urgencia ?? 'normal' }}</span></div>
              <div class="dg-item"><span class="dg-label">Autor</span>
                <span v-if="detalhe.anonimo" class="anon-badge">🔒 Anônimo</span>
                <span v-else>{{ detalhe.autor ?? '—' }}</span>
              </div>
              <div class="dg-item"><span class="dg-label">Data</span><span>{{ fmtData(detalhe.data) }}</span></div>
              <div class="dg-item"><span class="dg-label">Status</span>
                <select v-model="novoStatus" class="status-select">
                  <option v-for="s in statusOpts" :key="s.val" :value="s.val">{{ s.label }}</option>
                </select>
              </div>
            </div>
            <!-- Descrição -->
            <div class="detalhe-desc">
              <span class="dg-label">Descrição</span>
              <p>{{ detalhe.descricao }}</p>
            </div>
            <!-- Resposta -->
            <div class="form-group">
              <label class="dg-label">Resposta (visível ao autor identificado)</label>
              <textarea v-model="novaResposta" class="resposta-ta" rows="4"
                placeholder="Digite a resposta institucional..."></textarea>
            </div>
            <div class="modal-actions">
              <button class="btn-cancel" @click="fecharModal">Cancelar</button>
              <button class="btn-salvar" :disabled="salvando" @click="salvarResposta">
                {{ salvando ? 'Salvando...' : '✅ Salvar Resposta' }}
              </button>
            </div>
            <div v-if="feedbackMsg" class="feedback-msg" :class="feedbackOk ? 'msg-ok' : 'msg-err'">
              {{ feedbackMsg }}
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

const loaded     = ref(false)
const carregando = ref(true)
const lista      = ref([])
const busca      = ref('')
const filtroStatus = ref('')
const filtroTipo   = ref('')

const modalAberto  = ref(false)
const detalhe      = ref({})
const novoStatus   = ref('')
const novaResposta = ref('')
const salvando     = ref(false)
const feedbackMsg  = ref('')
const feedbackOk   = ref(true)

const tipos = [
  { val: 'reclamacao',  ico: '😤', nome: 'Reclamação',  cor: '#ef4444' },
  { val: 'sugestao',    ico: '💡', nome: 'Sugestão',    cor: '#f59e0b' },
  { val: 'elogio',      ico: '🌟', nome: 'Elogio',      cor: '#10b981' },
  { val: 'denuncia',    ico: '⚠️', nome: 'Denúncia',    cor: '#8b5cf6' },
  { val: 'outros',      ico: '📝', nome: 'Outros',      cor: '#64748b' },
]

const filtrosStatus = [
  { val: 'recebida',   ico: '📥', nome: 'Recebida',    cor: 'blue'   },
  { val: 'analise',    ico: '🔍', nome: 'Em Análise',  cor: 'yellow' },
  { val: 'respondida', ico: '✅', nome: 'Respondida',  cor: 'green'  },
  { val: 'arquivada',  ico: '📦', nome: 'Arquivada',   cor: 'gray'   },
]

const statusOpts = [
  { val: 'recebida',   label: '📥 Recebida'   },
  { val: 'analise',    label: '🔍 Em Análise' },
  { val: 'respondida', label: '✅ Respondida' },
  { val: 'arquivada',  label: '📦 Arquivada'  },
]

// ── Fetch ─────────────────────────────────────────────────────────
const fetchManifestacoes = async () => {
  carregando.value = true
  try {
    const { data } = await api.get('/api/v3/ouvidoria/admin')
    lista.value = data.manifestacoes ?? []
  } catch {
    lista.value = []
  } finally {
    carregando.value = false
  }
}

onMounted(async () => {
  await fetchManifestacoes()
  setTimeout(() => { loaded.value = true }, 80)
})

// ── Computed ──────────────────────────────────────────────────────
const itensFiltrados = computed(() => {
  let r = lista.value
  if (filtroStatus.value) r = r.filter(m => m.status === filtroStatus.value)
  if (filtroTipo.value)   r = r.filter(m => m.tipo   === filtroTipo.value)
  if (busca.value.trim()) {
    const b = busca.value.toLowerCase()
    r = r.filter(m =>
      (m.protocolo ?? '').toLowerCase().includes(b) ||
      (m.descricao  ?? '').toLowerCase().includes(b) ||
      (m.area       ?? '').toLowerCase().includes(b)
    )
  }
  return r
})

const total      = computed(() => lista.value.length)
const pendentes  = computed(() => lista.value.filter(m => m.status === 'recebida').length)
const emAnalise  = computed(() => lista.value.filter(m => m.status === 'analise').length)
const respondidas= computed(() => lista.value.filter(m => m.status === 'respondida').length)

// ── Modal ─────────────────────────────────────────────────────────
function abrirDetalhe(m) {
  detalhe.value    = { ...m }
  novoStatus.value = m.status
  novaResposta.value = m.resposta ?? ''
  feedbackMsg.value = ''
  modalAberto.value = true
}

function fecharModal() { modalAberto.value = false }

async function salvarResposta() {
  salvando.value = true
  feedbackMsg.value = ''
  try {
    await api.put(`/api/v3/ouvidoria/${detalhe.value.id}`, {
      status:   novoStatus.value,
      resposta: novaResposta.value,
    })
    // Atualiza item na lista localmente
    const idx = lista.value.findIndex(m => m.id === detalhe.value.id)
    if (idx !== -1) {
      lista.value[idx].status   = novoStatus.value
      lista.value[idx].resposta = novaResposta.value
    }
    feedbackOk.value  = true
    feedbackMsg.value = '✅ Resposta salva com sucesso!'
    setTimeout(fecharModal, 1500)
  } catch (e) {
    feedbackOk.value  = false
    feedbackMsg.value = `Erro: ${e.response?.data?.erro ?? e.message}`
  } finally {
    salvando.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────
const tipoCor  = t => tipos.find(x => x.val === t)?.cor  ?? '#64748b'
const tipoIco  = t => tipos.find(x => x.val === t)?.ico  ?? '📝'
const tipoNome = t => tipos.find(x => x.val === t)?.nome ?? t

const statusLabel = s => ({ recebida: '📥 Recebida', analise: '🔍 Em Análise',
  respondida: '✅ Respondida', arquivada: '📦 Arquivada' }[s] ?? s)

const fmtData = d => d ? new Date(d + 'T12:00:00').toLocaleDateString('pt-BR') : '—'
</script>

<style scoped>
.oadm-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* Hero */
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #0d1a3a 55%, #0a1a0a 100%);
  opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 220px; height: 220px; background: #10b981; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6ee7b7; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-kpis { display: flex; gap: 10px; flex-wrap: wrap; }
.hk { display: flex; flex-direction: column; align-items: center; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 10px 18px; min-width: 70px; }
.hk-val { font-size: 22px; font-weight: 900; color: #fff; }
.hk-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-top: 2px; }
.hk-blue   .hk-val { color: #60a5fa; }
.hk-yellow .hk-val { color: #fbbf24; }
.hk-orange .hk-val { color: #f97316; }
.hk-green  .hk-val { color: #34d399; }

/* Filtros */
.filtros-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; background: #fff;
  border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px;
  opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.07s; }
.filtros-bar.loaded { opacity: 1; transform: none; }
.search-wrap { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; flex: 1; min-width: 200px; }
.search-ico { font-size: 14px; }
.search-input { border: none; background: transparent; outline: none; font-size: 13px; color: #1e293b; width: 100%; font-family: inherit; }
.filtros-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.fchip { padding: 6px 12px; border-radius: 99px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.fchip.active, .fchip-blue.active   { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.fchip-yellow.active { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
.fchip-green.active  { background: #f0fdf4; border-color: #10b981; color: #065f46; }
.fchip-gray.active   { background: #f8fafc; border-color: #94a3b8; color: #475569; }

/* Lista */
.lista-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden;
  opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.lista-wrap.loaded { opacity: 1; transform: none; }
.state-loading, .state-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 12px; font-size: 28px; }
.state-loading p, .state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }
.spinner { width: 28px; height: 28px; border: 3px solid #e2e8f0; border-top-color: #10b981; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.ouv-table { display: flex; flex-direction: column; }
.ouv-thead { display: grid; grid-template-columns: 130px 130px 1fr 1fr 100px 130px 140px;
  gap: 0 12px; padding: 10px 18px; background: #f8fafc; border-bottom: 1px solid #f1f5f9;
  font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.06em; }
.ouv-row { display: grid; grid-template-columns: 130px 130px 1fr 1fr 100px 130px 140px;
  gap: 0 12px; padding: 12px 18px; border-bottom: 1px solid #f8fafc; align-items: center;
  animation: rowIn 0.3s cubic-bezier(0.22,1,0.36,1) calc(var(--ri) * 30ms) both; }
@keyframes rowIn { from { opacity: 0; transform: translateX(8px); } to { opacity: 1; transform: none; } }
.ouv-row:hover { background: #fafafa; }
.ouv-proto { font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace; }
.ouv-tipo-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 8px; border: 1px solid; white-space: nowrap; }
.ouv-area { font-size: 12px; color: #475569; }
.ouv-autor { font-size: 12px; color: #1e293b; font-weight: 600; }
.ouv-data { font-size: 12px; color: #64748b; }
.anon-badge { display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; }

/* Status chips */
.ouv-status-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 8px; white-space: nowrap; }
.sc-recebida   { background: #eff6ff; color: #1d4ed8; }
.sc-analise    { background: #fffbeb; color: #92400e; }
.sc-respondida { background: #f0fdf4; color: #065f46; }
.sc-arquivada  { background: #f8fafc; color: #64748b; }

.btn-detalhe { padding: 7px 14px; border-radius: 10px; border: 1.5px solid #10b981; background: #f0fdf4; color: #065f46; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.btn-detalhe:hover { background: #10b981; color: #fff; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: flex-start; justify-content: space-between; padding: 20px 24px 14px; border-bottom: 1px solid #f1f5f9; gap: 12px; }
.modal-hdr h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 4px 0 0; }
.modal-proto { display: inline-block; font-size: 10px; font-weight: 700; background: #f3f0ff; color: #7c3aed; padding: 2px 9px; border-radius: 6px; font-family: monospace; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; color: #64748b; font-size: 14px; flex-shrink: 0; }
.modal-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.detalhe-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.dg-item { display: flex; flex-direction: column; gap: 4px; }
.dg-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.dg-item > span:not(.dg-label) { font-size: 13px; font-weight: 600; color: #1e293b; }
.urg-alta   { color: #ef4444 !important; font-weight: 800 !important; }
.urg-media  { color: #f59e0b !important; font-weight: 700 !important; }
.urg-normal { color: #64748b !important; }
.detalhe-desc { display: flex; flex-direction: column; gap: 6px; background: #f8fafc; border-radius: 12px; padding: 14px 16px; }
.detalhe-desc p { margin: 0; font-size: 13px; color: #475569; line-height: 1.6; }
.status-select { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; font-size: 12px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; cursor: pointer; }
.resposta-ta { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; resize: vertical; min-height: 90px; width: 100%; box-sizing: border-box; }
.resposta-ta:focus { border-color: #10b981; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.modal-actions { display: flex; gap: 10px; }
.btn-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.btn-salvar { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
.btn-salvar:disabled { opacity: 0.5; cursor: not-allowed; }
.feedback-msg { padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; text-align: center; }
.msg-ok  { background: #f0fdf4; color: #065f46; }
.msg-err { background: #fef2f2; color: #b91c1c; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
@media (max-width: 900px) {
  .ouv-thead, .ouv-row { grid-template-columns: 1fr 1fr 1fr; }
  .ouv-thead span:nth-child(n+4), .ouv-row span:nth-child(n+4) { display: none; }
  .detalhe-grid { grid-template-columns: 1fr 1fr; }
}
</style>
