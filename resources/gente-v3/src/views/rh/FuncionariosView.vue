<template>
  <div class="func-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">Recursos Humanos</span>
          <h1 class="hero-title">Funcionários</h1>
          <p class="hero-sub">{{ total }} servidores cadastrados · página {{ paginaAtual }} de {{ ultimaPagina }}</p>
        </div>
        <div class="hero-chips">
          <div class="chip"><span class="chip-dot green"></span><span>Ativos</span><strong>{{ ativos }}</strong></div>
          <div class="chip"><span class="chip-dot red"></span><span>Inativos</span><strong>{{ total - ativos }}</strong></div>
          <button class="btn-novo" @click="abrirModalNovo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Funcionário
          </button>
        </div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar" :class="{ loaded }">
      <div class="search-wrap">
        <svg class="search-ico" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input v-model="busca" class="search-input" placeholder="Buscar por nome, matrícula ou setor..." @input="debounceBusca" />
        <button v-if="busca" class="search-clear" @click="busca = ''; fetchFuncionarios()">✕</button>
      </div>
      <div class="toolbar-right">
        <select v-model="filtroAtivo" class="filter-select" @change="fetchFuncionarios()">
          <option value="">Todos</option>
          <option value="1">Ativos</option>
          <option value="0">Inativos</option>
        </select>
        <span class="result-count">{{ funcionarios.length }} resultado{{ funcionarios.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando servidores...</p></div>
    <!-- ERRO -->
    <div v-else-if="erro" class="state-box error">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p>{{ erro }}</p>
      <button class="retry-btn" @click="fetchFuncionarios">Tentar novamente</button>
    </div>
    <!-- VAZIO -->
    <div v-else-if="funcionarios.length === 0" class="state-box empty">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <p>Nenhum funcionário encontrado para "{{ busca }}"</p>
    </div>

    <!-- TABELA -->
    <div v-else class="table-card" :class="{ loaded }">
      <table class="func-table">
        <thead>
          <tr>
            <th>Servidor</th>
            <th>Matrícula</th>
            <th>Lotação / Setor</th>
            <th>Vínculo</th>
            <th>Início</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(f, i) in funcionarios"
            :key="f.FUNCIONARIO_ID"
            class="func-row"
            :class="{ 'row-visible': loaded }"
            :style="{ '--row-delay': `${i * 30}ms` }"
          >
            <td class="td-name">
              <div class="avatar-wrap">
                <div class="func-avatar" :style="{ '--hue': avatarHue(f.FUNCIONARIO_ID) }">
                  {{ iniciais(f.pessoa?.PESSOA_NOME) }}
                </div>
                <div>
                  <span class="func-nome">{{ f.pessoa?.PESSOA_NOME || '—' }}</span>
                  <span class="func-cpf">CPF {{ f.pessoa?.PESSOA_CPF_NUMERO || '—' }}</span>
                </div>
              </div>
            </td>
            <td><code class="matricula-chip">{{ f.FUNCIONARIO_MATRICULA || '—' }}</code></td>
            <td>
              <span class="lotacao-info">{{ f.setor || '—' }}</span>
            </td>
            <td><span class="vinculo-badge">{{ f.vinculo || '—' }}</span></td>
            <td>{{ formatDate(f.FUNCIONARIO_DATA_INICIO) }}</td>
            <td>
              <span class="status-badge" :class="f.FUNCIONARIO_DATA_FIM ? 'badge-red' : 'badge-green'">
                <span class="badge-dot"></span>
                {{ f.FUNCIONARIO_DATA_FIM ? 'Inativo' : 'Ativo' }}
              </span>
            </td>
            <td>
              <div class="row-actions">
                <button class="act-btn act-blue" title="Ver perfil" @click.stop="$router.push(`/funcionario/${f.FUNCIONARIO_ID}`)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                </button>
                <button class="act-btn act-purple" title="Editar" @click.stop="abrirModalEdicao(f)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button v-if="!f.FUNCIONARIO_DATA_FIM" class="act-btn act-red" title="Inativar" @click.stop="confirmarInativacao(f)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- PAGINAÇÃO -->
      <div class="pagination">
        <button class="pg-btn" :disabled="paginaAtual <= 1" @click="mudarPagina(paginaAtual - 1)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="pg-info">{{ paginaAtual }} / {{ ultimaPagina }}</span>
        <button class="pg-btn" :disabled="paginaAtual >= ultimaPagina" @click="mudarPagina(paginaAtual + 1)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════ MODAL CADASTRO / EDIÇÃO ══════════════════════════════ -->
    <teleport to="body">
      <transition name="modal-fade">
        <div v-if="modalAberto" class="modal-overlay" @click.self="fecharModal">
          <div class="modal-box">
            <div class="modal-header">
              <h2 class="modal-title">{{ modoEdicao ? 'Editar Funcionário' : 'Novo Funcionário' }}</h2>
              <button class="modal-close" @click="fecharModal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>

            <div class="modal-body">

              <!-- ── SEÇÃO: Dados Pessoais ──────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Dados Pessoais</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Nome Completo <span class="req">*</span></label>
                    <input v-model="form.PESSOA_NOME" type="text" class="form-input" placeholder="Nome completo do servidor" required />
                  </div>
                  <div class="form-group">
                    <label>CPF</label>
                    <input v-model="form.PESSOA_CPF_NUMERO" type="text" class="form-input" placeholder="000.000.000-00" />
                  </div>
                  <div class="form-group">
                    <label>Data de Nascimento</label>
                    <input v-model="form.PESSOA_DATA_NASCIMENTO" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Sexo</label>
                    <select v-model="form.PESSOA_SEXO" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Masculino</option>
                      <option value="2">Feminino</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Estado Civil</label>
                    <select v-model="form.PESSOA_ESTADO_CIVIL" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Solteiro(a)</option>
                      <option value="2">Casado(a)</option>
                      <option value="3">Divorciado(a)</option>
                      <option value="4">Viúvo(a)</option>
                      <option value="5">União Estável</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Escolaridade</label>
                    <select v-model="form.PESSOA_ESCOLARIDADE" class="form-input">
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
                    <label>Tipo de Sangue</label>
                    <select v-model="form.PESSOA_TIPO_SANGUE" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">A</option><option value="2">B</option>
                      <option value="3">AB</option><option value="4">O</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Fator RH</label>
                    <select v-model="form.PESSOA_RH_MAIS" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Positivo (+)</option>
                      <option value="0">Negativo (-)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>PcD (Pessoa com Deficiência)</label>
                    <select v-model="form.PESSOA_PCD" class="form-input">
                      <option value="0">Não</option>
                      <option value="1">Sim</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Raça / Cor <span class="req">*</span></label>
                    <select v-model="form.PESSOA_RACA" class="form-input">
                      <option value="">Selecione (autodeclaração)</option>
                      <option value="1">Branca</option>
                      <option value="2">Preta</option>
                      <option value="3">Parda</option>
                      <option value="4">Amarela</option>
                      <option value="5">Indígena</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Gênero</label>
                    <select v-model="form.PESSOA_GENERO" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Masculino</option>
                      <option value="2">Feminino</option>
                      <option value="3">Não binário</option>
                      <option value="4">Prefiro não informar</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Nacionalidade</label>
                    <select v-model="form.PESSOA_NACIONALIDADE" class="form-input">
                      <option value="">Selecione</option>
                      <option value="1">Brasileiro(a)</option>
                      <option value="2">Naturalizado(a) Brasileiro(a)</option>
                      <option value="3">Estrangeiro(a)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Município de Nascimento</label>
                    <input v-model="form.CIDADE_ID_NATURAL" type="text" class="form-input" placeholder="Cidade de nascimento" />
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Filiação ────────────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Filiação</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Nome da Mãe</label>
                    <input v-model="form.PESSOA_NOME_MAE" type="text" class="form-input" placeholder="Nome completo da mãe" />
                  </div>
                  <div class="form-group col-2">
                    <label>Nome do Pai</label>
                    <input v-model="form.PESSOA_NOME_PAI" type="text" class="form-input" placeholder="Nome completo do pai" />
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Contato ─────────────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Contato</h3>
                <div class="form-grid">
                  <div class="form-group">
                    <label>E-mail</label>
                    <input v-model="form.PESSOA_EMAIL" type="email" class="form-input" placeholder="email@exemplo.com" />
                  </div>
                  <div class="form-group">
                    <label>Celular</label>
                    <input v-model="form.PESSOA_CELULAR" type="text" class="form-input" placeholder="(00) 90000-0000" />
                  </div>
                  <div class="form-group">
                    <label>Telefone</label>
                    <input v-model="form.PESSOA_TELEFONE" type="text" class="form-input" placeholder="(00) 0000-0000" />
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Endereço ────────────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Endereço</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Logradouro</label>
                    <input v-model="form.PESSOA_ENDERECO" type="text" class="form-input" placeholder="Rua, Avenida, etc." />
                  </div>
                  <div class="form-group">
                    <label>Complemento</label>
                    <input v-model="form.PESSOA_COMPLEMENTO" type="text" class="form-input" placeholder="Apto, Bloco..." />
                  </div>
                  <div class="form-group">
                    <label>CEP</label>
                    <input v-model="form.PESSOA_CEP" type="text" class="form-input" placeholder="00000-000" />
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Documentos ──────────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Documentos</h3>
                <div class="form-grid">
                  <div class="form-group">
                    <label>RG (Número)</label>
                    <input v-model="form.PESSOA_RG_NUMERO" type="text" class="form-input" placeholder="Número do RG" />
                  </div>
                  <div class="form-group">
                    <label>RG (Órgão Expedidor)</label>
                    <input v-model="form.PESSOA_RG_EXPEDIDOR" type="text" class="form-input" placeholder="SSP, PM, etc." />
                  </div>
                  <div class="form-group">
                    <label>RG (UF)</label>
                    <select v-model="form.UF_ID_RG" class="form-input">
                      <option value="">Selecione a UF</option>
                      <option value="AC">AC</option><option value="AL">AL</option>
                      <option value="AP">AP</option><option value="AM">AM</option>
                      <option value="BA">BA</option><option value="CE">CE</option>
                      <option value="DF">DF</option><option value="ES">ES</option>
                      <option value="GO">GO</option><option value="MA">MA</option>
                      <option value="MT">MT</option><option value="MS">MS</option>
                      <option value="MG">MG</option><option value="PA">PA</option>
                      <option value="PB">PB</option><option value="PR">PR</option>
                      <option value="PE">PE</option><option value="PI">PI</option>
                      <option value="RJ">RJ</option><option value="RN">RN</option>
                      <option value="RS">RS</option><option value="RO">RO</option>
                      <option value="RR">RR</option><option value="SC">SC</option>
                      <option value="SP">SP</option><option value="SE">SE</option>
                      <option value="TO">TO</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>RG (Data Expedição)</label>
                    <input v-model="form.PESSOA_RG_EXPEDICAO" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>CNH (Número)</label>
                    <input v-model="form.PESSOA_CNH_NUMERO" type="text" class="form-input" placeholder="Número da CNH" />
                  </div>
                  <div class="form-group">
                    <label>CNH (Categoria)</label>
                    <select v-model="form.PESSOA_CNH_CATEGORIA" class="form-input">
                      <option value="">Sem CNH</option>
                      <option value="1">A</option><option value="2">B</option>
                      <option value="3">C</option><option value="4">D</option>
                      <option value="5">E</option><option value="6">AB</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>CNH (Validade)</label>
                    <input v-model="form.PESSOA_CNH_VALIDADE" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Título de Eleitor</label>
                    <input v-model="form.PESSOA_TITULO_NUMERO" type="text" class="form-input" placeholder="Número do título" />
                  </div>
                  <div class="form-group">
                    <label>Título (Zona)</label>
                    <input v-model="form.PESSOA_TITULO_ZONA" type="text" class="form-input" placeholder="Zona eleitoral" />
                  </div>
                  <div class="form-group">
                    <label>Título (Seção)</label>
                    <input v-model="form.PESSOA_TITULO_SECAO" type="text" class="form-input" placeholder="Seção eleitoral" />
                  </div>
                  <div class="form-group">
                    <label>PIS / PASEP</label>
                    <input v-model="form.PESSOA_PIS_PASEP" type="text" class="form-input" placeholder="000.00000.00-0" />
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Dados Funcionais ────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Dados Funcionais</h3>
                <div class="form-grid">
                  <div class="form-group">
                    <label>Matrícula</label>
                    <input v-model="form.FUNCIONARIO_MATRICULA" type="text" class="form-input" placeholder="Número de matrícula" />
                  </div>
                  <div class="form-group">
                    <label>Data de Admissão <span class="req">*</span></label>
                    <input v-model="form.FUNCIONARIO_DATA_INICIO" type="date" class="form-input" required />
                  </div>
                  <div class="form-group">
                    <label>Data de Demissão</label>
                    <input v-model="form.FUNCIONARIO_DATA_FIM" type="date" class="form-input" />
                  </div>
                  <div class="form-group col-full">
                    <label>Observação</label>
                    <textarea v-model="form.FUNCIONARIO_OBSERVACAO" class="form-input" rows="2" placeholder="Observações sobre o funcionário..."></textarea>
                  </div>
                </div>
              </div>

              <!-- ── SEÇÃO: Lotação ─────────────────────────── -->
              <div class="form-section">
                <h3 class="section-label">Lotação e Vínculo</h3>
                <div class="form-grid">
                  <div class="form-group col-2">
                    <label>Setor / Unidade</label>
                    <select v-model="form.SETOR_ID" class="form-input">
                      <option value="">Selecione o setor</option>
                      <option v-for="s in apoio.setores" :key="s.id" :value="s.id">
                        {{ s.unidade ? `${s.unidade} — ` : '' }}{{ s.nome }}
                      </option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Tipo de Vínculo</label>
                    <select v-model="form.VINCULO_ID" class="form-input">
                      <option value="">Selecione o vínculo</option>
                      <option v-for="v in apoio.vinculos" :key="v.id" :value="v.id">{{ v.nome }}</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Atribuição / Cargo</label>
                    <select v-model="form.ATRIBUICAO_ID" class="form-input">
                      <option value="">Selecione a atribuição</option>
                      <option v-for="a in apoio.atribuicoes" :key="a.id" :value="a.id">{{ a.nome }}</option>
                    </select>
                  </div>
                </div>
              </div>

            </div>

            <!-- FOOTER DO MODAL -->
            <div class="modal-footer">
              <p v-if="erroModal" class="modal-erro">{{ erroModal }}</p>
              <p v-if="successoModal" class="modal-sucesso">{{ successoModal }}</p>
              <button class="btn-cancel" @click="fecharModal" :disabled="salvando">Cancelar</button>
              <button class="btn-salvar" @click="salvar" :disabled="salvando">
                <svg v-if="salvando" class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0110 10" stroke-linecap="round"/></svg>
                <span v-if="!salvando">{{ modoEdicao ? 'Salvar Alterações' : 'Cadastrar Funcionário' }}</span>
                <span v-else>Salvando...</span>
              </button>
            </div>
          </div>
        </div>
      </transition>

      <!-- CONFIRM INATIVAÇÃO -->
      <transition name="modal-fade">
        <div v-if="confirmAberto" class="modal-overlay" @click.self="confirmAberto = false">
          <div class="modal-box modal-sm">
            <div class="modal-header">
              <h2 class="modal-title">Inativar Funcionário</h2>
              <button class="modal-close" @click="confirmAberto = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
              <p class="confirm-text">Tem certeza que deseja inativar <strong>{{ funcParaInativar?.pessoa?.PESSOA_NOME }}</strong>?</p>
              <div class="form-group">
                <label>Data de Desligamento</label>
                <input v-model="dataInativacao" type="date" class="form-input" />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn-cancel" @click="confirmAberto = false">Cancelar</button>
              <button class="btn-salvar btn-danger" @click="inativar" :disabled="salvando">
                {{ salvando ? 'Inativando...' : 'Confirmar Inativação' }}
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
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()

// ── Estado ─────────────────────────────────────────────────
const funcionarios = ref([])
const loading      = ref(true)
const loaded       = ref(false)
const erro         = ref('')
const busca        = ref('')
const filtroAtivo  = ref('1')
const paginaAtual  = ref(1)
const ultimaPagina = ref(1)
const total        = ref(0)

const apoio = ref({ setores: [], vinculos: [], atribuicoes: [] })

// ── Modal ───────────────────────────────────────────────────
const modalAberto    = ref(false)
const modoEdicao     = ref(false)
const salvando       = ref(false)
const erroModal      = ref('')
const successoModal  = ref('')
const confirmAberto  = ref(false)
const funcParaInativar = ref(null)
const dataInativacao = ref(new Date().toISOString().slice(0, 10))

const formVazio = () => ({
  /* Pessoa */
  PESSOA_NOME: '', PESSOA_CPF_NUMERO: '',
  PESSOA_DATA_NASCIMENTO: '', PESSOA_SEXO: '', PESSOA_ESTADO_CIVIL: '',
  PESSOA_ESCOLARIDADE: '', PESSOA_TIPO_SANGUE: '', PESSOA_RH_MAIS: '',
  PESSOA_PCD: '0', PESSOA_NOME_MAE: '', PESSOA_NOME_PAI: '',
  PESSOA_RACA: '', PESSOA_GENERO: '', PESSOA_NACIONALIDADE: '',
  CIDADE_ID_NATURAL: '',
  PESSOA_PIS_PASEP: '',
  PESSOA_EMAIL: '', PESSOA_CELULAR: '', PESSOA_TELEFONE: '',
  PESSOA_ENDERECO: '', PESSOA_COMPLEMENTO: '', PESSOA_CEP: '',
  PESSOA_RG_NUMERO: '', PESSOA_RG_EXPEDIDOR: '', UF_ID_RG: '', PESSOA_RG_EXPEDICAO: '',
  PESSOA_CNH_NUMERO: '', PESSOA_CNH_CATEGORIA: '', PESSOA_CNH_VALIDADE: '',
  PESSOA_TITULO_NUMERO: '', PESSOA_TITULO_ZONA: '', PESSOA_TITULO_SECAO: '',
  /* Funcionário */
  FUNCIONARIO_MATRICULA: '', FUNCIONARIO_DATA_INICIO: '', FUNCIONARIO_DATA_FIM: '',
  FUNCIONARIO_OBSERVACAO: '',
  /* Lotação */
  SETOR_ID: '', VINCULO_ID: '', ATRIBUICAO_ID: '',
  /* ID para edição */
  _id: null,
})
const form = ref(formVazio())

// ── Computed ────────────────────────────────────────────────
const ativos = computed(() =>
  funcionarios.value.filter(f => !f.FUNCIONARIO_DATA_FIM).length
)

// ── Carregamento ────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([fetchFuncionarios(), fetchApoio()])
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchFuncionarios = async (pag = 1) => {
  loading.value = true
  erro.value = ''
  paginaAtual.value = pag
  try {
    const params = { page: pag }
    if (busca.value)    params.q = busca.value
    if (filtroAtivo.value !== '') params.funcionario_ativo = filtroAtivo.value
    const { data } = await api.get('/api/v3/funcionarios', { params })
    funcionarios.value = data.data ?? data
    ultimaPagina.value = data.last_page ?? 1
    total.value        = data.total ?? funcionarios.value.length
  } catch (e) {
    erro.value = e.response?.data?.message || 'Erro ao carregar funcionários.'
  } finally {
    loading.value = false
  }
}

const fetchApoio = async () => {
  try {
    const { data } = await api.get('/api/v3/apoio')
    apoio.value = data
  } catch {}
}

// ── Debounce busca ──────────────────────────────────────────
let timer
const debounceBusca = () => {
  clearTimeout(timer)
  timer = setTimeout(() => fetchFuncionarios(1), 380)
}

const mudarPagina = (pag) => { fetchFuncionarios(pag) }

// ── Modal: Novo ─────────────────────────────────────────────
const abrirModalNovo = () => {
  form.value     = formVazio()
  modoEdicao.value = false
  erroModal.value  = ''
  successoModal.value = ''
  modalAberto.value = true
}

// ── Modal: Edição ───────────────────────────────────────────
const abrirModalEdicao = (f) => {
  const lotacao = (f.lotacoes ?? []).filter(l => !l.LOTACAO_DATA_FIM).sort((a, b) => b.LOTACAO_ID - a.LOTACAO_ID)[0]
  form.value = {
    _id: f.FUNCIONARIO_ID,
    /* Pessoa */
    PESSOA_NOME:            f.pessoa?.PESSOA_NOME ?? '',
    PESSOA_CPF_NUMERO:      f.pessoa?.PESSOA_CPF_NUMERO ?? '',
    PESSOA_DATA_NASCIMENTO: f.pessoa?.PESSOA_DATA_NASCIMENTO ?? '',
    PESSOA_SEXO:            f.pessoa?.PESSOA_SEXO ?? '',
    PESSOA_ESTADO_CIVIL:    f.pessoa?.PESSOA_ESTADO_CIVIL ?? '',
    PESSOA_ESCOLARIDADE:    f.pessoa?.PESSOA_ESCOLARIDADE ?? '',
    PESSOA_TIPO_SANGUE:     f.pessoa?.PESSOA_TIPO_SANGUE ?? '',
    PESSOA_RH_MAIS:         f.pessoa?.PESSOA_RH_MAIS ?? '',
    PESSOA_PCD:             f.pessoa?.PESSOA_PCD ?? '0',
    PESSOA_RACA:            f.pessoa?.PESSOA_RACA ?? '',
    PESSOA_GENERO:          f.pessoa?.PESSOA_GENERO ?? '',
    PESSOA_NACIONALIDADE:   f.pessoa?.PESSOA_NACIONALIDADE ?? '',
    CIDADE_ID_NATURAL:      f.pessoa?.CIDADE_ID_NATURAL ?? '',
    PESSOA_PIS_PASEP:       f.pessoa?.PESSOA_PIS_PASEP ?? '',
    PESSOA_NOME_MAE:        f.pessoa?.PESSOA_NOME_MAE ?? '',
    PESSOA_NOME_PAI:        f.pessoa?.PESSOA_NOME_PAI ?? '',
    PESSOA_EMAIL:           '',
    PESSOA_CELULAR:         '',
    PESSOA_TELEFONE:        '',
    PESSOA_ENDERECO:        f.pessoa?.PESSOA_ENDERECO ?? '',
    PESSOA_COMPLEMENTO:     f.pessoa?.PESSOA_COMPLEMENTO ?? '',
    PESSOA_CEP:             f.pessoa?.PESSOA_CEP ?? '',
    PESSOA_RG_NUMERO:       f.pessoa?.PESSOA_RG_NUMERO ?? '',
    PESSOA_RG_EXPEDIDOR:    f.pessoa?.PESSOA_RG_EXPEDIDOR ?? '',
    UF_ID_RG:               f.pessoa?.UF_ID_RG ?? '',
    PESSOA_RG_EXPEDICAO:    f.pessoa?.PESSOA_RG_EXPEDICAO ?? '',
    PESSOA_CNH_NUMERO:      f.pessoa?.PESSOA_CNH_NUMERO ?? '',
    PESSOA_CNH_CATEGORIA:   f.pessoa?.PESSOA_CNH_CATEGORIA ?? '',
    PESSOA_CNH_VALIDADE:    f.pessoa?.PESSOA_CNH_VALIDADE ?? '',
    PESSOA_TITULO_NUMERO:   f.pessoa?.PESSOA_TITULO_NUMERO ?? '',
    PESSOA_TITULO_ZONA:     f.pessoa?.PESSOA_TITULO_ZONA ?? '',
    PESSOA_TITULO_SECAO:    f.pessoa?.PESSOA_TITULO_SECAO ?? '',
    /* Funcionário */
    FUNCIONARIO_MATRICULA:    f.FUNCIONARIO_MATRICULA ?? '',
    FUNCIONARIO_DATA_INICIO:  f.FUNCIONARIO_DATA_INICIO ?? '',
    FUNCIONARIO_DATA_FIM:     f.FUNCIONARIO_DATA_FIM ?? '',
    FUNCIONARIO_OBSERVACAO:   f.FUNCIONARIO_OBSERVACAO ?? '',
    /* Lotação */
    SETOR_ID:       lotacao?.SETOR_ID ?? '',
    VINCULO_ID:     lotacao?.VINCULO_ID ?? '',
    ATRIBUICAO_ID:  '',
  }
  modoEdicao.value    = true
  erroModal.value     = ''
  successoModal.value = ''
  modalAberto.value   = true
}

const fecharModal = () => { modalAberto.value = false }

// ── Salvar (criar ou editar) ────────────────────────────────
const salvar = async () => {
  if (!form.value.PESSOA_NOME) { erroModal.value = 'O nome é obrigatório.'; return }
  salvando.value  = true
  erroModal.value = ''

  try {
    if (modoEdicao.value) {
      await api.put(`/api/v3/funcionarios/${form.value._id}`, form.value)
      successoModal.value = 'Funcionário atualizado com sucesso!'
    } else {
      await api.post('/api/v3/funcionarios', form.value)
      successoModal.value = 'Funcionário cadastrado com sucesso!'
    }
    await fetchFuncionarios(paginaAtual.value)
    setTimeout(() => fecharModal(), 1500)
  } catch (e) {
    erroModal.value = e.response?.data?.erro || e.response?.data?.message || 'Erro ao salvar. Tente novamente.'
  } finally {
    salvando.value = false
  }
}

// ── Inativar ────────────────────────────────────────────────
const confirmarInativacao = (f) => {
  funcParaInativar.value = f
  dataInativacao.value   = new Date().toISOString().slice(0, 10)
  confirmAberto.value    = true
}

const inativar = async () => {
  salvando.value = true
  try {
    await api.delete(`/api/v3/funcionarios/${funcParaInativar.value.FUNCIONARIO_ID}`, {
      data: { FUNCIONARIO_DATA_FIM: dataInativacao.value }
    })
    confirmAberto.value = false
    await fetchFuncionarios(paginaAtual.value)
  } catch (e) {
    alert(e.response?.data?.erro || 'Erro ao inativar.')
  } finally {
    salvando.value = false
  }
}

// ── Helpers ─────────────────────────────────────────────────
const iniciais = (nome) => {
  if (!nome) return '?'
  return nome.trim().split(' ').filter(Boolean).slice(0, 2).map(n => n[0].toUpperCase()).join('')
}
const avatarHue = (id) => ((id * 47) % 360)
const formatDate = (d) => {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-')
  return `${day}/${m}/${y}`
}
</script>

<style scoped>
.func-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero {
  position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #312e81 100%);
  border-radius: 24px; padding: 32px 40px; overflow: hidden;
  opacity: 0; transform: translateY(-12px);
  transition: all 0.5s cubic-bezier(0.22,1,0.36,1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 320px; height: 320px; background: #6366f1; top: -100px; right: -80px; }
.hs2 { width: 200px; height: 200px; background: #10b981; bottom: -60px; right: 280px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a78bfa; margin-bottom: 6px; }
.hero-title { font-size: 30px; font-weight: 900; color: #fff; margin: 0 0 4px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-chips { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.chip { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 8px 14px; font-size: 13px; color: #e2e8f0; }
.chip-dot { width: 8px; height: 8px; border-radius: 50%; }
.chip-dot.green { background: #10b981; }
.chip-dot.red { background: #f43f5e; }
.chip strong { color: #fff; }

.btn-novo {
  display: flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;
  border: none; border-radius: 14px; padding: 10px 18px;
  font-size: 14px; font-weight: 700; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 16px rgba(99,102,241,0.4);
}
.btn-novo:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,102,241,0.5); }

/* TOOLBAR */
.toolbar {
  display: flex; align-items: center; gap: 14px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px 18px;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.05s;
}
.toolbar.loaded { opacity: 1; transform: none; }
.search-wrap { flex: 1; display: flex; align-items: center; gap: 10px; }
.search-ico { width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0; }
.search-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; font-family: inherit; }
.search-input::placeholder { color: #cbd5e1; }
.search-clear { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 16px; padding: 0 4px; }
.toolbar-right { display: flex; align-items: center; gap: 12px; }
.filter-select { border: 1px solid #e2e8f0; border-radius: 10px; padding: 7px 12px; font-size: 13px; font-family: inherit; color: #475569; outline: none; cursor: pointer; }
.result-count { font-size: 12px; color: #94a3b8; font-weight: 600; white-space: nowrap; }

/* ESTADOS */
.state-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 20px; text-align: center; color: #64748b; gap: 12px; }
.state-box p { font-size: 15px; font-weight: 500; margin: 0; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.retry-btn { background: #6366f1; color: #fff; border: none; border-radius: 10px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 4px; }

/* TABLE CARD */
.table-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: visible; overflow-x: auto;
  opacity: 0; transform: translateY(12px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s;
}
.table-card.loaded { opacity: 1; transform: none; }
.func-table { width: 100%; border-collapse: collapse; }
.func-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.func-table th { padding: 12px 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; text-align: left; white-space: nowrap; }
.func-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; cursor: default; }
.func-row:hover { background: #f8fafc; }
.func-row:last-child { border-bottom: none; }
.func-row.row-visible td { animation: rowIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) var(--row-delay) both; }
@keyframes rowIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
.func-table td { padding: 13px 16px; font-size: 13px; color: #334155; vertical-align: middle; }

.td-name .avatar-wrap { display: flex; align-items: center; gap: 10px; }
.func-avatar { width: 38px; height: 38px; border-radius: 12px; background: hsl(var(--hue), 60%, 92%); color: hsl(var(--hue), 60%, 35%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; flex-shrink: 0; }
.func-nome { display: block; font-weight: 700; color: #1e293b; font-size: 14px; }
.func-cpf { display: block; font-size: 11px; color: #94a3b8; font-family: monospace; }
.matricula-chip { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 8px; padding: 3px 8px; font-size: 12px; font-weight: 700; font-family: monospace; }
.lotacao-info { font-size: 12px; color: #475569; }
.vinculo-badge { display: inline-block; background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; border-radius: 8px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-green { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.badge-red   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.row-actions { display: flex; gap: 5px; }
.act-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; transition: all 0.15s; color: #64748b; }
.act-btn:hover { transform: translateY(-1px); }
.act-btn.act-blue:hover  { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.act-btn.act-purple:hover { background: #f5f3ff; border-color: #ddd6fe; color: #7c3aed; }
.act-btn.act-red:hover   { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

/* PAGINAÇÃO */
.pagination { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px; border-top: 1px solid #f1f5f9; }
.pg-btn { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; cursor: pointer; transition: all 0.15s; color: #475569; }
.pg-btn:hover:not(:disabled) { border-color: #6366f1; color: #6366f1; }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pg-info { font-size: 13px; font-weight: 600; color: #475569; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-box { background: #fff; border-radius: 24px; width: 100%; max-width: 780px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 32px 80px rgba(0,0,0,0.25); }
.modal-sm { max-width: 440px; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 22px 28px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
.modal-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; color: #64748b; transition: all 0.15s; }
.modal-close:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
.modal-body { overflow-y: auto; padding: 24px 28px; flex: 1; display: flex; flex-direction: column; gap: 24px; }
.modal-footer { padding: 16px 28px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
.modal-erro   { flex: 1; font-size: 13px; font-weight: 600; color: #dc2626; margin: 0; }
.modal-sucesso { flex: 1; font-size: 13px; font-weight: 600; color: #15803d; margin: 0; }

/* FORM SECTIONS */
.form-section { border: 1px solid #f1f5f9; border-radius: 16px; padding: 20px; }
.section-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 0 0 14px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.col-2 { grid-column: span 2; }
.form-group.col-full { grid-column: 1 / -1; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.form-input {
  width: 100%; padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-family: inherit; color: #1e293b; outline: none;
  background: #fff; transition: border-color 0.15s; box-sizing: border-box;
}
.form-input:focus { border-color: #6366f1; }
textarea.form-input { resize: vertical; min-height: 64px; }
.req { color: #dc2626; }
.confirm-text { font-size: 15px; color: #334155; margin: 0 0 16px; }

/* BOTÕES MODAL */
.btn-cancel { padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; color: #475569; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.btn-cancel:hover { background: #f8fafc; }
.btn-salvar { display: flex; align-items: center; gap: 8px; padding: 10px 22px; border: none; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.btn-salvar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-salvar:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-danger { background: linear-gradient(135deg, #dc2626, #f43f5e) !important; box-shadow: none !important; }
.btn-danger:hover:not(:disabled) { box-shadow: 0 6px 20px rgba(220,38,38,0.4) !important; }

.esocial-tag { display: inline-block; background: #dbeafe; color: #1d4ed8; border-radius: 4px; padding: 1px 5px; font-size: 9px; font-weight: 800; letter-spacing: 0.05em; margin-left: 4px; vertical-align: middle; }
.spin { animation: spin 0.9s linear infinite; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .modal-box { transform: scale(0.96) translateY(10px); }

/* ═══ RESPONSIVE MOBILE ═══════════════════════════════════════════ */
@media (max-width: 768px) {
  /* Hero empilha */
  .hero { padding: 20px; }
  .hero-inner { flex-direction: column; align-items: flex-start; }
  .hero-title { font-size: 22px; }
  .hero-chips { flex-wrap: wrap; width: 100%; }
  .btn-novo { width: 100%; justify-content: center; }

  /* Tabela com scroll horizontal */
  .table-card { overflow-x: auto; border-radius: 16px; }
  .func-table { min-width: 600px; }

  /* Toolbar empilha */
  .toolbar { flex-direction: column; align-items: stretch; gap: 10px; }
  .toolbar-right { width: 100%; justify-content: space-between; }
  .search-input { width: 100%; }

  /* Modal fullscreen em mobile */
  .modal-box {
    width: 100vw;
    max-width: 100vw;
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
    margin: 0;
  }

  /* Form grid colapsa para 1 coluna */
  .form-grid { grid-template-columns: 1fr !important; }
  .form-group.col-2,
  .form-group.col-full { grid-column: 1; }
}

@media (max-width: 480px) {
  .modal-footer { flex-direction: column; gap: 8px; }
  .btn-cancel, .btn-salvar { width: 100%; justify-content: center; }
}

</style>
