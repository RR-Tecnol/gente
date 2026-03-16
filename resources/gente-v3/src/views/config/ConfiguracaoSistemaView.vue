<template>
  <div class="cfg-page">
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⚙️ Sistema</span>
          <h1 class="hero-title">Configurações do Sistema</h1>
          <p class="hero-sub">Parâmetros gerais do órgão e da aplicação</p>
        </div>
        <div class="hero-actions">
          <button class="save-btn" @click="salvarTodas" :disabled="salvando">
            <span v-if="salvando" class="btn-spin"></span>
            <template v-else>💾 Salvar Alterações</template>
          </button>
        </div>
      </div>
    </div>

    <div v-if="okMsg" class="toast-ok">✅ {{ okMsg }}</div>
    <div v-if="erroMsg" class="toast-err">⚠️ {{ erroMsg }}</div>

    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando configurações...</p></div>

    <template v-else>
      <!-- SEÇÃO: Identificação do Órgão -->
      <div class="cfg-card" :class="{ loaded }">
        <div class="cfg-card-hdr"><span class="cfg-ico">🏛️</span><h2>Identificação do Órgão</h2></div>
        <div class="cfg-grid">
          <div class="fg" v-for="k in secaoOrgao" :key="k">
            <label>{{ label(k) }}</label>
            <input v-if="tipoCfg(k) !== 'BOOLEAN'" v-model="cfg[k]" class="cfg-input" :placeholder="label(k)" />
            <label v-else class="toggle-lbl">
              <input type="checkbox" v-model="cfg[k]" class="toggle-chk" />
              <span class="toggle-track"><span class="toggle-thumb"></span></span>
              <span>{{ cfg[k] ? 'Habilitado' : 'Desabilitado' }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Folha de Pagamento -->
      <div class="cfg-card" :class="{ loaded }" style="--delay: 0.05s">
        <div class="cfg-card-hdr"><span class="cfg-ico">💰</span><h2>Folha de Pagamento</h2></div>
        <div class="cfg-grid">
          <div class="fg" v-for="k in secaoFolha" :key="k">
            <label>{{ label(k) }}</label>
            <input v-if="tipoCfg(k) !== 'BOOLEAN'" v-model="cfg[k]" class="cfg-input" :type="tipoCfg(k) === 'NUMBER' ? 'number' : 'text'" :placeholder="label(k)" />
            <label v-else class="toggle-lbl">
              <input type="checkbox" v-model="cfg[k]" class="toggle-chk" />
              <span class="toggle-track"><span class="toggle-thumb"></span></span>
              <span>{{ cfg[k] ? 'Habilitado' : 'Desabilitado' }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Ponto Eletrônico -->
      <div class="cfg-card" :class="{ loaded }" style="--delay: 0.08s">
        <div class="cfg-card-hdr"><span class="cfg-ico">🕐</span><h2>Ponto Eletrônico</h2></div>
        <div class="cfg-grid">
          <div class="fg" v-for="k in secaoPonto" :key="k">
            <label>{{ label(k) }}</label>
            <input v-if="tipoCfg(k) !== 'BOOLEAN'" v-model="cfg[k]" class="cfg-input" :type="tipoCfg(k) === 'NUMBER' ? 'number' : 'text'" :placeholder="label(k)" />
            <label v-else class="toggle-lbl">
              <input type="checkbox" v-model="cfg[k]" class="toggle-chk" />
              <span class="toggle-track"><span class="toggle-thumb"></span></span>
              <span>{{ cfg[k] ? 'Habilitado' : 'Desabilitado' }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Outras (chaves não mapeadas) -->
      <div v-if="outrasChaves.length" class="cfg-card" :class="{ loaded }" style="--delay: 0.1s">
        <div class="cfg-card-hdr"><span class="cfg-ico">🔧</span><h2>Outras Configurações</h2></div>
        <div class="cfg-grid">
          <div class="fg" v-for="k in outrasChaves" :key="k">
            <label>{{ label(k) }}</label>
            <input v-model="cfg[k]" class="cfg-input" :placeholder="k" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loaded = ref(false); const loading = ref(true)
const salvando = ref(false); const okMsg = ref(''); const erroMsg = ref('')
const cfg = ref({})
const tiposMap = ref({})

const LABELS = {
  'orgao.nome': 'Nome do Órgão', 'orgao.cnpj': 'CNPJ', 'orgao.cnes': 'CNES',
  'orgao.endereco': 'Endereço', 'orgao.cidade': 'Cidade', 'orgao.uf': 'UF',
  'orgao.telefone': 'Telefone', 'orgao.email': 'E-mail institucional',
  'orgao.logo': 'URL do Logotipo', 'orgao.site': 'Site',
  'folha.dia_pagamento': 'Dia de Pagamento', 'folha.competencia_atual': 'Competência Atual',
  'folha.inss_aliquota': 'Alíquota INSS (%)', 'folha.irrf_deducao': 'Dedução IRRF',
  'folha.fgts_aliquota': 'Alíquota FGTS (%)', 'folha.calcular_automatico': 'Calcular automaticamente',
  'ponto.tolerancia_minutos': 'Tolerância (min)', 'ponto.intervalo_almoco': 'Intervalo Almoço (min)',
  'ponto.horas_extras_ativo': 'Hora Extra habilitada', 'ponto.banco_horas_ativo': 'Banco de Horas habilitado',
  'ponto.tipo_registro': 'Tipo de Registro',
}

const secaoOrgao = ['orgao.nome','orgao.cnpj','orgao.cnes','orgao.endereco','orgao.cidade','orgao.uf','orgao.telefone','orgao.email','orgao.logo','orgao.site']
const secaoFolha = ['folha.dia_pagamento','folha.competencia_atual','folha.inss_aliquota','folha.irrf_deducao','folha.fgts_aliquota','folha.calcular_automatico']
const secaoPonto = ['ponto.tolerancia_minutos','ponto.intervalo_almoco','ponto.horas_extras_ativo','ponto.banco_horas_ativo','ponto.tipo_registro']
const todasSecoes = [...secaoOrgao, ...secaoFolha, ...secaoPonto]

const outrasChaves = computed(() => Object.keys(cfg.value).filter(k => !todasSecoes.includes(k)))

const label = (k) => LABELS[k] || k.replace(/[._]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
const tipoCfg = (k) => tiposMap.value[k] || 'STRING'

onMounted(async () => {
  try {
    const { data } = await api.get('/configuracoes/api')
    const mapa = {}; const tipos = {}
    Object.entries(data).forEach(([chave, obj]) => {
      const tipo = obj.CONFIG_TIPO || 'STRING'
      tipos[chave] = tipo
      if (tipo === 'BOOLEAN') mapa[chave] = obj.CONFIG_VALOR === '1'
      else mapa[chave] = obj.CONFIG_VALOR ?? ''
    })
    cfg.value = mapa; tiposMap.value = tipos
  } catch {
    // fallback com campos padrão vazios
    cfg.value = Object.fromEntries([...secaoOrgao, ...secaoFolha, ...secaoPonto].map(k => [k, '']))
  }
  finally { loading.value = false; setTimeout(() => { loaded.value = true }, 80) }
})

const salvarTodas = async () => {
  salvando.value = true; okMsg.value = ''; erroMsg.value = ''
  const erros = []
  for (const [chave, valor] of Object.entries(cfg.value)) {
    try {
      await api.put(`/configuracoes/${chave}`, {
        CONFIG_VALOR: tipoCfg(chave) === 'BOOLEAN' ? (valor ? '1' : '0') : String(valor ?? '')
      })
    } catch { erros.push(chave) }
  }
  salvando.value = false
  if (erros.length === 0) {
    okMsg.value = 'Todas as configurações foram salvas!'; setTimeout(() => okMsg.value = '', 3500)
  } else {
    erroMsg.value = `Erro ao salvar: ${erros.join(', ')}`; setTimeout(() => erroMsg.value = '', 5000)
  }
}
</script>

<style scoped>
.cfg-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { background: linear-gradient(135deg, #0f172a, #1e1040); border-radius: 22px; padding: 26px 32px; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #c4b5fd; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.save-btn { padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; gap: 8px; }
.save-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.4); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.toast-ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: 14px; padding: 12px 18px; font-size: 13px; font-weight: 600; }
.toast-err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 14px; padding: 12px 18px; font-size: 13px; font-weight: 600; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 10px; color: #94a3b8; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.cfg-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 22px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) var(--delay, 0.02s); }
.cfg-card.loaded { opacity: 1; transform: none; }
.cfg-card-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; }
.cfg-ico { font-size: 20px; }
.cfg-card-hdr h2 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.cfg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; transition: border-color 0.15s; }
.cfg-input:focus { border-color: #7c3aed; }
/* toggle */
.toggle-lbl { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 600; color: #475569; text-transform: none; letter-spacing: 0; }
.toggle-chk { display: none; }
.toggle-track { width: 38px; height: 22px; border-radius: 99px; background: #e2e8f0; position: relative; transition: background 0.2s; flex-shrink: 0; }
.toggle-chk:checked + .toggle-track { background: #7c3aed; }
.toggle-thumb { position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #fff; top: 3px; left: 3px; transition: transform 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
.toggle-chk:checked + .toggle-track .toggle-thumb { transform: translateX(16px); }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
</style>
