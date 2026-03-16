<template>
  <div class="cv-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📄 Recursos Humanos</span>
          <h1 class="hero-title">Contratos e Vínculos</h1>
          <p class="hero-sub">Histórico de vínculos empregatícios e documentos contratuais</p>
        </div>
        <div class="hero-vinculo-atual">
          <div class="va-tag">Vínculo Atual</div>
          <div class="va-tipo">{{ vinculoAtual.tipo }}</div>
          <div class="va-info">{{ vinculoAtual.regime }} · {{ vinculoAtual.cargo }}</div>
          <div class="va-prev-badge" :class="regimePrevBadge.cls">{{ regimePrevBadge.ico }} {{ regimePrevBadge.txt }}</div>
          <div class="va-tempo">{{ tempoServico }}</div>
        </div>
      </div>
    </div>

    <!-- CONTRATO ATIVO -->
    <div class="contrato-ativo-card" :class="{ loaded }">
      <div class="cac-hdr">
        <div class="cac-left">
          <div class="ca-ico">📋</div>
          <div>
            <h2 class="cac-title">Contrato de Trabalho Vigente</h2>
            <p class="cac-sub">Número: {{ vinculoAtual.contrato }}</p>
          </div>
        </div>
        <span class="ca-status">✅ Ativo</span>
      </div>
      <div class="cac-details">
        <div class="cad-item" v-for="d in vinculoAtual.detalhes" :key="d.label">
          <span class="cad-label">{{ d.label }}</span>
          <span class="cad-val">{{ d.val }}</span>
        </div>
      </div>
      <div class="cac-actions">
        <button class="ca-btn" v-for="a in acoes" :key="a.label" @click="downloadDoc(a)">
          {{ a.ico }} {{ a.label }}
        </button>
      </div>
    </div>

    <!-- HISTÓRICO DE VÍNCULOS -->
    <div class="hist-section" :class="{ loaded }">
      <h2 class="sec-title">📅 Histórico de Vínculos</h2>
      <div class="timeline-vinc">
        <div v-for="(v, i) in historicov" :key="v.id" class="tv-item" :style="{ '--tvi': i }">
          <div class="tv-marcador" :class="{ 'tv-ativo': v.ativo }"></div>
          <div class="tv-card" :class="{ 'tv-ativo-card': v.ativo }">
            <div class="tv-hdr">
              <span class="tv-tipo">{{ v.tipo }}</span>
              <span class="tv-periodo">{{ v.inicio }} → {{ v.ativo ? 'Atual' : v.fim }}</span>
              <span class="tv-badge" :class="v.ativo ? 'vb-ativo' : 'vb-enc'">{{ v.ativo ? 'Ativo' : 'Encerrado' }}</span>
            </div>
            <div class="tv-details">
              <span class="tvd-item">💼 {{ v.cargo }}</span>
              <span class="tvd-item">🏥 {{ v.setor }}</span>
              <span class="tvd-item">💰 {{ v.salario }}</span>
              <span class="tvd-item tvd-regime" :class="v.regimePrevBadge.cls">{{ v.regimePrevBadge.ico }} {{ v.regime }}</span>
            </div>
            <div class="tv-docs" v-if="v.docs.length > 0">
              <span class="tv-docs-title">Documentos:</span>
              <button v-for="d in v.docs" :key="d" class="tv-doc-btn" @click="downloadDoc({ label: d })">
                📄 {{ d }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- DOCUMENTOS FUNCIONAIS -->
    <div class="docs-section" :class="{ loaded }">
      <h2 class="sec-title">📁 Documentos Funcionais</h2>
      <div class="docs-grid">
        <div v-for="(d, i) in documentos" :key="d.id" class="doc-card" :style="{ '--dci': i }">
          <div class="dc-ico-wrap" :class="d.tipo === 'pdf' ? 'dc-pdf' : d.tipo === 'docx' ? 'dc-docx' : 'dc-img'">
            {{ d.tipo === 'pdf' ? '📄' : d.tipo === 'docx' ? '📝' : '🖼️' }}
          </div>
          <div class="dc-info">
            <span class="dc-nome">{{ d.nome }}</span>
            <span class="dc-data">{{ formatDate(d.data) }}</span>
          </div>
          <button class="dc-download" @click="downloadDoc(d)" title="Baixar">⬇️</button>
        </div>
      </div>
    </div>

    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const toast = ref({ visible: false, msg: '' })

const vinculoAtualData = ref({
  tipo: 'Servidor Efetivo',
  regime: 'Estatutário',
  regimePrev: 'RPPS', // RPPS = IPAM (regime próprio São Luís) | RGPS = INSS (temporários, PSS, estagiários)
  cargo: 'Carregando...',
  contrato: '—',
  admissao: null,
  detalhes: []
})

// Badge visual do regime previdenciário
const regimePrevBadge = computed(() => {
  const r = vinculoAtualData.value.regimePrev
  if (r === 'RPPS') return { ico: '🏦', txt: 'RPPS — IPAM São Luís', cls: 'rpv-rpps' }
  if (r === 'RGPS') return { ico: '🟢', txt: 'RGPS — INSS', cls: 'rpv-rgps' }
  return { ico: 'ℹ️', txt: r, cls: 'rpv-neutro' }
})

const prevBadge = (regimePrev) => {
  if (regimePrev === 'RPPS') return { ico: '🏦', cls: 'rpv-rpps' }
  if (regimePrev === 'RGPS') return { ico: '🟢', cls: 'rpv-rgps' }
  return { ico: 'ℹ️', cls: 'rpv-neutro' }
}

const vinculoAtual = computed(() => ({
  tipo: vinculoAtualData.value.tipo,
  regime: vinculoAtualData.value.regime,
  cargo: vinculoAtualData.value.cargo,
  contrato: vinculoAtualData.value.contrato,
  detalhes: vinculoAtualData.value.detalhes.length ? vinculoAtualData.value.detalhes : [
    { label: 'Data de Admissão', val: vinculoAtualData.value.admissao ? new Date(vinculoAtualData.value.admissao+'T12:00:00').toLocaleDateString('pt-BR') : '—' },
    { label: 'Cargo', val: vinculoAtualData.value.cargo },
    { label: 'Setor', val: vinculoAtualData.value.setor ?? '—' },
    { label: 'Unidade', val: vinculoAtualData.value.unidade ?? '—' },
    { label: 'Regime Jurídico', val: vinculoAtualData.value.regime },
    { label: 'Previdência', val: vinculoAtualData.value.regimePrev === 'RPPS' ? 'RPPS — IPAM São Luís' : 'RGPS — INSS' },
    { label: 'Matrícula', val: vinculoAtualData.value.contrato },
    { label: 'CPF', val: vinculoAtualData.value.cpf ?? '—' },
    { label: 'PIS/PASEP', val: vinculoAtualData.value.pis ?? '—' },
  ]
}))

const tempoServico = computed(() => {
  const admissao = vinculoAtualData.value.admissao
  const inicio = admissao ? new Date(admissao) : new Date('2021-03-15')
  const hoje = new Date()
  const anos = Math.floor((hoje - inicio) / (1000 * 60 * 60 * 24 * 365.25))
  const meses = Math.floor(((hoje - inicio) % (1000 * 60 * 60 * 24 * 365.25)) / (1000 * 60 * 60 * 24 * 30.44))
  return `${anos} anos e ${meses} meses de serviço`
})

const acoes = [
  { ico: '📄', label: 'Portaria de Nomeação' },
  { ico: '📋', label: 'Termo de Posse' },
  { ico: '📑', label: 'Declaração de Vínculo' },
  { ico: '🖨️', label: 'Ficha Cadastral' },
]

const historicov = ref([
  {
    id: 1, ativo: true, tipo: 'Servidor Efetivo', inicio: 'Mar/2021', fim: null,
    cargo: 'Enfermeiro Assistencial', setor: 'UTI Adulto', salario: 'R$ 5.500,00',
    regime: 'Estatutário', regimePrev: 'RPPS',
    get regimePrevBadge() { return prevBadge(this.regimePrev) },
    docs: ['Portaria de Nomeação', 'Termo de Posse']
  },
  {
    id: 2, ativo: false, tipo: 'Contrato Temporário (PSS)', inicio: 'Jan/2020', fim: 'Mar/2021',
    cargo: 'Enfermeiro', setor: 'Pronto-Socorro', salario: 'R$ 4.200,00',
    regime: 'CLT / PSS', regimePrev: 'RGPS',
    get regimePrevBadge() { return prevBadge(this.regimePrev) },
    docs: ['Contrato de Prestação']
  },
  {
    id: 3, ativo: false, tipo: 'Estágio Curricular', inicio: 'Jul/2019', fim: 'Dez/2019',
    cargo: 'Estagiário de Enfermagem', setor: 'Clínica Médica', salario: 'Bolsa R$ 900,00',
    regime: 'Lei de Estágio', regimePrev: 'RGPS',
    get regimePrevBadge() { return prevBadge(this.regimePrev) },
    docs: ['Termo de Estágio']
  },
])

const documentos = ref([
  { id: 1, nome: 'Portaria de Nomeação nº 847/2021', tipo: 'pdf', data: '2021-03-10' },
  { id: 2, nome: 'Termo de Posse e Exercício', tipo: 'pdf', data: '2021-03-15' },
  { id: 3, nome: 'Ficha de Cadastro Funcional', tipo: 'docx', data: '2021-03-15' },
  { id: 4, nome: 'Declaração de Acumulação de Cargos', tipo: 'pdf', data: '2022-01-05' },
  { id: 5, nome: 'Progressão Funcional — 2023', tipo: 'pdf', data: '2023-03-16' },
  { id: 6, nome: 'Declaração de Vínculo Atual', tipo: 'pdf', data: '2026-01-10' },
])

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/contratos')
    if (!data.fallback && data.contrato) {
      const c = data.contrato
      vinculoAtualData.value = {
        tipo: c.vinculo || 'Servidor',
        regime: c.vinculo || 'Estatutário',
        regimePrev: c.regime_prev ?? 'RPPS',
        cargo: c.cargo || '—',
        contrato: c.matricula || '—',
        admissao: c.admissao,
        setor: c.setor,
        unidade: c.unidade,
        cpf: c.cpf,
        pis: c.pis,
        detalhes: [],
      }
    }
    if (!data.fallback && data.historico?.length) {
      historicov.value = data.historico.map((l, idx) => ({
        id: l.id ?? idx,
        ativo: l.ativo,
        tipo: l.tipo,
        inicio: l.inicio ? new Date(l.inicio+'T12:00:00').toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' }) : '—',
        fim: l.fim,
        cargo: l.cargo,
        setor: [l.setor, l.unidade].filter(Boolean).join(' — '),
        salario: '—',
        regime: l.regime,
        regimePrev: l.regime_prev ?? (l.regime?.includes('PSS') || l.regime?.includes('Tempor') || l.regime?.includes('CLT') || l.regime?.includes('Estágio') ? 'RGPS' : 'RPPS'),
        get regimePrevBadge() { return prevBadge(this.regimePrev) },
        docs: [],
      }))
    }
  } catch { /* usa mock */ } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const downloadDoc = (d) => {
  toast.value = { visible: true, msg: `📄 "${d.label || d.nome}" baixado!` }
  setTimeout(() => { toast.value.visible = false }, 3000)
}
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.cv-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a2a14 55%, #0f1a3a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #3b82f6; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 280px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #60a5fa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-vinculo-atual { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; padding: 14px 20px; }
.va-tag { font-size: 10px; font-weight: 700; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
.va-tipo { font-size: 18px; font-weight: 900; color: #fff; }
.va-info { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.va-tempo { font-size: 11px; color: #34d399; font-weight: 700; margin-top: 6px; }
.contrato-ativo-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; border-top: 3px solid #3b82f6; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.contrato-ativo-card.loaded { opacity: 1; transform: none; }
.cac-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.cac-left { display: flex; align-items: center; gap: 14px; }
.ca-ico { width: 46px; height: 46px; border-radius: 13px; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.cac-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0 0 3px; }
.cac-sub { font-size: 12px; color: #94a3b8; margin: 0; font-family: monospace; }
.ca-status { background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 5px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; }
.cac-details { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 16px; }
.cad-item { display: flex; flex-direction: column; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 9px 12px; }
.cad-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.06em; margin-bottom: 3px; }
.cad-val { font-size: 13px; font-weight: 700; color: #1e293b; }
.cac-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.ca-btn { padding: 8px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.ca-btn:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; transform: translateY(-1px); }
.hist-section, .docs-section { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.hist-section.loaded, .docs-section.loaded { opacity: 1; transform: none; }
.sec-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0 0 14px; }
.timeline-vinc { display: flex; flex-direction: column; gap: 0; padding-left: 16px; border-left: 2px solid #e2e8f0; }
.tv-item { position: relative; padding-bottom: 18px; animation: tvIn 0.4s cubic-bezier(0.22,1,0.36,1) calc(var(--tvi) * 80ms) both; }
@keyframes tvIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.tv-marcador { position: absolute; left: -21px; top: 10px; width: 12px; height: 12px; border-radius: 50%; background: #e2e8f0; border: 3px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
.tv-marcador.tv-ativo { background: #3b82f6; box-shadow: 0 0 0 2px #3b82f6; }
.tv-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 14px 16px; margin-left: 12px; }
.tv-ativo-card { border-color: #bae6fd; background: #f0f9ff; }
.tv-hdr { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.tv-tipo { font-size: 14px; font-weight: 800; color: #1e293b; }
.tv-periodo { font-size: 12px; color: #94a3b8; flex: 1; }
.tv-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.vb-ativo { background: #dbeafe; color: #1e40af; }
.vb-enc { background: #f1f5f9; color: #64748b; }
.tv-details { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.tvd-item { font-size: 12px; color: #475569; font-weight: 600; }
.tv-docs { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tv-docs-title { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.tv-doc-btn { padding: 4px 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 11px; font-weight: 600; color: #3b82f6; cursor: pointer; }
.docs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.doc-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; animation: docIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--dci) * 50ms) both; }
@keyframes docIn { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: none; } }
.dc-ico-wrap { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.dc-pdf { background: #fef2f2; }
.dc-docx { background: #eff6ff; }
.dc-img { background: #f0fdf4; }
.dc-nome { display: block; font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.dc-data { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.dc-download { margin-left: auto; width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 14px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.dc-download:hover { background: #eff6ff; border-color: #3b82f6; transform: translateY(-1px); }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
/* Badge Regime Previdenciário */
.va-prev-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 99px; letter-spacing: 0.04em; }
.rpv-rpps { background: rgba(59,130,246,0.18); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); }
.rpv-rgps { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.rpv-neutro { background: rgba(255,255,255,0.08); color: #94a3b8; }
.tvd-regime { font-weight: 700; padding: 2px 8px !important; border-radius: 6px !important; }
.tvd-item.rpv-rpps { background: #dbeafe !important; color: #1d4ed8 !important; }
.tvd-item.rpv-rgps { background: #d1fae5 !important; color: #065f46 !important; }

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
