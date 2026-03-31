<template>
  <div class="view-container">
    <!-- ═══ HERO ═══════════════════════════════════════════════════ -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" style="background: #14b8a6;"></div>
        <div class="hs hs2" style="background: #f59e0b;"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Gestão SESMT</span>
          <h1 class="hero-title">Segurança do Trabalho</h1>
          <p class="hero-sub">Normas regulamentadoras, EPIs e Incidentes</p>
        </div>
        <div class="hero-chips">
          <div class="chip" style="background: rgba(20, 184, 166, 0.15); border-color: rgba(20, 184, 166, 0.3);">
            <div class="chip-dot" style="background: #14b8a6;"></div>
            <strong>{{ kpis.epis_pendentes || 0 }}</strong> EPIs Pendentes
          </div>
          <div class="chip" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.3);">
            <div class="chip-dot" style="background: #f59e0b;"></div>
            <strong>{{ kpis.incidentes_abertos || 0 }}</strong> Incid. Abertos
          </div>
           <div class="chip" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3);">
            <div class="chip-dot" style="background: #ef4444;"></div>
            <strong>{{ kpis.laudos_vencidos || 0 }}</strong> Laudos Vencidos
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TABS ═══════════════════════════════════════════════════ -->
    <div class="filter-tabs" style="margin-top: 20px;" :class="{ loaded }">
      <button class="ftab" :class="{ active: tabAtiva === 'incidentes' }" @click="tabAtiva = 'incidentes'">Incidentes / CAT</button>
      <button class="ftab" :class="{ active: tabAtiva === 'epis' }" @click="tabAtiva = 'epis'">Solicitações de EPI</button>
      <button class="ftab" :class="{ active: tabAtiva === 'laudos' }" @click="tabAtiva = 'laudos'">Gestão de Laudos</button>
    </div>

    <!-- ═══ CONTEÚDO: INCIDENTES ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'incidentes'" style="margin-top: 20px;">
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Registro de Incidentes e Acidentes</h2>
         <button class="btn-novo" @click="fetchIncidentes" style="background:transparent; color:#64748b; border:1px solid #e2e8f0;">Atualizar</button>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Servidor Envolvido</th>
              <th>Data e Tipo</th>
              <th>Local Ocorrência</th>
              <th>Relato</th>
              <th>CAT (eSocial)</th>
              <th width="100">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading.incidentes" class="data-row row-visible"><td colspan="6" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="incidentes.length === 0" class="data-row row-visible"><td colspan="6" style="text-align:center; padding: 40px; color:#64748b;">Nenhum incidente registrado.</td></tr>
            
            <tr v-for="(item, i) in incidentes" :key="item.ACIDENTE_ID" class="data-row" :class="{ 'row-visible': loaded, 'row-closed': item.ACIDENTE_CLOSED }" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.FUNCIONARIO_NOME || 'Externo/Desconhecido' }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;" v-if="item.FUNCIONARIO_MATRICULA">MATRÍCLUA: {{ item.FUNCIONARIO_MATRICULA }}</div>
              </td>
              <td>
                <div style="font-weight: 600;">{{ item.ACIDENTE_TIPO === 'acidente' ? 'Acidente' : 'Quase-Acidente' }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">{{ formataDataBr(item.created_at) }}</div>
              </td>
              <td style="color: #475569; font-size: 13px;">{{ item.ACIDENTE_LOCAL }}</td>
              <td>
                <div title="Ler relato completo" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #64748b;">
                  {{ item.ACIDENTE_DESCRICAO }}
                </div>
              </td>
              <td>
                 <span v-if="item.ACIDENTE_CAT" class="badge badge-green"><span class="badge-dot"></span>CAT: {{ item.ACIDENTE_CAT }}</span>
                 <span v-else-if="item.ACIDENTE_CLOSED" class="badge badge-gray">S/ CAT (Fechado)</span>
                 <span v-else class="badge badge-yellow">Analisando...</span>
              </td>
              <td>
                <div class="row-actions" v-if="!item.ACIDENTE_CLOSED">
                  <button class="act-btn act-blue" @click="abrirModalCat(item)" title="Emitir CAT / Encerrar Caso">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
                <div v-else style="font-size: 11px; color: #10b981; font-weight: 700; text-align:center;">
                  ✓ Concluído
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: EPIs ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'epis'" style="margin-top: 20px;">
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Solicitações de EPI (Almoxarifado)</h2>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Servidor Solicitante</th>
              <th>Equipamento Requerido</th>
              <th>Justificativa / Motivo</th>
              <th>Status</th>
              <th width="120">Decisão</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading.epis" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="episPendentes.length === 0" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px; color:#64748b;">Nenhuma solicitação pendente.</td></tr>
            
            <tr v-for="(item, i) in episPendentes" :key="item.EPI_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.FUNCIONARIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ item.FUNCIONARIO_MATRICULA }}</div>
              </td>
              <td><span style="font-weight: 600;">{{ item.EPI_NOME }}</span></td>
              <td style="color: #475569; font-size: 12px;">{{ item.EPI_OBS || 'Nenhum' }}</td>
              <td><span class="badge badge-yellow"><span class="badge-dot"></span>Pendente</span></td>
              <td>
                <button class="btn-novo" style="font-size: 11px; padding: 6px 10px;" @click="abrirModalEpi(item)">Liberar Entrega</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: LAUDOS ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'laudos'" style="margin-top: 20px;">
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Gestor de Laudos (SST)</h2>
         <button class="btn-novo" @click="abrirModalLaudo()">+ Adicionar Laudo</button>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Tipo do Laudo</th>
              <th>Local / Setor Abrangido</th>
              <th>Validade Documento</th>
              <th width="100">Excluir</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading.laudos" class="data-row row-visible"><td colspan="4" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="laudos.length === 0" class="data-row row-visible"><td colspan="4" style="text-align:center; padding: 40px; color:#64748b;">Nenhum laudo gerenciado no momento.</td></tr>
            
            <tr v-for="(item, i) in laudos" :key="item.LAUDO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong style="color: #1e293b;">{{ item.LAUDO_TIPO }}</strong></td>
              <td style="color: #475569;">{{ item.LAUDO_LOCAL }}</td>
              <td>
                 <span class="badge" :class="tagVencimento(item.LAUDO_DATA_VALIDADE).color">
                   <span class="badge-dot"></span>{{ tagVencimento(item.LAUDO_DATA_VALIDADE).label }}
                 </span>
              </td>
              <td>
                <button class="act-btn act-red" @click="excluirLaudo(item.LAUDO_ID)">
                   <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ MODAIS ═════════════════════════════════════════════════ -->
    <div v-if="modal.laudo" class="modal-overlay" @mousedown.self="modal.laudo = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">Registrar Novo Laudo</h2>
          <button class="modal-close" @click="modal.laudo = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Tipo (LTCAT, PPRA, PGR, etc)</label>
              <input v-model="formLaudo.tipo" class="form-input" placeholder="Ex: LTCAT">
            </div>
            <div class="form-group col-full">
              <label>Local ou Setor Aplicável</label>
              <input v-model="formLaudo.local" class="form-input" placeholder="Ex: Toda a Unidade Hospitalar">
            </div>
            <div class="form-group col-full">
              <label>Data de Validade/Vencimento</label>
              <input v-model="formLaudo.validade" type="date" class="form-input">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modal.laudo = false">Cancelar</button>
          <button class="btn-novo" @click="salvarLaudo" :disabled="salvando || !formLaudo.tipo || !formLaudo.local">Salvar Documento</button>
        </div>
      </div>
    </div>

    <div v-if="modal.epi" class="modal-overlay" @mousedown.self="modal.epi = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">Baixa e Entrega de EPI</h2>
          <button class="modal-close" @click="modal.epi = false">✕</button>
        </div>
        <div class="modal-body">
           <div style="margin-bottom: 20px; font-size: 13px; color: #475569;">
             Entregando: <strong style="color: #1e293b;">{{ formEpi.NOME }}</strong> para o servidor <strong style="color: #1e293b;">{{ formEpi.FUNCIONARIO }}</strong>
           </div>
          <div class="form-grid">
            <div class="form-group">
              <label>CA (Certificado de Aprovação)</label>
              <input v-model="formEpi.EPI_CA" class="form-input" placeholder="Ex: 31234">
            </div>
            <div class="form-group">
              <label>Quantidade Entregue</label>
              <input v-model="formEpi.EPI_QUANTIDADE" type="number" min="1" class="form-input">
            </div>
            <div class="form-group col-full">
              <label>Data de Descarte (Vencimento Vida Útil)</label>
              <input v-model="formEpi.EPI_DATA_VENCIMENTO" type="date" class="form-input">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modal.epi = false">Fechar</button>
          <button class="btn-novo" @click="registrarEntregaEpi" :disabled="salvando || !formEpi.EPI_CA">Confirmar Entrega Técnica</button>
        </div>
      </div>
    </div>

    <div v-if="modal.cat" class="modal-overlay" @mousedown.self="modal.cat = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">Encerrar e Formalizar Incidência</h2>
          <button class="modal-close" @click="modal.cat = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Número do Recibo da CAT (eSocial)</label>
              <input v-model="formCat.cat_numero" class="form-input" placeholder="(Opcional, preencha se gerou CAT)">
            </div>
            <div class="form-group col-full">
               <label class="toggle-wrap" style="font-size: 13px; font-weight: 600; color: #475569; display:flex; align-items:center; gap:6px; cursor: pointer;">
                 <input type="checkbox" v-model="formCat.encerrar" style="accent-color: #10b981;"> Dar baixa e encerrar acompanhamento do caso 
               </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modal.cat = false">Cancelar</button>
          <button class="btn-novo" @click="salvarCat" :disabled="salvando">Salvar Mudanças</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tabAtiva = ref('incidentes')
const loading = reactive({ incidentes: false, epis: false, laudos: false })
const salvando = ref(false)

const incidentes = ref([])
const episPendentes = ref([])
const laudos = ref([])
const kpis = ref({ epis_pendentes: 0, incidentes_abertos: 0, laudos_vencidos: 0 })

// Utils
const formataDataBr = (isoStr) => {
  if (!isoStr) return ''
  const parts = isoStr.split(' ')[0].split('-')
  if (parts.length !== 3) return isoStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}
const tagVencimento = (dataStr) => {
  if (!dataStr) return { label: 'Permanente', color: 'badge-gray' }
  const v = new Date(dataStr)
  v.setHours(23, 59, 59, 999) 
  const diff = (v - new Date()) / 86400000
  if (diff < 0) return { label: `Vencido (${formataDataBr(dataStr)})`, color: 'badge-red' }
  if (diff <= 30) return { label: `Vence em ${Math.ceil(diff)}d (${formataDataBr(dataStr)})`, color: 'badge-yellow' }
  return { label: `Em Dia (${formataDataBr(dataStr)})`, color: 'badge-green' }
}

onMounted(() => {
  fetchIncidentes()
  fetchKpis()
  setTimeout(() => loaded.value = true, 50)
})

watch(tabAtiva, (newTab) => {
  if (newTab === 'incidentes') fetchIncidentes()
  if (newTab === 'epis') fetchEpis()
  if (newTab === 'laudos') fetchLaudos()
})

const fetchKpis = async () => {
   try {
     const { data } = await api.get('/api/v3/seguranca-admin/kpis')
     kpis.value = data || kpis.value
   } catch (e) { /* ign */ }
}

const fetchIncidentes = async () => {
  loading.incidentes = true
  try {
    const { data } = await api.get('/api/v3/seguranca-admin/incidentes')
    incidentes.value = data || []
  } catch (e) { console.error(e) } finally { loading.incidentes = false }
}

const fetchEpis = async () => {
  loading.epis = true
  try {
    const { data } = await api.get('/api/v3/seguranca-admin/epis/solicitacoes')
    episPendentes.value = data || []
  } catch (e) { console.error(e) } finally { loading.epis = false }
}

const fetchLaudos = async () => {
  loading.laudos = true
  try {
    const { data } = await api.get('/api/v3/seguranca-admin/laudos')
    laudos.value = data || []
  } catch (e) { console.error(e) } finally { loading.laudos = false }
}

// —— MODAL EPI
const modal = reactive({ epi: false, laudo: false, cat: false })
const formEpi = ref({})

const abrirModalEpi = (item) => {
   formEpi.value = { 
     EPI_ID: item.EPI_ID, NOME: item.EPI_NOME, FUNCIONARIO: item.FUNCIONARIO_NOME, 
     EPI_CA: '', EPI_QUANTIDADE: 1, EPI_DATA_VENCIMENTO: '' 
   }
   modal.epi = true
}
const registrarEntregaEpi = async () => {
  salvando.value = true
  try {
    await api.post('/api/v3/seguranca-admin/epis/entregar', formEpi.value)
    modal.epi = false
    fetchEpis()
    fetchKpis()
  } catch(e) { alert('Erro ao registrar baixa.') } finally { salvando.value = false }
}

// —— MODAL LAUDO
const formLaudo = ref({ tipo: '', local: '', validade: '' })
const abrirModalLaudo = () => {
   formLaudo.value = { tipo: '', local: '', validade: '' }
   modal.laudo = true
}
const salvarLaudo = async () => {
  salvando.value = true
  try {
    await api.post('/api/v3/seguranca-admin/laudos', formLaudo.value)
    modal.laudo = false
    fetchLaudos()
    fetchKpis()
  } catch(e) { alert('Erro ao salvar laudo.') } finally { salvando.value = false }
}
const excluirLaudo = async (id) => {
   if(!confirm('Deseja excluir permanentemente este laudo?')) return
   try {
      await api.delete(`/api/v3/seguranca-admin/laudos/${id}`)
      fetchLaudos()
      fetchKpis()
   } catch(e) { alert('Falha ao excluir') }
}

// —— MODAL CAT
const formCat = ref({ id: null, cat_numero: '', encerrar: true })
const abrirModalCat = (item) => {
   formCat.value = { id: item.ACIDENTE_ID, cat_numero: item.ACIDENTE_CAT || '', encerrar: true }
   modal.cat = true
}
const salvarCat = async () => {
  salvando.value = true
  try {
    await api.post(`/api/v3/seguranca-admin/incidentes/${formCat.value.id}/cat`, formCat.value)
    modal.cat = false
    fetchIncidentes()
    fetchKpis()
  } catch(e) { alert('Erro ao registrar CAT.') } finally { salvando.value = false }
}

</script>

<style scoped>
.view-container { display: flex; flex-direction: column; }
.row-closed td { background: #f8fafc; opacity: 0.75; }
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
