<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-200">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">Contratos Administrativos</h1>
        <p class="text-slate-500 mt-1">Gestão, aditivos e fiscalização de contratos</p>
      </div>
      <div class="flex gap-3">
        <button @click="exportarCsv" class="btn btn-outline flex items-center gap-2">
          <i data-lucide="download" class="w-4 h-4"></i> Exportar Base
        </button>
      </div>
    </div>

    <!-- Error/Sucesso globais -->
    <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
      <div class="flex items-center">
        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
        <p class="text-sm text-red-700">{{ errorMsg }}</p>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="border-b border-slate-200">
        <nav class="flex -mb-px px-6" aria-label="Tabs">
          <button
            v-for="tab in tabs" :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              activeTab === tab.id
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
              'whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors duration-200'
            ]"
          >
            <i :data-lucide="tab.icon" class="w-4 h-4"></i>
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <div class="p-6">
        <!-- Loader geral -->
        <div v-if="isLoading" class="flex justify-center items-center py-12">
          <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- CONTRATOS ATIVOS -->
        <div v-else-if="activeTab === 'ativos'" class="space-y-4">
          <div class="flex gap-4 mb-4">
            <div class="flex-1 relative">
              <i data-lucide="search" class="w-5 h-5 absolute left-3 top-2.5 text-slate-400"></i>
              <input v-model="filters.busca" @input="fetchContratos" type="text" placeholder="Buscar por objeto ou fornecedor..." class="pl-10 input w-full">
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Número</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fornecedor</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Objeto</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Valor (R$)</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Vigência</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-slate-200">
                <tr v-for="c in contratos" :key="c.CONTRATO_ID" class="hover:bg-slate-50 transition-colors">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">{{ c.CONTRATO_NUMERO }}</td>
                  <td class="px-6 py-4 text-sm text-slate-500">{{ c.CONTRATO_FORNECEDOR }}</td>
                  <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate" :title="c.CONTRATO_OBJETO">{{ c.CONTRATO_OBJETO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-emerald-600">
                    {{ formatCurrency(c.CONTRATO_VALOR) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500">
                    {{ formatDate(c.CONTRATO_INICIO) }} até {{ formatDate(c.CONTRATO_FIM) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <button @click="abrirDetalhes(c)" class="text-indigo-600 hover:text-indigo-900 font-medium">Ver Detalhes</button>
                  </td>
                </tr>
                <tr v-if="!contratos.length">
                  <td colspan="6" class="px-6 py-8 text-center text-slate-500">Nenhum contrato ativo encontrado.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- VENCENDO -->
        <div v-else-if="activeTab === 'vencendo'" class="space-y-4">
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Contrato</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fornecedor</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Término</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-slate-200">
                <tr v-for="c in contratosVencendo" :key="c.CONTRATO_ID" class="hover:bg-slate-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">{{ c.CONTRATO_NUMERO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ c.CONTRATO_FORNECEDOR }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-slate-700">
                    {{ formatDate(c.CONTRATO_FIM) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span :class="c.urgencia === 'CRITICO' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                      Faltam {{ c.dias_restantes }} dias
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <button @click="abrirAditivo(c)" class="btn btn-primary btn-sm">Registrar Aditivo</button>
                  </td>
                </tr>
                <tr v-if="!contratosVencendo.length">
                  <td colspan="5" class="px-6 py-8 text-center text-slate-500">Nenhum contrato vencendo nos próximos 60 dias.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ENCERRADOS (Apenas listagem com filtro específico) -->
        <div v-else-if="activeTab === 'encerrados'" class="space-y-4">
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Número</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fornecedor</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Vigência Final</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-slate-200">
                <tr v-for="c in contratosEncerrados" :key="c.CONTRATO_ID" class="hover:bg-slate-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">{{ c.CONTRATO_NUMERO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ c.CONTRATO_FORNECEDOR }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500">{{ formatDate(c.CONTRATO_FIM) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span class="bg-slate-100 text-slate-800 px-2.5 py-0.5 rounded-full text-xs font-medium">{{ c.CONTRATO_STATUS }}</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <button @click="abrirDetalhes(c)" class="text-indigo-600 hover:text-indigo-900 font-medium">Histórico</button>
                  </td>
                </tr>
                <tr v-if="!contratosEncerrados.length">
                  <td colspan="5" class="px-6 py-8 text-center text-slate-500">Nenhum contrato encerrado encontrado.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- FISCALIZAÇÃO -->
        <div v-else-if="activeTab === 'fiscalizacao'" class="space-y-6">
          <div class="max-w-xl mx-auto space-y-4">
            <label class="block text-sm font-medium text-slate-700">Selecione o Contrato para Fiscalização Mensal</label>
            <select v-model="selectedFiscalContractId" @change="carregarFiscalizacoes" class="input w-full">
              <option value="">-- Selecione um Contrato Ativo --</option>
              <option v-for="c in contratos" :key="c.CONTRATO_ID" :value="c.CONTRATO_ID">
                {{ c.CONTRATO_NUMERO }} - {{ c.CONTRATO_FORNECEDOR }}
              </option>
            </select>
          </div>

          <div v-if="selectedFiscalContractId" class="border border-slate-200 rounded-lg p-6 space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-bold text-slate-800">Histórico de Fiscalização</h3>
              <button @click="abrirFiscalizacao" class="btn btn-primary flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Nova Inspeção Mensal
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200 mt-4">
                <thead class="bg-slate-50">
                  <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Ref</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Responsável</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Observação</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                  <tr v-for="f in fiscalizacoes" :key="f.FISCAL_ID">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900">{{ formatDate(f.FISCAL_DATA) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">{{ f.FISCAL_COMPETENCIA }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-500">{{ f.FISCAL_RESPONSAVEL || 'N/I' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                      <span :class="{
                        'bg-emerald-100 text-emerald-800': f.FISCAL_STATUS === 'REGULAR',
                        'bg-red-100 text-red-800': f.FISCAL_STATUS === 'IRREGULAR',
                        'bg-yellow-100 text-yellow-800': f.FISCAL_STATUS === 'PENDENCIA',
                        'bg-slate-100 text-slate-800': f.FISCAL_STATUS === 'SUSPENSO'
                      }" class="px-2 py-0.5 rounded text-xs font-semibold">
                        {{ f.FISCAL_STATUS }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500">{{ f.FISCAL_OBSERVACAO }}</td>
                  </tr>
                  <tr v-if="!fiscalizacoes.length">
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sem registros de fiscalização para este contrato.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DETALHES -->
    <div v-if="detalhesModal" class="fixed inset-0 z-50 flex py-10 justify-center bg-slate-900/50 backdrop-blur-sm overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl flex flex-col mx-4 h-fit">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Detalhes do Contrato</h3>
          <button @click="detalhesModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
            <div><span class="text-slate-500 text-sm block">Número</span><span class="font-semibold">{{ selectedContratoDetails?.contrato.CONTRATO_NUMERO }}</span></div>
            <div><span class="text-slate-500 text-sm block">Fornecedor</span><span class="font-semibold">{{ selectedContratoDetails?.contrato.CONTRATO_FORNECEDOR }}</span></div>
            <div class="col-span-2"><span class="text-slate-500 text-sm block">Objeto</span><span>{{ selectedContratoDetails?.contrato.CONTRATO_OBJETO }}</span></div>
            <div><span class="text-slate-500 text-sm block">Valor</span><span class="font-bold text-emerald-600">{{ formatCurrency(selectedContratoDetails?.contrato.CONTRATO_VALOR) }}</span></div>
            <div><span class="text-slate-500 text-sm block">Status</span><span class="font-semibold">{{ selectedContratoDetails?.contrato.CONTRATO_STATUS }}</span></div>
          </div>

          <div>
            <h4 class="font-bold text-slate-800 mb-2 border-b pb-1">Aditivos ({{ selectedContratoDetails?.aditivos.length || 0 }})</h4>
            <div v-if="selectedContratoDetails?.aditivos.length" class="space-y-2">
              <div v-for="ad in selectedContratoDetails.aditivos" :key="ad.ADITIVO_ID" class="bg-slate-50 border border-slate-200 rounded p-3 text-sm">
                <div class="flex justify-between font-semibold mb-1">
                  <span>{{ ad.ADITIVO_NUMERO }}º Aditivo ({{ ad.ADITIVO_TIPO }})</span>
                  <span>{{ formatDate(ad.ADITIVO_DATA) }}</span>
                </div>
                <div class="text-slate-600">
                  <span v-if="ad.ADITIVO_PRAZO_DIAS">Prazo adicionado: +{{ ad.ADITIVO_PRAZO_DIAS }} dias<br></span>
                  <span v-if="ad.ADITIVO_VALOR">Valor ajustado: {{ formatCurrency(ad.ADITIVO_VALOR) }}<br></span>
                  <span v-if="ad.ADITIVO_OBJETO">Obj: {{ ad.ADITIVO_OBJETO }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">Sem aditivos.</p>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl flex justify-end">
          <button @click="detalhesModal = false" class="btn btn-outline">Fechar</button>
        </div>
      </div>
    </div>

    <!-- MODAL REGISTRO DE ADITIVO -->
    <div v-if="aditivoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Registrar Aditivo</h3>
          <button @click="aditivoModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <form @submit.prevent="salvarAditivo" class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Data do Aditivo</label>
              <input v-model="formAditivo.aditivo_data" type="date" required class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
              <select v-model="formAditivo.aditivo_tipo" required class="input w-full">
                <option value="PRAZO">Prorrogação de Prazo</option>
                <option value="VALOR">Alteração de Valor</option>
                <option value="PRAZO_VALOR">Prazo e Valor</option>
                <option value="OUTROS">Outros</option>
              </select>
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Dias p/ Prorrog (se houver)</label>
              <input v-model="formAditivo.aditivo_prazo_dias" type="number" min="1" class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Valor Adicionado (R$)</label>
              <input v-model="formAditivo.aditivo_valor" type="number" step="0.01" class="input w-full">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Objeto/Observação</label>
            <textarea v-model="formAditivo.aditivo_objeto" rows="2" class="input w-full"></textarea>
          </div>

          <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 mt-6">
            <button type="button" @click="aditivoModal = false" class="btn btn-outline" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="isSaving">
              <span v-if="isSaving">Salvando...</span>
              <span v-else>Salvar Aditivo</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL FISCALIZACAO -->
    <div v-if="fiscalizacaoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Nova Inspeção/Fiscalização</h3>
          <button @click="fiscalizacaoModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <form @submit.prevent="salvarFiscalizacao" class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Data</label>
              <input v-model="formFiscal.fiscal_data" type="date" required class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Status Encontrado</label>
              <select v-model="formFiscal.fiscal_status" required class="input w-full">
                <option value="REGULAR">Regular</option>
                <option value="IRREGULAR">Irregular / Falhas Grave</option>
                <option value="PENDENCIA">Com Pendências</option>
                <option value="SUSPENSO">Contrato Suspenso</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Responsável pela Fiscalização (Fiscal)</label>
            <input v-model="formFiscal.fiscal_responsavel" type="text" maxlength="150" class="input w-full">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Observações do Mês</label>
            <textarea v-model="formFiscal.fiscal_observacao" rows="3" required class="input w-full"></textarea>
          </div>

          <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 mt-6">
            <button type="button" @click="fiscalizacaoModal = false" class="btn btn-outline" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="isSaving">
              <span v-if="isSaving">Salvando...</span>
              <span v-else>Salvar Fiscalização</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import api from '@/plugins/axios'
import lucide from 'lucide'

const tabs = [
  { id: 'ativos', name: 'Contratos Ativos', icon: 'check-circle' },
  { id: 'vencendo', name: 'Vencendo', icon: 'alert-triangle' },
  { id: 'encerrados', name: 'Encerrados', icon: 'x-circle' },
  { id: 'fiscalizacao', name: 'Fiscalização Mensal', icon: 'eye' }
]

const activeTab = ref('ativos')
const isLoading = ref(false)
const isSaving = ref(false)
const errorMsg = ref('')

// Dados
const contratos = ref([])
const contratosVencendo = ref([])
const contratosEncerrados = ref([])
const fiscalizacoes = ref([])

// Filtros
const filters = reactive({ busca: '', status: 'VIGENTE' })
const selectedFiscalContractId = ref('')

// Modais
const detalhesModal = ref(false)
const aditivoModal = ref(false)
const fiscalizacaoModal = ref(false)

const selectedContratoDetails = ref(null)
const selectedContratoForAditivo = ref(null)

const formAditivo = reactive({
  aditivo_data: new Date().toISOString().substring(0, 10),
  aditivo_tipo: 'PRAZO',
  aditivo_prazo_dias: null,
  aditivo_valor: null,
  aditivo_objeto: ''
})

const formFiscal = reactive({
  fiscal_data: new Date().toISOString().substring(0, 10),
  fiscal_status: 'REGULAR',
  fiscal_responsavel: '',
  fiscal_observacao: ''
})

// Lifecycle
onMounted(() => {
  fetchContratos()
})

watch(activeTab, (newVal) => {
  errorMsg.value = ''
  if (newVal === 'ativos') fetchContratos()
  if (newVal === 'vencendo') fetchVencendo()
  if (newVal === 'encerrados') fetchEncerrados()
  if (newVal === 'fiscalizacao' && selectedFiscalContractId.value) carregarFiscalizacoes()
  
  nextTick(() => lucide.createIcons())
})

// Helpers
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const [y, m, d] = dateStr.split('-')
  return `${d}/${m}/${y}`
}

const formatCurrency = (val) => {
  if (!val) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val)
}

// Fetch APIs
const fetchContratos = async () => {
  isLoading.value = true; errorMsg.value = ''
  try {
    const res = await api.get('/api/v3/contratos-admin', { params: { status: 'VIGENTE', busca: filters.busca } })
    contratos.value = res.data.contratos
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Falha ao carregar contratos ativos.'
  } finally { isLoading.value = false }
}

const fetchVencendo = async () => {
  isLoading.value = true; errorMsg.value = ''
  try {
    const res = await api.get('/api/v3/contratos-admin/vencendo')
    contratosVencendo.value = res.data.contratos
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Falha ao carregar alertas.'
  } finally { isLoading.value = false }
}

const fetchEncerrados = async () => {
  isLoading.value = true; errorMsg.value = ''
  try {
    const res = await api.get('/api/v3/contratos-admin')
    contratosEncerrados.value = res.data.contratos.filter(c => ['ENCERRADO', 'RESCINDIDO'].includes(c.CONTRATO_STATUS))
  } catch (err) {
    errorMsg.value = 'Falha ao carregar contratos encerrados.'
  } finally { isLoading.value = false }
}

// Modal Detalhes
const abrirDetalhes = async (c) => {
  try {
    const res = await api.get(`/api/v3/contratos-admin/${c.CONTRATO_ID}`)
    selectedContratoDetails.value = res.data
    detalhesModal.value = true
    nextTick(() => lucide.createIcons())
  } catch (err) {
    errorMsg.value = 'Falha ao carregar detalhes.'
  }
}

// Modal Aditivo
const abrirAditivo = (c) => {
  selectedContratoForAditivo.value = c
  formAditivo.aditivo_data = new Date().toISOString().substring(0, 10)
  formAditivo.aditivo_tipo = 'PRAZO'
  formAditivo.aditivo_prazo_dias = ''
  formAditivo.aditivo_valor = ''
  formAditivo.aditivo_objeto = ''
  aditivoModal.value = true
}

const salvarAditivo = async () => {
  if (!selectedContratoForAditivo.value) return
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.post(`/api/v3/contratos-admin/${selectedContratoForAditivo.value.CONTRATO_ID}/aditivo`, formAditivo)
    aditivoModal.value = false
    fetchVencendo()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao salvar aditivo.'
  } finally { isSaving.value = false }
}

// Fiscalização
const carregarFiscalizacoes = async () => {
  if (!selectedFiscalContractId.value) { fiscalizacoes.value = []; return }
  try {
    const res = await api.get(`/api/v3/contratos-admin/${selectedFiscalContractId.value}`)
    fiscalizacoes.value = res.data.fiscalizacoes
  } catch (e) {
    errorMsg.value = 'Erro ao buscar fiscalizações deste contrato.'
  }
}

const abrirFiscalizacao = () => {
  formFiscal.fiscal_data = new Date().toISOString().substring(0, 10)
  formFiscal.fiscal_status = 'REGULAR'
  formFiscal.fiscal_responsavel = ''
  formFiscal.fiscal_observacao = ''
  fiscalizacaoModal.value = true
}

const salvarFiscalizacao = async () => {
  if (!selectedFiscalContractId.value) return
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.post(`/api/v3/contratos-admin/${selectedFiscalContractId.value}/fiscalizar`, formFiscal)
    fiscalizacaoModal.value = false
    carregarFiscalizacoes()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao registrar fiscalização.'
  } finally { isSaving.value = false }
}

// Export 
const exportarCsv = () => {
  window.open('/api/v3/contratos-admin/export/csv', '_blank')
}
</script>
