<template>
  <div class="perfil-page">

    <!-- BOTÃO VOLTAR -->
    <button class="btn-back" @click="$router.push('/funcionarios')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Voltar para Funcionários
    </button>

    <!-- LOADING -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Carregando perfil...</p>
    </div>

    <!-- ERRO -->
    <div v-else-if="erro" class="state-box error">
      <span class="state-ico">⚠️</span>
      <h3>{{ erro }}</h3>
      <button class="retry-btn" @click="fetchPerfil">Tentar novamente</button>
    </div>

    <!-- CONTEÚDO -->
    <template v-else-if="funcionario">

      <!-- HERO -->
      <div class="hero" :class="{ loaded }">
        <div class="hero-shapes"><div class="hs hs1"></div><div class="hs hs2"></div><div class="hs hs3"></div></div>
        <div class="hero-inner">
          <div class="hero-avatar" :style="{ '--hue': avatarHue(funcionario.FUNCIONARIO_ID) }">{{ iniciais(funcionario.pessoa?.PESSOA_NOME) }}</div>
          <div class="hero-info">
            <div class="hero-badges">
              <span class="status-chip" :class="funcionario.FUNCIONARIO_DATA_FIM ? 'inactive' : 'active'">
                <span class="chip-dot"></span>
                {{ funcionario.FUNCIONARIO_DATA_FIM ? 'Inativo' : 'Ativo' }}
              </span>
              <span class="matricula-chip">🪪 Matrícula: <strong>{{ funcionario.FUNCIONARIO_MATRICULA || 'N/D' }}</strong></span>
            </div>
            <h1 class="hero-nome">{{ funcionario.pessoa?.PESSOA_NOME || 'Funcionário' }}</h1>
            <p class="hero-cargo">{{ funcionario.atribuicao || 'Servidor Público' }} · {{ funcionario.setor || 'Lotação não definida' }}</p>
          </div>
          <div class="hero-meta">
            <div class="meta-item"><span class="meta-label">Início</span><span class="meta-value">{{ formatDate(funcionario.FUNCIONARIO_DATA_INICIO) }}</span></div>
            <div class="meta-item" v-if="tempoServico"><span class="meta-label">Tempo de Serviço</span><span class="meta-value">{{ tempoServico }}</span></div>
            <div class="meta-item" v-if="funcionario.vinculo"><span class="meta-label">Vínculo</span><span class="meta-value">{{ funcionario.vinculo }}</span></div>
          </div>
        </div>
      </div>

      <!-- GRID PRINCIPAL -->
      <div class="perfil-grid" :class="{ loaded }">

        <!-- COL ESQUERDA -->
        <div class="col-left">

          <!-- Dados Pessoais -->
          <div class="info-card anim-card" :style="{ '--ci': 0 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#eff6ff;color:#3b82f6">👤</span><h2 class="card-hdr-title">Dados Pessoais</h2></div>
            <div class="fields-grid" v-if="funcionario.pessoa">
              <div class="field"><span class="field-label">Nome Completo</span><span class="field-value">{{ funcionario.pessoa.PESSOA_NOME || '—' }}</span></div>
              <div class="field"><span class="field-label">CPF</span><span class="field-value mono">{{ formatCpf(funcionario.pessoa.PESSOA_CPF_NUMERO) }}</span></div>
              <div class="field" v-if="funcionario.pessoa.PESSOA_DATA_NASCIMENTO"><span class="field-label">Nascimento</span><span class="field-value">{{ formatDate(funcionario.pessoa.PESSOA_DATA_NASCIMENTO) }}</span></div>
              <div class="field" v-if="funcionario.pessoa.PESSOA_SEXO != null"><span class="field-label">Sexo</span><span class="field-value">{{ sexoLabel(funcionario.pessoa.PESSOA_SEXO) }}</span></div>
              <div class="field" v-if="funcionario.pessoa.PESSOA_ESTADO_CIVIL != null"><span class="field-label">Estado Civil</span><span class="field-value">{{ estadoCivilLabel(funcionario.pessoa.PESSOA_ESTADO_CIVIL) }}</span></div>
              <div class="field" v-if="funcionario.pessoa.PESSOA_ESCOLARIDADE"><span class="field-label">Escolaridade</span><span class="field-value">{{ escolaridadeLabel(funcionario.pessoa.PESSOA_ESCOLARIDADE) }}</span></div>
            </div>
            <p v-else class="no-data">Dados pessoais não disponíveis</p>
          </div>

          <!-- Dados Funcionais -->
          <div class="info-card anim-card" :style="{ '--ci': 1 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#f0fdf4;color:#16a34a">📋</span><h2 class="card-hdr-title">Dados Funcionais</h2></div>
            <div class="fields-grid">
              <div class="field"><span class="field-label">Matrícula</span><span class="field-value mono">{{ funcionario.FUNCIONARIO_MATRICULA || '—' }}</span></div>
              <div class="field"><span class="field-label">Data de Início</span><span class="field-value">{{ formatDate(funcionario.FUNCIONARIO_DATA_INICIO) }}</span></div>
              <div class="field" v-if="funcionario.FUNCIONARIO_DATA_FIM"><span class="field-label">Data de Saída</span><span class="field-value">{{ formatDate(funcionario.FUNCIONARIO_DATA_FIM) }}</span></div>
              <div class="field" v-if="funcionario.setor"><span class="field-label">Lotação / Setor</span><span class="field-value">{{ funcionario.setor }}</span></div>
              <div class="field" v-if="funcionario.unidade"><span class="field-label">Unidade</span><span class="field-value">{{ funcionario.unidade }}</span></div>
              <div class="field" v-if="funcionario.vinculo"><span class="field-label">Vínculo</span><span class="field-value">{{ funcionario.vinculo }}</span></div>
              <div class="field" v-if="funcionario.atribuicao"><span class="field-label">Atribuição</span><span class="field-value">{{ funcionario.atribuicao }}</span></div>
            </div>
          </div>

          <!-- Progressão Funcional (TASK-15 Sprint 3a) -->
          <div class="info-card anim-card" :style="{ '--ci': 2 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#f5f3ff;color:#7c3aed">📈</span><h2 class="card-hdr-title">Progressão Funcional</h2></div>
            <div v-if="progressao" class="fields-grid">
              <div class="field" v-if="progressao.carreira"><span class="field-label">Carreira</span><span class="field-value">{{ progressao.carreira }}</span></div>
              <div class="field" v-if="progressao.classe"><span class="field-label">Classe</span><span class="field-value"><span class="badge-prog">{{ progressao.classe }}</span></span></div>
              <div class="field" v-if="progressao.referencia"><span class="field-label">Referência</span><span class="field-value"><code style="font-family:monospace">{{ progressao.referencia }}</code></span></div>
              <div class="field" v-if="progressao.salario_base"><span class="field-label">Vencimento Base</span><span class="field-value" style="color:#059669">{{ formatMoney(progressao.salario_base) }}</span></div>
              <div class="field" v-if="progressao.ultima_progressao"><span class="field-label">Última Progressão</span><span class="field-value">{{ formatDate(progressao.ultima_progressao) }}</span></div>
              <div class="field">
                <span class="field-label">Status</span>
                <span v-if="progressao.meses_restantes <= 0" class="field-value" style="color:#16a34a;font-weight:900">✅ Elegível agora</span>
                <span v-else class="field-value" style="color:#ea580c">⏳ {{ progressao.meses_restantes }}m restantes</span>
              </div>
            </div>
            <p v-else class="no-data">Dados de progressão não disponíveis.</p>
            <a href="/progressao-admin" style="display:block;margin-top:10px;font-size:12px;font-weight:700;color:#6366f1;text-decoration:none">📊 Ver todos os elegíveis →</a>
          </div>

          <!-- Adicionais e Vantagens (Sprint 3 — Parte 10) -->
          <div class="info-card anim-card" :style="{ '--ci': 3 }">
            <div class="card-hdr">
              <span class="card-hdr-icon" style="background:#fff7ed;color:#ea580c">💰</span>
              <h2 class="card-hdr-title">Adicionais e Vantagens</h2>
              <button class="btn-sm btn-orange" @click="showFormAdicional = !showFormAdicional" style="margin-left:auto">
                {{ showFormAdicional ? '✕ Cancelar' : '+ Novo' }}
              </button>
            </div>

            <!-- Formulário novo adicional -->
            <div v-if="showFormAdicional" class="adicional-form">
              <div class="form-row-3">
                <label>Rubrica (C2)</label>
                <select v-model="novoAdicional.rubrica_id" class="form-sel">
                  <option v-for="r in rubricasC2" :key="r.RUBRICA_ID" :value="r.RUBRICA_ID">
                    {{ r.RUBRICA_CODIGO }} — {{ r.RUBRICA_DESCRICAO }}
                  </option>
                </select>
              </div>
              <div class="form-row-3">
                <label>Tipo</label>
                <select v-model="novoAdicional.tipo" class="form-sel">
                  <option value="fixo">Fixo (R$)</option>
                  <option value="percentual">Percentual do vencimento</option>
                  <option value="percentual_sm">Percentual do SM</option>
                </select>
              </div>
              <div class="form-row-3">
                <label>Valor / %</label>
                <input v-model.number="novoAdicional.valor" type="number" step="0.01" class="form-inp" placeholder="Ex: 500.00 ou 20"/>
              </div>
              <div class="form-row-3">
                <label>Vigência início</label>
                <input v-model="novoAdicional.vigencia_inicio" type="date" class="form-inp"/>
              </div>
              <div class="form-row-3">
                <label>Ato administrativo</label>
                <input v-model="novoAdicional.ato_adm" type="text" class="form-inp" placeholder="Decreto nº..."/>
              </div>
              <div class="form-row-3" style="grid-column:1/-1">
                <button class="btn-primary-sm" @click="salvarAdicional" :disabled="salvandoAdicional">
                  {{ salvandoAdicional ? 'Salvando...' : '💾 Salvar adicional' }}
                </button>
              </div>
            </div>

            <!-- Lista de adicionais -->
            <div v-if="adicionais.length === 0 && !loadingAdicionais" class="no-data">Nenhum adicional cadastrado.</div>
            <div v-if="loadingAdicionais" class="no-data">Carregando...</div>
            <div v-else class="adicional-list">
              <div v-for="ad in adicionais" :key="ad.id" class="adicional-item">
                <div class="adic-info">
                  <span class="adic-rubrica">{{ ad.rubrica_descricao }}</span>
                  <span class="adic-val">
                    {{ ad.tipo === 'fixo' ? `R$ ${formatMoney(ad.valor)}` : `${ad.valor}%` }}
                    <small v-if="ad.tipo === 'percentual_sm'">(SM)</small>
                  </span>
                  <span class="adic-meta">Desde {{ formatDate(ad.vigencia_inicio) }}</span>
                </div>
                <button class="btn-sm btn-red-ghost" @click="inativarAdicional(ad.id)" title="Inativar">✗</button>
              </div>
            </div>
          </div>

        </div>

        <!-- COL DIREITA -->
        <div class="col-right">

          <!-- Linha do Tempo -->
          <div class="info-card anim-card" :style="{ '--ci': 2 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#fdf4ff;color:#9333ea">📅</span><h2 class="card-hdr-title">Linha do Tempo</h2></div>
            <div class="timeline">
              <div class="tl-item"><div class="tl-dot active"></div><div class="tl-content"><span class="tl-title">Admissão</span><span class="tl-date">{{ formatDate(funcionario.FUNCIONARIO_DATA_INICIO) }}</span></div></div>
              <div class="tl-line"></div>
              <div class="tl-item" v-if="funcionario.setor"><div class="tl-dot setor"></div><div class="tl-content"><span class="tl-title">Lotação: {{ funcionario.setor }}</span><span class="tl-date">Vigente</span></div></div>
              <div class="tl-line" v-if="funcionario.setor"></div>
              <div class="tl-item"><div class="tl-dot" :class="funcionario.FUNCIONARIO_DATA_FIM ? 'demitido' : 'atual'"></div><div class="tl-content">
                <span class="tl-title">{{ !funcionario.FUNCIONARIO_DATA_FIM ? '🟢 Ativo até hoje' : '🔴 Desligamento' }}</span>
                <span class="tl-date">{{ funcionario.FUNCIONARIO_DATA_FIM ? formatDate(funcionario.FUNCIONARIO_DATA_FIM) : 'Atualmente' }}</span>
              </div></div>
            </div>
          </div>

          <!-- Holerites Recentes -->
          <div class="info-card anim-card" :style="{ '--ci': 3 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#f0fdf4;color:#059669">💰</span><h2 class="card-hdr-title">Holerites Recentes</h2></div>
            <div v-if="holeritesFuncionario.length" class="holerites-mini">
              <div v-for="h in holeritesFuncionario.slice(0, 4)" :key="h.detalhe_folha_id" class="holerite-mini-row">
                <div class="hm-comp"><span class="hm-label">{{ formatCompetencia(h.competencia) }}</span></div>
                <div class="hm-liquido">{{ formatMoney(h.liquido) }}</div>
                <a :href="`/contra-cheque/${h.funcionario_id}/${h.competencia}/pdf`" target="_blank" class="hm-pdf">PDF</a>
              </div>
            </div>
            <div v-else class="no-data-sm">Nenhum holerite disponível</div>
          </div>

          <!-- Ações Rápidas -->
          <div class="info-card anim-card" :style="{ '--ci': 4 }">
            <div class="card-hdr"><span class="card-hdr-icon" style="background:#fff7ed;color:#ea580c">⚡</span><h2 class="card-hdr-title">Ações</h2></div>
            <div class="actions-list">
              <button class="action-item blue" @click="abrirModal('editar')">
                <span>✏️</span><span>Editar Cadastro</span>
                <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
              <button class="action-item green" @click="abrirModal('documentos')">
                <span>📁</span><span>Documentos</span>
                <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
              <button class="action-item purple" @click="abrirModal('historico')">
                <span>🕐</span><span>Histórico Funcional</span>
                <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
              <button class="action-item orange" @click="abrirModal('escalas')">
                <span>📆</span><span>Ver Escalas</span>
                <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
              <button class="action-item teal" @click="abrirModal('dependentes')">
                <span>👨‍👩‍👧</span><span>Dependentes <span v-if="dependentes.length" class="dep-badge">{{ dependentes.length }}</span></span>
                <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ════════════════════ MODAIS ════════════════════════ -->

    <!-- MODAL: Editar Cadastro -->
    <transition name="modal">
      <div v-if="modal === 'editar'" class="modal-overlay" @click.self="modal = null">
        <div class="modal-card modal-lg">
          <div class="modal-hdr">
            <h3>✏️ Editar Cadastro</h3>
            <button class="modal-close" @click="modal = null">✕</button>
          </div>
          <div class="modal-body" v-if="editForm">
            <p class="modal-desc">Edite os dados cadastrais do servidor. Campos em branco não serão alterados.</p>
            <div class="form-section">
              <h4 class="form-section-title">📋 Dados Funcionais</h4>
              <div class="form-two-col">
                <div class="form-group">
                  <label>Matrícula</label>
                  <input v-model="editForm.FUNCIONARIO_MATRICULA" class="cfg-input" placeholder="Ex: 000123" />
                </div>
                <div class="form-group">
                  <label>Data de Início</label>
                  <input type="date" v-model="editForm.FUNCIONARIO_DATA_INICIO" class="cfg-input" />
                </div>
                <div class="form-group">
                  <label>Data de Saída (se inativo)</label>
                  <input type="date" v-model="editForm.FUNCIONARIO_DATA_FIM" class="cfg-input" />
                </div>
                <div class="form-group">
                  <label>Observação</label>
                  <input v-model="editForm.FUNCIONARIO_OBSERVACAO" class="cfg-input" placeholder="Observações gerais" />
                </div>
              </div>
            </div>
            <div class="form-section" v-if="editForm.pessoa">
              <h4 class="form-section-title">👤 Dados Pessoais</h4>
              <div class="form-two-col">
                <div class="form-group" style="grid-column:1/-1">
                  <label>Nome Completo</label>
                  <input v-model="editForm.pessoa.PESSOA_NOME" class="cfg-input" placeholder="Nome completo" />
                </div>
                <div class="form-group">
                  <label>Data de Nascimento</label>
                  <input type="date" v-model="editForm.pessoa.PESSOA_DATA_NASCIMENTO" class="cfg-input" />
                </div>
                <div class="form-group">
                  <label>E-mail</label>
                  <input type="email" v-model="editForm.email" class="cfg-input" placeholder="email@exemplo.gov.br" />
                </div>
                <div class="form-group">
                  <label>PIS / PASEP (NIT)</label>
                  <input
                    :value="editForm.pessoa.PESSOA_PIS_PASEP"
                    @input="editForm.pessoa.PESSOA_PIS_PASEP = maskPis($event.target.value)"
                    class="cfg-input"
                    placeholder="000.00000.00-0"
                    maxlength="14"
                  />
                </div>
              </div>
            </div>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modal = null">Cancelar</button>
              <button class="modal-submit" :disabled="salvando" @click="salvarEdicao">
                <span v-if="salvando" class="btn-spin"></span>
                <template v-else>💾 Salvar Alterações</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL: Documentos -->
    <transition name="modal">
      <div v-if="modal === 'documentos'" class="modal-overlay" @click.self="modal = null">
        <div class="modal-card modal-lg">
          <div class="modal-hdr">
            <h3>📁 Documentos do Servidor</h3>
            <button class="modal-close" @click="modal = null">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="modalLoading" class="modal-loading"><div class="spinner-sm"></div><p>Carregando...</p></div>
            <template v-else>

              <!-- Upload zone -->
              <div class="upload-zone" @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="onDrop">
                <input ref="fileInput" type="file" hidden @change="onFileSelected" accept=".pdf,.jpg,.png,.doc,.docx" />
                <span class="upload-ico">📤</span>
                <p class="upload-hint">Arraste um arquivo ou <strong>clique para selecionar</strong></p>
                <span class="upload-types">PDF, JPG, PNG, DOC — máx. 10 MB</span>
              </div>

              <!-- Formulário de upload -->
              <div v-if="uploadFile" class="upload-form">
                <div class="uf-file-name">📄 {{ uploadFile.name }} <span class="uf-size">({{ (uploadFile.size / 1024).toFixed(1) }} KB)</span></div>
                <div class="uf-row">
                  <div class="form-group">
                    <label>Tipo de Documento</label>
                    <select v-model="uploadTipo" class="cfg-input">
                      <option>RG</option><option>CPF</option><option>CNH</option><option>Diploma</option>
                      <option>PIS/PASEP</option><option>Título de Eleitor</option><option>Reservista</option>
                      <option>Comprovante de Endereço</option><option>Laudo Médico</option><option>Outro</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Número (opcional)</label>
                    <input v-model="uploadNumero" class="cfg-input" placeholder="Ex: 12.345.678-9" />
                  </div>
                </div>
                <div v-if="uploadando" class="upload-progress">
                  <div class="up-bar" :style="{ width: uploadPct + '%' }"></div>
                </div>
                <div class="uf-actions">
                  <button class="modal-cancel" @click="uploadFile = null">Cancelar</button>
                  <button class="modal-submit" :disabled="uploadando" @click="uploadDoc">
                    <span v-if="uploadando" class="btn-spin"></span>
                    <template v-else>📤 Enviar Documento</template>
                  </button>
                </div>
              </div>

              <!-- Lista de documentos -->
              <div v-if="documentos.length === 0 && !uploadFile" class="modal-empty">
                <span>📭</span><p>Nenhum documento cadastrado</p>
              </div>
              <div v-if="documentos.length" class="doc-list">
                <div v-for="d in documentos" :key="d.id" class="doc-item" :class="d.obrigatorio ? 'doc-req' : ''">
                  <div class="doc-ico">{{ d.obrigatorio ? '📌' : '📄' }}</div>
                  <div class="doc-info">
                    <span class="doc-tipo">{{ d.tipo }}</span>
                    <span class="doc-num" v-if="d.numero">{{ d.numero }}</span>
                    <span class="doc-num" v-else style="color:#94a3b8">Número não informado</span>
                  </div>
                  <a v-if="d.url" :href="d.url" target="_blank" class="hm-pdf">⬇ Baixar</a>
                  <button class="doc-del" @click="deletarDoc(d.id)" title="Excluir">🗑</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL: Histórico Funcional -->
    <transition name="modal">
      <div v-if="modal === 'historico'" class="modal-overlay" @click.self="modal = null">
        <div class="modal-card modal-lg">
          <div class="modal-hdr">
            <h3>🕐 Histórico Funcional</h3>
            <button class="modal-close" @click="modal = null">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="modalLoading" class="modal-loading"><div class="spinner-sm"></div><p>Carregando...</p></div>
            <template v-else>
              <!-- Lotações -->
              <h4 class="hist-section-title">🏥 Lotações ({{ historico.lotacoes?.length || 0 }})</h4>
              <div v-if="historico.lotacoes?.length" class="hist-list">
                <div v-for="(l, i) in historico.lotacoes" :key="i" class="hist-item" :class="l.ativa ? 'hi-ativa' : ''">
                  <div class="hi-left">
                    <span class="hi-dot" :class="l.ativa ? 'dot-green' : 'dot-gray'"></span>
                    <div>
                      <span class="hi-titulo">{{ l.setor }}</span>
                      <span class="hi-sub">{{ l.cargo }} · {{ l.vinculo }}</span>
                    </div>
                  </div>
                  <div class="hi-datas">
                    <span>{{ formatDate(l.inicio) }}</span>
                    <span>→</span>
                    <span>{{ l.fim ? formatDate(l.fim) : 'Atual' }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="no-data-sm">Nenhuma lotação registrada</p>

              <!-- Férias -->
              <h4 class="hist-section-title" style="margin-top:16px">🏖️ Férias ({{ historico.ferias?.length || 0 }})</h4>
              <div v-if="historico.ferias?.length" class="hist-list">
                <div v-for="(f, i) in historico.ferias" :key="i" class="hist-item">
                  <div class="hi-left"><span class="hi-dot dot-blue"></span><div><span class="hi-titulo">Período de férias</span></div></div>
                  <div class="hi-datas"><span>{{ formatDate(f.inicio) }}</span><span>→</span><span>{{ f.fim ? formatDate(f.fim) : '—' }}</span></div>
                </div>
              </div>
              <p v-else class="no-data-sm">Nenhum período de férias registrado</p>

              <!-- Afastamentos -->
              <h4 class="hist-section-title" style="margin-top:16px">🏥 Afastamentos ({{ historico.afastamentos?.length || 0 }})</h4>
              <div v-if="historico.afastamentos?.length" class="hist-list">
                <div v-for="(a, i) in historico.afastamentos" :key="i" class="hist-item">
                  <div class="hi-left"><span class="hi-dot dot-red"></span><div><span class="hi-titulo">{{ a.descricao }}</span></div></div>
                  <div class="hi-datas"><span>{{ formatDate(a.inicio) }}</span><span>→</span><span>{{ a.fim ? formatDate(a.fim) : '—' }}</span></div>
                </div>
              </div>
              <p v-else class="no-data-sm">Nenhum afastamento registrado</p>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL: Escalas -->
    <transition name="modal">
      <div v-if="modal === 'escalas'" class="modal-overlay" @click.self="modal = null">
        <div class="modal-card modal-lg">
          <div class="modal-hdr">
            <h3>📆 Escalas do Servidor</h3>
            <button class="modal-close" @click="modal = null">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="modalLoading" class="modal-loading"><div class="spinner-sm"></div><p>Carregando...</p></div>
            <div v-else-if="escalas.length === 0" class="modal-empty">
              <span>📭</span><p>Nenhuma escala encontrada</p>
            </div>
            <div v-else class="escala-list">
              <div class="escala-hdr-row">
                <span>Data</span><span>Setor</span><span>Turno</span><span>Horário</span>
              </div>
              <div v-for="e in escalas" :key="e.id" class="escala-row">
                <span class="es-data">{{ formatDate(e.data) }}</span>
                <span class="es-setor">{{ e.setor || '—' }}</span>
                <span class="es-turno">{{ e.turno || '—' }}</span>
                <span class="es-hora">{{ e.entrada || '—' }} → {{ e.saida || '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL: Dependentes -->
    <transition name="modal">
      <div v-if="modal === 'dependentes'" class="modal-overlay" @click.self="modal = null">
        <div class="modal-card modal-lg">
          <div class="modal-hdr">
            <h3>👨‍👩‍👧 Dependentes</h3>
            <button class="modal-close" @click="modal = null">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="modalLoading" class="modal-loading"><div class="spinner-sm"></div><p>Carregando...</p></div>
            <template v-else>
              <p class="modal-desc">Dependentes são considerados para dedução de IRRF. Informe apenas dependentes com direito legal à dedução.</p>

              <!-- Lista de dependentes -->
              <div v-if="dependentes.length === 0 && !addingDep" class="modal-empty">
                <span>👨‍👩‍👧</span><p>Nenhum dependente cadastrado</p>
              </div>
              <div v-if="dependentes.length" class="dep-list">
                <div v-for="dep in dependentes" :key="dep.id" class="dep-row">
                  <div class="dep-row-info">
                    <span class="dep-row-nome">{{ dep.nome }}</span>
                    <span class="dep-row-sub">{{ parentescoLabel(dep.parentesco) }} · Nasc. {{ formatDate(dep.data_nasc) }}</span>
                    <span class="dep-irrf-chip" :class="dep.deducao_irrf == '1' ? 'irrf-sim' : 'irrf-nao'">
                      {{ dep.deducao_irrf == '1' ? 'Deduz IRRF' : dep.deducao_irrf == '2' ? 'Pensão' : 'Sem dedução' }}
                    </span>
                  </div>
                  <button class="doc-del" @click="deletarDependente(dep.id)" title="Excluir">🗑</button>
                </div>
              </div>

              <!-- Formulário de adição -->
              <div v-if="addingDep" class="dep-form">
                <h4 class="form-section-title" style="margin:0 0 8px">➕ Novo Dependente</h4>
                <div class="form-two-col">
                  <div class="form-group" style="grid-column:1/-1">
                    <label>Nome completo *</label>
                    <input v-model="newDep.nome" class="cfg-input" placeholder="Nome do dependente" />
                  </div>
                  <div class="form-group">
                    <label>CPF</label>
                    <input v-model="newDep.cpf" class="cfg-input" placeholder="000.000.000-00" maxlength="14" @input="mascararCpfDep" />
                  </div>
                  <div class="form-group">
                    <label>Data de nascimento</label>
                    <input type="date" v-model="newDep.data_nasc" class="cfg-input" />
                  </div>
                  <div class="form-group">
                    <label>Parentesco *</label>
                    <select v-model="newDep.parentesco" class="cfg-input">
                      <option value="">Selecione</option>
                      <option value="01">Cônjuge / Companheiro(a)</option>
                      <option value="02">Filho(a)</option>
                      <option value="03">Enteado(a)</option>
                      <option value="09">Sogro(a)</option>
                      <option value="10">Filho(a) inválido(a)</option>
                      <option value="11">Irmão/irmã, neto(a) ou bisneto(a)</option>
                      <option value="12">Pais, avós ou bisavós</option>
                      <option value="99">Outros</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Tipo de Dedução (IRRF)</label>
                    <select v-model="newDep.deducao_irrf" class="cfg-input">
                      <option value="1">Dependente IRRF</option>
                      <option value="2">Pensão alimentícia</option>
                      <option value="0">Sem dedução</option>
                    </select>
                  </div>
                </div>
                <div class="uf-actions" style="margin-top:4px">
                  <button class="modal-cancel" @click="addingDep = false">Cancelar</button>
                  <button class="modal-submit" :disabled="salvandoDep" @click="salvarDependente">
                    <span v-if="salvandoDep" class="btn-spin"></span>
                    <template v-else>💾 Salvar Dependente</template>
                  </button>
                </div>
              </div>

              <button v-if="!addingDep" class="btn-add-dep" @click="iniciarAddDep">+ Adicionar Dependente</button>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- MODAL: Confirmação genérica (BUG-EST-04) -->
    <transition name="modal">
      <div v-if="modalConfirm.visible" class="modal-overlay" @click.self="modalConfirm.visible = false">
        <div class="modal-card" style="max-width:400px">
          <div class="modal-hdr">
            <h3>{{ modalConfirm.titulo }}</h3>
            <button class="modal-close" @click="modalConfirm.visible = false">✕</button>
          </div>
          <div class="modal-body">
            <p style="font-size:14px;color:#475569;margin:0 0 16px">{{ modalConfirm.msg }}</p>
            <div class="modal-actions">
              <button class="modal-cancel" @click="modalConfirm.visible = false">Cancelar</button>
              <button class="modal-submit" style="background:#ef4444" @click="modalConfirm.onConfirm(); modalConfirm.visible = false">Confirmar</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- TOAST -->
    <transition name="toast">
      <div v-if="toast.visible" class="toast" :class="toast.tipo">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()
const funcionario = ref(null)
const holeritesFuncionario = ref([])
const progressao = ref(null)   // Sprint 3a TASK-15
const loading = ref(true)
const erro = ref('')
const loaded = ref(false)
const modal = ref(null)
const modalLoading = ref(false)
const salvando = ref(false)
const editForm = ref(null)
const documentos = ref([])
const historico = reactive({ lotacoes: [], ferias: [], afastamentos: [] })
const escalas = ref([])
const toast = ref({ visible: false, msg: '', tipo: 'ok' })

// ── Confirmação genérica (BUG-EST-04) ─────────────────────────────
const modalConfirm = ref({ visible: false, titulo: '', msg: '', onConfirm: () => {} })
const openConfirm = (titulo, msg, fn) => {
  modalConfirm.value = { visible: true, titulo, msg, onConfirm: fn }
}

// ── Dependentes ──────────────────────────────────────────────────
const dependentes = ref([])
const addingDep = ref(false)
const salvandoDep = ref(false)
const newDep = ref({ nome: '', cpf: '', data_nasc: '', parentesco: '', deducao_irrf: '1' })

const iniciarAddDep = () => {
  newDep.value = { nome: '', cpf: '', data_nasc: '', parentesco: '', deducao_irrf: '1' }
  addingDep.value = true
}

const salvarDependente = async () => {
  if (!newDep.value.nome || !newDep.value.parentesco) {
    showToast('Informe o nome e o parentesco.', 'err'); return
  }
  salvandoDep.value = true
  try {
    const { data } = await api.post(`/api/v3/funcionarios/${route.params.id}/dependentes`, newDep.value)
    dependentes.value.push(data.dependente ?? { ...newDep.value, id: Date.now() })
    addingDep.value = false
    showToast('✅ Dependente salvo!')
  } catch (e) {
    showToast('Erro: ' + (e.response?.data?.erro || e.message), 'err')
  } finally { salvandoDep.value = false }
}

const deletarDependente = (depId) => {
  openConfirm('Excluir Dependente', 'Esta ação não pode ser desfeita. Deseja continuar?', async () => {
    try {
      await api.delete(`/api/v3/funcionarios/${route.params.id}/dependentes/${depId}`)
      dependentes.value = dependentes.value.filter(d => d.id !== depId)
      showToast('Dependente removido.')
    } catch (e) { showToast('Erro ao excluir.', 'err') }
  })
}

const mascararCpfDep = () => {
  let v = newDep.value.cpf.replace(/\D/g, '').slice(0, 11)
  if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4')
  else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3')
  else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/, '$1.$2')
  newDep.value.cpf = v
}

const parentescoLabel = (cod) => ({
  '01': 'Cônjuge/Companheiro(a)', '02': 'Filho(a)', '03': 'Enteado(a)',
  '09': 'Sogro(a)', '10': 'Filho(a) inválido(a)', '11': 'Irmão/irmã, neto(a)',
  '12': 'Pais, avós', '99': 'Outros'
})[cod] ?? cod

// ── Upload de Documentos ─────────────────────────────────────────
const uploadFile = ref(null)
const uploadTipo = ref('Outro')
const uploadNumero = ref('')
const uploadando = ref(false)
const uploadPct = ref(0)

const onFileSelected = (e) => { uploadFile.value = e.target.files[0] || null }
const onDrop = (e) => { uploadFile.value = e.dataTransfer?.files[0] || null }

const uploadDoc = async () => {
  if (!uploadFile.value) return
  uploadando.value = true; uploadPct.value = 0
  try {
    const form = new FormData()
    form.append('arquivo', uploadFile.value)
    form.append('tipo', uploadTipo.value)
    form.append('numero', uploadNumero.value)
    await api.post(`/api/v3/funcionarios/${route.params.id}/documentos`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => { uploadPct.value = Math.round((e.loaded / e.total) * 100) },
    })
    uploadFile.value = null; uploadNumero.value = ''
    // Recarrega lista
    const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/documentos`)
    documentos.value = data
    showToast('✅ Documento enviado com sucesso!')
  } catch (e) {
    showToast('Erro ao enviar: ' + (e.response?.data?.erro || e.message), 'err')
  } finally { uploadando.value = false }
}

const deletarDoc = (docId) => {
  openConfirm('Excluir Documento', 'Excluir permanentemente este documento? Esta ação não pode ser desfeita.', async () => {
    try {
      await api.delete(`/api/v3/funcionarios/${route.params.id}/documentos/${docId}`)
      documentos.value = documentos.value.filter(d => d.id !== docId)
      showToast('Documento excluído.')
    } catch (e) { showToast('Erro ao excluir.', 'err') }
  })
}

// ── Sprint 3 — Adicionais e Vantagens (Parte 10) ──────────────────
const adicionais         = ref([])
const rubricasC2         = ref([])
const loadingAdicionais  = ref(false)
const showFormAdicional  = ref(false)
const salvandoAdicional  = ref(false)
const novoAdicional      = ref({
  rubrica_id: null, tipo: 'fixo', valor: 0,
  vigencia_inicio: new Date().toISOString().slice(0, 10),
  ato_adm: ''
})

const fetchAdicionais = async () => {
  loadingAdicionais.value = true
  try {
    const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/adicionais`)
    adicionais.value = data.adicionais ?? []
    if (rubricasC2.value.length === 0) {
      const r = await api.get('/api/v3/rubricas?camada=2')
      rubricasC2.value = r.data.rubricas ?? []
    }
  } catch { adicionais.value = [] }
  finally { loadingAdicionais.value = false }
}

const salvarAdicional = async () => {
  if (!novoAdicional.value.rubrica_id) {
    showToast('Selecione a rubrica.', 'err'); return
  }
  salvandoAdicional.value = true
  try {
    await api.post(`/api/v3/funcionarios/${route.params.id}/adicionais`, novoAdicional.value)
    showFormAdicional.value = false
    novoAdicional.value = { rubrica_id: null, tipo: 'fixo', valor: 0, vigencia_inicio: new Date().toISOString().slice(0, 10), ato_adm: '' }
    showToast('✅ Adicional salvo!')
    await fetchAdicionais()
  } catch (e) {
    showToast('Erro: ' + (e.response?.data?.erro || e.message), 'err')
  } finally { salvandoAdicional.value = false }
}

const inativarAdicional = (adId) => {
  openConfirm('Inativar Adicional', 'O adicional será desativado e não constará na próxima folha. Confirmar?', async () => {
    try {
      await api.delete(`/api/v3/funcionarios/${route.params.id}/adicionais/${adId}`)
      showToast('Adicional inativado.')
      await fetchAdicionais()
    } catch (e) { showToast('Erro ao inativar.', 'err') }
  })
}

onMounted(async () => {
  await fetchPerfil()
  await fetchAdicionais()
  setTimeout(() => { loaded.value = true }, 80)
})

const fetchPerfil = async () => {
  loading.value = true
  erro.value = ''
  try {
    const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}`)
    funcionario.value = data.funcionario
    holeritesFuncionario.value = data.holerites || []
    progressao.value = data.progressao || null   // Sprint 3a TASK-15
  } catch (err) {
    erro.value = err.response?.data?.message || 'Funcionário não encontrado.'
  } finally {
    loading.value = false
  }
}

const abrirModal = async (tipo) => {
  modal.value = tipo
  if (tipo === 'editar') {
    editForm.value = JSON.parse(JSON.stringify(funcionario.value))
    // Aplica máscara no valor já existente
    if (editForm.value.pessoa?.PESSOA_PIS_PASEP) {
      editForm.value.pessoa.PESSOA_PIS_PASEP = maskPis(editForm.value.pessoa.PESSOA_PIS_PASEP)
    }
    return
  }
  modalLoading.value = true
  try {
    if (tipo === 'documentos') {
      const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/documentos`)
      documentos.value = data
    } else if (tipo === 'historico') {
      const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/historico`)
      Object.assign(historico, data)
    } else if (tipo === 'escalas') {
      const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/escalas`)
      escalas.value = data
    } else if (tipo === 'dependentes') {
      try {
        const { data } = await api.get(`/api/v3/funcionarios/${route.params.id}/dependentes`)
        dependentes.value = data.dependentes ?? []
      } catch { dependentes.value = [] }
      addingDep.value = false
    }
  } catch (e) {
    showToast('Erro ao carregar dados: ' + (e.response?.data?.message || e.message), 'err')
  } finally {
    modalLoading.value = false
  }
}

const salvarEdicao = async () => {
  salvando.value = true
  try {
    await api.put(`/api/v3/funcionarios/${route.params.id}`, editForm.value)
    await fetchPerfil()
    modal.value = null
    showToast('✅ Cadastro atualizado com sucesso!')
  } catch (e) {
    showToast('Erro ao salvar: ' + (e.response?.data?.message || e.message), 'err')
  } finally {
    salvando.value = false
  }
}

const showToast = (msg, tipo = 'ok') => {
  toast.value = { visible: true, msg, tipo }
  setTimeout(() => { toast.value.visible = false }, 4000)
}

const tempoServico = computed(() => {
  if (!funcionario.value?.FUNCIONARIO_DATA_INICIO) return null
  const inicio = new Date(funcionario.value.FUNCIONARIO_DATA_INICIO)
  const fim = funcionario.value.FUNCIONARIO_DATA_FIM ? new Date(funcionario.value.FUNCIONARIO_DATA_FIM) : new Date()
  const meses = Math.floor((fim - inicio) / (1000 * 60 * 60 * 24 * 30.44))
  const anos = Math.floor(meses / 12); const m = meses % 12
  if (anos > 0) return `${anos} ano${anos > 1 ? 's' : ''}${m > 0 ? ` e ${m} mês${m > 1 ? 'es' : ''}` : ''}`
  return `${meses > 0 ? meses + ' mês' + (meses > 1 ? 'es' : '') : 'Menos de 1 mês'}`
})

const iniciais = (nome) => { if (!nome) return '?'; const w = nome.trim().split(' ').filter(Boolean); return w.length >= 2 ? (w[0][0] + w[w.length - 1][0]).toUpperCase() : nome.substring(0, 2).toUpperCase() }
const avatarHue = (id) => (id * 137) % 360
const formatDate = (d) => { try { return d ? new Date(d).toLocaleDateString('pt-BR') : '—' } catch { return d } }
const formatCpf = (v) => { if (!v) return '—'; const n = v.replace(/\D/g, ''); if (n.length === 11) return n.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4'); return v }
const formatMoney = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
const formatCompetencia = (c) => { if (!c || String(c).length !== 6) return c || '—'; const s = String(c); const meses = { '01':'Jan','02':'Fev','03':'Mar','04':'Abr','05':'Mai','06':'Jun','07':'Jul','08':'Ago','09':'Set','10':'Out','11':'Nov','12':'Dez' }; return `${meses[s.substring(4,6)] || s.substring(4,6)} ${s.substring(0,4)}` }
const sexoLabel = (v) => (['Masculino','Feminino','Outro'])[v - 1] || v
const estadoCivilLabel = (v) => (['Solteiro(a)','Casado(a)','Divorciado(a)','Viúvo(a)','União Estável'])[v - 1] || v
const escolaridadeLabel = (v) => ({ 1:'Fundamental Incompleto',2:'Fundamental Completo',3:'Médio Incompleto',4:'Médio Completo',5:'Superior Incompleto',6:'Superior Completo',7:'Pós-Graduação',8:'Mestrado',9:'Doutorado' })[v] || v

const maskPis = (v) => {
  const d = (v || '').replace(/\D/g, '').slice(0, 11)
  if (d.length <= 3)  return d
  if (d.length <= 8)  return `${d.slice(0,3)}.${d.slice(3)}`
  if (d.length <= 10) return `${d.slice(0,3)}.${d.slice(3,8)}.${d.slice(8)}`
  return `${d.slice(0,3)}.${d.slice(3,8)}.${d.slice(8,10)}-${d.slice(10)}`
}
</script>

<style scoped>
.perfil-page { display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; }
.btn-back { display: inline-flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 9px 16px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; align-self: flex-start; transition: all 0.18s; }
.btn-back:hover { border-color: #6366f1; color: #6366f1; background: #f5f3ff; }
.state-box { display: flex; flex-direction: column; align-items: center; padding: 80px 20px; text-align: center; color: #64748b; }
.state-ico { font-size: 48px; margin-bottom: 12px; }
.state-box h3 { font-size: 18px; font-weight: 700; color: #334155; margin: 0 0 16px; }
.spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px; }
.spinner-sm { width: 28px; height: 28px; border: 2px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.retry-btn { background: #6366f1; color: #fff; border: none; border-radius: 10px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; }
.hero { position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 45%, #312e81 100%); border-radius: 22px; padding: 36px 40px; overflow: hidden; opacity: 0; transform: translateY(-10px); transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
.hero.loaded { opacity: 1; transform: none; }
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 300px; height: 300px; background: #6366f1; right: -80px; top: -100px; }
.hs2 { width: 200px; height: 200px; background: #10b981; right: 200px; bottom: -60px; }
.hs3 { width: 150px; height: 150px; background: #f59e0b; left: 40%; top: -50px; }
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
.hero-avatar { width: 80px; height: 80px; border-radius: 20px; flex-shrink: 0; background: hsl(var(--hue) 65% 55%); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 900; color: #fff; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.hero-info { flex: 1; min-width: 0; }
.hero-badges { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.status-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.status-chip.active { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.status-chip.inactive { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.chip-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.matricula-chip { background: rgba(255,255,255,0.1); color: #cbd5e1; font-size: 12px; padding: 4px 12px; border-radius: 999px; }
.hero-nome { font-size: 26px; font-weight: 900; color: #fff; letter-spacing: -0.02em; margin: 0 0 5px; }
.hero-cargo { font-size: 14px; color: #94a3b8; margin: 0; }
.hero-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-left: auto; }
.meta-item { text-align: right; }
.meta-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #60a5fa; margin-bottom: 3px; }
.meta-value { display: block; font-size: 15px; font-weight: 800; color: #fff; }
.perfil-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; opacity: 0; transform: translateY(12px); transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.15s; }
.perfil-grid.loaded { opacity: 1; transform: none; }
.col-left, .col-right { display: flex; flex-direction: column; gap: 16px; }
.info-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; overflow: hidden; }
.anim-card { animation: cardIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) calc(var(--ci) * 60ms + 200ms) both; }
@keyframes cardIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
.info-card:hover { box-shadow: 0 8px 32px -8px rgba(0,0,0,0.1); }
.card-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.card-hdr-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.card-hdr-title { font-size: 15px; font-weight: 800; color: #1e293b; margin: 0; }
.fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { background: #f8fafc; border-radius: 12px; padding: 12px 14px; }
.field-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 4px; }
.field-value { display: block; font-size: 14px; font-weight: 700; color: #1e293b; }
.field-value.mono { font-family: monospace; letter-spacing: 0.05em; }
.no-data { font-size: 13px; color: #94a3b8; text-align: center; padding: 20px 0; }
.no-data-sm { font-size: 13px; color: #94a3b8; padding: 12px 0; }
.timeline { padding: 4px 0; }
.tl-item { display: flex; gap: 14px; align-items: flex-start; }
.tl-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
.tl-dot.active { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.tl-dot.setor { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.2); }
.tl-dot.atual { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); }
.tl-dot.demitido { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.2); }
.tl-line { width: 2px; height: 24px; background: #e2e8f0; margin-left: 5px; }
.tl-content { padding-bottom: 2px; }
.tl-title { display: block; font-size: 13px; font-weight: 700; color: #334155; }
.tl-date { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.holerites-mini { display: flex; flex-direction: column; gap: 8px; }
.holerite-mini-row { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #f8fafc; border-radius: 12px; }
.hm-comp { flex: 1; }
.hm-label { font-size: 13px; font-weight: 700; color: #334155; }
.hm-liquido { font-size: 14px; font-weight: 800; color: #15803d; }
.hm-pdf { padding: 4px 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-size: 11px; font-weight: 700; border-radius: 8px; text-decoration: none; transition: all 0.15s; }
.hm-pdf:hover { background: #1d4ed8; color: #fff; }
.badge-prog { background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; }
.actions-list { display: flex; flex-direction: column; gap: 8px; }
.action-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: 14px; font-weight: 600; transition: all 0.18s; border: 1px solid transparent; cursor: pointer; background: none; width: 100%; text-align: left; }
.action-item .action-arrow { margin-left: auto; opacity: 0; transition: all 0.18s; }
.action-item:hover { transform: translateX(3px); }
.action-item:hover .action-arrow { opacity: 1; }
.action-item.blue { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.action-item.blue:hover { background: #1d4ed8; color: #fff; }
.action-item.green { background: #f0fdf4; color: #166534; border-color: #86efac; }
.action-item.green:hover { background: #166534; color: #fff; }
.action-item.purple { background: #f5f3ff; color: #5b21b6; border-color: #c4b5fd; }
.action-item.purple:hover { background: #5b21b6; color: #fff; }
.action-item.orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.action-item.orange:hover { background: #c2410c; color: #fff; }
.action-item.teal { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.action-item.teal:hover { background: #0f766e; color: #fff; }
.dep-badge { display: inline-flex; align-items: center; justify-content: center; background: #0f766e; color: #fff; border-radius: 99px; font-size: 10px; font-weight: 800; width: 18px; height: 18px; margin-left: 4px; }

/* Dependentes Modal */
.btn-add-dep { padding: 10px 18px; border-radius: 10px; border: 1.5px dashed #6366f1; background: #eef2ff;
  color: #4f46e5; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-add-dep:hover { background: #e0e7ff; }
.dep-list { display: flex; flex-direction: column; gap: 8px; }
.dep-row  { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 14px; }
.dep-row-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.dep-row-nome { font-size: 13px; font-weight: 800; color: #1e293b; }
.dep-row-sub  { font-size: 11px; color: #64748b; }
.dep-irrf-chip { display: inline-flex; align-self: flex-start; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; margin-top: 2px; }
.irrf-sim { background: #dcfce7; color: #166534; }
.irrf-nao { background: #f1f5f9; color: #64748b; }
.dep-form { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }

/* MODAIS */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-card { background: #fff; border-radius: 22px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 64px rgba(0,0,0,0.2); }
.modal-card.modal-lg { max-width: 680px; }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; background: #fff; z-index: 1; }
.modal-hdr h3 { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { border: none; background: #f1f5f9; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; color: #64748b; font-size: 14px; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.modal-desc { font-size: 13px; color: #64748b; margin: 0; }
.modal-loading { display: flex; align-items: center; gap: 10px; padding: 20px 0; color: #64748b; font-size: 13px; }
.modal-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 32px 0; color: #94a3b8; }
.modal-empty span { font-size: 32px; }
.modal-empty p { font-size: 14px; margin: 0; }
.form-section { display: flex; flex-direction: column; gap: 12px; background: #f8fafc; border-radius: 14px; padding: 16px; }
.form-section-title { font-size: 13px; font-weight: 800; color: #475569; margin: 0 0 4px; }
.form-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; }
.cfg-input { border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 9px 13px; font-size: 13px; font-family: inherit; color: #1e293b; background: #fff; outline: none; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.cfg-input:focus { border-color: #6366f1; }
.modal-actions { display: flex; gap: 10px; }
.modal-cancel { flex: 1; padding: 11px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; font-weight: 700; color: #64748b; cursor: pointer; }
.modal-submit { flex: 2; padding: 11px; border-radius: 12px; border: none; background: #6366f1; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.modal-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }

/* DOCUMENTOS */
.doc-list { display: flex; flex-direction: column; gap: 10px; }
.doc-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
.doc-item.doc-req { border-color: #fbbf24; background: #fffbeb; }
.doc-ico { font-size: 20px; }
.doc-info { flex: 1; }
.doc-tipo { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.doc-num { display: block; font-size: 12px; color: #64748b; font-family: monospace; margin-top: 2px; }
.doc-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.dbr { background: #fef3c7; color: #92400e; }
.dbo { background: #f1f5f9; color: #64748b; }
/* Upload */
.upload-zone { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 28px 20px; text-align: center; cursor: pointer; transition: border-color 0.2s; }
.upload-zone:hover { border-color: #6366f1; }
.upload-ico { font-size: 28px; display: block; margin-bottom: 8px; }
.upload-hint { font-size: 13px; color: #475569; margin: 0 0 4px; }
.upload-types { font-size: 11px; color: #94a3b8; }
.upload-form { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.uf-file-name { font-size: 13px; font-weight: 700; color: #1e293b; }
.uf-size { font-weight: 400; color: #94a3b8; }
.uf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.uf-actions { display: flex; gap: 10px; }
.upload-progress { height: 6px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
.up-bar { height: 100%; background: #6366f1; border-radius: 99px; transition: width 0.2s; }
.doc-del { border: none; background: #fef2f2; color: #dc2626; border-radius: 9px; width: 30px; height: 30px; cursor: pointer; font-size: 14px; transition: background 0.15s; }
.doc-del:hover { background: #dc2626; color: #fff; }

/* HISTÓRICO */
.hist-section-title { font-size: 13px; font-weight: 800; color: #475569; margin: 0 0 8px; }
.hist-list { display: flex; flex-direction: column; gap: 8px; }
.hist-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
.hist-item.hi-ativa { border-color: #86efac; background: #f0fdf4; }
.hi-left { display: flex; align-items: center; gap: 10px; }
.hi-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot-green { background: #10b981; }
.dot-gray { background: #94a3b8; }
.dot-blue { background: #3b82f6; }
.dot-red { background: #ef4444; }
.hi-titulo { display: block; font-size: 13px; font-weight: 700; color: #1e293b; }
.hi-sub { display: block; font-size: 11px; color: #64748b; margin-top: 2px; }
.hi-datas { display: flex; gap: 6px; align-items: center; font-size: 12px; color: #64748b; white-space: nowrap; }

/* ESCALAS */
.escala-list { display: flex; flex-direction: column; gap: 6px; }
.escala-hdr-row { display: grid; grid-template-columns: 90px 1fr 100px 120px; gap: 10px; padding: 8px 14px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
.escala-row { display: grid; grid-template-columns: 90px 1fr 100px 120px; gap: 10px; padding: 11px 14px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
.es-data { font-size: 12px; font-weight: 700; color: #334155; }
.es-setor { font-size: 12px; color: #475569; }
.es-turno { font-size: 12px; color: #6366f1; font-weight: 700; }
.es-hora { font-size: 12px; font-family: monospace; color: #64748b; }

/* TOAST */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); padding: 13px 22px; border-radius: 14px; font-size: 14px; font-weight: 600; z-index: 200; box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
.toast.ok { background: #1e293b; color: #fff; }
.toast.err { background: #ef4444; color: #fff; }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.22,1,0.36,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(16px); }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
@media (max-width: 768px) {
  .perfil-grid { grid-template-columns: 1fr; }
  .hero-inner { flex-direction: column; align-items: flex-start; }
  .hero-meta { margin-left: 0; flex-direction: row; gap: 16px; }
  .meta-item { text-align: left; }
  .form-two-col { grid-template-columns: 1fr; }
  .escala-hdr-row, .escala-row { grid-template-columns: 80px 1fr 80px; }
  .es-hora { display: none; }
}
</style>
