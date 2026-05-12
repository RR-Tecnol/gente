# BRIEFING — Redesign Visual: ContratosAdminView.vue
**Executor:** Antygravity  
**Autoridade de design:** `docs/DESIGN_SYSTEM_GENTE_V3.md`  
**Arquivo alvo:** `resources/gente-v3/src/views/administrativo/ContratosAdminView.vue`  
**Escopo:** SOMENTE visual/template. Toda a lógica Vue (script setup, refs, apis, modais) é preservada sem alteração.

---

## Regra zero

Não alterar NADA no `<script setup>`. Reescrever apenas o `<template>`. Todas as variáveis, métodos, watchers e imports já existem e estão corretos.

---

## Estrutura obrigatória do template

```
[TOPBAR]   → já existe no layout pai — NÃO duplicar
[HERO]     → novo, seguindo spec §5 do Design System
[KPI GRID] → 3 cards (Impacto Cruzado, Atenção Operacional, Teia de Módulos)
[PANEL]    → filter tabs + search row + tabela + paginação
[MODAIS]   → Detalhes, Aditivo, Fiscalização — apenas visual atualizado
```

O wrapper raiz do componente deve ser:
```html
<template>
  <div style="background:#f1f5f9; font-family:'Inter',system-ui,sans-serif; min-height:100vh;">
    <!-- conteúdo aqui -->
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
      <h1 class="gv3-hero-title">Contratos Administrativos</h1>
      <p class="gv3-hero-sub">Gestão, aditivos e fiscalização de contratos</p>
    </div>
    <div class="gv3-hero-right">
      <div class="gv3-chip">
        <span class="gv3-chip-dot green"></span>
        <strong>{{ contratos.length }}</strong>&nbsp;contratos ativos
      </div>
      <div class="gv3-chip">
        <span class="gv3-chip-dot amber"></span>
        <strong>{{ contratosVencendo.length }}</strong>&nbsp;vencendo em breve
      </div>
      <button class="gv3-btn-novo" @click="exportarCsv">
        <!-- ícone download SVG inline 14x14 -->
        Exportar base
      </button>
    </div>
  </div>
</div>
```

**CSS do hero (prefixado gv3- para não conflitar):**
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
.gv3-hs2 { width: 180px; height: 180px; background: #f59e0b; bottom: -50px; right: 260px; }
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
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #a78bfa;
  margin-bottom: 6px;
}
.gv3-hero-title {
  font-size: 26px;
  font-weight: 900;
  color: #ffffff;
  line-height: 1.1;
  margin: 0 0 6px;
}
.gv3-hero-sub {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}
.gv3-hero-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.gv3-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 8px 14px;
  font-size: 13px;
  color: #e2e8f0;
  white-space: nowrap;
}
.gv3-chip strong { color: #fff; font-weight: 700; }
.gv3-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.gv3-chip-dot.green { background: #10b981; }
.gv3-chip-dot.amber { background: #f59e0b; }
.gv3-btn-novo {
  display: flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: none;
  border-radius: 14px;
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(99,102,241,0.4);
  font-family: inherit;
  transition: all 0.2s;
}
.gv3-btn-novo:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(99,102,241,0.5);
}
```

---

## 2. KPI GRID (3 cards)

Inserir após o hero, antes do panel. Usar os dados reativos já existentes.

```html
<div class="gv3-kpi-grid">

  <!-- Card 1: Impacto Cruzado -->
  <div class="gv3-kpi-card" style="--kc:#6366f1">
    <div class="gv3-kpi-label">Impacto cruzado</div>
    <div class="gv3-kpi-value">{{ contratos.length }}</div>
    <div class="gv3-kpi-sub">contratos ativos abastecendo OSS, Almoxarifado e Frotas</div>
  </div>

  <!-- Card 2: Atenção Operacional -->
  <div class="gv3-kpi-card" style="--kc:#f59e0b">
    <div class="gv3-kpi-label">Atenção operacional</div>
    <div class="gv3-kpi-value" style="color:#d97706">{{ contratosVencendo.length }}</div>
    <div class="gv3-kpi-sub">contratos próximos do vencimento (&lt;30 dias)</div>
  </div>

  <!-- Card 3: Teia de Módulos -->
  <div class="gv3-kpi-card" style="--kc:#10b981">
    <div class="gv3-kpi-label">Teia de módulos</div>
    <div class="gv3-mod-chips">
      <span class="gv3-mod-chip">Monitor OSS</span>
      <span class="gv3-mod-chip">Almoxarifado</span>
      <span class="gv3-mod-chip">Frotas</span>
    </div>
  </div>

</div>
```

**CSS:**
```css
.gv3-kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin: 0 16px;
}
.gv3-kpi-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 18px 22px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.gv3-kpi-card::before {
  content: '';
  display: block;
  width: 36px;
  height: 3px;
  border-radius: 2px;
  background: var(--kc, #6366f1);
  margin-bottom: 2px;
}
.gv3-kpi-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
}
.gv3-kpi-value {
  font-size: 30px;
  font-weight: 900;
  color: #1e293b;
  line-height: 1;
}
.gv3-kpi-sub {
  font-size: 12px;
  color: #64748b;
}
.gv3-mod-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 4px;
}
.gv3-mod-chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  background: #f8fafc;
  cursor: pointer;
}
.gv3-mod-chip:hover {
  border-color: #6366f1;
  color: #4f46e5;
  background: #eef2ff;
}
```

---

## 3. PANEL (filter tabs + search + tabela)

Substituir o bloco `bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden` que continha as tabs antigas.

```html
<div class="gv3-panel">

  <!-- Filter Tabs -->
  <div class="gv3-toolbar">
    <div class="gv3-filter-tabs">
      <button
        v-for="tab in tabs" :key="tab.id"
        class="gv3-ftab"
        :class="{ active: activeTab === tab.id }"
        @click="activeTab = tab.id"
      >
        {{ tab.name }}
        <span v-if="tab.id === 'ativos'" class="gv3-ftab-count">{{ contratos.length }}</span>
        <span v-if="tab.id === 'vencendo'" class="gv3-ftab-count warn">{{ contratosVencendo.length }}</span>
        <span v-if="tab.id === 'encerrados'" class="gv3-ftab-count">{{ contratosEncerrados.length }}</span>
      </button>
    </div>
  </div>

  <!-- Search row (só nas tabs ativos/encerrados) -->
  <div v-if="activeTab === 'ativos' || activeTab === 'encerrados'" class="gv3-search-row">
    <div class="gv3-search-wrap">
      <!-- ícone lupa SVG inline 14x14 stroke=#94a3b8 -->
      <input
        v-model="filters.busca"
        @input="fetchContratos"
        type="text"
        placeholder="Buscar por objeto ou fornecedor…"
        class="gv3-search-input"
      />
    </div>
    <span class="gv3-result-count">{{ contratos.length }} resultado{{ contratos.length !== 1 ? 's' : '' }}</span>
  </div>

  <!-- Loading state -->
  <div v-if="isLoading" class="gv3-state-box">
    <div class="gv3-spinner"></div>
    <p>Carregando…</p>
  </div>

  <!-- TAB: CONTRATOS ATIVOS -->
  <div v-else-if="activeTab === 'ativos'">
    <div v-if="!contratos.length" class="gv3-state-box">
      <p>Nenhum contrato ativo encontrado.</p>
    </div>
    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Número</th>
          <th>Fornecedor</th>
          <th>Objeto</th>
          <th class="right">Valor (R$)</th>
          <th>Vigência</th>
          <th>Status</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="c in contratos" :key="c.CONTRATO_ID"
          class="gv3-data-row"
          @click="abrirDetalhes(c)"
        >
          <td><span class="gv3-mono">{{ c.CONTRATO_NUMERO }}</span></td>
          <td>
            <div class="gv3-fornec-name">{{ c.CONTRATO_FORNECEDOR }}</div>
            <div v-if="c.CONTRATO_CNPJ" class="gv3-fornec-cnpj">{{ c.CONTRATO_CNPJ }}</div>
          </td>
          <td class="gv3-objeto">{{ c.CONTRATO_OBJETO }}</td>
          <td class="gv3-valor">{{ formatCurrency(c.CONTRATO_VALOR) }}</td>
          <td>
            <span
              v-if="c.dias_restantes !== undefined && c.dias_restantes <= 30"
              class="gv3-vig-warn"
            >
              <!-- ícone alerta SVG inline 12x12 -->
              Vence em {{ c.dias_restantes }} dias
            </span>
            <span v-else class="gv3-vig">
              {{ formatDate(c.CONTRATO_INICIO) }} — {{ formatDate(c.CONTRATO_FIM) }}
            </span>
          </td>
          <td>
            <span class="gv3-badge gv3-badge-green">
              <span class="gv3-badge-dot"></span>Ativo
            </span>
          </td>
          <td>
            <div class="gv3-row-actions" @click.stop>
              <button class="gv3-act-btn act-blue" @click.stop="abrirDetalhes(c)" aria-label="Ver detalhes">
                <!-- ícone eye SVG inline 14x14 -->
              </button>
              <button class="gv3-act-btn" @click.stop="abrirAditivo(c)" aria-label="Registrar aditivo">
                <!-- ícone edit SVG inline 14x14 -->
              </button>
              <button class="gv3-act-btn" aria-label="Mais opções">
                <!-- ícone dots-vertical SVG inline 14x14 -->
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: VENCENDO -->
  <div v-else-if="activeTab === 'vencendo'">
    <div v-if="!contratosVencendo.length" class="gv3-state-box">
      <p>Nenhum contrato vencendo nos próximos 60 dias.</p>
    </div>
    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Fornecedor</th>
          <th>Término</th>
          <th>Urgência</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="c in contratosVencendo" :key="c.CONTRATO_ID"
          class="gv3-data-row"
          :style="c.urgencia === 'CRITICO' ? 'background:#fef2f2' : 'background:#fffbeb'"
        >
          <td><span class="gv3-mono">{{ c.CONTRATO_NUMERO }}</span></td>
          <td class="gv3-fornec-name">{{ c.CONTRATO_FORNECEDOR }}</td>
          <td><span class="gv3-vig-warn">{{ formatDate(c.CONTRATO_FIM) }}</span></td>
          <td>
            <span :class="c.urgencia === 'CRITICO' ? 'gv3-badge gv3-badge-red' : 'gv3-badge gv3-badge-amber'">
              <span class="gv3-badge-dot"></span>Faltam {{ c.dias_restantes }} dias
            </span>
          </td>
          <td>
            <div class="gv3-row-actions">
              <button class="gv3-act-btn act-blue" @click="abrirAditivo(c)">
                <!-- ícone edit SVG inline 14x14 -->
                Registrar aditivo
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: ENCERRADOS -->
  <div v-else-if="activeTab === 'encerrados'">
    <div v-if="!contratosEncerrados.length" class="gv3-state-box">
      <p>Nenhum contrato encerrado encontrado.</p>
    </div>
    <table v-else class="gv3-table">
      <thead>
        <tr>
          <th>Número</th>
          <th>Fornecedor</th>
          <th>Vigência final</th>
          <th>Status</th>
          <th class="right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in contratosEncerrados" :key="c.CONTRATO_ID" class="gv3-data-row">
          <td><span class="gv3-mono">{{ c.CONTRATO_NUMERO }}</span></td>
          <td class="gv3-fornec-name">{{ c.CONTRATO_FORNECEDOR }}</td>
          <td class="gv3-vig">{{ formatDate(c.CONTRATO_FIM) }}</td>
          <td>
            <span class="gv3-badge gv3-badge-gray">
              <span class="gv3-badge-dot"></span>{{ c.CONTRATO_STATUS }}
            </span>
          </td>
          <td>
            <div class="gv3-row-actions">
              <button class="gv3-act-btn act-blue" @click="abrirDetalhes(c)" aria-label="Histórico">
                <!-- ícone eye SVG inline 14x14 -->
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: FISCALIZAÇÃO -->
  <div v-else-if="activeTab === 'fiscalizacao'" class="gv3-fiscal-wrap">
    <div class="gv3-form-group">
      <label class="gv3-form-label">Selecione o contrato para fiscalização mensal</label>
      <select v-model="selectedFiscalContractId" @change="carregarFiscalizacoes" class="gv3-form-input">
        <option value="">— Selecione um contrato ativo —</option>
        <option v-for="c in contratos" :key="c.CONTRATO_ID" :value="c.CONTRATO_ID">
          {{ c.CONTRATO_NUMERO }} — {{ c.CONTRATO_FORNECEDOR }}
        </option>
      </select>
    </div>

    <div v-if="selectedFiscalContractId" class="gv3-fiscal-panel">
      <div class="gv3-fiscal-header">
        <span class="gv3-section-label">Histórico de fiscalização</span>
        <button class="gv3-btn-novo" @click="abrirFiscalizacao">
          <!-- ícone plus SVG inline -->
          Nova inspeção mensal
        </button>
      </div>
      <table class="gv3-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Ref</th>
            <th>Responsável</th>
            <th>Status</th>
            <th>Observação</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in fiscalizacoes" :key="f.FISCAL_ID" class="gv3-data-row">
            <td class="gv3-vig">{{ formatDate(f.FISCAL_DATA) }}</td>
            <td><span class="gv3-mono">{{ f.FISCAL_COMPETENCIA }}</span></td>
            <td class="gv3-vig">{{ f.FISCAL_RESPONSAVEL || 'N/I' }}</td>
            <td>
              <span :class="{
                'gv3-badge gv3-badge-green':  f.FISCAL_STATUS === 'REGULAR',
                'gv3-badge gv3-badge-red':    f.FISCAL_STATUS === 'IRREGULAR',
                'gv3-badge gv3-badge-amber':  f.FISCAL_STATUS === 'PENDENCIA',
                'gv3-badge gv3-badge-gray':   f.FISCAL_STATUS === 'SUSPENSO'
              }">
                <span class="gv3-badge-dot"></span>{{ f.FISCAL_STATUS }}
              </span>
            </td>
            <td class="gv3-vig">{{ f.FISCAL_OBSERVACAO }}</td>
          </tr>
          <tr v-if="!fiscalizacoes.length">
            <td colspan="5" class="gv3-state-box">Sem registros de fiscalização para este contrato.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Paginação (quando houver lista grande) -->
  <div v-if="contratos.length > 0 && activeTab === 'ativos'" class="gv3-pagination">
    <span class="gv3-pg-info">Exibindo {{ contratos.length }} resultado{{ contratos.length !== 1 ? 's' : '' }}</span>
    <div class="gv3-pg-btns">
      <button class="gv3-pg-btn" disabled>
        <!-- chevron-left SVG inline -->
      </button>
      <button class="gv3-pg-btn active">1</button>
      <button class="gv3-pg-btn">
        <!-- chevron-right SVG inline -->
      </button>
    </div>
  </div>

</div>
```

**CSS do panel:**
```css
.gv3-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  overflow: hidden;
  margin: 0 16px 16px;
}
.gv3-toolbar {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.gv3-filter-tabs { display: flex; gap: 5px; flex-wrap: wrap; }
.gv3-ftab {
  padding: 6px 13px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s;
}
.gv3-ftab.active {
  background: #6366f1;
  color: #fff;
  border-color: #6366f1;
  box-shadow: 0 2px 8px rgba(99,102,241,0.3);
}
.gv3-ftab:not(.active):hover { background: #f8fafc; border-color: #cbd5e1; }
.gv3-ftab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 17px;
  height: 17px;
  border-radius: 999px;
  font-size: 10px;
  padding: 0 3px;
  background: #f1f5f9;
  color: #64748b;
}
.gv3-ftab.active .gv3-ftab-count { background: rgba(255,255,255,0.25); color: #fff; }
.gv3-ftab-count.warn { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

.gv3-search-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.gv3-search-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0 11px;
  height: 36px;
}
.gv3-search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: #1e293b;
  flex: 1;
  font-family: inherit;
}
.gv3-search-input::placeholder { color: #94a3b8; }
.gv3-result-count { font-size: 12px; font-weight: 600; color: #94a3b8; white-space: nowrap; }

/* TABLE */
.gv3-table { width: 100%; border-collapse: collapse; }
.gv3-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.gv3-table th {
  padding: 11px 16px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
  text-align: left;
  white-space: nowrap;
}
.gv3-table th.right { text-align: right; }
.gv3-data-row { border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background 0.12s; }
.gv3-data-row:hover { background: #f8fafc; }
.gv3-data-row:last-child { border-bottom: none; }
.gv3-table td { padding: 12px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.gv3-mono { font-family: 'JetBrains Mono','Fira Code',monospace; font-size: 12px; font-weight: 700; color: #64748b; }
.gv3-fornec-name { font-weight: 600; color: #1e293b; }
.gv3-fornec-cnpj { font-size: 11px; color: #94a3b8; margin-top: 1px; font-family: monospace; }
.gv3-objeto { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gv3-valor { text-align: right; font-family: 'JetBrains Mono',monospace; font-size: 12px; font-weight: 700; color: #1e293b; }
.gv3-vig { font-size: 12px; color: #64748b; white-space: nowrap; }
.gv3-vig-warn { font-size: 12px; color: #d97706; font-weight: 700; display: flex; align-items: center; gap: 4px; }

/* BADGES */
.gv3-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
.gv3-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.gv3-badge-green { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.gv3-badge-red   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.gv3-badge-amber { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
.gv3-badge-gray  { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.gv3-badge-blue  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* ACTION BUTTONS */
.gv3-row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.gv3-act-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-width: 30px;
  height: 30px;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  cursor: pointer;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  padding: 0 8px;
  font-family: inherit;
  transition: all 0.15s;
}
.gv3-act-btn:hover { transform: translateY(-1px); }
.gv3-act-btn.act-blue:hover  { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.gv3-act-btn.act-green:hover { background: #f0fdf4; border-color: #86efac; color: #15803d; }
.gv3-act-btn.act-red:hover   { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

/* STATE BOX */
.gv3-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: #64748b;
  gap: 12px;
}
.gv3-state-box p { font-size: 14px; font-weight: 500; margin: 0; }
.gv3-spinner {
  width: 40px; height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: gv3spin 0.8s linear infinite;
}
@keyframes gv3spin { to { transform: rotate(360deg); } }

/* PAGINATION */
.gv3-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-top: 1px solid #f1f5f9;
}
.gv3-pg-info { font-size: 13px; font-weight: 600; color: #64748b; }
.gv3-pg-btns { display: flex; gap: 4px; }
.gv3-pg-btn {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #fff;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  font-family: inherit;
  transition: all 0.15s;
}
.gv3-pg-btn.active { background: #6366f1; border-color: #6366f1; color: #fff; }
.gv3-pg-btn:not(.active):not(:disabled):hover { border-color: #6366f1; color: #6366f1; }
.gv3-pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* FISCALIZAÇÃO */
.gv3-fiscal-wrap { padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.gv3-fiscal-panel { border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
.gv3-fiscal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.gv3-section-label {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
}
.gv3-form-group { display: flex; flex-direction: column; gap: 6px; }
.gv3-form-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}
.gv3-form-input {
  width: 100%;
  padding: 9px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 13px;
  font-family: inherit;
  color: #1e293b;
  outline: none;
  background: #fff;
  transition: border-color 0.15s;
}
.gv3-form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
```

---

## 4. MODAIS

Manter toda a lógica existente. Atualizar apenas o visual seguindo o padrão `docs/DESIGN_SYSTEM_GENTE_V3.md §12`.

**Substituições de classe nos modais:**

| Antes | Depois |
|-------|--------|
| `fixed inset-0 z-50 flex ... bg-slate-900/50 backdrop-blur-sm` | Manter igual — está correto |
| `bg-white rounded-xl shadow-xl w-full max-w-4xl` | `background:#fff; border-radius:24px; box-shadow:0 32px 80px rgba(0,0,0,0.25); width:100%; max-width:780px` |
| `.px-6 .py-4 .border-b .bg-slate-50 .rounded-t-xl` (header) | `padding:22px 28px; border-bottom:1px solid #f1f5f9; background:#fff` |
| `.text-lg .font-bold .text-slate-800` (modal title) | `font-size:18px; font-weight:800; color:#1e293b` |
| Botão fechar (X) | `width:32px; height:32px; border:1px solid #e2e8f0; border-radius:9px; background:#fff; color:#64748b` com hover `background:#fef2f2; border-color:#fca5a5; color:#dc2626` |
| `.p-6` (modal body) | `padding:24px 28px; overflow-y:auto` |
| `.px-6 .py-4 .border-t .bg-slate-50` (footer) | `padding:16px 28px; border-top:1px solid #f1f5f9; background:#fff` |
| `btn btn-primary` | `.gv3-btn-novo` |
| `btn btn-outline` | `border:1px solid #e2e8f0; border-radius:14px; padding:9px 16px; background:#f8fafc; color:#475569; font-weight:600` |
| Inputs nos formulários dos modais | `.gv3-form-input` |
| Labels dos formulários | `.gv3-form-label` |
| Grids de campos | `display:grid; grid-template-columns:1fr 1fr; gap:14px` |

---

## 5. ERRO GLOBAL

Substituir o bloco `bg-red-50 border-l-4 border-red-500` por:

```html
<div v-if="errorMsg" class="gv3-toast-error" style="margin:0 16px 12px">
  <!-- ícone alert-circle SVG inline -->
  {{ errorMsg }}
</div>
```
```css
.gv3-toast-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fef2f2;
  border: 1px solid #fca5a5;
  border-radius: 10px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: #991b1b;
}
```

---

## 6. Ícones SVG

O arquivo atual usa `lucide` via `createIcons`. Manter o import e a chamada `createIcons({ icons })` no script. No template, substituir os `<i data-lucide="...">` pelos SVGs inline equivalentes abaixo (14×14, stroke=currentColor, stroke-width=1.5, stroke-linecap=round):

| Ação | SVG path |
|------|----------|
| download | `<path d="M12 13H2M7 1v8M4 6l3 3 3-3"/>` |
| search | `<circle cx="6" cy="6" r="4.5"/><line x1="9.5" y1="9.5" x2="13" y2="13"/>` |
| eye | `<ellipse cx="7" cy="7" rx="6" ry="4"/><circle cx="7" cy="7" r="1.5"/>` |
| edit | `<path d="M9.5 2.5l2 2L4 12H2v-2L9.5 2.5z"/>` |
| dots-vertical | usar três `<circle>` em cy=3,7,11 r=1.2 fill=currentColor |
| x | `<line x1="1" y1="1" x2="13" y2="13"/><line x1="13" y1="1" x2="1" y2="13"/>` |
| plus | `<line x1="7" y1="1" x2="7" y2="13"/><line x1="1" y1="7" x2="13" y2="7"/>` |
| alert-triangle | `<path d="M6 1L11 10H1L6 1z"/><line x1="6" y1="5" x2="6" y2="7.5"/><circle cx="6" cy="9" r="0.5" fill="currentColor"/>` |
| chevron-left | `<polyline points="8,2 4,6 8,10"/>` |
| chevron-right | `<polyline points="4,2 8,6 4,10"/>` |

---

## 7. Responsividade mínima

```css
@media (max-width: 768px) {
  .gv3-hero { margin: 12px; padding: 20px; }
  .gv3-hero-inner { flex-direction: column; }
  .gv3-kpi-grid { grid-template-columns: 1fr 1fr; margin: 0 12px; }
  .gv3-panel { margin: 0 12px 12px; }
  .gv3-filter-tabs { overflow-x: auto; flex-wrap: nowrap; }
}
```

---

## 8. Checklist de entrega

Antes de reportar como concluído, verificar:

- [ ] Hero com gradiente `#0f172a → #1e3a5f → #312e81`, shapes blur e eyebrow `#a78bfa`
- [ ] Botão primário com gradiente `#6366f1 → #8b5cf6`
- [ ] KPI cards com acento `::before` colorido e `font-weight:900`
- [ ] Filter tabs: ativa em `#6366f1` sólido com sombra indigo
- [ ] Badges: `.gv3-badge-green/red/amber/gray/blue` com bordas e backgrounds corretos
- [ ] Linha vencendo com `background:#fffbeb` ou `#fef2f2` conforme urgência
- [ ] Table header 10px / 800 / uppercase / `#94a3b8`
- [ ] Valores monetários em `font-family: 'JetBrains Mono', monospace`
- [ ] Estados loading/vazio/erro implementados com `.gv3-state-box`
- [ ] Script setup **não alterado** — zero mudanças em lógica, apis, refs
- [ ] Nenhum `fetch()` direto adicionado — todos os dados já vêm dos métodos existentes
- [ ] Modais funcionando com ESC (já implementado no script existente)
- [ ] `npm run build` sem erros

---

*Claude — Chief Engineer GENTE v3 | 12/05/2026*
