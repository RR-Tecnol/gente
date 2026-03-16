<template>
  <div class="ag-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📅 Gerência Pessoal</span>
          <h1 class="hero-title">Agenda</h1>
          <p class="hero-sub">{{ mesLabel }} · {{ eventosDoMes.length }} evento{{ eventosDoMes.length !== 1 ? 's' : '' }} programado{{ eventosDoMes.length !== 1 ? 's' : '' }}</p>
        </div>
        <div class="hero-proximos">
          <div class="hp-label">Próximos eventos:</div>
          <div class="hp-eventos">
            <div v-for="e in proximosEventos" :key="e.id" class="hpe" :style="{ '--ec': tipoCor(e.tipo) }">
              <span class="hpe-ico">{{ tipoIco(e.tipo) }}</span>
              <span class="hpe-nome">{{ e.titulo }}</span>
              <span class="hpe-data">{{ e.dia }}/{{ mesAtual.getMonth() + 1 }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CONTROLES -->
    <div class="cal-toolbar" :class="{ loaded }">
      <div class="cal-nav">
        <button class="nav-btn" @click="navMes(-1)">‹</button>
        <span class="nav-mes">{{ mesLabel }}</span>
        <button class="nav-btn" @click="navMes(1)">›</button>
      </div>
      <div class="tipo-filters">
        <button v-for="t in tipos" :key="t.val" class="tf-btn" :class="{ 'tf-active': tipoFiltro === t.val }" :style="{ '--tc': t.cor }" @click="tipoFiltro = tipoFiltro === t.val ? '' : t.val">
          {{ t.ico }} {{ t.nome }}
        </button>
      </div>
      <button class="new-event-btn" @click="modalAberto = true">+ Novo Evento</button>
    </div>

    <!-- CALENDÁRIO + EVENTOS -->
    <div class="cal-layout" :class="{ loaded }">
      <!-- GRADE MENSAL -->
      <div class="cal-grid-wrap">
        <div class="cal-weekdays">
          <span v-for="d in ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']" :key="d" class="cw-day">{{ d }}</span>
        </div>
        <div class="cal-grid">
          <div v-for="cell in cells" :key="cell.key" class="cal-cell"
            :class="{ 'cc-vazio': !cell.dia, 'cc-hoje': cell.hoje, 'cc-selected': cell.dia === diaSelecionado, 'cc-tem': cell.eventos.length > 0 }"
            @click="cell.dia && (diaSelecionado = diaSelecionado === cell.dia ? null : cell.dia)">
            <div v-if="cell.dia" class="cc-num">{{ cell.dia }}</div>
            <div class="cc-dots">
              <span v-for="(e, ei) in cell.eventos.slice(0, 3)" :key="ei" class="cc-dot" :style="{ background: tipoCor(e.tipo) }"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- EVENTOS DO DIA / MÊS -->
      <div class="eventos-panel">
        <div v-if="diaSelecionado" class="ep-hdr">
          <span class="ep-titulo">{{ diaSelecionado }} de {{ mesLabel }}</span>
          <span class="ep-count">{{ eventosDoDia.length }} evento{{ eventosDoDia.length !== 1 ? 's' : '' }}</span>
        </div>
        <div v-else class="ep-hdr">
          <span class="ep-titulo">Todos os eventos</span>
          <span class="ep-count">{{ eventosDoMesFiltrados.length }}</span>
        </div>
        <div class="ep-list">
          <div v-for="(ev, i) in (diaSelecionado ? eventosDoDia : eventosDoMesFiltrados)" :key="ev.id" class="ev-item" :style="{ '--evi': i, '--ec': tipoCor(ev.tipo) }">
            <div class="ev-hora">{{ ev.hora }}</div>
            <div class="ev-barra" :style="{ background: tipoCor(ev.tipo) }"></div>
            <div class="ev-content">
              <div class="ev-titulo-row">
                <span class="ev-ico">{{ tipoIco(ev.tipo) }}</span>
                <span class="ev-titulo">{{ ev.titulo }}</span>
                <span class="ev-tipo-chip" :style="{ color: tipoCor(ev.tipo), background: tipoCor(ev.tipo) + '14' }">{{ tipoNome(ev.tipo) }}</span>
                <span v-if="ev.escopo === 'global'" class="ev-escopo-chip ev-global">Global</span>
                <span v-else-if="ev.escopo === 'setor'" class="ev-escopo-chip ev-setor">Setor</span>
              </div>
              <div v-if="ev.desc" class="ev-desc">{{ ev.desc }}</div>
              <div class="ev-meta">
                <span v-if="ev.local">📍 {{ ev.local }}</span>
                <span v-if="ev.dia">📅 Dia {{ ev.dia }}</span>
              </div>
            </div>
          </div>
          <div v-if="(diaSelecionado ? eventosDoDia : eventosDoMesFiltrados).length === 0" class="ep-empty">
            <span>📅</span><p>{{ diaSelecionado ? 'Nenhum evento neste dia' : 'Nenhum evento este mês' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL NOVO EVENTO -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>📅 Novo Evento</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Título</label>
              <input v-model="novoEv.titulo" class="cfg-input" placeholder="Ex: Reunião de equipe" />
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Tipo</label>
                <select v-model="novoEv.tipo" class="cfg-input">
                  <option value="">Selecione...</option>
                  <option v-for="t in tipos" :key="t.val" :value="t.val">{{ t.ico }} {{ t.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Data</label>
                <input v-model="novoEv.data" type="date" class="cfg-input" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Horário</label>
                <input v-model="novoEv.hora" type="time" class="cfg-input" />
              </div>
              <div class="form-group">
                <label>Local / Link</label>
                <input v-model="novoEv.local" class="cfg-input" placeholder="Ex: Sala A2" />
              </div>
            </div>
            <div class="form-group">
              <label>Descrição</label>
              <textarea v-model="novoEv.desc" class="cfg-input cfg-ta" rows="3" placeholder="Detalhes do evento..."></textarea>
            </div>
            <div class="form-group">
              <label>Visibilidade</label>
              <div class="escopo-btns">
                <button type="button" class="escopo-btn" :class="{ active: novoEv.escopo === 'pessoal' }" @click="novoEv.escopo = 'pessoal'">Só eu</button>
                <button type="button" class="escopo-btn" :class="{ active: novoEv.escopo === 'setor' }"   @click="novoEv.escopo = 'setor'">Meu setor</button>
                <button type="button" class="escopo-btn" :class="{ active: novoEv.escopo === 'global' }"  @click="novoEv.escopo = 'global'">Toda a empresa</button>
              </div>
              <span class="escopo-hint">
                <template v-if="novoEv.escopo === 'pessoal'">Apenas você verá este evento.</template>
                <template v-else-if="novoEv.escopo === 'setor'">Todos do seu setor poderão ver. Requer perfil de gestor.</template>
                <template v-else>Todos os funcionários poderão ver. Requer perfil de administrador.</template>
              </span>
            </div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novoEv.titulo || !novoEv.tipo || !novoEv.data" @click="salvarEvento">✅ Salvar</button>
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
const mesAtual = ref(new Date())
const diaSelecionado = ref(null)
const tipoFiltro = ref('')
const modalAberto = ref(false)
const salvando = ref(false)
const novoEv = reactive({ titulo: '', tipo: '', data: '', hora: '09:00', local: '', desc: '', escopo: 'pessoal' })

const tipos = [
  { val: 'reuniao', ico: '🤝', nome: 'Reunião', cor: '#3b82f6' },
  { val: 'plantao', ico: '🏥', nome: 'Plantão', cor: '#10b981' },
  { val: 'treinamento', ico: '📚', nome: 'Treinamento', cor: '#6366f1' },
  { val: 'prazo', ico: '⏰', nome: 'Prazo', cor: '#ef4444' },
  { val: 'pessoal', ico: '🌟', nome: 'Pessoal', cor: '#f59e0b' },
]

const mockEventos = [
  { id: 1, titulo: 'Reunião da Equipe de Enfermagem', tipo: 'reuniao', dia: 26, hora: '08:00', local: 'Sala de Reuniões B2', desc: 'Pauta: escalas de março e novos protocolos CCIH.' },
  { id: 2, titulo: 'Plantão UTI Adulto', tipo: 'plantao', dia: 27, hora: '07:00', local: 'UTI Adulto — Bloco C', desc: null },
  { id: 3, titulo: 'Treinamento: Suporte Básico de Vida', tipo: 'treinamento', dia: 28, hora: '14:00', local: 'Sala de Treinamento 3', desc: 'Reciclagem anual obrigatória NR-7.' },
  { id: 4, titulo: 'Prazo: Solicitação de Férias', tipo: 'prazo', dia: 28, hora: '17:00', local: null, desc: 'Período mínimo de 30 dias de aviso.' },
]

const eventos = ref([])

// ── Mapeia registro do backend → formato Vue ──────────────────
const mapEvento = (e) => ({
  id:     e.AGENDA_ID     ?? e.id,
  titulo: e.AGENDA_TITULO ?? e.titulo ?? '—',
  tipo:   (e.AGENDA_TIPO  ?? e.tipo   ?? 'pessoal').toLowerCase(),
  dia:    e.AGENDA_DIA    ?? e.dia    ?? new Date((e.AGENDA_DATA ?? e.data) + 'T12:00:00').getDate(),
  hora:   e.AGENDA_HORA   ?? e.hora   ?? null,
  local:  e.AGENDA_LOCAL  ?? e.local  ?? null,
  desc:   e.AGENDA_DESC   ?? e.desc   ?? null,
  escopo: e.AGENDA_ESCOPO ?? e.escopo ?? 'pessoal',
})

const fetchEventos = async () => {
  const comp = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2, '0')}`
  try {
    const { data } = await api.get('/api/v3/agenda', { params: { competencia: comp } })
    eventos.value = (!data.fallback && data.eventos?.length)
      ? data.eventos.map(mapEvento)
      : mockEventos
  } catch {
    eventos.value = mockEventos
  }
}

onMounted(async () => {
  await fetchEventos()
  setTimeout(() => { loaded.value = true }, 80)
})

const mesLabel = computed(() => mesAtual.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase()))

const cells = computed(() => {
  const ano = mesAtual.value.getFullYear()
  const mes = mesAtual.value.getMonth()
  const primeiroDia = new Date(ano, mes, 1).getDay()
  const totalDias = new Date(ano, mes + 1, 0).getDate()
  const hoje = new Date()
  const result = []
  for (let i = 0; i < primeiroDia; i++) result.push({ key: `v${i}`, dia: null, hoje: false, eventos: [] })
  for (let d = 1; d <= totalDias; d++) {
    const evsDia = eventos.value.filter(e => e.dia === d && (!tipoFiltro.value || e.tipo === tipoFiltro.value))
    const eHoje = hoje.getFullYear() === ano && hoje.getMonth() === mes && hoje.getDate() === d
    result.push({ key: `d${d}`, dia: d, hoje: eHoje, eventos: evsDia })
  }
  return result
})

const eventosDoMes = computed(() => eventos.value)
const eventosDoMesFiltrados = computed(() => tipoFiltro.value ? eventos.value.filter(e => e.tipo === tipoFiltro.value) : eventos.value)
const eventosDoDia = computed(() => diaSelecionado.value ? eventosDoMesFiltrados.value.filter(e => e.dia === diaSelecionado.value) : [])
const proximosEventos = computed(() => {
  const hoje = new Date().getDate()
  const isMesAtual = mesAtual.value.getMonth() === new Date().getMonth() && mesAtual.value.getFullYear() === new Date().getFullYear()
  const futuros = isMesAtual ? eventos.value.filter(e => e.dia >= hoje) : eventos.value
  return [...futuros].sort((a, b) => a.dia - b.dia).slice(0, 3)
})

const navMes = (delta) => {
  const d = new Date(mesAtual.value)
  d.setMonth(d.getMonth() + delta)
  mesAtual.value = d
  diaSelecionado.value = null
  fetchEventos()
}

const salvarEvento = async () => {
  salvando.value = true
  const data = new Date(novoEv.data + 'T12:00:00')
  const dia = data.getDate()
  const eventoLocal = { id: Date.now(), titulo: novoEv.titulo, tipo: novoEv.tipo, dia, hora: novoEv.hora, local: novoEv.local || null, desc: novoEv.desc || null, escopo: novoEv.escopo }
  try {
    const resp = await api.post('/api/v3/agenda', {
      titulo: novoEv.titulo, tipo: novoEv.tipo, data: novoEv.data,
      hora: novoEv.hora, local: novoEv.local, desc: novoEv.desc,
      escopo: novoEv.escopo,
    })
    eventoLocal.id = resp.data?.id ?? eventoLocal.id
    eventoLocal.escopo = resp.data?.escopo ?? novoEv.escopo // backend pode corrigir o escopo
  } catch { /* fallback local */ }
  eventos.value.push(eventoLocal)
  Object.assign(novoEv, { titulo: '', tipo: '', data: '', hora: '09:00', local: '', desc: '', escopo: 'pessoal' })
  modalAberto.value = false
  salvando.value = false
}

const tipoCor = (t) => tipos.find(x => x.val === t)?.cor ?? '#64748b'
const tipoIco = (t) => tipos.find(x => x.val === t)?.ico ?? '📅'
const tipoNome = (t) => tipos.find(x => x.val === t)?.nome ?? t
</script>

<style scoped>
.ag-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0a1a2a 55%, #1a0a1a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #8b5cf6; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-proximos { min-width: 260px; }
.hp-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
.hp-eventos { display: flex; flex-direction: column; gap: 5px; }
.hpe { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.07); border: 1px solid color-mix(in srgb, var(--ec) 30%, rgba(255,255,255,0.1)); border-radius: 10px; padding: 6px 12px; }
.hpe-ico { font-size: 14px; }
.hpe-nome { flex: 1; font-size: 12px; font-weight: 700; color: #e2e8f0; }
.hpe-data { font-size: 11px; color: #64748b; font-weight: 700; }
.cal-toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.cal-toolbar.loaded { opacity: 1; transform: none; }
.cal-nav { display: flex; align-items: center; gap: 10px; }
.nav-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; font-weight: 800; }
.nav-btn:hover { background: #f1f5f9; }
.nav-mes { font-size: 15px; font-weight: 800; color: #1e293b; min-width: 160px; text-align: center; }
.tipo-filters { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; }
.tf-btn { display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 99px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.tf-btn:hover { border-color: var(--tc); color: var(--tc); }
.tf-btn.tf-active { border-color: var(--tc); background: color-mix(in srgb, var(--tc) 10%, white); color: var(--tc); }
.new-event-btn { padding: 9px 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.18s; }
.new-event-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(139,92,246,0.35); }
.cal-layout { display: grid; grid-template-columns: 1fr 360px; gap: 16px; align-items: start; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.cal-layout.loaded { opacity: 1; transform: none; }
.cal-grid-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; }
.cal-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cw-day { text-align: center; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; padding: 10px 0; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.cal-cell { border-right: 1px solid #f8fafc; border-bottom: 1px solid #f8fafc; min-height: 64px; padding: 7px 8px; position: relative; cursor: pointer; transition: background 0.12s; display: flex; flex-direction: column; align-items: flex-start; }
.cal-cell:hover { background: #f8fafc; }
.cc-vazio { cursor: default; background: #fafafa; }
.cc-vazio:hover { background: #fafafa; }
.cc-hoje .cc-num { background: #8b5cf6; color: #fff; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-weight: 900; }
.cc-selected { background: #f3f0ff; }
.cc-num { font-size: 13px; font-weight: 700; color: #1e293b; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
.cc-dots { display: flex; gap: 3px; flex-wrap: wrap; margin-top: 4px; }
.cc-dot { width: 6px; height: 6px; border-radius: 50%; }
.eventos-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; max-height: 480px; }
.ep-hdr { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
.ep-titulo { font-size: 14px; font-weight: 800; color: #1e293b; text-transform: capitalize; }
.ep-count { font-size: 12px; background: #f3f0ff; color: #7c3aed; border-radius: 99px; padding: 2px 10px; font-weight: 700; }
.ep-list { flex: 1; overflow-y: auto; padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
.ev-item { display: flex; align-items: flex-start; gap: 10px; animation: evIn 0.3s cubic-bezier(0.22,1,0.36,1) calc(var(--evi) * 50ms) both; }
@keyframes evIn { from { opacity: 0; transform: translateX(6px); } to { opacity: 1; transform: none; } }
.ev-hora { font-size: 11px; font-weight: 700; color: #64748b; min-width: 40px; padding-top: 3px; }
.ev-barra { width: 3px; border-radius: 99px; align-self: stretch; flex-shrink: 0; min-height: 36px; }
.ev-content { flex: 1; }
.ev-titulo-row { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 3px; }
.ev-ico { font-size: 15px; }
.ev-titulo { font-size: 13px; font-weight: 700; color: #1e293b; }
.ev-tipo-chip { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 8px; }
.ev-desc { font-size: 11px; color: #64748b; line-height: 1.4; margin-bottom: 3px; }
.ev-meta { font-size: 11px; color: #94a3b8; font-weight: 500; display: flex; gap: 10px; }
.ep-empty { display: flex; flex-direction: column; align-items: center; padding: 40px 20px; gap: 8px; font-size: 28px; }
.ep-empty p { font-size: 13px; color: #94a3b8; margin: 0; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #8b5cf6; }
.cfg-ta { resize: vertical; min-height: 70px; }
.modal-actions { display: flex; gap: 10px; margin-top: 4px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
/* Escopo */
.escopo-btns { display: flex; gap: 6px; }
.escopo-btn { flex: 1; padding: 8px 6px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.escopo-btn.active { border-color: #8b5cf6; background: #f3f0ff; color: #7c3aed; }
.escopo-hint { font-size: 11px; color: #94a3b8; font-style: italic; margin-top: 2px; }
/* Badges de escopo nos eventos */
.ev-escopo-chip { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px; }
.ev-global { background: #dbeafe; color: #1e40af; }
.ev-setor  { background: #d1fae5; color: #065f46; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
@media (max-width: 860px) { .cal-layout { grid-template-columns: 1fr; } }
</style>
