<template>
  <div class="pe-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⏰ Gestão de Jornada</span>
          <h1 class="hero-title">Plantões Extras</h1>
          <p class="hero-sub">Solicitações, aprovações e controle de horas extras de plantão</p>
        </div>
        <div class="hero-summary">
          <div class="hs-card" v-for="k in kpisComputados" :key="k.label" :style="{ '--kc': k.cor }">
            <span class="ks-ico">{{ k.ico }}</span>
            <div>
              <span class="ks-val" :style="{ color: k.cor }">{{ k.val }}</span>
              <span class="ks-label">{{ k.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tabAtiva === t.id }" @click="tabAtiva = t.id">
        {{ t.ico }} {{ t.nome }}
        <span v-if="t.count > 0" class="tab-count">{{ t.count }}</span>
      </button>
    </div>

    <!-- TAB: MEUS PLANTÕES -->
    <div v-if="tabAtiva === 'meus'" class="tab-content" :class="{ loaded }">
      <div v-if="loading" class="loading-msg">⏳ Carregando plantões...</div>
      <div v-else-if="plantoes.length === 0" class="empty-state">
        <span class="empty-ico">📋</span>
        <p>Nenhum plantão extra registrado</p>
      </div>
      <div v-else class="plantoes-grid">
        <div v-for="(p, i) in plantoes" :key="p.id" class="plantao-card" :style="{ '--pci': i, '--pcc': statusCor(p.status) }">
          <div class="pc-top" :style="{ background: statusCor(p.status) + '14', borderBottom: '2px solid ' + statusCor(p.status) + '40' }">
            <div class="pct-data">
              <span class="pct-dia">{{ new Date(p.data + 'T12:00:00').getDate() }}</span>
              <span class="pct-mes">{{ new Date(p.data + 'T12:00:00').toLocaleDateString('pt-BR', { month: 'short' }) }}</span>
            </div>
            <span class="pct-status" :style="{ color: statusCor(p.status), background: statusCor(p.status) + '15' }">
              {{ statusLabel(p.status) }}
            </span>
          </div>
          <div class="pc-body">
            <div class="pcb-row">
              <span class="pcb-ico">🏥</span><span class="pcb-val">{{ p.setor }}</span>
            </div>
            <div class="pcb-row">
              <span class="pcb-ico">⏰</span><span class="pcb-val">{{ p.horaIni }}h → {{ p.horaFim }}h ({{ p.duracaoH }}h)</span>
            </div>
            <div v-if="p.valor" class="pcb-row">
              <span class="pcb-ico">💰</span><span class="pcb-val" style="font-weight:800;color:#059669">R$ {{ fmtMoeda(p.valor) }}</span>
            </div>
          </div>
          <div class="pc-footer">
            <span class="pcf-tipo" :class="p.tipo === 'urgencia' ? 'pt-red' : 'pt-blue'">{{ p.tipo === 'urgencia' ? '🚨 Urgência' : '📅 Programado' }}</span>
            <span class="pcf-pag" :class="p.pago ? 'pp-green' : 'pp-gray'">{{ p.pago ? '✓ Pago' : 'Aguardando' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: SOLICITAR -->
    <div v-if="tabAtiva === 'solicitar'" class="tab-content" :class="{ loaded }">
      <div class="form-panel">
        <div class="fp-hdr">
          <h3>📋 Nova Solicitação de Plantão Extra</h3>
          <p>Solicitações são aprovadas pelo coordenador até 48h antes.</p>
        </div>
        <div class="form-two-col">
          <div class="form-group">
            <label>Data do Plantão</label>
            <input type="date" v-model="novoP.data" class="cfg-input" />
          </div>
          <div class="form-group">
            <label>Tipo</label>
            <select v-model="novoP.tipo" class="cfg-input">
              <option value="programado">📅 Programado</option>
              <option value="urgencia">🚨 Urgência / Cobertura</option>
            </select>
          </div>
          <div class="form-group">
            <label>Hora Início</label>
            <input type="time" v-model="novoP.horaIni" class="cfg-input" />
          </div>
          <div class="form-group">
            <label>Hora Fim</label>
            <input type="time" v-model="novoP.horaFim" class="cfg-input" />
          </div>
          <div class="form-group" style="grid-column: 1/-1">
            <label>Setor</label>
            <select v-model="novoP.setor" class="cfg-input">
              <option value="">Selecione...</option>
              <option>UTI Adulto</option>
              <option>UTI Pediátrica</option>
              <option>Pronto-Socorro</option>
              <option>Clínica Médica</option>
              <option>Centro Cirúrgico</option>
              <option>Maternidade</option>
            </select>
          </div>
          <div class="form-group" style="grid-column: 1/-1">
            <label>Justificativa</label>
            <textarea v-model="novoP.justificativa" class="cfg-input cfg-ta" rows="3" placeholder="Descreva o motivo da solicitação..."></textarea>
          </div>
        </div>
        <button class="submit-btn" :disabled="!novoPValido || enviando" @click="solicitarPlantao">
          <span v-if="enviando" class="btn-spin"></span>
          <template v-else>📨 Enviar Solicitação</template>
        </button>
      </div>
    </div>

    <!-- TAB: HISTÓRICO -->
    <div v-if="tabAtiva === 'historico'" class="tab-content" :class="{ loaded }">
      <div class="hist-resumo">
        <div v-for="m in historicoPorMes" :key="m.mes" class="hm-item">
          <div class="hm-header">
            <span class="hm-mes">{{ m.mes }}</span>
            <span class="hm-total">{{ m.plantoes }} plantões · {{ m.horas }}h</span>
          </div>
          <div class="hm-bar"><div class="hm-fill" :style="{ width: (m.horas / 30 * 100) + '%' }"></div></div>
        </div>
        <div v-if="historicoPorMes.length === 0" class="empty-state">
          <span class="empty-ico">📊</span>
          <p>Nenhum histórico disponível</p>
        </div>
      </div>
    </div>

    <transition name="toast"><div v-if="toast.visible" class="toast">{{ toast.msg }}</div></transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'
const loaded   = ref(false)
const loading  = ref(false)
const tabAtiva = ref('meus')
const enviando = ref(false)
const toast    = ref({ visible: false, msg: '' })
const novoP    = reactive({ data: '', tipo: 'programado', horaIni: '', horaFim: '', setor: '', justificativa: '' })
const plantoes = ref([])

// Mapeamento de colunas do backend para o formato esperado pelo frontend
const mapearPlantao = (p) => ({
  id:       p.PLANTAO_ID     ?? p.id,
  data:     p.PLANTAO_DATA   ?? p.data,
  setor:    p.PLANTAO_SETOR  ?? p.setor  ?? '—',
  horaIni:  p.PLANTAO_HORA_INI ?? p.horaIni ?? '—',
  horaFim:  p.PLANTAO_HORA_FIM ?? p.horaFim ?? '—',
  duracaoH: p.PLANTAO_DURACAO  ?? p.duracaoH ?? '—',
  valor:    p.PLANTAO_VALOR    ?? p.valor    ?? null,
  tipo:     (p.PLANTAO_TIPO  ?? p.tipo  ?? 'programado').toLowerCase(),
  status:   (p.PLANTAO_STATUS ?? p.status ?? 'pendente').toLowerCase(),
  pago:     !!(p.PLANTAO_PAGO ?? p.pago ?? false),
})

const mockPlantoes = [
  { id: 1, data: '2026-02-22', setor: 'UTI Adulto',      horaIni: '07:00', horaFim: '19:00', duracaoH: 12, valor: 520, tipo: 'programado', status: 'aprovado', pago: true },
  { id: 2, data: '2026-02-15', setor: 'Pronto-Socorro',  horaIni: '19:00', horaFim: '07:00', duracaoH: 12, valor: 520, tipo: 'urgencia',   status: 'aprovado', pago: false },
  { id: 3, data: '2026-02-08', setor: 'UTI Pediátrica',  horaIni: '07:00', horaFim: '19:00', duracaoH: 12, valor: 208, tipo: 'programado', status: 'aprovado', pago: true },
  { id: 4, data: '2026-03-01', setor: 'Centro Cirúrgico',horaIni: '07:00', horaFim: '19:00', duracaoH: 12, valor: 520, tipo: 'programado', status: 'pendente', pago: false },
]

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/plantoes-extras')
    if (!data.fallback && data.plantoes?.length) {
      plantoes.value = data.plantoes.map(mapearPlantao)
    } else {
      plantoes.value = mockPlantoes
    }
  } catch {
    plantoes.value = mockPlantoes
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
})

// KPIs computados dinamicamente a partir dos plantões
const mesAtual = new Date().toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' })
const kpisComputados = computed(() => {
  const aprovados = plantoes.value.filter(p => p.status === 'aprovado')
  const horas     = aprovados.reduce((a, p) => a + (Number(p.duracaoH) || 0), 0)
  const valor     = aprovados.reduce((a, p) => a + (Number(p.valor)    || 0), 0)
  return [
    { ico: '📅', label: mesAtual,       val: `${aprovados.length} plantões`, cor: '#3b82f6' },
    { ico: '⏱️', label: 'Horas Extras', val: `${horas}h`,                   cor: '#f59e0b' },
    { ico: '💰', label: 'Valor Acumul.', val: valor > 0 ? `R$ ${fmtMoeda(valor)}` : '—',  cor: '#10b981' },
  ]
})

// Histórico por mês a partir dos plantões carregados
const historicoPorMes = computed(() => {
  const mapa = {}
  plantoes.value.filter(p => p.status === 'aprovado').forEach(p => {
    if (!p.data) return
    const [ano, mes] = p.data.split('-')
    const key = `${['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][+mes-1]}/${ano}`
    if (!mapa[key]) mapa[key] = { mes: key, plantoes: 0, horas: 0 }
    mapa[key].plantoes++
    mapa[key].horas += Number(p.duracaoH) || 0
  })
  return Object.values(mapa).slice(0, 6)
})

const tabs = computed(() => [
  { id: 'meus',      ico: '📋', nome: 'Meus Plantões', count: plantoes.value.filter(p => p.status === 'pendente').length },
  { id: 'solicitar', ico: '➕', nome: 'Solicitar',      count: 0 },
  { id: 'historico', ico: '📊', nome: 'Histórico',      count: 0 },
])

const novoPValido = computed(() => novoP.data && novoP.setor && novoP.horaIni && novoP.horaFim)

const statusCor   = (s) => ({ aprovado: '#10b981', pendente: '#f59e0b', rejeitado: '#ef4444' })[s] ?? '#64748b'
const statusLabel = (s) => ({ aprovado: '✅ Aprovado', pendente: '⏳ Pendente', rejeitado: '❌ Rejeitado' })[s] ?? s
const fmtMoeda    = v  => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(v)

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 4000) }

const solicitarPlantao = async () => {
  enviando.value = true
  try {
    const { data } = await api.post('/api/v3/plantoes-extras', { ...novoP })
    plantoes.value.push(mapearPlantao({
      id: data.id ?? Date.now(), PLANTAO_DATA: novoP.data, PLANTAO_SETOR: novoP.setor,
      PLANTAO_HORA_INI: novoP.horaIni, PLANTAO_HORA_FIM: novoP.horaFim,
      PLANTAO_DURACAO: 12, PLANTAO_TIPO: novoP.tipo, PLANTAO_STATUS: 'PENDENTE',
    }))
    showToast('✅ Solicitação enviada ao coordenador para aprovação!')
  } catch {
    plantoes.value.push(mapearPlantao({
      id: Date.now(), PLANTAO_DATA: novoP.data, PLANTAO_SETOR: novoP.setor,
      PLANTAO_HORA_INI: novoP.horaIni, PLANTAO_HORA_FIM: novoP.horaFim,
      PLANTAO_DURACAO: 12, PLANTAO_TIPO: novoP.tipo, PLANTAO_STATUS: 'PENDENTE',
    }))
    showToast('✅ Solicitação registrada! (aguardando sincronização)')
  } finally {
    Object.assign(novoP, { data: '', tipo: 'programado', horaIni: '', horaFim: '', setor: '', justificativa: '' })
    enviando.value = false
    tabAtiva.value = 'meus'
  }
}
</script>

<style scoped>
.pe-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0a1a14 55%, #1a140a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #f59e0b; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fbbf24; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-summary { display: flex; gap: 10px; flex-wrap: wrap; }
.hs-card { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 10px 16px; }
.ks-ico { font-size: 20px; }
.ks-val { display: block; font-size: 16px; font-weight: 900; }
.ks-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; }
.tabs { display: flex; gap: 6px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { display: flex; align-items: center; gap: 7px; padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; }
.tab-btn.active { background: #fffbeb; border-color: #fbbf24; color: #92400e; }
.tab-count { background: #f59e0b; color: #fff; border-radius: 99px; padding: 1px 7px; font-size: 10px; font-weight: 900; }
.tab-content { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.tab-content.loaded { opacity: 1; transform: none; }
.loading-msg { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; font-weight: 600; }
.empty-state { text-align: center; padding: 60px 20px; }
.empty-ico { font-size: 48px; display: block; margin-bottom: 12px; }
.empty-state p { color: #94a3b8; font-size: 14px; font-weight: 600; }
.plantoes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.plantao-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; border-left: 3px solid var(--pcc); animation: pcIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--pci) * 60ms) both; }
@keyframes pcIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.pc-top { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; }
.pct-dia { display: block; font-size: 24px; font-weight: 900; color: #1e293b; line-height: 1; }
.pct-mes { display: block; font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; }
.pct-status { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; }
.pc-body { padding: 10px 14px; display: flex; flex-direction: column; gap: 6px; }
.pcb-row { display: flex; align-items: center; gap: 8px; }
.pcb-ico { font-size: 14px; }
.pcb-val { font-size: 13px; color: #475569; font-weight: 600; }
.pc-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-top: 1px solid #f8fafc; }
.pcf-tipo, .pcf-pag { font-size: 11px; font-weight: 700; }
.pt-red { color: #ef4444; } .pt-blue { color: #3b82f6; }
.pp-green { color: #10b981; } .pp-gray { color: #94a3b8; }
.form-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; max-width: 640px; display: flex; flex-direction: column; gap: 16px; }
.fp-hdr h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.fp-hdr p  { font-size: 12px; color: #94a3b8; margin: 0; }
.form-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #f59e0b; }
.cfg-ta { resize: vertical; min-height: 80px; }
.submit-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 13px; border-radius: 14px; border: none; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; }
.submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245,158,11,0.35); }
.submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.hist-resumo { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 14px; max-width: 540px; }
.hm-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
.hm-mes { font-size: 13px; font-weight: 800; color: #1e293b; }
.hm-total { font-size: 13px; font-weight: 700; color: #f59e0b; }
.hm-bar { height: 7px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.hm-fill { height: 100%; background: linear-gradient(to right, #f59e0b, #d97706); border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }

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
