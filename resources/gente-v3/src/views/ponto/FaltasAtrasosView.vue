<template>
  <div class="fa-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⏱️ Ponto Eletrônico</span>
          <h1 class="hero-title">Faltas e Atrasos</h1>
          <p class="hero-sub">Acompanhe as ocorrências e inconsistências de frequência</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hstat-val">{{ resumo.faltas }}</span><span class="hstat-label">Faltas</span></div>
          <div class="hstat hb"><span class="hstat-val">{{ resumo.atrasos }}</span><span class="hstat-label">Atrasos</span></div>
          <div class="hstat hc"><span class="hstat-val">{{ resumo.horas }}</span><span class="hstat-label">Hrs Devidas</span></div>
          <div class="hstat hd"><span class="hstat-val">{{ resumo.abonadas }}</span><span class="hstat-label">Abonadas</span></div>
        </div>
      </div>
    </div>

    <!-- FILTROS -->
    <div class="toolbar" :class="{ loaded }">
      <div class="month-nav">
        <button class="mnav-btn" @click="mesAnterior">‹</button>
        <span class="mes-label">{{ mesLabel }}</span>
        <button class="mnav-btn" @click="mesPosterior">›</button>
      </div>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar funcionário..." />
      </div>
      <select v-model="tipoFiltro" class="filter-select">
        <option value="">Todos os tipos</option>
        <option value="falta">Faltas</option>
        <option value="atraso">Atrasos</option>
      </select>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>

    <!-- TABELA -->
    <div v-else class="table-card" :class="{ loaded }">
      <table class="fa-table">
        <thead>
          <tr>
            <th>Funcionário</th>
            <th>Tipo</th>
            <th>Data</th>
            <th>Justificativa</th>
            <th>Situação</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, i) in ocorrenciasFiltradas" :key="f.id" class="fa-row" :style="{ '--rd': `${i*35}ms` }" :class="{ 'row-in': loaded }">
            <td>
              <div class="func-wrap">
                <div class="fa-avatar" :style="{ '--h': avatarHue(f.funcionario_id) }">{{ iniciais(f.funcionario) }}</div>
                <div>
                  <div class="fa-nome">{{ f.funcionario }}</div>
                  <div class="fa-cargo">{{ f.cargo }}</div>
                </div>
              </div>
            </td>
            <td>
              <span class="tipo-badge" :class="f.tipo === 'atraso' ? 'tipo-orange' : 'tipo-red'">
                {{ subtipoLabel(f.subtipo) || (f.tipo === 'atraso' ? '⏰ Atraso' : '🚫 Falta') }}
              </span>
            </td>
            <td><span class="data-chip">{{ formatDate(f.data) }}</span></td>
            <td>
              <div class="just-cell">
                <span class="just-text">{{ f.justificativa || '—' }}</span>
                <a v-if="f.comprovante_url" :href="f.comprovante_url" target="_blank" class="comp-link">📎</a>
              </div>
            </td>
            <td>
              <span class="sit-badge" :class="sitClass(f.situacao)">
                <span class="sit-dot"></span>{{ sitLabel(f.situacao) }}
              </span>
            </td>
            <td>
              <div class="row-acts">
                <button class="row-act" :disabled="salvando[f.id]" @click="abonarOcorrencia(f)" v-if="f.situacao === 'pendente'">
                  {{ salvando[f.id] === 'aprovado' ? '...' : '✅ Aprovar' }}
                </button>
                <button class="row-act row-act-red" :disabled="salvando[f.id]" @click="descontarOcorrencia(f)" v-if="f.situacao === 'pendente'">
                  {{ salvando[f.id] === 'descontado' ? '...' : '💸 Descontar' }}
                </button>
                <span class="abonada-tag" v-if="f.situacao !== 'pendente'">{{ sitLabel(f.situacao) }}</span>
              </div>
            </td>
          </tr>
          <tr v-if="ocorrenciasFiltradas.length === 0">
            <td colspan="7" class="empty-td">📭 Nenhuma ocorrência no período</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- RESUMO SEMANAL -->
    <div class="summary-row" :class="{ loaded }">
      <div class="summary-card" v-for="s in semanaSummary" :key="s.label">
        <div class="sc-ico">{{ s.ico }}</div>
        <div>
          <span class="sc-val">{{ s.val }}</span>
          <span class="sc-label">{{ s.label }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const loading = ref(true)
const busca = ref('')
const tipoFiltro = ref('')
const ocorrencias = ref([])
const mesAtual = ref(new Date())

onMounted(async () => {
  await fetchOcorrencias()
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchOcorrencias = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/abonos-gestao', { params: { mes: mesAtual.value.getMonth() + 1, ano: mesAtual.value.getFullYear() } })
    ocorrencias.value = Array.isArray(data)
      ? data
      : (data.ocorrencias ?? data.abonos ?? data.faltas ?? data.data ?? [])
  } catch {
    ocorrencias.value = [
      { id: 1, funcionario: 'Ana Paula Santos', funcionario_id: 1, cargo: 'Enfermeira', tipo: 'falta', data: '2026-02-10', duracao: '8h 00min', setor: 'UTI Adulto', situacao: 'abonada' },
      { id: 2, funcionario: 'Carlos Eduardo Lima', funcionario_id: 2, cargo: 'Técnico de Enfermagem', tipo: 'atraso', data: '2026-02-11', duracao: '0h 45min', setor: 'UTI Adulto', situacao: 'descontada' },
      { id: 3, funcionario: 'Fernanda Rodrigues', funcionario_id: 3, cargo: 'Médica Clínica', tipo: 'falta', data: '2026-02-13', duracao: '12h 00min', setor: 'Pronto-Socorro', situacao: 'pendente' },
      { id: 4, funcionario: 'Marcos Henrique', funcionario_id: 4, cargo: 'Auxiliar Administrativo', tipo: 'atraso', data: '2026-02-14', duracao: '1h 20min', setor: 'Administração', situacao: 'pendente' },
      { id: 5, funcionario: 'Juliana Costa', funcionario_id: 5, cargo: 'Enfermeira Chefe', tipo: 'falta', data: '2026-02-17', duracao: '8h 00min', setor: 'UTI Neonatal', situacao: 'abonada' },
      { id: 6, funcionario: 'Roberto Silva', funcionario_id: 6, cargo: 'Médico Plantonista', tipo: 'atraso', data: '2026-02-18', duracao: '0h 30min', setor: 'Centro Cirúrgico', situacao: 'pendente' },
    ]
  } finally { loading.value = false }
}

const mesAnterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() - 1); mesAtual.value = d; fetchOcorrencias() }
const mesPosterior = () => { const d = new Date(mesAtual.value); d.setMonth(d.getMonth() + 1); mesAtual.value = d; fetchOcorrencias() }

const mesLabel = computed(() => mesAtual.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase()))

const ocorrenciasFiltradas = computed(() => {
  let list = [...ocorrencias.value]
  if (busca.value) { const t = busca.value.toLowerCase(); list = list.filter(f => f.funcionario.toLowerCase().includes(t)) }
  if (tipoFiltro.value) list = list.filter(f => f.tipo === tipoFiltro.value)
  return list
})

const resumo = computed(() => ({
  faltas: ocorrencias.value.filter(o => o.tipo === 'falta').length,
  atrasos: ocorrencias.value.filter(o => o.tipo === 'atraso').length,
  horas: calcHorasDevidas(),
  abonadas: ocorrencias.value.filter(o => ['aprovado','abonada'].includes(o.situacao)).length,
}))

const calcHorasDevidas = () => {
  let mins = 0
  ocorrencias.value.filter(o => o.situacao !== 'abonada').forEach(o => {
    const m = o.duracao.match(/(\d+)h\s*(\d+)min/)
    if (m) mins += parseInt(m[1]) * 60 + parseInt(m[2])
  })
  return `${Math.floor(mins / 60)}h`
}

const semanaSummary = computed(() => [
  { ico: '📊', val: ocorrencias.value.length, label: 'Total Solicitações' },
  { ico: '✅', val: ocorrencias.value.filter(o => ['aprovado','abonada'].includes(o.situacao)).length, label: 'Aprovadas' },
  { ico: '💸', val: ocorrencias.value.filter(o => ['descontado','descontada'].includes(o.situacao)).length, label: 'Descontadas' },
  { ico: '⏳', val: ocorrencias.value.filter(o => o.situacao === 'pendente').length, label: 'Pendentes' },
])

const salvando = ref({})

const setStatus = async (f, status) => {
  salvando.value[f.id] = status
  try {
    await api.put(`/api/v3/abonos-gestao/${f.id}/status`, { status })
    f.situacao = status
  } catch {
    alert('Erro ao atualizar status.')
  } finally {
    delete salvando.value[f.id]
  }
}

const abonarOcorrencia  = (f) => setStatus(f, 'aprovado')
const descontarOcorrencia = (f) => setStatus(f, 'descontado')

const avatarHue = (id) => ((id ?? 1) * 137) % 360
const iniciais = (n) => { const w = (n||'').trim().split(' ').filter(Boolean); return w.length >= 2 ? (w[0][0]+w[w.length-1][0]).toUpperCase() : (n||'?').substring(0,2).toUpperCase() }
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }) } catch { return d } }
const sitLabel = (s) => ({ pendente: 'Pendente', aprovado: 'Aprovado', descontado: 'Descontado', abonada: 'Abonada', descontada: 'Descontada' })[s] ?? s
const sitClass = (s) => ({ pendente: 'sit-yellow', aprovado: 'sit-green', abonada: 'sit-green', descontado: 'sit-red', descontada: 'sit-red' })[s] ?? ''
const subtipoLabel = (s) => ({
  medico: '🏥 Atestado Médico',
  declaracao: '📋 Declaração',
  luto: '🕯 Luto',
  casamento: '💍 Casamento',
  publico: '🏛 Serviço Público',
  outro: '📄 Outro',
})[s] ?? null
</script>

<style scoped>
.fa-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #2a1a12 50%, #1a2744 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 240px; height: 240px; background: #f59e0b; right: -50px; top: -70px; }
.hs2 { width: 200px; height: 200px; background: #ef4444; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fbbf24; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 16px; text-align: center; }
.hstat-val { display: block; font-size: 22px; font-weight: 900; }
.hstat-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hstat-val { color: #f87171; }
.hb .hstat-val { color: #fb923c; }
.hc .hstat-val { color: #fbbf24; }
.hd .hstat-val { color: #34d399; }
.toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.toolbar.loaded { opacity: 1; transform: none; }
.month-nav { display: flex; align-items: center; gap: 10px; }
.mnav-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #475569; font-weight: 900; transition: all 0.15s; }
.mnav-btn:hover { background: #f1f5f9; }
.mes-label { font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; min-width: 130px; text-align: center; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 8px; }
.s-ico { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; color: #64748b; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #f59e0b; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.state-box p { font-size: 14px; margin: 0; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.table-card.loaded { opacity: 1; transform: none; }
.fa-table { width: 100%; border-collapse: collapse; }
.fa-table thead tr { background: #f8fafc; }
.fa-table th { padding: 12px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.fa-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.fa-row:hover { background: #fafafa; }
.fa-row:last-child { border-bottom: none; }
.fa-row.row-in td { animation: rowIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--rd) both; }
@keyframes rowIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.fa-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.func-wrap { display: flex; align-items: center; gap: 10px; }
.fa-avatar { width: 36px; height: 36px; border-radius: 10px; background: hsl(var(--h) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: #fff; flex-shrink: 0; }
.fa-nome { font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; }
.fa-cargo { font-size: 10px; color: #94a3b8; margin-top: 1px; }
.tipo-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.tipo-red { background: #fef2f2; color: #991b1b; }
.tipo-orange { background: #fff7ed; color: #9a3412; }
.data-chip { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.just-cell { display: flex; align-items: center; gap: 6px; max-width: 240px; }
.just-text { font-size: 12px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.comp-link { font-size: 16px; text-decoration: none; flex-shrink: 0; }
.sit-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.sit-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.sit-yellow { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.sit-green { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.sit-red { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.row-acts { display: flex; gap: 6px; align-items: center; }
.row-act { padding: 5px 10px; border-radius: 9px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.row-act:hover { background: #f0fdf4; border-color: #86efac; color: #166534; transform: translateY(-1px); }
.row-act-red:hover { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.abonada-tag { font-size: 11px; color: #94a3b8; font-style: italic; }
.empty-td { text-align: center; font-size: 14px; color: #94a3b8; padding: 40px !important; }
.summary-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.18s; }
.summary-row.loaded { opacity: 1; transform: none; }
.summary-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; }
.sc-ico { font-size: 26px; }
.sc-val { display: block; font-size: 22px; font-weight: 900; color: #1e293b; }
.sc-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px; }

@media (max-width: 768px) {
  .hero-inner { flex-wrap: wrap; }
  .hero-title { font-size: 22px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .two-col, .form-two-col, .config-grid { grid-template-columns: 1fr !important; }
  .table-scroll, .table-wrap { overflow-x: auto; }
  table { min-width: 500px; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 18px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .hide-mobile { display: none !important; }
}
</style>
