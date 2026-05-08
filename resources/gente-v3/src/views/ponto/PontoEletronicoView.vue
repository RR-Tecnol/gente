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
          <p class="hero-sub">
            {{ mesAnoExibido }} · {{ diasUteis }} dias úteis
            <template v-if="pontoFuncionarioAlvoId">
              <br><span class="hero-sub-alvo">Espelho do servidor #{{ pontoFuncionarioAlvoId }} (somente leitura; batidas desativadas)</span>
            </template>
          </p>
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
          <div v-if="podeAlternarVisao" class="visao-toggle" title="Alternar visão para testes de fluxo">
            <button :class="['visao-btn', { active: modoVisao === 'funcionario' }]" @click="modoVisao = 'funcionario'">
              👤 Funcionário
            </button>
            <button :class="['visao-btn', { active: modoVisao === 'gestor' }]" @click="modoVisao = 'gestor'">
              👥 Gestor
            </button>
          </div>
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
      <div v-if="dadosPontoIndisponiveis" class="state-warn">
        ⚠️ Não foi possível carregar os registros reais de ponto agora. Exibindo grade sem dados até reconectar.
      </div>

      <!-- Dashboard superior: Ponto + resumo acionável -->
      <div class="top-dashboard" v-if="isMesAtual">
        <div class="ponto-priority-stack">
          <div class="bater-card bater-card-top">
            <div class="bater-relogio">{{ horaAtual }}</div>
            <div class="bater-data">{{ dataHojeFormatada }}</div>
            <button
              class="bater-btn"
              :class="baterBtnClass"
              :disabled="baterPontoLoading || expedienteEncerrado || !podeRegistrarBatidasPessoais"
              @click="baterPonto"
            >
              <span v-if="baterPontoLoading" class="btn-spin"></span>
              <template v-else>{{ proximaBatidaLabel }}</template>
            </button>
            <div v-if="ultimaBatida" class="ultima-batida">
              Última: <strong>{{ ultimaBatida }}</strong>
            </div>
            <div v-if="baterMsg" class="bater-msg" :class="baterMsgClass">{{ baterMsg }}</div>
            <button
              class="reset-teste-btn"
              :disabled="baterPontoLoading"
              @click="resetDiaTeste"
              title="Temporário para testes locais"
            >
              🔄 Reiniciar batidas do dia (teste)
            </button>
          </div>

          <div v-if="mostrarInsightGestor" class="risk-panel risk-panel-below-point">
            <div class="risk-head">
              <div class="risk-head-text">
                <strong>Mapa de Risco Operacional</strong>
                <small>Radar por setor para ação antecipada da gestão</small>
              </div>
            </div>
            <div v-if="riskLoading" class="risk-state">Carregando mapa de risco...</div>
            <div v-else-if="riskErro" class="risk-state err">{{ riskErro }}</div>
            <div v-else-if="!riskSetoresExibidos.length" class="risk-state">Sem dados de risco para a competência selecionada.</div>
            <div v-else class="risk-table">
              <div class="risk-row risk-head-row">
                <span>Setor</span>
                <span class="num">Índice</span>
                <span>Severidade</span>
                <span class="num">Déficit</span>
                <span class="num">Faltas</span>
                <span class="num">Inconsist.</span>
              </div>
              <div v-for="setor in riskSetoresExibidos" :key="setor.setor" class="risk-row risk-row-body">
                <span class="setor">{{ setor.setor }}</span>
                <span class="num">{{ formatarIndiceRisco(setor.indice_risco) }}</span>
                <span>
                  <b :class="['sev-badge', `sev-${setor.severidade}`]">{{ setor.severidade }}</b>
                </span>
                <span class="num">{{ formatarHorasRisco(setor.deficit_total_horas) }}</span>
                <span class="num">{{ formatarInteiroRisco(setor.faltas_total) }}</span>
                <span class="num">{{ formatarInteiroRisco(setor.jornadas_inconsistentes) }}</span>
              </div>
            </div>
            <div v-if="riskSetorTop" class="risk-actions">
              <button type="button" class="risk-btn criticos" @click="acaoVerFuncionariosCriticos">
                Ver funcionários críticos
              </button>
              <button type="button" class="risk-btn escala" @click="acaoAbrirEscalaSetor">
                Abrir escala do setor
              </button>
              <button type="button" class="risk-btn notificar" @click="acaoNotificarCoordenacao">
                Notificar coordenação
              </button>
            </div>
            <small v-if="riskAcaoMsg" class="risk-acao-msg">{{ riskAcaoMsg }}</small>
          </div>
        </div>

        <div class="summary-card summary-card-top">
          <h3 class="sc-title">Resumo do Mês</h3>
          <div class="sc-items">
            <div class="sc-item">
              <span class="sc-label sc-label-wrap">
                Banco de Horas
                <button class="help-btn" type="button" @click.stop="toggleHelp('banco_horas')">?</button>
                <span class="help-tip" :class="{ open: helpOpen === 'banco_horas' }">
                  Saldo acumulado do mês: horas a mais (positivo) ou a menos (negativo) em relação ao esperado.
                </span>
              </span>
              <span class="sc-val" :class="bancoHorasClass">{{ bancoHoras }}</span>
            </div>
            <div class="sc-item">
              <span class="sc-label sc-label-wrap">
                Horas Esperadas
                <button class="help-btn" type="button" @click.stop="toggleHelp('horas_esperadas')">?</button>
                <span class="help-tip" :class="{ open: helpOpen === 'horas_esperadas' }">
                  Total de horas que deveriam ser cumpridas no mês conforme sua jornada configurada.
                </span>
              </span>
              <span class="sc-val">{{ resumo.horasEsperadas }}h</span>
            </div>
            <div class="sc-item">
              <span class="sc-label sc-label-wrap">
                Horas Trabalhadas
                <button class="help-btn" type="button" @click.stop="toggleHelp('horas_trabalhadas')">?</button>
                <span class="help-tip" :class="{ open: helpOpen === 'horas_trabalhadas' }">
                  Total de horas registradas nas batidas válidas de ponto dentro do mês.
                </span>
              </span>
              <span class="sc-val">{{ resumo.horasTrabalhadas }}h</span>
            </div>
            <div class="sc-item">
              <span class="sc-label sc-label-wrap">
                Faltas
                <button class="help-btn" type="button" @click.stop="toggleHelp('faltas')">?</button>
                <span class="help-tip" :class="{ open: helpOpen === 'faltas' }">
                  Quantidade de dias úteis sem registro válido de jornada.
                </span>
              </span>
              <span class="sc-val red">{{ resumo.faltas }} dia{{ resumo.faltas !== 1 ? 's' : '' }}</span>
            </div>
          </div>
          <div class="dashboard-insight">
            <strong>{{ insightFechamentoTitulo }}</strong>
            <span>{{ insightFechamentoTexto }}</span>
            <small v-if="insightFechamentoDetalhe">{{ insightFechamentoDetalhe }}</small>
          </div>
          <div v-if="mostrarInsightGestor" class="dashboard-insight gestor">
            <strong>Projeção de fechamento (gestor)</strong>
            <span>{{ insightProjecaoGestor }}</span>
          </div>
          <div class="sc-progress">
            <div class="sc-prog-label">
              <span>Progresso do mês</span>
              <span>{{ pctMesTotalDias }}%</span>
            </div>
            <div class="sc-prog-bar"><div class="sc-prog-fill" :style="{ width: pctMesTotalDias + '%' }"></div></div>
          </div>
        </div>
      </div>

      <!-- CALENDÁRIO ──────────────────────────────────────────── -->
      <div class="calendar-shell">
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
            @click="(!dia.isFuturo && dia.temRegistro) && abrirDia(dia)"
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
              <span class="horas-meta">Meta: {{ metaDiaAbertoLabel }}</span>
            </div>
          </div>
          <div v-if="diaAbertoData?.inconsistente" class="recon-card">
            <strong>🛠️ Reconciliação automática sugerida</strong>
            <p>{{ diaAbertoData?.sugestaoReconciliacao }}</p>
            <button class="recon-btn" :disabled="reconciliacaoLoading" @click="solicitarReconciliacaoDia">
              {{ reconciliacaoLoading ? 'Enviando...' : 'Enviar para aprovação da gestão' }}
            </button>
            <small v-if="reconciliacaoMsg">{{ reconciliacaoMsg }}</small>
          </div>
        </div>

        <!-- Sem dia selecionado -->
        <div class="detail-card empty-detail" v-else>
          <span class="empty-ico">📅</span>
          <p>Clique em um dia com batidas para ver os detalhes</p>
        </div>

        <!-- Justificar falta via Abono -->
        <router-link to="/abono-faltas" class="legado-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Justificar uma Falta
        </router-link>
      </div>

    </div>

    <transition name="modal-fade">
      <div v-if="saidaModalOpen" class="modal-overlay" @click.self="cancelarModalSaidaAntecipada">
        <div class="modal-card">
          <h3 class="modal-title">⚠️ Saída antecipada detectada</h3>
          <p class="modal-text" v-if="saidaModalStep === 1">
            Você está tentando encerrar antes do horário previsto.
            Confirme se deseja continuar.
          </p>
          <p class="modal-text" v-else>
            Esta ação pode gerar <strong>horas negativas</strong> e possível
            impacto salarial.
          </p>

          <div class="modal-resumo">
            <div><span>Trabalhadas hoje:</span> <strong>{{ saidaModalResumo.trabalhadas }}</strong></div>
            <div><span>Meta do dia:</span> <strong>{{ saidaModalResumo.meta }}</strong></div>
            <div>
              <span>Diferença estimada:</span>
              <strong :class="saidaModalResumo.deltaMin < 0 ? 'neg' : 'pos'">
                {{ saidaModalResumo.deltaLabel }}
              </strong>
            </div>
          </div>

          <div class="modal-actions">
            <button class="modal-btn secondary" :disabled="saidaModalLoading" @click="cancelarModalSaidaAntecipada">
              Cancelar
            </button>
            <button
              v-if="saidaModalStep === 1"
              class="modal-btn primary"
              :disabled="saidaModalLoading"
              @click="avancarModalSaidaAntecipada"
            >
              Continuar
            </button>
            <button
              v-else
              class="modal-btn danger"
              :disabled="saidaModalLoading"
              @click="confirmarModalSaidaAntecipada"
            >
              Confirmo encerrar expediente
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
/** Quando vindo da Escala (query), espelho de outro servidor autorizado pelo escopo. */
const pontoFuncionarioAlvoId = ref(null)
const podeAlternarVisao = computed(() => authStore.isAdmin)
const modoVisao = ref('funcionario')
const mostrarInsightGestor = computed(() => podeAlternarVisao.value && modoVisao.value === 'gestor')
const riskLoading = ref(false)
const riskErro = ref('')
const riskSetores = ref([])
const riskAcaoMsg = ref('')
const riskSetorTop = computed(() => riskSetores.value[0] || null)
const riskSetoresExibidos = computed(() => riskSetores.value.slice(0, 6))
const helpOpen = ref(null)
const toggleHelp = (key) => {
  helpOpen.value = helpOpen.value === key ? null : key
}

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
const podeRegistrarBatidasPessoais = computed(() => !pontoFuncionarioAlvoId.value)

// ── Regime de Ponto (carregado da API) ─────────────────────────
const regimePonto = ref('4_batidas') // default enquanto carrega

// ── Horários / Tolerância (da config do sistema) ────────────────
const pontoHoraEntrada = ref('08:00')
const pontoHoraSaida   = ref('18:00')
const pontoTolerancia  = ref(15)
const pontoIntervaloAlmoco = ref(120) // minutos, default 2h

const saidaModalOpen = ref(false)
const saidaModalStep = ref(1)
const saidaModalLoading = ref(false)
const saidaModalResumo = ref({
  trabalhadas: '0h00',
  meta: '0h00',
  deltaMin: 0,
  deltaLabel: '0h00',
})
let saidaModalResolver = null
const reconciliacaoLoading = ref(false)
const reconciliacaoMsg = ref('')

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
  const reg = registros.value.find(r => Number(r.dia) === hoje.getDate())
  return reg?.batidas ?? []
})
const calcularMinutosTrabalhadosBatidas = (batidas = []) => {
  let minTotal = 0
  for (let i = 0; i < Math.floor(batidas.length / 2) * 2; i += 2) {
    const [h1, m1] = String(batidas[i]?.hora || '00:00').split(':').map(Number)
    const [h2, m2] = String(batidas[i + 1]?.hora || '00:00').split(':').map(Number)
    minTotal += (h2 * 60 + m2) - (h1 * 60 + m1)
  }
  return Math.max(0, minTotal)
}
const proximaBatidaIdx   = computed(() => Math.min(batidasHoje.value.length, sequenciaTipos.value.length - 1))
const proximaBatidaLabel = computed(() => {
  if (expedienteEncerrado.value) return '✅ Expediente encerrado'
  return sequenciaLabels.value[proximaBatidaIdx.value] ?? '✅ Expediente encerrado'
})
const baterBtnClass = computed(() => sequenciaCores.value[proximaBatidaIdx.value] ?? '')
const expedienteEncerrado = computed(() => batidasHoje.value.length >= sequenciaTipos.value.length)

const resetDiaTeste = async () => {
  if (baterPontoLoading.value) return
  const ok = window.confirm(
    'Isso vai apagar as batidas de HOJE para o seu usuário neste ambiente de teste. Deseja continuar?'
  )
  if (!ok) return

  baterPontoLoading.value = true
  baterMsg.value = ''
  try {
    const { data } = await api.post('/api/v3/ponto/reset-dia-teste', { confirmar_reset_teste: true })
    await fetchPonto()
    ultimaBatida.value = ''
    baterMsg.value = `✅ ${data?.mensagem || 'Reset de teste concluído.'}`
    baterMsgClass.value = 'ok'
  } catch (e) {
    const msg = e?.response?.data?.erro || 'Falha ao reiniciar batidas de teste.'
    baterMsg.value = `❌ ${msg}`
    baterMsgClass.value = 'err'
  } finally {
    baterPontoLoading.value = false
    setTimeout(() => { baterMsg.value = '' }, 5000)
  }
}

const formatarMinutos = (min) => {
  const sinal = min < 0 ? '-' : ''
  const abs = Math.abs(min)
  const h = Math.floor(abs / 60)
  const m = abs % 60
  return `${sinal}${h}h${String(m).padStart(2, '0')}`
}

const estimarResumoSaida = (horaSaida) => {
  const toMin = (hhmm) => {
    const [h, m] = String(hhmm).split(':').map(Number)
    return h * 60 + m
  }

  const eventos = [...batidasHoje.value.map(b => ({ tipo: b.tipo, min: toMin(b.hora) }))]
  eventos.push({ tipo: 'saida', min: toMin(horaSaida) })

  let aberto = null
  let trabalhados = 0
  for (const ev of eventos) {
    if ((ev.tipo === 'entrada' || ev.tipo === 'retorno_almoco') && aberto == null) {
      aberto = ev.min
      continue
    }
    if ((ev.tipo === 'saida_almoco' || ev.tipo === 'saida') && aberto != null) {
      if (ev.min >= aberto) trabalhados += (ev.min - aberto)
      aberto = null
    }
  }

  const meta = metaMinDia(hoje.getDate())
  const delta = trabalhados - meta
  return {
    trabalhadas: formatarMinutos(trabalhados),
    meta: formatarMinutos(meta),
    deltaMin: delta,
    deltaLabel: formatarMinutos(delta),
  }
}

const abrirModalSaidaAntecipada = (horaSaida) => {
  saidaModalResumo.value = estimarResumoSaida(horaSaida)
  saidaModalStep.value = 1
  saidaModalOpen.value = true
  saidaModalLoading.value = false
  return new Promise((resolve) => { saidaModalResolver = resolve })
}

const cancelarModalSaidaAntecipada = () => {
  saidaModalOpen.value = false
  const resolve = saidaModalResolver
  saidaModalResolver = null
  if (resolve) resolve(false)
}

const avancarModalSaidaAntecipada = () => {
  saidaModalStep.value = 2
}

const confirmarModalSaidaAntecipada = () => {
  saidaModalLoading.value = true
  saidaModalOpen.value = false
  const resolve = saidaModalResolver
  saidaModalResolver = null
  if (resolve) resolve(true)
}

const enviarRegistroPonto = async (tipo, hora, payloadExtra = {}) => {
  return api.post('/api/v3/ponto/registro', {
    data: `${hoje.getFullYear()}-${String(hoje.getMonth()+1).padStart(2,'0')}-${String(hoje.getDate()).padStart(2,'0')}`,
    hora: hora + ':00',
    tipo,
    ...payloadExtra,
  })
}

const montarMensagemPosRegistro = (hora, retorno) => {
  const avisos = []
  if (retorno?.anomalia_jornada_curta) {
    avisos.push('anomalia de jornada curta sinalizada para gestão')
  }
  if (retorno?.sla_deficit_estourado) {
    avisos.push('SLA de déficit diário estourado, gestão notificada')
  }
  if (!avisos.length) {
    return `✅ Batida registrada às ${hora}!`
  }
  return `✅ Batida registrada às ${hora}. Alerta: ${avisos.join(' e ')}.`
}

const baterPonto = async () => {
  if (baterPontoLoading.value) return
  if (expedienteEncerrado.value) {
    baterMsg.value = 'Expediente já encerrado hoje.'
    baterMsgClass.value = 'ok'
    setTimeout(() => { baterMsg.value = '' }, 3500)
    return
  }
  baterPontoLoading.value = true; baterMsg.value = ''
  const tipo = sequenciaTipos.value[proximaBatidaIdx.value] ?? 'saida'
  const hora = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  let payloadExtra = {}

  if (tipo === 'saida') {
    const agoraMin = (() => {
      const [h, m] = hora.split(':').map(Number)
      return h * 60 + m
    })()
    const [hs, ms] = pontoHoraSaida.value.split(':').map(Number)
    const limiteSaidaMin = (hs * 60 + ms) - Number(pontoTolerancia.value || 0)

    if (agoraMin < limiteSaidaMin) {
      const confirmou = await abrirModalSaidaAntecipada(hora)
      if (!confirmou) {
        baterPontoLoading.value = false
        baterMsg.value = 'Saída antecipada cancelada pelo usuário.'
        baterMsgClass.value = 'err'
        setTimeout(() => { baterMsg.value = '' }, 4000)
        return
      }

      payloadExtra = {
        confirmacao_saida_antecipada: true,
        aceite_desconto_salarial: true,
      }
    }
  }

  try {
    const { data } = await enviarRegistroPonto(tipo, hora, payloadExtra)
    ultimaBatida.value = hora
    baterMsg.value = montarMensagemPosRegistro(hora, data); baterMsgClass.value = 'ok'
    const idx = registros.value.findIndex(r => Number(r.dia) === hoje.getDate())
    if (idx >= 0) registros.value[idx].batidas.push({ hora, tipo })
    else registros.value.push({ dia: hoje.getDate(), batidas: [{ hora, tipo }] })
    await fetchPonto()
  } catch (e) {
    const erroCodigo = e?.response?.data?.codigo
    if (tipo === 'saida' && (erroCodigo === 'SAIDA_ANTECIPADA_REQUER_CONFIRMACAO' || erroCodigo === 'SAIDA_COM_DEFICIT_REQUER_CONFIRMACAO')) {
      const confirmou = await abrirModalSaidaAntecipada(hora)
      if (confirmou) {
        try {
          const { data } = await enviarRegistroPonto(tipo, hora, {
            confirmacao_saida_antecipada: true,
            aceite_desconto_salarial: true,
          })
          ultimaBatida.value = hora
          baterMsg.value = montarMensagemPosRegistro(hora, data); baterMsgClass.value = 'ok'
          const idx = registros.value.findIndex(r => Number(r.dia) === hoje.getDate())
          if (idx >= 0) registros.value[idx].batidas.push({ hora, tipo })
          else registros.value.push({ dia: hoje.getDate(), batidas: [{ hora, tipo }] })
          await fetchPonto()
          return
        } catch (e2) {
          const msg2 = e2?.response?.data?.erro || e2?.response?.data?.error || 'Erro ao registrar.'
          baterMsg.value = `❌ ${msg2}`
          baterMsgClass.value = 'err'
          return
        }
      } else {
        baterMsg.value = 'Saída antecipada cancelada pelo usuário.'
        baterMsgClass.value = 'err'
        return
      }
    }

    const msg = e?.response?.data?.erro || e?.response?.data?.error || 'Erro ao registrar.'
    baterMsg.value = `❌ ${msg}`
    baterMsgClass.value = 'err'
  }
  finally { baterPontoLoading.value = false; setTimeout(() => { baterMsg.value = '' }, 4000) }
}

const aplicarQueryInicialPonto = () => {
  const q = route.query
  if (q.competencia && /^\d{4}-\d{2}$/.test(String(q.competencia))) {
    const [y, m] = String(q.competencia).split('-').map(Number)
    if (y >= 1990 && y <= 2100 && m >= 1 && m <= 12) {
      anoAtual.value = y
      mesAtual.value = m - 1
    }
  }
  pontoFuncionarioAlvoId.value = null
  if (q.funcionario_id != null && String(q.funcionario_id).trim() !== '') {
    const fid = parseInt(String(q.funcionario_id), 10)
    if (Number.isFinite(fid) && fid > 0) {
      pontoFuncionarioAlvoId.value = fid
    }
  }
}

onMounted(async () => {
  atualizarRelogio(); clockInterval = setInterval(atualizarRelogio, 1000)
  aplicarQueryInicialPonto()
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
  await fetchHeatmapRisco()
  setTimeout(() => { loaded.value = true }, 80)
})
onUnmounted(() => { if (clockInterval) clearInterval(clockInterval) })

watch([anoAtual, mesAtual], async () => {
  await fetchPonto()
  await fetchHeatmapRisco()
})
watch(mostrarInsightGestor, async (ativo) => {
  if (ativo) await fetchHeatmapRisco()
})

const apuracaoBackend = ref(null)
const dadosPontoIndisponiveis = ref(false)
const diasFeriado = ref(new Set())
const diasEscalaPrevista = ref(new Set())
const metaMinutosEscalaPorDia = ref({})

const diaExigeMeta = (dia) => {
  const isFeriado = diasFeriado.value.has(dia.num)
  const temEscalaPrevista = diasEscalaPrevista.value.has(dia.num)
  // Dia útil não-feriado exige meta por padrão.
  if (!dia.isFds && !isFeriado) return true
  // Domingo/sábado/feriado só exigem meta se houver escala prevista.
  return temEscalaPrevista
}
const metaMinDia = (diaOrNum) => {
  const diaNum = typeof diaOrNum === 'number' ? diaOrNum : Number(diaOrNum?.num)
  const metaEscala = Number(metaMinutosEscalaPorDia.value?.[diaNum] ?? NaN)
  if (Number.isFinite(metaEscala) && metaEscala > 0) return metaEscala
  return metaDiariaMin.value
}

const fetchPonto = async () => {
  loading.value = true
  diaAberto.value = null
  dadosPontoIndisponiveis.value = false
  try {
    // Formato YYYY-MM esperado pelo endpoint
    const comp = `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`
    const params = { competencia: comp }
    if (pontoFuncionarioAlvoId.value) {
      params.funcionario_id = pontoFuncionarioAlvoId.value
    }
    const { data } = await api.get('/api/v3/ponto', { params })
    // ✅ FIX: nunca sobrescreve com mock quando a API respondeu normalmente.
    // Mock só entra se a requisição lançar exceção (erro de rede/500).
    registros.value = data.registros ?? []
    apuracaoBackend.value = data.apuracao ?? null
    diasFeriado.value = new Set((data.dias_feriado ?? []).map(Number))
    diasEscalaPrevista.value = new Set((data.dias_escala_prevista ?? []).map(Number))
    metaMinutosEscalaPorDia.value = data.meta_minutos_escala_por_dia ?? {}
  } catch (e) {
    registros.value = gerarRegistrosMock()
    apuracaoBackend.value = null
    diasFeriado.value = new Set()
    diasEscalaPrevista.value = new Set()
    metaMinutosEscalaPorDia.value = {}
    dadosPontoIndisponiveis.value = true
  } finally {
    loading.value = false
  }
}

const fetchHeatmapRisco = async () => {
  if (!mostrarInsightGestor.value) return
  riskLoading.value = true
  riskErro.value = ''
  riskAcaoMsg.value = ''
  try {
    const comp = `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`
    const { data } = await api.get('/api/v3/ponto/heatmap-risco', { params: { competencia: comp } })
    riskSetores.value = Array.isArray(data?.setores) ? data.setores : []
  } catch (e) {
    riskSetores.value = []
    riskErro.value = e?.response?.data?.erro || 'Falha ao carregar mapa de risco.'
  } finally {
    riskLoading.value = false
  }
}

const acaoVerFuncionariosCriticos = () => {
  if (!riskSetorTop.value) return
  riskAcaoMsg.value = `Abrindo Banco de Horas (Equipe) para setor ${riskSetorTop.value.setor}...`
  router.push({
    path: '/banco-horas',
    query: {
      modo: 'equipe',
      acao: 'criticos',
      setor: riskSetorTop.value.setor,
      competencia: `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`,
    },
  })
}

const acaoAbrirEscalaSetor = () => {
  if (!riskSetorTop.value) return
  riskAcaoMsg.value = `Abrindo Banco de Horas (Equipe) para plano de cobertura em ${riskSetorTop.value.setor}...`
  router.push({
    path: '/banco-horas',
    query: {
      modo: 'equipe',
      acao: 'escala',
      setor: riskSetorTop.value.setor,
      competencia: `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`,
    },
  })
}

const acaoNotificarCoordenacao = () => {
  if (!riskSetorTop.value) return
  riskAcaoMsg.value = `Abrindo Banco de Horas (Equipe) para notificação da coordenação de ${riskSetorTop.value.setor}...`
  router.push({
    path: '/banco-horas',
    query: {
      modo: 'equipe',
      acao: 'notificar',
      setor: riskSetorTop.value.setor,
      competencia: `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}`,
    },
  })
}

const formatarIndiceRisco = (v) => {
  const n = Number(v ?? 0)
  if (!Number.isFinite(n)) return '0,00'
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 })
}
const formatarHorasRisco = (v) => {
  const n = Number(v ?? 0)
  if (!Number.isFinite(n)) return '0h'
  return `${n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}h`
}
const formatarInteiroRisco = (v) => {
  const n = Number(v ?? 0)
  if (!Number.isFinite(n)) return '0'
  return Math.round(n).toLocaleString('pt-BR')
}


// Fallback seguro: não gerar dados fictícios de jornada.
const gerarRegistrosMock = () => []

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
    const isFeriado = diasFeriado.value.has(d)
    const temEscalaPrevista = diasEscalaPrevista.value.has(d)
    const reg = registros.value.find(r => Number(r.dia) === d)
    let classe = 'cal-day-normal'
    let badge = null
    let badgeClass = ''
    let dots = []
    let titulo = ''
    let temRegistro = false
    if (!reg || reg.batidas.length === 0) {
      if (isFds || isFeriado) {
        classe = 'cal-day-fds'
        titulo = isFeriado ? 'Feriado' : 'Fim de semana'
        if (temEscalaPrevista) {
          classe = 'cal-day-falta'
          badge = '!'
          badgeClass = 'badge-red'
          titulo = isFeriado ? 'Feriado com escala prevista (sem batida)' : 'Fim de semana com escala prevista (sem batida)'
          temRegistro = true
        }
      } else if (isFuturo) {
        classe = 'cal-day-futuro'
        titulo = 'Dia futuro'
      } else {
        classe = 'cal-day-falta'
        badge = '!'
        badgeClass = 'badge-red'
        titulo = 'Falta — clique para detalhes'
        temRegistro = true  // BUG-EST-06: falta também é clicável
      }
    } else if (isFuturo) {
      classe = 'cal-day-futuro'
      titulo = 'Dia futuro'
    } else {
      const nBatidas = reg.batidas.length
      const pares = nBatidas % 2 === 0
      const minTrabalhadosDia = calcularMinutosTrabalhadosBatidas(reg.batidas)
      const metaObrigatoriaDia = (!isFds && !isFeriado) || temEscalaPrevista
      const metaDiaMin = metaObrigatoriaDia ? metaMinDia(d) : 0
      const cumpriuMetaDia = minTrabalhadosDia >= metaDiaMin
      if (!pares) {
        classe = 'cal-day-inconsistente'
        dots = ['yellow','yellow']
        titulo = 'Batidas inconsistentes'
        badge = '~'
        badgeClass = 'badge-yellow'
      } else if (!cumpriuMetaDia) {
        classe = 'cal-day-inconsistente'
        dots = ['yellow','yellow']
        titulo = `Jornada incompleta (${Math.floor(minTrabalhadosDia / 60)}h${String(minTrabalhadosDia % 60).padStart(2,'0')})`
        badge = '!'
        badgeClass = 'badge-yellow'
      } else {
        classe = 'cal-day-presente'
        dots = Array(Math.min(nBatidas, 4)).fill('green')
        titulo = metaObrigatoriaDia ? `${nBatidas} batidas` : `${nBatidas} batidas (plantão/extra)`
      }
      temRegistro = true
    }
    dias.push({ num: d, classe, dots, badge, badgeClass, titulo, isHoje, isFuturo, isFds, isFeriado, temEscalaPrevista, temRegistro, reg })
  }
  return dias
})

const diasUteis = computed(() => diasDoMes.value.filter(d => !d.isFds && !d.isFeriado).length)
const diasUteisPassados = computed(() => diasDoMes.value.filter(d => !d.isFds && !d.isFeriado && !d.isFuturo).length)
const diasUteisRestantes = computed(() => Math.max(diasUteis.value - diasUteisPassados.value, 0))

const resumo = computed(() => {
  const diasNaoFuturos = diasDoMes.value.filter(d => !d.isFuturo)
  const diasConsolidados = diasNaoFuturos.filter((d) => {
    // Consolida saldo do dia apenas quando a jornada está fechada
    // (número par de batidas). Evita lançar déficit no primeiro clique.
    const batidas = d.reg?.batidas ?? []
    const temBatida = batidas.length > 0
    if (!temBatida) return diaExigeMeta(d)
    return batidas.length % 2 === 0
  })
  const diasComMetaObrigatoria = diasNaoFuturos.filter(d => diaExigeMeta(d))
  const diasComBatida = diasNaoFuturos.filter(d => d.reg?.batidas?.length > 0)

  const presentes = diasComBatida.filter(d => {
    const pares = d.reg.batidas.length % 2 === 0
    const min = calcularMinutosTrabalhadosBatidas(d.reg.batidas)
    const metaDia = diaExigeMeta(d) ? metaMinDia(d) : 0
    return pares && min >= metaDia
  }).length

  const inconsistentes = diasComBatida.filter(d => {
    const pares = d.reg.batidas.length % 2 === 0
    const min = calcularMinutosTrabalhadosBatidas(d.reg.batidas)
    const metaDia = diaExigeMeta(d) ? metaMinDia(d) : 0
    return !pares || min < metaDia
  }).length

  // Falta só existe quando havia meta obrigatória para o dia.
  const faltas = diasComMetaObrigatoria.filter(d => !d.reg || d.reg.batidas.length === 0).length

  // Horas trabalhadas somam toda batida válida do mês (inclui plantão/fim de semana).
  const minTotal = diasComBatida.reduce((acc, d) => acc + calcularMinutosTrabalhadosBatidas(d.reg.batidas), 0)
  const horasTrabalhadas = (minTotal / 60).toFixed(1)

  // Horas esperadas só contam em dias consolidados com meta obrigatória.
  const minEsperados = diasConsolidados
    .filter(d => diaExigeMeta(d))
    .reduce((acc, d) => acc + metaMinDia(d), 0)
  const horasEsperadas = (minEsperados / 60).toFixed(1)

  return { presentes, inconsistentes, faltas, horasTrabalhadas, horasEsperadas }
})

const bancoHoras = computed(() => {
  const diff = parseFloat(resumo.value.horasTrabalhadas) - resumo.value.horasEsperadas
  const s = diff >= 0 ? '+' : ''
  return `${s}${diff.toFixed(1)}h`
})
const bancoHorasClass = computed(() => parseFloat(resumo.value.horasTrabalhadas) >= resumo.value.horasEsperadas ? 'green' : 'red')

const saldoMinMes = computed(() => {
  const horas = parseFloat(resumo.value.horasTrabalhadas) - parseFloat(resumo.value.horasEsperadas)
  return Math.round(horas * 60)
})
const minutosNecessariosPorDia = computed(() => {
  if (saldoMinMes.value >= 0 || diasUteisRestantes.value <= 0) return 0
  return Math.ceil(Math.abs(saldoMinMes.value) / diasUteisRestantes.value)
})
const metaFechamentoDiarioMin = computed(() => metaDiariaMin.value + minutosNecessariosPorDia.value)
const formatarDuracaoCurta = (minutos) => {
  const h = Math.floor(minutos / 60)
  const m = minutos % 60
  return `${h}h${String(m).padStart(2, '0')}`
}
const formatarDuracaoHumana = (minutos) => {
  const abs = Math.abs(minutos)
  const h = Math.floor(abs / 60)
  const m = abs % 60
  if (h > 0 && m > 0) return `${h}h e ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
}
const insightFechamentoTitulo = computed(() => {
  if (saldoMinMes.value >= 0) return 'Você já está no positivo'
  if (diasUteisRestantes.value === 0) return 'Mês encerrou com saldo negativo'
  return 'O que fazer para recuperar o saldo'
})
const insightFechamentoTexto = computed(() => {
  if (saldoMinMes.value >= 0) {
    return `Você está no azul. Mantendo a rotina de ${metaDiariaLabel.value} por dia, tende a fechar o mês positivo.`
  }
  if (diasUteisRestantes.value === 0) {
    return `O mês fechou com déficit de ${formatarDuracaoHumana(saldoMinMes.value)}.`
  }
  if (minutosNecessariosPorDia.value >= 180) {
    return 'Não é mais viável zerar o saldo apenas com ajuste diário de jornada neste mês.'
  }
  return `Para virar o mês no positivo, faça cerca de ${formatarDuracaoHumana(minutosNecessariosPorDia.value)} a mais por dia nos próximos ${diasUteisRestantes.value} dias úteis.`
})
const insightFechamentoDetalhe = computed(() => {
  if (saldoMinMes.value >= 0) return ''
  if (diasUteisRestantes.value === 0) return 'Converse com a gestão para definir a estratégia de compensação.'
  if (minutosNecessariosPorDia.value >= 180) {
    return `Para zerar neste mês, seriam necessárias cerca de ${formatarDuracaoHumana(minutosNecessariosPorDia.value)} extras por dia nos ${diasUteisRestantes.value} dias úteis restantes. Recomendação: alinhar plano de compensação com a gestão.`
  }
  return ''
})
const insightProjecaoGestor = computed(() => {
  if (diasUteisPassados.value <= 0) {
    return 'Ainda sem base de dias úteis concluídos para projetar o fechamento.'
  }
  const mediaSaldoDia = saldoMinMes.value / diasUteisPassados.value
  const saldoProjetadoFimMes = Math.round(saldoMinMes.value + (mediaSaldoDia * diasUteisRestantes.value))
  const sinal = saldoProjetadoFimMes >= 0 ? 'positivo' : 'negativo'
  return `No ritmo atual, a projeção de fechamento é ${sinal} em ${formatarDuracaoHumana(saldoProjetadoFimMes)}.`
})

const pctMesTotalDias = computed(() => {
  return Math.min(100, Math.round((diasUteisPassados.value / diasUteis.value) * 100)) || 0
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

const abrirDia = (dia) => {
  diaAberto.value = dia.num
  reconciliacaoMsg.value = ''
}

const solicitarReconciliacaoDia = async () => {
  if (!diaAberto.value || reconciliacaoLoading.value) return
  const dataDia = `${anoAtual.value}-${String(mesAtual.value + 1).padStart(2, '0')}-${String(diaAberto.value).padStart(2, '0')}`
  const sugestao = diaAbertoData.value?.sugestaoReconciliacao || 'Ajustar batida pendente para fechar pares da jornada.'
  reconciliacaoLoading.value = true
  reconciliacaoMsg.value = ''
  try {
    await api.post('/api/v3/ponto/reconciliacao/sugerir', {
      data: dataDia,
      sugestao,
      motivo: 'Jornada inconsistente detectada automaticamente no fechamento diário.',
    })
    reconciliacaoMsg.value = 'Solicitação enviada para aprovação da gestão.'
  } catch (e) {
    reconciliacaoMsg.value = e?.response?.data?.erro || 'Falha ao enviar solicitação de reconciliação.'
  } finally {
    reconciliacaoLoading.value = false
  }
}

const diaAbertoData = computed(() => {
  if (!diaAberto.value) return null
  const dia = diasDoMes.value.find(d => d.num === diaAberto.value)
  if (!dia?.reg) return { batidas: [], statusLabel: 'Falta', statusIcon: '❌', statusClass: 'sc-red', horasTrabalhadas: null }
  const tipoLabels = { entrada: 'Entrada', saida_almoco: 'Saída Almoço', retorno_almoco: 'Retorno Almoço', saida: 'Saída' }
  const tipoIcons = { entrada: '🟢', saida_almoco: '🟡', retorno_almoco: '🔵', saida: '🔴' }
  const batidas = dia.reg.batidas.map(b => ({ ...b, tipoLabel: tipoLabels[b.tipo] || b.tipo, tipoIcon: tipoIcons[b.tipo] || '⚪' }))
  const inconsistente = batidas.length % 2 !== 0
  const ultimaBatidaTipo = batidas[batidas.length - 1]?.tipo
  const sugestaoReconciliacao = ultimaBatidaTipo === 'entrada' || ultimaBatidaTipo === 'retorno_almoco'
    ? 'Sugerido: completar a próxima batida de saída para encerrar o par aberto.'
    : 'Sugerido: revisar sequência de batidas e solicitar ajuste do fechamento pendente.'
  let minTotal = 0
  for (let i = 0; i < Math.floor(batidas.length / 2) * 2; i += 2) {
    const [h1, m1] = batidas[i].hora.split(':').map(Number)
    const [h2, m2] = batidas[i + 1].hora.split(':').map(Number)
    minTotal += (h2 * 60 + m2) - (h1 * 60 + m1)
  }
  const metaObrigatoriaDia = diaExigeMeta(dia)
  const metaDiaMin = metaObrigatoriaDia ? metaMinDia(dia) : 0
  const horasTrabalhadas = minTotal > 0 ? `${Math.floor(minTotal / 60)}h${String(minTotal % 60).padStart(2,'0')}min` : null
  const pctHoras = metaDiaMin > 0 ? Math.min(100, Math.round((minTotal / metaDiaMin) * 100)) : 100
  const temDeficit = metaObrigatoriaDia && batidas.length > 0 && minTotal < metaDiaMin
  return {
    batidas,
    horasTrabalhadas,
    pctHoras,
    statusLabel: inconsistente
      ? 'Batidas Inconsistentes'
      : (temDeficit ? 'Jornada Incompleta' : (metaObrigatoriaDia ? 'Presente' : 'Plantão / Extra')),
    statusIcon: inconsistente ? '⚠️' : (temDeficit ? '📉' : '✅'),
    statusClass: inconsistente || temDeficit ? 'sc-yellow' : 'sc-green',
    inconsistente,
    sugestaoReconciliacao,
  }
})
const metaDiaAbertoLabel = computed(() => {
  if (!diaAberto.value) return metaDiariaLabel.value
  const dia = diasDoMes.value.find(d => d.num === diaAberto.value)
  if (!dia || !diaExigeMeta(dia)) return 'Sem meta obrigatória'
  const min = metaMinDia(dia)
  const h = Math.floor(min / 60)
  const m = min % 60
  return m ? `${h}h${String(m).padStart(2, '0')}min` : `${h}h`
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
.hero-sub-alvo { display: block; margin-top: 6px; font-size: 12px; color: #fde68a; font-weight: 600; text-transform: none; }
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
.visao-toggle {
  display: inline-flex;
  border: 1px solid rgba(255,255,255,0.22);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.2);
}
.visao-btn {
  border: none;
  background: transparent;
  color: #cbd5e1;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}
.visao-btn.active {
  background: rgba(255,255,255,0.2);
  color: #fff;
}
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
.state-warn {
  grid-column: 1 / -1;
  margin-bottom: 2px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 12px;
  font-weight: 700;
}
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }
.state-box p { font-size: 15px; font-weight: 500; margin: 0; }

/* ── LAYOUT ────────────────────────────────────────────────── */
.ponto-layout {
  display: grid;
  grid-template-columns: minmax(0, 820px) minmax(300px, 340px);
  justify-content: center;
  gap: 20px;
  align-items: start;
  opacity: 0; transform: translateY(10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.ponto-layout.loaded { opacity: 1; transform: none; }
.top-dashboard {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(300px, 540px) minmax(280px, 1fr);
  gap: 14px;
  align-items: stretch;
}
.ponto-priority-stack {
  display: grid;
  gap: 12px;
  align-content: start;
}
.bater-card-top {
  grid-column: auto;
  width: 100%;
}
.summary-card-top {
  min-height: 100%;
}
.dashboard-insight {
  margin: 4px 0 12px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #dbeafe;
  background: linear-gradient(135deg, #eff6ff, #ecfeff);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.dashboard-insight strong {
  font-size: 12px;
  color: #1e3a8a;
}
.dashboard-insight span {
  font-size: 12px;
  color: #1e293b;
  line-height: 1.35;
}
.dashboard-insight small {
  margin-top: 2px;
  font-size: 11px;
  color: #475569;
  line-height: 1.35;
}
.dashboard-insight.gestor {
  border-color: #c7d2fe;
  background: linear-gradient(135deg, #eef2ff, #f5f3ff);
}
.dashboard-insight.gestor strong { color: #3730a3; }
.risk-panel {
  position: relative;
  margin: 4px 0 12px;
  border: none;
  border-radius: 18px;
  background:
    radial-gradient(120% 95% at 12% 0%, rgba(56, 189, 248, 0.13), transparent 58%),
    radial-gradient(120% 95% at 92% 100%, rgba(20, 184, 166, 0.11), transparent 58%),
    linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248, 252, 255, 0.95));
  padding: 12px;
  display: grid;
  gap: 10px;
  overflow: hidden;
}
.risk-panel::before {
  content: none;
}
.risk-panel-below-point {
  margin: 0;
}
.risk-head {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.risk-head-text { display: grid; gap: 2px; }
.risk-head strong { font-size: 13px; color: #0f172a; }
.risk-head small { font-size: 11px; color: #64748b; }
.risk-state { position: relative; z-index: 1; font-size: 12px; color: #475569; }
.risk-state.err { color: #b91c1c; }
.risk-table { position: relative; z-index: 1; display: grid; gap: 6px; overflow-x: auto; }
.risk-row {
  display: grid;
  grid-template-columns: minmax(180px, 2.2fr) 92px 110px 110px 80px 100px;
  gap: 8px;
  align-items: center;
  font-size: 12px;
  min-width: 700px;
}
.risk-head-row { font-weight: 700; color: #64748b; }
.risk-row span { overflow: hidden; text-overflow: ellipsis; }
.risk-row .num {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.risk-row-body {
  border: none;
  border-radius: 12px;
  padding: 7px 8px;
  background: rgba(239, 246, 255, 0.72);
  transition: transform 0.16s ease, box-shadow 0.18s ease;
  animation: riskRowIn .3s cubic-bezier(0.22,1,0.36,1);
}
.risk-row-body:hover {
  transform: translateY(-1px);
  box-shadow: none;
}
.risk-row .setor { font-weight: 600; color: #0f172a; }
.sev-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 2px 8px;
  text-transform: uppercase;
  font-size: 10px;
  font-weight: 800;
}
.sev-baixo { background: #dcfce7; color: #166534; }
.sev-medio { background: #fef9c3; color: #854d0e; }
.sev-alto { background: #ffedd5; color: #9a3412; }
.sev-critico { background: #fee2e2; color: #991b1b; }
.risk-actions { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 8px; }
.risk-btn {
  border: none;
  border-radius: 12px;
  padding: 7px 11px;
  font-size: 12px;
  font-weight: 800;
  background: #fff;
  color: #1e293b;
  cursor: pointer;
  transition: transform .16s ease, box-shadow .2s ease, background .2s ease;
}
.risk-btn:hover { transform: translateY(-1px); box-shadow: none; }
.risk-btn.criticos { background: linear-gradient(135deg, #fee2e2, #fff1f2); color: #991b1b; }
.risk-btn.escala { background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1d4ed8; }
.risk-btn.notificar { background: linear-gradient(135deg, #ede9fe, #f5f3ff); color: #5b21b6; }
.risk-acao-msg { position: relative; z-index: 1; font-size: 11px; color: #1e3a8a; font-weight: 700; }
@keyframes riskRowIn {
  from { opacity: 0; transform: translateY(5px) scale(0.99); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.sc-label-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.help-btn {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #64748b;
  font-size: 10px;
  line-height: 1;
  font-weight: 800;
  cursor: pointer;
  padding: 0;
}
.help-tip {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 220px;
  max-width: 280px;
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 11px;
  line-height: 1.35;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.28);
  z-index: 30;
  opacity: 0;
  pointer-events: none;
  transform: translateY(-4px);
  transition: all 0.15s ease;
}
.sc-label-wrap:hover .help-tip,
.sc-label-wrap:focus-within .help-tip,
.help-tip.open {
  opacity: 1;
  pointer-events: auto;
  transform: translateY(0);
}

/* ── CALENDÁRIO ─────────────────────────────────────────────── */
.calendar-shell {
  position: relative;
  border-radius: 24px;
  padding: 10px;
  background:
    radial-gradient(110% 90% at 10% 0%, rgba(56, 189, 248, 0.14), transparent 62%),
    radial-gradient(100% 85% at 100% 100%, rgba(20, 184, 166, 0.12), transparent 62%),
    linear-gradient(145deg, rgba(14, 116, 144, 0.1), rgba(2, 132, 199, 0.06));
  border: 1px solid rgba(125, 211, 252, 0.28);
  box-shadow: 0 18px 44px -28px rgba(8, 47, 73, 0.45);
}
.calendar-shell::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background-image:
    linear-gradient(120deg, rgba(255,255,255,0.16) 0%, transparent 45%),
    repeating-linear-gradient(135deg, rgba(125, 211, 252, 0.08) 0 10px, transparent 10px 20px);
  pointer-events: none;
}
.calendar-card {
  position: relative;
  z-index: 1;
  background:
    radial-gradient(120% 100% at 20% 0%, rgba(186, 230, 253, 0.25), transparent 56%),
    radial-gradient(120% 100% at 90% 90%, rgba(153, 246, 228, 0.2), transparent 58%),
    linear-gradient(180deg, rgba(255,255,255,0.95), rgba(248, 252, 255, 0.94));
  backdrop-filter: blur(2px);
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 20px;
  overflow: hidden;
}
.calendar-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(circle at 18% 22%, rgba(125, 211, 252, 0.16) 0 12%, transparent 30%),
    radial-gradient(circle at 75% 68%, rgba(45, 212, 191, 0.12) 0 12%, transparent 34%),
    repeating-linear-gradient(160deg, rgba(255,255,255,0.18) 0 8px, transparent 8px 20px);
  opacity: 0.45;
  pointer-events: none;
  z-index: 0;
}
.cal-header {
  position: relative;
  z-index: 1;
  display: grid; grid-template-columns: repeat(7, 1fr);
  background: rgba(248, 250, 252, 0.84); border-bottom: 1px solid #e2e8f0;
  padding: 10px 10px;
}
.cal-dow { text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.cal-grid {
  position: relative;
  z-index: 1;
  display: grid; grid-template-columns: repeat(7, 1fr);
  gap: 6px; padding: 10px;
}
.cal-day {
  aspect-ratio: 1 / 0.92; border-radius: 12px; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  position: relative; transition: all 0.15s; user-select: none;
}
.cal-empty { background: transparent; cursor: default; }
.cal-day-normal { background: rgba(248, 250, 252, 0.88); }
.cal-day-fds { background: rgba(248, 250, 252, 0.78); opacity: 0.5; cursor: default; }
.cal-day-futuro { background: rgba(248, 250, 252, 0.7); opacity: 0.3; cursor: default; }
.cal-day-presente { background: rgba(240, 253, 244, 0.92); }
.cal-day-presente:hover { background: #dcfce7; transform: scale(1.05); }
.cal-day-inconsistente { background: rgba(255, 251, 235, 0.92); }
.cal-day-inconsistente:hover { background: #fef3c7; transform: scale(1.05); }
.cal-day-falta { background: rgba(254, 242, 242, 0.92); cursor: pointer; /* BUG-EST-06 */ }
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
.cal-legend {
  position: relative;
  z-index: 1;
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  border-top: 1px solid #e2e8f0;
  background: rgba(255,255,255,0.82);
  flex-wrap: wrap;
}
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
.recon-card {
  margin-top: 12px;
  border: 1px dashed #f59e0b;
  background: #fffaf0;
  border-radius: 12px;
  padding: 10px 12px;
  display: grid;
  gap: 8px;
}
.recon-card p { margin: 0; font-size: 13px; color: #7c5a12; }
.recon-btn {
  border: 0;
  border-radius: 10px;
  padding: 8px 10px;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.recon-btn:disabled { opacity: .65; cursor: not-allowed; }

/* SUMMARY CARD */
.summary-card {
  background:
    radial-gradient(120% 95% at 12% 0%, rgba(56, 189, 248, 0.08), transparent 58%),
    radial-gradient(120% 95% at 92% 100%, rgba(20, 184, 166, 0.07), transparent 58%),
    linear-gradient(180deg, rgba(255,255,255,0.98), rgba(239, 246, 255, 0.92));
  border: none;
  border-radius: 18px;
  padding: 20px;
}
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
.reset-teste-btn {
  margin-top: 4px;
  padding: 9px 10px;
  border-radius: 10px;
  border: 1px dashed rgba(251, 191, 36, 0.6);
  background: rgba(251, 191, 36, 0.15);
  color: #fbbf24;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.reset-teste-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
  padding: 16px;
}
.modal-card {
  width: min(520px, 100%);
  background: #fff;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  padding: 20px;
  box-shadow: 0 18px 48px rgba(15, 23, 42, 0.25);
}
.modal-title {
  margin: 0 0 8px 0;
  font-size: 18px;
  color: #1e293b;
  font-weight: 900;
}
.modal-text {
  margin: 0;
  color: #475569;
  font-size: 13px;
}
.modal-resumo {
  margin-top: 14px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 12px;
  display: grid;
  gap: 6px;
  font-size: 13px;
}
.modal-resumo .neg { color: #dc2626; }
.modal-resumo .pos { color: #16a34a; }
.modal-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.modal-btn {
  border: none;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}
.modal-btn.secondary { background: #e2e8f0; color: #334155; }
.modal-btn.primary { background: #2563eb; color: #fff; }
.modal-btn.danger { background: #dc2626; color: #fff; }
.modal-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* BOTÃO LEGADO */
.legado-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(135deg, #0f172a, #1e3a5f);
  color: #fff; text-decoration: none; border-radius: 14px; padding: 13px;
  font-size: 13px; font-weight: 700; transition: all 0.2s;
}
.legado-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,23,42,0.3); }

@media (max-width: 1180px) {
  .ponto-layout {
    grid-template-columns: minmax(0, 1fr) minmax(280px, 320px);
    gap: 14px;
  }
  .top-dashboard {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .ponto-layout { grid-template-columns: 1fr; }
  .top-dashboard { grid-template-columns: 1fr; }
  .calendar-shell { padding: 8px; border-radius: 20px; }
  .calendar-card { border-radius: 16px; }
  .cal-grid { gap: 5px; padding: 8px; }
  .risk-row {
    grid-template-columns: minmax(170px, 2fr) 86px 104px 100px 72px 92px;
    min-width: 640px;
    font-size: 11px;
  }
}

@media (max-width: 640px) {
  /* Hero compacto */
  .hero-inner { flex-direction: column; gap: 20px; }
  .hero-title { font-size: 26px; }
  .hero-eyebrow { font-size: 12px; }
  .hero-stats { gap: 10px; }
  .visao-toggle { width: 100%; }
  .visao-btn { flex: 1; text-align: center; }
  .hstat { padding: 12px 16px; }
  .hstat-val { font-size: 20px; }

  /* Calendário menor */
  .cal-day { min-height: 46px; font-size: 12px; }
  .day-badge { font-size: 9px; padding: 1px 4px; }
  .calendar-shell { padding: 6px; }
  .cal-grid { gap: 4px; padding: 8px; }
  .cal-day {
    min-height: 44px;
    border-radius: 10px;
    touch-action: manipulation;
  }
  .nav-btn,
  .bater-btn,
  .reset-teste-btn {
    touch-action: manipulation;
  }

  /* Botão de ponto touch-friendly */
  .bater-btn {
    min-height: 56px;
    font-size: 15px;
    border-radius: 16px;
  }
}

@media (max-width: 420px) {
  .cal-dow { font-size: 10px; }
  .day-num { font-size: 11px; }
  .cal-day { min-height: 40px; }
  .day-dot { width: 3px; height: 3px; }
  .cal-legend { gap: 10px; padding: 10px 12px; }
  .help-tip {
    left: auto;
    right: 0;
    min-width: 180px;
    max-width: 220px;
  }
}
</style>
