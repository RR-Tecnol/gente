<template>
  <div class="sagres-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏛️ TCE-MA — SAGRES SINC-Folha</span>
          <h1 class="hero-title">Exportação TCE-MA</h1>
          <p class="hero-sub">Geração de XML SAGRES para envio mensal ao Tribunal de Contas do Maranhão</p>
        </div>
        <div class="kpi-strip">
          <div class="kpi-card orange"><span class="kpi-label">Pendentes</span><span class="kpi-val">{{ stats.pendentes }}</span></div>
          <div class="kpi-card green"><span class="kpi-label">Enviados</span><span class="kpi-val">{{ stats.enviados }}</span></div>
          <div class="kpi-card red"><span class="kpi-label">Com Erros</span><span class="kpi-val">{{ stats.erros }}</span></div>
        </div>
      </div>
    </div>

    <!-- GERADOR -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">⚙️ Gerar XML SAGRES SINC-Folha</h2>
      <div class="gen-grid">
        <div class="form-group">
          <label>Competência</label>
          <input class="form-input" type="month" v-model="comp" />
        </div>
        <div class="form-group">
          <label>Ente</label>
          <input class="form-input" value="Prefeitura Municipal de São Luís — MA" readonly />
        </div>
      </div>
      <div class="btn-row">
        <button class="btn-primary" @click="gerar" :disabled="gerando">
          {{ gerando ? '⏳ Gerando...' : '📄 Gerar XML SAGRES' }}
        </button>
        <button class="btn-secondary" @click="gerarLocal">
          💾 Download XML Local
        </button>
      </div>
    </div>

    <!-- TABELA DEPARA -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">🔄 De-Para de Eventos GENTE ↔ SAGRES</h2>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Código SAGRES</th><th>Descrição SAGRES</th><th>Tipo</th><th>Ativo</th>
          </tr></thead>
          <tbody>
            <tr v-if="!depara.length"><td colspan="4" class="empty-td">📭 Nenhum de-para cadastrado</td></tr>
            <tr v-for="d in depara" :key="d.DEPARA_ID">
              <td class="mono bold">{{ d.SAGRES_COD }}</td>
              <td>{{ d.SAGRES_DESCRICAO }}</td>
              <td><span class="badge" :class="d.TIPO === 'P' ? 'ok' : 'red'">{{ d.TIPO === 'P' ? '+ Provento' : '− Desconto' }}</span></td>
              <td><span class="badge" :class="d.ATIVO ? 'ok' : 'gray'">{{ d.ATIVO ? 'Ativo' : 'Inativo' }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📋 Histórico de Exportações</h2>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr>
            <th>Competência</th><th>Status</th><th>Gerado em</th><th>Enviado em</th><th>Ações</th>
          </tr></thead>
          <tbody>
            <tr v-if="!historico.length"><td colspan="5" class="empty-td">📭 Nenhuma exportação registrada</td></tr>
            <tr v-for="h in historico" :key="h.EXPORTACAO_ID">
              <td class="mono bold">{{ h.COMPETENCIA }}</td>
              <td><span class="badge" :class="statusCls(h.STATUS)">{{ h.STATUS }}</span></td>
              <td>{{ fmtDate(h.created_at) }}</td>
              <td>{{ h.ENVIADO_EM ? fmtDate(h.ENVIADO_EM) : '—' }}</td>
              <td>
                <button v-if="h.ARQUIVO_XML_PATH" class="act-btn blue" @click="baixar(h)">⬇ Download</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- INFO BOX -->
    <div class="info-box" :class="{ loaded }">
      <h4>📋 SAGRES SINC-Folha — Estrutura do XML</h4>
      <pre class="xml-pre">&lt;FOLHA competencia="AAAA-MM" ente="São Luís"&gt;
  &lt;SERVIDOR&gt;
    &lt;CPF&gt;...&lt;/CPF&gt;  &lt;NOME&gt;...&lt;/NOME&gt;  &lt;MATRICULA&gt;...&lt;/MATRICULA&gt;
    &lt;CARGO&gt;...&lt;/CARGO&gt;  &lt;LOTACAO&gt;...&lt;/LOTACAO&gt;
    &lt;REMUNERACAO_BRUTA&gt;...&lt;/REMUNERACAO_BRUTA&gt;
    &lt;DESCONTOS&gt;...&lt;/DESCONTOS&gt;  &lt;LIQUIDO&gt;...&lt;/LIQUIDO&gt;
    &lt;EVENTOS&gt;
      &lt;EVENTO cod="001" descricao="Salário Base" tipo="P" valor="..."/&gt;
      &lt;EVENTO cod="101" descricao="IRRF" tipo="D" valor="..."/&gt;
    &lt;/EVENTOS&gt;
  &lt;/SERVIDOR&gt;
&lt;/FOLHA&gt;</pre>
      <p class="obs">⚠️ Prazo de envio: conforme Calendário de Obrigações do TCE-MA. Atraso pode gerar retenção de repasses federais.</p>
    </div>

    <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded  = ref(false)
const comp    = ref(new Date().toISOString().slice(0, 7))
const gerando = ref(false)
const toast   = ref({ visible: false, msg: '' })
const depara  = ref([])
const historico = ref([])
const stats   = ref({ pendentes: 0, enviados: 0, erros: 0 })

const MOCK_DEPARA = [
  { DEPARA_ID: 1, SAGRES_COD: '001', SAGRES_DESCRICAO: 'Salário Base', TIPO: 'P', ATIVO: 1 },
  { DEPARA_ID: 2, SAGRES_COD: '010', SAGRES_DESCRICAO: 'Gratificação / Adicional', TIPO: 'P', ATIVO: 1 },
  { DEPARA_ID: 3, SAGRES_COD: '020', SAGRES_DESCRICAO: 'Hora Extra', TIPO: 'P', ATIVO: 1 },
  { DEPARA_ID: 4, SAGRES_COD: '030', SAGRES_DESCRICAO: 'Verba Indenizatória', TIPO: 'P', ATIVO: 1 },
  { DEPARA_ID: 5, SAGRES_COD: '040', SAGRES_DESCRICAO: 'Décimo Terceiro Salário', TIPO: 'P', ATIVO: 1 },
  { DEPARA_ID: 6, SAGRES_COD: '101', SAGRES_DESCRICAO: 'IRRF', TIPO: 'D', ATIVO: 1 },
  { DEPARA_ID: 7, SAGRES_COD: '102', SAGRES_DESCRICAO: 'Contribuição Previdenciária (RPPS/IPAM)', TIPO: 'D', ATIVO: 1 },
  { DEPARA_ID: 8, SAGRES_COD: '103', SAGRES_DESCRICAO: 'Consignação em Folha', TIPO: 'D', ATIVO: 1 },
  { DEPARA_ID: 9, SAGRES_COD: '110', SAGRES_DESCRICAO: 'Desconto Diverso', TIPO: 'D', ATIVO: 1 },
]

async function carregar() {
  try {
    const [rDep, rHist] = await Promise.all([
      api.get('/api/v3/sagres/depara'),
      api.get('/api/v3/sagres/exportacoes'),
    ])
    depara.value    = rDep.data.depara ?? MOCK_DEPARA
    historico.value = rHist.data.exportacoes ?? []
  } catch {
    depara.value = MOCK_DEPARA
  }
  stats.value = {
    pendentes: historico.value.filter(h => h.STATUS === 'GERADO').length,
    enviados:  historico.value.filter(h => h.STATUS === 'ENVIADO').length,
    erros:     historico.value.filter(h => h.STATUS === 'ERRO').length,
  }
}

async function gerar() {
  gerando.value = true
  try {
    const { data } = await api.post('/api/v3/sagres/gerar', { competencia: comp.value })
    showToast(`✅ XML SAGRES gerado para ${comp.value}. ID: ${data.exportacao_id}`)
    await carregar()
  } catch {
    showToast('⚠️ Erro ao gerar XML. Verifique se a folha da competência existe.')
  } finally { gerando.value = false }
}

function gerarLocal() {
  const compFmt = comp.value.replace('-', '')
  const mes = comp.value.slice(0, 7)
  const servidoresMock = [
    { cpf: '12345678901', nome: 'MARIA SILVA', matricula: '20250001', cargo: 'ENFERMEIRO', lotacao: 'SEMUS', bruto: 8800, desconto: 1800, liquido: 7000 },
    { cpf: '98765432100', nome: 'JOÃO COSTA', matricula: '20250002', cargo: 'PROFESSOR', lotacao: 'SEMED', bruto: 5500, desconto: 1100, liquido: 4400 },
  ]
  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n<FOLHA competencia="${mes}" ente="São Luís" gerado="${new Date().toISOString()}">\n`
  for (const s of servidoresMock) {
    xml += `  <SERVIDOR>\n`
    xml += `    <CPF>${s.cpf}</CPF>\n    <NOME>${s.nome}</NOME>\n    <MATRICULA>${s.matricula}</MATRICULA>\n`
    xml += `    <CARGO>${s.cargo}</CARGO>\n    <LOTACAO>${s.lotacao}</LOTACAO>\n`
    xml += `    <REMUNERACAO_BRUTA>${s.bruto.toFixed(2)}</REMUNERACAO_BRUTA>\n    <DESCONTOS>${s.desconto.toFixed(2)}</DESCONTOS>\n    <LIQUIDO>${s.liquido.toFixed(2)}</LIQUIDO>\n`
    xml += `    <EVENTOS>\n      <EVENTO cod="001" descricao="Salário Base" tipo="P" valor="${s.bruto.toFixed(2)}"/>\n`
    xml += `      <EVENTO cod="101" descricao="IRRF" tipo="D" valor="${(s.desconto * 0.5).toFixed(2)}"/>\n`
    xml += `      <EVENTO cod="102" descricao="RPPS/IPAM" tipo="D" valor="${(s.desconto * 0.5).toFixed(2)}"/>\n    </EVENTOS>\n  </SERVIDOR>\n`
  }
  xml += `</FOLHA>`
  const blob = new Blob([xml], { type: 'text/xml' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = `SAGRES_${compFmt}.xml`; a.click()
  URL.revokeObjectURL(url)
  showToast('✅ XML SAGRES gerado localmente (dados mock).')
}

function baixar(h) { showToast(`ℹ️ Download do arquivo: ${h.ARQUIVO_XML_PATH ?? 'não disponível'}`) }

const statusCls = s => ({ GERADO: 'warn', VALIDADO: 'blue', ENVIADO: 'ok', ERRO: 'red' })[s] ?? ''
const fmtDate   = d => d ? new Date(d).toLocaleDateString('pt-BR') : '—'
function showToast(msg) { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

onMounted(async () => { await carregar(); setTimeout(() => loaded.value = true, 80) })
</script>

<style scoped>
.sagres-page { display:flex; flex-direction:column; gap:1.25rem; font-family:'Inter',system-ui,sans-serif; }
.hero { background:linear-gradient(135deg,#1c1917,#292524,#7c3aed); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes,.hs { position:absolute; pointer-events:none; }
.hs { border-radius:50%; opacity:.1; inset:unset; }
.hs1 { width:200px; height:200px; top:-60px; right:-40px; background:#f59e0b; }
.hs2 { width:120px; height:120px; bottom:-30px; left:60px; background:#a78bfa; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#fcd34d; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .4rem; }
.hero-sub { font-size:.88rem; opacity:.8; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.kpi-strip { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.65rem 1rem; min-width:90px; text-align:center; }
.kpi-card.orange { border-top:2px solid #fb923c; }
.kpi-card.green  { border-top:2px solid #4ade80; }
.kpi-card.red    { border-top:2px solid #f87171; }
.kpi-label { display:block; font-size:.65rem; opacity:.75; text-transform:uppercase; }
.kpi-val   { display:block; font-size:1.2rem; font-weight:800; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .15s,transform .4s .15s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:1rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
.gen-grid { display:grid; grid-template-columns:1fr 2fr; gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; box-sizing:border-box; }
.btn-row { display:flex; gap:.75rem; }
.btn-primary  { padding:.6rem 1.25rem; border-radius:10px; border:none; background:linear-gradient(135deg,#7c3aed,#6366f1); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.btn-secondary { padding:.6rem 1.25rem; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:.85rem; cursor:pointer; }
.table-scroll { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.data-table th { text-align:left; padding:.55rem .75rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.data-table td { padding:.55rem .75rem; border-bottom:1px solid #f8fafc; }
.mono  { font-family:monospace; font-size:.78rem; color:#64748b; }
.bold  { font-weight:700; color:#1e293b; }
.badge { display:inline-block; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.badge.ok   { background:#dcfce7; color:#166534; }
.badge.blue { background:#dbeafe; color:#1e40af; }
.badge.warn { background:#fef3c7; color:#92400e; }
.badge.red  { background:#fee2e2; color:#991b1b; }
.badge.gray { background:#f1f5f9; color:#64748b; }
.act-btn { padding:.3rem .65rem; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:.8rem; }
.act-btn.blue:hover { background:#dbeafe; border-color:#bfdbfe; }
.empty-td { text-align:center; padding:2.5rem; color:#94a3b8; font-size:.85rem; }
.info-box { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:1.25rem 1.5rem; opacity:0; transform:translateY(10px); transition:opacity .4s .2s,transform .4s .2s; }
.info-box.loaded { opacity:1; transform:none; }
.info-box h4 { font-size:.88rem; font-weight:800; color:#1e293b; margin:0 0 .75rem; }
.xml-pre { background:#f1f5f9; border-radius:10px; padding:1rem; font-size:.75rem; font-family:monospace; white-space:pre-wrap; color:#334155; margin:.5rem 0; }
.obs { font-size:.78rem; color:#92400e; margin:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; }
</style>
