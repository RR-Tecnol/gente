<template>
  <div class="padm-page">
    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📊 Gestão RH</span>
          <h1 class="hero-title">Gerenciar Pesquisas de Satisfação</h1>
          <p class="hero-sub">Crie, publique e analise os resultados das pesquisas de clima</p>
        </div>
        <button class="btn-nova" @click="abrirModalNova">+ Nova Pesquisa</button>
      </div>
    </div>

    <!-- LISTA DE PESQUISAS -->
    <div class="lista-wrap" :class="{ loaded }">
      <div v-if="carregando" class="state-center"><div class="spinner"></div><p>Carregando...</p></div>
      <div v-else-if="!lista.length" class="state-center"><span>📋</span><p>Nenhuma pesquisa criada ainda.</p></div>
      <div v-else class="pesq-list">
        <div v-for="(p, i) in lista" :key="p.id" class="pesq-card" :style="{ '--ri': i }">
          <div class="pc-top">
            <div>
              <span class="pc-status-chip" :class="`sc-${p.status}`">{{ statusLabel(p.status) }}</span>
              <h3 class="pc-titulo">{{ p.titulo }}</h3>
              <p class="pc-desc">{{ p.desc || 'Sem descrição' }}</p>
            </div>
            <div class="pc-meta">
              <span>📅 {{ fmtData(p.fim) }}</span>
              <span>📝 {{ p.total_perguntas ?? 0 }} perguntas</span>
              <span>👥 {{ p.total_respostas ?? 0 }} respostas</span>
            </div>
          </div>
          <div class="pc-actions">
            <button class="act-btn act-ed"  @click="editarPesquisa(p)">✏️ Editar</button>
            <button v-if="p.status === 'rascunho'"  class="act-btn act-pub" @click="mudarStatus(p, 'aberta')">🚀 Publicar</button>
            <button v-if="p.status === 'aberta'"    class="act-btn act-enc" @click="mudarStatus(p, 'encerrada')">🔒 Encerrar</button>
            <button v-if="p.status !== 'rascunho'"  class="act-btn act-res" @click="verResultados(p)">📈 Resultados</button>
            <button class="act-btn act-del" @click="excluir(p)">🗑️</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: CRIAR / EDITAR PESQUISA -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="fecharModal">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>{{ editandoId ? '✏️ Editar Pesquisa' : '+ Nova Pesquisa' }}</h3>
            <button class="modal-close" @click="fecharModal">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Título *</label>
              <input v-model="form.titulo" class="cfg-input" placeholder="Ex: Pesquisa de Clima Q2/2026" />
            </div>
            <div class="form-group">
              <label>Descrição</label>
              <textarea v-model="form.desc" class="cfg-input cfg-ta" rows="2" placeholder="Objetivo da pesquisa..."></textarea>
            </div>
            <div class="form-2col">
              <div class="form-group">
                <label>Início</label>
                <input v-model="form.inicio" type="date" class="cfg-input" />
              </div>
              <div class="form-group">
                <label>Prazo final</label>
                <input v-model="form.fim" type="date" class="cfg-input" />
              </div>
            </div>

            <!-- PERGUNTAS -->
            <div class="form-group perg-section">
              <div class="perg-hdr">
                <label>Perguntas</label>
                <button class="btn-add-perg" @click="addPergunta">+ Adicionar</button>
              </div>
              <div v-for="(pq, idx) in form.perguntas" :key="idx" class="perg-row">
                <div class="perg-row-top">
                  <span class="perg-num">{{ idx + 1 }}</span>
                  <select v-model="pq.tipo" class="tipo-select">
                    <option value="nps">Escala 0-10 (NPS)</option>
                    <option value="estrelas">Estrelas (1-5)</option>
                    <option value="opcoes">Múltipla Escolha</option>
                    <option value="texto">Texto Livre</option>
                  </select>
                  <button class="btn-del-perg" @click="form.perguntas.splice(idx, 1)">✕</button>
                </div>
                <input v-model="pq.texto" class="cfg-input" :placeholder="`Texto da pergunta ${idx + 1}...`" />
                <div v-if="pq.tipo === 'opcoes'" class="opcoes-edit">
                  <label style="font-size:11px;color:#94a3b8;">Opções (uma por linha)</label>
                  <textarea v-model="pq.opcoesRaw" class="cfg-input cfg-ta" rows="3" placeholder="Opção A&#10;Opção B&#10;Opção C"></textarea>
                </div>
              </div>
              <div v-if="!form.perguntas.length" class="no-perg">Nenhuma pergunta adicionada. Clique em "+ Adicionar".</div>
            </div>

            <div class="modal-actions">
              <button class="btn-cancel" @click="fecharModal">Cancelar</button>
              <button class="btn-salvar" :disabled="!form.titulo || salvando" @click="salvar">
                {{ salvando ? 'Salvando...' : '✅ Salvar' }}
              </button>
            </div>
            <div v-if="feedbackMsg" class="feedback-msg" :class="feedbackOk ? 'msg-ok' : 'msg-err'">{{ feedbackMsg }}</div>
          </div>
        </div>
      </div>
    </transition>

    <!-- PAINEL DE RESULTADOS -->
    <transition name="modal">
      <div v-if="resultadoAberto" class="modal-overlay" @click.self="resultadoAberto = false">
        <div class="modal-card modal-wide">
          <div class="modal-hdr">
            <h3>📈 Resultados — {{ pesquisaSelecionada?.titulo }}</h3>
            <button class="modal-close" @click="resultadoAberto = false">✕</button>
          </div>
          <div class="modal-body" v-if="resultado">
            <!-- KPIs -->
            <div class="res-kpis">
              <div class="kpi kpi-blue"><span class="kpi-val">{{ resultado.total }}</span><span class="kpi-lbl">Respostas</span></div>
              <div class="kpi kpi-green"><span class="kpi-val">{{ resultado.nps }}</span><span class="kpi-lbl">eNPS</span></div>
              <div class="kpi kpi-em"><span class="kpi-val">{{ resultado.promotores }}%</span><span class="kpi-lbl">Promotores</span></div>
              <div class="kpi kpi-neu"><span class="kpi-val">{{ resultado.neutros }}%</span><span class="kpi-lbl">Neutros</span></div>
              <div class="kpi kpi-det"><span class="kpi-val">{{ resultado.detratores }}%</span><span class="kpi-lbl">Detratores</span></div>
            </div>
            <!-- Por pergunta -->
            <div class="res-perguntas">
              <div v-for="pq in resultado.perguntas" :key="pq.id" class="res-pq">
                <div class="res-pq-hdr">
                  <span class="res-pq-tipo-chip">{{ tipoLabel(pq.tipo) }}</span>
                  <span class="res-pq-texto">{{ pq.texto }}</span>
                </div>
                <!-- NPS / estrelas: mostra média -->
                <div v-if="pq.tipo === 'nps' || pq.tipo === 'estrelas'" class="res-media">
                  <span class="res-media-num" :style="{ color: notaCor(pq.media, pq.tipo) }">
                    {{ pq.media?.toFixed(1) ?? '—' }}
                  </span>
                  <span class="res-media-max">/{{ pq.tipo === 'nps' ? 10 : 5 }}</span>
                  <div class="res-bar-wrap">
                    <div class="res-bar-fill" :style="{ width: (pq.media / (pq.tipo === 'nps' ? 10 : 5) * 100) + '%', background: notaCor(pq.media, pq.tipo) }"></div>
                  </div>
                </div>
                <!-- Opções: ranking -->
                <div v-else-if="pq.tipo === 'opcoes'" class="res-opcoes">
                  <div v-for="op in pq.ranking" :key="op.valor" class="res-op-row">
                    <span class="res-op-val">{{ op.valor }}</span>
                    <div class="res-bar-wrap"><div class="res-bar-fill" :style="{ width: op.pct + '%', background: '#8b5cf6' }"></div></div>
                    <span class="res-op-pct">{{ op.pct }}%</span>
                  </div>
                </div>
                <!-- Texto: amostra -->
                <div v-else class="res-textos">
                  <div v-for="(t, ti) in (pq.textos ?? []).slice(0, 4)" :key="ti" class="res-texto-item">
                    "{{ t }}"
                  </div>
                  <p v-if="!pq.textos?.length" style="color:#94a3b8;font-size:12px;">Sem respostas de texto.</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="state-center"><div class="spinner"></div><p>Carregando resultados...</p></div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded      = ref(false)
const carregando  = ref(true)
const lista       = ref([])

const modalAberto    = ref(false)
const editandoId     = ref(null)
const salvando       = ref(false)
const feedbackMsg    = ref('')
const feedbackOk     = ref(true)

const resultadoAberto     = ref(false)
const pesquisaSelecionada = ref(null)
const resultado           = ref(null)

const form = reactive({
  titulo: '', desc: '', inicio: '', fim: '',
  perguntas: [],
})

// ── Fetch ─────────────────────────────────────────────────────────
const fetchLista = async () => {
  carregando.value = true
  try {
    const { data } = await api.get('/api/v3/pesquisas/admin')
    lista.value = data.pesquisas ?? []
  } catch { lista.value = [] }
  carregando.value = false
}

onMounted(async () => {
  await fetchLista()
  setTimeout(() => { loaded.value = true }, 80)
})

// ── Modal criar/editar ────────────────────────────────────────────
function abrirModalNova() {
  editandoId.value = null
  Object.assign(form, { titulo: '', desc: '', inicio: '', fim: '', perguntas: [] })
  feedbackMsg.value = ''
  modalAberto.value = true
}

function editarPesquisa(p) {
  editandoId.value = p.id
  Object.assign(form, {
    titulo: p.titulo, desc: p.desc ?? '', inicio: p.inicio ?? '', fim: p.fim ?? '',
    perguntas: (p.perguntas ?? []).map(pq => ({
      id: pq.id, tipo: pq.tipo, texto: pq.texto,
      opcoesRaw: (pq.opcoes ?? []).join('\n'),
    })),
  })
  feedbackMsg.value = ''
  modalAberto.value = true
}

function fecharModal() { modalAberto.value = false }

function addPergunta() {
  form.perguntas.push({ tipo: 'nps', texto: '', opcoesRaw: '' })
}

async function salvar() {
  salvando.value = true
  feedbackMsg.value = ''
  const payload = {
    titulo: form.titulo, desc: form.desc,
    inicio: form.inicio, fim: form.fim,
    perguntas: form.perguntas.map((pq, i) => ({
      id: pq.id ?? null,
      tipo: pq.tipo, texto: pq.texto, ordem: i + 1,
      opcoes: pq.tipo === 'opcoes' ? pq.opcoesRaw.split('\n').map(s => s.trim()).filter(Boolean) : [],
    })),
  }
  try {
    if (editandoId.value) {
      await api.put(`/api/v3/pesquisas/${editandoId.value}`, payload)
    } else {
      await api.post('/api/v3/pesquisas', payload)
    }
    feedbackOk.value = true
    feedbackMsg.value = '✅ Salvo com sucesso!'
    await fetchLista()
    setTimeout(fecharModal, 1200)
  } catch (e) {
    feedbackOk.value = false
    feedbackMsg.value = `Erro: ${e.response?.data?.erro ?? e.message}`
  } finally { salvando.value = false }
}

async function mudarStatus(p, s) {
  try { await api.patch(`/api/v3/pesquisas/${p.id}/status`, { status: s }); await fetchLista() }
  catch { alert('Erro ao mudar status') }
}

async function excluir(p) {
  if (!confirm(`Excluir "${p.titulo}"? As respostas serão perdidas.`)) return
  try { await api.delete(`/api/v3/pesquisas/${p.id}`); await fetchLista() }
  catch { alert('Erro ao excluir') }
}

// ── Resultados ────────────────────────────────────────────────────
async function verResultados(p) {
  pesquisaSelecionada.value = p
  resultado.value = null
  resultadoAberto.value = true
  try {
    const { data } = await api.get(`/api/v3/pesquisas/${p.id}/resultados`)
    resultado.value = data
  } catch { resultado.value = { total: 0, nps: 0, promotores: 0, neutros: 0, detratores: 0, perguntas: [] } }
}

// ── Helpers ───────────────────────────────────────────────────────
const statusLabel = s => ({ rascunho: '📋 Rascunho', aberta: '🟢 Aberta', encerrada: '🔒 Encerrada' }[s] ?? s)
const tipoLabel   = t => ({ nps: 'NPS', estrelas: '⭐', opcoes: 'Opções', texto: '📝 Texto' }[t] ?? t)

const fmtData = d => d ? new Date(d + 'T12:00:00').toLocaleDateString('pt-BR') : '—'

const notaCor = (v, tipo) => {
  const max = tipo === 'nps' ? 10 : 5
  const pct = v / max
  return pct >= 0.7 ? '#10b981' : pct >= 0.5 ? '#f59e0b' : '#ef4444'
}
</script>

<style scoped>
.padm-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* Hero */
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #1a0a2a 55%, #0a1a1a 100%);
  opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 220px; height: 220px; background: #8b5cf6; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.btn-nova { padding: 12px 22px; border-radius: 14px; border: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; white-space: nowrap; }

/* Lista */
.lista-wrap { opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.lista-wrap.loaded { opacity: 1; transform: none; }
.state-center { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px; gap: 12px; font-size: 28px; }
.state-center p { font-size: 14px; color: #94a3b8; margin: 0; }
.spinner { width: 26px; height: 26px; border: 3px solid #e2e8f0; border-top-color: #8b5cf6; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pesq-list { display: flex; flex-direction: column; gap: 12px; }
.pesq-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px 20px;
  animation: cardIn 0.3s cubic-bezier(0.22,1,0.36,1) calc(var(--ri) * 40ms) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.pc-top { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.pc-status-chip { display: inline-block; font-size: 10px; font-weight: 800; padding: 2px 10px; border-radius: 8px; margin-bottom: 6px; }
.sc-rascunho  { background: #f8fafc; color: #64748b; }
.sc-aberta    { background: #f0fdf4; color: #065f46; }
.sc-encerrada { background: #f1f5f9; color: #475569; }
.pc-titulo { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.pc-desc { font-size: 12px; color: #64748b; margin: 0; }
.pc-meta { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; }
.pc-meta span { font-size: 11px; color: #94a3b8; font-weight: 600; }
.pc-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.act-btn { padding: 7px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; color: #475569; }
.act-ed:hover  { border-color: #3b82f6; color: #1d4ed8; background: #eff6ff; }
.act-pub:hover { border-color: #10b981; color: #065f46; background: #f0fdf4; }
.act-enc:hover { border-color: #f59e0b; color: #92400e; background: #fffbeb; }
.act-res:hover { border-color: #8b5cf6; color: #6d28d9; background: #f3f0ff; }
.act-del:hover { border-color: #ef4444; color: #b91c1c; background: #fef2f2; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-wide { max-width: 760px; }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 14px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; font-size: 14px; color: #64748b; }
.modal-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.form-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-ta { resize: vertical; min-height: 60px; }

/* Perguntas */
.perg-section { gap: 8px; }
.perg-hdr { display: flex; align-items: center; justify-content: space-between; }
.btn-add-perg { padding: 5px 12px; border-radius: 8px; border: 1.5px solid #8b5cf6; background: #f3f0ff; color: #6d28d9; font-size: 12px; font-weight: 800; cursor: pointer; }
.perg-row { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.perg-row-top { display: flex; align-items: center; gap: 8px; }
.perg-num { font-size: 11px; font-weight: 800; color: #8b5cf6; background: #f3f0ff; border-radius: 6px; padding: 2px 7px; }
.tipo-select { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 5px 8px; font-size: 12px; font-family: inherit; background: #fff; flex: 1; outline: none; }
.btn-del-perg { border: none; background: #fef2f2; color: #ef4444; border-radius: 6px; width: 24px; height: 24px; cursor: pointer; font-size: 12px; }
.no-perg { text-align: center; font-size: 12px; color: #94a3b8; padding: 16px; }
.opcoes-edit { display: flex; flex-direction: column; gap: 4px; }
.modal-actions { display: flex; gap: 10px; }
.btn-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.btn-salvar { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
.btn-salvar:disabled { opacity: 0.5; cursor: not-allowed; }
.feedback-msg { padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; text-align: center; }
.msg-ok  { background: #f0fdf4; color: #065f46; }
.msg-err { background: #fef2f2; color: #b91c1c; }

/* Resultados */
.res-kpis { display: flex; gap: 10px; flex-wrap: wrap; }
.kpi { display: flex; flex-direction: column; align-items: center; padding: 12px 18px; border-radius: 14px; border: 1px solid #e2e8f0; flex: 1; min-width: 80px; }
.kpi-val { font-size: 22px; font-weight: 900; }
.kpi-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-top: 2px; }
.kpi-blue .kpi-val  { color: #3b82f6; }
.kpi-green .kpi-val { color: #10b981; }
.kpi-em .kpi-val    { color: #34d399; }
.kpi-neu .kpi-val   { color: #94a3b8; }
.kpi-det .kpi-val   { color: #ef4444; }
.res-perguntas { display: flex; flex-direction: column; gap: 14px; }
.res-pq { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; }
.res-pq-hdr { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; }
.res-pq-tipo-chip { font-size: 10px; font-weight: 800; background: #f3f0ff; color: #6d28d9; padding: 2px 8px; border-radius: 6px; white-space: nowrap; }
.res-pq-texto { font-size: 13px; font-weight: 700; color: #1e293b; }
.res-media { display: flex; align-items: center; gap: 10px; }
.res-media-num { font-size: 28px; font-weight: 900; }
.res-media-max { font-size: 14px; color: #94a3b8; font-weight: 700; }
.res-bar-wrap { flex: 1; height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
.res-bar-fill { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
.res-opcoes { display: flex; flex-direction: column; gap: 6px; }
.res-op-row { display: flex; align-items: center; gap: 10px; }
.res-op-val { font-size: 12px; font-weight: 600; color: #475569; width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.res-op-pct { font-size: 12px; font-weight: 800; color: #6d28d9; width: 36px; text-align: right; }
.res-textos { display: flex; flex-direction: column; gap: 6px; }
.res-texto-item { font-size: 12px; color: #475569; background: #fff; border-radius: 8px; padding: 8px 12px; border-left: 3px solid #8b5cf6; font-style: italic; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
