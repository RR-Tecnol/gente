<template>
  <div class="view-container">
    <!-- ═══ HERO ═══════════════════════════════════════════════════ -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" style="background: #3b82f6;"></div>
        <div class="hs hs2" style="background: #8b5cf6;"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Desenvolvimento RH</span>
          <h1 class="hero-title">Gestão de Treinamentos</h1>
          <p class="hero-sub">Painel administrativo de cursos, matrículas e capacitações</p>
        </div>
        <div class="hero-chips">
          <div class="chip" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3);">
            <div class="chip-dot" style="background: #3b82f6;"></div>
            <strong>{{ kpis.cursos_ativos || 0 }}</strong> Cursos Ativos
          </div>
          <div class="chip" style="background: rgba(139, 92, 246, 0.15); border-color: rgba(139, 92, 246, 0.3);">
            <div class="chip-dot" style="background: #8b5cf6;"></div>
            <strong>{{ kpis.total_inscricoes || 0 }}</strong> Inscrições
          </div>
           <div class="chip" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3);">
            <div class="chip-dot" style="background: #10b981;"></div>
            <strong>{{ kpis.total_concluidos || 0 }}</strong> Conclusões
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TABS ═══════════════════════════════════════════════════ -->
    <div class="filter-tabs" style="margin-top: 20px;" :class="{ loaded }">
      <button class="ftab" :class="{ active: tabAtiva === 'cursos' }" @click="tabAtiva = 'cursos'">Catálogo de Cursos</button>
      <button class="ftab" :class="{ active: tabAtiva === 'inscritos' }" @click="tabAtiva = 'inscritos'">Gestão de Inscrições</button>
    </div>

    <!-- ═══ CONTEÚDO: CURSOS ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'cursos'" style="margin-top: 20px;">
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Catálogo Ofertado</h2>
         <button class="btn-novo" @click="abrirModalCurso()">+ Novo Curso</button>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Título do Curso</th>
              <th>Área e Modalidade</th>
              <th>Carga (Horas)</th>
              <th>Status</th>
              <th width="100">Editar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading.cursos" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="cursos.length === 0" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px; color:#64748b;">Nenhum curso cadastrado no catálogo.</td></tr>
            
            <tr v-for="(item, i) in cursos" :key="item.TREINAMENTO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.TREINAMENTO_TITULO }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ item.TREINAMENTO_DESC?.substring(0,60) || 'Sem descrição' }}...</div>
              </td>
              <td>
                <div style="font-weight: 600;">{{ item.TREINAMENTO_AREA }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">{{ item.TREINAMENTO_MODALIDADE }}</div>
              </td>
              <td style="color: #475569; font-weight: 700;">{{ item.TREINAMENTO_CARGA }}h</td>
              <td>
                 <span v-if="item.TREINAMENTO_ATIVO" class="badge badge-green"><span class="badge-dot"></span>Ativo (Aberto)</span>
                 <span v-else class="badge badge-gray">Inativo</span>
              </td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-blue" @click="abrirModalCurso(item)" title="Editar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONTEÚDO: INSCRITOS ═════════════════════════════════ -->
    <div v-if="tabAtiva === 'inscritos'" style="margin-top: 20px;">
      <div class="toolbar" :class="{ loaded }">
         <h2 style="font-size: 16px; font-weight: 800; color: #1e293b; margin: 0;">Progresso e Matriculados</h2>
      </div>

      <div class="table-card" :class="{ loaded }" style="margin-top: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Servidor</th>
              <th>Curso Relacionado</th>
              <th>Status Atual</th>
              <th>Andamento</th>
              <th>Data Emissão</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading.inscritos" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
            <tr v-else-if="inscricoes.length === 0" class="data-row row-visible"><td colspan="5" style="text-align:center; padding: 40px; color:#64748b;">Nenhuma matrícula encontrada.</td></tr>
            
            <tr v-for="(item, i) in inscricoes" :key="item.INSCRICAO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>
                <div style="font-weight: 700; color: #1e293b;">{{ item.FUNCIONARIO_NOME }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">MATRÍCULA: {{ item.FUNCIONARIO_MATRICULA }}</div>
              </td>
              <td><span style="font-weight: 600;">{{ item.TREINAMENTO_TITULO }}</span></td>
              <td>
                 <span class="badge" :class="item.INSCRICAO_STATUS === 'concluido' ? 'badge-green' : (item.INSCRICAO_STATUS === 'andamento' ? 'badge-blue' : 'badge-yellow')">
                    {{ statusLabel(item.INSCRICAO_STATUS) }}
                 </span>
              </td>
              <td><strong style="color: #6366f1;">{{ item.INSCRICAO_PROGRESSO }}%</strong></td>
              <td style="color: #475569; font-size: 12px;">{{ item.created_at ? formataDataBr(item.created_at) : '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ MODAIS ═════════════════════════════════════════════════ -->
    <div v-if="modal.curso" class="modal-overlay" @mousedown.self="modal.curso = false">
      <div class="modal-box modal-md" style="animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1);">
        <div class="modal-header">
          <h2 class="modal-title">{{ formCurso.TREINAMENTO_ID ? 'Editar Curso' : 'Novo Curso Base' }}</h2>
          <button class="modal-close" @click="modal.curso = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group col-full">
              <label>Título do Curso / Capacitação</label>
              <input v-model="formCurso.titulo" class="form-input" placeholder="Ex: Liderança e Gestão">
            </div>
            <div class="form-group col-full">
              <label>Ementa ou Descrição Básica</label>
              <textarea v-model="formCurso.desc" class="form-input" rows="3"></textarea>
            </div>
            <div class="form-group">
              <label>Área Temática</label>
              <select v-model="formCurso.area" class="form-input">
                <option value="Comportamental">Comportamental</option>
                <option value="Saúde">Saúde Integrada</option>
                <option value="Segurança">Segurança (SST)</option>
                <option value="Tecnologia">Tecnologia e Redes</option>
                <option value="Geral">Generalista / RH</option>
              </select>
            </div>
            <div class="form-group">
              <label>Modalidade Oficial</label>
              <select v-model="formCurso.modalidade" class="form-input">
                <option value="EAD">EAD (Assíncrono)</option>
                <option value="Presencial">Presencial (Físico)</option>
                <option value="Híbrido">Híbrido (Ambos)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Carga Horária Planejada (Hrs)</label>
              <input v-model="formCurso.carga" type="number" class="form-input">
            </div>
            <div class="form-group">
              <label>Quantidade de Vagas Visíveis</label>
              <input v-model="formCurso.vagas" type="number" class="form-input">
            </div>
            <div class="form-group col-full" v-if="formCurso.TREINAMENTO_ID">
               <label class="toggle-wrap" style="font-size: 13px; font-weight: 600; color: #475569; display:flex; align-items:center; gap:6px; cursor: pointer;">
                 <input type="checkbox" v-model="formCurso.ativo" style="accent-color: #3b82f6;"> Curso Ativo no Catálogo do RH
               </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modal.curso = false">Cancelar</button>
          <button class="btn-novo" @click="salvarCurso" :disabled="salvando || !formCurso.titulo">Salvar Capacitação</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false)
const tabAtiva = ref('cursos')
const loading = reactive({ cursos: false, inscritos: false })
const salvando = ref(false)

const cursos = ref([])
const inscricoes = ref([])
const kpis = ref({ cursos_ativos: 0, total_inscricoes: 0, total_concluidos: 0 })

// Utils
const formataDataBr = (isoStr) => {
  if (!isoStr) return ''
  const parts = isoStr.split(' ')[0].split('-')
  if (parts.length !== 3) return isoStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

const statusLabel = (s) => ({ concluido: 'Concluído', andamento: 'Em Andamento', inscrito: 'Matriculado' })[s] ?? s

onMounted(() => {
  fetchCursos()
  fetchKpis()
  setTimeout(() => loaded.value = true, 50)
})

watch(tabAtiva, (newTab) => {
  if (newTab === 'cursos') fetchCursos()
  if (newTab === 'inscritos') fetchInscritos()
})

const fetchKpis = async () => {
   try {
     const { data } = await api.get('/api/v3/treinamentos-admin/kpis')
     kpis.value = data || kpis.value
   } catch (e) { /* ign */ }
}

const fetchCursos = async () => {
  loading.cursos = true
  try {
    const { data } = await api.get('/api/v3/treinamentos-admin/cursos')
    cursos.value = data || []
  } catch (e) { console.error(e) } finally { loading.cursos = false }
}

const fetchInscritos = async () => {
  loading.inscritos = true
  try {
    const { data } = await api.get('/api/v3/treinamentos-admin/inscricoes')
    inscricoes.value = data || []
  } catch (e) { console.error(e) } finally { loading.inscritos = false }
}

// —— MODAL CURSO
const modal = reactive({ curso: false })
const formCurso = ref({})

const abrirModalCurso = (item) => {
   if(item) {
     formCurso.value = { 
       TREINAMENTO_ID: item.TREINAMENTO_ID, titulo: item.TREINAMENTO_TITULO, desc: item.TREINAMENTO_DESC, 
       area: item.TREINAMENTO_AREA, carga: item.TREINAMENTO_CARGA, modalidade: item.TREINAMENTO_MODALIDADE, 
       vagas: item.TREINAMENTO_VAGAS, ativo: !!item.TREINAMENTO_ATIVO
     }
   } else {
      formCurso.value = { 
       TREINAMENTO_ID: null, titulo: '', desc: '', area: 'Geral', carga: 10, modalidade: 'EAD', vagas: 0, ativo: true
     }
   }
   modal.curso = true
}

const salvarCurso = async () => {
  salvando.value = true
  try {
    if(formCurso.value.TREINAMENTO_ID) {
        await api.put(`/api/v3/treinamentos-admin/cursos/${formCurso.value.TREINAMENTO_ID}`, formCurso.value)
    } else {
        await api.post('/api/v3/treinamentos-admin/cursos', formCurso.value)
    }
    modal.curso = false
    fetchCursos()
    fetchKpis()
  } catch(e) { alert('Erro ao registrar.') } finally { salvando.value = false }
}
</script>

<style scoped>
.view-container { display: flex; flex-direction: column; }
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
