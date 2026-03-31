<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"/><div class="hs hs2"/><div class="hs hs3"/></div>
      <div class="hero-content">
        <div class="hero-badge">🏥 Gestão de Saúde · Admin</div>
        <h1 class="hero-title">Monitor de OSS</h1>
        <p class="hero-sub">Acompanhamento qualitativo das Organizações Sociais de Saúde</p>
      </div>
    </div>

    <div class="page-body">
      <!-- Aviso de mock -->
      <div class="mock-banner">
        <span class="mock-icon">🔬</span>
        <span>Módulo em fase piloto — indicadores de referência. Integração com sistemas das OSS prevista para implantação completa.</span>
      </div>

      <!-- Cards de OSS -->
      <div v-if="loadingOss" class="loading-state">
        <div class="spinner"/> Carregando organizações...
      </div>

      <div v-else class="oss-grid">
        <div
          v-for="oss in ossList"
          :key="oss.EMPRESA_ID"
          class="oss-card"
          :class="{ ativo: ossAtiva?.EMPRESA_ID === oss.EMPRESA_ID }"
          @click="selecionarOss(oss)"
        >
          <div class="oss-card-header">
            <div class="oss-icon">🏥</div>
            <div class="oss-info">
              <h3 class="oss-nome">{{ oss.RAZAO_SOCIAL }}</h3>
              <span class="oss-contrato">Contrato {{ oss.CONTRATO_NUM ?? 'N/A' }}</span>
            </div>
            <div class="oss-status-dot" :class="getStatusGeral(oss)" />
          </div>
          <div class="oss-card-footer">
            <span class="oss-valor">{{ formatarValor(oss.VALOR_MENSAL) }}/mês</span>
            <span class="oss-vigencia">até {{ formatarData(oss.VIGENCIA_FIM) }}</span>
          </div>
        </div>

        <div v-if="!ossList.length" class="empty-state">
          <p>Nenhuma OSS ativa cadastrada.</p>
          <small>Cadastre organizações sociais em Contratos Administrativos → Terceirizados.</small>
        </div>
      </div>

      <!-- Painel de indicadores -->
      <transition name="fade">
        <div v-if="ossAtiva" class="indicadores-painel">
          <div class="painel-header">
            <h2>{{ ossAtiva.RAZAO_SOCIAL }}</h2>
            <div class="painel-controles">
              <select v-model="competencia" class="cs-select" @change="carregarIndicadores">
                <option v-for="c in competencias" :key="c" :value="c">{{ c }}</option>
              </select>
              <button class="cs-btn-outline" @click="ossAtiva = null">Fechar</button>
            </div>
          </div>

          <div v-if="loadingIndicadores" class="loading-state">
            <div class="spinner"/> Carregando indicadores...
          </div>

          <div v-else class="indicadores-grid">
            <div
              v-for="ind in indicadores"
              :key="ind.codigo"
              class="ind-card"
              :class="getStatusIndicador(ind)"
            >
              <div class="ind-header">
                <span class="ind-codigo">{{ ind.codigo }}</span>
                <div class="ind-semaforo" :class="getStatusIndicador(ind)">
                  {{ getStatusLabel(ind) }}
                </div>
              </div>
              <p class="ind-nome">{{ ind.nome }}</p>
              <div class="ind-valores">
                <div class="ind-barra-container">
                  <div
                    class="ind-barra"
                    :class="getStatusIndicador(ind)"
                    :style="{ width: getBarraWidth(ind) + '%' }"
                  />
                </div>
                <div class="ind-numeros">
                  <span class="ind-realizado">{{ ind.realizado }}{{ ind.unidade }}</span>
                  <span class="ind-meta">Meta: {{ ind.meta }}{{ ind.unidade }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="indicadores.length" class="painel-resumo">
            <div class="resumo-item verde">
              <span class="resumo-num">{{ countStatus('verde') }}</span>
              <span>Metas atingidas</span>
            </div>
            <div class="resumo-item amarelo">
              <span class="resumo-num">{{ countStatus('amarelo') }}</span>
              <span>Atenção</span>
            </div>
            <div class="resumo-item vermelho">
              <span class="resumo-num">{{ countStatus('vermelho') }}</span>
              <span>Abaixo da meta</span>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

const loaded      = ref(false)
const ossList     = ref([])
const ossAtiva    = ref(null)
const indicadores = ref([])
const loadingOss  = ref(true)
const loadingIndicadores = ref(false)
const competencia = ref('')

// Últimas 12 competências
const competencias = computed(() => {
  const lista = []
  const d = new Date()
  for (let i = 0; i < 12; i++) {
    lista.push(`${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`)
    d.setMonth(d.getMonth() - 1)
  }
  return lista
})

onMounted(async () => {
  competencia.value = competencias.value[0]
  setTimeout(() => (loaded.value = true), 100)
  await carregarOss()
})

async function carregarOss() {
  try {
    const { data } = await api.get('/oss')
    ossList.value = data.oss ?? []
  } catch {
    ossList.value = []
  } finally {
    loadingOss.value = false
  }
}

async function selecionarOss(oss) {
  ossAtiva.value = oss
  await carregarIndicadores()
}

async function carregarIndicadores() {
  if (!ossAtiva.value) return
  loadingIndicadores.value = true
  try {
    const { data } = await api.get(`/oss/${ossAtiva.value.EMPRESA_ID}/indicadores`, {
      params: { competencia: competencia.value }
    })
    indicadores.value = data.indicadores ?? []
  } catch {
    indicadores.value = []
  } finally {
    loadingIndicadores.value = false
  }
}

function getStatusIndicador(ind) {
  const pct = ind.inverso
    ? (ind.meta / ind.realizado) * 100
    : (ind.realizado / ind.meta) * 100
  if (pct >= 95) return 'verde'
  if (pct >= 80) return 'amarelo'
  return 'vermelho'
}

function getStatusLabel(ind) {
  const s = getStatusIndicador(ind)
  return s === 'verde' ? '✓ OK' : s === 'amarelo' ? '⚠ Atenção' : '✗ Crítico'
}

function getBarraWidth(ind) {
  const pct = ind.inverso
    ? Math.min((ind.meta / ind.realizado) * 100, 100)
    : Math.min((ind.realizado / ind.meta) * 100, 100)
  return Math.max(pct, 4)
}

function countStatus(status) {
  return indicadores.value.filter(i => getStatusIndicador(i) === status).length
}

function getStatusGeral(oss) {
  // Sem dados reais — mock: status baseado no ID para variação visual
  return oss.EMPRESA_ID % 3 === 0 ? 'amarelo' : 'verde'
}

function formatarValor(v) {
  return Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatarData(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-BR')
}
</script>

<style scoped>
.mock-banner {
  background: #fef3c7; border: 1px solid #f59e0b; border-radius: 10px;
  padding: 12px 16px; display: flex; gap: 10px; align-items: center;
  font-size: 13px; color: #92400e; margin-bottom: 24px;
}
.mock-icon { font-size: 18px; }
.oss-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 32px; }
.oss-card { background: var(--card-bg); border: 2px solid var(--border); border-radius: 14px; padding: 18px; cursor: pointer; transition: all .2s; }
.oss-card:hover, .oss-card.ativo { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.oss-card-header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.oss-icon { font-size: 28px; }
.oss-info { flex: 1; }
.oss-nome { font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 4px; line-height: 1.3; }
.oss-contrato { font-size: 12px; color: var(--muted); }
.oss-status-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.oss-status-dot.verde { background: #10b981; }
.oss-status-dot.amarelo { background: #f59e0b; }
.oss-status-dot.vermelho { background: #ef4444; }
.oss-card-footer { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 10px; }
.oss-valor { font-weight: 600; color: var(--accent); }

.indicadores-painel { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
.painel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.painel-header h2 { font-size: 18px; font-weight: 700; color: var(--text); }
.painel-controles { display: flex; gap: 10px; align-items: center; }
.indicadores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 24px; }
.ind-card { border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
.ind-card.verde { border-left: 4px solid #10b981; }
.ind-card.amarelo { border-left: 4px solid #f59e0b; }
.ind-card.vermelho { border-left: 4px solid #ef4444; }
.ind-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.ind-codigo { font-size: 11px; color: var(--muted); font-weight: 600; }
.ind-semaforo { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.ind-semaforo.verde { background: #d1fae5; color: #065f46; }
.ind-semaforo.amarelo { background: #fef3c7; color: #92400e; }
.ind-semaforo.vermelho { background: #fee2e2; color: #991b1b; }
.ind-nome { font-size: 13px; font-weight: 600; color: var(--text); margin: 0 0 12px; }
.ind-barra-container { background: var(--border); border-radius: 4px; height: 6px; margin-bottom: 8px; overflow: hidden; }
.ind-barra { height: 100%; border-radius: 4px; transition: width .4s ease; }
.ind-barra.verde { background: #10b981; }
.ind-barra.amarelo { background: #f59e0b; }
.ind-barra.vermelho { background: #ef4444; }
.ind-numeros { display: flex; justify-content: space-between; font-size: 12px; }
.ind-realizado { font-weight: 700; color: var(--text); }
.ind-meta { color: var(--muted); }

.painel-resumo { display: flex; gap: 16px; justify-content: center; padding-top: 16px; border-top: 1px solid var(--border); }
.resumo-item { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 12px 24px; border-radius: 10px; }
.resumo-item.verde { background: #d1fae5; }
.resumo-item.amarelo { background: #fef3c7; }
.resumo-item.vermelho { background: #fee2e2; }
.resumo-num { font-size: 28px; font-weight: 800; }
.resumo-item.verde .resumo-num { color: #065f46; }
.resumo-item.amarelo .resumo-num { color: #92400e; }
.resumo-item.vermelho .resumo-num { color: #991b1b; }
.resumo-item span:last-child { font-size: 12px; font-weight: 500; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.loading-state { display: flex; align-items: center; gap: 10px; color: var(--muted); padding: 24px; }
.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
