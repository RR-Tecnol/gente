<template>
  <div class="rel-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div><div class="hs hs2"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📊 Inteligência</span>
          <h1 class="hero-title">Central de Relatórios</h1>
          <p class="hero-sub">{{ totalRelatorios }} relatórios · {{ stats.funcionarios ?? '…' }} funcionários ativos · Folha {{ stats.competencia ?? '—' }}</p>
        </div>
        <div class="hero-search-wrap">
          <svg class="hs-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="busca" class="hero-search" placeholder="Buscar relatório..." />
        </div>
      </div>
    </div>

    <!-- CATEGORIAS FILTRO ──────────────────────────────────── -->
    <div class="cat-bar" :class="{ loaded }">
      <button
        v-for="c in categorias"
        :key="c.id"
        class="cat-btn"
        :class="{ active: catAtiva === c.id }"
        @click="catAtiva = catAtiva === c.id ? '' : c.id"
      >
        <span>{{ c.ico }}</span>
        <span>{{ c.nome }}</span>
        <span class="cat-count">{{ relatoriosPorCat(c.id).length }}</span>
      </button>
    </div>

    <!-- GRID DE CARDS ──────────────────────────────────────── -->
    <div class="rel-grid" :class="{ loaded }">
      <template v-for="(cat, ci) in categorias" :key="cat.id">
        <template v-if="!catAtiva || catAtiva === cat.id">
          <div class="cat-section" v-if="relFiltrados(cat.id).length">
            <div class="cat-section-title">
              <span class="cat-section-ico">{{ cat.ico }}</span>
              <h2>{{ cat.nome }}</h2>
              <span class="cat-section-line"></span>
            </div>
            <div class="rel-cards">
              <button
                v-for="(r, ri) in relFiltrados(cat.id)"
                :key="r.label"
                class="rel-card"
                :class="{ active: painelAtivo?.label === r.label }"
                :style="{ '--cat-color': cat.cor, '--ci': ci, '--ri': ri }"
                @click="abrirPainel(r)"
              >
                <div class="rc-ico">{{ r.ico }}</div>
                <div class="rc-info">
                  <span class="rc-label">{{ r.label }}</span>
                  <span class="rc-desc">{{ r.desc }}</span>
                </div>
                <div class="rc-arrow">
                  <svg v-if="painelAtivo?.label !== r.label" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L12 12L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
              </button>
            </div>
          </div>
        </template>
      </template>

      <!-- Sem resultados -->
      <div v-if="semResultados" class="no-results">
        <span>🔍</span>
        <p>Nenhum relatório encontrado para "<strong>{{ busca }}</strong>"</p>
      </div>
    </div>

    <!-- ══ PAINEL INLINE DE DADOS ══════════════════════════════ -->
    <transition name="painel-fade">
      <div v-if="painelAtivo" class="painel">

        <!-- Cabeçalho do painel -->
        <div class="painel-header">
          <div class="painel-title-wrap">
            <span class="painel-ico">{{ painelAtivo.ico }}</span>
            <div>
              <h2 class="painel-title">{{ painelAtivo.label }}</h2>
              <p class="painel-desc">{{ painelAtivo.desc }}</p>
            </div>
          </div>
          <div class="painel-actions">
            <button class="btn-export btn-csv" @click="exportarCSV" :disabled="!dadosRelatorio.length" title="Exportar CSV">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M4 16l4 4 4-4M12 12v8M20 12H4M4 4h16v8H4z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              CSV
            </button>
            <button class="btn-export btn-xls" @click="exportarExcel" :disabled="!dadosRelatorio.length" title="Exportar Excel">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 13l6 6M15 13l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              Excel
            </button>
            <button class="btn-export btn-pdf" @click="exportarPDF" :disabled="!dadosRelatorio.length" title="Exportar PDF">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M9 15h6M9 11h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              PDF
            </button>
            <button class="btn-fechar" @click="painelAtivo = null">✕ Fechar</button>
          </div>
        </div>

        <!-- Filtros dinâmicos -->
        <div class="painel-filtros">
          <template v-if="painelAtivo.filtro === 'periodo'">
            <div class="filtro-group">
              <label>Data início</label>
              <input type="date" v-model="filtros.data_inicio" class="filtro-input" @change="() => carregarRelatorio()" />
            </div>
            <div class="filtro-group">
              <label>Data fim</label>
              <input type="date" v-model="filtros.data_fim" class="filtro-input" @change="() => carregarRelatorio()" />
            </div>
          </template>
          <template v-else-if="painelAtivo.filtro === 'competencia'">
            <div class="filtro-group">
              <label>Competência</label>
              <input type="month" v-model="filtros.competencia" class="filtro-input" @change="() => carregarRelatorio()" />
            </div>
          </template>
          <template v-if="painelAtivo.filtro">
            <div class="filtro-group">
              <label>Busca</label>
              <input type="text" v-model="filtros.busca" class="filtro-input" placeholder="Nome ou matrícula..." @keyup.enter="() => carregarRelatorio()" />
            </div>
            <button class="btn-filtrar" @click="() => carregarRelatorio()">🔍 Filtrar</button>
          </template>
        </div>

        <!-- Totais (folha) -->
        <div v-if="totais" class="totais-bar">
          <div class="total-item">
            <span class="total-label">Servidores</span>
            <span class="total-val">{{ totais.servidores }}</span>
          </div>
          <div class="total-item green">
            <span class="total-label">Proventos</span>
            <span class="total-val">{{ moeda(totais.bruto) }}</span>
          </div>
          <div class="total-item red">
            <span class="total-label">Descontos</span>
            <span class="total-val">{{ moeda(totais.descontos) }}</span>
          </div>
          <div class="total-item blue">
            <span class="total-label">Líquido</span>
            <span class="total-val">{{ moeda(totais.liquido) }}</span>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="carregando" class="painel-loading">
          <div class="spinner"></div>
          <span>Carregando dados...</span>
        </div>

        <!-- Tabela de dados -->
        <div v-else-if="dadosRelatorio.length" class="table-wrap">
          <table class="rel-table">
            <thead>
              <tr>
                <th v-for="col in colunas" :key="col.key">{{ col.label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in dadosRelatorio" :key="i">
                <td v-for="col in colunas" :key="col.key" :class="col.classe">
                  <span v-if="col.format === 'moeda'">{{ moeda(row[col.key]) }}</span>
                  <span v-else-if="col.format === 'data'">{{ formatData(row[col.key]) }}</span>
                  <span v-else-if="col.format === 'status'" :class="`badge badge-${row[col.key]?.toLowerCase()}`">{{ row[col.key] || '—' }}</span>
                  <span v-else>{{ row[col.key] || '—' }}</span>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Paginação -->
          <div class="paginacao" v-if="paginacao.last_page > 1">
            <button class="pg-btn" :disabled="paginacao.current_page <= 1" @click="mudarPagina(paginacao.current_page - 1)">‹ Anterior</button>
            <span class="pg-info">Página {{ paginacao.current_page }} de {{ paginacao.last_page }} · {{ paginacao.total }} registros</span>
            <button class="pg-btn" :disabled="paginacao.current_page >= paginacao.last_page" @click="mudarPagina(paginacao.current_page + 1)">Próxima ›</button>
          </div>
          <p v-else class="pg-info">{{ dadosRelatorio.length }} registros</p>
        </div>

        <!-- Sem dados -->
        <div v-else class="painel-vazio">
          <span>📭</span>
          <p>Nenhum dado encontrado para os filtros selecionados.</p>
        </div>

      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const busca = ref('')
const catAtiva = ref('')
const loaded = ref(false)
const stats = ref({ funcionarios: null, competencia: null })

// ── Estado do painel ─────────────────────────────────────────────
const painelAtivo = ref(null)
const carregando  = ref(false)
const dadosRelatorio = ref([])
const colunas     = ref([])
const totais      = ref(null)
const paginacao   = ref({ current_page: 1, last_page: 1, total: 0 })
const filtros = ref({
  data_inicio: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10),
  data_fim:    new Date().toISOString().slice(0,10),
  competencia: new Date().toISOString().slice(0,7),
  busca:       '',
})

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/relatorios/stats')
    if (!data.fallback) {
      stats.value = data
      filtros.value.competencia = data.competencia ?? filtros.value.competencia
    }
  } catch { /* mantém defaults */ }
  setTimeout(() => { loaded.value = true }, 80)
})

// ── Configurações de cada relatório ─────────────────────────────
const CONFIG_RELATORIOS = {
  funcionarios: {
    endpoint: '/api/v3/relatorios/funcionarios',
    filtro: 'none',
    colunas: [
      { key: 'matricula', label: 'Matrícula' },
      { key: 'nome',      label: 'Nome' },
      { key: 'cargo',     label: 'Cargo' },
      { key: 'setor',     label: 'Setor' },
      { key: 'admissao',  label: 'Admissão', format: 'data' },
    ],
    paginado: true,
  },
  admissoes: {
    endpoint: '/api/v3/relatorios/admissoes',
    filtro: 'periodo',
    colunas: [
      { key: 'matricula', label: 'Matrícula' },
      { key: 'nome',      label: 'Nome' },
      { key: 'cargo',     label: 'Cargo' },
      { key: 'setor',     label: 'Setor' },
      { key: 'admissao',  label: 'Admissão', format: 'data' },
    ],
  },
  frequencia: {
    endpoint: '/api/v3/relatorios/frequencia',
    filtro: 'periodo',
    colunas: [
      { key: 'nome',      label: 'Funcionário' },
      { key: 'setor',     label: 'Setor' },
      { key: 'presencas', label: 'Presenças' },
      { key: 'faltas',    label: 'Faltas' },
      { key: 'atrasos',   label: 'Atrasos' },
    ],
    resumo: true,
  },
  folha: {
    endpoint: '/api/v3/relatorios/folha',
    filtro: 'competencia',
    colunas: [
      { key: 'matricula', label: 'Matrícula' },
      { key: 'nome',      label: 'Nome' },
      { key: 'cargo',     label: 'Cargo' },
      { key: 'setor',     label: 'Setor' },
      { key: 'bruto',     label: 'Bruto',    format: 'moeda', classe: 'num green-text' },
      { key: 'descontos', label: 'Descontos',format: 'moeda', classe: 'num red-text' },
      { key: 'liquido',   label: 'Líquido',  format: 'moeda', classe: 'num blue-text' },
    ],
    totais: true,
  },
}

const categorias = [
  { id: 'rh',         nome: 'Recursos Humanos', ico: '👥', cor: '#6366f1' },
  { id: 'financeiro', nome: 'Financeiro',        ico: '💰', cor: '#10b981' },
  { id: 'ponto',      nome: 'Ponto Eletrônico', ico: '⏱️', cor: '#f59e0b' },
  { id: 'escala',     nome: 'Escalas',           ico: '📅', cor: '#0d9488' },
  { id: 'admin',      nome: 'Administrativo',    ico: '⚙️', cor: '#8b5cf6' },
]

const relatorios = [
  { cat: 'rh',         ico: '📋', label: 'Quadro de Funcionários',  desc: 'Lista de servidores ativos',          cfgKey: 'funcionarios' },
  { cat: 'rh',         ico: '🏷️', label: 'Admissões do Período',    desc: 'Funcionários admitidos por período',  cfgKey: 'admissoes' },
  { cat: 'rh',         ico: '🏳️', label: 'Servidores por Setor',    desc: 'Distribuição por unidade',            href: '/organograma' },
  { cat: 'financeiro', ico: '💵', label: 'Folha de Pagamento',      desc: 'Proventos e descontos por competência', cfgKey: 'folha' },
  { cat: 'financeiro', ico: '📈', label: 'Progressão Salarial',     desc: 'Histórico de remuneração',            href: '/progressao-funcional' },
  { cat: 'financeiro', ico: '🏦', label: 'Remessa CNAB 240',        desc: 'Arquivo de crédito bancário',         href: '/remessa-cnab' },
  { cat: 'ponto',      ico: '📆', label: 'Espelho de Ponto',        desc: 'Frequência por período',              cfgKey: 'frequencia' },
  { cat: 'ponto',      ico: '❌', label: 'Faltas e Atrasos',        desc: 'Ocorrências e justificativas',        href: '/faltas-atrasos' },
  { cat: 'ponto',      ico: '✅', label: 'Abono de Faltas',         desc: 'Abonos enviados e aprovados',         href: '/abono-faltas' },
  { cat: 'ponto',      ico: '🕐', label: 'Banco de Horas',          desc: 'Saldo de horas trabalhadas vs esperadas', href: '/banco-horas' },
  { cat: 'escala',     ico: '🗂️', label: 'Matriz de Escala',        desc: 'Grade de plantões por unidade',       href: '/escala-matriz-v3' },
  { cat: 'escala',     ico: '🔄', label: 'Substituições',           desc: 'Histórico de trocas de plantão',      href: '/substituicoes' },
  { cat: 'escala',     ico: '📡', label: 'Sobreaviso',              desc: 'Períodos de sobreaviso',               href: '/escala-sobreaviso' },
  { cat: 'admin',      ico: '🏢', label: 'Organograma',             desc: 'Setores e hierarquia',                href: '/organograma' },
  { cat: 'admin',      ico: '⚙️', label: 'Configurações',           desc: 'Parâmetros do sistema',               href: '/configuracoes' },
]

const totalRelatorios = computed(() => relatorios.length)
const relatoriosPorCat = (catId) => relatorios.filter(r => r.cat === catId)
const relFiltrados = (catId) => {
  let list = relatoriosPorCat(catId)
  if (busca.value) {
    const term = busca.value.toLowerCase()
    list = list.filter(r => r.label.toLowerCase().includes(term) || r.desc.toLowerCase().includes(term))
  }
  return list
}
const semResultados = computed(() => {
  if (!busca.value) return false
  return categorias.every(c => relFiltrados(c.id).length === 0)
})

// ── Abre painel ──────────────────────────────────────────────────
const abrirPainel = (r) => {
  if (r.href) { window.location.href = r.href; return }
  if (!r.cfgKey || !CONFIG_RELATORIOS[r.cfgKey]) return

  if (painelAtivo.value?.label === r.label) {
    painelAtivo.value = null
    return
  }

  painelAtivo.value = { ...r, ...CONFIG_RELATORIOS[r.cfgKey] }
  dadosRelatorio.value = []
  totais.value = null
  carregarRelatorio()

  // Scroll suave até o painel
  setTimeout(() => document.querySelector('.painel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100)
}

const carregarRelatorio = async (pagina = 1) => {
  if (!painelAtivo.value?.endpoint) return
  carregando.value = true
  dadosRelatorio.value = []
  totais.value = null

  try {
    const params = { page: pagina }
    const f = painelAtivo.value.filtro
    if (f === 'periodo') {
      params.data_inicio = filtros.value.data_inicio
      params.data_fim    = filtros.value.data_fim
    } else if (f === 'competencia') {
      params.competencia = filtros.value.competencia
    }
    if (filtros.value.busca) params.busca = filtros.value.busca

    const { data } = await api.get(painelAtivo.value.endpoint, { params })

    // Suporte a paginado e não paginado
    if (data.data && Array.isArray(data.data)) {
      dadosRelatorio.value = painelAtivo.value.resumo && data.resumo ? data.resumo : data.data
      paginacao.value = { current_page: data.current_page ?? 1, last_page: data.last_page ?? 1, total: data.total ?? data.data.length }
    } else if (Array.isArray(data)) {
      dadosRelatorio.value = data
    }

    if (data.totais) totais.value = data.totais
    colunas.value = painelAtivo.value.colunas
  } catch (e) {
    dadosRelatorio.value = []
  } finally {
    carregando.value = false
  }
}

const mudarPagina = (p) => carregarRelatorio(p)

// ── Exportar CSV ─────────────────────────────────────────────────
const exportarCSV = () => {
  if (!dadosRelatorio.value.length) return
  const cols = colunas.value
  const header = cols.map(c => c.label).join(';')
  const rows = dadosRelatorio.value.map(row =>
    cols.map(c => `"${row[c.key] ?? ''}"`.replace(/\n/g, ' ')).join(';')
  )
  const csv = [header, ...rows].join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a'); a.href = url
  a.download = `${nomeArquivo()}.csv`
  a.click(); URL.revokeObjectURL(url)
}

// ── Exportar Excel (HTML table → .xls sem lib externa) ───────────
const exportarExcel = () => {
  if (!dadosRelatorio.value.length) return
  const cols  = colunas.value
  const nomeRel = painelAtivo.value?.label ?? 'Relatório'

  const thead = `<tr>${cols.map(c => `<th style="background:#1e293b;color:#fff;font-weight:bold;padding:8px 12px;white-space:nowrap">${c.label}</th>`).join('')}</tr>`
  const tbody = dadosRelatorio.value.map(row =>
    `<tr>${cols.map(c => {
      const v = row[c.key] ?? ''
      const style = c.format === 'moeda'
        ? 'text-align:right;mso-number-format:"#,##0.00"'
        : c.format === 'data' ? 'mso-number-format:"yyyy-mm-dd"' : ''
      return `<td style="padding:7px 12px;border-bottom:1px solid #e2e8f0;${style}">${v}</td>`
    }).join('')}</tr>`
  ).join('')

  const html = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="utf-8">
    <style>table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px}</style>
    </head><body>
    <h2 style="font-family:Arial;color:#1e293b">${nomeRel}</h2>
    <p style="font-family:Arial;font-size:11px;color:#64748b">Gerado em ${new Date().toLocaleString('pt-BR')}</p>
    <table><thead>${thead}</thead><tbody>${tbody}</tbody></table>
    </body></html>`

  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a'); a.href = url
  a.download = `${nomeArquivo()}.xls`
  a.click(); URL.revokeObjectURL(url)
}

// ── Exportar PDF (via print CSS) ─────────────────────────────────
const exportarPDF = () => {
  if (!dadosRelatorio.value.length) return
  const cols   = colunas.value
  const nomeRel = painelAtivo.value?.label ?? 'Relatório'
  const hoje   = new Date().toLocaleString('pt-BR')

  const thead = `<tr>${cols.map(c => `<th>${c.label}</th>`).join('')}</tr>`
  const tbody = dadosRelatorio.value.map(row =>
    `<tr>${cols.map(c => `<td>${row[c.key] ?? '—'}</td>`).join('')}</tr>`
  ).join('')

  const win = window.open('', '_blank')
  win.document.write(`
    <!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">
    <title>${nomeRel}</title>
    <style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 20px; }
      .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 2px solid #1e293b; padding-bottom: 10px; }
      .header h1 { font-size: 16px; font-weight: bold; }
      .header small { font-size: 10px; color: #64748b; }
      .org { font-size: 11px; color: #475569; margin-top: 2px; }
      table { width: 100%; border-collapse: collapse; margin-top: 4px; }
      th { background: #1e293b; color: #fff; font-weight: bold; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
      td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
      tr:nth-child(even) td { background: #f8fafc; }
      .total-row td { font-weight: bold; border-top: 2px solid #1e293b; background: #f8fafc; }
      @media print { body { padding: 10px; } }
    </style>
    </head><body>
    <div class="header">
      <div><h1>📊 ${nomeRel}</h1><p class="org">GENTE v3 — Central de Relatórios</p></div>
      <small>Gerado em ${hoje}</small>
    </div>
    <table><thead>${thead}</thead><tbody>${tbody}</tbody></table>
    </body></html>`)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print(); win.close() }, 400)
}

const nomeArquivo = () =>
  `${(painelAtivo.value?.label ?? 'relatorio').replace(/\s+/g, '-')}_${new Date().toISOString().slice(0, 10)}`

// ── Helpers ──────────────────────────────────────────────────────
const moeda = (v) => v != null ? new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(v) : '—'
const formatData = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—'
</script>

<style scoped>
.rel-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f2a1f 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 260px; height: 260px; background: #10b981; right: 20px; top: -70px; }
.hs2 { width: 200px; height: 200px; background: #6366f1; left: 30%; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-search-wrap { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 11px 16px; }
.hs-ico { width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0; }
.hero-search { border: none; background: transparent; color: #fff; font-size: 14px; font-family: inherit; outline: none; width: 200px; }
.hero-search::placeholder { color: #475569; }

/* CAT BAR */
.cat-bar { display: flex; gap: 8px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.08s; }
.cat-bar.loaded { opacity: 1; transform: none; }
.cat-btn { display: flex; align-items: center; gap: 7px; padding: 9px 15px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.cat-btn:hover { border-color: #6366f1; color: #4f46e5; }
.cat-btn.active { background: #f0f9ff; border-color: #6366f1; color: #4f46e5; }
.cat-count { background: #f1f5f9; color: #64748b; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 800; }
.cat-btn.active .cat-count { background: #e0e7ff; color: #4f46e5; }

/* GRID */
.rel-grid { display: flex; flex-direction: column; gap: 28px; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.12s; }
.rel-grid.loaded { opacity: 1; transform: none; }
.cat-section-title { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.cat-section-ico { font-size: 18px; }
.cat-section-title h2 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.cat-section-line { flex: 1; height: 1px; background: #f1f5f9; }

.rel-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.rel-card {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
  text-decoration: none; transition: all 0.18s; cursor: pointer; text-align: left; font-family: inherit;
  animation: cardIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) calc((var(--ci) * 50ms) + (var(--ri) * 30ms)) both;
}
@keyframes cardIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.rel-card:hover { border-color: var(--cat-color); transform: translateY(-2px); box-shadow: 0 8px 24px -8px color-mix(in srgb, var(--cat-color) 20%, transparent); }
.rel-card.active { border-color: var(--cat-color); background: color-mix(in srgb, var(--cat-color) 6%, white); }
.rc-ico { font-size: 22px; flex-shrink: 0; width: 44px; height: 44px; background: color-mix(in srgb, var(--cat-color) 8%, white); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid color-mix(in srgb, var(--cat-color) 15%, transparent); }
.rc-info { flex: 1; min-width: 0; }
.rc-label { display: block; font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rc-desc { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.rc-arrow { color: #cbd5e1; transition: all 0.15s; }
.rel-card:hover .rc-arrow { color: var(--cat-color); }

.no-results { display: flex; flex-direction: column; align-items: center; padding: 60px; color: #94a3b8; gap: 10px; font-size: 16px; text-align: center; }
.no-results span { font-size: 40px; }

/* ══ PAINEL ══════════════════════════════════════════════════════ */
.painel {
  background: #fff; border-radius: 20px; border: 1px solid #e2e8f0;
  box-shadow: 0 4px 32px -8px rgba(0,0,0,0.08);
  overflow: hidden;
}
.painel-fade-enter-active, .painel-fade-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.painel-fade-enter-from, .painel-fade-leave-to { opacity: 0; transform: translateY(10px); }

.painel-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fafafa; }
.painel-title-wrap { display: flex; align-items: center; gap: 14px; }
.painel-ico { font-size: 28px; }
.painel-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; }
.painel-desc { font-size: 12px; color: #94a3b8; margin: 0; }
.painel-actions { display: flex; gap: 8px; }

.btn-export { display: flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; border: 1.5px solid; }
.btn-export:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-csv { border-color: #6366f1; background: #eff6ff; color: #4f46e5; }
.btn-csv:hover:not(:disabled) { background: #6366f1; color: #fff; }
.btn-xls { border-color: #16a34a; background: #f0fdf4; color: #15803d; }
.btn-xls:hover:not(:disabled) { background: #16a34a; color: #fff; }
.btn-pdf { border-color: #dc2626; background: #fef2f2; color: #b91c1c; }
.btn-pdf:hover:not(:disabled) { background: #dc2626; color: #fff; }
.btn-fechar { padding: 9px 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #fff; color: #64748b; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.btn-fechar:hover { background: #f8fafc; }

/* Filtros */
.painel-filtros { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 12px; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
.filtro-group { display: flex; flex-direction: column; gap: 4px; }
.filtro-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.filtro-input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: inherit; color: #1e293b; outline: none; background: #fff; }
.filtro-input:focus { border-color: #6366f1; }
.btn-filtrar { padding: 8px 18px; border: none; border-radius: 10px; background: #6366f1; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-filtrar:hover { background: #4f46e5; }

/* Totais */
.totais-bar { display: flex; gap: 1px; background: #f1f5f9; border-bottom: 1px solid #f1f5f9; }
.total-item { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 12px 10px; background: #fff; gap: 3px; }
.total-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.07em; }
.total-val { font-size: 16px; font-weight: 800; color: #1e293b; }
.total-item.green .total-val { color: #16a34a; }
.total-item.red   .total-val { color: #dc2626; }
.total-item.blue  .total-val { color: #2563eb; }

/* Loading */
.painel-loading { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px; color: #64748b; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Tabela */
.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.rel-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.rel-table th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.rel-table td { padding: 11px 14px; font-size: 13px; color: #334155; border-bottom: 1px solid #f8fafc; }
.rel-table tr:hover td { background: #fafbff; }
.rel-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.green-text { color: #16a34a !important; font-weight: 700; }
.red-text   { color: #dc2626 !important; font-weight: 700; }
.blue-text  { color: #2563eb !important; font-weight: 700; }

/* Badges de status */
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-presente { background: #dcfce7; color: #15803d; }
.badge-falta    { background: #fee2e2; color: #b91c1c; }
.badge-atraso   { background: #fef3c7; color: #b45309; }

/* Paginação */
.paginacao { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 14px 24px; border-top: 1px solid #f1f5f9; }
.pg-btn { padding: 7px 16px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: inherit; color: #475569; }
.pg-btn:hover:not(:disabled) { background: #f1f5f9; }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-info { font-size: 12px; color: #94a3b8; font-weight: 600; padding: 14px 24px; text-align: center; }

/* Vazio */
.painel-vazio { display: flex; flex-direction: column; align-items: center; padding: 50px; color: #94a3b8; gap: 10px; }
.painel-vazio span { font-size: 40px; }
.painel-vazio p { font-size: 14px; }

/* Responsive */
@media (max-width: 640px) {
  .hero { padding: 20px; }
  .hero-title { font-size: 22px; }
  .hero-inner { flex-direction: column; }
  .hero-search { width: 100%; }
  .painel-header { flex-direction: column; align-items: flex-start; }
  .painel-actions { width: 100%; }
  .btn-export, .btn-fechar { flex: 1; justify-content: center; }
  .totais-bar { flex-wrap: wrap; }
  .total-item { width: 50%; flex-basis: 50%; }
}
</style>
