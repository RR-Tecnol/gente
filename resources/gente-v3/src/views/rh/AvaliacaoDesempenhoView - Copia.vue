<template>
  <div class="ad-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⭐ Gestão de Pessoas</span>
          <h1 class="hero-title">Avaliação de Desempenho</h1>
          <p class="hero-sub">Ciclo {{ cicloAtual }} · Acompanhe sua evolução profissional</p>
        </div>
        <div class="hero-nota-wrap">
          <div class="nota-ring">
            <svg viewBox="0 0 90 90">
              <circle cx="45" cy="45" r="36" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="9"/>
              <circle cx="45" cy="45" r="36" fill="none"
                :stroke="notaCor(ultimaNota)"
                stroke-width="9"
                stroke-linecap="round"
                :stroke-dasharray="226"
                :stroke-dashoffset="226 - (226 * (ultimaNota / 10))"
                transform="rotate(-90 45 45)"
                style="transition: stroke-dashoffset 1.2s cubic-bezier(0.22,1,0.36,1)"/>
            </svg>
            <div class="ring-inner">
              <span class="ring-nota">{{ ultimaNota.toFixed(1) }}</span>
              <span class="ring-sub">/ 10</span>
            </div>
          </div>
          <div class="nota-info">
            <span class="ni-label">Última Avaliação</span>
            <span class="ni-conceito" :style="{ color: notaCor(ultimaNota) }">{{ notaConceito(ultimaNota) }}</span>
            <span class="ni-ciclo">Ciclo {{ avaliacoes[0]?.ciclo }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- HISTÓRICO MINI -->
    <div class="historico-strip" :class="{ loaded }">
      <div v-for="(av, i) in avaliacoes" :key="av.ciclo" class="hs-item" :style="{ '--hd': `${i * 80}ms` }">
        <div class="hs-bar-wrap">
          <div class="hs-bar" :style="{ height: (av.nota / 10 * 80) + 'px', background: notaCor(av.nota) }"></div>
        </div>
        <span class="hs-nota" :style="{ color: notaCor(av.nota) }">{{ av.nota.toFixed(1) }}</span>
        <span class="hs-ciclo">{{ av.ciclo }}</span>
      </div>
    </div>

    <!-- CICLO ATUAL -->
    <div class="main-grid" :class="{ loaded }">

      <!-- CRITÉRIOS -->
      <div class="criterios-card">
        <div class="card-hdr">
          <h2 class="card-title">📊 Avaliação em Andamento</h2>
          <span class="ciclo-badge">Ciclo {{ cicloAtual }}</span>
        </div>

        <div class="criterios-list">
          <div v-for="c in criterios" :key="c.id" class="criterio-item">
            <div class="crit-hdr">
              <span class="crit-ico">{{ c.ico }}</span>
              <div class="crit-info">
                <span class="crit-nome">{{ c.nome }}</span>
                <span class="crit-peso">Peso {{ c.peso }}%</span>
              </div>
              <div class="crit-nota-wrap">
                <span class="crit-nota" :style="{ color: notaCor(c.nota) }">{{ c.nota }}</span>
                <span class="crit-max">/10</span>
              </div>
            </div>
            <div class="stars">
              <button v-for="n in 10" :key="n" class="star-btn"
                :class="{ 'star-on': n <= c.nota, 'star-hover': n <= c.hovered }"
                @mouseenter="c.hovered = n" @mouseleave="c.hovered = 0" @click="c.nota = n">
                ★
              </button>
            </div>
            <input v-model="c.obs" class="crit-obs" placeholder="Observações sobre este critério..." />
          </div>
        </div>

        <div class="nota-final-wrap">
          <div class="nf-label">Nota Final Ponderada</div>
          <div class="nf-valor" :style="{ color: notaCor(notaFinal) }">{{ notaFinal.toFixed(1) }}</div>
          <div class="nf-conceito" :style="{ color: notaCor(notaFinal) }">{{ notaConceito(notaFinal) }}</div>
        </div>

        <div v-if="erroSalvo" class="av-erro">❌ {{ erroSalvo }}</div>
        <div v-if="salvoOk"   class="av-ok">✅ Rascunho salvo com sucesso!</div>

        <button class="submit-btn" :disabled="salvando" @click="salvarAvaliacao">
          <span v-if="salvando" class="btn-spin-sm"></span>
          <template v-else>💾 Salvar Avaliação</template>
        </button>
      </div>

      <!-- HISTÓRICO COMPLETO -->
      <div class="hist-card">
        <h2 class="card-title">📋 Histórico de Ciclos</h2>
        <div class="av-list">
          <div v-for="av in avaliacoes" :key="av.ciclo" class="av-item">
            <div class="av-ciclo-info">
              <span class="av-ciclo">{{ av.ciclo }}</span>
              <div class="av-conceito-badge" :style="{ background: notaCor(av.nota) + '18', color: notaCor(av.nota), borderColor: notaCor(av.nota) + '40' }">
                {{ notaConceito(av.nota) }}
              </div>
            </div>
            <div class="av-criterios-preview">
              <div v-for="c in av.criterios" :key="c.nome" class="avcp-item">
                <span class="avcp-nome">{{ c.nome }}</span>
                <div class="avcp-stars">
                  <span v-for="n in 10" :key="n" class="avcp-star" :class="{ on: n <= c.nota }" :style="{ color: n <= c.nota ? notaCor(c.nota) : '#e2e8f0' }">★</span>
                </div>
                <span class="avcp-nota">{{ c.nota }}</span>
              </div>
            </div>
            <div class="av-footer">
              <span class="av-nota-final" :style="{ color: notaCor(av.nota) }">{{ av.nota.toFixed(1) }}/10</span>
              <span class="av-avaliador">Por: {{ av.avaliador }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded    = ref(false)
const salvando  = ref(false)
const salvoOk   = ref(false)
const erroSalvo = ref('')
const cicloAtual = '2026.1'
const ultimaNota = ref(0)

const criterios = reactive([
  { id: 1, ico: '🎯', nome: 'Cumprimento de Metas', peso: 25, nota: 8, hovered: 0, obs: '' },
  { id: 2, ico: '🤝', nome: 'Trabalho em Equipe', peso: 20, nota: 9, hovered: 0, obs: '' },
  { id: 3, ico: '⏰', nome: 'Pontualidade e Assiduidade', peso: 20, nota: 7, hovered: 0, obs: '' },
  { id: 4, ico: '💡', nome: 'Iniciativa e Proatividade', peso: 15, nota: 8, hovered: 0, obs: '' },
  { id: 5, ico: '📚', nome: 'Qualidade Técnica', peso: 20, nota: 9, hovered: 0, obs: '' },
])

const avaliacoes = ref([])

const notaCor = (nota) => {
  if (nota >= 9) return '#10b981'
  if (nota >= 7) return '#3b82f6'
  if (nota >= 5) return '#f59e0b'
  return '#ef4444'
}

const notaConceito = (nota) => {
  if (nota >= 9) return 'Excelente'
  if (nota >= 7) return 'Bom'
  if (nota >= 5) return 'Regular'
  return 'Insatisfatório'
}

const FALLBACK_AVALIACOES = [
  { ciclo: '2025.2', nota: 8.4, avaliador: 'Gestor Imediato', criterios: [
    { nome: 'Cumprimento de Metas', nota: 9 },
    { nome: 'Trabalho em Equipe', nota: 8 },
    { nome: 'Pontualidade', nota: 8 },
    { nome: 'Iniciativa', nota: 8 },
    { nome: 'Qualidade Técnica', nota: 9 },
  ]},
  { ciclo: '2025.1', nota: 7.9, avaliador: 'Gestor Imediato', criterios: [
    { nome: 'Cumprimento de Metas', nota: 8 },
    { nome: 'Trabalho em Equipe', nota: 8 },
    { nome: 'Pontualidade', nota: 7 },
    { nome: 'Iniciativa', nota: 8 },
    { nome: 'Qualidade Técnica', nota: 8 },
  ]},
  { ciclo: '2024.2', nota: 7.2, avaliador: 'Gestor Imediato', criterios: [
    { nome: 'Cumprimento de Metas', nota: 7 },
    { nome: 'Trabalho em Equipe', nota: 8 },
    { nome: 'Pontualidade', nota: 7 },
    { nome: 'Iniciativa', nota: 7 },
    { nome: 'Qualidade Técnica', nota: 7 },
  ]},
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/avaliacoes')
    if (!data.fallback && data.avaliacoes?.length) {
      avaliacoes.value = data.avaliacoes
      ultimaNota.value = data.avaliacoes[0]?.nota ?? 0
    } else {
      avaliacoes.value = FALLBACK_AVALIACOES
      ultimaNota.value = FALLBACK_AVALIACOES[0].nota
    }
  } catch {
    avaliacoes.value = FALLBACK_AVALIACOES
    ultimaNota.value = FALLBACK_AVALIACOES[0].nota
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})


const notaFinal = computed(() => {
  const total = criterios.reduce((a, c) => a + (c.nota * c.peso), 0)
  return total / 100
})

const salvarAvaliacao = async () => {
  salvando.value = true; erroSalvo.value = ''; salvoOk.value = false
  try {
    const { data } = await api.post('/api/v3/avaliacoes', {
      ciclo: cicloAtual,
      criterios: criterios.map(c => ({ nome: c.nome, peso: c.peso, nota: c.nota, obs: c.obs })),
    })
    ultimaNota.value = data.nota_final ?? notaFinal.value
    salvoOk.value = true
    setTimeout(() => salvoOk.value = false, 3000)
  } catch (e) {
    erroSalvo.value = e.response?.data?.erro || 'Erro ao salvar. Tente novamente.'
  } finally {
    salvando.value = false
  }
}
</script>

<style scoped>
.ad-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1a3a 55%, #0d2014 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #f59e0b; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 280px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fbbf24; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-nota-wrap { display: flex; align-items: center; gap: 18px; }
.nota-ring { position: relative; width: 90px; height: 90px; flex-shrink: 0; }
.nota-ring svg { width: 90px; height: 90px; }
.ring-inner { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-nota { font-size: 24px; font-weight: 900; color: #fff; line-height: 1; }
.ring-sub { font-size: 10px; color: #64748b; font-weight: 600; }
.ni-label { display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
.ni-conceito { display: block; font-size: 18px; font-weight: 900; margin-bottom: 4px; }
.ni-ciclo { display: block; font-size: 12px; color: #64748b; }
.historico-strip { display: flex; align-items: flex-end; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px 22px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.historico-strip.loaded { opacity: 1; transform: none; }
.hs-item { display: flex; flex-direction: column; align-items: center; gap: 5px; animation: barIn 0.6s cubic-bezier(0.22,1,0.36,1) var(--hd) both; }
@keyframes barIn { from { opacity: 0; transform: scaleY(0); transform-origin: bottom; } to { opacity: 1; transform: none; } }
.hs-bar-wrap { width: 40px; height: 80px; background: #f1f5f9; border-radius: 8px; overflow: hidden; display: flex; align-items: flex-end; }
.hs-bar { width: 100%; border-radius: 8px; transition: height 0.8s cubic-bezier(0.22,1,0.36,1); }
.hs-nota { font-size: 12px; font-weight: 900; }
.hs-ciclo { font-size: 10px; font-weight: 600; color: #94a3b8; white-space: nowrap; }
.main-grid { display: grid; grid-template-columns: 1fr 380px; gap: 18px; align-items: start; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; }
.main-grid.loaded { opacity: 1; transform: none; }
.criterios-card, .hist-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; }
.card-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.card-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.ciclo-badge { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; }
.criterios-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 18px; }
.criterio-item { border: 1px solid #f1f5f9; border-radius: 14px; padding: 14px; }
.crit-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.crit-ico { font-size: 20px; }
.crit-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.crit-peso { display: block; font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
.crit-nota-wrap { margin-left: auto; text-align: center; }
.crit-nota { font-size: 22px; font-weight: 900; }
.crit-max { font-size: 12px; color: #94a3b8; }
.stars { display: flex; gap: 3px; margin-bottom: 8px; }
.star-btn { font-size: 20px; color: #e2e8f0; background: none; border: none; cursor: pointer; padding: 2px; transition: all 0.1s; line-height: 1; }
.star-btn.star-on { color: #f59e0b; }
.star-btn.star-hover { color: #fbbf24; transform: scale(1.1); }
.crit-obs { width: 100%; border: 1px solid #f1f5f9; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-family: inherit; color: #475569; background: #fafafa; outline: none; box-sizing: border-box; }
.crit-obs:focus { border-color: #f59e0b; }
.nota-final-wrap { text-align: center; background: #f8fafc; border-radius: 16px; padding: 16px; margin-bottom: 14px; }
.nf-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
.nf-valor { font-size: 42px; font-weight: 900; line-height: 1; }
.nf-conceito { font-size: 14px; font-weight: 700; margin-top: 4px; }
.submit-btn { width: 100%; padding: 13px; border-radius: 14px; border: none; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245,158,11,0.35); }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spin-sm { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.av-erro { font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; margin-bottom: 8px; }
.av-ok  { font-size: 12px; font-weight: 600; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: 10px; padding: 8px 12px; margin-bottom: 8px; }
.submit-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245,158,11,0.35); }
.av-list { display: flex; flex-direction: column; gap: 12px; }
.av-item { border: 1px solid #f1f5f9; border-radius: 14px; overflow: hidden; }
.av-ciclo-info { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.av-ciclo { font-size: 13px; font-weight: 800; color: #1e293b; }
.av-conceito-badge { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; border: 1px solid; }
.av-criterios-preview { padding: 10px 14px; display: flex; flex-direction: column; gap: 5px; }
.avcp-item { display: flex; align-items: center; gap: 8px; }
.avcp-nome { font-size: 11px; font-weight: 600; color: #64748b; min-width: 80px; }
.avcp-stars { display: flex; }
.avcp-star { font-size: 12px; transition: color 0.1s; }
.avcp-nota { font-size: 11px; font-weight: 800; color: #1e293b; margin-left: auto; }
.av-footer { display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; border-top: 1px solid #f1f5f9; }
.av-nota-final { font-size: 14px; font-weight: 900; }
.av-avaliador { font-size: 11px; color: #94a3b8; }
@media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }
</style>
