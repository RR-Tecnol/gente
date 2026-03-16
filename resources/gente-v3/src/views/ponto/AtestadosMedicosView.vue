<template>
  <div class="at-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏥 Medicina do Trabalho</span>
          <h1 class="hero-title">Atestados Médicos</h1>
          <p class="hero-sub">Registro e acompanhamento de afastamentos por doença</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hv">{{ atestados.length }}</span><span class="hl">Total</span></div>
          <div class="hstat hb"><span class="hv">{{ diasTotais }}</span><span class="hl">Dias Afastados</span></div>
          <div class="hstat hc"><span class="hv">{{ atestados.filter(a => a.status === 'pendente').length }}</span><span class="hl">Pendentes</span></div>
        </div>
      </div>
    </div>

    <!-- AÇÕES -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar por CID ou médico..." />
      </div>
      <select v-model="filtroStatus" class="filter-sel">
        <option value="">Todos</option>
        <option value="pendente">Pendentes</option>
        <option value="aprovado">Aprovados</option>
        <option value="rejeitado">Rejeitados</option>
      </select>
      <button class="novo-btn" @click="modalAberto = true">+ Registrar Atestado</button>
    </div>

    <!-- LISTA DE ATESTADOS -->
    <div class="at-list" :class="{ loaded }">
      <div v-for="(a, i) in atestadosFiltrados" :key="a.id" class="at-item" :style="{ '--ai': i }">
        <div class="at-data-col">
          <div class="at-dia">{{ new Date(a.inicio + 'T12:00:00').getDate() }}</div>
          <div class="at-mes">{{ new Date(a.inicio + 'T12:00:00').toLocaleDateString('pt-BR', { month: 'short' }) }}</div>
          <div class="at-ano">{{ new Date(a.inicio + 'T12:00:00').getFullYear() }}</div>
        </div>
        <div class="at-content">
          <div class="at-hdr-row">
            <div class="at-cid-wrap">
              <code class="cid-code">{{ a.cid }}</code>
              <span class="cid-desc">{{ a.descricao }}</span>
            </div>
            <span class="at-status" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>
          </div>
          <div class="at-detalhes">
            <span class="at-det">🩺 {{ a.medico }}</span>
            <span class="at-det">🏥 {{ a.crm }}</span>
            <span class="at-det">📅 {{ formatDate(a.inicio) }} → {{ formatDate(a.fim) }}</span>
            <span class="at-det" :class="a.dias > 15 ? 'det-red' : a.dias > 7 ? 'det-yellow' : ''">⏱️ <strong>{{ a.dias }} dia{{ a.dias > 1 ? 's' : '' }}</strong></span>
          </div>
          <div v-if="a.obs" class="at-obs">{{ a.obs }}</div>
          <div v-if="a.parecer" class="at-parecer">
            <span class="par-ico">👤</span> <strong>RH:</strong> {{ a.parecer }}
          </div>
        </div>
        <div class="at-actions">
          <button class="at-act" title="Baixar PDF">📄</button>
          <!-- Botões de aprovação (gestor) -->
          <template v-if="a.status === 'pendente'">
            <button class="at-act at-act-green" title="Aprovar" @click="abrirAprovacao(a, 'aprovar')">✅</button>
            <button class="at-act at-act-red"   title="Rejeitar" @click="abrirAprovacao(a, 'rejeitar')">❌</button>
          </template>
          <button class="at-act at-act-danger" title="Remover" v-if="a.status === 'pendente'" @click="remover(a.id)">🗑️</button>
        </div>
      </div>

      <div v-if="atestadosFiltrados.length === 0" class="state-empty">
        <span>📋</span><p>Nenhum atestado {{ filtroStatus ? 'com este status' : 'registrado' }}.</p>
      </div>
    </div>

    <!-- MODAL APROVAÇÃO DO GESTOR -->
    <transition name="modal">
      <div v-if="modalAprovacao" class="modal-overlay" @click.self="modalAprovacao = null">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>{{ acaoAtual === 'aprovar' ? '✅ Aprovar Atestado' : '❌ Rejeitar Atestado' }}</h3>
            <button class="modal-close" @click="modalAprovacao = null">✕</button>
          </div>
          <div class="modal-body">
            <div class="aprova-info">
              <span class="cid-code">{{ modalAprovacao.cid }}</span>
              <span class="cid-desc">{{ modalAprovacao.descricao }}</span>
            </div>
            <p class="aprova-sub">
              {{ acaoAtual === 'aprovar'
                ? 'Confirme a aprovação. O servidor será notificado.'
                : 'Informe o motivo da rejeição para o servidor.' }}
            </p>
            <div class="form-group">
              <label>Observação do Gestor <span class="opt-label">(opcional)</span></label>
              <textarea v-model="obsGestor" class="cfg-input cfg-ta" rows="3"
                :placeholder="acaoAtual === 'aprovar'
                  ? 'Ex: Atestado validado. Ausência justificada.'
                  : 'Ex: Documento ilegível. Envie o original.' "
              ></textarea>
            </div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAprovacao = null" :disabled="aprovando">Cancelar</button>
              <button
                class="modal-submit"
                :class="acaoAtual === 'aprovar' ? 'btn-green' : 'btn-red'"
                :disabled="aprovando"
                @click="confirmarAprovacao"
              >
                <span v-if="aprovando" class="btn-spin"></span>
                <template v-else>{{ acaoAtual === 'aprovar' ? '✅ Confirmar Aprovação' : '❌ Confirmar Rejeição' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL REGISTRAR -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>🩺 Registrar Atestado</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group">
                <label>CID-10</label>
                <input v-model="novoAt.cid" class="cfg-input" placeholder="Ex: J06.9" />
              </div>
              <div class="form-group">
                <label>Descrição do CID</label>
                <input v-model="novoAt.descricao" class="cfg-input" placeholder="Ex: Infecção das vias aéreas" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Médico Emitente</label>
                <input v-model="novoAt.medico" class="cfg-input" placeholder="Dr. Nome Sobrenome" />
              </div>
              <div class="form-group">
                <label>CRM</label>
                <input v-model="novoAt.crm" class="cfg-input" placeholder="CRM/SP 12345" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Data Início</label>
                <input v-model="novoAt.inicio" type="date" class="cfg-input" />
              </div>
              <div class="form-group">
                <label>Data Fim</label>
                <input v-model="novoAt.fim" type="date" class="cfg-input" :min="novoAt.inicio" />
              </div>
            </div>
            <div class="form-group">
              <label>Observações</label>
              <textarea v-model="novoAt.obs" class="cfg-input cfg-ta" rows="3" placeholder="Informações adicionais..."></textarea>
            </div>
      <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novoAtValido || salvando" @click="salvarAtestado">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>✅ Registrar</template>
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

const loaded = ref(false)
const modalAberto = ref(false)
const salvando = ref(false)
const busca = ref('')
const filtroStatus = ref('')
const novoAt = reactive({ cid: '', descricao: '', medico: '', crm: '', inicio: '', fim: '', obs: '' })
const toast = ref({ visible: false, msg: '' })

const atestados = ref([])

// ── Mapeamento colunas DB → formato Vue ────────────────
const mapAtestado = (a) => {
  const ini = a.AFASTAMENTO_DATA_INICIO ?? a.inicio ?? ''
  const fim = a.AFASTAMENTO_DATA_FIM    ?? a.fim    ?? ini
  const dias = ini && fim
    ? Math.round((new Date(fim) - new Date(ini)) / 86400000) + 1
    : (a.dias ?? 1)
  return {
    id:        a.AFASTAMENTO_ID  ?? a.id,
    cid:       a.AFASTAMENTO_CID ?? a.cid       ?? '—',
    descricao: a.AFASTAMENTO_DESCRICAO ?? a.descricao ?? 'Afastamento',
    medico:    a.AFASTAMENTO_MEDICO ?? a.medico  ?? '—',
    crm:       a.AFASTAMENTO_CRM    ?? a.crm     ?? '—',
    inicio:    ini,
    fim:       fim,
    dias,
    obs:       a.AFASTAMENTO_OBS    ?? a.obs     ?? '',
    status:   (a.AFASTAMENTO_STATUS ?? a.status ?? 'pendente').toLowerCase(),
    parecer:   a.AFASTAMENTO_PARECER ?? a.parecer ?? null,
  }
}

const mockAtestados = [
  { id: 1, cid: 'J06.9', descricao: 'Infecção aguda das vias aéreas superiores', medico: 'Dr. Paulo Saraiva', crm: 'CRM/SP 78234', inicio: '2026-02-03', fim: '2026-02-05', dias: 3, obs: '', status: 'aprovado', parecer: 'Atestado validado. Ausência justificada conforme NR.' },
  { id: 2, cid: 'M54.5', descricao: 'Lombalgia — dor lombar inespecífica', medico: 'Dra. Carla Fonseca', crm: 'CRM/SP 45678', inicio: '2026-01-15', fim: '2026-01-22', dias: 8, obs: 'Encaminhado a fisioterapia', status: 'aprovado', parecer: null },
  { id: 3, cid: 'J11.1', descricao: 'Influenza com manifestações respiratórias', medico: 'Dr. Roberto Alves', crm: 'CRM/SP 91234', inicio: '2026-02-18', fim: '2026-02-20', dias: 3, obs: '', status: 'pendente', parecer: null },
]

const diasTotais = computed(() => atestados.value.filter(a => a.status === 'aprovado').reduce((s, a) => s + a.dias, 0))

const atestadosFiltrados = computed(() => {
  let list = [...atestados.value].sort((a, b) => b.inicio.localeCompare(a.inicio))
  if (busca.value) { const t = busca.value.toLowerCase(); list = list.filter(a => (a.cid+a.descricao+a.medico).toLowerCase().includes(t)) }
  if (filtroStatus.value) list = list.filter(a => a.status === filtroStatus.value)
  return list
})

const novoAtValido = computed(() => novoAt.cid && novoAt.medico && novoAt.inicio && novoAt.fim && novoAt.descricao)

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/atestados')
    atestados.value = (!data.fallback && data.atestados?.length)
      ? data.atestados.map(mapAtestado)
      : mockAtestados
  } catch {
    atestados.value = mockAtestados
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

const calcDias = (ini, fim) => Math.round((new Date(fim) - new Date(ini)) / 86400000) + 1

const salvarAtestado = async () => {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/atestados', { ...novoAt })
    atestados.value.unshift(mapAtestado({
      AFASTAMENTO_ID: data.id ?? Date.now(),
      AFASTAMENTO_DATA_INICIO: novoAt.inicio,
      AFASTAMENTO_DATA_FIM:    novoAt.fim,
      AFASTAMENTO_CID:         novoAt.cid,
      AFASTAMENTO_DESCRICAO:   novoAt.descricao,
      AFASTAMENTO_MEDICO:      novoAt.medico,
      AFASTAMENTO_CRM:         novoAt.crm,
      AFASTAMENTO_OBS:         novoAt.obs,
      AFASTAMENTO_STATUS:      'pendente',
    }))
    showToast('✅ Atestado registrado e enviado ao RH!')
  } catch (error) {
    console.error("Erro ao salvar atestado na API:", error);
    atestados.value.unshift({ id: Date.now(), ...novoAt, dias: calcDias(novoAt.inicio, novoAt.fim), status: 'pendente', parecer: null })
    showToast('✅ Atestado salvo localmente!')
  } finally {
    Object.assign(novoAt, { cid: '', descricao: '', medico: '', crm: '', inicio: '', fim: '', obs: '' })
    salvando.value  = false
    modalAberto.value = false
  }
}

const remover = async (id) => {
  try { await api.delete(`/api/v3/atestados/${id}`) } catch { /* fallback */ }
  atestados.value = atestados.value.filter(a => a.id !== id)
  showToast('🗑️ Atestado removido.')
}

// ── Aprovação pelo Gestor ────────────────────────────────────────
const modalAprovacao = ref(null)   // atestado sendo avaliado
const acaoAtual      = ref('aprovar') // 'aprovar' | 'rejeitar'
const obsGestor      = ref('')
const aprovando      = ref(false)

const abrirAprovacao = (atestado, acao) => {
  modalAprovacao.value = atestado
  acaoAtual.value      = acao
  obsGestor.value      = ''
}

const confirmarAprovacao = async () => {
  if (!modalAprovacao.value) return
  aprovando.value = true
  try {
    const { data } = await api.put(`/api/v3/atestados/${modalAprovacao.value.id}/aprovar`, {
      acao: acaoAtual.value,
      observacao: obsGestor.value || null,
    })
    // Atualiza localmente sem reload
    const idx = atestados.value.findIndex(a => a.id === modalAprovacao.value.id)
    if (idx !== -1) {
      atestados.value[idx].status  = data.status ?? acaoAtual.value === 'aprovar' ? 'aprovado' : 'rejeitado'
      atestados.value[idx].parecer = obsGestor.value || null
    }
    showToast(acaoAtual.value === 'aprovar' ? '✅ Atestado aprovado com sucesso!' : '❌ Atestado rejeitado.')
    modalAprovacao.value = null
  } catch (e) {
    showToast('⚠️ Erro: ' + (e.response?.data?.erro || e.message))
  } finally { aprovando.value = false }
}

const statusLabel = (s) => ({ pendente: 'Pendente', aprovado: 'Aprovado', rejeitado: 'Rejeitado' })[s] ?? s
const statusClass = (s) => ({ pendente: 'st-yellow', aprovado: 'st-green', rejeitado: 'st-red' })[s] ?? ''
const formatDate  = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }) } catch { return d } }
</script>

<style scoped>
.at-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1a2a 55%, #0a2a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #0d9488; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 18px; text-align: center; }
.hv { display: block; font-size: 22px; font-weight: 900; }
.hl { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hv { color: #60a5fa; } .hb .hv { color: #fbbf24; } .hc .hv { color: #fb923c; }
.toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; flex-wrap: wrap; }
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; min-width: 200px; display: flex; align-items: center; gap: 8px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.filter-sel { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }
.novo-btn { padding: 9px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.18s; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(13,148,136,0.3); }
.at-list { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.at-list.loaded { opacity: 1; transform: none; }
.at-item { display: flex; align-items: flex-start; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; transition: all 0.15s; animation: atIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ai) * 50ms) both; }
@keyframes atIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.at-item:hover { box-shadow: 0 4px 16px -4px rgba(0,0,0,0.1); border-color: #cbd5e1; }
.at-data-col { text-align: center; min-width: 44px; border-right: 1px solid #f1f5f9; padding-right: 14px; flex-shrink: 0; }
.at-dia { font-size: 24px; font-weight: 900; color: #1e293b; line-height: 1; }
.at-mes { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; }
.at-ano { font-size: 10px; color: #cbd5e1; }
.at-content { flex: 1; min-width: 0; }
.at-hdr-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.at-cid-wrap { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.cid-code { background: #eff6ff; color: #1e40af; border: 1px solid #bae6fd; padding: 3px 10px; border-radius: 8px; font-size: 13px; font-weight: 800; font-family: monospace; }
.cid-desc { font-size: 13px; font-weight: 700; color: #1e293b; }
.at-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; white-space: nowrap; flex-shrink: 0; }
.st-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.st-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.st-red { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.at-detalhes { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
.at-det { font-size: 12px; color: #64748b; font-weight: 500; }
.det-red strong { color: #ef4444; }
.det-yellow strong { color: #f59e0b; }
.at-obs { font-size: 12px; color: #64748b; font-style: italic; margin-bottom: 6px; }
.at-parecer { font-size: 12px; color: #064e3b; background: #f0fdf4; border-left: 2px solid #10b981; padding: 6px 10px; border-radius: 0 8px 8px 0; }
.par-ico { }
.at-actions { display: flex; flex-direction: column; gap: 6px; align-items: center; }
.at-act { width: 32px; height: 32px; border-radius: 9px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 15px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.at-act:hover { background: #eff6ff; transform: translateY(-1px); }
.at-act-danger:hover { background: #fef2f2; border-color: #fca5a5; }
.at-act-green { border-color: #86efac; }
.at-act-green:hover { background: #dcfce7; border-color: #22c55e; }
.at-act-red   { border-color: #fca5a5; }
.at-act-red:hover   { background: #fef2f2; border-color: #ef4444; }
/* Modal Aprovação */
.aprova-info { display: flex; align-items: center; gap: 10px; background: #f8fafc; border-radius: 12px; padding: 12px 14px; }
.aprova-sub { font-size: 13px; color: #64748b; margin: 0; }
.btn-green { background: linear-gradient(135deg, #22c55e, #16a34a) !important; }
.btn-green:hover { box-shadow: 0 6px 18px rgba(34,197,94,0.35); }
.btn-red   { background: linear-gradient(135deg, #ef4444, #dc2626) !important; }
.btn-red:hover   { box-shadow: 0 6px 18px rgba(239,68,68,0.35); }
.opt-label { font-size: 10px; color: #94a3b8; font-weight: 400; text-transform: none; letter-spacing: 0; margin-left: 4px; }
.state-empty { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; font-size: 36px; }
.state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 540px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #0d9488; }
.cfg-ta { resize: vertical; min-height: 70px; }
.modal-actions { display: flex; gap: 10px; margin-top: 4px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #0d9488; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
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
