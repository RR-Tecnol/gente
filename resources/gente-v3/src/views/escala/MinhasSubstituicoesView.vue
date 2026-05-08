<template>
  <div class="subs-page">
    <!-- HERO (padrão Organograma / GENTE v3) -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Área do servidor · Prefeitura de São Luís</span>
          <h1 class="hero-title">Minhas Substituições</h1>
          <p class="hero-sub">
            Convites e histórico: ícones e tags seguem a secretaria vinculada à escala da convocação (dados de lotação), não o texto do cargo.
          </p>
          <p v-if="servidorUnidadeSigla" class="hero-lotacao">Sua lotação atual: <strong>{{ servidorUnidadeSigla }}</strong></p>
        </div>
        <div class="hero-meta">
          <span class="hm-pill" v-if="pendentes.length">{{ pendentes.length }} pendente{{ pendentes.length !== 1 ? 's' : '' }}</span>
          <span class="hm-pill hm-muted" v-else>Nenhuma pendência</span>
        </div>
      </div>
    </div>

    <div class="content-wrap" :class="{ loaded }">
      <section class="panel">
        <div class="panel-hdr">
          <span class="ph-ico">📋</span>
          <h2>Substituições pendentes</h2>
          <span class="ph-count">{{ pendentes.length }}</span>
        </div>
        <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando convites…</p></div>
        <div v-else-if="pendentes.length === 0" class="state-vazio">Nenhum convite aguardando sua resposta.</div>
        <div v-else class="card-grid">
          <article v-for="s in pendentes" :key="s.id" class="sub-card">
            <div class="sub-card-top">
              <div class="sub-avatar-wrap">
                <span class="ctx-ico" :title="s.unidade_sigla || 'Secretaria'">{{ contextIcon(s) }}</span>
                <div class="sub-avatar">{{ iniciais(s.solicitante) }}</div>
              </div>
              <div class="sub-head">
                <span class="sub-data">{{ formatarData(s.data_plantao) }}</span>
                <span class="sub-turno-line">
                  <svg class="ico-meta" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  <span class="sub-turno">Turno <strong>{{ s.turno || '—' }}</strong></span>
                </span>
                <span class="sub-solic">Solicitante: <strong class="sub-solic-nome">{{ s.solicitante || '—' }}</strong></span>
              </div>
            </div>
            <div class="sub-meta">
              <span v-if="s.unidade_sigla" class="sigla-chip">{{ s.unidade_sigla }}</span>
              <span v-if="s.unidade_escolar || s.setor" class="meta-linha">
                <svg class="ico-meta" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-4.35 7-10a7 7 0 10-14 0c0 5.65 7 10 7 10z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="11" r="2.5" fill="currentColor"/></svg>
                <span class="meta-text">{{ s.unidade_escolar || s.setor }}</span>
              </span>
              <span v-if="s.disciplina_cargo" class="meta-cargo"><strong>{{ s.disciplina_cargo }}</strong></span>
              <span v-if="faixaHorario(s)" class="sub-horario">{{ faixaHorario(s) }}</span>
            </div>
            <div class="tag-row">
              <span class="tag tag-status tag-pendente">Pendente</span>
              <span class="tag" :class="tagContexto(s).cls">{{ tagContexto(s).label }}</span>
              <span v-if="s.tipo_convocacao" class="tag tag-conv">{{ labelConvocacao(s.tipo_convocacao) }}</span>
            </div>
            <div v-if="String(s.tipo_convocacao || '').toUpperCase() !== 'COMPULSORIA'" class="sub-actions">
              <button type="button" class="btn-aceitar" :disabled="acaoId === s.id" @click="decidir(s, 'confirmada')">Aceitar</button>
              <button type="button" class="btn-recusar" :disabled="acaoId === s.id" @click="abrirModalRecusa(s)">Recusar</button>
            </div>
            <p v-else class="sub-aviso">Convocação compulsória — entre em contato com o gestor para exceções.</p>
          </article>
        </div>
      </section>

      <section class="panel">
        <div class="panel-hdr">
          <span class="ph-ico">📚</span>
          <h2>Histórico de coberturas</h2>
          <span class="ph-count">{{ historico.length }}</span>
        </div>
        <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando histórico…</p></div>
        <div v-else-if="historico.length === 0" class="state-vazio">Ainda não há registros concluídos ou recusados.</div>
        <div v-else class="card-grid">
          <article v-for="s in historico" :key="`h-${s.id}`" class="sub-card sub-card--hist">
            <div class="sub-card-top">
              <div class="sub-avatar-wrap">
                <span class="ctx-ico ctx-ico--sm" :title="s.unidade_sigla || 'Secretaria'">{{ contextIcon(s) }}</span>
                <div class="sub-avatar sub-avatar--hist">{{ iniciais(s.solicitante) }}</div>
              </div>
              <div class="sub-head">
                <span class="sub-data">{{ formatarData(s.data_plantao) }}</span>
                <span class="sub-turno-line">
                  <svg class="ico-meta ico-meta--muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  <span class="sub-turno">Turno <strong>{{ s.turno || '—' }}</strong></span>
                </span>
                <span class="sub-solic">Solicitante: <strong class="sub-solic-nome">{{ s.solicitante || '—' }}</strong></span>
              </div>
            </div>
            <div class="sub-meta sub-meta--hist" v-if="s.unidade_escolar || s.setor || s.disciplina_cargo">
              <span v-if="s.unidade_sigla" class="sigla-chip">{{ s.unidade_sigla }}</span>
              <span v-if="s.unidade_escolar || s.setor" class="meta-linha">
                <svg class="ico-meta ico-meta--muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-4.35 7-10a7 7 0 10-14 0c0 5.65 7 10 7 10z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="11" r="2.5" fill="currentColor"/></svg>
                <span class="meta-text">{{ s.unidade_escolar || s.setor }}</span>
              </span>
              <span v-if="s.disciplina_cargo" class="meta-cargo"><strong>{{ s.disciplina_cargo }}</strong></span>
            </div>
            <div class="tag-row">
              <span class="tag" :class="tagStatusHistorico(s).cls">{{ tagStatusHistorico(s).label }}</span>
              <span class="tag" :class="tagContexto(s).cls">{{ tagContexto(s).label }}</span>
            </div>
            <p v-if="s.motivo" class="sub-motivo"><span class="sub-motivo-lbl">Fundamento / observação</span>{{ s.motivo }}</p>
          </article>
        </div>
      </section>
    </div>

    <transition name="modal-fade">
      <div v-if="modalRecusa.aberto" class="modal-overlay" @click.self="fecharModalRecusa">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="recusa-titulo">
          <div class="modal-hdr">
            <h3 id="recusa-titulo">Justificativa da recusa</h3>
            <button type="button" class="modal-x" @click="fecharModalRecusa" aria-label="Fechar">✕</button>
          </div>
          <div class="modal-body">
            <p class="modal-lead">Descreva o motivo da recusa. O gestor poderá consultar este texto.</p>
            <label class="mf-label" for="recusa-texto">Justificativa <span class="req">*</span></label>
            <textarea
              id="recusa-texto"
              v-model="modalRecusa.texto"
              class="mf-textarea"
              rows="4"
              placeholder="Ex.: indisponibilidade familiar, conflito de agenda já assumida…"
            />
            <p v-if="erroModal" class="modal-err">{{ erroModal }}</p>
          </div>
          <div class="modal-ft">
            <button type="button" class="btn-ghost" @click="fecharModalRecusa">Cancelar</button>
            <button type="button" class="btn-primary" :disabled="enviandoRecusa" @click="confirmarRecusa">
              {{ enviandoRecusa ? 'Enviando…' : 'Enviar recusa' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const loading = ref(true)
const loaded = ref(false)
const pendentes = ref([])
const historico = ref([])
/** Sigla da secretaria do servidor (lotação ativa), ecoada pela API. */
const servidorUnidadeSigla = ref(null)
const acaoId = ref(null)
const modalRecusa = ref({ aberto: false, substituicao: null, texto: '' })
const erroModal = ref('')
const enviandoRecusa = ref(false)

/**
 * Contexto de interface a partir da secretaria-mãe da escala (API: unidade_sigla).
 * Alinhado ao organograma (UNIDADE.UNIDADE_SIGLA), sem heurística em nome de cargo.
 */
function tagContexto(s) {
  const u = String(s.unidade_sigla || '').toUpperCase().trim()
  if (u === 'SEMUS') return { label: 'Plantão', cls: 'tag-ctx-plantao' }
  if (u === 'SEMED') return { label: 'Turno', cls: 'tag-ctx-turno' }
  if (u === 'SEMUSC') return { label: 'Ronda', cls: 'tag-ctx-ronda' }
  if (u === 'SEMOSP') return { label: 'Obra / campo', cls: 'tag-ctx-obra' }
  if (u) return { label: 'Cobertura', cls: 'tag-ctx-default' }
  return { label: 'Cobertura', cls: 'tag-ctx-default' }
}

function contextIcon(s) {
  const u = String(s.unidade_sigla || '').toUpperCase().trim()
  if (u === 'SEMUS') return '🏥'
  if (u === 'SEMED') return '🏫'
  if (u === 'SEMUSC') return '🛡️'
  if (u === 'SEMOSP') return '🏗️'
  return '🏢'
}

function tagStatusHistorico(s) {
  const st = String(s.status || '').toLowerCase()
  if (st.includes('confirm')) return { label: 'Confirmada', cls: 'tag-status tag-ok' }
  if (st.includes('recus')) return { label: 'Recusada', cls: 'tag-status tag-bad' }
  return { label: s.status || 'Registrado', cls: 'tag-status tag-neutral' }
}

function labelConvocacao(t) {
  const u = String(t || '').toUpperCase()
  if (u === 'COMPULSORIA') return 'Compulsória'
  if (u === 'OPTATIVA') return 'Optativa'
  return t || ''
}

function formatarData(iso) {
  if (!iso) return '—'
  const d = new Date(String(iso).slice(0, 10) + 'T12:00:00')
  if (Number.isNaN(d.getTime())) return String(iso)
  return d.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })
}

function faixaHorario(s) {
  const a = (s.horario_inicio || '').trim()
  const b = (s.horario_fim || '').trim()
  if (a && b) return `${a} – ${b}`
  if (a) return `Desde ${a}`
  return ''
}

function iniciais(nome) {
  const w = String(nome || '').trim().split(/\s+/).filter(Boolean)
  if (!w.length) return '?'
  if (w.length === 1) return w[0].slice(0, 2).toUpperCase()
  return `${w[0][0]}${w[w.length - 1][0]}`.toUpperCase()
}

const carregar = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v3/substituicoes/minhas')
    pendentes.value = data?.pendentes || []
    historico.value = data?.historico || []
    servidorUnidadeSigla.value = data?.servidor_unidade_sigla || null
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 60)
  }
}

const decidir = async (s, status) => {
  acaoId.value = s.id
  try {
    await api.put(`/api/v3/substituicoes/${s.id}`, { status, justificativa: '' })
    await carregar()
  } catch (e) {
    console.error(e)
  } finally {
    acaoId.value = null
  }
}

const abrirModalRecusa = (s) => {
  erroModal.value = ''
  modalRecusa.value = { aberto: true, substituicao: s, texto: '' }
}

const fecharModalRecusa = () => {
  if (enviandoRecusa.value) return
  modalRecusa.value = { aberto: false, substituicao: null, texto: '' }
  erroModal.value = ''
}

const confirmarRecusa = async () => {
  const txt = String(modalRecusa.value.texto || '').trim()
  if (!txt) {
    erroModal.value = 'Informe a justificativa.'
    return
  }
  const s = modalRecusa.value.substituicao
  if (!s?.id) return
  erroModal.value = ''
  enviandoRecusa.value = true
  try {
    await api.put(`/api/v3/substituicoes/${s.id}`, { status: 'recusada', justificativa: txt })
    fecharModalRecusa()
    await carregar()
  } catch (e) {
    erroModal.value = e?.response?.data?.erro || e?.response?.data?.message || 'Não foi possível registrar a recusa.'
  } finally {
    enviandoRecusa.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.subs-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: 'Inter', system-ui, sans-serif;
}

/* HERO — alinhado ao Organograma */
.hero {
  position: relative;
  border-radius: 22px;
  padding: 26px 34px;
  overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #1e2a1e 55%, #1a244a 100%);
  opacity: 0;
  transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded {
  opacity: 1;
  transform: none;
}
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #0d9488; right: 280px; bottom: -50px; }
.hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}
.hero-eyebrow {
  display: block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #818cf8;
  margin-bottom: 5px;
}
.hero-title {
  font-size: 26px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.02em;
  margin: 0 0 3px;
}
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; max-width: 640px; line-height: 1.55; }
.hero-lotacao { font-size: 12px; color: #cbd5e1; margin: 10px 0 0; }
.hero-lotacao strong { color: #a5b4fc; }
.hero-meta { display: flex; align-items: center; gap: 8px; }
.hm-pill {
  font-size: 12px;
  font-weight: 800;
  padding: 8px 14px;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.25);
  color: #e0e7ff;
  border: 1px solid rgba(129, 140, 248, 0.45);
}
.hm-pill.hm-muted {
  background: rgba(148, 163, 184, 0.12);
  color: #94a3b8;
  border-color: rgba(148, 163, 184, 0.25);
}

.content-wrap {
  display: flex;
  flex-direction: column;
  gap: 22px;
  opacity: 0;
  transform: translateY(10px);
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.08s;
}
.content-wrap.loaded {
  opacity: 1;
  transform: none;
}

.panel {
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 18px;
  padding: 20px 22px 24px;
  box-shadow: 0 10px 40px -12px rgba(15, 23, 42, 0.12);
}
.panel-hdr {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 2px solid #e2e8f0;
}
.ph-ico { font-size: 20px; }
.panel-hdr h2 {
  flex: 1;
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
}
.ph-count {
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  background: #312e81;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid #1e1b4b;
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 14px;
}

.sub-card {
  border: 1px solid #cbd5e1;
  border-radius: 16px;
  padding: 18px 20px;
  background: linear-gradient(165deg, #fff 0%, #f8fafc 55%, #f1f5f9 100%);
  box-shadow: 0 8px 28px -12px rgba(15, 23, 42, 0.14);
  transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
}
.sub-card:hover {
  box-shadow: 0 12px 36px -10px rgba(15, 23, 42, 0.16);
  transform: translateY(-1px);
}
.sub-card--hist { opacity: 0.98; }

.sub-card-top {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}
.sub-avatar-wrap {
  position: relative;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 4px;
}
.ctx-ico {
  font-size: 18px;
  line-height: 1;
  user-select: none;
}
.ctx-ico--sm { font-size: 16px; }
.sub-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  font-size: 13px;
  font-weight: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sigla-chip {
  display: inline-flex;
  align-items: center;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #eef2ff;
  background: #312e81;
  border: 2px solid #1e1b4b;
  border-radius: 999px;
  padding: 4px 11px;
  width: fit-content;
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.12) inset;
}
.sub-avatar--hist {
  background: linear-gradient(135deg, #64748b, #475569);
}
.sub-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.sub-data { font-size: 15px; font-weight: 800; color: #020617; letter-spacing: -0.02em; }
.sub-turno-line {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
}
.sub-turno {
  font-size: 12px;
  font-weight: 600;
  color: #4338ca;
}
.sub-turno strong { font-weight: 800; color: #312e81; }
.sub-solic { font-size: 12px; color: #64748b; margin-top: 2px; }
.sub-solic-nome { font-weight: 800; color: #0f172a; font-size: 13px; }
.ico-meta {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  color: #4f46e5;
}
.ico-meta--muted { color: #64748b; }
.meta-linha {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.meta-text { font-size: 12px; font-weight: 600; color: #334155; line-height: 1.45; }
.meta-cargo { font-size: 12px; color: #475569; }
.meta-cargo strong { font-weight: 800; color: #0f172a; }
.sub-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  color: #475569;
  margin-bottom: 12px;
}
.sub-meta--hist { margin-top: 4px; margin-bottom: 10px; }
.sub-horario { font-weight: 700; color: #0f766e; font-size: 11px; }

.tag-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}
.tag {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  padding: 5px 12px;
  border-radius: 999px;
  border: 2px solid transparent;
}
.tag-status.tag-pendente {
  background: #fef3c7;
  color: #92400e;
  border-color: #f59e0b;
}
.tag-status.tag-ok {
  background: #d1fae5;
  color: #065f46;
  border-color: #10b981;
}
.tag-status.tag-bad {
  background: #fee2e2;
  color: #991b1b;
  border-color: #ef4444;
}
.tag-status.tag-neutral {
  background: #e2e8f0;
  color: #334155;
  border-color: #94a3b8;
}
.tag-conv {
  background: #dbeafe;
  color: #1e40af;
  border-color: #3b82f6;
}
.tag-ctx-plantao { background: #d1fae5; color: #065f46; border-color: #059669; }
.tag-ctx-turno { background: #e0e7ff; color: #3730a3; border-color: #6366f1; }
.tag-ctx-ronda { background: #ffedd5; color: #9a3412; border-color: #ea580c; }
.tag-ctx-obra { background: #e0f2fe; color: #075985; border-color: #0284c7; }
.tag-ctx-default { background: #f1f5f9; color: #334155; border-color: #64748b; }

.sub-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-aceitar,
.btn-recusar {
  flex: 1;
  min-width: 120px;
  padding: 10px 14px;
  border-radius: 11px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
}
.btn-aceitar:disabled,
.btn-recusar:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-aceitar {
  background: linear-gradient(135deg, #059669, #047857);
  color: #fff;
}
.btn-recusar {
  background: #fff;
  color: #b91c1c;
  border: 1.5px solid #fecaca;
}
.btn-recusar:hover:not(:disabled) {
  background: #fef2f2;
}
.sub-aviso {
  margin: 0;
  font-size: 12px;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 10px 12px;
}
.sub-motivo {
  margin: 10px 0 0;
  font-size: 12px;
  color: #334155;
  line-height: 1.55;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}
.sub-motivo-lbl {
  display: block;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 4px;
}

.state-box,
.state-vazio {
  text-align: center;
  padding: 28px 16px;
  color: #64748b;
  font-size: 14px;
}
.state-vazio { background: #f8fafc; border-radius: 14px; border: 1px dashed #e2e8f0; }
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  margin: 0 auto 10px;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal recusa */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.62);
  backdrop-filter: blur(8px);
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-box {
  width: 100%;
  max-width: 440px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 32px 64px rgba(0, 0, 0, 0.22);
  overflow: hidden;
}
.modal-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 22px 0;
}
.modal-hdr h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 900;
  color: #1e293b;
}
.modal-x {
  border: none;
  background: #f1f5f9;
  border-radius: 9px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 13px;
  color: #475569;
}
.modal-body { padding: 14px 22px 8px; }
.modal-lead {
  margin: 0 0 12px;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}
.mf-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #475569;
  margin-bottom: 6px;
}
.req { color: #ef4444; }
.mf-textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 14px;
  font-family: inherit;
  color: #1e293b;
  background: #f8fafc;
  resize: vertical;
  min-height: 100px;
  outline: none;
  transition: border-color 0.15s;
}
.mf-textarea:focus {
  border-color: #6366f1;
}
.modal-err {
  margin: 10px 0 0;
  font-size: 12px;
  font-weight: 600;
  color: #dc2626;
}
.modal-ft {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 22px 20px;
}
.btn-ghost {
  padding: 10px 18px;
  border-radius: 11px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  font-size: 13px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.btn-primary {
  padding: 10px 20px;
  border-radius: 11px;
  border: none;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
.btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.22s ease;
}
.modal-fade-enter-active .modal-box,
.modal-fade-leave-active .modal-box {
  transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.22s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .modal-box,
.modal-fade-leave-to .modal-box {
  opacity: 0;
  transform: scale(0.96) translateY(8px);
}
</style>
