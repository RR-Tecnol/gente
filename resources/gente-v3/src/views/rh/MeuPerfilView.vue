<template>
  <div class="perfil-page">

    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <!-- Avatar -->
        <div class="hero-avatar" :style="{ '--hue': avatarHue }">
          {{ iniciais }}
        </div>
        <div class="hero-info">
          <span class="hero-eyebrow">👤 Meu Perfil</span>
          <h1 class="hero-nome">{{ nomeExibido }}</h1>
          <p class="hero-cargo">{{ cargo }} · {{ setor }}</p>
          <div class="hero-meta">
            <span class="meta-chip" v-if="funcionario?.FUNCIONARIO_MATRICULA">🪪 {{ funcionario.FUNCIONARIO_MATRICULA }}</span>
            <span class="meta-chip">📅 Desde {{ formatDate(funcionario?.FUNCIONARIO_DATA_INICIO) }}</span>
            <span class="meta-chip status-active" v-if="!funcionario?.FUNCIONARIO_DATA_FIM">● Ativo</span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="state-box"><div class="spinner"></div><p>Carregando perfil...</p></div>

    <!-- CONTEÚDO -->
    <div v-else class="perfil-grid" :class="{ loaded }">

      <!-- ═══ COLUNA ESQUERDA ═══ -->
      <div class="col-left">

        <!-- Dados Pessoais (somente leitura) -->
        <div class="info-card anim-card" :style="{ '--ci': 0 }">
          <div class="card-hdr"><span class="card-ico" style="background:#eff6ff;color:#3b82f6">👤</span><h2 class="card-title">Dados Pessoais</h2></div>
          <div class="fields-grid" v-if="funcionario?.pessoa">
            <div class="field"><span class="fl">Nome</span><span class="fv">{{ funcionario.pessoa.PESSOA_NOME }}</span></div>
            <div class="field"><span class="fl">CPF</span><span class="fv mono">{{ formatCpf(funcionario.pessoa.PESSOA_CPF_NUMERO) }}</span></div>
            <div class="field" v-if="funcionario.pessoa.PESSOA_DATA_NASCIMENTO"><span class="fl">Nascimento</span><span class="fv">{{ formatDate(funcionario.pessoa.PESSOA_DATA_NASCIMENTO) }}</span></div>
            <div class="field" v-if="funcionario.pessoa.PESSOA_SEXO != null"><span class="fl">Sexo</span><span class="fv">{{ sexoLabel(funcionario.pessoa.PESSOA_SEXO) }}</span></div>
            <div class="field" v-if="funcionario.pessoa.PESSOA_RG_NUMERO"><span class="fl">RG</span><span class="fv mono">{{ funcionario.pessoa.PESSOA_RG_NUMERO }}</span></div>
            <div class="field" v-if="funcionario.pessoa.PESSOA_PIS_PASEP"><span class="fl">PIS/PASEP</span><span class="fv mono">{{ funcionario.pessoa.PESSOA_PIS_PASEP }}</span></div>
          </div>
          <p v-else class="no-data">Dados pessoais não disponíveis.</p>
        </div>

        <!-- Dados Funcionais -->
        <div class="info-card anim-card" :style="{ '--ci': 1 }">
          <div class="card-hdr"><span class="card-ico" style="background:#f0fdf4;color:#16a34a">📋</span><h2 class="card-title">Dados Funcionais</h2></div>
          <div class="fields-grid">
            <div class="field"><span class="fl">Matrícula</span><span class="fv mono">{{ funcionario?.FUNCIONARIO_MATRICULA || '—' }}</span></div>
            <div class="field"><span class="fl">Início</span><span class="fv">{{ formatDate(funcionario?.FUNCIONARIO_DATA_INICIO) }}</span></div>
            <div class="field" v-if="setor"><span class="fl">Setor</span><span class="fv">{{ setor }}</span></div>
            <div class="field" v-if="unidade"><span class="fl">Unidade</span><span class="fv">{{ unidade }}</span></div>
            <div class="field" v-if="vinculo"><span class="fl">Vínculo</span><span class="fv">{{ vinculo }}</span></div>
            <div class="field" v-if="cargo"><span class="fl">Função</span><span class="fv">{{ cargo }}</span></div>
          </div>
        </div>

        <!-- Contatos -->
        <div class="info-card anim-card" :style="{ '--ci': 2 }" v-if="funcionario?.contatos?.length">
          <div class="card-hdr"><span class="card-ico" style="background:#fef9c3;color:#ca8a04">📞</span><h2 class="card-title">Contatos</h2></div>
          <div class="contatos-list">
            <div v-for="c in funcionario.contatos" :key="c.CONTATO_ID" class="contato-item">
              <span class="contato-tipo">{{ tipoContato(c.CONTATO_TIPO) }}</span>
              <span class="contato-val">{{ c.CONTATO_VALOR ?? c.CONTATO_TELEFONE ?? c.CONTATO_EMAIL }}</span>
            </div>
          </div>
        </div>

      </div>

      <!-- ═══ COLUNA DIREITA ═══ -->
      <div class="col-right">

        <!-- Editar Dados Editáveis -->
        <div class="info-card anim-card" :style="{ '--ci': 0 }">
          <div class="card-hdr">
            <span class="card-ico" style="background:#f5f3ff;color:#7c3aed">✏️</span>
            <h2 class="card-title">Editar Dados</h2>
          </div>
          <div class="edit-form">

            <div class="form-group">
              <label>Nome Social</label>
              <input v-model="editForm.PESSOA_NOME_SOCIAL" type="text" class="form-input" placeholder="Opcional — como prefere ser chamado(a)" />
            </div>

            <div class="form-group">
              <label>Estado Civil</label>
              <select v-model="editForm.PESSOA_ESTADO_CIVIL" class="form-input">
                <option value="">— selecione —</option>
                <option value="0">Solteiro(a)</option>
                <option value="1">Casado(a)</option>
                <option value="2">Divorciado(a)</option>
                <option value="3">Viúvo(a)</option>
                <option value="4">União Estável</option>
              </select>
            </div>

            <div class="form-group">
              <label>Escolaridade</label>
              <select v-model="editForm.PESSOA_ESCOLARIDADE" class="form-input">
                <option value="">— selecione —</option>
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
              <label>E-mail de acesso</label>
              <input v-model="editForm.USUARIO_EMAIL" type="email" class="form-input" placeholder="seu@email.com" />
            </div>

            <div v-if="erroEdit" class="form-erro">{{ erroEdit }}</div>
            <div v-if="okEdit"   class="form-ok">{{ okEdit }}</div>

            <button class="btn-salvar" :disabled="salvando" @click="salvarPerfil">
              <div v-if="salvando" class="btn-spinner"></div>
              <template v-else>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Salvar Alterações
              </template>
            </button>
          </div>
        </div>

        <!-- Acesso rápido -->
        <div class="info-card anim-card" :style="{ '--ci': 1 }">
          <div class="card-hdr"><span class="card-ico" style="background:#fff7ed;color:#ea580c">⚡</span><h2 class="card-title">Acesso Rápido</h2></div>
          <div class="quick-links">
            <router-link to="/ferias-licencas" class="ql-item">
              <span class="ql-ico">🏖️</span>
              <div><span class="ql-title">Férias e Licenças</span><span class="ql-sub">Ver e agendar períodos</span></div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </router-link>
            <router-link to="/meus-holerites" class="ql-item">
              <span class="ql-ico">💰</span>
              <div><span class="ql-title">Holerites</span><span class="ql-sub">Consultar contracheques</span></div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </router-link>
            <router-link to="/abono-faltas" class="ql-item">
              <span class="ql-ico">📝</span>
              <div><span class="ql-title">Abono de Faltas</span><span class="ql-sub">Justificar ausências</span></div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </router-link>
            <router-link to="/configuracoes" class="ql-item">
              <span class="ql-ico">🔑</span>
              <div><span class="ql-title">Alterar Senha</span><span class="ql-sub">Configurações de acesso</span></div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </router-link>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'

const loaded   = ref(false)
const loading  = ref(true)
const salvando = ref(false)
const erroEdit = ref('')
const okEdit   = ref('')

const funcionario = ref(null)
const usuario     = ref(null)

const editForm = reactive({
  PESSOA_NOME_SOCIAL:  '',
  PESSOA_ESTADO_CIVIL: '',
  PESSOA_ESCOLARIDADE: '',
  USUARIO_EMAIL:       '',
})

// ── Computed ──────────────────────────────────────────────────
const nomeExibido = computed(() =>
  editForm.PESSOA_NOME_SOCIAL || funcionario.value?.pessoa?.PESSOA_NOME || 'Meu Perfil'
)
const iniciais    = computed(() => {
  const n = funcionario.value?.pessoa?.PESSOA_NOME || 'U'
  return n.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})
const avatarHue   = computed(() => (funcionario.value?.FUNCIONARIO_ID ?? 42) * 37 % 360)
const cargo       = computed(() => funcionario.value?.atribuicao ?? '—')
const setor       = computed(() => funcionario.value?.setor ?? '—')
const unidade     = computed(() => funcionario.value?.unidade ?? '')
const vinculo     = computed(() => funcionario.value?.vinculo ?? '')

// ── Carregamento ──────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/perfil')
    funcionario.value = data.funcionario
    usuario.value     = data.usuario
    // Preenche formulário de edição
    editForm.PESSOA_NOME_SOCIAL  = data.funcionario?.pessoa?.PESSOA_NOME_SOCIAL  ?? ''
    editForm.PESSOA_ESTADO_CIVIL = String(data.funcionario?.pessoa?.PESSOA_ESTADO_CIVIL ?? '')
    editForm.PESSOA_ESCOLARIDADE = String(data.funcionario?.pessoa?.PESSOA_ESCOLARIDADE ?? '')
    editForm.USUARIO_EMAIL       = data.usuario?.USUARIO_EMAIL ?? ''
  } catch (e) {
    // Fallback silencioso
    funcionario.value = null
  } finally {
    loading.value = false
    setTimeout(() => { loaded.value = true }, 80)
  }
})

// ── Salvar ────────────────────────────────────────────────────
const salvarPerfil = async () => {
  salvando.value = true; erroEdit.value = ''; okEdit.value = ''
  try {
    await api.put('/api/v3/perfil', { ...editForm })
    okEdit.value = 'Perfil atualizado com sucesso!'
    setTimeout(() => { okEdit.value = '' }, 3000)
  } catch (e) {
    erroEdit.value = e.response?.data?.erro || 'Erro ao salvar.'
  } finally {
    salvando.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────
const formatDate = (d) => { try { return new Date(d+'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d ?? '—' } }
const formatCpf  = (cpf) => { if (!cpf) return '—'; const s = String(cpf).padStart(11, '0'); return `${s.slice(0,3)}.${s.slice(3,6)}.${s.slice(6,9)}-${s.slice(9)}` }
const sexoLabel  = (s) => ['Masculino','Feminino','Outro'][+s] ?? s
const tipoContato= (t) => ({ 0: '📞', 1: '📱', 2: '📧', 3: '🏠' })[t] ?? '📌'
</script>

<style scoped>
.perfil-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 28px 36px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #312e81 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 240px; height: 240px; background: #818cf8; right: -60px; top: -80px; }
.hs2 { width: 200px; height: 200px; background: #06b6d4; right: 300px; bottom: -80px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
.hero-avatar { width: 72px; height: 72px; border-radius: 18px; background: hsl(var(--hue), 60%, 45%); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 900; color: #fff; flex-shrink: 0; border: 3px solid rgba(255,255,255,0.2); }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #a5b4fc; margin-bottom: 4px; }
.hero-nome  { font-size: 24px; font-weight: 900; color: #fff; margin: 0 0 4px; }
.hero-cargo { font-size: 13px; color: #94a3b8; margin: 0 0 10px; }
.hero-meta  { display: flex; gap: 8px; flex-wrap: wrap; }
.meta-chip  { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; background: rgba(255,255,255,0.1); color: #e2e8f0; }
.status-active { background: rgba(52,211,153,0.2); color: #34d399; }

/* GRID */
.perfil-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; opacity: 0; transform: translateY(8px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.perfil-grid.loaded { opacity: 1; transform: none; }
.col-left, .col-right { display: flex; flex-direction: column; gap: 14px; }

/* CARDS */
.info-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px; animation: cardAnim 0.4s cubic-bezier(0.22,1,0.36,1) calc(var(--ci, 0) * 60ms) both; }
@keyframes cardAnim { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.card-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.card-ico { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.card-title { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0; }
.fields-grid { display: flex; flex-direction: column; gap: 10px; }
.field { display: flex; flex-direction: column; gap: 2px; padding-bottom: 10px; border-bottom: 1px solid #f8fafc; }
.field:last-child { border-bottom: none; padding-bottom: 0; }
.fl { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; }
.fv { font-size: 14px; font-weight: 600; color: #1e293b; }
.mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; color: #475569; }
.no-data { font-size: 13px; color: #94a3b8; }

/* CONTATOS */
.contatos-list { display: flex; flex-direction: column; gap: 10px; }
.contato-item { display: flex; align-items: center; gap: 10px; font-size: 13px; }
.contato-tipo { font-size: 18px; flex-shrink: 0; }
.contato-val  { color: #1e293b; font-weight: 600; }

/* FORMULÁRIO EDIÇÃO */
.edit-form { display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; }
.form-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 9px 12px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: #6366f1; }
.form-erro { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 9px 12px; font-size: 13px; font-weight: 600; color: #dc2626; }
.form-ok   { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 9px 12px; font-size: 13px; font-weight: 600; color: #15803d; }
.btn-salvar { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.18s; }
.btn-salvar:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(99,102,241,0.35); }
.btn-salvar:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* QUICK LINKS */
.quick-links { display: flex; flex-direction: column; gap: 4px; }
.ql-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: all 0.15s; }
.ql-item:hover { background: #f8fafc; border-color: #e2e8f0; }
.ql-ico { font-size: 20px; flex-shrink: 0; }
.ql-title { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.ql-sub   { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; }
.ql-item svg { margin-left: auto; flex-shrink: 0; }

/* STATE */
.state-box { display: flex; flex-direction: column; align-items: center; padding: 60px; gap: 12px; color: #94a3b8; }
.state-box p { font-size: 14px; margin: 0; }
.spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }

@media (max-width: 720px) { .perfil-grid { grid-template-columns: 1fr; } }
</style>
