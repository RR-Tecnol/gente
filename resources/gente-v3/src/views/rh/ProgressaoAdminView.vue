<template>
  <div class="pa-page">
    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📈 RH / Admin</span>
          <h1 class="hero-title">Progressão Funcional</h1>
          <p class="hero-sub">Motor de elegibilidade, simulador LRF e aplicação em lote</p>
        </div>
        <div class="hero-badges">
          <div class="hb-item hb-blue">
            <span class="hb-num">{{ elegiveis.length }}</span>
            <span class="hb-lbl">Elegíveis</span>
          </div>
          <div class="hb-item" :class="lrfClass">
            <span class="hb-num">{{ impacto.percentual_lrf ?? '—' }}%</span>
            <span class="hb-lbl">LRF Estimado</span>
          </div>
          <div class="hb-item hb-green">
            <span class="hb-num">{{ fmtMoeda(impacto.impacto_mensal ?? 0) }}</span>
            <span class="hb-lbl">Impacto Mensal</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs" :class="{ loaded }">
      <button v-for="tab in abas" :key="tab.id" class="tab-btn" :class="{ active: abaAtiva === tab.id }" @click="abaAtiva = tab.id">
        {{ tab.ico }} {{ tab.label }}
      </button>
    </div>

    <!-- ABA 1: Elegíveis -->
    <div v-if="abaAtiva === 'elegiveis'" class="card" :class="{ loaded }">
      <div class="card-hdr">
        <h2 class="card-title">✅ Servidores Elegíveis — {{ now }}</h2>
        <div class="card-acts">
          <button class="act-btn act-outline" @click="exportarLista">📄 Exportar</button>
          <button class="act-btn act-primary" :disabled="selecionados.length === 0 || aplicandoLote" @click="aplicarLote">
            <span v-if="aplicandoLote">⏳</span><template v-else>⚡ Aplicar Selecionados ({{ selecionados.length }})</template>
          </button>
        </div>
      </div>

      <div v-if="carregando" class="loading-txt">⏳ Calculando elegibilidade...</div>
      <div v-else-if="elegiveis.length === 0" class="empty-msg">Nenhum servidor elegível neste momento.</div>
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th><input type="checkbox" @change="toggleAll" :checked="selecionados.length === elegiveis.length" /></th>
            <th>Servidor</th><th>Cargo / Carreira</th><th>Classe</th><th>Ref. Atual</th><th>Nova Ref.</th>
            <th>Salário Atual</th><th>Novo Salário</th><th>Aumento</th><th>Meses</th><th>Nota</th><th></th>
          </tr></thead>
          <tbody>
            <tr v-for="s in elegiveis" :key="s.id">
              <td><input type="checkbox" :value="s.id" v-model="selecionados" /></td>
              <td class="td-nome">{{ s.nome }}</td>
              <td class="td-cargo">{{ s.cargo }}<br><span class="carreira-tag" v-if="s.carreira">{{ s.carreira }}</span></td>
              <td><span class="badge-classe">{{ s.classe }}</span></td>
              <td><code class="ref-badge">{{ s.referencia }}</code></td>
              <td><code class="ref-badge ref-new">{{ s.proxima_ref ?? '—' }}</code></td>
              <td class="td-money">R$ {{ fmtMoeda(s.salario_atual) }}</td>
              <td class="td-money td-new">R$ {{ fmtMoeda(s.novo_vencimento ?? 0) }}</td>
              <td class="td-aumento">+R$ {{ fmtMoeda(s.aumento) }}</td>
              <td class="td-meses">{{ s.meses_na_ref }}m</td>
              <td>
                <span v-if="s.nota !== null" class="nota-badge" :class="s.nota >= 7 ? 'nb-ok' : 'nb-bad'">{{ s.nota }}</span>
                <span v-else class="nota-badge nb-null">—</span>
              </td>
              <td>
                <button class="mini-btn" @click="aplicarUm(s)" :disabled="aplicandoId === s.id">
                  {{ aplicandoId === s.id ? '⏳' : '⚡' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal de aplicação -->
      <transition name="modal">
        <div v-if="modalAto.visible" class="modal-overlay" @click.self="modalAto.visible = false">
          <div class="modal-card">
            <div class="modal-hdr"><h3>⚡ Aplicar Progressão</h3><button class="modal-close" @click="modalAto.visible = false">✕</button></div>
            <div class="modal-body">
              <p style="font-size:14px;color:#475569;margin:0 0 14px">Servidor: <strong>{{ modalAto.nome }}</strong></p>
              <label class="field-lbl">Ato Administrativo (opcional)</label>
              <input v-model="modalAto.ato" class="field-input" placeholder="Ex: Portaria 023/2026" />
              <div v-if="modalAto.erro" class="err-msg">⚠️ {{ modalAto.erro }}</div>
              <div v-if="modalAto.ok" class="ok-msg">✅ {{ modalAto.ok }}</div>
              <div class="modal-actions">
                <button class="modal-cancel" @click="modalAto.visible = false">Cancelar</button>
                <button class="modal-submit" @click="confirmarAplicacao" :disabled="modalAto.salvando">
                  {{ modalAto.salvando ? '⏳ Aplicando...' : 'Confirmar' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- ABA 2: Todos com status -->
    <div v-if="abaAtiva === 'todos'" class="card" :class="{ loaded }">
      <div class="card-hdr">
        <h2 class="card-title">👥 Todos os Servidores</h2>
        <div class="card-acts">
          <!-- BUG-EST-12: filtro por setor -->
          <select v-model="filtroSetor" class="search-input" style="width:200px">
            <option value="">Todos os setores</option>
            <option v-for="s in setores" :key="s.SETOR_ID ?? s.id" :value="s.SETOR_ID ?? s.id">{{ s.SETOR_NOME ?? s.nome }}</option>
          </select>
          <input v-model="buscaTodos" class="search-input" placeholder="🔍 Buscar..." />
        </div>
      </div>
      <div v-if="carregando" class="loading-txt">⏳ Carregando...</div>
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th>Servidor</th><th>Cargo</th><th>Classe/Ref</th><th>Salário</th><th>Status</th><th>Bloqueios</th><th>Meses</th>
          </tr></thead>
          <tbody>
            <tr v-for="s in todosFiltered" :key="s.id">
              <td class="td-nome">{{ s.nome }}</td>
              <td class="td-cargo">{{ s.cargo }}</td>
              <td><span class="badge-classe">{{ s.classe }}</span> <code class="ref-badge">{{ s.referencia }}</code></td>
              <td class="td-money">R$ {{ fmtMoeda(s.salario_atual) }}</td>
              <td>
                <span class="status-badge" :class="s.elegivel ? 'sb-ok' : (s.elegivel_promocao ? 'sb-prom' : 'sb-block')">
                  {{ s.elegivel ? '✅ Elegível' : (s.elegivel_promocao ? '⬆️ Promoção' : '🔒 Bloqueado') }}
                </span>
              </td>
              <td class="td-bloq">
                <div v-for="b in (s.bloqueios ?? [])" :key="b" class="bloq-item">• {{ b }}</div>
                <span v-if="!s.bloqueios?.length && !s.elegivel_promocao" class="ok-txt">Sem bloqueios</span>
                <span v-if="s.elegivel_promocao" class="prom-txt">Ref. máxima — elegível para promoção de classe</span>
              </td>
              <td class="td-meses">{{ s.meses_na_ref }}m</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ABA 3: Impacto Financeiro LRF -->
    <div v-if="abaAtiva === 'impacto'" class="card" :class="{ loaded }">
      <div class="card-hdr">
        <h2 class="card-title">💰 Simulador de Impacto Financeiro</h2>
        <button class="act-btn act-outline" @click="carregarImpacto">🔄 Recalcular</button>
      </div>

      <!-- Painel LRF -->
      <div class="lrf-panel" :class="'lrf-' + (impacto.status_lrf ?? 'seguro')">
        <div class="lrf-gauge-wrap">
          <div class="lrf-gauge">
            <div class="lrf-fill" :style="{ width: Math.min(100, (impacto.percentual_lrf ?? 0) / 0.54) + '%' }" :class="'lrf-' + (impacto.status_lrf ?? 'seguro')"></div>
            <div class="lrf-marks">
              <span class="lrf-mark" style="left:90%"><span class="lrf-mark-lbl">48,6%</span></span>
              <span class="lrf-mark" style="left:95%"><span class="lrf-mark-lbl">51,3%</span></span>
              <span class="lrf-mark" style="left:100%"><span class="lrf-mark-lbl">54%</span></span>
            </div>
          </div>
        </div>
        <div class="lrf-status-row">
          <span class="lrf-pct-big">{{ impacto.percentual_lrf ?? '—' }}%</span>
          <span class="lrf-status-badge lrf-badge-{{ impacto.status_lrf ?? 'seguro' }}">
            {{ { seguro: '✅ Seguro', alerta: '⚠️ Alerta', limite_prudencial: '🟠 Limite Prudencial', limite_excedido: '🔴 Limite Excedido' }[impacto.status_lrf ?? 'seguro'] }}
          </span>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card"><span class="kc-ico">👥</span><span class="kc-val">{{ impacto.servidores_impactados ?? 0 }}</span><span class="kc-lbl">Servidores Impactados</span></div>
        <div class="kpi-card"><span class="kc-ico">📅</span><span class="kc-val">R$ {{ fmtMoeda(impacto.impacto_mensal ?? 0) }}</span><span class="kc-lbl">Impacto Mensal</span></div>
        <div class="kpi-card"><span class="kc-ico">📆</span><span class="kc-val">R$ {{ fmtMoeda(impacto.impacto_anual ?? 0) }}</span><span class="kc-lbl">Impacto Anual</span></div>
        <div class="kpi-card"><span class="kc-ico">💼</span><span class="kc-val">R$ {{ fmtMoeda(impacto.folha_atual ?? 0) }}</span><span class="kc-lbl">Folha Atual (mês)</span></div>
        <div class="kpi-card kci-highlight"><span class="kc-ico">💰</span><span class="kc-val">R$ {{ fmtMoeda(impacto.nova_folha ?? 0) }}</span><span class="kc-lbl">Nova Folha (mês)</span></div>
        <div class="kpi-card"><span class="kc-ico">📊</span><span class="kc-val">{{ impacto.percentual_impacto_folha ?? 0 }}%</span><span class="kc-lbl">% Impacto na Folha</span></div>
        <div class="kpi-card"><span class="kc-ico">🏛️</span><span class="kc-val">R$ {{ fmtMoeda(impacto.rcl ?? 0) }}</span><span class="kc-lbl">RCL Anual</span></div>
        <div class="kpi-card"><span class="kc-ico">⚖️</span><span class="kc-val">R$ {{ fmtMoeda(impacto.despesa_anual ?? 0) }}</span><span class="kc-lbl">Despesa Anual Pessoal</span></div>
      </div>

      <!-- Tabela detalhada por servidor -->
      <h3 class="sub-title">Impacto por Servidor</h3>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th>Servidor</th><th>Cargo</th><th>Classe</th><th>Ref. Atual</th><th>Nova Ref.</th>
            <th>Salário Atual</th><th>Novo Salário</th><th>Diferença</th>
          </tr></thead>
          <tbody>
            <tr v-for="d in (impacto.detalhes ?? [])" :key="d.id">
              <td class="td-nome">{{ d.nome }}</td>
              <td>{{ d.cargo }}</td>
              <td><span class="badge-classe">{{ d.classe }}</span></td>
              <td><code class="ref-badge">{{ d.ref_atual }}</code></td>
              <td><code class="ref-badge ref-new">{{ d.ref_nova }}</code></td>
              <td class="td-money">R$ {{ fmtMoeda(d.salario_atual) }}</td>
              <td class="td-money td-new">R$ {{ fmtMoeda(d.novo_salario) }}</td>
              <td class="td-aumento">+R$ {{ fmtMoeda(d.diferenca) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Configurar RCL -->
      <div class="rcl-config">
        <h3 class="sub-title">Atualizar RCL e Folha do Município</h3>
        <div class="rcl-form">
          <div class="rcl-field"><label>Ano</label><input v-model.number="formRcl.ano" class="field-input" type="number" /></div>
          <div class="rcl-field"><label>RCL Anual (R$)</label><input v-model.number="formRcl.rcl" class="field-input" type="number" /></div>
          <div class="rcl-field"><label>Folha Mensal Atual (R$)</label><input v-model.number="formRcl.folha_mensal" class="field-input" type="number" /></div>
          <button class="act-btn act-primary" @click="salvarRcl" :disabled="salvandoRcl">{{ salvandoRcl ? '⏳' : '💾 Salvar' }}</button>
        </div>
      </div>
    </div>

    <!-- ABA 4: Tabela Salarial (TASK-14) -->
    <div v-if="abaAtiva === 'tabela'" class="card" :class="{ loaded }">
      <div class="card-hdr">
        <h2 class="card-title">📊 Tabela Salarial por Carreira</h2>
        <div style="display:flex;gap:8px;align-items:center">
          <input v-model="tabelaBusca" class="search-input" placeholder="🔍 Buscar cargo ou carreira..." />
          <button class="act-btn act-outline" @click="carregarTabelaSalarial">🔄 Atualizar</button>
        </div>
      </div>

      <div v-if="carregandoTabela" class="loading-txt">⏳ Carregando tabela salarial...</div>
      <div v-else-if="!cargosTabela.length" class="empty-msg">
        Nenhum cargo cadastrado. Execute a migration e adicione cargos.
      </div>
      <template v-else>
        <div v-for="([carreira, cargos]) in cargosByCarreira" :key="carreira" class="carreira-group">
          <div class="cg-header">
            <span class="cg-title">{{ carreira }}</span>
            <span class="cg-count">{{ cargos.length }} cargo(s)</span>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr>
                <th>Cargo</th><th>Classe</th><th>Ref.</th>
                <th>Vencimento Base</th><th>CH (h/mês)</th><th>CBO</th><th></th>
              </tr></thead>
              <tbody>
                <tr v-for="c in cargos" :key="c.cargo_id">
                  <!-- Modo visualização -->
                  <template v-if="editandoCargoId !== c.cargo_id">
                    <td class="td-nome">{{ c.nome }}</td>
                    <td><span class="badge-classe">{{ c.classe || '—' }}</span></td>
                    <td><code class="ref-badge">{{ c.referencia || '—' }}</code></td>
                    <td class="td-money td-new">{{ c.salario_base != null ? 'R$ ' + fmtMoeda(c.salario_base) : '—' }}</td>
                    <td class="td-meses">{{ c.carga_horaria ? c.carga_horaria + 'h' : '—' }}</td>
                    <td class="td-cargo">{{ c.cbo || '—' }}</td>
                    <td><button class="mini-btn" title="Editar" @click="iniciarEdicaoCargo(c)">✏️</button></td>
                  </template>
                  <!-- Modo edição inline -->
                  <template v-else>
                    <td><input v-model="editandoCargo.nome" class="field-input-sm" disabled /></td>
                    <td><input v-model="editandoCargo.classe" class="field-input-sm" placeholder="A, B..." /></td>
                    <td><input v-model="editandoCargo.referencia" class="field-input-sm" placeholder="1, 2..." /></td>
                    <td><input v-model.number="editandoCargo.salario_base" class="field-input-sm" type="number" step="0.01" placeholder="0,00" /></td>
                    <td><input v-model.number="editandoCargo.carga_horaria" class="field-input-sm" type="number" placeholder="200" /></td>
                    <td><input v-model="editandoCargo.cbo" class="field-input-sm" placeholder="13124" /></td>
                    <td style="display:flex;gap:4px">
                      <button class="act-btn act-primary act-sm" :disabled="salvandoTabela" @click="salvarCargo">
                        {{ salvandoTabela ? '⏳' : '💾' }}
                      </button>
                      <button class="act-btn act-outline act-sm" @click="cancelarEdicaoCargo">✕</button>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- ABA 5: Histórico de Aprovações (BUG-EST-13) -->
    <div v-if="abaAtiva === 'historico'" class="card" :class="{ loaded }">
      <div class="card-hdr">
        <h2 class="card-title">📜 Histórico de Aprovações</h2>
        <div class="card-acts">
          <input v-model="buscaHist" class="search-input" placeholder="🔍 Servidor ou ato..." @input="buscarHistorico(1)" />
        </div>
      </div>

      <div v-if="carregandoHist" class="loading-txt">⏳ Carregando histórico...</div>
      <div v-else-if="!historico.itens?.length" class="empty-msg">
        Nenhum registro de progressão encontrado ainda.
        <span style="display:block;font-size:12px;color:#94a3b8;margin-top:4px">As aprovações futuras aparecerão aqui.</span>
      </div>
      <div v-else>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr>
              <th>Tipo</th>
              <th>Servidor</th>
              <th>Cargo</th>
              <th>Classe/Ref. De → Para</th>
              <th>Salário De → Para</th>
              <th>Ato Administrativo</th>
              <th>Aprovado por</th>
              <th>Data</th>
            </tr></thead>
            <tbody>
              <tr v-for="h in historico.itens" :key="h.id">
                <td>
                  <span class="status-badge" :class="h.tipo === 'promocao' ? 'sb-prom' : 'sb-ok'">
                    {{ h.tipo === 'promocao' ? '⬆️ Promoção' : '📈 Progressão' }}
                  </span>
                </td>
                <td class="td-nome">{{ h.servidor }}</td>
                <td class="td-cargo">{{ h.cargo ?? '—' }}</td>
                <td>
                  <code class="ref-badge">{{ h.classe_de ?? '—' }}/{{ h.ref_de ?? '—' }}</code>
                  <span style="color:#94a3b8;margin:0 4px">→</span>
                  <code class="ref-badge ref-new">{{ h.classe_para ?? '—' }}/{{ h.ref_para ?? '—' }}</code>
                </td>
                <td class="td-money">
                  <span style="color:#94a3b8">{{ h.salario_de ? 'R$ ' + fmtMoeda(h.salario_de) : '—' }}</span>
                  <span v-if="h.salario_para"> → <strong>R$ {{ fmtMoeda(h.salario_para) }}</strong></span>
                </td>
                <td style="font-size:12px;color:#475569">{{ h.ato ?? '—' }}</td>
                <td style="font-size:12px;color:#64748b">{{ h.aprovador ?? 'Sistema' }}</td>
                <td style="font-size:12px;white-space:nowrap">{{ h.data ? new Date(h.data).toLocaleDateString('pt-BR') : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Paginação -->
        <div class="hist-pag" v-if="historico.total_paginas > 1">
          <button class="act-btn act-outline" :disabled="historico.pagina <= 1" @click="buscarHistorico(historico.pagina - 1)">‹ Anterior</button>
          <span style="font-size:13px;color:#64748b">{{ historico.pagina }} / {{ historico.total_paginas }} ({{ historico.total }} registros)</span>
          <button class="act-btn act-outline" :disabled="historico.pagina >= historico.total_paginas" @click="buscarHistorico(historico.pagina + 1)">Próximo ›</button>
        </div>
      </div>
    </div>

    <!-- ABA 6: Configurações -->
    <div v-if="abaAtiva === 'config'" class="card" :class="{ loaded }">
      <div class="card-hdr"><h2 class="card-title">⚙️ Configuração do Motor</h2></div>
      <div class="config-section">
        <h3 class="sub-title">Parâmetros Globais</h3>
        <div class="config-grid">
          <div class="cfg-field"><label>Interstício (meses)</label><input v-model.number="cfg.intersticio" class="field-input" type="number" /></div>
          <div class="cfg-field"><label>Nota Mínima de Avaliação</label><input v-model.number="cfg.nota_minima" class="field-input" type="number" step="0.1" /></div>
          <div class="cfg-field"><label>Anuênio (% por ano)</label><input v-model.number="cfg.anuenio_pct" class="field-input" type="number" step="0.1" /></div>
        </div>
        <button class="act-btn act-primary" @click="salvarConfig" :disabled="salvandoCfg">{{ salvandoCfg ? '⏳' : '💾 Salvar Config' }}</button>
        <div v-if="cfgOk" class="ok-msg" style="margin-top:8px">{{ cfgOk }}</div>
      </div>

      <div class="config-section">
        <div class="sub-hdr"><h3 class="sub-title">Carreiras Cadastradas</h3>
          <button class="act-btn act-primary act-sm" @click="modalCarreira = true">+ Nova Carreira</button>
        </div>
        <div class="carreira-list">
          <div v-for="c in carreiras" :key="c.id" class="carreira-item">
            <span class="carreira-nome">{{ c.nome }}</span>
            <span class="regime-badge" :class="c.regime === 'efetivo' ? 'rb-blue' : 'rb-orange'">{{ c.regime }}</span>
          </div>
          <div v-if="!carreiras.length" class="empty-msg">Nenhuma carreira cadastrada.</div>
        </div>
      </div>
    </div>

    <!-- Modal Nova Carreira -->
    <transition name="modal">
      <div v-if="modalCarreira" class="modal-overlay" @click.self="modalCarreira = false">
        <div class="modal-card">
          <div class="modal-hdr"><h3>Nova Carreira</h3><button class="modal-close" @click="modalCarreira = false">✕</button></div>
          <div class="modal-body">
            <label class="field-lbl">Nome da Carreira</label>
            <input v-model="novaCarreira.nome" class="field-input" placeholder="Ex: Magistério Municipal" />
            <label class="field-lbl" style="margin-top:10px">Regime</label>
            <select v-model="novaCarreira.regime" class="field-input">
              <option value="efetivo">Efetivo</option>
              <option value="comissionado">Comissionado</option>
            </select>
            <div class="modal-actions" style="margin-top:14px">
              <button class="modal-cancel" @click="modalCarreira = false">Cancelar</button>
              <button class="modal-submit" @click="criarCarreira" :disabled="criandoCarreira">{{ criandoCarreira ? '⏳' : 'Criar' }}</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toast -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.type">{{ toast.ico }} {{ toast.msg }}</div>
    </transition>

    <!-- Modal Aplicar em Lote (BUG-EST-04) -->
    <transition name="modal">
      <div v-if="modalLote.visible" class="modal-overlay" @click.self="modalLote.visible = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>⚡ Aplicar Progressão em Lote</h3>
            <button class="modal-close" @click="modalLote.visible = false">✕</button>
          </div>
          <div class="modal-body">
            <p style="font-size:14px;color:#475569;margin:0 0 14px">
              Confirmar progressão para <strong>{{ selecionados.length }} servidor(es)</strong>?
            </p>
            <label class="field-lbl">Ato Administrativo (opcional)</label>
            <input v-model="modalLote.ato" class="field-input" placeholder="Ex: Portaria 023/2026" />
            <div v-if="modalLote.erro" class="err-msg">⚠️ {{ modalLote.erro }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalLote.visible = false">Cancelar</button>
              <button class="modal-submit" @click="confirmarLote" :disabled="modalLote.salvando">
                {{ modalLote.salvando ? '⏳ Aplicando...' : 'Confirmar' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/plugins/axios'

const loaded      = ref(false)
const carregando  = ref(false)
const abaAtiva    = ref('elegiveis')
const buscaTodos  = ref('')
const selecionados= ref([])
const aplicandoLote = ref(false)
const aplicandoId   = ref(null)
const salvandoRcl   = ref(false)
const salvandoCfg   = ref(false)
const criandoCarreira = ref(false)
const cfgOk       = ref('')
const modalCarreira = ref(false)
const novaCarreira  = ref({ nome: '', regime: 'efetivo' })

const abas = [
  { id: 'elegiveis', ico: '✅', label: 'Elegíveis' },
  { id: 'todos',     ico: '👥', label: 'Todos' },
  { id: 'impacto',   ico: '💰', label: 'Impacto LRF' },
  { id: 'tabela',    ico: '📊', label: 'Tabela Salarial' },
  { id: 'historico', ico: '📜', label: 'Histórico' }, // BUG-EST-13
  { id: 'config',    ico: '⚙️', label: 'Configuração' },
]

const now = new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })

// Dados
const elegiveis  = ref([])
const todos      = ref([])
const impacto    = ref({})
const carreiras  = ref([])
const cfg        = ref({ intersticio: 24, nota_minima: 7.0, anuenio_pct: 1.0 })
const formRcl    = ref({ ano: new Date().getFullYear(), rcl: 0, folha_mensal: 0 })
// BUG-EST-12: filtro de setor
const filtroSetor  = ref('')
const setores      = ref([])
// BUG-EST-13: histórico
const historico    = ref({ itens: [], total: 0, pagina: 1, total_paginas: 1 })
const buscaHist    = ref('')
const carregandoHist = ref(false)

// ── Tabela Salarial ──────────────────────────────────────────────
const cargosTabela     = ref([])
const carregandoTabela = ref(false)
const tabelaBusca      = ref('')
const editandoCargoId  = ref(null)
const editandoCargo    = ref({})
const salvandoTabela   = ref(false)

const cargosByCarreira = computed(() => {
  const lista = tabelaBusca.value
    ? cargosTabela.value.filter(c =>
        (c.nome ?? '').toLowerCase().includes(tabelaBusca.value.toLowerCase()) ||
        (c.carreira ?? '').toLowerCase().includes(tabelaBusca.value.toLowerCase())
      )
    : cargosTabela.value
  const grupos = {}
  for (const c of lista) {
    const k = c.carreira || 'Outros'
    if (!grupos[k]) grupos[k] = []
    grupos[k].push(c)
  }
  return Object.entries(grupos).sort(([a], [b]) => a.localeCompare(b))
})

const carregarTabelaSalarial = async () => {
  carregandoTabela.value = true
  try {
    const { data } = await api.get('/api/v3/cargos', { params: { ativo: 1 } })
    cargosTabela.value = data.cargos ?? []
  } catch { cargosTabela.value = [] }
  finally { carregandoTabela.value = false }
}

const iniciarEdicaoCargo = (c) => {
  editandoCargoId.value = c.cargo_id
  editandoCargo.value = { ...c }
}

const cancelarEdicaoCargo = () => {
  editandoCargoId.value = null
  editandoCargo.value = {}
}

const salvarCargo = async () => {
  salvandoTabela.value = true
  try {
    await api.put(`/api/v3/cargos/${editandoCargoId.value}`, {
      CARGO_CARREIRA:    editandoCargo.value.carreira,
      CARGO_CLASSE:      editandoCargo.value.classe,
      CARGO_REFERENCIA:  editandoCargo.value.referencia,
      CARGO_SALARIO_BASE: editandoCargo.value.salario_base,
      CARGO_CARGA_HORARIA: editandoCargo.value.carga_horaria,
      CARGO_CODIGO_CBO:  editandoCargo.value.cbo,
    })
    mostrarToast('success', '✅', 'Cargo atualizado!')
    editandoCargoId.value = null
    await carregarTabelaSalarial()
  } catch (e) {
    mostrarToast('error', '❌', e.response?.data?.erro || 'Erro ao salvar.')
  } finally { salvandoTabela.value = false }
}

const modalAto = ref({ visible: false, id: null, nome: '', ato: '', salvando: false, erro: '', ok: '' })
const toast    = ref({ visible: false, type: '', ico: '', msg: '' })

const mostrarToast = (type, ico, msg) => {
  toast.value = { visible: true, type, ico, msg }
  setTimeout(() => { toast.value.visible = false }, 3000)
}

const fmtMoeda = (v) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(Number(v) || 0)

const todosFiltered = computed(() => {
  let list = todos.value
  if (filtroSetor.value) {
    // BUG-EST-12: filtro local por setor (setor_nome vem do backend via join)
    list = list.filter(s => String(s.setor_id) === String(filtroSetor.value))
  }
  if (buscaTodos.value) {
    const q = buscaTodos.value.toLowerCase()
    list = list.filter(s => s.nome?.toLowerCase().includes(q) || s.cargo?.toLowerCase().includes(q))
  }
  return list
})

const lrfClass = computed(() => {
  const s = impacto.value.status_lrf ?? 'seguro'
  return { 'hb-green': s === 'seguro', 'hb-yellow': s === 'alerta', 'hb-orange': s === 'limite_prudencial', 'hb-red': s === 'limite_excedido' }
})

const carregarElegiveis = async () => {
  carregando.value = true
  try {
    const { data } = await api.get('/api/v3/progressao-funcional/lista-elegiveis')
    elegiveis.value = data.elegiveis ?? []
  } catch { elegiveis.value = [] } finally { carregando.value = false }
}

const carregarTodos = async () => {
  try {
    const { data } = await api.get('/api/v3/progressao-funcional/admin')
    todos.value = data.servidores ?? []
  } catch { todos.value = [] }
}

const carregarImpacto = async () => {
  try {
    const { data } = await api.get('/api/v3/progressao-funcional/impacto')
    impacto.value = data
    formRcl.value.rcl = data.rcl ?? 0
    formRcl.value.folha_mensal = data.folha_atual ?? 0
  } catch { impacto.value = {} }
}

const carregarSetores = async () => {
  try {
    const { data } = await api.get('/api/v3/setores')
    setores.value = data.itens ?? data.setores ?? data ?? []
  } catch { setores.value = [] }
}

const buscarHistorico = async (pagina = 1) => {
  carregandoHist.value = true
  try {
    const { data } = await api.get('/api/v3/progressao-funcional/historico', {
      params: { page: pagina, per_page: 20, busca: buscaHist.value || undefined }
    })
    historico.value = data
  } catch { historico.value = { itens: [], total: 0, pagina: 1, total_paginas: 1 } }
  finally { carregandoHist.value = false }
}

const carregarConfig = async () => {
  try {
    const { data } = await api.get('/api/v3/progressao-funcional/carreiras')
    carreiras.value = data.carreiras ?? []
    const c = (data.configs ?? []).find(c => !c.CARREIRA_ID) ?? {}
    cfg.value = {
      intersticio:  c.CONFIG_INTERSTICIO_MESES ?? 24,
      nota_minima:  c.CONFIG_NOTA_MINIMA ?? 7.0,
      anuenio_pct:  c.CONFIG_ANUENIO_PCT ?? 1.0,
    }
  } catch { carreiras.value = [] }
}

onMounted(async () => {
  await Promise.all([carregarElegiveis(), carregarTodos(), carregarImpacto(), carregarConfig(), carregarTabelaSalarial(), carregarSetores()])
  setTimeout(() => { loaded.value = true }, 80)
})

watch(abaAtiva, (v) => {
  if (v === 'tabela') carregarTabelaSalarial()
  if (v === 'historico') buscarHistorico() // BUG-EST-13
})

const toggleAll = (e) => {
  selecionados.value = e.target.checked ? elegiveis.value.map(s => s.id) : []
}

// BUG-EST-04: modal de lote sem window.confirm()
const modalLote = ref({ visible: false, ato: '', salvando: false, erro: '' })

const aplicarUm = (s) => {
  modalAto.value = { visible: true, id: s.id, nome: s.nome, ato: '', salvando: false, erro: '', ok: '' }
}

const confirmarAplicacao = async () => {
  modalAto.value.salvando = true; modalAto.value.erro = ''; modalAto.value.ok = ''
  try {
    await api.post(`/api/v3/progressao-funcional/aplicar/${modalAto.value.id}`, { ato: modalAto.value.ato })
    modalAto.value.ok = 'Progressão aplicada com sucesso!'
    mostrarToast('success', '✅', 'Progressão aplicada!')
    setTimeout(async () => {
      modalAto.value.visible = false
      await Promise.all([carregarElegiveis(), carregarTodos(), carregarImpacto()])
    }, 1200)
  } catch (e) {
    const msg = e.response?.data?.erro || 'Erro ao aplicar progressão.'
    const teto = e.response?.data?.teto ?? false
    modalAto.value.erro = teto
      ? '⚠️ Servidor no teto da carreira. Use "Promover" para mudar de classe.'
      : msg
  }
  finally { modalAto.value.salvando = false }
}

const aplicarLote = () => {
  if (selecionados.value.length === 0) return
  modalLote.value = { visible: true, ato: '', salvando: false, erro: '' }
}

const confirmarLote = async () => {
  modalLote.value.salvando = true; modalLote.value.erro = ''
  aplicandoLote.value = true
  let ok = 0
  const detalhes = [] // BUG-EST-05: coletar detalhes de cada erro
  for (const id of selecionados.value) {
    try {
      await api.post(`/api/v3/progressao-funcional/aplicar/${id}`, { ato: modalLote.value.ato })
      ok++
    } catch (e) {
      const err = e.response?.data
      const teto = err?.teto ?? false
      const nome = elegiveis.value.find(s => s.id === id)?.nome ?? `#${id}`
      detalhes.push(teto ? `${nome}: no teto da carreira` : `${nome}: ${err?.bloqueios?.[0] ?? 'erro'}`)
    }
  }
  if (detalhes.length) {
    modalLote.value.erro = `${detalhes.length} servidor(es) não processado(s): ${detalhes.join(' | ')}`
    mostrarToast('success', '⚠️', `${ok} aplicada(s). ${detalhes.length} erro(s) — veja o modal.`)
  } else {
    mostrarToast('success', '✅', `${ok} progressões aplicadas com sucesso!`)
    modalLote.value.visible = false
  }
  if (ok > 0) selecionados.value = selecionados.value.filter(id => !selecionados.value.includes(id))
  selecionados.value = detalhes.length ? selecionados.value : []
  aplicandoLote.value = false
  modalLote.value.salvando = false
  await Promise.all([carregarElegiveis(), carregarTodos(), carregarImpacto()])
}

const salvarRcl = async () => {
  salvandoRcl.value = true
  try {
    await api.put('/api/v3/progressao-funcional/receita', formRcl.value)
    mostrarToast('success', '✅', 'RCL atualizada!')
    await carregarImpacto()
  } catch (e) { mostrarToast('error', '❌', e.response?.data?.erro || 'Erro ao salvar RCL.') }
  finally { salvandoRcl.value = false }
}

const salvarConfig = async () => {
  salvandoCfg.value = true; cfgOk.value = ''
  try {
    await api.put('/api/v3/progressao-funcional/config', cfg.value)
    cfgOk.value = 'Configuração salva!'
    mostrarToast('success', '✅', 'Configuração salva!')
  } catch (e) { mostrarToast('error', '❌', 'Erro ao salvar.') }
  finally { salvandoCfg.value = false }
}

const criarCarreira = async () => {
  criandoCarreira.value = true
  try {
    await api.post('/api/v3/progressao-funcional/carreiras', novaCarreira.value)
    mostrarToast('success', '✅', 'Carreira criada!')
    modalCarreira.value = false
    novaCarreira.value = { nome: '', regime: 'efetivo' }
    await carregarConfig()
  } catch (e) { mostrarToast('error', '❌', 'Erro ao criar carreira.') }
  finally { criandoCarreira.value = false }
}

const exportarLista = () => {
  const rows = [['Nome','Cargo','Classe','Ref. Atual','Nova Ref.','Salário Atual','Novo Salário','Aumento']]
  elegiveis.value.forEach(s => rows.push([s.nome, s.cargo, s.classe, s.referencia, s.proxima_ref ?? '', s.salario_atual, s.novo_vencimento ?? '', s.aumento]))
  const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n')
  const a = document.createElement('a')
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent('\uFEFF' + csv)
  a.download = `elegiveis_progressao_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<style scoped>
.pa-page { display: flex; flex-direction: column; gap: 16px; font-family: 'Inter', system-ui, sans-serif; }
.hero { border-radius: 22px; padding: 26px 32px; background: linear-gradient(135deg, #0f172a 0%, #1e1060 55%, #0f172a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.45s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; margin: 0 0 3px; letter-spacing: -0.02em; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-badges { display: flex; gap: 12px; flex-wrap: wrap; }
.hb-item { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 12px 18px; text-align: center; min-width: 100px; }
.hb-num { display: block; font-size: 20px; font-weight: 900; color: #fff; }
.hb-lbl { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-top: 2px; }
.hb-blue .hb-num { color: #93c5fd; }
.hb-green .hb-num { color: #6ee7b7; }
.hb-yellow .hb-num { color: #fde68a; }
.hb-orange .hb-num { color: #fdba74; }
.hb-red .hb-num { color: #fca5a5; }
.tabs { display: flex; gap: 6px; opacity: 0; transform: translateY(4px); transition: all 0.3s 0.05s; }
.tabs.loaded { opacity: 1; transform: none; }
.tab-btn { padding: 9px 18px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.15s; }
.tab-btn.active { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.tab-btn:hover:not(.active) { border-color: #a5b4fc; color: #4f46e5; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; opacity: 0; transform: translateY(6px); transition: all 0.35s 0.08s; }
.card.loaded { opacity: 1; transform: none; }
.card-hdr { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.card-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.card-acts { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.hist-pag { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; } /* BUG-EST-13 */
.act-btn { padding: 9px 16px; border-radius: 12px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.act-primary { background: linear-gradient(135deg,#4f46e5,#6366f1); color: #fff; }
.act-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.act-outline { background: #f8fafc; border: 1.5px solid #e2e8f0; color: #475569; }
.act-sm { padding: 6px 12px; font-size: 12px; }
.search-input { padding: 8px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 13px; outline: none; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th { text-align: left; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; padding: 0 10px 10px; border-bottom: 2px solid #f1f5f9; }
.data-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-table tr:hover td { background: #f8fafc; }
.td-nome { font-weight: 700; color: #1e293b; max-width: 180px; }
.td-cargo { color: #475569; font-size: 12px; }
.td-money { font-family: monospace; font-weight: 700; color: #475569; }
.td-new { color: #059669; }
.td-aumento { font-family: monospace; font-weight: 800; color: #059669; }
.td-meses { font-size: 12px; color: #94a3b8; text-align: center; }
.td-bloq { font-size: 11px; color: #64748b; max-width: 240px; }
.badge-classe { background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; }
.ref-badge { background: #f1f5f9; color: #475569; font-size: 11px; padding: 3px 8px; border-radius: 6px; font-family: monospace; }
.ref-new { background: #dcfce7; color: #166534; }
.carreira-tag { font-size: 10px; color: #7c3aed; font-weight: 700; background: #f3e8ff; padding: 1px 6px; border-radius: 4px; }
.nota-badge { font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 8px; }
.nb-ok { background: #dcfce7; color: #166534; }
.nb-bad { background: #fee2e2; color: #991b1b; }
.nb-null { background: #f1f5f9; color: #94a3b8; }
.mini-btn { width: 28px; height: 28px; border: 1.5px solid #e2e8f0; background: #f8fafc; border-radius: 8px; cursor: pointer; font-size: 13px; }
.mini-btn:hover { background: #eff6ff; border-color: #93c5fd; }
.status-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; white-space: nowrap; }
.sb-ok { background: #dcfce7; color: #166534; }
.sb-prom { background: #ede9fe; color: #5b21b6; }
.sb-block { background: #fee2e2; color: #991b1b; }
.bloq-item { font-size: 11px; color: #64748b; margin-bottom: 2px; }
.ok-txt { font-size: 11px; color: #16a34a; }
.prom-txt { font-size: 11px; color: #7c3aed; }
/* LRF Panel */
.lrf-panel { border: 2px solid #e2e8f0; border-radius: 16px; padding: 18px; margin-bottom: 16px; }
.lrf-seguro { border-color: #86efac; background: #f0fdf4; }
.lrf-alerta { border-color: #fde68a; background: #fffbeb; }
.lrf-limite_prudencial { border-color: #fdba74; background: #fff7ed; }
.lrf-limite_excedido { border-color: #fca5a5; background: #fef2f2; }
.lrf-gauge-wrap { margin-bottom: 10px; }
.lrf-gauge { height: 14px; background: #f1f5f9; border-radius: 99px; overflow: hidden; position: relative; }
.lrf-fill { height: 100%; border-radius: 99px; transition: width 1s cubic-bezier(0.22,1,0.36,1); }
.lrf-fill.lrf-seguro { background: #22c55e; }
.lrf-fill.lrf-alerta { background: #f59e0b; }
.lrf-fill.lrf-limite_prudencial { background: #f97316; }
.lrf-fill.lrf-limite_excedido { background: #ef4444; }
.lrf-status-row { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.lrf-pct-big { font-size: 28px; font-weight: 900; color: #1e293b; font-family: monospace; }
.lrf-status-badge { font-size: 13px; font-weight: 700; padding: 6px 14px; border-radius: 99px; background: rgba(255,255,255,0.7); }
/* KPI Grid */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); gap: 10px; margin-bottom: 20px; }
.kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 4px; }
.kci-highlight { border-color: #6366f1; background: #eef2ff; }
.kc-ico { font-size: 20px; }
.kc-val { font-size: 16px; font-weight: 900; color: #1e293b; font-family: monospace; }
.kc-lbl { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.sub-title { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 12px; }
.sub-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
/* RCL Config */
.rcl-config { margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
.rcl-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-top: 10px; }
.rcl-field { display: flex; flex-direction: column; gap: 4px; }
.rcl-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; }
/* Config Section */
.config-section { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; }
.config-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 12px; }
.cfg-field { display: flex; flex-direction: column; gap: 4px; }
.cfg-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; }
.field-lbl { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px; }
.field-input { padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 13px; outline: none; font-family: inherit; width: 100%; box-sizing: border-box; }
.field-input:focus { border-color: #6366f1; }
/* Carreiras */
.carreira-list { display: flex; flex-direction: column; gap: 8px; }
.carreira-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #f1f5f9; border-radius: 12px; }
.carreira-nome { font-weight: 700; color: #1e293b; font-size: 14px; flex: 1; }
.regime-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.rb-blue { background: #eff6ff; color: #1d4ed8; }
.rb-orange { background: #fff7ed; color: #c2410c; }
/* Outros */
.loading-txt { font-size: 14px; color: #94a3b8; text-align: center; padding: 24px; }
.empty-msg { font-size: 14px; color: #94a3b8; text-align: center; padding: 24px; }
.err-msg { font-size: 13px; font-weight: 600; color: #dc2626; margin-top: 6px; }
.ok-msg { font-size: 13px; font-weight: 600; color: #16a34a; }
/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 300; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 8px; }
.modal-actions { display: flex; gap: 10px; margin-top: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg,#4f46e5,#6366f1); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
.modal-submit:disabled { opacity: 0.5; }
.modal-enter-active,.modal-leave-active { transition: opacity 0.25s; }
.modal-enter-from,.modal-leave-to { opacity: 0; }
/* Toast */
.toast { position: fixed; bottom: 28px; right: 28px; z-index: 400; display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 14px; font-size: 14px; font-weight: 600; color: #fff; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast.success { background: #059669; }
.toast.error { background: #dc2626; }
.toast-enter-active,.toast-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from,.toast-leave-to { opacity: 0; transform: translateX(20px); }
/* Tabela Salarial */
.carreira-group { margin-bottom: 24px; }
.cg-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: linear-gradient(90deg,#f5f3ff,#eff6ff); border-radius: 12px; margin-bottom: 8px; }
.cg-title { font-size: 15px; font-weight: 800; color: #4f46e5; }
.cg-count { font-size: 11px; font-weight: 700; color: #94a3b8; background: #fff; padding: 3px 10px; border-radius: 99px; }
.field-input-sm { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; font-size: 12px; font-family: inherit; outline: none; width: 100%; box-sizing: border-box; }
.field-input-sm:focus { border-color: #6366f1; }
.field-input-sm:disabled { background: #f8fafc; color: #94a3b8; }
@media (max-width: 700px) { .config-grid { grid-template-columns: 1fr; } .hero-inner { flex-direction: column; } }
</style>
