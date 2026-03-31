<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" />
        <div class="hs hs2" />
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏦 Gestão Financeira</span>
          <h1 class="hero-title">Módulo Consignatárias</h1>
          <p class="hero-sub">Gestão de múltiplas operadoras, layouts, processamento de margem e histórico de arquivos (Bloco B).</p>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'operadoras' }" @click="aba = 'operadoras'">🏢 Operadoras</button>
      <button class="tab-btn" :class="{ active: aba === 'importar' }" @click="aba = 'importar'">📥 Importar Arquivo</button>
      <button class="tab-btn" :class="{ active: aba === 'gerar' }" @click="aba = 'gerar'">📤 Gerar Remessa</button>
      <button class="tab-btn" :class="{ active: aba === 'historico' }" @click="aba = 'historico'; carregarHistorico()">📋 Histórico</button>
    </div>

    <!-- TAB 1: OPERADORAS -->
    <div v-if="aba === 'operadoras'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Lista de Operadoras Cadastradas</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalOp(null)">+ Nova Operadora</button>
        </div>
      </div>

      <div v-if="errorMsgOp" class="error-msg">{{ errorMsgOp }}</div>

      <div class="table-scroll" v-if="!isLoadingOp && operadoras.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>CNPJ</th>
              <th>Código</th>
              <th>Tipo</th>
              <th>Margem %</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(op, i) in operadoras" :key="op.CONSIGNATARIA_ID || i" class="data-row" :class="{ 'row-visible': loaded }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ op.CONSIGNATARIA_NOME }}</strong></td>
              <td>{{ op.CONSIGNATARIA_CNPJ }}</td>
              <td>{{ op.CONSIGNATARIA_CODIGO }}</td>
              <td><span class="badge badge-purple">{{ op.CONSIGNATARIA_TIPO }}</span></td>
              <td>{{ op.CONSIGNATARIA_MARGEM_MAX_PCT }}%</td>
              <td>
                <span class="status-badge" :class="op.CONSIGNATARIA_ATIVA ? 'ativo' : 'cancelado'">
                  {{ op.CONSIGNATARIA_ATIVA ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="row-actions">
                <button class="act-btn act-blue" @click="abrirModalOp(op)" title="Editar">✏️</button>
                <button class="act-btn" :class="op.CONSIGNATARIA_ATIVA ? 'act-red' : 'act-green'" @click="toggleStatus(op)" :title="op.CONSIGNATARIA_ATIVA ? 'Inativar' : 'Ativar'">
                  {{ op.CONSIGNATARIA_ATIVA ? '❌' : '✅' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="!isLoadingOp" class="empty-state">
        📭 Nenhuma operadora encontrada. Adicione a primeira.
      </div>
      
      <div v-if="isLoadingOp" class="spinner-wrap"><div class="spinner"></div></div>

      <!-- MODAL NOVA/EDITAR OPERADORA -->
      <div v-if="modalAberto" class="modal-overlay" @click.self="fecharModalOp">
        <div class="modal modal-md">
          <div class="modal-hdr">
            <h3 class="modal-title">{{ modoEdicao ? 'Editar Operadora' : 'Nova Operadora' }}</h3>
            <button class="modal-close" @click="fecharModalOp">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-grid">
              <div class="form-group col-full">
                <label>Nome Fantasia / Razão Social</label>
                <input v-model="formOp.CONSIGNATARIA_NOME" class="form-input" placeholder="Ex: Neoconsig" />
              </div>
              <div class="form-group">
                <label>CNPJ</label>
                <input v-model="formOp.CONSIGNATARIA_CNPJ" class="form-input" placeholder="00.000.000/0000-00" />
              </div>
              <div class="form-group">
                <label>Código da Operadora</label>
                <input v-model="formOp.CONSIGNATARIA_CODIGO" class="form-input" placeholder="Ex: NEO01" />
              </div>
              <div class="form-group">
                <label>Tipo de Consignação</label>
                <select v-model="formOp.CONSIGNATARIA_TIPO" class="form-input">
                  <option value="banco">Banco / Empréstimo</option>
                  <option value="cartao">Cartão de Crédito</option>
                  <option value="seguro">Seguro de Vida</option>
                  <option value="sindicato">Sindicato</option>
                </select>
              </div>
              <div class="form-group">
                <label>Margem Máxima (%)</label>
                <input type="number" v-model="formOp.CONSIGNATARIA_MARGEM_MAX_PCT" class="form-input" min="0" max="100" />
              </div>
              <div class="form-group col-full">
                <label>Contato / Email</label>
                <input v-model="formOp.CONSIGNATARIA_CONTATO" class="form-input" placeholder="Opcional. Email ou telefone de suporte" />
              </div>
            </div>
            <div v-if="erroModal" class="error-msg">{{ erroModal }}</div>
            <div v-if="sucessoModal" class="success-msg">{{ sucessoModal }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="fecharModalOp">Cancelar</button>
            <button class="btn-primary" @click="salvarOperadora" :disabled="salvandoOp">
              {{ salvandoOp ? '⏳ Salvando...' : 'Salvar Operadora' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: IMPORTAR -->
    <div v-if="aba === 'importar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📥 Importar Arquivo de Margem ou Retorno</h2>
      
      <div v-if="errorMsgImp" class="error-msg">{{ errorMsgImp }}</div>
      
      <div class="form-grid">
        <div class="form-group">
          <label>Operadora Parceira</label>
          <select v-model="formImp.consignataria_id" class="form-input" @change="carregarLayouts(formImp.consignataria_id)">
            <option value="">Selecione a operadora...</option>
            <option v-for="op in operadoras" :key="op.CONSIGNATARIA_ID" :value="op.CONSIGNATARIA_ID">
              {{ op.CONSIGNATARIA_NOME }}
            </option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Layout de Configuração</label>
          <select v-model="formImp.layout_id" class="form-input" :disabled="!formImp.consignataria_id || isLoadingLayouts">
            <option value="">{{ isLoadingLayouts ? 'Carregando layouts...' : 'Selecione o layout...' }}</option>
            <option v-for="ly in layoutsDisponiveis" :key="ly.LAYOUT_ID" :value="ly.LAYOUT_ID">
              {{ ly.LAYOUT_TIPO }} - {{ ly.LAYOUT_FORMATO }} (v{{ ly.LAYOUT_VERSAO }})
            </option>
          </select>
        </div>
        
        <div class="form-group col-full">
          <label>Arquivo Base (Multi-formato)</label>
          <input type="file" @change="setArquivo" class="form-input" />
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" @click="processarArquivo" :disabled="!isImportValid || isLoadingImp">
          {{ isLoadingImp ? '⏳ Processando...' : '⚙️ Processar Arquivo' }}
        </button>
      </div>

      <div class="log-box" v-if="logImportacao">
        <div class="log-label">Resultado do Processamento</div>
        <textarea class="form-input" rows="8" readonly :value="logImportacao"></textarea>
      </div>
    </div>

    <!-- TAB 3: GERAR -->
    <div v-if="aba === 'gerar'" class="section-card" :class="{ loaded }">
      <h2 class="section-title">📤 Gerar Arquivo de Remessa</h2>
      
      <div v-if="errorMsgGerar" class="error-msg">{{ errorMsgGerar }}</div>
      <div v-if="sucessoGerar" class="success-msg">{{ sucessoGerar }}</div>
      
      <div class="form-grid">
        <div class="form-group">
          <label>Operadora Beneficiária</label>
          <select v-model="formGerar.consignataria_id" class="form-input">
            <option value="">Selecione...</option>
            <option v-for="op in operadoras" :key="op.CONSIGNATARIA_ID" :value="op.CONSIGNATARIA_ID">
              {{ op.CONSIGNATARIA_NOME }}
            </option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Tipo do Arquivo</label>
          <select v-model="formGerar.tipo" class="form-input">
            <option value="financeiro">Base Financeira Total</option>
            <option value="cadastro">Atualização Cadastral (Cartão)</option>
            <option value="retorno-quitadas">Retorno: Parcelas Quitadas</option>
            <option value="retorno-pendentes">Retorno: Parcelas Pendentes</option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Competência (Ex: 202603)</label>
          <input v-model="formGerar.competencia" class="form-input" placeholder="AAAAMM" maxlength="6" />
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" @click="gerarRemessa" :disabled="!isGerarValid || isLoadingGerar">
          {{ isLoadingGerar ? '⏳ Gerando Remessa...' : '🚀 Gerar Arquivo' }}
        </button>
      </div>
    </div>

    <!-- TAB 4: HISTÓRICO -->
    <div v-if="aba === 'historico'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">📋 Histórico de Remessas e Retornos</h2>
      </div>

      <div class="filter-bar">
        <div class="search-wrap">
          <select v-model="formHist.consignataria_id" class="form-input small" @change="carregarHistorico">
            <option value="">Todas Operadoras</option>
            <option v-for="op in operadoras" :key="op.CONSIGNATARIA_ID" :value="op.CONSIGNATARIA_ID">
              {{ op.CONSIGNATARIA_NOME }}
            </option>
          </select>
        </div>
        <div class="search-wrap">
          <select v-model="formHist.status" class="form-input small" @change="carregarHistorico">
            <option value="">Todos Status</option>
            <option value="sucesso">Sucesso</option>
            <option value="erro">Erro</option>
            <option value="pendente">Pendente</option>
          </select>
        </div>
        <div class="search-wrap">
          <input v-model="formHist.competencia" @input="carregarHistorico" class="form-input small" placeholder="Comp. AAAAMM" maxlength="6" />
        </div>
      </div>

      <div v-if="errorMsgHist" class="error-msg" style="margin-top:1rem">{{ errorMsgHist }}</div>

      <div class="table-scroll" v-if="!isLoadingHist && historico.length > 0" style="margin-top:1rem">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Data Lançamento</th>
              <th>Competência</th>
              <th>Operadora</th>
              <th>Tipo</th>
              <th>Total Registros</th>
              <th>Total Valor Bruto</th>
              <th>Status</th>
              <th>Obs</th>
              <th>Baixar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(hist, i) in historico" :key="hist.REMESSA_ID || i" class="data-row row-visible" :style="{ '--row-delay': `${i * 20}ms` }">
              <td>{{ formatDateTime(hist.created_at) }}</td>
              <td><strong>{{ hist.REMESSA_COMPETENCIA }}</strong></td>
              <td>{{ getOpNome(hist.CONSIGNATARIA_ID) }}</td>
              <td><span class="badge badge-gray">{{ hist.REMESSA_TIPO }}</span></td>
              <td>{{ hist.REMESSA_TOTAL_REGISTROS || 0 }}</td>
              <td class="money">{{ formatMoney(hist.REMESSA_TOTAL_VALOR) }}</td>
              <td>
                <span class="status-badge" :class="hist.REMESSA_STATUS === 'sucesso' ? 'ativo' : (hist.REMESSA_STATUS === 'erro' ? 'cancelado' : 'pendente')">
                  {{ (hist.REMESSA_STATUS || 'UNKNOWN').toUpperCase() }}
                </span>
              </td>
              <td><span class="sub" style="max-width:180px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" :title="hist.REMESSA_OBS">{{ hist.REMESSA_OBS || '-' }}</span></td>
              <td class="row-actions">
                <button class="act-btn act-blue" @click="downloadHistorico(hist)" title="Download Arquivo Físico">⬇️</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="!isLoadingHist" class="empty-state" style="margin-top:1rem">
        📭 Nenhum registro de histórico encontrado.
      </div>
      
      <div v-if="isLoadingHist" class="spinner-wrap" style="margin-top:1rem"><div class="spinner"></div></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/plugins/axios'

// == GLOBAL ==
const aba = ref('operadoras')
const loaded = ref(false)

onMounted(async () => {
  await carregarOperadoras()
  setTimeout(() => loaded.value = true, 80)
})

// == HELPERS ==
const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0)
const formatDateTime = (iso) => {
  if (!iso) return '-'
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}
const getOpNome = (id) => {
  const o = operadoras.value.find(x => String(x.CONSIGNATARIA_ID) === String(id))
  return o ? o.CONSIGNATARIA_NOME : `[ID:${id}]`
}

// == TAB 1: OPERADORAS ==
const operadoras = ref([])
const isLoadingOp = ref(false)
const errorMsgOp = ref('')

async function carregarOperadoras() {
  isLoadingOp.value = true
  errorMsgOp.value = ''
  try {
    const { data } = await api.get('/api/v3/consignatarias')
    // O stub pode devolver {"ok":true} ou [], extraimos com safe check
    operadoras.value = Array.isArray(data) ? data : (data.data || data.retorno || [])
  } catch (e) {
    errorMsgOp.value = 'Erro ao carregar operadoras. Verifique o console.'
    console.error(e)
  } finally {
    isLoadingOp.value = false
  }
}

async function toggleStatus(op) {
  try {
    await api.patch(`/api/v3/consignatarias/${op.CONSIGNATARIA_ID}/toggle-ativa`)
    await carregarOperadoras()
  } catch (e) {
    errorMsgOp.value = 'Erro ao alterar status da operadora.'
  }
}

// Modal Operadoras
const modalAberto = ref(false)
const modoEdicao = ref(false)
const erroModal = ref('')
const sucessoModal = ref('')
const salvandoOp = ref(false)
const formOpObjOrigem = {
  CONSIGNATARIA_NOME: '',
  CONSIGNATARIA_CNPJ: '',
  CONSIGNATARIA_CODIGO: '',
  CONSIGNATARIA_TIPO: 'banco',
  CONSIGNATARIA_MARGEM_MAX_PCT: 30,
  CONSIGNATARIA_CONTATO: ''
}
const formOp = ref({ ...formOpObjOrigem })
let idEmEdicao = null

function fecharModalOp() {
  modalAberto.value = false
}

function abrirModalOp(op = null) {
  erroModal.value = ''
  sucessoModal.value = ''
  if (op) {
    modoEdicao.value = true
    idEmEdicao = op.CONSIGNATARIA_ID
    formOp.value = { ...op }
  } else {
    modoEdicao.value = false
    idEmEdicao = null
    formOp.value = { ...formOpObjOrigem }
  }
  modalAberto.value = true
}

async function salvarOperadora() {
  if (!formOp.value.CONSIGNATARIA_NOME || !formOp.value.CONSIGNATARIA_CODIGO || !formOp.value.CONSIGNATARIA_CNPJ) {
    erroModal.value = 'Preencha Nome, CNPJ e Código.'
    return
  }
  salvandoOp.value = true
  erroModal.value = ''
  sucessoModal.value = ''
  try {
    if (modoEdicao.value) {
      await api.put(`/api/v3/consignatarias/${idEmEdicao}`, formOp.value)
    } else {
      await api.post('/api/v3/consignatarias', formOp.value)
    }
    sucessoModal.value = 'Salvo com sucesso!'
    await carregarOperadoras()
    setTimeout(() => { fecharModalOp() }, 1000)
  } catch (e) {
    erroModal.value = e.response?.data?.aviso || 'Erro ao salvar operadora.'
  } finally {
    salvandoOp.value = false
  }
}

// == TAB 2: IMPORTAR ==
const formImp = ref({ consignataria_id: '', layout_id: '', arquivo: null })
const layoutsDisponiveis = ref([])
const isLoadingLayouts = ref(false)
const isLoadingImp = ref(false)
const errorMsgImp = ref('')
const logImportacao = ref('')

const isImportValid = computed(() => formImp.value.consignataria_id && formImp.value.layout_id && formImp.value.arquivo)

async function carregarLayouts(id) {
  formImp.value.layout_id = ''
  layoutsDisponiveis.value = []
  if (!id) return
  isLoadingLayouts.value = true
  try {
    const { data } = await api.get(`/api/v3/consignatarias/${id}/layouts`)
    layoutsDisponiveis.value = Array.isArray(data) ? data : (data.data || data.retorno || [])
  } catch (e) {
    console.error('Erro ao carregar layouts', e)
  } finally {
    isLoadingLayouts.value = false
  }
}

function setArquivo(e) {
  formImp.value.arquivo = e.target.files[0] || null
}

async function processarArquivo() {
  if (!isImportValid.value) return
  isLoadingImp.value = true
  errorMsgImp.value = ''
  logImportacao.value = ''
  
  const fd = new FormData()
  fd.append('arquivo', formImp.value.arquivo)
  fd.append('layout_id', formImp.value.layout_id)
  
  try {
    const res = await api.post(`/api/v3/consignatarias/${formImp.value.consignataria_id}/importar`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    logImportacao.value = JSON.stringify(res.data, null, 2)
  } catch (e) {
    errorMsgImp.value = 'Falha ao processar arquivo.'
    logImportacao.value = JSON.stringify(e.response?.data || e.message, null, 2)
  } finally {
    isLoadingImp.value = false
  }
}


// == TAB 3: GERAR ==
const formGerar = ref({ consignataria_id: '', tipo: 'financeiro', competencia: '' })
const isLoadingGerar = ref(false)
const errorMsgGerar = ref('')
const sucessoGerar = ref('')

const isGerarValid = computed(() => formGerar.value.consignataria_id && formGerar.value.tipo && formGerar.value.competencia.length === 6)

async function gerarRemessa() {
  if (!isGerarValid.value) return
  isLoadingGerar.value = true
  errorMsgGerar.value = ''
  sucessoGerar.value = ''
  
  try {
    const url = `/api/v3/consignatarias/remessas/gerar`
    // Stub em B4 requer post, embora a view instrua que poderia ser get, uso o POST configurado em B2
    const res = await api.post(url, {
      consignataria_id: formGerar.value.consignataria_id,
      tipo: formGerar.value.tipo,
      competencia: formGerar.value.competencia
    }, { responseType: 'blob' })
    
    // Tratamento de download de Blob via Axios
    const blob = new Blob([res.data])
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `remessa_${formGerar.value.consignataria_id}_${formGerar.value.competencia}.txt`
    a.click()
    sucessoGerar.value = 'Arquivo gerado com sucesso e download iniciado!'
  } catch (e) {
    errorMsgGerar.value = 'Erro ao gerar remessa bancária / retorno.'
  } finally {
    isLoadingGerar.value = false
  }
}


// == TAB 4: HISTÓRICO ==
const historico = ref([])
const formHist = ref({ consignataria_id: '', status: '', competencia: '' })
const isLoadingHist = ref(false)
const errorMsgHist = ref('')

// Adicionamos um timer debounce para nao bater na api a cada letra da competência se o usuário digitar
let histTimer = null

async function carregarHistorico() {
  clearTimeout(histTimer)
  histTimer = setTimeout(async () => {
    isLoadingHist.value = true
    errorMsgHist.value = ''
    try {
      const q = new URLSearchParams()
      if (formHist.value.consignataria_id) q.append('consignataria_id', formHist.value.consignataria_id)
      if (formHist.value.status) q.append('status', formHist.value.status)
      if (formHist.value.competencia) q.append('competencia', formHist.value.competencia)
      
      const { data } = await api.get(`/api/v3/consignatarias/remessas?${q.toString()}`)
      historico.value = Array.isArray(data) ? data : (data.data || data.retorno || [])
    } catch (e) {
      errorMsgHist.value = 'Erro ao consultar o histórico.'
    } finally {
      isLoadingHist.value = false
    }
  }, 380) // debounce seguindo o Design System
}

async function downloadHistorico(hist) {
  try {
    const res = await api.get(`/api/v3/consignatarias/remessas/${hist.REMESSA_ID}/download`, { responseType: 'blob' })
    const blob = new Blob([res.data])
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `historico_remessa_${hist.REMESSA_ID}.txt`
    a.click()
  } catch (e) {
    alert('Erro ao tentar baixar arquivo.')
  }
}

</script>

<style scoped>
/* Aproveitando definições obrigatórias de tokens do Design System + estrutura base do ConsignacaoView */
.cs-page { display:flex; flex-direction:column; gap:1.5rem; padding:1.5rem; max-width:1200px; margin:0 auto; }
.hero { background:linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #312e81 100%); border-radius:24px; padding:32px 40px; color:#fff; position:relative; overflow:hidden; opacity:0; transform:translateY(-12px); transition:all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity:1; transform:none; }
.hero-shapes { position:absolute; inset:0; pointer-events:none; }
.hs { position:absolute; border-radius:50%; filter:blur(70px); opacity:0.15; }
.hs1 { width:320px; height:320px; background:#6366f1; top:-100px; right:-80px; }
.hs2 { width:200px; height:200px; background:#f59e0b; bottom:-60px; right:280px; }
.hero-eyebrow { font-size:11px; font-weight:700; letter-spacing:0.1em; color:#a78bfa; text-transform:uppercase; display:block; margin-bottom:4px; }
.hero-title { font-size:30px; font-weight:900; margin:0 0 4px; color:#ffffff; }
.hero-sub { opacity:1; font-size:13px; color:#94a3b8; margin:0; }
.hero-inner { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:relative; }

.tabs-bar { display:flex; gap:6px; flex-wrap:wrap; opacity:0; transform:translateY(6px); transition:all 0.4s cubic-bezier(0.22,1,0.36,1) 0.05s; }
.tabs-bar.loaded { opacity:1; transform:none; }
.tab-btn { padding:6px 14px; border-radius:999px; border:1px solid #e2e8f0; cursor:pointer; background:#fff; color:#475569; font-weight:600; font-size:13px; transition:all 0.15s; }
.tab-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
.tab-btn:not(.active):hover { background:#f8fafc; border-color:#cbd5e1; }

.section-card { background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:24px; opacity:0; transform:translateY(12px); transition:all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.section-card.loaded { opacity:1; transform:none; }
.section-title { font-size:16px; font-weight:700; color:#1e293b; margin:0 0 20px; }
.section-hdr { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:20px; }

.toolbar-right { display:flex; align-items:center; gap:10px; }
.filter-bar { display:flex; align-items:center; gap:14px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:12px 18px; margin-bottom:12px; }
.search-wrap { display:flex; gap:10px; flex:1; min-width:120px; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-group.col-full { grid-column: 1 / -1; }
.form-group label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; }
.form-input { border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; font-size:13px; width:100%; color:#1e293b; background:#fff; transition:0.15s; outline:none; font-family:inherit; }
.form-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,0.1); }
.form-input.small { width:100%; min-width:140px; }
.form-input:disabled { background:#f8fafc; color:#94a3b8; cursor:not-allowed; }

.form-actions { display:flex; gap:10px; margin-top:20px; }
.btn-primary, .btn-novo { display:flex; align-items:center; gap:6px; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; padding:10px 18px; border-radius:14px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 16px rgba(99,102,241,0.4); transition:all 0.2s; }
.btn-primary:hover, .btn-novo:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(99,102,241,0.5); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
.btn-ghost { background:none; border:none; color:#64748b; padding:9px 14px; font-size:14px; font-weight:600; cursor:pointer; border-radius:10px; transition:0.15s; }
.btn-ghost:hover { background:#f1f5f9; color:#334155; }

.empty-state { text-align:center; padding:60px 20px; color:#64748b; font-size:15px; font-weight:500; display:flex; flex-direction:column; align-items:center; gap:12px; }
.spinner-wrap { display:flex; justify-content:center; padding:40px; }
.spinner { width:48px; height:48px; border:3px solid #e2e8f0; border-top-color:#6366f1; border-radius:50%; animation:spin 0.8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.success-msg { background:#f0fdf4; color:#15803d; border:1px solid #86efac; border-radius:10px; padding:10px 16px; font-weight:600; font-size:13px; }
.error-msg { background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; border-radius:10px; padding:10px 16px; font-weight:600; font-size:13px; }

.table-scroll { overflow-x:auto; margin:0 -24px -24px; padding:0 24px 24px; }
.cs-table { width:100%; border-collapse:collapse; text-align:left; }
.cs-table thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9; }
.cs-table th { padding:12px 16px; font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; white-space:nowrap; }
.data-row { border-bottom:1px solid #f8fafc; transition:0.12s; }
.data-row:hover { background:#f8fafc; }
.data-row:last-child { border-bottom:none; }
.data-row.row-visible td { animation:rowIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--row-delay) both; }
@keyframes rowIn { from { opacity:0; transform:translateX(-6px); } to { opacity:1; transform:none; } }
.cs-table td { padding:13px 16px; font-size:13px; color:#334155; vertical-align:middle; }

.money { text-align:right; font-weight:600; font-variant-numeric: tabular-nums; }
.badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
.badge-gray { background:#f8fafc; color:#475569; border:1px solid #e2e8f0; }
.badge-purple { background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; }

.status-badge { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
.status-badge.ativo { background:#f0fdf4; color:#15803d; border:1px solid #86efac; }
.status-badge.cancelado { background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; }
.status-badge.pendente { background:#fffbeb; color:#92400e; border:1px solid #fcd34d; }

.row-actions { display:flex; gap:5px; }
.act-btn { display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; transition:0.15s; color:#64748b; font-size:12px; }
.act-btn:hover { transform:translateY(-1px); }
.act-blue:hover   { background:#eff6ff; border-color:#bfdbfe; }
.act-green:hover  { background:#f0fdf4; border-color:#86efac; }
.act-red:hover    { background:#fef2f2; border-color:#fca5a5; }

.sub { font-size:12px; color:#94a3b8; font-weight:normal; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:#fff; border-radius:24px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 32px 80px rgba(0,0,0,0.25); }
.modal-md { max-width:600px; }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:22px 28px; border-bottom:1px solid #f1f5f9; flex-shrink:0; }
.modal-title { font-size:18px; font-weight:800; color:#1e293b; margin:0; }
.modal-close { display:flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; cursor:pointer; color:#64748b; transition:0.15s; }
.modal-close:hover { background:#fef2f2; border-color:#fca5a5; color:#dc2626; }
.modal-body { overflow-y:auto; padding:24px 28px; flex:1; display:flex; flex-direction:column; gap:20px; }
.modal-footer { padding:16px 28px; border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-shrink:0; }

.log-box { margin-top:20px; background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0; }
.log-label { font-size:12px; font-weight:700; color:#64748b; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em; }
.log-box textarea { width:100%; font-family:monospace; font-size:12px; border:none; background:transparent; resize:vertical; outline:none; color:#1e293b; }

@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; }
  .toolbar-right { margin-top: 10px; width: 100%; }
  .filter-bar { flex-direction: column; }
}
</style>
