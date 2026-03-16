<template>
  <div class="abono-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Recursos Humanos</span>
          <h1 class="hero-title">Abono de Faltas</h1>
          <p class="hero-sub">Justifique ausências com documentação e acompanhe os registros</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card">
            <span class="kpi-num">{{ total }}</span>
            <span class="kpi-label">Registros</span>
          </div>
          <div class="kpi-card kpi-pend">
            <span class="kpi-num">{{ pendentes }}</span>
            <span class="kpi-label">Aguardando</span>
          </div>
          <div class="kpi-card kpi-ok">
            <span class="kpi-num">{{ aprovados }}</span>
            <span class="kpi-label">Aprovados</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs" :class="{ loaded }">
      <button v-for="t in ['Justificar Falta', 'Histórico de Abonos']" :key="t"
        class="tab-btn" :class="{ active: tabAtiva === t }" @click="tabAtiva = t">
        {{ t === 'Justificar Falta' ? '📝' : '📋' }} {{ t }}
      </button>
    </div>

    <!-- ════ TAB: JUSTIFICAR ════ -->
    <div v-if="tabAtiva === 'Justificar Falta'" class="tab-content" :class="{ loaded }">
      <div class="form-panel">
        <div class="panel-hdr">
          <h2 class="panel-title">{{ editandoId ? 'Editar Abono' : 'Registrar Justificativa' }}</h2>
          <p class="panel-sub">Preencha os dados para registrar a justificativa de ausência. Campos com <span class="req">*</span> são obrigatórios.</p>
        </div>

        <!-- Data da falta -->
        <div class="form-group">
          <label>Data da Falta <span class="req">*</span></label>
          <input v-model="form.ABONO_FALTA_DATA" type="date" class="form-input" :max="hoje" />
          <span class="field-hint">Data em que ocorreu a ausência</span>
        </div>

        <!-- Tipo de justificativa -->
        <div class="form-group">
          <label>Tipo de Justificativa <span class="req">*</span></label>
          <div class="tipo-grid">
            <button
              v-for="tipo in tiposJustificativa"
              :key="tipo.value"
              class="tipo-card"
              :class="{ selected: form.tipo === tipo.value }"
              @click="form.tipo = tipo.value"
              type="button"
            >
              <span class="tipo-ico">{{ tipo.ico }}</span>
              <span class="tipo-label">{{ tipo.label }}</span>
            </button>
          </div>
        </div>

        <!-- Justificativa textual -->
        <div class="form-group">
          <label>Justificativa Detalhada <span class="req">*</span></label>
          <textarea
            v-model="form.ABONO_FALTA_JUSTIFICATIVA"
            class="form-input"
            rows="4"
            :placeholder="placeholderJustificativa"
          ></textarea>
          <span class="field-hint">{{ form.ABONO_FALTA_JUSTIFICATIVA?.length || 0 }} / 500 caracteres</span>
        </div>

        <!-- Alerta: banco pode exigir item de escala -->
        <div class="info-note">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span>O abono ficará pendente de aprovação do gestor responsável.</span>
        </div>

        <div v-if="erroForm" class="form-erro">{{ erroForm }}</div>
        <div v-if="okForm"   class="form-ok">{{ okForm }}</div>

        <div class="form-actions">
          <button v-if="editandoId" class="cancel-link" @click="cancelarEdicao">Cancelar edição</button>
          <button class="btn-enviar" :disabled="!formValido || enviando" @click="enviar">
            <div v-if="enviando" class="btn-spinner"></div>
            <template v-else>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              {{ editandoId ? 'Salvar Alterações' : 'Enviar Justificativa' }}
            </template>
          </button>
        </div>
      </div>
    </div>

    <!-- ════ TAB: HISTÓRICO ════ -->
    <div v-if="tabAtiva === 'Histórico de Abonos'" class="tab-content" :class="{ loaded }">
      <!-- Filtro de busca -->
      <div class="hist-toolbar">
        <div class="search-wrap">
          <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="busca" class="search-input" placeholder="Buscar por data ou justificativa..." />
        </div>
        <select v-model="filtroStatus" class="filter-select">
          <option value="">Todos</option>
          <option value="pendente">Pendente</option>
          <option value="aprovado">Aprovado</option>
          <option value="reprovado">Reprovado</option>
        </select>
      </div>

      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando registros...</p></div>

      <div v-else-if="abonosFiltrados.length === 0" class="state-box empty">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <p>{{ busca ? 'Nenhum registro encontrado' : 'Nenhuma justificativa enviada ainda' }}</p>
        <button v-if="!busca" class="btn-enviar" style="padding:10px 20px;font-size:13px;margin-top:4px" @click="tabAtiva='Justificar Falta'">Registrar agora</button>
      </div>

      <div v-else class="abonos-grid">
        <div
          v-for="(a, i) in abonosFiltrados"
          :key="a.DETALHE_ESCALA_ITEM_ID ?? a.id"
          class="abono-card"
          :style="{ '--card-delay': `${i * 40}ms` }"
        >
          <!-- Header do card -->
          <div class="card-hdr">
            <div class="card-data">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              {{ formatDate(a.ABONO_FALTA_DATA ?? a.data) }}
            </div>
            <span class="card-status" :class="statusClass(a.status ?? 'pendente')">
              <span class="status-dot"></span>{{ statusLabel(a.status ?? 'pendente') }}
            </span>
          </div>

          <!-- Tipo (ícone) -->
          <div v-if="a.tipo" class="card-tipo">
            <span class="tipo-badge-sm">{{ tipoIcon(a.tipo) }} {{ tipoLabelStr(a.tipo) }}</span>
          </div>

          <!-- Justificativa -->
          <p class="card-just">{{ a.ABONO_FALTA_JUSTIFICATIVA ?? a.justificativa }}</p>

          <!-- Footer com ações -->
          <div class="card-footer">
            <span class="card-date">Enviado em {{ formatDate(a.criado_em ?? a.ABONO_FALTA_DATA ?? a.data) }}</span>
            <div class="card-actions" v-if="(a.status ?? 'pendente') === 'pendente'">
              <button class="act-btn act-edit" @click="editarAbono(a)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </button>
              <button class="act-btn act-del" @click="excluirAbono(a)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

// ── Estado ────────────────────────────────────────────────────
const loaded   = ref(false)
const loading  = ref(true)
const enviando = ref(false)
const tabAtiva = ref('Justificar Falta')
const abonos   = ref([])
const editandoId = ref(null)
const erroForm = ref('')
const okForm   = ref('')
const busca    = ref('')
const filtroStatus = ref('')

const hoje = new Date().toISOString().slice(0, 10)

const form = reactive({
  ABONO_FALTA_DATA: '',
  ABONO_FALTA_JUSTIFICATIVA: '',
  tipo: '',
})

// ── Tipos de justificativa ────────────────────────────────────
const tiposJustificativa = [
  { value: 'medico',      ico: '🏥', label: 'Atestado Médico' },
  { value: 'declaracao',  ico: '📄', label: 'Declaração' },
  { value: 'luto',        ico: '🕊️', label: 'Luto' },
  { value: 'casamento',   ico: '💍', label: 'Casamento' },
  { value: 'servico',     ico: '🏛️', label: 'Serviço Público' },
  { value: 'outro',       ico: '📝', label: 'Outro' },
]

const tipoIcon    = (v) => tiposJustificativa.find(t => t.value === v)?.ico ?? '📝'
const tipoLabelStr= (v) => tiposJustificativa.find(t => t.value === v)?.label ?? v

const placeholderJustificativa = computed(() => {
  const m = {
    medico: 'Consulta médica / internação / procedimento cirúrgico...',
    declaracao: 'Descreva brevemente a declaração e o órgão emissor...',
    luto: 'Informe o familiar e grau de parentesco...',
    casamento: 'Data do casamento e dias necessários...',
    servico: 'Convocação, eleição, juri....',
    outro: 'Descreva o motivo da ausência...',
  }
  return m[form.tipo] ?? 'Descreva detalhadamente o motivo da ausência...'
})

// ── Computed ──────────────────────────────────────────────────
const total    = computed(() => abonos.value.length)
const pendentes= computed(() => abonos.value.filter(a => (a.status ?? 'pendente') === 'pendente').length)
const aprovados= computed(() => abonos.value.filter(a => a.status === 'aprovado').length)

const formValido = computed(() =>
  form.ABONO_FALTA_DATA && form.ABONO_FALTA_JUSTIFICATIVA?.trim().length >= 10
)

const abonosFiltrados = computed(() => {
  let lista = [...abonos.value]
  if (busca.value) {
    const q = busca.value.toLowerCase()
    lista = lista.filter(a =>
      (a.ABONO_FALTA_DATA ?? '').includes(q) ||
      (a.ABONO_FALTA_JUSTIFICATIVA ?? '').toLowerCase().includes(q)
    )
  }
  if (filtroStatus.value) {
    lista = lista.filter(a => (a.status ?? 'pendente') === filtroStatus.value)
  }
  return lista.sort((a, b) => {
    const da = a.ABONO_FALTA_DATA ?? a.data ?? ''
    const db = b.ABONO_FALTA_DATA ?? b.data ?? ''
    return da < db ? 1 : -1
  })
})

// ── Carregamento ──────────────────────────────────────────────
onMounted(async () => {
  await fetchAbonos()
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchAbonos = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/abono-faltas')
    abonos.value = data.abonos ?? data ?? []
  } catch {
    abonos.value = []
  } finally {
    loading.value = false
  }
}

// ── Enviar / Editar ───────────────────────────────────────────
const enviar = async () => {
  if (!formValido.value) return
  enviando.value = true
  erroForm.value = ''
  okForm.value   = ''

  const payload = {
    ABONO_FALTA_DATA:          form.ABONO_FALTA_DATA,
    ABONO_FALTA_JUSTIFICATIVA: form.tipo
      ? `[${tipoLabelStr(form.tipo)}] ${form.ABONO_FALTA_JUSTIFICATIVA}`
      : form.ABONO_FALTA_JUSTIFICATIVA,
    justificativa: form.ABONO_FALTA_JUSTIFICATIVA,
  }

  try {
    if (editandoId.value) {
      await api.put(`/api/v3/abono-faltas/${editandoId.value}`, payload)
      // Atualiza local
      const idx = abonos.value.findIndex(a => (a.DETALHE_ESCALA_ITEM_ID ?? a.id) === editandoId.value)
      if (idx >= 0) abonos.value[idx] = { ...abonos.value[idx], ...payload }
      okForm.value = 'Abono atualizado com sucesso!'
    } else {
      const { data } = await api.post('/api/v3/abono-faltas', payload)
      okForm.value = 'Justificativa enviada com sucesso!'
      abonos.value.unshift({
        DETALHE_ESCALA_ITEM_ID: data.abono_id ?? Date.now(),
        ...payload,
        tipo: form.tipo,
        status: 'pendente',
        criado_em: hoje,
      })
    }
    resetForm()
    setTimeout(() => { tabAtiva.value = 'Histórico de Abonos'; okForm.value = '' }, 1300)
  } catch (e) {
    erroForm.value = e.response?.data?.erro || 'Erro ao registrar. Verifique os dados e tente novamente.'
  } finally {
    enviando.value = false
  }
}

// ── Editar ────────────────────────────────────────────────────
const editarAbono = (a) => {
  editandoId.value = a.DETALHE_ESCALA_ITEM_ID ?? a.id
  form.ABONO_FALTA_DATA          = a.ABONO_FALTA_DATA ?? a.data ?? ''
  form.ABONO_FALTA_JUSTIFICATIVA = a.ABONO_FALTA_JUSTIFICATIVA ?? a.justificativa ?? ''
  form.tipo = a.tipo ?? ''
  erroForm.value = ''
  okForm.value   = ''
  tabAtiva.value = 'Justificar Falta'
}

const cancelarEdicao = () => resetForm()

const resetForm = () => {
  editandoId.value = null
  Object.assign(form, { ABONO_FALTA_DATA: '', ABONO_FALTA_JUSTIFICATIVA: '', tipo: '' })
}

// ── Excluir ───────────────────────────────────────────────────
const excluirAbono = async (a) => {
  const id = a.DETALHE_ESCALA_ITEM_ID ?? a.id
  if (!confirm('Tem certeza que deseja excluir esta justificativa?')) return
  try {
    if (id && !String(id).startsWith('mock')) {
      await api.delete(`/api/v3/abono-faltas/${id}`)
    }
    abonos.value = abonos.value.filter(x => (x.DETALHE_ESCALA_ITEM_ID ?? x.id) !== id)
  } catch (e) {
    alert(e.response?.data?.erro || 'Erro ao excluir.')
  }
}

// ── Helpers ───────────────────────────────────────────────────
const statusLabel = (s) => ({ pendente: 'Aguardando', aprovado: 'Aprovado', reprovado: 'Reprovado' })[s] ?? 'Pendente'
const statusClass = (s) => ({ pendente: 'st-yellow', aprovado: 'st-green', reprovado: 'st-red' })[s] ?? 'st-yellow'
const formatDate  = (d) => {
  if (!d) return '—'
  try { return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) }
  catch { return d }
}
</script>

<style scoped>
.abono-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 260px; height: 260px; background: #818cf8; right: -60px; top: -80px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 320px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a5b4fc; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-kpis { display: flex; gap: 10px; flex-wrap: wrap; }
.kpi-card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 12px 18px; text-align: center; min-width: 80px; }
.kpi-card.kpi-pend { border-color: rgba(245,158,11,0.4); }
.kpi-card.kpi-ok { border-color: rgba(52,211,153,0.4); }
.kpi-num { display: block; font-size: 26px; font-weight: 900; color: #fff; line-height: 1; }
.kpi-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px; }

/* TABS */
.tabs { display: flex; gap: 6px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { display: flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.tab-btn.active { background: #eef2ff; border-color: #818cf8; color: #3730a3; }
.tab-content { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.tab-content.loaded { opacity: 1; transform: none; }

/* FORM */
.form-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 26px; display: flex; flex-direction: column; gap: 18px; max-width: 680px; }
.panel-hdr { display: flex; flex-direction: column; gap: 4px; margin-bottom: 2px; }
.panel-title { font-size: 17px; font-weight: 800; color: #1e293b; margin: 0; }
.panel-sub { font-size: 13px; color: #64748b; margin: 0; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: #6366f1; }
textarea.form-input { resize: vertical; min-height: 100px; }
.field-hint { font-size: 11px; color: #94a3b8; }
.req { color: #dc2626; }

/* TIPOS */
.tipo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.tipo-card { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 8px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #f8fafc; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.tipo-card:hover { border-color: #a5b4fc; background: #eef2ff; }
.tipo-card.selected { border-color: #6366f1; background: #eef2ff; }
.tipo-ico   { font-size: 22px; }
.tipo-label { font-size: 11px; font-weight: 700; color: #475569; text-align: center; }
.tipo-card.selected .tipo-label { color: #3730a3; }

/* INFO */
.info-note { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; font-size: 12px; color: #3730a3; }
.form-erro { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #dc2626; }
.form-ok   { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #15803d; }
.form-actions { display: flex; align-items: center; gap: 12px; }
.cancel-link { background: none; border: none; color: #94a3b8; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: underline; font-family: inherit; }
.btn-enviar { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 13px; border-radius: 13px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.btn-enviar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.35); }
.btn-enviar:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* HISTÓRICO toolbar */
.hist-toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 16px; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 9px; }
.search-ico { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
.search-input { flex: 1; border: none; font-size: 13px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.search-input::placeholder { color: #cbd5e1; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; cursor: pointer; }

/* ESTADOS */
.state-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 70px 20px; gap: 12px; color: #94a3b8; text-align: center; }
.state-box p { font-size: 14px; font-weight: 500; margin: 0; }
.spinner { width: 38px; height: 38px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }

/* CARDS GRID */
.abonos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
.abono-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; display: flex; flex-direction: column; gap: 10px; animation: cardIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--card-delay) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.abono-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 20px rgba(99,102,241,0.08); }
.card-hdr { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.card-data { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #1e293b; }
.card-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.st-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.st-green  { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.st-red    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.card-tipo { display: flex; }
.tipo-badge-sm { background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; border-radius: 8px; padding: 3px 9px; font-size: 12px; font-weight: 700; }
.card-just { font-size: 13px; color: #334155; margin: 0; line-height: 1.55; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
.card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid #f8fafc; gap: 10px; }
.card-date { font-size: 11px; color: #94a3b8; }
.card-actions { display: flex; gap: 6px; }
.act-btn { display: flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.15s; color: #64748b; }
.act-edit:hover { background: #f5f3ff; border-color: #ddd6fe; color: #7c3aed; }
.act-del:hover  { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

@media (max-width: 640px) { .tipo-grid { grid-template-columns: repeat(2, 1fr); } .abonos-grid { grid-template-columns: 1fr; } }
</style>
