<template>
  <div class="bh-page">
    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⏱️ Ponto Eletrônico</span>
          <h1 class="hero-title">Banco de Horas</h1>
          <p class="hero-sub">{{ mesLabel }} · Análise detalhada do saldo de horas</p>
        </div>
        <div class="month-nav">
          <button class="mnav-btn" @click="mesAnterior">‹</button>
          <span class="mes-label-nav">{{ mesLabel }}</span>
          <button class="mnav-btn" @click="mesPosterior">›</button>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loading-bar"><span></span></div>

    <!-- KPI STRIP ───────────────────────────────────────────── -->
    <div class="kpi-strip" :class="{ loaded }">
      <div class="kpi-card kc-blue">
        <span class="kc-ico">🎯</span>
        <div class="kc-info">
          <span class="kc-label">Horas Esperadas</span>
          <span class="kc-val">{{ saldo.esperadas }}h</span>
        </div>
      </div>
      <div class="kpi-card kc-green">
        <span class="kc-ico">✅</span>
        <div class="kc-info">
          <span class="kc-label">Horas Trabalhadas</span>
          <span class="kc-val">{{ saldo.trabalhadas }}h</span>
        </div>
      </div>
      <div class="kpi-card" :class="saldo.saldo >= 0 ? 'kc-teal' : 'kc-red'">
        <span class="kc-ico">{{ saldo.saldo >= 0 ? '📈' : '📉' }}</span>
        <div class="kc-info">
          <span class="kc-label">Saldo do Mês</span>
          <span class="kc-val">{{ saldo.saldo >= 0 ? '+' : '' }}{{ saldo.saldo }}h</span>
        </div>
      </div>
      <div class="kpi-card kc-purple">
        <span class="kc-ico">💰</span>
        <div class="kc-info">
          <span class="kc-label">Saldo Acumulado</span>
          <span class="kc-val">{{ saldoAcumuladoDisplay >= 0 ? '+' : '' }}{{ saldoAcumuladoDisplay }}h</span>
        </div>
      </div>
      <div class="kpi-card kc-orange">
        <span class="kc-ico">📊</span>
        <div class="kc-info">
          <span class="kc-label">Aproveitamento</span>
          <span class="kc-val">{{ saldo.esperadas > 0 ? Math.round((saldo.trabalhadas / saldo.esperadas) * 100) : 0 }}%</span>
        </div>
      </div>
    </div>

    <!-- PROGRESSO DO MÊS ─────────────────────────────────────── -->
    <div class="progress-card" :class="{ loaded }">
      <div class="prog-hdr">
        <h2 class="prog-title">Progresso do Mês</h2>
        <span class="prog-dias">{{ diasUtilsPassados }} de {{ diasUteis }} dias úteis</span>
      </div>
      <div class="prog-track">
        <div class="prog-fill" :style="{ width: diasUteis > 0 ? (diasUtilsPassados / diasUteis * 100) + '%' : '0%' }"></div>
      </div>
      <div class="prog-categories">
        <div class="prog-cat pc-green"><span class="pc-bar" :style="{ width: saldo.trabalhadas > 0 ? (saldo.normais / saldo.trabalhadas * 100) + '%' : '0%' }"></span></div>
        <div class="prog-cat pc-orange"><span class="pc-bar" :style="{ width: saldo.trabalhadas > 0 ? (saldo.extras / saldo.trabalhadas * 100) + '%' : '0%' }"></span></div>
        <div class="prog-cat pc-red"><span class="pc-bar" :style="{ width: saldo.trabalhadas > 0 ? (saldo.negativas / saldo.trabalhadas * 100) + '%' : '0%' }"></span></div>
      </div>
      <div class="prog-legend">
        <span class="pl-item pl-green">🟢 Normais: {{ saldo.normais }}h</span>
        <span class="pl-item pl-orange">🟠 Extras: {{ saldo.extras }}h</span>
        <span class="pl-item pl-red">🔴 Negativas: {{ saldo.negativas }}h</span>
      </div>
      <div v-if="usingRealData" class="real-badge">✅ Dados reais do backend</div>
    </div>

    <!-- TABELA DIÁRIA ────────────────────────────────────────── -->
    <div class="table-card" :class="{ loaded }">
      <div class="tc-hdr">
        <h2 class="tc-title">📅 Registro Diário</h2>
        <div class="tc-filters">
          <button v-for="f in filterOpts" :key="f.val" class="f-btn" :class="{ active: tipoFiltro === f.val }" @click="tipoFiltro = f.val">{{ f.label }}</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="bh-table">
          <thead>
            <tr>
              <th>Dia</th>
              <th>Entrada</th>
              <th>Saída</th>
              <th>Trabalhadas</th>
              <th>Esperadas</th>
              <th>Saldo</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(d, i) in diasFiltrados" :key="i" class="bh-row" :style="{ '--rd': `${i * 25}ms` }" :class="{ 'row-in': loaded }">
              <td>
                <div class="dia-cell">
                  <span class="dia-num">{{ d.diaNum }}</span>
                  <span class="dia-dow" :class="{ 'dow-fds': d.isFds }">{{ d.dow }}</span>
                </div>
              </td>
              <td><span class="hora-val">{{ d.entrada || '—' }}</span></td>
              <td><span class="hora-val">{{ d.saida || '—' }}</span></td>
              <td><span class="hora-val bold">{{ d.trabalhadas || '—' }}</span></td>
              <td><span class="hora-val dimmed">{{ d.isFds ? '—' : '8h 00min' }}</span></td>
              <td>
                <span class="saldo-chip" :class="saldoClass(d.saldoMin)">
                  {{ formatSaldo(d.saldoMin) }}
                </span>
              </td>
              <td>
                <span class="sit-badge-bh" :class="d.sitClass">{{ d.sitLabel }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const mesAtual = ref(new Date())
const tipoFiltro = ref('todos')
const registros = ref([])
const loading = ref(false)
const usingRealData = ref(false)
const saldoAcumuladoBackend = ref(null)

const filterOpts = [
  { val: 'todos', label: 'Todos' },
  { val: 'extra', label: '📈 Extras' },
  { val: 'negativo', label: '📉 Negativos' },
  { val: 'falta', label: '🚫 Faltas' },
]

const DOW = ['Do','Se','Te','Qu','Qu','Se','Sá']
const fmt  = (mins) => `${String(Math.floor(mins / 60)).padStart(2,'0')}:${String(mins % 60).padStart(2,'0')}`
const fmtDur = (mins) => `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2,'0')}min`

// ── Monta grade com dados REAIS (registros do backend) ────────
const montarComDadosReais = (pontosBackend) => {
  const ano = mesAtual.value.getFullYear()
  const mes = mesAtual.value.getMonth()
  const total = new Date(ano, mes + 1, 0).getDate()
  const hoje = new Date()

  // indexa registros por dia
  const mapaRegistros = {}
  pontosBackend.forEach(r => {
    const d = new Date(r.REGISTRO_DATA_HORA ?? r.data_hora ?? r.PONTO_DATA)
    const dia = d.getDate()
    if (!mapaRegistros[dia]) mapaRegistros[dia] = []
    mapaRegistros[dia].push(r)
  })

  const lista = []
  for (let d = 1; d <= total; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    const isFds = dow === 0 || dow === 6
    const isPast = dt < hoje
    const regs = mapaRegistros[d] || []
    const entrada = regs.find(r => (r.REGISTRO_TIPO || r.tipo) === 'ENTRADA')
    const saida   = regs.find(r => (r.REGISTRO_TIPO || r.tipo) === 'SAIDA')

    let trabalhadas = null, saldoMin = isFds ? 0 : null, entradaStr = null, saidaStr = null
    if (entrada) {
      const h = new Date(entrada.REGISTRO_DATA_HORA ?? entrada.data_hora)
      entradaStr = fmt(h.getHours() * 60 + h.getMinutes())
    }
    if (saida) {
      const h = new Date(saida.REGISTRO_DATA_HORA ?? saida.data_hora)
      saidaStr = fmt(h.getHours() * 60 + h.getMinutes())
    }
    if (entrada && saida && !isFds) {
      const hIni = new Date(entrada.REGISTRO_DATA_HORA ?? entrada.data_hora)
      const hFim = new Date(saida.REGISTRO_DATA_HORA  ?? saida.data_hora)
      const dur  = (hFim - hIni) / 60000
      trabalhadas = fmtDur(dur)
      saldoMin    = dur - 480
    } else if (!isFds && isPast && regs.length === 0) {
      saldoMin = -480 // falta
    }

    lista.push({ data: dt, diaNum: d, dow: DOW[dow], isFds, entrada: entradaStr, saida: saidaStr, trabalhadas, saldoMin })
  }
  registros.value = lista
  usingRealData.value = true
}

// ── Fallback: gera mock ────────────────────────────────────────
const gerarMock = () => {
  const ano = mesAtual.value.getFullYear()
  const mes = mesAtual.value.getMonth()
  const total = new Date(ano, mes + 1, 0).getDate()
  const hoje = new Date()
  const mock = []
  for (let d = 1; d <= total; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    const isPast = dt < hoje
    const isFds = dow === 0 || dow === 6
    if (isFds || !isPast) { mock.push({ data: dt, diaNum: d, dow: DOW[dow], isFds, entrada: null, saida: null, saldoMin: isFds ? 0 : null, trabalhadas: null }); continue }
    const rand = Math.random()
    const entMin = 480 + Math.floor(Math.random() * 20) - 10
    const durMin = rand > 0.9 ? 0 : rand > 0.7 ? 600 + Math.floor(Math.random() * 60) : 480 + Math.floor(Math.random() * 30) - 15
    const saldoMin = rand > 0.9 ? -480 : durMin - 480
    mock.push({
      data: dt, diaNum: d, dow: DOW[dow], isFds,
      entrada: rand > 0.9 ? null : fmt(entMin),
      saida:   rand > 0.9 ? null : fmt(entMin + durMin),
      trabalhadas: rand > 0.9 ? null : fmtDur(durMin),
      saldoMin,
    })
  }
  registros.value = mock
  usingRealData.value = false
}

const fetchRegistros = async () => {
  loading.value = true
  const comp = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  try {
    // 1. Busca apurações mensais (banco-horas)
    const { data: bh } = await api.get('/api/v3/banco-horas')
    if (!bh.fallback && bh.apuracoes?.length) {
      const apuMes = bh.apuracoes.find(a => a.competencia === comp)
      if (apuMes) saldoAcumuladoBackend.value = apuMes.saldo_acumulado ?? null
    }

    // 2. Busca registros diários reais do ponto
    const { data: ponto } = await api.get('/api/v3/ponto', { params: { competencia: comp } })
    if (!ponto.fallback && ponto.registros?.length) {
      montarComDadosReais(ponto.registros)
      return
    }

    // 3. Fallback: mock
    gerarMock()
  } catch {
    gerarMock()
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchRegistros()
  setTimeout(() => { loaded.value = true }, 80)
})

const mesAnterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() - 1); mesAtual.value = d; fetchRegistros() }
const mesPosterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() + 1); mesAtual.value = d; fetchRegistros() }
const mesLabel = computed(() => mesAtual.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase()))

const saldoAcumuladoDisplay = computed(() => {
  if (saldoAcumuladoBackend.value !== null) return saldoAcumuladoBackend.value
  return Math.round(saldo.value.saldo) + 3
})

const diasComSit = computed(() => registros.value.map(d => {
  let sitLabel = '—', sitClass = 'sit-gray'
  if (d.isFds)              { sitLabel = 'Fim de Semana'; sitClass = 'sit-gray' }
  else if (d.saldoMin === null)  { sitLabel = 'Futuro';       sitClass = 'sit-light' }
  else if (d.saldoMin === -480) { sitLabel = 'Falta';        sitClass = 'sit-red'   }
  else if (d.saldoMin >= 60)   { sitLabel = 'Extra';        sitClass = 'sit-blue'  }
  else if (d.saldoMin < -15)   { sitLabel = 'Negativo';     sitClass = 'sit-orange'}
  else                          { sitLabel = 'Normal';       sitClass = 'sit-green' }
  return { ...d, sitLabel, sitClass }
}))

const diasFiltrados = computed(() => {
  if (tipoFiltro.value === 'todos') return diasComSit.value
  const map = { extra: 'Extra', negativo: 'Negativo', falta: 'Falta' }
  return diasComSit.value.filter(d => d.sitLabel === map[tipoFiltro.value])
})

const diasUteis = computed(() => registros.value.filter(d => !d.isFds).length)
const diasUtilsPassados = computed(() => registros.value.filter(d => !d.isFds && d.saldoMin !== null).length)

const saldo = computed(() => {
  const dias = registros.value.filter(d => !d.isFds && d.saldoMin !== null)
  const total = dias.reduce((a, d) => a + d.saldoMin, 0)
  const extras = dias.filter(d => d.saldoMin > 0).reduce((a, d) => a + d.saldoMin, 0)
  const neg = dias.filter(d => d.saldoMin < 0 && d.saldoMin > -480).reduce((a, d) => a + Math.abs(d.saldoMin), 0)
  const trab = diasUtilsPassados.value * 480 + total
  return {
    esperadas:  diasUtilsPassados.value * 8,
    trabalhadas: Math.round(trab / 60),
    normais:    Math.round((trab - extras) / 60),
    extras:     Math.round(extras / 60),
    negativas:  Math.round(neg / 60),
    saldo:      Math.round(total / 60),
  }
})

const formatSaldo = (m) => { if (m === null || m === 0) return '—'; const s = Math.abs(m); return `${m >= 0 ? '+' : '-'}${Math.floor(s/60)}h${String(s%60).padStart(2,'0')}` }
const saldoClass  = (m) => m === null ? '' : m >= 60 ? 'saldo-pos' : m < -15 ? 'saldo-neg' : 'saldo-ok'
</script>

<style scoped>
.bh-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a2a1a 55%, #0f1a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #3b82f6; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #60a5fa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.month-nav { display: flex; align-items: center; gap: 12px; }
.mnav-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); font-size: 18px; cursor: pointer; color: #fff; font-weight: 900; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.mnav-btn:hover { background: rgba(255,255,255,0.15); }
.mes-label-nav { font-size: 15px; font-weight: 800; color: #fff; white-space: nowrap; min-width: 150px; text-align: center; }
.loading-bar { height: 3px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.loading-bar span { display: block; height: 100%; width: 40%; background: linear-gradient(to right, #3b82f6, #10b981); border-radius: 99px; animation: loadSlide 1.2s ease-in-out infinite; }
@keyframes loadSlide { 0% { transform: translateX(-100%); } 100% { transform: translateX(350%); } }
.kpi-strip { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr)); gap: 12px; width: 100%; box-sizing: border-box; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.kpi-strip.loaded { opacity: 1; transform: none; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-top: 3px solid; }
.kc-ico { font-size: 24px; }
.kc-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; }
.kc-val { display: block; font-size: 20px; font-weight: 900; margin-top: 2px; }
.kc-blue { border-top-color: #3b82f6; } .kc-blue .kc-val { color: #1d4ed8; }
.kc-green { border-top-color: #10b981; } .kc-green .kc-val { color: #065f46; }
.kc-teal { border-top-color: #0d9488; } .kc-teal .kc-val { color: #0d9488; }
.kc-red { border-top-color: #ef4444; } .kc-red .kc-val { color: #dc2626; }
.kc-purple { border-top-color: #6366f1; } .kc-purple .kc-val { color: #4f46e5; }
.kc-orange { border-top-color: #f59e0b; } .kc-orange .kc-val { color: #92400e; }
.progress-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 22px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.progress-card.loaded { opacity: 1; transform: none; }
.prog-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.prog-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.prog-dias { font-size: 12px; color: #94a3b8; font-weight: 600; }
.prog-track { position: relative; height: 10px; background: #f1f5f9; border-radius: 99px; overflow: hidden; margin-bottom: 12px; }
.prog-fill { height: 100%; background: linear-gradient(to right, #10b981, #0d9488); border-radius: 99px; transition: width 1s cubic-bezier(0.22,1,0.36,1); }
.prog-categories { display: flex; gap: 4px; height: 8px; border-radius: 99px; overflow: hidden; margin-bottom: 10px; }
.prog-cat { flex: 1; border-radius: 99px; overflow: hidden; background: #f1f5f9; }
.pc-bar { display: block; height: 100%; border-radius: 99px; }
.pc-green .pc-bar { background: #10b981; }
.pc-orange .pc-bar { background: #f59e0b; }
.pc-red .pc-bar { background: #ef4444; }
.prog-legend { display: flex; gap: 16px; flex-wrap: wrap; }
.pl-item { font-size: 12px; font-weight: 700; color: #475569; }
.real-badge { margin-top: 10px; font-size: 11px; font-weight: 700; color: #10b981; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.table-card.loaded { opacity: 1; transform: none; }
.tc-hdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px; }
.tc-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.tc-filters { display: flex; gap: 6px; }
.f-btn { padding: 6px 12px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 11px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.15s; }
.f-btn.active { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.table-scroll { overflow-x: auto; }
.bh-table { width: 100%; border-collapse: collapse; }
.bh-table thead tr { background: #f8fafc; }
.bh-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.bh-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.bh-row:hover { background: #fafafa; }
.bh-row:last-child { border-bottom: none; }
.bh-row.row-in td { animation: rowIn 0.3s cubic-bezier(0.22,1,0.36,1) var(--rd) both; }
@keyframes rowIn { from { opacity: 0; } to { opacity: 1; } }
.bh-table td { padding: 10px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.dia-cell { display: flex; align-items: center; gap: 8px; }
.dia-num { font-size: 15px; font-weight: 900; color: #1e293b; min-width: 22px; }
.dia-dow { font-size: 10px; font-weight: 700; color: #94a3b8; }
.dow-fds { color: #cbd5e1; }
.hora-val { font-family: monospace; font-size: 13px; }
.hora-val.bold { font-weight: 800; color: #1e293b; }
.hora-val.dimmed { color: #94a3b8; }
.saldo-chip { padding: 3px 10px; border-radius: 8px; font-size: 12px; font-weight: 800; font-family: monospace; }
.saldo-pos { background: #dcfce7; color: #166534; }
.saldo-ok  { background: #f0fdf4; color: #16a34a; }
.saldo-neg { background: #fef2f2; color: #dc2626; }
.sit-badge-bh { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.sit-green  { background: #dcfce7; color: #166534; }
.sit-blue   { background: #dbeafe; color: #1e40af; }
.sit-orange { background: #fff7ed; color: #9a3412; }
.sit-red    { background: #fef2f2; color: #991b1b; }
.sit-gray   { background: #f1f5f9; color: #64748b; }
.sit-light  { background: #f8fafc; color: #94a3b8; }
@media (max-width: 768px) {
  .kpi-strip { grid-template-columns: repeat(2, 1fr); }
  .hero-inner { flex-direction: column; align-items: flex-start; }
  .bh-page { padding: 0 12px; }
}
@media (max-width: 480px) {
  .bh-table th:nth-child(5), .bh-table td:nth-child(5) { display: none; }
  .kc-val { font-size: 18px; }
}
</style>
