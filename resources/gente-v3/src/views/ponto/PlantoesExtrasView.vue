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
      <div v-if="dadosIndisponiveis" class="state-warn">
        ⚠️ Não foi possível carregar plantões agora. Tentando reconectar com o servidor.
      </div>
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
              <span class="pcb-ico">🏥</span><span class="pcb-val">{{ p.setor || '—' }}</span>
            </div>
            <div class="pcb-row">
              <span class="pcb-ico">⏰</span><span class="pcb-val">{{ textoPeriodo(p) }}</span>
            </div>
            <div v-if="p.valor" class="pcb-row">
              <span class="pcb-ico">💰</span><span class="pcb-val" style="font-weight:800;color:#059669">R$ {{ fmtMoeda(p.valor) }}</span>
            </div>
          </div>
          <div class="pc-footer">
            <span class="pcf-tipo" :class="p.tipo === 'urgencia' ? 'pt-red' : 'pt-blue'">{{ p.tipo === 'urgencia' ? '🚨 Urgência' : '📅 Programado' }}</span>
            <span class="pcf-pag" :class="rodapePagClass(p)">{{ rodapePagTexto(p) }}</span>
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
      <div class="hist-panel">
        <div class="hist-top">
          <div class="hist-kpi">
            <span class="hk-label">Total de Solicitações</span>
            <b class="hk-value">{{ historicoResumo.totalSolicitacoes }}</b>
          </div>
          <div class="hist-kpi">
            <span class="hk-label">Aprovadas/Pagas</span>
            <b class="hk-value">{{ historicoResumo.totalAprovadas }}</b>
          </div>
          <div class="hist-kpi">
            <span class="hk-label">Horas no Histórico</span>
            <b class="hk-value">{{ formatHoras(historicoResumo.totalHoras) }}h</b>
          </div>
        </div>

        <div class="hist-table-wrap">
          <table class="hist-table" v-if="historicoLista.length">
            <thead>
              <tr>
                <th>Data</th>
                <th>Setor</th>
                <th>Período</th>
                <th>Horas</th>
                <th>Status Plantão</th>
                <th>Status Hora Extra</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in historicoLista" :key="`hist-${p.id}`">
                <td>{{ formatDataBR(p.data) }}</td>
                <td>{{ p.setor || '—' }}</td>
                <td>{{ p.horaIni || '—' }} → {{ p.horaFim || '—' }}</td>
                <td>{{ formatHoras(p.duracaoH) }}h</td>
                <td><span class="hist-chip" :class="`hc-${p.status}`">{{ statusLabel(p.status) }}</span></td>
                <td>
                  <span v-if="p.horaExtraStatus" class="hist-chip hc-he">
                    {{ statusHoraExtraLabel(p.horaExtraStatus) }}
                  </span>
                  <span v-else class="hist-chip hc-none">Sem vínculo</span>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty-state">
            <span class="empty-ico">📊</span>
            <p>Nenhum histórico disponível</p>
          </div>
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
const dadosIndisponiveis = ref(false)
const novoP    = reactive({ data: '', tipo: 'programado', horaIni: '', horaFim: '', setor: '', justificativa: '' })
const plantoes = ref([])

// Mapeamento de colunas do backend para o formato esperado pelo frontend
const normalizarStatus = (s) => {
  const raw = String(s ?? '').toLowerCase()
  if (raw.includes('aprovad')) return 'aprovada'
  if (raw.includes('reprov') || raw.includes('rejeit')) return 'rejeitada'
  if (raw.includes('pago') || raw === 'paga') return 'paga'
  if (raw.includes('inclu') && (raw.includes('folha') || raw.includes('folh'))) return 'incluida_folha'
  if (raw.includes('pend')) return 'pendente'
  return 'pendente'
}

const mapearPlantao = (p) => {
  const v = p.PLANTAO_VALOR ?? p.VALOR_CALCULADO ?? p.valor
  const valorNum = v !== undefined && v !== null && v !== '' ? Number(v) : null
  const st = normalizarStatus(p.PLANTAO_STATUS ?? p.STATUS ?? p.status ?? 'pendente')
  const he = String(p.HORA_EXTRA_STATUS ?? p.hora_extra_status ?? '').toUpperCase()
  const pagoFolha = he === 'PAGA' || he === 'INCLUIDA_FOLHA' || (st === 'paga' || st === 'incluida_folha')
  return {
    id:       p.PLANTAO_ID     ?? p.PLANTAO_EXTRA_ID ?? p.id,
    data:     p.PLANTAO_DATA   ?? p.DATA_PLANTAO ?? p.data,
    setor:    p.PLANTAO_SETOR  ?? p.setor  ?? null,
    horaIni:  p.PLANTAO_HORA_INI ?? p.HORA_INICIO ?? p.horaIni ?? null,
    horaFim:  p.PLANTAO_HORA_FIM ?? p.HORA_FIM ?? p.horaFim ?? null,
    duracaoH: Number(p.PLANTAO_DURACAO  ?? p.PLANTAO_HORAS ?? p.TOTAL_HORAS ?? p.duracaoH ?? 0),
    valor:    Number.isFinite(valorNum) ? valorNum : null,
    tipo:     (p.PLANTAO_TIPO  ?? p.tipo  ?? 'programado').toLowerCase(),
    status:   st,
    horaExtraId: p.HORA_EXTRA_ID ?? p.hora_extra_id ?? null,
    horaExtraStatus: p.HORA_EXTRA_STATUS ?? p.hora_extra_status ?? null,
    pago:     pagoFolha,
  }
}

onMounted(async () => {
  loading.value = true
  dadosIndisponiveis.value = false
  try {
    const { data } = await api.get('/api/v3/plantoes-extras')
    plantoes.value = Array.isArray(data?.plantoes) ? data.plantoes.map(mapearPlantao) : []
  } catch {
    plantoes.value = []
    dadosIndisponiveis.value = true
    showToast('⚠️ Não foi possível carregar plantões agora.')
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
})

// KPIs: competência do mês corrente; horas/valor só de plantões aprovados (teia com HORA_EXTRA.VALOR_CALCULADO)
const mesAtual = new Date().toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' })
const prefixoMes = (() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
})()
const kpisComputados = computed(() => {
  const noMes = plantoes.value.filter((p) => p.data && String(p.data).slice(0, 7) === prefixoMes)
  const aprovados = noMes.filter((p) => ['aprovada', 'paga', 'incluida_folha'].includes(p.status))
  const horas = aprovados.reduce((a, p) => a + (Number(p.duracaoH) || 0), 0)
  const valor = aprovados.reduce((a, p) => a + (Number(p.valor) || 0), 0)
  return [
    { ico: '📅', label: mesAtual, val: `${noMes.length} no mês`, cor: '#3b82f6' },
    { ico: '⏱️', label: 'Horas aprovadas', val: `${horas.toFixed(2).replace('.', ',')}h`, cor: '#f59e0b' },
    { ico: '💰', label: 'Valor (aprov. mês)', val: valor > 0 ? `R$ ${fmtMoeda(valor)}` : (aprovados.length ? 'R$ 0,00' : '—'), cor: '#10b981' },
  ]
})

const historicoLista = computed(() => [...plantoes.value]
  .sort((a, b) => String(b.data || '').localeCompare(String(a.data || ''))))

const historicoResumo = computed(() => {
  const totalSolicitacoes = historicoLista.value.length
  const totalAprovadas = historicoLista.value.filter((p) => ['aprovada', 'paga', 'incluida_folha'].includes(p.status)).length
  const totalHoras = historicoLista.value.reduce((acc, p) => acc + (Number(p.duracaoH) || 0), 0)
  return { totalSolicitacoes, totalAprovadas, totalHoras }
})

const tabs = computed(() => [
  { id: 'meus',      ico: '📋', nome: 'Meus Plantões', count: plantoes.value.filter(p => p.status === 'pendente').length },
  { id: 'solicitar', ico: '➕', nome: 'Solicitar',      count: 0 },
  { id: 'historico', ico: '📊', nome: 'Histórico',      count: 0 },
])

const novoPValido = computed(() => novoP.data && novoP.setor && novoP.horaIni && novoP.horaFim)

const statusCor   = (s) => ({ aprovada: '#10b981', pendente: '#f59e0b', rejeitada: '#ef4444', paga: '#0ea5e9', incluida_folha: '#6366f1' })[s] ?? '#64748b'
const statusLabel = (s) => ({ aprovada: '✅ Aprovada', pendente: '⏳ Pendente', rejeitada: '❌ Rejeitada', paga: '💸 Paga', incluida_folha: '🧾 Em Folha' })[s] ?? s
const fmtMoeda    = v  => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(v)

const textoPeriodo = (p) => {
  const a = p.horaIni ? String(p.horaIni).trim() : ''
  const b = p.horaFim ? String(p.horaFim).trim() : ''
  if (a && b) {
    return `${a} → ${b} (${formatHoras(p.duracaoH)}h)`
  }
  if (p.duracaoH != null && Number(p.duracaoH) > 0) {
    return `Duração: ${formatHoras(p.duracaoH)}h`
  }
  return '—'
}
const rodapePagTexto = (p) => {
  if (p.pago) return '✓ Pago / Folha'
  const he = String(p.horaExtraStatus || '').toUpperCase()
  if (he === 'PENDENTE' || he === '') return p.status === 'pendente' ? 'Hora extra: análise' : 'Folha pendente'
  if (he === 'APROVADA') return 'Hora extra OK'
  if (he === 'REJEITADA') return 'Hora extra negada'
  return 'Aguardando'
}
const rodapePagClass = (p) => (p.pago || String(p.horaExtraStatus || '').toUpperCase() === 'APROVADA' ? 'pp-green' : 'pp-gray')
const formatHoras = (v) => Number(v || 0).toFixed(2).replace('.', ',')
const formatDataBR = (d) => d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR') : '—'
const statusHoraExtraLabel = (s) => {
  const raw = String(s || '').toUpperCase()
  if (raw === 'APROVADA') return 'Hora Extra: Aprovada'
  if (raw === 'REJEITADA') return 'Hora Extra: Rejeitada'
  if (raw === 'PENDENTE') return 'Hora Extra: Pendente'
  if (raw === 'PAGA') return 'Hora Extra: Paga'
  if (raw === 'INCLUIDA_FOLHA') return 'Hora Extra: Em Folha'
  return `Hora Extra: ${raw || '—'}`
}

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 4000) }

const solicitarPlantao = async () => {
  enviando.value = true
  try {
    await api.post('/api/v3/plantoes-extras', { ...novoP })
    const { data: newData } = await api.get('/api/v3/plantoes-extras')
    plantoes.value = Array.isArray(newData?.plantoes) ? newData.plantoes.map(mapearPlantao) : []
    showToast('✅ Solicitação enviada ao coordenador para aprovação!')
  } catch {
    dadosIndisponiveis.value = true
    showToast('❌ Falha ao enviar solicitação. Tente novamente.')
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
.ks-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; line-height: 1.25; max-width: 11rem; white-space: normal; }
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
.state-warn {
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
}
.plantoes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
.plantao-card {
  background: linear-gradient(130deg, rgba(226, 240, 252, 0.38), rgba(214, 247, 242, 0.22)), #fff;
  border: 1px solid #c7deef;
  border-radius: 18px;
  overflow: hidden;
  border-left: 4px solid var(--pcc);
  animation: pcIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--pci) * 60ms) both;
}
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
.pcf-pag { padding: 3px 9px; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; }
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
.hist-panel { background: #fff; border: 1px solid #d9e6f2; border-radius: 20px; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
.hist-top { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; }
.hist-kpi { border: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fbff, #f9fffd); border-radius: 12px; padding: 10px; }
.hk-label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.hk-value { font-size: 20px; color: #0f172a; }
.hist-table-wrap { border: 1px solid #d9e6f2; border-radius: 14px; overflow: auto; background: #fcfeff; }
.hist-table { width: 100%; border-collapse: collapse; min-width: 860px; }
.hist-table thead tr { background: linear-gradient(135deg, rgba(226,240,252,.5), rgba(214,247,242,.38)); }
.hist-table th { text-align: left; font-size: 11px; color: #475569; text-transform: uppercase; letter-spacing: .05em; padding: 10px; border-bottom: 1px solid #d9e6f2; }
.hist-table td { font-size: 13px; color: #1e293b; padding: 10px; border-bottom: 1px solid #eef4f9; }
.hist-table tbody tr:hover { background: #f8fbff; }
.hist-chip { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; border: 1px solid transparent; }
.hc-aprovada { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.hc-pendente { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.hc-rejeitada { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.hc-paga { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.hc-incluida_folha { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
.hc-he { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
.hc-none { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }

@media (max-width: 768px) {
  .hero-inner { flex-wrap: wrap; }
  .hero-title { font-size: 22px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .two-col, .form-two-col, .config-grid { grid-template-columns: 1fr !important; }
  .hist-top { grid-template-columns: 1fr !important; }
  .table-scroll, .table-wrap { overflow-x: auto; }
  table { min-width: 500px; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 18px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .hide-mobile { display: none !important; }
}
</style>

