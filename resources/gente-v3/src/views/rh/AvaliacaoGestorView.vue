<template>
  <div class="view-container">
    <!-- ═══ HERO ═══════════════════════════════════════════════════ -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div>
        <div class="hs hs2" style="background: #a78bfa;"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Gestão</span>
          <h1 class="hero-title">Avaliações de Desempenho</h1>
          <p class="hero-sub">Ciclo ativo e avaliações pendentes da sua equipe</p>
        </div>
        <div class="hero-chips">
          <div class="chip">
            <div class="chip-dot amber"></div>
            <strong>{{ pendentesCount }}</strong> Pendentes
          </div>
          <div class="chip">
            <div class="chip-dot green"></div>
            <strong>{{ concluidasCount }}</strong> Concluídas
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TOOLBAR ════════════════════════════════════════════════ -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <input 
          v-model="busca" 
          class="search-input"
          placeholder="Buscar por nome do servidor..."
          @input="debounceBusca" 
        />
        <button v-if="busca" class="search-clear" @click="limparBusca">✕</button>
      </div>

      <div class="toolbar-right">
        <select v-model="filtroStatus" class="filter-select" @change="fetchDados">
          <option value="">Status: Todos</option>
          <option value="pendente">Pendentes</option>
          <option value="rascunho">Rascunhos</option>
          <option value="concluida">Concluídas</option>
        </select>
        <span class="result-count">{{ listaFiltrada.length }} resultado{{ listaFiltrada.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>

    <!-- ═══ CONTEÚDO ═══════════════════════════════════════════════ -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Carregando avaliações...</p>
    </div>

    <div v-else-if="erro" class="state-box error">
      <p>{{ erro }}</p>
      <button class="retry-btn" @click="fetchDados">Tentar novamente</button>
    </div>

    <div v-else-if="listaFiltrada.length === 0" class="state-box empty">
      <p>Nenhuma avaliação encontrada<span v-if="busca"> para "{{ busca }}"</span></p>
    </div>

    <div v-else class="table-card" :class="{ loaded }">
      <table class="data-table">
        <thead>
          <tr>
            <th>Servidor</th>
            <th>Cargo</th>
            <th>Ciclo</th>
            <th>Status</th>
            <th>Nota Final</th>
            <th width="80">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in listaPaginada" :key="item.AVALIACAO_ID || i" 
              class="data-row" :class="{ 'row-visible': loaded }"
              :style="{ '--row-delay': `${i * 30}ms` }">
            <td>
              <div style="display: flex; align-items: center; gap: 10px;">
                <div class="avatar" :style="{ '--hue': avatarHue(item.FUNCIONARIO_ID) }">
                  {{ iniciais(item.PESSOA_NOME) }}
                </div>
                <div>
                  <div style="font-weight: 600; color: #1e293b;">{{ item.PESSOA_NOME }}</div>
                  <div style="font-size: 11px; color: #64748b; font-family: monospace;">{{ item.FUNCIONARIO_MATRICULA || 'S/ MAT.' }}</div>
                </div>
              </div>
            </td>
            <td>{{ item.CARGO_NOME || 'Não especificado' }}</td>
            <td>{{ item.AVALIACAO_CICLO }}</td>
            <td>
              <span class="badge" :class="getBadgeClass(item.AVALIACAO_STATUS)">
                <span class="badge-dot"></span>
                {{ getStatusLabel(item.AVALIACAO_STATUS) }}
              </span>
            </td>
            <td>
              <strong v-if="item.AVALIACAO_NOTA_FINAL" :style="{ color: Number(item.AVALIACAO_NOTA_FINAL) >= 7 ? '#10b981' : (Number(item.AVALIACAO_NOTA_FINAL) >= 5 ? '#f59e0b' : '#f43f5e') }">
                {{ Number(item.AVALIACAO_NOTA_FINAL).toFixed(1) }}
              </strong>
              <span v-else style="color: #94a3b8;">-</span>
            </td>
            <td>
              <div class="row-actions">
                <button v-if="['pendente', 'rascunho'].includes(item.AVALIACAO_STATUS?.toLowerCase())" class="act-btn act-blue" @click="abrirModalAvaliacao(item)" title="Avaliar">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button v-else class="act-btn act-green" @click="abrirModalAvaliacao(item)" title="Visualizar">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <div v-if="listaFiltrada.length > itensPorPagina && !loading" class="pagination">
      <button class="pg-btn" :disabled="paginaAtual === 1" @click="paginaAtual--">‹</button>
      <span class="pg-info">Página {{ paginaAtual }} de {{ totalPaginas }}</span>
      <button class="pg-btn" :disabled="paginaAtual === totalPaginas" @click="paginaAtual++">›</button>
    </div>

    <!-- ═══ MODAL DE AVALIAÇÃO ═══════════════════════════════════════════ -->
    <div v-if="modalAberto" class="modal-overlay" @mousedown.self="modalAberto = false">
      <div class="modal-box modal-lg" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">{{ isVisualizacao ? 'Visualizar Avaliação' : 'Avaliar Servidor' }}</h2>
          <button class="modal-close" @click="modalAberto = false">✕</button>
        </div>
        
        <div class="modal-body">
          <div v-if="erroModal" class="toast-error" style="margin-bottom: 0;">{{ erroModal }}</div>
          
          <div style="display: flex; gap: 16px; padding: 16px; background: #f8fafc; border-radius: var(--r-xl); border: 1px solid #e2e8f0;">
            <div class="avatar avatar-lg" :style="{ '--hue': avatarHue(form.FUNCIONARIO_ID) }">
              {{ iniciais(form.PESSOA_NOME) }}
            </div>
            <div>
              <div style="font-size: 16px; font-weight: 800; color: #1e293b;">{{ form.PESSOA_NOME }}</div>
              <div style="font-size: 13px; color: #64748b; margin-top: 4px;">{{ form.CARGO_NOME || 'Cargo não especificado' }}</div>
              <div style="display: flex; gap: 8px; margin-top: 8px;">
                <span class="badge badge-gray"><span class="badge-dot"></span>Ciclo: {{ form.AVALIACAO_CICLO }}</span>
                <span class="badge badge-gray"><span class="badge-dot"></span>Mat.: {{ form.FUNCIONARIO_MATRICULA || '-' }}</span>
              </div>
            </div>
            <div style="margin-left: auto; text-align: right;">
              <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Nota Atual</div>
              <div style="font-size: 32px; font-weight: 900; line-height: 1;" :style="{ color: notaAtualColor }">
                {{ notaCalculadaTexto }}
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3 class="section-label">Critérios de Avaliação (Notas de 0 a 10)</h3>
            
            <div v-if="!form.criterios || form.criterios.length === 0" style="padding: 20px; text-align: center; color: #64748b; font-size: 13px;">
              Carregando critérios...
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
              <div v-for="(crit, index) in form.criterios" :key="index" style="padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                  <label style="font-size: 13px; font-weight: 700; color: #334155;">{{ crit.CRITERIO_NOME }}</label>
                  <span style="font-size: 11px; font-weight: 700; color: #94a3b8;">Peso: {{ crit.CRITERIO_PESO }}%</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 16px;">
                  <input type="range" min="0" max="10" step="1" v-model.number="crit.CRITERIO_NOTA" :disabled="isVisualizacao"
                         style="flex: 1; accent-color: #6366f1; cursor: pointer;">
                  <div style="width: 40px; text-align: center; font-weight: 800; color: #6366f1; font-size: 16px;">
                    {{ crit.CRITERIO_NOTA !== null && crit.CRITERIO_NOTA !== undefined ? crit.CRITERIO_NOTA : '-' }}
                  </div>
                </div>
                
                <input v-if="!isVisualizacao || crit.CRITERIO_OBS" type="text" v-model="crit.CRITERIO_OBS" class="form-input" 
                       placeholder="Observações adicionais para este critério (opcional)" :disabled="isVisualizacao"
                       style="margin-top: 10px; font-size: 12px; padding: 6px 12px;">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3 class="section-label">Parecer Final Geral</h3>
            <div class="form-group col-full">
              <textarea v-model="form.AVALIACAO_OBS" class="form-input" rows="3" 
                        placeholder="Observações gerais sobre o desempenho do servidor..."
                        :disabled="isVisualizacao"></textarea>
            </div>
          </div>

        </div>
        
        <div class="modal-footer">
          <p v-if="successoModal" class="modal-sucesso">{{ successoModal }}</p>
          <button class="btn-ghost" @click="modalAberto = false">
            {{ isVisualizacao ? 'Fechar' : 'Cancelar' }}
          </button>
          <button v-if="!isVisualizacao" class="btn-novo" @click="salvarAvaliacao" :disabled="salvando">
            <span v-if="salvando" class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></span>
            {{ form.AVALIACAO_STATUS === 'rascunho' ? 'Atualizar Rascunho' : 'Concluir Avaliação' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/plugins/axios'

// ── ESTADOS GERAIS ──────────────────────────────────────────────
const loaded = ref(false)
const loading = ref(true)
const erro = ref('')
const lista = ref([])
const pendentesCount = ref(0)
const concluidasCount = ref(0)

// ── FILTROS E PAGINAÇÃO ─────────────────────────────────────────
const busca = ref('')
const filtroStatus = ref('')
const paginaAtual = ref(1)
const itensPorPagina = 20

// Debounce timer
let timer = null
const debounceBusca = () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    paginaAtual.value = 1
  }, 380)
}
const limparBusca = () => { busca.value = ''; debounceBusca() }

// Helpers
const avatarHue = (id) => ((id * 47) % 360)
const iniciais = (nome) => {
  if (!nome) return '?'
  return nome.trim().split(' ').filter(Boolean).slice(0, 2).map(n => n[0].toUpperCase()).join('')
}
const getBadgeClass = (status) => {
  if (!status) return 'badge-gray'
  const s = status.toLowerCase()
  if (['concluída', 'concluida', 'publicada'].includes(s)) return 'badge-green'
  if (['pendente', 'em andamento'].includes(s)) return 'badge-amber'
  if (s === 'rascunho') return 'badge-gray'
  return 'badge-blue'
}
const getStatusLabel = (status) => {
  if (!status) return 'Desconhecido'
  const s = status.toLowerCase()
  if (s === 'concluida') return 'Concluída'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

// Lógica de dados locais
const listaFiltrada = computed(() => {
  let filtrado = lista.value

  if (filtroStatus.value) {
    const fs = filtroStatus.value.toLowerCase()
    filtrado = filtrado.filter(item => {
      const status = (item.AVALIACAO_STATUS || 'pendente').toLowerCase()
      // Mapear "concluída" para "concluida"
      const statusNormalized = status === 'concluída' ? 'concluida' : status
      return statusNormalized === fs
    })
  }

  if (busca.value) {
    const termo = busca.value.toLowerCase().trim()
    filtrado = filtrado.filter(item => 
      (item.PESSOA_NOME || '').toLowerCase().includes(termo) ||
      (item.FUNCIONARIO_MATRICULA || '').toLowerCase().includes(termo) ||
      (item.CARGO_NOME || '').toLowerCase().includes(termo)
    )
  }

  return filtrado
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(listaFiltrada.value.length / itensPorPagina)))
const listaPaginada = computed(() => {
  const i = (paginaAtual.value - 1) * itensPorPagina
  return listaFiltrada.value.slice(i, i + itensPorPagina)
})

watch(listaFiltrada, () => {
  if (paginaAtual.value > totalPaginas.value) paginaAtual.value = totalPaginas.value
})

// ── BUSCA DA API ────────────────────────────────────────────────
const fetchDados = async () => {
  loading.value = true
  erro.value = ''
  
  try {
    const response = await api.get('/api/v3/avaliacoes')
    
    // Supondo que a API retorna um array direto ou { dados: [] }
    const dadosApi = Array.isArray(response.data) ? response.data : 
                     (response.data.dados || response.data.avaliacoes || [])
    
    lista.value = dadosApi.map(item => ({
      ...item,
      // Status padrão caso nulo
      AVALIACAO_STATUS: item.AVALIACAO_STATUS || 'pendente'
    }))
    
    // Atualiza contadores
    pendentesCount.value = lista.value.filter(i => 
      ['pendente', 'rascunho'].includes(i.AVALIACAO_STATUS.toLowerCase())
    ).length
    concluidasCount.value = lista.value.filter(i => 
      ['concluída', 'concluida', 'publicada'].includes(i.AVALIACAO_STATUS.toLowerCase())
    ).length

  } catch (err) {
    console.error('Erro ao buscar avaliações:', err)
    erro.value = 'Não foi possível carregar as avaliações. Tente novamente mais tarde.'
  } finally {
    loading.value = false
    loaded.value = true
  }
}

// ── MODAL AVALIAÇÃO ─────────────────────────────────────────────
const modalAberto = ref(false)
const salvando = ref(false)
const erroModal = ref('')
const successoModal = ref('')

const formVazio = () => ({
  AVALIACAO_ID: null,
  FUNCIONARIO_ID: null,
  PESSOA_NOME: '',
  FUNCIONARIO_MATRICULA: '',
  CARGO_NOME: '',
  AVALIACAO_CICLO: '2026.1', // Deveria vir da API ou param
  AVALIACAO_STATUS: 'pendente',
  AVALIACAO_OBS: '',
  criterios: [
    { CRITERIO_NOME: 'Produtividade e Qualidade', CRITERIO_PESO: 25, CRITERIO_NOTA: null, CRITERIO_OBS: '' },
    { CRITERIO_NOME: 'Assiduidade e Pontualidade', CRITERIO_PESO: 20, CRITERIO_NOTA: null, CRITERIO_OBS: '' },
    { CRITERIO_NOME: 'Iniciativa e Proatividade', CRITERIO_PESO: 20, CRITERIO_NOTA: null, CRITERIO_OBS: '' },
    { CRITERIO_NOME: 'Relacionamento Interpessoal', CRITERIO_PESO: 15, CRITERIO_NOTA: null, CRITERIO_OBS: '' },
    { CRITERIO_NOME: 'Conhecimento Técnico', CRITERIO_PESO: 20, CRITERIO_NOTA: null, CRITERIO_OBS: '' }
  ]
})

const form = ref(formVazio())

const isVisualizacao = computed(() => {
  return !['pendente', 'rascunho'].includes((form.value.AVALIACAO_STATUS || '').toLowerCase())
})

// Nota calculada reativa
const notaCalculada = computed(() => {
  if (!form.value.criterios || form.value.criterios.length === 0) return 0
  
  let totalPeso = 0
  let somaPonderada = 0
  
  form.value.criterios.forEach(crit => {
    if (crit.CRITERIO_NOTA !== null && crit.CRITERIO_NOTA !== undefined && crit.CRITERIO_NOTA !== '') {
      somaPonderada += Number(crit.CRITERIO_NOTA) * Number(crit.CRITERIO_PESO)
      totalPeso += Number(crit.CRITERIO_PESO)
    }
  })
  
  if (totalPeso === 0) return 0
  return (somaPonderada / totalPeso)
})

const notaCalculadaTexto = computed(() => {
  const nota = notaCalculada.value
  return nota > 0 ? nota.toFixed(1) : '-'
})

const notaAtualColor = computed(() => {
  const nota = notaCalculada.value
  if (nota === 0) return '#94a3b8'
  if (nota >= 7) return '#10b981'
  if (nota >= 5) return '#f59e0b'
  return '#f43f5e'
})

const abrirModalAvaliacao = (item) => {
  erroModal.value = ''
  successoModal.value = ''
  
  // Clone do item para o form
  form.value = { 
    ...formVazio(),
    ...JSON.parse(JSON.stringify(item))
  }
  
  // Se a API não retornou critérios formatados, cria os padrão e preenche se houver notas salvadas
  if (!form.value.criterios || form.value.criterios.length === 0) {
    form.value.criterios = formVazio().criterios
  }
  
  modalAberto.value = true
}

const salvarAvaliacao = async () => {
  erroModal.value = ''
  
  // Validação: todos critérios devem ter nota
  const incompleto = form.value.criterios.some(c => c.CRITERIO_NOTA === null || c.CRITERIO_NOTA === '')
  if (incompleto) {
    erroModal.value = 'Por favor, atribua uma nota para todos os critérios antes de concluir.'
    return
  }

  salvando.value = true
  
  try {
    const payload = {
      FUNCIONARIO_ID: form.value.FUNCIONARIO_ID,
      AVALIACAO_CICLO: form.value.AVALIACAO_CICLO,
      AVALIACAO_OBS: form.value.AVALIACAO_OBS,
      AVALIACAO_NOTA_FINAL: notaCalculada.value,
      AVALIACAO_STATUS: 'concluida', // ou 'enviada' dependendo da regra
      criterios: form.value.criterios
    }
    
    // Se tem ID, é PUT, senão POST (ou a controller lida com upsert no POST)
    if (form.value.AVALIACAO_ID) {
        // Assume post para upsert como instruído
        payload.AVALIACAO_ID = form.value.AVALIACAO_ID
    }

    const { data } = await api.post('/api/v3/avaliacoes', payload)
    
    successoModal.value = 'Avaliação salva com sucesso!'
    
    // Atualiza a lista
    await fetchDados()
    
    setTimeout(() => {
      modalAberto.value = false
    }, 1500)
    
  } catch (err) {
    erroModal.value = err.response?.data?.message || 'Erro ao salvar avaliação. Tente novamente.'
  } finally {
    salvando.value = false
  }
}

// Event loop: ESC para fechar
const onKeydown = (e) => {
  if (e.key === 'Escape' && modalAberto.value) {
    modalAberto.value = false
  }
}

onMounted(() => {
  fetchDados()
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})

</script>

<style scoped>
/* A maior parte dos estilos vem dos globais, definiremos apenas os específicos da view */
.view-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

@keyframes spin { 
  to { transform: rotate(360deg); } 
}
</style>
