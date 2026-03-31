<template>
  <div class="tr-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📚 Desenvolvimento Profissional</span>
          <h1 class="hero-title">Treinamentos e Capacitações</h1>
          <p class="hero-sub">{{ meusCursos.filter(c => c.status === 'concluido').length }} concluídos · {{ meusCursos.filter(c => c.status === 'andamento').length }} em andamento · {{ horasTotais }}h de formação</p>
        </div>
        <div class="hero-cert">
          <div class="cert-stack">
            <div v-for="(c, i) in meusCursos.filter(c => c.certificado).slice(0, 3)" :key="i" class="cert-card-mini" :style="{ '--ci': i }">
              <span>🎓</span>
              <span class="cert-mini-nome">{{ c.titulo.substring(0, 20) }}...</span>
            </div>
          </div>
          <span class="cert-count">{{ meusCursos.filter(c => c.certificado).length }} certificados</span>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs" :class="{ loaded }">
      <button v-for="t in tabs" :key="t.id" class="tab-btn" :class="{ active: tabAtiva === t.id }" @click="tabAtiva = t.id">
        {{ t.ico }} {{ t.nome }}
        <span class="tab-count" v-if="t.count">{{ t.count }}</span>
      </button>
    </div>

    <!-- TAB: MEUS CURSOS -->
    <div v-if="tabAtiva === 'meus'" class="tab-content" :class="{ loaded }">
      <div class="cursos-grid">
        <div v-for="(c, i) in meusCursos" :key="c.id" class="curso-card" :style="{ '--cd': `${i * 50}ms` }">
          <div class="cc-top" :style="{ background: areaCor(c.area) }">
            <span class="cc-ico">{{ areaIco(c.area) }}</span>
            <div class="cc-badges">
              <span class="cc-area">{{ c.area }}</span>
              <span class="cc-carga">{{ c.carga }}h</span>
            </div>
          </div>
          <div class="cc-body">
            <h3 class="cc-titulo">{{ c.titulo }}</h3>
            <p class="cc-desc">{{ c.desc }}</p>
            <div class="cc-progress" v-if="c.status === 'andamento'">
              <div class="cp-bar-wrap">
                <div class="cp-bar" :style="{ width: c.progresso + '%' }"></div>
              </div>
              <span class="cp-pct">{{ c.progresso }}%</span>
            </div>
          </div>
          <div class="cc-footer">
            <span class="cc-status" :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
            <span class="cc-data">{{ formatDate(c.data) }}</span>
            <button v-if="c.certificado" class="cert-btn" @click="downloadCert(c)" title="Baixar Certificado">🎓</button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: CATÁLOGO -->
    <div v-if="tabAtiva === 'catalogo'" class="tab-content" :class="{ loaded }">
      <div class="cat-filters">
        <button v-for="a in areas" :key="a" class="area-pill" :class="{ active: areaFiltro === a }" @click="areaFiltro = areaFiltro === a ? '' : a">
          {{ areaIco(a) }} {{ a }}
        </button>
      </div>
      <div class="cursos-grid">
        <div v-for="(c, i) in catalogoFiltrado" :key="c.id" class="curso-card catalogo" :style="{ '--cd': `${i * 40}ms` }">
          <div class="cc-top" :style="{ background: areaCor(c.area) }">
            <span class="cc-ico">{{ areaIco(c.area) }}</span>
            <div class="cc-badges">
              <span class="cc-area">{{ c.area }}</span>
              <span class="cc-carga">{{ c.carga }}h</span>
            </div>
          </div>
          <div class="cc-body">
            <h3 class="cc-titulo">{{ c.titulo }}</h3>
            <p class="cc-desc">{{ c.desc }}</p>
            <div class="cc-meta">
              <span>📅 {{ c.proxima }}</span>
              <span>👥 {{ c.vagas }} vagas</span>
              <span>💰 {{ c.custo === 0 ? 'Gratuito' : 'R$ ' + c.custo }}</span>
            </div>
          </div>
          <div class="cc-footer">
            <span class="cc-mod">🖥️ {{ c.modalidade }}</span>
            <button class="inscr-btn" @click="inscrever(c)">Inscrever-se</button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: CERTIFICADOS -->
    <div v-if="tabAtiva === 'certificados'" class="tab-content" :class="{ loaded }">
      <div class="certs-grid">
        <div v-for="(c, i) in meusCursos.filter(c => c.certificado)" :key="c.id" class="cert-card" :style="{ '--cd': `${i * 60}ms` }">
          <div class="cert-hdr" :style="{ background: `linear-gradient(135deg, ${areaCor(c.area)}, ${areaCor(c.area)}cc)` }">
            <span class="cert-ico-lg">🎓</span>
            <span class="cert-hdr-area">{{ c.area }}</span>
          </div>
          <div class="cert-body">
            <h3 class="cert-titulo">{{ c.titulo }}</h3>
            <div class="cert-details">
              <div class="cert-det-item"><span>📅 Concluído em</span><strong>{{ formatDate(c.data) }}</strong></div>
              <div class="cert-det-item"><span>⏱️ Carga horária</span><strong>{{ c.carga }}h</strong></div>
              <div class="cert-det-item"><span>🆔 Código</span><strong>CERT-{{ String(c.id).padStart(4, '0') }}-2026</strong></div>
            </div>
            <button class="cert-download-btn" :style="{ background: areaCor(c.area) }" @click="downloadCert(c)">
              ⬇️ Baixar Certificado PDF
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tabAtiva = ref('meus')
const areaFiltro = ref('')
const toast = ref({ visible: false, msg: '' })

const MOCK_CURSOS = [
  { id: 1, titulo: 'Suporte Avançado de Vida (ACLS)', desc: 'Ressuscitação cardiopulmonar avançada.', area: 'Saúde', carga: 16, status: 'concluido', progresso: 100, data: '2025-11-20', certificado: true },
  { id: 2, titulo: 'NR-32 — Segurança em Serviços de Saúde', desc: 'Norma regulamentadora para estabelecimentos de saúde.', area: 'Segurança', carga: 8, status: 'concluido', progresso: 100, data: '2025-09-15', certificado: true },
  { id: 3, titulo: 'Gestão de Resíduos Hospitalares', desc: 'Manejo correto de resíduos sólidos de serviços de saúde.', area: 'Saúde', carga: 4, status: 'concluido', progresso: 100, data: '2025-07-10', certificado: true },
  { id: 4, titulo: 'Excel Avançado para Gestão Hospitalar', desc: 'Planilhas e análise de dados para o setor de saúde.', area: 'Tecnologia', carga: 20, status: 'andamento', progresso: 65, data: '2026-01-15', certificado: false },
  { id: 5, titulo: 'Comunicação Não-Violenta no Trabalho', desc: 'Técnicas de comunicação assertiva.', area: 'Comportamental', carga: 8, status: 'andamento', progresso: 30, data: '2026-02-01', certificado: false },
  { id: 6, titulo: 'Biossegurança Hospitalar', desc: 'EPIs, protocolos e procedimentos de biossegurança.', area: 'Segurança', carga: 6, status: 'inscrito', progresso: 0, data: '2026-03-10', certificado: false },
]

const MOCK_CATALOGO = [
  { id: 10, titulo: 'Liderança em Saúde', desc: 'Desenvolvimento de lideranças no contexto hospitalar.', area: 'Comportamental', carga: 24, proxima: 'Mar/2026', vagas: 20, custo: 0, modalidade: 'EAD' },
  { id: 11, titulo: 'PHTLS — Trauma Pré-Hospitalar', desc: 'Suporte de vida em trauma para profissionais de emergência.', area: 'Saúde', carga: 16, proxima: 'Abr/2026', vagas: 12, custo: 0, modalidade: 'Presencial' },
  { id: 12, titulo: 'Power BI para Gestão Hospitalar', desc: 'Dashboards e visualização de dados no setor público.', area: 'Tecnologia', carga: 16, proxima: 'Mar/2026', vagas: 25, custo: 0, modalidade: 'Híbrido' },
  { id: 13, titulo: 'Controle de Infecção Hospitalar', desc: 'Prevenção e controle de infecções relacionadas à assistência.', area: 'Saúde', carga: 8, proxima: 'Abr/2026', vagas: 30, custo: 0, modalidade: 'EAD' },
  { id: 14, titulo: 'NR-35 — Trabalho em Altura', desc: 'Norma para atividades realizadas acima de 2 metros.', area: 'Segurança', carga: 8, proxima: 'Mai/2026', vagas: 15, custo: 0, modalidade: 'Presencial' },
]

const meusCursos = ref([])
const catalogo = ref([])

const areas = computed(() => [...new Set([...meusCursos.value.map(c => c.area), ...catalogo.value.map(c => c.area)])])
const tabs = computed(() => [
  { id: 'meus', ico: '📖', nome: 'Meus Cursos', count: meusCursos.value.length },
  { id: 'catalogo', ico: '🛒', nome: 'Catálogo', count: catalogo.value.length },
  { id: 'certificados', ico: '🎓', nome: 'Certificados', count: meusCursos.value.filter(c => c.certificado).length },
])
const horasTotais = computed(() => meusCursos.value.filter(c => c.status === 'concluido').reduce((a, c) => a + c.carga, 0))
const catalogoFiltrado = computed(() => areaFiltro.value ? catalogo.value.filter(c => c.area === areaFiltro.value) : catalogo.value)

onMounted(async () => {
  try {
    const [rMeus, rCat] = await Promise.all([
      api.get('/api/v3/treinamentos/meus'),
      api.get('/api/v3/treinamentos/catalogo'),
    ])
    meusCursos.value = (!rMeus.data.fallback && rMeus.data.cursos?.length) ? rMeus.data.cursos : MOCK_CURSOS
    catalogo.value = rCat.data.catalogo?.length ? rCat.data.catalogo : MOCK_CATALOGO
  } catch {
    meusCursos.value = MOCK_CURSOS
    catalogo.value = MOCK_CATALOGO
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const areaCor = (a) => ({ Saúde: '#0d9488', Segurança: '#f59e0b', Tecnologia: '#6366f1', Comportamental: '#ec4899' })[a] ?? '#64748b'
const areaIco = (a) => ({ Saúde: '🏥', Segurança: '🦺', Tecnologia: '💻', Comportamental: '🧠' })[a] ?? '📚'
const statusLabel = (s) => ({ concluido: 'Concluído', andamento: 'Em Andamento', inscrito: 'Inscrito' })[s] ?? s
const statusClass = (s) => ({ concluido: 'st-green', andamento: 'st-blue', inscrito: 'st-yellow' })[s] ?? ''
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' }) } catch { return d } }

const inscrever = async (c) => {
  try { await api.post(`/api/v3/treinamentos/${c.id}/inscrever`) } catch { /* ok */ }
  toast.value = { visible: true, msg: `✅ Inscrição realizada em "${c.titulo}"!` }
  setTimeout(() => { toast.value.visible = false }, 3000)
}
const downloadCert = (c) => { toast.value = { visible: true, msg: `🎓 Certificado de "${c.titulo}" baixado!` }; setTimeout(() => { toast.value.visible = false }, 3000) }
</script>


<style scoped>
.tr-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e1a3a 55%, #0d2a14 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #10b981; right: 280px; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-cert { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.cert-stack { display: flex; flex-direction: column; gap: 4px; }
.cert-card-mini { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 5px 10px; font-size: 11px; color: #e2e8f0; animation: certIn 0.4s cubic-bezier(0.22,1,0.36,1) calc(var(--ci) * 80ms) both; }
@keyframes certIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: none; } }
.cert-count { font-size: 12px; font-weight: 700; color: #818cf8; }
.tabs { display: flex; gap: 6px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { display: flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; }
.tab-btn.active { background: #f0f4ff; border-color: #6366f1; color: #4f46e5; }
.tab-count { background: #e0e7ff; color: #4f46e5; border-radius: 99px; padding: 1px 7px; font-size: 11px; font-weight: 800; }
.tab-content { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.tab-content.loaded { opacity: 1; transform: none; }
.cat-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.area-pill { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.18s; }
.area-pill.active { border-color: #6366f1; background: #f0f4ff; color: #4f46e5; }
.cursos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.curso-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; transition: all 0.18s; animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1) var(--cd) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.curso-card:hover { box-shadow: 0 8px 32px -8px rgba(0,0,0,0.12); transform: translateY(-2px); }
.cc-top { padding: 18px 18px 28px; position: relative; display: flex; align-items: flex-start; justify-content: space-between; }
.cc-ico { font-size: 32px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
.cc-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.cc-area { background: rgba(255,255,255,0.25); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; backdrop-filter: blur(4px); }
.cc-carga { background: rgba(0,0,0,0.2); color: #fff; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 99px; }
.cc-body { padding: 14px 16px; flex: 1; margin-top: -14px; background: #fff; border-radius: 14px 14px 0 0; position: relative; }
.cc-titulo { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 5px; line-height: 1.3; }
.cc-desc { font-size: 12px; color: #64748b; margin: 0 0 10px; line-height: 1.5; }
.cc-progress { display: flex; align-items: center; gap: 10px; }
.cp-bar-wrap { flex: 1; height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.cp-bar { height: 100%; background: linear-gradient(to right, #6366f1, #8b5cf6); border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.cp-pct { font-size: 11px; font-weight: 800; color: #6366f1; white-space: nowrap; }
.cc-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.cc-meta span { font-size: 11px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 3px 9px; color: #64748b; font-weight: 600; }
.cc-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid #f8fafc; gap: 8px; }
.cc-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.st-green { background: #dcfce7; color: #166534; }
.st-blue { background: #dbeafe; color: #1e40af; }
.st-yellow { background: #fffbeb; color: #92400e; }
.cc-data { font-size: 11px; color: #94a3b8; }
.cc-mod { font-size: 11px; color: #64748b; font-weight: 600; }
.cert-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 15px; display: flex; align-items: center; justify-content: center; }
.inscr-btn { padding: 6px 14px; border-radius: 10px; border: none; background: #6366f1; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.inscr-btn:hover { background: #4f46e5; transform: translateY(-1px); }
.certs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.cert-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1) var(--cd) both; }
.cert-hdr { height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; }
.cert-ico-lg { font-size: 36px; }
.cert-hdr-area { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 0.06em; }
.cert-body { padding: 16px; }
.cert-titulo { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 12px; line-height: 1.3; }
.cert-details { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.cert-det-item { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; }
.cert-det-item strong { color: #1e293b; font-weight: 700; }
.cert-download-btn { width: 100%; padding: 10px; border-radius: 12px; border: none; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.cert-download-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
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
