<template>
  <div class="gd-page">
    <!-- HERO -->
    <div class="hero" :class="{ loaded }">
      <div class="hero-shapes"><div class="hs hs1"></div></div>
      <div class="hero-inner">
        <div>
          <span class="hero-eyebrow">⚙️ Gestão · RH</span>
          <h1 class="hero-title">Gestão de Declarações</h1>
          <p class="hero-sub">Analise solicitações e gerencie modelos de documentos</p>
        </div>
        <div class="hero-stats">
          <div class="hstat ha"><span class="hv">{{ contagem.pendente }}</span><span class="hl">Pendentes</span></div>
          <div class="hstat hb"><span class="hv">{{ contagem.andamento }}</span><span class="hl">Em Andamento</span></div>
          <div class="hstat hc"><span class="hv">{{ contagem.pronto }}</span><span class="hl">Resolvidos</span></div>
          <div class="hstat hd"><span class="hv">{{ contagem.indeferido }}</span><span class="hl">Indeferidos</span></div>
        </div>
      </div>
    </div>

    <!-- ABAS PRINCIPAIS -->
    <div class="main-tabs" :class="{ loaded }">
      <button class="mtab" :class="{ active: abaAtiva === 'solicitacoes' }" @click="abaAtiva = 'solicitacoes'">
        📋 Solicitações
        <span class="mtab-count" v-if="contagem.pendente + contagem.andamento > 0">{{ contagem.pendente + contagem.andamento }}</span>
      </button>
      <button class="mtab" :class="{ active: abaAtiva === 'modelos' }" @click="abaAtiva = 'modelos'; carregarModelos()">
        📄 Modelos de Documento
      </button>
    </div>

    <!-- ════════════════════ ABA: SOLICITAÇÕES ════════════════════ -->
    <template v-if="abaAtiva === 'solicitacoes'">
      <div class="toolbar" :class="{ loaded }">
        <div class="filtro-tabs">
          <button v-for="tab in tabs" :key="tab.key"
            class="ftab" :class="{ active: filtroAtivo === tab.key }"
            @click="filtroAtivo = tab.key; paginaAtual = 1">
            {{ tab.label }}
            <span class="ftab-count" v-if="contagem[tab.key] > 0">{{ contagem[tab.key] }}</span>
          </button>
        </div>
        <div class="search-wrap">
          <svg viewBox="0 0 24 24" fill="none" class="s-ico"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input v-model="busca" class="s-input" placeholder="Buscar servidor ou documento..." />
        </div>
      </div>

      <div class="lista-section" :class="{ loaded }">
        <div v-if="itensPaginados.length === 0" class="empty-state">
          <span>📭</span><p>Nenhuma solicitação encontrada.</p>
        </div>
        <div v-for="(item, i) in itensPaginados" :key="item.id" class="card" :style="{ '--ci': i }">
          <div class="card-ico" :style="{ background: corStatus(item.status) + '15' }">{{ icoStatus(item.status) }}</div>
          <div class="card-info">
            <span class="card-nome">{{ item.nome }}</span>
            <span class="card-servidor">👤 {{ item.servidor }} · Mat. {{ item.matricula }}</span>
            <span class="card-data">📅 Solicitado em {{ formatDate(item.data) }}</span>
            <span v-if="item.obs" class="card-obs">💬 {{ item.obs }}</span>
          </div>
          <div class="card-meta">
            <span class="pi-badge" :class="badgeClass(item.status)">{{ labelStatus(item.status) }}</span>
            <span class="card-proto">{{ item.protocolo }}</span>
          </div>
          <div class="card-acoes" v-if="item.status === 'pendente' || item.status === 'andamento'">
            <button class="btn-aprovar" @click="abrirModal(item, 'pronto')" :disabled="processando === item.id">
              <span v-if="processando === item.id" class="spin"></span>
              <template v-else>✅ Aprovar</template>
            </button>
            <button class="btn-indeferir" @click="abrirModal(item, 'indeferido')" :disabled="processando === item.id">❌ Indeferir</button>
          </div>
          <div class="card-acoes" v-else>
            <span class="badge-resolvido" :class="badgeClass(item.status)">{{ labelStatus(item.status) }}</span>
          </div>
        </div>
        <div class="paginacao" v-if="totalPaginas > 1">
          <button class="pg-btn" :disabled="paginaAtual === 1" @click="paginaAtual--">‹</button>
          <span class="pg-info">{{ paginaAtual }} / {{ totalPaginas }}</span>
          <button class="pg-btn" :disabled="paginaAtual === totalPaginas" @click="paginaAtual++">›</button>
        </div>
      </div>
    </template>

    <!-- ════════════════════ ABA: MODELOS ════════════════════ -->
    <template v-else-if="abaAtiva === 'modelos'">
      <div class="modelos-layout" :class="{ loaded }">

        <!-- LISTA DE TIPOS -->
        <div class="tipos-list">
          <h3 class="tipos-title">Tipos de Declaração</h3>
          <div v-for="m in modelos" :key="m.tipo"
            class="tipo-item" :class="{ active: modeloAtivo?.tipo === m.tipo }"
            @click="selecionarModelo(m)">
            <div class="tipo-ico">📄</div>
            <div class="tipo-info">
              <span class="tipo-nome">{{ m.tipo }}</span>
              <span class="tipo-status" :class="m.temModelo ? 'ts-custom' : 'ts-default'">
                {{ m.temModelo ? '✅ Modelo personalizado' : '🔧 Padrão do sistema' }}
              </span>
              <span v-if="m.atualizadoEm" class="tipo-data">Editado em {{ formatDate(m.atualizadoEm) }}</span>
            </div>
          </div>
          <div v-if="carregandoModelos" class="tipos-loading">Carregando…</div>
        </div>

        <!-- EDITOR -->
        <div class="editor-panel" v-if="modeloAtivo">
          <div class="ep-header">
            <div>
              <h3 class="ep-title">{{ modeloAtivo.tipo }}</h3>
              <p class="ep-sub">Edite o HTML do modelo ou faça upload de um arquivo .html</p>
            </div>
            <div class="ep-actions">
              <label class="btn-upload" title="Fazer upload de arquivo HTML">
                📂 Upload .html
                <input type="file" accept=".html,.htm" @change="onUpload" style="display:none" />
              </label>
              <button class="btn-preview" @click="abrirPreview" title="Pré-visualizar com dados de exemplo">👁️ Preview</button>
              <button class="btn-reset" @click="resetarModelo" v-if="modeloAtivo.temModelo" title="Remover modelo e usar padrão do sistema">🗑️ Remover</button>
            </div>
          </div>

          <!-- BARRA DE FERRAMENTAS -->
          <div class="wysiwyg-toolbar">
            <div class="tb-group">
              <select class="tb-font" @change="exec('fontName', $event.target.value)" title="Fonte">
                <option value="Arial">Arial</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Courier New">Courier New</option>
                <option value="Georgia">Georgia</option>
                <option value="Verdana">Verdana</option>
              </select>
              <select class="tb-size" @change="exec('fontSize', $event.target.value)" title="Tamanho">
                <option value="2">10</option>
                <option value="3" selected>12</option>
                <option value="4">14</option>
                <option value="5">18</option>
                <option value="6">24</option>
                <option value="7">36</option>
              </select>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
              <button class="tb-btn" @mousedown.prevent="exec('bold')" title="Negrito"><b>N</b></button>
              <button class="tb-btn" @mousedown.prevent="exec('italic')" title="Itálico"><i>I</i></button>
              <button class="tb-btn" @mousedown.prevent="exec('underline')" title="Sublinhado"><u>S</u></button>
              <button class="tb-btn" @mousedown.prevent="exec('strikeThrough')" title="Tachado"><s>T</s></button>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
              <button class="tb-btn" @mousedown.prevent="exec('justifyLeft')" title="Alinhar à esquerda">☰═</button>
              <button class="tb-btn" @mousedown.prevent="exec('justifyCenter')" title="Centralizar">≡</button>
              <button class="tb-btn" @mousedown.prevent="exec('justifyRight')" title="Alinhar à direita">☶</button>
              <button class="tb-btn" @mousedown.prevent="exec('justifyFull')" title="Justificar">≡</button>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
              <button class="tb-btn" @mousedown.prevent="exec('insertUnorderedList')" title="Lista">&#9679;</button>
              <button class="tb-btn" @mousedown.prevent="exec('insertOrderedList')" title="Lista numerada">1.</button>
              <button class="tb-btn" @mousedown.prevent="exec('indent')" title="Aumentar recuo">⇥</button>
              <button class="tb-btn" @mousedown.prevent="exec('outdent')" title="Diminuir recuo">⇤</button>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
              <label class="tb-color" title="Cor do texto">
                🎨
                <input type="color" @input="exec('foreColor', $event.target.value)" style="width:0;height:0;opacity:0;position:absolute" />
              </label>
              <button class="tb-btn" @mousedown.prevent="exec('removeFormat')" title="Limpar formatação">&#10006;</button>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
              <label class="btn-upload" title="Upload de arquivo HTML">
                📂 Upload .html
                <input type="file" accept=".html,.htm" @change="onUpload" style="display:none" />
              </label>
            </div>
          </div>

          <!-- EDITOR CONTENTEDITABLE -->
          <div
            ref="editorRef"
            class="wysiwyg-body"
            contenteditable="true"
            @input="onEditorInput"
            @blur="onEditorInput"
            data-placeholder="Clique aqui e comece a digitar o modelo..."
          ></div>

          <div class="ep-footer">
            <div class="vars-panel">
              <span class="vars-label">Inserir variável:</span>
              <span v-for="v in variaveis" :key="v.tag" class="var-tag" @mousedown.prevent="inserirVar(v.tag)" :title="v.desc">{{ v.tag }}</span>
            </div>
            <div class="ep-footer-right">
              <button class="btn-preview" @click="abrirPreview">&#128065; Preview</button>
              <button class="btn-reset" v-if="modeloAtivo?.temModelo" @click="resetarModelo">🗑️ Remover</button>
              <button class="btn-salvar" @click="salvarModelo" :disabled="salvando">
                <span v-if="salvando" class="spin"></span>
                <template v-else>💾 Salvar Modelo</template>
              </button>
            </div>
          </div>
        </div>

        <div class="editor-empty" v-else>
          <span>👈</span>
          <p>Selecione um tipo de declaração para editar seu modelo</p>
        </div>
      </div>

      <!-- MODAL PREVIEW -->
      <teleport to="body">
        <transition name="overlay">
          <div v-if="preview.aberto" class="overlay" @click.self="preview.aberto = false">
            <div class="preview-modal">
              <div class="preview-header">
                <span>👁️ Preview — {{ modeloAtivo?.tipo }}</span>
                <button class="preview-close" @click="preview.aberto = false">✕</button>
              </div>
              <iframe ref="previewFrame" class="preview-frame" sandbox="allow-same-origin allow-scripts"></iframe>
            </div>
          </div>
        </transition>
      </teleport>
    </template>

    <!-- MODAL APROVAR/INDEFERIR -->
    <teleport to="body">
      <transition name="overlay">
        <div v-if="modal.aberto" class="overlay" @click.self="fecharModal">
          <div class="modal">
            <div class="modal-ico">{{ modal.acao === 'pronto' ? '✅' : '❌' }}</div>
            <h3 class="modal-title">{{ modal.acao === 'pronto' ? 'Aprovar Solicitação' : 'Indeferir Solicitação' }}</h3>
            <p class="modal-doc">{{ modal.item?.nome }}</p>
            <p class="modal-servidor">{{ modal.item?.servidor }}</p>
            <div class="obs-wrap">
              <label>Observação <span class="obs-hint">(opcional)</span></label>
              <textarea v-model="modal.obs" class="obs-input" rows="3" placeholder="Ex.: Documento emitido e disponível para retirada..."></textarea>
            </div>
            <div class="modal-btns">
              <button class="mbtn mbtn-cancel" @click="fecharModal">Cancelar</button>
              <button class="mbtn" :class="modal.acao === 'pronto' ? 'mbtn-aprovar' : 'mbtn-indeferir'"
                @click="confirmarAcao" :disabled="processando === modal.item?.id">
                <span v-if="processando === modal.item?.id" class="spin"></span>
                <template v-else>{{ modal.acao === 'pronto' ? '✅ Confirmar Aprovação' : '❌ Confirmar Indeferimento' }}</template>
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

    <transition name="toast">
      <div v-if="toast.visible" class="toast">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import api from '@/plugins/axios'

// ─── Estado geral ────────────────────────────────────────────────
const loaded      = ref(false)
const abaAtiva    = ref('solicitacoes')
const toast       = ref({ visible: false, msg: '' })
const processando = ref(null)

// ─── Solicitações ────────────────────────────────────────────────
const itens      = ref([])
const busca      = ref('')
const filtroAtivo = ref('todos')
const paginaAtual = ref(1)
const porPagina   = 8
const modal       = ref({ aberto: false, item: null, acao: '', obs: '' })

const tabs = [
  { key: 'todos',      label: 'Todos' },
  { key: 'pendente',   label: 'Pendentes' },
  { key: 'andamento',  label: 'Em Andamento' },
  { key: 'pronto',     label: 'Resolvidos' },
  { key: 'indeferido', label: 'Indeferidos' },
]

const contagem = computed(() => ({
  todos:      itens.value.length,
  pendente:   itens.value.filter(i => i.status === 'pendente').length,
  andamento:  itens.value.filter(i => i.status === 'andamento').length,
  pronto:     itens.value.filter(i => i.status === 'pronto').length,
  indeferido: itens.value.filter(i => i.status === 'indeferido').length,
}))

const itensFiltrados = computed(() => {
  let lista = filtroAtivo.value === 'todos' ? itens.value : itens.value.filter(i => i.status === filtroAtivo.value)
  if (busca.value) {
    const q = busca.value.toLowerCase()
    lista = lista.filter(i =>
      i.nome?.toLowerCase().includes(q) || i.servidor?.toLowerCase().includes(q) ||
      i.matricula?.toLowerCase().includes(q) || i.protocolo?.toLowerCase().includes(q))
  }
  return lista
})
const totalPaginas   = computed(() => Math.max(1, Math.ceil(itensFiltrados.value.length / porPagina)))
const itensPaginados = computed(() => {
  const ini = (paginaAtual.value - 1) * porPagina
  return itensFiltrados.value.slice(ini, ini + porPagina)
})

// ─── Modelos / Editor ───────────────────────────────────────────
const modelos           = ref([])
const modeloAtivo       = ref(null)
const htmlAtivo         = ref('')
const salvando          = ref(false)
const carregandoModelos = ref(false)
const editorRef         = ref(null)
const previewFrame      = ref(null)
const preview           = ref({ aberto: false, html: '' })

// execCommand wrapper para o WYSIWYG
const exec = (cmd, val = null) => {
  editorRef.value?.focus()
  document.execCommand(cmd, false, val)
  onEditorInput()
}

// Sincroniza innerHTML → htmlAtivo
const onEditorInput = () => {
  htmlAtivo.value = editorRef.value?.innerHTML ?? ''
}

// ─── Helpers de HTML ─────────────────────────────────────────────
// Extrai só o conteúdo interno do <body> para editar no WYSIWYG
const extractBodyContent = (html) => {
  if (!html || !html.trim()) return ''
  // Se for documento HTML completo, extrai o <body>
  const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
  if (bodyMatch) return bodyMatch[1].trim()
  // Caso contrário usa como está
  return html
}

// Monta documento HTML completo com CSS padrão a partir do body content
const buildFullHtml = (tipo, bodyHtml) => {
  return `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>${tipo}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;font-size:13px;color:#1e293b;margin:60px auto;max-width:720px;line-height:1.85;padding:0 20px}
  .topo{text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:18px;margin-bottom:30px}
  .topo h1{font-size:15px;color:#1e3a8a;font-weight:900;letter-spacing:.03em}
  .topo p{font-size:11px;color:#64748b;margin-top:4px}
  .titulo{text-align:center;font-size:14px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;margin:0 0 24px;border-bottom:1px solid #e2e8f0;padding-bottom:10px}
  .corpo p{margin-bottom:14px;text-align:justify}
  .tabela{margin:22px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}
  .tabela table{width:100%;border-collapse:collapse}
  .tabela tr:nth-child(even){background:#f8fafc}
  .tabela td{padding:7px 12px;font-size:12px;border-bottom:1px solid #f1f5f9}
  .tabela td:first-child{font-weight:700;color:#475569;width:42%}
  .assinatura{margin-top:64px;display:flex;justify-content:space-between;gap:40px}
  .ass-bloco{flex:1;text-align:center}
  .ass-linha{border-top:1px solid #1e293b;margin-bottom:6px}
  .ass-nome{font-size:12px;font-weight:700;color:#1e293b}
  .ass-cargo{font-size:11px;color:#64748b}
  .rodape{margin-top:36px;text-align:center;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px}

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
</style></head><body>
${bodyHtml}
</body></html>`
}

// Atualiza o editor apenas com body content (safe para contenteditable)
const setEditorHtml = (rawHtml) => {
  const bodyContent = extractBodyContent(rawHtml)
  if (editorRef.value) editorRef.value.innerHTML = bodyContent
  htmlAtivo.value = bodyContent
}

const variaveis = [
  { tag: '{{NOME}}',          desc: 'Nome completo do servidor' },
  { tag: '{{MATRICULA}}',     desc: 'Matrícula funcional' },
  { tag: '{{CPF}}',           desc: 'CPF formatado' },
  { tag: '{{CARGO}}',         desc: 'Cargo/função' },
  { tag: '{{SETOR}}',         desc: 'Setor/lotação' },
  { tag: '{{DATA_ADMISSAO}}', desc: 'Data de admissão' },
  { tag: '{{DATA_HOJE}}',     desc: 'Data atual' },
  { tag: '{{PROTOCOLO}}',     desc: 'Número do protocolo' },
  { tag: '{{TIPO}}',          desc: 'Tipo da declaração' },
  { tag: '{{ANO}}',           desc: 'Ano atual' },
]

// Dados de exemplo para preview
const dadosExemplo = {
  '{{NOME}}':          'João da Silva Santos',
  '{{MATRICULA}}':     '123456',
  '{{CPF}}':           '123.456.789-00',
  '{{CARGO}}':         'Analista Judiciário',
  '{{SETOR}}':         'Departamento de TI',
  '{{DATA_ADMISSAO}}': '01/03/2018',
  '{{DATA_HOJE}}':     new Date().toLocaleDateString('pt-BR'),
  '{{PROTOCOLO}}':     'REQ-' + new Date().getFullYear() + '-001',
  '{{TIPO}}':          'Declaração de Exemplo',
  '{{ANO}}':           String(new Date().getFullYear()),
}

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v3/rh/declaracoes')
    itens.value = data.itens ?? []
  } catch { itens.value = [] }
  finally { setTimeout(() => { loaded.value = true }, 80) }
})

const carregarModelos = async () => {
  carregandoModelos.value = true
  try {
    const { data } = await api.get('/api/v3/rh/modelos')
    modelos.value = data.modelos ?? []
  } catch { modelos.value = [] }
  finally { carregandoModelos.value = false }
}

const selecionarModelo = async (m) => {
  modeloAtivo.value = m
  htmlAtivo.value   = ''
  // Espera o editor montar no DOM (v-if depende de modeloAtivo)
  await nextTick()
  if (editorRef.value) editorRef.value.innerHTML = ''

  if (m.temModelo) {
    try {
      const { data } = await api.get('/api/v3/rh/modelos/' + encodeURIComponent(m.tipo))
      const body = extractBodyContent(data.html ?? '')
      htmlAtivo.value = body
      if (editorRef.value) editorRef.value.innerHTML = body
    } catch { htmlAtivo.value = ''; if (editorRef.value) editorRef.value.innerHTML = '' }
  } else {
    // Template inicial para novos modelos - editor nao fica bloqueado
    const templateInicial = '<div class="topo"><h1>PREFEITURA MUNICIPAL DE SAO LUIS</h1>'
      + '<p>Departamento de Gestao de Pessoas - Sistema GENTE v3</p></div>'
      + '<div class="titulo">' + m.tipo + '</div>'
      + '<div class="corpo">'
      + '<p>Declaramos, para os devidos fins de direito, que <strong>{{NOME}}</strong>,'
      + ' servidor(a) com matricula <strong>{{MATRICULA}}</strong>,'
      + ' portador(a) do CPF <strong>{{CPF}}</strong>,'
      + ' encontra-se regularmente vinculado(a) ao quadro de pessoal desta instituicao.</p>'
      + '<p>Por ser verdade, firmamos a presente declaracao.</p>'
      + '<p>Sao Luis, {{DATA_HOJE}}.</p>'
      + '</div>'
    htmlAtivo.value = templateInicial
    if (editorRef.value) editorRef.value.innerHTML = templateInicial
  }
}

const salvarModelo = async () => {
  if (!modeloAtivo.value || !htmlAtivo.value.trim()) {
    showToast('⚠️ O modelo não pode estar vazio.')
    return
  }
  salvando.value = true
  try {
    // Monta HTML completo para salvar no banco
    const htmlCompleto = buildFullHtml(modeloAtivo.value.tipo, htmlAtivo.value)
    const { data } = await api.post('/api/v3/rh/modelos', {
      tipo: modeloAtivo.value.tipo,
      html: htmlCompleto
    })
    // Atualiza estado local
    const idx = modelos.value.findIndex(m => m.tipo === modeloAtivo.value.tipo)
    if (idx !== -1) {
      modelos.value[idx].temModelo    = true
      modelos.value[idx].atualizadoEm = data.atualizadoEm
      modeloAtivo.value = { ...modelos.value[idx] }
    }
    showToast('✅ Modelo salvo com sucesso!')
  } catch { showToast('❌ Erro ao salvar o modelo.') }
  finally { salvando.value = false }
}

const resetarModelo = async () => {
  if (!confirm('Remover o modelo personalizado e usar o padrão do sistema?')) return
  try {
    await api.delete('/api/v3/rh/modelos/' + encodeURIComponent(modeloAtivo.value.tipo))
    const idx = modelos.value.findIndex(m => m.tipo === modeloAtivo.value.tipo)
    if (idx !== -1) {
      modelos.value[idx].temModelo    = false
      modelos.value[idx].atualizadoEm = null
      modeloAtivo.value = { ...modelos.value[idx] }
    }
    setEditorHtml('')
    showToast('🔧 Modelo removido — usando padrão do sistema.')
  } catch { showToast('❌ Erro ao remover o modelo.') }
}

const onUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => { setEditorHtml(ev.target.result) }
  reader.readAsText(file, 'UTF-8')
  e.target.value = ''
}

// Insere a variável na posição do cursor no editor
const inserirVar = (tag) => {
  editorRef.value?.focus()
  document.execCommand('insertText', false, tag)
  onEditorInput()
}

const abrirPreview = () => {
  const bodyComVars = htmlAtivo.value
  const bodyPreview = Object.entries(dadosExemplo).reduce(
    (h, [k, v]) => h.replaceAll(k, v), bodyComVars
  )
  const fullHtml = buildFullHtml(modeloAtivo.value?.tipo ?? 'Preview', bodyPreview)
  preview.value = { aberto: true, html: fullHtml }
}

// Injeta o HTML no iframe depois que a transition terminar de montar o DOM
watch(() => preview.value.aberto, (open) => {
  if (!open) return
  setTimeout(() => {
    const frame = previewFrame.value
    if (!frame) { console.warn('[preview] iframe ref nulo'); return }
    try {
      frame.contentDocument.open()
      frame.contentDocument.write(preview.value.html)
      frame.contentDocument.close()
    } catch (e) {
      // fallback: Blob URL
      const blob = new Blob([preview.value.html], { type: 'text/html' })
      frame.src = URL.createObjectURL(blob)
    }
  }, 120)
}, { flush: 'post' })


// ─── Ações de solicitação ────────────────────────────────────────
const abrirModal  = (item, acao) => { modal.value = { aberto: true, item, acao, obs: '' } }
const fecharModal = () => { modal.value.aberto = false }

const confirmarAcao = async () => {
  const { item, acao, obs } = modal.value
  processando.value = item.id
  try {
    await api.patch(`/api/v3/rh/declaracoes/${item.id}`, { status: acao, obs })
    const idx = itens.value.findIndex(i => i.id === item.id)
    if (idx !== -1) { itens.value[idx].status = acao; itens.value[idx].obs = obs || itens.value[idx].obs }
    showToast(acao === 'pronto' ? `✅ Solicitação de "${item.nome}" aprovada!` : `❌ Solicitação de "${item.nome}" indeferida.`)
    fecharModal()
  } catch { showToast('❌ Erro ao atualizar a solicitação.') }
  finally { processando.value = null }
}

// ─── Utilitários ─────────────────────────────────────────────────
const showToast = (msg) => {
  toast.value = { visible: true, msg }
  setTimeout(() => { toast.value.visible = false }, 4000)
}
const corStatus  = (s) => ({ pronto: '#10b981', andamento: '#f59e0b', pendente: '#94a3b8', indeferido: '#ef4444' })[s] ?? '#94a3b8'
const icoStatus  = (s) => ({ pronto: '✅', andamento: '⏳', pendente: '📋', indeferido: '❌' })[s] ?? '📋'
const labelStatus = (s) => ({ pronto: 'Resolvido', andamento: 'Em andamento', pendente: 'Pendente', indeferido: 'Indeferido' })[s] ?? s
const badgeClass  = (s) => ({ pronto: 'badge-green', andamento: 'badge-yellow', pendente: 'badge-gray', indeferido: 'badge-red' })[s] ?? ''
const formatDate  = (d) => { try { return new Date((d + '').length === 10 ? d + 'T12:00:00' : d).toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' }) } catch { return d } }
</script>

<style scoped>
.gd-page { display: flex; flex-direction: column; gap: 18px; font-family: 'Inter', system-ui, sans-serif; }

/* HERO */
.hero { position: relative; border-radius: 22px; padding: 26px 34px; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1c1a0f 55%, #0f1a2a 100%); opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22,1,0.36,1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.12; }
.hs1 { width: 220px; height: 220px; background: #10b981; right: -40px; top: -60px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hero-eyebrow { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #34d399; margin-bottom: 5px; }
.hero-title { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 3px; }
.hero-sub { font-size: 13px; color: #94a3b8; margin: 0; }
.hero-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.hstat { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 10px 18px; text-align: center; }
.hv { display: block; font-size: 22px; font-weight: 900; }
.hl { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 2px; }
.ha .hv { color: #fbbf24; } .hb .hv { color: #60a5fa; } .hc .hv { color: #34d399; } .hd .hv { color: #f87171; }

/* ABAS PRINCIPAIS */
.main-tabs { display: flex; gap: 8px; opacity: 0; transition: all 0.3s 0.05s; }
.main-tabs.loaded { opacity: 1; }
.mtab { padding: 10px 22px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.18s; }
.mtab:hover { border-color: #94a3b8; color: #1e293b; }
.mtab.active { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
.mtab-count { background: #ef4444; border-radius: 99px; font-size: 10px; font-weight: 800; padding: 1px 7px; color: #fff; }

/* TOOLBAR */
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; opacity: 0; transform: translateY(6px); transition: all 0.4s 0.06s; }
.toolbar.loaded { opacity: 1; transform: none; }
.filtro-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.ftab { padding: 6px 14px; border-radius: 99px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.18s; white-space: nowrap; }
.ftab:hover { border-color: #94a3b8; color: #1e293b; }
.ftab.active { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
.ftab-count { background: rgba(255,255,255,0.25); border-radius: 99px; font-size: 10px; font-weight: 800; padding: 1px 7px; }
.ftab:not(.active) .ftab-count { background: #f1f5f9; color: #64748b; }
.search-wrap { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 14px; min-width: 240px; }
.s-ico { width: 15px; height: 15px; color: #94a3b8; flex-shrink: 0; }
.s-input { flex: 1; border: none; font-size: 14px; color: #1e293b; outline: none; background: transparent; }

/* LISTA */
.lista-section { display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(8px); transition: all 0.4s 0.1s; }
.lista-section.loaded { opacity: 1; transform: none; }
.card { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 18px; animation: cardIn 0.35s cubic-bezier(0.22,1,0.36,1) calc(var(--ci) * 35ms) both; flex-wrap: wrap; transition: box-shadow 0.18s; }
@keyframes cardIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.card:hover { box-shadow: 0 4px 18px -4px rgba(0,0,0,0.08); }
.card-ico { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.card-info { flex: 1; min-width: 180px; display: flex; flex-direction: column; gap: 2px; }
.card-nome { font-size: 13px; font-weight: 700; color: #1e293b; }
.card-servidor { font-size: 11px; color: #475569; }
.card-data { font-size: 11px; color: #94a3b8; }
.card-obs { font-size: 11px; color: #64748b; font-style: italic; background: #f8fafc; border-radius: 6px; padding: 3px 8px; margin-top: 3px; }
.card-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.card-proto { font-family: monospace; font-size: 10px; color: #94a3b8; }
.card-acoes { display: flex; gap: 8px; align-items: center; }
.btn-aprovar { padding: 7px 14px; border-radius: 10px; border: none; background: #10b981; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; gap: 5px; }
.btn-aprovar:hover { filter: brightness(1.1); transform: translateY(-1px); }
.btn-aprovar:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-indeferir { padding: 7px 14px; border-radius: 10px; border: 1.5px solid #fecaca; background: #fff; color: #ef4444; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.btn-indeferir:hover { background: #fef2f2; transform: translateY(-1px); }
.btn-indeferir:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.badge-resolvido { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 99px; }
.pi-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.badge-green { background: #dcfce7; color: #166534; }
.badge-yellow { background: #fffbeb; color: #92400e; }
.badge-gray { background: #f1f5f9; color: #64748b; }
.badge-red { background: #fef2f2; color: #991b1b; }
.paginacao { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 4px; }
.pg-btn { width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 18px; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; }
.pg-btn:hover:not(:disabled) { border-color: #1e3a8a; color: #1e3a8a; }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-info { font-size: 13px; font-weight: 600; color: #475569; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 40px; gap: 10px; font-size: 32px; color: #94a3b8; }
.empty-state p { font-size: 14px; margin: 0; }

/* ═══ MODELOS ═══ */
.modelos-layout { display: grid; grid-template-columns: 280px 1fr; gap: 16px; min-height: 500px; opacity: 0; transition: opacity 0.3s; }
.modelos-layout.loaded { opacity: 1; }
.tipos-list { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 0; display: flex; flex-direction: column; overflow-y: auto; max-height: 68vh; }
.tipos-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; padding: 0 16px 10px; margin: 0; }
.tipos-loading { text-align: center; color: #94a3b8; font-size: 13px; padding: 20px; }
.tipo-item { display: flex; align-items: flex-start; gap: 10px; padding: 11px 16px; cursor: pointer; transition: background 0.15s; border-left: 3px solid transparent; }
.tipo-item:hover { background: #f8fafc; }
.tipo-item.active { background: #eff6ff; border-left-color: #1e3a8a; }
.tipo-ico { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
.tipo-info { display: flex; flex-direction: column; gap: 2px; }
.tipo-nome { font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.tipo-status { font-size: 10px; font-weight: 600; }
.ts-custom { color: #059669; }
.ts-default { color: #94a3b8; }
.tipo-data { font-size: 10px; color: #cbd5e1; }

/* EDITOR */
.editor-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.ep-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ep-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0 0 3px; }
.ep-sub { font-size: 12px; color: #94a3b8; margin: 0; }
.ep-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* WYSIWYG TOOLBAR */
.wysiwyg-toolbar { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px 10px 0 0; padding: 8px 10px; }
.tb-group { display: flex; align-items: center; gap: 3px; }
.tb-sep { width: 1px; height: 20px; background: #e2e8f0; margin: 0 4px; }
.tb-btn { width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent; color: #475569; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
.tb-btn:hover { background: #e2e8f0; }
.tb-font { height: 28px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; color: #475569; padding: 0 6px; outline: none; background: #fff; cursor: pointer; }
.tb-size { width: 50px; height: 28px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; color: #475569; padding: 0 4px; outline: none; background: #fff; cursor: pointer; }
.tb-color { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; position: relative; transition: background 0.15s; }
.tb-color:hover { background: #e2e8f0; }

/* EDITOR BODY */
.wysiwyg-body { min-height: 340px; max-height: 500px; overflow-y: auto; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px; padding: 16px 20px; font-size: 13px; color: #1e293b; background: #fff; outline: none; line-height: 1.8; cursor: text; }
.wysiwyg-body:empty:before { content: attr(data-placeholder); color: #cbd5e1; pointer-events: none; }
.wysiwyg-body:focus { border-color: #1e3a8a; box-shadow: 0 0 0 2px rgba(30,58,138,0.07); }

/* FOOTER REORGANIZADO */
.ep-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 4px; }
.ep-footer-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.vars-panel { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.vars-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
.var-tag { font-family: monospace; font-size: 11px; background: #1e293b; color: #7dd3fc; border-radius: 6px; padding: 2px 8px; cursor: pointer; transition: background 0.15s; user-select: none; }
.var-tag:hover { background: #1e3a8a; }
.btn-upload { padding: 7px 12px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; color: #475569; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; white-space: nowrap; }
.btn-upload:hover { border-color: #94a3b8; }
.btn-preview { padding: 7px 12px; border-radius: 10px; border: 1.5px solid #dbeafe; background: #eff6ff; color: #1e3a8a; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.btn-preview:hover { background: #dbeafe; }
.btn-reset { padding: 7px 12px; border-radius: 10px; border: 1.5px solid #fecaca; background: #fff; color: #ef4444; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.18s; }
.btn-reset:hover { background: #fef2f2; }
.btn-salvar { padding: 9px 20px; border-radius: 12px; border: none; background: #1e3a8a; color: #fff; font-size: 13px; font-weight: 800; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; gap: 8px; }
.btn-salvar:hover { filter: brightness(1.12); }
.btn-salvar:disabled { opacity: 0.7; cursor: not-allowed; }
.editor-empty { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; font-size: 32px; color: #94a3b8; min-height: 300px; }
.editor-empty p { font-size: 14px; margin: 0; }

/* PREVIEW MODAL */
.preview-modal { background: #fff; border-radius: 16px; width: 90vw; max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 32px 80px rgba(0,0,0,0.25); }
.preview-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #1e293b; }
.preview-close { width: 30px; height: 30px; border-radius: 8px; border: none; background: #f1f5f9; color: #64748b; font-size: 14px; cursor: pointer; }
.preview-close:hover { background: #e2e8f0; }
.preview-frame { flex: 1; border: none; width: 100%; min-height: 600px; }

/* MODAL */
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 300; display: flex; align-items: center; justify-content: center; }
.modal { background: #fff; border-radius: 20px; padding: 32px; max-width: 440px; width: 90%; box-shadow: 0 24px 60px rgba(0,0,0,0.2); display: flex; flex-direction: column; gap: 14px; }
.modal-ico { font-size: 40px; text-align: center; }
.modal-title { font-size: 18px; font-weight: 800; color: #1e293b; text-align: center; margin: 0; }
.modal-doc { font-size: 14px; font-weight: 600; color: #475569; text-align: center; margin: 0; }
.modal-servidor { font-size: 12px; color: #94a3b8; text-align: center; margin: -8px 0 0; }
.obs-wrap { display: flex; flex-direction: column; gap: 6px; }
.obs-wrap label { font-size: 13px; font-weight: 700; color: #475569; }
.obs-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.obs-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1e293b; font-family: inherit; resize: vertical; outline: none; transition: border 0.18s; }
.obs-input:focus { border-color: #1e3a8a; }
.modal-btns { display: flex; gap: 10px; }
.mbtn { flex: 1; padding: 11px; border-radius: 12px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.18s; display: flex; align-items: center; justify-content: center; gap: 6px; }
.mbtn-cancel { background: #f1f5f9; color: #64748b; }
.mbtn-cancel:hover { background: #e2e8f0; }
.mbtn-aprovar { background: #10b981; color: #fff; }
.mbtn-aprovar:hover { filter: brightness(1.1); }
.mbtn-indeferir { background: #ef4444; color: #fff; }
.mbtn-indeferir:hover { filter: brightness(1.1); }
.mbtn:disabled { opacity: 0.7; cursor: not-allowed; }

/* TOAST / SPINNER */
.spin { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 400; box-shadow: 0 16px 48px rgba(0,0,0,0.2); white-space: nowrap; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
.overlay-enter-active, .overlay-leave-active { transition: opacity 0.25s; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

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
