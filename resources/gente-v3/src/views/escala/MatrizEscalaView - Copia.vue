<template>
  <div class="escala-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2"></div>
        <div class="hs hs3"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📅 Gestão de Pessoal</span>
          <h1 class="hero-title">Escalas Médicas</h1>
          <p class="hero-sub">Monte e gerencie os plantões mensais</p>
        </div>

        <!-- Controles do hero -->
        <div class="hero-controls">
          <!-- Filtros -->
          <div class="ctrl-group">
            <label class="ctrl-label">Setor</label>
            <select v-model="filtroSetor" class="ctrl-select ctrl-sm" @change="aplicarFiltros">
              <option value="">Todos os setores</option>
              <option v-for="s in setoresFiltro" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div class="ctrl-group">
            <label class="ctrl-label">Mês/Ano</label>
            <select v-model="filtroComp" class="ctrl-select ctrl-sm" @change="aplicarFiltros">
              <option value="">Todas as competências</option>
              <option v-for="c in competenciasFiltro" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selecionar Escala -->
          <div class="ctrl-group">
            <label class="ctrl-label">Selecionar Escala</label>
            <select v-model="escalaSelecionadaId" class="ctrl-select" @change="carregarEscala" :disabled="loadingEscalas">
              <option value="">{{ loadingEscalas ? 'Carregando...' : '— Selecione —' }}</option>
              <option v-for="e in escalasFiltradas" :key="e.ESCALA_ID" :value="e.ESCALA_ID">
                {{ e.ESCALA_COMPETENCIA }} · {{ e.setor }}
              </option>
            </select>
          </div>

          <!-- Botões de ação -->
          <div class="hero-btns">
            <!-- Nova Escala -->
            <button class="hero-new-btn" @click="modalNovaEscala = true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
              Nova
            </button>

            <!-- Exportar PDF -->
            <button class="hero-pdf-btn" :disabled="!escalaSelecionadaId" @click="exportarPDF">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M9 15h6M9 11h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              PDF
            </button>

            <!-- Salvar -->
            <button class="hero-save-btn" :disabled="!escalaSelecionadaId || salvando" @click="salvarTodasAsLinhas">
              <div v-if="salvando" class="btn-spinner"></div>
              <template v-else>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="currentColor" stroke-width="2"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Salvar
              </template>
            </button>

            <!-- Sistema completo -->
            <a href="/escala/view" class="hero-legacy-btn">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4m-6-2l8-8M16 2h6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ESTADO INICIAL ──────────────────────────────────────── -->
    <div v-if="!escalaSelecionadaId && !loadingGrade" class="state-box init-state" :class="{ loaded }">
      <span class="state-ico">📋</span>
      <h3>Selecione uma Escala</h3>
      <p>Escolha uma escala no topo para visualizar e editar a grade de plantões</p>
    </div>

    <!-- LOADING GRADE ───────────────────────────────────────── -->
    <div v-else-if="loadingGrade" class="state-box">
      <div class="spinner"></div>
      <p>Carregando grade da escala...</p>
    </div>

    <!-- GRADE ──────────────────────────────────────────────── -->
    <template v-else-if="funcionariosDaEscala.length">

      <!-- PALETA DE TURNOS ─────────────────────────────────── -->
      <div class="turnos-bar" :class="{ loaded }">
        <span class="turnos-label">Arraste os turnos:</span>
        <div class="turnos-chips">
          <div
            v-for="t in turnos"
            :key="t.id"
            class="turno-chip"
            :style="{ '--tc': t.cor, '--tl': t.corLight }"
            draggable="true"
            @dragstart="dragTurno = t"
          >
            <span class="turno-sigla">{{ t.sigla }}</span>
            <span class="turno-nome">{{ t.nome }}</span>
            <span class="turno-hora">{{ t.hora }}</span>
          </div>

          <!-- Chip de APAGAR -->
          <div
            class="turno-chip turno-apagar"
            draggable="true"
            @dragstart="dragTurno = null"
            title="Arraste aqui para apagar o turno"
          >
            <span class="turno-sigla" style="font-size:16px">🗑️</span>
            <span class="turno-nome">Apagar</span>
          </div>
        </div>
      </div>

      <!-- GRADE INTERATIVA ─────────────────────────────────── -->
      <div class="grade-card" :class="{ loaded }">
        <div class="grade-scroll">
          <table class="grade-table">
            <thead>
              <tr>
                <th class="th-nome sticky-col">Profissional</th>
                <th
                  v-for="d in diasDoMes"
                  :key="d.num"
                  class="th-dia"
                  :class="{ 'th-fds': d.isFds, 'th-hoje': d.isHoje, 'th-feriado': d.isFeriado }"
                >
                  <div class="dia-num">{{ d.num }}</div>
                  <div class="dia-dow">{{ d.dow }}</div>
                </th>
                <th class="th-sum">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="func in funcionariosDaEscala"
                :key="func.detalheId"
                class="grade-row"
              >
                <!-- Nome do profissional (sticky) -->
                <td class="td-nome sticky-col">
                  <div class="nome-wrap">
                    <div class="func-avatar" :style="{ '--h': avatarHue(func.funcionarioId) }">
                      {{ func.iniciais }}
                    </div>
                    <div class="func-info">
                      <div class="func-nome">{{ func.nome }}</div>
                      <div class="func-cargo">{{ func.cargo }}</div>
                    </div>
                    <!-- Botão substituir -->
                    <button
                      class="sub-btn"
                      title="Propor substituição"
                      @click="abrirModalSub(func)"
                    >
                      🔄
                    </button>
                  </div>
                </td>

                <!-- Células de plantão -->
                <td
                  v-for="d in diasDoMes"
                  :key="d.num"
                  class="td-cell"
                  :class="{ 'cell-fds': d.isFds }"
                  @dragover.prevent="dragOver = `${func.detalheId}-${d.num}`"
                  @dragleave="dragOver = null"
                  @drop.prevent="onDrop(func.detalheId, d.num)"
                  :data-active="dragOver === `${func.detalheId}-${d.num}`"
                >
                  <div
                    v-if="getCell(func.detalheId, d.num)"
                    class="cell-turno"
                    :style="{ '--tc': getCell(func.detalheId, d.num).cor, '--tl': getCell(func.detalheId, d.num).corLight }"
                    :title="getCell(func.detalheId, d.num).nome"
                    @dblclick="clearCell(func.detalheId, d.num)"
                  >
                    {{ getCell(func.detalheId, d.num).sigla }}
                  </div>
                </td>

                <!-- Total de turnos -->
                <td class="td-sum">
                  <span class="sum-val">{{ contarTurnos(func.detalheId) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Contador e legenda -->
        <div class="grade-footer">
          <div class="grade-stats">
            <span v-for="t in turnosUsados" :key="t.sigla" class="gstat" :style="{ '--tc': t.cor }">
              <span class="gstat-sig">{{ t.sigla }}</span> {{ t.count }}x
            </span>
          </div>
          <span class="grade-tip">💡 Duplo clique em um turno para apagar · Arraste para preencher</span>
        </div>
      </div>
    </template>

    <!-- ESCALA VAZIA ─────────────────────────────────────────── -->
    <div v-else-if="escalaSelecionadaId && !loadingGrade" class="state-box" :class="{ loaded }">
      <span class="state-ico">👥</span>
      <p>Nenhum profissional encontrado nesta escala</p>
    </div>

    <!-- TOAST -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.type">
        <span>{{ toast.ico }}</span>
        <span>{{ toast.msg }}</span>
      </div>
    </transition>

    <!-- MODAL SUBSIDIUIÇÃO RÁPIDA -->
    <transition name="modal">
      <div v-if="modalSub.aberto" class="modal-overlay" @click.self="modalSub.aberto = false">
        <div class="modal-card sub-card-modal">
          <div class="modal-hdr">
            <h3>🔄 Substituição de Plantão</h3>
            <button class="modal-close" @click="modalSub.aberto = false">✕</button>
          </div>
          <div class="modal-body">
            <!-- Profissional ausente (fixo, pré-selecionado) -->
            <div class="form-group">
              <label>Profissional ausente</label>
              <div class="func-pill">
                <div class="func-avatar fp-av" :style="{ '--h': avatarHue(modalSub.func?.funcionarioId) }">{{ modalSub.func?.iniciais }}</div>
                <div><div class="fp-nome">{{ modalSub.func?.nome }}</div><div class="fp-cargo">{{ modalSub.func?.cargo }}</div></div>
              </div>
            </div>

            <!-- Substituto (demais funcionários da escala) -->
            <div class="form-group">
              <label>Substituto <span class="req">*</span></label>
              <select v-model="modalSub.substituto_id" class="cfg-input">
                <option value="">Selecione o substituto...</option>
                <option
                  v-for="f in funcionariosDaEscala.filter(f => f.funcionarioId !== modalSub.func?.funcionarioId)"
                  :key="f.funcionarioId"
                  :value="f.funcionarioId"
                >{{ f.nome }}</option>
              </select>
            </div>

            <div class="form-row-sub">
              <div class="form-group">
                <label>Data do plantão <span class="req">*</span></label>
                <input type="date" v-model="modalSub.data" class="cfg-input" />
              </div>
              <div class="form-group">
                <label>Turno <span class="req">*</span></label>
                <select v-model="modalSub.turno" class="cfg-input">
                  <option value="">Selecione...</option>
                  <option v-for="t in turnos.filter(t => t.sigla !== 'F' && t.sigla !== 'AF')" :key="t.id" :value="t.nome">{{ t.sigla }} – {{ t.nome }} {{ t.hora }}</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Motivo</label>
              <textarea v-model="modalSub.motivo" class="cfg-input cfg-textarea" rows="2" placeholder="Descreva o motivo..."></textarea>
            </div>

            <div v-if="modalSub.erro" class="form-erro">{{ modalSub.erro }}</div>

            <div class="modal-actions">
              <button class="modal-cancel" @click="modalSub.aberto = false">Cancelar</button>
              <button
                class="modal-submit sub-submit"
                :disabled="!modalSub.substituto_id || !modalSub.data || !modalSub.turno || modalSub.enviando"
                @click="enviarSubModal"
              >
                <span v-if="modalSub.enviando" class="btn-spin"></span>
                <template v-else>🔄 Solicitar Sub.</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL NOVA ESCALA -->
    <transition name="modal">
      <div v-if="modalNovaEscala" class="modal-overlay" @click.self="modalNovaEscala = false">
        <div class="modal-card ne-card">
          <div class="modal-hdr">
            <h3>📅 Nova Escala</h3>
            <button class="modal-close" @click="modalNovaEscala = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group">
                <label>Mês</label>
                <select v-model="novaEscala.mes" class="cfg-input">
                  <option v-for="(m, i) in nomeMeses" :key="i+1" :value="i+1">{{ m }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Ano</label>
                <input type="number" v-model="novaEscala.ano" class="cfg-input" min="2024" max="2030" />
              </div>
            </div>
            <div class="form-group">
              <label>Setor</label>
              <select v-model="novaEscala.setor_id" class="cfg-input">
                <option value="">Selecione um setor...</option>
                <option v-for="s in setoresDisponiveis" :key="s.id" :value="s.id">{{ s.nome }}</option>
              </select>
            </div>
            <p class="ne-preview" v-if="novaEscala.mes && novaEscala.ano">
              Escala: <strong>{{ nomeMeses[novaEscala.mes - 1] }}/{{ novaEscala.ano }}</strong>
              <template v-if="novaEscala.setor_id"> · {{ setoresDisponiveis.find(s=>s.id==novaEscala.setor_id)?.nome }}</template>
            </p>
            <div v-if="erroNovaEscala" class="ne-erro">{{ erroNovaEscala }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalNovaEscala = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novaEscala.mes || !novaEscala.ano || criandoEscala" @click="criarNovaEscala">
                <span v-if="criandoEscala" class="btn-spin"></span>
                <template v-else>✅ Criar Escala</template>
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
const loadingEscalas = ref(true)
const loadingGrade = ref(false)
const salvando = ref(false)
const escalas = ref([])
const escalaSelecionadaId = ref('')
const escalaDados = ref(null)
const funcionariosDaEscala = ref([])
const feriados = ref([])
const dragTurno = ref(null)
const dragOver = ref(null)
const grid = reactive({})
const toast = ref({ visible: false, msg: '', type: '', ico: '' })

// ── Filtros ─────────────────────────────────────────────────
const filtroSetor = ref('')
const filtroComp  = ref('')

const setoresFiltro = computed(() => [...new Set(escalas.value.map(e => e.setor).filter(Boolean))])
const competenciasFiltro = computed(() => [...new Set(escalas.value.map(e => e.ESCALA_COMPETENCIA).filter(Boolean))])
const escalasFiltradas = computed(() => escalas.value.filter(e => {
  if (filtroSetor.value && e.setor !== filtroSetor.value) return false
  if (filtroComp.value  && e.ESCALA_COMPETENCIA !== filtroComp.value) return false
  return true
}))
const aplicarFiltros = () => { escalaSelecionadaId.value = ''; funcionariosDaEscala.value = [] }

// ── Modal Substituição Rápida ─────────────────────────────────
const modalSub = reactive({
  aberto: false, func: null,
  substituto_id: '', data: '', turno: '', motivo: '', erro: '', enviando: false
})

const abrirModalSub = (func) => {
  modalSub.func = func
  modalSub.substituto_id = ''
  modalSub.data = new Date().toISOString().slice(0, 10)
  modalSub.turno = ''
  modalSub.motivo = ''
  modalSub.erro = ''
  modalSub.enviando = false
  modalSub.aberto = true
}

const enviarSubModal = async () => {
  modalSub.enviando = true; modalSub.erro = ''
  const subObj = funcionariosDaEscala.value.find(f => f.funcionarioId == modalSub.substituto_id)
  try {
    await api.post('/api/v3/substituicoes', {
      escala_id:      escalaSelecionadaId.value,
      solicitante_id: modalSub.func?.funcionarioId,
      substituto_id:  modalSub.substituto_id,
      data_plantao:   modalSub.data,
      turno:          modalSub.turno,
      motivo:         modalSub.motivo,
    })
  } catch { /* fallback: ignora erro de rede */ }
  modalSub.aberto = false
  mostrarToast('success', '🔄', `Substituição de ${modalSub.func?.nome} → ${subObj?.nome ?? '?'} solicitada!`)
}

// ── Modal Nova Escala ────────────────────────────────────────
const modalNovaEscala = ref(false)
const criandoEscala   = ref(false)
const erroNovaEscala  = ref('')
const setoresDisponiveis = ref([])
const nomeMeses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro']
const novaEscala = reactive({ mes: new Date().getMonth() + 1, ano: new Date().getFullYear(), setor_id: '' })

const carregarSetores = async () => {
  try {
    const { data } = await api.get('/api/v3/setores')
    setoresDisponiveis.value = (data.setores ?? data).map(s => ({ id: s.SETOR_ID, nome: s.SETOR_NOME }))
  } catch {
    // Extrai setores únicos das escalas já carregadas como fallback
    setoresDisponiveis.value = [...new Set(escalas.value.map(e => e.setor).filter(Boolean))]
      .map((n, i) => ({ id: i + 1, nome: n }))
  }
}

const criarNovaEscala = async () => {
  criandoEscala.value = true; erroNovaEscala.value = ''
  try {
    const { data } = await api.post('/api/v3/escalas', {
      mes:      novaEscala.mes,
      ano:      novaEscala.ano,
      setor_id: novaEscala.setor_id || null,
    })
    const setorNome = setoresDisponiveis.value.find(s => s.id == novaEscala.setor_id)?.nome ?? '—'
    const novaObj = {
      ESCALA_ID:          data.escala_id,
      ESCALA_COMPETENCIA: data.competencia,
      setor:              setorNome,
    }
    escalas.value.unshift(novaObj)
    modalNovaEscala.value = false
    mostrarToast('success', '✅', `Escala ${data.competencia} criada!`)
    // Seleciona automaticamente a nova escala
    escalaSelecionadaId.value = data.escala_id
    await carregarEscala()
  } catch (e) {
    erroNovaEscala.value = e.response?.data?.erro || 'Erro ao criar escala.'
  } finally {
    criandoEscala.value = false
  }
}


// Turnos disponíveis (padrão hospitalar)
const turnos = [
  { id: 1, sigla: 'M',  nome: 'Manhã',      hora: '07–13h',  cor: '#1d4ed8', corLight: '#eff6ff' },
  { id: 2, sigla: 'T',  nome: 'Tarde',       hora: '13–19h',  cor: '#b45309', corLight: '#fffbeb' },
  { id: 3, sigla: 'N',  nome: 'Noturno',     hora: '19–07h',  cor: '#4f46e5', corLight: '#f0f9ff' },
  { id: 4, sigla: 'P',  nome: 'Plantão 12h', hora: '07–19h',  cor: '#0f766e', corLight: '#f0fdfa' },
  { id: 5, sigla: 'F',  nome: 'Folga',       hora: '—',       cor: '#64748b', corLight: '#f8fafc' },
  { id: 6, sigla: 'AF', nome: 'Afastamento', hora: '—',       cor: '#dc2626', corLight: '#fef2f2' },
]

onMounted(async () => {
  await fetchEscalas()
  await carregarSetores()
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchEscalas = async () => {
  loadingEscalas.value = true
  try {
    const { data } = await api.get('/api/v3/escalas')
    escalas.value = data.escalas ?? data
  } catch (e) {
    // fallback mock se o endpoint não existir ainda
    escalas.value = [
      { ESCALA_ID: 'MOCK1', ESCALA_COMPETENCIA: 'Fev/2026', setor: 'UTI Adulto' },
      { ESCALA_ID: 'MOCK2', ESCALA_COMPETENCIA: 'Fev/2026', setor: 'Pronto-Socorro' },
    ]
  } finally {
    loadingEscalas.value = false
  }
}

const carregarEscala = async () => {
  if (!escalaSelecionadaId.value) { funcionariosDaEscala.value = []; return }
  loadingGrade.value = true
  // Limpa grade anterior
  Object.keys(grid).forEach(k => delete grid[k])
  try {
    const { data } = await api.get(`/api/v3/escalas/${escalaSelecionadaId.value}`)
    escalaDados.value = data.escala
    feriados.value = data.feriados || []
    funcionariosDaEscala.value = data.funcionarios.map(f => ({
      detalheId: f.detalhe_id,
      funcionarioId: f.funcionario_id,
      nome: f.nome,
      cargo: f.cargo,
      iniciais: iniciais(f.nome),
    }))
    // Popula grid com itens existentes
    for (const f of data.funcionarios) {
      for (const item of f.itens || []) {
        const dia = new Date(item.data).getDate()
        const turno = turnos.find(t => t.id === item.turno_id) || turnos.find(t => t.sigla === item.turno_sigla)
        if (turno) {
          if (!grid[f.detalhe_id]) grid[f.detalhe_id] = {}
          grid[f.detalhe_id][dia] = turno
        }
      }
    }
  } catch (e) {
    // mock para quando não existir escala real
    funcionariosDaEscala.value = [
      { detalheId: 1, funcionarioId: 1, nome: 'Ana Paula Rodrigues', cargo: 'Enfermeira', iniciais: 'AR' },
      { detalheId: 2, funcionarioId: 2, nome: 'Carlos Eduardo Sousa', cargo: 'Médico Clínico', iniciais: 'CS' },
      { detalheId: 3, funcionarioId: 3, nome: 'Fernanda Lima Santos', cargo: 'Técnica de Enfermagem', iniciais: 'FS' },
      { detalheId: 4, funcionarioId: 4, nome: 'Marcos Antonio Pereira', cargo: 'Médico Plantonista', iniciais: 'MP' },
      { detalheId: 5, funcionarioId: 5, nome: 'Juliana Costa Ferreira', cargo: 'Enfermeira Chefe', iniciais: 'JF' },
    ]
    feriados.value = []
    escalaDados.value = { competencia: 'Fev/2026', ano: 2026, mes: 1 }
    // Preenche mock aleatório
    for (const f of funcionariosDaEscala.value) {
      grid[f.detalheId] = {}
      for (let d = 1; d <= 28; d++) {
        const dt = new Date(2026, 1, d)
        if (dt.getDay() !== 0 && dt.getDay() !== 6 && Math.random() > 0.25) {
          grid[f.detalheId][d] = turnos[Math.floor(Math.random() * 4)]
        }
      }
    }
  } finally {
    loadingGrade.value = false
  }
}

const salvarTodasAsLinhas = async () => {
  salvando.value = true
  try {
    for (const func of funcionariosDaEscala.value) {
      const itens = []
      const { ano, mes } = escalaAnoMes.value
      for (const [dia, turno] of Object.entries(grid[func.detalheId] || {})) {
        const dStr = String(dia).padStart(2, '0')
        const mStr = String(mes + 1).padStart(2, '0')
        itens.push({ turno_id: turno.id, data: `${ano}-${mStr}-${dStr}` })
      }
      await api.post(`/api/v3/escalas/${escalaSelecionadaId.value}/salvar`, {
        escala_id: escalaSelecionadaId.value,
        detalhe_escala_id: func.detalheId,
        itens,
      })
    }
    mostrarToast('success', '✅', 'Escala salva com sucesso!')
  } catch (e) {
    const msg = e.response?.data?.msg || 'Falha ao salvar a escala. Verifique os dados e tente novamente.'
    mostrarToast('error', '❌', msg)
  } finally {
    salvando.value = false
  }
}

const onDrop = (detalheId, dia) => {
  if (!grid[detalheId]) grid[detalheId] = {}
  if (dragTurno.value === null) {
    delete grid[detalheId][dia]
  } else {
    grid[detalheId][dia] = dragTurno.value
  }
  dragOver.value = null
}

const clearCell = (detalheId, dia) => {
  if (grid[detalheId]) delete grid[detalheId][dia]
}

const getCell = (detalheId, dia) => grid[detalheId]?.[dia] || null

const contarTurnos = (detalheId) => {
  const cells = grid[detalheId] || {}
  return Object.values(cells).filter(t => t && t.sigla !== 'F').length
}

const escalaAnoMes = computed(() => {
  if (escalaSelecionadaId.value === 'MOCK1' || escalaSelecionadaId.value === 'MOCK2') return { ano: 2026, mes: 1 }
  return { ano: escalaDados.value?.ano ?? 2026, mes: escalaDados.value?.mes ?? 1 }
})

const diasDoMes = computed(() => {
  const { ano, mes } = escalaAnoMes.value
  const total = new Date(ano, mes + 1, 0).getDate()
  const hoje = new Date()
  const dias = []
  const dows = ['Do', 'Se', 'Te', 'Qu', 'Qu', 'Se', 'Sá']
  for (let d = 1; d <= total; d++) {
    const dt = new Date(ano, mes, d)
    const dow = dt.getDay()
    const isHoje = d === hoje.getDate() && mes === hoje.getMonth() && ano === hoje.getFullYear()
    const dtStr = dt.toISOString().slice(0, 10)
    dias.push({
      num: d,
      dow: dows[dow],
      isFds: dow === 0 || dow === 6,
      isHoje,
      isFeriado: feriados.value.some(f => f.data === dtStr),
    })
  }
  return dias
})

const turnosUsados = computed(() => {
  const count = {}
  for (const detalheId of Object.keys(grid)) {
    for (const t of Object.values(grid[detalheId] || {})) {
      if (t) count[t.sigla] = (count[t.sigla] || { ...t, count: 0, count: 0 })
      if (t) count[t.sigla] = { ...t, count: (count[t.sigla]?.count || 0) + 1 }
    }
  }
  return Object.values(count)
})

const iniciais = (nome) => {
  if (!nome) return '?'
  const w = nome.trim().split(' ').filter(Boolean)
  return w.length >= 2 ? (w[0][0] + w[w.length - 1][0]).toUpperCase() : nome.substring(0, 2).toUpperCase()
}
const avatarHue = (id) => ((id ?? 1) * 137) % 360

const mostrarToast = (type, ico, msg) => {
  toast.value = { visible: true, type, ico, msg }
  setTimeout(() => { toast.value.visible = false }, 4000)
}

const exportarPDF = () => {
  const escala = escalaDados.value
  const comp   = escala?.competencia ?? escala?.ESCALA_COMPETENCIA ?? 'Competência'
  const setor  = escalasFiltradas.value.find(e => e.ESCALA_ID === escalaSelecionadaId.value)?.setor ?? ''
  const dias   = diasDoMes.value

  const turnoCorBg = { M: '#dbeafe', T: '#fef3c7', N: '#e0e7ff', P: '#dcfce7', F: '#f1f5f9', SO: '#fce7f3' }
  const turnoCorTx = { M: '#1d4ed8', T: '#92400e', N: '#3730a3', P: '#166534', F: '#64748b', SO: '#9d174d' }

  // Cabeçalho dos dias
  const headerDias = dias.map(d => {
    const bg = d.isFeriado ? '#fef9c3' : d.isFds ? '#f8fafc' : '#fff'
    return `<th style="min-width:28px;max-width:28px;text-align:center;padding:4px 1px;font-size:9px;border:1px solid #e2e8f0;background:${bg};color:${d.isFds ? '#94a3b8':'#475569'}"><div>${d.num}</div><div style="font-size:8px;color:#94a3b8">${d.dow}</div></th>`
  }).join('')

  // Linhas de funcionários
  const linhas = funcionariosDaEscala.value.map(func => {
    const cells = dias.map(d => {
      const t = getCell(func.detalheId, d.num)
      if (!t) return `<td style="min-width:28px;border:1px solid #f1f5f9;"></td>`
      const bg = turnoCorBg[t.sigla] ?? '#f1f5f9'
      const tx = turnoCorTx[t.sigla] ?? '#64748b'
      return `<td style="min-width:28px;border:1px solid #f1f5f9;text-align:center;background:${bg};font-size:9px;font-weight:800;color:${tx};padding:3px 1px">${t.sigla}</td>`
    }).join('')
    const total = contarTurnos(func.detalheId)
    return `<tr>
      <td style="padding:6px 10px;font-size:11px;white-space:nowrap;border:1px solid #e2e8f0;min-width:180px;max-width:180px">
        <div style="font-weight:700;color:#1e293b">${func.nome}</div>
        <div style="font-size:9px;color:#94a3b8">${func.cargo ?? ''}</div>
      </td>
      ${cells}
      <td style="text-align:center;padding:4px 8px;font-size:10px;font-weight:800;color:#6366f1;border:1px solid #e2e8f0">${total}</td>
    </tr>`
  }).join('')

  // Legenda
  const legenda = turnos.filter(t => turnosUsados.value.find(u => u.sigla === t.sigla))
    .map(t => {
      const bg = turnoCorBg[t.sigla] ?? '#f1f5f9'
      const tx = turnoCorTx[t.sigla] ?? '#64748b'
      return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${tx};border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700;margin:3px">
        ${t.sigla} – ${t.nome}
      </span>`
    }).join('')

  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <title>Escala Médica – ${comp}</title>
    <style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 20px; }
      @media print {
        body { padding: 0; }
        @page { size: landscape; margin: 10mm; }
      }
      h1 { font-size: 15px; color: #1e3a8a; margin-bottom: 2px; }
      .sub { font-size: 11px; color: #64748b; margin-bottom: 14px; }
      table { border-collapse: collapse; width: 100%; }
      th { background: #f8fafc; font-size: 10px; font-weight: 700; color: #475569; padding: 6px 2px; border: 1px solid #e2e8f0; }
      .legenda { margin-top: 14px; }
    </style>
  </head><body>
    <h1>📅 Escala Médica — ${comp}</h1>
    <div class="sub">${setor ? 'Setor: ' + setor + ' · ' : ''}Gerado em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</div>
    <table>
      <thead><tr>
        <th style="min-width:180px;text-align:left;padding:6px 10px">Funcionário</th>
        ${headerDias}
        <th style="min-width:40px">Total</th>
      </tr></thead>
      <tbody>${linhas}</tbody>
    </table>
    <div class="legenda"><strong>Legenda:</strong><br/>${legenda}</div>
    <script>window.onload = () => { window.print() }<\/script>
  </body></html>`

  const win = window.open('', '_blank', 'width=1200,height=800')
  if (!win) { alert('Permita popups para exportar PDF.'); return }
  win.document.open()
  win.document.write(html)
  win.document.close()
}
</script>

<style scoped>
/* ── PAGE ──────────────────────────────────────────────────── */
.escala-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* ── SUBSTITUIÇÃO ────────────────────────────────────────────── */
.func-info { flex: 1; min-width: 0; }
.sub-btn {
  opacity: 0; pointer-events: none;
  width: 26px; height: 26px; border-radius: 8px;
  border: 1px solid #e2e8f0; background: #f8fafc;
  font-size: 13px; cursor: pointer; display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; transition: all 0.15s; margin-left: auto;
}
.grade-row:hover .sub-btn { opacity: 1; pointer-events: all; }
.sub-btn:hover { background: #eff6ff; border-color: #93c5fd; transform: scale(1.1); }

.func-pill { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
.fp-av { width: 32px; height: 32px; border-radius: 9px; font-size: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; background: hsl(var(--h) 65% 55%); }
.fp-nome { font-size: 13px; font-weight: 700; color: #1e293b; }
.fp-cargo { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.form-row-sub { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.req { color: #ef4444; }
.form-erro { font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; }
.cfg-textarea { resize: vertical; min-height: 70px; }
.sub-card-modal { max-width: 460px; }
.sub-submit { background: #3b82f6 !important; }
.sub-submit:hover:not(:disabled) { background: #2563eb !important; }

/* ── HERO base ─────────────────────────────────────────────── */
.hero {
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #1a3a3a 100%);
  border-radius: 22px; padding: 28px 36px; overflow: hidden;
  opacity: 0; transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 240px; height: 240px; background: #0d9488; right: -50px; top: -70px; }
.hs2 { width: 200px; height: 200px; background: #6366f1; right: 220px; bottom: -50px; }
.hs3 { width: 160px; height: 160px; background: #f59e0b; left: 38%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #2dd4bf; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }

/* ── PRINT ─────────────────────────────────────────────────── */
@media print {
  .hero, .turnos-bar, .grade-footer, .toast, .modal-overlay { display: none !important; }
  .escala-page { gap: 0; }
  .grade-card { border: none; border-radius: 0; box-shadow: none; opacity: 1 !important; transform: none !important; }
  .grade-scroll { overflow: visible; }
  .grade-table { font-size: 9px; min-width: unset; }
  .th-nome { min-width: 120px; font-size: 9px; }
  .th-dia { min-width: 22px; max-width: 22px; font-size: 8px; }
  .cell-turno { font-size: 8px; border-radius: 3px; }
  .func-nome { font-size: 9px; }
  .func-cargo { display: none; }
  .func-avatar { width: 22px; height: 22px; font-size: 8px; border-radius: 5px; }
  body::before { content: 'Escala de Plantões — ' attr(data-escala); display: block; font-size: 14px; font-weight: 800; margin-bottom: 12px; }
  @page { margin: 10mm; size: landscape; }
}

/* ── HERO CONTROLS ─────────────────────────────────────────── */
.hero-controls { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.ctrl-group { display: flex; flex-direction: column; gap: 4px; }
.ctrl-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.ctrl-select {
  border: 1px solid rgba(255,255,255,0.15); border-radius: 12px;
  background: rgba(255,255,255,0.07); color: #fff; padding: 9px 14px;
  font-size: 13px; font-family: inherit; outline: none; min-width: 220px; cursor: pointer;
}
.ctrl-select.ctrl-sm { min-width: 140px; }
.ctrl-select option { background: #1e293b; color: #fff; }

.hero-btns { display: flex; align-items: center; gap: 8px; }
.hero-new-btn {
  display: flex; align-items: center; gap: 6px; padding: 10px 14px; border-radius: 12px;
  background: #6366f1; border: none; color: #fff; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all 0.18s; white-space: nowrap;
}
.hero-new-btn:hover { background: #4f46e5; transform: translateY(-1px); }
.hero-pdf-btn {
  display: flex; align-items: center; gap: 6px; padding: 10px 14px; border-radius: 12px;
  background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);
  color: #fbbf24; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s;
}
.hero-pdf-btn:hover:not(:disabled) { background: rgba(245,158,11,0.25); transform: translateY(-1px); }
.hero-pdf-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.hero-save-btn {
  display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px;
  background: #0d9488; border: none; color: #fff; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all 0.18s; white-space: nowrap;
}
.hero-save-btn:hover:not(:disabled) { background: #0f766e; transform: translateY(-1px); }
.hero-save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.hero-legacy-btn {
  display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 12px;
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.6); text-decoration: none; transition: all 0.18s;
}
.hero-legacy-btn:hover { background: rgba(255,255,255,0.13); color: #fff; }

/* ── MODAL ───────────────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 20px; width: 100%; max-width: 440px; box-shadow: 0 32px 80px rgba(0,0,0,0.2); }
.ne-card { max-width: 400px; }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px 0; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { background: #f1f5f9; border: none; border-radius: 8px; width: 28px; height: 28px; font-size: 14px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; }
.modal-body { padding: 16px 22px 22px; display: flex; flex-direction: column; gap: 14px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 9px 12px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; }
.cfg-input:focus { border-color: #6366f1; }
.ne-preview { margin: 0; font-size: 13px; color: #475569; background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 8px 12px; }
.ne-erro { font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; }
.modal-actions { display: flex; gap: 10px; padding-top: 4px; }
.modal-cancel { flex: 0; padding: 10px 16px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; font-family: inherit; }
.modal-submit { flex: 1; padding: 10px; border-radius: 10px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); font-size: 13px; font-weight: 800; color: #fff; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.96); }


/* ── ESTADOS ───────────────────────────────────────────────── */
.state-box {
  display: flex; flex-direction: column; align-items: center; padding: 80px 20px;
  text-align: center; color: #64748b;
  opacity: 0; transform: translateY(8px);
  transition: all 0.4s 0.15s cubic-bezier(0.22, 1, 0.36, 1);
}
.state-box.loaded { opacity: 1; transform: none; }
.state-ico { font-size: 48px; margin-bottom: 14px; }
.state-box h3 { font-size: 20px; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
.state-box p { font-size: 14px; margin: 0; max-width: 320px; }
.state-box.init-state { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #0d9488; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }

/* ── TURNOS BAR ─────────────────────────────────────────────── */
.turnos-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.turnos-bar.loaded { opacity: 1; transform: none; }
.turnos-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; white-space: nowrap; }
.turnos-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.turno-chip {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 12px; cursor: grab;
  border: 1px solid; border-color: color-mix(in srgb, var(--tc) 30%, transparent);
  background: var(--tl); user-select: none;
  transition: all 0.15s;
}
.turno-chip:active { cursor: grabbing; transform: scale(0.97); }
.turno-chip:hover { transform: translateY(-2px); box-shadow: 0 4px 16px color-mix(in srgb, var(--tc) 20%, transparent); }
.turno-sigla { font-size: 13px; font-weight: 900; color: var(--tc); min-width: 20px; text-align: center; }
.turno-nome { font-size: 12px; font-weight: 700; color: color-mix(in srgb, var(--tc) 80%, #000); }
.turno-hora { font-size: 10px; color: color-mix(in srgb, var(--tc) 60%, #888); }
.turno-apagar { --tc: #dc2626; --tl: #fef2f2; }

/* ── GRADE CARD ─────────────────────────────────────────────── */
.grade-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden;
  opacity: 0; transform: translateY(12px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.15s;
}
.grade-card.loaded { opacity: 1; transform: none; }
.grade-scroll { overflow-x: auto; }
.grade-scroll::-webkit-scrollbar { height: 6px; }
.grade-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
.grade-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

/* ── GRADE TABLE ─────────────────────────────────────────────── */
.grade-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.grade-table thead tr { background: #0f172a; color: #fff; }
.th-nome {
  padding: 12px 16px; font-size: 11px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8;
  background: #0f172a; min-width: 200px; text-align: left;
  position: sticky; left: 0; z-index: 2;
}
.th-dia {
  padding: 6px 4px; min-width: 38px; max-width: 38px; text-align: center;
  font-size: 10px; font-weight: 700; color: #94a3b8; border-right: 1px solid #1e293b;
}
.th-fds { background: rgba(255,255,255,0.03); }
.th-hoje { background: rgba(13,148,136,0.2); color: #2dd4bf !important; }
.th-feriado { background: rgba(239,68,68,0.1); color: #f87171 !important; }
.dia-num { font-size: 12px; font-weight: 900; color: #fff; }
.dia-dow { font-size: 9px; font-weight: 600; margin-top: 1px; }
.th-sum { padding: 6px 12px; font-size: 10px; font-weight: 700; color: #94a3b8; min-width: 52px; text-align: center; }

/* ── LINHA ─────────────────────────────────────────────────── */
.grade-row { border-bottom: 1px solid #f1f5f9; transition: background 0.12s; }
.grade-row:hover { background: #fafafa; }
.grade-row:last-child { border-bottom: none; }

.td-nome { padding: 10px 16px; background: #fff; }
.grade-row:hover .td-nome { background: #fafafa; }
.sticky-col { position: sticky; left: 0; z-index: 1; box-shadow: 4px 0 8px -4px rgba(0,0,0,0.08); }

.nome-wrap { display: flex; align-items: center; gap: 10px; }
.func-avatar {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  background: hsl(var(--h) 65% 55%);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 800; color: #fff;
}
.func-nome { font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; }
.func-cargo { font-size: 10px; color: #94a3b8; margin-top: 1px; }

/* ── CÉLULAS ─────────────────────────────────────────────────── */
.td-cell {
  padding: 3px; border-right: 1px solid #f1f5f9; height: 46px; min-width: 38px;
  transition: background 0.1s;
}
.td-cell.cell-fds { background: #f8fafc; }
.td-cell[data-active="true"] { background: rgba(13,148,136,0.08); }

.cell-turno {
  width: 100%; height: 100%; border-radius: 6px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  background: var(--tl);
  border: 1px solid color-mix(in srgb, var(--tc) 25%, transparent);
  font-size: 11px; font-weight: 900; color: var(--tc);
  transition: all 0.15s; user-select: none;
}
.cell-turno:hover { transform: scale(1.05); box-shadow: 0 2px 8px color-mix(in srgb, var(--tc) 25%, transparent); }

.td-sum { padding: 4px 12px; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; min-width: 52px; }
.sum-val { background: #f1f5f9; border-radius: 8px; padding: 4px 8px; }

/* ── FOOTER ──────────────────────────────────────────────────── */
.grade-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 8px; }
.grade-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.gstat { display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; color: #64748b; }
.gstat-sig { background: color-mix(in srgb, var(--tc) 10%, white); color: var(--tc); border: 1px solid color-mix(in srgb, var(--tc) 20%, transparent); border-radius: 6px; padding: 2px 6px; font-size: 11px; font-weight: 900; }
.grade-tip { font-size: 11px; color: #94a3b8; }

/* ── TOAST ──────────────────────────────────────────────────── */
.toast {
  position: fixed; bottom: 28px; right: 28px; z-index: 200;
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; border-radius: 14px;
  font-size: 14px; font-weight: 600; color: #fff;
  box-shadow: 0 16px 48px rgba(0,0,0,0.2);
}
.toast.success { background: #0d9488; }
.toast.error { background: #dc2626; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px) scale(0.95); }
</style>
