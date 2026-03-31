<template>
  <div class="ac-page">

    <!-- ESTADO: Token inválido / expirado -->
    <div v-if="tokenStatus === 'invalido'" class="msg-card error">
      <span class="msg-ico">🔗</span>
      <h2>Link inválido ou expirado</h2>
      <p>Este link de cadastro não é válido ou já foi utilizado.<br>
         Solicite um novo link ao setor de Recursos Humanos.</p>
    </div>

    <!-- ESTADO: Já preenchido / aguardando aprovação -->
    <div v-else-if="tokenStatus === 'preenchido'" class="msg-card info">
      <span class="msg-ico">⏳</span>
      <h2>Cadastro enviado!</h2>
      <p>Seus dados foram recebidos com sucesso.<br>
         Aguarde a aprovação do setor de RH para acessar o sistema.</p>
    </div>

    <!-- ESTADO: Aprovado -->
    <div v-else-if="tokenStatus === 'aprovado'" class="msg-card success">
      <span class="msg-ico">✅</span>
      <h2>Conta ativada!</h2>
      <p>Seu cadastro foi aprovado. <a href="/login">Clique aqui para fazer login</a>.</p>
    </div>

    <!-- ESTADO: Carregando -->
    <div v-else-if="carregando" class="msg-card">
      <div class="spinner"></div>
      <p>Verificando link...</p>
    </div>

    <!-- FORMULÁRIO de autocadastro -->
    <div v-else-if="tokenStatus === 'pendente'" class="form-card">

      <div class="form-header">
        <img :src="'/images/logo-gente.png'" alt="GENTE" class="form-logo" @error="$event.target.style.display='none'" />
        <div>
          <h1 class="form-title">Cadastro de Funcionário</h1>
          <p class="form-sub">Preencha seus dados para concluir o cadastro no sistema GENTE</p>
        </div>
      </div>

      <form @submit.prevent="enviar" class="form-body">

        <!-- ── Dados Pessoais ─────────────────────────────────── -->
        <div class="form-section">
          <h3 class="section-title">👤 Dados Pessoais</h3>
          <div class="form-row2">
            <div class="form-field">
              <label>Nome completo <span class="req">*</span></label>
              <input v-model="form.nome" type="text" placeholder="Nome como consta no documento" required />
            </div>
            <div class="form-field">
              <label>Nome social</label>
              <input v-model="form.nome_social" type="text" placeholder="Opcional" />
            </div>
          </div>
          <div class="form-row3">
            <div class="form-field">
              <label>CPF <span class="req">*</span></label>
              <input v-model="form.cpf" type="text" placeholder="000.000.000-00" required @input="mascararCpf" maxlength="14" />
            </div>
            <div class="form-field">
              <label>Data de nascimento <span class="req">*</span></label>
              <input v-model="form.data_nasc" type="date" required />
            </div>
            <div class="form-field">
              <label>Sexo</label>
              <select v-model="form.sexo">
                <option value="">Selecione</option>
                <option value="1">Masculino</option>
                <option value="2">Feminino</option>
                <option value="3">Não-binário</option>
              </select>
            </div>
          </div>
          <div class="form-row2">
            <div class="form-field">
              <label>RG</label>
              <input v-model="form.rg" type="text" placeholder="0000000" />
            </div>
            <div class="form-field">
              <label>Órgão Emissor</label>
              <input v-model="form.org_emissor" type="text" placeholder="SSP/UF" />
            </div>
          </div>
          <div class="form-row2">
            <div class="form-field">
              <label>PIS / PASEP <span class="req">*</span> <span class="campo-hint">(11 dígitos)</span></label>
              <input v-model="form.pis" type="text" placeholder="000.00000.00-0" @input="mascararPis" maxlength="14" />
              <span v-if="form.pis && form.pis.replace(/\D/g,'').length < 11" class="campo-aviso">⚠️ PIS/PASEP exige 11 dígitos (crítico para eSocial)</span>
            </div>
            <div class="form-field">
              <label>Estado Civil</label>
              <select v-model="form.estado_civil">
                <option value="">Selecione</option>
                <option value="1">Solteiro(a)</option>
                <option value="2">Casado(a)</option>
                <option value="3">Separado(a)</option>
                <option value="4">Divorciado(a)</option>
                <option value="5">Viúvo(a)</option>
              </select>
            </div>
          </div>
          <div class="form-row2">
            <div class="form-field">
              <label>Grau de instrução</label>
              <select v-model="form.grau_instrucao">
                <option value="">Selecione</option>
                <option value="1">Fundamental Incompleto</option>
                <option value="2">Fundamental Completo</option>
                <option value="3">Médio Incompleto</option>
                <option value="4">Médio Completo</option>
                <option value="5">Superior Incompleto</option>
                <option value="6">Superior Completo</option>
                <option value="7">Pós-graduação</option>
                <option value="8">Mestrado</option>
                <option value="9">Doutorado</option>
              </select>
            </div>
            <div class="form-field">
              <label>Raça / Cor (eSocial)</label>
              <select v-model="form.raca_cor">
                <option value="">Selecione</option>
                <option value="1">Branca</option>
                <option value="2">Preta</option>
                <option value="3">Parda</option>
                <option value="4">Amarela</option>
                <option value="5">Indígena</option>
                <option value="6">Não informado</option>
              </select>
            </div>
          </div>
        </div>

        <!-- ── Contato ─────────────────────────────────────────── -->
        <div class="form-section">
          <h3 class="section-title">📞 Contato</h3>
          <div class="form-row2">
            <div class="form-field">
              <label>E-mail <span class="req">*</span></label>
              <input v-model="form.email" type="email" required placeholder="seuemail@exemplo.com" />
            </div>
            <div class="form-field">
              <label>Telefone / WhatsApp</label>
              <input v-model="form.telefone" type="text" placeholder="(00) 00000-0000" @input="mascararTelefone" maxlength="15" />
            </div>
          </div>
        </div>

        <!-- ── Endereço ────────────────────────────────────────── -->
        <div class="form-section">
          <h3 class="section-title">🏠 Endereço</h3>
          <div class="form-row2">
            <div class="form-field">
              <label>CEP</label>
              <input v-model="form.cep" type="text" placeholder="00000-000" @input="mascararCep" @blur="buscarCep" maxlength="9" />
            </div>
          </div>
          <div class="form-row2">
            <div class="form-field" style="flex:2">
              <label>Logradouro</label>
              <input v-model="form.logradouro" type="text" placeholder="Rua, Av., Travessa..." />
            </div>
            <div class="form-field">
              <label>Número</label>
              <input v-model="form.numero" type="text" placeholder="123" />
            </div>
          </div>
          <div class="form-row3">
            <div class="form-field">
              <label>Bairro</label>
              <input v-model="form.bairro" type="text" />
            </div>
            <div class="form-field">
              <label>Cidade</label>
              <input v-model="form.cidade" type="text" />
            </div>
            <div class="form-field">
              <label>UF</label>
              <input v-model="form.uf" type="text" maxlength="2" style="text-transform:uppercase" />
            </div>
          </div>
        </div>

        <!-- ── Dependentes (IRRF) ─────────────────────────────── -->
        <div class="form-section">
          <div class="section-hdr">
            <h3 class="section-title">👨‍👩‍👧 Dependentes <span class="section-info">— para dedução de IRRF</span></h3>
            <button type="button" class="btn-add-dep" @click="addDependente">+ Adicionar</button>
          </div>
          <div v-if="dependentes.length === 0" class="dep-empty">
            Nenhum dependente cadastrado. Clique em "+ Adicionar" se possível.
          </div>
          <div v-for="(dep, idx) in dependentes" :key="idx" class="dep-card">
            <div class="dep-card-hdr">
              <span class="dep-num">Dependente {{ idx + 1 }}</span>
              <button type="button" class="dep-remove" @click="removeDependente(idx)">✕ Remover</button>
            </div>
            <div class="form-row3">
              <div class="form-field" style="grid-column:span 2">
                <label>Nome completo <span class="req">*</span></label>
                <input v-model="dep.nome" type="text" placeholder="Nome do dependente" />
              </div>
              <div class="form-field">
                <label>CPF</label>
                <input v-model="dep.cpf" type="text" placeholder="000.000.000-00" maxlength="14" @input="mascararCpfDep(dep)" />
              </div>
            </div>
            <div class="form-row3">
              <div class="form-field">
                <label>Data de nascimento</label>
                <input v-model="dep.data_nasc" type="date" />
              </div>
              <div class="form-field">
                <label>Parentesco <span class="req">*</span></label>
                <select v-model="dep.parentesco">
                  <option value="">Selecione</option>
                  <option value="01">Cônjuge / Companheiro(a)</option>
                  <option value="02">Filho(a)</option>
                  <option value="03">Enteado(a)</option>
                  <option value="09">Sogro(a)</option>
                  <option value="10">Filho(a) inválido(a)</option>
                  <option value="11">Irmão/irmã, neto(a) ou bisneto(a) sem arrimo</option>
                  <option value="12">Pais, avós ou bisavós</option>
                  <option value="99">Outros</option>
                </select>
              </div>
              <div class="form-field">
                <label>Tipo de dedução (IRRF)</label>
                <select v-model="dep.deducao_irrf">
                  <option value="1">Dependente IRRF</option>
                  <option value="2">Pensão alimentícia</option>
                  <option value="0">Sem dedução</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Documentos (Upload) ────────────────────────────── -->
        <div class="form-section">
          <!-- Secao Documentos -->
          <div class="doc-section-hdr">
            <h3 class="section-title">Documentos</h3>
            <button type="button" class="doc-help-btn" @click="docHelpOpen = true">? O que enviar</button>
          </div>
          <p class="section-desc">Envie fotos ou scans dos documentos (JPG, PNG ou PDF — máx. 5MB cada).</p>

          <div class="uploads-grid">
            <div class="upload-item">
              <label class="upload-label">Documento de Identidade (RG / CNH)</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_identidade }" @click="$refs.refDocId.click()" @dragover.prevent @drop.prevent="onDrop('doc_identidade', $event)">
                <input ref="refDocId" type="file" accept="image/*,.pdf" style="display:none" @change="onFile('doc_identidade', $event)" />
                <div v-if="!arquivos.doc_identidade" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_identidade" :src="previews.doc_identidade" class="prev-img" />
                  <span v-else class="prev-pdf">{{ arquivos.doc_identidade.name }}</span>
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_identidade')">✕</button>
                </div>
              </div>
            </div>

            <div class="upload-item">
              <label class="upload-label">CPF (foto do cartão)</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_cpf }" @click="$refs.refDocCpf.click()" @dragover.prevent @drop.prevent="onDrop('doc_cpf', $event)">
                <input ref="refDocCpf" type="file" accept="image/*,.pdf" style="display:none" @change="onFile('doc_cpf', $event)" />
                <div v-if="!arquivos.doc_cpf" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_cpf" :src="previews.doc_cpf" class="prev-img" />
                  <span v-else class="prev-pdf">{{ arquivos.doc_cpf.name }}</span>
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_cpf')">✕</button>
                </div>
              </div>
            </div>

            <div class="upload-item">
              <label class="upload-label">Comprovante de Residência</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_residencia }" @click="$refs.refDocRes.click()" @dragover.prevent @drop.prevent="onDrop('doc_residencia', $event)">
                <input ref="refDocRes" type="file" accept="image/*,.pdf" style="display:none" @change="onFile('doc_residencia', $event)" />
                <div v-if="!arquivos.doc_residencia" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_residencia" :src="previews.doc_residencia" class="prev-img" />
                  <span v-else class="prev-pdf">{{ arquivos.doc_residencia.name }}</span>
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_residencia')">✕</button>
                </div>
              </div>
            </div>

            <div class="upload-item">
              <label class="upload-label">Cartão PIS / PASEP</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_pis }" @click="$refs.refDocPis.click()" @dragover.prevent @drop.prevent="onDrop('doc_pis', $event)">
                <input ref="refDocPis" type="file" accept="image/*,.pdf" style="display:none" @change="onFile('doc_pis', $event)" />
                <div v-if="!arquivos.doc_pis" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_pis" :src="previews.doc_pis" class="prev-img" />
                  <span v-else class="prev-pdf">{{ arquivos.doc_pis.name }}</span>
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_pis')">✕</button>
                </div>
              </div>
            </div>

            <div class="upload-item">
              <label class="upload-label">Foto (3x4 ou similar)</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_foto }" @click="$refs.refDocFoto.click()" @dragover.prevent @drop.prevent="onDrop('doc_foto', $event)">
                <input ref="refDocFoto" type="file" accept="image/*" style="display:none" @change="onFile('doc_foto', $event)" />
                <div v-if="!arquivos.doc_foto" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_foto" :src="previews.doc_foto" class="prev-img" />
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_foto')">✕</button>
                </div>
              </div>
            </div>

            <div class="upload-item">
              <label class="upload-label">Comprovante de Dependentes</label>
              <div class="upload-box" :class="{ 'has-file': arquivos.doc_dependentes }" @click="$refs.refDocDep.click()" @dragover.prevent @drop.prevent="onDrop('doc_dependentes', $event)">
                <input ref="refDocDep" type="file" accept="image/*,.pdf" style="display:none" @change="onFile('doc_dependentes', $event)" />
                <div v-if="!arquivos.doc_dependentes" class="upload-placeholder">
                  <span class="upload-ico">📤</span>
                  <span>Clique ou arraste</span>
                </div>
                <div v-else class="upload-preview">
                  <img v-if="previews.doc_dependentes" :src="previews.doc_dependentes" class="prev-img" />
                  <span v-else class="prev-pdf">{{ arquivos.doc_dependentes.name }}</span>
                  <button type="button" class="prev-remove" @click.stop="removeArquivo('doc_dependentes')">✕</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Popup: O que enviar em cada campo -->
        <transition name="modal">
          <div v-if="docHelpOpen" class="doc-help-overlay" @click.self="docHelpOpen = false">
            <div class="doc-help-card">
              <div class="doc-help-hdr">
                <strong>O que enviar em cada campo?</strong>
                <button type="button" class="doc-help-close" @click="docHelpOpen = false">&#x2715;</button>
              </div>
              <ul class="doc-help-list">
                <li><span class="doc-help-tag">Identidade</span> RG (frente e verso) ou CNH &mdash; foto legível, sem reflexo.</li>
                <li><span class="doc-help-tag">CPF</span> Foto do cartão CPF ou print do app Receita/Gov.br mostrando o número.</li>
                <li><span class="doc-help-tag">Comprovante de residência</span> Conta de luz, água, gás ou telefone fixo (máx. 90 dias). Seu nome deve constar no documento.</li>
                <li><span class="doc-help-tag">Cartão PIS / PASEP</span> Frente do cartão ou extrato com número PIS/PASEP. Trabalhadores de primeiro emprego podem apresentar declaração de não cadastro.</li>
                <li><span class="doc-help-tag">Foto</span> Foto tipo 3x4 recente, fundo branco ou claro. Selfie nítida é aceita.</li>
                <li><span class="doc-help-tag">Comprovante de dependentes</span> Certidão de nascimento (filhos/enteados), certidão de casamento (cônjuge) ou declaração judicial (guarda).</li>
              </ul>
              <p class="doc-help-tip">Dúvidas? Entre em contato com o RH antes de enviar.</p>
            </div>
          </div>
        </transition>

        <!-- ── Senha de acesso ────────────────────────────────── -->
        <div class="form-section">
          <h3 class="section-title">🔐 Senha de acesso</h3>
          <div class="form-row2">
            <div class="form-field">
              <label>Senha <span class="req">*</span></label>
              <input v-model="form.senha" type="password" placeholder="Mínimo 6 caracteres" required minlength="6" />
            </div>
            <div class="form-field">
              <label>Confirmar senha <span class="req">*</span></label>
              <input v-model="form.senha_confirm" type="password" placeholder="Repita a senha" required />
            </div>
          </div>
          <p v-if="form.senha && form.senha_confirm && form.senha !== form.senha_confirm" class="campo-erro">
            ⚠️ As senhas não coincidem.
          </p>
        </div>

        <!-- Erro geral -->
        <div v-if="erro" class="form-erro">{{ erro }}</div>

        <!-- Submit -->
        <div class="form-footer">
          <p class="form-aviso">
            🔒 Seus dados são protegidos conforme a LGPD. Apenas o setor de RH terá acesso.
          </p>
          <button type="submit" class="btn-submit" :disabled="enviando || (form.senha !== form.senha_confirm && !!form.senha_confirm)">
            <span v-if="enviando" class="btn-spin"></span>
            <span v-else>✅ Enviar Cadastro</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()
const token = route.params.token

const tokenStatus = ref(null)
const carregando   = ref(true)
const enviando     = ref(false)
const erro         = ref('')

const form = ref({
  nome: '', nome_social: '', cpf: '', data_nasc: '', sexo: '',
  rg: '', org_emissor: '', pis: '', estado_civil: '',
  grau_instrucao: '', raca_cor: '',
  email: '', telefone: '',
  cep: '', logradouro: '', numero: '', bairro: '', cidade: '', uf: '',
  senha: '', senha_confirm: '',
})

// Popup ajuda documentos
const docHelpOpen = ref(false)

// Dependentes
const dependentes = ref([])

const addDependente = () => {
  dependentes.value.push({ nome: '', cpf: '', data_nasc: '', parentesco: '', deducao_irrf: '1' })
}
const removeDependente = (idx) => dependentes.value.splice(idx, 1)

// Arquivos para upload
const arquivos = ref({ doc_identidade: null, doc_cpf: null, doc_residencia: null, doc_pis: null, doc_foto: null, doc_dependentes: null })
const previews = ref({ doc_identidade: null, doc_cpf: null, doc_residencia: null, doc_pis: null, doc_foto: null, doc_dependentes: null })

const onFile = (campo, evt) => {
  const file = evt.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { alert('Arquivo muito grande. Máximo 5MB.'); return }
  arquivos.value[campo] = file
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = e => { previews.value[campo] = e.target.result }
    reader.readAsDataURL(file)
  } else {
    previews.value[campo] = null
  }
}
const onDrop = (campo, evt) => {
  const file = evt.dataTransfer.files[0]
  if (!file) return
  arquivos.value[campo] = file
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = e => { previews.value[campo] = e.target.result }
    reader.readAsDataURL(file)
  } else {
    previews.value[campo] = null
  }
}
const removeArquivo = (campo) => {
  arquivos.value[campo] = null
  previews.value[campo] = null
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/v3/autocadastro/${token}`)
    tokenStatus.value = data.status ?? 'pendente'
    if (data.nome)  form.value.nome  = data.nome
    if (data.email) form.value.email = data.email
  } catch {
    tokenStatus.value = 'invalido'
  } finally {
    carregando.value = false
  }
})

const enviar = async () => {
  if (form.value.senha !== form.value.senha_confirm) {
    erro.value = 'As senhas não coincidem.'; return
  }
  enviando.value = true; erro.value = ''
  try {
    const fd = new FormData()
    // Campos do form
    for (const [k, v] of Object.entries(form.value)) {
      if (k !== 'senha_confirm') fd.append(k, v ?? '')
    }
    // Dependentes como JSON
    fd.append('dependentes', JSON.stringify(dependentes.value))
    // Arquivos
    for (const [k, f] of Object.entries(arquivos.value)) {
      if (f) fd.append(k, f)
    }
    await api.post(`/api/v3/autocadastro/${token}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    tokenStatus.value = 'preenchido'
  } catch (e) {
    erro.value = e.response?.data?.erro || 'Erro ao enviar cadastro. Tente novamente.'
  } finally {
    enviando.value = false
  }
}

const buscarCep = async () => {
  const cep = form.value.cep.replace(/\D/g, '')
  if (cep.length !== 8) return
  try {
    const { data } = await api.get(`https://viacep.com.br/ws/${cep}/json/`)
    if (!data.erro) {
      form.value.logradouro = data.logradouro || ''
      form.value.bairro     = data.bairro     || ''
      form.value.cidade     = data.localidade || ''
      form.value.uf         = data.uf         || ''
    }
  } catch {}
}

const mascararCpf = () => {
  let v = form.value.cpf.replace(/\D/g, '').slice(0, 11)
  if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4')
  else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3')
  else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/, '$1.$2')
  form.value.cpf = v
}

const mascararCpfDep = (dep) => {
  let v = dep.cpf.replace(/\D/g, '').slice(0, 11)
  if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4')
  else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3')
  else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/, '$1.$2')
  dep.cpf = v
}

// BUG-EST-10: máscara PIS/PASEP — 000.00000.00-0
const mascararPis = () => {
  let v = form.value.pis.replace(/\D/g, '').slice(0, 11)
  if (v.length > 9)      v = v.replace(/(\d{3})(\d{5})(\d{2})(\d{0,1})/, '$1.$2.$3-$4')
  else if (v.length > 8) v = v.replace(/(\d{3})(\d{5})(\d{0,2})/, '$1.$2.$3')
  else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,5})/, '$1.$2')
  form.value.pis = v
}

// BUG-EST-10: máscara telefone — (00) 00000-0000
const mascararTelefone = () => {
  let v = form.value.telefone.replace(/\D/g, '').slice(0, 11)
  if (v.length > 10)     v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
  else if (v.length > 6) v = v.replace(/(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3')
  else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2')
  form.value.telefone = v
}

// BUG-EST-10: máscara CEP — 00000-000
const mascararCep = () => {
  let v = form.value.cep.replace(/\D/g, '').slice(0, 8)
  if (v.length > 5) v = v.replace(/(\d{5})(\d{0,3})/, '$1-$2')
  form.value.cep = v
}
</script>

<style scoped>
.ac-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0a1f3a 100%);
  display: flex; align-items: flex-start; justify-content: center;
  padding: 24px; font-family: 'Inter', system-ui, sans-serif;
}

/* Cards de estado */
.msg-card {
  background: #fff; border-radius: 24px; padding: 48px 56px;
  text-align: center; max-width: 480px; width: 100%;
  box-shadow: 0 24px 80px rgba(0,0,0,0.3);
}
.msg-card.error   { border-top: 4px solid #ef4444; }
.msg-card.info    { border-top: 4px solid #3b82f6; }
.msg-card.success { border-top: 4px solid #22c55e; }
.msg-ico { font-size: 56px; display: block; margin-bottom: 16px; }
.msg-card h2 { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
.msg-card p  { font-size: 14px; color: #64748b; line-height: 1.6; }
.msg-card a  { color: #6366f1; font-weight: 700; }

/* Formulário */
.form-card {
  background: #fff; border-radius: 24px; width: 100%; max-width: 900px;
  box-shadow: 0 24px 80px rgba(0,0,0,0.3); overflow: hidden;
  margin: 24px 0;
}

.form-header {
  display: flex; align-items: center; gap: 16px;
  padding: 28px 36px; background: linear-gradient(135deg, #0f172a, #1e3a5f);
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.form-logo  { height: 40px; width: auto; }
.form-title { font-size: 20px; font-weight: 900; color: #fff; margin: 0; }
.form-sub   { font-size: 12px; color: #94a3b8; margin: 4px 0 0; }

.form-body    { padding: 28px 36px; display: flex; flex-direction: column; gap: 28px; }
.form-section { display: flex; flex-direction: column; gap: 14px;
                border: 1px solid #f1f5f9; border-radius: 16px; padding: 20px; }
.section-hdr  { display: flex; align-items: center; justify-content: space-between; }
.section-title { font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
.section-info  { font-weight: 400; color: #94a3b8; text-transform: none; font-size: 11px; }
.section-desc  { font-size: 12px; color: #94a3b8; margin: -4px 0 4px; }

.form-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }

.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.form-field input,
.form-field select {
  padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-family: inherit; color: #1e293b; background: #fff; outline: none;
  transition: border-color 0.15s;
}
.form-field input:focus,
.form-field select:focus { border-color: #6366f1; }

/* Dependentes */
.btn-add-dep { padding: 6px 14px; border-radius: 8px; border: 1.5px dashed #6366f1; background: #eef2ff;
               color: #4f46e5; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-add-dep:hover { background: #e0e7ff; }
.dep-empty   { font-size: 12px; color: #94a3b8; text-align: center; padding: 12px; }
.dep-card    { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.dep-card-hdr{ display: flex; align-items: center; justify-content: space-between; }
.dep-num     { font-size: 12px; font-weight: 800; color: #6366f1; }
.dep-remove  { padding: 3px 10px; border-radius: 6px; border: 1px solid #fca5a5; background: #fef2f2;
               color: #dc2626; font-size: 11px; font-weight: 700; cursor: pointer; font-family: inherit; }

/* Upload */
.uploads-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; align-items: start; }
.upload-item  { display: flex; flex-direction: column; gap: 4px; }
.upload-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;
  height: 2.6em; line-height: 1.3; overflow: hidden;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.upload-sublabel { display: none; } /* oculto — info agora no popup */

/* Botão de ajuda e popup de documentos */
.doc-section-hdr { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 2px; }
.doc-help-btn { padding: 5px 12px; border-radius: 8px; border: 1.5px solid #6366f1; background: #eef2ff;
  color: #4f46e5; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit;
  transition: all 0.15s; white-space: nowrap; }
.doc-help-btn:hover { background: #6366f1; color: #fff; }
.doc-help-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 9999;
  display: flex; align-items: center; justify-content: center; padding: 16px; }
.doc-help-card { background: #fff; border-radius: 18px; padding: 28px 28px 22px; max-width: 520px; width: 100%;
  box-shadow: 0 24px 60px rgba(0,0,0,.18); }
.doc-help-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.doc-help-hdr strong { font-size: 15px; color: #1e293b; }
.doc-help-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px;
  cursor: pointer; font-size: 13px; color: #64748b; display: flex; align-items: center; justify-content: center; }
.doc-help-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.doc-help-list li { font-size: 13px; color: #475569; line-height: 1.5; }
.doc-help-tag { display: inline-block; background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 6px; margin-right: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
.doc-help-tip { margin-top: 16px; font-size: 11.5px; color: #94a3b8; font-style: italic; }
.upload-box   {
  border: 2px dashed #e2e8f0; border-radius: 12px; background: #f8fafc;
  height: 110px; cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; position: relative; overflow: hidden;
}
.upload-box:hover   { border-color: #6366f1; background: #eef2ff; }
.upload-box.has-file{ border-color: #34d399; background: #f0fdf4; }
.upload-placeholder { display: flex; flex-direction: column; align-items: center; gap: 4px; color: #94a3b8; font-size: 12px; }
.upload-ico  { font-size: 24px; line-height: 1; }
.upload-preview { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; }
.prev-img    { max-width: 100%; max-height: 100%; object-fit: cover; border-radius: 10px; }
.prev-pdf    { font-size: 11px; color: #475569; font-weight: 600; padding: 4px 8px; }
.prev-remove { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; border-radius: 50%;
               border: none; background: #ef4444; color: #fff; font-size: 10px; cursor: pointer;
               display: flex; align-items: center; justify-content: center; font-weight: 700; }

/* Bits gerais */
.req         { color: #ef4444; }
.campo-hint  { font-size: 10px; font-weight: 400; color: #94a3b8; text-transform: none; letter-spacing: 0; }
.campo-aviso { font-size: 11px; font-weight: 600; color: #d97706; margin-top: 2px; } /* BUG-EST-10 */
.campo-erro  { font-size: 12px; font-weight: 600; color: #dc2626; }
.form-erro   {
  background: #fef2f2; border: 1px solid #fca5a5; border-radius: 12px;
  padding: 12px 16px; font-size: 13px; font-weight: 600; color: #b91c1c;
}

.form-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.form-aviso  { font-size: 11px; color: #94a3b8; max-width: 400px; }

.btn-submit {
  padding: 13px 32px; background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; border: none; border-radius: 14px; font-size: 14px; font-weight: 800;
  cursor: pointer; font-family: inherit; transition: all 0.15s; display: flex; align-items: center; gap: 8px;
}
.btn-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.4); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.spinner { width: 24px; height: 24px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto 12px; }

@media (max-width: 640px) {
  .ac-page { padding: 12px; align-items: flex-start; }
  .form-header { padding: 20px; }
  .form-body { padding: 16px; }
  .form-row2, .form-row3 { grid-template-columns: 1fr; }
  .form-footer { flex-direction: column; align-items: stretch; }
  .btn-submit { justify-content: center; }
  .uploads-grid { grid-template-columns: 1fr 1fr; }
}
</style>
