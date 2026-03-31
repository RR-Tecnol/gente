<template>
  <div class="view-container">
    <!-- ═══ HERO ═══════════════════════════════════════════════════ -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" style="background: #ef4444;"></div>
        <div class="hs hs2" style="background: #3b82f6;"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Gestão SESMT</span>
          <h1 class="hero-title">Medicina Ocupacional</h1>
          <p class="hero-sub">Painel administrativo de ASOs e Agendamentos</p>
        </div>
        <div class="hero-chips">
          <div class="chip">
            <div class="chip-dot" style="background: #fbbf24;"></div>
            <strong>{{ kpis.proximos || 0 }}</strong> A Vencer
          </div>
          <div class="chip" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3);">
            <div class="chip-dot" style="background: #ef4444;"></div>
            <strong>{{ kpis.vencidos || 0 }}</strong> Vencidos
          </div>
           <div class="chip" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3);">
            <div class="chip-dot" style="background: #3b82f6;"></div>
            <strong>{{ kpis.agendados || 0 }}</strong> Agendamentos
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TABS ═══════════════════════════════════════════════════ -->
    <div class="filter-tabs" style="margin-top: 20px;" :class="{ loaded }">
      <button class="ftab" :class="{ active: tabAtiva === 'agendamentos' }" @click="tabAtiva = 'agendamentos'">Agendamentos Pendentes</button>
      <button class="ftab" :class="{ active: tabAtiva === 'exames' }" @click="tabAtiva = 'exames'">Histórico de ASOs</button>
    </div>

    <!-- ═══ CONTEÚDO: AGENDAMENTOS ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'agendamentos'" style="margin-top: 20px;">
      
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Solicitações dos Servidores</h2>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Servidor</th>
              <th>Tipo do Exame</th>
              <th>Data Solicitada</th>
              <th>Status</th>
              <th width="120">Decisão</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingAgendamentos" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="agendamentos.length === 0" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px; color:#64748b;">Nenhuma solicitação de agendamento pendente no momento.</td></tr>
            
            <tr v-for="(item, i) in agendamentos" :key="item.AFASTAMENTO_ID" class="data-row" :class="{ 'row-visible': loaded }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.FUNCIONARIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">MATRÍCULA: {{ item.FUNCIONARIO_MATRICULA }}</div>
              </td>
              <td>
                <div style="font-weight: 600;">{{ extrairNomeExame(item.AFASTAMENTO_DESCRICAO) }}</div>
                <div v-if="item.AFASTAMENTO_OBS" style="font-size: 11px; color: #94a3b8; margin-top: 4px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="item.AFASTAMENTO_OBS">
                   Obs: {{ item.AFASTAMENTO_OBS }}
                </div>
              </td>
              <td style="font-weight: 600;">{{ formataDataBr(item.AFASTAMENTO_DATA_INICIO) }}</td>
              <td><span class="badge badge-yellow"><span class="badge-dot"></span>Aguardando...</span></td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-green" @click="decidirAgendamento(item.AFASTAMENTO_ID, 'aprovado')" title="Confirmar Agendamento / Finalizar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <button class="act-btn act-red" @click="decidirAgendamento(item.AFASTAMENTO_ID, 'cancelado')" title="Dispensar / Cancelar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: HISTÓRICO DE EXAMES ASO ══════════════════════ -->
    <div v-if="tabAtiva === 'exames'" style="margin-top: 20px;">
      
      <div class="toolbar" :class="{ loaded }">
        <div class="search-wrap">
          <input 
            v-model="buscaExames" 
            class="search-input"
            placeholder="Buscar servidor por nome ou matrícula..."
            @input="debounceBusca"
          />
          <button v-if="buscaExames" class="search-clear" @click="buscaExames = ''; debounceBusca()">✕</button>
        </div>
        <div class="toolbar-right" style="display:flex; gap:10px;">
           <label class="toggle-wrap" style="font-size: 13px; font-weight: 600; color: #475569; display:flex; align-items:center; gap:6px; cursor: pointer;">
             <input type="checkbox" v-model="filtroVencidos" @change="fetchExames" style="accent-color: #ef4444;"> Somente Vencidos
           </label>
           <button class="btn-novo" @click="abrirModalExame()">
            + Lançar ASO Manual
           </button>
        </div>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Servidor</th>
              <th>Tipo do Exame / Data</th>
              <th>Dr(a). Responsável</th>
              <th>Validade</th>
              <th>Aptidão</th>
              <th width="60">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingExames" class="data-row row-visible"><td colspan="6" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="exames.length === 0" class="data-row row-visible"><td colspan="6" style="text-align:center; padding: 40px; color:#64748b;">Nenhum exame ASO encontrado.</td></tr>
            
            <tr v-for="(item, i) in exames" :key="item.EXAME_ID" class="data-row" :class="{ 'row-visible': loaded }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.FUNCIONARIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ item.FUNCIONARIO_MATRICULA }}</div>
              </td>
              <td>
                <div style="font-weight: 600;">{{ item.EXAME_TIPO }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">REALIZADO: {{ formataDataBr(item.EXAME_DATA_REALIZACAO) }}</div>
              </td>
              <td style="color: #475569; font-size: 13px;">{{ item.EXAME_MEDICO || 'NR' }}</td>
              <td>
                 <span class="badge" :class="tagVencimento(item.EXAME_DATA_VENCIMENTO).color">
                   <span class="badge-dot"></span>{{ tagVencimento(item.EXAME_DATA_VENCIMENTO).label }}
                 </span>
              </td>
              <td>
                 <span class="badge" :class="item.EXAME_APTO ? 'badge-green' : 'badge-red'">
                   {{ item.EXAME_APTO ? 'Apto' : 'Restrito/Inapto' }}
                 </span>
               </td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-blue" @click="abrirModalExame(item)" title="Editar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <!-- Button exc -->
                  <button class="act-btn act-red" @click="deletarExame(item.EXAME_ID)" title="Excluir" style="margin-left:auto;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                 </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>


    <!-- ═══ MODAIS ═════════════════════════════════════════════════ -->

    <div v-if="modalExameAberto" class="modal-overlay" @mousedown.self="modalExameAberto = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">{{ formExame.EXAME_ID ? 'Editar ASO' : 'Lançar Novo ASO' }}</h2>
          <button class="modal-close" @click="modalExameAberto = false">✕</button>
        </div>
        
        <div class="modal-body">
          <div v-if="erroModal" class="toast-error">{{ erroModal }}</div>
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Pessoa / Servidor (Mín. 3 Letras ou Matrícula)</label>
              <div v-if="strServidorSelecionado" style="display:flex; align-items:center; gap: 10px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px;">
                 <span style="font-weight: 600; font-size: 14px; flex: 1;">{{ strServidorSelecionado }}</span>
                 <button @click="resetServidor" style="background:transparent; border:none; color: #ef4444; font-weight:700; cursor:pointer;" v-if="!formExame.EXAME_ID">TROCAR</button>
              </div>
              <div v-else style="position: relative;">
                <input v-model="buscaServidorModal" @input="debounceBuscaModal" class="form-input" placeholder="Digite para buscar...">
                <div v-if="listaServidoresBusca.length > 0" style="position:absolute; top: 100%; left: 0; right: 0; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px; z-index: 10; max-height: 180px; overflow-y: auto;">
                   <div v-for="sv in listaServidoresBusca" :key="sv.FUNCIONARIO_ID" @click="selecionarServidor(sv)" style="padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: 13px;">
                      <strong>{{ sv.PESSOA_NOME }}</strong> | {{ sv.FUNCIONARIO_MATRICULA }}
                   </div>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Tipo Ocupacional</label>
              <select v-model="formExame.EXAME_TIPO" class="form-input">
                <option value="Periódico">Periódico</option>
                <option value="Admissional">Admissional</option>
                <option value="Demissional">Demissional</option>
                <option value="Retorno ao Trabalho">Retorno ao Trabalho</option>
                <option value="Mudança de Função">Mudança de Função</option>
              </select>
            </div>
            <div class="form-group">
              <label>Categoria de Aptidão</label>
              <select v-model="formExame.EXAME_APTO" class="form-input">
                 <option :value="true">Apto Totalmente</option>
                 <option :value="false">Restrito / Inapto</option>
              </select>
            </div>
            <div class="form-group">
              <label>Data de Realização</label>
              <input v-model="formExame.EXAME_DATA_REALIZACAO" type="date" class="form-input">
            </div>
            <div class="form-group">
              <label>Data de Vencimento</label>
              <input v-model="formExame.EXAME_DATA_VENCIMENTO" type="date" class="form-input">
            </div>
            <div class="form-group col-full">
              <label>Médico(a) Responsável e Registro (Ex: CRM)</label>
              <input v-model="formExame.EXAME_MEDICO" class="form-input" placeholder="Dr(a). Fulano de Tal - CRM/MA 12345">
            </div>
             <div class="form-group col-full">
              <label>Observações Adicionais</label>
               <input v-model="formExame.EXAME_OBS" class="form-input" placeholder="Necessidade de reavaliação em curto prazo, uso de lente, etc...">
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalExameAberto = false">Cancelar</button>
          <button class="btn-novo" @click="salvarExame" :disabled="salvando || !formExame.FUNCIONARIO_ID">
            <span v-if="salvando" class="spinner" style="width: 14px; height: 14px; border-width: 2px;"></span>
            Salvar
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tabAtiva = ref('agendamentos')
const loadingAgendamentos = ref(false)
const loadingExames = ref(false)
const salvando = ref(false)
const erroModal = ref('')

const agendamentos = ref([])
const exames = ref([])
const kpis = ref({ total: 0, vencidos: 0, proximos: 0, agendados: 0 })

// Buscas Exames
const buscaExames = ref('')
const filtroVencidos = ref(false)

// Utils
const formataDataBr = (isoStr) => {
  if (!isoStr) return ''
  const parts = isoStr.split(' ')[0].split('-')
  if (parts.length !== 3) return isoStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}
const extrairNomeExame = (str) => {
  return str ? str.replace('Exame Ocupacional: ', '') : 'Exame Ocupacional'
}

const tagVencimento = (dataStr) => {
  if (!dataStr) return { label: 'Sob Demanda', color: 'badge-gray' }
  const v = new Date(dataStr)
  v.setHours(23, 59, 59, 999) // Evitar fuso hr desfazendo um dia
  const diff = (v - new Date()) / 86400000
  if (diff < 0) return { label: `Vencido (${formataDataBr(dataStr)})`, color: 'badge-red' }
  if (diff <= 30) return { label: `Vence em ${Math.ceil(diff)}d (${formataDataBr(dataStr)})`, color: 'badge-yellow' }
  return { label: `Em Dia (${formataDataBr(dataStr)})`, color: 'badge-green' }
}

onMounted(() => {
  fetchAgendamentos()
  fetchKpis()
  setTimeout(() => loaded.value = true, 50)
})

watch(tabAtiva, (newTab) => {
  if (newTab === 'agendamentos') fetchAgendamentos()
  if (newTab === 'exames') fetchExames()
})

// ── AGENDAMENTOS ─────────────────────────────────────────────────────
const fetchAgendamentos = async () => {
  loadingAgendamentos.value = true
  try {
    const { data } = await api.get('/api/v3/medicina-admin/agendamentos')
    agendamentos.value = data || []
  } catch (e) {
    console.error(e)
  } finally {
     loadingAgendamentos.value = false
  }
}

const decidirAgendamento = async (id, decisao) => {
   const msg = decisao === 'aprovado' ? 'Confirmar este agendamento como finalizado/concluído?' : 'Deseja dispensar/cancelar esta solicitação do servidor?';
   if (!confirm(msg)) return
   try {
      await api.post(`/api/v3/medicina-admin/agendamentos/${id}/status`, { status: decisao })
      fetchAgendamentos()
      fetchKpis()
   } catch (e) {
      alert('Erro inesperado')
   }
}

// ── EXAMES / ASOS ────────────────────────────────────────────────────
let timerExames = null
const debounceBusca = () => {
  clearTimeout(timerExames)
  timerExames = setTimeout(() => fetchExames(), 600)
}

const fetchExames = async () => {
  loadingExames.value = true
  try {
    const qBusca = buscaExames.value ? `&busca=${encodeURIComponent(buscaExames.value)}` : ''
    const qVencido = filtroVencidos.value ? `&vencidos=true` : ''
    const { data } = await api.get(`/api/v3/medicina-admin/exames?z=1${qBusca}${qVencido}`)
    exames.value = data || []
  } catch (e) {
    console.error(e)
  } finally {
     loadingExames.value = false
  }
}

const deletarExame = async (id) => {
   if (!confirm('Deseja excluir o registro corporativo deste ASO?')) return
   try {
      await api.delete(`/api/v3/medicina-admin/exames/${id}`)
      fetchExames()
      fetchKpis()
   } catch (e) {
      alert('Erro ao excluir exame')
   }
}

// ── MODAL E CADASTRO ─────────────────────────────────────────────────
const modalExameAberto = ref(false)
const formExame = ref({})
// Busca servidor (auto-complete manual)
const buscaServidorModal = ref('')
const listaServidoresBusca = ref([])
const strServidorSelecionado = ref('')

let timerModal = null
const debounceBuscaModal = () => {
  clearTimeout(timerModal)
  timerModal = setTimeout(async () => {
     if(buscaServidorModal.value.length < 3) {
         listaServidoresBusca.value = []
         return
     }
     try {
        const { data } = await api.get(`/api/v3/medicina-admin/servidores?busca=${encodeURIComponent(buscaServidorModal.value)}`)
        listaServidoresBusca.value = data || []
     } catch (e) {
         // ignore
     }
  }, 400)
}

const selecionarServidor = (sv) => {
   formExame.value.FUNCIONARIO_ID = sv.FUNCIONARIO_ID
   strServidorSelecionado.value = `${sv.PESSOA_NOME} (${sv.FUNCIONARIO_MATRICULA})`
   listaServidoresBusca.value = []
   buscaServidorModal.value = ''
}
const resetServidor = () => {
   formExame.value.FUNCIONARIO_ID = null
   strServidorSelecionado.value = ''
}

const abrirModalExame = (item = null) => {
  erroModal.value = ''
  
  if (item) {
    formExame.value = JSON.parse(JSON.stringify(item))
    // formata boleano
    formExame.value.EXAME_APTO = !!formExame.value.EXAME_APTO
    strServidorSelecionado.value = `${item.FUNCIONARIO_NOME} (${item.FUNCIONARIO_MATRICULA})`
  } else {
    formExame.value = { EXAME_ID: null, FUNCIONARIO_ID: null, EXAME_TIPO: 'Periódico', EXAME_DATA_REALIZACAO: new Date().toISOString().slice(0,10), EXAME_DATA_VENCIMENTO: '', EXAME_MEDICO: '', EXAME_APTO: true, EXAME_OBS: '' }
    resetServidor()
  }
  modalExameAberto.value = true
}

const salvarExame = async () => {
  if (!formExame.value.FUNCIONARIO_ID) {
    erroModal.value = 'Selecione quem fará o ASO.'
    return
  }
  salvando.value = true
  try {
    const payload = {...formExame.value}
    if(!payload.EXAME_DATA_VENCIMENTO) payload.EXAME_DATA_VENCIMENTO = null
    await api.post('/api/v3/medicina-admin/exames', payload)
    modalExameAberto.value = false
    await fetchExames()
    fetchKpis()
  } catch (e) {
    erroModal.value = e.response?.data?.error || 'Erro ao salvar o exame.'
  } finally {
    salvando.value = false
  }
}

const fetchKpis = async () => {
   try {
     const { data } = await api.get('/api/v3/medicina-admin/kpis')
     kpis.value = data || kpis.value
   } catch (e) {
     // ign
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
