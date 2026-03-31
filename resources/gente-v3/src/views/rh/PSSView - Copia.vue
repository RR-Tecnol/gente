<template>
  <div class="pss-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📋 Concurso Público & PSS</span>
          <h1 class="hero-title">Seleções e Concursos — PSS</h1>
          <p class="hero-sub">Gestão de editais, inscrições, vagas e cadastro de convocados</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card green"><span class="kpi-label">Editais Ativos</span><span class="kpi-val">{{ stats.ativos }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Total Candidatos</span><span class="kpi-val">{{ stats.candidatos }}</span></div>
          <div class="kpi-card orange"><span class="kpi-label">Vagas Abertas</span><span class="kpi-val">{{ stats.vagas }}</span></div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-card" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
    </div>

    <!-- Tab: EDITAIS -->
    <div v-if="tab === 'editais'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📄 Editais de Seleção</h2>
        <button class="btn-primary" @click="tab = 'novo'">+ Novo Edital</button>
      </div>
      <div class="editais-grid">
        <div v-if="!editais.length" class="empty-td">📭 Nenhum edital cadastrado</div>
        <div v-for="e in editais" :key="e.EDITAL_ID" class="edital-card" :class="statusEditCls(e.STATUS)">
          <div class="edital-hdr">
            <span class="edital-num">{{ e.NUMERO_EDITAL }}</span>
            <span class="badge" :class="statusEditCls(e.STATUS)">{{ e.STATUS }}</span>
          </div>
          <h3 class="edital-titulo">{{ e.TITULO }}</h3>
          <div class="edital-info">
            <span>📅 {{ fmtDate(e.DATA_ABERTURA) }} → {{ fmtDate(e.DATA_ENCERRAMENTO) }}</span>
            <span>🏢 {{ e.ORGAO }}</span>
          </div>
          <div class="edital-vagas">
            <span>{{ e.TOTAL_VAGAS }} vagas totais · {{ e.VAGAS_AMPLA }} ampla concorrência · {{ e.VAGAS_PCD }} PcD</span>
          </div>
          <button class="btn-see" @click="verCandidatos(e)">Ver candidatos →</button>
        </div>
      </div>
    </div>

    <!-- Tab: CANDIDATOS -->
    <div v-if="tab === 'candidatos'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">👥 Candidatos</h2>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Nome</th><th>CPF</th><th>Cargo Pretendido</th><th>Classificação</th><th>Nota</th><th>Status</th>
          </tr></thead>
          <tbody>
            <tr v-if="!candidatos.length"><td colspan="6" class="empty-td">📭 Selecione um edital para ver candidatos</td></tr>
            <tr v-for="c in candidatos" :key="c.CANDIDATO_ID">
              <td class="nome">{{ c.NOME }}</td>
              <td class="mono">{{ mascaraCpf(c.CPF) }}</td>
              <td>{{ c.cargo }}</td>
              <td class="center bold">{{ c.CLASSIFICACAO }}º</td>
              <td class="center">{{ Number(c.NOTA_FINAL).toFixed(2) }}</td>
              <td><span class="badge" :class="candidCls(c.STATUS)">{{ c.STATUS }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: NOVO EDITAL -->
    <div v-if="tab === 'novo'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📋 Cadastrar Edital de Seleção</h2>
      <div class="form-grid">
        <div class="form-group"><label>Número do Edital</label><input class="form-input" v-model="form.numero_edital" placeholder="Ex: 001/2026 SEMURH" /></div>
        <div class="form-group"><label>Tipo</label><select class="form-input" v-model="form.tipo"><option value="PSS">PSS — Processo Seletivo Simplificado</option><option value="CONCURSO">Concurso Público</option></select></div>
        <div class="form-group full"><label>Título / Descrição</label><input class="form-input" v-model="form.titulo" /></div>
        <div class="form-group"><label>Órgão Responsável</label><input class="form-input" v-model="form.orgao" /></div>
        <div class="form-group"><label>Data de Abertura</label><input class="form-input" type="date" v-model="form.data_abertura" /></div>
        <div class="form-group"><label>Data de Encerramento</label><input class="form-input" type="date" v-model="form.data_encerramento" /></div>
        <div class="form-group"><label>Total de Vagas</label><input class="form-input" type="number" v-model="form.total_vagas" /></div>
        <div class="form-group"><label>Vagas Ampla Concorrência</label><input class="form-input" type="number" v-model="form.vagas_ampla" /></div>
        <div class="form-group"><label>Vagas PcD</label><input class="form-input" type="number" v-model="form.vagas_pcd" /></div>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" @click="tab = 'editais'">Cancelar</button>
        <button class="btn-primary" :disabled="enviando" @click="salvarEdital">
          {{ enviando ? '⏳...' : '✅ Salvar Edital' }}
        </button>
      </div>
    </div>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tab = ref('editais')
const enviando = ref(false)
const toast = ref({ visible: false, msg: '' })
const editais = ref([])
const candidatos = ref([])
const stats = ref({ ativos: 0, candidatos: 0, vagas: 0 })
const form = ref({ numero_edital: '', tipo: 'PSS', titulo: '', orgao: '', data_abertura: '', data_encerramento: '', total_vagas: 0, vagas_ampla: 0, vagas_pcd: 0 })

const tabs = [
  { id: 'editais',    label: '📄 Editais' },
  { id: 'candidatos', label: '👥 Candidatos' },
  { id: 'novo',       label: '+ Novo Edital' },
]

const MOCK_EDIT = [
  { EDITAL_ID: 1, NUMERO_EDITAL: '001/2026 SEMURH', TIPO: 'PSS', TITULO: 'PSS — Técnico de Enfermagem, Auxiliar de Saúde e Agente Comunitário de Saúde', ORGAO: 'SEMUS', DATA_ABERTURA: '2026-01-05', DATA_ENCERRAMENTO: '2026-01-25', TOTAL_VAGAS: 150, VAGAS_AMPLA: 120, VAGAS_PCD: 15, STATUS: 'HOMOLOGADO' },
  { EDITAL_ID: 2, NUMERO_EDITAL: '002/2026 SEMED', TIPO: 'PSS', TITULO: 'PSS — Professor de Educação Infantil e Ensino Fundamental', ORGAO: 'SEMED', DATA_ABERTURA: '2026-02-10', DATA_ENCERRAMENTO: '2026-03-01', TOTAL_VAGAS: 200, VAGAS_AMPLA: 160, VAGAS_PCD: 20, STATUS: 'ABERTO' },
]

const MOCK_CAND = [
  { CANDIDATO_ID: 1, NOME: 'Maria Ferreira', CPF: '11122233344', cargo: 'Técnico de Enfermagem', CLASSIFICACAO: 1, NOTA_FINAL: 87.5, STATUS: 'APROVADO' },
  { CANDIDATO_ID: 2, NOME: 'João Mendes', CPF: '55566677788', cargo: 'Técnico de Enfermagem', CLASSIFICACAO: 2, NOTA_FINAL: 82.3, STATUS: 'APROVADO' },
  { CANDIDATO_ID: 3, NOME: 'Ana Lima', CPF: '99988877766', cargo: 'Auxiliar de Saúde', CLASSIFICACAO: 1, NOTA_FINAL: 79.0, STATUS: 'CONVOCADO' },
]

async function carregar() {
  try {
    const { data } = await api.get('/api/v3/pss/editais')
    editais.value = data.editais?.data ?? MOCK_EDIT
  } catch { editais.value = MOCK_EDIT }
  stats.value = {
    ativos:     editais.value.filter(e => e.STATUS === 'ABERTO').length,
    vagas:      editais.value.reduce((a, e) => a + (e.TOTAL_VAGAS || 0), 0),
    candidatos: 0,
  }
}

async function verCandidatos(e) {
  tab.value = 'candidatos'
  try {
    const { data } = await api.get(`/api/v3/pss/editais/${e.EDITAL_ID}/candidatos`)
    candidatos.value = data.candidatos?.data ?? MOCK_CAND
  } catch { candidatos.value = MOCK_CAND }
}

async function salvarEdital() {
  enviando.value = true
  try {
    await api.post('/api/v3/pss/editais', form.value)
    showToast('✅ Edital cadastrado!'); tab.value = 'editais'; await carregar()
  } catch { showToast('⚠️ Erro ao salvar edital.') }
  finally { enviando.value = false }
}

const statusEditCls = s => ({ ABERTO: 'ok', HOMOLOGADO: 'blue', ENCERRADO: 'gray', SUSPENSO: 'red' })[s] ?? ''
const candidCls     = s => ({ APROVADO: 'ok', CONVOCADO: 'blue', NOMEADO: 'purple', ELIMINADO: 'red', CLASSIFICADO: 'green' })[s] ?? ''
const mascaraCpf    = c => c ? String(c).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '—'
const fmtDate       = d => d ? new Date(d + 'T12:00').toLocaleDateString('pt-BR') : '—'
function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }
onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.pss-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#1e1b4b,#3730a3,#1d4ed8); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#818cf8; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#60a5fa; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#c7d2fe; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.blue   { border-top:2px solid #60a5fa; }
.kpi-card.orange { border-top:2px solid #fb923c; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.tabs-card { display:flex; gap:.5rem; opacity:0; transition:opacity .4s .1s; }
.tabs-card.loaded { opacity:1; }
.tab-btn { padding:.65rem 1.25rem; border-radius:10px; border:1px solid #e2e8f0; background:#fff; font-size:.85rem; font-weight:700; color:#64748b; cursor:pointer; transition:all .15s; }
.tab-btn.active { background:#1d4ed8; color:#fff; border-color:#1d4ed8; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#1d4ed8,#3730a3); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.editais-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1rem; }
.edital-card { border:1.5px solid #e2e8f0; border-radius:14px; padding:1.1rem 1.25rem; transition:box-shadow .15s; }
.edital-card:hover { box-shadow:0 8px 24px rgba(0,0,0,.1); }
.edital-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem; }
.edital-num { font-family:monospace; font-size:.75rem; font-weight:700; color:#6366f1; }
.edital-titulo { font-size:.9rem; font-weight:800; color:#1e293b; margin:.4rem 0 .5rem; line-height:1.4; }
.edital-info { display:flex; flex-direction:column; gap:.2rem; font-size:.78rem; color:#64748b; }
.edital-vagas { font-size:.75rem; color:#94a3b8; margin:.5rem 0; }
.btn-see { font-size:.78rem; font-weight:700; color:#1d4ed8; background:none; border:none; cursor:pointer; padding:0; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; }
.nome  { font-weight:700; color:#1e293b; }
.mono  { font-family:monospace; font-size:.78rem; color:#64748b; }
.bold  { font-weight:800; }
.center { text-align:center; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok     { background:#dcfce7; color:#166534; }
.badge.blue   { background:#dbeafe; color:#1e40af; }
.badge.green  { background:#dcfce7; color:#166534; }
.badge.purple { background:#ede9fe; color:#6d28d9; }
.badge.red    { background:#fee2e2; color:#991b1b; }
.badge.gray   { background:#f1f5f9; color:#64748b; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group.full { grid-column:1/-1; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.form-actions { display:flex; gap:.75rem; justify-content:flex-end; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>
