# BRIEFING — Redesign Visual: FrotasView.vue
**Executor:** Antygravity  
**Autoridade de design:** `docs/DESIGN_SYSTEM_GENTE_V3.md`  
**Arquivo alvo:** `resources/gente-v3/src/views/administrativo/FrotasView.vue`  
**Escopo:** SOMENTE visual/template. Toda a lógica Vue (script setup, refs, apis, watchers, modais) é preservada sem alteração.

---

## Regra zero

Não alterar NADA no `<script setup>`. Reescrever apenas o `<template>`. Todas as variáveis, métodos, watchers, reactive objects e imports já existem e estão corretos. O `statusColors` já mapeado no script **não será usado para classes Tailwind** — os badges de status serão implementados com as classes `gv3-badge-*` definidas abaixo.

---

## Estrutura obrigatória do template

```
[WRAPPER ROOT]   → background #f1f5f9, font Inter
[HERO]           → gradiente padrão, eyebrow, 4 chips de KPI, btn "Cadastrar Veículo"
[KPI GRID]       → 4 cards: Total, Em Uso, Em Manutenção, Alertas 7D
[PANEL]          → filter tabs + conteúdo das 4 tabs
[MODAIS]         → Cadastro Veículo, Retorno, Manutenção — visual atualizado
```

Wrapper raiz:
```html
<template>
  <div style="background:#f1f5f9; font-family:'Inter',system-ui,sans-serif; min-height:100vh;">
    <!-- conteúdo -->
  </div>
</template>
```

---

## 1. HERO

```html
<div class="gv3-hero">
  <div class="gv3-hero-shapes">
    <div class="gv3-hs gv3-hs1"></div>
    <div class="gv3-hs gv3-hs2"></div>
  </div>
  <div class="gv3-hero-inner">
    <div>
      <span class="gv3-eyebrow">ERP Administrativo</span>
      <h1 class="gv3-hero-title">Gestão de Frotas</h1>
      <p class="gv3-hero-sub">Controle de veículos, saídas, manutenções e alertas</p>
    </div>
    <div class="gv3-hero-right">
      <div class="gv3-chip">
        <span class="gv3-chip-dot green"></span>
        <strong>{{ veiculos.length }}</strong>&nbsp;veículos cadastrados
      </div>
      <div class="gv3-chip">
        <span class="gv3-chip-dot amber"></span>
        <strong>{{ saidasAbertas.length }}</strong>&nbsp;em circulação
      </div>
      <div class="gv3-chip">
        <span class="gv3-chip-dot red"></span>
        <strong>{{ alertasManutencao.length }}</strong>&nbsp;alertas de manutenção
      </div>
      <button class="gv3-btn-novo" @click="abrirCadastroVeiculo">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="7" y1="1" x2="7" y2="13"/><line x1="1" y1="7" x2="13" y2="7"/></svg>
        Cadastrar veículo
      </button>
    </div>
  </div>
</div>
```

**CSS do hero — igual ao padrão, com hs2 azul para módulo administrativo de operações:**
```css
.gv3-hero {
  margin: 16px;
  border-radius: 24px;
  overflow: hidden;
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #312e81 100%);
  padding: 28px 32px;
}
.gv3-hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.gv3-hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.gv3-hs1 { width: 280px; height: 280px; background: #6366f1; top: -90px; right: -60px; }
.gv3-hs2 { width: 180px; height: 180px; background: #3b82f6; bottom: -50px; right: 260px; }
.gv3-hero-inner {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.gv3-eyebrow {
  display: block;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: #a78bfa; margin-bottom: 6px;
}
.gv3-hero-title { font-size: 26px; font-weight: 900; color: #fff; line-height: 1.1; margin: 0 0 6px; }
.gv3-hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.gv3-hero-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.gv3-chip {
  display: flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px; padding: 8px 14px;
  font-size: 13px; color: #e2e8f0; white-space: nowrap;
}
.gv3-chip strong { color: #fff; font-weight: 700; }
.gv3-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.gv3-chip-dot.green { background: #10b981; }
.gv3-chip-dot.amber { background: #f59e0b; }
.gv3-chip-dot.red   { background: #f43f5e; }
.gv3-btn-novo {
  display: flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff; border: none; border-radius: 14px;
  padding: 10px 18px; font-size: 14px; font-weight: 700;
  cursor: pointer; box-shadow: 0 4px 16px rgba(99,102,241,0.4);
  font-family: inherit; transition: all 0.2s;
}
.gv3-btn-novo:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,102,241,0.5); }
```

---

## 2. KPI GRID (4 cards)

```html
<div class="gv3-kpi-grid">

  <div class="gv3-kpi-card" style="--kc:#6366f1">
    <div class="gv3-kpi-label">Total da frota</div>
    <div class="gv3-kpi-value">{{ veiculos.length }}</div>
    <div class="gv3-kpi-sub">veículos cadastrados</div>
  </div>

  <div class="gv3-kpi-card" style="--kc:#f59e0b">
    <div class="gv3-kpi-label">Em uso</div>
    <div class="gv3-kpi-value" style="color:#d97706">
      {{ veiculos.filter(v => v.VEICULO_STATUS === 'EM_USO').length }}
    </div>
    <div class="gv3-kpi-sub">veículos em circulação agora</div>
  </div>

  <div class="gv3-kpi-card" style="--kc:#f43f5e">
    <div class="gv3-kpi-label">Em manutenção</div>
    <div class="gv3-kpi-value" style="color:#dc2626">
      {{ veiculos.filter(v => v.VEICULO_STATUS === 'EM_MANUTENCAO').length }}
    </div>
    <div class="gv3-kpi-sub">veículos fora de serviço</div>
  </div>

  <div class="gv3-kpi-card" style="--kc:#10b981">
    <div class="gv3-kpi-label">Alertas 7D</div>
    <div class="gv3-kpi-value" :style="alertasManutencao.length > 0 ? 'color:#d97706' : 'color:#1e293b'">
      {{ alertasManutencao.length }}
    </div>
    <div class="gv3-kpi-sub">manutenções previstas nos próximos 7 dias</div>
  </div>

</div>
```

**CSS dos KPI cards — igual ao padrão com 4 colunas:**
```css
.gv3-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin: 0 16px;
}
.gv3-kpi-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 16px; padding: 18px 22px;
  display: flex; flex-direction: column; gap: 6px;
}
.gv3-kpi-card::before {
  content: ''; display: block; width: 36px; height: 3px;
  border-radius: 2px; background: var(--kc, #6366f1); margin-bottom: 2px;
}
.gv3-kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
.gv3-kpi-value { font-size: 30px; font-weight: 900; color: #1e293b; line-height: 1; }
.gv3-kpi-sub   { font-size: 12px; color: #64748b; }
```

---

## 3. PANEL — Filter tabs

```html
<div class="gv3-panel">

  <!-- TABS -->
  <div class="gv3-toolbar">
    <div class="gv3-filter-tabs">
      <button
        v-for="tab in tabs" :key="tab.id"
        class="gv3-ftab"
        :class="{ active: activeTab === tab.id }"
        @click="activeTab = tab.id"
      >
        <!-- ícone SVG inline por tab (ver seção 7) -->
        {{ tab.name }}
        <span v-if="tab.id === 'frota'" class="gv3-ftab-count">{{ veiculos.length }}</span>
        <span v-if="tab.id === 'saidas'" class="gv3-ftab-count" :class="{ warn: saidasAbertas.length > 0 }">{{ saidasAbertas.length }}</span>
        <span v-if="tab.id === 'alertas'" class="gv3-ftab-count" :class="{ danger: alertasManutencao.length > 0 }">{{ alertasManutencao.length }}</span>
      </button>
    </div>
  </div>

  <!-- LOADING STATE -->
  <div v-if="isLoading" class="gv3-state-box">
    <div class="gv3-spinner"></div>
    <p>Carregando…</p>
  </div>

  <!-- TAB: CONTROLE DE VEÍCULOS -->
  <div v-else-if="activeTab === 'frota'">

    <!-- Search/filter row -->
    <div class="gv3-search-row">
      <select v-model="filtersFrota.status" @change="fetchFrota" class="gv3-form-input" style="max-width:180px">
        <option value="">Todos os status</option>
        <option value="DISPONIVEL">Disponível</option>
        <option value="EM_USO">Em uso</option>
        <option value="EM_MANUTENCAO">Em manutenção</option>
        <option value="INATIVO">Inativo</option>
      </select>
      <select v-model="filtersFrota.tipo" @change="fetchFrota" class="gv3-form-input" style="max-width:160px">
        <option value="">Todos os tipos</option>
        <option value="CARRO">Carro</option>
        <option value="VAN">Van</option>
        <option value="ONIBUS">Ônibus</option>
        <option value="CAMINHAO">Caminhão</option>
        <option value="MOTO">Moto</option>
        <option value="AMBULANCIA">Ambulância</option>
      </select>
      <span class="gv3-result-count">{{ veiculos.length }} veículo{{ veiculos.length !== 1 ? 's' : '' }}</span>
    </div>

    <div v-if="!veiculos.length" class="gv3-state-box">
      <p>Nenhum veículo encontrado com os filtros informados.</p>
    </div>

    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Placa</th>
          <th>Marca / Modelo</th>
          <th>Ano</th>
          <th>Tipo</th>
          <th class="right">KM atual</th>
          <th>Status</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="v in veiculos" :key="v.VEICULO_ID"
          class="gv3-data-row"
        >
          <td>
            <span class="gv3-placa">{{ v.VEICULO_PLACA }}</span>
          </td>
          <td>
            <div class="gv3-fornec-name">{{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }}</div>
            <div v-if="v.VEICULO_COR" class="gv3-fornec-cnpj">{{ v.VEICULO_COR }}</div>
          </td>
          <td><span class="gv3-vig">{{ v.VEICULO_ANO }}</span></td>
          <td>
            <span class="gv3-badge gv3-badge-blue">{{ v.VEICULO_TIPO }}</span>
          </td>
          <td class="gv3-valor">{{ Number(v.VEICULO_KM_ATUAL).toLocaleString('pt-BR') }} km</td>
          <td>
            <span :class="{
              'gv3-badge gv3-badge-green':  v.VEICULO_STATUS === 'DISPONIVEL',
              'gv3-badge gv3-badge-amber':  v.VEICULO_STATUS === 'EM_USO',
              'gv3-badge gv3-badge-red':    v.VEICULO_STATUS === 'EM_MANUTENCAO',
              'gv3-badge gv3-badge-gray':   v.VEICULO_STATUS === 'INATIVO'
            }">
              <span class="gv3-badge-dot"></span>
              {{ v.VEICULO_STATUS === 'DISPONIVEL' ? 'Disponível'
               : v.VEICULO_STATUS === 'EM_USO' ? 'Em uso'
               : v.VEICULO_STATUS === 'EM_MANUTENCAO' ? 'Em manutenção'
               : 'Inativo' }}
            </span>
          </td>
          <td>
            <div class="gv3-row-actions">
              <button
                class="gv3-act-btn act-blue"
                @click="selectedVeiculoHistorico = v.VEICULO_ID; activeTab = 'manutencoes'"
                aria-label="Ver histórico"
              >
                <!-- eye SVG 14x14 -->
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><ellipse cx="7" cy="7" rx="6" ry="4"/><circle cx="7" cy="7" r="1.5"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- paginação simples -->
    <div v-if="veiculos.length > 0" class="gv3-pagination">
      <span class="gv3-pg-info">Exibindo {{ veiculos.length }} veículo{{ veiculos.length !== 1 ? 's' : '' }}</span>
    </div>
  </div>

  <!-- TAB: SAÍDAS E RETORNOS -->
  <div v-else-if="activeTab === 'saidas'" class="gv3-saidas-wrap">

    <!-- Form inline de nova saída -->
    <div class="gv3-saida-form-card">
      <div class="gv3-section-header">
        <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M9 3l4 4-4 4M13 7H2"/></svg>
        <span class="gv3-section-label">Registrar nova saída</span>
      </div>
      <div class="gv3-saida-form-grid">
        <div class="gv3-form-group">
          <label class="gv3-form-label">Veículo disponível</label>
          <select v-model="formSaida.veiculo_id" required class="gv3-form-input" @change="onVeiculoSaidaChange">
            <option value="">Selecione…</option>
            <option v-for="v in veiculosDisponiveis" :key="v.VEICULO_ID" :value="v.VEICULO_ID">
              {{ v.VEICULO_PLACA }} — {{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }} ({{ v.VEICULO_KM_ATUAL }} km)
            </option>
          </select>
        </div>
        <div class="gv3-form-group">
          <label class="gv3-form-label">ID motorista (funcionário)</label>
          <input v-model="formSaida.motorista_id" type="number" required placeholder="Ex: 1024" class="gv3-form-input">
        </div>
        <div class="gv3-form-group">
          <label class="gv3-form-label">Data / hora de saída</label>
          <input v-model="formSaida.saida_data_hora" type="datetime-local" required class="gv3-form-input">
        </div>
        <div class="gv3-form-group">
          <label class="gv3-form-label">KM de saída</label>
          <input v-model="formSaida.km_saida" type="number" required class="gv3-form-input" readonly title="Preenchido automaticamente pelo veículo selecionado">
        </div>
        <div class="gv3-form-group" style="grid-column: span 2">
          <label class="gv3-form-label">Destino</label>
          <input v-model="formSaida.saida_destino" type="text" maxlength="200" required class="gv3-form-input">
        </div>
        <div class="gv3-form-group" style="grid-column: span 3">
          <label class="gv3-form-label">Finalidade da viagem</label>
          <input v-model="formSaida.saida_finalidade" type="text" maxlength="200" required class="gv3-form-input">
        </div>
        <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
          <button type="button" class="gv3-btn-novo" :disabled="isSaving" @click="registrarSaida">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 3l4 4-4 4M13 7H2"/></svg>
            {{ isSaving ? 'Registrando…' : 'Confirmar saída do veículo' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Saídas em aberto -->
    <div class="gv3-subsection-header">
      <span class="gv3-section-label">Saídas em aberto — aguardando retorno</span>
      <span class="gv3-ftab-count warn">{{ saidasAbertas.length }}</span>
    </div>

    <div v-if="!saidasAbertas.length" class="gv3-state-box">
      <p>Nenhum veículo aguardando retorno neste momento.</p>
    </div>
    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Data / hora saída</th>
          <th>Veículo</th>
          <th>Motorista</th>
          <th>Destino</th>
          <th class="right">KM saída</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in saidasAbertas" :key="s.SAIDA_ID" class="gv3-data-row" style="background:#fffbeb">
          <td><span class="gv3-mono">{{ formatDateTime(s.SAIDA_DATA_HORA) }}</span></td>
          <td>
            <span class="gv3-placa">{{ s.VEICULO_PLACA }}</span>
            <span class="gv3-fornec-cnpj"> {{ s.VEICULO_MODELO }}</span>
          </td>
          <td class="gv3-fornec-name">{{ s.motorista }}</td>
          <td class="gv3-vig" :title="s.SAIDA_DESTINO" style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ s.SAIDA_DESTINO }}</td>
          <td class="gv3-valor">{{ s.KM_SAIDA }} km</td>
          <td>
            <div class="gv3-row-actions">
              <button class="gv3-act-btn act-green" @click="abrirRetornoModal(s)">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 11l-4-4 4-4M1 7h12"/></svg>
                Retorno
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: MANUTENÇÕES -->
  <div v-else-if="activeTab === 'manutencoes'" class="gv3-manut-wrap">

    <div class="gv3-manut-sidebar">
      <div class="gv3-form-group">
        <label class="gv3-form-label">Selecione o veículo</label>
        <select v-model="selectedVeiculoHistorico" @change="fetchHistorico" class="gv3-form-input">
          <option value="">— Selecione um veículo —</option>
          <option v-for="v in veiculos" :key="v.VEICULO_ID" :value="v.VEICULO_ID">
            {{ v.VEICULO_PLACA }} — {{ v.VEICULO_MODELO }} ({{ v.VEICULO_STATUS }})
          </option>
        </select>
      </div>
      <div v-if="selectedVeiculoHistorico">
        <button class="gv3-btn-novo" style="width:100%; justify-content:center" @click="abrirManutencaoModal">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 2l-1.5 1.5M2 12l8-8M5 9L2 12m7-3l3-3-3-7-3 3"/></svg>
          Registrar manutenção
        </button>
      </div>
    </div>

    <div class="gv3-manut-content">
      <div v-if="!selectedVeiculoHistorico" class="gv3-state-box" style="border:1.5px dashed #e2e8f0; border-radius:16px; min-height:280px">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round"><rect x="5" y="10" width="30" height="22" rx="4"/><path d="M14 10V7a6 6 0 0112 0v3"/><circle cx="20" cy="21" r="3"/></svg>
        <p>Selecione um veículo para visualizar seu histórico e registrar manutenções.</p>
      </div>
      <div v-else>
        <div class="gv3-subsection-header" style="margin-bottom:0">
          <span class="gv3-section-label">Histórico de manutenções</span>
        </div>
        <div v-if="!historicoVeiculo.manutencoes?.length" class="gv3-state-box">
          <p>Nenhuma manutenção registrada para este veículo.</p>
        </div>
        <table v-else class="gv3-table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Tipo</th>
              <th>Descrição / Fornecedor</th>
              <th class="right">Custo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in historicoVeiculo.manutencoes" :key="m.MANUT_ID" class="gv3-data-row">
              <td><span class="gv3-mono">{{ formatDate(m.MANUT_DATA) }}</span></td>
              <td>
                <span :class="m.MANUT_TIPO === 'PREVENTIVA' ? 'gv3-badge gv3-badge-blue' : 'gv3-badge gv3-badge-amber'">
                  <span class="gv3-badge-dot"></span>{{ m.MANUT_TIPO === 'PREVENTIVA' ? 'Preventiva' : 'Corretiva' }}
                </span>
              </td>
              <td>
                <div class="gv3-fornec-name">{{ m.MANUT_DESCRICAO }}</div>
                <div v-if="m.MANUT_FORNECEDOR" class="gv3-fornec-cnpj">{{ m.MANUT_FORNECEDOR }}</div>
              </td>
              <td class="gv3-valor">{{ m.MANUT_VALOR ? formatCurrency(m.MANUT_VALOR) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB: ALERTAS -->
  <div v-else-if="activeTab === 'alertas'">
    <div v-if="!alertasManutencao.length" class="gv3-state-box">
      <p>Nenhum veículo com manutenção prevista para os próximos 30 dias.</p>
    </div>
    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Veículo</th>
          <th>Data prevista</th>
          <th>Urgência</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="v in alertasManutencao" :key="v.VEICULO_ID"
          class="gv3-data-row"
          :style="v.urgencia === 'CRITICO' ? 'background:#fef2f2' : 'background:#fffbeb'"
        >
          <td>
            <span class="gv3-placa">{{ v.VEICULO_PLACA }}</span>
            <span class="gv3-fornec-cnpj"> {{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }}</span>
          </td>
          <td><span class="gv3-vig-warn">{{ formatDate(v.VEICULO_PROX_MANUTENCAO) }}</span></td>
          <td>
            <span :class="v.urgencia === 'CRITICO' ? 'gv3-badge gv3-badge-red' : 'gv3-badge gv3-badge-amber'">
              <span class="gv3-badge-dot"></span>
              {{ v.dias_restantes < 0
                  ? 'Atrasado ' + Math.abs(v.dias_restantes) + ' dias'
                  : v.dias_restantes === 0
                    ? 'Hoje!'
                    : 'Faltam ' + v.dias_restantes + ' dias' }}
            </span>
          </td>
          <td>
            <div class="gv3-row-actions">
              <button class="gv3-act-btn act-blue" @click="abrirManutencaoFromAlerta(v.VEICULO_ID)">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><ellipse cx="7" cy="7" rx="6" ry="4"/><circle cx="7" cy="7" r="1.5"/></svg>
                Ver veículo
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

</div>
```

---

## 4. CSS COMPLETO DO PANEL E COMPONENTES

```css
/* PANEL */
.gv3-panel {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 20px; overflow: hidden;
  margin: 0 16px 16px;
}
.gv3-toolbar {
  display: flex; align-items: center;
  padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}
.gv3-filter-tabs { display: flex; gap: 5px; flex-wrap: wrap; }
.gv3-ftab {
  padding: 6px 13px; border-radius: 999px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  border: 1px solid #e2e8f0; background: #fff; color: #475569;
  font-family: inherit; display: inline-flex; align-items: center; gap: 5px;
  transition: all 0.15s;
}
.gv3-ftab.active { background: #6366f1; color: #fff; border-color: #6366f1; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
.gv3-ftab:not(.active):hover { background: #f8fafc; border-color: #cbd5e1; }
.gv3-ftab-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 17px; height: 17px; border-radius: 999px;
  font-size: 10px; padding: 0 3px;
  background: #f1f5f9; color: #64748b;
}
.gv3-ftab.active .gv3-ftab-count { background: rgba(255,255,255,0.25); color: #fff; }
.gv3-ftab-count.warn   { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
.gv3-ftab-count.danger { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

/* SEARCH ROW */
.gv3-search-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap;
}
.gv3-result-count { font-size: 12px; font-weight: 600; color: #94a3b8; white-space: nowrap; margin-left: auto; }

/* TABLE */
.gv3-table { width: 100%; border-collapse: collapse; }
.gv3-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.gv3-table th {
  padding: 11px 16px; font-size: 10px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #94a3b8; text-align: left; white-space: nowrap;
}
.gv3-table th.right { text-align: right; }
.gv3-data-row { border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background 0.12s; }
.gv3-data-row:hover { background: #f8fafc; }
.gv3-data-row:last-child { border-bottom: none; }
.gv3-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }

/* PLACA — destaque especial para frotas */
.gv3-placa {
  font-family: 'JetBrains Mono','Fira Code',monospace;
  font-size: 13px; font-weight: 800; color: #1e293b;
  background: #f1f5f9; border: 1px solid #e2e8f0;
  border-radius: 6px; padding: 2px 8px; letter-spacing: 0.05em;
}
.gv3-mono { font-family: 'JetBrains Mono','Fira Code',monospace; font-size: 12px; font-weight: 700; color: #64748b; }
.gv3-fornec-name { font-weight: 600; color: #1e293b; }
.gv3-fornec-cnpj { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.gv3-valor { text-align: right; font-family: 'JetBrains Mono',monospace; font-size: 12px; font-weight: 700; color: #1e293b; }
.gv3-vig { font-size: 12px; color: #64748b; white-space: nowrap; }
.gv3-vig-warn { font-size: 12px; color: #d97706; font-weight: 700; }

/* BADGES */
.gv3-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 700; white-space: nowrap;
}
.gv3-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.gv3-badge-green  { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.gv3-badge-red    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.gv3-badge-amber  { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
.gv3-badge-blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.gv3-badge-gray   { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

/* ACTION BUTTONS */
.gv3-row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.gv3-act-btn {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  min-width: 30px; height: 30px; border-radius: 9px;
  border: 1px solid #e2e8f0; background: #f8fafc;
  cursor: pointer; color: #64748b;
  font-size: 12px; font-weight: 600; padding: 0 8px;
  font-family: inherit; transition: all 0.15s;
}
.gv3-act-btn:hover { transform: translateY(-1px); }
.gv3-act-btn.act-blue:hover  { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.gv3-act-btn.act-green:hover { background: #f0fdf4; border-color: #86efac; color: #15803d; }
.gv3-act-btn.act-red:hover   { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

/* STATES */
.gv3-state-box {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 60px 20px; text-align: center; color: #64748b; gap: 12px;
}
.gv3-state-box p { font-size: 14px; font-weight: 500; margin: 0; }
.gv3-spinner {
  width: 40px; height: 40px;
  border: 3px solid #e2e8f0; border-top-color: #6366f1;
  border-radius: 50%; animation: gv3spin 0.8s linear infinite;
}
@keyframes gv3spin { to { transform: rotate(360deg); } }

/* PAGINATION */
.gv3-pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-top: 1px solid #f1f5f9;
}
.gv3-pg-info { font-size: 13px; font-weight: 600; color: #64748b; }

/* SAÍDAS */
.gv3-saidas-wrap { padding: 16px; display: flex; flex-direction: column; gap: 16px; }
.gv3-saida-form-card {
  background: #f8fafc; border: 1px solid #e2e8f0;
  border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 14px;
}
.gv3-section-header { display: flex; align-items: center; gap: 8px; }
.gv3-section-label {
  font-size: 11px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.08em; color: #64748b;
}
.gv3-saida-form-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
}
.gv3-subsection-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}

/* MANUTENÇÕES */
.gv3-manut-wrap {
  display: grid; grid-template-columns: 260px 1fr;
  gap: 0; min-height: 400px;
}
.gv3-manut-sidebar {
  padding: 20px; border-right: 1px solid #f1f5f9;
  display: flex; flex-direction: column; gap: 14px;
  background: #f8fafc;
}
.gv3-manut-content { flex: 1; }

/* FORM ELEMENTS */
.gv3-form-group { display: flex; flex-direction: column; gap: 5px; }
.gv3-form-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.06em; color: #64748b;
}
.gv3-form-input {
  width: 100%; padding: 9px 12px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-family: inherit; color: #1e293b;
  outline: none; background: #fff; transition: border-color 0.15s;
  box-sizing: border-box;
}
.gv3-form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.gv3-form-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }

/* ERRO GLOBAL */
.gv3-toast-error {
  display: flex; align-items: center; gap: 8px;
  background: #fef2f2; border: 1px solid #fca5a5;
  border-radius: 10px; padding: 10px 16px;
  font-size: 13px; font-weight: 600; color: #991b1b;
  margin: 0 16px 12px;
}

/* RESPONSIVO */
@media (max-width: 768px) {
  .gv3-hero { margin: 12px; padding: 20px; }
  .gv3-hero-inner { flex-direction: column; }
  .gv3-kpi-grid { grid-template-columns: 1fr 1fr; margin: 0 12px; }
  .gv3-panel { margin: 0 12px 12px; }
  .gv3-filter-tabs { overflow-x: auto; flex-wrap: nowrap; }
  .gv3-saida-form-grid { grid-template-columns: 1fr; }
  .gv3-manut-wrap { grid-template-columns: 1fr; }
  .gv3-manut-sidebar { border-right: none; border-bottom: 1px solid #f1f5f9; }
}
```

---

## 5. ERRO GLOBAL

Substituir o `bg-red-50 border-l-4 border-red-500` por:
```html
<div v-if="errorMsg" class="gv3-toast-error">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="6"/><line x1="7" y1="4" x2="7" y2="7.5"/><circle cx="7" cy="10" r="0.5" fill="currentColor"/></svg>
  {{ errorMsg }}
</div>
```

---

## 6. MODAIS — substituições de classe

Manter toda a lógica. Atualizar apenas os estilos inline conforme tabela:

| Antes | Depois |
|-------|--------|
| `bg-white rounded-xl shadow-xl w-full max-w-2xl` | `background:#fff; border-radius:24px; box-shadow:0 32px 80px rgba(0,0,0,0.25); width:100%; max-width:680px` |
| `bg-white rounded-xl shadow-xl w-full max-w-md` | `background:#fff; border-radius:24px; box-shadow:0 32px 80px rgba(0,0,0,0.25); width:100%; max-width:480px` |
| `bg-white rounded-xl shadow-xl w-full max-w-lg` | `background:#fff; border-radius:24px; box-shadow:0 32px 80px rgba(0,0,0,0.25); width:100%; max-width:560px` |
| `px-6 py-4 border-b bg-slate-50 rounded-t-xl` (modal header) | `padding:22px 28px; border-bottom:1px solid #f1f5f9; background:#fff` |
| `text-lg font-bold text-slate-800` (modal title) | `font-size:18px; font-weight:800; color:#1e293b` |
| Botão X fechar | `width:32px; height:32px; border:1px solid #e2e8f0; border-radius:9px; background:#fff; color:#64748b; cursor:pointer` hover: `background:#fef2f2; border-color:#fca5a5; color:#dc2626` |
| `p-6 space-y-4` (modal body) | `padding:24px 28px; display:flex; flex-direction:column; gap:14px` |
| `pt-4 flex justify-end gap-3 border-t mt-6` (modal footer) | `padding:16px 28px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:10px` |
| `btn btn-primary` | `.gv3-btn-novo` |
| `btn btn-outline` | `border:1px solid #e2e8f0; border-radius:14px; padding:9px 16px; background:#f8fafc; color:#475569; font-weight:600; font-family:inherit; cursor:pointer` |
| `btn btn-primary bg-emerald-600` (confirmar chegada) | `.gv3-btn-novo` com `background:linear-gradient(135deg,#10b981,#059669)` e shadow verde |
| `input class="input w-full"` | `class="gv3-form-input"` |
| `select class="input w-full"` | `class="gv3-form-input"` |
| `textarea class="input w-full"` | `class="gv3-form-input"` |
| `block text-sm font-medium text-slate-700 mb-1` (labels) | `class="gv3-form-label"` |
| `grid grid-cols-2 gap-4` (form grids) | `display:grid; grid-template-columns:1fr 1fr; gap:14px` |
| `bg-slate-100 p-3 rounded text-sm` (info da saída no modal retorno) | `background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; font-size:13px; display:flex; flex-direction:column; gap:4px` |

**Modal Retorno — info box:**
```html
<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; font-size:13px; display:flex; flex-direction:column; gap:4px; margin-bottom:4px">
  <span style="font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#94a3b8; padding-bottom:6px; border-bottom:1px solid #e2e8f0; margin-bottom:2px">Dados da saída</span>
  <span style="color:#334155">Motorista: <strong>{{ selectedSaida?.motorista }}</strong></span>
  <span style="color:#334155">Destino: {{ selectedSaida?.SAIDA_DESTINO }}</span>
  <span style="color:#334155">KM inicial: <strong style="color:#15803d">{{ selectedSaida?.KM_SAIDA }} km</strong></span>
</div>
```

---

## 7. Checklist de entrega

- [ ] Hero com gradiente `#0f172a → #1e3a5f → #312e81`, hs2 azul `#3b82f6`, eyebrow `#a78bfa`
- [ ] Botão primário gradiente `#6366f1 → #8b5cf6`
- [ ] KPI grid com **4 colunas** (Total, Em Uso, Em Manutenção, Alertas 7D)
- [ ] Filter tabs: ativa em `#6366f1`, count `warn` amarelo, count `danger` vermelho
- [ ] Placa exibida com `.gv3-placa` (fundo cinza, mono, destaque)
- [ ] Badges de status veículo: verde=Disponível, âmbar=Em Uso, vermelho=Em Manutenção, cinza=Inativo
- [ ] Linha saída em aberto e alerta: `background:#fffbeb` ou `#fef2f2` conforme urgência
- [ ] Tab Saídas: form de nova saída com `.gv3-saida-form-card` + tabela de saídas em aberto
- [ ] Tab Manutenções: layout 2 colunas (sidebar 260px + conteúdo) com `.gv3-manut-wrap`
- [ ] Tab Alertas: badges vermelho/âmbar conforme `v.urgencia === 'CRITICO'`
- [ ] Modais com `border-radius:24px`, header/footer brancos sem bg-slate-50
- [ ] Inputs/selects/textareas usando `.gv3-form-input` em todos os formulários
- [ ] `statusColors` do script **não** é mais usado no template — substituído pelos `gv3-badge-*`
- [ ] Script setup **não alterado** — zero mudanças em lógica, apis, refs
- [ ] `npm run build` sem erros

---

*Claude — Chief Engineer GENTE v3 | 12/05/2026*
