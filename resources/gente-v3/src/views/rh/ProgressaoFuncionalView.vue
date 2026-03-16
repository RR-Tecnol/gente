<template>
  <div class="pf-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📈 Carreira</span>
          <h1 class="hero-title">Progressão Funcional</h1>
          <p class="hero-sub">Evolução salarial, triênios, quinquênios e enquadramento</p>
        </div>
        <div class="hero-prog-wrap">
          <div class="hp-nivel">
            <span class="hp-nivel-label">Nível Atual</span>
            <span class="hp-nivel-val">{{ nivelAtual }}</span>
          </div>
          <div class="hp-prox">
            <span class="hp-prox-label">Próxima Progressão</span>
            <span class="hp-prox-val">{{ proximaProgressao }}</span>
            <div class="hp-pct-bar">
              <div class="hp-pct-fill" :style="{ width: pctParaProxima + '%' }"></div>
            </div>
            <span class="hp-pct-txt">{{ pctParaProxima }}% do período cumprido</span>
          </div>
          <!-- Badge de elegibilidade -->
          <div class="hp-eleg" :class="elegibilidade.elegivel ? 'he-ok' : 'he-block'">
            <span class="he-ico">{{ elegibilidade.elegivel ? '✅' : '🔒' }}</span>
            <span class="he-txt">{{ elegibilidade.elegivel ? 'Elegível' : 'Bloqueado' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- RESUMO FINANCEIRO -->
    <div class="fin-cards" :class="{ loaded }">
      <div class="fc-card fc-blue">
        <span class="fc-ico">💰</span>
        <div><span class="fc-label">Salário Base</span><span class="fc-val">R$ {{ fmtMoeda(salarioBase) }}</span></div>
      </div>
      <div class="fc-card fc-teal">
        <span class="fc-ico">📅</span>
        <div><span class="fc-label">Adicional Triênio</span><span class="fc-val">R$ {{ fmtMoeda(addTrienio) }}</span></div>
      </div>
      <div class="fc-card fc-purple">
        <span class="fc-ico">🏅</span>
        <div><span class="fc-label">Adicional Quinquênio</span><span class="fc-val">R$ {{ fmtMoeda(addQuinquenio) }}</span></div>
      </div>
      <div class="fc-card fc-green">
        <span class="fc-ico">📊</span>
        <div><span class="fc-label">Remuneração Total</span><span class="fc-val">R$ {{ fmtMoeda(salarioBase + addTrienio + addQuinquenio) }}</span></div>
      </div>
    </div>

    <!-- BLOQUEIOS DE ELEGIBILIDADE (se houver) -->
    <div v-if="elegibilidade.bloqueios?.length" class="bloqueios-card" :class="{ loaded }">
      <div class="bloq-hdr">
        <span class="bloq-ico">🔒</span>
        <div>
          <strong>Progressão bloqueada</strong>
          <p>Resolva os pontos abaixo para ser elegível à próxima progressão:</p>
        </div>
      </div>
      <ul class="bloq-lista">
        <li v-for="b in elegibilidade.bloqueios" :key="b">{{ b }}</li>
      </ul>
    </div>
    <div v-else-if="elegibilidade.elegivel" class="ok-card" :class="{ loaded }">
      ✅ <strong>Você está elegível para progressão!</strong> Entre em contato com o RH para solicitar a aplicação.
    </div>

    <!-- TIMELINE DE PROGRESSÕES -->
    <div class="timeline-card" :class="{ loaded }">
      <h2 class="tc-title">📅 Histórico de Progressões</h2>
      <div class="prog-timeline">
        <div v-for="(p, i) in progressoes" :key="p.id" class="pt-item" :class="{ 'pt-future': p.futura, 'pt-ativa': p.ativa }" :style="{ '--pi': i }">
          <div class="pt-marcador" :class="{ 'pm-ativo': p.ativa, 'pm-futuro': p.futura }">
            <span v-if="!p.futura">{{ p.ativa ? '📍' : '✅' }}</span>
            <span v-else>⏳</span>
          </div>
          <div class="pt-card">
            <div class="pt-hdr">
              <div>
                <span class="pt-nivel">{{ p.nivel }}</span>
                <span class="pt-referencia">{{ p.tipo }}</span>
              </div>
              <div class="pt-right">
                <span class="pt-salario">R$ {{ fmtMoeda(p.salario) }}</span>
                <span class="pt-badge" :class="p.futura ? 'pb-future' : p.ativa ? 'pb-ativo' : 'pb-done'">
                  {{ p.futura ? 'Futuro' : p.ativa ? 'Atual' : 'Concluído' }}
                </span>
              </div>
            </div>
            <div class="pt-body">
              <span class="pt-data">{{ formatDate(p.data) }}</span>
              <span class="pt-reajuste" v-if="!p.futura && p.reajuste > 0">+{{ p.reajuste }}%</span>
            </div>
            <div class="pt-obs" v-if="p.obs">{{ p.obs }}</div>
          </div>
        </div>
      </div>
      <div v-if="!progressoes.length" class="empty-timeline">Nenhuma progressão registrada ainda. O RH irá registrar ao aplicar a primeira progressão.</div>
    </div>

    <!-- ADICIONAIS -->
    <div class="adicionais-grid" :class="{ loaded }">
      <!-- Triênios -->
      <div class="ad-card">
        <div class="ad-hdr">
          <span class="ad-ico">📅</span>
          <h3 class="ad-title">Triênios (3% cada)</h3>
        </div>
        <div class="ad-list">
          <div v-for="t in trienios" :key="t.periodo" class="ad-item" :class="{ 'ai-ativo': t.ativo }">
            <div class="ai-info">
              <span class="ai-nome">{{ t.periodo }}° Triênio</span>
              <span class="ai-datas">{{ formatDate(t.inicio) }} → {{ t.ativo ? 'Vigente' : formatDate(t.fim) }}</span>
            </div>
            <span class="ai-pct">+{{ t.pct }}%</span>
            <span class="ai-badge" :class="t.ativo ? 'aib-green' : 'aib-gray'">{{ t.ativo ? 'Ativo' : 'Encerrado' }}</span>
          </div>
        </div>
      </div>
      <!-- Quinquênios -->
      <div class="ad-card">
        <div class="ad-hdr">
          <span class="ad-ico">🏅</span>
          <h3 class="ad-title">Quinquênios (5% cada)</h3>
        </div>
        <div class="ad-list">
          <div v-for="q in quinquenios" :key="q.periodo" class="ad-item" :class="{ 'ai-ativo': q.ativo, 'ai-futuro': q.futuro }">
            <div class="ai-info">
              <span class="ai-nome">{{ q.periodo }}° Quinquênio</span>
              <span class="ai-datas">{{ q.futuro ? 'A partir de ' + formatDate(q.inicio) : formatDate(q.inicio) + ' → ' + (q.ativo ? 'Vigente' : formatDate(q.fim)) }}</span>
            </div>
            <span class="ai-pct">+{{ q.pct }}%</span>
            <span class="ai-badge" :class="q.futuro ? 'aib-purple' : q.ativo ? 'aib-green' : 'aib-gray'">{{ q.futuro ? 'Futuro' : q.ativo ? 'Ativo' : 'Encerrado' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const elegibilidade = ref({ elegivel: false, elegivel_promocao: false, bloqueios: [], meses_na_referencia: 0 })

// ── Dados dinâmicos: calculados a partir do backend ──────────
const progressoes = ref([])
const trienios    = ref([])
const quinquenios = ref([])
const admissaoReal = ref(null)
const salarioBaseReal = ref(0)

// ── Cálculo automático de triênios / quinquênios ─────────────
const calcAdicionais = (admissao) => {
  const admDt = new Date(admissao + 'T12:00:00')
  const agora = new Date()

  const tri = []
  for (let i = 1; i <= 4; i++) {
    const ini = new Date(admDt)
    ini.setFullYear(ini.getFullYear() + (i - 1) * 3)
    const fim = new Date(ini)
    fim.setFullYear(fim.getFullYear() + 3)
    const ativo    = ini <= agora && fim > agora
    const encerrado = fim <= agora
    tri.push({
      periodo: i,
      inicio: ini.toISOString().slice(0, 10),
      fim:    encerrado ? fim.toISOString().slice(0, 10) : null,
      pct: 3, ativo,
      futuro: !ativo && !encerrado,
    })
  }
  trienios.value = tri

  const qui = []
  for (let i = 1; i <= 3; i++) {
    const ini = new Date(admDt)
    ini.setFullYear(ini.getFullYear() + (i - 1) * 5)
    const fim = new Date(ini)
    fim.setFullYear(fim.getFullYear() + 5)
    const ativo    = ini <= agora && fim > agora
    const encerrado = fim <= agora
    qui.push({
      periodo: i,
      inicio: ini.toISOString().slice(0, 10),
      fim:    encerrado ? fim.toISOString().slice(0, 10) : null,
      pct: 5, ativo,
      futuro: !ativo && !encerrado,
    })
  }
  quinquenios.value = qui
}

// ── Mapeamento de progressões do backend → formato Vue ───────
const mapProgressao = (p, idx) => ({
  id:        p.id         ?? p.PROGRESSAO_ID ?? idx,
  nivel:     p.nivel      ?? p.PROGRESSAO_NIVEL      ?? '—',
  referencia: p.referencia ?? p.PROGRESSAO_REFERENCIA ?? '—',
  salario:   Number(p.salario ?? p.PROGRESSAO_SALARIO ?? 0),
  data:      p.data       ?? p.PROGRESSAO_DATA         ?? null,
  tipo:      p.tipo       ?? p.PROGRESSAO_TIPO         ?? 'Progressão',
  reajuste:  Number(p.reajuste ?? p.PROGRESSAO_REAJUSTE ?? 0),
  obs:       p.obs        ?? p.PROGRESSAO_OBS          ?? null,
  ativa:     !!(p.ativa   ?? p.PROGRESSAO_ATIVA        ?? false),
  futura:    !!(p.futura  ?? p.PROGRESSAO_FUTURA       ?? false),
})

const mockProgressoes = [
  { id: 1, nivel: 'Inicial — Ref. A-I',   tipo: 'Admissão',              salario: 4800, data: '2021-03-15', reajuste: 0,    obs: 'Ingresso como Servidor Efetivo — Concurso Público 001/2020', ativa: false, futura: false },
  { id: 2, nivel: 'Médio — Ref. A-II',    tipo: 'Progressão por Mérito', salario: 5500, data: '2023-03-15', reajuste: 14.6, obs: null,                                                          ativa: true,  futura: false },
  { id: 3, nivel: 'Superior — Ref. A-III',tipo: 'Progressão por Mérito', salario: 6250, data: '2025-03-15', reajuste: 13.6, obs: null,                                                          ativa: false, futura: true  },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/progressao-funcional')

    if (data.fallback) {
      // backend retornou fallback — usa mock
      progressoes.value = mockProgressoes
      calcAdicionais('2021-03-15')
      salarioBaseReal.value = 5500
    } else {
      // Dados reais do backend
      progressoes.value = data.progressoes?.length ? data.progressoes : mockProgressoes
      elegibilidade.value = data.elegibilidade ?? elegibilidade.value
      const admissao = data.admissao || '2021-03-15'
      admissaoReal.value = admissao
      calcAdicionais(admissao)
      salarioBaseReal.value = data.vencimento_base || data.salario_base || 0
      if (!salarioBaseReal.value) {
        const ativa = progressoes.value.find(p => p.ativa)
        salarioBaseReal.value = ativa?.salario ?? 5500
      }
    }
  } catch {
    progressoes.value = mockProgressoes
    calcAdicionais('2021-03-15')
    salarioBaseReal.value = 5500
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

// ── Computed dinâmicos ────────────────────────────────────────
const nivelAtual = computed(() => {
  const p = progressoes.value.find(p => p.ativa)
  return p ? `${p.nivel} — Ref. ${p.referencia}` : '—'
})

const proximaProgressao = computed(() => {
  const prox = progressoes.value.find(p => p.futura)
  if (!prox?.data) return 'A definir'
  return new Date(prox.data + 'T12:00:00').toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' })
})

const pctParaProxima = computed(() => {
  const atual = progressoes.value.find(p => p.ativa)
  const prox  = progressoes.value.find(p => p.futura)
  if (!atual?.data || !prox?.data) return 72
  const inicio = new Date(atual.data + 'T12:00:00')
  const fim    = new Date(prox.data  + 'T12:00:00')
  const agora  = new Date()
  const total  = fim - inicio
  const passado = agora - inicio
  return Math.min(100, Math.max(0, Math.round(passado / total * 100)))
})

const salarioBase = computed(() => salarioBaseReal.value)
const addTrienio  = computed(() => salarioBase.value * 0.03 * trienios.value.filter(t => t.ativo || !t.futuro).length)
const addQuinquenio = computed(() => salarioBase.value * 0.05 * quinquenios.value.filter(q => q.ativo && !q.futuro).length)

const fmtMoeda  = (v) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(v)
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.pf-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a102a 55%, #0e2a0e 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #a855f7; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #c084fc; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-prog-wrap { display: flex; gap: 14px; flex-wrap: wrap; }
.hp-nivel { background: rgba(168,85,247,0.15); border: 1px solid rgba(168,85,247,0.3); border-radius: 14px; padding: 12px 20px; text-align: center; }
.hp-nivel-label { display: block; font-size: 10px; font-weight: 700; color: #c084fc; text-transform: uppercase; margin-bottom: 4px; }
.hp-nivel-val { display: block; font-size: 18px; font-weight: 900; color: #fff; }
.hp-prox { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 12px 20px; min-width: 200px; }
.hp-prox-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; mb: 4px; }
.hp-prox-val { display: block; font-size: 16px; font-weight: 900; color: #fff; margin: 4px 0 10px; }
.hp-pct-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 99px; overflow: hidden; margin-bottom: 5px; }
.hp-pct-fill { height: 100%; background: linear-gradient(to right, #a855f7, #c084fc); border-radius: 99px; transition: width 1s cubic-bezier(0.22,1,0.36,1); }
.hp-pct-txt { font-size: 11px; color: #c084fc; font-weight: 700; }
.fin-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 185px), 1fr)); gap: 12px; width: 100%; box-sizing: border-box; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.fin-cards.loaded { opacity: 1; transform: none; }
.fc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-top: 3px solid; }
.fc-ico { font-size: 24px; }
.fc-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.fc-val { display: block; font-size: 16px; font-weight: 900; }
.fc-blue { border-top-color: #3b82f6; } .fc-blue .fc-val { color: #1d4ed8; }
.fc-teal { border-top-color: #0d9488; } .fc-teal .fc-val { color: #0d9488; }
.fc-purple { border-top-color: #a855f7; } .fc-purple .fc-val { color: #7e22ce; }
.fc-green { border-top-color: #10b981; } .fc-green .fc-val { color: #065f46; }
.timeline-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.10s; }
.timeline-card.loaded { opacity: 1; transform: none; }
.tc-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 18px; }
.prog-timeline { display: flex; flex-direction: column; gap: 0; padding-left: 16px; border-left: 2px solid #e2e8f0; }
.pt-item { position: relative; padding-bottom: 16px; animation: ptIn 0.4s cubic-bezier(0.22,1,0.36,1) calc(var(--pi) * 80ms) both; }
@keyframes ptIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.pt-marcador { position: absolute; left: -24px; top: 8px; width: 14px; height: 14px; border-radius: 50%; background: #e2e8f0; border: 3px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; font-size: 10px; display: flex; align-items: center; justify-content: center; }
.pm-ativo { background: #a855f7; box-shadow: 0 0 0 2px #a855f7; width: 18px; height: 18px; left: -26px; }
.pm-futuro { background: #e2e8f0; }
.pt-card { margin-left: 12px; border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px 14px; transition: all 0.15s; }
.pt-ativa .pt-card { border-color: #e9d5ff; background: #faf5ff; }
.pt-future .pt-card { border-style: dashed; opacity: 0.7; }
.pt-hdr { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px; gap: 10px; }
.pt-nivel { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.pt-referencia { display: block; font-size: 11px; color: #94a3b8; font-weight: 600; }
.pt-right { text-align: right; }
.pt-salario { display: block; font-size: 16px; font-weight: 900; color: #1e293b; font-family: monospace; }
.pt-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 99px; margin-top: 3px; }
.pb-done { background: #f1f5f9; color: #64748b; }
.pb-ativo { background: #f3e8ff; color: #7e22ce; }
.pb-future { background: #f8fafc; color: #94a3b8; border: 1px dashed #e2e8f0; }
.pt-body { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pt-data { font-size: 12px; color: #94a3b8; }
.pt-tipo { font-size: 12px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 6px; padding: 2px 8px; }
.pt-reajuste { font-size: 12px; font-weight: 800; color: #10b981; }
.pt-obs { font-size: 12px; color: #64748b; font-style: italic; margin-top: 6px; }
.adicionais-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.adicionais-grid.loaded { opacity: 1; transform: none; }
.ad-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; }
.ad-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.ad-ico { font-size: 22px; }
.ad-title { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0; }
.ad-list { display: flex; flex-direction: column; gap: 8px; }
.ad-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #f1f5f9; border-radius: 12px; }
.ai-ativo { border-color: #e9d5ff; background: #faf5ff; }
.ai-futuro { opacity: 0.6; border-style: dashed; }
.ai-info { flex: 1; }
.ai-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.ai-datas { display: block; font-size: 11px; color: #94a3b8; }
.ai-pct { font-size: 15px; font-weight: 900; color: #1e293b; font-family: monospace; }
.ai-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.aib-green { background: #dcfce7; color: #166534; }
.aib-gray { background: #f1f5f9; color: #64748b; }
.aib-purple { background: #f3e8ff; color: #7e22ce; }
@media (max-width: 768px) {
  .adicionais-grid { grid-template-columns: 1fr 1fr; }
  .fin-cards { grid-template-columns: repeat(2, 1fr) !important; }
  .hero-prog-wrap { flex-direction: column; width: 100%; }
  .hp-prox { width: 100%; }
}
@media (max-width: 480px) {
  .adicionais-grid { grid-template-columns: 1fr; }
  .fin-cards { grid-template-columns: repeat(2, 1fr) !important; }
  .hero-title { font-size: 20px !important; }
}
/* ── Badge de elegibilidade no hero ──────────────────────────── */
.hp-eleg { display: flex; flex-direction: column; align-items: center; gap: 4px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 12px 18px; }
.he-ok { border-color: rgba(110,231,183,0.4); background: rgba(110,231,183,0.1); }
.he-block { border-color: rgba(252,165,165,0.4); background: rgba(252,165,165,0.1); }
.he-ico { font-size: 20px; }
.he-txt { font-size: 11px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.05em; }
/* ── Bloqueios e OK cards ────────────────────────────────────── */
.bloqueios-card { background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 16px; padding: 16px 18px; opacity: 0; transform: translateY(6px); transition: all 0.35s 0.08s; }
.bloqueios-card.loaded { opacity: 1; transform: none; }
.bloq-hdr { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.bloq-ico { font-size: 22px; }
.bloq-hdr strong { font-size: 14px; font-weight: 800; color: #991b1b; }
.bloq-hdr p { font-size: 12px; color: #b91c1c; margin: 2px 0 0; }
.bloq-lista { margin: 0; padding: 0 0 0 18px; display: flex; flex-direction: column; gap: 5px; }
.bloq-lista li { font-size: 13px; color: #7f1d1d; }
.ok-card { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 16px; padding: 14px 18px; font-size: 14px; color: #166534; opacity: 0; transform: translateY(6px); transition: all 0.35s 0.08s; }
.ok-card.loaded { opacity: 1; transform: none; }
.empty-timeline { font-size: 13px; color: #94a3b8; text-align: center; padding: 20px 0; font-style: italic; }
</style>
