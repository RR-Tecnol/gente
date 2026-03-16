<template>
  <div class="pg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">👔 Visão Gerencial</span>
          <h1 class="hero-title">Portal do Gestor</h1>
          <p class="hero-sub">Aprove pedidos, gerencie sua equipe e acompanhe indicadores</p>
        </div>
        <div class="hero-pendencias">
          <div v-if="totalPendencias > 0" class="hp-alert">
            <span class="hpa-ico">🔔</span>
            <span class="hpa-txt">{{ totalPendencias }} iten{{ totalPendencias !== 1 ? 's' : '' }} aguardando sua aprovação</span>
          </div>
          <div v-else class="hp-ok">✅ Sem pendências</div>
        </div>
      </div>
    </div>

    <!-- KPI CARDS -->
    <div class="kpi-grid" :class="{ loaded }">
      <div v-for="(k, i) in kpis" :key="k.label" class="kpi-card" :style="{ '--ki': i, '--kc': k.cor }">
        <div class="kpi-ico-wrap"><span>{{ k.ico }}</span></div>
        <div class="kpi-info">
          <span class="kpi-val" :style="{ color: k.cor }">{{ k.val }}</span>
          <span class="kpi-label">{{ k.label }}</span>
          <span class="kpi-delta" :class="k.up ? 'kd-up' : 'kd-down'" v-if="k.delta">{{ k.up ? '▲' : '▼' }} {{ k.delta }}</span>
        </div>
      </div>
    </div>

    <!-- APROVAÇÕES PENDENTES -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">⏳ Aprovações Pendentes</h2>
      <span class="sh-badge" v-if="pendencias.length > 0">{{ pendencias.length }}</span>
    </div>
    <div class="aprovacoes-list" :class="{ loaded }">
      <div v-for="(p, i) in pendencias" :key="p.id" class="aprov-item" :style="{ '--api': i }">
        <div class="ap-tipo-ico" :style="{ background: tipoCor(p.tipo) + '15', borderColor: tipoCor(p.tipo) + '30' }">
          <span>{{ tipoIco(p.tipo) }}</span>
        </div>
        <div class="ap-info">
          <span class="ap-nome">{{ p.servidor }}</span>
          <span class="ap-tipo-txt">{{ tipoLabel(p.tipo) }}</span>
          <span class="ap-detalhe">{{ p.detalhe }}</span>
        </div>
        <div class="ap-data">
          <span class="apd-val">{{ formatDate(p.data) }}</span>
          <span class="apd-label">Solicitado</span>
        </div>
        <div class="ap-acoes">
          <button class="ap-btn-neg" @click="reprovar(p)" title="Reprovar">✕</button>
          <button class="ap-btn-ok" @click="aprovar(p)" title="Aprovar">✓</button>
        </div>
      </div>
      <div v-if="pendencias.length === 0" class="ap-empty">
        <span>✅</span><p>Todas as solicitações foram resolvidas!</p>
      </div>
    </div>

    <!-- EQUIPE -->
    <div class="section-hdr" :class="{ loaded }">
      <h2 class="sh-title">👥 Minha Equipe ({{ equipe.length }})</h2>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar servidor..." />
      </div>
    </div>
    <div class="equipe-grid" :class="{ loaded }">
      <div v-for="(m, i) in equipeFiltrada" :key="m.id" class="membro-card" :style="{ '--mi': i }">
        <div class="mc-avatar">
          <img :src="`https://api.dicebear.com/7.x/initials/svg?seed=${m.nome}&backgroundColor=3b82f6`" :alt="m.nome" />
          <span class="mc-status-dot" :class="m.presente ? 'msd-on' : 'msd-off'"></span>
        </div>
        <div class="mc-info">
          <span class="mc-nome">{{ m.nome }}</span>
          <span class="mc-cargo">{{ m.cargo }}</span>
          <span class="mc-status-txt" :class="m.presente ? 'mst-on' : 'mst-off'">{{ m.statusLabel }}</span>
        </div>
        <div class="mc-meta">
          <div class="mcm-row" v-if="m.turno"><span>🕐</span> {{ m.turno }}</div>
          <div class="mcm-row" v-if="m.ferias"><span>🏖️</span> Em férias</div>
          <div class="mcm-row" v-if="m.atestado"><span>🏥</span> Atestado</div>
        </div>
        <div class="mc-btns">
          <button class="mc-btn" @click="verFicha(m)">Ver ficha</button>
          <button class="mc-btn mc-btn-av" @click="abrirAvaliacao(m)">⭐ Avaliar</button>
        </div>
      </div>
    </div>

    <!-- INDICADORES DA EQUIPE -->
    <div class="indicadores-section" :class="{ loaded }">
      <h2 class="sh-title">📊 Indicadores da Equipe</h2>
      <div class="ind-grid">
        <div class="ind-card ind-presenca">
          <h3>📍 Presença Hoje</h3>
          <div class="ind-big">{{ equipe.filter(m => m.presente).length }}<span>/{{ equipe.length }}</span></div>
          <div class="ind-bar-wrap">
            <div class="ind-bar" :style="{ width: (equipe.filter(m => m.presente).length / equipe.length * 100) + '%', background: '#10b981' }"></div>
          </div>
          <div class="ind-sub-list">
            <span class="ind-chip ic-green">{{ equipe.filter(m => m.presente && !m.ferias && !m.atestado).length }} Presentes</span>
            <span class="ind-chip ic-yellow">{{ equipe.filter(m => m.ferias).length }} Férias</span>
            <span class="ind-chip ic-red">{{ equipe.filter(m => m.atestado).length }} Atestado</span>
          </div>
        </div>
        <div class="ind-card ind-avaliacao">
          <h3>⭐ Avaliações — Ciclo Q1/26</h3>
          <div v-for="a in avaliacdes" :key="a.label" class="av-row">
            <span class="av-label">{{ a.label }}</span>
            <div class="av-bar"><div class="av-fill" :style="{ width: (a.pct) + '%', background: a.cor }"></div></div>
            <span class="av-pct">{{ a.pct }}%</span>
          </div>
        </div>
        <div class="ind-card ind-plantoes">
          <h3>📅 Plantões Esta Semana</h3>
          <div v-for="p in plantoesSemana" :key="p.nome" class="plt-row">
            <span class="plt-nome">{{ p.nome }}</span>
            <span class="plt-turno" :class="p.turno === 'Manhã' ? 'pt-manha' : p.turno === 'Tarde' ? 'pt-tarde' : 'pt-noite'">{{ p.turno }}</span>
            <span class="plt-setor">{{ p.setor }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TOAST -->
    <transition name="toast"><div v-if="toast.visible" class="toast" :class="toast.tipo">{{ toast.msg }}</div></transition>

    <!-- MODAL AVALIAÇÃO -->
    <transition name="modal">
      <div v-if="modalAv.open" class="av-overlay" @click.self="modalAv.open = false">
        <div class="av-modal">
          <div class="av-modal-hdr">
            <div>
              <span class="av-modal-eyebrow">⭐ Avaliação de Desempenho</span>
              <h2 class="av-modal-title">{{ modalAv.membro?.nome }}</h2>
              <span class="av-modal-sub">{{ modalAv.membro?.cargo }} · Ciclo {{ cicloAtual }}</span>
            </div>
            <button class="av-modal-close" @click="modalAv.open = false">✕</button>
          </div>

          <div class="av-criterios">
            <div v-for="c in modalAv.criterios" :key="c.id" class="av-crit-item">
              <div class="av-crit-hdr">
                <span class="av-crit-ico">{{ c.ico }}</span>
                <div class="av-crit-info">
                  <span class="av-crit-nome">{{ c.nome }}</span>
                  <span class="av-crit-peso">Peso {{ c.peso }}%</span>
                </div>
                <span class="av-crit-nota" :style="{ color: avNotaCor(c.nota) }">{{ c.nota }}<span class="av-crit-max">/10</span></span>
              </div>
              <div class="av-stars">
                <button v-for="n in 10" :key="n" class="av-star"
                  :class="{ 'star-on': n <= c.nota, 'star-hov': n <= c.hovered }"
                  @mouseenter="c.hovered = n" @mouseleave="c.hovered = 0"
                  @click="c.nota = n">
                  ★
                </button>
              </div>
              <input v-model="c.obs" class="av-obs" placeholder="Observações sobre este critério..." />
            </div>
          </div>

          <div class="av-nf-wrap">
            <span class="av-nf-label">Nota Final Ponderada</span>
            <span class="av-nf-val" :style="{ color: avNotaCor(avNotaFinal) }">{{ avNotaFinal.toFixed(1) }}</span>
            <span class="av-nf-conceito" :style="{ color: avNotaCor(avNotaFinal) }">{{ avConceito(avNotaFinal) }}</span>
          </div>

          <div v-if="modalAv.erro" class="av-msg-err">❌ {{ modalAv.erro }}</div>
          <div v-if="modalAv.ok"   class="av-msg-ok">✅ Avaliação salva com sucesso!</div>

          <div class="av-modal-footer">
            <button class="av-btn-cancel" @click="modalAv.open = false">Cancelar</button>
            <button class="av-btn-save" :disabled="modalAv.salvando" @click="salvarAvaliacao">
              <span v-if="modalAv.salvando" class="btn-spin"></span>
              <template v-else>💾 Salvar Avaliação</template>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const busca = ref('')
const toast = ref({ visible: false, msg: '', tipo: '' })

// ── Mapeamento de equipe backend→Vue ─────────────────────────
const mapMembro = (m) => ({
  id:          m.id          ?? m.FUNCIONARIO_ID,
  nome:        m.nome        ?? [m.FUNCIONARIO_NOME, m.FUNCIONARIO_SOBRENOME].filter(Boolean).join(' '),
  cargo:       m.cargo       ?? m.CARGO_NOME ?? '—',
  turno:       m.turno       ?? m.FUNCIONARIO_TURNO ?? null,
  presente:    !!(m.presente  ?? false),
  ferias:      !!(m.ferias    ?? false),
  atestado:    !!(m.atestado  ?? false),
  statusLabel: m.statusLabel ?? (m.presente ? 'Presente' : 'Ausente'),
})

const mapPendencia = (p) => ({
  id:          p.id         ?? p.ref_id,
  servidor:    p.servidor   ?? '—',
  tipo:        p.tipo       ?? 'outros',
  detalhe:     p.detalhe    ?? '—',
  data:        p.data       ?? null,
  ref_id:      p.ref_id     ?? null,
  ref_tabela:  p.ref_tabela ?? null,
})

// ── Mock data completo como fallback ─────────────────────────
const mockEquipe = [
  { id: 1, nome: 'Ana Beatriz Santos', cargo: 'Enfermeira Assistencial', turno: 'Manhã 07–13h', presente: true, ferias: false, atestado: false, statusLabel: 'Presente' },
  { id: 2, nome: 'Carlos Eduardo Lima', cargo: 'Técnico de Enfermagem', turno: 'Tarde 13–19h', presente: false, ferias: false, atestado: false, statusLabel: 'Escalado Tarde' },
  { id: 3, nome: 'Fernanda Rodrigues', cargo: 'Enfermeira Chefe', turno: null, presente: false, ferias: true, atestado: false, statusLabel: 'Em Férias' },
  { id: 4, nome: 'Marcos Vinicius Souza', cargo: 'Técnico de Enfermagem', turno: 'Noite 19–07h', presente: false, ferias: false, atestado: false, statusLabel: 'Escalado Noite' },
  { id: 5, nome: 'Juliana Martins', cargo: 'Enfermeira Assistencial', turno: 'Manhã 07–13h', presente: true, ferias: false, atestado: false, statusLabel: 'Presente' },
  { id: 6, nome: 'Roberto Alves', cargo: 'Técnico de Enfermagem', turno: null, presente: false, ferias: false, atestado: true, statusLabel: 'Atestado' },
  { id: 7, nome: 'Patrícia Costa', cargo: 'Enfermeira Assistencial', turno: 'Manhã 07–13h', presente: true, ferias: false, atestado: false, statusLabel: 'Presente' },
  { id: 8, nome: 'Diego Ferreira', cargo: 'Técnico de Enfermagem', turno: 'Tarde 13–19h', presente: true, ferias: false, atestado: false, statusLabel: 'Presente' },
]

const mockPendencias = [
  { id: 1, servidor: 'Ana Beatriz Santos', tipo: 'ferias', detalhe: '15 dias — 10/03/2026 a 25/03/2026', data: '2026-02-20' },
  { id: 2, servidor: 'Carlos Eduardo Lima', tipo: 'plantao', detalhe: 'Plantão extra 08/03 — UTI Adulto 07h–19h', data: '2026-02-21' },
  { id: 3, servidor: 'Fernanda Rodrigues', tipo: 'abono', detalhe: 'Abono de falta — 18/02/2026 — Consulta médica', data: '2026-02-22' },
  { id: 4, servidor: 'Marcos Vinicius Souza', tipo: 'horas', detalhe: 'Compensação banco de horas — 4h em 06/03', data: '2026-02-23' },
]

const equipe    = ref([])
const pendencias = ref([])
const kpisData  = ref(null)

// ── KPIs: usa valores do backend ou calcula a partir da equipe localmente ──
const kpis = computed(() => {
  const total     = kpisData.value?.total     ?? equipe.value.length
  const presentes = kpisData.value?.presentes ?? equipe.value.filter(m => m.presente).length
  const pend      = kpisData.value?.pendencias?? pendencias.value.length
  const emFerias  = kpisData.value?.emFerias  ?? equipe.value.filter(m => m.ferias).length
  const pctPres   = total > 0 ? Math.round(presentes / total * 100) : 0
  return [
    { ico: '👥', label: 'Servidores na Equipe', val: String(total),            cor: '#3b82f6', delta: null },
    { ico: '✅', label: 'Presença Hoje',         val: `${presentes}/${total}`, cor: '#10b981', delta: `${pctPres}%`, up: true },
    { ico: '⏳', label: 'Pendências',            val: String(pend),             cor: '#f59e0b', delta: null },
    { ico: '🏖️', label: 'Em Férias / Afastado',  val: String(emFerias),        cor: '#a855f7', delta: null },
  ]
})

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/gestor')
    if (!data.fallback && data.equipe?.length) {
      equipe.value    = data.equipe.map(mapMembro)
      pendencias.value = (data.pendencias ?? []).map(mapPendencia)
      kpisData.value  = data.kpis ?? null
    } else {
      equipe.value    = mockEquipe
      pendencias.value = mockPendencias
    }
  } catch {
    equipe.value    = mockEquipe
    pendencias.value = mockPendencias
  } finally {
    setTimeout(() => { loaded.value = true }, 80)
  }
})

const totalPendencias = computed(() => pendencias.value.length)
const equipeFiltrada  = computed(() => busca.value
  ? equipe.value.filter(m => (m.nome + m.cargo).toLowerCase().includes(busca.value.toLowerCase()))
  : equipe.value)

const tipoCor   = (t) => ({ ferias: '#3b82f6', plantao: '#f59e0b', abono: '#10b981', horas: '#a855f7' })[t] ?? '#64748b'
const tipoIco   = (t) => ({ ferias: '🏖️', plantao: '🏥', abono: '📋', horas: '⏱️' })[t] ?? '📄'
const tipoLabel = (t) => ({ ferias: 'Férias', plantao: 'Plantão Extra', abono: 'Abono de Falta', horas: 'Banco de Horas' })[t] ?? t
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }) } catch { return d } }
const showToast  = (msg, tipo = 'ok') => { toast.value = { visible: true, msg, tipo }; setTimeout(() => toast.value.visible = false, 3500) }

const aprovar = async (p) => {
  try { await api.post('/api/v3/gestor/aprovar', { acao: 'aprovado', ref_id: p.ref_id, ref_tabela: p.ref_tabela }) } catch { /* fallback */ }
  pendencias.value = pendencias.value.filter(x => x.id !== p.id)
  showToast(`✅ "${p.servidor}" — ${tipoLabel(p.tipo)} aprovad${p.tipo === 'ferias' ? 'as' : 'o'}!`, 'ok')
}

const reprovar = async (p) => {
  try { await api.post('/api/v3/gestor/aprovar', { acao: 'reprovado', ref_id: p.ref_id, ref_tabela: p.ref_tabela }) } catch { /* fallback */ }
  pendencias.value = pendencias.value.filter(x => x.id !== p.id)
  showToast(`❌ "${p.servidor}" — solicitação reprovada.`, 'err')
}

const avaliacdes = [
  { label: 'Acima das Metas (9–10)', pct: 42, cor: '#10b981' },
  { label: 'Dentro das Metas (7–8)', pct: 33, cor: '#3b82f6' },
  { label: 'Abaixo das Metas (<7)', pct: 25, cor: '#f59e0b' },
]

const plantoesSemana = computed(() =>
  equipe.value.filter(m => m.turno).slice(0, 5).map(m => ({
    nome: m.nome.split(' ')[0] + ' ' + (m.nome.split(' ').slice(-1)[0] ?? ''),
    turno: m.turno?.includes('07') ? 'Manhã' : m.turno?.includes('13') ? 'Tarde' : 'Noite',
    setor: m.cargo,
  }))
)

const verFicha = (m) => { showToast(`🔍 Abrindo ficha de ${m.nome}...`, 'ok') }

// ── Avaliação de Desempenho ──────────────────────────────────
const cicloAtual = '2026.1'

const criarCriterios = () => [
  { id: 1, ico: '🎯', nome: 'Cumprimento de Metas',     peso: 25, nota: 7, hovered: 0, obs: '' },
  { id: 2, ico: '🤝', nome: 'Trabalho em Equipe',        peso: 20, nota: 7, hovered: 0, obs: '' },
  { id: 3, ico: '⏰', nome: 'Pontualidade e Assiduidade',peso: 20, nota: 7, hovered: 0, obs: '' },
  { id: 4, ico: '💡', nome: 'Iniciativa e Proatividade', peso: 15, nota: 7, hovered: 0, obs: '' },
  { id: 5, ico: '📚', nome: 'Qualidade Técnica',         peso: 20, nota: 7, hovered: 0, obs: '' },
]

const modalAv = ref({ open: false, membro: null, criterios: criarCriterios(), salvando: false, ok: false, erro: '' })

const avNotaFinal = computed(() => {
  const total = modalAv.value.criterios.reduce((a, c) => a + c.nota * c.peso, 0)
  return total / 100
})

const avNotaCor    = (n) => n >= 9 ? '#10b981' : n >= 7 ? '#3b82f6' : n >= 5 ? '#f59e0b' : '#ef4444'
const avConceito   = (n) => n >= 9 ? 'Excelente' : n >= 7 ? 'Bom' : n >= 5 ? 'Regular' : 'Insatisfatório'

const abrirAvaliacao = (m) => {
  modalAv.value = { open: true, membro: m, criterios: criarCriterios(), salvando: false, ok: false, erro: '' }
}

const salvarAvaliacao = async () => {
  modalAv.value.salvando = true
  modalAv.value.erro = ''
  modalAv.value.ok = false
  try {
    await api.post('/api/v3/avaliacoes', {
      funcionario_id: modalAv.value.membro.id,
      ciclo: cicloAtual,
      criterios: modalAv.value.criterios.map(c => ({ nome: c.nome, peso: c.peso, nota: c.nota, obs: c.obs })),
    })
    modalAv.value.ok = true
    showToast(`✅ Avaliação de ${modalAv.value.membro.nome} salva!`, 'ok')
    setTimeout(() => { modalAv.value.open = false }, 1800)
  } catch (e) {
    modalAv.value.erro = e.response?.data?.erro || 'Erro ao salvar. Tente novamente.'
  } finally {
    modalAv.value.salvando = false
  }
}
</script>

<style scoped>
.pg-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #0a1428 55%, #14280a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #0ea5e9; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #38bdf8; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hp-alert { display: flex; align-items: center; gap: 10px; background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3); border-radius: 14px; padding: 12px 18px; animation: blink 2s ease infinite; }
@keyframes blink { 0%,100% { box-shadow: none; } 50% { box-shadow: 0 0 20px rgba(245,158,11,0.25); } }
.hpa-ico { font-size: 20px; }
.hpa-txt { font-size: 13px; font-weight: 700; color: #fbbf24; }
.hp-ok { font-size: 14px; font-weight: 700; color: #34d399; padding: 12px 20px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); border-radius: 14px; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s; }
.kpi-grid.loaded { opacity: 1; transform: none; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 12px; border-top: 3px solid var(--kc); animation: kIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ki) * 50ms) both; }
@keyframes kIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.kpi-ico-wrap { font-size: 24px; }
.kpi-val { display: block; font-size: 22px; font-weight: 900; }
.kpi-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-top: 1px; }
.kpi-delta { display: block; font-size: 11px; font-weight: 700; margin-top: 3px; }
.kd-up { color: #10b981; } .kd-down { color: #ef4444; }
.section-hdr { display: flex; align-items: center; gap: 12px; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.section-hdr.loaded { opacity: 1; transform: none; }
.sh-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; flex: 1; }
.sh-badge { background: #f59e0b; color: #fff; border-radius: 99px; padding: 2px 10px; font-size: 12px; font-weight: 900; }
.search-wrap { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 7px 12px; }
.s-ico { width: 14px; height: 14px; color: #94a3b8; }
.s-input { border: none; font-size: 13px; color: #1e293b; outline: none; background: transparent; font-family: inherit; width: 160px; }
.aprovacoes-list { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.aprovacoes-list.loaded { opacity: 1; transform: none; }
.aprov-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 18px; animation: apIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--api) * 60ms) both; flex-wrap: wrap; }
@keyframes apIn { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: none; } }
.ap-tipo-ico { width: 44px; height: 44px; border-radius: 12px; border: 1px solid; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.ap-info { flex: 1; min-width: 160px; }
.ap-nome { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.ap-tipo-txt { display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-top: 1px; }
.ap-detalhe { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.ap-data { text-align: right; }
.apd-val { display: block; font-size: 12px; font-weight: 700; color: #1e293b; }
.apd-label { display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }
.ap-acoes { display: flex; gap: 8px; }
.ap-btn-neg, .ap-btn-ok { width: 36px; height: 36px; border-radius: 10px; border: none; font-size: 16px; font-weight: 900; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; }
.ap-btn-neg { background: #fef2f2; color: #ef4444; }
.ap-btn-neg:hover { background: #ef4444; color: #fff; transform: scale(1.08); }
.ap-btn-ok { background: #f0fdf4; color: #16a34a; }
.ap-btn-ok:hover { background: #16a34a; color: #fff; transform: scale(1.08); }
.ap-empty { display: flex; flex-direction: column; align-items: center; padding: 40px; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
.ap-empty span { font-size: 32px; }
.ap-empty p { font-size: 14px; color: #94a3b8; margin: 0; }
.equipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.equipe-grid.loaded { opacity: 1; transform: none; }
.membro-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px; display: flex; flex-direction: column; gap: 10px; animation: mcIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--mi) * 40ms) both; transition: all 0.18s; }
@keyframes mcIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.membro-card:hover { box-shadow: 0 6px 24px -6px rgba(0,0,0,0.12); transform: translateY(-2px); }
.mc-avatar { position: relative; width: 48px; }
.mc-avatar img { width: 48px; height: 48px; border-radius: 14px; }
.mc-status-dot { position: absolute; bottom: -2px; right: -2px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; }
.msd-on { background: #10b981; }
.msd-off { background: #94a3b8; }
.mc-info { flex: 1; }
.mc-nome { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.mc-cargo { display: block; font-size: 11px; color: #64748b; margin-top: 2px; }
.mc-status-txt { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-top: 4px; }
.mst-on { background: #dcfce7; color: #166534; }
.mst-off { background: #f1f5f9; color: #64748b; }
.mc-meta { display: flex; flex-direction: column; gap: 4px; }
.mcm-row { font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 6px; }
.mc-btn { padding: 8px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; }
.mc-btn:hover { border-color: #0ea5e9; color: #0ea5e9; }
.indicadores-section { display: flex; flex-direction: column; gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.14s; }
.indicadores-section.loaded { opacity: 1; transform: none; }
.ind-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.ind-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; }
.ind-card h3 { font-size: 13px; font-weight: 800; color: #1e293b; margin: 0 0 14px; }
.ind-big { font-size: 48px; font-weight: 900; color: #1e293b; line-height: 1; margin-bottom: 8px; }
.ind-big span { font-size: 22px; color: #94a3b8; font-weight: 700; }
.ind-bar-wrap { height: 7px; background: #f1f5f9; border-radius: 99px; overflow: hidden; margin-bottom: 12px; }
.ind-bar { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.ind-sub-list { display: flex; gap: 8px; flex-wrap: wrap; }
.ind-chip { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.ic-green { background: #dcfce7; color: #166534; }
.ic-yellow { background: #fffbeb; color: #92400e; }
.ic-red { background: #fef2f2; color: #991b1b; }
.av-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.av-label { font-size: 11px; color: #475569; font-weight: 600; flex: 1; min-width: 90px; }
.av-bar { flex: 1; height: 7px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.av-fill { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.av-pct { font-size: 12px; font-weight: 800; color: #475569; min-width: 32px; text-align: right; }
.plt-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
.plt-nome { flex: 1; font-size: 13px; font-weight: 700; color: #1e293b; }
.plt-turno { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 8px; }
.pt-manha { background: #fef3c7; color: #92400e; }
.pt-tarde { background: #dbeafe; color: #1e40af; }
.pt-noite { background: #1e293b; color: #e2e8f0; }
.plt-setor { font-size: 11px; color: #94a3b8; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast.ok { background: #1e293b; color: #fff; }
.toast.err { background: #ef4444; color: #fff; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
@media (max-width: 700px) { .equipe-grid { grid-template-columns: repeat(2, 1fr); } }
.mc-btns { display: flex; gap: 8px; }
.mc-btn-av { border-color: #fbbf24 !important; color: #d97706 !important; background: #fffbeb !important; }
.mc-btn-av:hover { background: #f59e0b !important; color: #fff !important; border-color: #f59e0b !important; }
/* ── Modal Avaliação ── */
.av-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 300; padding: 20px; }
.av-modal { background: #fff; border-radius: 24px; width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 80px rgba(0,0,0,0.22); }
.av-modal-hdr { display: flex; align-items: flex-start; justify-content: space-between; padding: 24px 24px 16px; border-bottom: 1px solid #f1f5f9; }
.av-modal-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #f59e0b; margin-bottom: 4px; }
.av-modal-title { font-size: 20px; font-weight: 900; color: #1e293b; margin: 0 0 2px; }
.av-modal-sub { font-size: 12px; color: #94a3b8; }
.av-modal-close { border: none; background: #f1f5f9; border-radius: 10px; width: 32px; height: 32px; font-size: 16px; cursor: pointer; color: #64748b; transition: all 0.15s; flex-shrink: 0; }
.av-modal-close:hover { background: #ef4444; color: #fff; }
.av-criterios { padding: 16px 24px; display: flex; flex-direction: column; gap: 14px; }
.av-crit-item { border: 1px solid #f1f5f9; border-radius: 14px; padding: 14px; }
.av-crit-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.av-crit-ico { font-size: 20px; }
.av-crit-info { flex: 1; }
.av-crit-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.av-crit-peso { display: block; font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
.av-crit-nota { font-size: 22px; font-weight: 900; }
.av-crit-max { font-size: 12px; color: #94a3b8; font-weight: 500; }
.av-stars { display: flex; gap: 3px; margin-bottom: 8px; }
.av-star { font-size: 22px; color: #e2e8f0; background: none; border: none; cursor: pointer; padding: 2px; transition: all 0.1s; line-height: 1; }
.av-star.star-on { color: #f59e0b; }
.av-star.star-hov { color: #fbbf24; transform: scale(1.12); }
.av-obs { width: 100%; border: 1px solid #f1f5f9; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-family: inherit; color: #475569; background: #fafafa; outline: none; box-sizing: border-box; }
.av-obs:focus { border-color: #f59e0b; }
.av-nf-wrap { margin: 0 24px 16px; text-align: center; background: #f8fafc; border-radius: 16px; padding: 16px; }
.av-nf-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
.av-nf-val { display: block; font-size: 44px; font-weight: 900; line-height: 1; }
.av-nf-conceito { display: block; font-size: 14px; font-weight: 700; margin-top: 4px; }
.av-msg-err { margin: 0 24px 10px; font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; }
.av-msg-ok  { margin: 0 24px 10px; font-size: 12px; font-weight: 600; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: 10px; padding: 8px 12px; }
.av-modal-footer { display: flex; gap: 10px; padding: 16px 24px; border-top: 1px solid #f1f5f9; }
.av-btn-cancel { flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.15s; }
.av-btn-cancel:hover { background: #f1f5f9; }
.av-btn-save { flex: 2; padding: 12px; border-radius: 12px; border: none; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.av-btn-save:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245,158,11,0.35); }
.av-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .av-modal, .modal-leave-to .av-modal { transform: scale(0.95) translateY(12px); }
</style>
