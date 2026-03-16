<template>
  <div class="com-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📢 Comunicação Interna</span>
          <h1 class="hero-title">Comunicados</h1>
          <p class="hero-sub">{{ naoLidos }} não lido{{ naoLidos !== 1 ? 's' : '' }} · {{ comunicados.length }} comunicados</p>
        </div>
        <div class="hero-actions">
          <button class="ha-btn sec" @click="marcarTodosLidos">✅ Marcar todos como lidos</button>
          <button class="ha-btn primary" @click="abrirModalNovo">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Comunicado
          </button>
        </div>
      </div>
    </div>

    <!-- FILTROS -->
    <div class="filters-row" :class="{ loaded }">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar comunicado..." />
      </div>
      <div class="cat-pills">
        <button v-for="c in categorias" :key="c.val" class="cat-pill"
          :class="{ 'cp-active': catFiltro === c.val }"
          :style="{ '--cc': c.cor }"
          @click="catFiltro = catFiltro === c.val ? '' : c.val">
          {{ c.ico }} {{ c.nome }}
          <span class="cp-count" v-if="contaPorCat(c.val) > 0">{{ contaPorCat(c.val) }}</span>
        </button>
      </div>
      <select v-model="apenasNaoLidos" class="filter-sel">
        <option :value="false">Todos</option>
        <option :value="true">Não lidos</option>
      </select>
    </div>

    <!-- LISTA -->
    <div class="com-list" :class="{ loaded }">
      <div v-if="loading" class="state-box"><div class="spinner"></div></div>
      <template v-else>
        <!-- Fixados -->
        <div v-for="(c, i) in fixados" :key="'f-' + (c.id ?? c._localId)"
          class="com-item ci-fixado"
          :class="{ 'ci-unread': !c.lido }"
          :style="{ '--cid': `${i * 35}ms` }">
          <div class="ci-pin-tag">📌 Fixado</div>
          <div class="ci-inner" @click="abrirComunicado(c)">
            <div class="ci-left" :style="{ background: catCor(c.categoria) + '15', borderColor: catCor(c.categoria) + '40' }">
              <span class="ci-ico">{{ catIco(c.categoria) }}</span>
            </div>
            <div class="ci-body">
              <div class="ci-hdr">
                <span class="ci-titulo" :class="{ 'ct-bold': !c.lido }">{{ c.titulo }}</span>
                <div class="ci-badges">
                  <span class="ci-prioridade" v-if="c.prioridade === 'urgente'">🔴 Urgente</span>
                  <span class="ci-prioridade ci-imp" v-else-if="c.prioridade === 'importante'">🟡 Importante</span>
                  <span class="ci-cat" :style="{ color: catCor(c.categoria), background: catCor(c.categoria) + '12' }">{{ catNome(c.categoria) }}</span>
                </div>
              </div>
              <p class="ci-preview">{{ c.preview || resumo(c.conteudo) }}</p>
              <div class="ci-meta">
                <span class="ci-autor">{{ c.autorNome }} · {{ c.autorSetor }}</span>
                <span class="ci-data">{{ formatDate(c.data) }}</span>
              </div>
            </div>
            <div class="ci-arrow">›</div>
          </div>
          <div class="ci-crud-actions" v-if="c.meu || isAdmin">
            <button class="ci-act-btn" @click.stop="editarComunicado(c)" title="Editar"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <button class="ci-act-btn ci-act-del" @click.stop="excluirComunicado(c)" title="Excluir"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
          </div>
        </div>

        <!-- Normais -->
        <div v-for="(c, i) in naoFixados" :key="'n-' + (c.id ?? i)"
          class="com-item"
          :class="{ 'ci-unread': !c.lido }"
          :style="{ '--cid': `${(fixados.length + i) * 35}ms` }">
          <div class="ci-inner" @click="abrirComunicado(c)">
            <div class="ci-left" :style="{ background: catCor(c.categoria) + '15', borderColor: catCor(c.categoria) + '40' }">
              <span class="ci-ico">{{ catIco(c.categoria) }}</span>
            </div>
            <div class="ci-body">
              <div class="ci-hdr">
                <span class="ci-titulo" :class="{ 'ct-bold': !c.lido }">{{ c.titulo }}</span>
                <div class="ci-badges">
                  <span class="ci-prioridade" v-if="c.prioridade === 'urgente'">🔴 Urgente</span>
                  <span class="ci-prioridade ci-imp" v-else-if="c.prioridade === 'importante'">🟡 Importante</span>
                  <span class="ci-cat" :style="{ color: catCor(c.categoria), background: catCor(c.categoria) + '12' }">{{ catNome(c.categoria) }}</span>
                </div>
              </div>
              <p class="ci-preview">{{ c.preview || resumo(c.conteudo) }}</p>
              <div class="ci-meta">
                <span class="ci-autor">{{ c.autorNome }} · {{ c.autorSetor }}</span>
                <span class="ci-data">{{ formatDate(c.data) }}</span>
                <span v-if="!c.lido" class="ci-unread-dot"></span>
              </div>
            </div>
            <div class="ci-arrow">›</div>
          </div>
          <div class="ci-crud-actions" v-if="c.meu || isAdmin">
            <button class="ci-act-btn" @click.stop="editarComunicado(c)" title="Editar"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <button class="ci-act-btn ci-act-del" @click.stop="excluirComunicado(c)" title="Excluir"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
          </div>
        </div>

        <div v-if="comunicadosFiltrados.length === 0" class="state-empty">
          <span>📭</span><p>Nenhum comunicado encontrado.</p>
        </div>
      </template>
    </div>

    <!-- MODAL LEITURA -->
    <teleport to="body">
      <transition name="modal">
        <div v-if="comunicadoAberto" class="modal-overlay" @click.self="fecharModal">
          <div class="modal-card">
            <div class="modal-top" :style="{ background: `linear-gradient(135deg, ${catCor(comunicadoAberto.categoria)}22, ${catCor(comunicadoAberto.categoria)}08)` }">
              <div class="mt-cat">
                <span class="mt-ico">{{ catIco(comunicadoAberto.categoria) }}</span>
                <span class="mt-cat-nome" :style="{ color: catCor(comunicadoAberto.categoria) }">{{ catNome(comunicadoAberto.categoria) }}</span>
              </div>
              <button class="modal-close" @click="fecharModal">✕</button>
            </div>
            <div class="modal-body-com">
              <h2 class="mb-titulo">{{ comunicadoAberto.titulo }}</h2>
              <div class="mb-meta">
                <img :src="`https://api.dicebear.com/7.x/initials/svg?seed=${comunicadoAberto.autorNome}&backgroundColor=3b82f6`" class="mb-avatar" />
                <div>
                  <span class="mb-autor">{{ comunicadoAberto.autorNome }}</span>
                  <span class="mb-setor">{{ comunicadoAberto.autorSetor }} · {{ formatDateLong(comunicadoAberto.data) }}</span>
                </div>
              </div>
              <div class="mb-content" v-html="comunicadoAberto.conteudo"></div>
            </div>
          </div>
        </div>
      </transition>

      <!-- MODAL CRIAR / EDITAR -->
      <transition name="modal">
        <div v-if="modalForm" class="modal-overlay" @click.self="modalForm = false">
          <div class="modal-card modal-form-card">
            <div class="modal-top" style="background:#f8fafc">
              <h2 class="mf-title">{{ editandoId ? 'Editar Comunicado' : 'Novo Comunicado' }}</h2>
              <button class="modal-close" @click="modalForm = false">✕</button>
            </div>
            <div class="modal-body-com" style="display:flex;flex-direction:column;gap:16px">

              <div class="form-group">
                <label>Título <span class="req">*</span></label>
                <input v-model="form.titulo" type="text" class="form-input" placeholder="Ex: Calendário de Férias Coletivas 2026" />
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label>Categoria</label>
                  <select v-model="form.categoria" class="form-input">
                    <option v-for="c in categorias" :key="c.val" :value="c.val">{{ c.ico }} {{ c.nome }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Prioridade</label>
                  <select v-model="form.prioridade" class="form-input">
                    <option value="normal">Normal</option>
                    <option value="importante">🟡 Importante</option>
                    <option value="urgente">🔴 Urgente</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Conteúdo <span class="req">*</span></label>
                <textarea v-model="form.conteudo" class="form-input" rows="6" placeholder="Escreva o comunicado aqui. Suporta HTML básico: <strong>, <p>, <ul><li>..."></textarea>
              </div>

              <label class="form-check">
                <input type="checkbox" v-model="form.fixado" />
                <span>📌 Fixar no topo da lista</span>
              </label>

              <div v-if="erroModal" class="form-erro">{{ erroModal }}</div>
              <div v-if="okModal" class="form-ok">{{ okModal }}</div>

              <div class="form-actions">
                <button class="btn-cancel" @click="modalForm = false" :disabled="salvando">Cancelar</button>
                <button class="btn-publicar" @click="publicar" :disabled="!formValido || salvando">
                  <div v-if="salvando" class="btn-spinner"></div>
                  <template v-else>{{ editandoId ? 'Salvar Alterações' : 'Publicar' }}</template>
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const authStore = useAuthStore()
const isAdmin   = computed(() => authStore.user?.USUARIO_ADMIN ?? false)

const loaded  = ref(false)
const loading = ref(true)
const salvando= ref(false)
const busca   = ref('')
const catFiltro     = ref('')
const apenasNaoLidos= ref(false)
const comunicadoAberto = ref(null)
const modalForm  = ref(false)
const editandoId = ref(null)
const erroModal  = ref('')
const okModal    = ref('')

const comunicados = ref([])

const form = reactive({ titulo: '', conteudo: '', categoria: 'rh', prioridade: 'normal', fixado: false })

const categorias = [
  { val: 'rh',         ico: '👥', nome: 'RH',          cor: '#6366f1' },
  { val: 'financeiro', ico: '💰', nome: 'Financeiro',   cor: '#f59e0b' },
  { val: 'operacional',ico: '⚙️', nome: 'Operacional',  cor: '#3b82f6' },
  { val: 'saude',      ico: '🏥', nome: 'Saúde',        cor: '#10b981' },
  { val: 'urgente',    ico: '🚨', nome: 'Urgente',      cor: '#ef4444' },
]

// ── Computed ──────────────────────────────────────────────────
const naoLidos = computed(() => comunicados.value.filter(c => !c.lido).length)
const formValido= computed(() => form.titulo.trim() && form.conteudo.trim())

const comunicadosFiltrados = computed(() => {
  let list = [...comunicados.value].sort((a, b) => {
    if (a.fixado && !b.fixado) return -1
    if (!a.fixado && b.fixado) return 1
    return (b.data ?? '').localeCompare(a.data ?? '')
  })
  if (busca.value) { const t = busca.value.toLowerCase(); list = list.filter(c => c.titulo.toLowerCase().includes(t) || (c.conteudo ?? '').toLowerCase().includes(t)) }
  if (catFiltro.value)  list = list.filter(c => c.categoria === catFiltro.value)
  if (apenasNaoLidos.value) list = list.filter(c => !c.lido)
  return list
})

const fixados    = computed(() => comunicadosFiltrados.value.filter(c => c.fixado))
const naoFixados = computed(() => comunicadosFiltrados.value.filter(c => !c.fixado))

// ── Carregamento ──────────────────────────────────────────────
const mockData = [
  { id: 1, titulo: 'Calendário de Férias Coletivas 2026', categoria: 'rh', prioridade: 'importante', conteudo: '<p>O período de <strong>férias coletivas</strong> será entre 21/12/2026 e 04/01/2027.</p>', autorNome: 'Luciana Ferreira', autorSetor: 'Gestão de Pessoas', data: '2026-02-20', lido: false, fixado: true, meu: false },
  { id: 2, titulo: 'Novo Sistema de Ponto Digital', categoria: 'operacional', prioridade: 'importante', conteudo: '<p>A partir de <strong>01/03/2026</strong>, o registro de ponto será via leitores biométricos.</p>', autorNome: 'Carlos Mendes', autorSetor: 'TI Hospitalar', data: '2026-02-18', lido: false, fixado: false, meu: false },
  { id: 3, titulo: '⚠️ Protocolo H1N1 — Ala D', categoria: 'urgente', prioridade: 'urgente', conteudo: '<p><strong>⚠️ ATENÇÃO:</strong> Casos confirmados de H1N1 na ala D. Uso de N95 obrigatório.</p>', autorNome: 'Dra. Ingrid Sousa', autorSetor: 'CCIH', data: '2026-02-19', lido: false, fixado: false, meu: false },
  { id: 4, titulo: 'Pagamento de Fevereiro — Confirmado', categoria: 'financeiro', prioridade: 'normal', conteudo: '<p>O pagamento da competência <strong>Fevereiro/2026</strong> será realizado em 25/02/2026.</p>', autorNome: 'Fernando Alves', autorSetor: 'Financeiro', data: '2026-02-15', lido: true, fixado: false, meu: false },
  { id: 5, titulo: 'Campanha de Vacinação Antigripal 2026', categoria: 'saude', prioridade: 'importante', conteudo: '<p>Vacinação gratuita de 06 a 30/04/2026 no Ambulatório B — Sala 12.</p>', autorNome: 'Enf. Mariana Luz', autorSetor: 'Saúde do Trabalhador', data: '2026-02-10', lido: true, fixado: false, meu: false },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/comunicados')
    if (data.fallback || !data.comunicados?.length) {
      // Backend retornou fallback ou vazio: usa mock rico
      comunicados.value = mockData
    } else {
      comunicados.value = data.comunicados
    }
  } catch {
    comunicados.value = mockData
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
})

// ── Ações CRUD ────────────────────────────────────────────────
const abrirModalNovo = () => {
  editandoId.value = null
  Object.assign(form, { titulo: '', conteudo: '', categoria: 'rh', prioridade: 'normal', fixado: false })
  erroModal.value = ''; okModal.value = ''
  modalForm.value = true
}

const editarComunicado = (c) => {
  editandoId.value = c.id
  Object.assign(form, { titulo: c.titulo, conteudo: c.conteudo, categoria: c.categoria, prioridade: c.prioridade, fixado: c.fixado })
  erroModal.value = ''; okModal.value = ''
  modalForm.value = true
}

const publicar = async () => {
  if (!formValido.value) return
  salvando.value = true; erroModal.value = ''
  const payload = { titulo: form.titulo, conteudo: form.conteudo, categoria: form.categoria, prioridade: form.prioridade, fixado: form.fixado }
  try {
    if (editandoId.value) {
      await api.put(`/api/v3/comunicados/${editandoId.value}`, payload)
      const idx = comunicados.value.findIndex(c => c.id === editandoId.value)
      if (idx >= 0) comunicados.value[idx] = { ...comunicados.value[idx], ...payload }
      okModal.value = 'Comunicado atualizado!'
    } else {
      const { data } = await api.post('/api/v3/comunicados', payload)
      const novo = {
        id: data.id ?? Date.now(),
        ...payload,
        autorNome: authStore.user?.nome ?? authStore.user?.USUARIO_LOGIN ?? 'Você',
        autorSetor: '',
        data: new Date().toISOString().slice(0, 10),
        lido: true,
        meu: true,
        preview: resumo(form.conteudo),
      }
      if (form.fixado) { comunicados.value.unshift(novo) } else { comunicados.value.push(novo) }
      okModal.value = 'Comunicado publicado!'
    }
    setTimeout(() => { modalForm.value = false; okModal.value = '' }, 1200)
  } catch (e) {
    erroModal.value = e.response?.data?.erro || 'Erro ao publicar.'
  } finally {
    salvando.value = false
  }
}

const excluirComunicado = async (c) => {
  if (!confirm(`Excluir "${c.titulo}"?`)) return
  try {
    if (c.id) await api.delete(`/api/v3/comunicados/${c.id}`)
    comunicados.value = comunicados.value.filter(x => x.id !== c.id)
  } catch (e) { alert(e.response?.data?.erro || 'Erro ao excluir.') }
}

const abrirComunicado = (c) => { c.lido = true; comunicadoAberto.value = c }
const fecharModal = () => { comunicadoAberto.value = null }
const marcarTodosLidos = () => { comunicados.value.forEach(c => c.lido = true) }

// ── Helpers ───────────────────────────────────────────────────
const resumo = (html) => mb_substr(strip_tags(html ?? ''), 140)
const strip_tags = (s) => s.replace(/<[^>]*>/g, '')
const mb_substr  = (s, n) => s.length > n ? s.slice(0, n) + '...' : s
const catCor  = (v) => categorias.find(c => c.val === v)?.cor  ?? '#64748b'
const catIco  = (v) => categorias.find(c => c.val === v)?.ico  ?? '📝'
const catNome = (v) => categorias.find(c => c.val === v)?.nome ?? v
const contaPorCat = (v) => comunicados.value.filter(c => c.categoria === v && !c.lido).length
const formatDate  = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
const formatDateLong = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.com-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1420 55%, #0a1a1a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #3b82f6; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #8b5cf6; left: 30%; bottom: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #60a5fa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.ha-btn { display: flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; font-family: inherit; }
.ha-btn.sec { border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); color: #e2e8f0; }
.ha-btn.sec:hover { background: rgba(255,255,255,0.14); }
.ha-btn.primary { border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.ha-btn.primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }

.filters-row { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.08s; }
.filters-row.loaded { opacity: 1; transform: none; }
.search-wrap { display: flex; align-items: center; gap: 8px; flex: 0 0 200px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.cat-pills { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; }
.cat-pill { display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 99px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.cat-pill:hover { border-color: var(--cc); color: var(--cc); }
.cat-pill.cp-active { border-color: var(--cc); background: color-mix(in srgb, var(--cc) 10%, white); color: var(--cc); }
.cp-count { background: currentColor; color: #fff; border-radius: 99px; padding: 1px 6px; font-size: 10px; }
.filter-sel { border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }

.com-list { display: flex; flex-direction: column; gap: 8px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.com-list.loaded { opacity: 1; transform: none; }
.state-box { display: flex; justify-content: center; padding: 50px; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.state-empty { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; font-size: 36px; }
.state-empty p { font-size: 14px; color: #94a3b8; margin: 0; }

.com-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; animation: comIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--cid) both; transition: all 0.15s; }
.com-item:hover { box-shadow: 0 6px 24px -6px rgba(0,0,0,0.12); }
@keyframes comIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.ci-fixado { border: 1.5px solid #e0e7ff; }
.ci-pin-tag { background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 800; padding: 4px 14px; border-bottom: 1px solid #e0e7ff; }
.ci-inner { display: flex; align-items: stretch; cursor: pointer; }
.ci-unread { border-left: 3px solid #3b82f6; }
.ci-left { width: 56px; display: flex; align-items: center; justify-content: center; border-right: 1px solid; flex-shrink: 0; }
.ci-ico { font-size: 22px; }
.ci-body { flex: 1; padding: 14px 16px; min-width: 0; }
.ci-hdr { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
.ci-titulo { font-size: 14px; font-weight: 600; color: #475569; }
.ct-bold { font-weight: 800; color: #1e293b; }
.ci-badges { display: flex; align-items: center; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
.ci-prioridade { font-size: 11px; font-weight: 700; }
.ci-cat { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 8px; }
.ci-preview { font-size: 12px; color: #64748b; margin: 0 0 8px; line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-meta { display: flex; align-items: center; gap: 10px; }
.ci-autor, .ci-data { font-size: 11px; color: #94a3b8; }
.ci-unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; }
.ci-arrow { display: flex; align-items: center; padding: 0 16px; font-size: 20px; color: #cbd5e1; font-weight: 300; flex-shrink: 0; }
.ci-crud-actions { display: flex; gap: 6px; padding: 6px 14px; border-top: 1px solid #f8fafc; background: #f8fafc; justify-content: flex-end; }
.ci-act-btn { display: flex; align-items: center; gap: 4px; padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 7px; background: #fff; font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.13s; color: #64748b; }
.ci-act-btn:hover { border-color: #ddd6fe; color: #7c3aed; background: #f5f3ff; }
.ci-act-del:hover { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }

/* MODAIS */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 620px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 32px 64px rgba(0,0,0,0.22); }
.modal-form-card { max-width: 560px; }
.modal-top { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
.mt-cat { display: flex; align-items: center; gap: 8px; }
.mt-ico { font-size: 22px; } .mt-cat-nome { font-size: 13px; font-weight: 800; }
.mf-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; font-size: 14px; color: #64748b; }
.modal-body-com { padding: 20px 24px; overflow-y: auto; flex: 1; }
.mb-titulo { font-size: 20px; font-weight: 900; color: #1e293b; margin: 0 0 14px; line-height: 1.3; }
.mb-meta { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; }
.mb-avatar { width: 38px; height: 38px; border-radius: 10px; }
.mb-autor { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.mb-setor  { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.mb-content { font-size: 14px; color: #475569; line-height: 1.7; }
.mb-content :deep(p) { margin: 0 0 12px; }
.mb-content :deep(ul) { padding-left: 18px; margin: 8px 0 12px; }
.mb-content :deep(strong) { color: #1e293b; font-weight: 800; }

/* FORM */
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 9px 12px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: #6366f1; }
textarea.form-input { resize: vertical; min-height: 120px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-check { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; }
.req { color: #dc2626; }
.form-erro { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #dc2626; font-weight: 600; }
.form-ok   { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #15803d; font-weight: 600; }
.form-actions { display: flex; gap: 10px; justify-content: flex-end; }
.btn-cancel { padding: 9px 18px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #475569; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-publicar { display: flex; align-items: center; justify-content: center; gap: 7px; padding: 9px 20px; border: none; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; min-width: 120px; }
.btn-publicar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-publicar:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

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
