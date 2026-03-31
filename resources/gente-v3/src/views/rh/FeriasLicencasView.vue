<template>
  <div class="ferias-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">??? Recursos Humanos</span>
          <h1 class="hero-title">Férias e Afastamentos</h1>
          <p class="hero-sub">Gerencie férias, licenças e afastamentos administrativos</p>
        </div>
        <div class="hero-saldo-wrap">
          <div class="saldo-ring">
            <svg viewBox="0 0 80 80" class="ring-svg">
              <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8"/>
              <circle cx="40" cy="40" r="32" fill="none" stroke="#34d399" stroke-width="8"
                stroke-dasharray="201"
                :stroke-dashoffset="201 - (201 * Math.min(saldo / 30, 1))"
                stroke-linecap="round" transform="rotate(-90 40 40)"/>
            </svg>
            <div class="ring-inner">
              <span class="ring-val">{{ saldo }}</span>
              <span class="ring-sub">dias</span>
            </div>
          </div>
          <div class="saldo-info">
            <span class="saldo-title">Férias Disponíveis</span>
            <div class="saldo-stats">
              <div><span class="ss-label">Adquiridos</span><span class="ss-val green">30d</span></div>
              <div><span class="ss-label">Gozados</span><span class="ss-val red">{{ 30 - saldo }}d</span></div>
              <div><span class="ss-label">Próx. venc.</span><span class="ss-val yellow">{{ vencimento }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TABS PRINCIPAIS -->
    <div class="tabs" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: modulo === 'ferias' }" @click="modulo = 'ferias'">
        ??? Férias
        <span v-if="solicitacoes.length" class="tab-count">{{ solicitacoes.length }}</span>
      </button>
      <button class="tab-btn" :class="{ active: modulo === 'afastamentos' }" @click="modulo = 'afastamentos'">
        ?? Afastamentos / Licenças
        <span v-if="afastamentos.length" class="tab-count afast">{{ afastamentos.length }}</span>
      </button>
    </div>

    <!-- ----------------------------------------------------------
         MÓDULO: FÉRIAS
    ---------------------------------------------------------- -->
    <template v-if="modulo === 'ferias'">
      <!-- Sub-abas de férias -->
      <div class="sub-tabs" :class="{ loaded }">
        <button v-for="t in tabsFerias" :key="t.id" class="stab-btn" :class="{ active: tabFerias === t.id }" @click="tabFerias = t.id">
          {{ t.ico }} {{ t.nome }}
          <span v-if="t.count" class="stab-count">{{ t.count }}</span>
        </button>
      </div>

      <!-- TAB: AGENDAR FÉRIAS -->
      <div v-if="tabFerias === 'solicitar'" class="tab-content" :class="{ loaded }">
        <div class="form-panel">
          <h2 class="panel-title">{{ editandoId ? 'Editar Agendamento' : 'Agendar Férias' }}</h2>
          <div class="form-two-col">
            <div class="form-group">
              <label>Data de Início <span class="req">*</span></label>
              <input v-model="form.FERIAS_DATA_INICIO" type="date" class="cfg-input" :min="hoje" />
            </div>
            <div class="form-group">
              <label>Data de Fim <span class="req">*</span></label>
              <input v-model="form.FERIAS_DATA_FIM" type="date" class="cfg-input" :min="form.FERIAS_DATA_INICIO || hoje" />
            </div>
          </div>

          <div v-if="duracaoDias > 0" class="duracao-preview">
            <strong>{{ duracaoDias }} dias</strong>
            <span class="dur-corridos">(corridos, incluindo fins de semana)</span>
            <span v-if="duracaoDias > saldo" class="dur-warn">?? Excede o saldo disponível</span>
          </div>

          <!-- BUG-EST-14: alerta de sobreposição de férias no setor -->
          <div v-if="sobreposicao.membros?.length" class="overlap-warn">
            <span class="overlap-ico">??</span>
            <div class="overlap-info">
              <strong>{{ sobreposicao.membros.length }} servidor(es) do seu setor também estarão de férias neste período</strong>
              <span v-if="sobreposicao.pct >= 30" class="overlap-pct pct-danger"> — {{ sobreposicao.pct }}% do setor ausente</span>
              <span v-else class="overlap-pct"> — {{ sobreposicao.pct }}% do setor</span>
              <ul class="overlap-list">
                <li v-for="m in sobreposicao.membros" :key="m.nome">
                  {{ m.nome }} ({{ formatDate(m.inicio) }} ? {{ formatDate(m.fim) }})
                </li>
              </ul>
            </div>
          </div>

          <details class="period-details">
            <summary class="period-summary">Período Aquisitivo (opcional)</summary>
            <div class="form-two-col" style="margin-top:12px">
              <div class="form-group">
                <label>Ano de Início</label>
                <input v-model="form.FERIAS_AQUISITIVO_INICIO" type="number" class="cfg-input" placeholder="2024" min="2000" max="2099" />
              </div>
              <div class="form-group">
                <label>Ano de Fim</label>
                <input v-model="form.FERIAS_AQUISITIVO_FIM" type="number" class="cfg-input" placeholder="2025" min="2000" max="2099" />
              </div>
            </div>
          </details>

          <!-- Upload de documentos (Férias) -->
          <div class="form-group">
            <label>Documento Anexo <span class="opt-label">(opcional)</span></label>
            <div class="upload-zone" :class="{ 'uz-has-file': arquivoFerias }" @click="$refs.inputFerias.click()" @dragover.prevent @drop.prevent="onDropFerias">
              <input ref="inputFerias" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none" @change="onArquivoFerias" />
              <template v-if="!arquivoFerias">
                <span class="uz-ico">??</span>
                <span class="uz-text">Clique ou arraste um arquivo <span class="uz-hint">(PDF, imagem ou Word)</span></span>
              </template>
              <template v-else>
                <span class="uz-ico">?</span>
                <span class="uz-text"><strong>{{ arquivoFerias.name }}</strong></span>
                <button class="uz-remove" @click.stop="arquivoFerias = null">?</button>
              </template>
            </div>
          </div>

          <div v-if="erroForm" class="form-erro">{{ erroForm }}</div>
          <div v-if="okForm"   class="form-ok">{{ okForm }}</div>

          <div class="form-actions">
            <button v-if="editandoId" class="cancel-link" @click="cancelarEdicao">Cancelar edição</button>
            <button class="submit-btn" :disabled="!formValido || enviando" @click="solicitar">
              <div v-if="enviando" class="btn-spinner"></div>
              <template v-else>? {{ editandoId ? 'Salvar Alterações' : 'Confirmar Agendamento' }}</template>
            </button>
          </div>
        </div>
      </div>

      <!-- TAB: HISTÓRICO FÉRIAS -->
      <div v-if="tabFerias === 'historico'" class="tab-content" :class="{ loaded }">
        <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
        <div v-else-if="solicitacoes.length === 0" class="state-box">
          <p>Nenhum período de férias registrado</p>
          <button class="submit-btn" style="margin-top:8px;padding:10px 20px;font-size:13px" @click="tabFerias='solicitar'">Agendar agora</button>
        </div>
        <div v-else class="timeline">
          <div v-for="(s, i) in solicitacoesOrdenadas" :key="s.FERIAS_ID ?? i" class="tl-item" :style="{ '--ti': i }">
            <div class="tl-dot" :class="dotClass(s)"></div>
            <div class="tl-card">
              <div class="tl-hdr">
                <div class="tl-tipo">??? Férias</div>
                <span class="tl-status" :class="dotClass(s)">{{ statusLabel(s) }}</span>
              </div>
              <div class="tl-periodo">
                {{ formatDate(s.FERIAS_DATA_INICIO ?? s.inicio) }} ? {{ formatDate(s.FERIAS_DATA_FIM ?? s.fim) }}
                &nbsp;·&nbsp;<strong>{{ diasPeriodo(s) }} dias</strong>
              </div>
              <div v-if="s.FERIAS_AQUISITIVO_INICIO" class="tl-aquisitivo">
                Período aquisitivo: {{ s.FERIAS_AQUISITIVO_INICIO }}/{{ s.FERIAS_AQUISITIVO_FIM }}
              </div>
              <div class="tl-actions">
                <button class="tl-btn tl-edit" @click="editarFerias(s)">?? Editar</button>
                <button class="tl-btn tl-cancel" @click="cancelarFerias(s)">? Cancelar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: CALENDÁRIO -->
      <div v-if="tabFerias === 'calendario'" class="tab-content" :class="{ loaded }">
        <div class="cal-legend">
          <span class="cal-leg-item"><span class="cal-leg-dot" style="background:#34d399"></span>Férias</span>
          <span class="cal-leg-item"><span class="cal-leg-dot" style="background:#f59e0b"></span>Hoje</span>
        </div>
        <div class="cal-grid">
          <div v-for="m in mesesCalendario" :key="m.mes" class="cal-month">
            <h3 class="cal-month-title">{{ m.nome }}</h3>
            <div class="cal-days">
              <span v-for="d in m.dias" :key="d.num" class="cal-day"
                :class="{ 'cd-ferias': d.eFerias, 'cd-hoje': d.isHoje, 'cd-fds': d.isFds }">
                {{ d.num }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: PERÍODOS AQUISITIVOS -->
      <div v-if="tabFerias === 'periodos'" class="tab-content" :class="{ loaded }">
        <div v-if="loadingSaldo" class="state-box"><div class="spinner"></div><p>Calculando períodos...</p></div>
        <template v-else-if="periodosAquis.length">
          <!-- Resumo -->
          <div class="pa-resumo">
            <div class="pa-res-item">
              <span class="pa-res-ico">??</span>
              <div>
                <span class="pa-res-label">Saldo Total Disponível</span>
                <span class="pa-res-val">{{ saldoTotal }} dias</span>
              </div>
            </div>
            <div class="pa-res-item">
              <span class="pa-res-ico">??</span>
              <div>
                <span class="pa-res-label">Períodos com Saldo</span>
                <span class="pa-res-val">{{ periodosAquis.filter(p => p.saldo_dias > 0).length }}</span>
              </div>
            </div>
            <div class="pa-res-item">
              <span class="pa-res-ico">??</span>
              <div>
                <span class="pa-res-label">Períodos Vencidos</span>
                <span class="pa-res-val" style="color:#ef4444">{{ periodosAquis.filter(p => p.vencido && p.saldo_dias > 0).length }}</span>
              </div>
            </div>
          </div>

          <!-- Cards por período -->
          <div class="pa-list">
            <div
              v-for="(p, i) in periodosAquis"
              :key="i"
              class="pa-card"
              :class="{ 'pa-vencido': p.vencido && p.saldo_dias > 0, 'pa-ok': !p.vencido && p.saldo_dias > 0, 'pa-zerado': p.saldo_dias === 0 }"
            >
              <div class="pa-card-hdr">
                <div>
                  <span class="pa-periodo">{{ p.periodo }}</span>
                  <span v-if="p.vencido && p.saldo_dias > 0" class="pa-badge pa-badge-venc">?? Vencido</span>
                  <span v-else-if="p.saldo_dias === 0" class="pa-badge pa-badge-ok">? Gozado</span>
                  <span v-else class="pa-badge pa-badge-disp">?? Disponível</span>
                </div>
                <span class="pa-saldo-num">{{ p.saldo_dias }}<small>dias</small></span>
              </div>
              <!-- Barra de progresso -->
              <div class="pa-bar-track">
                <div
                  class="pa-bar-fill"
                  :class="{ 'pa-bar-venc': p.vencido && p.saldo_dias > 0, 'pa-bar-ok': !(p.vencido && p.saldo_dias > 0) }"
                  :style="{ width: (p.usados_dias / p.direito_dias * 100) + '%' }"
                ></div>
              </div>
              <div class="pa-card-footer">
                <span>? {{ p.usados_dias }} dias usados</span>
                <span>?? {{ p.saldo_dias }} dias restantes de {{ p.direito_dias }}</span>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="state-box">
          <p>Nenhum período aquisitivo concluído ainda</p>
          <small style="color:#94a3b8">São necessários 12 meses de serviço para o primeiro período</small>
        </div>
      </div>
    </template>

    <!-- ----------------------------------------------------------
         MÓDULO: AFASTAMENTOS / LICENÇAS
    ---------------------------------------------------------- -->
    <template v-if="modulo === 'afastamentos'">
      <div class="tabs" :class="{ loaded }">
        <button v-for="t in tabsAfast" :key="t.id" class="stab-btn" :class="{ active: tabAfast === t.id }" @click="tabAfast = t.id">
          {{ t.ico }} {{ t.nome }}
        </button>
      </div>

      <!-- TAB: SOLICITAR AFASTAMENTO -->
      <div v-if="tabAfast === 'solicitar'" class="tab-content" :class="{ loaded }">
        <div class="form-panel">
          <h2 class="panel-title">Solicitar Afastamento / Licença</h2>

          <div class="form-group">
            <label>Tipo de Afastamento <span class="req">*</span></label>
            <select v-model="formAfast.tipo" class="cfg-input" @change="formAfast.tipo_nome = tiposAfastamento.find(t=>t.val===formAfast.tipo)?.nome">
              <option value="">Selecione...</option>
              <option v-for="t in tiposAfastamento" :key="t.val" :value="t.val">{{ t.ico }} {{ t.nome }}</option>
            </select>
          </div>

          <!-- Info do tipo selecionado -->
          <div v-if="tipoSelecionado" class="tipo-info-box" :style="{ borderColor: tipoSelecionado.cor }">
            <span class="tipo-ico">{{ tipoSelecionado.ico }}</span>
            <div>
              <strong class="tipo-nome">{{ tipoSelecionado.nome }}</strong>
              <p class="tipo-impacto">{{ tipoSelecionado.impacto }}</p>
              <span class="tipo-chip" :style="{ background: tipoSelecionado.cor + '18', color: tipoSelecionado.cor }">
                {{ tipoSelecionado.folha }}
              </span>
            </div>
          </div>

          <div class="form-two-col">
            <div class="form-group">
              <label>Data de Início <span class="req">*</span></label>
              <input v-model="formAfast.inicio" type="date" class="cfg-input" />
            </div>
            <div class="form-group">
              <label>Data de Fim (prevista)</label>
              <input v-model="formAfast.fim" type="date" class="cfg-input" :min="formAfast.inicio" />
            </div>
          </div>

          <div class="form-group">
            <label>Justificativa / Observações</label>
            <textarea v-model="formAfast.obs" class="cfg-input cfg-ta" rows="3" placeholder="Descreva os motivos ou informações adicionais..."></textarea>
          </div>

          <!-- Upload de documentos (Afastamento) -->
          <div class="form-group">
            <label>
              Documento Comprobatório
              <span v-if="tipoSelecionado" class="opt-label"> — {{ docExigido }}</span>
            </label>
            <div class="upload-zone" :class="{ 'uz-has-file': arquivoAfast, 'uz-afast': true }" @click="$refs.inputAfast.click()" @dragover.prevent @drop.prevent="onDropAfast">
              <input ref="inputAfast" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none" @change="onArquivoAfast" />
              <template v-if="!arquivoAfast">
                <span class="uz-ico">??</span>
                <span class="uz-text">Clique ou arraste um arquivo <span class="uz-hint">(PDF, imagem ou Word)</span></span>
              </template>
              <template v-else>
                <span class="uz-ico">?</span>
                <span class="uz-text"><strong>{{ arquivoAfast.name }}</strong></span>
                <button class="uz-remove" @click.stop="arquivoAfast = null">?</button>
              </template>
            </div>
          </div>

          <div v-if="erroAfast" class="form-erro">{{ erroAfast }}</div>
          <div v-if="okAfast"   class="form-ok">{{ okAfast }}</div>

          <div class="form-actions">
            <button class="submit-btn afast-btn" :disabled="!formAfastValido || enviandoAfast" @click="solicitarAfast">
              <div v-if="enviandoAfast" class="btn-spinner"></div>
              <template v-else>?? Enviar Solicitação</template>
            </button>
          </div>
        </div>
      </div>

      <!-- TAB: HISTÓRICO AFASTAMENTOS -->
      <div v-if="tabAfast === 'historico'" class="tab-content" :class="{ loaded }">
        <div v-if="loadingAfast" class="state-box"><div class="spinner spinner-afast"></div><p>Carregando...</p></div>
        <div v-else-if="afastamentos.length === 0" class="state-box">
          <p>Nenhum afastamento registrado</p>
          <button class="submit-btn afast-btn" style="margin-top:8px;padding:10px 20px;font-size:13px" @click="tabAfast='solicitar'">Solicitar agora</button>
        </div>
        <div v-else class="timeline">
          <div v-for="(a, i) in afastamentos" :key="a.id ?? i" class="tl-item" :style="{ '--ti': i }">
            <div class="tl-dot" :class="dotAfastClass(a)"></div>
            <div class="tl-card">
              <div class="tl-hdr">
                <div class="tl-tipo">{{ tipoAfastIco(a.tipo) }} {{ a.tipo_nome ?? a.tipo }}</div>
                <span class="tl-status" :class="dotAfastClass(a)">{{ a.status ?? 'Pendente' }}</span>
              </div>
              <div class="tl-periodo">
                {{ formatDate(a.inicio) }}
                <template v-if="a.fim"> ? {{ formatDate(a.fim) }} &nbsp;·&nbsp; <strong>{{ diasAfastamento(a) }} dias</strong></template>
              </div>
              <div class="tl-chip-folha" v-if="tipoAfastFolha(a.tipo)">
                <span class="mini-chip">{{ tipoAfastFolha(a.tipo) }}</span>
              </div>
              <div v-if="a.obs" class="tl-aquisitivo">{{ a.obs }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import api from '@/plugins/axios'

// -- Estado comum ----------------------------------------------
const loaded   = ref(false)
const loading  = ref(true)
const modulo   = ref('ferias')
const hoje     = new Date().toISOString().slice(0, 10)

// -- FÉRIAS ----------------------------------------------------
const tabFerias     = ref('solicitar')
const enviando      = ref(false)
const saldo         = ref(0)
const vencimento    = ref('—')
const solicitacoes  = ref([])
const editandoId    = ref(null)
const erroForm      = ref('')
const okForm        = ref('')
// Períodos aquisitivos reais
const periodosAquis = ref([])
const saldoTotal    = ref(0)
const loadingSaldo  = ref(false)
const arquivoFerias  = ref(null)
const onArquivoFerias = (e) => { arquivoFerias.value = e.target.files[0] ?? null }
const onDropFerias    = (e) => { arquivoFerias.value = e.dataTransfer.files[0] ?? null }
// BUG-EST-14: sobreposição de férias
const sobreposicao = ref({ membros: [], pct: 0 })
let sobreposicaoTimer = null


const form = reactive({
  FERIAS_DATA_INICIO: '', FERIAS_DATA_FIM: '',
  FERIAS_AQUISITIVO_INICIO: '', FERIAS_AQUISITIVO_FIM: '',
})

// BUG-EST-14: verificar sobreposição ao mudar datas
watch(() => [form.FERIAS_DATA_INICIO, form.FERIAS_DATA_FIM], ([ini, fim]) => {
  clearTimeout(sobreposicaoTimer)
  if (!ini || !fim) { sobreposicao.value = { membros: [], pct: 0 }; return }
  sobreposicaoTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/v3/ferias/sobreposicao', { params: { inicio: ini, fim } })
      sobreposicao.value = data
    } catch { sobreposicao.value = { membros: [], pct: 0 } }
  }, 500)
})

const tabsFerias = computed(() => [
  { id: 'solicitar',  ico: '??', nome: editandoId.value ? 'Editar' : 'Agendar' },
  { id: 'historico',  ico: '??', nome: 'Histórico', count: solicitacoes.value.length || null },
  { id: 'calendario', ico: '??',  nome: 'Calendário' },
  { id: 'periodos',   ico: '??', nome: 'Períodos Aquisitivos' },
])

const duracaoDias = computed(() => {
  if (!form.FERIAS_DATA_INICIO || !form.FERIAS_DATA_FIM) return 0
  const d1 = new Date(form.FERIAS_DATA_INICIO), d2 = new Date(form.FERIAS_DATA_FIM)
  if (d2 <= d1) return 0
  return Math.round((d2 - d1) / (1000 * 60 * 60 * 24))
})

const formValido = computed(() => form.FERIAS_DATA_INICIO && form.FERIAS_DATA_FIM && duracaoDias.value > 0)
const solicitacoesOrdenadas = computed(() => [...solicitacoes.value].sort((a, b) => {
  const da = a.FERIAS_DATA_INICIO ?? a.inicio ?? '', db = b.FERIAS_DATA_INICIO ?? b.inicio ?? ''
  return da < db ? 1 : -1
}))

onMounted(async () => {
  await Promise.all([fetchFerias(), fetchAfastamentos(), fetchSaldoFerias()])
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchFerias = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/ferias')
    solicitacoes.value = data.ferias ?? data.solicitacoes ?? []
    saldo.value        = data.saldo  ?? 0
    vencimento.value   = data.vencimento ?? '—'
  } catch { solicitacoes.value = [] }
  finally { loading.value = false }
}

const fetchSaldoFerias = async () => {
  loadingSaldo.value = true
  try {
    // Tenta identificar o funcionario_id do usuário logado
    const user = JSON.parse(localStorage.getItem('gente_user') || '{}')
    const funcId = user?.FUNCIONARIO_ID ?? user?.funcionario_id
    if (!funcId) return
    const { data } = await api.get(`/api/v3/ferias/saldo/${funcId}`)
    periodosAquis.value = data.periodos_aquisitivos ?? []
    saldoTotal.value    = data.total_saldo_dias ?? 0
    // Atualiza o anel no hero com o saldo real
    saldo.value = saldoTotal.value
  } catch { periodosAquis.value = [] }
  finally { loadingSaldo.value = false }
}

const solicitar = async () => {
  if (!formValido.value) return
  enviando.value = true; erroForm.value = ''; okForm.value = ''
  const payload = {
    FERIAS_DATA_INICIO:       form.FERIAS_DATA_INICIO,
    FERIAS_DATA_FIM:          form.FERIAS_DATA_FIM,
    FERIAS_AQUISITIVO_INICIO: form.FERIAS_AQUISITIVO_INICIO || null,
    FERIAS_AQUISITIVO_FIM:    form.FERIAS_AQUISITIVO_FIM    || null,
  }
  try {
    if (editandoId.value) {
      await api.put(`/api/v3/ferias/${editandoId.value}`, payload)
      okForm.value = 'Férias atualizadas!'
      const idx = solicitacoes.value.findIndex(s => (s.FERIAS_ID ?? s.id) === editandoId.value)
      if (idx >= 0) solicitacoes.value[idx] = { ...solicitacoes.value[idx], ...payload }
    } else {
      const { data } = await api.post('/api/v3/ferias', payload)
      okForm.value = 'Férias agendadas com sucesso!'
      solicitacoes.value.unshift({ FERIAS_ID: data.ferias_id, ...payload })
      // Upload do anexo se houver
      if (arquivoFerias.value && data.ferias_id) {
        const fd = new FormData()
        fd.append('arquivo', arquivoFerias.value)
        fd.append('tipo', 'ferias')
        await api.post(`/api/v3/ferias/${data.ferias_id}/anexo`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }).catch(() => {})
        arquivoFerias.value = null
      }
    }
    resetForm()
    setTimeout(() => { tabFerias.value = 'historico'; okForm.value = '' }, 1200)
  } catch (e) { erroForm.value = e.response?.data?.erro || 'Erro ao registrar.' }
  finally { enviando.value = false }
}

const editarFerias = (s) => {
  editandoId.value = s.FERIAS_ID ?? s.id
  form.FERIAS_DATA_INICIO       = s.FERIAS_DATA_INICIO ?? s.inicio ?? ''
  form.FERIAS_DATA_FIM          = s.FERIAS_DATA_FIM    ?? s.fim    ?? ''
  form.FERIAS_AQUISITIVO_INICIO = s.FERIAS_AQUISITIVO_INICIO ?? ''
  form.FERIAS_AQUISITIVO_FIM    = s.FERIAS_AQUISITIVO_FIM    ?? ''
  erroForm.value = ''; okForm.value = ''; tabFerias.value = 'solicitar'
}
const cancelarEdicao = () => resetForm()
const resetForm = () => {
  editandoId.value = null
  Object.assign(form, { FERIAS_DATA_INICIO: '', FERIAS_DATA_FIM: '', FERIAS_AQUISITIVO_INICIO: '', FERIAS_AQUISITIVO_FIM: '' })
}
const cancelarFerias = async (s) => {
  const id = s.FERIAS_ID ?? s.id
  if (!confirm('Cancelar este período de férias?')) return
  try {
    if (id && !String(id).startsWith('mock')) await api.delete(`/api/v3/ferias/${id}`)
    solicitacoes.value = solicitacoes.value.filter(f => (f.FERIAS_ID ?? f.id) !== id)
  } catch (e) { alert(e.response?.data?.erro || 'Erro ao cancelar.') }
}

// -- AFASTAMENTOS / LICENÇAS -----------------------------------
const tabAfast       = ref('solicitar')
const enviandoAfast  = ref(false)
const loadingAfast   = ref(true)
const afastamentos   = ref([])
const erroAfast      = ref('')
const okAfast        = ref('')

const formAfast = reactive({ tipo: '', tipo_nome: '', inicio: '', fim: '', obs: '' })

const tiposAfastamento = [
  {
    val: 'licenca_premio',
    ico: '??', nome: 'Licença Prêmio',
    cor: '#f59e0b',
    folha: 'Sem impacto na remuneração',
    impacto: 'Direito adquirido após 5 anos de efetivo exercício. Pode ser convertida em pecúnia conforme legislação municipal.',
  },
  {
    val: 'fins_particulares',
    ico: '??', nome: 'Afastamento para Fins Particulares',
    cor: '#6366f1',
    folha: 'Desconto proporcional ou sem remuneração',
    impacto: 'Afastamento sem vínculo de saúde ou interesse público. Impacta na folha com desconto integral dos dias afastados.',
  },
  {
    val: 'licenca_maternidade',
    ico: '??', nome: 'Licença Maternidade',
    cor: '#ec4899',
    folha: 'Remuneração mantida (reembolso previdenciário)',
    impacto: 'Duração de 120 a 180 dias conforme política municipal. INSS/RPPS reembolsa a entidade.',
  },
  {
    val: 'licenca_paternidade',
    ico: '?????', nome: 'Licença Paternidade',
    cor: '#3b82f6',
    folha: 'Remuneração mantida (sem desconto)',
    impacto: 'Mínimo de 5 dias, podendo ser estendida por programas como Empresa Cidadã. Sem impacto negativo na folha.',
  },
  {
    val: 'licenca_capacitacao',
    ico: '??', nome: 'Licença p/ Capacitação / Estudo',
    cor: '#10b981',
    folha: 'Variável (conforme convenção ou lei)',
    impacto: 'Afastamento para cursos de pós-graduação ou aprimoramento profissional de interesse da administração.',
  },
  {
    val: 'licenca_judicial',
    ico: '??', nome: 'Afastamento por Decisão Judicial',
    cor: '#94a3b8',
    folha: 'Conforme determinação judicial',
    impacto: 'Gerado por mandado judicial. O tratamento na folha depende da natureza da decisão.',
  },
]

const tabsAfast = [
  { id: 'solicitar', ico: '??', nome: 'Solicitar' },
  { id: 'historico', ico: '??', nome: 'Histórico' },
]

const tipoSelecionado = computed(() => tiposAfastamento.find(t => t.val === formAfast.tipo) ?? null)
const formAfastValido = computed(() => formAfast.tipo && formAfast.inicio)

const arquivoAfast = ref(null)
const inputAfast   = ref(null)
const onArquivoAfast = (e) => { arquivoAfast.value = e.target.files[0] ?? null }
const onDropAfast    = (e) => { arquivoAfast.value = e.dataTransfer.files[0] ?? null }

const docExigidoMap = {
  licenca_premio:       'Requerimento assinado + comprovante de tempo de serviço',
  fins_particulares:    'Requerimento com justificativa (formulário RH)',
  licenca_maternidade:  'Certidão de nascimento ou declaração hospitalar',
  licenca_paternidade:  'Certidão de nascimento',
  licenca_capacitacao:  'Comprovante de matrícula / aceite do curso',
  licenca_judicial:     'Cópia do mandado judicial',
}
const docExigido = computed(() => docExigidoMap[formAfast.tipo] ?? 'Documento comprobatório (opcional)')

const fetchAfastamentos = async () => {
  loadingAfast.value = true
  try {
    const { data } = await api.get('/api/v3/afastamentos')
    // Filtra apenas os tipos administrativos (exclui atestados médicos)
    const tiposAdmin = tiposAfastamento.map(t => t.val)
    afastamentos.value = (data.afastamentos ?? []).filter(a => {
      const tipo = (a.AFASTAMENTO_TIPO ?? a.tipo ?? '').toLowerCase().replace(/\s+/g, '_')
      return tiposAdmin.some(t => tipo.includes(t) || t.includes(tipo))
    })
  } catch { afastamentos.value = [] }
  finally { loadingAfast.value = false }
}

const solicitarAfast = async () => {
  if (!formAfastValido.value) return
  enviandoAfast.value = true; erroAfast.value = ''; okAfast.value = ''
  try {
    const { data } = await api.post('/api/v3/afastamentos', {
      tipo:  formAfast.tipo,
      inicio: formAfast.inicio,
      fim:    formAfast.fim || null,
      obs:    formAfast.obs || null,
    })
    okAfast.value = `? Solicitação registrada! Protocolo: ${data.protocolo ?? data.id ?? '—'}`
    afastamentos.value.unshift({
      id: data.id, tipo: formAfast.tipo, tipo_nome: tipoSelecionado.value?.nome,
      inicio: formAfast.inicio, fim: formAfast.fim, obs: formAfast.obs, status: 'Pendente',
    })
    // Upload do documento comprobatório se houver
    if (arquivoAfast.value && data.id) {
      const fd = new FormData()
      fd.append('arquivo', arquivoAfast.value)
      fd.append('tipo', 'afastamento')
      await api.post(`/api/v3/afastamentos/${data.id}/anexo`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }).catch(() => {})
      arquivoAfast.value = null
    }
    Object.assign(formAfast, { tipo: '', tipo_nome: '', inicio: '', fim: '', obs: '' })
    setTimeout(() => { tabAfast.value = 'historico'; okAfast.value = '' }, 1500)
  } catch (e) { erroAfast.value = e.response?.data?.erro || 'Erro ao registrar a solicitação.' }
  finally { enviandoAfast.value = false }
}

// -- Calendário Férias -----------------------------------------
const mesesCalendario = computed(() => {
  const meses = [], agora = new Date(), hojeStr = hoje
  for (let m = 0; m < 6; m++) {
    const d = new Date(agora.getFullYear(), agora.getMonth() + m, 1)
    const total = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()
    const nome  = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
    const dias  = Array.from({ length: total }, (_, i) => {
      const dt    = new Date(d.getFullYear(), d.getMonth(), i + 1)
      const dtStr = dt.toISOString().slice(0, 10)
      const dow   = dt.getDay()
      const eFerias = solicitacoes.value.some(p => {
        const ini = p.FERIAS_DATA_INICIO ?? p.inicio ?? ''
        const fim = p.FERIAS_DATA_FIM    ?? p.fim    ?? ''
        return dtStr >= ini && dtStr <= fim
      })
      return { num: i + 1, isFds: dow === 0 || dow === 6, isHoje: dtStr === hojeStr, eFerias }
    })
    meses.push({ mes: m, nome, dias })
  }
  return meses
})

// -- Helpers ---------------------------------------------------
const formatDate = (d) => {
  if (!d) return '—'
  try { return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) }
  catch { return d }
}
const diasPeriodo = (s) => {
  const ini = s.FERIAS_DATA_INICIO ?? s.inicio, fim = s.FERIAS_DATA_FIM ?? s.fim
  if (!ini || !fim) return '?'
  return Math.max(0, Math.round((new Date(fim) - new Date(ini)) / (1000 * 60 * 60 * 24)))
}
const diasAfastamento = (a) => {
  if (!a.inicio || !a.fim) return '?'
  return Math.max(0, Math.round((new Date(a.fim) - new Date(a.inicio)) / (1000 * 60 * 60 * 24)))
}
const statusLabel = (s) => {
  const ini = s.FERIAS_DATA_INICIO ?? s.inicio ?? '', h = hoje
  if (!ini) return 'Pendente'
  if (ini > h) return 'Agendada'
  const fim = s.FERIAS_DATA_FIM ?? s.fim ?? ''
  if (fim >= h) return 'Em gozo'
  return 'Encerrada'
}
const dotClass = (s) => {
  const label = statusLabel(s)
  if (label === 'Em gozo') return 'st-green'
  if (label === 'Agendada') return 'st-blue'
  if (label === 'Encerrada') return 'st-gray'
  return 'st-yellow'
}
const dotAfastClass = (a) => {
  const st = (a.status ?? '').toLowerCase()
  if (st === 'aprovado' || st === 'em andamento') return 'st-green'
  if (st === 'pendente') return 'st-yellow'
  if (st === 'rejeitado') return 'st-red'
  return 'st-gray'
}
const tipoAfastIco  = (val) => tiposAfastamento.find(t => t.val === val)?.ico ?? '??'
const tipoAfastFolha = (val) => tiposAfastamento.find(t => t.val === val)?.folha ?? null
</script>

<style scoped>
.ferias-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0d2a1e 55%, #1a2744 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 240px; height: 240px; background: #10b981; right: -60px; top: -80px; }
.hs2 { width: 200px; height: 200px; background: #3b82f6; right: 300px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-saldo-wrap { display: flex; align-items: center; gap: 20px; }
.saldo-ring { position: relative; width: 80px; height: 80px; flex-shrink: 0; }
.ring-svg { width: 80px; height: 80px; }
.ring-svg circle { transition: stroke-dashoffset 1s ease; }
.ring-inner { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-val { font-size: 22px; font-weight: 900; color: #fff; line-height: 1; }
.ring-sub { font-size: 10px; color: #94a3b8; font-weight: 600; }
.saldo-title { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; }
.saldo-stats { display: flex; gap: 16px; }
.saldo-stats div { text-align: center; }
.ss-label { display: block; font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; }
.ss-val { display: block; font-size: 16px; font-weight: 900; margin-top: 2px; }
.ss-val.green { color: #34d399; } .ss-val.red { color: #f87171; } .ss-val.yellow { color: #fbbf24; font-size: 13px; }

/* TABS */
.tabs { display: flex; gap: 8px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; max-width: 680px; margin: 0 auto; width: 100%; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { display: flex; align-items: center; gap: 7px; padding: 11px 20px; border-radius: 14px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 14px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.tab-btn.active { background: #f0fdf4; border-color: #34d399; color: #065f46; }
.tab-count { background: #dcfce7; color: #166534; border-radius: 99px; padding: 1px 7px; font-size: 11px; font-weight: 800; }
.tab-count.afast { background: #ede9fe; color: #5b21b6; }

/* SUB-TABS */
.sub-tabs { display: flex; gap: 6px; flex-wrap: wrap; max-width: 680px; margin: 0 auto; width: 100%; }
.stab-btn { display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.stab-btn.active { background: #f0fdf4; border-color: #34d399; color: #065f46; }
.stab-count { background: #dcfce7; color: #166534; border-radius: 99px; padding: 1px 6px; font-size: 11px; font-weight: 800; }

.tab-content { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; width: 100%; }
.tab-content.loaded { opacity: 1; transform: none; }

/* FORM */
.form-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; display: flex; flex-direction: column; gap: 16px; max-width: 680px; width: 100%; margin: 0 auto; box-sizing: border-box; }
.panel-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.form-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #10b981; }
.cfg-ta { resize: vertical; min-height: 80px; }
.req { color: #dc2626; }

/* TIPO INFO BOX */
.tipo-info-box { display: flex; align-items: flex-start; gap: 14px; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; background: #fafafa; transition: border-color 0.2s; }
.tipo-ico { font-size: 28px; flex-shrink: 0; }
.tipo-nome { display: block; font-size: 14px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
.tipo-impacto { font-size: 12px; color: #64748b; margin: 0 0 8px; line-height: 1.5; }
.tipo-chip { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 8px; display: inline-block; }

.duracao-preview { display: flex; align-items: center; gap: 10px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px; padding: 10px 14px; font-size: 14px; color: #065f46; }
.dur-corridos { font-size: 12px; color: #94a3b8; }
.dur-warn { color: #b45309; font-size: 13px; font-weight: 600; margin-left: auto; }
.period-details { border: 1px dashed #e2e8f0; border-radius: 12px; padding: 12px; }
.period-summary { font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; text-transform: uppercase; letter-spacing: 0.06em; }
.form-erro { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #dc2626; }
.form-ok   { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #15803d; }
.form-actions { display: flex; align-items: center; gap: 12px; }
.cancel-link { background: none; border: none; color: #94a3b8; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: underline; font-family: inherit; }
.submit-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 13px; border-radius: 13px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.submit-btn.afast-btn { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(16,185,129,0.3); }
.submit-btn.afast-btn:hover:not(:disabled) { box-shadow: 0 8px 24px rgba(99,102,241,0.3); }
.submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* HISTÓRICO / TIMELINE */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; color: #94a3b8; gap: 12px; }
.state-box p { font-size: 14px; margin: 0; }
.spinner { width: 38px; height: 38px; border: 3px solid #e2e8f0; border-top-color: #10b981; border-radius: 50%; animation: spin 0.8s linear infinite; }
.spinner.spinner-afast { border-top-color: #6366f1; }
.timeline { display: flex; flex-direction: column; gap: 0; }
.tl-item { display: flex; gap: 16px; padding-bottom: 16px; animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) calc(var(--ti) * 50ms) both; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
.tl-dot { width: 14px; height: 14px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; border: 3px solid #fff; box-shadow: 0 0 0 2px currentColor; }
.st-green  { color: #10b981; } .st-green.tl-dot  { background: #dcfce7; }
.st-blue   { color: #3b82f6; } .st-blue.tl-dot   { background: #dbeafe; }
.st-yellow { color: #f59e0b; } .st-yellow.tl-dot { background: #fef3c7; }
.st-gray   { color: #94a3b8; } .st-gray.tl-dot   { background: #f1f5f9; }
.st-red    { color: #ef4444; } .st-red.tl-dot    { background: #fee2e2; }
.tl-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; flex: 1; }
.tl-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; gap: 10px; }
.tl-tipo { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 800; color: #1e293b; }
.tl-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
.st-green.tl-status  { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.st-blue.tl-status   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.st-yellow.tl-status { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.st-gray.tl-status   { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
.st-red.tl-status    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.tl-periodo { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; margin-bottom: 6px; flex-wrap: wrap; }
.tl-aquisitivo { font-size: 11px; color: #94a3b8; margin: 4px 0; }
.tl-chip-folha { margin: 4px 0; }
.mini-chip { font-size: 11px; font-weight: 600; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2px 10px; color: #475569; }
.tl-actions { display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f8fafc; }
.tl-btn { display: flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.15s; color: #64748b; }
.tl-edit:hover  { background: #f5f3ff; border-color: #ddd6fe; color: #7c3aed; }
.tl-cancel:hover{ background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

/* UPLOAD ZONE */
.opt-label { font-size: 10px; font-weight: 500; color: #94a3b8; text-transform: none; letter-spacing: 0; }
.upload-zone { display: flex; align-items: center; gap: 12px; border: 1.5px dashed #cbd5e1; border-radius: 14px; padding: 14px 18px; cursor: pointer; background: #f8fafc; transition: all 0.18s; user-select: none; min-height: 54px; }
.upload-zone:hover { border-color: #10b981; background: #f0fdf4; }
.upload-zone.uz-afast:hover { border-color: #6366f1; background: #f5f3ff; }
.upload-zone.uz-has-file { border-style: solid; border-color: #34d399; background: #f0fdf4; }
.upload-zone.uz-afast.uz-has-file { border-color: #818cf8; background: #f5f3ff; }
.uz-ico { font-size: 20px; flex-shrink: 0; }
.uz-text { flex: 1; font-size: 13px; color: #475569; font-weight: 500; }
.uz-hint { font-size: 11px; color: #94a3b8; font-weight: 400; }
.uz-remove { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 3px 8px; font-size: 11px; color: #dc2626; cursor: pointer; font-weight: 700; flex-shrink: 0; }
.uz-remove:hover { background: #fee2e2; }

/* CALENDÁRIO */
.cal-legend { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
.cal-leg-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #475569; }
.cal-leg-dot { width: 10px; height: 10px; border-radius: 3px; }
.cal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.cal-month { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px; }
.cal-month-title { font-size: 13px; font-weight: 800; color: #1e293b; margin: 0 0 12px; text-transform: capitalize; }
.cal-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.cal-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 7px; font-size: 11px; font-weight: 600; color: #475569; }
.cal-day.cd-fds    { color: #cbd5e1; }
.cal-day.cd-ferias { background: #dcfce7; color: #166534; font-weight: 800; }
.cal-day.cd-hoje   { background: #f59e0b; color: #fff; font-weight: 900; border-radius: 9px; }

@media (max-width: 700px) { .form-two-col { grid-template-columns: 1fr; } }

/* PERÍODOS AQUISITIVOS */
.pa-resumo { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.pa-res-item { flex: 1; min-width: 150px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; display: flex; align-items: center; gap: 12px; }
.pa-res-ico { font-size: 24px; }
.pa-res-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; margin-bottom: 4px; }
.pa-res-val { display: block; font-size: 22px; font-weight: 900; color: #1e293b; }
.pa-list { display: flex; flex-direction: column; gap: 12px; }
.pa-card { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 10px; transition: box-shadow 0.18s; }
.pa-card:hover { box-shadow: 0 4px 20px -4px rgba(0,0,0,0.1); }
.pa-ok   { border-color: #86efac; }
.pa-vencido { border-color: #fbbf24; background: #fffbeb; }
.pa-zerado  { border-color: #e2e8f0; opacity: 0.7; }
.pa-card-hdr { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.pa-periodo { display: block; font-size: 14px; font-weight: 800; color: #1e293b; margin-bottom: 5px; }
.pa-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.pa-badge-disp { background: #dcfce7; color: #166534; }
.pa-badge-venc { background: #fef3c7; color: #92400e; }
.pa-badge-ok   { background: #f0fdf4; color: #166534; }
.pa-saldo-num { font-size: 28px; font-weight: 900; color: #1e293b; white-space: nowrap; text-align: right; }
.pa-saldo-num small { font-size: 12px; margin-left: 3px; color: #94a3b8; font-weight: 600; }
.pa-bar-track { height: 8px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.pa-bar-fill { height: 100%; border-radius: 99px; transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
.pa-bar-ok   { background: linear-gradient(90deg, #34d399, #10b981); }
.pa-bar-venc { background: linear-gradient(90deg, #f59e0b, #f97316); }
.pa-card-footer { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; font-weight: 600; }

/* BUG-EST-14: overlap de ferias */
.overlap-warn { display: flex; align-items: flex-start; gap: 12px; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 14px; padding: 14px 16px; margin-top: 4px; }
.overlap-ico  { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
.overlap-info { font-size: 13px; color: #78350f; line-height: 1.5; }
.overlap-pct  { font-size: 12px; font-weight: 600; color: #92400e; }
.pct-danger   { color: #b91c1c; }
.overlap-list { margin: 6px 0 0 16px; padding: 0; font-size: 12px; color: #92400e; }
.overlap-list li { margin-bottom: 2px; }
</style>
