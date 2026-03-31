<template>
  <div class="config-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div class="hero-avatar-wrap">
          <div class="hero-avatar" :style="{ '--h': avatarHue }">
            {{ iniciais(user?.PESSOA_NOME || user?.USUARIO_LOGIN || 'U') }}
          </div>
          <div class="hero-avatar-edit" @click="editandoNome = true" title="Editar perfil">✏️</div>
        </div>
        <div>
          <span class="hero-eyebrow">⚙️ Minha Conta</span>
          <h1 class="hero-title">{{ user?.PESSOA_NOME || user?.USUARIO_LOGIN || 'Usuário' }}</h1>
          <p class="hero-sub">{{ user?.USUARIO_LOGIN }} · {{ user?.email || 'Sem e-mail cadastrado' }}</p>
        </div>
        <div class="hero-meta" v-if="funcionario">
          <div class="hm-item"><span class="hm-label">Matrícula</span><span class="hm-val">{{ funcionario.FUNCIONARIO_MATRICULA || '—' }}</span></div>
          <div class="hm-item"><span class="hm-label">Setor</span><span class="hm-val">{{ funcionario.setor || '—' }}</span></div>
          <div class="hm-item"><span class="hm-label">Vínculo</span><span class="hm-val">{{ funcionario.vinculo || '—' }}</span></div>
        </div>
      </div>
    </div>

    <!-- GRID ─────────────────────────────────────────────────── -->
    <div class="config-grid" :class="{ loaded }">

      <!-- SIDEBAR de seções -->
      <div class="config-nav">
        <button
          v-for="s in secoes"
          :key="s.id"
          class="cnav-btn"
          :class="{ active: secaoAtiva === s.id }"
          @click="secaoAtiva = s.id"
        >
          <span class="cnav-ico">{{ s.ico }}</span>
          <span>{{ s.nome }}</span>
        </button>
      </div>

      <!-- CONTEÚDO das seções -->
      <div class="config-content">

        <!-- Segurança -->
        <template v-if="secaoAtiva === 'senha'">
          <h2 class="sec-title">🔐 Alterar Senha</h2>

          <div class="fields-col">
            <div class="field-group">
              <label>Senha Atual</label>
              <div class="input-wrap">
                <input v-model="senhaSub.atual" :type="mostraSenha ? 'text' : 'password'" class="cfg-input" placeholder="Digite sua senha atual" />
                <button class="eye-btn" @click="mostraSenha = !mostraSenha">{{ mostraSenha ? '🙈' : '👁️' }}</button>
              </div>
            </div>
            <div class="field-group">
              <label>Nova Senha</label>
              <input v-model="senhaSub.nova" type="password" class="cfg-input" placeholder="Mínimo 8 caracteres" />
              <div class="strength-bar" v-if="senhaSub.nova">
                <div class="strength-fill" :style="{ width: senhaForca.pct + '%', background: senhaForca.cor }"></div>
              </div>
              <span class="strength-label" v-if="senhaSub.nova" :style="{ color: senhaForca.cor }">{{ senhaForca.label }}</span>
            </div>
            <div class="field-group">
              <label>Confirmar Nova Senha</label>
              <input v-model="senhaSub.confirmar" type="password" class="cfg-input" placeholder="Repita a nova senha" />
              <span class="match-msg" v-if="senhaSub.confirmar" :class="senhasIguais ? 'match-ok' : 'match-err'">
                {{ senhasIguais ? '✅ Senhas conferem' : '❌ Senhas não conferem' }}
              </span>
            </div>
            <button class="save-btn" :disabled="!senhaValida || salvandoSenha" @click="alterarSenha">
              <div v-if="salvandoSenha" class="btn-spinner"></div>
              <template v-else>🔒 Alterar Senha</template>
            </button>
          </div>
        </template>

        <!-- Aparência -->
        <template v-if="secaoAtiva === 'aparencia'">
          <h2 class="sec-title">🎨 Preferências Visuais</h2>

          <div class="pref-section">
            <h3 class="pref-subtitle">Tema da Interface</h3>
            <div class="tema-options">
              <div v-for="t in temas" :key="t.id" class="tema-card" :class="{ 'tema-active': temaAtivo === t.id }" @click="temaAtivo = t.id">
                <div class="tema-preview" :style="{ background: t.bg }">
                  <div class="tema-sidebar" :style="{ background: t.sidebar }"></div>
                  <div class="tema-content" :style="{ background: t.content }">
                    <div class="tema-card-mock" :style="{ background: t.card }"></div>
                  </div>
                </div>
                <span class="tema-name">{{ t.nome }}</span>
                <span v-if="temaAtivo === t.id" class="tema-check">✓</span>
              </div>
            </div>
          </div>

          <div class="pref-section">
            <h3 class="pref-subtitle">Densidade do Layout</h3>
            <div class="density-options">
              <button v-for="d in densidades" :key="d.val" class="density-btn" :class="{ active: densidadeAtiva === d.val }" @click="densidadeAtiva = d.val">
                {{ d.label }}
              </button>
            </div>
          </div>

          <button class="save-btn" @click="salvarPreferencias">💾 Salvar Preferências</button>
        </template>

        <!-- Notificações -->
        <template v-if="secaoAtiva === 'notificacoes'">
          <h2 class="sec-title">🔔 Notificações</h2>

          <div class="notif-list">
            <div v-for="n in notificacoes" :key="n.id" class="notif-item">
              <div class="notif-info">
                <span class="notif-ico">{{ n.ico }}</span>
                <div>
                  <span class="notif-title">{{ n.titulo }}</span>
                  <p class="notif-desc">{{ n.desc }}</p>
                </div>
              </div>
              <label class="toggle">
                <input type="checkbox" v-model="n.ativo" />
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
              </label>
            </div>
          </div>

          <button class="save-btn" @click="salvarNotificacoes">🔔 Salvar Notificações</button>
        </template>

        <!-- Sessão -->
        <template v-if="secaoAtiva === 'sessao'">
          <h2 class="sec-title">🖥️ Sessão Atual</h2>
          <div class="sessao-info">
            <div class="sessao-row"><span>Navegador</span><strong>{{ navegador }}</strong></div>
            <div class="sessao-row"><span>Plataforma</span><strong>{{ plataforma }}</strong></div>
            <div class="sessao-row"><span>Idioma</span><strong>Português Brasileiro</strong></div>
            <div class="sessao-row"><span>Fuso Horário</span><strong>{{ fuso }}</strong></div>
          </div>
          <button class="save-btn logout-btn" @click="logout">🚪 Encerrar Sessão</button>
        </template>

        <!-- Vínculos -->
        <template v-if="secaoAtiva === 'vinculos'">
          <div class="sec-title-row">
            <h2 class="sec-title">🔗 Tipos de Vínculo</h2>
            <button class="novo-vinc-btn" @click="modalNovoVinculo = true">+ Novo Vínculo</button>
          </div>
          <p class="vinc-desc">Configure a <strong>Sigla</strong> de cada vínculo para que o motor de folha identifique o regime previdenciário correto (RPPS ou RGPS). Termos reconhecidos: <em>efetivo, estatutário, rpps, comissão, comissao, DAS</em>.</p>

          <div v-if="carregandoVinculos" class="vinc-loading">⏳ Carregando...</div>

          <div v-else class="vinc-table-wrap">
            <table class="vinc-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Descrição</th>
                  <th>Sigla</th>
                  <th>Regime Detectado</th>
                  <th>Ativo</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in vinculos" :key="v.VINCULO_ID">
                  <!-- Modo edição -->
                  <template v-if="vinculoEditando?.VINCULO_ID === v.VINCULO_ID">
                    <td class="vinc-id">{{ v.VINCULO_ID }}</td>
                    <td><input v-model="vinculoEditando.VINCULO_DESCRICAO" class="vinc-input" /></td>
                    <td><input v-model="vinculoEditando.VINCULO_SIGLA" class="vinc-input vinc-sigla" placeholder="Ex: EFETIVO" /></td>
                    <td><span class="regime-badge" :class="detectarRegime(vinculoEditando.VINCULO_SIGLA, vinculoEditando.VINCULO_DESCRICAO).cls">{{ detectarRegime(vinculoEditando.VINCULO_SIGLA, vinculoEditando.VINCULO_DESCRICAO).label }}</span></td>
                    <td><span class="ativo-dot" :class="v.VINCULO_ATIVO ? 'dot-on' : 'dot-off'"></span></td>
                    <td class="vinc-actions">
                      <button class="vinc-btn vinc-save" :disabled="salvandoVinculo" @click="salvarVinculo">{{ salvandoVinculo ? '⏳' : '💾' }}</button>
                      <button class="vinc-btn vinc-cancel" @click="cancelarEdicao">✕</button>
                    </td>
                  </template>
                  <!-- Modo visualização -->
                  <template v-else>
                    <td class="vinc-id">{{ v.VINCULO_ID }}</td>
                    <td class="vinc-nome">{{ v.VINCULO_DESCRICAO }}</td>
                    <td><code class="vinc-sigla-badge">{{ v.VINCULO_SIGLA || '—' }}</code></td>
                    <td><span class="regime-badge" :class="detectarRegime(v.VINCULO_SIGLA, v.VINCULO_DESCRICAO).cls">{{ detectarRegime(v.VINCULO_SIGLA, v.VINCULO_DESCRICAO).label }}</span></td>
                    <td>
                      <button class="toggle-mini" @click="toggleVinculo(v)" :class="v.VINCULO_ATIVO ? 'tog-on' : 'tog-off'">
                        {{ v.VINCULO_ATIVO ? 'Ativo' : 'Inativo' }}
                      </button>
                    </td>
                    <td class="vinc-actions">
                      <button class="vinc-btn vinc-edit" @click="editarVinculo(v)">✏️</button>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Ponto Eletrônico (apenas admin/gestor) -->
        <template v-if="secaoAtiva === 'ponto' && (authStore.isAdmin || authStore.isGestor)">
          <h2 class="sec-title">⏱️ Ponto Eletrônico</h2>

          <!-- Configurações Globais -->
          <div class="pref-section">
            <h3 class="pref-subtitle">⚙️ Padrões Globais</h3>
            <p style="font-size:12px;color:#94a3b8;margin-bottom:16px;">Os campos abaixo valem para TODOS os funcionários sem configuração individual.</p>

            <div class="regime-toggle">
              <button class="regime-opt" :class="{ active: regimePonto === '2_batidas' }" @click="pontoConfig.regime = '2_batidas'" :disabled="salvandoConfig">
                <span class="regime-ico">2×</span>
                <strong>2 Batidas</strong>
                <span class="regime-desc">Entrada e Saída</span>
              </button>
              <button class="regime-opt" :class="{ active: regimePonto === '4_batidas' }" @click="pontoConfig.regime = '4_batidas'" :disabled="salvandoConfig">
                <span class="regime-ico">4×</span>
                <strong>4 Batidas</strong>
                <span class="regime-desc">Entrada, Almoço, Retorno e Saída</span>
              </button>
            </div>

            <div class="ponto-fields">
              <div class="ponto-field">
                <label>🕐 Horário-limite de Entrada</label>
                <input type="time" v-model="pontoConfig.hora_entrada" class="ponto-input" />
              </div>
              <div class="ponto-field">
                <label>🏠 Horário-limite de Saída</label>
                <input type="time" v-model="pontoConfig.hora_saida" class="ponto-input" />
              </div>
              <div class="ponto-field">
                <label>🍽️ Intervalo de Almoço (min)</label>
                <input type="number" v-model.number="pontoConfig.intervalo_almoco" min="0" max="240" class="ponto-input ponto-input-sm" />
              </div>
              <div class="ponto-field">
                <label>⏳ Tolerância (minutos)</label>
                <input type="number" v-model.number="pontoConfig.tolerancia" min="0" max="120" class="ponto-input ponto-input-sm" />
              </div>
              <div class="ponto-field" style="align-self:flex-end;">
                <label style="color:#10b981;font-weight:700;">✅ Jornada líquida</label>
                <span class="ponto-jornada-preview">{{ jornadaLiquidaLabel }}</span>
              </div>
            </div>

            <button class="save-btn" style="margin-top:12px;" :disabled="salvandoConfig" @click="salvarPontoConfig">
              <div v-if="salvandoConfig" class="btn-spinner"></div>
              <template v-else>💾 Salvar Padrões</template>
            </button>
            <p v-if="configMsg" :class="configMsgOk ? 'regime-ok' : 'regime-err'" style="margin-top:8px;">{{ configMsg }}</p>
          </div>

          <!-- Configurações por Funcionário -->
          <div class="pref-section">
            <h3 class="pref-subtitle">👤 Configuração por Funcionário</h3>
            <p style="font-size:12px;color:#94a3b8;margin-bottom:12px;">Deixe um campo em branco para herdar o padrão global.</p>

            <!-- Busca de funcionário -->
            <div class="func-search-wrap">
              <span class="func-search-ico">🔍</span>
              <input
                v-model="buscaFunc"
                type="text"
                class="func-search-input"
                placeholder="Buscar funcionário pelo nome..."
                @input="funcEditando = null"
              />
              <span v-if="buscaFunc" class="func-search-clear" @click="buscaFunc = ''; funcEditando = null">✕</span>
            </div>

            <div v-if="carregandoFuncs" class="vinc-loading">⏳ Carregando...</div>

            <!-- Nenhum termo digitado -->
            <p v-else-if="!buscaFunc" style="font-size:13px;color:#94a3b8;margin-top:10px;">
              Digite o nome do funcionário para buscar.
            </p>

            <!-- Nenhum resultado -->
            <p v-else-if="funcsFiltradas.length === 0" style="font-size:13px;color:#f59e0b;margin-top:10px;">
              ⚠️ Nenhum funcionário encontrado para "{{ buscaFunc }}".
            </p>

            <!-- Resultados -->
            <div v-else class="vinc-table-wrap" style="margin-top:10px;">
              <table class="vinc-table ponto-func-table">
                <thead>
                  <tr>
                    <th>Funcionário</th>
                    <th>Regime</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Tolerância / Almoço</th>
                    <th v-if="authStore.isAdmin">Jornada Esp.</th>
                    <th>#</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="f in funcsFiltradas" :key="f.FUNCIONARIO_ID">
                    <template v-if="funcEditando?.FUNCIONARIO_ID === f.FUNCIONARIO_ID">
                      <td class="vinc-nome">{{ f.PESSOA_NOME }}</td>
                      <td>
                        <select v-model="funcEditando.REGIME" class="ponto-input ponto-select">
                          <option value="">Padrão ({{ pontoConfig.regime === '2_batidas' ? '2×' : '4×' }})</option>
                          <option value="2_batidas">2 Batidas</option>
                          <option value="4_batidas">4 Batidas</option>
                        </select>
                      </td>
                      <td><input type="time" v-model="funcEditando.HORA_ENTRADA" class="ponto-input" /></td>
                      <td><input type="time" v-model="funcEditando.HORA_SAIDA" class="ponto-input" /></td>
                      <td>
                        <div style="display:flex;gap:4px;flex-direction:column">
                          <input type="number" v-model.number="funcEditando.TOLERANCIA" min="0" max="120" placeholder="Tol." class="ponto-input ponto-input-sm" />
                          <input type="number" v-model.number="funcEditando.INTERVALO_ALMOCO" min="0" max="240" placeholder="Almoço" class="ponto-input ponto-input-sm" />
                        </div>
                      </td>
                      <td v-if="authStore.isAdmin">
                        <div style="display:flex;gap:4px;flex-direction:column">
                          <input type="number" v-model.number="funcEditando.JORNADA_FINANCEIRA_HORAS" step="0.1" placeholder="Horas" class="ponto-input ponto-input-sm" />
                          <textarea v-if="funcEditando.JORNADA_FINANCEIRA_HORAS" v-model="funcEditando.JORNADA_FINANCEIRA_OBS" class="ponto-input" placeholder="Justificativa..." rows="2"></textarea>
                        </div>
                      </td>
                      <td class="vinc-actions">
                        <button class="vinc-btn vinc-save" :disabled="salvandoFunc" @click="salvarFuncPonto">{{ salvandoFunc ? '⏳' : '💾' }}</button>
                        <button class="vinc-btn vinc-cancel" @click="funcEditando = null">✕</button>
                      </td>
                    </template>
                    <template v-else>
                      <td class="vinc-nome">{{ f.PESSOA_NOME }}</td>
                      <td>
                        <span class="regime-badge" :class="f.REGIME ? 'regime-custom' : 'regime-padrao'">
                          {{ f.REGIME === '2_batidas' ? '2 Batidas' : f.REGIME === '4_batidas' ? '4 Batidas' : '🔄 Padrão' }}
                        </span>
                      </td>
                      <td>{{ f.HORA_ENTRADA || '—' }}</td>
                      <td>{{ f.HORA_SAIDA || '—' }}</td>
                      <td>
                        {{ f.TOLERANCIA != null ? f.TOLERANCIA + 'm' : 'Pad.' }} / 
                        {{ f.INTERVALO_ALMOCO != null ? f.INTERVALO_ALMOCO + 'm' : 'Pad.' }}
                      </td>
                      <td v-if="authStore.isAdmin">
                        <span v-if="f.JORNADA_FINANCEIRA_HORAS" class="regime-badge regime-custom" :title="f.JORNADA_FINANCEIRA_OBS">
                          {{ f.JORNADA_FINANCEIRA_HORAS }}h/dia
                        </span>
                        <span v-else class="regime-badge regime-padrao">Trâmite Normal</span>
                      </td>
                      <td class="vinc-actions">
                        <button class="vinc-btn vinc-edit" @click="editarFuncPonto(f)">✏️</button>
                      </td>
                    </template>
                  </tr>
                </tbody>
              </table>
              <p v-if="funcsPonto.length > 8 && buscaFunc.length < 2" style="font-size:11px;color:#94a3b8;margin-top:6px;">
                Continue digitando para refinar a busca…
              </p>
            </div>
            <p v-if="funcMsg" :class="funcMsgOk ? 'regime-ok' : 'regime-err'" style="margin-top:8px;">{{ funcMsg }}</p>
          </div>
        </template>

      </div>
    </div>

    <!-- Toast -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.type">
        <span>{{ toast.ico }}</span> {{ toast.msg }}
      </div>
    </transition>

    <!-- Modal Novo Vínculo -->
    <transition name="modal">
      <div v-if="modalNovoVinculo" class="modal-overlay" @click.self="modalNovoVinculo = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>+ Novo Vínculo</h3>
            <button class="modal-close" @click="modalNovoVinculo = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-2"><label>Nome do Vínculo <span class="req">*</span></label><input v-model="novoVinculo.VINCULO_DESCRICAO" class="cfg-input" placeholder="Ex: Estatutário Efetivo" /></div>
              <div class="form-group"><label>Sigla</label><input v-model="novoVinculo.VINCULO_SIGLA" class="cfg-input" maxlength="20" placeholder="EFETIVO" style="text-transform:uppercase;font-family:monospace" /></div>
              <div class="form-group"><label>Tipo eSocial</label>
                <select v-model="novoVinculo.VINCULO_TIPO_ESOCIAL" class="cfg-input">
                  <option value="">Selecione</option>
                  <option value="01">01 – Empregado CLT</option>
                  <option value="02">02 – Trabalhador avulso</option>
                  <option value="03">03 – Empregado doméstico</option>
                  <option value="04">04 – Servidor público</option>
                  <option value="05">05 – Militar</option>
                  <option value="06">06 – Estagiário</option>
                  <option value="07">07 – Temporário</option>
                  <option value="99">99 – Outros</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group"><label><input type="checkbox" v-model="novoVinculo.VINCULO_FGTS" style="margin-right:6px" /> Sujeito ao FGTS</label></div>
              <div class="form-group"><label><input type="checkbox" v-model="novoVinculo.VINCULO_INSS" style="margin-right:6px" /> Contribui INSS</label></div>
              <div class="form-group"><label><input type="checkbox" v-model="novoVinculo.VINCULO_IRRF" style="margin-right:6px" /> Sujeito ao IRRF</label></div>
            </div>
            <div v-if="erroNovoVinculo" class="erro-msg">⚠️ {{ erroNovoVinculo }}</div>
            <div v-if="okNovoVinculo" class="ok-msg">✅ {{ okNovoVinculo }}</div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalNovoVinculo = false" :disabled="criandoVinculo">Cancelar</button>
              <button class="modal-submit" @click="criarVinculo" :disabled="criandoVinculo">
                <span v-if="criandoVinculo" class="btn-spinner"></span>
                <template v-else>Criar Vínculo</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const authStore = useAuthStore()
const router = useRouter()
const user = computed(() => authStore.user)
const loaded = ref(false)
const salvandoSenha = ref(false)

// ── Vínculos ──────────────────────────────────────────────
const vinculos = ref([])
const vinculoEditando = ref(null)   // objeto sendo editado inline
const salvandoVinculo = ref(false)
const carregandoVinculos = ref(false)
const modalNovoVinculo = ref(false)
const criandoVinculo = ref(false)
const erroNovoVinculo = ref('')
const okNovoVinculo = ref('')
const novoVinculoVazio = () => ({ VINCULO_DESCRICAO: '', VINCULO_SIGLA: '', VINCULO_TIPO_ESOCIAL: '', VINCULO_FGTS: true, VINCULO_INSS: true, VINCULO_IRRF: true })
const novoVinculo = ref(novoVinculoVazio())

const carregarVinculos = async () => {
  carregandoVinculos.value = true
  try {
    const { data } = await api.get('/api/v3/admin/vinculos')
    vinculos.value = data
  } catch { mostrarToast('error', '❌', 'Não foi possível carregar os vínculos.') }
  finally { carregandoVinculos.value = false }
}

const criarVinculo = async () => {
  erroNovoVinculo.value = ''; okNovoVinculo.value = ''
  if (!novoVinculo.value.VINCULO_DESCRICAO.trim()) { erroNovoVinculo.value = 'Nome é obrigatório.'; return }
  criandoVinculo.value = true
  try {
    await api.post('/api/v3/admin/vinculos', novoVinculo.value)
    okNovoVinculo.value = 'Vínculo criado com sucesso!'
    await carregarVinculos()
    setTimeout(() => { modalNovoVinculo.value = false; novoVinculo.value = novoVinculoVazio() }, 1200)
    mostrarToast('success', '✅', 'Vínculo criado!')
  } catch (e) { erroNovoVinculo.value = e.response?.data?.erro || 'Erro ao criar vínculo.' }
  finally { criandoVinculo.value = false }
}

const editarVinculo = (v) => {
  vinculoEditando.value = { ...v }
}

const cancelarEdicao = () => {
  vinculoEditando.value = null
}

const salvarVinculo = async () => {
  if (!vinculoEditando.value) return
  salvandoVinculo.value = true
  try {
    await api.put(`/api/v3/admin/vinculos/${vinculoEditando.value.VINCULO_ID}`, vinculoEditando.value)
    await carregarVinculos()
    vinculoEditando.value = null
    mostrarToast('success', '✅', 'Vínculo atualizado com sucesso!')
  } catch { mostrarToast('error', '❌', 'Falha ao salvar o vínculo.') }
  finally { salvandoVinculo.value = false }
}

const toggleVinculo = async (v) => {
  try {
    await api.put(`/api/v3/admin/vinculos/${v.VINCULO_ID}`, { ...v, VINCULO_ATIVO: v.VINCULO_ATIVO ? 0 : 1 })
    await carregarVinculos()
  } catch { mostrarToast('error', '❌', 'Falha ao alterar status.') }
}
const mostraSenha = ref(false)
const secaoAtiva = ref('senha')
const temaAtivo = ref('light')
const densidadeAtiva = ref('normal')
const funcionario = ref(null)
const toast = ref({ visible: false, msg: '', type: '', ico: '' })

const senhaSub = reactive({ atual: '', nova: '', confirmar: '' })

const secoes = computed(() => [
  { id: 'senha', ico: '🔐', nome: 'Segurança' },
  { id: 'aparencia', ico: '🎨', nome: 'Aparência' },
  { id: 'notificacoes', ico: '🔔', nome: 'Notificações' },
  { id: 'sessao', ico: '🖥️', nome: 'Sessão' },
  { id: 'vinculos', ico: '🔗', nome: 'Vínculos' },
  ...(authStore.isAdmin || authStore.isGestor ? [{ id: 'ponto', ico: '⏱️', nome: 'Ponto' }] : []),
])

// ── Configuração de Ponto ──────────────────────────────────────
const pontoConfig = reactive({ regime: '4_batidas', hora_entrada: '08:00', hora_saida: '18:00', intervalo_almoco: 120, tolerancia: 15 })
const regimePonto = computed(() => pontoConfig.regime)
const salvandoConfig = ref(false)
const configMsg = ref('')
const configMsgOk = ref(true)

// Preview ao vivo: jornada líquida = (saida - entrada) - intervalo
const jornadaLiquidaLabel = computed(() => {
  const [he, me] = (pontoConfig.hora_entrada || '08:00').split(':').map(Number)
  const [hs, ms] = (pontoConfig.hora_saida   || '18:00').split(':').map(Number)
  const bruto   = (hs * 60 + ms) - (he * 60 + me)
  const liquido = bruto - (pontoConfig.intervalo_almoco ?? 120)
  if (liquido <= 0) return '⚠️ Inválido'
  const h = Math.floor(liquido / 60)
  const m = liquido % 60
  return m ? `${h}h ${m}min` : `${h}h`
})

const carregarRegime = async () => {
  try {
    const { data } = await api.get('/api/v3/ponto/config')
    if (data) {
      pontoConfig.regime           = data.regime            ?? '4_batidas'
      pontoConfig.hora_entrada      = data.hora_entrada      ?? '08:00'
      pontoConfig.hora_saida        = data.hora_saida        ?? '18:00'
      pontoConfig.intervalo_almoco  = data.intervalo_almoco  ?? 120
      pontoConfig.tolerancia        = data.tolerancia        ?? 15
    }
  } catch {}
}

const salvarPontoConfig = async () => {
  salvandoConfig.value = true; configMsg.value = ''
  try {
    await api.put('/api/v3/ponto/config', { ...pontoConfig })
    configMsg.value = '✅ Padrões salvos com sucesso!'; configMsgOk.value = true
  } catch {
    configMsg.value = '❌ Falha ao salvar. Verifique suas permissões.'; configMsgOk.value = false
  } finally {
    salvandoConfig.value = false
    setTimeout(() => { configMsg.value = '' }, 4000)
  }
}

// ── Config por Funcionário ─────────────────────────────────────
const funcsPonto = ref([])
const funcEditando = ref(null)
const carregandoFuncs = ref(false)
const salvandoFunc = ref(false)
const funcMsg = ref('')
const funcMsgOk = ref(true)

const carregarFuncsPonto = async () => {
  carregandoFuncs.value = true
  try {
    const { data } = await api.get('/api/v3/ponto/config/funcionarios')
    funcsPonto.value = data ?? []
  } catch {} finally { carregandoFuncs.value = false }
}

const buscaFunc = ref('')
const funcsFiltradas = computed(() => {
  const t = buscaFunc.value.trim().toLowerCase()
  if (!t) return []
  return funcsPonto.value
    .filter(f => (f.PESSOA_NOME ?? '').toLowerCase().includes(t))
    .slice(0, 8)
})

const editarFuncPonto = (f) => {
  funcEditando.value = {
    ...f, 
    REGIME: f.REGIME ?? '', 
    HORA_ENTRADA: f.HORA_ENTRADA ?? '', 
    HORA_SAIDA: f.HORA_SAIDA ?? '', 
    TOLERANCIA: f.TOLERANCIA ?? '',
    INTERVALO_ALMOCO: f.INTERVALO_ALMOCO ?? '',
    JORNADA_FINANCEIRA_HORAS: f.JORNADA_FINANCEIRA_HORAS ?? '',
    JORNADA_FINANCEIRA_OBS: f.JORNADA_FINANCEIRA_OBS ?? ''
  }
}

const salvarFuncPonto = async () => {
  if (!funcEditando.value) return
  salvandoFunc.value = true; funcMsg.value = ''
  try {
    await api.put(`/api/v3/ponto/config/funcionarios/${funcEditando.value.FUNCIONARIO_ID}`, {
      REGIME:                   funcEditando.value.REGIME || null,
      HORA_ENTRADA:             funcEditando.value.HORA_ENTRADA || null,
      HORA_SAIDA:               funcEditando.value.HORA_SAIDA || null,
      TOLERANCIA:               funcEditando.value.TOLERANCIA !== '' ? funcEditando.value.TOLERANCIA : null,
      INTERVALO_ALMOCO:         funcEditando.value.INTERVALO_ALMOCO !== '' ? funcEditando.value.INTERVALO_ALMOCO : null,
      JORNADA_FINANCEIRA_HORAS: funcEditando.value.JORNADA_FINANCEIRA_HORAS !== '' ? funcEditando.value.JORNADA_FINANCEIRA_HORAS : null,
      JORNADA_FINANCEIRA_OBS:   funcEditando.value.JORNADA_FINANCEIRA_OBS || null,
    })
    await carregarFuncsPonto()
    funcEditando.value = null
    funcMsg.value = '✅ Configuração salva!'; funcMsgOk.value = true
  } catch {
    funcMsg.value = '❌ Erro ao salvar.'; funcMsgOk.value = false
  } finally {
    salvandoFunc.value = false; setTimeout(() => { funcMsg.value = '' }, 4000)
  }
}

const temas = [
  { id: 'light', nome: 'Claro', bg: '#f8fafc', sidebar: '#0f172a', content: '#f1f5f9', card: '#fff' },
  { id: 'dark', nome: 'Escuro', bg: '#0f172a', sidebar: '#020617', content: '#1e293b', card: '#1e293b' },
  { id: 'ocean', nome: 'Oceano', bg: '#f0f9ff', sidebar: '#0369a1', content: '#e0f2fe', card: '#fff' },
]

const densidades = [
  { val: 'compact', label: '🗜️ Compacto' },
  { val: 'normal', label: '📐 Normal' },
  { val: 'spacious', label: '📏 Espaçoso' },
]

const notificacoes = reactive([
  { id: 1, ico: '🏥', titulo: 'Abono de Faltas', desc: 'Notificação quando um abono for deferido ou indeferido', ativo: true },
  { id: 2, ico: '📅', titulo: 'Escalas Hospitalares', desc: 'Lembrete 2 dias antes de um plantão', ativo: true },
  { id: 3, ico: '💰', titulo: 'Holerite disponível', desc: 'Aviso quando um novo holerite for processado', ativo: true },
  { id: 4, ico: '⏱️', titulo: 'Inconsistências de Ponto', desc: 'Alerta de batidas inconsistentes no mês', ativo: false },
])

onMounted(async () => {
  try {
    const u = authStore.user
    if (u?.FUNCIONARIO_ID) {
      const { data } = await api.get(`/api/v3/funcionarios/${u.FUNCIONARIO_ID}`)
      funcionario.value = data.funcionario
    }
  } catch {}
  const tarefas = [carregarVinculos(), carregarRegime()]
  if (authStore.isAdmin || authStore.isGestor) tarefas.push(carregarFuncsPonto())
  await Promise.all(tarefas)
  setTimeout(() => { loaded.value = true }, 80)
})

const avatarHue = computed(() => {
  const id = user.value?.USUARIO_ID ?? user.value?.id ?? 1
  return (id * 137) % 360
})
const iniciais = (nome) => {
  if (!nome) return 'U'
  const w = nome.trim().split(' ').filter(Boolean)
  return w.length >= 2 ? (w[0][0] + w[w.length - 1][0]).toUpperCase() : nome.substring(0, 2).toUpperCase()
}

const senhaForca = computed(() => {
  const s = senhaSub.nova
  if (!s) return { pct: 0, cor: '#e2e8f0', label: '' }
  let pts = 0
  if (s.length >= 8) pts++
  if (/[A-Z]/.test(s)) pts++
  if (/[0-9]/.test(s)) pts++
  if (/[^A-Za-z0-9]/.test(s)) pts++
  const map = [
    { pct: 25, cor: '#ef4444', label: 'Fraca' },
    { pct: 50, cor: '#f59e0b', label: 'Razoável' },
    { pct: 75, cor: '#3b82f6', label: 'Boa' },
    { pct: 100, cor: '#10b981', label: 'Forte 💪' },
  ]
  return map[pts - 1] || map[0]
})
const senhasIguais = computed(() => senhaSub.nova && senhaSub.nova === senhaSub.confirmar)
const senhaValida = computed(() => senhaSub.atual && senhaSub.nova.length >= 8 && senhasIguais.value)

const alterarSenha = async () => {
  salvandoSenha.value = true
  try {
    const { data } = await api.post('/api/v3/perfil/alterar-senha', {
      senha_atual:  senhaSub.atual,
      nova_senha:   senhaSub.nova,
      confirmacao:  senhaSub.confirmar,
    })
    if (data.ok) {
      mostrarToast('success', '✅', 'Senha alterada com sucesso!')
      Object.assign(senhaSub, { atual: '', nova: '', confirmar: '' })
    } else {
      mostrarToast('error', '❌', data.erro || 'Falha ao alterar a senha.')
    }
  } catch (e) {
    mostrarToast('error', '❌', e.response?.data?.erro || e.response?.data?.message || 'Falha ao alterar a senha.')
  } finally {
    salvandoSenha.value = false
  }
}

const salvarPreferencias = () => mostrarToast('success', '🎨', 'Preferências salvas!')
const salvarNotificacoes = () => mostrarToast('success', '🔔', 'Configurações de notificação salvas!')

const logout = async () => {
  await authStore.logout()
  router.push('/login')
}

const navegador = navigator.userAgent.includes('Chrome') ? 'Google Chrome' : navigator.userAgent.includes('Firefox') ? 'Firefox' : 'Navegador desconhecido'
const plataforma = navigator.platform || 'N/D'
const fuso = Intl.DateTimeFormat().resolvedOptions().timeZone

const mostrarToast = (type, ico, msg) => {
  toast.value = { visible: true, type, ico, msg }
  setTimeout(() => { toast.value.visible = false }, 3500)
}

// Espelha o VinculoEnum do backend para feedback visual em tempo real
const detectarRegime = (sigla, desc) => {
  const t = ((sigla ?? '') + ' ' + (desc ?? '')).toLowerCase()
  if (['efetivo','estatut','rpps','concursado','regime pr'].some(k => t.includes(k)))
    return { label: 'RPPS — Servidor Efetivo', cls: 'regime-rpps' }
  if (['comiss','das','cds','nomeado','livre'].some(k => t.includes(k)))
    return { label: 'RGPS — Cargo em Comissão', cls: 'regime-rgps' }
  if (['est\xE1gio','estagio','estagi'].some(k => t.includes(k)))
    return { label: 'Estágio', cls: 'regime-estagio' }
  return { label: 'Não identificado', cls: 'regime-outro' }
}
</script>

<style scoped>
.config-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero {
  position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #1e2a1e 50%, #1a2044 100%);
  opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 240px; height: 240px; background: #4f46e5; right: -40px; top: -60px; }
.hs2 { width: 200px; height: 200px; background: #10b981; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.hero-avatar-wrap { position: relative; }
.hero-avatar { width: 72px; height: 72px; border-radius: 20px; background: hsl(var(--h) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 900; color: #fff; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.hero-avatar-edit { position: absolute; bottom: -4px; right: -4px; width: 22px; height: 22px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: -0.01em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-meta { margin-left: auto; display: flex; gap: 20px; flex-wrap: wrap; }
.hm-item { text-align: right; }
.hm-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #4f46e5; margin-bottom: 2px; }
.hm-val { display: block; font-size: 14px; font-weight: 800; color: #fff; }

/* GRID */
.config-grid {
  display: grid; grid-template-columns: 200px 1fr; gap: 20px; align-items: start;
  opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.1s;
}
.config-grid.loaded { opacity: 1; transform: none; }

/* CONFIG NAV */
.config-nav { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 10px; display: flex; flex-direction: column; gap: 4px; }
.cnav-btn { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; border: none; background: none; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s; text-align: left; }
.cnav-btn:hover { background: #f8fafc; }
.cnav-btn.active { background: #f0f9ff; color: #1d4ed8; }
.cnav-ico { font-size: 17px; }

/* CONTENT */
.config-content { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; min-height: 400px; }
.sec-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0 0 22px; }

/* FIELDS */
.fields-col { display: flex; flex-direction: column; gap: 16px; max-width: 480px; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #4f46e5; background: #fff; }
.input-wrap { position: relative; }
.input-wrap .cfg-input { padding-right: 44px; }
.eye-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; font-size: 16px; }
.strength-bar { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; margin-top: 4px; }
.strength-fill { height: 100%; border-radius: 99px; transition: all 0.4s; }
.strength-label { font-size: 11px; font-weight: 700; }
.match-msg { font-size: 12px; font-weight: 600; }
.match-ok { color: #16a34a; }
.match-err { color: #dc2626; }
.save-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px 24px; border-radius: 13px; border: none;
  background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff;
  font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.18s; margin-top: 8px;
}
.save-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.35); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.logout-btn { background: linear-gradient(135deg, #dc2626, #ef4444); }
.logout-btn:hover { box-shadow: 0 8px 24px rgba(220,38,38,0.35); }
.btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* TEMAS */
.pref-section { margin-bottom: 22px; }
.pref-subtitle { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 12px; }
.tema-options { display: flex; gap: 12px; flex-wrap: wrap; }
.tema-card { cursor: pointer; border-radius: 14px; border: 2px solid #e2e8f0; overflow: hidden; width: 110px; transition: all 0.18s; position: relative; }
.tema-card:hover { border-color: #6366f1; }
.tema-card.tema-active { border-color: #4f46e5; }
.tema-preview { height: 70px; display: flex; padding: 8px; gap: 6px; }
.tema-sidebar { width: 22px; border-radius: 6px; }
.tema-content { flex: 1; border-radius: 6px; padding: 6px; }
.tema-card-mock { height: 14px; border-radius: 4px; }
.tema-name { display: block; font-size: 11px; font-weight: 700; color: #475569; text-align: center; padding: 6px; }
.tema-check { position: absolute; top: 6px; right: 6px; width: 18px; height: 18px; background: #4f46e5; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; }
.density-options { display: flex; gap: 8px; }
.density-btn { padding: 8px 16px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.15s; }
.density-btn.active { border-color: #6366f1; background: #eff6ff; color: #4f46e5; }

/* NOTIFICAÇÕES */
.notif-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
.notif-item { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; background: #f8fafc; border-radius: 14px; border: 1px solid #f1f5f9; }
.notif-info { display: flex; align-items: center; gap: 12px; }
.notif-ico { font-size: 22px; }
.notif-title { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.notif-desc { font-size: 11px; color: #94a3b8; margin: 2px 0 0; }
.toggle { position: relative; display: inline-block; width: 42px; height: 24px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-track { position: absolute; inset: 0; background: #e2e8f0; border-radius: 99px; cursor: pointer; transition: background 0.2s; }
.toggle input:checked + .toggle-track { background: #4f46e5; }
.toggle-thumb { position: absolute; width: 18px; height: 18px; background: #fff; border-radius: 50%; top: 3px; left: 3px; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.toggle input:checked + .toggle-track .toggle-thumb { transform: translateX(18px); }

/* SESSÃO */
.sessao-info { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
.sessao-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 14px; color: #64748b; }
.sessao-row strong { color: #1e293b; font-weight: 700; }

/* TOAST */
.toast { position: fixed; bottom: 28px; right: 28px; z-index: 200; display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 14px; font-size: 14px; font-weight: 600; color: #fff; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast.success { background: #059669; }
.toast.error { background: #dc2626; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px) scale(0.95); }

@media (max-width: 768px) { .config-grid { grid-template-columns: 1fr; } }

/* VÍNCULOS */
.vinc-desc { font-size: 13px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; margin: 0 0 18px; line-height: 1.6; }
.vinc-loading { font-size: 14px; color: #94a3b8; padding: 20px 0; text-align: center; }
.vinc-table-wrap { overflow-x: auto; }
.vinc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.vinc-table th { text-align: left; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; padding: 0 10px 10px; border-bottom: 2px solid #f1f5f9; }
.vinc-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.vinc-table tr:hover td { background: #f8fafc; }
.vinc-id { font-size: 11px; color: #cbd5e1; font-weight: 700; width: 36px; }
.vinc-nome { font-weight: 700; color: #1e293b; }
.vinc-sigla-badge { background: #f1f5f9; color: #475569; font-size: 11px; padding: 3px 8px; border-radius: 6px; font-family: monospace; }
.regime-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; white-space: nowrap; }
.regime-rpps { background: #eff6ff; color: #1d4ed8; }
.regime-rgps { background: #f0fdf4; color: #166534; }
.regime-estagio { background: #fefce8; color: #92400e; }
.regime-outro { background: #f8fafc; color: #94a3b8; }
.toggle-mini { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; border: 1.5px solid; cursor: pointer; transition: all 0.15s; }
.tog-on { background: #f0fdf4; color: #166534; border-color: #86efac; }
.tog-off { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
.vinc-actions { display: flex; gap: 6px; align-items: center; }
.vinc-btn { width: 30px; height: 30px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.15s; }
.vinc-edit:hover { background: #eff6ff; border-color: #93c5fd; }
.vinc-save { background: #f0fdf4; border-color: #86efac; }
.vinc-cancel { background: #fef2f2; border-color: #fca5a5; }
.vinc-input { width: 100%; padding: 6px 10px; border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13px; font-family: inherit; color: #1e293b; background: #fff; outline: none; box-sizing: border-box; }
.vinc-input:focus { border-color: #6366f1; }
.vinc-input.vinc-sigla { max-width: 120px; font-family: monospace; text-transform: uppercase; }
.ativo-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.dot-on { background: #22c55e; }
.dot-off { background: #e2e8f0; }

/* ── Toggle Regime de Ponto ────────────────────────────── */
.regime-toggle { display: flex; gap: 14px; margin-bottom: 16px; }
.regime-opt {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 20px 16px; border: 2px solid #e2e8f0; border-radius: 16px;
  background: #f8fafc; cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.regime-opt:hover:not(:disabled) { border-color: #a5b4fc; background: #eef2ff; }
.regime-opt.active { border-color: #6366f1; background: #eef2ff; }
.regime-opt.active .regime-ico { color: #6366f1; }
.regime-opt:disabled { opacity: 0.5; cursor: not-allowed; }
.regime-ico { font-size: 24px; font-weight: 900; color: #94a3b8; }
.regime-opt strong { font-size: 14px; color: #1e293b; }
.regime-desc { font-size: 11px; color: #64748b; font-weight: 500; }
.regime-ok { color: #16a34a; font-size: 13px; font-weight: 600; }
.regime-err { color: #dc2626; font-size: 13px; font-weight: 600; }
.regime-custom { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.regime-padrao { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

/* ── Campos de Ponto (horário / tolerância) ───────────── */
.ponto-fields { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 12px; }
.ponto-field { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
.ponto-field label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.ponto-input {
  padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-family: inherit; color: #1e293b; background: #fff;
  outline: none; box-sizing: border-box; transition: border-color 0.15s;
}
.ponto-input:focus { border-color: #6366f1; }
.ponto-input-sm { max-width: 90px; }
.ponto-select { cursor: pointer; appearance: auto; }
.ponto-func-table th { white-space: nowrap; }

/* ── Caixa de Busca de Funcionário ───────────────── */
.func-search-wrap {
  position: relative; display: flex; align-items: center;
  border: 1.5px solid #e2e8f0; border-radius: 12px;
  background: #f8fafc; overflow: hidden;
  transition: border-color 0.15s;
}
.func-search-wrap:focus-within { border-color: #6366f1; background: #fff; }
.func-search-ico { padding: 0 10px 0 14px; font-size: 15px; color: #94a3b8; pointer-events: none; }
.func-search-input {
  flex: 1; border: none; background: transparent; outline: none;
  font-size: 13px; color: #1e293b; padding: 10px 0; font-family: inherit;
}
.func-search-clear {
  padding: 0 14px; font-size: 14px; color: #94a3b8; cursor: pointer;
  transition: color 0.15s;
}
.func-search-clear:hover { color: #ef4444; }
.ponto-jornada-preview {
  display: inline-block; padding: 7px 12px;
  background: #f0fdf4; border: 1.5px solid #86efac;
  border-radius: 10px; font-size: 14px; font-weight: 800;
  color: #16a34a; letter-spacing: -0.01em;
}

/* ── Novo Vínculo: header com botão ─────────────────────── */
.sec-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0; }
.sec-title-row .sec-title { margin-bottom: 0; }
.novo-vinc-btn { padding: 9px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.novo-vinc-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.35); }

/* ── Modal ──────────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 300; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 580px; overflow: hidden; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 13px; color: #64748b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.col-2 { grid-column: span 2; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #dc2626; }
.erro-msg { font-size: 13px; font-weight: 600; color: #dc2626; }
.ok-msg { font-size: 13px; font-weight: 600; color: #15803d; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
