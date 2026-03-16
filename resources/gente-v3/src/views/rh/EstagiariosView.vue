<template>
  <div class="est-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🎓 Lei nº 11.788/2008</span>
          <h1 class="hero-title">Gestão de Estagiários</h1>
          <p class="hero-sub">Contratos, frequência e bolsas — CIEE/MA, IEL/MA e instituições parceiras</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card green"><span class="kpi-label">Ativos</span><span class="kpi-val">{{ stats.ativos }}</span></div>
          <div class="kpi-card orange"><span class="kpi-label">Vencendo (30d)</span><span class="kpi-val">{{ stats.vencendo }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Bolsas/mês</span><span class="kpi-val">{{ fmtShort(stats.totalBolsas) }}</span></div>
        </div>
      </div>
    </div>

    <!-- ALERTA VENCIMENTO -->
    <div v-if="stats.vencendo > 0" class="alerta-vencimento">
      ⚠️ <strong>{{ stats.vencendo }} contrato(s)</strong> vencem em menos de 30 dias. Verifique a fila de renovação.
    </div>

    <!-- TABS -->
    <div class="tabs-card" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
    </div>

    <!-- Tab: CONTRATOS -->
    <div v-if="tab === 'contratos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Contratos de Estágio</h2>
        <div class="toolbar">
          <select v-model="filtroStatus" class="filter-sel">
            <option value="">Todos</option>
            <option value="ATIVO">Ativos</option>
            <option value="CONCLUIDO">Concluídos</option>
            <option value="RESCINDIDO">Rescindidos</option>
          </select>
          <button class="btn-primary" @click="tab = 'novo'">+ Cadastrar Estagiário</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Estagiário</th><th>Instituição</th><th>Setor/Secretaria</th>
            <th>Período</th><th>Bolsa</th><th>Status</th><th>Ações</th>
          </tr></thead>
          <tbody>
            <tr v-if="!listaFiltrada.length"><td colspan="7" class="empty-td">📭 Nenhum contrato</td></tr>
            <tr v-for="c in listaFiltrada" :key="c.CONTRATO_ID" :class="{ 'row-warn': isVencendo(c) }">
              <td>
                <span class="nome">{{ c.nome }}</span>
                <span class="sub">{{ c.agente }} · {{ mascaraCpf(c.cpf) }}</span>
              </td>
              <td>{{ c.instituicao }}</td>
              <td>
                <span class="nome">{{ c.setor ?? c.secretaria ?? '—' }}</span>
                <span class="sub">{{ c.secretaria ?? '' }}</span>
              </td>
              <td class="mono">
                {{ fmtDate(c.DATA_INICIO) }}<br>→ {{ fmtDate(c.DATA_FIM) }}
                <span v-if="isVencendo(c)" class="badge orange" style="margin-top:3px;display:block">⚠️ Vence em breve</span>
              </td>
              <td class="money">{{ fmtMoney(c.BOLSA_VALOR) }}</td>
              <td><span class="badge" :class="statusCls(c.STATUS)">{{ c.STATUS }}</span></td>
              <td>
                <div class="row-actions">
                  <button v-if="c.STATUS === 'ATIVO'" class="act-btn blue" @click="abrirFrequencia(c)" title="Registrar Frequência">📅</button>
                  <button v-if="c.STATUS === 'ATIVO'" class="act-btn red" @click="encerrar(c.CONTRATO_ID)" title="Encerrar contrato">🔚</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: NOVO CADASTRO -->
    <div v-if="tab === 'novo'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📝 Cadastrar Estagiário</h2>
      <div class="form-grid">
        <div class="form-group"><label>Nome completo</label><input class="form-input" v-model="form.nome" /></div>
        <div class="form-group"><label>CPF</label><input class="form-input" v-model="form.cpf" placeholder="000.000.000-00" /></div>
        <div class="form-group"><label>Instituição de Ensino</label><input class="form-input" v-model="form.instituicao_ensino" /></div>
        <div class="form-group">
          <label>Agente de Integração</label>
          <select class="form-input" v-model="form.agente_integracao">
            <option value="CIEE">CIEE/MA</option>
            <option value="IEL">IEL/MA</option>
            <option value="NUBE">NUBE</option>
            <option value="DIRETO">Direto (sem agente)</option>
          </select>
        </div>
        <div class="form-group"><label>Curso</label><input class="form-input" v-model="form.curso" /></div>
        <div class="form-group"><label>Período Letivo</label><input class="form-input" v-model="form.periodo_letivo" placeholder="Ex: 6º semestre" /></div>
        <div class="form-group"><label>Data de Início</label><input class="form-input" type="date" v-model="form.data_inicio" /></div>
        <div class="form-group"><label>Data de Término</label><input class="form-input" type="date" v-model="form.data_fim" /></div>
        <div class="form-group"><label>Carga Horária Diária</label><select class="form-input" v-model="form.carga_hr_dia"><option value="4">4h</option><option value="5">5h</option><option value="6">6h (máximo)</option></select></div>
        <div class="form-group"><label>Bolsa Mensal (R$)</label><input class="form-input" type="number" step="0.01" v-model="form.bolsa_valor" /></div>
        <div class="form-group"><label>Auxílio-Transporte (R$)</label><input class="form-input" type="number" step="0.01" v-model="form.auxilio_transporte" /></div>
      </div>
      <div class="info-box">
        <h4>📋 Regras — Lei nº 11.788/2008</h4>
        <ul>
          <li>Duração máxima: <strong>2 anos</strong> no mesmo órgão</li>
          <li>Carga horária máxima: <strong>6h/dia, 30h/semana</strong> (ensino superior)</li>
          <li>Bolsa-auxílio obrigatória para estágio <strong>não obrigatório</strong></li>
          <li>Auxílio-transporte obrigatório junto com a bolsa</li>
          <li>Recesso remunerado: <strong>30 dias/ano</strong> (proporcional)</li>
        </ul>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" @click="tab = 'contratos'">Cancelar</button>
        <button class="btn-primary" :disabled="!formValido || enviando" @click="cadastrar">
          {{ enviando ? '⏳...' : '✅ Cadastrar' }}
        </button>
      </div>
    </div>

    <!-- Modal Frequência -->
    <transition name="modal">
      <div v-if="modalFreq" class="modal-overlay" @click.self="modalFreq = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>📅 Frequência Mensal — {{ freqItem?.nome }}</h3>
            <button class="modal-close" @click="modalFreq = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group"><label>Mês de Referência</label><input class="form-input" type="month" v-model="freq.mes_ref" /></div>
            <div class="form-group"><label>Dias Presentes (de 22 úteis)</label><input class="form-input" type="number" min="0" max="22" v-model="freq.dias_presentes" /></div>
            <div class="preview-box" v-if="freq.dias_presentes > 0">
              Bolsa calculada: <span class="pv-total">{{ fmtMoney(freqItem?.BOLSA_VALOR * (freq.dias_presentes / 22)) }}</span>
            </div>
            <div class="modal-actions">
              <button class="btn-secondary" @click="modalFreq = false">Cancelar</button>
              <button class="btn-primary" @click="salvarFrequencia">✅ Registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tab = ref('contratos')
const filtroStatus = ref('ATIVO')
const enviando = ref(false)
const modalFreq = ref(false)
const freqItem = ref(null)
const toast = ref({ visible: false, msg: '' })
const contratos = ref([])
const stats = ref({ ativos: 0, vencendo: 0, totalBolsas: 0 })
const freq = ref({ mes_ref: new Date().toISOString().slice(0, 7), dias_presentes: 22 })

const form = ref({ nome: '', cpf: '', instituicao_ensino: '', agente_integracao: 'CIEE', curso: '', periodo_letivo: '', data_inicio: '', data_fim: '', carga_hr_dia: 6, bolsa_valor: 0, auxilio_transporte: 0 })

const tabs = [
  { id: 'contratos', label: '📋 Contratos' },
  { id: 'novo',      label: '+ Cadastrar' },
]

const MOCK = [
  { CONTRATO_ID: 1, nome: 'Beatriz Almeida', cpf: '12345678901', instituicao: 'UFMA', agente: 'CIEE', setor: 'Recursos Humanos', secretaria: 'SEMURH', DATA_INICIO: '2025-08-01', DATA_FIM: '2026-07-31', CARGA_HR_DIA: 6, BOLSA_VALOR: 800, STATUS: 'ATIVO' },
  { CONTRATO_ID: 2, nome: 'Carlos Mendes', cpf: '98765432100', instituicao: 'UEMA', agente: 'IEL', setor: 'TI', secretaria: 'SEMAD', DATA_INICIO: '2025-10-01', DATA_FIM: '2026-03-20', CARGA_HR_DIA: 6, BOLSA_VALOR: 750, STATUS: 'ATIVO' },
  { CONTRATO_ID: 3, nome: 'Fernanda Lima', cpf: '11122233344', instituicao: 'IFMA', agente: 'CIEE', setor: 'Financeiro', secretaria: 'SEMAD', DATA_INICIO: '2024-03-01', DATA_FIM: '2026-02-28', CARGA_HR_DIA: 4, BOLSA_VALOR: 650, STATUS: 'CONCLUIDO' },
]

const listaFiltrada = computed(() =>
  filtroStatus.value ? contratos.value.filter(c => c.STATUS === filtroStatus.value) : contratos.value
)
const formValido = computed(() =>
  form.value.nome && form.value.cpf && form.value.instituicao_ensino && form.value.data_inicio && form.value.data_fim && form.value.bolsa_valor > 0
)

function isVencendo(c) {
  if (c.STATUS !== 'ATIVO') return false
  const fim = new Date(c.DATA_FIM)
  const diff = (fim - new Date()) / 86400000
  return diff >= 0 && diff <= 30
}

async function carregar() {
  try {
    const { data } = await api.get('/api/v3/estagiarios')
    contratos.value = data.contratos?.data ?? MOCK
    stats.value.vencendo = data.vencendo_30dias ?? 0
  } catch { contratos.value = MOCK }
  stats.value.ativos = contratos.value.filter(c => c.STATUS === 'ATIVO').length
  stats.value.totalBolsas = contratos.value.filter(c => c.STATUS === 'ATIVO').reduce((a, c) => a + parseFloat(c.BOLSA_VALOR || 0), 0)
}

async function cadastrar() {
  enviando.value = true
  try {
    await api.post('/api/v3/estagiarios', form.value)
    showToast('✅ Estagiário cadastrado com sucesso!')
    tab.value = 'contratos'
    await carregar()
  } catch { showToast('⚠️ Erro ao cadastrar estagiário.') }
  finally { enviando.value = false }
}

async function encerrar(id) {
  try {
    await api.patch(`/api/v3/estagiarios/${id}/status`, { status: 'RESCINDIDO' })
    showToast('✅ Contrato encerrado.')
    await carregar()
  } catch { showToast('⚠️ Erro ao encerrar contrato.') }
}

function abrirFrequencia(c) { freqItem.value = c; freq.value.dias_presentes = 22; modalFreq.value = true }

async function salvarFrequencia() {
  try {
    const { data } = await api.post(`/api/v3/estagiarios/${freqItem.value.CONTRATO_ID}/frequencia`, freq.value)
    showToast(`✅ Frequência registrada. Bolsa calculada: ${fmtMoney(data.bolsa_calculada)}`)
    modalFreq.value = false
  } catch { showToast('⚠️ Erro ao registrar frequência.') }
}

const mascaraCpf  = c => c ? String(c).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '—'
const fmtMoney    = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const fmtShort    = v => v >= 1e3 ? `R$ ${(v/1e3).toFixed(0)}K` : fmtMoney(v)
const fmtDate     = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'
const statusCls   = s => ({ ATIVO: 'ok', CONCLUIDO: 'gray', RESCINDIDO: 'red' })[s] ?? ''

function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.est-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#042f2e,#0c4a6e,#3730a3); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#34d399; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#818cf8; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#6ee7b7; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.orange { border-top:2px solid #fb923c; }
.kpi-card.blue   { border-top:2px solid #60a5fa; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.alerta-vencimento { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:.85rem 1.25rem; font-size:.85rem; color:#92400e; font-weight:600; }
.tabs-card { display:flex; gap:.5rem; opacity:0; transition:opacity .4s .1s; }
.tabs-card.loaded { opacity:1; }
.tab-btn { padding:.65rem 1.25rem; border-radius:10px; border:1px solid #e2e8f0; background:#fff; font-size:.85rem; font-weight:700; color:#64748b; cursor:pointer; transition:all .15s; }
.tab-btn.active { background:#059669; color:#fff; border-color:#059669; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.toolbar { display:flex; gap:.75rem; align-items:center; }
.filter-sel { border:1.5px solid #e2e8f0; border-radius:8px; padding:.55rem .9rem; font-size:.85rem; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#059669,#0d9488); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; vertical-align:top; }
.row-warn { background:#fffbeb; }
.nome { display:block; font-weight:700; color:#1e293b; }
.sub  { display:block; font-size:.7rem; color:#94a3b8; }
.mono { font-family:monospace; font-size:.78rem; color:#64748b; }
.money { font-family:monospace; font-weight:700; color:#1e293b; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok     { background:#dcfce7; color:#166534; }
.badge.gray   { background:#f1f5f9; color:#64748b; }
.badge.red    { background:#fee2e2; color:#991b1b; }
.badge.orange { background:#ffedd5; color:#9a3412; }
.row-actions { display:flex; gap:.4rem; }
.act-btn { padding:.3rem .55rem; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:.85rem; transition:all .15s; }
.act-btn.blue:hover { background:#dbeafe; border-color:#bfdbfe; }
.act-btn.red:hover  { background:#fee2e2; border-color:#fca5a5; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.info-box { background:#f0fdf4; border:1px solid #86efac; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.25rem; }
.info-box h4 { font-size:.88rem; font-weight:800; color:#166534; margin:0 0 .75rem; }
.info-box ul { margin:0; padding-left:1.25rem; }
.info-box li { font-size:.82rem; color:#334155; margin-bottom:.35rem; line-height:1.5; }
.form-actions { display:flex; gap:.75rem; justify-content:flex-end; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); z-index:100; display:flex; align-items:center; justify-content:center; padding:1rem; }
.modal-card { background:#fff; border-radius:20px; width:100%; max-width:440px; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.2); }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.modal-hdr h3 { font-size:1rem; font-weight:800; color:#1e293b; margin:0; }
.modal-close { border:none; background:#f1f5f9; border-radius:8px; width:28px; height:28px; cursor:pointer; color:#64748b; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:.9rem; }
.preview-box { display:flex; align-items:center; gap:.75rem; background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:.75rem 1rem; font-size:.88rem; color:#166534; }
.pv-total { font-size:1.2rem; font-weight:900; color:#166534; }
.modal-actions { display:flex; gap:.75rem; }
.modal-enter-active,.modal-leave-active { transition:opacity .3s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>
