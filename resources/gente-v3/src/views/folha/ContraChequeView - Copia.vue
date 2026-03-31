<template>
  <div class="holerites-page">

    <!-- ═══ HERO BANNER — RESUMO ANUAL ══════════════════════════════ -->
    <div class="hero-banner" :class="{ 'loaded': pageLoaded }">
      <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
      </div>
      <div class="hero-content">
        <div class="hero-left">
          <span class="hero-label">💰 Contracheque Digital</span>
          <h1 class="hero-title">Meus Holerites</h1>
          <p class="hero-sub">{{ holerites.length }} competência{{ holerites.length !== 1 ? 's' : '' }} disponíve{{ holerites.length !== 1 ? 'is' : 'l' }}</p>
        </div>
        <div class="hero-stats" v-if="holerites.length">
          <div class="stat-pill">
            <span class="stat-label">Receita Total</span>
            <span class="stat-value green">{{ formatMoney(totalProventos) }}</span>
          </div>
          <div class="stat-pill">
            <span class="stat-label">Descontos</span>
            <span class="stat-value red">{{ formatMoney(totalDescontos) }}</span>
          </div>
          <div class="stat-pill highlight">
            <span class="stat-label">Líquido Acumulado</span>
            <span class="stat-value">{{ formatMoney(totalLiquido) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ LOADING ══════════════════════════════════════════════════ -->
    <div v-if="loading" class="loading-state">
      <div class="loading-orb"></div>
      <p>Buscando seus contracheques...</p>
    </div>

    <!-- ═══ ERRO ═════════════════════════════════════════════════════ -->
    <div v-else-if="erro" class="error-state">
      <div class="error-icon">⚠️</div>
      <h3>Falha na comunicação</h3>
      <p>{{ erro }}</p>
      <button class="btn-retry" @click="fetchHolerites">Tentar novamente</button>
    </div>

    <!-- ═══ VAZIO ════════════════════════════════════════════════════ -->
    <div v-else-if="holerites.length === 0" class="empty-state">
      <div class="empty-icon">📂</div>
      <h3>Nenhum holerite encontrado</h3>
      <p>Nenhuma folha de pagamento processada para seu perfil ainda.</p>
    </div>

    <!-- ═══ GRID DE CARDS ═════════════════════════════════════════════ -->
    <div v-else class="cards-grid">
      <div
        v-for="(folha, index) in holerites"
        :key="index"
        class="holerite-card"
        :style="{ '--delay': `${index * 80}ms` }"
        :class="{ 'card-visible': pageLoaded }"
      >
        <!-- Cabeçalho gradiente -->
        <div class="card-header">
          <div class="card-month-info">
            <span class="card-month-label">COMPETÊNCIA</span>
            <span class="card-month-value">{{ formatCompetencia(folha.competencia) }}</span>
          </div>
          <div class="card-badge">
            <span class="badge-dot"></span>
            Processado
          </div>
        </div>

        <!-- Corpo: barras de valor -->
        <div class="card-body">
          <!-- Barra de Proventos -->
          <div class="value-row">
            <div class="value-info">
              <div class="value-icon provento-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M17 7H7M17 7V17" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <span class="value-label">Proventos</span>
            </div>
            <span class="value-amount provento-amount">{{ formatMoney(folha.proventos) }}</span>
          </div>
          <div class="bar-container">
            <div class="bar-track">
              <div class="bar-fill provento-bar" :style="{ width: pageLoaded ? '100%' : '0%' }"></div>
            </div>
          </div>

          <!-- Barra de Descontos -->
          <div class="value-row">
            <div class="value-info">
              <div class="value-icon desconto-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M7 7L17 17M7 17H17" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <span class="value-label">Descontos</span>
            </div>
            <span class="value-amount desconto-amount">{{ formatMoney(folha.descontos) }}</span>
          </div>
          <div class="bar-container">
            <div class="bar-track">
              <div
                class="bar-fill desconto-bar"
                :style="{ width: pageLoaded ? `${((folha.descontos / folha.proventos) * 100).toFixed(0)}%` : '0%' }"
              ></div>
            </div>
          </div>

          <!-- Separador -->
          <div class="divider"></div>

          <!-- Líquido Destaque -->
          <div class="liquido-block">
            <span class="liquido-label">💳 Líquido a Receber</span>
            <span class="liquido-value">{{ formatMoney(folha.liquido) }}</span>
          </div>

          <!-- Indicador de retenção -->
          <div class="retention-info">
            <span class="retention-label">Retenção</span>
            <div class="retention-bar-wrap">
              <div class="retention-bar">
                <div
                  class="retention-fill"
                  :style="{ width: pageLoaded ? `${((folha.descontos / folha.proventos) * 100).toFixed(0)}%` : '0%' }"
                ></div>
              </div>
              <span class="retention-pct">{{ ((folha.descontos / folha.proventos) * 100).toFixed(1) }}%</span>
            </div>
          </div>
        </div>

        <!-- Rodapé: botão PDF -->
        <div class="card-footer">
          <button
            class="btn-pdf"
            @click="baixarHolerite(folha.funcionario_id, folha.competencia, folha)"
          >
            <span class="btn-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 16L7 11M12 16L17 11M12 16V4M6 20H18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Baixar PDF
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

const holerites = ref([])
const loading = ref(true)
const erro = ref('')
const pageLoaded = ref(false)

onMounted(async () => {
  await fetchHolerites()
  // Delay mínimo para as animações CSS funcionarem
  setTimeout(() => { pageLoaded.value = true }, 100)
})

const fetchHolerites = async () => {
  loading.value = true
  erro.value = ''
  try {
    const response = await api.get('/api/v3/meus-holerites')
    const data = response.data
    // Normaliza os campos: o backend retorna {bruto, descontos, liquido}
    // mas a view usa {proventos, descontos, liquido}
    const lista = data.holerites ?? data ?? []
    holerites.value = lista.map(h => ({
      ...h,
      proventos: h.proventos ?? h.bruto ?? 0,
      descontos: h.descontos ?? 0,
      liquido:   h.liquido ?? 0,
      // Para o link do PDF precisamos do funcionario_id — pode vir diretamente ou via id
      funcionario_id: h.funcionario_id ?? null,
    }))
  } catch (err) {
    console.error('Erro ao buscar holerites:', err)
    erro.value = err.response?.data?.erro || 'Problema ao comunicar com o servidor.'
  } finally {
    loading.value = false
  }
}

const totalProventos = computed(() => holerites.value.reduce((s, h) => s + h.proventos, 0))
const totalDescontos = computed(() => holerites.value.reduce((s, h) => s + h.descontos, 0))
const totalLiquido = computed(() => holerites.value.reduce((s, h) => s + h.liquido, 0))

const formatMoney = (v) => {
  if (v == null) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
}

const formatCompetencia = (comp) => {
  if (!comp || String(comp).length !== 6) return comp
  const s = String(comp)
  const ano = s.substring(0, 4)
  const mes = s.substring(4, 6)
  const meses = { '01':'Janeiro','02':'Fevereiro','03':'Março','04':'Abril','05':'Maio','06':'Junho','07':'Julho','08':'Agosto','09':'Setembro','10':'Outubro','11':'Novembro','12':'Dezembro' }
  return `${meses[mes] || mes} / ${ano}`
}

const baixarHolerite = (funcionarioId, competencia, holerite) => {
  // Usa o ID do cálculo para a rota nova
  const calculoId = holerite?.calculo_id ?? holerite?.id ?? funcionarioId
  window.open(`/api/v3/meus-holerites/${calculoId}/pdf`, '_blank')
}
</script>

<style scoped>
/* ═══ PAGE CONTAINER ════════════════════════════════════════════════ */
.holerites-page {
  min-height: 100%;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ═══ HERO BANNER ═══════════════════════════════════════════════════ */
.hero-banner {
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f2f5a 100%);
  border-radius: 24px;
  padding: 36px 40px;
  margin-bottom: 32px;
  overflow: hidden;
  opacity: 0;
  transform: translateY(-12px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero-banner.loaded {
  opacity: 1;
  transform: translateY(0);
}

.hero-bg-shapes { position: absolute; inset: 0; pointer-events: none; }
.shape {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  opacity: 0.15;
}
.shape-1 { width: 300px; height: 300px; background: #3b82f6; top: -80px; right: -60px; }
.shape-2 { width: 200px; height: 200px; background: #10b981; bottom: -60px; right: 200px; }
.shape-3 { width: 150px; height: 150px; background: #6366f1; top: 20px; left: 40%; }

.hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 24px;
}

.hero-label {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #60a5fa;
  margin-bottom: 8px;
}
.hero-title {
  font-size: 32px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.02em;
  margin: 0 0 6px;
  line-height: 1.1;
}
.hero-sub {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.hero-stats {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.stat-pill {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  backdrop-filter: blur(12px);
  border-radius: 16px;
  padding: 14px 20px;
  min-width: 130px;
  transition: background 0.2s;
}
.stat-pill:hover { background: rgba(255,255,255,0.12); }
.stat-pill.highlight {
  background: linear-gradient(135deg, rgba(16,185,129,0.25), rgba(16,185,129,0.1));
  border-color: rgba(16,185,129,0.4);
}
.stat-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
  margin-bottom: 4px;
}
.stat-value {
  display: block;
  font-size: 18px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.02em;
}
.stat-value.green { color: #34d399; }
.stat-value.red { color: #f87171; }

/* ═══ ESTADOS ═══════════════════════════════════════════════════════ */
.loading-state, .empty-state, .error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
  color: #64748b;
}
.loading-orb {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  animation: spin 0.8s linear infinite;
  margin-bottom: 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-state p, .empty-state p, .error-state p { font-size: 14px; color: #94a3b8; margin-top: 6px; }
.loading-state p { font-size: 15px; font-weight: 500; margin: 0; }
.empty-icon, .error-icon { font-size: 48px; margin-bottom: 16px; }
.empty-state h3, .error-state h3 { font-size: 20px; font-weight: 700; color: #334155; margin: 0 0 8px; }
.btn-retry {
  margin-top: 20px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 10px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-retry:hover { background: #2563eb; }

/* ═══ GRID DE CARDS ═════════════════════════════════════════════════ */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

/* ═══ HOLERITE CARD ═════════════════════════════════════════════════ */
.holerite-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  opacity: 0;
  transform: translateY(24px) scale(0.98);
  transition:
    opacity 0.4s cubic-bezier(0.22, 1, 0.36, 1) var(--delay),
    transform 0.4s cubic-bezier(0.22, 1, 0.36, 1) var(--delay),
    box-shadow 0.25s ease;
}
.holerite-card.card-visible {
  opacity: 1;
  transform: translateY(0) scale(1);
}
.holerite-card:hover {
  box-shadow: 0 20px 60px -12px rgba(15, 23, 42, 0.18);
  transform: translateY(-4px) scale(1.004);
}

/* ─── CARD HEADER ─────────────────────────────────────────────────── */
.card-header {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  padding: 22px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;
}
.card-header::before {
  content: '';
  position: absolute;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: rgba(255,255,255,0.04);
  top: -60px;
  right: -40px;
}
.card-header::after {
  content: '';
  position: absolute;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(56,189,248,0.08);
  bottom: -30px;
  left: 20px;
}

.card-month-info { position: relative; z-index: 1; }
.card-month-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #60a5fa;
  margin-bottom: 5px;
}
.card-month-value {
  display: block;
  font-size: 22px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.02em;
}

.card-badge {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(16,185,129,0.15);
  border: 1px solid rgba(16,185,129,0.3);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 700;
  color: #34d399;
  letter-spacing: 0.05em;
}
.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #34d399;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}

/* ─── CARD BODY ───────────────────────────────────────────────────── */
.card-body {
  padding: 22px 24px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.value-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.value-info { display: flex; align-items: center; gap: 8px; }
.value-icon {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.provento-icon { background: #dcfce7; color: #16a34a; }
.desconto-icon { background: #fee2e2; color: #dc2626; }
.value-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.value-amount { font-size: 15px; font-weight: 800; }
.provento-amount { color: #16a34a; }
.desconto-amount { color: #dc2626; }

.bar-container { margin-top: -6px; margin-bottom: 4px; }
.bar-track {
  height: 4px;
  background: #f1f5f9;
  border-radius: 99px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 1.2s cubic-bezier(0.22, 1, 0.36, 1) var(--delay, 0ms);
}
.provento-bar { background: linear-gradient(90deg, #34d399, #10b981); }
.desconto-bar { background: linear-gradient(90deg, #f87171, #ef4444); }

.divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
  margin: 4px 0;
}

.liquido-block {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 1px solid #86efac;
  border-radius: 14px;
  padding: 14px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.liquido-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #166534;
}
.liquido-value {
  font-size: 22px;
  font-weight: 900;
  color: #15803d;
  letter-spacing: -0.03em;
}

.retention-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.retention-label {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 600;
  white-space: nowrap;
}
.retention-bar-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
}
.retention-bar {
  flex: 1;
  height: 6px;
  background: #f1f5f9;
  border-radius: 99px;
  overflow: hidden;
}
.retention-fill {
  height: 100%;
  background: linear-gradient(90deg, #fbbf24, #f59e0b);
  border-radius: 99px;
  transition: width 1.2s cubic-bezier(0.22, 1, 0.36, 1) var(--delay, 0ms);
}
.retention-pct {
  font-size: 11px;
  font-weight: 700;
  color: #92400e;
  white-space: nowrap;
}

/* ─── CARD FOOTER ─────────────────────────────────────────────────── */
.card-footer {
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
  background: #fafafa;
}
.btn-pdf {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 13px 20px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 0.02em;
  position: relative;
  overflow: hidden;
}
.btn-pdf::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
  opacity: 0;
  transition: opacity 0.2s;
}
.btn-pdf:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,23,42,0.3); }
.btn-pdf:hover::before { opacity: 1; }
.btn-pdf:active { transform: translateY(0); }
.btn-icon { display: flex; align-items: center; }

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
  .hide-mobile { display: none !important; }
}
</style>
