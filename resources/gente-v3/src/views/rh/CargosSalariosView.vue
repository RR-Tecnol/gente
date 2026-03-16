<template>
  <div class="cs-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div><div class="hs hs3"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">RH · eSocial S-1030 / S-1040</span>
          <h1 class="hero-title">Cargos e Salários</h1>
          <p class="hero-sub">Estrutura PCCS aderente ao eSocial — {{ totalCargos }} cargo(s) · {{ totalFuncoes }} função(ões)</p>
        </div>
        <div class="esocial-badge">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Aderente ao eSocial</span>
        </div>
      </div>
    </div>

    <!-- ABAS -->
    <div class="tabs-bar" :class="{ loaded }">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: abaAtiva === tab.key }"
        @click="abaAtiva = tab.key"
      >
        <component :is="'svg'" v-html="tab.icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"></component>
        {{ tab.label }}
      </button>
    </div>

    <!-- ════════ ABA: CARGOS ════════ -->
    <div v-show="abaAtiva === 'cargos'" class="tab-content" :class="{ loaded }">

      <!-- toolbar -->
      <div class="toolbar">
        <div class="search-wrap">
          <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="buscaCargo" class="search-input" placeholder="Buscar por nome, sigla ou CBO..." @input="debounceCargos" />
        </div>
        <div class="toolbar-right">
          <select v-model="filtroAtivoCargo" class="filter-select" @change="fetchCargos">
            <option value="">Todos</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </select>
          <button class="btn-novo" @click="abrirModalCargo()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Cargo
          </button>
        </div>
      </div>

      <!-- LEGENDA eSocial -->
      <div class="esocial-note">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><strong>eSocial S-1030:</strong> O CBO (6 dígitos) é obrigatório para envio ao governo. Cargo de gestão = chefia/direção. Data início fecha o período do histórico.</span>
      </div>

      <!-- TABELA CARGOS -->
      <div class="table-card">
        <div v-if="loadingCargos" class="state-box"><div class="spinner indigo"></div><p>Carregando cargos...</p></div>
        <div v-else-if="cargos.length === 0" class="state-box">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
          <p>Nenhum cargo encontrado</p>
        </div>
        <table v-else class="cs-table">
          <thead>
            <tr>
              <th>Cargo</th>
              <th>CBO <span class="req">*</span></th>
              <th>Escolaridade</th>
              <th>Sal. Base</th>
              <th>Gestão</th>
              <th>Vigência</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cargos" :key="c.cargo_id" class="cs-row">
              <td>
                <span class="item-nome">{{ c.nome }}</span>
                <span v-if="c.sigla" class="item-sigla">{{ c.sigla }}</span>
                <span v-if="c.descricao" class="item-desc">{{ c.descricao }}</span>
              </td>
              <td>
                <code v-if="c.cbo" class="cbo-chip">{{ c.cbo }}</code>
                <span v-else class="missing">⚠ Sem CBO</span>
              </td>
              <td>{{ escolaridadeLabel(c.escolaridade) || '—' }}</td>
              <td>
                <span v-if="c.remuneracao" class="money">{{ formatMoney(c.remuneracao) }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="c.gestao" class="badge-gestao">✦ Gestão</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="text-muted">{{ c.data_inicio ? formatDate(c.data_inicio) : '—' }}</td>
              <td>
                <span class="status-badge" :class="c.ativo ? 'badge-green' : 'badge-red'">
                  <span class="badge-dot"></span>{{ c.ativo ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-purple" title="Editar" @click="abrirModalCargo(c)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                  <button v-if="c.ativo" class="act-btn act-red" title="Inativar" @click="inativarCargo(c)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ════════ ABA: FUNÇÕES ════════ -->
    <div v-show="abaAtiva === 'funcoes'" class="tab-content" :class="{ loaded }">
      <div class="toolbar">
        <div class="search-wrap">
          <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="buscaFuncao" class="search-input" placeholder="Buscar função ou cargo em comissão..." @input="debounceFuncoes" />
        </div>
        <button class="btn-novo btn-teal" @click="abrirModalFuncao()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nova Função
        </button>
      </div>

      <div class="esocial-note teal">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><strong>eSocial S-1040:</strong> Funções são posições <em>acima</em> do cargo base, remuneradas com gratificação. Tipo 1=Função de Confiança · Tipo 2=Cargo em Comissão.</span>
      </div>

      <div class="table-card">
        <div v-if="loadingFuncoes" class="state-box"><div class="spinner teal"></div><p>Carregando funções...</p></div>
        <div v-else-if="funcoes.length === 0" class="state-box">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
          <p>Nenhuma função/comissão cadastrada</p>
        </div>
        <table v-else class="cs-table">
          <thead>
            <tr>
              <th>Função / Cargo em Comissão</th>
              <th>CBO</th>
              <th>Tipo (eSocial)</th>
              <th>Gratificação</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in funcoes" :key="f.funcao_id" class="cs-row">
              <td><span class="item-nome">{{ f.nome }}</span></td>
              <td><code v-if="f.cbo" class="cbo-chip">{{ f.cbo }}</code><span v-else class="text-muted">—</span></td>
              <td>
                <span v-if="f.tipo == 1" class="tipo-badge tipo-confianca">Confiança</span>
                <span v-else-if="f.tipo == 2" class="tipo-badge tipo-comissao">Comissão</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td><span v-if="f.gratificacao" class="money">{{ formatMoney(f.gratificacao) }}</span><span v-else class="text-muted">—</span></td>
              <td><span class="status-badge" :class="f.ativo ? 'badge-green' : 'badge-red'"><span class="badge-dot"></span>{{ f.ativo ? 'Ativa' : 'Inativa' }}</span></td>
              <td>
                <div class="row-actions">
                  <button class="act-btn act-purple" title="Editar" @click="abrirModalFuncao(f)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                  <button v-if="f.ativo" class="act-btn act-red" title="Inativar" @click="inativarFuncao(f)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ════════ ABA: FAIXAS SALARIAIS (PCCS) ════════ -->
    <div v-show="abaAtiva === 'faixas'" class="tab-content" :class="{ loaded }">
      <div class="esocial-note amber">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><strong>PCCS — Plano de Cargos, Carreiras e Salários:</strong> As faixas são internas ao sistema (não enviadas ao eSocial). O eSocial S-2200 usa apenas o salário contratual final (<code>vrSalFx</code>).</span>
      </div>

      <!-- Grade PCCS gerada a partir dos cargos e suas remunerações -->
      <div class="table-card">
        <div class="pccs-header">
          <h3 class="pccs-title">Grade Salarial por Cargo</h3>
          <p class="pccs-sub">Remuneração base cadastrada em cada cargo. Para faixas detalhadas (mín/ref/máx), adicione a coluna CARGO_REMUNERACAO_MIN/MAX ao banco.</p>
        </div>
        <table class="cs-table">
          <thead>
            <tr>
              <th>Cargo</th>
              <th>CBO</th>
              <th>Escolaridade Mínima</th>
              <th>Salário Base</th>
              <th>Tipo</th>
              <th>Vigência</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cargosPccs" :key="c.cargo_id" class="cs-row">
              <td>
                <span class="item-nome">{{ c.nome }}</span>
                <span v-if="c.sigla" class="item-sigla">{{ c.sigla }}</span>
              </td>
              <td><code v-if="c.cbo" class="cbo-chip">{{ c.cbo }}</code><span v-else class="missing">⚠ Sem CBO</span></td>
              <td>{{ escolaridadeLabel(c.escolaridade) || '—' }}</td>
              <td>
                <span v-if="c.remuneracao" class="money-big">{{ formatMoney(c.remuneracao) }}</span>
                <span v-else class="missing">Não informado</span>
              </td>
              <td>
                <span v-if="c.gestao" class="badge-gestao">Gestão</span>
                <span v-else class="tipo-badge" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe">Operacional</span>
              </td>
              <td class="text-muted">{{ c.data_inicio ? formatDate(c.data_inicio) : 'Sem data' }}</td>
            </tr>
            <tr v-if="cargosPccs.length === 0">
              <td colspan="6" style="text-align:center;padding:40px;color:#94a3b8">
                Cadastre cargos com salário base na aba <strong>Cargos</strong> para ver a grade PCCS.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════ MODAL CARGO ══════ -->
    <teleport to="body">
      <transition name="modal-fade">
        <div v-if="modalCargo" class="modal-overlay" @click.self="modalCargo = false">
          <div class="modal-box">
            <div class="modal-header">
              <h2 class="modal-title">{{ formCargo._id ? 'Editar Cargo' : 'Novo Cargo' }}</h2>
              <button class="modal-close" @click="modalCargo = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">

              <div class="form-section">
                <h3 class="section-label">Identificação (eSocial S-1030)</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Nome do Cargo <span class="req">*</span></label>
                    <input v-model="formCargo.CARGO_NOME" type="text" class="form-input" placeholder="Ex: Enfermeiro Padrão" required />
                  </div>
                  <div class="form-group">
                    <label>Sigla</label>
                    <input v-model="formCargo.CARGO_SIGLA" type="text" class="form-input" placeholder="ENF" maxlength="10" />
                  </div>
                  <div class="form-group">
                    <label>CBO (6 dígitos) <span class="req">*</span></label>
                    <input v-model="formCargo.CARGO_CBO" type="text" class="form-input cbo-input" placeholder="225125" maxlength="6" />
                    <span class="field-hint">Obrigatório para o eSocial. Consulte <a href="https://cbo.mte.gov.br" target="_blank">cbo.mte.gov.br</a></span>
                  </div>
                  <div class="form-group col-full">
                    <label>Descrição / Atribuições</label>
                    <textarea v-model="formCargo.CARGO_DESCRICAO" class="form-input" rows="2" placeholder="Descreva as atribuições e responsabilidades do cargo..."></textarea>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <h3 class="section-label">Remuneração e Requisitos</h3>
                <div class="form-grid">
                  <div class="form-group">
                    <label>Salário Base (R$)</label>
                    <input v-model="formCargo.CARGO_REMUNERACAO" type="number" step="0.01" min="0" class="form-input" placeholder="0,00" />
                  </div>
                  <div class="form-group">
                    <label>Escolaridade Mínima</label>
                    <select v-model="formCargo.CARGO_ESCOLARIDADE" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Fundamental Incompleto</option>
                      <option value="2">Fundamental Completo</option>
                      <option value="3">Médio Incompleto</option>
                      <option value="4">Médio Completo</option>
                      <option value="5">Superior Incompleto</option>
                      <option value="6">Superior Completo</option>
                      <option value="7">Pós-Graduação</option>
                      <option value="8">Mestrado</option>
                      <option value="9">Doutorado</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Cargo de Gestão?</label>
                    <select v-model="formCargo.CARGO_GESTAO" class="form-input">
                      <option value="0">Não (operacional)</option>
                      <option value="1">Sim (chefia/direção)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Data de Vigência (início)</label>
                    <input v-model="formCargo.CARGO_DATA_INICIO" type="date" class="form-input" />
                    <span class="field-hint">Histórico imutável por período no eSocial</span>
                  </div>
                </div>
              </div>

            </div>
            <div class="modal-footer">
              <p v-if="erroModal" class="modal-erro">{{ erroModal }}</p>
              <p v-if="okModal" class="modal-sucesso">{{ okModal }}</p>
              <button class="btn-cancel" @click="modalCargo = false" :disabled="salvando">Cancelar</button>
              <button class="btn-salvar" @click="salvarCargo" :disabled="salvando">
                <svg v-if="salvando" class="spin" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0110 10" stroke-linecap="round"/></svg>
                {{ salvando ? 'Salvando...' : formCargo._id ? 'Salvar Alterações' : 'Criar Cargo' }}
              </button>
            </div>
          </div>
        </div>
      </transition>

      <!-- ══════ MODAL FUNÇÃO ══════ -->
      <transition name="modal-fade">
        <div v-if="modalFuncao" class="modal-overlay" @click.self="modalFuncao = false">
          <div class="modal-box modal-sm">
            <div class="modal-header">
              <h2 class="modal-title">{{ formFuncao._id ? 'Editar Função' : 'Nova Função / Comissão' }}</h2>
              <button class="modal-close" @click="modalFuncao = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
              <div class="form-section">
                <h3 class="section-label">Identificação (eSocial S-1040)</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Nome da Função <span class="req">*</span></label>
                    <input v-model="formFuncao.nome" type="text" class="form-input" placeholder="Ex: Coordenador de Enfermagem" />
                  </div>
                  <div class="form-group">
                    <label>CBO (6 dígitos)</label>
                    <input v-model="formFuncao.cbo" type="text" class="form-input cbo-input" placeholder="225125" maxlength="6" />
                    <span class="field-hint">Sobrepõe o CBO do cargo base no eSocial</span>
                  </div>
                  <div class="form-group">
                    <label>Tipo (eSocial)</label>
                    <select v-model="formFuncao.tipo" class="form-input">
                      <option value="">Não se aplica</option>
                      <option value="1">1 — Função de Confiança</option>
                      <option value="2">2 — Cargo em Comissão</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Gratificação (R$)</label>
                    <input v-model="formFuncao.gratificacao" type="number" step="0.01" min="0" class="form-input" placeholder="0,00" />
                    <span class="field-hint">Valor adicional ao salário do cargo base</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <p v-if="erroModal" class="modal-erro">{{ erroModal }}</p>
              <p v-if="okModal" class="modal-sucesso">{{ okModal }}</p>
              <button class="btn-cancel" @click="modalFuncao = false" :disabled="salvando">Cancelar</button>
              <button class="btn-salvar btn-teal-s" @click="salvarFuncao" :disabled="salvando">
                {{ salvando ? 'Salvando...' : formFuncao._id ? 'Salvar Alterações' : 'Criar Função' }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

// ── Abas ───────────────────────────────────────────────────
const abaAtiva = ref('cargos')
const tabs = [
  { key: 'cargos',  label: 'Cargos (S-1030)',     icon: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>' },
  { key: 'funcoes', label: 'Funções / Comissão (S-1040)', icon: '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>' },
  { key: 'faixas',  label: 'Grade Salarial (PCCS)', icon: '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>' },
]

// ── Estado ──────────────────────────────────────────────────
const loaded  = ref(false)
const cargos  = ref([])
const funcoes = ref([])
const loadingCargos  = ref(true)
const loadingFuncoes = ref(true)
const buscaCargo   = ref('')
const buscaFuncao  = ref('')
const filtroAtivoCargo = ref('1')

const totalCargos  = computed(() => cargos.value.length)
const totalFuncoes = computed(() => funcoes.value.length)
const cargosPccs   = computed(() => cargos.value.filter(c => c.ativo))

// ── Modal Cargo ───────────────────────────────────────────
const modalCargo = ref(false)
const modalFuncao= ref(false)
const salvando   = ref(false)
const erroModal  = ref('')
const okModal    = ref('')

const formCargoVazio = () => ({
  _id: null,
  CARGO_NOME: '', CARGO_SIGLA: '',
  CARGO_CBO: '', CARGO_DESCRICAO: '',
  CARGO_REMUNERACAO: '', CARGO_ESCOLARIDADE: '',
  CARGO_GESTAO: '0', CARGO_DATA_INICIO: '',
})
const formFuncaoVazio = () => ({
  _id: null, nome: '', cbo: '', tipo: '', gratificacao: '',
})
const formCargo  = ref(formCargoVazio())
const formFuncao = ref(formFuncaoVazio())

// ── Carregamento ──────────────────────────────────────────
onMounted(async () => {
  await Promise.all([fetchCargos(), fetchFuncoes()])
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchCargos = async () => {
  loadingCargos.value = true
  try {
    const params = {}
    if (buscaCargo.value) params.q = buscaCargo.value
    if (filtroAtivoCargo.value !== '') params.ativo = filtroAtivoCargo.value
    const { data } = await api.get('/api/v3/cargos', { params })
    cargos.value = data.cargos ?? data
  } catch {
    cargos.value = []
  } finally {
    loadingCargos.value = false
  }
}

const fetchFuncoes = async () => {
  loadingFuncoes.value = true
  try {
    const params = {}
    if (buscaFuncao.value) params.q = buscaFuncao.value
    const { data } = await api.get('/api/v3/funcoes', { params })
    funcoes.value = data.funcoes ?? data
  } catch {
    funcoes.value = []
  } finally {
    loadingFuncoes.value = false
  }
}

let timerC, timerF
const debounceCargos  = () => { clearTimeout(timerC); timerC = setTimeout(fetchCargos,  350) }
const debounceFuncoes = () => { clearTimeout(timerF); timerF = setTimeout(fetchFuncoes, 350) }

// ── Modal Cargo ───────────────────────────────────────────
const abrirModalCargo = (c = null) => {
  erroModal.value = ''; okModal.value = ''
  formCargo.value = c ? {
    _id: c.cargo_id, CARGO_NOME: c.nome ?? '',
    CARGO_SIGLA: c.sigla ?? '', CARGO_CBO: c.cbo ?? '',
    CARGO_DESCRICAO: c.descricao ?? '',
    CARGO_REMUNERACAO: c.remuneracao ?? '',
    CARGO_ESCOLARIDADE: c.escolaridade ?? '',
    CARGO_GESTAO: c.gestao ? '1' : '0',
    CARGO_DATA_INICIO: c.data_inicio ?? '',
  } : formCargoVazio()
  modalCargo.value = true
}

const salvarCargo = async () => {
  if (!formCargo.value.CARGO_NOME) { erroModal.value = 'O nome do cargo é obrigatório.'; return }
  salvando.value = true; erroModal.value = ''
  try {
    const payload = { ...formCargo.value }
    if (formCargo.value._id) {
      await api.put(`/api/v3/cargos/${formCargo.value._id}`, payload)
      okModal.value = 'Cargo atualizado!'
    } else {
      await api.post('/api/v3/cargos', payload)
      okModal.value = 'Cargo criado com sucesso!'
    }
    await fetchCargos()
    setTimeout(() => { modalCargo.value = false }, 1200)
  } catch (e) {
    erroModal.value = e.response?.data?.erro || 'Erro ao salvar.'
  } finally {
    salvando.value = false
  }
}

const inativarCargo = async (c) => {
  if (!confirm(`Inativar o cargo "${c.nome}"?`)) return
  try {
    await api.delete(`/api/v3/cargos/${c.cargo_id}`)
    await fetchCargos()
  } catch (e) { alert(e.response?.data?.erro || 'Erro ao inativar.') }
}

// ── Modal Função ──────────────────────────────────────────
const abrirModalFuncao = (f = null) => {
  erroModal.value = ''; okModal.value = ''
  formFuncao.value = f ? {
    _id: f.funcao_id, nome: f.nome, cbo: f.cbo ?? '',
    tipo: f.tipo ?? '', gratificacao: f.gratificacao ?? '',
  } : formFuncaoVazio()
  modalFuncao.value = true
}

const salvarFuncao = async () => {
  if (!formFuncao.value.nome) { erroModal.value = 'O nome da função é obrigatório.'; return }
  salvando.value = true; erroModal.value = ''
  try {
    if (formFuncao.value._id) {
      await api.put(`/api/v3/funcoes/${formFuncao.value._id}`, formFuncao.value)
      okModal.value = 'Função atualizada!'
    } else {
      await api.post('/api/v3/funcoes', formFuncao.value)
      okModal.value = 'Função criada!'
    }
    await fetchFuncoes()
    setTimeout(() => { modalFuncao.value = false }, 1200)
  } catch (e) {
    erroModal.value = e.response?.data?.erro || 'Erro ao salvar.'
  } finally {
    salvando.value = false
  }
}

const inativarFuncao = async (f) => {
  if (!confirm(`Inativar a função "${f.nome}"?`)) return
  try {
    await api.delete(`/api/v3/funcoes/${f.funcao_id}`)
    await fetchFuncoes()
  } catch (e) { alert(e.response?.data?.erro || 'Erro ao inativar.') }
}

// ── Helpers ───────────────────────────────────────────────
const escolaridadeLabel = (v) => ({
  1:'Fund. Incompleto',2:'Fund. Completo',3:'Médio Inc.',4:'Médio Completo',
  5:'Superior Inc.',6:'Superior Completo',7:'Pós-Graduação',8:'Mestrado',9:'Doutorado'
})[v] ?? null

const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const formatDate  = (d) => {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-')
  return `${day}/${m}/${y}`
}
</script>

<style scoped>
.cs-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero {
  position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0d3d34 100%);
  border-radius: 24px; padding: 32px 40px; overflow: hidden;
  opacity: 0; transform: translateY(-12px);
  transition: all 0.5s cubic-bezier(0.22,1,0.36,1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 280px; height: 280px; background: #6366f1; top: -80px; right: -60px; }
.hs2 { width: 200px; height: 200px; background: #10b981; bottom: -60px; right: 260px; }
.hs3 { width: 160px; height: 160px; background: #f59e0b; left: 35%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 6px; }
.hero-title { font-size: 30px; font-weight: 900; color: #fff; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.esocial-badge { display: flex; align-items: center; gap: 6px; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; padding: 8px 14px; font-size: 12px; font-weight: 700; color: #6ee7b7; }

/* ABAS */
.tabs-bar {
  display: flex; gap: 4px; background: #fff;
  border: 1px solid #e2e8f0; border-radius: 16px; padding: 6px;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.05s;
}
.tabs-bar.loaded { opacity: 1; transform: none; }
.tab-btn {
  display: flex; align-items: center; gap: 7px;
  padding: 9px 16px; border-radius: 12px; border: none; background: transparent;
  font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.18s;
  font-family: inherit; white-space: nowrap;
}
.tab-btn:hover { color: #1e293b; background: #f8fafc; }
.tab-btn.active { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }

/* TOOLBAR */
.toolbar { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 16px; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 10px; }
.search-ico { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
.search-input { flex: 1; border: none; font-size: 13px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.search-input::placeholder { color: #cbd5e1; }
.toolbar-right { display: flex; align-items: center; gap: 10px; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; cursor: pointer; }

.btn-novo { display: flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none; border-radius: 12px; padding: 9px 16px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: inherit; }
.btn-novo:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-teal { background: linear-gradient(135deg, #0d9488, #10b981) !important; }
.btn-teal:hover { box-shadow: 0 6px 18px rgba(16,185,129,0.4) !important; }

/* NOTE ESOCIAL */
.esocial-note { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; font-size: 12px; color: #3730a3; line-height: 1.5; }
.esocial-note.teal { background: #f0fdf4; border-color: #a7f3d0; color: #065f46; }
.esocial-note.amber { background: #fffbeb; border-color: #fde68a; color: #92400e; }
code { font-family: monospace; font-size: 11px; background: rgba(0,0,0,0.08); border-radius: 4px; padding: 1px 5px; }

/* TAB CONTENT */
.tab-content { display: flex; flex-direction: column; gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.35s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.tab-content.loaded { opacity: 1; transform: none; }

/* TABLE */
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; }
.state-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; color: #94a3b8; gap: 12px; }
.state-box p { font-size: 14px; font-weight: 500; margin: 0; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-radius: 50%; animation: spin 0.8s linear infinite; }
.spinner.indigo { border-top-color: #6366f1; }
.spinner.teal   { border-top-color: #0d9488; }
@keyframes spin { to { transform: rotate(360deg); } }
.cs-table { width: 100%; border-collapse: collapse; }
.cs-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.cs-table th { padding: 11px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; white-space: nowrap; }
.cs-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.cs-row:hover { background: #f8fafc; }
.cs-row:last-child { border-bottom: none; }
.cs-table td { padding: 13px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.item-nome { display: block; font-weight: 700; color: #1e293b; }
.item-sigla { display: inline-block; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 6px; padding: 1px 6px; font-size: 10px; font-weight: 800; font-family: monospace; margin-top: 2px; }
.item-desc { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.cbo-chip { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; border-radius: 6px; padding: 3px 8px; font-weight: 800; font-size: 12px; font-family: monospace; letter-spacing: 0.05em; }
.missing { font-size: 12px; color: #f59e0b; font-weight: 600; }
.text-muted { color: #94a3b8; font-size: 12px; }
.money { font-family: monospace; font-size: 13px; font-weight: 700; color: #15803d; }
.money-big { font-family: monospace; font-size: 15px; font-weight: 900; color: #15803d; }
.badge-gestao { display: inline-block; background: #fdf4ff; color: #7e22ce; border: 1px solid #e9d5ff; border-radius: 8px; padding: 3px 8px; font-size: 11px; font-weight: 700; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-green { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.badge-red   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.tipo-badge { display: inline-block; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; border: 1px solid; }
.tipo-confianca { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.tipo-comissao  { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.row-actions { display: flex; gap: 4px; }
.act-btn { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; transition: all 0.15s; color: #64748b; }
.act-btn:hover { transform: translateY(-1px); }
.act-btn.act-purple:hover { background: #f5f3ff; border-color: #ddd6fe; color: #7c3aed; }
.act-btn.act-red:hover    { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

/* PCCS */
.pccs-header { padding: 18px 20px 0; }
.pccs-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 4px; }
.pccs-sub { font-size: 12px; color: #94a3b8; margin: 0 0 12px; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-box { background: #fff; border-radius: 24px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 32px 80px rgba(0,0,0,0.25); }
.modal-sm { max-width: 480px; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 26px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
.modal-title { font-size: 17px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; cursor: pointer; color: #64748b; transition: all 0.15s; }
.modal-close:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
.modal-body { overflow-y: auto; padding: 22px 26px; flex: 1; display: flex; flex-direction: column; gap: 20px; }
.modal-footer { padding: 14px 26px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
.modal-erro   { flex: 1; font-size: 13px; font-weight: 600; color: #dc2626; margin: 0; }
.modal-sucesso { flex: 1; font-size: 13px; font-weight: 600; color: #15803d; margin: 0; }

/* FORM */
.form-section { border: 1px solid #f1f5f9; border-radius: 14px; padding: 18px; }
.section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 0 0 12px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group.col-2 { grid-column: span 2; }
.form-group.col-full { grid-column: 1 / -1; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.form-input { width: 100%; padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: inherit; color: #1e293b; outline: none; background: #fff; transition: border-color 0.15s; box-sizing: border-box; }
.form-input:focus { border-color: #6366f1; }
textarea.form-input { resize: vertical; min-height: 60px; }
.cbo-input { font-family: monospace; font-size: 15px; font-weight: 800; letter-spacing: 0.1em; }
.field-hint { font-size: 11px; color: #94a3b8; }
.field-hint a { color: #6366f1; text-decoration: none; }
.req { color: #dc2626; }

.btn-cancel { padding: 9px 18px; border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; color: #475569; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.btn-cancel:hover { background: #f8fafc; }
.btn-salvar { display: flex; align-items: center; gap: 7px; padding: 9px 20px; border: none; border-radius: 11px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.btn-salvar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-salvar:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-teal-s { background: linear-gradient(135deg, #0d9488, #10b981) !important; }
.btn-teal-s:hover:not(:disabled) { box-shadow: 0 6px 18px rgba(16,185,129,0.4) !important; }

.spin { animation: spin 0.9s linear infinite; }
.modal-fade-enter-active, .modal-fade-leave-active { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .hero-inner { flex-wrap: wrap; }
  .hero-title { font-size: 22px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .two-col, .form-two-col, .config-grid { grid-template-columns: 1fr !important; }
  .table-scroll, .table-wrap { overflow-x: auto; }
  table { min-width: 500px; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 18px !important; }
  .kpi-strip, .fin-cards, .stats-row { grid-template-columns: repeat(2, 1fr) !important; }
  .hide-mobile { display: none !important; }
}
</style>
