<template>
  <div class="ponto-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2"></div>
      </div>
      <div class="hero-inner">
        <div class="hero-left">
          <span class="hero-eyebrow">⏱️ Controle de Frequência</span>
          <h1 class="hero-title">Ponto Eletrônico</h1>
          <p class="hero-sub">{{ mesAnoExibido }} · {{ diasUteis }} dias úteis</p>
        </div>
        <div class="hero-nav">
          <button class="nav-btn" @click="mudarMes(-1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
          <span class="nav-mes">{{ mesAnoExibido }}</span>
          <button class="nav-btn" @click="mudarMes(1)" :disabled="isMesFuturo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
        </div>
        <div class="hero-stats">
          <div class="hstat">
            <span class="hstat-val">{{ resumo.presentes }}</span>
            <span class="hstat-label">Presenças</span>
          </div>
          <div class="hstat hstat-yellow">
            <span class="hstat-val">{{ resumo.inconsistentes }}</span>
            <span class="hstat-label">Inconsistentes</span>
          </div>
          <div class="hstat hstat-red">
            <span class="hstat-val">{{ resumo.faltas }}</span>
            <span class="hstat-label">Faltas</span>
          </div>
          <div class="hstat hstat-blue">
            <span class="hstat-val">{{ resumo.horasTrabalhadas }}h</span>
            <span class="hstat-label">Trabalhadas</span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOADING ─────────────────────────────────────────────── -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Carregando batidas...</p>
    </div>

    <!-- CONTEÚDO ─────────────────────────────────────────────── -->
    <div v-else class="ponto-layout" :class="{ loaded }">

      <!-- CALENDÁRIO ──────────────────────────────────────────── -->
      <div class="calendar-card">
        <!-- Cabeçalho dos dias da semana -->
        <div class="cal-header">
          <span v-for="d in ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']" :key="d" class="cal-dow">{{ d }}</span>
        </div>

        <!-- Grid dos dias -->
        <div class="cal-grid">
          <!-- Espaços vazios antes do dia 1 -->
          <div v-for="n in primeiroDiaSemana" :key="'vazio-'+n" class="cal-day cal-empty"></div>

          <!-- Dias reais -->
          <div
            v-for="dia in diasDoMes"
            :key="dia.num"
            class="cal-day"
            :class="[dia.classe, { 'cal-selected': diaAberto === dia.num, 'cal-hoje': dia.isHoje }]"
            @click="(!dia.isFds && !dia.isFuturo) && abrirDia(dia)"
            :title="dia.titulo"
          >
            <span class="day-num">{{ dia.num }}</span>
            <div v-if="dia.dots.length" class="day-dots">
              <span v-for="(dot, i) in dia.dots.slice(0, 4)" :key="i" class="day-dot" :class="dot"></span>
            </div>
            <span v-if="dia.badge" class="day-badge" :class="dia.badgeClass">{{ dia.badge }}</span>
          </div>
        </div>

        <!-- Legenda -->
        <div class="cal-legend">
          <div class="leg-item"><span class="leg-dot green"></span>Presente</div>
          <div class="leg-item"><span class="leg-dot yellow"></span>Inconsistente</div>
          <div class="leg-item"><span class="leg-dot red"></span>Falta</div>
          <div class="leg-item"><span class="leg-dot gray"></span>Feriado/Fim de semana</div>
        </div>
      </div>

      <!-- PAINEL LATERAL ──────────────────────────────────────── -->
      <div class="side-panel">

        <!-- Batidas do dia selecionado -->
        <div class="detail-card" v-if="diaAberto">
          <div class="dc-header">
            <div class="dc-title-wrap">
              <div class="dc-icon" :class="diaAbertoData?.statusClass">
                {{ diaAbertoData?.statusIcon }}
              </div>
              <div>
                <h3 class="dc-title">{{ diaFormatado }}</h3>
                <span class="dc-sub">{{ diaAbertoData?.statusLabel }}</span>
              </div>
            </div>
          </div>

          <div class="batidas-list" v-if="diaAbertoData?.batidas?.length">
            <div
              v-for="(b, i) in diaAbertoData.batidas"
              :key="i"
              class="batida-item"
              :class="b.tipo"
            >
              <div class="batida-tipo">{{ b.tipoLabel }}</div>
              <div class="batida-hora">{{ b.hora }}</div>
              <div class="batida-icone">{{ b.tipoIcon }}</div>
            </div>
          </div>
          <div v-else class="no-batida">
            <span>📭</span>
            <p>Nenhuma batida registrada</p>
          </div>

          <!-- Horas trabalhadas no dia -->
          <div class="horas-info" v-if="diaAbertoData?.horasTrabalhadas">
            <div class="horas-bar-wrap">
              <div class="horas-label">
                <span>Horas trabalhadas</span>
                <strong>{{ diaAbertoData.horasTrabalhadas }}</strong>
              </div>
              <div class="horas-bar">
                <div class="horas-fill" :style="{ width: diaAbertoData.pctHoras + '%' }"></div>
              </div>
              <span class="horas-meta">Meta: {{ metaDiariaLabel }}</span>
            </div>
          </div>
        </div>

        <!-- Sem dia selecionado -->
        <div class="detail-card empty-detail" v-else>
          <span class="empty-ico">📅</span>
          <p>Clique em um dia com batidas para ver os detalhes</p>
        </div>

        <!-- Resumo do mês -->
        <div class="summary-card">
          <h3 class="sc-title">Resumo do Mês</h3>
          <div class="sc-items">
            <div class="sc-item">
              <span class="sc-label">Banco de Horas</span>
              <span class="sc-val" :class="bancoHorasClass">{{ bancoHoras }}</span>
            </div>
            <div class="sc-item">
              <span class="sc-label">Horas Esperadas</span>
              <span class="sc-val">{{ resumo.horasEsperadas }}h</span>
            </div>
            <div class="sc-item">
              <span class="sc-label">Horas Trabalhadas</span>
              <span class="sc-val">{{ resumo.horasTrabalhadas }}h</span>
            </div>
            <div class="sc-item">
              <span class="sc-label">Faltas</span>
              <span class="sc-val red">{{ resumo.faltas }} dia{{ resumo.faltas !== 1 ? 's' : '' }}</span>
            </div>
          </div>
          <div class="sc-progress">
            <div class="sc-prog-label">
              <span>Progresso do mês</span>
              <span>{{ pctMesTotalDias }}%</span>
            </div>
            <div class="sc-prog-bar"><div class="sc-prog-fill" :style="{ width: pctMesTotalDias + '%' }"></div></div>
          </div>
        </div>

        <!-- Bater Ponto Agora -->
        <div class="bater-card" v-if="isMesAtual">
          <div class="bater-relogio">{{ horaAtual }}</div>
          <div class="bater-data">{{ dataHojeFormatada }}</div>
          <button
            class="bater-btn"
            :class="baterBtnClass"
            :disabled="baterPontoLoading"
            @click="baterPonto"
          >
            <span v-if="baterPontoLoading" class="btn-spin"></span>
            <template v-else>{{ proximaBatidaLabel }}</template>
          </button>
          <div v-if="ultimaBatida" class="ultima-batida">
            Última: <strong>{{ ultimaBatida }}</strong>
          </div>
          <div v-if="baterMsg" class="bater-msg" :class="baterMsgClass">{{ baterMsg }}</div>
        </div>

        <!-- Justificar falta via Abono -->
        <router-link to="/abono-faltas" class="legado-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Justificar uma Falta
        </router-link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/plugins/axios'

const hoje = new Date()
const anoAtual = ref(hoje.getFullYear())
const mesAtual = ref(hoje.getMonth()) // 0-index
const loading = ref(true)
const loaded = ref(false)
const registros = ref([]) // dados do backend
const diaAberto = ref(null)

// ── Bater Ponto ───────────────────────────────────────────────
const horaAtual = ref('')
const baterPontoLoading = ref(false)
const baterMsg = ref('')
const baterMsgClass = ref('ok')
const ultimaBatida = ref('')
let clockInterval = null
const atualizarRelogio = () => {
  horaAtual.value = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
const dataHojeFormatada = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
const isMesAtual = computed(() => anoAtual.value === hoje.getFullYear() && mesAtual.value === hoje.getMonth())

// ── Regime de Ponto (carregado da API) ─────────────────────────
const regimePonto = ref('4_batidas') // default enquanto carrega

// ── Horários / Tolerância (da config do sistema) ────────────────
const pontoHoraEntrada = ref('08:00')
const pontoHoraSaida   = ref('18:00')
const pontoTolerancia  = ref(15)
const pontoIntervaloAlmoco = ref(120) // minutos, default 2h

// Jornada diária líquida = (saida - entrada) - intervalo_almoco
const metaDiariaMin = computed(() => {
  const [he, me] = pontoHoraEntrada.value.split(':').map(Number)
  const [hs, ms] = pontoHoraSaida.value.split(':').map(Number)
  const bruto = (hs * 60 + ms) - (he * 60 + me)
  const liquido = bruto - pontoIntervaloAlmoco.value
  return liquido > 0 ? liquido : 480 // fallback 8h
})
const metaDiariaLabel = computed(() => {
  const h = Math.floor(metaDiariaMin.value / 60)
  const m = metaDiariaMin.value % 60
  return m ? `${h}h${String(m).padStart(2,'0')}min` : `${h}h`
})


const REGIMES = {
  '4_batidas': {
    tipos:  ['entrada', 'saida_almoco', 'retorno_almoco', 'saida'],
    labels: ['🕐 Registrar Entrada', '☕ Saída p/ Almoço', '🔙 Retorno do Almoço', '🏠 Registrar Saída'],
    cores:  ['bater-entrada', 'bater-almoco', 'bater-retorno', 'bater-saida'],
  },
  '2_batidas': {
    tipos:  ['entrada', 'saida'],
    labels: ['🕐 Registrar Entrada', '🏠 Registrar Saída'],
    cores:  ['bater-entrada', 'bater-saida'],
  },
}

const sequenciaTipos   = computed(() => REGIMES[regimePonto.value]?.tipos  ?? REGIMES['4_batidas'].tipos)
const sequenciaLabels  = computed(() => REGIMES[regimePonto.value]?.labels ?? REGIMES['4_batidas'].labels)
const sequenciaCores   = computed(() => REGIMES[regimePonto.value]?.cores  ?? REGIMES['4_batidas'].cores)

const batidasHoje = computed(() => {
  if (!isMesAtual.value) return []
  const reg = registros.value.find(r => r.dia === hoje.getDate())
  return reg?.batidas ?? []
})
const proximaBatidaIdx   = computed(() => Math.min(batidasHoje.value.length, sequenciaTipos.value.length - 1))
const proximaBatidaLabel = computed(() => sequenciaLabels.value[proximaBatidaIdx.value] ?? '✅ Expediente encerrado')
const baterBtnClass = computed(() => sequenciaCores.value[proximaBatidaIdx.value] ?? '')
const expedienteEncerrado = computed(() => batidasHoje.value.length >= sequenciaTipos.value.length)

const baterPonto = async () => {
  if (baterPontoLoading.value || expedienteEncerrado.value) return
  baterPontoLoading.value = true; baterMsg.value = ''
  const tipo = sequenciaTipos.value[proximaBatidaIdx.value] ?? 'saida'
  const hora = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  try {
    await api.post('/api/v3/ponto/registro', { data: hoje.toISOString().slice(0, 10), hora: hora + ':00', tipo })
    ultimaBatida.value = hora
    baterMsg.value = `✅ Batida registrada às ${hora}!`; baterMsgClass.value = 'ok'
    const idx = registros.value.findIndex(r => r.dia === hoje.getDate())
    if (idx >= 0) registros.value[idx].batidas.push({ hora, tipo })
    else registros.value.push({ dia: hoje.getDate(), batidas: [{ hora, tipo }] })
  } catch { baterMsg.value = '❌ Erro ao registrar.'; baterMsgClass.value = 'err' }
  finally { baterPontoLoading.value = false; setTimeout(() => { baterMsg.value = '' }, 4000) }
}

onMounted(async () => {
  atualizarRelogio(); clockInterval = setInterval(atualizarRelogio, 1000)
  // Carrega a config completa de ponto (regime + horários + tolerância)
  try {
    const { data } = await api.get('/api/v3/ponto/config')
    if (data?.regime)           regimePonto.value           = data.regime
    if (data?.hora_entrada)     pontoHoraEntrada.value      = data.hora_entrada
    if (data?.hora_saida)       pontoHoraSaida.value        = data.hora_saida
    if (data?.tolerancia != null)       pontoTolerancia.value       = data.tolerancia
    if (data?.intervalo_almoco != null) pontoIntervaloAlmoco.value  = data.intervalo_almoco
  } catch { /* mantém defaults */ }
  await fetchPonto()
  setTimeout(() => { loaded.value = true }, 80)
})
onUnmounted(() => { if (clockInterval) clearInterval(clockInterval) })

watch([anoAtual, mesAtual], () => fetchPonto())

const apuracaoBackend = ref(null)

const fetchPonto = async () => {
  loading.value = true
  diaAberto.value = null
  try {
    // Formato YYYY-MM esperado pelo endpoint
    const comp = `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`
    const { data } = await api.get('/api/v3/ponto', { params: { competencia: comp } })
    // ✅ FIX: nunca sobrescreve com mock quando a API respondeu normalmente.
    // Mock só entra se a requisição lançar exceção (erro de rede/500).
    registros.value = data.registros ?? []
    apuracaoBackend.value = data.apuracao ?? null
  } catch (e) {
    // Só usa mock se for problema de conexão (API não existe ainda)
    registros.value = gerarRegistrosMock()
    apuracaoBackend.value = null
  } finally {
    loading.value = false
  }
}


// ── MOCK para quando o endpoint ainda não existir (SQLite sem batidas) ─────────
const gerarRegistrosMock = () => {
  const arr = []
  const ano = anoAtual.value
  const mes = mesAtual.value
  const totalDias = new Date(ano, mes + 1, 0).getDate()
  const hojeDia = hoje.getDate()
  const hojeMes = hoje.getMonth()
  const hojeAno = hoje.getFullYear()

  for (let d = 1; d <= totalDias; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    if (dow === 0 || dow === 6) continue // fim de semana

    // Dias futuros: não gera mock
    if (d > hojeDia && mes === hojeMes && ano === hojeAno) continue

    // ⚠️ HOJE: nunca gera mock — deixa sem registro para que o
    //    estado real do banco determine o próximo passo do botão
    if (d === hojeDia && mes === hojeMes && ano === hojeAno) continue

    // Dias passados: gera mock variado
    const r = Math.random()
    if (r > 0.85) {
      arr.push({ dia: d, batidas: [] }) // falta
    } else if (r > 0.7) {
      arr.push({ dia: d, batidas: [
        { hora: '08:05', tipo: 'entrada' },
        { hora: '12:00', tipo: 'saida_almoco' },
        { hora: '13:05', tipo: 'retorno_almoco' },
        // saída esquecida — inconsistente
      ]})
    } else {
      const ent = 8 + (Math.random() > 0.5 ? 0 : 1)
      const min = Math.floor(Math.random() * 30)
      arr.push({ dia: d, batidas: [
        { hora: `0${ent}:${String(min).padStart(2,'0')}`, tipo: 'entrada' },
        { hora: '12:00', tipo: 'saida_almoco' },
        { hora: '13:00', tipo: 'retorno_almoco' },
        { hora: `${(ent + 8 < 10 ? '0' : '') + (ent + 8)}:${String(min).padStart(2,'0')}`, tipo: 'saida' },
      ]})
    }
  }
  return arr
}

// ── CALENDÁRIO ────────────────────────────────────────────────────────────────
const primeiroDiaSemana = computed(() => new Date(anoAtual.value, mesAtual.value, 1).getDay())

const diasDoMes = computed(() => {
  const total = new Date(anoAtual.value, mesAtual.value + 1, 0).getDate()
  const hMes = hoje.getMonth()
  const hAno = hoje.getFullYear()
  const hDia = hoje.getDate()
  const dias = []
  for (let d = 1; d <= total; d++) {
    const dt = new Date(anoAtual.value, mesAtual.value, d)
    const dow = dt.getDay()
    const isHoje = d === hDia && mesAtual.value === hMes && anoAtual.value === hAno
    const isFuturo = new Date(anoAtual.value, mesAtual.value, d) > hoje
    const isFds = dow === 0 || dow === 6
    const reg = registros.value.find(r => r.dia === d)
    let classe = 'cal-day-normal'
    let badge = null
    let badgeClass = ''
    let dots = []
    let titulo = ''
    let temRegistro = false
    if (isFds) {
      classe = 'cal-day-fds'
      titulo = 'Fim de semana'
    } else if (isFuturo) {
      classe = 'cal-day-futuro'
      titulo = 'Dia futuro'
    } else if (!reg || reg.batidas.length === 0) {
      classe = 'cal-day-falta'
      badge = '!'
      badgeClass = 'badge-red'
      titulo = 'Falta — clique para detalhes'
      temRegistro = true  // BUG-EST-06: falta também é clicável
    } else {
      const nBatidas = reg.batidas.length
      const pares = nBatidas % 2 === 0
      if (!pares) {
        classe = 'cal-day-inconsistente'
        dots = ['yellow','yellow']
        titulo = 'Batidas inconsistentes'
        badge = '~'
        badgeClass = 'badge-yellow'
      } else {
        classe = 'cal-day-presente'
        dots = Array(Math.min(nBatidas, 4)).fill('green')
        titulo = `${nBatidas} batidas`
      }
      temRegistro = true
    }
    dias.push({ num: d, classe, dots, badge, badgeClass, titulo, isHoje, isFuturo, isFds, temRegistro, reg })
  }
  return dias
})

const diasUteis = computed(() => diasDoMes.value.filter(d => !d.isFds).length)

const resumo = computed(() => {
  const dias = diasDoMes.value.filter(d => !d.isFds && !d.isFuturo)
  const presentes = dias.filter(d => d.reg?.batidas?.length >= 2 && d.reg.batidas.length % 2 === 0).length
  const inconsistentes = dias.filter(d => d.reg?.batidas?.length > 0 && d.reg.batidas.length % 2 !== 0).length
  const faltas = dias.filter(d => !d.reg || d.reg.batidas.length === 0).length
  let minTotal = 0
  for (const d of dias) {
    if (d.reg?.batidas?.length >= 2) {
      const bs = d.reg.batidas
      for (let i = 0; i < Math.floor(bs.length / 2) * 2; i += 2) {
        const [h1, m1] = bs[i].hora.split(':').map(Number)
        const [h2, m2] = bs[i + 1].hora.split(':').map(Number)
        minTotal += (h2 * 60 + m2) - (h1 * 60 + m1)
      }
    }
  }
  const horasTrabalhadas = (minTotal / 60).toFixed(1)
  const horasEsperadas = ((diasUteis.value * metaDiariaMin.value) / 60).toFixed(1)
  return { presentes, inconsistentes, faltas, horasTrabalhadas, horasEsperadas }
})

const bancoHoras = computed(() => {
  const diff = parseFloat(resumo.value.horasTrabalhadas) - resumo.value.horasEsperadas
  const s = diff >= 0 ? '+' : ''
  return `${s}${diff.toFixed(1)}h`
})
const bancoHorasClass = computed(() => parseFloat(resumo.value.horasTrabalhadas) >= resumo.value.horasEsperadas ? 'green' : 'red')

const pctMesTotalDias = computed(() => {
  const diaPassados = diasDoMes.value.filter(d => !d.isFuturo && !d.isFds).length
  return Math.min(100, Math.round((diaPassados / diasUteis.value) * 100)) || 0
})

const mesAnoExibido = computed(() => {
  return new Date(anoAtual.value, mesAtual.value, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
})

const isMesFuturo = computed(() => {
  return anoAtual.value > hoje.getFullYear() || (anoAtual.value === hoje.getFullYear() && mesAtual.value >= hoje.getMonth())
})

const mudarMes = (delta) => {
  let m = mesAtual.value + delta
  let a = anoAtual.value
  if (m < 0) { m = 11; a-- }
  if (m > 11) { m = 0; a++ }
  if (new Date(a, m, 1) > hoje) return
  mesAtual.value = m
  anoAtual.value = a
}

const abrirDia = (dia) => { diaAberto.value = dia.num }

const diaAbertoData = computed(() => {
  if (!diaAberto.value) return null
  const dia = diasDoMes.value.find(d => d.num === diaAberto.value)
  if (!dia?.reg) return { batidas: [], statusLabel: 'Falta', statusIcon: '❌', statusClass: 'sc-red', horasTrabalhadas: null }
  const tipoLabels = { entrada: 'Entrada', saida_almoco: 'Saída Almoço', retorno_almoco: 'Retorno Almoço', saida: 'Saída' }
  const tipoIcons = { entrada: '🟢', saida_almoco: '🟡', retorno_almoco: '🔵', saida: '🔴' }
  const batidas = dia.reg.batidas.map(b => ({ ...b, tipoLabel: tipoLabels[b.tipo] || b.tipo, tipoIcon: tipoIcons[b.tipo] || '⚪' }))
  const inconsistente = batidas.length % 2 !== 0
  let minTotal = 0
  for (let i = 0; i < Math.floor(batidas.length / 2) * 2; i += 2) {
    const [h1, m1] = batidas[i].hora.split(':').map(Number)
    const [h2, m2] = batidas[i + 1].hora.split(':').map(Number)
    minTotal += (h2 * 60 + m2) - (h1 * 60 + m1)
  }
  const horasTrabalhadas = minTotal > 0 ? `${Math.floor(minTotal / 60)}h${String(minTotal % 60).padStart(2,'0')}min` : null
  const pctHoras = Math.min(100, Math.round((minTotal / metaDiariaMin.value) * 100))
  return {
    batidas,
    horasTrabalhadas,
    pctHoras,
    statusLabel: inconsistente ? 'Batidas Inconsistentes' : 'Presente',
    statusIcon: inconsistente ? '⚠️' : '✅',
    statusClass: inconsistente ? 'sc-yellow' : 'sc-green',
  }
})

const diaFormatado = computed(() => {
  if (!diaAberto.value) return ''
  return new Date(anoAtual.value, mesAtual.value, diaAberto.value).toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
})
</script>

<style scoped>
/* ── PAGE ──────────────────────────────────────────────────── */
.ponto-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* ── HERO ──────────────────────────────────────────────────── */
.hero {
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1a2f52 50%, #1e1b4b 100%);
  border-radius: 22px; padding: 28px 36px; overflow: hidden;
  opacity: 0; transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 260px; height: 260px; background: #4f46e5; right: -60px; top: -80px; }
.hs2 { width: 180px; height: 180px; background: #0d9488; bottom: -50px; right: 250px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 5px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; text-transform: capitalize; }
.hero-nav { display: flex; align-items: center; gap: 14px; }
.nav-btn {
  width: 36px; height: 36px; border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.07); border-radius: 10px; color: #fff;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.18s;
}
.nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.15); }
.nav-btn:disabled { opacity: 0.3; cursor: default; }
.nav-mes { font-size: 15px; font-weight: 800; color: #fff; text-transform: capitalize; letter-spacing: -0.01em; white-space: nowrap; }
.hero-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.hstat {
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px; padding: 12px 18px; text-align: center; min-width: 80px;
}
.hstat-yellow { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.2); }
.hstat-red { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.2); }
.hstat-blue { background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); }
.hstat-val { display: block; font-size: 22px; font-weight: 900; color: #fff; }
.hstat-yellow .hstat-val { color: #fbbf24; }
.hstat-red .hstat-val { color: #f87171; }
.hstat-blue .hstat-val { color: #60a5fa; }
.hstat-label { display: block; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-top: 3px; }

/* ── ESTADOS ───────────────────────────────────────────────── */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 80px; color: #64748b; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }
.state-box p { font-size: 15px; font-weight: 500; margin: 0; }

/* ── LAYOUT ────────────────────────────────────────────────── */
.ponto-layout {
  display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start;
  opacity: 0; transform: translateY(10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.ponto-layout.loaded { opacity: 1; transform: none; }

/* ── CALENDÁRIO ─────────────────────────────────────────────── */
.calendar-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; }
.cal-header {
  display: grid; grid-template-columns: repeat(7, 1fr);
  background: #f8fafc; border-bottom: 1px solid #f1f5f9;
  padding: 10px 8px;
}
.cal-dow { text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr);
  gap: 4px; padding: 12px;
}
.cal-day {
  aspect-ratio: 1; border-radius: 12px; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  position: relative; transition: all 0.15s; user-select: none;
}
.cal-empty { background: transparent; cursor: default; }
.cal-day-normal { background: #f8fafc; }
.cal-day-fds { background: #f8fafc; opacity: 0.5; cursor: default; }
.cal-day-futuro { background: #f8fafc; opacity: 0.3; cursor: default; }
.cal-day-presente { background: #f0fdf4; }
.cal-day-presente:hover { background: #dcfce7; transform: scale(1.05); }
.cal-day-inconsistente { background: #fffbeb; }
.cal-day-inconsistente:hover { background: #fef3c7; transform: scale(1.05); }
.cal-day-falta { background: #fef2f2; cursor: pointer; /* BUG-EST-06 */ }
.cal-day-falta:hover { background: #fee2e2; transform: scale(1.05); }
.cal-selected { outline: 2px solid #6366f1 !important; outline-offset: 1px; }
.cal-hoje .day-num { background: #6366f1; color: #fff; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.day-num { font-size: 12px; font-weight: 700; color: #334155; }
.day-dots { display: flex; gap: 2px; margin-top: 3px; }
.day-dot { width: 4px; height: 4px; border-radius: 50%; }
.day-dot.green { background: #16a34a; }
.day-dot.yellow { background: #d97706; }
.day-dot.red { background: #dc2626; }
.day-dot.gray { background: #94a3b8; }
.day-badge {
  position: absolute; top: 3px; right: 3px;
  width: 14px; height: 14px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 8px; font-weight: 800;
}
.badge-red { background: #ef4444; color: #fff; }
.badge-yellow { background: #f59e0b; color: #fff; }
.cal-legend { display: flex; gap: 16px; padding: 12px 16px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; }
.leg-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; font-weight: 600; }
.leg-dot { width: 10px; height: 10px; border-radius: 50%; }
.leg-dot.green { background: #16a34a; }
.leg-dot.yellow { background: #f59e0b; }
.leg-dot.red { background: #ef4444; }
.leg-dot.gray { background: #e2e8f0; }

/* ── PAINEL LATERAL ─────────────────────────────────────────── */
.side-panel { display: flex; flex-direction: column; gap: 14px; }

/* DETAIL CARD */
.detail-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
  padding: 20px; transition: box-shadow 0.2s;
}
.detail-card:hover { box-shadow: 0 8px 32px -8px rgba(0,0,0,0.1); }
.empty-detail { display: flex; flex-direction: column; align-items: center; padding: 32px; text-align: center; }
.empty-ico { font-size: 36px; margin-bottom: 10px; }
.empty-detail p { font-size: 13px; color: #94a3b8; margin: 0; }
.dc-header { margin-bottom: 16px; }
.dc-title-wrap { display: flex; align-items: center; gap: 12px; }
.dc-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.sc-green { background: #f0fdf4; }
.sc-yellow { background: #fffbeb; }
.sc-red { background: #fef2f2; }
.dc-title { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 2px; text-transform: capitalize; }
.dc-sub { font-size: 11px; color: #64748b; font-weight: 600; }
.batidas-list { display: flex; flex-direction: column; gap: 8px; }
.batida-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; border-radius: 12px; border: 1px solid #f1f5f9;
  background: #f8fafc;
}
.batida-tipo { font-size: 12px; font-weight: 700; color: #64748b; flex: 1; }
.batida-hora { font-family: monospace; font-size: 16px; font-weight: 900; color: #1e293b; }
.batida-icone { font-size: 16px; margin-left: 8px; }
.no-batida { display: flex; flex-direction: column; align-items: center; padding: 20px; text-align: center; color: #94a3b8; font-size: 13px; gap: 8px; }
.horas-info { margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
.horas-bar-wrap {}
.horas-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
.horas-label strong { color: #1e293b; }
.horas-bar { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.horas-fill { height: 100%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 99px; transition: width 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
.horas-meta { font-size: 11px; color: #94a3b8; display: block; margin-top: 4px; }

/* SUMMARY CARD */
.summary-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px; }
.sc-title { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 14px; }
.sc-items { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.sc-item { display: flex; justify-content: space-between; align-items: center; }
.sc-label { font-size: 12px; color: #64748b; font-weight: 600; }
.sc-val { font-size: 14px; font-weight: 800; color: #1e293b; }
.sc-val.green { color: #16a34a; }
.sc-val.red { color: #dc2626; }
.sc-progress {}
.sc-prog-label { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 6px; }
.sc-prog-bar { height: 8px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.sc-prog-fill { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 99px; transition: width 1s cubic-bezier(0.22, 1, 0.36, 1); }

/* ── CARD BATER PONTO ─────────────────────────────────────── */
.bater-card {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  border-radius: 16px; padding: 18px; text-align: center;
  display: flex; flex-direction: column; gap: 10px;
}
.bater-relogio {
  font-size: 32px; font-weight: 900; color: #fff;
  font-variant-numeric: tabular-nums; letter-spacing: 0.04em;
  font-family: 'Courier New', monospace;
}
.bater-data { font-size: 11px; color: #64748b; font-weight: 600; text-transform: capitalize; }
.bater-btn {
  padding: 13px; border: none; border-radius: 12px; font-size: 14px;
  font-weight: 800; cursor: pointer; font-family: inherit;
  transition: all 0.18s; display: flex; align-items: center; justify-content: center; gap: 8px;
}
.bater-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.bater-btn.bater-entrada  { background: #16a34a; color: #fff; }
.bater-btn.bater-almoco   { background: #d97706; color: #fff; }
.bater-btn.bater-retorno  { background: #2563eb; color: #fff; }
.bater-btn.bater-saida    { background: #dc2626; color: #fff; }
.bater-btn:not(:disabled):hover { transform: translateY(-1px); filter: brightness(1.12); }
.ultima-batida { font-size: 11px; color: #64748b; }
.ultima-batida strong { color: #94a3b8; }
.bater-msg { font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 10px; }
.bater-msg.ok  { background: rgba(22,163,74,0.15); color: #16a34a; }
.bater-msg.err { background: rgba(220,38,38,0.15); color: #dc2626; }
.btn-spin { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }

/* BOTÃO LEGADO */
.legado-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(135deg, #0f172a, #1e3a5f);
  color: #fff; text-decoration: none; border-radius: 14px; padding: 13px;
  font-size: 13px; font-weight: 700; transition: all 0.2s;
}
.legado-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,23,42,0.3); }

@media (max-width: 900px) {
  .ponto-layout { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  /* Hero compacto */
  .hero-inner { flex-direction: column; gap: 20px; }
  .hero-title { font-size: 26px; }
  .hero-eyebrow { font-size: 12px; }
  .hero-stats { gap: 10px; }
  .hstat { padding: 12px 16px; }
  .hstat-val { font-size: 20px; }

  /* Calendário menor */
  .cal-day { min-height: 46px; font-size: 12px; }
  .day-badge { font-size: 9px; padding: 1px 4px; }

  /* Botão de ponto touch-friendly */
  .bater-btn {
    min-height: 56px;
    font-size: 15px;
    border-radius: 16px;
  }
}
</style>
