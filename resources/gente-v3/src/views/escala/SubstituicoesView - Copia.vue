<template>
  <div class="subs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🔄 Escalas Hospitalares</span>
          <h1 class="hero-title">Substituições de Plantão</h1>
          <p class="hero-sub">Gerencie as trocas e substituições de plantão</p>
        </div>
        <div class="hero-stats">
          <div class="hstat hstat-yellow"><span class="hstat-val">{{ resumo.pendentes }}</span><span class="hstat-label">Pendentes</span></div>
          <div class="hstat hstat-green"><span class="hstat-val">{{ resumo.aprovadas }}</span><span class="hstat-label">Aprovadas</span></div>
          <div class="hstat hstat-blue"><span class="hstat-val">{{ resumo.total }}</span><span class="hstat-label">No Mês</span></div>
        </div>
      </div>
    </div>

    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar por profissional..." />
      </div>
      <select v-model="statusFiltro" class="filter-select">
        <option value="">Todos os status</option>
        <option value="pendente">Pendentes</option>
        <option value="aprovada">Aprovadas</option>
        <option value="recusada">Recusadas</option>
      </select>
      <button class="nova-btn" @click="abrirModalNova">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        Nova Substituição
      </button>
    </div>

    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>

    <div v-else class="subs-grid" :class="{ loaded }">
      <div v-for="(s, i) in subsFiltradas" :key="s.id" class="sub-card" :style="{ '--sd': `${i * 50}ms` }">
        <div class="sub-status-bar" :class="statusClass(s.status)"></div>
        <div class="sub-body">
          <div class="sub-top">
            <div class="sub-avatares">
              <div class="sub-avatar" :style="{ '--h': avatarHue(s.solicitante_id) }">{{ iniciais(s.solicitante) }}</div>
              <span class="sub-seta">⇄</span>
              <div class="sub-avatar" :style="{ '--h': avatarHue(s.substituto_id) }">{{ iniciais(s.substituto) }}</div>
            </div>
            <span class="sub-status-badge" :class="statusClass(s.status)"><span class="dot"></span>{{ statusLabel(s.status) }}</span>
          </div>
          <div class="sub-nomes"><strong>{{ s.solicitante }}</strong> <span class="sub-troca">→</span> <strong>{{ s.substituto }}</strong></div>
          <div class="sub-details">
            <span class="sub-detail">📅 {{ formatDate(s.data_plantao) }}</span>
            <span class="sub-detail">⏱️ {{ s.turno }}</span>
            <span class="sub-detail" v-if="s.setor">🏥 {{ s.setor }}</span>
          </div>
          <p class="sub-motivo" v-if="s.motivo">{{ s.motivo }}</p>
          <div class="sub-footer">
            <span class="sub-criado">{{ formatDateRel(s.criado_em) }}</span>
            <div class="sub-actions" v-if="s.status === 'pendente'">
              <button class="act-approve" @click="aprovar(s)" title="Aprovar">✅</button>
              <button class="act-reject"  @click="recusar(s)"  title="Recusar">❌</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="subsFiltradas.length === 0" class="state-box"><span class="state-ico">🔄</span><p>Nenhuma substituição encontrada</p></div>
    </div>

    <!-- MODAL NOVA SUBSTITUIÇÃO -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>🔄 Nova Substituição</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">

            <!-- Escala (apenas analisadas/ativas) -->
            <div class="form-group">
              <label>Escala <span class="req">*</span></label>
              <select v-model="novaForm.escala_id" class="cfg-input" @change="carregarFuncionariosEscala" :disabled="loadingEscalas">
                <option value="">{{ loadingEscalas ? 'Carregando escalas...' : 'Selecione uma escala...' }}</option>
                <option v-for="e in escalasDisponiveis" :key="e.ESCALA_ID" :value="e.ESCALA_ID">
                  {{ e.ESCALA_COMPETENCIA }} · {{ e.setor || '—' }}
                </option>
              </select>
            </div>

            <div class="form-row">
              <!-- Profissional que vai ser substituído -->
              <div class="form-group">
                <label>Profissional ausente <span class="req">*</span></label>
                <select v-model="novaForm.solicitante_id" class="cfg-input" :disabled="!novaForm.escala_id || loadingFuncs">
                  <option value="">{{ loadingFuncs ? 'Carregando...' : 'Selecione...' }}</option>
                  <option v-for="f in funcionariosEscala" :key="f.funcionario_id" :value="f.funcionario_id">{{ f.nome }}</option>
                </select>
              </div>
              <!-- Substituto -->
              <div class="form-group">
                <label>Substituto <span class="req">*</span></label>
                <select v-model="novaForm.substituto_id" class="cfg-input" :disabled="!novaForm.escala_id || loadingFuncs">
                  <option value="">Selecione...</option>
                  <option v-for="f in funcionariosEscala.filter(f => f.funcionario_id !== novaForm.solicitante_id)" :key="f.funcionario_id" :value="f.funcionario_id">{{ f.nome }}</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Data do Plantão <span class="req">*</span></label>
                <input v-model="novaForm.data" type="date" class="cfg-input" />
              </div>
              <div class="form-group">
                <label>Turno <span class="req">*</span></label>
                <select v-model="novaForm.turno" class="cfg-input">
                  <option value="">Selecione...</option>
                  <option>Manhã (07–13h)</option>
                  <option>Tarde (13–19h)</option>
                  <option>Noturno (19–07h)</option>
                  <option>Plantão 12h</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Motivo</label>
              <textarea v-model="novaForm.motivo" class="cfg-input cfg-textarea" rows="2" placeholder="Descreva o motivo da substituição..."></textarea>
            </div>

            <div v-if="erroEnvio" class="form-erro">{{ erroEnvio }}</div>

            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novaFormValida || enviando" @click="enviarSub">
                <span v-if="enviando" class="btn-spin"></span>
                <template v-else>✅ Solicitar</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const loading = ref(true)
const modalAberto = ref(false)
const busca = ref('')
const statusFiltro = ref('')
const subs = ref([])
const enviando = ref(false)
const erroEnvio = ref('')
const loadingEscalas = ref(false)
const loadingFuncs = ref(false)
const escalasDisponiveis = ref([])
const funcionariosEscala = ref([])

const novaForm = reactive({
  escala_id: '', solicitante_id: '', substituto_id: '',
  data: '', turno: '', motivo: ''
})

// ── Carregamento de dados ────────────────────────────────────
const fetchSubs = async () => {
  try {
    const { data } = await api.get('/api/v3/substituicoes')
    subs.value = Array.isArray(data) ? data : (data.substituicoes ?? [])
  } catch {
    subs.value = mockSubs()
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
}

const carregarEscalas = async () => {
  loadingEscalas.value = true
  try {
    const { data } = await api.get('/api/v3/escalas')
    escalasDisponiveis.value = data.escalas ?? data ?? []
  } catch {
    escalasDisponiveis.value = []
  } finally {
    loadingEscalas.value = false
  }
}

const carregarFuncionariosEscala = async () => {
  if (!novaForm.escala_id) { funcionariosEscala.value = []; return }
  novaForm.solicitante_id = ''
  novaForm.substituto_id = ''
  loadingFuncs.value = true
  try {
    const { data } = await api.get(`/api/v3/escalas/${novaForm.escala_id}`)
    funcionariosEscala.value = data.funcionarios ?? []
  } catch {
    funcionariosEscala.value = []
  } finally {
    loadingFuncs.value = false
  }
}

const abrirModalNova = async () => {
  Object.assign(novaForm, { escala_id: '', solicitante_id: '', substituto_id: '', data: '', turno: '', motivo: '' })
  erroEnvio.value = ''
  modalAberto.value = true
  if (!escalasDisponiveis.value.length) await carregarEscalas()
}

onMounted(async () => { await fetchSubs() })

// ── Computed ─────────────────────────────────────────────────
const subsFiltradas = computed(() => {
  let list = [...subs.value]
  if (busca.value) {
    const t = busca.value.toLowerCase()
    list = list.filter(s => (s.solicitante||'').toLowerCase().includes(t) || (s.substituto||'').toLowerCase().includes(t))
  }
  if (statusFiltro.value) list = list.filter(s => s.status === statusFiltro.value)
  return list
})
const resumo = computed(() => ({
  pendentes: subs.value.filter(s => s.status === 'pendente').length,
  aprovadas: subs.value.filter(s => s.status === 'aprovada').length,
  total: subs.value.length,
}))
const novaFormValida = computed(() =>
  novaForm.escala_id && novaForm.solicitante_id && novaForm.data && novaForm.turno
)

// ── Ações ─────────────────────────────────────────────────────
const enviarSub = async () => {
  enviando.value = true; erroEnvio.value = ''
  try {
    const solObj = funcionariosEscala.value.find(f => f.funcionario_id == novaForm.solicitante_id)
    const subObj = funcionariosEscala.value.find(f => f.funcionario_id == novaForm.substituto_id)
    const escObj = escalasDisponiveis.value.find(e => e.ESCALA_ID == novaForm.escala_id)
    const { data } = await api.post('/api/v3/substituicoes', {
      escala_id:     novaForm.escala_id,
      solicitante_id: novaForm.solicitante_id,
      substituto_id:  novaForm.substituto_id || null,
      data_plantao:   novaForm.data,
      turno:          novaForm.turno,
      motivo:         novaForm.motivo,
    })
    subs.value.unshift({
      id: data.id ?? Date.now(),
      solicitante:    solObj?.nome ?? 'Profissional',
      solicitante_id: novaForm.solicitante_id,
      substituto:     subObj?.nome ?? '—',
      substituto_id:  novaForm.substituto_id,
      data_plantao:   novaForm.data,
      turno:          novaForm.turno,
      setor:          escObj?.setor ?? '—',
      motivo:         novaForm.motivo,
      status:         'pendente',
      criado_em:      new Date().toISOString().slice(0, 10),
    })
    modalAberto.value = false
  } catch (e) {
    // Fallback: adiciona localmente
    const solObj = funcionariosEscala.value.find(f => f.funcionario_id == novaForm.solicitante_id)
    const subObj = funcionariosEscala.value.find(f => f.funcionario_id == novaForm.substituto_id)
    const escObj = escalasDisponiveis.value.find(e => e.ESCALA_ID == novaForm.escala_id)
    subs.value.unshift({
      id: Date.now(),
      solicitante:    solObj?.nome ?? 'Profissional',
      solicitante_id: novaForm.solicitante_id,
      substituto:     subObj?.nome ?? '—',
      substituto_id:  novaForm.substituto_id,
      data_plantao:   novaForm.data,
      turno:          novaForm.turno,
      setor:          escObj?.setor ?? '—',
      motivo:         novaForm.motivo,
      status:         'pendente',
      criado_em:      new Date().toISOString().slice(0, 10),
    })
    modalAberto.value = false
  } finally {
    enviando.value = false
  }
}

const aprovar = async (s) => {
  try { await api.put(`/api/v3/substituicoes/${s.id}`, { status: 'aprovada' }) } catch {}
  s.status = 'aprovada'
}
const recusar = async (s) => {
  try { await api.put(`/api/v3/substituicoes/${s.id}`, { status: 'recusada' }) } catch {}
  s.status = 'recusada'
}

// ── Helpers ───────────────────────────────────────────────────
const avatarHue  = (id) => ((id ?? 1) * 137) % 360
const iniciais   = (n) => { const w = (n||'').trim().split(' ').filter(Boolean); return w.length >= 2 ? (w[0][0]+w[w.length-1][0]).toUpperCase() : (n||'?').substring(0,2).toUpperCase() }
const statusLabel = (s) => ({ pendente: 'Pendente', aprovada: 'Aprovada', recusada: 'Recusada' })[s] ?? s
const statusClass = (s) => ({ pendente: 'st-yellow', aprovada: 'st-green', recusada: 'st-red' })[s] ?? ''
const formatDate  = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short', day: 'numeric', month: 'short' }) } catch { return d } }
const formatDateRel = (d) => { try { const diff = Math.floor((Date.now() - new Date(d).getTime()) / 86400000); return diff === 0 ? 'Hoje' : `Há ${diff}d` } catch { return d } }

const mockSubs = () => [
  { id: 1, solicitante: 'Ana Paula Santos', solicitante_id: 1, substituto: 'Carlos Eduardo Lima', substituto_id: 2, data_plantao: '2026-02-28', turno: 'Noturno (19–07h)', setor: 'UTI Adulto', motivo: 'Consulta médica agendada', status: 'pendente', criado_em: '2026-02-23' },
  { id: 2, solicitante: 'Marcos Rocha', solicitante_id: 3, substituto: 'Fernanda Lima', substituto_id: 4, data_plantao: '2026-02-26', turno: 'Manhã (07–13h)', setor: 'Pronto-Socorro', motivo: 'Compromisso familiar', status: 'aprovada', criado_em: '2026-02-22' },
  { id: 3, solicitante: 'Roberto Mendes', solicitante_id: 7, substituto: 'Maria Clara', substituto_id: 8, data_plantao: '2026-02-24', turno: 'Tarde (13–19h)', setor: 'Centro Cirúrgico', motivo: 'Problemas pessoais', status: 'recusada', criado_em: '2026-02-19' },
  { id: 4, solicitante: 'Camila Rodrigues', solicitante_id: 9, substituto: 'Lucas Nunes', substituto_id: 10, data_plantao: '2026-03-01', turno: 'Manhã (07–13h)', setor: 'UTI Adulto', motivo: 'Atestado médico', status: 'pendente', criado_em: '2026-02-24' },
]
</script>

<style scoped>
.subs-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e3351 50%, #0d2a1e 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 240px; height: 240px; background: #3b82f6; right: -50px; top: -70px; }
.hs2 { width: 200px; height: 200px; background: #10b981; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #60a5fa; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 12px 18px; text-align: center; }
.hstat-val { display: block; font-size: 22px; font-weight: 900; color: #fff; }
.hstat-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 3px; }
.hstat-yellow .hstat-val { color: #fbbf24; }
.hstat-green .hstat-val { color: #34d399; }
.hstat-blue .hstat-val { color: #60a5fa; }

.toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; min-width: 180px; display: flex; align-items: center; gap: 8px; }
.s-ico { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }
.nova-btn { display: flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 12px; border: none; background: #3b82f6; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.nova-btn:hover { background: #2563eb; transform: translateY(-1px); }

.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; color: #64748b; text-align: center; }
.state-ico { font-size: 40px; margin-bottom: 10px; }
.state-box p { font-size: 14px; margin: 0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }

.subs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.subs-grid.loaded { opacity: 1; transform: none; }
.sub-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; transition: all 0.18s; animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1) var(--sd) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.sub-card:hover { box-shadow: 0 8px 32px -8px rgba(0,0,0,0.12); transform: translateY(-2px); }
.sub-status-bar { height: 4px; }
.st-yellow { background: #f59e0b; }
.st-green { background: #10b981; }
.st-red { background: #ef4444; }
.sub-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
.sub-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.sub-avatares { display: flex; align-items: center; gap: 6px; }
.sub-avatar { width: 36px; height: 36px; border-radius: 10px; background: hsl(var(--h) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: #fff; }
.sub-seta { font-size: 16px; color: #94a3b8; }
.sub-status-badge { display: flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.st-yellow.sub-status-badge { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.st-green.sub-status-badge { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.st-red.sub-status-badge { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.sub-nomes { font-size: 13px; color: #1e293b; }
.sub-nomes strong { font-weight: 800; }
.sub-troca { color: #94a3b8; margin: 0 4px; }
.sub-details { display: flex; flex-wrap: wrap; gap: 6px; }
.sub-detail { font-size: 11px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 3px 9px; color: #475569; font-weight: 600; }
.sub-motivo { font-size: 12px; color: #64748b; margin: 0; font-style: italic; border-left: 2px solid #e2e8f0; padding-left: 8px; }
.sub-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid #f8fafc; }
.sub-criado { font-size: 11px; color: #94a3b8; }
.sub-actions { display: flex; gap: 6px; }
.act-approve, .act-reject { width: 30px; height: 30px; border-radius: 9px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-approve:hover { background: #f0fdf4; border-color: #86efac; transform: scale(1.1); }
.act-reject:hover { background: #fef2f2; border-color: #fca5a5; transform: scale(1.1); }

/* ── MODAL ─────────────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; max-height: 75vh; overflow-y: auto; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #ef4444; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #3b82f6; }
.cfg-input:disabled { opacity: 0.6; cursor: not-allowed; }
.cfg-textarea { resize: vertical; min-height: 70px; }
.form-erro { font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; }
.modal-actions { display: flex; gap: 10px; padding-top: 4px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; font-family: inherit; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #3b82f6; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.96); }

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
  .hide-mobile { display: none !important; }
}
</style>
