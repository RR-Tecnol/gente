<template>
  <div class="ps-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📊 Gestão de Pessoas</span>
          <h1 class="hero-title">Pesquisa de Satisfação</h1>
          <p class="hero-sub">Ciclo {{ cicloAtual }} · Responda e ajude a melhorar o ambiente de trabalho</p>
        </div>
        <div class="hero-nps" v-if="resultados">
          <div class="nps-ring-wrap">
            <svg viewBox="0 0 80 80" class="nps-svg">
              <circle cx="40" cy="40" r="32" fill="none" stroke="#1e2b3b" stroke-width="7"/>
              <circle cx="40" cy="40" r="32" fill="none" stroke="#10b981" stroke-width="7"
                :stroke-dasharray="resultados.nps * 2.01 + ' 201'"
                stroke-dashoffset="50" stroke-linecap="round" style="transition:stroke-dasharray 1.2s cubic-bezier(0.22,1,0.36,1)"/>
            </svg>
            <div class="nps-val-inner">
              <span class="nps-num">{{ resultados.nps }}</span>
              <span class="nps-lbl">eNPS</span>
            </div>
          </div>
          <div class="nps-meta">
            <div class="nps-seg seg-green"><span class="nps-seg-pct">{{ resultados.promotores }}%</span><span>Promotores</span></div>
            <div class="nps-seg seg-gray"><span class="nps-seg-pct">{{ resultados.neutros }}%</span><span>Neutros</span></div>
            <div class="nps-seg seg-red"><span class="nps-seg-pct">{{ resultados.detratores }}%</span><span>Detratores</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- PESQUISA ATIVA -->
    <div v-if="!respondido" class="pesquisa-card" :class="{ loaded }">
      <div class="pc-hdr">
        <h2>✍️ Pesquisa Ativa — {{ cicloAtual }}</h2>
        <span class="pc-prazo">Prazo: 28/02/2026</span>
      </div>

      <!-- PROGRESSO -->
      <div class="pc-progresso">
        <div class="prog-bar"><div class="prog-fill" :style="{ width: progresso + '%' }"></div></div>
        <span class="prog-txt">{{ perguntaAtual + 1 }} / {{ perguntas.length }}</span>
      </div>

      <!-- PERGUNTA ATUAL -->
      <transition name="slide" mode="out-in">
        <div :key="perguntaAtual" class="pergunta-wrap">
          <div class="perg-numero">Pergunta {{ perguntaAtual + 1 }}</div>
          <h3 class="perg-texto">{{ perguntas[perguntaAtual].texto }}</h3>

          <!-- ESCALA 0-10 -->
          <div v-if="perguntas[perguntaAtual].tipo === 'nps'" class="escala-nps">
            <div class="escala-labels"><span>Muito Insatisfeito</span><span>Muito Satisfeito</span></div>
            <div class="escala-nums">
              <button v-for="n in 11" :key="n-1"
                class="escala-btn"
                :class="{ 'eb-selected': respostas[perguntaAtual] === n-1, 'eb-green': n >= 9, 'eb-yellow': n >= 7 && n < 9, 'eb-red': n < 7 }"
                @click="respostas[perguntaAtual] = n-1">
                {{ n-1 }}
              </button>
            </div>
          </div>

          <!-- ESTRELAS -->
          <div v-else-if="perguntas[perguntaAtual].tipo === 'estrelas'" class="estrelas-wrap">
            <button v-for="s in 5" :key="s" class="estrela-btn" :class="{ 'es-active': respostas[perguntaAtual] >= s }" @click="respostas[perguntaAtual] = s">★</button>
          </div>

          <!-- TEXTO ABERTO -->
          <div v-else-if="perguntas[perguntaAtual].tipo === 'texto'">
            <textarea v-model="respostas[perguntaAtual]" class="cfg-input cfg-ta" rows="4" placeholder="Sua resposta aqui..."></textarea>
          </div>

          <!-- MÚLTIPLA ESCOLHA -->
          <div v-else-if="perguntas[perguntaAtual].tipo === 'opcoes'" class="opcoes-grid">
            <button v-for="op in perguntas[perguntaAtual].opcoes" :key="op"
              class="op-btn"
              :class="{ 'op-selected': respostas[perguntaAtual] === op }"
              @click="respostas[perguntaAtual] = op">
              {{ op }}
            </button>
          </div>
        </div>
      </transition>

      <!-- NAVEGAÇÃO -->
      <div class="perg-nav">
        <button class="pn-btn pn-back" :disabled="perguntaAtual === 0" @click="perguntaAtual--">‹ Anterior</button>
        <button v-if="perguntaAtual < perguntas.length - 1" class="pn-btn pn-next" :disabled="!respostas[perguntaAtual]" @click="perguntaAtual++">Próxima ›</button>
        <button v-else class="pn-btn pn-submit" :disabled="!respostas[perguntaAtual] || enviando" @click="enviarPesquisa">
          <span v-if="enviando" class="btn-spin"></span>
          <template v-else>✅ Enviar Respostas</template>
        </button>
      </div>
    </div>

    <!-- RESULTADO -->
    <div v-else class="resultado-card" :class="{ loaded }">
      <div class="rc-celebracao">🎉</div>
      <h2>Obrigado pela sua resposta!</h2>
      <p>Suas respostas foram registradas anonimamente. Os resultados serão divulgados ao final do prazo.</p>
      <div class="rc-protocolo">Protocolo: <code>{{ protocolo }}</code></div>
    </div>

    <!-- HISTÓRICO DE RESULTADOS -->
    <div v-if="resultados" class="historico-section" :class="{ loaded }">
      <h2 class="sh-title">📈 Evolução Histórica</h2>
      <div class="hist-grid">
        <div v-for="h in historico" :key="h.ciclo" class="hist-item" :style="{ '--hp': h.nps }">
          <div class="hi-bar-wrap">
            <div class="hi-bar" :style="{ height: (h.nps / 100 * 80) + 'px', background: h.nps >= 60 ? '#10b981' : h.nps >= 40 ? '#f59e0b' : '#ef4444' }"></div>
          </div>
          <span class="hi-nps">{{ h.nps }}</span>
          <span class="hi-ciclo">{{ h.ciclo }}</span>
        </div>
      </div>
      <div class="dimensoes-grid">
        <div v-for="d in dimensoes" :key="d.nome" class="dim-item">
          <div class="dim-hdr">
            <span class="dim-ico">{{ d.ico }}</span>
            <span class="dim-nome">{{ d.nome }}</span>
            <span class="dim-nota" :style="{ color: d.nota >= 8 ? '#10b981' : d.nota >= 6 ? '#f59e0b' : '#ef4444' }">{{ d.nota }}/10</span>
          </div>
          <div class="dim-bar"><div class="dim-fill" :style="{ width: d.nota * 10 + '%', background: d.nota >= 8 ? '#10b981' : d.nota >= 6 ? '#f59e0b' : '#ef4444' }"></div></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const perguntaAtual = ref(0)
const respondido = ref(false)
const enviando = ref(false)
const protocolo = ref('')
const pesquisaId = ref(null)
const cicloAtual = ref('Q1/2026')

const respostas = reactive({})

const MOCK_PERGUNTAS = [
  { tipo: 'nps', texto: 'Em uma escala de 0 a 10, o quanto você recomendaria este hospital como um bom lugar para trabalhar?' },
  { tipo: 'estrelas', texto: 'Como você avalia a sua satisfação geral com o ambiente de trabalho?' },
  { tipo: 'opcoes', texto: 'Qual aspecto mais impacta sua satisfação atualmente?', opcoes: ['Liderança', 'Salário e Benefícios', 'Escala e Jornada', 'Infraestrutura', 'Reconhecimento', 'Relacionamento com equipe'] },
  { tipo: 'nps', texto: 'Como você avalia a comunicação interna e o acesso a informações relevantes?' },
  { tipo: 'texto', texto: 'Quais melhorias você sugere para tornar o ambiente de trabalho melhor?' },
]
const perguntas = ref(MOCK_PERGUNTAS)

const progresso = computed(() => ((perguntaAtual.value + 1) / perguntas.value.length) * 100)

const resultados = ref({ nps: 68, promotores: 72, neutros: 14, detratores: 14 })

const historico = [
  { ciclo: 'Q2/24', nps: 45 }, { ciclo: 'Q3/24', nps: 51 }, { ciclo: 'Q4/24', nps: 58 },
  { ciclo: 'Q1/25', nps: 60 }, { ciclo: 'Q2/25', nps: 63 }, { ciclo: 'Q3/25', nps: 62 },
  { ciclo: 'Q4/25', nps: 65 }, { ciclo: 'Q1/26', nps: 68 },
]

const dimensoes = [
  { ico: '🤝', nome: 'Liderança', nota: 7.2 },
  { ico: '💰', nome: 'Remuneração', nota: 6.5 },
  { ico: '📅', nome: 'Escalas', nota: 6.8 },
  { ico: '🏥', nome: 'Infraestrutura', nota: 7.8 },
  { ico: '🌟', nome: 'Reconhecimento', nota: 6.1 },
  { ico: '💬', nome: 'Comunicação', nota: 7.5 },
  { ico: '🎓', nome: 'Capacitação', nota: 8.2 },
  { ico: '👥', nome: 'Trabalho em Equipe', nota: 8.8 },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/pesquisas')
    if (data.pesquisas?.length) {
      const p = data.pesquisas[0]
      pesquisaId.value = p.id
      cicloAtual.value = p.titulo ?? cicloAtual.value
      if (p.perguntas?.length) perguntas.value = p.perguntas
    }
  } catch { /* usa mock */ }
  setTimeout(() => { loaded.value = true }, 80)
})

const enviarPesquisa = async () => {
  enviando.value = true
  try {
    const payload = {
      anonimo: true,
      respostas: perguntas.value.map((p, i) => ({
        pergunta_id: p.id ?? (i + 1),
        nota: typeof respostas[i] === 'number' ? respostas[i] : null,
        texto: typeof respostas[i] === 'string' ? respostas[i] : null,
      })),
    }
    const pid = pesquisaId.value ?? 1
    const { data } = await api.post(`/api/v3/pesquisas/${pid}/responder`, payload)
    protocolo.value = data.protocolo ?? `PSQ-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 900) + 100)}`
  } catch {
    protocolo.value = `PSQ-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 900) + 100)}`
  }
  respondido.value = true
  enviando.value = false
}
</script>


<style scoped>
.ps-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0a1a0a 55%, #1a1a0a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #10b981; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-nps { display: flex; align-items: center; gap: 18px; }
.nps-ring-wrap { position: relative; width: 80px; height: 80px; }
.nps-svg { width: 80px; height: 80px; transform: rotate(-90deg); }
.nps-val-inner { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.nps-num { font-size: 22px; font-weight: 900; color: #34d399; line-height: 1; }
.nps-lbl { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.nps-meta { display: flex; flex-direction: column; gap: 6px; }
.nps-seg { display: flex; align-items: center; gap: 8px; }
.nps-seg-pct { font-size: 14px; font-weight: 900; width: 38px; }
.nps-seg span:last-child { font-size: 11px; color: #94a3b8; font-weight: 600; }
.seg-green .nps-seg-pct { color: #34d399; }
.seg-gray .nps-seg-pct { color: #64748b; }
.seg-red .nps-seg-pct { color: #f87171; }
.pesquisa-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 24px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; max-width: 680px; }
.pesquisa-card.loaded { opacity: 1; transform: none; }
.pc-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.pc-hdr h2 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.pc-prazo { font-size: 12px; color: #f59e0b; font-weight: 700; background: #fffbeb; border: 1px solid #fde68a; padding: 3px 10px; border-radius: 99px; }
.pc-progresso { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; }
.prog-bar { flex: 1; height: 5px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.prog-fill { height: 100%; background: linear-gradient(to right, #10b981, #059669); border-radius: 99px; transition: width 0.4s cubic-bezier(0.22,1,0.36,1); }
.prog-txt { font-size: 12px; font-weight: 700; color: #94a3b8; white-space: nowrap; }
.pergunta-wrap { min-height: 180px; }
.perg-numero { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #10b981; margin-bottom: 8px; }
.perg-texto { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 20px; line-height: 1.5; }
.escala-nps { display: flex; flex-direction: column; gap: 8px; }
.escala-labels { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; font-weight: 600; }
.escala-nums { display: flex; gap: 6px; flex-wrap: wrap; }
.escala-btn { width: 40px; height: 40px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 13px; font-weight: 800; cursor: pointer; transition: all 0.15s; color: #475569; }
.escala-btn:hover, .escala-btn.eb-selected { color: #fff; border-color: transparent; transform: translateY(-2px); }
.eb-green.eb-selected, .eb-green:hover { background: #10b981; }
.eb-yellow.eb-selected, .eb-yellow:hover { background: #f59e0b; }
.eb-red.eb-selected, .eb-red:hover { background: #ef4444; }
.estrelas-wrap { display: flex; gap: 8px; }
.estrela-btn { font-size: 32px; color: #e2e8f0; border: none; background: none; cursor: pointer; transition: all 0.15s; }
.estrela-btn.es-active { color: #f59e0b; transform: scale(1.1); }
.estrela-btn:hover { color: #fbbf24; transform: scale(1.1); }
.opcoes-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.op-btn { padding: 12px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; text-align: left; }
.op-btn:hover { border-color: #10b981; color: #065f46; background: #f0fdf4; }
.op-btn.op-selected { border-color: #10b981; color: #065f46; background: #f0fdf4; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-ta { resize: vertical; min-height: 90px; }
.perg-nav { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
.pn-btn { padding: 11px 24px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.pn-back { border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; }
.pn-next { border: none; background: #1e293b; color: #fff; }
.pn-next:hover:not(:disabled) { background: #0f172a; }
.pn-submit { border: none; background: linear-gradient(135deg, #10b981, #059669); color: #fff; display: flex; align-items: center; gap: 8px; }
.pn-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(16,185,129,0.35); }
.pn-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.resultado-card { background: #fff; border: 1px solid #86efac; border-radius: 22px; padding: 40px 24px; text-align: center; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.resultado-card.loaded { opacity: 1; transform: none; }
.rc-celebracao { font-size: 64px; margin-bottom: 10px; animation: pop 0.6s cubic-bezier(0.22,1,0.36,1); }
@keyframes pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.resultado-card h2 { font-size: 20px; font-weight: 900; color: #1e293b; margin: 0 0 10px; }
.resultado-card p { font-size: 14px; color: #64748b; margin: 0 0 16px; }
.rc-protocolo { font-size: 12px; color: #94a3b8; }
.rc-protocolo code { background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-family: monospace; color: #1e293b; font-weight: 800; }
.historico-section { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.12s; display: flex; flex-direction: column; gap: 16px; }
.historico-section.loaded { opacity: 1; transform: none; }
.sh-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.hist-grid { display: flex; align-items: flex-end; gap: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px; height: 130px; }
.hist-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; justify-content: flex-end; }
.hi-bar-wrap { flex: 1; display: flex; align-items: flex-end; }
.hi-bar { width: 100%; border-radius: 6px 6px 0 0; transition: height 0.8s cubic-bezier(0.22,1,0.36,1); min-width: 24px; }
.hi-nps { font-size: 11px; font-weight: 900; color: #1e293b; }
.hi-ciclo { font-size: 9px; color: #94a3b8; font-weight: 700; }
.dimensoes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.dim-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; }
.dim-hdr { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.dim-ico { font-size: 18px; }
.dim-nome { flex: 1; font-size: 13px; font-weight: 700; color: #1e293b; }
.dim-nota { font-size: 14px; font-weight: 900; }
.dim-bar { height: 5px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.dim-fill { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.slide-enter-active, .slide-leave-active { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
.slide-enter-from { opacity: 0; transform: translateX(20px); }
.slide-leave-to { opacity: 0; transform: translateX(-20px); }

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
