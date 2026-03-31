<template>
  <div class="folha-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div><div class="hs hs2"></div><div class="hs hs3"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">💼 Folha de Pagamento</span>
          <h1 class="hero-title">Processamento de Folha</h1>
          <p class="hero-sub">Gestão centralizada de folhas de pagamento e contra-cheques</p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-strip" v-if="ultima">
          <div class="kpi-card purple">
            <span class="kpi-label">Competência Atual</span>
            <span class="kpi-val">{{ formatCompetencia(ultima.FOLHA_COMPETENCIA) }}</span>
          </div>
          <div class="kpi-card green">
            <span class="kpi-label">Proventos</span>
            <span class="kpi-val">{{ formatMoney(ultima.total_proventos) }}</span>
          </div>
          <div class="kpi-card red">
            <span class="kpi-label">Descontos</span>
            <span class="kpi-val">{{ formatMoney(ultima.total_descontos) }}</span>
          </div>
          <div class="kpi-card blue">
            <span class="kpi-label">Líquido</span>
            <span class="kpi-val">{{ formatMoney(ultima.total_liquido) }}</span>
          </div>
          <div class="kpi-card teal">
            <span class="kpi-label">Servidores</span>
            <span class="kpi-val">{{ ultima.qtd_funcionarios ?? '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOADING ─────────────────────────────────────────────── -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Carregando folhas...</p>
    </div>

    <template v-else>

      <!-- EVOLUÇÃO MENSAL ──────────────────────────────────────── -->
      <div class="section-card evolucao-card" :class="{ loaded }">
        <h2 class="section-title">📊 Evolução Mensal · Últimos 6 meses</h2>
        <div class="bars-wrap">
          <div
            v-for="f in folhasGrafico"
            :key="f.FOLHA_ID"
            class="bar-col"
          >
            <span class="bar-val">{{ formatMoneyShort(f.total_liquido) }}</span>
            <div class="bar-track">
              <div class="bar-fill" :style="{ height: f.pct + '%' }"></div>
            </div>
            <span class="bar-label">{{ formatCompetenciaShort(f.FOLHA_COMPETENCIA) }}</span>
          </div>
        </div>
      </div>

      <!-- TABELA ──────────────────────────────────────────────── -->
      <div class="section-card" :class="{ loaded }">
        <div class="section-hdr">
          <h2 class="section-title">📋 Todas as Folhas</h2>
          <div class="toolbar-right">
            <select v-model="secretariaSel" class="sec-select" @change="filtrarPorSecretaria">
              <option value="">🏢 Todas as Secretarias</option>
              <option v-for="s in secretarias" :key="s.UNIDADE_ID" :value="s.UNIDADE_ID">{{ s.UNIDADE_NOME }}</option>
            </select>
            <div class="search-wrap">
              <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              <input v-model="busca" class="search-input" placeholder="Competência..." />
            </div>
            <button class="calc-btn" @click="modalCalc = true">
              🚀 Calcular Nova Folha
            </button>
          </div>
        </div>
        <div class="table-scroll">
          <table class="folha-table">
            <thead>
              <tr>
                <th>Competência</th>
                <th v-if="secretariaSel">Secretaria</th>
                <th>Situação</th>
                <th>Servidores</th>
                <th>Proventos</th>
                <th>Descontos</th>
                <th>Líquido</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(f, i) in folhasFiltradas"
                :key="f.FOLHA_ID"
                class="f-row"
                :style="{ '--rd': `${i * 35}ms` }"
                :class="{ 'row-in': loaded }"
              >
                <td>
                  <span class="comp-chip">{{ formatCompetencia(f.FOLHA_COMPETENCIA) }}</span>
                </td>
                <td v-if="secretariaSel">
                  <span class="sec-chip">{{ f.secretaria_nome ?? '—' }}</span>
                </td>
                <td>
                  <span class="sit-badge" :class="sitClass(f.FOLHA_SITUACAO)">
                    <span class="sit-dot"></span>
                    {{ sitLabel(f.FOLHA_SITUACAO) }}
                  </span>
                </td>
                <td>
                  <span class="num-big">{{ f.qtd_funcionarios ?? '—' }}</span>
                </td>
                <td class="money green">{{ formatMoney(f.total_proventos) }}</td>
                <td class="money red">{{ formatMoney(f.total_descontos) }}</td>
                <td class="money dark">{{ formatMoney(f.total_liquido) }}</td>
                <td>
                  <div class="row-actions">
                    <button class="act-btn act-blue" title="Ver detalhes" @click="abrirDetalhes(f)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <button class="act-btn act-green" title="Gerar CNAB 240" @click="$router.push('/remessa-cnab')">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 15l-4.5-4.5M12 15l4.5-4.5M12 15V3M3 21h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="folhasFiltradas.length === 0">
                <td colspan="7" class="empty-td">
                  <span>📭</span> Nenhuma folha encontrada
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>

  </div>

  <!-- MODAL CALCULAR FOLHA -->
  <transition name="modal">
    <div v-if="modalCalc" class="modal-overlay" @click.self="modalCalc = false">
      <div class="modal-card">
        <div class="modal-hdr">
          <h3>🚀 Calcular Nova Folha</h3>
          <button class="modal-close" @click="modalCalc = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="calc-hint">Selecione a competência (mês/ano) da folha já criada no banco para consolidar os totais.</p>
          <div class="cal-row">
            <div class="form-group">
              <label>Mês</label>
              <select v-model="calcMes" class="cfg-input">
                <option value="01">Janeiro</option><option value="02">Fevereiro</option>
                <option value="03">Março</option><option value="04">Abril</option>
                <option value="05">Maio</option><option value="06">Junho</option>
                <option value="07">Julho</option><option value="08">Agosto</option>
                <option value="09">Setembro</option><option value="10">Outubro</option>
                <option value="11">Novembro</option><option value="12">Dezembro</option>
              </select>
            </div>
            <div class="form-group">
              <label>Ano</label>
              <input v-model="calcAno" type="number" min="2020" max="2099" class="cfg-input" placeholder="2026" />
            </div>
          </div>
          <div v-if="calcErro" class="calc-erro">⚠️ {{ calcErro }}</div>
          <div v-if="calcOk" class="calc-ok">✅ {{ calcOk }}</div>
          <div class="modal-actions">
            <button class="modal-cancel" @click="modalCalc = false" :disabled="calculando">Cancelar</button>
            <button class="modal-submit" :disabled="calculando" @click="calcularFolha">
              <span v-if="calculando" class="btn-spin"></span>
              <template v-else>🚀 Confirmar Cálculo</template>
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>

  <!-- MODAL DETALHES DA FOLHA -->
  <transition name="modal">
    <div v-if="modalDetalhes" class="modal-overlay" @click.self="modalDetalhes = false">
      <div class="modal-card modal-wide">
        <div class="modal-hdr">
          <h3>📋 Detalhes — {{ formatCompetencia(folhaSelecionada?.FOLHA_COMPETENCIA) }}</h3>
          <button class="modal-close" @click="modalDetalhes = false">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="loadingDet" class="det-loading"><div class="spinner"></div><p>Carregando...</p></div>
          <template v-else>
            <div class="det-kpis">
              <div class="det-kpi green"><span>Proventos</span><strong>{{ formatMoney(folhaSelecionada?.total_proventos) }}</strong></div>
              <div class="det-kpi red"><span>Descontos</span><strong>{{ formatMoney(folhaSelecionada?.total_descontos) }}</strong></div>
              <div class="det-kpi dark"><span>Líquido</span><strong>{{ formatMoney(folhaSelecionada?.total_liquido) }}</strong></div>
              <div class="det-kpi blue"><span>Servidores</span><strong>{{ detalhes.length }}</strong></div>
            </div>
            <div class="det-table-wrap">
              <table class="det-table">
                <thead><tr><th>Funcionário</th><th>Proventos</th><th>Descontos</th><th>Líquido</th></tr></thead>
                <tbody>
                  <tr v-for="d in detalhes" :key="d.id">
                    <td>{{ d.nome ?? `ID ${d.funcionario_id}` }}</td>
                    <td class="money green">{{ formatMoney(d.proventos) }}</td>
                    <td class="money red">{{ formatMoney(d.descontos) }}</td>
                    <td class="money dark">{{ formatMoney(d.liquido) }}</td>
                  </tr>
                  <tr v-if="detalhes.length === 0"><td colspan="4" class="empty-td">📭 Nenhum detalhe encontrado</td></tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
      </div>
    </div>
  </transition>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loading = ref(true)
const loaded = ref(false)
const folhas = ref([])
const busca = ref('')
const secretariaSel = ref('')
const secretarias = ref([])
// Modal calcular folha
const modalCalc  = ref(false)
const calcMes    = ref(String(new Date().getMonth() + 1).padStart(2, '0'))
const calcAno    = ref(new Date().getFullYear())
const calculando = ref(false)
const calcErro   = ref('')
const calcOk     = ref('')
// Modal detalhes
const modalDetalhes   = ref(false)
const folhaSelecionada = ref(null)
const detalhes        = ref([])
const loadingDet      = ref(false)

onMounted(async () => {
  try {
    const [rFol, rSec] = await Promise.all([
      api.get('/api/v3/folhas'),
      api.get('/api/v3/secretarias').catch(() => ({ data: { unidades: [] } }))
    ])
    folhas.value = rFol.data.folhas ?? rFol.data
    secretarias.value = rSec.data.unidades ?? []
  } catch (e) {
    // Dados mock se endpoint falhar
    folhas.value = [
      { FOLHA_ID: 1, FOLHA_COMPETENCIA: '022026', FOLHA_SITUACAO: 'F', qtd_funcionarios: 87, total_proventos: 543210, total_descontos: 98432, total_liquido: 444778 },
      { FOLHA_ID: 2, FOLHA_COMPETENCIA: '012026', FOLHA_SITUACAO: 'F', qtd_funcionarios: 85, total_proventos: 531000, total_descontos: 96000, total_liquido: 435000 },
      { FOLHA_ID: 3, FOLHA_COMPETENCIA: '122025', FOLHA_SITUACAO: 'F', qtd_funcionarios: 84, total_proventos: 519800, total_descontos: 94100, total_liquido: 425700 },
      { FOLHA_ID: 4, FOLHA_COMPETENCIA: '112025', FOLHA_SITUACAO: 'F', qtd_funcionarios: 83, total_proventos: 508400, total_descontos: 93000, total_liquido: 415400 },
      { FOLHA_ID: 5, FOLHA_COMPETENCIA: '102025', FOLHA_SITUACAO: 'F', qtd_funcionarios: 82, total_proventos: 497000, total_descontos: 91000, total_liquido: 406000 },
      { FOLHA_ID: 6, FOLHA_COMPETENCIA: '092025', FOLHA_SITUACAO: 'F', qtd_funcionarios: 80, total_proventos: 484000, total_descontos: 89000, total_liquido: 395000 },
    ]
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const filtrarPorSecretaria = async () => {
  if (!secretariaSel.value) {
    try { const { data } = await api.get('/api/v3/folhas'); folhas.value = data.folhas ?? data } catch { /* mantém */ }
    return
  }
  try {
    const { data } = await api.get(`/api/v3/folha/por-secretaria?unidade_id=${secretariaSel.value}&competencia=${folhas.value[0]?.FOLHA_COMPETENCIA ?? ''}`)
    folhas.value = data.folhas ?? data
  } catch { /* mantém dados anteriores */ }
}

const ultima = computed(() => folhas.value[0] || null)

const folhasFiltradas = computed(() => {
  if (!busca.value) return folhas.value
  return folhas.value.filter(f => String(f.FOLHA_COMPETENCIA).includes(busca.value.replace(/\D/g, '')))
})

const folhasGrafico = computed(() => {
  const arr = [...folhas.value].slice(0, 6).reverse()
  const max = Math.max(1, ...arr.map(f => f.total_liquido || 0))
  return arr.map(f => ({ ...f, pct: Math.max(10, Math.round(((f.total_liquido || 0) / max) * 100)) }))
})

const formatCompetencia = (c) => {
  if (!c) return '—'
  const s = String(c).padStart(6, '0')
  const m = { '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }
  return `${m[s.substring(0,2)] || s.slice(0,2)} / ${s.substring(2)}`
}
const formatCompetenciaShort = (c) => {
  if (!c) return '—'
  const s = String(c).padStart(6, '0')
  const m = { '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }
  return m[s.substring(0,2)] || s.slice(0,2)
}
const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const formatMoneyShort = (v) => {
  if (!v) return 'R$ 0'
  if (v >= 1000000) return `R$ ${(v / 1000000).toFixed(1)}M`
  if (v >= 1000) return `R$ ${(v / 1000).toFixed(0)}K`
  return `R$ ${v}`
}
const sitLabel = (s) => ({ A: 'Aberta', F: 'Fechada', P: 'Em proc.', C: 'Cancelada' })[s] ?? '—'
const sitClass = (s) => ({ A: 'sit-yellow', F: 'sit-green', P: 'sit-blue', C: 'sit-red' })[s] ?? 'sit-gray'

const calcularFolha = async () => {
  calcErro.value = ''; calcOk.value = ''
  if (!calcMes.value || !calcAno.value) { calcErro.value = 'Informe o mês e o ano.'; return }
  // Envia no formato YYYY-MM que o backend espera
  const competencia = `${calcAno.value}-${String(calcMes.value).padStart(2,'0')}`
  calculando.value = true
  try {
    const { data } = await api.post('/api/v3/folhas/calcular', { competencia })
    calcOk.value = data.mensagem || `Folha calculada! ${data.qtd_funcionarios} servidores`
    setTimeout(async () => {
      modalCalc.value = false
      // Recarrega lista para refletir novos totais
      const resp = await api.get('/api/v3/folhas')
      folhas.value = Array.isArray(resp.data) ? resp.data : (resp.data.folhas ?? [])
    }, 2000)
  } catch (e) {
    calcErro.value = e.response?.data?.erro || 'Erro ao calcular folha.'
  } finally { calculando.value = false }
}

const abrirDetalhes = async (folha) => {
  folhaSelecionada.value = folha
  modalDetalhes.value = true
  loadingDet.value = true
  try {
    const { data } = await api.get(`/api/v3/folhas/${folha.FOLHA_ID}/detalhes`)
    detalhes.value = data.detalhes ?? []
  } catch {
    detalhes.value = []
  } finally {
    loadingDet.value = false
  }
}
</script>

<style scoped>
.folha-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero {
  position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #2c1654 50%, #1e3a5f 100%);
  opacity: 0; transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.13; }
.hs1 { width: 240px; height: 240px; background: #7c3aed; right: -50px; top: -70px; }
.hs2 { width: 180px; height: 180px; background: #10b981; bottom: -50px; right: 250px; }
.hs3 { width: 160px; height: 160px; background: #3b82f6; left: 35%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.kpi-strip { display: flex; gap: 10px; flex-wrap: wrap; }
.kpi-card { padding: 12px 16px; border-radius: 14px; min-width: 90px; text-align: right; border: 1px solid; }
.kpi-label { display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
.kpi-val { display: block; font-size: 16px; font-weight: 900; }
.kpi-card.purple { background: rgba(124,58,237,0.15); border-color: rgba(124,58,237,0.3); color: #c4b5fd; }
.kpi-card.green { background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.25); color: #6ee7b7; }
.kpi-card.red { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.25); color: #fca5a5; }
.kpi-card.blue { background: rgba(59,130,246,0.12); border-color: rgba(59,130,246,0.25); color: #93c5fd; }
.kpi-card.teal { background: rgba(13,148,136,0.12); border-color: rgba(13,148,136,0.25); color: #5eead4; }

/* ESTADOS */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 80px; color: #64748b; }
/* DETALHES MODAL */
.modal-wide { max-width: 760px !important; }
.det-kpis { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.det-kpi { flex: 1; min-width: 110px; padding: 10px 14px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 3px; }
.det-kpi span { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; }
.det-kpi strong { font-size: 15px; font-weight: 900; }
.det-kpi.green strong { color: #15803d; }
.det-kpi.red strong { color: #dc2626; }
.det-kpi.dark strong { color: #1e293b; }
.det-kpi.blue strong { color: #1d4ed8; }
.det-table-wrap { overflow-y: auto; max-height: 320px; border-radius: 10px; border: 1px solid #f1f5f9; }
.det-table { width: 100%; border-collapse: collapse; }
.det-table th { padding: 9px 14px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; background: #f8fafc; border-bottom: 1px solid #f1f5f9; text-align: left; }
.det-table td { padding: 9px 14px; font-size: 12px; color: #334155; border-bottom: 1px solid #f8fafc; }
.det-loading { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 30px; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }
.state-box p { font-size: 15px; font-weight: 500; margin: 0; }

/* SECTION CARD */
.section-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px;
  opacity: 0; transform: translateY(10px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.section-card.loaded { opacity: 1; transform: none; }
.section-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.section-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 16px; }
.section-hdr .section-title { margin: 0; }
.toolbar-right { display: flex; gap: 10px; }
.search-wrap { display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; background: #f8fafc; }
.search-ico { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
.search-input { border: none; font-size: 13px; color: #1e293b; outline: none; background: transparent; font-family: inherit; width: 140px; }

/* EVOLUÇÃO */
.evolucao-card {}
.bars-wrap { display: flex; gap: 14px; align-items: flex-end; height: 140px; padding-top: 24px; }
.bar-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.bar-track { width: 100%; flex: 1; background: #f1f5f9; border-radius: 8px; display: flex; flex-direction: column; justify-content: flex-end; overflow: hidden; cursor: pointer; }
.bar-fill { background: linear-gradient(180deg, #7c3aed, #8b5cf6); border-radius: 8px 8px 0 0; min-height: 4px; transition: height 1s cubic-bezier(0.22, 1, 0.36, 1); }
.bar-val { font-size: 10px; font-weight: 800; color: #7c3aed; white-space: nowrap; }
.bar-label { font-size: 11px; font-weight: 700; color: #94a3b8; }

/* TABELA */
.table-scroll { overflow-x: auto; border-radius: 12px; border: 1px solid #f1f5f9; }
.folha-table { width: 100%; border-collapse: collapse; }
.folha-table thead tr { background: #f8fafc; }
.folha-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.f-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.f-row:hover { background: #fafafa; }
.f-row:last-child { border-bottom: none; }
.f-row.row-in td { animation: rowIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) var(--rd) both; }
@keyframes rowIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.folha-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.comp-chip { display: inline-block; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 10px; padding: 4px 12px; font-size: 13px; font-weight: 800; white-space: nowrap; }
.num-big { font-size: 16px; font-weight: 800; color: #1e293b; }
.money { font-family: monospace; font-weight: 700; font-size: 13px; }
.money.green { color: #15803d; }
.money.red { color: #dc2626; }
.money.dark { color: #1e293b; font-size: 14px; font-weight: 900; }
.sit-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.sit-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.sit-green { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.sit-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.sit-blue { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.sit-red { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.sit-gray { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.row-actions { display: flex; gap: 6px; }
.act-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; transition: all 0.15s; text-decoration: none; color: #64748b; }
.act-btn:hover { transform: translateY(-1px); }
.act-btn.act-blue:hover { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.act-btn.act-green:hover { background: #f0fdf4; border-color: #86efac; color: #15803d; }
.empty-td { text-align: center; font-size: 14px; color: #94a3b8; padding: 40px !important; display: flex; align-items: center; justify-content: center; gap: 8px; }
.sec-select { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; background: #f8fafc; color: #334155; font-family: inherit; cursor: pointer; }
.sec-chip { display: inline-block; background: #f0fdf4; color: #166534; border: 1px solid #86efac; border-radius: 8px; padding: 2px 8px; font-size: 12px; font-weight: 600; }
/* Calcular Folha */
.calc-btn { padding: 9px 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.18s; }
.calc-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.4); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.calc-hint { font-size: 13px; color: #64748b; margin: 0; }
.cal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #7c3aed; }
.calc-erro { font-size: 13px; font-weight: 600; color: #dc2626; }
.calc-ok   { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
