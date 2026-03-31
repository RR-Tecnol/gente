<template>
  <div class="terc-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏗️ Gestão de Terceiros</span>
          <h1 class="hero-title">Terceirizados</h1>
          <p class="hero-sub">Fiscalização de contratos, postos de trabalho e checklists mensais</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card blue"><span class="kpi-label">Empresas Ativas</span><span class="kpi-val">{{ stats.empresas }}</span></div>
          <div class="kpi-card green"><span class="kpi-label">Postos Ativos</span><span class="kpi-val">{{ stats.postos }}</span></div>
          <div class="kpi-card orange"><span class="kpi-label">Check Pendentes</span><span class="kpi-val">{{ stats.pendentes }}</span></div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-card" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
    </div>

    <!-- Tab: EMPRESAS -->
    <div v-if="tab === 'empresas'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">🏢 Empresas Terceirizadas</h2>
        <button class="btn-primary" @click="tab = 'nova'">+ Cadastrar Empresa</button>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Empresa</th><th>CNPJ</th><th>Contrato</th>
            <th>Postos</th><th>Vigência</th><th>Status</th>
          </tr></thead>
          <tbody>
            <tr v-if="!empresas.length"><td colspan="6" class="empty-td">📭 Nenhuma empresa cadastrada</td></tr>
            <tr v-for="e in empresas" :key="e.EMPRESA_ID">
              <td>
                <span class="nome">{{ e.RAZAO_SOCIAL }}</span>
                <span class="sub">{{ e.NOME_FANTASIA ?? '' }}</span>
              </td>
              <td class="mono">{{ mascaraCnpj(e.CNPJ) }}</td>
              <td class="mono">{{ e.CONTRATO_NUMERO }}</td>
              <td class="center bold">{{ e.postos ?? 0 }}</td>
              <td class="mono">{{ fmtDate(e.VIGENCIA_INICIO) }} → {{ fmtDate(e.VIGENCIA_FIM) }}</td>
              <td><span class="badge" :class="e.STATUS === 'ATIVO' ? 'ok' : 'gray'">{{ e.STATUS }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: POSTOS -->
    <div v-if="tab === 'postos'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📍 Postos de Trabalho</h2>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Nome</th><th>Empresa</th><th>Secretaria</th>
            <th>Qtde</th><th>Função</th><th>Checklist</th>
          </tr></thead>
          <tbody>
            <tr v-if="!postos.length"><td colspan="6" class="empty-td">📭 Nenhum posto cadastrado</td></tr>
            <tr v-for="p in postos" :key="p.POSTO_ID">
              <td class="nome">{{ p.DESCRICAO }}</td>
              <td>{{ p.empresa }}</td>
              <td>{{ p.secretaria }}</td>
              <td class="center bold">{{ p.QUANTIDADE }}</td>
              <td>{{ p.FUNCAO }}</td>
              <td>
                <button class="act-btn blue" @click="abrirCheck(p)" title="Checklist mensal">📋</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: NOVA EMPRESA -->
    <div v-if="tab === 'nova'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">🏢 Cadastrar Empresa Terceirizada</h2>
      <div class="form-grid">
        <div class="form-group"><label>Razão Social</label><input class="form-input" v-model="form.razao_social" /></div>
        <div class="form-group"><label>Nome Fantasia</label><input class="form-input" v-model="form.nome_fantasia" /></div>
        <div class="form-group"><label>CNPJ</label><input class="form-input" v-model="form.cnpj" placeholder="00.000.000/0001-00" /></div>
        <div class="form-group"><label>Contrato nº</label><input class="form-input" v-model="form.contrato_numero" /></div>
        <div class="form-group"><label>Início da Vigência</label><input class="form-input" type="date" v-model="form.vigencia_inicio" /></div>
        <div class="form-group"><label>Fim da Vigência</label><input class="form-input" type="date" v-model="form.vigencia_fim" /></div>
        <div class="form-group"><label>Valor do Contrato (R$)</label><input class="form-input" type="number" step="0.01" v-model="form.valor_contrato" /></div>
        <div class="form-group"><label>Objeto do Contrato</label><textarea class="form-input ta" v-model="form.objeto" /></div>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" @click="tab = 'empresas'">Cancelar</button>
        <button class="btn-primary" :disabled="enviando" @click="salvar">
          {{ enviando ? '⏳...' : '✅ Cadastrar' }}
        </button>
      </div>
    </div>

    <!-- Modal Checklist -->
    <transition name="modal">
      <div v-if="modalCheck" class="modal-overlay" @click.self="modalCheck = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>📋 Checklist Mensal — {{ checkItem?.DESCRICAO }}</h3>
            <button class="modal-close" @click="modalCheck = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group"><label>Mês de Referência</label><input class="form-input" type="month" v-model="check.mes_ref" /></div>
            <div class="checklist-items">
              <label v-for="item in checkItens" :key="item.key" class="check-item">
                <input type="checkbox" v-model="check[item.key]" />
                <span>{{ item.label }}</span>
              </label>
            </div>
            <div class="form-group"><label>Observações</label><textarea class="form-input ta" v-model="check.observacao" /></div>
            <div class="modal-actions">
              <button class="btn-secondary" @click="modalCheck = false">Cancelar</button>
              <button class="btn-primary" @click="salvarCheck">✅ Registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tab = ref('empresas')
const enviando = ref(false)
const modalCheck = ref(false)
const checkItem = ref(null)
const toast = ref({ visible: false, msg: '' })
const empresas = ref([])
const postos = ref([])
const stats = ref({ empresas: 0, postos: 0, pendentes: 0 })
const form = ref({ razao_social: '', nome_fantasia: '', cnpj: '', contrato_numero: '', vigencia_inicio: '', vigencia_fim: '', valor_contrato: 0, objeto: '' })
const check = ref({ mes_ref: new Date().toISOString().slice(0, 7), observacao: '', folha_paga: false, epis_entregues: false, uniformes_ok: false, ponto_enviado: false, certidoes_ok: false })

const tabs = [
  { id: 'empresas', label: '🏢 Empresas' },
  { id: 'postos',   label: '📍 Postos' },
  { id: 'nova',     label: '+ Cadastrar Empresa' },
]

const checkItens = [
  { key: 'folha_paga',     label: '✅ Folha de pagamento comprovada' },
  { key: 'epis_entregues', label: '🦺 EPIs entregues aos funcionários' },
  { key: 'uniformes_ok',   label: '👔 Uniformes em conformidade' },
  { key: 'ponto_enviado',  label: '⏱️ Ponto eletrônico enviado' },
  { key: 'certidoes_ok',   label: '📄 Certidões negativas em dia' },
]

const MOCK_EMP = [
  { EMPRESA_ID: 1, RAZAO_SOCIAL: 'Serv Limpeza LTDA', NOME_FANTASIA: 'CleanServ', CNPJ: '12345678000199', CONTRATO_NUMERO: '001/2025', VIGENCIA_INICIO: '2025-01-01', VIGENCIA_FIM: '2026-12-31', STATUS: 'ATIVO', postos: 45 },
  { EMPRESA_ID: 2, RAZAO_SOCIAL: 'Vigilância Maranhão S.A.', NOME_FANTASIA: 'VigMa', CNPJ: '98765432000188', CONTRATO_NUMERO: '002/2025', VIGENCIA_INICIO: '2025-03-01', VIGENCIA_FIM: '2026-02-28', STATUS: 'ATIVO', postos: 120 },
]

const MOCK_POSTOS = [
  { POSTO_ID: 1, DESCRICAO: 'Limpeza — SEMUS', empresa: 'CleanServ', secretaria: 'SEMUS', QUANTIDADE: 20, FUNCAO: 'Auxiliar de Limpeza' },
  { POSTO_ID: 2, DESCRICAO: 'Vigilância — Prefeitura', empresa: 'VigMa', secretaria: 'SEMAD', QUANTIDADE: 30, FUNCAO: 'Vigilante Patrimonial' },
]

async function carregar() {
  try {
    const [rEmp, rPost] = await Promise.all([
      api.get('/api/v3/terceirizados/empresas'),
      api.get('/api/v3/terceirizados/postos'),
    ])
    empresas.value = rEmp.data.empresas?.data ?? MOCK_EMP
    postos.value   = rPost.data.postos?.data ?? MOCK_POSTOS
  } catch { empresas.value = MOCK_EMP; postos.value = MOCK_POSTOS }
  stats.value = { empresas: empresas.value.filter(e => e.STATUS === 'ATIVO').length, postos: postos.value.length, pendentes: 2 }
}

async function salvar() {
  enviando.value = true
  try {
    await api.post('/api/v3/terceirizados/empresas', form.value)
    showToast('✅ Empresa cadastrada!'); tab.value = 'empresas'; await carregar()
  } catch { showToast('⚠️ Erro ao cadastrar empresa.') }
  finally { enviando.value = false }
}

function abrirCheck(p) { checkItem.value = p; modalCheck.value = true }

async function salvarCheck() {
  try {
    await api.post(`/api/v3/terceirizados/postos/${checkItem.value.POSTO_ID}/checklist`, check.value)
    showToast('✅ Checklist registrado!'); modalCheck.value = false
  } catch { showToast('⚠️ Erro ao salvar checklist.') }
}

const mascaraCnpj = c => c ? String(c).replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5') : '—'
const fmtDate     = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'
function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }
onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.terc-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#292524,#1c1917,#78350f); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#fbbf24; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#f97316; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#fcd34d; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.blue   { border-top:2px solid #60a5fa; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.orange { border-top:2px solid #fb923c; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.tabs-card { display:flex; gap:.5rem; opacity:0; transition:opacity .4s .1s; }
.tabs-card.loaded { opacity:1; }
.tab-btn { padding:.65rem 1.25rem; border-radius:10px; border:1px solid #e2e8f0; background:#fff; font-size:.85rem; font-weight:700; color:#64748b; cursor:pointer; transition:all .15s; }
.tab-btn.active { background:#78350f; color:#fff; border-color:#78350f; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#b45309,#78350f); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; vertical-align:middle; }
.nome  { display:block; font-weight:700; color:#1e293b; }
.sub   { display:block; font-size:.7rem; color:#94a3b8; }
.mono  { font-family:monospace; font-size:.78rem; color:#64748b; white-space:nowrap; }
.bold  { font-weight:800; }
.center { text-align:center; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok   { background:#dcfce7; color:#166534; }
.badge.gray { background:#f1f5f9; color:#64748b; }
.badge.warn { background:#fef3c7; color:#92400e; }
.act-btn { padding:.3rem .55rem; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:.85rem; }
.act-btn.blue:hover { background:#dbeafe; border-color:#bfdbfe; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.ta { resize:vertical; min-height:60px; font-family:inherit; }
.form-actions { display:flex; gap:.75rem; justify-content:flex-end; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); z-index:100; display:flex; align-items:center; justify-content:center; padding:1rem; }
.modal-card { background:#fff; border-radius:20px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.2); }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.modal-hdr h3 { font-size:1rem; font-weight:800; color:#1e293b; margin:0; }
.modal-close { border:none; background:#f1f5f9; border-radius:8px; width:28px; height:28px; cursor:pointer; color:#64748b; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:.9rem; }
.checklist-items { display:flex; flex-direction:column; gap:.5rem; }
.check-item { display:flex; align-items:center; gap:.65rem; font-size:.85rem; color:#334155; cursor:pointer; }
.check-item input { width:16px; height:16px; }
.modal-actions { display:flex; gap:.75rem; }
.modal-enter-active,.modal-leave-active { transition:opacity .3s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>
