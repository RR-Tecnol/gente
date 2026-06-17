<template>
  <div class="escala-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div><div class="hs hs3"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">📅 Escalas</span>
          <h1 class="hero-title">Escala de Trabalho</h1>
          <p class="hero-sub">Gestão mensal de escalas por setor · {{ mesSelecionado }}</p>
        </div>
        <div class="hero-ctrl">
          <div class="mode-switch" title="Alternar tipo de escala">
            <button class="mode-btn active" type="button">Funcionários gerais</button>
            <button class="mode-btn" type="button" @click="irParaEscalaMedica">Escalas médicas</button>
          </div>
          <button class="ctrl-btn" @click="mudarMes(-1)">‹</button>
          <span class="ctrl-mes">{{ mesSelecionado }}</span>
          <button class="ctrl-btn" @click="mudarMes(1)">›</button>
        </div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg class="s-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="s-input" placeholder="Buscar funcionário ou setor..." />
      </div>
      <template v-if="mostrarGrade">
        <button type="button" class="voltar-lista-btn" @click="voltarParaLista">← Lista de setores</button>
        <span v-if="macroCarregarTudo" class="macro-badge">Visão macro (paginada)</span>
        <select v-else v-model="setorSel" class="filter-sel" @change="onTrocarSetorSelect">
          <option v-for="s in setores" :key="s.id" :value="String(s.id)">{{ labelSetorOption(s) }}</option>
        </select>
        <button class="novo-btn" :disabled="gradeSomenteLeitura" @click="abrirModal()">+ Novo Registro</button>
        <button class="save-btn" type="button" :disabled="gradeSomenteLeitura" @click="fetchEscala">Salvar</button>
        <button class="pdf-btn" :disabled="gradeSomenteLeitura" @click="copiarMesAnterior">Copiar Mês Anterior</button>
        <button class="pdf-btn" @click="exportarPDF" :disabled="escalaFiltrada.length === 0">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M9 15h6M9 11h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          PDF
        </button>
      </template>
      <template v-else>
        <div class="macro-wrap">
          <button type="button" class="macro-btn" :disabled="gradeSomenteLeitura" @click="confirmarCarregarTodoMunicipio">
            Carregar todos os setores
          </button>
          <p class="macro-warn">
            Atenção: carregar todo o município pode demorar. A grade será paginada (<code>carregar_tudo=1</code>).
          </p>
        </div>
      </template>
    </div>

    <!-- LISTA DE SETORES (master) — padrão Escalas Médicas -->
    <div v-if="loading && !mostrarGrade" class="state-box init-state" :class="{ loaded }">
      <div class="spinner"></div>
      <p>Carregando setores…</p>
    </div>

    <div v-else-if="!mostrarGrade" class="state-box init-state" :class="{ loaded }">
      <div class="state-head">
        <span class="state-ico">📋</span>
        <div>
          <h3>Setores no escopo</h3>
          <p>Expanda um card e abra a grade mensal. Evita carregar a planilha infinita sem escolher o setor.</p>
        </div>
      </div>
      <p v-if="hintApi" class="hint-banner">{{ hintApi }}</p>
      <div class="escala-cards-wrap">
        <div v-if="!setoresFiltrados.length" class="escala-cards-empty">
          Nenhum setor disponível no seu escopo para esta competência.
        </div>
        <div
          v-for="s in setoresFiltrados"
          :key="s.id"
          class="escala-card"
          :class="{ expanded: setorExpandido === s.id }"
        >
          <button type="button" class="escala-card-head" @click="toggleSetorCard(s.id)">
            <div>
              <div class="ec-title">{{ s.nome }}</div>
              <div class="ec-sub">{{ s.unidade_sigla || s.unidade_nome || 'Unidade' }}</div>
            </div>
            <span class="ec-arrow">{{ setorExpandido === s.id ? '▾' : '▸' }}</span>
          </button>
          <div v-if="setorExpandido === s.id" class="escala-card-body">
            <div class="ec-metrics">
              <span v-if="s.unidade_nome"><b>Unidade:</b> {{ s.unidade_nome }}</span>
              <span><b>Setor ID:</b> {{ s.id }}</span>
            </div>
            <button type="button" class="ec-open-btn" @click="abrirGradeDoSetor(s.id)">
              Abrir grade deste setor
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="loading && mostrarGrade" class="state-box"><div class="spinner"></div><p>Carregando escala…</p></div>

    <template v-else-if="mostrarGrade">
      <div class="turnos-sticky-stack">
        <div
          ref="turnosBarEl"
          class="turnos-bar"
          :class="{ loaded, 'bar-readonly': gradeSomenteLeitura, 'sudo-unlock-active': mostrarIndicadorIntervencaoSudo }"
        >
          <span class="turnos-label">Arraste os Turnos:</span>
          <div class="turnos-chips">
            <div
              v-for="t in turnosDisponiveis"
              :key="t.cod"
              class="turno-chip"
              :style="{ '--tc': t.cor, '--tl': t.corLight }"
              :draggable="!gradeSomenteLeitura"
              @dragstart="onDragStartTurno($event, t)"
            >
              <span class="turno-sigla">{{ t.cod }}</span>
              <span class="turno-nome">{{ t.nome }}</span>
              <span class="turno-hora">{{ t.hora || '—' }}</span>
            </div>
            <div
              class="turno-chip turno-apagar"
              :class="{ active: modoBorracha }"
              :draggable="!gradeSomenteLeitura"
              @dragstart="onDragStartApagar($event)"
              @click="toggleBorracha"
            >
              <span class="turno-sigla">🗑️</span>
              <span class="turno-nome">Apagar</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="mostrarGrade && mostrarIndicadorIntervencaoSudo" class="sudo-grade-banner" role="status">
        <span class="sudo-grade-ico" aria-hidden="true">⚡</span>
        <span><strong>Intervenção administrativa (Sudo):</strong> a grade está desbloqueada para edição; cada alteração de célula homologada/trancada é registrada na auditoria.</span>
      </div>

      <div v-if="mostrarGrade && workflow && (!macroCarregarTudo || sudoUnlock)" class="workflow-toolbar">
        <span class="wf-status-pill">{{ workflow.status_label }}</span>
        <div class="wf-actions">
          <button v-if="workflow.permissoes?.enviar_validacao && !gradeSomenteLeitura" type="button" class="wf-action-btn" @click="acaoWorkflow('enviar_validacao')">
            Enviar para validação
          </button>
          <button v-if="workflow.permissoes?.reenviar_validacao && !gradeSomenteLeitura" type="button" class="wf-action-btn" @click="acaoWorkflow('reenviar_validacao')">
            Reenviar para validação
          </button>
          <button v-if="workflow.permissoes?.devolver_ajuste && !gradeSomenteLeitura" type="button" class="wf-action-btn wf-danger" @click="abrirModalDevolucao">
            Devolver para ajuste
          </button>
          <button v-if="workflow.permissoes?.homologar && !gradeSomenteLeitura" type="button" class="wf-action-btn wf-success" @click="acaoWorkflow('homologar')">
            Homologar (SAGEP)
          </button>
        </div>
      </div>

      <div v-if="mostrarGrade && !macroCarregarTudo && mostrarBannerDevolucao" class="banner-devolucao" role="alert">
        <strong>Escala devolvida para ajuste</strong>
        <p v-if="workflow?.motivo_devolucao" class="banner-devolucao-motivo">{{ workflow.motivo_devolucao }}</p>
        <p v-if="workflow?.devolvido_por_nome" class="banner-devolucao-meta">
          Devolvida por: <strong>{{ workflow.devolvido_por_nome }}</strong>
          <span v-if="workflow?.devolvido_em"> · {{ workflow.devolvido_em }}</span>
        </p>
      </div>

      <div class="vacancia-alerta" v-if="deficitRegencia > 0">
        🚨 Alerta de Vacância: déficit de {{ deficitRegencia }} profissional(is) em Regência no dia de hoje.
      </div>

      <!-- GRADE MENSAL -->
      <div
        class="grade-card"
        :class="{ loaded, 'grade-readonly': gradeSomenteLeitura, 'sudo-unlock-active': mostrarIndicadorIntervencaoSudo }"
      >
        <div class="grade-scroll">
          <table class="grade-table">
            <thead>
              <tr>
                <th class="th-nome sticky-col">Servidor / Setor</th>
                <th v-for="d in diasMes" :key="d.num" class="th-dia" :class="{ 'th-fds': d.fimSemana }">
                  <div class="dia-num">{{ d.num }}</div>
                  <div class="dia-dow">{{ d.sem }}</div>
                </th>
                <th class="th-sum">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="linha in escalaFiltrada" :key="linha.funcionario_id" class="grade-row">
                <td class="td-nome sticky-col">
                  <div class="nome-wrap">
                    <div class="func-avatar">{{ iniciais(linha.nome) }}</div>
                    <div class="func-info">
                      <div class="func-nome">{{ linha.nome }}</div>
                      <div class="func-setor">{{ linha.setor }}</div>
                    </div>
                    <button v-if="!gradeSomenteLeitura" type="button" class="limpar-mes-btn" @click.stop="limparMesServidor(linha)">Limpar mês</button>
                  </div>
                </td>
                <td
                  v-for="d in diasMes"
                  :key="d.num"
                  class="td-cell"
                  :class="{ 'cell-fds': d.fimSemana, 'cell-bloqueada': isDiaPassado(d.num) || isDiaAfastamento(linha, d.num) }"
                  @dragover.prevent="dragOver = `${linha.funcionario_id}-${d.num}`"
                  @dragleave="dragOver = null"
                  @drop.prevent="onDropTurno(linha, d.num)"
                  :data-active="dragOver === `${linha.funcionario_id}-${d.num}`"
                  @click="onClickCelula(linha, d)"
                  :title="getDiaTitle(linha, d.num)"
                >
                  <div
                    v-if="getTurnoCod(linha, d.num)"
                    class="cell-turno"
                    :class="getTurnoClass(linha, d.num)"
                    :style="getTurnoStyle(linha, d.num)"
                    :draggable="!gradeSomenteLeitura && !isDiaAfastamento(linha, d.num)"
                    @dragstart="onDragStartCelula($event, linha, d)"
                  >
                    {{ getTurnoCod(linha, d.num) }}
                    <span
                      v-if="isDiaAfastamento(linha, d.num) && getAfastamentoInfo(linha, d.num)?.ultrapassa_15_dias"
                      class="afastamento-badge-15"
                      title="Licença superior a 15 dias: verificar rito de perícia/baixa SISFOLHA."
                    >
                      15+
                    </span>
                  </div>
                </td>
                <td class="td-sum"><span class="sum-val">{{ contarTurnosLinha(linha) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="escalaFiltrada.length === 0" class="state-box state-box-escala-empty">
          <span class="empty-ico" aria-hidden="true">📅</span>
          <p>Nenhum registro de escala para <strong>{{ mesSelecionado }}</strong> neste setor.</p>
          <p v-if="setorSel" class="empty-sub">Competência alinhada ao selector do cabeçalho; os seeds de homologação preenchem o mês corrente e os meses adjacentes em <code>SidebarCoverageSeeder</code>.</p>
        </div>
      </div>

      <div v-if="macroCarregarTudo && paginacao.last_page > 1" class="pager-macro">
        <button type="button" class="pdf-btn" :disabled="paginacao.page <= 1" @click="paginaAnterior">Anterior</button>
        <span class="pager-meta">Página {{ paginacao.page }} / {{ paginacao.last_page }} · {{ paginacao.total }} servidores</span>
        <button type="button" class="pdf-btn" :disabled="paginacao.page >= paginacao.last_page" @click="paginaProxima">Próxima</button>
      </div>

      <!-- LEGENDA -->
      <div class="legenda" :class="{ loaded }">
        <span v-for="t in turnosCores" :key="t.cod" class="leg-item" :class="t.cls">
          <span class="leg-dot"></span> {{ t.cod }} – {{ t.nome }}
        </span>
      </div>
    </template>

    <!-- MODAL REGISTRO -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>{{ editando ? '✏️ Editar DIA' : '+ Novo Registro de Escala' }}</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group">
                <label>Funcionário <span class="req">*</span></label>
                <select v-model="form.funcionario_id" class="cfg-input">
                  <option value="">Selecione</option>
                  <option v-for="f in funcionarios" :key="f.id" :value="f.id">{{ f.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Data <span class="req">*</span></label>
                <input v-model="form.data" type="date" class="cfg-input" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Turno <span class="req">*</span></label>
                <select v-model="form.turno" class="cfg-input">
                  <option value="">Selecione</option>
                  <option v-for="t in turnosCores" :key="t.cod" :value="t.cod">{{ t.cod }} – {{ t.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Observação</label>
                <input v-model="form.obs" class="cfg-input" placeholder="Detalhamento (opcional)" />
              </div>
            </div>
            <template v-if="formPrecisaJustificativaInstitucional">
              <div class="form-group">
                <label><span class="req">*</span> Motivo legal (fundo administrativo)</label>
                <select v-model="form.motivo_id" class="cfg-input">
                  <option value="">Selecione o motivo…</option>
                  <option v-for="m in motivosAlteracao" :key="m.id" :value="m.id">
                    {{ m.titulo }}{{ m.exige_documento ? ' (exige nº de documento)' : '' }}
                  </option>
                </select>
                <p class="hint-text">{{ motivoSelecionadoForm?.descricao }}</p>
              </div>
              <div v-if="motivoSelecionadoForm?.exige_documento" class="form-group">
                <label>Portaria / CI / processo <span class="req">*</span></label>
                <input v-model="form.documento_referencia" class="cfg-input" placeholder="Ex: Portaria 123/2026, CI 45/2026" />
              </div>
            </template>
            <div v-if="erroSalvar" class="erro-msg">⚠️ {{ erroSalvar }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalAberto = false" :disabled="salvando">Cancelar</button>
              <button class="modal-submit" @click="salvar" :disabled="salvando">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>{{ editando ? 'Salvar Alteração' : 'Registrar' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="toast.visible" class="toast-msg">{{ toast.msg }}</div>
    </transition>

    <transition name="modal">
      <div v-if="modalJustificativa.aberto" class="modal-overlay" @click.self="cancelarJustificativa">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>🛡️ Justificativa Obrigatória</h3>
            <button class="modal-close" @click="cancelarJustificativa">✕</button>
          </div>
          <div class="modal-body">
            <p class="hint-text" style="margin:0 0 10px;">Para ajuste retroativo, informe o <strong>fundo administrativo</strong> (TCE-MA exige rastreabilidade, não basta "ajuste" genérico).</p>
            <div class="form-group">
              <label>Motivo <span class="req">*</span></label>
              <select v-model="modalJustificativa.motivo_id" class="cfg-input" @change="modalJustificativa.erroLocal = ''">
                <option value="">Selecione…</option>
                <option v-for="m in motivosAlteracao" :key="m.id" :value="m.id">
                  {{ m.titulo }}{{ m.exige_documento ? ' (exige documento)' : '' }}
                </option>
              </select>
            </div>
            <p v-if="modalMotivoSelecionado?.descricao" class="hint-text">{{ modalMotivoSelecionado.descricao }}</p>
            <div v-if="modalMotivoSelecionado?.exige_documento" class="form-group">
              <label>Portaria / CI / ofício <span class="req">*</span></label>
              <input v-model="modalJustificativa.documento_referencia" class="cfg-input" placeholder="Nº e ano do ato" />
            </div>
            <div class="form-group">
              <label>Complemento (opcional)</label>
              <textarea v-model="modalJustificativa.observacao" class="cfg-textarea" rows="2" placeholder="Detalhe a situação (não substitui o motivo)"></textarea>
            </div>
            <div class="form-group">
              <label>Observação adicional (opcional)</label>
              <input v-model="modalJustificativa.observacao_adicional" class="cfg-input" placeholder="Contexto extra para a auditoria" />
            </div>
            <div v-if="modalJustificativa.erroLocal" class="erro-msg" style="margin:0">⚠️ {{ modalJustificativa.erroLocal }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="cancelarJustificativa">Cancelar</button>
              <button class="modal-submit" @click="confirmarJustificativa">Confirmar e registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="modalSubstituicao.aberto" class="modal-overlay" @click.self="fecharSubstituicao">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>🔄 Substituição Oficial?</h3>
            <button class="modal-close" @click="fecharSubstituicao">✕</button>
          </div>
          <div class="modal-body">
            <p class="hero-sub">Esta movimentação representa substituição oficial para a folha?</p>
            <div class="modal-actions">
              <button class="modal-cancel" @click="confirmarSubstituicao(false)">Não</button>
              <button class="modal-submit" @click="confirmarSubstituicao(true)">Sim, registrar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="modalPontoLeitura.aberto" class="modal-overlay" @click.self="fecharModalPontoLeitura">
        <div class="modal-card modal-ponto-resumo">
          <div class="modal-hdr">
            <h3>📋 Ponto / dia (somente leitura)</h3>
            <button type="button" class="modal-close" @click="fecharModalPontoLeitura">✕</button>
          </div>
          <div class="modal-body">
            <p class="hero-sub">
              <strong>{{ modalPontoLeitura.nome }}</strong> · {{ modalPontoLeitura.dataFmt }}<br>
              <span v-if="modalPontoLeitura.turnoEscala">Turno na escala: <strong>{{ modalPontoLeitura.turnoEscala }}</strong></span>
              <span v-if="modalPontoLeitura.obsEscala"><br>Obs. escala: {{ modalPontoLeitura.obsEscala }}</span>
            </p>
            <div v-if="modalPontoLeitura.loading" class="ponto-resumo-loading">Carregando batidas do ponto…</div>
            <p v-else-if="modalPontoLeitura.erro" class="erro-msg">{{ modalPontoLeitura.erro }}</p>
            <div v-else>
              <p v-if="!modalPontoLeitura.batidas.length" class="hero-sub">Sem registros de batida neste dia (ou sem permissão para ver o espelho deste servidor).</p>
              <ul v-else class="ponto-batidas-list">
                <li v-for="(b, i) in modalPontoLeitura.batidas" :key="i">
                  <span class="pb-hora">{{ b.hora }}</span>
                  <span class="pb-tipo">{{ b.tipo }}</span>
                </li>
              </ul>
            </div>
            <div class="modal-actions">
              <button type="button" class="modal-submit" @click="irParaPontoEletronico">Abrir módulo Ponto</button>
              <button type="button" class="modal-cancel" @click="fecharModalPontoLeitura">Fechar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="modalLimpezaCritica.aberto" class="modal-overlay" @click.self="modalLimpezaCritica.aberto = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>⚠️ Confirmação Crítica</h3>
            <button class="modal-close" @click="modalLimpezaCritica.aberto = false">✕</button>
          </div>
          <div class="modal-body">
            <p class="hero-sub">Esta ação removerá {{ modalLimpezaCritica.total }} células do planejamento mensal. Confirmar?</p>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalLimpezaCritica.aberto = false">Cancelar</button>
              <button class="modal-submit" @click="confirmarLimpezaCritica">Confirmar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal">
      <div v-if="modalWfDevolver.aberto" class="modal-overlay" @click.self="fecharModalDevolucao">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>Devolver escala para ajuste</h3>
            <button type="button" class="modal-close" @click="fecharModalDevolucao">✕</button>
          </div>
          <div class="modal-body">
            <label class="req">Motivo da devolução</label>
            <textarea v-model="modalWfDevolver.motivo" class="cfg-textarea" rows="4" placeholder="Descreva o que o setor deve corrigir na grade…"></textarea>
            <p v-if="modalWfDevolver.erro" class="erro-msg" style="margin-top:8px">⚠️ {{ modalWfDevolver.erro }}</p>
            <div class="modal-actions">
              <button type="button" class="modal-cancel" @click="fecharModalDevolucao">Cancelar</button>
              <button type="button" class="modal-submit" @click="confirmarDevolucaoWorkflow">Confirmar devolução</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { useSemadReadOnlyShell } from '@/composables/useSemadReadOnlyShell'

const loaded    = ref(false)
const loading   = ref(true)
const busca     = ref('')
const setorSel  = ref('')
/** Visão macro: todos os setores elegíveis, grade paginada (API carregar_tudo=1). */
const macroCarregarTudo = ref(false)
const setorExpandido = ref(null)
const hintApi = ref('')
const paginacao = ref({ page: 1, per_page: 50, total: 0, last_page: 1 })
const escala    = ref([])
const setores   = ref([])
const funcionarios = ref([])
const modalAberto  = ref(false)
const editando     = ref(false)
const salvando     = ref(false)
const erroSalvar   = ref('')
const toast        = ref({ visible: false, msg: '' })
const dragTurno    = ref(null)
const dragOver     = ref(null)
const modoBorracha = ref(false)
const turnosDisponiveis = ref([])
const turnosBarEl = ref(null)
const scrollHostEl = ref(null)
const turnosFixado = ref(false)
const turnosFixadoStyle = ref({})
const turnosBarPlaceholderHeight = ref(0)
const turnosTriggerScrollTop = ref(null)
const dragCelula = ref(null)
const modalSubstituicao = ref({ aberto: false, origem: null, destino: null, turno: '' })
/** Datas passadas: resumo de ponto (leitura) em vez de edição de escala. */
const modalPontoLeitura = ref({
  aberto: false,
  funcionario_id: null,
  nome: '',
  dataIso: '',
  dataFmt: '',
  competencia: '',
  turnoEscala: '',
  obsEscala: '',
  loading: false,
  erro: '',
  batidas: [],
})
const modalLimpezaCritica = ref({ aberto: false, total: 0, linha: null })
const modalJustificativa = ref({
  aberto: false,
  motivo_id: '',
  documento_referencia: '',
  observacao: '',
  observacao_adicional: '',
  erroLocal: '',
  resolver: null,
})
const motivosAlteracao = ref([])
/** Workflow v3 (status, permissões, devolução) — vem do GET /escala-trabalho quando há setor. */
const workflow = ref(null)
const WF_STATUS_RASCUNHO = 'RASCUNHO'
const WF_STATUS_DEVOLVIDA = 'DEVOLVIDA_AJUSTE'

const modalWfDevolver = ref({
  aberto: false,
  motivo: '',
  erro: '',
})

/** Sudo ativo (cabeçalho + permissão): destranca grade por status e, no front, pela visão macro. */
const sudoUnlock = computed(() => !!workflow.value?.pode_editar_grade_sudo)

const { isReadOnly: semadMantaReadOnly } = useSemadReadOnlyShell()

const gradeSomenteLeitura = computed(() => {
  if (semadMantaReadOnly.value) return true
  const sudo = sudoUnlock.value
  const bloqueadoMacro = macroCarregarTudo.value && !sudo
  const w = workflow.value
  const st = w?.status
  const statusBloqueado = w != null && st != null && String(st) !== '' && st !== WF_STATUS_RASCUNHO && st !== WF_STATUS_DEVOLVIDA
  return (bloqueadoMacro || statusBloqueado) && !sudo
})

/** Indicador dourado/raio: Sudo está a anular trava de status ou de visão macro. */
const mostrarIndicadorIntervencaoSudo = computed(() => {
  if (!sudoUnlock.value) return false
  if (macroCarregarTudo.value) return true
  const st = workflow.value?.status
  return !!st && st !== WF_STATUS_RASCUNHO && st !== WF_STATUS_DEVOLVIDA
})

const mostrarBannerDevolucao = computed(() => {
  const w = workflow.value
  return !!w && String(w.status || '') === WF_STATUS_DEVOLVIDA
})

const router = useRouter()

const debugRunId = `run_${Date.now()}`
let debugScrollLogged = false
let scrollListenerHost = null
let debugScrollSamples = 0

const postDebugLog = (hypothesisId, location, message, data = {}) => {
  fetch('http://127.0.0.1:7642/ingest/22e0fa66-9b90-49be-a92d-2118e4bbe6b4',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'f94096'},body:JSON.stringify({sessionId:'f94096',runId:debugRunId,hypothesisId,location,message,data,timestamp:Date.now()})}).catch(()=>{})
}

const coletarOverflowAncestrais = (el) => {
  const out = []
  let p = el?.parentElement || null
  while (p && out.length < 8) {
    const cs = window.getComputedStyle(p)
    out.push({
      tag: p.tagName,
      cls: p.className || '',
      overflow: cs.overflow,
      overflowY: cs.overflowY,
      position: cs.position,
      top: cs.top,
    })
    p = p.parentElement
  }
  return out
}

const resolverScrollHost = (el) => {
  let p = el?.parentElement || null
  while (p) {
    const cs = window.getComputedStyle(p)
    const oy = cs.overflowY
    if (oy === 'auto' || oy === 'scroll') return p
    p = p.parentElement
  }
  return null
}

const recalcularAncoraTurnos = () => {
  const el = turnosBarEl.value
  const host = scrollHostEl.value || resolverScrollHost(el)
  if (!el || !host) return
  const hostRect = host.getBoundingClientRect()
  const elRect = el.getBoundingClientRect()
  turnosBarPlaceholderHeight.value = Math.ceil(elRect.height)
  turnosTriggerScrollTop.value = host.scrollTop + (elRect.top - hostRect.top)
}

const atualizarFixacaoTurnos = () => {
  const el = turnosBarEl.value
  const host = scrollHostEl.value
  if (!el || !host || turnosTriggerScrollTop.value == null) {
    turnosFixado.value = false
    turnosFixadoStyle.value = {}
    return
  }
  const deveFixar = host.scrollTop > turnosTriggerScrollTop.value
  if (deveFixar) {
    const hostRect = host.getBoundingClientRect()
    const elRect = el.getBoundingClientRect()
    turnosFixadoStyle.value = {
      position: 'fixed',
      top: `${Math.round(hostRect.top)}px`,
      left: `${Math.round(elRect.left)}px`,
      width: `${Math.round(elRect.width)}px`,
      zIndex: '120',
    }
  } else {
    turnosFixadoStyle.value = {}
  }
  turnosFixado.value = deveFixar
}

const logStickyDiagnostico = (stage) => {
  const el = turnosBarEl.value
  if (!el) {
    // #region agent log
    postDebugLog('H1', 'EscalaTrabalhoView.vue:sticky:no-el', 'turnos-bar não encontrado', { stage })
    // #endregion
    return
  }
  const cs = window.getComputedStyle(el)
  const rect = el.getBoundingClientRect()
  const stack = el.closest('.turnos-sticky-stack')
  const stackCs = stack ? window.getComputedStyle(stack) : null
  const stackRect = stack ? stack.getBoundingClientRect() : null
  const host = scrollHostEl.value || resolverScrollHost(el)
  const hostRect = host ? host.getBoundingClientRect() : null
  // #region agent log
  postDebugLog('H1', 'EscalaTrabalhoView.vue:sticky:metrics', 'métricas do sticky', {
    stage,
    topCss: cs.top,
    positionCss: cs.position,
    zIndexCss: cs.zIndex,
    rectTop: rect.top,
    rectLeft: rect.left,
    windowScrollY: window.scrollY,
    hostTag: host?.tagName || null,
    hostClass: host?.className || null,
    hostScrollTop: host?.scrollTop ?? null,
    hostClientTop: hostRect?.top ?? null,
    hostClientHeight: hostRect?.height ?? null,
    stackPositionCss: stackCs?.position ?? null,
    stackTopCss: stackCs?.top ?? null,
    stackRectTop: stackRect?.top ?? null,
    mostrarGrade: mostrarGrade.value,
    macro: macroCarregarTudo.value,
  })
  // #endregion
  // #region agent log
  postDebugLog('H2', 'EscalaTrabalhoView.vue:sticky:parents', 'overflow dos ancestrais', {
    stage,
    parents: coletarOverflowAncestrais(el),
  })
  // #endregion
}

const onDebugScrollSticky = () => {
  const host = scrollHostEl.value || window
  atualizarFixacaoTurnos()
  if (debugScrollSamples < 6) {
    debugScrollSamples += 1
    // #region agent log
    postDebugLog('H5', 'EscalaTrabalhoView.vue:sticky:scroll-sample', 'amostra de scroll/fixação', {
      sample: debugScrollSamples,
      listenerHostTag: scrollListenerHost?.tagName || 'WINDOW',
      listenerHostClass: scrollListenerHost?.className || '',
      hostTag: host?.tagName || 'WINDOW',
      hostClass: host?.className || '',
      hostScrollTop: host?.scrollTop ?? null,
      windowScrollY: window.scrollY,
      fixado: turnosFixado.value,
      triggerScrollTop: turnosTriggerScrollTop.value,
    })
    // #endregion
  }
  if (debugScrollLogged) return
  debugScrollLogged = true
  logStickyDiagnostico('scroll_once')
}

const onDebugResizeSticky = () => {
  recalcularAncoraTurnos()
  atualizarFixacaoTurnos()
}

const detachScrollListener = () => {
  if (!scrollListenerHost) return
  scrollListenerHost.removeEventListener('scroll', onDebugScrollSticky)
  scrollListenerHost = null
}

const attachScrollListener = () => {
  const host = scrollHostEl.value || window
  if (scrollListenerHost === host) return
  detachScrollListener()
  scrollListenerHost = host
  scrollListenerHost.addEventListener('scroll', onDebugScrollSticky, { passive: true })
  // #region agent log
  postDebugLog('H5', 'EscalaTrabalhoView.vue:sticky:attach', 'listener de scroll anexado', {
    listenerHostTag: scrollListenerHost?.tagName || 'WINDOW',
    listenerHostClass: scrollListenerHost?.className || '',
  })
  // #endregion
}

const sincronizarFixacaoTurnos = async (stage) => {
  await nextTick()
  if (!mostrarGrade.value || loading.value) {
    turnosFixado.value = false
    turnosFixadoStyle.value = {}
    turnosTriggerScrollTop.value = null
    scrollHostEl.value = null
    detachScrollListener()
    return
  }
  scrollHostEl.value = resolverScrollHost(turnosBarEl.value)
  attachScrollListener()
  recalcularAncoraTurnos()
  atualizarFixacaoTurnos()
  // #region agent log
  postDebugLog('H4', 'EscalaTrabalhoView.vue:sticky:sync', 'sincronização reativa do sticky', {
    stage,
    hasTurnosBar: !!turnosBarEl.value,
    hostTag: scrollHostEl.value?.tagName || null,
    hostClass: scrollHostEl.value?.className || null,
    triggerScrollTop: turnosTriggerScrollTop.value,
  })
  // #endregion
}

const now = new Date()
const anoRef = ref(now.getFullYear())
const mesRef = ref(now.getMonth() + 1) // 1-12

const form = ref({ funcionario_id: '', data: '', turno: '', obs: '', motivo_id: '', documento_referencia: '' })

const turnosCores = [
  { cod: 'M',  nome: 'Matutino',   cls: 'tur-m' },
  { cod: 'V',  nome: 'Vespertino', cls: 'tur-v' },
  { cod: 'N',  nome: 'Noturno',    cls: 'tur-n' },
  { cod: 'I',  nome: 'Integral',   cls: 'tur-i' },
  { cod: 'F',  nome: 'Folga',      cls: 'tur-f' },
  { cod: 'SO', nome: 'Sobreaviso', cls: 'tur-so' },
  { cod: 'AT', nome: 'Atestado',   cls: 'tur-at' },
]

const sems  = ['D','S','T','Q','Q','S','S']

/** Mês competência em português (evita ambiguidade com abreviaturas tipo «Jul»). */
const mesSelecionado = computed(() => {
  const d = new Date(anoRef.value, mesRef.value - 1, 1)
  const s = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
  return s.charAt(0).toUpperCase() + s.slice(1)
})

const mostrarGrade = computed(() => macroCarregarTudo.value || String(setorSel.value || '').length > 0)

const setoresFiltrados = computed(() => {
  const t = String(busca.value || '').trim().toLowerCase()
  const list = setores.value || []
  if (!t) return list
  return list.filter((s) => {
    const blob = [s.nome, s.unidade_nome, s.unidade_sigla].filter(Boolean).join(' ').toLowerCase()
    return blob.includes(t)
  })
})

const labelSetorOption = (s) => {
  const u = s.unidade_sigla || s.unidade_nome
  return u ? `${s.nome} · ${u}` : s.nome
}

const diasMes = computed(() => {
  const dias = new Date(anoRef.value, mesRef.value, 0).getDate()
  return Array.from({ length: dias }, (_, i) => {
    const dt = new Date(anoRef.value, mesRef.value - 1, i + 1)
    return { num: i + 1, sem: sems[dt.getDay()], fimSemana: dt.getDay() === 0 || dt.getDay() === 6 }
  })
})

const mudarMes = (d) => {
  mesRef.value += d
  if (mesRef.value > 12) { mesRef.value = 1; anoRef.value++ }
  if (mesRef.value < 1)  { mesRef.value = 12; anoRef.value-- }
  if (macroCarregarTudo.value) {
    paginacao.value = { ...paginacao.value, page: 1 }
  }
  fetchEscala()
}

const toggleSetorCard = (id) => {
  setorExpandido.value = setorExpandido.value === id ? null : id
}

const abrirGradeDoSetor = (id) => {
  macroCarregarTudo.value = false
  setorSel.value = String(id)
  setorExpandido.value = null
  fetchEscala()
}

const voltarParaLista = () => {
  macroCarregarTudo.value = false
  setorSel.value = ''
  setorExpandido.value = null
  paginacao.value = { page: 1, per_page: paginacao.value.per_page || 50, total: 0, last_page: 1 }
  escala.value = []
  workflow.value = null
  fetchEscala()
}

const confirmarCarregarTodoMunicipio = () => {
  if (!window.confirm('Carregar todos os setores do município nesta competência pode demorar e transferir muitos dados. Deseja continuar?')) {
    return
  }
  macroCarregarTudo.value = true
  setorSel.value = ''
  setorExpandido.value = null
  paginacao.value = { ...paginacao.value, page: 1 }
  fetchEscala()
}

const onTrocarSetorSelect = () => {
  macroCarregarTudo.value = false
  paginacao.value = { ...paginacao.value, page: 1 }
  fetchEscala()
}

const paginaAnterior = () => {
  if (paginacao.value.page <= 1) return
  paginacao.value = { ...paginacao.value, page: paginacao.value.page - 1 }
  fetchEscala()
}

const paginaProxima = () => {
  if (paginacao.value.page >= paginacao.value.last_page) return
  paginacao.value = { ...paginacao.value, page: paginacao.value.page + 1 }
  fetchEscala()
}

const irParaEscalaMedica = () => {
  router.push('/escala-matriz-v3')
}

const escalaFiltrada = computed(() => {
  if (!busca.value) return escala.value
  const t = busca.value.toLowerCase()
  return escala.value.filter(e => (e.nome + e.setor).toLowerCase().includes(t))
})

const todayDay = computed(() => Number(new Date().getDate()))
const deficitRegencia = computed(() => {
  const total = escalaFiltrada.value.length
  const emReg = escalaFiltrada.value.filter((l) => {
    const cod = String(getTurnoCod(l, todayDay.value) || '').toUpperCase()
    return ['M', 'V', 'N', 'I'].includes(cod)
  }).length
  return Math.max(0, total - emReg)
})

const getAfastamentoInfo = (l, d) => l?.dias?.[d]?.afastamento || null
const isDiaAfastamento = (l, d) => !!l?.dias?.[d]?.bloqueada_por_afastamento && !!getAfastamentoInfo(l, d)
const getTurnoClass = (l, d) => {
  const afast = getAfastamentoInfo(l, d)
  if (afast?.sigla) return 'cel-afastamento'
  return l.dias?.[d]?.turno ? `cel-${String(l.dias[d].turno).toLowerCase()}` : ''
}
const getTurnoCod = (l, d) => {
  const afast = getAfastamentoInfo(l, d)
  if (afast?.sigla) return String(afast.sigla).toUpperCase()
  return l.dias?.[d]?.turno || ''
}
const getTurnoStyle = (l, d) => {
  const afast = getAfastamentoInfo(l, d)
  if (!afast?.cor) return null
  return { background: afast.cor, color: '#fff', borderColor: 'rgba(15, 23, 42, 0.25)' }
}
const getDiaTitle = (l, d) => {
  const afast = getAfastamentoInfo(l, d)
  if (afast) {
    const extra = afast.ultrapassa_15_dias ? ' • LM > 15 dias' : ''
    return `${afast.sigla || 'AF'} - ${afast.tipo || 'Afastamento'} (${afast.inicio || ''} até ${afast.fim || ''})${extra}`
  }
  return l.dias?.[d] ? `${l.nome} - Dia ${d}: ${l.dias[d].turno}` : ''
}
const iniciais = (nome) => {
  const w = String(nome || '').trim().split(' ').filter(Boolean)
  if (!w.length) return '?'
  if (w.length === 1) return w[0].slice(0, 2).toUpperCase()
  return `${w[0][0]}${w[w.length - 1][0]}`.toUpperCase()
}
const contarTurnosLinha = (linha) => diasMes.value.filter((d) => {
  if (isDiaAfastamento(linha, d.num)) return false
  const c = String(getTurnoCod(linha, d.num) || '').toUpperCase()
  return c && c !== 'F'
}).length

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3200) }
/** Extrai faixa aproximada do rótulo de turno (ex.: "07-13h") para POST /substituicoes. */
const parseFaixaTurnoLabel = (horaLabel) => {
  const s = String(horaLabel || '').trim()
  const m = s.match(/(\d{1,2})\s*[-–]\s*(\d{1,2})/)
  if (!m) return { inicio: null, fim: null }
  const a = String(Number(m[1])).padStart(2, '0')
  const b = String(Number(m[2])).padStart(2, '0')
  return { inicio: `${a}:00`, fim: `${b}:00` }
}
const nomeSetorSelecionado = computed(() => {
  const id = Number(setorSel.value)
  if (!id) return ''
  const s = (setores.value || []).find((x) => Number(x.id) === id)
  return String(s?.nome ?? s?.SETOR_NOME ?? '').trim()
})
const isRetroativa = (dataIso) => dataIso < new Date().toISOString().slice(0, 10)
const isoDiaCompetencia = (diaNum) => `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(diaNum).padStart(2, '0')}`
const isDiaPassado = (diaNum) => isRetroativa(isoDiaCompetencia(diaNum))
const formPrecisaJustificativaInstitucional = computed(() => {
  const d = String(form.value.data || '')
  return d.length >= 10 && isRetroativa(d)
})
const motivoSelecionadoForm = computed(() =>
  motivosAlteracao.value.find((m) => m.id === Number(form.value.motivo_id))
)
const modalMotivoSelecionado = computed(() =>
  motivosAlteracao.value.find((m) => m.id === Number(modalJustificativa.value.motivo_id))
)

const solicitarJustificativa = () => new Promise((resolve) => {
  modalJustificativa.value = {
    aberto: true,
    motivo_id: '',
    documento_referencia: '',
    observacao: '',
    observacao_adicional: '',
    erroLocal: '',
    resolver: resolve,
  }
})
const confirmarJustificativa = () => {
  const m = modalJustificativa.value
  const mot = motivosAlteracao.value.find((x) => x.id === Number(m.motivo_id))
  if (!m.motivo_id && !String(m.observacao).trim() && !String(m.observacao_adicional).trim()) {
    modalJustificativa.value = { ...m, erroLocal: 'Selecione um motivo legal ou preencha o complemento com o fundamento.' }
    return
  }
  if (mot?.exige_documento && !String(m.documento_referencia).trim()) {
    modalJustificativa.value = { ...m, erroLocal: 'Este motivo exige nº de portaria, CI ou ofício.' }
    return
  }
  m.resolver?.({
    motivo_id: m.motivo_id ? Number(m.motivo_id) : null,
    documento_referencia: String(m.documento_referencia || '').trim(),
    observacao: String(m.observacao || '').trim(),
    observacao_adicional: String(m.observacao_adicional || '').trim(),
  })
  modalJustificativa.value = {
    aberto: false,
    motivo_id: '',
    documento_referencia: '',
    observacao: '',
    observacao_adicional: '',
    erroLocal: '',
    resolver: null,
  }
}
const cancelarJustificativa = () => {
  modalJustificativa.value.resolver?.(null)
  modalJustificativa.value = {
    aberto: false,
    motivo_id: '',
    documento_referencia: '',
    observacao: '',
    observacao_adicional: '',
    erroLocal: '',
    resolver: null,
  }
}
const MACRO_SUDO_EDIT_ACK = 'gente_escala_macro_sudo_edit_ack'

const onDropTurno = async (linha, dia) => {
  if (gradeSomenteLeitura.value) {
    dragTurno.value = null
    dragOver.value = null
    return
  }
  if (macroCarregarTudo.value && sudoUnlock.value) {
    try {
      if (sessionStorage.getItem(MACRO_SUDO_EDIT_ACK) !== '1') {
        const ok = window.confirm(
          'Visão macro (vários setores): editar a grade com Sudo pode ser lenta e gerar muitas requisições. Continuar?'
        )
        if (!ok) {
          dragTurno.value = null
          dragOver.value = null
          return
        }
        sessionStorage.setItem(MACRO_SUDO_EDIT_ACK, '1')
      }
    } catch {
      /* sessionStorage indisponível — segue sem bloquear */
    }
  }
  if (!linha?.funcionario_id) return
  if (isDiaAfastamento(linha, dia)) {
    showToast('⛔ Dia bloqueado por afastamento. Remova/encerre o afastamento para editar.')
    dragTurno.value = null
    dragOver.value = null
    return
  }
  if (isDiaPassado(dia)) {
    showToast('⛔ Data passada bloqueada: alterações só são permitidas a partir de hoje.')
    dragTurno.value = null
    dragOver.value = null
    return
  }
  if (dragCelula.value && (dragCelula.value.funcionario_id !== linha.funcionario_id || dragCelula.value.dia !== dia)) {
    modalSubstituicao.value = {
      aberto: true,
      origem: { ...dragCelula.value },
      destino: { funcionario_id: linha.funcionario_id, nome: linha.nome, dia },
      turno: dragCelula.value.turno,
    }
    dragCelula.value = null
    dragOver.value = null
    return
  }
  if (!dragTurno.value) return
  const data = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(dia).padStart(2, '0')}`
  const isApagar = !dragTurno.value.cod
  const basePost = {
    funcionario_id: linha.funcionario_id,
    data,
    turno: dragTurno.value.cod || null,
    setor_id: setorSel.value || undefined,
  }
  if (isApagar) {
    basePost.observacao = 'Remoção pontual via borracha da matriz'
  }
  try {
    await api.post('/api/v3/escala-trabalho', basePost)
    await fetchEscala()
    showToast(isApagar ? '🧽 Turno removido da célula com auditoria.' : '✅ Turno aplicado na matriz.')
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha ao aplicar turno.'}`)
  } finally {
    dragTurno.value = null
    dragOver.value = null
  }
}

const onDragCelula = (linha, dia) => {
  const turno = String(getTurnoCod(linha, dia) || '').toUpperCase()
  if (!turno) return
  dragCelula.value = { funcionario_id: linha.funcionario_id, nome: linha.nome, dia, turno }
}

const onDragStartTurno = (e, t) => {
  if (gradeSomenteLeitura.value) {
    e.preventDefault()
    return
  }
  dragTurno.value = t
}

const onDragStartApagar = (e) => {
  if (gradeSomenteLeitura.value) {
    e.preventDefault()
    return
  }
  dragTurno.value = { cod: '', nome: 'Apagar' }
}

const toggleBorracha = () => {
  if (gradeSomenteLeitura.value) return
  modoBorracha.value = !modoBorracha.value
}

const onDragStartCelula = (e, linha, d) => {
  if (gradeSomenteLeitura.value) {
    e.preventDefault()
    return
  }
  if (isDiaAfastamento(linha, d.num)) {
    e.preventDefault()
    return
  }
  onDragCelula(linha, d.num)
}

const onClickCelula = (linha, d) => {
  if (gradeSomenteLeitura.value) return
  if (isDiaAfastamento(linha, d.num)) {
    showToast('⛔ Célula bloqueada por afastamento.')
    return
  }
  if (modoBorracha.value) {
    apagarCelula(linha, d.num)
  } else {
    editarDia(linha, d)
  }
}

const acaoWorkflow = async (acao) => {
  try {
    await api.post('/api/v3/escala-trabalho/workflow', {
      mes: mesRef.value,
      ano: anoRef.value,
      setor_id: Number(setorSel.value),
      acao,
    })
    showToast('✅ Situação da escala atualizada.')
    await fetchEscala()
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha no workflow.'}`)
  }
}

const abrirModalDevolucao = () => {
  modalWfDevolver.value = { aberto: true, motivo: '', erro: '' }
}

const fecharModalDevolucao = () => {
  modalWfDevolver.value = { aberto: false, motivo: '', erro: '' }
}

const confirmarDevolucaoWorkflow = async () => {
  const m = modalWfDevolver.value
  const txt = String(m.motivo || '').trim()
  if (txt.length < 5) {
    modalWfDevolver.value = { ...m, erro: 'Informe o motivo (mín. 5 caracteres).' }
    return
  }
  try {
    await api.post('/api/v3/escala-trabalho/workflow', {
      mes: mesRef.value,
      ano: anoRef.value,
      setor_id: Number(setorSel.value),
      acao: 'devolver_ajuste',
      motivo_devolucao: txt,
    })
    fecharModalDevolucao()
    showToast('✅ Escala devolvida ao setor para ajuste.')
    await fetchEscala()
  } catch (e) {
    modalWfDevolver.value = {
      ...modalWfDevolver.value,
      erro: e?.response?.data?.erro || 'Falha ao devolver.',
    }
  }
}

const fecharSubstituicao = () => {
  modalSubstituicao.value = { aberto: false, origem: null, destino: null, turno: '' }
}

const fecharModalPontoLeitura = () => {
  modalPontoLeitura.value = {
    aberto: false,
    funcionario_id: null,
    nome: '',
    dataIso: '',
    dataFmt: '',
    competencia: '',
    turnoEscala: '',
    obsEscala: '',
    loading: false,
    erro: '',
    batidas: [],
  }
}

const irParaPontoEletronico = () => {
  const comp = modalPontoLeitura.value.competencia
  const fid = modalPontoLeitura.value.funcionario_id
  fecharModalPontoLeitura()
  router.push({ name: 'PontoEletronico', query: { competencia: comp || undefined, funcionario_id: fid || undefined } })
}

const abrirResumoPontoLeitura = async (linha, diaNum) => {
  const dataIso = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(diaNum).padStart(2, '0')}`
  const comp = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}`
  const cel = linha.dias?.[diaNum] || {}
  modalPontoLeitura.value = {
    aberto: true,
    funcionario_id: linha.funcionario_id,
    nome: linha.nome || 'Servidor',
    dataIso,
    dataFmt: new Date(dataIso + 'T12:00:00').toLocaleDateString('pt-BR'),
    competencia: comp,
    turnoEscala: String(cel.turno || '').trim() || '—',
    obsEscala: String(cel.obs || '').trim(),
    loading: true,
    erro: '',
    batidas: [],
  }
  try {
    const { data } = await api.get('/api/v3/ponto', {
      params: { competencia: comp, funcionario_id: linha.funcionario_id },
    })
    const diaInt = Number(diaNum)
    const chunk = (data.registros || []).find((r) => Number(r.dia) === diaInt)
    modalPontoLeitura.value.batidas = chunk?.batidas || []
  } catch (e) {
    modalPontoLeitura.value.erro = e?.response?.data?.erro || e?.response?.data?.message || 'Não foi possível carregar o espelho de ponto.'
  } finally {
    modalPontoLeitura.value.loading = false
  }
}

const confirmarSubstituicao = async (oficial) => {
  if (gradeSomenteLeitura.value) return fecharSubstituicao()
  const ctx = modalSubstituicao.value
  if (!ctx?.origem || !ctx?.destino) return fecharSubstituicao()
  const data = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(ctx.destino.dia).padStart(2, '0')}`
  try {
    await api.post('/api/v3/escala-trabalho', {
      funcionario_id: ctx.destino.funcionario_id,
      data,
      turno: ctx.turno,
      setor_id: setorSel.value || undefined,
      observacao: oficial ? 'Substituição oficial via arraste na matriz' : 'Movimentação por arraste na matriz',
      substituicao_oficial: oficial ? 1 : 0,
      origem_funcionario_id: ctx.origem.funcionario_id,
    })
    // Notificações em NOTIFICACAO só são gravadas no POST /substituicoes (não no POST escala-trabalho).
    // Alterações apenas de escala (ex.: sobreaviso SO) não disparam esse fluxo até haver registo em SUBSTITUICAO_ESCALA.
    if (oficial) {
      const escalaId = workflow.value?.escala_id ? Number(workflow.value.escala_id) : null
      const tMeta = (turnosDisponiveis.value || []).find(
        (t) => String(t.cod || '').toUpperCase() === String(ctx.turno || '').toUpperCase()
      )
      const faixa = parseFaixaTurnoLabel(tMeta?.hora)
      const localNome = nomeSetorSelecionado.value
      if (!escalaId) {
        showToast('⚠️ Grade atualizada. Substituição oficial em folha não registada: selecione um setor (escala com cabeçalho) — visão macro não tem escala_id único.')
      } else {
        try {
          await api.post('/api/v3/substituicoes', {
            escala_id: escalaId,
            solicitante_id: ctx.origem.funcionario_id,
            substituto_id: ctx.destino.funcionario_id,
            data_plantao: data,
            turno: ctx.turno,
            motivo: 'Substituição oficial gerada via arraste na Escala de Trabalho',
            horario_inicio: faixa.inicio || undefined,
            horario_fim: faixa.fim || undefined,
            unidade_escolar: localNome || undefined,
            disciplina_cargo: ctx.destino?.nome ? `Substituto: ${ctx.destino.nome}` : undefined,
          })
        } catch (subErr) {
          showToast(
            `⚠️ Grade atualizada; falha ao gravar substituição/notificação: ${subErr?.response?.data?.erro || subErr?.response?.data?.message || 'erro'}`
          )
        }
      }
    }
    await fetchEscala()
    if (!oficial) {
      showToast('✅ Turno movido na grade.')
    } else if (workflow.value?.escala_id) {
      showToast('✅ Substituição oficial e grade atualizadas.')
    }
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha ao processar troca.'}`)
  } finally {
    fecharSubstituicao()
  }
}

const apagarCelula = async (linha, dia) => {
  if (gradeSomenteLeitura.value) return
  if (isDiaAfastamento(linha, dia)) {
    showToast('⛔ Célula bloqueada por afastamento.')
    return
  }
  const turnoAtual = String(getTurnoCod(linha, dia) || '').trim()
  if (!turnoAtual) return
  if (isDiaPassado(dia)) {
    showToast('⛔ Data passada bloqueada: alterações só são permitidas a partir de hoje.')
    return
  }
  const data = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(dia).padStart(2, '0')}`
  const apagarPayload = {
    funcionario_id: linha.funcionario_id,
    data,
    turno: null,
    setor_id: setorSel.value || undefined,
  }
  apagarPayload.observacao = `Remoção pontual via clique borracha (${turnoAtual})`
  try {
    await api.post('/api/v3/escala-trabalho', apagarPayload)
    await fetchEscala()
    showToast('🧽 Célula apagada com segurança.')
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha ao apagar célula.'}`)
  }
}

const limparMesServidor = async (linha) => {
  if (gradeSomenteLeitura.value) return
  const diasComTurno = diasMes.value.filter((d) => !isDiaAfastamento(linha, d.num) && String(getTurnoCod(linha, d.num) || '').trim() !== '')
  const totalApagar = diasComTurno.length
  if (!totalApagar) return
  if (totalApagar > 5) {
    modalLimpezaCritica.value = { aberto: true, total: totalApagar, linha }
    return
  }
  await executarLimpezaMensal(linha, diasComTurno)
}

const confirmarLimpezaCritica = async () => {
  const linha = modalLimpezaCritica.value.linha
  modalLimpezaCritica.value.aberto = false
  if (!linha) return
  const diasComTurno = diasMes.value.filter((d) => String(getTurnoCod(linha, d.num) || '').trim() !== '')
  await executarLimpezaMensal(linha, diasComTurno)
}

const executarLimpezaMensal = async (linha, diasComTurno) => {
  if (gradeSomenteLeitura.value) return
  if (!linha) return
  const temRetroativo = diasComTurno.some((d) => isRetroativa(`${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(d.num).padStart(2, '0')}`))
  if (temRetroativo) {
    showToast('⛔ Limpeza bloqueada: existem dias passados e retroativo não é permitido.')
    return
  }
  try {
    for (const d of diasMes.value) {
      const turnoAtual = String(getTurnoCod(linha, d.num) || '').trim()
      if (!turnoAtual) continue
      const data = `${anoRef.value}-${String(mesRef.value).padStart(2, '0')}-${String(d.num).padStart(2, '0')}`
      const pay = {
        funcionario_id: linha.funcionario_id,
        data,
        turno: null,
        setor_id: setorSel.value || undefined,
      }
      pay.observacao = `Limpeza mensal em massa (${turnoAtual})`
      await api.post('/api/v3/escala-trabalho', pay)
    }
    await fetchEscala()
    showToast('✅ Limpeza mensal concluída com auditoria.')
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha na limpeza mensal.'}`)
  }
}

const copiarMesAnterior = async () => {
  if (gradeSomenteLeitura.value) return
  try {
    await api.post('/api/v3/escala-trabalho/copiar-mes-anterior', {
      mes: mesRef.value,
      ano: anoRef.value,
      setor_id: setorSel.value || undefined,
    })
    await fetchEscala()
    showToast('✅ Base do mês anterior copiada com sucesso.')
  } catch (e) {
    showToast(`❌ ${e?.response?.data?.erro || 'Falha ao copiar mês anterior.'}`)
  }
}

const fetchMotivos = async () => {
  try {
    const { data } = await api.get('/api/v3/motivos-alteracao-escala')
    motivosAlteracao.value = data.motivos ?? []
  } catch {
    motivosAlteracao.value = []
  }
}

const fetchEscala = async () => {
  loading.value = true
  try {
    const params = { mes: mesRef.value, ano: anoRef.value }
    if (macroCarregarTudo.value) {
      params.carregar_tudo = 1
      params.page = paginacao.value.page
      params.per_page = paginacao.value.per_page || 50
    } else if (String(setorSel.value || '').trim() !== '') {
      params.setor_id = setorSel.value
    }
    const { data } = await api.get('/api/v3/escala-trabalho', { params })
    escala.value = data.escala ?? []
    setores.value = data.setores ?? []
    funcionarios.value = data.funcionarios ?? []
    workflow.value = data.workflow ?? null
    hintApi.value = data.hint || ''
    if (data.paginacao) {
      paginacao.value = {
        page: Number(data.paginacao.page) || 1,
        per_page: Number(data.paginacao.per_page) || 50,
        total: Number(data.paginacao.total) || 0,
        last_page: Number(data.paginacao.last_page) || 1,
      }
    }
    if (macroCarregarTudo.value || String(setorSel.value || '').trim() !== '') {
      await fetchTurnos()
    }
    await fetchMotivos()
  } catch {
    escala.value = []
    workflow.value = null
    if (!mostrarGrade.value) {
      setores.value = []
    }
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
}

const fetchTurnos = async () => {
  const fallback = [
    { cod: 'M', nome: 'Manhã', hora: '07-13h', cor: '#1d4ed8', corLight: '#eff6ff' },
    { cod: 'V', nome: 'Vespertino', hora: '13-19h', cor: '#b45309', corLight: '#fffbeb' },
    { cod: 'N', nome: 'Noturno', hora: '19-22h', cor: '#4f46e5', corLight: '#f0f9ff' },
    { cod: 'I', nome: 'Integral', hora: '07-17h', cor: '#0f766e', corLight: '#f0fdfa' },
    { cod: 'F', nome: 'Folga', hora: '—', cor: '#64748b', corLight: '#f8fafc' },
    { cod: 'SO', nome: 'Sobreaviso', hora: '—', cor: '#9d174d', corLight: '#fce7f3' },
    { cod: 'AT', nome: 'Afastamento', hora: '—', cor: '#dc2626', corLight: '#fef2f2' },
  ]
  const ajustaLegendaEscala = (t) => {
    const cod = String(t?.cod || '').toUpperCase()
    let nome = String(t?.nome || '').trim()
    if (cod === 'F' && /folha/i.test(nome)) nome = 'Folga'
    if (cod === 'SO' && /planej/i.test(nome)) nome = 'Sobreaviso'
    return { ...t, nome: nome || 'Turno' }
  }
  try {
    const { data } = await api.get('/api/v3/turnos')
    const fromApi = (data?.turnos || [])
      .filter((t) => t?.ativo !== false)
      .map((t) => ({
        cod: String(t.codigo || '').trim().toUpperCase(),
        nome: String(t.nome || 'Turno').trim(),
        hora: String(t.hora || t.faixa || '—').trim(),
      }))
      .filter((t) => t.cod)

    const map = new Map()
    fallback.forEach((t) => map.set(t.cod, t))
    fromApi.forEach((t) => {
      const base = map.get(t.cod)
      map.set(t.cod, { ...(base || {}), ...t })
    })
    turnosDisponiveis.value = Array.from(map.values()).map(ajustaLegendaEscala)
  } catch {
    turnosDisponiveis.value = fallback.map(ajustaLegendaEscala)
  }
}

onMounted(async () => {
  await fetchEscala()
  await sincronizarFixacaoTurnos('mounted_after_fetch')
  // #region agent log
  postDebugLog('H3', 'EscalaTrabalhoView.vue:sticky:host', 'host de rolagem resolvido', {
    hostTag: scrollHostEl.value?.tagName || null,
    hostClass: scrollHostEl.value?.className || null,
  })
  // #endregion
  logStickyDiagnostico('mounted_after_fetch')
  window.addEventListener('resize', onDebugResizeSticky, { passive: true })
})

onUnmounted(() => {
  detachScrollListener()
  window.removeEventListener('resize', onDebugResizeSticky)
})

watch([mostrarGrade, loading, macroCarregarTudo, setorSel], async () => {
  await sincronizarFixacaoTurnos('watch_grade_or_loading')
})

const abrirModal = (funcionarioId = '', data = '') => {
  if (gradeSomenteLeitura.value) {
    showToast('🔒 Escala em modo somente leitura neste status.')
    return
  }
  form.value = { funcionario_id: funcionarioId, data, turno: '', obs: '', motivo_id: '', documento_referencia: '' }
  editando.value = false; erroSalvar.value = ''
  modalAberto.value = true
}

const editarDia = (linha, dia) => {
  if (gradeSomenteLeitura.value) {
    showToast('🔒 Escala em modo somente leitura neste status.')
    return
  }
  if (isDiaAfastamento(linha, dia.num)) {
    showToast('⛔ Célula bloqueada por afastamento.')
    return
  }
  const d = String(anoRef.value) + '-' + String(mesRef.value).padStart(2,'0') + '-' + String(dia.num).padStart(2,'0')
  if (isRetroativa(d)) {
    void abrirResumoPontoLeitura(linha, dia.num)
    return
  }
  form.value = {
    funcionario_id: linha.funcionario_id,
    data: d,
    turno: linha.dias?.[dia.num]?.turno || '',
    obs: linha.dias?.[dia.num]?.obs || '',
    motivo_id: '',
    documento_referencia: '',
  }
  editando.value = true; erroSalvar.value = ''
  modalAberto.value = true
}

const salvar = async () => {
  if (gradeSomenteLeitura.value) {
    erroSalvar.value = 'Escala bloqueada para edição neste status.'
    return
  }
  if (!form.value.funcionario_id || !form.value.data || !form.value.turno) {
    erroSalvar.value = 'Preencha todos os campos obrigatórios.'; return
  }
  if (isRetroativa(String(form.value.data))) {
    erroSalvar.value = 'Data passada bloqueada: só é permitido registrar escala a partir de hoje.'
    return
  }
  salvando.value = true; erroSalvar.value = ''
  try {
    const body = {
      funcionario_id: form.value.funcionario_id,
      data: form.value.data,
      turno: form.value.turno,
      setor_id: setorSel.value || undefined,
      observacao: form.value.obs,
      motivo_id: form.value.motivo_id || undefined,
      documento_referencia: form.value.documento_referencia || undefined,
    }
    await api.post('/api/v3/escala-trabalho', body)
    showToast('✅ Escala registrada!')
    modalAberto.value = false
    await fetchEscala()
  } catch (e) { erroSalvar.value = e.response?.data?.erro || 'Erro ao salvar.' }
  finally { salvando.value = false }
}

const exportarPDF = () => {
  const turnoCorBg = { m: '#dbeafe', v: '#fef3c7', n: '#e0e7ff', i: '#dcfce7', f: '#f1f5f9', so: '#fce7f3', at: '#ffedd5' }
  const turnoCorTx = { m: '#1d4ed8', v: '#92400e', n: '#3730a3', i: '#166534', f: '#64748b', so: '#9d174d', at: '#9a3412' }

  const headerDias = diasMes.value.map(d => {
    const bg = d.fimSemana ? '#f1f5f9' : '#fff'
    return `<th style="min-width:28px;max-width:28px;text-align:center;padding:4px 1px;font-size:9px;border:1px solid #e2e8f0;background:${bg};color:${d.fimSemana?'#94a3b8':'#475569'}"><div>${d.num}</div><div style="font-size:8px;color:#94a3b8">${d.sem}</div></th>`
  }).join('')

  const linhas = escalaFiltrada.value.map(linha => {
    const cells = diasMes.value.map(d => {
      const cod = getTurnoCod(linha, d.num)
      if (!cod) return `<td style="min-width:28px;border:1px solid #f1f5f9;"></td>`
      const key = cod.toLowerCase()
      const bg = turnoCorBg[key] ?? '#f1f5f9'
      const tx = turnoCorTx[key] ?? '#64748b'
      return `<td style="min-width:28px;border:1px solid #f1f5f9;text-align:center;background:${bg};font-size:9px;font-weight:800;color:${tx};padding:3px 1px">${cod}</td>`
    }).join('')
    const total = diasMes.value.filter(d => { const c = getTurnoCod(linha, d.num); return c && c !== 'F' }).length
    return `<tr>
      <td style="padding:6px 10px;font-size:11px;white-space:nowrap;border:1px solid #e2e8f0;min-width:190px;max-width:190px">
        <div style="font-weight:700;color:#1e293b">${linha.nome}</div>
        <div style="font-size:9px;color:#94a3b8">${linha.setor ?? ''}</div>
      </td>
      ${cells}
      <td style="text-align:center;padding:4px 8px;font-size:10px;font-weight:800;color:#6366f1;border:1px solid #e2e8f0">${total}</td>
    </tr>`
  }).join('')

  const legenda = turnosCores.map(t => {
    const bg = turnoCorBg[t.cod.toLowerCase()] ?? '#f1f5f9'
    const tx = turnoCorTx[t.cod.toLowerCase()] ?? '#64748b'
    return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${tx};border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700;margin:3px">${t.cod} – ${t.nome}</span>`
  }).join('')

  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <title>Escala de Trabalho – ${mesSelecionado.value}</title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;padding:20px}
      @media print{body{padding:0}@page{size:landscape;margin:10mm}}
      h1{font-size:15px;color:#1e3a8a;margin-bottom:2px}
      .sub{font-size:11px;color:#64748b;margin-bottom:14px}
      table{border-collapse:collapse;width:100%}
      th{background:#f8fafc;font-size:10px;font-weight:700;color:#475569;padding:6px 2px;border:1px solid #e2e8f0}
      .leg{margin-top:14px}
    </style></head><body>
    <h1>📅 Escala de Trabalho — ${mesSelecionado.value}</h1>
    <div class="sub">Gerado em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</div>
    <table>
      <thead><tr>
        <th style="min-width:190px;text-align:left;padding:6px 10px">Funcionário / Setor</th>
        ${headerDias}
        <th style="min-width:40px">Total</th>
      </tr></thead>
      <tbody>${linhas}</tbody>
    </table>
    <div class="leg"><strong>Legenda:</strong><br/>${legenda}</div>
    <script>window.onload=()=>{window.print()}<\/script>
  </body></html>`

  const win = window.open('', '_blank', 'width=1200,height=800')
  if (!win) { alert('Permita popups para exportar PDF.'); return }
  win.document.open()
  win.document.write(html)
  win.document.close()
}
</script>

<style scoped>
.escala-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
  max-width: 100%;
  font-family: 'Inter', system-ui, sans-serif;
}
.hero { position: relative; border-radius: 22px; padding: 26px 32px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #1a3a3a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #0d9488; right: -40px; top: -50px; }
.hs2 { width: 160px; height: 160px; background: #6366f1; right: 240px; bottom: -50px; }
.hs3 { width: 160px; height: 160px; background: #f59e0b; left: 38%; top: -40px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-ctrl { display: flex; align-items: center; gap: 12px; }
.mode-switch {
  display: inline-flex;
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 11px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.25);
}
.mode-btn {
  border: none;
  background: transparent;
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 700;
  padding: 7px 10px;
  cursor: pointer;
}
.mode-btn.active {
  background: rgba(255,255,255,0.2);
  color: #fff;
}
.ctrl-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.08); color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.ctrl-btn:hover { background: rgba(255,255,255,0.15); }
.ctrl-mes { font-size: 15px; font-weight: 800; color: #fff; min-width: 90px; text-align: center; }
.toolbar { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.08s; flex-wrap: wrap; }
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; min-width: 180px; display: flex; align-items: center; gap: 8px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.filter-sel { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; }
.novo-btn { padding: 9px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.novo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.35); }
.save-btn { padding: 9px 18px; border-radius: 12px; border: none; background: #0d9488; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.save-btn:hover { background: #0f766e; transform: translateY(-1px); }
.pdf-btn { display: flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; color: #475569; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.pdf-btn:hover:not(:disabled) { background: #f8fafc; border-color: #6366f1; color: #6366f1; }
.pdf-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.turnos-sticky-stack {
  position: sticky;
  top: 0;
  z-index: 20; /* acima da grade, abaixo de topbar/dropdowns/modais */
  background: #f8fafc;
  padding-top: 4px;
}
.turnos-bar {
  position: relative;
  z-index: 21;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(4px);
}
.turnos-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.turnos-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.turno-chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border: 1px solid color-mix(in srgb, var(--tc, #e2e8f0) 30%, transparent); border-radius: 10px; background: var(--tl, #fff); cursor: grab; user-select: none; }
.turno-chip:active { cursor: grabbing; }
.turno-chip.active { box-shadow: 0 0 0 2px #dc2626 inset; }
.turno-sigla { font-size: 11px; font-weight: 900; color: var(--tc, #1e293b); }
.turno-nome { font-size: 11px; color: color-mix(in srgb, var(--tc, #64748b) 80%, #000); font-weight: 700; }
.turno-hora { font-size: 10px; color: color-mix(in srgb, var(--tc, #64748b) 60%, #888); font-weight: 700; }
.turno-apagar { border-color: #fecaca; background: #fff1f2; }
.vacancia-alerta { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 10px; padding: 8px 10px; font-size: 12px; font-weight: 700; }
.workflow-toolbar {
  display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
  padding: 10px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc;
}
.wf-status-pill {
  font-size: 12px; font-weight: 800; color: #0f172a; background: #e2e8f0; border-radius: 999px; padding: 6px 12px;
}
.wf-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.wf-action-btn {
  border: 1px solid #cbd5e1; background: #fff; color: #334155; font-size: 12px; font-weight: 700;
  border-radius: 10px; padding: 7px 12px; cursor: pointer;
}
.wf-action-btn:hover { border-color: #6366f1; color: #4338ca; }
.wf-action-btn.wf-danger { border-color: #fecaca; background: #fff1f2; color: #991b1b; }
.wf-action-btn.wf-success { border-color: #bbf7d0; background: #ecfdf5; color: #166534; }
.banner-devolucao {
  border: 2px solid #f59e0b; background: linear-gradient(90deg, #fffbeb, #fef3c7);
  color: #92400e; border-radius: 12px; padding: 12px 14px; font-size: 13px;
}
.banner-devolucao strong { display: block; margin-bottom: 6px; color: #b45309; }
.banner-devolucao-motivo { margin: 0 0 6px; white-space: pre-wrap; font-weight: 600; color: #78350f; }
.banner-devolucao-meta { margin: 0; font-size: 12px; color: #92400e; }
.bar-readonly { opacity: 0.55; pointer-events: none; }
.sudo-grade-banner {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 14px; border-radius: 12px;
  border: 2px solid #ca8a04; background: linear-gradient(90deg, #fffbeb, #fef9c3);
  color: #854d0e; font-size: 12px; font-weight: 600; line-height: 1.45;
}
.sudo-grade-ico { font-size: 18px; line-height: 1; flex-shrink: 0; }
.turnos-bar.sudo-unlock-active, .grade-card.sudo-unlock-active {
  box-shadow: 0 0 0 2px rgba(202, 138, 4, 0.55), 0 0 0 6px rgba(250, 204, 21, 0.12);
}
.grade-readonly { position: relative; }
.grade-readonly::after {
  content: ''; position: absolute; inset: 0; background: repeating-linear-gradient(
    -12deg, transparent, transparent 10px, rgba(148, 163, 184, 0.06) 10px, rgba(148, 163, 184, 0.06) 20px
  ); pointer-events: none; border-radius: 20px;
}
.novo-btn:disabled, .pdf-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }
/* GRADE */
.grade-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  min-width: 0;
  max-width: 100%;
  overflow: hidden; /* cantos + overlay somente-leitura; scroll horizontal fica em .grade-scroll */
  opacity: 0;
  transform: translateY(8px);
  transition: all 0.4s 0.12s;
}
.grade-card.loaded { opacity: 1; transform: none; }
.grade-scroll {
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.grade-table { width: 100%; border-collapse: collapse; min-width: 920px; }
.grade-table thead tr { background: #0f172a; color: #fff; }
.th-nome { padding: 10px 14px; min-width: 220px; text-align: left; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; background: #0f172a; }
.limpar-mes-btn { margin-top: 6px; border: 1px solid #fecaca; color: #991b1b; background: #fff1f2; border-radius: 8px; padding: 3px 8px; font-size: 10px; font-weight: 700; cursor: pointer; }
.sticky-col { position: sticky; left: 0; z-index: 2; box-shadow: 4px 0 8px -4px rgba(0,0,0,0.08); }
.th-dia { min-width: 36px; max-width: 36px; padding: 4px 2px; text-align: center; border-right: 1px solid #1e293b; }
.th-fds { background: rgba(255,255,255,0.03); }
.dia-num { display: block; font-size: 12px; font-weight: 900; color: #fff; }
.dia-dow { display: block; font-size: 9px; color: #94a3b8; }
.th-sum { min-width: 54px; font-size: 10px; color: #94a3b8; text-align: center; }
.grade-row { border-bottom: 1px solid #f1f5f9; }
.grade-row:hover { background: #fafafa; }
.td-nome { padding: 10px 12px; background: #fff; }
.grade-row:hover .td-nome { background: #fafafa; }
.nome-wrap { display: flex; align-items: center; gap: 8px; }
.func-avatar { width: 30px; height: 30px; border-radius: 8px; background: #334155; color: #fff; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.func-info { min-width: 0; }
.func-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.func-setor { display: block; font-size: 11px; color: #94a3b8; }
.td-cell { min-width: 36px; height: 42px; padding: 3px; border-right: 1px solid #f1f5f9; }
.td-cell.cell-fds { background: #f8fafc; }
.td-cell.cell-bloqueada { background: #f8fafc; opacity: 0.65; cursor: not-allowed; }
.td-cell[data-active="true"] { background: rgba(13,148,136,0.08); }
.td-cell.cell-bloqueada[data-active="true"] { background: #f8fafc; }
.cell-turno { width: 100%; height: 100%; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; border: 1px solid transparent; }
.cel-m { background: #dbeafe; color: #1d4ed8; }
.cel-v { background: #fef3c7; color: #92400e; }
.cel-n { background: #e0e7ff; color: #3730a3; }
.cel-i { background: #dcfce7; color: #166534; }
.cel-f { background: #f1f5f9; color: #64748b; }
.cel-so { background: #fce7f3; color: #9d174d; }
.cel-at { background: #ffedd5; color: #9a3412; }
.cel-afastamento { position: relative; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.16); }
.afastamento-badge-15 {
  position: absolute;
  right: 2px;
  top: 2px;
  font-size: 8px;
  line-height: 1;
  font-weight: 900;
  color: #7f1d1d;
  background: #fef3c7;
  border: 1px solid #f59e0b;
  border-radius: 999px;
  padding: 1px 3px;
}
.td-sum { text-align: center; min-width: 54px; }
.sum-val { background: #f1f5f9; border-radius: 8px; padding: 4px 7px; font-size: 12px; font-weight: 800; color: #475569; }
/* LEGENDA */
.legenda { display: flex; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 18px; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.15s; }
.legenda.loaded { opacity: 1; transform: none; }
.leg-item { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #475569; padding: 4px 10px; border-radius: 8px; }
.leg-dot { width: 8px; height: 8px; border-radius: 3px; background: currentColor; }
.tur-m .leg-dot { color: #1d4ed8; } .tur-v .leg-dot { color: #92400e; } .tur-n .leg-dot { color: #3730a3; }
.tur-i .leg-dot { color: #166534; } .tur-f .leg-dot { color: #64748b; } .tur-so .leg-dot { color: #9d174d; } .tur-at .leg-dot { color: #9a3412; }
/* ESTADO */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; gap: 10px; font-size: 36px; color: #94a3b8; }
.state-box p { font-size: 14px; margin: 0; }
.state-box-escala-empty .empty-ico { font-size: 2rem; line-height: 1; }
.state-box-escala-empty p { max-width: 480px; text-align: center; color: #475569; line-height: 1.45; }
.state-box-escala-empty .empty-sub { font-size: 12px; color: #64748b; margin-top: 8px; max-width: 520px; line-height: 1.4; }
.state-box-escala-empty .empty-sub code { font-size: 11px; }
.spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.62); backdrop-filter: blur(8px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #6366f1; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.hint-text { font-size: 12px; color: #64748b; line-height: 1.45; margin: 0; }
.cfg-textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 56px; background: #fff; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.toast-msg { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
/* Master-detail (setores) + macro */
.voltar-lista-btn { padding: 9px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; color: #334155; font-size: 12px; font-weight: 800; cursor: pointer; }
.voltar-lista-btn:hover { border-color: #6366f1; color: #4338ca; }
.macro-wrap { display: flex; flex-direction: column; gap: 4px; max-width: 280px; }
.macro-btn { padding: 9px 14px; border-radius: 12px; border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; font-size: 12px; font-weight: 800; cursor: pointer; text-align: left; }
.macro-btn:hover { background: #fef3c7; }
.macro-warn { margin: 0; font-size: 11px; color: #b45309; line-height: 1.35; font-weight: 600; }
.hint-banner { margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: 12px; }
.state-box.init-state { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px 24px 28px; align-items: stretch; text-align: left; }
.state-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
.state-head h3 { margin: 0 0 4px; font-size: 18px; font-weight: 800; color: #0f172a; }
.state-head p { margin: 0; font-size: 13px; color: #64748b; max-width: 520px; }
.state-ico { font-size: 36px; line-height: 1; }
.escala-cards-wrap { display: grid; gap: 10px; width: 100%; }
.escala-cards-empty { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; font-size: 13px; color: #64748b; }
.escala-card { border: 1px solid #dbeafe; border-radius: 12px; background: linear-gradient(180deg, #f8fbff, #fff); overflow: hidden; }
.escala-card.expanded { border-color: #93c5fd; box-shadow: 0 8px 22px -16px rgba(30, 64, 175, 0.45); }
.escala-card-head { width: 100%; border: none; background: transparent; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; cursor: pointer; text-align: left; font-family: inherit; }
.ec-title { font-size: 14px; font-weight: 800; color: #0f172a; }
.ec-sub { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 2px; }
.ec-arrow { font-size: 16px; color: #1d4ed8; }
.escala-card-body { border-top: 1px solid #dbeafe; background: #f8fbff; padding: 10px 14px 12px; display: grid; gap: 10px; }
.ec-metrics { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: #334155; }
.ec-open-btn { justify-self: flex-start; border: 1px solid #93c5fd; background: #eff6ff; color: #1e40af; border-radius: 10px; padding: 7px 12px; font-size: 12px; font-weight: 800; cursor: pointer; font-family: inherit; }
.pager-macro { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; margin-top: 8px; }
.pager-meta { font-size: 12px; font-weight: 700; color: #475569; }
.macro-badge { font-size: 11px; font-weight: 800; color: #92400e; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 6px 10px; }
.modal-ponto-resumo .ponto-resumo-loading { font-size: 13px; color: #64748b; padding: 8px 0; }
.ponto-batidas-list { list-style: none; margin: 0; padding: 0; max-height: 220px; overflow-y: auto; }
.ponto-batidas-list li { display: flex; gap: 12px; padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.pb-hora { font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a; min-width: 52px; }
.pb-tipo { color: #475569; text-transform: capitalize; }
</style>
