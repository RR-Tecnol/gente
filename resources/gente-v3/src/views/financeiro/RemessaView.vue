<template>
  <div class="remessa-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2"></div>
        <div class="hs hs3"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏦 Financeiro</span>
          <h1 class="hero-title">Remessa Bancária</h1>
          <p class="hero-sub">Geração de arquivos CNAB 240 para crédito de salários</p>
        </div>
        <div class="hero-right">
          <div class="spec-chip">
            <span class="chip-ico">📄</span>
            <span>CNAB 240 Febraban</span>
          </div>
          <div class="spec-chip">
            <span class="chip-ico">🔒</span>
            <span>Layout completo: Seg. A + B</span>
          </div>
        </div>
      </div>
    </div>

    <!-- BANCOS SUPORTADOS ───────────────────────────────────── -->
    <div class="bancos-bar" :class="{ loaded }">
      <span class="bancos-label">Bancos suportados:</span>
      <div
        v-for="b in bancos"
        :key="b.codigo"
        class="banco-chip"
        :class="{ active: bancoSelecionado === b.codigo }"
        @click="bancoSelecionado = b.codigo"
      >
        <span class="banco-ico">{{ b.ico }}</span>
        <span class="banco-nome">{{ b.nome }}</span>
        <span class="banco-cod">{{ b.codigo }}</span>
      </div>
    </div>

    <!-- TOOLBAR ─────────────────────────────────────────────── -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="search-input" placeholder="Filtrar por competência..." />
      </div>
      <select v-model="statusFiltro" class="filter-select">
        <option value="">Todas as situações</option>
        <option value="aberta">Abertas</option>
        <option value="fechada">Fechadas</option>
      </select>
      <span class="result-count">{{ folhasFiltradas.length }} folha{{ folhasFiltradas.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- LOADING ─────────────────────────────────────────────── -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Carregando folhas de pagamento...</p>
    </div>

    <!-- ERRO ────────────────────────────────────────────────── -->
    <div v-else-if="erro" class="state-box error">
      <span class="state-ico">⚠️</span>
      <p>{{ erro }}</p>
      <button class="retry-btn" @click="fetchFolhas">Tentar novamente</button>
    </div>

    <!-- VAZIO ───────────────────────────────────────────────── -->
    <div v-else-if="folhasFiltradas.length === 0" class="state-box">
      <span class="state-ico">📭</span>
      <p>Nenhuma folha encontrada</p>
    </div>

    <!-- TABELA ──────────────────────────────────────────────── -->
    <div v-else class="table-card" :class="{ loaded }">
      <table class="folha-table">
        <thead>
          <tr>
            <th>Competência</th>
            <th>Funcionários</th>
            <th>Total Proventos</th>
            <th>Total Descontos</th>
            <th>Total Líquido</th>
            <th>Situação</th>
            <th>Gerar CNAB 240</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(f, i) in folhasFiltradas"
            :key="f.FOLHA_ID"
            class="folha-row"
            :class="{ 'row-visible': loaded }"
            :style="{ '--row-delay': `${i * 40}ms` }"
          >
            <td>
              <span class="comp-badge">{{ formatCompetencia(f.FOLHA_COMPETENCIA) }}</span>
            </td>
            <td>
              <div class="num-wrap">
                <span class="num-big">{{ f.qtd_funcionarios ?? '—' }}</span>
                <span class="num-label">servidores</span>
              </div>
            </td>
            <td class="td-money proventos">{{ formatMoney(f.total_proventos) }}</td>
            <td class="td-money descontos">{{ formatMoney(f.total_descontos) }}</td>
            <td class="td-money liquido">{{ formatMoney(f.total_liquido) }}</td>
            <td>
              <span class="status-badge" :class="situacaoClass(f.FOLHA_SITUACAO)">
                <span class="badge-dot"></span>
                {{ situacaoLabel(f.FOLHA_SITUACAO) }}
              </span>
            </td>
            <td>
              <div class="cnab-actions">
                <button
                  v-for="b in bancos"
                  :key="b.codigo"
                  class="cnab-btn"
                  :class="{ 'cnab-primary': b.codigo === bancoSelecionado, 'generating': gerando[`${f.FOLHA_ID}-${b.codigo}`] }"
                  :disabled="!!gerando[`${f.FOLHA_ID}-${b.codigo}`]"
                  :title="`Baixar CNAB 240 – ${b.nome}`"
                  @click="downloadCnab(f, b.codigo)"
                >
                  <div v-if="gerando[`${f.FOLHA_ID}-${b.codigo}`]" class="btn-spinner"></div>
                  <template v-else>
                    <span class="btn-banco-ico">{{ b.ico }}</span>
                    <span>{{ b.codigo }}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 15l-4.5-4.5M12 15l4.5-4.5M12 15V3M3 21h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </template>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TOAST ──────────────────────────────────────────────── -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.type">
        <span>{{ toast.ico }}</span>
        <span>{{ toast.msg }}</span>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loading = ref(true)
const erro = ref('')
const loaded = ref(false)
const folhas = ref([])
const busca = ref('')
const statusFiltro = ref('')
const bancoSelecionado = ref('104')
const gerando = ref({})

const toast = ref({ visible: false, msg: '', type: '', ico: '' })

const bancos = [
  { codigo: '104', nome: 'Caixa', ico: '🏛️' },
  { codigo: '001', nome: 'Banco do Brasil', ico: '🟡' },
  { codigo: '237', nome: 'Bradesco', ico: '🔴' },
  { codigo: '341', nome: 'Itaú', ico: '🟠' },
  { codigo: '033', nome: 'Santander', ico: '🔥' },
]

onMounted(async () => {
  await fetchFolhas()
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchFolhas = async () => {
  loading.value = true
  erro.value = ''
  try {
    const { data } = await api.get('/api/v3/folhas')
    folhas.value = data.folhas ?? data
  } catch (e) {
    erro.value = e.response?.data?.message || 'Falha ao carregar folhas de pagamento.'
  } finally {
    loading.value = false
  }
}

const folhasFiltradas = computed(() => {
  let list = [...folhas.value]
  if (busca.value) {
    const term = busca.value.replace(/\D/g, '')
    list = list.filter(f => String(f.FOLHA_COMPETENCIA).includes(term))
  }
  if (statusFiltro.value) {
    const mapa = { aberta: 'A', fechada: 'F' }
    list = list.filter(f => f.FOLHA_SITUACAO === mapa[statusFiltro.value])
  }
  return list
})

const downloadCnab = async (folha, banco) => {
  const key = `${folha.FOLHA_ID}-${banco}`
  gerando.value[key] = true
  try {
    const response = await api.get(`/remessa/${folha.FOLHA_ID}/download`, {
      params: { banco },
      responseType: 'blob',
    })
    const blob = new Blob([response.data], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `REMESSA_${folha.FOLHA_COMPETENCIA}_B${banco}.txt`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    mostrarToast('success', '✅', `Arquivo CNAB gerado! Competência ${formatCompetencia(folha.FOLHA_COMPETENCIA)}`)
  } catch (e) {
    const msg = e.response?.data?.msg || 'Falha ao gerar arquivo CNAB. Verifique os dados da folha.'
    mostrarToast('error', '❌', msg)
  } finally {
    gerando.value[key] = false
  }
}

const mostrarToast = (type, ico, msg) => {
  toast.value = { visible: true, type, ico, msg }
  setTimeout(() => { toast.value.visible = false }, 4000)
}

// Aceita formatos: AAAAMM (202603), MMAAAA (032026), AAAA-MM (2026-03)
const formatCompetencia = (c) => {
  if (!c) return '—'
  const meses = { '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }
  const s = String(c).replace('-', '')
  if (s.length === 6) {
    // Tenta AAAAMM (ex: 202603 -> Mar / 2026)
    const aaaa = s.substring(0, 4)
    const mm   = s.substring(4, 6)
    if (parseInt(aaaa) > 1900 && meses[mm]) return meses[mm] + ' / ' + aaaa
    // Tenta MMAAAA (ex: 032026 -> Mar / 2026)
    const mm2   = s.substring(0, 2)
    const aaaa2 = s.substring(2, 6)
    if (parseInt(aaaa2) > 1900 && meses[mm2]) return meses[mm2] + ' / ' + aaaa2
  }
  return s || '—'
}

const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)

const situacaoLabel = (s) => ({ A: 'Aberta', F: 'Fechada', P: 'Em Processamento', C: 'Cancelada' }[s] ?? s ?? '—')
const situacaoClass = (s) => ({ A: 'badge-yellow', F: 'badge-green', P: 'badge-blue', C: 'badge-red' }[s] ?? 'badge-gray')
</script>

<style scoped>
/* ── PAGE ──────────────────────────────────────────────────── */
.remessa-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* ── HERO ──────────────────────────────────────────────────── */
.hero {
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1c3050 45%, #1e3a2f 100%);
  border-radius: 22px; padding: 28px 36px; overflow: hidden;
  opacity: 0; transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 260px; height: 260px; background: #10b981; right: -60px; top: -80px; }
.hs2 { width: 200px; height: 200px; background: #3b82f6; right: 240px; bottom: -60px; }
.hs3 { width: 160px; height: 160px; background: #f59e0b; left: 30%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 5px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-right { display: flex; flex-direction: column; gap: 8px; }
.spec-chip { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 9px 14px; font-size: 12px; font-weight: 600; color: #cbd5e1; }
.chip-ico { font-size: 16px; }

/* ── BANCOS BAR ─────────────────────────────────────────────── */
.bancos-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.08s;
}
.bancos-bar.loaded { opacity: 1; transform: none; }
.bancos-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; }
.banco-chip {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 12px; cursor: pointer;
  border: 1px solid #e2e8f0; background: #fff;
  font-size: 12px; font-weight: 700; color: #475569;
  transition: all 0.18s;
}
.banco-chip:hover { border-color: #10b981; color: #065f46; }
.banco-chip.active { border-color: #10b981; background: #f0fdf4; color: #065f46; }
.banco-ico { font-size: 16px; }
.banco-nome { font-weight: 600; }
.banco-cod { font-family: monospace; font-size: 11px; color: #94a3b8; }

/* ── TOOLBAR ───────────────────────────────────────────────── */
.toolbar {
  display: flex; align-items: center; gap: 14px;
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 16px; padding: 12px 18px;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.12s;
}
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 10px; }
.search-ico { width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0; }
.search-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.search-input::placeholder { color: #cbd5e1; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; cursor: pointer; }
.result-count { font-size: 12px; color: #94a3b8; font-weight: 600; white-space: nowrap; }

/* ── ESTADOS ───────────────────────────────────────────────── */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 80px 20px; text-align: center; color: #64748b; }
.state-ico { font-size: 48px; margin-bottom: 14px; }
.state-box p { font-size: 15px; font-weight: 500; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #10b981; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }
.retry-btn { background: #10b981; color: #fff; border: none; border-radius: 10px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 12px; }

/* ── TABLE CARD ────────────────────────────────────────────── */
.table-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden;
  opacity: 0; transform: translateY(12px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.15s;
}
.table-card.loaded { opacity: 1; transform: none; }
.folha-table { width: 100%; border-collapse: collapse; }
.folha-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.folha-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; text-align: left; white-space: nowrap; }
.folha-row { border-bottom: 1px solid #f8fafc; transition: background 0.15s; }
.folha-row:hover { background: #f8fafc; }
.folha-row:last-child { border-bottom: none; }
.folha-row.row-visible td { animation: rowIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) var(--row-delay) both; }
@keyframes rowIn { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: none; } }
.folha-table td { padding: 14px 20px; font-size: 13px; color: #334155; vertical-align: middle; }

.comp-badge { display: inline-block; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 10px; padding: 4px 12px; font-size: 13px; font-weight: 800; white-space: nowrap; }
.num-wrap { display: flex; flex-direction: column; line-height: 1.2; }
.num-big { font-size: 18px; font-weight: 900; color: #1e293b; }
.num-label { font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
.td-money { font-family: monospace; font-size: 14px; font-weight: 700; }
.proventos { color: #15803d; }
.descontos { color: #dc2626; }
.liquido { color: #1e293b; font-size: 15px; font-weight: 900; }

.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-green { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.badge-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.badge-blue { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.badge-red { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-gray { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

/* ── CNAB ACTIONS ───────────────────────────────────────────── */
.cnab-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.cnab-btn {
  display: flex; align-items: center; gap: 4px;
  padding: 6px 10px; border-radius: 10px; cursor: pointer;
  border: 1px solid #e2e8f0; background: #f8fafc;
  font-size: 11px; font-weight: 700; color: #64748b;
  transition: all 0.18s; white-space: nowrap; min-width: 70px; justify-content: center;
}
.cnab-btn:hover:not(:disabled) { border-color: #10b981; color: #065f46; background: #f0fdf4; transform: translateY(-1px); }
.cnab-btn.cnab-primary { border-color: #10b981; background: #10b981; color: #fff; }
.cnab-btn.cnab-primary:hover:not(:disabled) { background: #059669; border-color: #059669; }
.cnab-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.cnab-btn.generating { background: #f0fdf4; border-color: #10b981; }
.btn-banco-ico { font-size: 12px; }
.btn-spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: currentColor; border-radius: 50%; animation: spin 0.7s linear infinite; }

/* ── TOAST ──────────────────────────────────────────────────── */
.toast {
  position: fixed; bottom: 28px; right: 28px; z-index: 200;
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; border-radius: 14px;
  font-size: 14px; font-weight: 600; color: #fff;
  box-shadow: 0 16px 48px rgba(0,0,0,0.2);
}
.toast.success { background: #059669; }
.toast.error { background: #dc2626; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px) scale(0.95); }
</style>
