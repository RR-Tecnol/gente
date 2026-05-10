<template>
  <div class="folha-page">
    
    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes">
        <div class="hs hs1"></div><div class="hs hs2"></div><div class="hs hs3"></div>
      </div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">💼 Financeiro</span>
          <h1 class="hero-title">13º Salário</h1>
          <p class="hero-sub">Cálculo e processamento das parcelas do 13º salário</p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-strip">
          <div class="kpi-card purple">
            <span class="kpi-label">Ano de Referência</span>
            <span class="kpi-val">{{ calcAno }}</span>
          </div>
          <div class="kpi-card blue">
            <span class="kpi-label">Parcela</span>
            <span class="kpi-val">{{ activeTab === 'DECIMO_TERCEIRO_1' ? '1ª Parcela' : (activeTab === 'DECIMO_TERCEIRO_2' ? '2ª Parcela' : 'Rescisório') }}</span>
          </div>
          <div class="kpi-card green" v-if="amostraData && amostraData.amostra.length > 0">
            <span class="kpi-label">Amostra ({{ amostraData.amostra.length }})</span>
            <span class="kpi-val">{{ formatMoney(amostraData.total_estimado_amostra) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-wrap" :class="{ loaded }">
      <button 
        class="tab-btn" 
        :class="{ active: activeTab === 'DECIMO_TERCEIRO_1' }"
        @click="mudarTab('DECIMO_TERCEIRO_1')"
      >
        1ª Parcela
      </button>
      <button 
        class="tab-btn" 
        :class="{ active: activeTab === 'DECIMO_TERCEIRO_2' }"
        @click="mudarTab('DECIMO_TERCEIRO_2')"
      >
        2ª Parcela
      </button>
      <button 
        class="tab-btn" 
        :class="{ active: activeTab === 'RESCISORIO' }"
        @click="mudarTab('RESCISORIO')"
      >
        Rescisório (Info)
      </button>
    </div>

    <!-- ALERTAS LEGAIS -->
    <div class="alertas-legais" :class="{ loaded }">
      <div class="alerta-item" v-if="activeTab === 'DECIMO_TERCEIRO_1'">
        <span class="alerta-icon">🔴</span>
        <span class="alerta-text"><strong>1ª Parcela:</strong> Pagar entre 1º de fevereiro e 30 de novembro. (Lei 4.749/65)</span>
      </div>
      <div class="alerta-item" v-if="activeTab === 'DECIMO_TERCEIRO_2'">
        <span class="alerta-icon">🔴</span>
        <span class="alerta-text"><strong>2ª Parcela:</strong> Pagar até 20 de dezembro. Deduz o adiantamento da 1ª parcela.</span>
      </div>
      <div class="alerta-item">
        <span class="alerta-icon">📋</span>
        <span class="alerta-text"><strong>Incidências:</strong> 1ª parcela — sem INSS e sem IRRF. 2ª parcela e rescisório — INSS + IRRF.</span>
      </div>
    </div>

    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Gerando prévia da amostra...</p>
    </div>

    <!-- CARD PREVIEW -->
    <div class="section-card" :class="{ loaded }" v-else-if="activeTab !== 'RESCISORIO'">
      <div class="section-hdr">
        <h2 class="section-title">📊 Prévia de Processamento (Amostra de até 10 servidores)</h2>
        <div class="toolbar-right">
          <input v-model="calcAno" type="number" min="2020" max="2099" class="cfg-input ano-input" @change="carregarAmostra" />
          <button class="calc-btn" @click="processar" :disabled="processando">
            <span v-if="processando" class="btn-spin"></span>
            <template v-else>🚀 Processar 13º — {{ calcAno }} {{ activeTab === 'DECIMO_TERCEIRO_1' ? '1ª P' : '2ª P' }}</template>
          </button>
        </div>
      </div>
      
      <p class="preview-warning">⚠️ Esta é apenas uma prévia. O processamento real irá calcular o valor para todos os servidores ativos.</p>

      <div class="table-scroll" v-if="amostraData && amostraData.amostra.length > 0">
        <table class="folha-table">
          <thead>
            <tr>
              <th>Servidor (ID)</th>
              <th>Venc. Base</th>
              <th v-if="activeTab === 'DECIMO_TERCEIRO_2'">Adiantamento (1ª P)</th>
              <th>Valor Bruto</th>
              <th>INSS</th>
              <th>IRRF</th>
              <th>Líquido</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in amostraData.amostra" :key="item.funcionario_id" class="f-row row-in">
              <td><strong>#{{ item.funcionario_id }}</strong></td>
              <td class="money">{{ formatMoney(item.venc_base) }}</td>
              <td class="money orange" v-if="activeTab === 'DECIMO_TERCEIRO_2'">{{ formatMoney(item.adiantamento) }}</td>
              <td class="money dark">{{ formatMoney(item.valor_bruto) }}</td>
              <td class="money red">{{ formatMoney(item.valor_inss) }}</td>
              <td class="money red">{{ formatMoney(item.valor_irrf) }}</td>
              <td class="money green">{{ formatMoney(item.valor_liquido) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-td" style="padding: 40px">
        <span>📭</span> Nenhuma amostra gerada para o ano selecionado.
      </div>
    </div>

    <!-- TAB RESCISÓRIO INFO -->
    <div class="section-card" :class="{ loaded }" v-else>
      <div class="section-hdr">
        <h2 class="section-title">🛑 Rescisório (Informativo)</h2>
      </div>
      <p style="color: #475569; line-height: 1.6; padding: 20px;">
        O cálculo do 13º Rescisório é feito de forma individual no momento do desligamento do servidor.
        <br><br>
        Esta rotina está integrada à funcionalidade de <strong>Exoneração/Desligamento</strong>, não sendo processada em lote por esta tela.
      </p>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()

const loaded = ref(false)
const loading = ref(false)
const processando = ref(false)
const activeTab = ref('DECIMO_TERCEIRO_1')
const calcAno = ref(new Date().getFullYear())
const amostraData = ref(null)

const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)

const mudarTab = (tab) => {
  activeTab.value = tab
  if (tab !== 'RESCISORIO') {
    carregarAmostra()
  }
}

const carregarAmostra = async () => {
  if (activeTab.value === 'RESCISORIO') return
  
  loading.value = true
  amostraData.value = null
  
  try {
    const { data } = await api.get(`/api/v3/decimo-terceiro/preview/${activeTab.value}/${calcAno.value}`)
    amostraData.value = data
  } catch (err) {
    console.error(err)
    alert('Erro ao carregar prévia: ' + (err.response?.data?.erro || err.message))
  } finally {
    loading.value = false
  }
}

const processar = async () => {
  const lbl = activeTab.value === 'DECIMO_TERCEIRO_1' ? '1ª Parcela' : '2ª Parcela'
  if (!confirm(`Deseja realmente processar a ${lbl} do 13º Salário para o ano de ${calcAno.value}?\n\nIsso irá gerar ou atualizar uma Folha no sistema.`)) {
    return
  }

  processando.value = true
  try {
    const { data } = await api.post('/api/v3/decimo-terceiro/calcular', {
      tipo: activeTab.value,
      ano: calcAno.value
    })
    
    alert(`✅ 13º processado com sucesso!\n\nServidores: ${data.servidores}\nTotal Líquido: ${formatMoney(data.total_liquido)}`)
    router.push('/folha-pagamento')
  } catch (err) {
    alert('Erro ao processar: ' + (err.response?.data?.erro || err.message))
  } finally {
    processando.value = false
  }
}

onMounted(() => {
  carregarAmostra().then(() => {
    setTimeout(() => { loaded.value = true }, 80)
  })
})
</script>

<style scoped>
.folha-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero {
  position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #2c1654 50%, #1e3a5f 100%);
  opacity: 0; transform: translateY(-10px);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.13; }
.hs1 { width: 240px; height: 240px; background: #7c3aed; right: -50px; top: -70px; }
.hs2 { width: 180px; height: 180px; background: #10b981; bottom: -50px; right: 250px; }
.hs3 { width: 160px; height: 160px; background: #3b82f6; left: 35%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 6px; }
.hero-title { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.kpi-strip { display: flex; gap: 10px; flex-wrap: wrap; }
.kpi-card { padding: 12px 16px; border-radius: 14px; min-width: 90px; text-align: right; border: 1px solid; }
.kpi-label { display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
.kpi-val { display: block; font-size: 16px; font-weight: 900; }
.kpi-card.purple { background: rgba(124,58,237,0.15); border-color: rgba(124,58,237,0.3); color: #c4b5fd; }
.kpi-card.green { background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.25); color: #6ee7b7; }
.kpi-card.red { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.25); color: #fca5a5; }
.kpi-card.blue { background: rgba(59,130,246,0.12); border-color: rgba(59,130,246,0.25); color: #93c5fd; }

/* TABS */
.tabs-wrap { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; opacity: 0; transform: translateY(10px); transition: all 0.4s ease 0.1s; }
.tabs-wrap.loaded { opacity: 1; transform: none; }
.tab-btn { background: none; border: none; padding: 12px 24px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
.tab-btn:hover { color: #3b82f6; }
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }

/* ALERTAS */
.alertas-legais { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(10px); transition: all 0.4s ease 0.2s; }
.alertas-legais.loaded { opacity: 1; transform: none; }
.alerta-item { display: flex; align-items: center; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; }
.alerta-icon { font-size: 18px; }
.alerta-text { font-size: 13px; color: #334155; }
.alerta-text strong { color: #0f172a; }

/* LOADING */
.state-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px; background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* SECTION CARD */
.section-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; opacity: 0; transform: translateY(10px); transition: all 0.4s ease 0.3s; }
.section-card.loaded { opacity: 1; transform: none; }
.section-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
.section-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; }
.toolbar-right { display: flex; gap: 12px; align-items: center; }

.cfg-input { border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 12px; font-size: 14px; outline: none; }
.ano-input { width: 100px; text-align: center; }
.calc-btn { background: #7c3aed; color: #fff; border: none; border-radius: 10px; padding: 8px 20px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; min-width: 160px; }
.calc-btn:hover:not(:disabled) { background: #6d28d9; transform: translateY(-1px); }
.calc-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }

.preview-warning { background: #fffbeb; color: #b45309; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin: 0 0 16px 0; border: 1px solid #fde68a; }

/* TABELA */
.table-scroll { overflow-x: auto; }
.folha-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.folha-table th { background: #f8fafc; padding: 12px 16px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; text-align: left; border-bottom: 1px solid #e2e8f0; }
.folha-table th:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
.folha-table th:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
.folha-table td { padding: 14px 16px; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9; }
.money { font-family: 'Inter', monospace; font-weight: 600; white-space: nowrap; }
.money.green { color: #15803d; }
.money.red { color: #dc2626; }
.money.orange { color: #ea580c; }
.money.dark { color: #1e293b; font-weight: 700; }
.empty-td { text-align: center; color: #64748b; font-size: 14px; font-weight: 500; }
</style>
