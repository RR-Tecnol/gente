<template>
  <div class="cs-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1" />
        <div class="hs hs2" />
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏢 ERP Administrativo</span>
          <h1 class="hero-title">Gestão de Patrimônio</h1>
          <p class="hero-sub">Tombo, inventário, movimentações e depreciação automática de bens móveis e imóveis (NBCASP 16.9).</p>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button class="tab-btn" :class="{ active: aba === 'bens' }" @click="aba = 'bens'; carregarBens()">📋 Catálogo de Bens</button>
      <button class="tab-btn" :class="{ active: aba === 'movimentacoes' }" @click="aba = 'movimentacoes'; carregarMovimentos()">📜 Movimentações</button>
      <button class="tab-btn" :class="{ active: aba === 'inventario' }" @click="aba = 'inventario'">🔍 Inventário por UO</button>
      <button class="tab-btn" :class="{ active: aba === 'depreciacao' }" @click="aba = 'depreciacao'; carregarDepreciacao()">📉 Relatório de Depreciação</button>
    </div>

    <!-- TAB 1: BENS -->
    <div v-if="aba === 'bens'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Catálogo Central de Bens</h2>
        <div class="toolbar-right">
          <button class="btn-novo" @click="abrirModalTombo()">+ Tombar Novo Bem</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && bens.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Nº Tombamento</th>
              <th>Descrição</th>
              <th>Categoria</th>
              <th>Status</th>
              <th>Valor Aquisição</th>
              <th>Valor Atual</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(bem, i) in bens" :key="bem.BEM_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ bem.BEM_NUMERO }}</strong></td>
              <td class="trunc-text" :title="bem.BEM_DESCRICAO">{{ bem.BEM_DESCRICAO }}</td>
              <td><span class="badge badge-purple">{{ bem.BEM_CATEGORIA }}</span></td>
              <td>
                <span class="status-badge" :class="bem.BEM_STATUS === 'ATIVO' ? 'vigente' : 'vencendo'">{{ bem.BEM_STATUS }}</span>
              </td>
              <td>R$ {{ formatMoney(bem.BEM_VALOR_AQUISICAO) }}</td>
              <td><strong>R$ {{ formatMoney(bem.BEM_VALOR_ATUAL) }}</strong></td>
              <td class="row-actions">
                <button class="act-btn act-blue" title="Editar" @click="alert('Editar: ' + bem.BEM_ID)">✏️</button>
                <button class="act-btn act-green" title="Transferir UO" @click="transferirBem(bem)">🔗</button>
                <button class="act-btn" style="background:#fee2e2; color:#b91c1c;" title="Dar Baixa" @click="baixarBem(bem)">❌</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">📭 Nenhum bem cadastrado.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 2: MOVIMENTAÇÕES -->
    <div v-if="aba === 'movimentacoes'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Histórico de Movimentações Patrimoniais</h2>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && movimentacoes.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Tipo</th>
              <th>Tombamento</th>
              <th>Motivo / Documento</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(m, i) in movimentacoes" :key="m.MOV_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td>{{ formatDataTime(m.created_at) }}</td>
              <td><span class="badge badge-yellow">{{ m.MOV_TIPO }}</span></td>
              <td><strong>{{ m.BEM_NUMERO }}</strong></td>
              <td class="trunc-text">{{ m.MOV_MOTIVO || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">Nenhuma movimentação registrada.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 3: INVENTÁRIO -->
    <div v-if="aba === 'inventario'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Inventário por Unidade Orçamentária (UO)</h2>
      </div>

      <div class="modal-body form-grid mx-auto" style="max-width: 800px; padding-bottom:0;">
        <div class="form-group col-full" style="display:flex; gap:16px; align-items:flex-end;">
          <div style="flex:1;">
            <label>Selecione a Unidade (UO_ID)</label>
            <input type="number" v-model="inventarioUoId" class="form-input" placeholder="Ex: 5">
          </div>
          <button class="btn btn-primary" @click="carregarInventario">Buscar</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll mt-3" v-if="!isLoading && inventario.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Tombamento</th>
              <th>Descrição</th>
              <th>Categoria</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(bem, i) in inventario" :key="bem.BEM_ID" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ bem.BEM_NUMERO }}</strong></td>
              <td>{{ bem.BEM_DESCRICAO }}</td>
              <td>{{ bem.BEM_CATEGORIA }}</td>
              <td>{{ bem.BEM_ESTADO }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right;"><strong>Total Patrimonial na UO:</strong></td>
              <td><strong>R$ {{ formatMoney(inventarioTotal) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div v-else-if="!isLoading && inventarioBuscaFeita" class="empty-state">Nenhum bem alocado nesta unidade.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- TAB 4: DEPRECIAÇÃO -->
    <div v-if="aba === 'depreciacao'" class="section-card" :class="{ loaded }">
      <div class="section-hdr">
        <h2 class="section-title">Relatório de Depreciação NBCASP 16.9</h2>
        <div class="toolbar-right" style="display:flex; gap:12px;">
          <input type="text" v-model="competenciaDeprec" class="form-input" style="width:120px" placeholder="AAAAMM" maxlength="6">
          <button class="btn-novo" @click="executarDepreciacao" style="background:#f59e0b">Executar Mês</button>
        </div>
      </div>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

      <div class="table-scroll" v-if="!isLoading && relatorioDeprec.length > 0">
        <table class="cs-table">
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Qtd. Bens</th>
              <th>Valor Aquisição</th>
              <th>Depreciação Acumulada</th>
              <th>Valor Patrimonial Líquido Atual</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(rel, i) in relatorioDeprec" :key="rel.BEM_CATEGORIA" class="data-row row-visible" :style="{ '--row-delay': `${i * 30}ms` }">
              <td><strong>{{ rel.BEM_CATEGORIA }}</strong></td>
              <td>{{ rel.qtd }}</td>
              <td>R$ {{ formatMoney(rel.valor_aquisicao) }}</td>
              <td style="color:#b91c1c;">- R$ {{ formatMoney(rel.depreciacao_acumulada) }}</td>
              <td style="color:#166534; font-weight:bold;">R$ {{ formatMoney(rel.valor_atual) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!isLoading" class="empty-state">Sem dados de depreciação.</div>
      <div v-if="isLoading" class="spinner-wrap"><div class="spinner"></div></div>
    </div>

    <!-- MODAL TOMBAR BEM -->
    <div v-if="modalTombo" class="modal-overlay" @click.self="fecharModais">
      <div class="modal modal-md">
        <div class="modal-hdr">
          <h3 class="modal-title">Tombar Novo Bem</h3>
          <button class="modal-close" @click="fecharModais">✕</button>
        </div>
        <div class="modal-body form-grid">
          <div class="form-group">
            <label>Nº Tombamento</label>
            <input v-model="formBem.bem_numero" class="form-input" placeholder="0001/2026" />
          </div>
          <div class="form-group">
            <label>Categoria</label>
            <select v-model="formBem.bem_categoria" class="form-select">
              <option value="EQUIPAMENTO">EQUIPAMENTO</option>
              <option value="MOVEL">MÓVEL</option>
              <option value="TI">TECNOLOGIA (TI)</option>
              <option value="VEICULO">VEÍCULO</option>
              <option value="IMOVEL">IMÓVEL</option>
            </select>
          </div>
          <div class="form-group col-full">
            <label>Descrição Completa</label>
            <textarea v-model="formBem.bem_descricao" class="form-input" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>Valor de Aquisição (R$)</label>
            <input type="number" step="0.01" v-model="formBem.bem_valor_aquisicao" class="form-input" />
          </div>
          <div class="form-group">
            <label>Data de Aquisição</label>
            <input type="date" v-model="formBem.bem_data_aquisicao" class="form-input" />
          </div>
          <div class="form-group">
            <label>Estado de Conservação</label>
            <select v-model="formBem.bem_estado" class="form-select">
              <option value="OTIMO">ÓTIMO</option>
              <option value="BOM">BOM</option>
              <option value="REGULAR">REGULAR</option>
              <option value="RUIM">RUIM</option>
              <option value="INSERVIVEL">INSERVÍVEL</option>
            </select>
          </div>
          <div class="form-group">
            <label>Unidade Destino (Opcional)</label>
            <input type="number" v-model="formBem.uo_id" class="form-input" placeholder="ID da UO" />
          </div>
          
          <div class="col-full mt-3">
             <div style="padding:12px; background:#f1f5f9; border-radius:8px; font-size:12px; color:#475569;">
               ℹ️ Vida Útil e Valor Residual serão calculados automaticamente baseado na categoria, conforme manual NBCASP (Ex: TI = 3 anos, 10% residual).
             </div>
          </div>
        </div>
        <div class="modal-ftr">
          <button class="btn btn-secondary" @click="fecharModais">Cancelar</button>
          <button class="btn btn-primary" style="background:#10b981; border:none;" @click="salvarBem" :disabled="isSaving">Salvar Bem Patrimonial</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';

const loaded = ref(false);
const aba = ref('bens');
const isLoading = ref(false);
const isSaving = ref(false);
const errorMsg = ref('');

const bens = ref([]);
const movimentacoes = ref([]);
const inventario = ref([]);
const inventarioTotal = ref(0);
const inventarioUoId = ref('');
const inventarioBuscaFeita = ref(false);

const relatorioDeprec = ref([]);
const competenciaDeprec = ref('');

const modalTombo = ref(false);
const formBem = ref({ bem_numero: '', bem_descricao: '', bem_categoria: 'EQUIPAMENTO', bem_valor_aquisicao: '', bem_data_aquisicao: '', bem_estado: 'BOM', uo_id: '' });

onMounted(() => {
  setTimeout(() => { loaded.value = true; }, 50);
  const now = new Date();
  const mesAnterior = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  competenciaDeprec.value = `${mesAnterior.getFullYear()}${String(mesAnterior.getMonth() + 1).padStart(2, '0')}`;
  carregarBens();
});

const formatDataTime = (iso) => {
  if (!iso) return '-';
  const d = new Date(iso);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR');
};

const formatMoney = (val) => {
  if (val == null) return '0,00';
  return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
};

const resetErro = () => { errorMsg.value = ''; };

const carregarBens = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/patrimonio/bens');
    bens.value = data.bens || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarMovimentos = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/patrimonio/movimentacoes');
    movimentacoes.value = data.movimentacoes || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarInventario = async () => {
  if (!inventarioUoId.value) return alert('Digite a UO');
  isLoading.value = true; resetErro(); inventarioBuscaFeita.value = true;
  try {
    const { data } = await api.get(`/patrimonio/inventario/${inventarioUoId.value}`);
    inventario.value = data.bens || [];
    inventarioTotal.value = data.valor_total || 0;
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const carregarDepreciacao = async () => {
  isLoading.value = true; resetErro();
  try {
    const { data } = await api.get('/patrimonio/depreciacao');
    relatorioDeprec.value = data.por_categoria || [];
  } catch(e) { errorMsg.value = e.response?.data?.erro || e.message; }
  finally { isLoading.value = false; }
};

const abrirModalTombo = () => {
  formBem.value = { bem_numero: '', bem_descricao: '', bem_categoria: 'EQUIPAMENTO', bem_valor_aquisicao: '', bem_data_aquisicao: new Date().toISOString().substring(0, 10), bem_estado: 'BOM', uo_id: '' };
  modalTombo.value = true;
};

const fecharModais = () => { modalTombo.value = false; };

const salvarBem = async () => {
  isSaving.value = true; resetErro();
  try {
    await api.post('/patrimonio/bens', formBem.value);
    fecharModais();
    carregarBens();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || "Erro ao tombar bem";
  } finally {
    isSaving.value = false;
  }
};

const transferirBem = async (bem) => {
  const dest = prompt(`Transferir Bem #${bem.BEM_NUMERO}\nID da nova UO destino:`);
  if (!dest) return;
  try {
    await api.post(`/patrimonio/bens/${bem.BEM_ID}/transferir`, { uo_destino_id: dest, motivo: 'Transferência manual interface' });
    alert('Bem transferido!');
    carregarBens();
  } catch (e) {
    alert(e.response?.data?.erro || e.message);
  }
};

const baixarBem = async (bem) => {
  const mot = prompt(`BAIXA DO BEM #${bem.BEM_NUMERO}\nDigite o motivo da baixa (ex: Sinistro, Doação):`);
  if (!mot) return;
  try {
    await api.post(`/patrimonio/bens/${bem.BEM_ID}/baixar`, { motivo: mot });
    alert('Bem baixado e inativado no patrimônio!');
    carregarBens();
  } catch (e) {
    alert(e.response?.data?.erro || e.message);
  }
};

const executarDepreciacao = async () => {
  if (competenciaDeprec.value.length !== 6) return alert('Competência em AAAAMM');
  if(!confirm(`Deseja rodar DEPRECIAÇÃO de todos os bens para ${competenciaDeprec.value}? Esta ação atualiza o valor contábil líquido.`)) return;
  
  isSaving.value = true; resetErro();
  try {
    const { data } = await api.post(`/patrimonio/depreciar/${competenciaDeprec.value}`);
    alert(`Sucesso! ${data.bens_depreciados} bens foram depreciados no mês.`);
    carregarDepreciacao();
  } catch(e) {
    errorMsg.value = e.response?.data?.erro || e.message;
  } finally {
    isSaving.value = false;
  }
};
</script>

<style scoped>
.cs-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
.hero { background: linear-gradient(135deg, #1e293b, #0f172a); color: white; padding: 40px; border-radius: 16px; margin-bottom: 30px; position: relative; overflow: hidden; opacity: 0; transform: translateY(20px); transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.hero.loaded { opacity: 1; transform: translateY(0); }
.hero-shapes .hs { position: absolute; border-radius: 50%; opacity: 0.1; }
.hs1 { width: 300px; height: 300px; background: #6366f1; top: -100px; right: -50px; }
.hs2 { width: 200px; height: 200px; background: #3b82f6; bottom: -80px; left: 20%; }
.hero-inner { position: relative; z-index: 2; }
.hero-eyebrow { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #818cf8; font-weight: 600; display: block; margin-bottom: 8px; }
.hero-title { font-size: 32px; font-weight: 700; margin: 0 0 12px 0; color: #f8fafc; }
.hero-sub { font-size: 16px; color: #cbd5e1; max-width: 600px; margin: 0; line-height: 1.5; }

.tabs-bar { display: flex; gap: 12px; margin-bottom: 24px; opacity: 0; transform: translateY(10px); transition: all 0.5s ease 0.2s; }
.tabs-bar.loaded { opacity: 1; transform: translateY(0); }
.tab-btn { background: white; border: 1px solid #e2e8f0; padding: 12px 24px; border-radius: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; }
.tab-btn:hover { background: #f8fafc; color: #0f172a; transform: translateY(-1px); }
.tab-btn.active { background: #6366f1; border-color: #6366f1; color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.25); }

.section-card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; opacity: 0; transform: translateY(10px); transition: all 0.5s ease; animation: fadeUp 0.5s forwards; }
@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
.section-hdr { padding: 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
.section-title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; }

.btn-novo { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-novo:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-novo:hover:not(:disabled) { filter: brightness(0.9); transform: translateY(-1px); }

.table-scroll { overflow-x: auto; padding: 0 24px 24px; }
.cs-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 16px; }
.cs-table th { text-align: left; padding: 12px 16px; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
.cs-table td { padding: 16px; color: #334155; font-size: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.cs-table tfoot td { background: #f8fafc; padding: 16px; border-top: 2px solid #e2e8f0; font-size: 15px; }
.data-row { opacity: 0; transform: translateX(-10px); transition: all 0.3s ease; transition-delay: var(--row-delay); }
.data-row.row-visible { opacity: 1; transform: translateX(0); }
.data-row:hover { background: #f8fafc; }

.trunc-text { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-purple { background: #e0e7ff; color: #4338ca; }
.badge-yellow { background: #fef3c7; color: #d97706; }

.status-badge { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; }
.status-badge.vigente { background: #dcfce7; color: #166534; }
.status-badge.vencendo { background: #fee2e2; color: #b91c1c; }

.empty-state { text-align: center; padding: 60px 20px; color: #64748b; font-size: 16px; }
.error-msg { margin: 24px; padding: 16px; background: #fee2e2; color: #b91c1c; border-radius: 8px; font-size: 14px; border: 1px solid #fca5a5; }
.spinner-wrap { padding: 40px; display: flex; justify-content: center; }
.spinner { width: 32px; height: 32px; border: 3px solid #f1f5f9; border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Ações */
.row-actions { display: flex; gap: 8px; }
.act-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; background: #f1f5f9; }
.act-btn:hover { filter: brightness(0.9); transform: translateY(-2px); }

/* Formulários */
.mx-auto { margin-left: auto; margin-right: auto; }
.mt-3 { margin-top: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 24px; }
.col-full { grid-column: 1 / -1; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-input, .form-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.2s; }
.form-input:focus, .form-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-hdr { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
.modal-title { margin: 0; font-size: 18px; color: #0f172a; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
.modal-ftr { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; }
.btn-primary { background: #6366f1; color: white; }
.btn-secondary { background: white; border: 1px solid #cbd5e1; color: #475569; }
</style>
