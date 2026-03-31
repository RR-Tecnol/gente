<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" />
        <div class="hs hs2" />
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📦 ERP Administrativo</span>
          <h1 class="hero-title">Almoxarifado & Estoque</h1>
          <p class="hero-sub">Gestão de itens, controle de saldo e movimentações de entrada e saída por secretaria.</p>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'itens' }" @click="aba = 'itens'; carregarItens()">📋 Catálogo de Itens</button>
      <button class="tab-btn" :class="{ active: aba === 'entrada' }" @click="aba = 'entrada'; preCarregarSelects()">📥 Entrada de Material</button>
      <button class="tab-btn" :class="{ active: aba === 'saida' }" @click="aba = 'saida'; preCarregarSelects()">📤 Saída de Material</button>
      <button class="tab-btn" :class="{ active: aba === 'saldo' }" @click="aba = 'saldo'; carregarMovimentos()">📊 Movimentações</button>
      <button class="tab-btn" :class="{ active: aba === 'alertas' }" @click="aba = 'alertas'; carregarMinimo()">⚠️ Alertas Mínimo</button>
    </div>

    <!-- TAB 1: ITENS -->
    <div v-if="aba === 'itens'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Catálogo de Itens e Saldo Consolidado</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalItem()">+ Cadastrar Item</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && itens.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Descrição</th>
              <th>Categoria</th>
              <th>Unid.</th>
              <th>Mínimo</th>
              <th>Saldo Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in itens" :key="item.ITEM_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ item.ITEM_CODIGO }}</strong></td>
              <td class="trunc-text" :title="item.ITEM_DESCRICAO">{{ item.ITEM_DESCRICAO }}</td>
              <td>{{ item.ITEM_CATEGORIA || '-' }}</td>
              <td><span class="badge badge-purple">{{ item.ITEM_UNIDADE }}</span></td>
              <td>{{ item.ITEM_ESTOQUE_MINIMO }}</td>
              <td>
                <span class="status-badge" :class="Number(item.saldo_total) <= Number(item.ITEM_ESTOQUE_MINIMO) ? 'vencendo' : 'vigente'">
                  {{ item.saldo_total }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">📭 Nenhum item no catálogo.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 2: ENTRADA -->
    <div v-if="aba === 'entrada'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Registrar Entrada de Material</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="modal-body form-grid mx-auto" style="max-width: 800px;">
        <div class="form-group col-full">
          <label>Almoxarifado</label>
          <select v-model="formEntrada.almox_id" class="form-select">
            <option value="">-- Selecione o Almoxarifado --</option>
            <option v-for="a in almoxarifados" :key="a.ALMOX_ID" :value="a.ALMOX_ID">{{ a.ALMOX_NOME }}</option>
          </select>
        </div>
        <div class="form-group col-full">
          <label>Item / Produto</label>
          <select v-model="formEntrada.item_id" class="form-select">
            <option value="">-- Selecione o Item --</option>
            <option v-for="i in itens" :key="i.ITEM_ID" :value="i.ITEM_ID">{{ i.ITEM_CODIGO }} - {{ i.ITEM_DESCRICAO }} ({{ i.ITEM_UNIDADE }})</option>
          </select>
        </div>
        <div class="form-group">
          <label>Quantidade Recebida</label>
          <input type="number" min="1" v-model="formEntrada.mov_quantidade" class="form-input" />
        </div>
        <div class="form-group">
          <label>Valor Unitário (R$)</label>
          <input type="number" step="0.01" v-model="formEntrada.mov_valor_unitario" class="form-input" />
        </div>
        <div class="form-group col-full">
          <label>Documento Referência (NF, Processo)</label>
          <input type="text" v-model="formEntrada.mov_documento" class="form-input" />
        </div>
        <div class="col-full mt-3">
          <button class="btn-novo w-100" @click="salvarEntrada" :disabled="isSaving || !formEntrada.item_id || !formEntrada.almox_id">Registrar Entrada</button>
        </div>
      </div>
    </div>

    <!-- TAB 3: SAÍDA -->
    <div v-if="aba === 'saida'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Registrar Saída (Requisição/Entrega)</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="modal-body form-grid mx-auto" style="max-width: 800px;">
        <div class="form-group col-full">
          <label>Almoxarifado de Origem</label>
          <select v-model="formSaida.almox_id" class="form-select">
            <option value="">-- Selecione --</option>
            <option v-for="a in almoxarifados" :key="a.ALMOX_ID" :value="a.ALMOX_ID">{{ a.ALMOX_NOME }}</option>
          </select>
        </div>
        <div class="form-group col-full">
          <label>Item a retirar</label>
          <select v-model="formSaida.item_id" class="form-select">
            <option value="">-- Selecione o Item --</option>
            <option v-for="i in itens" :key="i.ITEM_ID" :value="i.ITEM_ID">{{ i.ITEM_CODIGO }} - {{ i.ITEM_DESCRICAO }} (Qtde: {{i.saldo_total}})</option>
          </select>
        </div>
        <div class="form-group">
          <label>Quantidade a Retirar</label>
          <input type="number" min="1" v-model="formSaida.mov_quantidade" class="form-input" />
        </div>
        <div class="form-group">
          <label>Secretaria/Serviço Destino</label>
          <input type="text" v-model="formSaida.mov_obs" class="form-input" placeholder="Ex: GABINETE" />
        </div>
        <div class="form-group col-full">
          <label>Documento/Requisição</label>
          <input type="text" v-model="formSaida.mov_documento" class="form-input" />
        </div>
        <div class="col-full mt-3">
          <button class="btn btn-secondary w-100" style="background:#ef4444; color:white; border:none;" 
                  @click="salvarSaida" :disabled="isSaving || !formSaida.item_id || !formSaida.almox_id">Registrar Saída</button>
        </div>
      </div>
    </div>

    <!-- TAB 4: MOVIMENTAÇÕES -->
    <div v-if="aba === 'saldo'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Histórico de Movimentações (Entradas/Saídas)</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && movimentacoes.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Data/Hora</th>
              <th>Tipo</th>
              <th>Almoxarifado</th>
              <th>Item</th>
              <th>Qtd</th>
              <th>Doc/Obs</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(m, i) in movimentacoes" :key="m.MOV_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>{{ formatDataTime(m.created_at) }}</td>
              <td>
                <span :class="m.MOV_TIPO === 'ENTRADA' ? 'badge-green badge' : 'badge-red badge'">{{ m.MOV_TIPO }}</span>
              </td>
              <td>{{ m.ALMOX_NOME }}</td>
              <td class="trunc-text" :title="m.ITEM_DESCRICAO">{{ m.ITEM_DESCRICAO }}</td>
              <td><strong>{{ m.MOV_QUANTIDADE }} {{ m.ITEM_UNIDADE }}</strong></td>
              <td>{{ m.MOV_DOCUMENTO || m.MOV_OBS || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">Nenhuma movimentação registrada.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 5: ALERTAS -->
    <div v-if="aba === 'alertas'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Abaixo do Estoque Mínimo</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && abaixoMin.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Descrição</th>
              <th>Estoque Mínimo</th>
              <th>Saldo Atual</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in abaixoMin" :key="item.ITEM_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ item.ITEM_CODIGO }}</strong></td>
              <td class="trunc-text">{{ item.ITEM_DESCRICAO }}</td>
              <td>{{ item.ITEM_ESTOQUE_MINIMO }}</td>
              <td><strong style="color:#b91c1c;">{{ item.saldo_total }}</strong></td>
              <td><span class="badge badge-red">Crítico</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">🎉 Oba! Nenhum item com estoque abaixo do mínimo.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- MODAL NOVO ITEM -->
    <div v-if="modalItem" class="modal-overlay" @click.self="fecharModais">
      <div class="modal modal-md">
        <div class="modal-hdr">
          <h3 class="modal-title">Novo Item de Catálogo</h3>
          <button class="modal-close" @click="fecharModais">✕</button>
        </div>
        <div class="modal-body form-grid">
          <div class="form-group col-full">
            <label>Código do Item (SKU)</label>
            <input v-model="formItem.item_codigo" class="form-input" placeholder="Ex: MAT-1025" />
          </div>
          <div class="form-group col-full">
            <label>Descrição Completa</label>
            <textarea v-model="formItem.item_descricao" class="form-input" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>Unidade de Medida</label>
            <select v-model="formItem.item_unidade" class="form-select">
              <option value="UN">UN - Unidade</option>
              <option value="CX">CX - Caixa</option>
              <option value="PCT">PCT - Pacote</option>
              <option value="KG">KG - Quilograma</option>
              <option value="L">L - Litro</option>
              <option value="RESMA">RESMA - Resma</option>
            </select>
          </div>
          <div class="form-group">
            <label>Categoria</label>
            <input v-model="formItem.item_categoria" class="form-input" placeholder="Ex: Expediente" />
          </div>
          <div class="form-group">
            <label>Estoque Mínimo de Segurança</label>
            <input type="number" min="0" v-model="formItem.item_estoque_minimo" class="form-input" />
          </div>
        </div>
        <div class="modal-ftr">
          <button class="btn btn-secondary" @click="fecharModais">Cancelar</button>
          <button class="btn btn-primary" @click="salvarItem" :disabled="isSaving">Salvar Item</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';

const loaded = ref(false);
const aba = ref('itens');
const isLoading = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');

const itens = ref([]);
const almoxarifados = ref([]);
const movimentacoes = ref([]);
const abaixoMin = ref([]);

const modalItem = ref(false);
const formItem = ref({ item_codigo: '', item_descricao: '', item_unidade: 'UN', item_categoria: '', item_estoque_minimo: 0 });
const formEntrada = ref({ almox_id: '', item_id: '', mov_quantidade: 1, mov_valor_unitario: '', mov_documento: '' });
const formSaida = ref({ almox_id: '', item_id: '', mov_quantidade: 1, mov_documento: '', mov_obs: '' });

onMounted(() => {
  setTimeout(() => { loaded.value = true; }, 50);
  carregarItens();
  carregarAlmoxarifados();
});

const formatDataTime = (iso) => {
  if (!iso) return '-';
  const d = new Date(iso);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR');
};

const resetErro = () => { errorMsg.value = ''; };

const carregarItens = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/almoxarifado/itens');
    itens.value = data.itens || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarAlmoxarifados = async () => {
  try {
    const { data } = await api.get('/almoxarifado/lista'); // Endpoint fallback que vou criar no backend pro select
    almoxarifados.value = data.almoxarifados || [{ ALMOX_ID: 1, ALMOX_NOME: 'Almoxarifado Central (Padrão)' }];
  } catch (e) {
    // se rota nao existir, insere default manual p teste
    almoxarifados.value = [{ ALMOX_ID: 1, ALMOX_NOME: 'Almoxarifado Central (Sede)' }];
  }
};

const preCarregarSelects = () => {
  if (itens.value.length === 0) carregarItens();
  if (almoxarifados.value.length === 0) carregarAlmoxarifados();
};

const carregarMovimentos = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/almoxarifado/movimentacoes');
    movimentacoes.value = data.movimentacoes || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarMinimo = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/almoxarifado/abaixo-minimo');
    abaixoMin.value = data.itens || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const abrirModalItem = () => {
  formItem.value = { item_codigo: '', item_descricao: '', item_unidade: 'UN', item_categoria: '', item_estoque_minimo: 0 };
  modalItem.value = true;
};

const fecharModais = () => { modalItem.value = false; };

const salvarItem = async () => {
  isSaving.value = true; resetErro();
  try {
    await api.post('/almoxarifado/itens', formItem.value);
    fecharModais();
    carregarItens();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || "Erro ao salvar item";
  } finally {
    isSaving.value = false;
  }
};

const salvarEntrada = async () => {
  isSaving.value = true; resetErro();
  try {
    await api.post('/almoxarifado/entrada', formEntrada.value);
    formEntrada.value = { almox_id: '', item_id: '', mov_quantidade: 1, mov_valor_unitario: '', mov_documento: '' };
    alert('Entrada registrada com sucesso!');
    carregarItens();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || "Erro ao registrar entrada";
  } finally {
    isSaving.value = false;
  }
};

const salvarSaida = async () => {
  isSaving.value = true; resetErro();
  try {
    await api.post('/almoxarifado/saida', formSaida.value);
    formSaida.value = { almox_id: '', item_id: '', mov_quantidade: 1, mov_documento: '', mov_obs: '' };
    alert('Saída registrada com sucesso!');
    carregarItens();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || "Erro ao registrar saída";
  } finally {
    isSaving.value = false;
  }
};
</script>

<style scoped>
.cs-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
.hero { background: linear-gradient(135deg, #0f172a, #334155); color: white; padding: 40px; border-radius: 16px; margin-bottom: 30px; position: relative; overflow: hidden; opacity: 0; transform: translateY(20px); transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.hero.loaded { opacity: 1; transform: translateY(0); }
.hero-shapes .hs { position: absolute; border-radius: 50%; opacity: 0.1; }
.hs1 { width: 300px; height: 300px; background: #fbbf24; top: -100px; right: -50px; }
.hs2 { width: 200px; height: 200px; background: #f59e0b; bottom: -80px; left: 20%; }
.hero-inner { position: relative; z-index: 2; }
.hero-eyebrow { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #fbbf24; font-weight: 600; display: block; margin-bottom: 8px; }
.hero-title { font-size: 32px; font-weight: 700; margin: 0 0 12px 0; color: #f8fafc; }
.hero-sub { font-size: 16px; color: #cbd5e1; max-width: 600px; margin: 0; line-height: 1.5; }

.tabs-bar { display: flex; gap: 12px; margin-bottom: 24px; opacity: 0; transform: translateY(10px); transition: all 0.5s ease 0.2s; }
.tabs-bar.loaded { opacity: 1; transform: translateY(0); }
.tab-btn { background: white; border: 1px solid #e2e8f0; padding: 12px 24px; border-radius: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; }
.tab-btn:hover { background: #f8fafc; color: #0f172a; transform: translateY(-1px); }
.tab-btn.active { background: #f59e0b; border-color: #f59e0b; color: white; box-shadow: 0 4px 12px rgba(245,158,11,0.25); }

.section-card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; opacity: 0; transform: translateY(10px); transition: all 0.5s ease; animation: fadeUp 0.5s forwards; }
@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
.section-hdr { padding: 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
.section-title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; }

.btn-novo { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-novo:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-novo:hover:not(:disabled) { background: #059669; transform: translateY(-1px); }

.table-scroll { overflow-x: auto; padding: 0 24px 24px; }
.cs-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 16px; }
.cs-table th { text-align: left; padding: 12px 16px; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
.cs-table td { padding: 16px; color: #334155; font-size: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-row { opacity: 0; transform: translateX(-10px); transition: all 0.3s ease; transition-delay: var(--row-delay); }
.data-row.row-visible { opacity: 1; transform: translateX(0); }
.data-row:hover { background: #f8fafc; }

.trunc-text { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-purple { background: #e0e7ff; color: #4338ca; }
.badge-red { background: #fee2e2; color: #b91c1c; }
.badge-green { background: #d1fae5; color: #059669; }

.status-badge { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; }
.status-badge.vigente { background: #dcfce7; color: #166534; }
.status-badge.vencendo { background: #fee2e2; color: #b91c1c; }

.empty-state { text-align: center; padding: 60px 20px; color: #64748b; font-size: 16px; }
.error-msg { margin: 24px; padding: 16px; background: #fee2e2; color: #b91c1c; border-radius: 8px; font-size: 14px; border: 1px solid #fca5a5; }
.spinner-wrap { padding: 40px; display: flex; justify-content: center; }
.spinner { width: 32px; height: 32px; border: 3px solid #f1f5f9; border-top-color: #f59e0b; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Formulários */
.mx-auto { margin-left: auto; margin-right: auto; }
.mt-3 { margin-top: 24px; }
.w-100 { width: 100%; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 24px; }
.col-full { grid-column: 1 / -1; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-input, .form-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.2s; }
.form-input:focus, .form-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.1); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-hdr { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
.modal-title { margin: 0; font-size: 18px; color: #0f172a; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
.modal-ftr { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; }
.btn-primary { background: #f59e0b; color: white; }
.btn-secondary { background: white; border: 1px solid #cbd5e1; color: #475569; }
</style>
