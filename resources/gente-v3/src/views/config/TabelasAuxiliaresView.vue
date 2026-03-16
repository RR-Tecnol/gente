<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📋 Tabelas de Apoio</span>
          <h1 class="hero-title">Tabelas Auxiliares</h1>
          <p class="hero-sub">Banco, Cidade, UF, Bairro, Cartório, Conselho, Tipo de Documento</p>
        </div>
        <button class="novo-btn" @click="abrirModal()">+ Novo Registro</button>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button v-for="t in abas" :key="t.id" class="tab-btn" :class="{ active: aba === t.id }" @click="trocarAba(t.id)">
        {{ t.ico }} {{ t.nome }}
      </button>
    </div>

    <!-- TABELA GENÉRICA -->
    <div class="table-card" :class="{ loaded }">
      <div class="tb-toolbar">
        <div class="search-wrap">
          <svg class="s-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="busca" class="s-input" :placeholder="`Buscar ${abaAtual.nome.toLowerCase()}...`" />
        </div>
      </div>
      <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <table v-else class="cfg-table">
        <thead><tr>
          <th v-for="col in abaAtual.colunas" :key="col.k">{{ col.l }}</th>
          <th>Ações</th>
        </tr></thead>
        <tbody>
          <tr v-for="item in itensFiltrados" :key="item.id" class="cfg-row">
            <td v-for="col in abaAtual.colunas" :key="col.k">
              <span :class="col.k === 'nome' ? 'item-nome' : ''">{{ item[col.k] || '—' }}</span>
            </td>
            <td>
              <div class="row-actions">
                <button class="act-btn" @click="abrirModal(item)" title="Editar">✏️</button>
                <button class="act-btn act-red" @click="remover(item)" title="Remover">🗑️</button>
              </div>
            </td>
          </tr>
          <tr v-if="itensFiltrados.length === 0"><td :colspan="abaAtual.colunas.length + 1" class="empty-td">Nenhum registro encontrado.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>{{ form.id ? `✏️ Editar ${abaAtual.nome}` : `+ Novo ${abaAtual.nome}` }}</h3><button class="modal-close" @click="modalAberto = false">✕</button></div>
          <div class="modal-body">
            <div class="form-row" v-if="abaAtual.campos.length > 2">
              <div class="form-group" v-for="campo in abaAtual.campos" :key="campo.k" :class="campo.full ? 'col-2' : ''">
                <label>{{ campo.l }} <span v-if="campo.req" class="req">*</span></label>
                <select v-if="campo.options" v-model="form[campo.k]" class="cfg-input">
                  <option v-for="o in campo.options" :key="o.v" :value="o.v">{{ o.l }}</option>
                </select>
                <input v-else v-model="form[campo.k]" class="cfg-input" :placeholder="campo.l" :maxlength="campo.max" />
              </div>
            </div>
            <template v-else>
              <div class="form-group" v-for="campo in abaAtual.campos" :key="campo.k">
                <label>{{ campo.l }} <span v-if="campo.req" class="req">*</span></label>
                <input v-model="form[campo.k]" class="cfg-input" :placeholder="campo.l" :maxlength="campo.max" />
              </div>
            </template>
            <div v-if="erroModal" class="erro-msg">⚠️ {{ erroModal }}</div>
            <div v-if="okModal" class="ok-msg">✅ {{ okModal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ form.id ? 'Salvar' : 'Cadastrar' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false); const loading = ref(false); const aba = ref('banco')
const itens = ref([]); const busca = ref(''); const modalAberto = ref(false)
const salvando = ref(false); const erroModal = ref(''); const okModal = ref('')
const form = ref({})

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']

const abas = [
  { id: 'banco',          ico: '🏦', nome: 'Banco',         endpoint: '/api/v3/tabelas/banco',
    colunas: [{ k: 'codigo', l: 'Código' }, { k: 'nome', l: 'Nome' }],
    campos:  [{ k: 'codigo', l: 'Código COMPE', req: true, max: 10 }, { k: 'nome', l: 'Nome do Banco', req: true, max: 200 }] },
  { id: 'uf',             ico: '🗺️', nome: 'UF',            endpoint: '/api/v3/tabelas/uf',
    colunas: [{ k: 'sigla', l: 'Sigla' }, { k: 'nome', l: 'Estado' }, { k: 'regiao', l: 'Região' }],
    campos:  [{ k: 'sigla', l: 'Sigla (2 letras)', req: true, max: 2 }, { k: 'nome', l: 'Nome do Estado', req: true, max: 100 }, { k: 'regiao', l: 'Região', max: 30 }] },
  { id: 'cidade',         ico: '🏙️', nome: 'Cidade',        endpoint: '/api/v3/tabelas/cidade',
    colunas: [{ k: 'nome', l: 'Nome' }, { k: 'uf_sigla', l: 'UF' }, { k: 'ibge', l: 'Cód. IBGE' }],
    campos:  [{ k: 'nome', l: 'Nome da Cidade', req: true, max: 200 }, { k: 'uf_sigla', l: 'UF', req: true, options: UFS.map(u => ({ v: u, l: u })) }, { k: 'ibge', l: 'Código IBGE', max: 10 }] },
  { id: 'bairro',         ico: '🏘️', nome: 'Bairro',        endpoint: '/api/v3/tabelas/bairro',
    colunas: [{ k: 'nome', l: 'Nome' }, { k: 'cidade_nome', l: 'Cidade' }],
    campos:  [{ k: 'nome', l: 'Nome do Bairro', req: true, max: 200 }, { k: 'cidade_id', l: 'Cidade ID', max: 20 }] },
  { id: 'cartorio',       ico: '⚖️', nome: 'Cartório',      endpoint: '/api/v3/tabelas/cartorio',
    colunas: [{ k: 'nome', l: 'Nome' }, { k: 'cidade', l: 'Cidade' }],
    campos:  [{ k: 'nome', l: 'Nome do Cartório', req: true, max: 200 }, { k: 'cidade', l: 'Cidade', max: 100 }] },
  { id: 'conselho',       ico: '🪪', nome: 'Conselho',       endpoint: '/api/v3/tabelas/conselho',
    colunas: [{ k: 'sigla', l: 'Sigla' }, { k: 'nome', l: 'Conselho' }],
    campos:  [{ k: 'sigla', l: 'Sigla', req: true, max: 20 }, { k: 'nome', l: 'Nome do Conselho', req: true, max: 200 }] },
  { id: 'tipo_documento', ico: '📄', nome: 'Tipo de Doc',    endpoint: '/api/v3/tabelas/tipo-documento',
    colunas: [{ k: 'codigo', l: 'Código' }, { k: 'nome', l: 'Documento' }],
    campos:  [{ k: 'codigo', l: 'Código', req: true, max: 20 }, { k: 'nome', l: 'Nome do Documento', req: true, max: 200 }] },
]

const abaAtual = computed(() => abas.find(a => a.id === aba.value) || abas[0])
const itensFiltrados = computed(() => {
  if (!busca.value) return itens.value
  const t = busca.value.toLowerCase()
  return itens.value.filter(i => Object.values(i).some(v => String(v).toLowerCase().includes(t)))
})

const carregarItens = async () => {
  loading.value = true; busca.value = ''
  try { const { data } = await api.get(abaAtual.value.endpoint); itens.value = data.itens ?? [] }
  catch { itens.value = [] }
  finally { loading.value = false }
}

const trocarAba = (id) => { aba.value = id; carregarItens() }

onMounted(async () => {
  await carregarItens()
  setTimeout(() => { loaded.value = true }, 80)
})

const abrirModal = (item = null) => {
  erroModal.value = ''; okModal.value = ''
  if (item) { form.value = { ...item } }
  else { form.value = Object.fromEntries([{ k: 'id' }, ...abaAtual.value.campos].map(c => [c.k, ''])) }
  modalAberto.value = true
}

const salvar = async () => {
  const campoReq = abaAtual.value.campos.find(c => c.req && !form.value[c.k])
  if (campoReq) { erroModal.value = `${campoReq.l} é obrigatório.`; return }
  salvando.value = true
  try {
    const ep = abaAtual.value.endpoint
    if (form.value.id) { await api.put(`${ep}/${form.value.id}`, form.value); okModal.value = 'Atualizado!' }
    else { await api.post(ep, form.value); okModal.value = 'Cadastrado!' }
    await carregarItens()
    setTimeout(() => { modalAberto.value = false }, 1200)
  } catch (e) { erroModal.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const remover = async (item) => {
  if (!confirm(`Remover "${item.nome || item.id}"?`)) return
  try { await api.delete(`${abaAtual.value.endpoint}/${item.id}`); await carregarItens() }
  catch (e) { alert(e.response?.data?.erro || 'Erro ao remover.') }
}
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1a0f2a); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #c4b5fd; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.novo-btn { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.4); }
.tabs-bar { display: flex; gap: 4px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 6px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.06s; }
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn { padding: 7px 14px; border-radius: 10px; border: none; background: transparent; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s; font-family: inherit; white-space: nowrap; }
.tab-btn:hover { background: #f8fafc; }
.tab-btn.active { background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.1s; }
.table-card.loaded { opacity: 1; transform: none; }
.tb-toolbar { display: flex; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; background: #f8fafc; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 13px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 10px; color: #94a3b8; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cfg-table { width: 100%; border-collapse: collapse; }
.cfg-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cfg-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; }
.cfg-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.cfg-row:hover { background: #f8fafc; }
.cfg-row:last-child { border-bottom: none; }
.cfg-table td { padding: 11px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.item-nome { font-weight: 700; color: #1e293b; }
.row-actions { display: flex; gap: 4px; }
.act-btn { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.act-btn:hover { transform: translateY(-1px); }
.empty-td { text-align: center; padding: 40px; font-size: 14px; color: #94a3b8; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.col-2 { grid-column: span 2; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #7c3aed; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.ok-msg { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
