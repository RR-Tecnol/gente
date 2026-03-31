<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" />
        <div class="hs hs2" />
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🛒 ERP Administrativo</span>
          <h1 class="hero-title">Compras e Licitações</h1>
          <p class="hero-sub">Gestão central de processos licitatórios, contratos administrativos e pedidos de secretarias (Bloco D).</p>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'processos' }" @click="aba = 'processos'; carregarProcessos()">⚖️ Processos</button>
      <button class="tab-btn" :class="{ active: aba === 'contratos' }" @click="aba = 'contratos'; carregarContratos()">📝 Contratos</button>
      <button class="tab-btn" :class="{ active: aba === 'pedidos' }" @click="aba = 'pedidos'; carregarPedidos()">🛒 Pedidos</button>
      <button class="tab-btn" :class="{ active: aba === 'alertas' }" @click="aba = 'alertas'; carregarAlertas()">⚠️ Alertas de Vencimento</button>
    </div>

    <!-- TAB 1: PROCESSOS -->
    <div v-if="aba === 'processos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Processos Licitatórios</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalProcesso()">+ Novo Processo</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && processos.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Nº Processo</th>
              <th>Modalidade</th>
              <th>Data Abertura</th>
              <th>Objeto</th>
              <th>Valor Estimado</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(proc, i) in processos" :key="proc.PROCESSO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ proc.PROCESSO_NUMERO }}</strong></td>
              <td><span class="badge badge-purple">{{ proc.PROCESSO_MODALIDADE }}</span></td>
              <td>{{ formatData(proc.PROCESSO_DATA_ABERTURA) }}</td>
              <td class="trunc-text" :title="proc.PROCESSO_OBJETO">{{ proc.PROCESSO_OBJETO }}</td>
              <td>R$ {{ formatMoney(proc.PROCESSO_VALOR_ESTIMADO) }}</td>
              <td><span class="status-badge" :class="proc.PROCESSO_STATUS.toLowerCase()">{{ proc.PROCESSO_STATUS }}</span></td>
              <td class="row-actions">
                <button class="act-btn act-blue" title="Ver detalhes">🔍</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">📭 Nenhum processo encontrado.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 2: CONTRATOS -->
    <div v-if="aba === 'contratos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Contratos Administrativos</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalContrato()">+ Novo Contrato</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && contratos.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Nº Contrato</th>
              <th>Fornecedor</th>
              <th>Objeto</th>
              <th>Valor</th>
              <th>Início</th>
              <th>Fim</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(cnt, i) in contratos" :key="cnt.CONTRATO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ cnt.CONTRATO_NUMERO }}</strong></td>
              <td>{{ cnt.CONTRATO_FORNECEDOR || 'Não informado' }}</td>
              <td class="trunc-text" :title="cnt.CONTRATO_OBJETO">{{ cnt.CONTRATO_OBJETO }}</td>
              <td>R$ {{ formatMoney(cnt.CONTRATO_VALOR) }}</td>
              <td>{{ formatData(cnt.CONTRATO_INICIO) }}</td>
              <td>{{ formatData(cnt.CONTRATO_FIM) }}</td>
              <td><span class="status-badge" :class="cnt.CONTRATO_STATUS.toLowerCase()">{{ cnt.CONTRATO_STATUS }}</span></td>
              <td class="row-actions">
                <button class="act-btn act-blue" title="Ver detalhes">🔍</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">📭 Nenhum contrato encontrado.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 3: PEDIDOS -->
    <div v-if="aba === 'pedidos'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Pedidos de Compra</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalPedido()">+ Solicitar Compra</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && pedidos.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Órgão/Secretaria</th>
              <th>Descrição</th>
              <th>Valor Estimado</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ped, i) in pedidos" :key="ped.PEDIDO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>#{{ ped.PEDIDO_ID }}</td>
              <td>{{ ped.UO_ID ? `Unidade ${ped.UO_ID}` : 'Padrão' }}</td>
              <td class="trunc-text" :title="ped.PEDIDO_DESCRICAO">{{ ped.PEDIDO_DESCRICAO }}</td>
              <td>R$ {{ formatMoney(ped.PEDIDO_VALOR_ESTIMADO) }}</td>
              <td>
                <span class="status-badge" :class="ped.PEDIDO_STATUS.toLowerCase()">{{ ped.PEDIDO_STATUS }}</span>
              </td>
              <td class="row-actions">
                <button v-if="ped.PEDIDO_STATUS === 'SOLICITADO' || ped.PEDIDO_STATUS === 'EM_ANALISE'" 
                        class="act-btn act-green" title="Vincular a Processo" @click="vincularProcesso(ped)">
                  🔗 Vincular
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">📭 Nenhum pedido encontrado.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 4: ALERTAS (VENCENDO) -->
    <div v-if="aba === 'alertas'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Contratos Vencendo (Próximos 60 Dias)</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && alertas.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Nº Contrato</th>
              <th>Objeto</th>
              <th>Vencimento</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(cnt, i) in alertas" :key="cnt.CONTRATO_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ cnt.CONTRATO_NUMERO }}</strong></td>
              <td class="trunc-text">{{ cnt.CONTRATO_OBJETO }}</td>
              <td>{{ formatData(cnt.CONTRATO_FIM) }}</td>
              <td>
                <span :class="urgenciaClass(cnt.CONTRATO_FIM)" class="badge">
                  {{ diasPara(cnt.CONTRATO_FIM) }} dias
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">🎉 Oba! Nenhum contrato vence nos próximos 60 dias.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- MODAL NOVO PROCESSO -->
    <div v-if="modalProcesso" class="modal-overlay" @click.self="fecharModais">
      <div class="modal modal-md">
        <div class="modal-hdr">
          <h3 class="modal-title">Novo Processo Licitatório</h3>
          <button class="modal-close" @click="fecharModais">✕</button>
        </div>
        <div class="modal-body form-grid">
          <div class="form-group">
            <label>Nº do Processo</label>
            <input v-model="formProcesso.processo_numero" class="form-input" placeholder="001/2026" />
          </div>
          <div class="form-group">
            <label>Modalidade</label>
            <select v-model="formProcesso.processo_modalidade" class="form-select">
              <option value="PREGAO_ELETRONICO">Pregão Eletrônico</option>
              <option value="PREGAO_PRESENCIAL">Pregão Presencial</option>
              <option value="DISPENSA">Dispensa</option>
              <option value="INEXIGIBILIDADE">Inexigibilidade</option>
              <option value="CONCORRENCIA">Concorrência</option>
            </select>
          </div>
          <div class="form-group col-full">
            <label>Objeto</label>
            <textarea v-model="formProcesso.processo_objeto" class="form-input" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label>Data de Abertura</label>
            <input type="date" v-model="formProcesso.processo_data_abertura" class="form-input" />
          </div>
          <div class="form-group">
            <label>Valor Estimado</label>
            <input type="number" step="0.01" v-model="formProcesso.processo_valor_estimado" class="form-input" />
          </div>
        </div>
        <div class="modal-ftr">
          <button class="btn btn-secondary" @click="fecharModais">Cancelar</button>
          <button class="btn btn-primary" @click="salvarProcesso" :disabled="isSaving">Salvar Processo</button>
        </div>
      </div>
    </div>
    
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';

const loaded = ref(false);
const aba = ref('processos');
const isLoading = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');

const processos = ref([]);
const contratos = ref([]);
const pedidos = ref([]);
const alertas = ref([]);

const modalProcesso = ref(false);
const formProcesso = ref({
  processo_numero: '', processo_modalidade: 'PREGAO_ELETRONICO',
  processo_objeto: '', processo_data_abertura: '', processo_valor_estimado: ''
});

onMounted(() => {
  setTimeout(() => { loaded.value = true; }, 50);
  carregarProcessos();
});

const formatData = (iso) => {
  if (!iso) return '-';
  const parts = iso.substring(0, 10).split('-');
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

const formatMoney = (val) => {
  if (val == null) return '0,00';
  return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
};

const diasPara = (dtFim) => {
  const diff = new Date(dtFim) - new Date();
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
};

const urgenciaClass = (dtFim) => {
  const d = diasPara(dtFim);
  if (d <= 30) return 'badge-red';
  if (d <= 60) return 'badge-yellow';
  return 'badge-green';
};

const resetErro = () => { errorMsg.value = ''; };

const carregarProcessos = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/compras/processos');
    processos.value = data.processos || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarContratos = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/compras/contratos');
    contratos.value = data.contratos || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarPedidos = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/compras/pedidos');
    pedidos.value = data.pedidos || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarAlertas = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/compras/contratos/vencendo');
    alertas.value = data.contratos || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const abrirModalProcesso = () => {
  formProcesso.value = { processo_numero: '', processo_modalidade: 'PREGAO_ELETRONICO', processo_objeto: '', processo_data_abertura: new Date().toISOString().substring(0, 10), processo_valor_estimado: '' };
  modalProcesso.value = true;
};

const abrirModalContrato = () => { alert("Modal Contrato em construção!"); };
const abrirModalPedido = () => { alert("Modal Pedido em construção!"); };

const fecharModais = () => {
  modalProcesso.value = false;
};

const salvarProcesso = async () => {
  isSaving.value = true; resetErro();
  try {
    await api.post('/compras/processos', formProcesso.value);
    fecharModais();
    carregarProcessos();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || "Erro ao salvar processo";
  } finally {
    isSaving.value = false;
  }
};

const vincularProcesso = (ped) => {
  const proc = prompt(`Vincular pedido #${ped.PEDIDO_ID} ao Processo ID:`);
  if (!proc) return;
  api.patch(`/compras/pedidos/${ped.PEDIDO_ID}/vincular`, { processo_id: proc })
    .then(() => carregarPedidos())
    .catch(e => alert(e.response?.data?.erro || e.message));
};
</script>

<style scoped>
/* Aproveitando base do Design System já injetado via App.vue / styles.css */
.cs-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
.hero { background: linear-gradient(135deg, #1e293b, #0f172a); color: white; padding: 40px; border-radius: 16px; margin-bottom: 30px; position: relative; overflow: hidden; opacity: 0; transform: translateY(20px); transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.hero.loaded { opacity: 1; transform: translateY(0); }
.hero-shapes .hs { position: absolute; border-radius: 50%; opacity: 0.1; }
.hs1 { width: 300px; height: 300px; background: #6366f1; top: -100px; right: -50px; }
.hs2 { width: 200px; height: 200px; background: #3b82f6; bottom: -80px; left: 20%; }
.hero-inner { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
.hero-eyebrow { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #818cf8; font-weight: 600; display: block; margin-bottom: 8px; }
.hero-title { font-size: 32px; font-weight: 700; margin: 0 0 12px 0; color: #f8fafc; }
.hero-sub { font-size: 16px; color: #cbd5e1; max-width: 600px; margin: 0; line-height: 1.5; }

.tabs-bar { display: flex; gap: 12px; margin-bottom: 24px; opacity: 0; transform: translateY(10px); transition: all 0.5s ease 0.2s; }
.tabs-bar.loaded { opacity: 1; transform: translateY(0); }
.tab-btn { background: white; border: 1px solid #e2e8f0; padding: 12px 24px; border-radius: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.tab-btn:hover { background: #f8fafc; color: #0f172a; transform: translateY(-1px); }
.tab-btn.active { background: #3b82f6; border-color: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59,130,246,0.25); }

.section-card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; opacity: 0; transform: translateY(10px); transition: all 0.5s ease; animation: fadeUp 0.5s forwards; }
@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
.section-hdr { padding: 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
.section-title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; }

.btn-novo { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-novo:hover { background: #059669; transform: translateY(-1px); }

.table-scroll { overflow-x: auto; padding: 0 24px 24px; }
.cs-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 16px; }
.cs-table th { text-align: left; padding: 12px 16px; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
.cs-table td { padding: 16px; color: #334155; font-size: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-row { opacity: 0; transform: translateX(-10px); transition: all 0.3s ease; transition-delay: var(--row-delay); }
.data-row.row-visible { opacity: 1; transform: translateX(0); }
.data-row:hover { background: #f8fafc; }

.trunc-text { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge-purple { background: #e0e7ff; color: #4338ca; }
.badge-red { background: #fee2e2; color: #b91c1c; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.badge-yellow { background: #fef3c7; color: #d97706; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.badge-green { background: #d1fae5; color: #059669; font-weight: 600; padding: 4px 8px; border-radius: 4px; }

.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-badge.aberto { background: #e0e7ff; color: #4f46e5; }
.status-badge.vigente { background: #dcfce7; color: #166534; }
.status-badge.solicitado { background: #f3f4f6; color: #4b5563; }
.status-badge.vinculado { background: #dbeafe; color: #1e40af; }

.row-actions { display: flex; gap: 8px; }
.act-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.act-blue { background: #eff6ff; color: #3b82f6; } .act-blue:hover { background: #dbeafe; }
.act-green { background: #ecfdf5; color: #10b981; } .act-green:hover { background: #d1fae5; }

.empty-state { text-align: center; padding: 60px 20px; color: #64748b; font-size: 16px; }
.error-msg { margin: 24px; padding: 16px; background: #fee2e2; color: #b91c1c; border-radius: 8px; font-size: 14px; border: 1px solid #fca5a5; }
.spinner-wrap { padding: 40px; display: flex; justify-content: center; }
.spinner { width: 32px; height: 32px; border: 3px solid #f1f5f9; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Modais */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; animation: fadeIn 0.2s; }
.modal { background: white; border-radius: 16px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: modalUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalUp { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.modal-md { max-width: 600px; }
.modal-hdr { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.modal-title { margin: 0; font-size: 18px; font-weight: 600; color: #0f172a; }
.modal-close { background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; padding: 4px; }
.modal-close:hover { color: #0f172a; }
.modal-body { padding: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.col-full { grid-column: 1 / -1; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-input, .form-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s; font-family: inherit; }
.form-input:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.modal-ftr { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; border: none; }
.btn:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-primary { background: #3b82f6; color: white; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-secondary { background: white; color: #475569; border: 1px solid #cbd5e1; }
.btn-secondary:hover { background: #f1f5f9; }
</style>
