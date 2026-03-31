<template>
  <div class="view-container">
    <!-- ═══ HERO ═══════════════════════════════════════════════════ -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2" style="background: #10b981;"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Recursos Humanos</span>
          <h1 class="hero-title">Benefícios</h1>
          <p class="hero-sub">Gestão de catálogo e vínculos de benefícios dos servidores</p>
        </div>
        <div class="hero-chips">
          <div class="chip">
            <div class="chip-dot green"></div>
            <strong>{{ kpis.total_servidores || 0 }}</strong> Servidores
          </div>
          <div class="chip" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3);">
            <div class="chip-dot" style="background: #10b981;"></div>
            <strong>{{ formatarMoeda(kpis.custo_mensal || 0) }}</strong> /mês
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TABS ═══════════════════════════════════════════════════ -->
    <div class="filter-tabs" style="margin-top: 20px;" :class="{ loaded }">
      <button class="ftab" :class="{ active: tabAtiva === 'catalogo' }" @click="tabAtiva = 'catalogo'">Catálogo</button>
      <button class="ftab" :class="{ active: tabAtiva === 'servidor' }" @click="tabAtiva = 'servidor'">Por Servidor</button>
      <button class="ftab" :class="{ active: tabAtiva === 'relatorio' }" @click="tabAtiva = 'relatorio'">Relatório de Custo</button>
    </div>

    <!-- ═══ CONTEÚDO: CATÁLOGO ═════════════════════════════════════ -->
    <div v-if="tabAtiva === 'catalogo'" style="margin-top: 20px;">
      
      <div class="toolbar" :class="{ loaded }">
        <div class="search-wrap">
          <input 
            v-model="buscaCatalogo" 
            class="search-input"
            placeholder="Buscar benefício no catálogo..."
          />
          <button v-if="buscaCatalogo" class="search-clear" @click="buscaCatalogo = ''">✕</button>
        </div>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalCatalogo()">
            + Novo Benefício
          </button>
        </div>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nome / Tipo</th>
              <th>Valor Padrão</th>
              <th>Status</th>
              <th width="60">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingCatalogo" class="data-row row-visible"><td colspan="4" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="catalogo.length === 0" class="data-row row-visible"><td colspan="4" style="text-align:center; padding: 40px; color:#64748b;">Nenhum benefício cadastrado.</td></tr>
            <tr v-for="(item, i) in catalogoFiltrado" :key="item.BENEFICIO_ID" class="data-row" :class="{ 'row-visible': loaded }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.BENEFICIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ item.BENEFICIO_TIPO || '-' }}</div>
              </td>
              <td style="font-weight: 600;">{{ formatarMoeda(item.BENEFICIO_VALOR) }}</td>
              <td><span class="badge" :class="item.BENEFICIO_STATUS === 'ativo' ? 'badge-green' : 'badge-red'"><span class="badge-dot"></span>{{ item.BENEFICIO_STATUS === 'ativo' ? 'Ativo' : 'Inativo' }}</span></td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-blue" @click="abrirModalCatalogo(item)" title="Editar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: POR SERVIDOR ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'servidor'" style="margin-top: 20px;">
      
      <div class="toolbar" :class="{ loaded }">
        <div class="search-wrap">
          <input 
            v-model="buscaServidor" 
            class="search-input"
            placeholder="Buscar por matrícula ou nome do servidor..."
            @input="debounceBuscaServidor"
            @keyup.enter="buscarVinculosPorServidor"
          />
          <button v-if="buscaServidor" class="search-clear" @click="limparBuscaServidor">✕</button>
        </div>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalVinculo()" :disabled="!idFuncionarioAtivo" :style="{ opacity: !idFuncionarioAtivo ? 0.5 : 1 }">
            + Conceder Benefício
          </button>
        </div>
      </div>

      <div v-if="loadingServidor" class="state-box">
        <div class="spinner"></div>
        <p>Buscando vínculos...</p>
      </div>

      <div v-else-if="!vinculosServidor.length && !buscaServidorRealizada" class="state-box empty" style="padding: 40px 20px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" style="opacity: 0.3"><path d="M12 11c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z" fill="currentColor"/></svg>
        <p style="margin-top: 12px;">Busque por um servidor para ver ou adicionar seus benefícios.</p>
        <p style="font-size: 12px; margin-top: 6px;">Ex: "João", "Maria", ou "12345"</p>
      </div>

      <div v-else-if="vinculosServidor.length === 0" class="state-box empty">
        <p>Nenhum benefício encontrado para esta busca.</p>
      </div>

      <div v-else class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Benefício</th>
              <th>Vigência</th>
              <th>Valor / Condição</th>
              <th>Status</th>
              <th width="80">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(vinc, i) in vinculosServidor" :key="vinc.FB_ID" class="data-row" :class="{ 'row-visible': loaded }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ vinc.BENEFICIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ vinc.PESSOA_NOME }} ({{ vinc.FUNCIONARIO_MATRICULA }})</div>
              </td>
              <td>
                <div style="font-size: 13px;">{{ vinc.DATA_INICIO ? formataDataBr(vinc.DATA_INICIO) : '-' }}</div>
                <div v-if="vinc.DATA_FIM" style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-top:2px;">ATÉ {{ formataDataBr(vinc.DATA_FIM) }}</div>
              </td>
              <td>
                <div style="font-weight: 600; color: #10b981;">{{ formatarMoeda(vinc.VALOR_ESPECIFICO || vinc.BENEFICIO_VALOR) }}</div>
                <div v-if="vinc.VALOR_ESPECIFICO" style="font-size: 10px; color: #64748b; font-weight: 700;">VALOR ESPECÍFICO</div>
              </td>
              <td>
                <span class="badge" :class="vinc.VINCULO_STATUS === 'ativo' ? 'badge-green' : 'badge-gray'">
                  <span class="badge-dot"></span>{{ vinc.VINCULO_STATUS === 'ativo' ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td>
                 <button class="act-btn act-red" @click="deletarVinculo(vinc.FB_ID)" title="Inativar/Excluir" style="margin-left:auto;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                 </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: RELATÓRIO ════════════════════════════════════ -->
    <div v-if="tabAtiva === 'relatorio'" style="margin-top: 20px;">
      <div v-if="loadingRelatorio" class="state-box">
        <div class="spinner"></div>
      </div>
      <div v-else class="kpi-grid" :class="{ loaded }">
        <div class="kpi-card" v-for="rel in relatoriosPorTipo" :key="rel.BENEFICIO_NOME" style="--kpi-color: #3b82f6; transform: none; opacity: 1;">
          <div class="kpi-label">{{ rel.BENEFICIO_NOME }}</div>
          <div class="kpi-value" style="font-size: 24px;">{{ formatarMoeda(rel.custo_relativo) }}</div>
          <div class="kpi-sub" style="margin-top: 8px;"><strong>{{ rel.total_adesoes }}</strong> servidores associados</div>
        </div>
      </div>
    </div>

    <!-- ═══ MODAIS ═════════════════════════════════════════════════ -->

    <!-- Modal Catálogo -->
    <div v-if="modalCatalogoAberto" class="modal-overlay" @mousedown.self="modalCatalogoAberto = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">{{ formCatalogo.BENEFICIO_ID ? 'Editar Benefício' : 'Novo Benefício' }}</h2>
          <button class="modal-close" @click="modalCatalogoAberto = false">✕</button>
        </div>
        
        <div class="modal-body">
          <div v-if="erroModal" class="toast-error">{{ erroModal }}</div>
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Nome do Benefício</label>
              <input v-model="formCatalogo.BENEFICIO_NOME" class="form-input" placeholder="Ex: Auxílio Alimentação">
            </div>
            <div class="form-group">
              <label>Tipo / Categoria</label>
              <input v-model="formCatalogo.BENEFICIO_TIPO" class="form-input" placeholder="VT, VR, Plano de Saúde...">
            </div>
            <div class="form-group">
              <label>Valor Padrão P/ Mês</label>
              <input v-model.number="formCatalogo.BENEFICIO_VALOR" type="number" step="0.01" class="form-input" placeholder="0.00">
            </div>
            <div class="form-group col-full">
              <label>Status Operacional</label>
              <select v-model="formCatalogo.BENEFICIO_STATUS" class="form-input">
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalCatalogoAberto = false">Cancelar</button>
          <button class="btn-novo" @click="salvarCatalogo" :disabled="salvando">
            <span v-if="salvando" class="spinner" style="width: 14px; height: 14px; border-width: 2px;"></span>
            Salvar
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Funcionario_Beneficio (Para adição simplificada sem a necessidade de outra view) -->
    <div v-if="modalVinculoAberto" class="modal-overlay" @mousedown.self="modalVinculoAberto = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">Conceder Benefício</h2>
          <button class="modal-close" @click="modalVinculoAberto = false">✕</button>
        </div>
        
        <div class="modal-body">
          <div v-if="erroModal" class="toast-error">{{ erroModal }}</div>
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Benefício do Catálogo</label>
              <select v-model="formVinculo.BENEFICIO_ID" class="form-input">
                <option value="">Selecione um benefício ativo...</option>
                <option v-for="b in catalogo.filter(x => x.BENEFICIO_STATUS === 'ativo')" :key="b.BENEFICIO_ID" :value="b.BENEFICIO_ID">
                  {{ b.BENEFICIO_NOME }} — {{ formatarMoeda(b.BENEFICIO_VALOR) }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Vigência Inicial</label>
              <input v-model="formVinculo.DATA_INICIO" type="date" class="form-input">
            </div>
            <div class="form-group">
              <label>Término (Opcional)</label>
              <input v-model="formVinculo.DATA_FIM" type="date" class="form-input">
            </div>
            <div class="form-group">
              <label>Valor Específico (Opcional)</label>
               <input v-model.number="formVinculo.VALOR_ESPECIFICO" type="number" step="0.01" class="form-input" placeholder="Deixe em branco para o padrão">
            </div>
            <div class="form-group">
               <label>Nº Dependentes</label>
               <input v-model.number="formVinculo.DEPENDENTES" type="number" class="form-input">
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalVinculoAberto = false">Cancelar</button>
          <button class="btn-novo" @click="salvarVinculo" :disabled="salvando">
            <span v-if="salvando" class="spinner" style="width: 14px; height: 14px; border-width: 2px;"></span>
            Confirmar Concessão
          </button>
        </div>
      </div>
    </div>


  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/plugins/axios'

// Estados globais da View
const loaded = ref(false)
const tabAtiva = ref('catalogo')
const loadingCatalogo = ref(false)
const loadingServidor = ref(false)
const loadingRelatorio = ref(false)
const salvando = ref(false)
const erroModal = ref('')

// Buscas
const buscaCatalogo = ref('')
const buscaServidor = ref('')
const buscaServidorRealizada = ref(false)
const idFuncionarioAtivo = ref(null) // para injetar na concessão

// Dados
const catalogo = ref([])
const vinculosServidor = ref([])
const kpis = ref({ total_servidores: 0, custo_mensal: 0 })
const relatoriosPorTipo = ref([])

// ── UTILITÁRIOS ────────────────────────────────────────────────────────
const formatarMoeda = (valor) => {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(valor) || 0)
}
const formataDataBr = (isoStr) => {
  if (!isoStr) return ''
  const parts = isoStr.split('-')
  if (parts.length !== 3) return isoStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

// ── TABS LIFE-CYCLE E WATCHERS ───────────────────────────────────────
watch(tabAtiva, (newTab) => {
  if (newTab === 'catalogo' && catalogo.value.length === 0) fetchCatalogo()
  if (newTab === 'relatorio' && relatoriosPorTipo.value.length === 0) fetchRelatorio()
})

onMounted(() => {
  fetchCatalogo()
  fetchRelatorio(true) // Carrega KPIs para o hero em bg
  setTimeout(() => loaded.value = true, 50)
})

// ── LÓGICA DO CATÁLOGO ───────────────────────────────────────────────
const fetchCatalogo = async () => {
  loadingCatalogo.value = true
  try {
    const { data } = await api.get('/api/v3/beneficios/catalogo')
    catalogo.value = data || []
  } catch (e) {
    console.error(e)
  } finally {
    loadingCatalogo.value = false
  }
}

const catalogoFiltrado = computed(() => {
  if (!buscaCatalogo.value) return catalogo.value
  const t = buscaCatalogo.value.toLowerCase()
  return catalogo.value.filter(c => (c.BENEFICIO_NOME || '').toLowerCase().includes(t))
})

const modalCatalogoAberto = ref(false)
const formCatalogo = ref({})

const abrirModalCatalogo = (item = null) => {
  erroModal.value = ''
  if (item) {
    formCatalogo.value = JSON.parse(JSON.stringify(item))
  } else {
    formCatalogo.value = { BENEFICIO_ID: null, BENEFICIO_NOME: '', BENEFICIO_TIPO: '', BENEFICIO_VALOR: null, BENEFICIO_STATUS: 'ativo' }
  }
  modalCatalogoAberto.value = true
}

const salvarCatalogo = async () => {
  if (!formCatalogo.value.BENEFICIO_NOME) {
    erroModal.value = 'Nome do benefício é obrigatório.'
    return
  }
  salvando.value = true
  try {
    await api.post('/api/v3/beneficios/catalogo', formCatalogo.value)
    modalCatalogoAberto.value = false
    await fetchCatalogo()
    fetchRelatorio(true) // recarrega badges
  } catch (e) {
    erroModal.value = e.response?.data?.error || 'Erro ao salvar benefício.'
  } finally {
    salvando.value = false
  }
}


// ── LÓGICA POR SERVIDOR ──────────────────────────────────────────────
let timer = null
const debounceBuscaServidor = () => {
  clearTimeout(timer)
  timer = setTimeout(() => buscarVinculosPorServidor(), 600)
}
const limparBuscaServidor = () => { buscaServidor.value = ''; vinculosServidor.value = []; buscaServidorRealizada.value = false; idFuncionarioAtivo.value = null; }

const buscarVinculosPorServidor = async () => {
  if (!buscaServidor.value) { limparBuscaServidor(); return; }
  
  loadingServidor.value = true
  buscaServidorRealizada.value = true
  try {
    const { data } = await api.get(`/api/v3/beneficios/relatorio/servidores?busca=${encodeURIComponent(buscaServidor.value)}`)
    vinculosServidor.value = data || []
    if(vinculosServidor.value.length > 0) {
       idFuncionarioAtivo.value = vinculosServidor.value[0].FUNCIONARIO_ID
    } else {
       // Se o usuario da API backend /funcionarios existe e tivessemos o ID seria perfeito.
       // Mas na busca generica só pegamos FB se ele tiver beneficios. Como a task não exige uma refatoração da API de funcionarios, mantemos assim.
       idFuncionarioAtivo.value = null 
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingServidor.value = false
  }
}

const formVinculo = ref({})
const modalVinculoAberto = ref(false)

const abrirModalVinculo = () => {
  erroModal.value = ''
  formVinculo.value = { BENEFICIO_ID: '', DATA_INICIO: new Date().toISOString().slice(0, 10), DATA_FIM: '', VALOR_ESPECIFICO: '', DEPENDENTES: 0, STATUS: 'ativo' }
  modalVinculoAberto.value = true
}

const salvarVinculo = async () => {
  if (!formVinculo.value.BENEFICIO_ID || !formVinculo.value.DATA_INICIO) {
    erroModal.value = 'Benefício e data de início são obrigatórios.'
    return
  }
  salvando.value = true
  try {
    // payload
    const payload = {...formVinculo.value}
    if (payload.VALOR_ESPECIFICO === '') payload.VALOR_ESPECIFICO = null
    
    await api.post(`/api/v3/beneficios/${idFuncionarioAtivo.value}`, payload)
    modalVinculoAberto.value = false
    await buscarVinculosPorServidor()
    fetchRelatorio(true)
  } catch (e) {
    erroModal.value = e.response?.data?.error || 'Erro ao vincular.'
  } finally {
    salvando.value = false
  }
}

const deletarVinculo = async (fb_id) => {
  if (!confirm('Tem certeza que deseja inativar/remover este benefício do servidor?')) return
  try {
    await api.delete(`/api/v3/beneficios/${fb_id}`)
    await buscarVinculosPorServidor()
    fetchRelatorio(true)
  } catch (e) {
       /* empty */
  }
}


// ── LÓGICA DE RELATÓRIO ──────────────────────────────────────────────
const fetchRelatorio = async (silent = false) => {
  if (!silent) loadingRelatorio.value = true
  try {
    const { data } = await api.get('/api/v3/beneficios/relatorio/kpis')
    kpis.value.total_servidores = data.total_servidores
    kpis.value.custo_mensal = data.custo_mensal
    relatoriosPorTipo.value = data.distribuicao || []
  } catch (e) {
    /* empty */
  } finally {
    if (!silent) loadingRelatorio.value = false
  }
}

</script>

<style scoped>
.view-container {
  display: flex;
  flex-direction: column;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
