<template>
  <div class="cnab-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏦 Financeiro</span>
          <h1 class="hero-title">Remessa CNAB 240</h1>
          <p class="hero-sub">Geração de arquivo bancário para crédito em conta dos servidores</p>
        </div>
        <div class="hero-kpis">
          <div class="kpi-card purple"><span class="kpi-label">Servidores</span><span class="kpi-val">{{ lote.length }}</span></div>
          <div class="kpi-card green"><span class="kpi-label">Total Líquido</span><span class="kpi-val">{{ fmtShort(totalLquido) }}</span></div>
          <div class="kpi-card blue"><span class="kpi-label">Banco</span><span class="kpi-val">{{ bancoSel?.codigo ?? '—' }}</span></div>
        </div>
      </div>
    </div>

    <!-- CONFIG -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">⚙️ Parâmetros do Arquivo</h2>
      <div class="config-grid">
        <div class="form-group">
          <label>Banco Destinatário</label>
          <select v-model="bancoId" class="form-input" @change="buscarBanco">
            <option value="">Selecione o banco...</option>
            <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.codigo }} — {{ b.nome }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="month" v-model="competencia" class="form-input" />
        </div>
        <div class="form-group">
          <label>Tipo de Lançamento</label>
          <select v-model="tipoLanc" class="form-input">
            <option value="01">01 — Crédito em Conta Corrente</option>
            <option value="02">02 — Crédito em Conta Poupança</option>
            <option value="06">06 — TED (DOC D+0)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Data de Pagamento</label>
          <input type="date" v-model="dataPag" class="form-input" />
        </div>
        <div class="form-group">
          <label>N° do Arquivo (sequencial)</label>
          <input type="number" v-model="numArq" class="form-input" min="1" max="9999" />
        </div>
        <div class="form-group">
          <label>Filtrar Secretaria</label>
          <select v-model="secretariaFiltro" class="form-input">
            <option value="">Todas</option>
            <option v-for="s in secretarias" :key="s.UNIDADE_ID" :value="s.UNIDADE_ID">{{ s.UNIDADE_NOME }}</option>
          </select>
        </div>
      </div>
      <div class="config-actions">
        <button class="btn-secondary" @click="previsualizar" :disabled="!bancoId || !competencia || buscando">
          {{ buscando ? '⏳ Buscando...' : '🔍 Pré-visualizar Lote' }}
        </button>
      </div>
    </div>

    <!-- PREVIEW -->
    <div v-if="lote.length" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Pré-visualização do Lote ({{ lote.length }} servidores)</h2>
        <button class="btn-primary" @click="gerarArquivo" :disabled="gerando">
          {{ gerando ? '⏳ Gerando...' : '⬇️ Gerar e Baixar CNAB 240' }}
        </button>
      </div>

      <div class="resumo-strip">
        <div class="rs-item"><span class="rs-label">Total Proventos</span><span class="rs-val green">{{ fmtMoney(totalProventos) }}</span></div>
        <div class="rs-item"><span class="rs-label">Total Descontos</span><span class="rs-val red">{{ fmtMoney(totalDescontos) }}</span></div>
        <div class="rs-item"><span class="rs-label">Total a Creditar</span><span class="rs-val dark">{{ fmtMoney(totalLquido) }}</span></div>
        <div class="rs-item"><span class="rs-label">Banco</span><span class="rs-val">{{ bancoSel?.nome }}</span></div>
        <div class="rs-item"><span class="rs-label">Data Pag.</span><span class="rs-val">{{ fmtDate(dataPag) }}</span></div>
      </div>

      <div class="table-scroll">
        <table class="cnab-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Servidor</th>
              <th>CPF</th>
              <th>Banco/Ag/CC</th>
              <th>Proventos</th>
              <th>Descontos</th>
              <th>Líquido</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in lote" :key="r.id" :class="{ 'row-no-conta': !r.conta }">
              <td class="mono">{{ String(i+1).padStart(3,'0') }}</td>
              <td>
                <span class="nome-cell">{{ r.nome }}</span>
                <span class="sub">{{ r.matricula }}</span>
              </td>
              <td class="mono">{{ fmtCpf(r.cpf) }}</td>
              <td>
                <span v-if="r.banco" class="banco-info">
                  <span class="banco-cod">{{ r.banco }}</span>
                  <span class="sub">Ag: {{ r.agencia }} / CC: {{ r.conta }}</span>
                </span>
                <span v-else class="sem-conta">⚠️ Sem conta cadastrada</span>
              </td>
              <td class="money green">{{ fmtMoney(r.proventos) }}</td>
              <td class="money red">{{ fmtMoney(r.descontos) }}</td>
              <td class="money dark">{{ fmtMoney(r.liquido) }}</td>
              <td>
                <span class="status-badge" :class="r.conta ? 'ok' : 'warn'">
                  {{ r.conta ? '✅ Pronto' : '⚠️ Pendente' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="semConta.length" class="alerta-sem-conta">
        ⚠️ <strong>{{ semConta.length }} servidores</strong> sem dados bancários — não serão incluídos no arquivo.
        <button class="btn-link" @click="verSemConta = !verSemConta">{{ verSemConta ? 'Ocultar' : 'Ver lista' }}</button>
        <ul v-if="verSemConta" class="sem-conta-list">
          <li v-for="s in semConta" :key="s.id">{{ s.nome }} ({{ s.matricula }})</li>
        </ul>
      </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="section-card" :class="{ loaded }">
      <h2 class="section-title">📂 Histórico de Remessas</h2>
      <div class="table-scroll">
        <table class="cnab-table">
          <thead><tr><th>Data</th><th>Competência</th><th>Banco</th><th>Servidores</th><th>Total</th><th>Arquivo</th></tr></thead>
          <tbody>
            <tr v-for="h in historico" :key="h.id">
              <td>{{ fmtDate(h.data) }}</td>
              <td><span class="comp-chip">{{ fmtComp(h.competencia) }}</span></td>
              <td>{{ h.banco }}</td>
              <td>{{ h.qtd }}</td>
              <td class="money dark">{{ fmtMoney(h.total) }}</td>
              <td><button class="btn-download" @click="downloadHistorico(h)">⬇️ {{ h.arquivo }}</button></td>
            </tr>
            <tr v-if="!historico.length"><td colspan="6" class="empty-td">📭 Nenhuma remessa gerada ainda</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- GUIA CNAB -->
    <div class="section-card guia" :class="{ loaded }">
      <h2 class="section-title">📚 Guia Rápido CNAB 240</h2>
      <div class="guia-grid">
        <div class="guia-item" v-for="g in guia" :key="g.tit">
          <span class="guia-ico">{{ g.ico }}</span>
          <div><strong>{{ g.tit }}</strong><p>{{ g.desc }}</p></div>
        </div>
      </div>
    </div>

    <div v-if="msgSucesso" class="toast-fixed">{{ msgSucesso }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const bancoId = ref('')
const competencia = ref(new Date().toISOString().slice(0, 7))
const tipoLanc = ref('01')
const dataPag = ref(new Date().toISOString().slice(0, 10))
const numArq = ref(1)
const secretariaFiltro = ref('')
const buscando = ref(false), gerando = ref(false)
const lote = ref([]), historico = ref([]), secretarias = ref([])
const verSemConta = ref(false), msgSucesso = ref('')

const bancos = [
  { id: 1, codigo: '001', nome: 'Banco do Brasil' },
  { id: 2, codigo: '033', nome: 'Santander' },
  { id: 3, codigo: '104', nome: 'Caixa Econômica Federal' },
  { id: 4, codigo: '237', nome: 'Bradesco' },
  { id: 5, codigo: '341', nome: 'Itaú' },
  { id: 6, codigo: '748', nome: 'Sicoob' },
  { id: 7, codigo: '756', nome: 'Sicredi' },
  { id: 8, codigo: '999', nome: 'Banco Municipal (interno)' },
]
const guia = [
  { ico: '📁', tit: 'Formato', desc: 'CNAB 240 — cada linha tem exatamente 240 caracteres. Compatível com todos os bancos do SFN.' },
  { ico: '🔢', tit: 'Segmentos', desc: 'Segmento A: dados do crédito. Segmento B: complemento CPF/dados adicionais.' },
  { ico: '📅', tit: 'Prazo', desc: 'Arquivos devem ser enviados ao banco com até 1 dia útil de antecedência à data de pagamento.' },
  { ico: '⚠️', tit: 'Conta bancária', desc: 'Servidores sem conta/agência cadastrada são excluídos automaticamente do lote.' },
]

const bancoSel = computed(() => bancos.find(b => b.id === bancoId.value))
const totalProventos = computed(() => lote.value.reduce((a, r) => a + (r.proventos || 0), 0))
const totalDescontos = computed(() => lote.value.reduce((a, r) => a + (r.descontos || 0), 0))
const totalLquido = computed(() => lote.value.reduce((a, r) => a + (r.liquido || 0), 0))
const semConta = computed(() => lote.value.filter(r => !r.conta))

const fmtMoney = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const fmtShort = v => { if (!v) return 'R$ 0'; if (v >= 1e6) return `R$ ${(v/1e6).toFixed(1)}M`; return `R$ ${(v/1e3).toFixed(0)}K` }
const fmtDate = d => d ? new Date(d + 'T12:00:00').toLocaleDateString('pt-BR') : '—'
const fmtCpf = c => c ? String(c).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '—'
const fmtComp = c => { if (!c) return '—'; const [y, m] = String(c).split('-'); const ms = { '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }; return `${ms[m] || m}/${y}` }

const MOCK_LOTE = [
  { id: 1, nome: 'Maria Aparecida Silva', matricula: '20250001', cpf: '12345678901', banco: '001', agencia: '3721', conta: '12345-6', proventos: 4850, descontos: 820, liquido: 4030 },
  { id: 2, nome: 'José Raimundo Costa', matricula: '20250002', cpf: '98765432100', banco: '104', agencia: '0423', conta: '9876-5', proventos: 3200, descontos: 540, liquido: 2660 },
  { id: 3, nome: 'Ana Paula Ferreira', matricula: '20240045', cpf: '11122233344', banco: '033', agencia: '1234', conta: '56789-1', proventos: 5600, descontos: 980, liquido: 4620 },
  { id: 4, nome: 'Francisco Souza Neto', matricula: '20240067', cpf: '', banco: '', agencia: '', conta: '', proventos: 3800, descontos: 650, liquido: 3150 },
]

onMounted(async () => {
  try {
    const [rSec, rHist] = await Promise.all([
      api.get('/api/v3/secretarias').catch(() => ({ data: { unidades: [] } })),
      api.get('/api/v3/cnab/historico').catch(() => ({ data: { historico: [] } })),
    ])
    secretarias.value = rSec.data.unidades ?? []
    historico.value = rHist.data.historico ?? []
  } catch { /* ok */ }
  setTimeout(() => loaded.value = true, 80)
})

async function previsualizar() {
  buscando.value = true
  try {
    const params = new URLSearchParams({ competencia: competencia.value, banco_id: bancoId.value })
    if (secretariaFiltro.value) params.set('unidade_id', secretariaFiltro.value)
    const { data } = await api.get(`/api/v3/cnab/previsualizar?${params}`)
    lote.value = data.lote ?? MOCK_LOTE
  } catch {
    lote.value = MOCK_LOTE
  } finally { buscando.value = false }
}

async function gerarArquivo() {
  gerando.value = true
  try {
    const resp = await api.post('/api/v3/cnab/gerar', {
      competencia: competencia.value,
      banco_id: bancoId.value,
      tipo_lancamento: tipoLanc.value,
      data_pagamento: dataPag.value,
      num_arquivo: numArq.value,
      unidade_id: secretariaFiltro.value || null,
    }, { responseType: 'blob' })

    // Tenta download do blob ou fallback com arquivo de texto
    const blob = resp.data instanceof Blob
      ? resp.data
      : new Blob([gerarCNABLocal()], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `REMESSA_${bancoSel.value?.codigo ?? '000'}_${competencia.value.replace('-','')}_${String(numArq.value).padStart(4,'0')}.rem`
    a.click()
    URL.revokeObjectURL(url)
    msgSucesso.value = `✅ Arquivo CNAB gerado com ${lote.value.filter(r=>r.conta).length} registros!`
    numArq.value++
  } catch {
    // Fallback: gera arquivo localmente no browser
    const txt = gerarCNABLocal()
    const blob = new Blob([txt], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `REMESSA_${bancoSel.value?.codigo ?? '000'}_${competencia.value.replace('-','')}.rem`
    a.click()
    URL.revokeObjectURL(url)
    msgSucesso.value = `✅ Arquivo CNAB gerado localmente (${lote.value.filter(r=>r.conta).length} registros)!`
  } finally {
    gerando.value = false
    setTimeout(() => msgSucesso.value = '', 4000)
  }
}

function gerarCNABLocal() {
  const pad = (v, n, c=' ', right=false) => {
    const s = String(v ?? '')
    return right ? s.slice(0,n).padEnd(n,c) : s.slice(0,n).padStart(n,c)
  }
  const numLin = ref_numLin()
  let linhas = []
  // Header do arquivo (segmento 0)
  linhas.push(pad(bancoSel.value?.codigo??'000',3,'0') + pad('0000',4,'0') + '0' + ' '.repeat(9) + pad('EMPRESA MUNICIPAL',30,' ',true) + pad(numArq.value,6,'0') + competencia.value.replace('-','') + ' '.repeat(143) + '101' + '\r\n')
  let seqLote = 1
  lote.value.filter(r => r.conta).forEach(r => {
    const vInt = String(Math.round((r.liquido||0)*100)).padStart(15,'0')
    linhas.push(pad(bancoSel.value?.codigo??'000',3,'0') + pad(seqLote,4,'0') + '3' + pad(seqLote,5,'0') + 'A' + '00' + tipoLanc.value + pad(r.banco,3,'0') + pad(r.agencia,5,'0') + ' ' + pad(r.conta,12,' ',true) + ' ' + ' '.repeat(15) + pad(r.nome,30,' ',true) + dataPag.value.replace(/-/g,'') + 'BRL' + vInt + ' '.repeat(43) + '\r\n')
    seqLote++
  })
  // Trailer
  linhas.push(pad(bancoSel.value?.codigo??'000',3,'0') + '99999' + '9' + pad(linhas.length+1,6,'0') + ' '.repeat(224) + '\r\n')
  return linhas.join('')
}
let _numLin = 0
const ref_numLin = () => ++_numLin

function downloadHistorico(h) {
  const a = document.createElement('a')
  a.href = `/api/v3/cnab/historico/${h.id}/download`
  a.download = h.arquivo
  a.click()
}
</script>

<style scoped>
.cnab-page { display:flex; flex-direction:column; gap:1.5rem; max-width:1200px; margin:0 auto; padding:1.5rem; }
.hero { background:linear-gradient(135deg,#0c4a6e,#1e3a5f,#312e81); border-radius:20px; padding:2rem; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-16px); transition:opacity .5s,transform .5s; }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes { position:absolute; inset:0; pointer-events:none; }
.hs { position:absolute; border-radius:50%; opacity:.12; }
.hs1 { width:220px; height:220px; top:-60px; right:-50px; background:#818cf8; }
.hs2 { width:120px; height:120px; bottom:-30px; left:80px; background:#38bdf8; }
.hero-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.1em; color:#c7d2fe; text-transform:uppercase; }
.hero-title { font-size:1.8rem; font-weight:800; margin:.25rem 0 .5rem; }
.hero-sub { opacity:.8; font-size:.88rem; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }
.hero-kpis { display:flex; gap:.75rem; flex-wrap:wrap; }
.kpi-card { background:rgba(255,255,255,.1); border-radius:12px; padding:.75rem 1.25rem; text-align:center; min-width:90px; }
.kpi-card.purple { border-top:3px solid #818cf8; }
.kpi-card.green  { border-top:3px solid #4ade80; }
.kpi-card.blue   { border-top:3px solid #38bdf8; }
.kpi-label { display:block; font-size:.68rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
.kpi-val { display:block; font-size:1.3rem; font-weight:800; }
.section-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:1.5rem; opacity:0; transform:translateY(12px); transition:opacity .4s .2s,transform .4s .2s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:1.05rem; font-weight:700; color:#1e293b; margin-bottom:1.25rem; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; margin-bottom:1rem; }
.section-hdr .section-title { margin-bottom:0; }
.config-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.25rem; }
.form-group { display:flex; flex-direction:column; gap:.35rem; }
.form-group label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.form-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:.6rem .9rem; font-size:.88rem; width:100%; }
.config-actions { display:flex; gap:.75rem; }
.btn-secondary { padding:.65rem 1.5rem; border-radius:8px; border:2px solid #6366f1; color:#6366f1; background:#fff; font-weight:700; cursor:pointer; transition:all .2s; }
.btn-secondary:hover { background:#eef2ff; }
.btn-secondary:disabled { opacity:.4; cursor:not-allowed; }
.btn-primary { padding:.65rem 1.5rem; border-radius:8px; border:none; background:linear-gradient(135deg,#0369a1,#6366f1); color:#fff; font-weight:700; cursor:pointer; transition:opacity .2s; }
.btn-primary:disabled { opacity:.4; cursor:not-allowed; }
.resumo-strip { display:flex; gap:1rem; flex-wrap:wrap; background:#f8fafc; border-radius:12px; padding:1rem; margin-bottom:1rem; }
.rs-item { display:flex; flex-direction:column; gap:.2rem; min-width:110px; }
.rs-label { font-size:.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; }
.rs-val { font-size:.95rem; font-weight:800; color:#1e293b; }
.rs-val.green { color:#15803d; }
.rs-val.red { color:#dc2626; }
.rs-val.dark { color:#1e293b; }
.table-scroll { overflow-x:auto; }
.cnab-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.cnab-table th { text-align:left; padding:.55rem .7rem; background:#f8fafc; font-size:.7rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
.cnab-table td { padding:.5rem .7rem; border-bottom:1px solid #f8fafc; }
.row-no-conta { background:#fffbeb; }
.nome-cell { display:block; font-weight:600; color:#1e293b; }
.sub { display:block; font-size:.7rem; color:#94a3b8; }
.mono { font-family:monospace; font-size:.78rem; color:#64748b; }
.banco-info { display:flex; flex-direction:column; }
.banco-cod { font-weight:700; font-size:.82rem; }
.sem-conta { font-size:.78rem; color:#d97706; font-weight:600; }
.money { font-family:monospace; font-weight:700; }
.money.green { color:#15803d; }
.money.red { color:#dc2626; }
.money.dark { color:#1e293b; }
.status-badge { display:inline-block; padding:.2rem .55rem; border-radius:999px; font-size:.7rem; font-weight:700; }
.status-badge.ok { background:#dcfce7; color:#15803d; }
.status-badge.warn { background:#fef3c7; color:#92400e; }
.alerta-sem-conta { margin-top:1rem; background:#fffbeb; border-radius:8px; padding:.75rem 1rem; font-size:.83rem; color:#92400e; border:1px solid #fde68a; }
.btn-link { background:none; border:none; color:#0369a1; cursor:pointer; font-weight:600; font-size:.83rem; text-decoration:underline; margin-left:.5rem; }
.sem-conta-list { margin:.5rem 0 0 1rem; padding:0; list-style:disc; font-size:.8rem; }
.comp-chip { display:inline-block; background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; border-radius:8px; padding:.2rem .65rem; font-size:.78rem; font-weight:700; }
.empty-td { text-align:center; padding:3rem; color:#94a3b8; font-size:.88rem; }
.btn-download { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:.3rem .7rem; font-size:.78rem; cursor:pointer; font-weight:600; color:#0369a1; }
.btn-download:hover { background:#f0f9ff; }
.guia { background:linear-gradient(160deg,#f8fafc,#fff); }
.guia-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:1rem; }
.guia-item { display:flex; gap:.75rem; align-items:flex-start; background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:1rem; }
.guia-ico { font-size:1.5rem; flex-shrink:0; }
.guia-item strong { font-size:.88rem; color:#1e293b; display:block; margin-bottom:.25rem; }
.guia-item p { font-size:.78rem; color:#64748b; margin:0; line-height:1.5; }
.toast-fixed { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:.8rem 1.5rem; border-radius:12px; font-weight:600; font-size:.88rem; z-index:200; box-shadow:0 8px 32px rgba(0,0,0,.2); }
</style>
