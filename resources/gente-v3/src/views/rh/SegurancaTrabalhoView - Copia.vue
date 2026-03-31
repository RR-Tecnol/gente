<template>
  <div class="st-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⚠️ SESMT — Saúde e Segurança</span>
          <h1 class="hero-title">Segurança do Trabalho</h1>
          <p class="hero-sub">NRs, EPIs, riscos ocupacionais e incidentes</p>
        </div>
        <div class="hero-gauges">
          <div class="hg" v-for="g in gauges" :key="g.label">
            <svg viewBox="0 0 60 60" class="hg-svg">
              <circle cx="30" cy="30" r="24" fill="none" stroke="#1e2b3b" stroke-width="5"/>
              <circle cx="30" cy="30" r="24" fill="none" :stroke="g.cor" stroke-width="5"
                :stroke-dasharray="g.pct * 1.508 + ' 150.8'"
                stroke-dashoffset="37.7" stroke-linecap="round" style="transition:stroke-dasharray 1s cubic-bezier(0.22,1,0.36,1)"/>
            </svg>
            <div class="hg-info">
              <span class="hg-val" :style="{ color: g.cor }">{{ g.pct }}%</span>
              <span class="hg-label">{{ g.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- NRs APLICÁVEIS -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">📋 Normas Regulamentadoras Aplicáveis</h2>
    </div>
    <div class="nrs-grid" :class="{ loaded }">
      <div v-for="(nr, i) in nrs" :key="nr.id" class="nr-card" :style="{ '--nrd': `${i * 40}ms`, '--nrc': nr.cor }">
        <div class="nr-top">
          <span class="nr-badge">{{ nr.codigo }}</span>
          <span class="nr-status" :class="statusClass(nr.status)">{{ nr.status }}</span>
        </div>
        <p class="nr-nome">{{ nr.nome }}</p>
        <div class="nr-pct-bar">
          <div class="nr-pct-fill" :style="{ width: nr.conformidade + '%', background: nr.cor }"></div>
        </div>
        <span class="nr-pct-txt" :style="{ color: nr.cor }">{{ nr.conformidade }}% em conformidade</span>
        <div class="nr-data">Revisado: {{ formatDate(nr.ultimaRevisao) }}</div>
      </div>
    </div>

    <!-- EPIs -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">🦺 Meus EPIs</h2>
      <button class="solicitar-epi-btn" @click="modalEpi = true">+ Solicitar EPI</button>
    </div>
    <div class="epi-list" :class="{ loaded }">
      <div v-for="(e, i) in epis" :key="e.id" class="epi-item" :style="{ '--eid': `${i * 40}ms` }">
        <div class="epi-ico">{{ e.ico }}</div>
        <div class="epi-info">
          <span class="epi-nome">{{ e.nome }}</span>
          <span class="epi-ca">CA: {{ e.ca }}</span>
        </div>
        <div class="epi-meta">
          <span class="epi-val" v-if="e.validade">Validade: {{ formatDate(e.validade) }}</span>
          <span class="epi-qty">Qtd: {{ e.quantidade }}</span>
        </div>
        <span class="epi-badge" :class="e.vencido ? 'eb-red' : e.aVencer ? 'eb-yellow' : 'eb-green'">
          {{ e.vencido ? '🔴 Vencido' : e.aVencer ? '🟡 A vencer' : '🟢 OK' }}
        </span>
      </div>
    </div>

    <!-- NOTIFICAÇÕES / INCIDENTES -->
    <div class="inc-section" :class="{ loaded }">
      <h2 class="sh-title">🚨 Comunicações de Acidente e Incidentes</h2>
      <div class="inc-timeline">
        <div v-for="(inc, i) in incidentes" :key="inc.id" class="inc-item" :style="{ '--incd': `${i * 60}ms` }">
          <div class="inc-marcador" :class="inc.tipo === 'acidente' ? 'im-red' : 'im-yellow'"></div>
          <div class="inc-card">
            <div class="inc-hdr">
              <span class="inc-tipo-chip" :class="inc.tipo === 'acidente' ? 'ic-red' : 'ic-yellow'">
                {{ inc.tipo === 'acidente' ? '🚨 Acidente' : '⚠️ Quase-Acidente' }}
              </span>
              <span class="inc-data">{{ formatDate(inc.data) }}</span>
              <span class="inc-status" :class="inc.closed ? 'is-green' : 'is-blue'">{{ inc.closed ? 'Encerrado' : 'Em investigação' }}</span>
            </div>
            <p class="inc-desc">{{ inc.descricao }}</p>
            <div class="inc-meta">
              <span>📍 {{ inc.local }}</span>
              <span v-if="inc.cat">📄 CAT nº {{ inc.cat }}</span>
            </div>
          </div>
        </div>
        <button class="notificar-btn" @click="modalInc = true">+ Notificar Incidente / Acidente</button>
      </div>
    </div>

    <!-- MODAL EPI -->
    <transition name="modal">
      <div v-if="modalEpi" class="modal-overlay" @click.self="modalEpi = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>🦺 Solicitar EPI</h3><button class="modal-close" @click="modalEpi = false">✕</button></div>
          <div class="modal-body">
            <div class="form-group"><label>EPI</label>
              <select class="cfg-input" v-model="novoEpi.nome">
                <option value="">Selecione...</option>
                <option v-for="e in epis" :key="e.id">{{ e.nome }}</option>
                <option>Outro</option>
              </select>
            </div>
            <div class="form-group"><label>Justificativa</label><textarea class="cfg-input cfg-ta" rows="3" v-model="novoEpi.obs" placeholder="Motivo da solicitação..."></textarea></div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalEpi = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novoEpi.nome" @click="solicitarEpi">✅ Solicitar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>
    <transition name="modal">
      <div v-if="modalInc" class="modal-overlay" @click.self="modalInc = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>🚨 Notificar Incidente</h3><button class="modal-close" @click="modalInc = false">✕</button></div>
          <div class="modal-body">
            <div class="form-group"><label>Tipo</label>
              <select class="cfg-input" v-model="novoInc.tipo"><option value="quase">Quase-Acidente</option><option value="acidente">Acidente com Lesão</option></select>
            </div>
            <div class="form-group"><label>Local</label><input class="cfg-input" v-model="novoInc.local" placeholder="Ex: UTI Adulto — Bloco C" /></div>
            <div class="form-group"><label>Descrição</label><textarea class="cfg-input cfg-ta" rows="4" v-model="novoInc.descricao" placeholder="Descreva o ocorrido com detalhes..."></textarea></div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalInc = false">Cancelar</button>
              <button class="modal-submit" :disabled="!novoInc.descricao || !novoInc.local" @click="notificarInc">✅ Enviar Notificação</button>
            </div>
          </div>
        </div>
      </div>
    </transition>
    <transition name="toast"><div v-if="toast.visible" class="toast">{{ toast.msg }}</div></transition>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const modalEpi = ref(false)
const modalInc = ref(false)
const toast = ref({ visible: false, msg: '' })
const novoEpi = reactive({ nome: '', obs: '' })
const novoInc = reactive({ tipo: 'quase', local: '', descricao: '' })

const gauges = [
  { label: 'Conformidade NR', pct: 88, cor: '#10b981' },
  { label: 'EPIs Em Dia', pct: 75, cor: '#f59e0b' },
  { label: 'Treinamentos NR', pct: 92, cor: '#3b82f6' },
]

const nrs = [
  { id: 1, codigo: 'NR-6', nome: 'Equipamento de Proteção Individual', conformidade: 88, status: 'Conforme', cor: '#10b981', ultimaRevisao: '2025-12-01' },
  { id: 2, codigo: 'NR-7', nome: 'Programa de Controle Médico de Saúde Ocupacional (PCMSO)', conformidade: 95, status: 'Conforme', cor: '#10b981', ultimaRevisao: '2025-11-15' },
  { id: 3, codigo: 'NR-9', nome: 'Avaliação e Controle das Exposições Ocupacionais', conformidade: 82, status: 'Atenção', cor: '#f59e0b', ultimaRevisao: '2025-10-05' },
  { id: 4, codigo: 'NR-15', nome: 'Atividades e Operações Insalubres', conformidade: 91, status: 'Conforme', cor: '#10b981', ultimaRevisao: '2025-09-20' },
  { id: 5, codigo: 'NR-32', nome: 'Segurança e Saúde nos Serviços de Saúde', conformidade: 78, status: 'Atenção', cor: '#f59e0b', ultimaRevisao: '2026-01-10' },
  { id: 6, codigo: 'NR-35', nome: 'Trabalho em Altura', conformidade: 100, status: 'Conforme', cor: '#10b981', ultimaRevisao: '2025-08-01' },
]

const MOCK_EPIS = [
  { id: 1, ico: '🥾', nome: 'Bota de Borracha ESD', ca: '43298', validade: '2026-06-15', quantidade: 1, vencido: false, aVencer: false },
  { id: 2, ico: '🧤', nome: 'Luva de Procedimento (cx100)', ca: '12578', validade: '2026-08-30', quantidade: 3, vencido: false, aVencer: false },
  { id: 3, ico: '😷', nome: 'Máscara N95 PFF2', ca: '28574', validade: '2026-03-20', quantidade: 10, vencido: false, aVencer: true },
]

const MOCK_INCS = [
  { id: 1, tipo: 'quase', data: '2026-02-10', descricao: 'Derramamento de solução fisiológica no corredor da UTI.', local: 'UTI Adulto — Corredor B', cat: null, closed: true },
  { id: 2, tipo: 'acidente', data: '2026-01-22', descricao: 'Acidente com perfurocortante durante procedimento de punção venosa.', local: 'Pronto-Socorro — Box 3', cat: '2026-0047', closed: false },
]

const epis = ref([])
const incidentes = ref([])

onMounted(async () => {
  try {
    const [rEpi, rInc] = await Promise.all([
      api.get('/api/v3/seguranca/epis'),
      api.get('/api/v3/seguranca/incidentes'),
    ])
    epis.value = (!rEpi.data.fallback && rEpi.data.epis?.length) ? rEpi.data.epis : MOCK_EPIS
    incidentes.value = (!rInc.data.fallback && rInc.data.incidentes?.length) ? rInc.data.incidentes : MOCK_INCS
  } catch {
    epis.value = MOCK_EPIS
    incidentes.value = MOCK_INCS
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const statusClass = (s) => s === 'Conforme' ? 'sc-green' : 'sc-yellow'
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }

const solicitarEpi = async () => {
  try {
    await api.post('/api/v3/seguranca/epis', { nome: novoEpi.nome, obs: novoEpi.obs, ico: '🦺' })
    const { data } = await api.get('/api/v3/seguranca/epis')
    if (!data.fallback && data.epis?.length) epis.value = data.epis
  } catch { /* mantém mock */ }
  toast.value = { visible: true, msg: `✅ Solicitação de "${novoEpi.nome}" enviada ao SESMT!` }
  Object.assign(novoEpi, { nome: '', obs: '' }); modalEpi.value = false
  setTimeout(() => toast.value.visible = false, 3000)
}

const notificarInc = async () => {
  const novoItem = { id: Date.now(), tipo: novoInc.tipo, data: new Date().toISOString().slice(0, 10), descricao: novoInc.descricao, local: novoInc.local, cat: null, closed: false }
  incidentes.value.unshift(novoItem)
  try { await api.post('/api/v3/seguranca/incidentes', { tipo: novoInc.tipo, local: novoInc.local, descricao: novoInc.descricao }) } catch { /* ok */ }
  toast.value = { visible: true, msg: '🚨 Notificação de incidente enviada ao SESMT!' }
  Object.assign(novoInc, { tipo: 'quase', local: '', descricao: '' }); modalInc.value = false
  setTimeout(() => toast.value.visible = false, 3000)
}
</script>


<style scoped>
.st-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a0a0a 55%, #0a1a10 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #ef4444; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #f87171; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-gauges { display: flex; gap: 16px; align-items: center; }
.hg { display: flex; align-items: center; gap: 10px; }
.hg-svg { width: 54px; height: 54px; transform: rotate(-90deg); }
.hg-info { }
.hg-val { display: block; font-size: 16px; font-weight: 900; }
.hg-label { display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.section-hdr { display: flex; align-items: center; justify-content: space-between; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.section-hdr.loaded { opacity: 1; transform: none; }
.sh-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.solicitar-epi-btn { padding: 8px 16px; border-radius: 11px; border: none; background: #0d9488; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.solicitar-epi-btn:hover { transform: translateY(-1px); }
.nrs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.nrs-grid.loaded { opacity: 1; transform: none; }
.nr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; border-top: 3px solid var(--nrc); animation: nrIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--nrd) both; }
@keyframes nrIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.nr-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.nr-badge { font-size: 12px; font-weight: 900; color: #1e293b; background: #f1f5f9; padding: 3px 8px; border-radius: 8px; font-family: monospace; }
.nr-status { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.sc-green { background: #dcfce7; color: #166534; }
.sc-yellow { background: #fffbeb; color: #92400e; }
.nr-nome { font-size: 12px; font-weight: 700; color: #1e293b; margin: 0 0 10px; line-height: 1.4; }
.nr-pct-bar { height: 5px; background: #f1f5f9; border-radius: 99px; overflow: hidden; margin-bottom: 5px; }
.nr-pct-fill { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.nr-pct-txt { font-size: 11px; font-weight: 700; }
.nr-data { font-size: 10px; color: #94a3b8; margin-top: 6px; }
.epi-list { display: flex; flex-direction: column; gap: 8px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.epi-list.loaded { opacity: 1; transform: none; }
.epi-item { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; animation: epiIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--eid) both; }
@keyframes epiIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.epi-ico { font-size: 26px; flex-shrink: 0; }
.epi-info { flex: 1; }
.epi-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.epi-ca { display: block; font-size: 11px; color: #94a3b8; font-family: monospace; }
.epi-meta { display: flex; flex-direction: column; gap: 3px; align-items: flex-end; }
.epi-val { font-size: 11px; color: #64748b; }
.epi-qty { font-size: 11px; color: #64748b; }
.epi-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; flex-shrink: 0; }
.eb-green { background: #dcfce7; color: #166534; }
.eb-yellow { background: #fffbeb; color: #92400e; }
.eb-red { background: #fef2f2; color: #991b1b; }
.inc-section { display: flex; flex-direction: column; gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.inc-section.loaded { opacity: 1; transform: none; }
.inc-timeline { display: flex; flex-direction: column; gap: 0; padding-left: 16px; border-left: 2px solid #e2e8f0; }
.inc-item { position: relative; padding-bottom: 16px; animation: incIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--incd) both; }
@keyframes incIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.inc-marcador { position: absolute; left: -21px; top: 10px; width: 12px; height: 12px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 2px; }
.im-red { background: #ef4444; box-shadow: 0 0 0 2px #ef4444; }
.im-yellow { background: #f59e0b; box-shadow: 0 0 0 2px #f59e0b; }
.inc-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px 16px; margin-left: 12px; }
.inc-hdr { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.inc-tipo-chip { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.ic-red { background: #fef2f2; color: #991b1b; }
.ic-yellow { background: #fffbeb; color: #92400e; }
.inc-data { font-size: 12px; color: #94a3b8; flex: 1; }
.inc-status { font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 99px; }
.is-green { background: #dcfce7; color: #166534; }
.is-blue { background: #dbeafe; color: #1e40af; }
.inc-desc { font-size: 13px; color: #64748b; margin: 0 0 8px; line-height: 1.5; }
.inc-meta { display: flex; gap: 14px; font-size: 11px; color: #94a3b8; font-weight: 600; }
.notificar-btn { margin-left: 12px; margin-top: 6px; padding: 10px 18px; border-radius: 12px; border: 2px dashed #e2e8f0; background: transparent; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.18s; width: calc(100% - 12px); }
.notificar-btn:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-ta { resize: vertical; min-height: 80px; }
.modal-actions { display: flex; gap: 10px; margin-top: 4px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #0d9488; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

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
