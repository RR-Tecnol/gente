<template>
  <div class="bh-page">
    <div class="bh-bg-pulse"></div>
    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⏱️ Ponto Eletrônico</span>
          <h1 class="hero-title">Banco de Horas</h1>
          <p class="hero-sub">{{ modoEquipe ? 'Visão da equipe' : mesLabel }} · {{ modoEquipe ? 'Saldo consolidado por membro' : 'Análise detalhada do saldo de horas' }}</p>
          <p class="hero-local">São Luís do Maranhão · inspiração litorânea e azulejaria histórica</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <!-- BUG-EST-15: toggle gestor/equipe -->
          <div class="modo-toggle">
            <button :class="['mt-btn', { active: !modoEquipe }]" @click="modoEquipe = false">👤 Meu Saldo</button>
            <button :class="['mt-btn', { active: modoEquipe }]" @click="modoEquipe = true">👥 Equipe</button>
          </div>
          <div class="month-nav">
            <button class="mnav-btn" @click="mesAnterior">‹</button>
            <span class="mes-label-nav">{{ mesLabel }}</span>
            <button class="mnav-btn" @click="mesPosterior">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loading-bar"><span></span></div>

    <!-- KPI STRIP ───────────────────────────────────────────── -->
    <div v-if="!modoEquipe" class="kpi-strip" :class="{ loaded }">
      <div class="kpi-card kc-blue">
        <span class="kc-ico">🎯</span>
        <div class="kc-info">
          <span class="kc-label">Horas Esperadas</span>
          <span class="kc-val">{{ formatMinutosSmart(saldo.esperadasMin) }}</span>
        </div>
      </div>
      <div class="kpi-card kc-green">
        <span class="kc-ico">✅</span>
        <div class="kc-info">
          <span class="kc-label">Horas Trabalhadas</span>
          <span class="kc-val">{{ formatMinutosSmart(saldo.trabalhadasMin) }}</span>
        </div>
      </div>
      <div class="kpi-card" :class="saldo.totalMin >= 0 ? 'kc-teal' : 'kc-red'">
        <span class="kc-ico">{{ saldo.totalMin >= 0 ? '📈' : '📉' }}</span>
        <div class="kc-info">
          <span class="kc-label">Saldo do Mês</span>
          <span class="kc-val">{{ formatMinutosSmart(saldo.totalMin, true) }}</span>
        </div>
      </div>
      <div class="kpi-card kc-purple">
        <span class="kc-ico">💰</span>
        <div class="kc-info">
          <span class="kc-label">Saldo Acumulado</span>
          <span class="kc-val">{{ formatMinutosSmart(saldoAcumuladoMin, true) }}</span>
        </div>
      </div>
      <div class="kpi-card kc-orange">
        <span class="kc-ico">📊</span>
        <div class="kc-info">
          <span class="kc-label">Aproveitamento</span>
          <span class="kc-val">{{ saldo.esperadasMin > 0 ? Math.round((saldo.trabalhadasMin / saldo.esperadasMin) * 100) : 0 }}%</span>
        </div>
      </div>
    </div>

    <!-- PROGRESSO DO MÊS ─────────────────────────────────────── -->
    <div v-if="!modoEquipe" class="progress-card" :class="{ loaded }">
      <div class="prog-hdr">
        <h2 class="prog-title">Progresso do Mês</h2>
        <span class="prog-dias">{{ diasUtilsPassados }} de {{ diasUteis }} dias úteis</span>
      </div>
      <div class="prog-track">
        <div class="prog-fill" :style="{ width: diasUteis > 0 ? (diasUtilsPassados / diasUteis * 100) + '%' : '0%' }"></div>
      </div>
      <div class="prog-categories">
        <div class="prog-cat pc-green"><span class="pc-bar" :style="{ width: saldo.trabalhadasMin > 0 ? (saldo.normaisMin / saldo.trabalhadasMin * 100) + '%' : '0%' }"></span></div>
        <div class="prog-cat pc-orange"><span class="pc-bar" :style="{ width: saldo.trabalhadasMin > 0 ? (saldo.extrasMin / saldo.trabalhadasMin * 100) + '%' : '0%' }"></span></div>
        <div class="prog-cat pc-red"><span class="pc-bar" :style="{ width: saldo.trabalhadasMin > 0 ? (saldo.negativasMin / saldo.trabalhadasMin * 100) + '%' : '0%' }"></span></div>
      </div>
      <div class="prog-legend">
        <span class="pl-item pl-green">🟢 Normais: {{ formatMinutosSmart(saldo.normaisMin) }}</span>
        <span class="pl-item pl-orange">🟠 Extras: {{ formatMinutosSmart(saldo.extrasMin) }}</span>
        <span class="pl-item pl-red">🔴 Negativas: {{ formatMinutosSmart(saldo.negativasMin) }}</span>
      </div>
      <div v-if="usingRealData" class="real-badge">✅ Dados reais do backend</div>
      <div v-if="dadosIndisponiveis" class="warn-badge">⚠️ Dados temporariamente indisponíveis. Tentando reconectar.</div>
    </div>

    <!-- TABELA DIÁRIA ────────────────────────────────────────── -->
    <div v-if="!modoEquipe" class="table-card" :class="{ loaded }">
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
              <td><span class="hora-val dimmed">{{ (d.isFds || d.isFeriado) ? '—' : formatMinutosSmart(480) }}</span></td>
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

    <!-- BUG-EST-15: Visão da equipe (gestor) -->
    <div v-if="modoEquipe" class="table-card" :class="{ loaded }" style="margin-top:8px">
      <div class="tc-hdr">
        <h2 class="tc-title">👥 Banco de Horas da Equipe — {{ equipeResp.setor ?? 'Meu Setor' }}</h2>
        <div class="tc-filters">
          <select v-if="isAdmin" v-model="escopoEquipe" class="notify-select" @change="fetchEquipe">
            <option value="todos">Todas as equipes</option>
            <option value="meu_setor">Meu setor</option>
            <option value="setor">Setor específico</option>
          </select>
          <select
            v-if="isAdmin && escopoEquipe === 'setor'"
            v-model="setorEquipeId"
            class="notify-select"
            @change="fetchEquipe"
          >
            <option value="">Selecione um setor</option>
            <option v-for="s in setoresEquipe" :key="s.setor_id" :value="String(s.setor_id)">
              {{ s.setor }} ({{ s.quantidade }})
            </option>
          </select>
          <button v-for="f in filterOptsEquipe" :key="f.val" class="f-btn" :class="{ active: tipoFiltroEquipe === f.val }" @click="tipoFiltroEquipe = f.val">
            {{ f.label }}
          </button>
          <button class="f-btn active" @click="fetchEquipe">🔄 Atualizar</button>
        </div>
      </div>
      <div v-if="modoEquipe && comparativoSetores.length" class="table-scroll" style="padding-top:8px">
        <table class="bh-table">
          <thead>
            <tr>
              <th>Setor</th><th>Membros</th><th>Saldo Total</th><th>Saldo Médio</th><th>Negativos</th><th>Positivos</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in comparativoSetores" :key="c.setor_id" class="bh-row">
              <td class="td-nome">{{ c.setor }}</td>
              <td>{{ c.quantidade }}</td>
              <td><span class="saldo-chip" :class="c.saldo_total >= 0 ? 'saldo-pos' : 'saldo-neg'">{{ formatHorasSmart(c.saldo_total, true) }}</span></td>
              <td>{{ formatHorasSmart(c.saldo_medio, true) }}</td>
              <td style="color:#b91c1c;font-weight:700">{{ c.negativos }}</td>
              <td style="color:#166534;font-weight:700">{{ c.positivos }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="team-actions" v-if="membrosEquipeFiltrados.length">
        <button class="team-action-btn criticos" @click="abrirModalOperacional('criticos')">Ver funcionários críticos</button>
        <button class="team-action-btn escala" @click="abrirModalOperacional('escala')">Abrir escala do setor</button>
        <button class="team-action-btn notificar" @click="abrirModalOperacional('notificar')">Notificar coordenação</button>
      </div>
      <div v-if="carregandoEquipe" class="loading-bar" style="margin:16px"><span></span></div>
      <div v-else-if="!equipeResp.membros?.length" style="text-align:center;padding:32px;color:#94a3b8;font-size:13px">
        Nenhum membro da equipe encontrado ou sem dados de banco de horas.
      </div>
      <div v-else class="table-scroll">
        <table class="bh-table">
          <thead><tr>
            <th>Servidor</th><th>Cargo</th><th>Matrícula</th>
            <th>Saldo Mês</th><th>Créd. Mês</th><th>Déb. Mês</th><th>Saldo Acumulado</th>
          </tr></thead>
          <tbody>
            <tr v-for="m in membrosEquipeFiltrados" :key="m.funcionario_id" class="bh-row">
              <td class="td-nome">{{ m.nome }}</td>
              <td style="font-size:12px;color:#64748b">{{ m.cargo }}</td>
              <td style="font-family:monospace;font-size:12px">{{ m.matricula }}</td>
              <td><span class="saldo-chip" :class="m.saldo_mes >= 0 ? 'saldo-pos' : 'saldo-neg'">{{ formatHorasSmart(m.saldo_mes, true) }}</span></td>
              <td style="color:#065f46;font-weight:700">{{ formatHorasSmart(m.cred_mes, true) }}</td>
              <td style="color:#991b1b">{{ formatHorasSmart(-Number(m.deb_mes || 0), true) }}</td>
              <td><span class="saldo-chip" :class="m.saldo_acumulado >= 0 ? 'saldo-pos' : 'saldo-neg'">{{ formatHorasSmart(m.saldo_acumulado, true) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <teleport to="body">
      <transition name="modal-fade">
        <div v-if="modalOpAberto" class="modal-overlay" @click.self="fecharModalOperacional">
          <div class="modal-box">
            <div class="modal-header">
              <h2 class="modal-title">{{ modalOpTitulo }}</h2>
              <button class="modal-close" @click="fecharModalOperacional">✕</button>
            </div>
            <div class="modal-body">
              <p class="modal-text">{{ modalOpTexto }}</p>

              <div v-if="modalOpTipo === 'criticos'" class="modal-list">
                <div v-if="!membrosCriticos.length" class="modal-empty">Nenhum membro crítico encontrado para esta competência.</div>
                <div v-else v-for="m in membrosCriticos" :key="m.funcionario_id" class="modal-item">
                  <strong>{{ m.nome }}</strong>
                  <span>Matrícula: {{ m.matricula || '—' }}</span>
                  <span>Saldo mês: {{ formatHorasSmart(m.saldo_mes, true) }}</span>
                </div>
              </div>

              <div v-if="modalOpTipo === 'escala'" class="modal-next">
                <div class="impact-wrap">
                  <div v-if="impactoEscalaLoading" class="modal-empty">Carregando impacto operacional...</div>
                  <div v-else-if="impactoEscalaErro" class="modal-empty modal-err">{{ impactoEscalaErro }}</div>
                  <div v-else class="impact-summary">
                    <div class="impact-kpi">
                      <strong>{{ impactoEscalaResumo.total_impactados }}</strong>
                      <span>com déficit no mês</span>
                    </div>
                    <div class="impact-kpi">
                      <strong>{{ formatMoneyBr(impactoEscalaResumo.desconto_total_estimado) }}</strong>
                      <span>desconto estimado total</span>
                    </div>
                  </div>
                  <div v-if="impactoEscalaMembros.length" class="impact-list">
                    <div v-for="m in impactoEscalaMembros" :key="m.funcionario_id" class="impact-item">
                      <strong>{{ m.nome }}</strong>
                      <span>{{ m.cargo }} · Déficit: {{ m.deficit_horas }}h</span>
                      <span>Estimativa: {{ formatMoneyBr(m.desconto_estimado) }}</span>
                    </div>
                  </div>
                </div>
                <button class="f-btn active" @click="irParaMatrizEscala">Ir para Matriz de Escala</button>
              </div>

              <div v-if="modalOpTipo === 'notificar'" class="modal-next">
                <div class="notify-target" v-if="isAdmin">
                  <label>Notificar</label>
                  <select v-model="notificarAlvo" class="notify-select">
                    <option value="eu">Somente eu</option>
                    <option value="gestores">Somente gestores</option>
                    <option value="todos_funcionarios">Todos os funcionários</option>
                  </select>
                </div>
                <button class="f-btn active" :disabled="notificandoCoordenacao" @click="confirmarNotificacaoCoordenacao">
                  {{ notificandoCoordenacao ? 'Registrando...' : 'Registrar notificação operacional' }}
                </button>
              </div>

              <div v-if="modalHistorico.length" class="modal-history">
                <strong>Histórico recente da coordenação (setor/competência)</strong>
                <div v-for="item in modalHistorico" :key="item.id" class="modal-history-item">
                  <span>{{ item.titulo }}</span>
                  <small>{{ item.data }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const loaded = ref(false)
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const mesAtual = ref(new Date())
const tipoFiltro = ref('todos')
const registros = ref([])
const loading = ref(false)
const usingRealData = ref(false)
const saldoAcumuladoBackend = ref(null)
const dadosIndisponiveis = ref(false)
const feriadosMes = ref(new Set())
// BUG-EST-15: modo equipe
const modoEquipe   = ref(false)
const equipeResp   = ref({ membros: [], setor: null })
const carregandoEquipe = ref(false)
const modalOpAberto = ref(false)
const modalOpTipo = ref('')
const modalOpTitulo = ref('')
const modalOpTexto = ref('')
const modalHistorico = ref([])
const notificandoCoordenacao = ref(false)
const notificarAlvo = ref('eu')
const isAdmin = computed(() => Boolean(authStore?.isAdmin))
const impactoEscalaLoading = ref(false)
const impactoEscalaErro = ref('')
const impactoEscalaMembros = ref([])
const impactoEscalaResumo = ref({ total_impactados: 0, desconto_total_estimado: 0 })
const escopoEquipe = ref('meu_setor')
const setorEquipeId = ref('')
const setoresEquipe = ref([])
const comparativoSetores = ref([])

const filterOpts = [
  { val: 'todos', label: 'Todos' },
  { val: 'extra', label: '📈 Extras' },
  { val: 'negativo', label: '📉 Negativos' },
  { val: 'falta', label: '🚫 Faltas' },
]
const tipoFiltroEquipe = ref('todos')
const filterOptsEquipe = [
  { val: 'todos', label: 'Todos' },
  { val: 'positivo', label: '📈 Saldo +' },
  { val: 'negativo', label: '📉 Saldo -' },
  { val: 'zerado', label: '⚪ Zerado' },
]

const DOW = ['Do','Se','Te','Qu','Qu','Se','Sá']
const fmt  = (mins) => `${String(Math.floor(mins / 60)).padStart(2,'0')}:${String(mins % 60).padStart(2,'0')}`
const fmtDur = (mins) => formatMinutosSmart(mins)
const tipoRegistro = (r) => String(r?.REGISTRO_TIPO ?? r?.tipo ?? '').toLowerCase()
const extrairHora = (v) => {
  if (!v) return null
  const s = String(v)
  if (/^\d{2}:\d{2}/.test(s)) return s.slice(0, 5)
  const d = new Date(v)
  if (!Number.isNaN(d.getTime())) return fmt(d.getHours() * 60 + d.getMinutes())
  return null
}

// ── Monta grade com dados REAIS (registros do backend) ────────
const montarComDadosReais = (pontosBackend) => {
  const ano = mesAtual.value.getFullYear()
  const mes = mesAtual.value.getMonth()
  const total = new Date(ano, mes + 1, 0).getDate()
  const hoje = new Date()

  // indexa registros por dia
  const mapaRegistros = {}
  pontosBackend.forEach(r => {
    // formato 1: [{ dia, batidas: [{ hora, tipo }] }]
    if (Number.isFinite(Number(r?.dia)) && Array.isArray(r?.batidas)) {
      const dia = Number(r.dia)
      if (!mapaRegistros[dia]) mapaRegistros[dia] = []
      const batidas = r.batidas
        .map(b => ({ tipo: String(b?.tipo || '').toLowerCase(), hora: extrairHora(b?.hora) }))
        .filter(b => b.hora)
      mapaRegistros[dia].push(...batidas)
      return
    }

    // formato 2: flat [{ REGISTRO_DATA_HORA, REGISTRO_TIPO }]
    const dataHora = r?.REGISTRO_DATA_HORA ?? r?.data_hora ?? r?.PONTO_DATA
    const d = new Date(dataHora)
    if (Number.isNaN(d.getTime())) return
    const dia = d.getDate()
    if (!mapaRegistros[dia]) mapaRegistros[dia] = []
    mapaRegistros[dia].push({
      tipo: tipoRegistro(r),
      hora: fmt(d.getHours() * 60 + d.getMinutes()),
    })
  })

  const lista = []
  for (let d = 1; d <= total; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    const isFds = dow === 0 || dow === 6
    const isFeriado = feriadosMes.value.has(d)
    const isPast = dt < hoje
    const regs = mapaRegistros[d] || []
    const regsOrdenados = [...regs].sort((a, b) => {
      const [ha, ma] = String(a?.hora || '00:00').split(':').map(Number)
      const [hb, mb] = String(b?.hora || '00:00').split(':').map(Number)
      return (ha * 60 + ma) - (hb * 60 + mb)
    })
    const entrada = regsOrdenados.find(r => ['entrada', 'in', 'start', 'retorno_almoco'].includes(String(r?.tipo || '').toLowerCase())) ?? regsOrdenados[0]
    const saida   = [...regsOrdenados].reverse().find(r => ['saida', 'out', 'end', 'saida_almoco'].includes(String(r?.tipo || '').toLowerCase())) ?? regsOrdenados[regsOrdenados.length - 1]

    let trabalhadas = null
    let saldoMin = (isFds || isFeriado) ? 0 : null
    let entradaStr = null
    let saidaStr = null
    if (entrada?.hora) entradaStr = entrada.hora
    if (saida?.hora) saidaStr = saida.hora
    if (entrada && saida) {
      const [hIni, mIni] = String(entrada.hora || '00:00').split(':').map(Number)
      const [hFim, mFim] = String(saida.hora || '00:00').split(':').map(Number)
      const dur  = Math.max(0, ((hFim * 60 + mFim) - (hIni * 60 + mIni)))
      trabalhadas = fmtDur(dur)
      const metaEsperadaDia = (isFds || isFeriado) ? 0 : 480
      saldoMin = dur - metaEsperadaDia
    } else if (!isFds && !isFeriado && isPast && regs.length === 0) {
      saldoMin = -480 // falta
    }

    lista.push({ data: dt, diaNum: d, dow: DOW[dow], isFds, isFeriado, entrada: entradaStr, saida: saidaStr, trabalhadas, saldoMin })
  }
  registros.value = lista
  usingRealData.value = true
}

// ── Fallback seguro: sem dados fabricados ───────────────────────
const gerarFallbackVazio = () => {
  const ano = mesAtual.value.getFullYear()
  const mes = mesAtual.value.getMonth()
  const total = new Date(ano, mes + 1, 0).getDate()
  const lista = []
  for (let d = 1; d <= total; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    const isFds = dow === 0 || dow === 6
    lista.push({
      data: dt,
      diaNum: d,
      dow: DOW[dow],
      isFds,
      isFeriado: false,
      entrada: null,
      saida: null,
      saldoMin: isFds ? 0 : null,
      trabalhadas: null,
    })
  }
  registros.value = lista
  usingRealData.value = false
}

const fetchRegistros = async () => {
  loading.value = true
  dadosIndisponiveis.value = false
  saldoAcumuladoBackend.value = null
  feriadosMes.value = new Set()
  const comp = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  try {
    const { data: bh } = await api.get('/api/v3/banco-horas')
    if (!bh.fallback && bh.apuracoes?.length) {
      const apuMes = bh.apuracoes.find(a => a.competencia === comp)
      if (apuMes) saldoAcumuladoBackend.value = apuMes.saldo_acumulado ?? null
    }
    const { data: ponto } = await api.get('/api/v3/ponto', { params: { competencia: comp } })
    feriadosMes.value = new Set((ponto?.dias_feriado ?? []).map(Number))
    if (!ponto.fallback && ponto.registros?.length) {
      montarComDadosReais(ponto.registros)
      return
    }
    gerarFallbackVazio()
  } catch {
    gerarFallbackVazio()
    dadosIndisponiveis.value = true
  } finally {
    loading.value = false
  }
}

const fetchEquipe = async () => {
  carregandoEquipe.value = true
  try {
    const comp = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
    const params = { competencia: comp, escopo: escopoEquipe.value }
    if (escopoEquipe.value === 'setor' && setorEquipeId.value) params.setor_id = Number(setorEquipeId.value)
    const { data } = await api.get('/api/v3/banco-horas/equipe', { params })
    equipeResp.value = data
    setoresEquipe.value = data?.setores_disponiveis ?? []
    comparativoSetores.value = data?.comparativo_setores ?? []
  } catch {
    equipeResp.value = { membros: [], setor: null }
    setoresEquipe.value = []
    comparativoSetores.value = []
  }
  finally { carregandoEquipe.value = false }
}

const abrirModalOperacional = (tipo) => {
  modalOpTipo.value = tipo
  modalOpAberto.value = true
  const setor = equipeResp.value?.setor || route.query.setor || 'setor atual'
  if (tipo === 'criticos') {
    modalOpTitulo.value = `Funcionários críticos — ${setor}`
    modalOpTexto.value = 'Lista de membros com saldo negativo no mês, priorizados para ação imediata de gestão.'
    carregarHistoricoOperacional()
    return
  }
  if (tipo === 'escala') {
    modalOpTitulo.value = `Cobertura de escala — ${setor}`
    modalOpTexto.value = 'Painel de impacto por déficit da equipe para apoiar decisão de cobertura e mitigação de desconto.'
    carregarImpactoEscala()
    carregarHistoricoOperacional()
    return
  }
  modalOpTitulo.value = `Notificação de coordenação — ${setor}`
  modalOpTexto.value = 'Registrar acionamento da coordenação para plano de contingência operacional.'
  notificarAlvo.value = isAdmin.value ? 'gestores' : 'eu'
  carregarHistoricoOperacional()
}

const fecharModalOperacional = () => {
  modalOpAberto.value = false
  modalOpTipo.value = ''
  modalHistorico.value = []
  impactoEscalaMembros.value = []
  impactoEscalaErro.value = ''
  impactoEscalaResumo.value = { total_impactados: 0, desconto_total_estimado: 0 }
}

const membrosCriticos = computed(() => (equipeResp.value?.membros ?? []).filter(m => Number(m.saldo_mes) < 0))

const irParaMatrizEscala = () => {
  const setor = String(route.query.setor || equipeResp.value?.setor || '')
  router.push({ path: '/escala-matriz-v3', query: setor ? { setor } : {} })
}

const confirmarNotificacaoCoordenacao = () => {
  notificandoCoordenacao.value = true
  const setor = String(route.query.setor || equipeResp.value?.setor || 'Setor não informado')
  const competencia = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  api.post('/api/v3/banco-horas/equipe/notificacao-operacional', {
    setor,
    competencia,
    tipo: 'notificar',
    alvo: notificarAlvo.value,
    mensagem: `Notificação operacional registrada para ${setor} na competência ${competencia}.`,
  }).then(() => {
    modalOpTexto.value = 'Notificação operacional registrada com sucesso. Próximo passo: alinhar coberturas com coordenação e RH.'
    carregarHistoricoOperacional()
  }).catch((e) => {
    modalOpTexto.value = e?.response?.data?.erro || 'Falha ao registrar notificação operacional.'
  }).finally(() => {
    notificandoCoordenacao.value = false
  })
}

const carregarImpactoEscala = async () => {
  const setor = String(route.query.setor || equipeResp.value?.setor || '')
  const competencia = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  impactoEscalaLoading.value = true
  impactoEscalaErro.value = ''
  try {
    const { data } = await api.get('/api/v3/banco-horas/equipe/impacto-escala', {
      params: { setor, competencia },
    })
    impactoEscalaMembros.value = Array.isArray(data?.membros_impacto) ? data.membros_impacto : []
    impactoEscalaResumo.value = data?.resumo ?? { total_impactados: 0, desconto_total_estimado: 0 }
  } catch (e) {
    impactoEscalaErro.value = e?.response?.data?.erro || 'Falha ao calcular impacto de cobertura.'
    impactoEscalaMembros.value = []
    impactoEscalaResumo.value = { total_impactados: 0, desconto_total_estimado: 0 }
  } finally {
    impactoEscalaLoading.value = false
  }
}

const carregarHistoricoOperacional = async () => {
  const setor = String(route.query.setor || equipeResp.value?.setor || '')
  const competencia = `${mesAtual.value.getFullYear()}-${String(mesAtual.value.getMonth() + 1).padStart(2,'0')}`
  if (!setor) {
    modalHistorico.value = []
    return
  }
  try {
    const { data } = await api.get('/api/v3/banco-horas/equipe/notificacao-operacional', {
      params: { setor, competencia },
    })
    modalHistorico.value = Array.isArray(data?.historico) ? data.historico : []
  } catch {
    modalHistorico.value = []
  }
}

const aplicarAcaoVindaDoPonto = () => {
  const acao = String(route.query.acao || '')
  if (!acao) return
  if (['criticos', 'escala', 'notificar'].includes(acao)) {
    abrirModalOperacional(acao)
  }
}

onMounted(async () => {
  const compQuery = String(route.query.competencia || '')
  if (/^\d{4}-\d{2}$/.test(compQuery)) {
    const [y, m] = compQuery.split('-').map(Number)
    mesAtual.value = new Date(y, Math.max(0, m - 1), 1)
  }
  const modoQuery = String(route.query.modo || '')
  if (modoQuery === 'equipe') {
    modoEquipe.value = true
    await fetchEquipe()
    aplicarAcaoVindaDoPonto()
  } else {
    await fetchRegistros()
  }
  setTimeout(() => { loaded.value = true }, 80)
})

const atualizarPorModo = () => (modoEquipe.value ? fetchEquipe() : fetchRegistros())
watch(modoEquipe, () => {
  tipoFiltro.value = 'todos'
  tipoFiltroEquipe.value = 'todos'
  atualizarPorModo()
})
watch(escopoEquipe, (novo) => {
  if (novo !== 'setor') setorEquipeId.value = ''
})
watch(mesAtual, () => { atualizarPorModo() })
watch(() => route.query, () => {
  const modoQuery = String(route.query.modo || '')
  if (modoQuery === 'equipe' && !modoEquipe.value) {
    modoEquipe.value = true
  }
  if (modoQuery === 'equipe') {
    aplicarAcaoVindaDoPonto()
  }
})

const mesAnterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() - 1); mesAtual.value = d }
const mesPosterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() + 1); mesAtual.value = d }
const mesLabel = computed(() => mesAtual.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase()))

const saldoAcumuladoMin = computed(() => {
  if (saldoAcumuladoBackend.value !== null) return Math.round(Number(saldoAcumuladoBackend.value || 0) * 60)
  return saldo.value.totalMin
})

const diasComSit = computed(() => registros.value.map(d => {
  let sitLabel = '—', sitClass = 'sit-gray'
  const temBatida = Boolean(d.entrada || d.saida)
  if (d.saldoMin === null)       { sitLabel = 'Futuro';       sitClass = 'sit-light' }
  else if ((d.isFds || d.isFeriado) && !temBatida) {
    sitLabel = d.isFeriado ? 'Feriado' : 'Fim de Semana'
    sitClass = 'sit-gray'
  }
  else if ((d.isFds || d.isFeriado) && temBatida) {
    sitLabel = 'Extra'
    sitClass = 'sit-blue'
  }
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
const membrosEquipeFiltrados = computed(() => {
  const membros = equipeResp.value?.membros ?? []
  if (tipoFiltroEquipe.value === 'todos') return membros
  if (tipoFiltroEquipe.value === 'positivo') return membros.filter(m => Number(m.saldo_mes) > 0)
  if (tipoFiltroEquipe.value === 'negativo') return membros.filter(m => Number(m.saldo_mes) < 0)
  if (tipoFiltroEquipe.value === 'zerado') return membros.filter(m => Number(m.saldo_mes) === 0)
  return membros
})

const diasUteis = computed(() => registros.value.filter(d => !d.isFds && !d.isFeriado).length)
const diasUtilsPassados = computed(() => registros.value.filter(d => !d.isFds && !d.isFeriado && d.saldoMin !== null).length)

const saldo = computed(() => {
  const dias = registros.value.filter(d => !d.isFds && d.saldoMin !== null)
  const total = dias.reduce((a, d) => a + d.saldoMin, 0)
  const extras = dias.filter(d => d.saldoMin > 0).reduce((a, d) => a + d.saldoMin, 0)
  const neg = dias.filter(d => d.saldoMin < 0 && d.saldoMin > -480).reduce((a, d) => a + Math.abs(d.saldoMin), 0)
  const trab = diasUtilsPassados.value * 480 + total
  return {
    esperadasMin:  diasUtilsPassados.value * 480,
    trabalhadasMin: Math.max(0, Math.round(trab)),
    normaisMin:    Math.max(0, Math.round(trab - extras)),
    extrasMin:     Math.max(0, Math.round(extras)),
    negativasMin:  Math.max(0, Math.round(neg)),
    totalMin:      Math.round(total),
  }
})

const formatMinutosSmart = (mins, withSign = false) => {
  if (mins === null || mins === undefined) return '—'
  const total = Math.round(Number(mins) || 0)
  const abs = Math.abs(total)
  const h = Math.floor(abs / 60)
  const m = abs % 60
  const sign = total < 0 ? '-' : (withSign && total > 0 ? '+' : '')
  if (h === 0) return `${sign}${m}min`
  if (m === 0) return `${sign}${h}h`
  return `${sign}${h}h ${String(m).padStart(2, '0')}min`
}
const formatHorasSmart = (horas, withSign = false) => formatMinutosSmart(Math.round(Number(horas || 0) * 60), withSign)
const formatSaldo = (m) => { if (m === null) return '—'; return formatMinutosSmart(m, true) }
const saldoClass  = (m) => m === null ? '' : m >= 60 ? 'saldo-pos' : m < -15 ? 'saldo-neg' : 'saldo-ok'
const formatMoneyBr = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0))
</script>

<style scoped>
.bh-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.bh-page { position: relative; overflow: hidden; }
.bh-bg-pulse {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(120% 80% at 20% -10%, rgba(59,130,246,0.08), transparent 50%),
    radial-gradient(120% 80% at 90% 120%, rgba(20,184,166,0.08), transparent 55%);
  z-index: 0;
}
.bh-page > * { position: relative; z-index: 1; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0b1f3a 0%, #114a72 55%, #0f766e 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%),
    linear-gradient(-45deg, rgba(255,255,255,0.05) 25%, transparent 25%);
  background-size: 24px 24px;
  opacity: 0.22;
}
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #38bdf8; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #fde68a; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #bae6fd; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-local { font-size: 11px; color: #e2e8f0; margin: 6px 0 0; font-weight: 600; }
.month-nav { display: flex; align-items: center; gap: 12px; }
.mnav-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); font-size: 18px; cursor: pointer; color: #fff; font-weight: 900; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.mnav-btn:hover { background: rgba(255,255,255,0.15); }
.mes-label-nav { font-size: 15px; font-weight: 800; color: #fff; white-space: nowrap; min-width: 150px; text-align: center; }
.loading-bar { height: 3px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.loading-bar span { display: block; height: 100%; width: 40%; background: linear-gradient(to right, #3b82f6, #10b981); border-radius: 99px; animation: loadSlide 1.2s ease-in-out infinite; }
@keyframes loadSlide { 0% { transform: translateX(-100%); } 100% { transform: translateX(350%); } }
.kpi-strip { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr)); gap: 12px; width: 100%; box-sizing: border-box; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.kpi-strip.loaded { opacity: 1; transform: none; }
.kpi-card { background: #fff; border: 1px solid #dbeafe; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-top: 3px solid; box-shadow: 0 3px 10px rgba(2, 132, 199, 0.08); }
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
.warn-badge { margin-top: 8px; font-size: 11px; font-weight: 700; color: #b45309; }
.table-card {
  position: relative;
  background:
    linear-gradient(130deg, rgba(226, 240, 252, 0.45), rgba(214, 247, 242, 0.28)),
    rgba(255,255,255,0.96);
  border: 1px solid #c7deef;
  border-radius: 22px;
  overflow: hidden;
  opacity: 0;
  transform: translateY(8px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s;
}
.table-card::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  border-radius: 22px;
  border: 8px solid transparent;
  background:
    repeating-linear-gradient(-45deg, rgba(56,189,248,0.16) 0 6px, rgba(56,189,248,0.06) 6px 12px);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  padding: 6px;
}
.table-card.loaded { opacity: 1; transform: none; }
.tc-hdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px; }
.tc-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.tc-filters { display: flex; gap: 6px; }
.team-actions { display: flex; gap: 8px; padding: 10px 16px 0; flex-wrap: wrap; }
.team-action-btn {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: all .18s ease;
}
.team-action-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,.12); }
.team-action-btn.criticos { background: linear-gradient(135deg, #fee2e2, #fff1f2); color: #991b1b; }
.team-action-btn.escala { background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1d4ed8; }
.team-action-btn.notificar { background: linear-gradient(135deg, #ede9fe, #f5f3ff); color: #5b21b6; }
.f-btn { padding: 6px 12px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 11px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.15s; }
.f-btn.active { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.table-scroll { overflow-x: auto; padding: 4px 8px 10px; }
.bh-table { width: 100%; border-collapse: collapse; }
.bh-table thead tr { background: rgba(248, 250, 252, 0.82); backdrop-filter: blur(2px); }
.bh-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.bh-row { border-bottom: 1px solid rgba(226,232,240,0.65); transition: background 0.2s, transform 0.2s, box-shadow 0.2s; }
.bh-row:hover { background: rgba(255,255,255,0.86); transform: translateY(-1px); box-shadow: inset 0 0 0 1px rgba(186,230,253,0.7); }
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
.modo-toggle { display: flex; border: 1.5px solid rgba(255,255,255,0.2); border-radius: 12px; overflow: hidden; }
.mt-btn { padding: 7px 14px; background: transparent; border: none; color: rgba(255,255,255,0.6); font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.mt-btn.active { background: rgba(255,255,255,0.18); color: #fff; }
.td-nome { font-weight: 700; color: #1e293b; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 16px; }
.modal-box {
  width: min(760px, 100%);
  position: relative;
  background:
    linear-gradient(160deg, rgba(238,248,255,0.96), rgba(232,250,246,0.92));
  border-radius: 20px;
  border: 1px solid #c7deef;
  box-shadow: 0 24px 54px rgba(2,6,23,.25);
  overflow: hidden;
}
.modal-box::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  border-radius: 20px;
  border: 10px solid transparent;
  background: repeating-linear-gradient(-45deg, rgba(56,189,248,0.14) 0 7px, rgba(56,189,248,0.05) 7px 14px);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  padding: 7px;
}
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
.modal-title { margin: 0; font-size: 16px; color: #0f172a; }
.modal-close { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; width: 32px; height: 32px; cursor: pointer; }
.modal-body { padding: 14px 16px 16px; display: grid; gap: 10px; }
.modal-text { margin: 0; font-size: 13px; color: #334155; }
.modal-list { display: grid; gap: 8px; max-height: 280px; overflow: auto; }
.modal-item {
  border: 1px solid #cfe2f1;
  border-radius: 14px;
  padding: 10px 12px;
  display: grid;
  gap: 2px;
  font-size: 12px;
  color: #334155;
  background: linear-gradient(135deg, rgba(255,255,255,0.84), rgba(239,246,255,0.82));
  animation: modalCardIn .32s cubic-bezier(0.22,1,0.36,1);
}
.modal-item strong { color: #1e293b; }
.modal-item:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(14,116,144,0.12); }
.modal-empty { font-size: 12px; color: #64748b; }
.modal-err { color: #b91c1c; }
.modal-next { display: grid; gap: 8px; }
.notify-target { display: grid; gap: 4px; }
.notify-target label { font-size: 11px; color: #475569; font-weight: 700; }
.notify-select { border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 8px; font-size: 12px; color: #334155; background: #fff; }
.impact-wrap { display: grid; gap: 8px; width: 100%; margin-bottom: 4px; }
.impact-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.impact-kpi { border: 1px solid #d6e8f6; border-radius: 10px; padding: 8px 10px; background: rgba(255,255,255,0.82); display: grid; gap: 2px; }
.impact-kpi strong { font-size: 14px; color: #0f172a; }
.impact-kpi span { font-size: 11px; color: #475569; }
.impact-list { display: grid; gap: 8px; max-height: 220px; overflow: auto; }
.impact-item { border: 1px solid #d6e8f6; border-radius: 10px; padding: 8px 10px; background: rgba(255,255,255,0.82); display: grid; gap: 2px; font-size: 12px; color: #475569; }
.impact-item strong { color: #1e293b; }
.modal-history { margin-top: 8px; display: grid; gap: 8px; }
.modal-history strong { font-size: 12px; color: #334155; }
.modal-history-item { border: 1px solid #d6e8f6; border-radius: 10px; padding: 7px 9px; display: flex; justify-content: space-between; gap: 8px; font-size: 11px; color: #475569; background: rgba(255,255,255,0.82); }
@keyframes modalCardIn {
  from { opacity: 0; transform: translateY(6px) scale(0.985); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
