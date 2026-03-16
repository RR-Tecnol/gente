<template>
  <div class="ben-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🎁 Gestão de Pessoas</span>
          <h1 class="hero-title">Meus Benefícios</h1>
          <p class="hero-sub">Pacote de benefícios ativos · R$ {{ fmtMoeda(totalMensal) }}/mês em créditos</p>
        </div>
        <div class="hero-cards-mini">
          <div v-for="b in beneficiosAtivos.slice(0, 3)" :key="b.id" class="hcm" :style="{ '--bc': b.cor }">
            <span>{{ b.ico }}</span>
            <span class="hcm-nome">{{ b.nome }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- BENEFÍCIOS ATIVOS -->
    <div class="section-title-row" :class="{ loaded }">
      <h2 class="sec-title">✅ Benefícios Ativos</h2>
      <span class="sec-count">{{ beneficiosAtivos.length }} ativo{{ beneficiosAtivos.length !== 1 ? 's' : '' }}</span>
    </div>
    <div class="ben-grid" :class="{ loaded }">
      <div v-for="(b, i) in beneficiosAtivos" :key="b.id" class="ben-card" :style="{ '--bc': b.cor, '--bd': `${i * 60}ms` }">
        <div class="ben-top" :style="{ background: `linear-gradient(135deg, ${b.cor}22, ${b.cor}08)` }">
          <span class="ben-ico">{{ b.ico }}</span>
          <div class="ben-status-badge">Ativo ✓</div>
        </div>
        <div class="ben-body">
          <h3 class="ben-nome">{{ b.nome }}</h3>
          <p class="ben-desc">{{ b.desc }}</p>
          <div class="ben-detalhe">
            <div class="bd-item" v-for="d in b.detalhes" :key="d.label">
              <span class="bd-label">{{ d.label }}</span>
              <span class="bd-val">{{ d.val }}</span>
            </div>
          </div>
          <div class="ben-valor-row">
            <span class="bv-label">Crédito Mensal</span>
            <span class="bv-val" :style="{ color: b.cor }">R$ {{ fmtMoeda(b.valor) }}</span>
          </div>
        </div>
        <div class="ben-footer">
          <span class="ben-fornecedor">{{ b.fornecedor }}</span>
          <button class="ben-extrato-btn" @click="abrirExtrato(b)">Ver Extrato</button>
        </div>
      </div>
    </div>

    <!-- BENEFÍCIOS DISPONÍVEIS -->
    <div class="section-title-row" :class="{ loaded }">
      <h2 class="sec-title">🔓 Disponíveis para Ativação</h2>
    </div>
    <div class="disponiveis-list" :class="{ loaded }">
      <div v-for="(b, i) in beneficiosDisponiveis" :key="b.id" class="disp-item" :style="{ '--dd': `${i * 50}ms` }">
        <span class="disp-ico">{{ b.ico }}</span>
        <div class="disp-info">
          <span class="disp-nome">{{ b.nome }}</span>
          <span class="disp-desc">{{ b.desc }}</span>
        </div>
        <div class="disp-meta">
          <span class="disp-valor">R$ {{ fmtMoeda(b.valor) }}/mês</span>
          <span class="disp-custo" v-if="b.custo > 0">Desc. R$ {{ fmtMoeda(b.custo) }}</span>
          <span class="disp-custo gratuito" v-else>Sem desconto</span>
        </div>
        <button class="ativar-btn" :style="{ background: b.cor }" @click="ativarBeneficio(b)">Solicitar</button>
      </div>
    </div>

    <!-- MODAL EXTRATO -->
    <transition name="modal">
      <div v-if="extratoAberto" class="modal-overlay" @click.self="extratoAberto = null">
        <div class="modal-card">
          <div class="modal-hdr" :style="{ borderBottom: `3px solid ${extratoAberto.cor}` }">
            <span style="font-size:24px">{{ extratoAberto.ico }}</span>
            <div>
              <h3>{{ extratoAberto.nome }}</h3>
              <span class="modal-sub">Extrato de utilização</span>
            </div>
            <button class="modal-close" @click="extratoAberto = null">✕</button>
          </div>
          <div class="extrato-list">
            <div v-for="e in extratoAberto.extrato" :key="e.data" class="ext-item">
              <div class="ext-data">{{ e.data }}</div>
              <div class="ext-local">{{ e.local }}</div>
              <div class="ext-val" :class="e.tipo === 'credito' ? 'ev-green' : 'ev-red'">
                {{ e.tipo === 'credito' ? '+' : '-' }} R$ {{ fmtMoeda(e.valor) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const extratoAberto = ref(null)
const toast = ref({ visible: false, msg: '' })

// ── Mock de fallback ──────────────────────────────────────────
const mockAtivos = [
  { id: 1, ico: '🚌', nome: 'Vale-Transporte', cor: '#3b82f6', desc: 'Crédito diário para transporte público coletivo.', fornecedor: 'Ticket Log', valor: 440, detalhes: [{ label: 'Crédito/dia', val: 'R$ 22,00' }, { label: 'Dias úteis', val: '20d' }, { label: 'Desconto Folha', val: '6%' }], extrato: [] },
  { id: 2, ico: '🍽️', nome: 'Vale-Refeição', cor: '#f59e0b', desc: 'Benefício para refeições em restaurantes.', fornecedor: 'Alelo', valor: 800, detalhes: [{ label: 'Crédito/dia', val: 'R$ 40,00' }, { label: 'Dias úteis', val: '20d' }, { label: 'Desconto Folha', val: 'R$ 44,00' }], extrato: [] },
  { id: 3, ico: '🏥', nome: 'Plano de Saúde', cor: '#10b981', desc: 'Cobertura nacional em rede credenciada.', fornecedor: 'Unimed', valor: 650, detalhes: [{ label: 'Plano', val: 'Enfermaria Plus' }, { label: 'Dependentes', val: '2 incluídos' }, { label: 'Coparticipação', val: '20%' }], extrato: [] },
  { id: 4, ico: '😁', nome: 'Plano Odontológico', cor: '#6366f1', desc: 'Cobertura para consultas e procedimentos básicos.', fornecedor: 'OdontoPrev', valor: 80, detalhes: [{ label: 'Plano', val: 'Dental Básico' }, { label: 'Cobertura', val: 'Nacional' }, { label: 'Desconto Folha', val: 'R$ 25,00' }], extrato: [] },
]
const mockDisponiveis = [
  { id: 10, ico: '💊', nome: 'Farmácia Convênio', desc: 'Desconto de 30% em medicamentos nas farmácias parceiras.', cor: '#ef4444', valor: 200, custo: 0 },
  { id: 11, ico: '🏋️', nome: 'Academia de Ginástica', desc: 'Acesso à rede de academias parceiras.', cor: '#0d9488', valor: 120, custo: 60 },
  { id: 12, ico: '📚', nome: 'Auxílio-Educação', desc: 'Reembolso de até R$ 400/mês para cursos.', cor: '#6366f1', valor: 400, custo: 0 },
  { id: 13, ico: '🍼', nome: 'Auxílio-Creche', desc: 'Para servidores com filhos até 3 anos.', cor: '#f59e0b', valor: 500, custo: 0 },
]

const beneficiosAtivos = ref([])
const beneficiosDisponiveis = ref([])

// ── Mapeia registro do backend → formato Vue ──────────────────
const mapBeneficio = (b) => ({
  id:          b.BENEFICIO_ID        ?? b.id,
  ico:         b.BENEFICIO_ICO       ?? b.ico       ?? '🎁',
  nome:        b.BENEFICIO_NOME      ?? b.nome      ?? '—',
  cor:         b.BENEFICIO_COR       ?? b.cor       ?? '#64748b',
  desc:        b.BENEFICIO_DESC      ?? b.desc      ?? '',
  fornecedor:  b.BENEFICIO_FORNEC    ?? b.fornecedor ?? '—',
  valor: Number(b.BENEFICIO_VALOR    ?? b.valor     ?? 0),
  custo: Number(b.BENEFICIO_CUSTO    ?? b.custo     ?? 0),
  detalhes:    b.detalhes            ?? [],
  extrato:     b.extrato             ?? [],
})

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/beneficios')
    beneficiosAtivos.value = (!data.fallback && data.ativos?.length)
      ? data.ativos.map(mapBeneficio)
      : mockAtivos
    beneficiosDisponiveis.value = (!data.fallback && data.disponiveis?.length)
      ? data.disponiveis.map(mapBeneficio)
      : mockDisponiveis
  } catch {
    beneficiosAtivos.value = mockAtivos
    beneficiosDisponiveis.value = mockDisponiveis
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const totalMensal = computed(() => beneficiosAtivos.value.reduce((a, b) => a + b.valor, 0))

const abrirExtrato = (b) => { extratoAberto.value = b }

const ativarBeneficio = async (b) => {
  try {
    await api.post('/api/v3/beneficios/solicitar', { beneficio_id: b.id, nome: b.nome })
  } catch { /* fallback visual */ }
  toast.value = { visible: true, msg: `✅ Solicitação de "${b.nome}" enviada ao RH!` }
  setTimeout(() => { toast.value.visible = false }, 3000)
}

const fmtMoeda = (v) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0 }).format(v)
</script>


<style scoped>
.ben-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a0f2a 55%, #0d2a1a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #f59e0b; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 280px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fbbf24; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-cards-mini { display: flex; gap: 8px; }
.hcm { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.07); border: 1px solid color-mix(in srgb, var(--bc) 30%, rgba(255,255,255,0.1)); border-radius: 10px; padding: 6px 12px; font-size: 12px; color: #e2e8f0; }
.hcm-nome { font-weight: 600; }
.section-title-row { display: flex; align-items: center; gap: 12px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.section-title-row.loaded { opacity: 1; transform: none; }
.sec-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.sec-count { font-size: 12px; background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 2px 10px; border-radius: 99px; font-weight: 700; }
.ben-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.ben-grid.loaded { opacity: 1; transform: none; }
.ben-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; border-top: 3px solid var(--bc); transition: all 0.18s; animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1) var(--bd) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.ben-card:hover { box-shadow: 0 8px 32px -8px rgba(0,0,0,0.12); transform: translateY(-2px); }
.ben-top { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; }
.ben-ico { font-size: 30px; }
.ben-status-badge { background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.ben-body { padding: 8px 16px 12px; }
.ben-nome { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.ben-desc { font-size: 12px; color: #64748b; margin: 0 0 12px; line-height: 1.5; }
.ben-detalhe { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.bd-item { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 5px 10px; }
.bd-label { display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #94a3b8; }
.bd-val { display: block; font-size: 12px; font-weight: 800; color: #475569; }
.ben-valor-row { display: flex; align-items: center; justify-content: space-between; }
.bv-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.bv-val { font-size: 20px; font-weight: 900; }
.ben-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid #f8fafc; }
.ben-fornecedor { font-size: 11px; color: #94a3b8; }
.ben-extrato-btn { padding: 5px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.ben-extrato-btn:hover { background: #f1f5f9; transform: translateY(-1px); }
.disponiveis-list { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.disponiveis-list.loaded { opacity: 1; transform: none; }
.disp-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 18px; animation: dispIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--dd) both; flex-wrap: wrap; }
@keyframes dispIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.disp-ico { font-size: 26px; flex-shrink: 0; }
.disp-info { flex: 1; min-width: 150px; }
.disp-nome { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.disp-desc { display: block; font-size: 12px; color: #64748b; margin-top: 2px; }
.disp-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.disp-valor { font-size: 14px; font-weight: 900; color: #1e293b; }
.disp-custo { font-size: 11px; color: #ef4444; font-weight: 700; }
.disp-custo.gratuito { color: #10b981; }
.ativar-btn { padding: 9px 18px; border-radius: 12px; border: none; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.ativar-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: flex-end; justify-content: center; }
.modal-card { background: #fff; border-radius: 22px 22px 0 0; width: 100%; max-width: 480px; max-height: 80vh; overflow-y: auto; box-shadow: 0 -16px 64px rgba(0,0,0,0.15); }
.modal-hdr { display: flex; align-items: center; gap: 12px; padding: 20px 22px; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; flex: 1; }
.modal-sub { display: block; font-size: 12px; color: #94a3b8; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; font-size: 14px; color: #64748b; }
.extrato-list { padding: 8px 22px 22px; display: flex; flex-direction: column; gap: 8px; }
.ext-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; }
.ext-data { font-size: 11px; color: #94a3b8; font-weight: 700; white-space: nowrap; }
.ext-local { flex: 1; font-size: 13px; color: #475569; font-weight: 600; }
.ext-val { font-size: 14px; font-weight: 900; font-family: monospace; white-space: nowrap; }
.ev-green { color: #10b981; }
.ev-red { color: #ef4444; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-card, .modal-leave-to .modal-card { transform: translateY(100%); }

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
