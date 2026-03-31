<template>
  <div class="ac-gest-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">👤 Recursos Humanos</span>
          <h1 class="hero-title">Autocadastro</h1>
          <p class="hero-sub">Gere links de cadastro e aprove novos funcionários</p>
        </div>
        <div class="hero-stats">
          <div class="hs-card">
            <span class="hs-val">{{ countByStatus('pendente') }}</span>
            <span class="hs-label">Aguardando preenchimento</span>
          </div>
          <div class="hs-card hs-warn">
            <span class="hs-val">{{ countByStatus('preenchido') }}</span>
            <span class="hs-label">Aguardando aprovação</span>
          </div>
          <div class="hs-card hs-ok">
            <span class="hs-val">{{ countByStatus('aprovado') }}</span>
            <span class="hs-label">Aprovados</span>
          </div>
        </div>
      </div>
    </div>

    <!-- GERADOR DE LINK -->
    <div class="panel" :class="{ loaded }">
      <div class="panel-hdr">
        <span class="panel-ico">🔗</span>
        <h2>Gerar Novo Link</h2>
      </div>
      <div class="gerador-form">
        <div class="gf-field">
          <label>Nome do funcionário <span class="opt">(opcional)</span></label>
          <input v-model="novoNome" type="text" class="gf-input" placeholder="Ex: João Silva" />
        </div>
        <div class="gf-field">
          <label>E-mail <span class="opt">(opcional)</span></label>
          <input v-model="novoEmail" type="email" class="gf-input" placeholder="joao@hospital.com.br" />
        </div>
        <div class="gf-field gf-field-sm">
          <label>Validade</label>
          <select v-model="validadeDias" class="gf-input">
            <option :value="3">3 dias</option>
            <option :value="7">7 dias</option>
            <option :value="15">15 dias</option>
            <option :value="30">30 dias</option>
          </select>
        </div>
        <button class="btn-gerar" :disabled="gerando" @click="gerarLink">
          <span v-if="gerando" class="btn-spin"></span>
          <template v-else>✨ Gerar Link</template>
        </button>
      </div>

      <!-- Link gerado -->
      <transition name="fade">
        <div v-if="linkGerado" class="link-result">
          <div class="link-box">
            <span class="link-url">{{ linkGerado.url }}</span>
            <button class="btn-copy" @click="copiar(linkGerado.url)">
              {{ copiado ? '✅ Copiado!' : '📋 Copiar' }}
            </button>
          </div>
          <p class="link-info">
            Expira em <strong>{{ linkGerado.expira_em }}</strong> ·
            Envie este link para o funcionário preencher os dados.
          </p>
        </div>
      </transition>
    </div>

    <!-- TABELA DE TOKENS -->
    <div class="panel" :class="{ loaded }">
      <div class="panel-hdr">
        <span class="panel-ico">📋</span>
        <h2>Cadastros</h2>
        <div class="filter-tabs">
          <button v-for="f in filtros" :key="f.id" class="ftab" :class="{ active: filtroAtivo === f.id }" @click="filtroAtivo = f.id">
            {{ f.ico }} {{ f.nome }}
            <span v-if="f.count" class="ftab-count">{{ f.count }}</span>
          </button>
        </div>
        <button class="btn-refresh" @click="carregar" title="Atualizar">🔄</button>
      </div>

      <div v-if="carregando" class="state-box"><div class="spinner"></div><p>Carregando...</p></div>
      <div v-else-if="listaFiltrada.length === 0" class="state-box">
        <p>Nenhum cadastro {{ filtroAtivo !== 'todos' ? 'com este status' : '' }}</p>
      </div>
      <div v-else class="tokens-table">
        <div class="tt-hdr">
          <span>Nome / E-mail</span>
          <span>Status</span>
          <span>Criado em</span>
          <span>Expira em</span>
          <span>Ações</span>
        </div>
        <div v-for="t in listaFiltrada" :key="t.TOKEN" class="tt-row" :class="'row-' + t.TOKEN_STATUS">
          <div class="tt-pessoa">
            <span class="tt-nome">{{ t.TOKEN_NOME || t.TOKEN_DADOS?.nome || '—' }}</span>
            <span class="tt-email">{{ t.TOKEN_EMAIL || t.TOKEN_DADOS?.email || '—' }}</span>
          </div>
          <div>
            <span class="status-badge" :class="'sb-' + t.TOKEN_STATUS">
              {{ statusLabel(t.TOKEN_STATUS) }}
            </span>
          </div>
          <div class="tt-date">{{ formatDate(t.created_at) }}</div>
          <div class="tt-date" :class="{ 'exp-warn': isExpirando(t) }">
            {{ t.expira_em ? formatDate(t.expira_em) : '—' }}
          </div>
          <div class="tt-acoes">
            <button v-if="t.TOKEN_STATUS === 'preenchido'" class="btn-act btn-aprovar" @click="abrir(t)">
              ✅ Ver e Aprovar
            </button>
            <button v-else-if="t.TOKEN_STATUS === 'pendente'" class="btn-act btn-copiar" @click="copiarToken(t.TOKEN)">
              📋 Copiar link
            </button>
            <!-- BUG-EST-09: Ver Perfil para aprovados com FUNCIONARIO_ID -->
            <button v-if="t.TOKEN_STATUS === 'aprovado' && t.FUNCIONARIO_ID" class="btn-act btn-perfil" @click="verPerfil(t.FUNCIONARIO_ID)">
              👤 Ver Perfil
            </button>
            <button v-if="!['aprovado', 'revogado'].includes(t.TOKEN_STATUS)" class="btn-act btn-revogar" @click="confirmarRevogar(t)">
              ✕ Revogar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DRAWER DE APROVAÇÃO -->
    <transition name="drawer">
      <div v-if="tokenAberto" class="drawer-overlay" @click.self="tokenAberto = null">
        <div class="drawer">
          <div class="drawer-hdr">
            <div>
              <h3>Revisar Cadastro</h3>
              <p>{{ tokenAberto.TOKEN_DADOS?.nome || '—' }}</p>
            </div>
            <button class="drawer-close" @click="tokenAberto = null">✕</button>
          </div>
          <div class="drawer-body" v-if="tokenAberto.TOKEN_DADOS">
            <div class="dr-section">
              <div class="dr-sec-title">👤 Dados Pessoais</div>
              <div class="dr-grid">
                <div class="dr-item"><span class="dr-label">Nome</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.nome }}</span></div>
                <div class="dr-item"><span class="dr-label">Nome Social</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.nome_social || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">CPF</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.cpf || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Nascimento</span><span class="dr-val">{{ formatDate(tokenAberto.TOKEN_DADOS.data_nasc) }}</span></div>
                <div class="dr-item"><span class="dr-label">Sexo</span><span class="dr-val">{{ sexoLabel(tokenAberto.TOKEN_DADOS.sexo) }}</span></div>
                <div class="dr-item"><span class="dr-label">Estado Civil</span><span class="dr-val">{{ estadoCivilLabel(tokenAberto.TOKEN_DADOS.estado_civil) }}</span></div>
                <div class="dr-item"><span class="dr-label">RG</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.rg || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Órgão Emissor</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.org_emissor || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">PIS/PASEP</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.pis || '—' }}</span></div>
              </div>
            </div>
            <div class="dr-section">
              <div class="dr-sec-title">📞 Contato</div>
              <div class="dr-grid">
                <div class="dr-item"><span class="dr-label">E-mail</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.email }}</span></div>
                <div class="dr-item"><span class="dr-label">Telefone</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.telefone || '—' }}</span></div>
              </div>
            </div>
            <div class="dr-section">
              <div class="dr-sec-title">🏠 Endereço</div>
              <div class="dr-grid">
                <div class="dr-item"><span class="dr-label">CEP</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.cep || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Logradouro</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.logradouro || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Número</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.numero || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Bairro</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.bairro || '—' }}</span></div>
                <div class="dr-item"><span class="dr-label">Cidade/UF</span><span class="dr-val">{{ tokenAberto.TOKEN_DADOS.cidade }}{{ tokenAberto.TOKEN_DADOS.uf ? ' / ' + tokenAberto.TOKEN_DADOS.uf : '' }}</span></div>
              </div>
            </div>
          </div>
          <div class="drawer-footer">
            <div v-if="erroAprovacao" class="err-msg">{{ erroAprovacao }}</div>
            <button class="btn-revogar-full" :disabled="aprovando" @click="revogar(tokenAberto); tokenAberto = null">✕ Revogar</button>
            <button class="btn-aprovar-full" :disabled="aprovando" @click="aprovar(tokenAberto.TOKEN)">
              <span v-if="aprovando" class="btn-spin"></span>
              <template v-else>✅ Aprovar e Criar Conta</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    </transition>

    <!-- MODAL: confirmar revogação (BUG-EST-04) -->
    <transition name="modal">
      <div v-if="modalRevogar.visible" class="modal-overlay" @click.self="modalRevogar.visible = false">
        <div class="modal-card">
          <div class="modal-hdr">
            <h3>Revogar Link</h3>
            <button class="modal-close" @click="modalRevogar.visible = false">✕</button>
          </div>
          <div class="modal-body">
            <p style="font-size:14px;color:#475569;margin:0 0 16px">
              Revogar o link de <strong>{{ modalRevogar.nome }}</strong>? O candidato não poderá mais preencher o formulário.
            </p>
            <div class="modal-actions">
              <button class="btn-modal-cancel" @click="modalRevogar.visible = false">Cancelar</button>
              <button class="btn-modal-confirm" @click="revogar(modalRevogar.token); modalRevogar.visible = false">✕ Revogar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- TOAST -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const loaded    = ref(false)
const carregando = ref(true)
const gerando   = ref(false)
const aprovando = ref(false)
const tokens    = ref([])
const tokenAberto = ref(null)
const linkGerado  = ref(null)
const copiado     = ref(false)
const erroAprovacao = ref('')
const filtroAtivo = ref('todos')
const toast = ref({ visible: false, msg: '' })
const modalRevogar = ref({ visible: false, nome: '', token: null }) // BUG-EST-04
const router = useRouter()

// Form de geração
const novoNome    = ref('')
const novoEmail   = ref('')
const validadeDias = ref(7)

const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

onMounted(async () => {
  await carregar()
  setTimeout(() => loaded.value = true, 80)
})

const carregar = async () => {
  carregando.value = true
  try {
    const { data } = await api.get('/api/v3/autocadastro/pendentes')
    tokens.value = data.pendentes ?? []
  } catch {
    tokens.value = []
  } finally {
    carregando.value = false
  }
}

const filtros = computed(() => [
  { id: 'todos',      ico: '📋', nome: 'Todos' },
  { id: 'pendente',   ico: '🕐', nome: 'Aguardando',   count: countByStatus('pendente') || null },
  { id: 'preenchido', ico: '⏳', nome: 'Para aprovar', count: countByStatus('preenchido') || null },
  { id: 'aprovado',   ico: '✅', nome: 'Aprovados' },
  { id: 'revogado',   ico: '✕',  nome: 'Revogados' },
])

const countByStatus = (st) => tokens.value.filter(t => t.TOKEN_STATUS === st).length

const listaFiltrada = computed(() => {
  if (filtroAtivo.value === 'todos') return tokens.value
  return tokens.value.filter(t => t.TOKEN_STATUS === filtroAtivo.value)
})

const gerarLink = async () => {
  gerando.value = true; linkGerado.value = null
  try {
    const { data } = await api.post('/api/v3/autocadastro/gerar-link', {
      nome: novoNome.value || null,
      email: novoEmail.value || null,
      validade_dias: validadeDias.value,
    })
    linkGerado.value = data
    novoNome.value = ''; novoEmail.value = ''
    await carregar()
    showToast('🔗 Link gerado com sucesso!')
  } catch (e) {
    showToast('❌ Erro ao gerar link: ' + (e.response?.data?.erro || e.message))
  } finally {
    gerando.value = false
  }
}

const copiar = async (url) => {
  try { await navigator.clipboard.writeText(url) } catch { }
  copiado.value = true
  setTimeout(() => copiado.value = false, 2500)
}

const copiarToken = (token) => {
  const url = `${window.location.origin}/autocadastro/${token}`
  copiar(url)
  showToast('📋 Link copiado!')
}

const abrir = (t) => {
  erroAprovacao.value = ''
  tokenAberto.value = { ...t, TOKEN_DADOS: typeof t.TOKEN_DADOS === 'string' ? JSON.parse(t.TOKEN_DADOS) : (t.TOKEN_DADOS ?? {}) }
}

const aprovar = async (token) => {
  aprovando.value = true; erroAprovacao.value = ''
  try {
    const { data } = await api.post(`/api/v3/autocadastro/${token}/aprovar`)
    const idx = tokens.value.findIndex(t => t.TOKEN === token)
    if (idx !== -1) {
      tokens.value[idx].TOKEN_STATUS = 'aprovado'
      if (data.funcionario_id) tokens.value[idx].FUNCIONARIO_ID = data.funcionario_id
    }
    tokenAberto.value = null
    // BUG-EST-09: exibir matrícula e login no toast
    const mat = data.matricula ? ` Matrícula: ${data.matricula} | Login: ${data.login ?? ''}` : ''
    showToast(`✅ Cadastro aprovado!${mat}`)
  } catch (e) {
    erroAprovacao.value = e.response?.data?.erro || 'Erro ao aprovar.'
  } finally {
    aprovando.value = false
  }
}

// BUG-EST-09: navegar para perfil do funcionário
const verPerfil = (funcId) => router.push(`/funcionarios/${funcId}`)

// BUG-EST-04: substituir confirm() por modal
const confirmarRevogar = (t) => {
  modalRevogar.value = { visible: true, nome: t.TOKEN_NOME || t.TOKEN_EMAIL || 'este candidato', token: t }
}

const revogar = async (t) => {
  try {
    await api.delete(`/api/v3/autocadastro/${t.TOKEN}`)
    const idx = tokens.value.findIndex(x => x.TOKEN === t.TOKEN)
    if (idx !== -1) tokens.value[idx].TOKEN_STATUS = 'revogado'
    showToast('✕ Token revogado.')
  } catch {
    showToast('❌ Erro ao revogar.')
  }
}

// Helpers
const statusLabel = (st) => ({ pendente: '🕐 Aguardando', preenchido: '⏳ Para aprovar', aprovado: '✅ Aprovado', revogado: '✕ Revogado', expirado: '⌛ Expirado' }[st] ?? st)
const isExpirando = (t) => t.expira_em && new Date(t.expira_em) < new Date(Date.now() + 2 * 86400000)
const formatDate  = (d) => { if (!d) return '—'; try { return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }) } catch { return d } }
const sexoLabel   = (v) => ({ '1': 'Masculino', '2': 'Feminino', '3': 'Não-binário' }[v] ?? '—')
const estadoCivilLabel = (v) => ({ '1': 'Solteiro(a)', '2': 'Casado(a)', '3': 'Separado(a)', '4': 'Divorciado(a)', '5': 'Viúvo(a)' }[v] ?? '—')
</script>

<style scoped>
.ac-gest-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1a1f3a 55%, #1e2a4a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs  { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #a78bfa; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.hs-card { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 12px 20px; text-align: center; min-width: 110px; }
.hs-card.hs-warn { border-color: #f59e0b40; background: rgba(245,158,11,0.08); }
.hs-card.hs-ok   { border-color: #34d39940; background: rgba(52,211,153,0.08); }
.hs-val   { display: block; font-size: 26px; font-weight: 900; color: #fff; }
.hs-label { display: block; font-size: 10px; font-weight: 600; color: #64748b; margin-top: 2px; }

/* PANEL */
.panel { background: #fff; border-radius: 20px; padding: 22px 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.panel.loaded { opacity: 1; transform: none; }
.panel-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.panel-ico { font-size: 18px; }
.panel-hdr h2 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; flex: 1; }

/* GERADOR */
.gerador-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.gf-field { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 160px; }
.gf-field-sm { max-width: 120px; }
.gf-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.opt { font-size: 10px; color: #94a3b8; text-transform: none; font-weight: 400; }
.gf-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px; font-size: 13px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; }
.gf-input:focus { border-color: #6366f1; }
.btn-gerar { padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; height: 42px; display: flex; align-items: center; gap: 6px; font-family: inherit; transition: all 0.15s; }
.btn-gerar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-gerar:disabled { opacity: 0.5; cursor: not-allowed; }

/* LINK RESULT */
.link-result { margin-top: 14px; background: #f0f4ff; border: 1.5px solid #c7d2fe; border-radius: 14px; padding: 14px 18px; }
.link-box { display: flex; align-items: center; gap: 10px; }
.link-url { font-size: 12px; font-family: monospace; color: #4338ca; flex: 1; word-break: break-all; }
.btn-copy { padding: 6px 14px; border-radius: 9px; border: 1.5px solid #c7d2fe; background: #fff; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; color: #4338ca; font-family: inherit; transition: all 0.15s; }
.btn-copy:hover { background: #eef2ff; }
.link-info { font-size: 11px; color: #6366f1; margin: 6px 0 0; }

/* FILTER TABS */
.filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto; }
.ftab { display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.ftab.active { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.ftab-count { background: #fee2e2; color: #b91c1c; border-radius: 99px; padding: 1px 6px; font-size: 10px; font-weight: 800; }
.btn-refresh { border: none; background: none; font-size: 16px; cursor: pointer; padding: 4px; border-radius: 8px; transition: background 0.15s; }
.btn-refresh:hover { background: #f1f5f9; }

/* TABELA */
.tokens-table { display: flex; flex-direction: column; gap: 0; border: 1px solid #f1f5f9; border-radius: 14px; overflow: hidden; }
.tt-hdr { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 8px; padding: 10px 16px; background: #f8fafc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.tt-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 8px; padding: 12px 16px; align-items: center; border-bottom: 1px solid #f8fafc; transition: background 0.1s; }
.tt-row:last-child { border-bottom: none; }
.tt-row:hover { background: #fafafa; }
.tt-row.row-preenchido { border-left: 3px solid #f59e0b; }
.tt-row.row-aprovado   { border-left: 3px solid #34d399; opacity: 0.7; }
.tt-row.row-revogado   { border-left: 3px solid #94a3b8; opacity: 0.6; }
.tt-nome  { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.tt-email { display: block; font-size: 11px; color: #94a3b8; }
.tt-date  { font-size: 12px; color: #64748b; }
.exp-warn { color: #ef4444; font-weight: 700; }

.status-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; display: inline-block; }
.sb-pendente   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.sb-preenchido { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.sb-aprovado   { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
.sb-revogado   { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
.sb-expirado   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

.tt-acoes { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-act { padding: 5px 12px; border-radius: 8px; border: 1px solid; font-size: 11px; font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap; transition: all 0.13s; }
.btn-aprovar { background: #f0fdf4; border-color: #86efac; color: #166534; }
.btn-aprovar:hover { background: #dcfce7; }
.btn-copiar  { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.btn-copiar:hover { background: #dbeafe; }
.btn-revogar { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
.btn-revogar:hover { background: #fee2e2; }
.btn-perfil  { background: #f0f9ff; border-color: #bae6fd; color: #0369a1; }
.btn-perfil:hover { background: #e0f2fe; }

/* MODAL DE CONFIRMAÇÃO */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(3px); z-index: 200; display: flex; align-items: center; justify-content: center; }
.modal-card { background: #fff; border-radius: 18px; padding: 0; width: min(400px, 92vw); box-shadow: 0 24px 64px rgba(0,0,0,0.18); overflow: hidden; }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
.modal-hdr h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; color: #64748b; font-size: 13px; }
.modal-body { padding: 16px 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 4px; }
.btn-modal-cancel  { padding: 8px 18px; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff; color: #64748b; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }
.btn-modal-confirm { padding: 8px 18px; border: none; border-radius: 10px; background: #ef4444; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }

/* STATE BOX */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 48px; color: #94a3b8; gap: 10px; }
.state-box p { font-size: 14px; margin: 0; }
.spinner { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* DRAWER */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); z-index: 100; display: flex; justify-content: flex-end; }
.drawer { background: #fff; width: min(480px, 100%); height: 100%; overflow-y: auto; box-shadow: -16px 0 48px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
.drawer-hdr { display: flex; align-items: flex-start; gap: 12px; padding: 22px; border-bottom: 1px solid #f1f5f9; }
.drawer-hdr h3 { font-size: 16px; font-weight: 900; color: #1e293b; margin: 0 0 3px; }
.drawer-hdr p  { font-size: 12px; color: #94a3b8; margin: 0; }
.drawer-close { margin-left: auto; border: none; background: #f1f5f9; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; font-size: 13px; color: #64748b; flex-shrink: 0; }
.drawer-body  { padding: 18px 22px; display: flex; flex-direction: column; gap: 18px; flex: 1; }
.dr-section { display: flex; flex-direction: column; gap: 8px; }
.dr-sec-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #6366f1; padding-bottom: 6px; border-bottom: 1px solid #f1f5f9; }
.dr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.dr-item { background: #f8fafc; border-radius: 10px; padding: 8px 12px; }
.dr-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
.dr-val   { display: block; font-size: 13px; font-weight: 600; color: #1e293b; }
.drawer-footer { padding: 16px 22px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; }
.btn-revogar-full { flex: 0; padding: 11px 18px; border-radius: 12px; border: 1.5px solid #fca5a5; background: #fef2f2; color: #dc2626; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap; }
.btn-aprovar-full { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 0; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-aprovar-full:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.35); }
.btn-aprovar-full:disabled { opacity: 0.5; cursor: not-allowed; }
.err-msg { font-size: 12px; font-weight: 600; color: #dc2626; background: #fef2f2; border-radius: 8px; padding: 8px 12px; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }

/* TOAST */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 300; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
.fade-enter-active, .fade-leave-active { transition: all 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-6px); }
.drawer-enter-active, .drawer-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer, .drawer-leave-to .drawer { transform: translateX(100%); }

@media (max-width: 700px) {
  .tt-hdr, .tt-row { grid-template-columns: 1fr 1fr; }
  .tt-hdr span:nth-child(3), .tt-hdr span:nth-child(4),
  .tt-row > div:nth-child(3), .tt-row > div:nth-child(4) { display: none; }
  .gerador-form { flex-direction: column; }
  .gf-field-sm { max-width: 100%; }
  .hero-stats { gap: 8px; }
}
</style>
