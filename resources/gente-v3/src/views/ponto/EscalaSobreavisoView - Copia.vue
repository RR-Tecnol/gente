<template>
  <div class="sob-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📡 Gestão de Escalas</span>
          <h1 class="hero-title">Escala de Sobreaviso</h1>
          <p class="hero-sub">Plantões on-call · Disponibilidade e acionamentos do mês</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hv">{{ sobreaviso.filter(s => s.ativo).length }}</span><span class="hl">Em Sobreaviso</span></div>
          <div class="hstat hb"><span class="hv">{{ acionamentos.length }}</span><span class="hl">Acionamentos</span></div>
          <div class="hstat hc"><span class="hv">{{ valorTotal > 0 ? 'R$ ' + fmtMoeda(valorTotal) : '—' }}</span><span class="hl">Valor Acumulado</span></div>
        </div>
      </div>
    </div>

    <!-- CALENDÁRIO DE SOBREAVISO -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">📅 Calendário — {{ mesLabel }}</h2>
      <div class="cal-nav">
        <button class="nav-btn" @click="navMes(-1)">‹</button>
        <button class="nav-btn" @click="navMes(1)">›</button>
      </div>
    </div>
    <div class="cal-grid-card" :class="{ loaded }">
      <div class="cal-weekdays"><span v-for="d in ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']" :key="d">{{ d }}</span></div>
      <div class="cal-grid">
        <div v-for="cell in cells" :key="cell.key" class="cal-cell"
          :class="{ 'cc-vazio': !cell.dia, 'cc-hoje': cell.hoje, 'cc-sob': cell.sobAviso, 'cc-acion': cell.acionado }">
          <div v-if="cell.dia" class="cc-num">{{ cell.dia }}</div>
          <div v-if="cell.sobAviso" class="cc-label sob">📡</div>
          <div v-if="cell.acionado" class="cc-label acion">🚨</div>
        </div>
      </div>
      <div class="cal-legend">
        <span class="leg-item"><span class="leg-dot leg-sob"></span> Sobreaviso</span>
        <span class="leg-item"><span class="leg-dot leg-acion"></span> Acionamento</span>
      </div>
    </div>

    <!-- MINHA ESCALA -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">🗓️ Meus Períodos de Sobreaviso</h2>
    </div>
    <div class="sob-list" :class="{ loaded }">
      <div v-if="sobreaviso.length === 0" class="empty-state">
        <span class="empty-ico">📡</span>
        <p>Nenhum período de sobreaviso encontrado</p>
      </div>
      <div v-for="(s, i) in sobreaviso" :key="s.id" class="sob-item" :style="{ '--si': i }">
        <div class="si-status" :class="s.ativo ? 'sis-on' : 'sis-off'">{{ s.ativo ? '📡 ON' : 'OFF' }}</div>
        <div class="si-info">
          <span class="si-periodo">{{ s.periodoLabel }}</span>
          <span class="si-setor">{{ s.setor }}</span>
        </div>
        <div class="si-horas">
          <span class="sih-val">{{ s.horas }}h</span>
          <span class="sih-label">de sobreaviso</span>
        </div>
        <div class="si-valor">
          <span class="siv-val">{{ s.valor ? 'R$ ' + fmtMoeda(s.valor) : '—' }}</span>
          <span class="siv-label">{{ s.percentual ? s.percentual + '% do dia' : 'sobreaviso' }}</span>
        </div>
        <span class="si-badge" :class="s.ativo ? 'sb-green' : 'sb-gray'">{{ s.ativo ? 'Ativo' : 'Encerrado' }}</span>
      </div>
    </div>

    <!-- ACIONAMENTOS -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">🚨 Acionamentos Registrados</h2>
      <button class="new-btn" @click="modalAberto = true">+ Registrar Acionamento</button>
    </div>
    <div class="acion-list" :class="{ loaded }">
      <div v-if="acionamentos.length === 0" class="empty-state">
        <span class="empty-ico">🚨</span>
        <p>Nenhum acionamento registrado neste mês</p>
      </div>
      <div v-for="(a, i) in acionamentos" :key="a.id" class="acion-item" :style="{ '--ai': i }">
        <div class="ai-data">
          <span class="ai-dia">{{ new Date(a.data + 'T12:00:00').getDate() }}</span>
          <span class="ai-mes">{{ new Date(a.data + 'T12:00:00').toLocaleDateString('pt-BR', { month: 'short' }) }}</span>
        </div>
        <div class="ai-info">
          <span class="ai-titulo">{{ a.motivo }}</span>
          <span class="ai-local">{{ a.local }}</span>
          <span class="ai-hora">{{ a.horaIni }} → {{ a.horaFim }} · {{ a.duracaoH }}h atendidas</span>
        </div>
        <div class="ai-valor">
          <span class="aiv-val">{{ a.valor ? 'R$ ' + fmtMoeda(a.valor) : '—' }}</span>
          <span class="aiv-label">Hora extra NR</span>
        </div>
        <span class="ai-status" :class="a.pago ? 'as-green' : 'as-yellow'">{{ a.pago ? 'Pago' : 'Pendente' }}</span>
      </div>
    </div>

    <!-- MODAL REGISTRAR ACIONAMENTO -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>🚨 Registrar Acionamento</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group"><label>Data</label><input type="date" v-model="novoAcion.data" class="cfg-input" /></div>
              <div class="form-group"><label>Local/Setor</label><input v-model="novoAcion.local" class="cfg-input" placeholder="Ex: UTI Adulto" /></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Hora Início</label><input type="time" v-model="novoAcion.horaIni" class="cfg-input" /></div>
              <div class="form-group"><label>Hora Fim</label><input type="time" v-model="novoAcion.horaFim" class="cfg-input" /></div>
            </div>
            <div class="form-group"><label>Motivo do Acionamento</label><input v-model="novoAcion.motivo" class="cfg-input" placeholder="Ex: Emergência cirúrgica" /></div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novoAcion.data || !novoAcion.motivo || salvando" @click="salvarAcionamento">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>✅ Registrar</template>
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

const loaded      = ref(false)
const modalAberto = ref(false)
const salvando    = ref(false)
const mesAtual    = ref(new Date())
const novoAcion   = reactive({ data: '', local: '', horaIni: '', horaFim: '', motivo: '' })
const sobreaviso  = ref([])
const acionamentos = ref([])

// ── Mapeamento: colunas DB → formato Vue ────────────────────
const mapSobreaviso = (r) => {
  const inicio = r.SOBREAVISO_INICIO ?? r.inicio ?? null
  const fim    = r.SOBREAVISO_FIM    ?? r.fim    ?? null
  const fmtDate = (d) => d ? new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '?'
  return {
    id:          r.SOBREAVISO_ID  ?? r.id,
    periodoLabel: inicio ? `${fmtDate(inicio)} – ${fmtDate(fim ?? inicio)}` : (r.periodoLabel ?? '—'),
    setor:       r.SOBREAVISO_SETOR ?? r.setor ?? '—',
    horas:       r.SOBREAVISO_HORAS ?? r.horas ?? 0,
    percentual:  r.SOBREAVISO_PERCENTUAL ?? r.percentual ?? null,
    valor:       r.SOBREAVISO_VALOR ?? r.valor ?? null,
    ativo:       r.SOBREAVISO_ATIVO != null ? !!r.SOBREAVISO_ATIVO : (r.ativo ?? false),
    inicio:      inicio,
  }
}

const mapAcionamento = (r) => ({
  id:       r.ACIONAMENTO_ID    ?? r.id,
  data:     r.ACIONAMENTO_DATA  ?? r.data ?? '',
  motivo:   r.ACIONAMENTO_MOTIVO ?? r.motivo ?? '—',
  local:    r.ACIONAMENTO_LOCAL  ?? r.local  ?? '—',
  horaIni:  r.ACIONAMENTO_HORA_INI ?? r.horaIni ?? '—',
  horaFim:  r.ACIONAMENTO_HORA_FIM ?? r.horaFim ?? '—',
  duracaoH: r.ACIONAMENTO_DURACAO  ?? r.duracaoH ?? 0,
  valor:    r.ACIONAMENTO_VALOR    ?? r.valor    ?? null,
  pago:     !!(r.ACIONAMENTO_PAGO  ?? r.pago     ?? false),
})

const mockSobreaviso = [
  { id: 1, periodoLabel: '24/02 – 27/02/2026', setor: 'UTI Adulto',    horas: 72, percentual: 33.3, valor: 550, ativo: true  },
  { id: 2, periodoLabel: '10/02 – 12/02/2026', setor: 'Pronto-Socorro', horas: 48, percentual: 33.3, valor: 370, ativo: false },
]
const mockAcionamentos = [
  { id: 1, data: '2026-02-25', motivo: 'Emergência cirúrgica', local: 'Centro Cirúrgico', horaIni: '02:30', horaFim: '05:00', duracaoH: 2.5, valor: 185, pago: false },
  { id: 2, data: '2026-02-10', motivo: 'Intercorrência respiratória', local: 'UTI Adulto', horaIni: '23:00', horaFim: '01:30', duracaoH: 2.5, valor: 185, pago: true  },
]

const fetchDados = async () => {
  const comp = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  try {
    const { data } = await api.get('/api/v3/sobreaviso', { params: { competencia: comp } })
    sobreaviso.value   = (!data.fallback && data.sobreaviso?.length)   ? data.sobreaviso.map(mapSobreaviso)   : mockSobreaviso
    acionamentos.value = (!data.fallback && data.acionamentos?.length) ? data.acionamentos.map(mapAcionamento) : mockAcionamentos
  } catch {
    sobreaviso.value   = mockSobreaviso
    acionamentos.value = mockAcionamentos
  }
}

onMounted(async () => {
  await fetchDados()
  setTimeout(() => { loaded.value = true }, 80)
})

const navMes = (delta) => {
  const d = new Date(mesAtual.value)
  d.setMonth(d.getMonth() + delta)
  mesAtual.value = d
  fetchDados()
}

// Dias marcados no calendário
const diasSobreaviso = computed(() => {
  const dias = new Set()
  sobreaviso.value.forEach(s => {
    if (!s.inicio) return
    const ini = new Date(s.inicio + 'T12:00:00')
    const fim = s.fim ? new Date(s.fim + 'T12:00:00') : ini
    const ano = mesAtual.value.getFullYear(), mes = mesAtual.value.getMonth()
    for (let d = new Date(ini); d <= fim; d.setDate(d.getDate() + 1)) {
      if (d.getFullYear() === ano && d.getMonth() === mes) dias.add(d.getDate())
    }
  })
  return [...dias]
})

const diasAcionamento = computed(() =>
  acionamentos.value
    .filter(a => {
      const d = new Date(a.data + 'T12:00:00')
      return d.getFullYear() === mesAtual.value.getFullYear() && d.getMonth() === mesAtual.value.getMonth()
    })
    .map(a => new Date(a.data + 'T12:00:00').getDate())
)

const valorTotal = computed(() => sobreaviso.value.reduce((a, s) => a + (Number(s.valor) || 0), 0))
const mesLabel   = computed(() => mesAtual.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase()))
const fmtMoeda   = v => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(v)

const cells = computed(() => {
  const ano = mesAtual.value.getFullYear(), mes = mesAtual.value.getMonth()
  const pd = new Date(ano, mes, 1).getDay()
  const total = new Date(ano, mes + 1, 0).getDate()
  const hoje  = new Date()
  const res   = []
  for (let i = 0; i < pd; i++) res.push({ key: `v${i}`, dia: null, hoje: false, sobAviso: false, acionado: false })
  for (let d = 1; d <= total; d++) {
    const eHoje = hoje.getFullYear() === ano && hoje.getMonth() === mes && hoje.getDate() === d
    res.push({ key: `d${d}`, dia: d, hoje: eHoje, sobAviso: diasSobreaviso.value.includes(d), acionado: diasAcionamento.value.includes(d) })
  }
  return res
})

const salvarAcionamento = async () => {
  salvando.value = true
  try {
    const { data } = await api.post('/api/v3/sobreaviso/acionamento', { ...novoAcion })
    acionamentos.value.unshift(mapAcionamento({
      ACIONAMENTO_ID: data.id ?? Date.now(),
      ACIONAMENTO_DATA: novoAcion.data, ACIONAMENTO_MOTIVO: novoAcion.motivo,
      ACIONAMENTO_LOCAL: novoAcion.local, ACIONAMENTO_HORA_INI: novoAcion.horaIni,
      ACIONAMENTO_HORA_FIM: novoAcion.horaFim,
    }))
  } catch {
    acionamentos.value.unshift(mapAcionamento({
      ACIONAMENTO_ID: Date.now(), ACIONAMENTO_DATA: novoAcion.data,
      ACIONAMENTO_MOTIVO: novoAcion.motivo, ACIONAMENTO_LOCAL: novoAcion.local,
      ACIONAMENTO_HORA_INI: novoAcion.horaIni, ACIONAMENTO_HORA_FIM: novoAcion.horaFim,
    }))
  } finally {
    Object.assign(novoAcion, { data: '', local: '', horaIni: '', horaFim: '', motivo: '' })
    salvando.value  = false
    modalAberto.value = false
  }
}
</script>

<style scoped>
.sob-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0a1528 55%, #1a0a1a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 18px; text-align: center; }
.hv { display: block; font-size: 20px; font-weight: 900; }
.hl { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hv { color: #818cf8; } .hb .hv { color: #f87171; } .hc .hv { color: #34d399; font-size: 15px; }
.section-hdr { display: flex; align-items: center; justify-content: space-between; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.section-hdr.loaded { opacity: 1; transform: none; }
.sh-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.cal-nav { display: flex; gap: 6px; }
.nav-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 16px; cursor: pointer; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.new-btn { padding: 7px 14px; border-radius: 10px; border: none; background: #ef4444; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
.cal-grid-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.cal-grid-card.loaded { opacity: 1; transform: none; }
.cal-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cal-weekdays span { text-align: center; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; padding: 10px 0; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.cal-cell { min-height: 52px; border-right: 1px solid #f8fafc; border-bottom: 1px solid #f8fafc; padding: 5px; display: flex; flex-direction: column; align-items: center; }
.cc-vazio { background: #fafafa; }
.cc-hoje .cc-num { background: #6366f1; color: #fff; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-weight: 900; }
.cc-sob { background: #eff0ff; } .cc-acion { background: #fff0f0; }
.cc-sob.cc-acion { background: linear-gradient(135deg, #eff0ff, #fff0f0); }
.cc-num { font-size: 12px; font-weight: 700; color: #1e293b; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.cc-label { font-size: 13px; }
.cal-legend { display: flex; gap: 16px; padding: 10px 16px; border-top: 1px solid #f1f5f9; }
.leg-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; font-weight: 600; }
.leg-dot { width: 10px; height: 10px; border-radius: 3px; }
.leg-sob { background: #818cf8; } .leg-acion { background: #f87171; }
.empty-state { text-align: center; padding: 40px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
.empty-ico { font-size: 40px; display: block; margin-bottom: 10px; }
.empty-state p { color: #94a3b8; font-size: 14px; font-weight: 600; margin: 0; }
.sob-list { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.sob-list.loaded { opacity: 1; transform: none; }
.sob-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 18px; animation: sIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--si) * 50ms) both; flex-wrap: wrap; }
@keyframes sIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.si-status { font-size: 11px; font-weight: 900; padding: 5px 10px; border-radius: 10px; min-width: 48px; text-align: center; }
.sis-on  { background: #eff0ff; color: #4338ca; border: 1px solid #c7d2fe; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.3); } 50% { box-shadow: 0 0 0 6px rgba(99,102,241,0); } }
.sis-off { background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; }
.si-info { flex: 1; min-width: 150px; }
.si-periodo { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.si-setor   { display: block; font-size: 12px; color: #64748b; margin-top: 2px; }
.si-horas { text-align: center; }
.sih-val { display: block; font-size: 18px; font-weight: 900; color: #1e293b; }
.sih-label { display: block; font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase; }
.si-valor { text-align: center; }
.siv-val { display: block; font-size: 16px; font-weight: 900; color: #10b981; }
.siv-label { display: block; font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase; }
.si-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 99px; }
.sb-green { background: #dcfce7; color: #166534; } .sb-gray { background: #f1f5f9; color: #64748b; }
.acion-list { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.acion-list.loaded { opacity: 1; transform: none; }
.acion-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 18px; animation: aIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ai) * 50ms) both; flex-wrap: wrap; }
@keyframes aIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.ai-data { text-align: center; min-width: 36px; }
.ai-dia { display: block; font-size: 20px; font-weight: 900; color: #1e293b; line-height: 1; }
.ai-mes { display: block; font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; }
.ai-info { flex: 1; min-width: 160px; }
.ai-titulo { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.ai-local  { display: block; font-size: 12px; color: #64748b; margin-top: 2px; }
.ai-hora   { display: block; font-size: 11px; color: #94a3b8; margin-top: 3px; font-family: monospace; }
.ai-valor { text-align: right; }
.aiv-val { display: block; font-size: 16px; font-weight: 900; color: #f59e0b; }
.aiv-label { display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }
.ai-status { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 99px; }
.as-green { background: #dcfce7; color: #166534; } .as-yellow { background: #fffbeb; color: #92400e; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #6366f1; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #ef4444; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

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
