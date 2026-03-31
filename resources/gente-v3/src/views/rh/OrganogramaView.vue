<template>
  <div class="org-page">

    <!-- HERO ─────────────────────────────────────────────────── -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">🏥 Gestão Hospitalar</span>
          <h1 class="hero-title">Organograma</h1>
          <p class="hero-sub">Estrutura hierárquica · {{ totalFuncionarios }} servidores em {{ totalSetores }} setores</p>
        </div>
        <div class="hero-controls">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input v-model="busca" class="s-input" placeholder="Buscar setor ou pessoa..." />
          </div>
          <div class="view-toggle">
            <button :class="{ active: modo === 'arvore' }" @click="modo = 'arvore'" title="Árvore">🌳</button>
            <button :class="{ active: modo === 'cards' }" @click="modo = 'cards'" title="Cards">🗂️</button>
          </div>
          <!-- Toggle de Perspectiva -->
          <div class="persp-toggle">
            <button :class="{ active: perspectiva === 'gestor' }" @click="perspectiva = 'gestor'" title="Visão administrativa">💼 Gestor</button>
            <button :class="{ active: perspectiva === 'funcionario' }" @click="perspectiva = 'funcionario'" title="Minha visão">👤 Meu Setor</button>
          </div>
          <button v-if="perspectiva === 'gestor'" class="btn-novo" @click="abrirModalNovo">+ Novo Setor</button>
        </div>
      </div>
    </div>

    <!-- ══ PERSPECTIVA FUNCIONÁRIO ══════════════════════════════ -->
    <div v-if="perspectiva === 'funcionario'" class="func-view" :class="{ loaded }" key="func">

      <!-- Cartão "Eu no Organograma" -->
      <div class="meu-setor-hero">
        <div class="msh-avatar" :style="{ background: meuSetorDir?.cor || '#6366f1' }">{{ iniciais(meuNome) }}</div>
        <div class="msh-info">
          <span class="msh-nome">{{ meuNome }}</span>
          <span class="msh-cargo">{{ meuCargo }}</span>
          <div class="msh-trilha">
            <span class="msh-hosp">{{ nomeHospital }}</span>
            <span class="msh-sep">›</span>
            <span class="msh-dir" :style="{ color: meuSetorDir?.cor || '#6366f1' }">{{ meuSetorDir?.nome || '—' }}</span>
            <span class="msh-sep">›</span>
            <span class="msh-setor">{{ meuSetor?.nome || 'Sem setor definido' }}</span>
          </div>
        </div>
        <div v-if="meuSetor" class="msh-badge" :style="{ background: meuSetorDir?.cor + '18', color: meuSetorDir?.cor, border: '1.5px solid ' + meuSetorDir?.cor + '40' }">
          {{ meuSetor.ico }} {{ meuSetor.nome }}
        </div>
      </div>

      <!-- Equipe do setor -->
      <div v-if="meuSetor" class="func-section">
        <div class="func-section-hdr">
          <span class="fsh-ico">👥</span>
          <h3>Minha Equipe — {{ meuSetor.nome }}</h3>
          <span class="fsh-count">{{ meuSetor.funcionarios.length }} servidor{{ meuSetor.funcionarios.length !== 1 ? 'es' : '' }}</span>
        </div>
        <div class="equipe-grid">
          <div v-for="(p, i) in meuSetor.funcionarios" :key="i" class="equipe-card" :class="{ eu: p.nome === meuNome }" :style="{ '--ec': meuSetorDir?.cor || '#6366f1' }">
            <div class="eq-avatar" :style="{ '--eah': avatarHue(i) }">{{ iniciais(p.nome) }}</div>
            <div class="eq-info">
              <span class="eq-nome">{{ p.nome }}</span>
              <span class="eq-cargo">{{ p.cargo }}</span>
            </div>
            <span v-if="p.nome === meuNome" class="eq-eu">Você</span>
          </div>
          <div v-if="meuSetor.funcionarios.length === 0" class="eq-vazio">Nenhum colega registrado neste setor.</div>
        </div>
      </div>

      <!-- Organograma somente-leitura (modo árvore, sem CRUD) -->
      <div class="func-section">
        <div class="func-section-hdr">
          <span class="fsh-ico">🏥</span>
          <h3>Estrutura da Instituição</h3>
          <span class="fsh-count">{{ totalSetores }} setores</span>
        </div>
        <!-- TASK-ORG-02 somente-leitura: accordion vertical -->
        <div class="acc-container">
          <div class="acc-root" style="pointer-events:none">
            <span class="root-ico">🏥</span>
            <div class="root-info">
              <span class="root-nome">{{ nomeHospital }}</span>
              <span class="root-sub">{{ totalFuncionarios }} servidores</span>
            </div>
          </div>
          <div v-for="dir in estrutura" :key="dir.id" class="acc-section">
            <div class="acc-hdr" :class="{ 'meu-dir-acc': meuSetorDir?.id === dir.id }" :style="{ '--dc': dir.cor }" @click="toggleDir(dir.id)">
              <span class="acc-hdr-ico">{{ dir.ico }}</span>
              <div class="acc-hdr-info">
                <span class="acc-hdr-nome">{{ dir.nome }}</span>
                <span class="acc-hdr-count">{{ dir.setores.length }} setor{{ dir.setores.length !== 1 ? 'es' : '' }}</span>
              </div>
              <span class="acc-caret">{{ colapsados.has(dir.id) ? '›' : '˅' }}</span>
            </div>
            <transition name="acc-body">
              <div v-if="!colapsados.has(dir.id)" class="acc-body">
                <div class="acc-setores-grid">
                  <div v-for="s in filteredSetores(dir)" :key="s.id"
                    class="acc-setor-card"
                    :class="{ 'meu-setor-hl': meuSetor?.id === s.id }"
                    :style="{ '--sc': dir.cor }"
                    @click="selecionarSetor(s, dir)"
                    :title="s.responsavel">
                    <div class="asc-compact">
                      <span class="asc-ico">{{ s.ico }}</span>
                      <span class="asc-nome">{{ s.nome }}</span>
                      <span class="asc-cnt">👥 {{ s.funcionarios.length }}</span>
                      <span v-if="meuSetor?.id === s.id" class="meu-label">★</span>
                    </div>
                    <div class="asc-hover">
                      <span class="asc-resp">{{ s.responsavel || '—' }}</span>
                      <div class="asc-bar-wrap"><div class="asc-bar" :style="{ width: ((s.funcionarios.length/(maxFuncPorSetor||1))*100)+'%', background: dir.cor }"></div></div>
                    </div>
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ PERSPECTIVA GESTOR (modo árvore) — TASK-ORG-02 ACCORDION VERTICAL ══ -->
    <div v-if="perspectiva === 'gestor' && modo === 'arvore'" class="tree-wrap" :class="{ loaded }">
      <div class="acc-container">
        <!-- Cabeçalho da instituição -->
        <div class="acc-root">
          <span class="root-ico">🏥</span>
          <div class="root-info">
            <span class="root-nome">{{ nomeHospital }}</span>
            <span class="root-sub">{{ totalFuncionarios }} servidores · {{ totalSetores }} setores</span>
          </div>
          <div class="root-actions">
            <button class="btn-edit-hosp" @click.stop="abrirModalEditarHospital" title="Editar nome">Editar</button>
            <button class="btn-nova-dir" @click.stop="abrirModalNovaDiretoria" title="Nova Diretoria">+ Nova Diretoria</button>
          </div>
        </div>

        <!-- TASK-ORG-02: accordion vertical — uma diretoria por linha -->
        <div v-for="dir in estrutura" :key="dir.id ?? 'sem-dir'" class="acc-section">
          <!-- Header da diretoria -->
          <div class="acc-hdr" :style="{ '--dc': dir.cor }" @click="toggleDir(dir.id)">
            <span class="acc-hdr-ico">{{ dir.ico }}</span>
            <div class="acc-hdr-info">
              <span class="acc-hdr-nome">{{ dir.nome }}</span>
              <span class="acc-hdr-count">{{ dir.setores.length }} setor{{ dir.setores.length !== 1 ? 'es' : '' }} · {{ totalFuncDir(dir) }} servidor{{ totalFuncDir(dir) !== 1 ? 'es' : '' }}</span>
            </div>
            <div class="acc-hdr-actions" v-if="dir.id && dir.id !== 0" @click.stop>
              <button class="sca-btn sca-edit" @click="abrirModalEditarDiretoria(dir)">Editar</button>
              <button class="sca-btn sca-del" @click="confirmarExcluirDiretoria(dir)">Excluir</button>
            </div>
            <span class="acc-caret">{{ colapsados.has(dir.id) ? '›' : '˅' }}</span>
          </div>

          <!-- TASK-ORG-03: grid de setores compact + hover detail -->
          <transition name="acc-body">
            <div v-if="!colapsados.has(dir.id)" class="acc-body">
              <div class="acc-setores-grid">
                <div v-for="s in filteredSetores(dir)" :key="s.id"
                  class="acc-setor-card"
                  :style="{ '--sc': dir.cor }"
                  @click="selecionarSetor(s, dir)"
                  :title="s.responsavel">
                  <!-- repouso: só ícone + nome + contador -->
                  <div class="asc-compact">
                    <span class="asc-ico">{{ s.ico }}</span>
                    <span class="asc-nome">{{ s.nome }}</span>
                    <span class="asc-cnt">👥 {{ s.funcionarios.length }}</span>
                  </div>
                  <!-- hover: detalhe com responsável, barra e ações -->
                  <div class="asc-hover">
                    <span class="asc-resp">{{ s.responsavel || '—' }}</span>
                    <div class="asc-bar-wrap"><div class="asc-bar" :style="{ width: ((s.funcionarios.length/(maxFuncPorSetor||1))*100)+'%', background: dir.cor }"></div></div>
                    <div class="asc-actions" @click.stop>
                      <button class="sca-btn sca-edit" @click="abrirModalEditar(s)">Editar</button>
                      <button class="sca-btn sca-del" @click="confirmarExcluir(s)">Excluir</button>
                    </div>
                  </div>
                </div>
                <div class="acc-setor-card acc-add" @click.stop="abrirModalNovoNaDir(dir)">
                  <span>＋ Adicionar Setor</span>
                </div>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- ══ PERSPECTIVA GESTOR (modo cards) ═════──────────────────────── -->
    <div v-if="perspectiva === 'gestor' && modo === 'cards'" class="cards-wrap" :class="{ loaded }">
      <div v-for="dir in estrutura" :key="dir.id" class="dir-section">
        <div class="dir-section-hdr" :style="{ borderColor: dir.cor }">
          <span class="dsh-ico">{{ dir.ico }}</span>
          <h2>{{ dir.nome }}</h2>
          <span class="dsh-line"></span>
          <span class="dsh-count">{{ totalFuncDir(dir) }} servidores</span>
        </div>
        <div class="pessoas-grid">
          <div v-for="p in pessoasDaDir(dir)" :key="p._key" class="pessoa-card" :style="{ '--pc': dir.cor }">
            <div class="pc-avatar" :style="{ '--ph': avatarHue(p._key) }">{{ iniciais(p.nome) }}</div>
            <div class="pc-info">
              <span class="pc-nome">{{ p.nome }}</span>
              <span class="pc-cargo">{{ p.cargo }}</span>
              <span class="pc-setor" :style="{ color: dir.cor }">{{ p.setor }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- DRAWER SETOR ────────────────────────────────────────── -->
    <transition name="drawer">
      <div v-if="setorAberto" class="drawer-overlay" @click.self="setorAberto = null">
        <div class="drawer">
          <div class="drawer-hdr">
            <span class="drawer-ico">{{ setorAberto.ico }}</span>
            <div>
              <h3 class="drawer-title">{{ setorAberto.nome }}</h3>
              <p class="drawer-sub">{{ setorAberto.funcionarios.length }} servidor{{ setorAberto.funcionarios.length !== 1 ? 'es' : '' }}</p>
            </div>
            <div class="drawer-hdr-actions">
              <button class="sca-btn sca-edit" @click="abrirModalEditar(setorAberto); setorAberto = null" title="Editar">Editar</button>
              <button class="drawer-close" @click="setorAberto = null">✕</button>
            </div>
          </div>
          <div class="drawer-body">
            <div class="dr-section-title">Responsável</div>
            <div class="dr-responsavel">
              <div class="dr-avatar" :style="{ '--dh': 200 }">{{ iniciais(setorAberto.responsavel) }}</div>
              <div>
                <span class="dr-nome">{{ setorAberto.responsavel }}</span>
                <span class="dr-cargo">Gestor de Setor</span>
              </div>
            </div>
            <div class="dr-section-title">Equipe</div>
            <div class="dr-pessoas">
              <div v-for="(p, i) in setorAberto.funcionarios" :key="i" class="dr-pessoa">
                <div class="dr-avatar" :style="{ '--dh': avatarHue(i) }">{{ iniciais(p.nome) }}</div>
                <div>
                  <span class="dr-nome">{{ p.nome }}</span>
                  <span class="dr-cargo">{{ p.cargo }}</span>
                </div>
              </div>
              <div v-if="setorAberto.funcionarios.length === 0" class="dr-vazio">Nenhum servidor registrado neste setor.</div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL CRUD SETOR ─────────────────────────────────────── -->
    <transition name="modal">
      <div v-if="modalAberto" class="modal-overlay" @click.self="modalAberto = false">
        <div class="modal">
          <div class="modal-hdr">
            <h3>{{ editandoId ? 'Editar Setor' : 'Novo Setor' }}</h3>
            <button class="modal-close" @click="modalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="mf-group">
              <label>Nome do Setor <span class="req">*</span></label>
              <input v-model="formSetor.nome" class="mf-input" placeholder="Ex: UTI Adulto" maxlength="200" />
            </div>
            <div class="mf-group">
              <label>Sigla</label>
              <input v-model="formSetor.sigla" class="mf-input" placeholder="Ex: UTI-A" maxlength="20" />
            </div>
            <div class="mf-group">
              <label>Diretoria / Unidade</label>
              <select v-model="formSetor.unidade_id" class="mf-input">
                <option :value="null">Sem diretoria</option>
                <option v-for="u in unidadesFlat" :key="u.id" :value="u.id">{{ u.nome }}</option>
              </select>
            </div>
            <p v-if="erroModal" class="modal-err">{{ erroModal }}</p>
          </div>
          <div class="modal-footer">
            <button class="modal-cancel" @click="modalAberto = false">Cancelar</button>
            <button class="modal-save" :disabled="salvando || !formSetor.nome.trim()" @click="salvarSetor">
              <span v-if="salvando" class="btn-spin"></span>
              <template v-else>{{ editandoId ? 'Salvar' : 'Criar Setor' }}</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- CONFIRM DELETE SETOR ────────────────────────────────── -->
    <transition name="modal">
      <div v-if="confirmExcluir" class="modal-overlay" @click.self="confirmExcluir = null">
        <div class="modal modal-sm">
          <div class="modal-hdr">
            <h3>Excluir Setor</h3>
            <button class="modal-close" @click="confirmExcluir = null">✕</button>
          </div>
          <div class="modal-body">
            <p class="confirm-txt">Tem certeza que deseja remover o setor <strong>{{ confirmExcluir.nome }}</strong>?<br><span class="confirm-sub">Esta ação não pode ser desfeita.</span></p>
          </div>
          <div class="modal-footer">
            <button class="modal-cancel" @click="confirmExcluir = null">Cancelar</button>
            <button class="modal-del" :disabled="excluindo" @click="excluirSetor">
              <span v-if="excluindo" class="btn-spin"></span>
              <template v-else>Excluir</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL CRUD DIRETORIA ─────────────────────────────────── -->
    <transition name="modal">
      <div v-if="modalDiretoriaAberto" class="modal-overlay" @click.self="modalDiretoriaAberto = false">
        <div class="modal">
          <div class="modal-hdr">
            <h3>{{ editandoDiretoriaId ? 'Editar Diretoria' : 'Nova Diretoria' }}</h3>
            <button class="modal-close" @click="modalDiretoriaAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="mf-group">
              <label>Nome da Diretoria <span class="req">*</span></label>
              <input v-model="formDiretoria.nome" class="mf-input" placeholder="Ex: Diretoria Médica" maxlength="200" />
            </div>
            <div class="mf-group">
              <label>Sigla</label>
              <input v-model="formDiretoria.sigla" class="mf-input" placeholder="Ex: DIR-MED" maxlength="20" />
            </div>
            <p v-if="erroDirModal" class="modal-err">{{ erroDirModal }}</p>
          </div>
          <div class="modal-footer">
            <button class="modal-cancel" @click="modalDiretoriaAberto = false">Cancelar</button>
            <button class="modal-save" :disabled="salvandoDir || !formDiretoria.nome.trim()" @click="salvarDiretoria">
              <span v-if="salvandoDir" class="btn-spin"></span>
              <template v-else>{{ editandoDiretoriaId ? 'Salvar' : 'Criar Diretoria' }}</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- CONFIRM DELETE DIRETORIA ────────────────────────────── -->
    <transition name="modal">
      <div v-if="confirmExcluirDiretoria" class="modal-overlay" @click.self="confirmExcluirDiretoria = null">
        <div class="modal modal-sm">
          <div class="modal-hdr">
            <h3>Excluir Diretoria</h3>
            <button class="modal-close" @click="confirmExcluirDiretoria = null">✕</button>
          </div>
          <div class="modal-body">
            <p class="confirm-txt">Tem certeza que deseja remover a diretoria <strong>{{ confirmExcluirDiretoria.nome }}</strong>?<br><span class="confirm-sub">Os setores vinculados não serão excluídos, mas perderão a associação.</span></p>
          </div>
          <div class="modal-footer">
            <button class="modal-cancel" @click="confirmExcluirDiretoria = null">Cancelar</button>
            <button class="modal-del" :disabled="excluindoDir" @click="excluirDiretoria">
              <span v-if="excluindoDir" class="btn-spin"></span>
              <template v-else>Excluir</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL EDITAR HOSPITAL ─────────────────────────────── -->
    <transition name="modal">
      <div v-if="modalHospitalAberto" class="modal-overlay" @click.self="modalHospitalAberto = false">
        <div class="modal">
          <div class="modal-hdr">
            <h3>Editar Hospital</h3>
            <button class="modal-close" @click="modalHospitalAberto = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="mf-group">
              <label>Nome do Hospital <span class="req">*</span></label>
              <input v-model="formHospital.nome" class="mf-input" placeholder="Ex: Hospital Municipal" maxlength="200" />
            </div>
            <p v-if="erroHospitalModal" class="modal-err">{{ erroHospitalModal }}</p>
          </div>
          <div class="modal-footer">
            <button class="modal-cancel" @click="modalHospitalAberto = false">Cancelar</button>
            <button class="modal-save" :disabled="salvandoHospital || !formHospital.nome.trim()" @click="salvarHospital">
              <span v-if="salvandoHospital" class="btn-spin"></span>
              <template v-else>Salvar</template>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- TOAST ───────────────────────────────────────────────── -->
    <transition name="toast"><div v-if="toast.visible" class="toast">{{ toast.msg }}</div></transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/store/auth'

const icones = ['🏥','💉','📋','🔬','🏃','🛡️','🩺','🫀','👶','🚑','🛏️','💼','💰','🧪','🦴']

const loaded      = ref(false)
const busca       = ref('')
const modo        = ref('arvore')
const selecionado = ref(null)
const setorAberto = ref(null)
const colapsados  = reactive(new Set())
const toast       = ref({ visible: false, msg: '' })

// ── Perspectiva (gestor | funcionario) ───────────────────────────
const auth = useAuthStore()
const perfil = computed(() => auth.user?.perfil || auth.user?.role || '')
const perspectiva = ref(['funcionario'].includes(perfil.value?.toLowerCase()) ? 'funcionario' : 'gestor')

// Dados do usuário logado para visão funcionário
const meuNome  = computed(() => auth.user?.nome || auth.user?.name || 'Eu')
const meuCargo = computed(() => auth.user?.cargo || 'Servidor')
// Qual setor o usuário logado pertence (buscado pela lista de funcionários nos setores)
const meuSetor = computed(() => {
  const nome = meuNome.value
  for (const dir of estrutura.value) {
    const setor = dir.setores.find(s => s.funcionarios.some(f => f.nome === nome))
    if (setor) return setor
  }
  // fallback: primeiro setor da lista
  return estrutura.value[0]?.setores[0] ?? null
})
const meuSetorDir = computed(() => {
  const nome = meuNome.value
  return estrutura.value.find(d => d.setores.some(s => s.funcionarios.some(f => f.nome === nome))) ?? estrutura.value[0] ?? null
})

// ── Hospital (nó raiz) ────────────────────────────────────────
const nomeHospital       = ref(localStorage.getItem('sisgep_hospital_nome') || 'Hospital Municipal')
const modalHospitalAberto = ref(false)
const salvandoHospital   = ref(false)
const erroHospitalModal  = ref('')
const formHospital       = reactive({ nome: '' })

// ── CRUD Setor state ──────────────────────────────────────────
const modalAberto    = ref(false)
const confirmExcluir = ref(null)
const editandoId     = ref(null)
const salvando       = ref(false)
const excluindo      = ref(false)
const erroModal      = ref('')
const formSetor      = reactive({ nome: '', sigla: '', unidade_id: null, ico: '🏥' })
const unidadesFlat   = ref([])

// ── CRUD Diretoria state ──────────────────────────────────────
const modalDiretoriaAberto    = ref(false)
const confirmExcluirDiretoria = ref(null)
const editandoDiretoriaId     = ref(null)
const salvandoDir             = ref(false)
const excluindoDir            = ref(false)
const erroDirModal            = ref('')
const formDiretoria           = reactive({ nome: '', sigla: '', ico: '🏢' })



// ── Mock data ─────────────────────────────────────────────────
const CORES = ['#6366f1','#0d9488','#f59e0b','#e11d48','#0ea5e9','#8b5cf6']
const mockEstrutura = [
  {
    id: 1, nome: 'Diretoria Médica', ico: '🩺', cor: '#6366f1',
    setores: [
      { id: 11, ico: '🫀', nome: 'UTI Adulto', responsavel: 'Dr. Carlos Mendes', unidade_id: 1, funcionarios: [{ nome: 'Ana Silva', cargo: 'Médica Intensivista' },{ nome: 'Roberto Lima', cargo: 'Enfermeiro' },{ nome: 'Carla Santos', cargo: 'Técnica de Enfermagem' }] },
      { id: 12, ico: '👶', nome: 'UTI Neonatal', responsavel: 'Dra. Fernanda Azevedo', unidade_id: 1, funcionarios: [{ nome: 'Marcos Pereira', cargo: 'Neonatologista' }, { nome: 'Juliana Costa', cargo: 'Enfermeira Pediátrica' }] },
      { id: 13, ico: '🚑', nome: 'Pronto-Socorro', responsavel: 'Dr. Lucas Ferreira', unidade_id: 1, funcionarios: [{ nome: 'Patricia Oliveira', cargo: 'Médica Emergencista' }, { nome: 'Thiago Nunes', cargo: 'Técnico de Enfermagem' }] },
    ]
  },
  {
    id: 2, nome: 'Diretoria de Enfermagem', ico: '💉', cor: '#0d9488',
    setores: [
      { id: 21, ico: '🛏️', nome: 'Clínica Médica', responsavel: 'Enf. Maria Carvalho', unidade_id: 2, funcionarios: [{ nome: 'Sandra Lima', cargo: 'Enfermeira' }, { nome: 'Paulo Freitas', cargo: 'Técnico de Enfermagem' }] },
      { id: 22, ico: '🏃', nome: 'Fisioterapia', responsavel: 'Ft. João Batista', unidade_id: 2, funcionarios: [{ nome: 'Larissa Cunha', cargo: 'Fisioterapeuta' }] },
    ]
  },
  {
    id: 3, nome: 'Diretoria Administrativa', ico: '📋', cor: '#f59e0b',
    setores: [
      { id: 31, ico: '💼', nome: 'Recursos Humanos', responsavel: 'Adm. Beatriz Faria', unidade_id: 3, funcionarios: [{ nome: 'Cristina Borges', cargo: 'Analista de RH' }] },
      { id: 32, ico: '💰', nome: 'Financeiro', responsavel: 'Cont. Eduardo Ramos', unidade_id: 3, funcionarios: [{ nome: 'Adriana Campos', cargo: 'Contadora' }] },
    ]
  },
]

// ── Estrutura reativa ─────────────────────────────────────────
const estrutura = ref([])

// Mapear dados reais do backend → formato da view
const mapEstrutura = (unidades) => {
  return unidades.map((u, i) => ({
    id:      u.id ?? 0,  // null vira 0 (grupo virtual 'Sem Diretoria')
    nome:    u.nome || 'Sem Diretoria',
    sigla:   u.sigla ?? null,
    ico:     u.sigla ? ['🏥','💉','📋','🔬','🏃','🛡️'][i % 6] : '🏥',
    cor:     CORES[i % CORES.length],
    setores: (u.setores ?? []).map(s => ({
      id:           s.id,
      ico:          icones[(s.id ?? 0) % icones.length],
      nome:         s.nome,
      sigla:        s.sigla,
      responsavel:  s.responsavel ?? '—',
      unidade_id:   s.unidade_id ?? null,
      funcionarios: s.funcionarios ?? [],
    })),
  }))
}

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/organograma')
    if (!data.fallback && data.unidades?.length) {
      estrutura.value  = mapEstrutura(data.unidades)
      unidadesFlat.value = data.unidades_flat ?? []
    } else {
      estrutura.value  = mockEstrutura
    }
  } catch {
    estrutura.value = mockEstrutura
  } finally {
    // TASK-ORG-01: iniciar todas as diretorias colapsadas por padrão
    estrutura.value.forEach(dir => colapsados.add(dir.id))
    setTimeout(() => { loaded.value = true }, 80)
  }
})


// ── Computed ──────────────────────────────────────────────────
const toggleDir       = (id) => { colapsados.has(id) ? colapsados.delete(id) : colapsados.add(id) }
const selecionarSetor = (s, dir) => { setorAberto.value = { ...s, cor: dir?.cor } }

const filteredSetores = (dir) => {
  if (!busca.value) return dir.setores
  const t = busca.value.toLowerCase()
  return dir.setores.filter(s => s.nome.toLowerCase().includes(t) || (s.responsavel||'').toLowerCase().includes(t) || s.funcionarios.some(f => f.nome.toLowerCase().includes(t)))
}

const pessoasDaDir      = (dir) => dir.setores.flatMap(s => s.funcionarios.map((f, i) => ({ ...f, setor: s.nome, _key: `${s.id}-${i}` })))
const totalFuncDir      = (dir) => dir.setores.reduce((a, s) => a + s.funcionarios.length, 0)
const totalFuncionarios = computed(() => estrutura.value.reduce((a, d) => a + totalFuncDir(d), 0))
const totalSetores      = computed(() => estrutura.value.reduce((a, d) => a + d.setores.length, 0))
const maxFuncPorSetor   = computed(() => Math.max(1, ...estrutura.value.flatMap(d => d.setores.map(s => s.funcionarios.length))))

const avatarHue = (id) => ((typeof id === 'number' ? id : String(id).length * 13) * 137) % 360
const iniciais  = (n) => { const w = (n||'').trim().split(' ').filter(Boolean); return w.length >= 2 ? (w[0][0]+w[w.length-1][0]).toUpperCase() : (n||'?').substring(0,2).toUpperCase() }

// ── Toast ─────────────────────────────────────────────────────
const showToast = (msg) => { toast.value = { visible: true, msg }; setTimeout(() => toast.value.visible = false, 3500) }

// ── CRUD Setor ────────────────────────────────────────────────
const abrirModalNovo = () => {
  editandoId.value = null; erroModal.value = ''
  Object.assign(formSetor, { nome: '', sigla: '', unidade_id: null, ico: '🏥' })
  modalAberto.value = true
}
const abrirModalNovoNaDir = (dir) => {
  editandoId.value = null; erroModal.value = ''
  Object.assign(formSetor, { nome: '', sigla: '', unidade_id: dir.id, ico: '🏥' })
  modalAberto.value = true
}
const abrirModalEditar = (s) => {
  editandoId.value = s.id; erroModal.value = ''
  Object.assign(formSetor, { nome: s.nome, sigla: s.sigla ?? '', unidade_id: s.unidade_id ?? null, ico: s.ico ?? '🏥' })
  modalAberto.value = true
}
const confirmarExcluir = (s) => { confirmExcluir.value = s }

const salvarSetor = async () => {
  if (!formSetor.nome.trim()) { erroModal.value = 'Nome é obrigatório.'; return }
  salvando.value = true; erroModal.value = ''
  try {
    if (editandoId.value) {
      await api.put(`/api/v3/organograma/setor/${editandoId.value}`, { ...formSetor })

      // Encontrar em qual diretoria o setor está atualmente
      let setorSnapshot = null
      let dirAtualIdx = -1
      estrutura.value.forEach((dir, di) => {
        const idx = dir.setores.findIndex(s => s.id === editandoId.value)
        if (idx !== -1) { setorSnapshot = { ...dir.setores[idx] }; dirAtualIdx = di }
      })

      const novaUnidadeId = formSetor.unidade_id ?? 0
      const velhaUnidadeId = setorSnapshot?.unidade_id ?? 0
      const setorAtualizado = { ...setorSnapshot, nome: formSetor.nome, sigla: formSetor.sigla, ico: formSetor.ico, unidade_id: novaUnidadeId }

      if (dirAtualIdx !== -1 && novaUnidadeId !== velhaUnidadeId) {
        // Mudou de diretoria: remover da antiga
        estrutura.value[dirAtualIdx].setores = estrutura.value[dirAtualIdx].setores.filter(s => s.id !== editandoId.value)
        // Inserir na nova diretoria
        const novaDirIdx = estrutura.value.findIndex(d => {
          if (novaUnidadeId === 0 || novaUnidadeId === null) return !d.id || d.id === 0
          return d.id === novaUnidadeId
        })
        if (novaDirIdx !== -1) {
          estrutura.value[novaDirIdx].setores.push(setorAtualizado)
        } else {
          // Criar grupo 'Sem Diretoria' caso não exista
          estrutura.value.push({ id: 0, nome: 'Sem Diretoria', ico: '🏥', cor: '#64748b', setores: [setorAtualizado] })
        }
        // Limpar grupos virtuais vazios
        estrutura.value = estrutura.value.filter(d => d.setores.length > 0 || (d.id && d.id !== 0))
      } else if (dirAtualIdx !== -1) {
        // Mesma diretoria: apenas atualizar campos
        const idx = estrutura.value[dirAtualIdx].setores.findIndex(s => s.id === editandoId.value)
        if (idx !== -1) estrutura.value[dirAtualIdx].setores[idx] = setorAtualizado
      }

      showToast(`Setor "${formSetor.nome}" atualizado!`)
    } else {
      const { data } = await api.post('/api/v3/organograma/setor', { ...formSetor })
      const novoSetor = { id: data.id ?? Date.now(), nome: formSetor.nome, sigla: formSetor.sigla, ico: formSetor.ico, responsavel: '—', unidade_id: formSetor.unidade_id, funcionarios: [] }
      const dirIdx = estrutura.value.findIndex(d => d.id === formSetor.unidade_id)
      if (dirIdx !== -1) { estrutura.value[dirIdx].setores.push(novoSetor) }
      else { estrutura.value.push({ id: 0, nome: 'Sem Diretoria', ico: '🏥', cor: '#64748b', setores: [novoSetor] }) }
      showToast(`Setor "${formSetor.nome}" criado!`)
    }
    modalAberto.value = false
  } catch (e) {
    erroModal.value = e?.response?.data?.error ?? 'Erro ao salvar. Tente novamente.'
  } finally {
    salvando.value = false
  }
}

const excluirSetor = async () => {
  if (!confirmExcluir.value) return
  excluindo.value = true
  const id = confirmExcluir.value.id
  const nome = confirmExcluir.value.nome
  try {
    await api.delete(`/api/v3/organograma/setor/${id}`)
    estrutura.value.forEach(dir => { dir.setores = dir.setores.filter(s => s.id !== id) })
    showToast(`🗑️ Setor "${nome}" removido.`)
  } catch {
    showToast(`🗑️ Setor "${nome}" removido.`)
    estrutura.value.forEach(dir => { dir.setores = dir.setores.filter(s => s.id !== id) })
  } finally {
    excluindo.value = false
    confirmExcluir.value = null
  }
}

// ── CRUD Diretoria ────────────────────────────────────────────
const abrirModalNovaDiretoria = () => {
  editandoDiretoriaId.value = null; erroDirModal.value = ''
  Object.assign(formDiretoria, { nome: '', sigla: '', ico: '🏢' })
  modalDiretoriaAberto.value = true
}

const abrirModalEditarDiretoria = (dir) => {
  editandoDiretoriaId.value = dir.id; erroDirModal.value = ''
  Object.assign(formDiretoria, { nome: dir.nome, sigla: dir.sigla ?? '', ico: dir.ico ?? '🏢' })
  modalDiretoriaAberto.value = true
}

const confirmarExcluirDiretoria = (dir) => { confirmExcluirDiretoria.value = dir }

const salvarDiretoria = async () => {
  if (!formDiretoria.nome.trim()) { erroDirModal.value = 'Nome é obrigatório.'; return }
  salvandoDir.value = true; erroDirModal.value = ''
  try {
    if (editandoDiretoriaId.value) {
      await api.put(`/api/v3/organograma/diretoria/${editandoDiretoriaId.value}`, {
        nome: formDiretoria.nome,
        sigla: formDiretoria.sigla,
      })
      // Atualizar localmente
      const dirIdx = estrutura.value.findIndex(d => d.id === editandoDiretoriaId.value)
      if (dirIdx !== -1) {
        estrutura.value[dirIdx].nome  = formDiretoria.nome
        estrutura.value[dirIdx].sigla = formDiretoria.sigla
        estrutura.value[dirIdx].ico   = formDiretoria.ico
      }
      // Atualizar também unidades_flat
      const flatIdx = unidadesFlat.value.findIndex(u => u.id === editandoDiretoriaId.value)
      if (flatIdx !== -1) {
        unidadesFlat.value[flatIdx].nome  = formDiretoria.nome
        unidadesFlat.value[flatIdx].sigla = formDiretoria.sigla
      }
      showToast(`✅ Diretoria "${formDiretoria.nome}" atualizada!`)
    } else {
      const { data } = await api.post('/api/v3/organograma/diretoria', {
        nome: formDiretoria.nome,
        sigla: formDiretoria.sigla,
      })
      const novaDiretoria = {
        id:      data.id ?? Date.now(),
        nome:    formDiretoria.nome,
        sigla:   formDiretoria.sigla,
        ico:     formDiretoria.ico,
        cor:     CORES[estrutura.value.length % CORES.length],
        setores: [],
      }
      estrutura.value.push(novaDiretoria)
      // Adicionar ao unidades_flat para aparecer no select do modal de setor
      unidadesFlat.value.push({ id: novaDiretoria.id, nome: novaDiretoria.nome, sigla: novaDiretoria.sigla })
      showToast(`✅ Diretoria "${formDiretoria.nome}" criada!`)
    }
    modalDiretoriaAberto.value = false
  } catch (e) {
    erroDirModal.value = e?.response?.data?.error ?? 'Erro ao salvar. Tente novamente.'
  } finally {
    salvandoDir.value = false
  }
}

// ── Hospital ─────────────────────────────────────────────────
const abrirModalEditarHospital = () => {
  formHospital.nome = nomeHospital.value
  erroHospitalModal.value = ''
  modalHospitalAberto.value = true
}
const salvarHospital = () => {
  if (!formHospital.nome.trim()) { erroHospitalModal.value = 'Nome é obrigatório.'; return }
  nomeHospital.value = formHospital.nome.trim()
  localStorage.setItem('sisgep_hospital_nome', nomeHospital.value)
  showToast(`Nome atualizado para "${nomeHospital.value}"!`)
  modalHospitalAberto.value = false
}

const excluirDiretoria = async () => {
  if (!confirmExcluirDiretoria.value) return
  excluindoDir.value = true
  const id   = confirmExcluirDiretoria.value.id
  const nome = confirmExcluirDiretoria.value.nome
  try {
    await api.delete(`/api/v3/organograma/diretoria/${id}`)
    estrutura.value = estrutura.value.filter(d => d.id !== id)
    unidadesFlat.value = unidadesFlat.value.filter(u => u.id !== id)
    showToast(`🗑️ Diretoria "${nome}" removida.`)
  } catch {
    showToast('❌ Erro ao remover diretoria.')
  } finally {
    excluindoDir.value = false
    confirmExcluirDiretoria.value = null
  }
}
</script>

<style scoped>
.org-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e2a1e 55%, #1a244a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #6366f1; right: -40px; top: -60px; }
.hs2 { width: 180px; height: 180px; background: #0d9488; right: 280px; bottom: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #818cf8; margin-bottom: 5px; }
.hero-title { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.search-wrap { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 9px 14px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { border: none; background: transparent; color: #fff; font-size: 13px; font-family: inherit; outline: none; width: 180px; }
.s-input::placeholder { color: #475569; }
.view-toggle { display: flex; background: rgba(255,255,255,0.07); border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.12); }
.view-toggle button { padding: 8px 12px; border: none; background: none; font-size: 16px; cursor: pointer; transition: background 0.15s; }
.view-toggle button.active { background: rgba(255,255,255,0.15); }
.btn-novo { padding: 9px 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.btn-novo:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }

/* TOGGLE DE PERSPECTIVA */
.persp-toggle { display: flex; background: rgba(255,255,255,0.12); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); }
.persp-toggle button { padding: 8px 14px; border: none; background: none; color: rgba(255,255,255,0.65); font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; white-space: nowrap; font-family: inherit; }
.persp-toggle button.active { background: rgba(255,255,255,0.2); color: #fff; }
.persp-toggle button:hover:not(.active) { background: rgba(255,255,255,0.1); color: #fff; }

/* VISÃO FUNCIONÁRIO */
.func-view { display: flex; flex-direction: column; gap: 20px; animation: fadeIn 0.4s; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.meu-setor-hero { background: linear-gradient(135deg, #0f172a, #1e3a5f); border-radius: 20px; padding: 24px 28px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.msh-avatar { width: 64px; height: 64px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 900; color: #fff; flex-shrink: 0; }
.msh-info { flex: 1; min-width: 200px; }
.msh-nome { display: block; font-size: 20px; font-weight: 900; color: #fff; }
.msh-cargo { display: block; font-size: 13px; color: #94a3b8; margin: 3px 0 10px; }
.msh-trilha { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.msh-hosp { font-size: 12px; color: #64748b; font-weight: 600; }
.msh-sep { color: #334155; font-size: 14px; }
.msh-dir { font-size: 12px; font-weight: 700; }
.msh-setor { font-size: 12px; font-weight: 700; color: #e2e8f0; }
.msh-badge { padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; white-space: nowrap; }
.func-section { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.func-section-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.fsh-ico { font-size: 18px; }
.func-section-hdr h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; flex: 1; }
.fsh-count { font-size: 12px; font-weight: 700; color: #94a3b8; }
.equipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.equipe-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; border: 1.5px solid #f1f5f9; background: #fafafa; transition: all 0.15s; position: relative; }
.equipe-card.eu { border-color: var(--ec); background: color-mix(in srgb, var(--ec) 5%, #fff); }
.equipe-card:hover { box-shadow: 0 4px 14px -4px rgba(0,0,0,0.1); transform: translateY(-1px); }
.eq-avatar { width: 40px; height: 40px; border-radius: 12px; background: hsl(var(--eah) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900; color: #fff; flex-shrink: 0; }
.eq-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.eq-cargo { display: block; font-size: 11px; color: #64748b; margin-top: 2px; }
.eq-eu { position: absolute; top: 8px; right: 10px; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; background: var(--ec); color: #fff; }
.eq-vazio { font-size: 13px; color: #94a3b8; text-align: center; padding: 20px 0; width: 100%; }
.readonly-dir { pointer-events: auto; }
.meu-dir { box-shadow: 0 0 0 2px var(--dc) !important; }
.readonly-setor .sc-actions { display: none !important; }
.meu-setor-hl { box-shadow: 0 0 0 2.5px var(--sc) !important; background: color-mix(in srgb, var(--sc) 6%, #fff) !important; }
.meu-label { font-size: 10px; font-weight: 800; color: #6366f1; background: #eef2ff; padding: 2px 8px; border-radius: 20px; border: 1px solid #c7d2fe; white-space: nowrap; }

/* ÁRVORE */
.tree-wrap { opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.tree-wrap.loaded { opacity: 1; transform: none; }
.tree-container { display: flex; flex-direction: column; align-items: center; gap: 0; }
.tree-level { display: flex; justify-content: center; width: 100%; }
.tree-root-card { background: #0f172a; border-radius: 18px; padding: 16px 28px; display: flex; align-items: center; gap: 14px; border: 2px solid #334155; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
.root-actions { display: flex; align-items: center; gap: 8px; margin-left: 18px; }
.btn-edit-hosp { padding: 7px 14px; border-radius: 10px; border: 1.5px solid #475569; background: rgba(255,255,255,0.07); color: #94a3b8; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.btn-edit-hosp:hover { background: rgba(255,255,255,0.14); color: #fff; border-color: #64748b; }
.root-ico { font-size: 32px; }
.root-nome { display: block; font-size: 18px; font-weight: 900; color: #fff; }
.root-sub { display: block; font-size: 12px; color: #64748b; }
.btn-nova-dir { margin-left: 18px; padding: 8px 16px; border-radius: 11px; border: 1.5px solid #334155; background: rgba(99,102,241,0.15); color: #818cf8; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.btn-nova-dir:hover { background: rgba(99,102,241,0.3); border-color: #6366f1; color: #a5b4fc; transform: translateY(-1px); }
.tree-connector { width: 2px; height: 32px; background: linear-gradient(to bottom, #334155, #475569); margin: 0 auto; }
.root-conn { height: 24px; }
.dir-level { display: flex; gap: 20px; align-items: flex-start; width: 100%; flex-wrap: wrap; }
.dir-branch { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 10px; }
.dir-card { display: flex; align-items: center; gap: 10px; background: #fff; border: 1.5px solid color-mix(in srgb, var(--dc) 20%, #e2e8f0); border-radius: 16px; padding: 13px 16px; cursor: pointer; transition: all 0.18s; box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: relative; }
.dir-card:hover { box-shadow: 0 6px 20px -4px color-mix(in srgb, var(--dc) 20%, transparent); transform: translateY(-1px); }
.dir-card:hover .dir-actions { opacity: 1; }
.dir-ico { font-size: 22px; }
.dir-nome { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.dir-count { display: block; font-size: 11px; color: #94a3b8; font-weight: 600; }
.dir-actions { display: flex; gap: 3px; opacity: 0; transition: opacity 0.15s; margin-left: auto; }
.dir-caret { font-size: 10px; color: #94a3b8; }
.setores-grid { display: flex; flex-direction: column; gap: 8px; padding-left: 16px; border-left: 2px solid #f1f5f9; margin-left: 20px; }

/* setor-card com botões CRUD */
.setor-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px 14px; cursor: pointer; transition: all 0.15s; position: relative; }
.setor-card:hover { border-color: color-mix(in srgb, var(--sc) 30%, transparent); box-shadow: 0 4px 14px -4px color-mix(in srgb, var(--sc) 15%, transparent); transform: translateX(3px); }
.setor-card:hover .sc-actions { opacity: 1; }
.setor-add { display: flex; align-items: center; justify-content: center; border: 1.5px dashed #e2e8f0; border-radius: 14px; padding: 10px; color: #94a3b8; font-size: 12px; font-weight: 700; transition: all 0.15s; }
.setor-add:hover { border-color: #6366f1; color: #6366f1; background: #f0f0ff; }
.sc-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.sc-ico { font-size: 16px; }
.sc-nome { font-size: 13px; font-weight: 700; color: #1e293b; flex: 1; }
.sc-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.15s; }
.sca-btn { padding: 4px 10px; border-radius: 7px; border: none; background: #f8fafc; font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.13s; white-space: nowrap; height: auto; width: auto; }
.sca-edit:hover { background: #eff6ff; color: #3b82f6; }
.sca-del:hover { background: #fef2f2; color: #ef4444; }
.sc-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.sc-count { font-size: 11px; font-weight: 700; color: #475569; }
.sc-resp { font-size: 10px; color: #94a3b8; }
.sc-bar-wrap { height: 4px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.sc-bar { height: 100%; border-radius: 99px; transition: width 0.8s cubic-bezier(0.22,1,0.36,1); opacity: 0.7; }

/* CARDS */
.cards-wrap { display: flex; flex-direction: column; gap: 24px; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s; }
.cards-wrap.loaded { opacity: 1; transform: none; }
.dir-section-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding-left: 12px; border-left: 3px solid; }
.dsh-ico { font-size: 20px; }
.dir-section-hdr h2 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.dsh-line { flex: 1; height: 1px; background: #f1f5f9; }
.dsh-count { font-size: 12px; font-weight: 700; color: #94a3b8; white-space: nowrap; }
.pessoas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.pessoa-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px 14px; transition: all 0.15s; border-top: 2px solid color-mix(in srgb, var(--pc) 25%, transparent); }
.pessoa-card:hover { box-shadow: 0 4px 14px -4px rgba(0,0,0,0.1); transform: translateY(-1px); }
.pc-avatar { width: 36px; height: 36px; border-radius: 10px; background: hsl(var(--ph) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: #fff; flex-shrink: 0; }
.pc-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.pc-cargo { display: block; font-size: 11px; color: #64748b; margin-top: 1px; }
.pc-setor { display: block; font-size: 10px; font-weight: 700; margin-top: 3px; }

/* DRAWER */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); z-index: 100; display: flex; justify-content: flex-end; }
.drawer { background: #fff; width: 360px; height: 100%; overflow-y: auto; box-shadow: -16px 0 48px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
.drawer-hdr { display: flex; align-items: flex-start; gap: 12px; padding: 24px 22px 18px; border-bottom: 1px solid #f1f5f9; }
.drawer-ico { font-size: 28px; }
.drawer-title { font-size: 18px; font-weight: 900; color: #1e293b; margin: 0 0 3px; }
.drawer-sub { font-size: 12px; color: #94a3b8; margin: 0; }
.drawer-hdr-actions { margin-left: auto; display: flex; gap: 6px; align-items: center; }
.drawer-close { border: none; background: #f1f5f9; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; font-size: 13px; color: #64748b; }
.drawer-body { padding: 18px 22px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.dr-section-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 10px 0 8px; }
.dr-responsavel, .dr-pessoa { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; margin-bottom: 6px; }
.dr-avatar { width: 38px; height: 38px; border-radius: 10px; background: hsl(var(--dh) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900; color: #fff; flex-shrink: 0; }
.dr-nome { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.dr-cargo { display: block; font-size: 11px; color: #64748b; margin-top: 1px; }
.dr-pessoas { display: flex; flex-direction: column; gap: 6px; }
.dr-vazio { font-size: 13px; color: #94a3b8; text-align: center; padding: 20px 0; }
.drawer-enter-active, .drawer-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer, .drawer-leave-to .drawer { transform: translateX(100%); }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: #fff; border-radius: 22px; width: 100%; max-width: 480px; box-shadow: 0 32px 64px rgba(0,0,0,0.2); animation: mPop 0.28s cubic-bezier(0.22,1,0.36,1); }
.modal-sm { max-width: 380px; }
@keyframes mPop { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: none; } }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-hdr h3 { font-size: 16px; font-weight: 900; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 9px; width: 32px; height: 32px; cursor: pointer; font-size: 13px; color: #475569; }
.modal-body { padding: 18px 24px; display: flex; flex-direction: column; gap: 14px; }
.mf-group { display: flex; flex-direction: column; gap: 5px; }
.mf-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.req { color: #ef4444; }
.mf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.mf-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: #1e293b; background: #f8fafc; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.mf-input:focus { border-color: #6366f1; }
.ico-grid { display: flex; flex-wrap: wrap; gap: 4px; }
.ico-btn { border: 1.5px solid #e2e8f0; border-radius: 8px; background: #f8fafc; font-size: 16px; width: 34px; height: 34px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.13s; }
.ico-btn:hover { border-color: #6366f1; }
.ico-sel { border-color: #6366f1; background: #eef2ff; }
.modal-err { font-size: 12px; color: #ef4444; font-weight: 600; margin: 0; }
.confirm-txt { font-size: 14px; color: #1e293b; line-height: 1.6; margin: 0; }
.confirm-sub { font-size: 12px; color: #94a3b8; }
.modal-footer { display: flex; gap: 8px; justify-content: flex-end; padding: 0 24px 22px; }
.modal-cancel { padding: 10px 18px; border-radius: 11px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; }
.modal-save { display: flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 11px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.modal-save:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-del { display: flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 11px; border: none; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }
.modal-del:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spin { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.modal-enter-active, .modal-leave-active { transition: all 0.25s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* TOAST */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 300; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }

@media (max-width: 700px) { .dir-level { flex-direction: column; } .mf-row { grid-template-columns: 1fr; } }

/* ???????? TASK-ORG-02/03 � ACCORDION ORGANOGRAMA ???????? */
.acc-container { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; padding-bottom: 40px; }

.acc-root { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 18px; padding: 18px 22px; box-shadow: 0 2px 12px rgba(99,102,241,.08); border: 1.5px solid #e0e7fd; margin-bottom: 4px; }
.acc-root .root-ico { font-size: 28px; }
.acc-root .root-nome { font-size: 16px; font-weight: 800; color: #1e293b; display: block; }
.acc-root .root-sub  { font-size: 12px; color: #64748b; }

.acc-section { border-radius: 16px; overflow: hidden; box-shadow: 0 1px 8px rgba(0,0,0,.06); }

.acc-hdr { display: flex; align-items: center; gap: 14px; padding: 14px 20px; background: #fff; cursor: pointer; border-left: 4px solid var(--dc, #6366f1); border-bottom: 1.5px solid #f1f5f9; transition: background .15s; user-select: none; }
.acc-hdr:hover       { background: #f8faff; }
.meu-dir-acc         { border-left-width: 6px; background: #f5f3ff; }
.acc-hdr-ico         { font-size: 22px; flex-shrink: 0; }
.acc-hdr-info        { flex: 1; min-width: 0; }
.acc-hdr-nome        { display: block; font-size: 14px; font-weight: 700; color: #1e293b; }
.acc-hdr-count       { font-size: 11px; color: #64748b; }
.acc-hdr-actions     { display: flex; gap: 6px; }
.acc-caret           { font-size: 20px; color: #94a3b8; font-weight: 700; flex-shrink: 0; transition: transform .2s; }

.acc-body { background: #f8faff; padding: 16px 20px 20px; }

/* TASK-ORG-03: grid de setores compactos */
.acc-setores-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
@media (max-width: 700px) { .acc-setores-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 430px) { .acc-setores-grid { grid-template-columns: 1fr; } }

.acc-setor-card { position: relative; border-radius: 12px; background: #fff; border: 1.5px solid var(--sc, #6366f1)22; box-shadow: 0 1px 4px rgba(0,0,0,.06); cursor: pointer; overflow: hidden; transition: box-shadow .15s, transform .15s; }
.acc-setor-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); transform: translateY(-2px); }
.acc-setor-card.meu-setor-hl { border-color: var(--sc); background: color-mix(in srgb, var(--sc) 6%, white); }
.acc-setor-card.acc-add { display: flex; align-items: center; justify-content: center; min-height: 60px; border-style: dashed; color: #94a3b8; font-size: 13px; font-weight: 600; }
.acc-setor-card.acc-add:hover { color: #6366f1; border-color: #6366f1; background: #f5f3ff; }

/* Repouso � compact */
.asc-compact { display: flex; align-items: center; gap: 8px; padding: 12px 14px 8px; }
.asc-ico  { font-size: 18px; flex-shrink: 0; }
.asc-nome { font-size: 12px; font-weight: 700; color: #1e293b; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.asc-cnt  { font-size: 11px; color: #64748b; white-space: nowrap; }
.meu-label { color: var(--sc, #6366f1); font-size: 13px; }

/* Hover � detalhe extra */
.asc-hover { padding: 0 14px 10px; display: none; }
.acc-setor-card:hover .asc-hover { display: block; }
.asc-resp { font-size: 11px; color: #64748b; display: block; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.asc-bar-wrap { height: 3px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
.asc-bar { height: 100%; border-radius: 4px; transition: width .3s; }
.asc-actions { display: flex; gap: 6px; }

/* Transi��o do corpo do accordion */
.acc-body-enter-active, .acc-body-leave-active { transition: all .2s ease; overflow: hidden; }
.acc-body-enter-from, .acc-body-leave-to { opacity: 0; max-height: 0; padding-top: 0; padding-bottom: 0; }
</style>
