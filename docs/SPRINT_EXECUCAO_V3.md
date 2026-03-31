# GENTE v3 — Especificação de Execução
**Para:** Antygravity (agente executor)
**Versão:** 2.1 | **Data:** 30/03/2026
**Leitura obrigatória antes de qualquer ação:**
- `.agent/workflows/regras-gerais.md` (v4.3)
- `docs/MAPA_ESTADO_REAL.md`
- `docs/MAPA_CAMPOS_TABELAS.md` (antes de qualquer INSERT)
- `docs/DESIGN_SYSTEM_GENTE_V3.md` (antes de criar ou modificar qualquer view Vue)

> Execute os blocos em ordem. Confirme cada task antes de avançar.
> Em caso de obstáculo não previsto: PARAR e reportar.

---

## ⚠️ REGRA CRÍTICA — web.php (10.617 linhas)

**NUNCA editar o `routes/web.php` para adicionar endpoints novos.**

O arquivo já tem 10.617 linhas com todo o backend embutido inline.
Todo endpoint novo do Bloco A em diante vai em arquivo separado: `routes/nome_modulo.php`.
O `web.php` recebe apenas um `require` no bloco existente — nada mais.

Refatoração do legado do `web.php` é sprint separada, pós-Bloco A.

---

## 📋 ESTADO REAL DOS BACKENDS — auditoria 30/03/2026

Varredura completa do `web.php` (10.617 linhas) + arquivos em `routes/*.php`.

| Task | Módulo | Backend `/api/v3/` | Status | Ação |
|------|--------|--------------------|--------|------|
| A0 | Proporcional de salário | FolhaParserService.php | ✅ CONCLUÍDA | — |
| A1 | Avaliação — endpoints | `/v3/avaliacoes` GET+POST | ⚠️ PARCIAL | Bug de prefixo: falta `/api` — corrigir |
| A1b | AvaliacaoGestorView.vue | — | 🔴 FALTANDO | Criar view |
| A2 | Benefícios — endpoints | — | 🔴 FALTANDO | Criar `routes/beneficios.php` |
| A2b | BeneficiosAdminView.vue | — | 🔴 FALTANDO | Criar view |
| A3 | Medicina — endpoints servidor | `/api/v3/medicina` GET+POST/agendar | ✅ Existe | Só falta endpoint admin |
| A3b | MedicinaAdminView.vue | — | 🔴 FALTANDO | Criar view + endpoints admin |
| A4 | Segurança do Trabalho — endpoints | — | 🔴 FALTANDO | Criar `routes/seguranca_trabalho.php` |
| A4b | SegurancaAdminView.vue | — | 🔴 FALTANDO | Criar view |
| A5 | Treinamentos — endpoints | — | 🔴 FALTANDO | Criar `routes/treinamentos.php` |
| A5b | TreinamentosAdminView.vue | — | 🔴 FALTANDO | Criar view |
| A6 (pesq) | Pesquisa de Satisfação | — | 🔴 FALTANDO | Criar `routes/pesquisa.php` |
| A6 (ouv) | Ouvidoria — endpoints servidor | `/api/v3/ouvidoria` GET+POST | ✅ Existe | Só falta endpoint admin |
| A6b | Central de Relatórios | — | 🔴 FALTANDO | Criar `routes/relatorios.php` |
| A7 | eSocial XML | UI existe, XML inválido | ⚠️ PARCIAL | Sprint separada pós-Bloco A |

### BUG descoberto: prefixo ausente em `/v3/avaliacoes`

O endpoint de avaliação foi registrado sem o prefixo `/api/`:
```
Route::get('/v3/avaliacoes', ...)   ← ERRADO
Route::get('/api/v3/avaliacoes', ...) ← CORRETO
```
**Corrigir no web.php** (esta é a única exceção à regra acima — é correção de bug, não adição):
- Linha ~10371: `Route::get('/v3/avaliacoes'` → `Route::get('/avaliacoes'` (já está dentro de `prefix('api/v3')`)
- Linha ~10420: `Route::post('/v3/avaliacoes'` → `Route::post('/avaliacoes'`

---

## TASK-PONTO-CONFIG — Corrigir integração PONTO_CONFIG_FUNCIONARIO no motor de apuração

**Auditado em:** 30/03/2026 | **Prioridade:** Alta (funcionalidade existente quebrada silenciosamente)

**Contexto:** O RH já tem UI completa em `ConfiguracoesView.vue` para definir regime de ponto
(2×/4× batidas), horários e intervalo de almoço por funcionário. A tabela `PONTO_CONFIG_FUNCIONARIO`
existe com migration (05/03/2026). Os endpoints GET/PUT `/api/v3/ponto/config` funcionam.
**O problema:** o `ApuracaoPontoService.php` ignora completamente essa tabela no cálculo.

**Lacunas confirmadas:**

| Lacuna | Arquivo | Detalhe |
|--------|---------|---------|
| Coluna `INTERVALO_ALMOCO` ausente | `PONTO_CONFIG_FUNCIONARIO` | Hardcoded em 60min no service e 120min no endpoint |
| Coluna `JORNADA_FINANCEIRA_HORAS` ausente | `PONTO_CONFIG_FUNCIONARIO` | Campo para acordo informal — ver caso de uso abaixo |
| Regime 2×/4× ignorado no cálculo | `ApuracaoPontoService.php` | Sempre conta só 1ª entrada + 1ª saída |
| `PONTO_CONFIG_FUNCIONARIO` não consultada | `ApuracaoPontoService.php` | Service não carrega config individual |
| `FolhaParserService.php` não respeita jornada financeira | `FolhaParserService.php` | Usa sempre a jornada do turno, ignora acordo individual |

### Caso de uso: Acordo de Jornada Diferenciada (admin only)

Situação real: gestão e funcionário têm acordo informal onde o servidor trabalha fisicamente
menos horas (ex: 4h/dia) mas para fins de cálculo de folha e remuneração é tratado como
jornada completa (ex: 8h/dia).

**Regras de negócio:**
- Só admin pode configurar `JORNADA_FINANCEIRA_HORAS` — RH e gestor não têm acesso
- O ponto eletrônico registra e calcula a realidade física (4h) normalmente — sem alteração
- O `FolhaParserService` ao calcular o salário usa `JORNADA_FINANCEIRA_HORAS` no lugar
  da jornada do turno, resultando em salário cheio mesmo com ponto de 4h
- A distinção deve aparecer no holerite como "Jornada Contratual" (financeira) vs
  "Horas Registradas" (físicas) — transparência para auditoria
- Campo `null` = comportamento padrão (jornada financeira = jornada do turno)

**Passos a executar (nesta ordem):**

**Passo 1 — Migration para adicionar colunas faltantes:**
```php
// Criar: database/migrations/YYYY_MM_DD_add_colunas_ponto_config.php
Schema::table('PONTO_CONFIG_FUNCIONARIO', function (Blueprint $table) {
    if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'INTERVALO_ALMOCO')) {
        $table->unsignedSmallInteger('INTERVALO_ALMOCO')->nullable(); // minutos, null = usar turno
    }
    if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_HORAS')) {
        // Jornada para fins de folha (acordo informal admin-only)
        // null = usar jornada real do turno (comportamento padrão)
        // Ex: 8.0 = pagar como 8h mesmo que o ponto registre 4h
        $table->decimal('JORNADA_FINANCEIRA_HORAS', 4, 2)->nullable();
    }
    if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_OBS')) {
        // Obrigatório quando JORNADA_FINANCEIRA_HORAS != null — registra o motivo do acordo
        $table->string('JORNADA_FINANCEIRA_OBS', 500)->nullable();
    }
});
// Rodar: php artisan migrate
```

**Passo 2 — Corrigir `ApuracaoPontoService.php` método `calcular()`:**

Antes do loop `foreach ($porDia as $dia => $batidas)`, carregar a config do funcionário:
```php
// Carregar config individual do funcionário (se existir)
$pontoConfig = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
    ->where('FUNCIONARIO_ID', $funcionarioId)
    ->first();
$regimeConfig   = $pontoConfig->REGIME ?? '4_batidas';
$intervaloConfig = isset($pontoConfig->INTERVALO_ALMOCO) ? (int) $pontoConfig->INTERVALO_ALMOCO : null;
```

Dentro do loop, usar a config individual com fallback para o turno:
```php
// Intervalo: config individual > turno > padrão global 60min
$intervaloMinutos = $intervaloConfig
    ?? (int) ($turno->TURNO_INTERVALO_MINUTOS ?? 60);

// Regime 4 batidas: usar entrada1 e saida2 (ignora saida1/entrada2 do almoço)
// Regime 2 batidas: usar entrada1 e saida1 diretamente
if ($regimeConfig === '4_batidas') {
    $entradas = $batidas->where('REGISTRO_TIPO', 'ENTRADA')->values();
    $saidas   = $batidas->where('REGISTRO_TIPO', 'SAIDA')->values();
    $entrada  = $entradas->first();
    $saida    = $saidas->last(); // ultima saida do dia
} else {
    // 2 batidas — comportamento atual (preservado)
    $entrada = $batidas->firstWhere('REGISTRO_TIPO', 'ENTRADA');
    $saida   = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA');
}
```

**Passo 3 — Corrigir `FolhaParserService.php` para respeitar jornada financeira:**

No método que calcula o salário base, antes de aplicar proporcionalidade:
```php
// Carregar config do funcionário
$pontoConfig = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
    ->first();

// Se há jornada financeira configurada (acordo informal), usar ela para fins de folha
// O ponto físico NÃO é alterado — só o cálculo de remuneração
if ($pontoConfig && $pontoConfig->JORNADA_FINANCEIRA_HORAS) {
    // Substituir a carga horária do turno pela jornada acordada
    $horasJornadaDia = (float) $pontoConfig->JORNADA_FINANCEIRA_HORAS;
    // Log para auditoria
    \Illuminate\Support\Facades\Log::info("Jornada financeira aplicada", [
        'funcionario_id' => $funcionario->FUNCIONARIO_ID,
        'jornada_financeira' => $horasJornadaDia,
        'obs' => $pontoConfig->JORNADA_FINANCEIRA_OBS,
    ]);
}
```

**Passo 4 — Atualizar endpoint admin de config por funcionário:**

O endpoint `PUT /api/v3/ponto/config/funcionarios/{id}` deve:
- Exigir perfil `admin` para salvar `JORNADA_FINANCEIRA_HORAS` (verificar `$user->perfil === 'admin'`)
- Exigir `JORNADA_FINANCEIRA_OBS` preenchida quando `JORNADA_FINANCEIRA_HORAS` não for null
- Retornar erro 403 se não-admin tentar salvar esse campo

**Passo 5 — Atualizar UI `ConfiguracoesView.vue`:**

Na tabela de configuração por funcionário (seção de admin):
- Adicionar coluna "Jornada Financeira (h)" — visível e editável apenas para admin
- Tooltip explicativo: "Jornada para fins de folha. Deixe vazio para usar a jornada real do turno."
- Campo de observação obrigatório quando preenchido
- Badge visual diferenciado na linha do funcionário que tem acordo ativo

**Passo 6 — Atualizar endpoint GET `/api/v3/ponto/config` (individual):**
Adicionar `intervalo_almoco` e `jornada_financeira_horas` no response.

**Critério de aceite:**
- Funcionário com `REGIME=2_batidas` tem cálculo correto sem descontar intervalo de almoço
- Funcionário com `INTERVALO_ALMOCO=30` tem 30min descontados (não 60)
- Funcionário sem config individual usa o `TURNO_INTERVALO_MINUTOS` do turno como fallback
- Funcionário com `JORNADA_FINANCEIRA_HORAS=8.0` e ponto de 4h recebe salário cheio na folha
- Campo `JORNADA_FINANCEIRA_HORAS` só pode ser salvo por admin — retorna 403 para outros perfis
- `JORNADA_FINANCEIRA_OBS` é obrigatório quando `JORNADA_FINANCEIRA_HORAS` está preenchido
- Log de auditoria gerado toda vez que jornada financeira é aplicada no cálculo da folha

---

## 📁 MAPA DE ARQUIVOS routes/ EXISTENTES (auditoria 30/03/2026)

Routes modulares **já existem** para estes módulos — NÃO recriar:

| Arquivo | Módulo |
|---------|--------|
| `acumulacao.php` | Acumulação de cargos |
| `atestados.php` | Atestados médicos |
| `banco_horas.php` | Banco de horas |
| `consignacao.php` | Consignação |
| `contabilidade.php` | ERP Contabilidade |
| `controle_externo.php` | ERP Controle externo |
| `diarias.php` | Diárias |
| `esocial.php` | eSocial |
| `estagiarios.php` | Estagiários |
| `execucao_despesa.php` | ERP Execução despesa |
| `exoneracao.php` | Exoneração |
| `folha.php` | Folha de pagamento |
| `funcionarios.php` | Funcionários |
| `hora_extra.php` | Hora extra |
| `motor.php` | Motor de folha |
| `orcamento.php` | ERP Orçamento |
| `ponto_app.php` | App mobile (JWT) |
| `progressao_funcional.php` | Progressão funcional |
| `pss.php` | PSS |
| `receita_municipal.php` | ERP Receita |
| `rpps.php` | RPPS/IPAM |
| `sagres.php` | SAGRES/TCE-MA |
| `terceirizados.php` | Terceirizados |
| `tesouraria.php` | ERP Tesouraria |
| `transparencia.php` | Transparência pública |
| `verba_indenizatoria.php` | Verba indenizatória |

**Routes a CRIAR para o Bloco A** (ainda não existem):

| Arquivo a criar | Task | Prioridade |
|-----------------|------|-----------|
| *(não criar — bug no web.php foi corrigido)* | A1 Avaliação GET+POST | ✅ corrigido 30/03 |
| `routes/beneficios.php` | A2 Benefícios | 🔴 CRIAR |
| `routes/medicina_admin.php` | A3b endpoints admin ASO/EPI | 🔴 CRIAR |
| `routes/seguranca_trabalho.php` | A4 Segurança do trabalho | 🔴 CRIAR |
| `routes/treinamentos.php` | A5 Treinamentos | 🔴 CRIAR |
| `routes/pesquisa.php` | A6 Pesquisa de satisfação | 🔴 CRIAR |
| `routes/ouvidoria_admin.php` | A6 Ouvidoria admin | 🔴 CRIAR |
| `routes/relatorios.php` | A6b Central de relatórios | 🔴 CRIAR |

---

## TASK-00 — Fix rápido (5 min)

**Arquivo:** `resources/gente-v3/src/views/rh/ConsignacaoView.vue` linha ~9

```
DE:   margem 30% empréstimos + 5% cartão (lei)
PARA: margem 30% empréstimos + 10% cartão (Decreto 57.477/2021)
```

---

## BLOCO A — RH Complementar

*Todas as views já existem. Implementar apenas o backend e conectar.*

---

### TASK-A1 — Avaliação de Desempenho

**View:** `rh/AvaliacaoDesempenhoView.vue` ✅
**Backend:** `routes/` — verificar se existe arquivo; se não, criar `avaliacao_desempenho.php`

**Tabelas necessárias** (verificar se já existem com `Schema::hasTable` antes de criar):
```php
// AVALIACAO_CICLO — ciclos configurados pelo RH
// AVALIACAO — avaliação individual por servidor por ciclo
// AVALIACAO_CRITERIO — critérios com peso
// AVALIACAO_NOTA — nota por critério
```

**Endpoints:**
```
GET  /avaliacao/ciclos                     → lista ciclos (aberto/encerrado)
POST /avaliacao/ciclos                     → criar ciclo
GET  /avaliacao/ciclos/{id}/pendentes      → servidores sem avaliação no ciclo
POST /avaliacao/{funcionario_id}/avaliar   → gestor submete notas
GET  /avaliacao/{funcionario_id}/historico → histórico de avaliações
```

**Integração:** resultado da avaliação alimenta `PROGRESSAO_FUNCIONAL` (nota mínima para progressão).

**Critério de aceite:** gestor submete avaliação com 5 critérios e nota calculada aparece no painel de elegíveis da progressão.

---

### TASK-A2 — Benefícios

**View:** `rh/BeneficiosView.vue` ✅
**Backend:** verificar/criar `routes/beneficios.php`

**Tabelas:**
```php
// BENEFICIO — catálogo (plano saúde, VT, VR, auxílio-creche, auxílio-funeral)
// FUNCIONARIO_BENEFICIO — benefícios ativos por servidor com vigência e dependentes
```

**Endpoints:**
```
GET  /beneficios/catalogo                  → lista de benefícios disponíveis
GET  /beneficios/{funcionario_id}          → benefícios ativos do servidor
POST /beneficios/{funcionario_id}          → incluir benefício
DELETE /beneficios/{id}                    → excluir
GET  /beneficios/relatorio                 → custo total por benefício/secretaria
```

**Integração:** desconto de VT e plano de saúde deve entrar em `LANCAMENTO_FOLHA` no fechamento.

**Critério de aceite:** incluir VT para servidor e desconto aparece no próximo holerite.

---

### TASK-A3 — Medicina do Trabalho + Segurança do Trabalho

**Views:** `rh/MedicinaTrabalhoView.vue` ✅ e `rh/SegurancaTrabalhoView.vue` ✅
**Backend:** verificar/criar `routes/medicina_trabalho.php` e `routes/seguranca_trabalho.php`

**Tabelas:**
```php
// ASO — Atestado de Saúde Ocupacional (exame periódico)
// EPI_REGISTRO — EPIs entregues por servidor
// LAUDO_PERICULOSIDADE — laudos por cargo/setor
// ACIDENTE_TRABALHO — CAT integrada ao eSocial SST
```

**Endpoints medicina:**
```
GET  /medicina/asos/{funcionario_id}       → histórico de exames
POST /medicina/asos                        → registrar novo ASO
GET  /medicina/vencidos                    → ASOs vencidos ou próximos do vencimento (alerta)
```

**Endpoints segurança:**
```
GET  /seguranca/epis/{funcionario_id}      → EPIs do servidor
POST /seguranca/epis                       → registrar entrega de EPI
GET  /seguranca/laudos                     → laudos de insalubridade/periculosidade
POST /seguranca/acidentes                  → registrar acidente (gera dados para CAT eSocial)
```

**Critério de aceite:** registrar ASO e alerta aparece no painel quando vencer em 30 dias.

---

### TASK-A4 — Treinamentos e Capacitações

**View:** `rh/TreinamentosView.vue` ✅
**Backend:** verificar/criar `routes/treinamentos.php`

**Tabelas:**
```php
// TREINAMENTO — catálogo de cursos (nome, carga horária, modalidade, instrutor)
// TREINAMENTO_INSCRICAO — servidor inscrito em curso
// TREINAMENTO_PRESENCA — controle de presença e nota
// TREINAMENTO_CERTIFICADO — registro de certificado emitido
```

**Endpoints:**
```
GET  /treinamentos                         → catálogo
POST /treinamentos                         → cadastrar curso
POST /treinamentos/{id}/inscrever          → inscrever servidor(es)
POST /treinamentos/{id}/concluir           → registrar conclusão + gerar certificado
GET  /treinamentos/{funcionario_id}/historico → histórico de capacitações do servidor
GET  /treinamentos/{id}/certificado/pdf    → gerar PDF do certificado via DomPDF
```

**Critério de aceite:** servidor conclui curso e GET /certificado/pdf retorna PDF com nome e carga horária.

---

### TASK-A5 — Pesquisa de Satisfação + Ouvidoria

**Views:** `rh/PesquisaAdminView.vue`, `rh/PesquisaSatisfacaoView.vue`, `rh/OuvidoriaAdminView.vue` ✅
**Backend:** verificar/criar `routes/pesquisa.php` e `routes/ouvidoria.php`

**Tabelas pesquisa:**
```php
// PESQUISA — configuração (título, período, público-alvo)
// PESQUISA_PERGUNTA — perguntas com tipo (NPS 0-10, múltipla escolha, aberta)
// PESQUISA_RESPOSTA — resposta anônima por servidor
```

**Tabelas ouvidoria:**
```php
// OUVIDORIA_MANIFESTACAO — (categoria, texto, anônimo, urgência, protocolo, status)
// OUVIDORIA_RESPOSTA — resposta do RH com data e responsável
```

**Endpoints pesquisa:**
```
POST /pesquisas                            → criar pesquisa
GET  /pesquisas/{id}/responder             → formulário para o servidor
POST /pesquisas/{id}/responder             → submeter respostas
GET  /pesquisas/{id}/resultados            → NPS, promotores, neutros, detratores
```

**Endpoints ouvidoria:**
```
POST /ouvidoria                            → servidor envia manifestação (gera protocolo)
GET  /ouvidoria/admin                      → RH lista todas com status
PATCH /ouvidoria/{id}/responder            → RH responde
GET  /ouvidoria/protocolo/{num}            → servidor consulta status pelo protocolo
```

**Critério de aceite:** manifestação anônima cria protocolo; RH consegue responder sem ver o nome do autor.

---

### TASK-A6 — Central de Relatórios

**View:** `relatorios/RelatoriosView.vue` ✅
**Backend:** verificar/criar endpoints em `routes/relatorios.php`

**Relatórios prioritários:**
```
GET /relatorios/quadro-servidores          → cargo, setor, vínculo, admissão — CSV/Excel
GET /relatorios/folha/{competencia}        → proventos e descontos — CSV
GET /relatorios/atestados/{periodo}        → afastamentos por CID
GET /relatorios/banco-horas               → saldo por secretaria
GET /relatorios/progressao-elegiveis      → elegíveis na rodada atual
GET /relatorios/esocial-consistencia      → inconsistências cadastrais eSocial
GET /relatorios/custo-secretaria          → custo de pessoal por unidade
GET /relatorios/lrf-pessoal/{ano}         → gasto com pessoal vs RCL (LRF art. 19/20)
```

Todos retornam JSON para exibição na view e aceitam `?formato=csv` para download com BOM UTF-8.

**Critério de aceite:** GET /quadro-servidores retorna lista com todos os servidores ativos.

---

### TASK-A7 — eSocial XML válido

**TASK-A0 — Proporcional de salário no motor de folha (BUG crítico)**

> Incluir antes do BLOCO A pois impacta o motor existente.

**Arquivo:** `app/Services/FolhaParserService.php` — método `calcularRubricas()`

**Problema confirmado:** o motor recebe `$diasTrabalhados` da escala de ponto, mas nunca verifica
se o servidor foi admitido ou exonerado no mês de competência. Servidor admitido no dia 15
recebe salário integral; servidor exonerado no dia 10 também.

**Corrigir no início do método `calcularRubricas()`, após carregar `$funcionario`:**

```php
// Ajuste proporcional por admissão ou exoneração no mês de competência
if ($competencia) {
    $ano = (int) substr($competencia, 0, 4);
    $mes = (int) (strlen($competencia) === 6 ? substr($competencia, 4, 2) : substr($competencia, 5, 2));
    $inicioMes = Carbon::create($ano, $mes, 1);
    $fimMes    = $inicioMes->copy()->endOfMonth();

    $dataAdmissao  = $funcionario->FUNCIONARIO_DATA_INICIO
                     ? Carbon::parse($funcionario->FUNCIONARIO_DATA_INICIO) : null;
    $dataExoneracao = $funcionario->FUNCIONARIO_DATA_FIM
                     ? Carbon::parse($funcionario->FUNCIONARIO_DATA_FIM) : null;

    // Admitido neste mês → contar a partir da admissão até o fim do mês
    if ($dataAdmissao && $dataAdmissao->format('Ym') === $competencia) {
        $diasTrabalhados = $dataAdmissao->diffInDays($fimMes) + 1;
    }

    // Exonerado neste mês → contar do início do mês até a exoneração
    if ($dataExoneracao && $dataExoneracao->format('Ym') === $competencia) {
        $diasTrabalhados = $inicioMes->diffInDays($dataExoneracao) + 1;
    }
}
```

**Adicionar `use Carbon\Carbon;` no topo do arquivo se ainda não estiver.**

**Casos cobertos:**
- Mês cheio: `$diasTrabalhados` vem da escala (comportamento atual preservado)
- Admissão no mês: recalculado pelos dias reais a partir da data de admissão
- Exoneração no mês: recalculado pelos dias até a data de saída
- Admissão E exoneração no mesmo mês: recalculado pelo período real (raríssimo, mas tratado)

**Proporcional de 13º salário** (para rescisão): adicionar campo `DETALHE_FOLHA_DECIMO_TERCEIRO`
em `DETALHE_FOLHA` via migration, calculado como:
`(salario / 12) × meses_trabalhados_no_ano` — incluir quando TASK-C2 (Execução Despesa) for feita,
pois rescisão precisa empenho automático.

**Critério de aceite:**
- Servidor admitido dia 16/03: proventos = salario / 31 × 16 (não salário integral)
- Servidor exonerado dia 10/03: proventos = salario / 31 × 10
- Servidor com mês cheio: comportamento idêntico ao atual

---

### TASK-A7 — eSocial XML válido

**View:** `rh/ESocialView.vue` ✅ (391 linhas)
**Arquivo:** `routes/esocial.php`

**Bugs ativos confirmados:**
- BUG-ESOCIAL-01: `XML_GERADO` é um comentário HTML `<!-- ... -->` — não é XML válido
- BUG-ESOCIAL-02: JOIN usa `p.CPF` (não existe) → corrigir para `p.PESSOA_CPF_NUMERO`

**Eventos prioritários a implementar:**

| Evento | Quando gerar |
|--------|-------------|
| S-2200 | Admissão / atualização cadastral |
| S-2206 | Alteração de cargo ou jornada |
| S-2299 | Desligamento |
| S-1200 | Remunerações — no fechamento da folha |

**Estrutura mínima S-1200 válida:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v02_01_00">
  <evtRemun Id="ID_EVENTO">
    <ideEvento><indRetif>1</indRetif><perApur>2026-03</perApur></ideEvento>
    <ideEmpregador><tpInsc>1</tpInsc><nrInsc>06205473</nrInsc></ideEmpregador>
    <ideVinculo><cpfTrab>...</cpfTrab></ideVinculo>
    <dmDev>
      <codCateg>301</codCateg>
      <infoPerApur><ideEstabLot><remunPerApur>
        <vrTotCont>...</vrTotCont>
      </remunPerApur></ideEstabLot></infoPerApur>
    </dmDev>
  </evtRemun>
</eSocial>
```

**Validar com:** `simplexml_load_string($xml)` — se retornar false, o XML está inválido.

**Critério de aceite:** `GET /esocial/gerar/S-1200/{competencia}` retorna XML que passa em `simplexml_load_string`.


---

### TASK-A1b — View Gestor: Avaliação de Desempenho

**Criar:** `resources/gente-v3/src/views/rh/AvaliacaoGestorView.vue`
**Rota:** `/avaliacao-gestor` | **Sidebar:** seção "Minha Equipe" | **Roles:** `['admin','rh','gestor']`

**UX decidido — não inventar:**
- Hero: título "Avaliações da Equipe", KPIs: total pendentes / ciclo ativo / média do setor
- Ação primária: botão "Avaliar Servidor" no hero — abre modal de seleção de servidor
- Tab padrão ao abrir: "Pendentes" (servidores sem avaliação no ciclo ativo)
- Fluxo de avaliação: clicar em servidor → abre painel lateral com os critérios (não página nova)
- Ao submeter: toast "Avaliação registrada" + servidor sai da lista de pendentes
- Estado vazio de pendentes: "Todos os servidores do seu setor já foram avaliados neste ciclo ✓"

**Tabs:**
1. **Pendentes** — lista de servidores sem avaliação, ordenados por nome. Botão "Avaliar" em cada linha
2. **Avaliar** — formulário com critérios configuráveis (vem da API), nota por estrelas, obs geral, botão "Submeter"
3. **Histórico** — avaliações já enviadas pelo gestor logado, filtro por servidor e ciclo

---

### TASK-A2b — View Admin RH: Benefícios

**Criar:** `resources/gente-v3/src/views/rh/BeneficiosAdminView.vue`
**Rota:** `/beneficios-admin` | **Sidebar:** seção "Financeiro e Folha" | **Roles:** `['admin','rh']`

**UX decidido — não inventar:**
- Hero: título "Gestão de Benefícios", KPIs: solicitações pendentes / custo total do mês / benefícios ativos
- Ação primária: botão "Novo Benefício" no catálogo — abre modal de cadastro
- Tab padrão ao abrir: "Solicitações" (onde há ação urgente — aprovações pendentes)
- Aprovação/rejeição: inline na lista com botões verde/vermelho — não abre nova página
- Ao aprovar: toast + linha some da lista de pendentes
- Ao rejeitar: obrigatório preencher campo "motivo" antes de confirmar

**Tabs:**
1. **Solicitações** — lista de solicitações pendentes. Colunas: servidor, benefício, data, valor. Botões Aprovar / Rejeitar
2. **Catálogo** — CRUD de benefícios disponíveis: nome, ícone, valor, custo desconto, fornecedor
3. **Relatório** — custo total por benefício e por secretaria. Botão "Exportar CSV"

---

### TASK-A3b — View Admin SESMT: Medicina do Trabalho

**Criar:** `resources/gente-v3/src/views/rh/MedicinaAdminView.vue`
**Rota:** `/medicina-admin` | **Sidebar:** seção "Saúde Ocupacional" | **Roles:** `['admin','rh']`

**UX decidido — não inventar:**
- Hero: título "Medicina do Trabalho", KPIs com semáforo colorido: 🟢 Em dia / 🟡 Vence em 90 dias / 🔴 Vencidos
- Ação primária: botão "Registrar Exame" no hero
- Tab padrão ao abrir: "Vencidos" — lista de ação imediata, não o painel geral
- Registrar exame: modal (não página nova) com campos servidor, tipo, data, médico, validade
- Ao registrar: servidor atualiza de vermelho para verde no painel automaticamente

**Tabs:**
1. **Painel** — todos os servidores em cards coloridos por status de ASO. Filtro por secretaria
2. **Vencidos** — lista urgente com botão "Notificar Servidor" em cada linha
3. **Registrar Exame** — formulário completo de ASO
4. **PCMSO** — relatório anual exportável em PDF

---

### TASK-A4b — View Admin SESMT: Segurança do Trabalho

**Criar:** `resources/gente-v3/src/views/rh/SegurancaAdminView.vue`
**Rota:** `/seguranca-admin` | **Sidebar:** seção "Saúde Ocupacional" | **Roles:** `['admin','rh']`

**UX decidido — não inventar:**
- Hero: título "Segurança do Trabalho", KPIs: EPIs vencidos / CATs abertas / NRs em atenção
- Ação primária: botão "Registrar Entrega de EPI" no hero
- Tab padrão ao abrir: "EPIs" — é o fluxo mais frequente do SESMT
- Entrega de EPI: modal com campos servidor, item, quantidade, CA, validade — após salvar mostra recibo para impressão
- CAT: ao registrar acidente, formulário completo com botão "Gerar dados eSocial S-2210" ao final

**Tabs:**
1. **EPIs** — registro de entregas + painel de vencidos/a vencer por servidor
2. **Laudos** — cadastro de insalubridade/periculosidade por cargo. Tabela editável
3. **CATs** — acidentes registrados com status. Botão "Gerar XML eSocial" por linha
4. **Conformidade NR** — dashboard com barra de progresso por norma e data da última revisão

---

### TASK-A5b — View Admin RH: Treinamentos

**Criar:** `resources/gente-v3/src/views/rh/TreinamentosAdminView.vue`
**Rota:** `/treinamentos-admin` | **Sidebar:** seção "Desenvolvimento" | **Roles:** `['admin','rh']`

**UX decidido — não inventar:**
- Hero: título "Gestão de Treinamentos", KPIs: cursos ativos / inscrições pendentes / certificados emitidos no ano
- Ação primária: botão "Novo Curso" no hero — abre modal de cadastro
- Tab padrão ao abrir: "Cursos" (visão geral do catálogo)
- Inscrever servidor: na tab Turmas, botão "Inscrever" abre modal com busca de servidor por nome/matrícula
- Emissão de certificado: na tab Certificados, botão "Emitir PDF" gera download automático
- Ao encerrar turma sem marcar presença: alerta "X servidores ainda sem presença registrada"

**Tabs:**
1. **Cursos** — catálogo com CRUD. Cards com nome, área, carga, vagas, próxima data
2. **Turmas** — por curso: lista de inscritos, marcar presença/ausência, registrar nota final
3. **Certificados** — servidores que concluíram. Botão "Emitir PDF" individual ou "Emitir em Lote"
4. **Relatório** — horas por servidor e por setor, exportar CSV

---

## BLOCO B — Gestão de Consignatárias

*Módulo novo completo — view + backend + tabelas.*

### TASK-B1 — Migration

**Arquivo:** `database/migrations/2026_03_24_000001_create_consignatarias_tables.php`

```php
// CONSIGNATARIA — operadoras cadastradas
Schema::create('CONSIGNATARIA', function (Blueprint $table) {
    $table->increments('CONSIGNATARIA_ID');
    $table->string('CONSIGNATARIA_NOME', 100);
    $table->string('CONSIGNATARIA_CNPJ', 14)->nullable();
    $table->string('CONSIGNATARIA_TIPO', 30)->default('BANCO'); // BANCO|SINDICATO|COOPERATIVA|CARTAO
    $table->boolean('CONSIGNATARIA_ATIVA')->default(true);
    $table->timestamps();
});

// LAYOUT_CONSIGNATARIA — formato de arquivo por operadora
Schema::create('LAYOUT_CONSIGNATARIA', function (Blueprint $table) {
    $table->increments('LAYOUT_ID');
    $table->unsignedInteger('CONSIGNATARIA_ID');
    $table->string('LAYOUT_NOME', 50);        // ex: NEOCONSIG_DEBITOS
    $table->string('LAYOUT_DIRECAO', 10);     // ENTRADA | SAIDA
    $table->integer('LAYOUT_TAMANHO_LINHA')->default(115);
    $table->string('LAYOUT_ENCODING', 20)->default('UTF-8');
    $table->json('LAYOUT_MAPA_COLUNAS')->nullable();
    // ex: {"matricula":[1,8],"valor_parcela":[38,49],"id_operacao":[101,115]}
    $table->timestamps();
});

// CONSIG_REMESSA — cabeçalho de cada arquivo processado
Schema::create('CONSIG_REMESSA', function (Blueprint $table) {
    $table->increments('REMESSA_ID');
    $table->unsignedInteger('CONSIGNATARIA_ID');
    $table->unsignedInteger('LAYOUT_ID');
    $table->string('REMESSA_TIPO', 20);       // DEBITOS|RETORNO|CADASTRO|FINANCEIRO
    $table->string('REMESSA_DIRECAO', 10);    // ENTRADA | SAIDA
    $table->string('REMESSA_COMPETENCIA', 6)->nullable(); // AAAAMM
    $table->string('REMESSA_STATUS', 20)->default('PROCESSADO');
    $table->integer('REMESSA_TOTAL_LINHAS')->default(0);
    $table->integer('REMESSA_ERROS')->default(0);
    $table->text('REMESSA_LOG')->nullable();
    $table->string('REMESSA_ARQUIVO_NOME', 200)->nullable();
    $table->timestamps();
});
```

Após criar: `php artisan migrate`

### TASK-B2 — Seed Neoconsig

**Arquivo:** `database/seeders/ConsignatariaNeoconsigSeeder.php`

```php
$id = DB::table('CONSIGNATARIA')->insertGetId([
    'CONSIGNATARIA_NOME' => 'Neoconsig', 'CONSIGNATARIA_TIPO' => 'BANCO',
    'CONSIGNATARIA_ATIVA' => true, 'created_at' => now(), 'updated_at' => now(),
]);
$layouts = [
    ['NEOCONSIG_DEBITOS',      'ENTRADA', 115, '{"matricula":[1,8],"valor_parcela":[38,49],"id_operacao":[101,115]}'],
    ['NEOCONSIG_RETFINANCEIRO','ENTRADA',  66, '{"competencia":[11,16],"matricula":[17,30],"valor":[35,49],"id_operacao":[52,66]}'],
    ['NEOCONSIG_RETQUITADAS',  'ENTRADA',  66, '{"competencia":[11,16],"matricula":[17,30],"valor":[35,49],"id_operacao":[52,66]}'],
    ['NEOCONSIG_CADASTRO',     'SAIDA',   523, null],
    ['NEOCONSIG_FINANCEIRO',   'SAIDA',    66, '{"matricula":[17,30],"rubrica":[31,34],"valor":[35,49],"id_operacao":[52,66]}'],
];
foreach ($layouts as [$nome, $dir, $tam, $mapa]) {
    DB::table('LAYOUT_CONSIGNATARIA')->insert([
        'CONSIGNATARIA_ID' => $id, 'LAYOUT_NOME' => $nome, 'LAYOUT_DIRECAO' => $dir,
        'LAYOUT_TAMANHO_LINHA' => $tam, 'LAYOUT_MAPA_COLUNAS' => $mapa,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
```

### TASK-B3 — Backend (routes/consignatarias.php)

Registrar em `web.php` dentro do grupo `api/v3 + auth`:
```php
require __DIR__ . '/consignatarias.php';
```

Endpoints:
```
GET  /consignatarias                        → lista operadoras ativas
POST /consignatarias                        → cadastrar nova operadora
POST /consignatarias/{id}/importar          → upload + processar arquivo
GET  /consignatarias/{id}/gerar/{tipo}      → gerar arquivo (financeiro|cadastro|retorno-quitadas|retorno-pendentes)
GET  /consignatarias/{id}/historico         → remessas com status
GET  /consignatarias/margem/{funcionario_id}→ margem disponível por servidor
```

Spec completa Neoconsig (posições exatas de coluna): ver `docs/PLANO_SPRINTS.md` seção Sprint 3, TASK-10a a TASK-10f.

**Critério de aceite:**
- POST /importar com arquivo DEBITOS real → contratos criados em CONSIG_CONTRATO
- GET /gerar/financeiro → download 66 chars/linha, valor sem separador 2 decimais

### DECISÃO DE DESIGN — LAYOUT_MAPEAMENTO (registrada 30/03/2026)

**Decisão:** O campo `LAYOUT_MAPEAMENTO` (JSON) permanece como modelo de dados.
O admin **nunca edita JSON diretamente**. A interface deve exibir uma tabela editável
de campos (Nome, Posição Início, Posição Fim, Tipo, Obrigatório) que serializa/
deserializa o JSON de forma transparente.

**Task derivada:** `B-LAYOUT-EDITOR` — substituir o `<textarea>` atual da aba Layouts
em `ConsignatariasView.vue` por tabela editável com `+ Adicionar Campo`.
**Timing:** implementar antes do go-live com usuários de RH. Não bloqueante para B-IMP/B-GER.

---

### TASK-B4 — View (ConsignatariasView.vue)

Criar `resources/gente-v3/src/views/rh/ConsignatariasView.vue` com 4 tabs:
- Operadoras: lista + cadastro
- Importar: upload de arquivo + log de resultado
- Gerar: selecionar competência + tipo → download
- Histórico: remessas com status

Adicionar na sidebar (DashboardLayout.vue, seção "Financeiro e Folha") e no router.

---

## BLOCO C — ERP Financeiro

*Views existem. Implementar backends e integrar com folha.*

### TASK-C1 — Orçamento Público

**View:** `financeiro/OrcamentoView.vue` ✅ (192 linhas — tabs LOA/PPA/Execução)

**Migration** `2026_03_24_000002_create_orcamento_tables.php`:
```php
Schema::create('UNIDADE_ORCAMENTARIA', function (Blueprint $table) {
    $table->increments('UO_ID');
    $table->string('UO_CODIGO', 10);   // ex: 02.01
    $table->string('UO_NOME', 150);
    $table->unsignedInteger('UO_PAI_ID')->nullable();
    $table->boolean('UO_ATIVA')->default(true);
    $table->timestamps();
});
Schema::create('DOTACAO', function (Blueprint $table) {
    $table->increments('DOTACAO_ID');
    $table->integer('DOTACAO_ANO');
    $table->unsignedInteger('UO_ID');
    $table->string('DOTACAO_FUNCIONAL', 20);  // ex: 04.122.0001.2001
    $table->string('DOTACAO_ELEMENTO', 10);   // ex: 3.1.90.11
    $table->string('DOTACAO_DESCRICAO', 200)->nullable();
    $table->decimal('DOTACAO_INICIAL', 15, 2)->default(0);
    $table->decimal('DOTACAO_SUPLEMENTADA', 15, 2)->default(0);
    $table->decimal('DOTACAO_REDUZIDA', 15, 2)->default(0);
    $table->timestamps();
});
```

**Endpoints (routes/orcamento.php):**
```
GET  /orcamento/loa/{ano}          → dotações do ano agrupadas por UO
POST /orcamento/dotacoes           → criar dotação
PUT  /orcamento/dotacoes/{id}      → editar
GET  /orcamento/execucao/{ano}     → dotação vs empenhado vs liquidado vs pago
POST /orcamento/suplementacao      → suplementação/redução
```

---

### TASK-C2 — Execução da Despesa

**View:** `financeiro/ExecucaoDespesaView.vue` ✅ (175 linhas)

**Migration** `2026_03_24_000003_create_despesa_tables.php`:
```php
Schema::create('CREDOR', function (Blueprint $table) {
    $table->increments('CREDOR_ID');
    $table->string('CREDOR_CNPJ_CPF', 14);
    $table->string('CREDOR_NOME', 150);
    $table->string('CREDOR_TIPO', 15)->default('FORNECEDOR');
    $table->timestamps();
});
Schema::create('EMPENHO', function (Blueprint $table) {
    $table->increments('EMPENHO_ID');
    $table->string('EMPENHO_NUMERO', 20);
    $table->integer('EMPENHO_ANO');
    $table->unsignedInteger('DOTACAO_ID');
    $table->unsignedInteger('CREDOR_ID')->nullable();
    $table->decimal('EMPENHO_VALOR', 15, 2);
    $table->string('EMPENHO_TIPO', 15)->default('ORDINARIO');
    $table->string('EMPENHO_STATUS', 15)->default('EMITIDO');
    $table->string('EMPENHO_HISTORICO', 500)->nullable();
    $table->date('EMPENHO_DATA');
    $table->timestamps();
});
Schema::create('LIQUIDACAO', function (Blueprint $table) {
    $table->increments('LIQ_ID');
    $table->unsignedInteger('EMPENHO_ID');
    $table->decimal('LIQ_VALOR', 15, 2);
    $table->date('LIQ_DATA');
    $table->string('LIQ_DOCUMENTO', 50)->nullable();
    $table->timestamps();
});
Schema::create('ORDEM_PAGAMENTO', function (Blueprint $table) {
    $table->increments('OP_ID');
    $table->unsignedInteger('LIQ_ID');
    $table->decimal('OP_VALOR', 15, 2);
    $table->date('OP_DATA');
    $table->string('OP_FORMA', 20)->default('TED');
    $table->string('OP_STATUS', 15)->default('PENDENTE');
    $table->timestamps();
});
```

**Endpoints (routes/execucao_despesa.php):**
```
GET  /despesa/empenhos                  → lista com filtros
POST /despesa/empenhos                  → emitir (valida saldo orçamentário)
POST /despesa/empenhos/{id}/liquidar    → registrar liquidação
POST /despesa/liquidacoes/{id}/pagar    → emitir OP
POST /despesa/empenhos/{id}/anular      → anular com motivo
```

**Integração folha (em ProcessarFolhaJob):** criar EMPENHO automaticamente no fechamento com `EMPENHO_TIPO = ESTIMATIVO` e historico "Folha competência AAAAMM".


---

### TASK-C3 — Contabilidade PCASP (maior, mais crítico)

**View:** `financeiro/ContabilidadeView.vue` ✅ (189 linhas)

**Migration** `2026_03_24_000004_create_contabilidade_tables.php`:
```php
Schema::create('PCASP_CONTA', function (Blueprint $table) {
    $table->increments('CONTA_ID');
    $table->string('CONTA_CODIGO', 20);       // ex: 3.1.1.1.01
    $table->string('CONTA_NOME', 200);
    $table->string('CONTA_NATUREZA', 10);     // DEVEDORA | CREDORA
    $table->string('CONTA_NIVEL', 10);        // 1|2|3|4|ANALITICA
    $table->string('CONTA_GRUPO', 20);        // ATIVO|PASSIVO|PL|VARIACAO|CONTROLE
    $table->unsignedInteger('CONTA_PAI_ID')->nullable();
    $table->boolean('CONTA_ACEITA_LANCAMENTO')->default(false);
    $table->timestamps();
});
Schema::create('LANCAMENTO_CONTABIL', function (Blueprint $table) {
    $table->increments('LANCAMENTO_ID');
    $table->string('LANCAMENTO_NUMERO', 20);
    $table->date('LANCAMENTO_DATA');
    $table->string('LANCAMENTO_HISTORICO', 500);
    $table->string('LANCAMENTO_ORIGEM', 30)->nullable(); // FOLHA_PAGAMENTO|EMPENHO|MANUAL
    $table->unsignedInteger('ORIGEM_ID')->nullable();
    $table->string('LANCAMENTO_STATUS', 15)->default('ATIVO');
    $table->timestamps();
});
Schema::create('PARTIDA_CONTABIL', function (Blueprint $table) {
    $table->increments('PARTIDA_ID');
    $table->unsignedInteger('LANCAMENTO_ID');
    $table->unsignedInteger('CONTA_ID');
    $table->string('PARTIDA_TIPO', 7); // DEBITO | CREDITO
    $table->decimal('PARTIDA_VALOR', 15, 2);
    $table->timestamps();
});
```

**Seed PCASP mínimo** (`database/seeders/PcaspSeeder.php`):
Contas obrigatórias para folha:
- `3.1.1.1.01` Vencimentos e Vantagens Fixas (DEVEDORA, ANALITICA)
- `3.1.2.1.01` Contribuição Patronal IPAM (DEVEDORA, ANALITICA)
- `2.1.3.1.01` Salários e Vantagens a Pagar (CREDORA, ANALITICA)
- `2.1.3.2.01` RPPS/IPAM a Recolher (CREDORA, ANALITICA)
- `2.1.3.3.01` IRRF Folha a Recolher (CREDORA, ANALITICA)
- Mais contas pai para fechar a hierarquia (grupos 1, 2, 3 até nível 4)

**Criar `app/Services/ContabilidadeService.php`:**
Método `lancarFolha(int $folhaId)`:
1. Buscar proventos e descontos do DETALHE_FOLHA
2. Criar LANCAMENTO_CONTABIL com ORIGEM = FOLHA_PAGAMENTO
3. Inserir PARTIDA_CONTABIL: D 3.1.1.1.01 / C 2.1.3.1.01 (vencimentos)
4. Inserir PARTIDA_CONTABIL: D 3.1.2.1.01 / C 2.1.3.2.01 (RPPS patronal)
5. Validar partida dupla: SUM(DEBITOS) == SUM(CREDITOS) — rejeitar se desequilibrado

Integrar em `ProcessarFolhaJob`: chamar `ContabilidadeService::lancarFolha($folhaId)` após fechar.

**Endpoints (routes/contabilidade.php):**
```
GET  /contabilidade/balancete/{ano}/{mes}      → saldos por conta
GET  /contabilidade/pcasp                      → plano hierárquico
POST /contabilidade/lancamentos                → lançamento manual
GET  /contabilidade/lancamentos                → lista com filtros
POST /contabilidade/lancamentos/{id}/estornar  → estorno
GET  /contabilidade/export/sinc-folha/{comp}   → XML SAGRES/SINC-Folha (XML válido — não comentário HTML)
```

**Critério de aceite:**
- Fechamento de folha gera 3 lançamentos contábeis automaticamente
- GET /balancete retorna débitos = créditos (partida dupla equilibrada)
- Motor rejeita lançamento desequilibrado com erro 422

---

### TASK-C4 — Tesouraria

**View:** `financeiro/TesourariaView.vue` ✅ (304 linhas)

**Migration** `2026_03_24_000005_create_tesouraria_tables.php`:
```php
Schema::create('CONTA_BANCARIA', function (Blueprint $table) {
    $table->increments('CB_ID');
    $table->string('CB_BANCO', 3);
    $table->string('CB_BANCO_NOME', 100);
    $table->string('CB_AGENCIA', 10);
    $table->string('CB_CONTA', 20);
    $table->string('CB_TIPO', 20)->default('CORRENTE');
    $table->decimal('CB_SALDO_INICIAL', 15, 2)->default(0);
    $table->date('CB_SALDO_DATA');
    $table->boolean('CB_ATIVA')->default(true);
    $table->timestamps();
});
Schema::create('MOVIMENTO_BANCARIO', function (Blueprint $table) {
    $table->increments('MB_ID');
    $table->unsignedInteger('CB_ID');
    $table->date('MB_DATA');
    $table->string('MB_TIPO', 10);       // CREDITO | DEBITO
    $table->decimal('MB_VALOR', 15, 2);
    $table->string('MB_HISTORICO', 300)->nullable();
    $table->string('MB_ORIGEM', 30)->nullable();
    $table->unsignedInteger('ORIGEM_ID')->nullable();
    $table->boolean('MB_CONCILIADO')->default(false);
    $table->timestamps();
});
```

**Endpoints (routes/tesouraria.php):**
```
GET  /tesouraria/contas                    → lista com saldo atual
POST /tesouraria/contas                    → cadastrar
GET  /tesouraria/fluxo/{ano}/{mes}         → movimentos do mês
POST /tesouraria/movimentos                → lançamento manual
GET  /tesouraria/cnab240/{folha_id}        → gerar CNAB 240 para pagamento de folha
```

**Critério de aceite:** GET /cnab240 gera arquivo de 240 posições com header, detail e trailer.

---

### TASK-C5 — Receita Municipal

**View:** `financeiro/ReceitaMunicipalView.vue` ✅

**Tabelas:** `RECEITA_ORCADA`, `RECEITA_ARRECADADA`

**Endpoints (routes/receita_municipal.php):**
```
GET  /receita/orcada/{ano}         → previsão por rubrica
POST /receita/arrecadada           → registrar arrecadação
GET  /receita/comparativo/{ano}    → previsto vs arrecadado por mês
```

---

### TASK-C6 — SAGRES/TCE-MA backend real

**View:** `financeiro/SagresView.vue` ✅ (247 linhas — já tem painel)
**IMPORTANTE:** BUG-ESOCIAL-01 se aplica aqui também — gerar XML real, não comentário HTML.

**Endpoints (routes/sagres.php — verificar se já existe):**
```
GET  /sagres/sinc-folha/{competencia}   → XML SINC-Folha válido para TCE-MA
GET  /sagres/empenhos/{ano}/{mes}       → arquivo de empenhos
GET  /sagres/historico                  → transmissões registradas
POST /sagres/transmitir/{tipo}          → registrar transmissão
```

---

### TASK-C7 — Controle Externo

**View:** `financeiro/ControleExternoView.vue` ✅

**Endpoints (routes/controle_externo.php):**
```
GET  /controle-externo/lrf/pessoal/{ano}   → gasto com pessoal vs RCL (art. 19/20 LRF)
GET  /controle-externo/export/auditoria    → dados para auditor externo
```

---

## BLOCO D — ERP Administrativo

*Sem views, sem backend. Criar tudo do zero.*
*Para cada módulo: criar view Vue + backend Laravel + migration + registrar em sidebar e router.*

---

### TASK-D1 — Compras e Licitações

**Criar:** `resources/gente-v3/src/views/administrativo/ComprasView.vue`
**Backend:** `routes/compras.php`
**Sidebar:** nova seção "Administrativo" com item `/compras`

**Tabelas:**
```php
Schema::create('PROCESSO_LICITATORIO', function (Blueprint $table) {
    $table->increments('PROCESSO_ID');
    $table->string('PROCESSO_NUMERO', 30);    // ex: 001/2026
    $table->string('PROCESSO_MODALIDADE', 30); // PREGAO|TOMADA_PRECOS|CONVITE|DISPENSA|INEXIGIBILIDADE
    $table->string('PROCESSO_OBJETO', 500);
    $table->decimal('PROCESSO_VALOR_ESTIMADO', 15, 2)->nullable();
    $table->string('PROCESSO_STATUS', 20)->default('ABERTO');
    $table->date('PROCESSO_DATA_ABERTURA');
    $table->date('PROCESSO_DATA_ENCERRAMENTO')->nullable();
    $table->unsignedInteger('UO_ID')->nullable();
    $table->timestamps();
});

Schema::create('CONTRATO_ADMINISTRATIVO', function (Blueprint $table) {
    $table->increments('CONTRATO_ID');
    $table->string('CONTRATO_NUMERO', 30);
    $table->unsignedInteger('PROCESSO_ID')->nullable();
    $table->unsignedInteger('CREDOR_ID');
    $table->string('CONTRATO_OBJETO', 500);
    $table->decimal('CONTRATO_VALOR', 15, 2);
    $table->date('CONTRATO_INICIO');
    $table->date('CONTRATO_FIM');
    $table->string('CONTRATO_STATUS', 20)->default('VIGENTE');
    $table->timestamps();
});

Schema::create('PEDIDO_COMPRA', function (Blueprint $table) {
    $table->increments('PEDIDO_ID');
    $table->unsignedInteger('UO_ID');
    $table->string('PEDIDO_DESCRICAO', 300);
    $table->decimal('PEDIDO_VALOR_ESTIMADO', 15, 2)->nullable();
    $table->string('PEDIDO_STATUS', 20)->default('SOLICITADO');
    $table->unsignedInteger('PROCESSO_ID')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```
GET  /compras/processos             → lista processos licitatórios
POST /compras/processos             → abrir processo
GET  /compras/contratos             → contratos ativos
POST /compras/contratos             → registrar contrato
GET  /compras/contratos/vencendo    → contratos que vencem em 30/60 dias (alerta)
GET  /compras/pedidos               → pedidos de compra por secretaria
POST /compras/pedidos               → solicitar compra
PATCH /compras/pedidos/{id}/vincular → vincular pedido a processo licitatório
```

**View tabs:** Processos | Contratos | Pedidos | Alertas de Vencimento

**Critério de aceite:** alertas de contratos vencendo aparecem no painel; pedido de compra é vinculado a processo licitatório.

---

### TASK-D2 — Almoxarifado / Estoque

**Criar:** `resources/gente-v3/src/views/administrativo/AlmoxarifadoView.vue`
**Backend:** `routes/almoxarifado.php`

**Tabelas:**
```php
Schema::create('ALMOXARIFADO', function (Blueprint $table) {
    $table->increments('ALMOX_ID');
    $table->string('ALMOX_NOME', 100);
    $table->unsignedInteger('UO_ID')->nullable();
    $table->boolean('ALMOX_ATIVO')->default(true);
    $table->timestamps();
});

Schema::create('ITEM_ESTOQUE', function (Blueprint $table) {
    $table->increments('ITEM_ID');
    $table->string('ITEM_CODIGO', 20)->unique();
    $table->string('ITEM_DESCRICAO', 300);
    $table->string('ITEM_UNIDADE', 10);   // UN|CX|KG|L|M
    $table->string('ITEM_CATEGORIA', 50)->nullable();
    $table->integer('ITEM_ESTOQUE_MINIMO')->default(0);
    $table->timestamps();
});

Schema::create('MOVIMENTACAO_ESTOQUE', function (Blueprint $table) {
    $table->increments('MOV_ID');
    $table->unsignedInteger('ALMOX_ID');
    $table->unsignedInteger('ITEM_ID');
    $table->string('MOV_TIPO', 10);       // ENTRADA | SAIDA | AJUSTE
    $table->integer('MOV_QUANTIDADE');
    $table->decimal('MOV_VALOR_UNITARIO', 10, 2)->nullable();
    $table->string('MOV_DOCUMENTO', 50)->nullable();  // NF de entrada
    $table->unsignedInteger('UO_DESTINO_ID')->nullable(); // para saídas
    $table->unsignedInteger('REGISTRADO_POR')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```
GET  /almoxarifado/itens             → catálogo de itens com saldo atual
POST /almoxarifado/itens             → cadastrar item
POST /almoxarifado/entrada           → entrada de material (NF)
POST /almoxarifado/saida             → saída para secretaria
GET  /almoxarifado/saldo/{item_id}   → saldo por almoxarifado
GET  /almoxarifado/abaixo-minimo     → itens abaixo do estoque mínimo (alerta)
GET  /almoxarifado/movimentacoes     → histórico com filtros
```

**Integração compras:** entrada de NF vincula ao PEDIDO_COMPRA correspondente e dispara liquidação da despesa no módulo de Execução.

**View tabs:** Itens | Entrada | Saída | Saldo | Alertas

**Critério de aceite:** entrada de NF aumenta saldo; alerta aparece quando item fica abaixo do mínimo.


---

### TASK-D3 — Patrimônio

**Criar:** `resources/gente-v3/src/views/administrativo/PatrimonioView.vue`
**Backend:** `routes/patrimonio.php`

**Tabelas:**
```php
Schema::create('BEM_PATRIMONIAL', function (Blueprint $table) {
    $table->increments('BEM_ID');
    $table->string('BEM_NUMERO', 30)->unique();    // número de tombamento
    $table->string('BEM_DESCRICAO', 300);
    $table->string('BEM_CATEGORIA', 50);           // IMOVEL|MOVEL|EQUIPAMENTO|VEICULO
    $table->decimal('BEM_VALOR_AQUISICAO', 15, 2);
    $table->date('BEM_DATA_AQUISICAO');
    $table->decimal('BEM_VALOR_ATUAL', 15, 2)->nullable();
    $table->string('BEM_ESTADO', 20)->default('BOM'); // OTIMO|BOM|REGULAR|RUIM|INSERVIVEL
    $table->string('BEM_STATUS', 20)->default('ATIVO'); // ATIVO|BAIXADO|CEDIDO|EM_MANUTENCAO
    $table->unsignedInteger('UO_ID')->nullable();    // unidade responsável
    $table->unsignedInteger('SERVIDOR_ID')->nullable(); // servidor responsável
    $table->timestamps();
});

Schema::create('MOVIMENTACAO_PATRIMONIAL', function (Blueprint $table) {
    $table->increments('MOV_ID');
    $table->unsignedInteger('BEM_ID');
    $table->string('MOV_TIPO', 20);   // TRANSFERENCIA|BAIXA|EMPRESTIMO|DEVOLUCAO|MANUTENCAO
    $table->unsignedInteger('UO_ORIGEM_ID')->nullable();
    $table->unsignedInteger('UO_DESTINO_ID')->nullable();
    $table->text('MOV_MOTIVO')->nullable();
    $table->date('MOV_DATA');
    $table->unsignedInteger('REGISTRADO_POR')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```
GET  /patrimonio/bens                → catálogo com filtros (UO, categoria, estado)
POST /patrimonio/bens                → tombar bem
PUT  /patrimonio/bens/{id}           → atualizar
POST /patrimonio/bens/{id}/transferir→ transferir para outra unidade
POST /patrimonio/bens/{id}/baixar    → dar baixa (alienação, perda, sucata)
GET  /patrimonio/inventario/{uo_id}  → inventário da unidade
GET  /patrimonio/depreciacao         → relatório de depreciação anual
```

**View tabs:** Bens | Movimentações | Inventário | Depreciação

**Critério de aceite:** tombamento de bem e transferência entre unidades registrados com histórico.

**Depreciação (NBCASP 16.9):**

Bens públicos devem ser depreciados conforme a Norma Brasileira de Contabilidade Aplicada ao
Setor Público. Método padrão para prefeituras: **linear**.

Adicionar em `BEM_PATRIMONIAL`:
```php
$table->decimal('BEM_VIDA_UTIL_ANOS', 5, 1)->default(10);   // vida útil em anos
$table->decimal('BEM_VALOR_RESIDUAL', 15, 2)->default(0);   // valor residual (sucata)
$table->decimal('BEM_DEPRECIACAO_ACUMULADA', 15, 2)->default(0);
$table->date('BEM_DATA_ULTIMA_DEPRECIACAO')->nullable();
```

Fórmulas por categoria (valores padrão NBCASP):
```
Imóveis:            vida útil 25 anos, residual 0%
Veículos:           vida útil  5 anos, residual 20%
Equipamentos TI:    vida útil  3 anos, residual 10%
Máquinas/equipamentos: vida útil 10 anos, residual 10%
Móveis e utensílios:   vida útil 10 anos, residual 10%
```

Cálculo mensal (depreciaçãoMensal = (valorAquisicao − valorResidual) / (vidaUtilAnos × 12)):
```php
// app/Services/DepreciacaoService.php
public function depreciarMes(string $competencia): array {
    $bens = DB::table('BEM_PATRIMONIAL')
        ->where('BEM_STATUS', 'ATIVO')
        ->whereNull('BEM_DATA_ULTIMA_DEPRECIACAO')
        ->orWhereRaw("strftime('%Y-%m', BEM_DATA_ULTIMA_DEPRECIACAO) < ?", [$competencia])
        ->get();

    foreach ($bens as $bem) {
        $deprecMensal = round(
            ($bem->BEM_VALOR_AQUISICAO - $bem->BEM_VALOR_RESIDUAL)
            / ($bem->BEM_VIDA_UTIL_ANOS * 12), 2
        );
        $novaAcumulada = min(
            $bem->BEM_DEPRECIACAO_ACUMULADA + $deprecMensal,
            $bem->BEM_VALOR_AQUISICAO - $bem->BEM_VALOR_RESIDUAL
        );
        $novoValorAtual = $bem->BEM_VALOR_AQUISICAO - $novaAcumulada;

        DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $bem->BEM_ID)->update([
            'BEM_DEPRECIACAO_ACUMULADA'    => $novaAcumulada,
            'BEM_VALOR_ATUAL'              => $novoValorAtual,
            'BEM_DATA_ULTIMA_DEPRECIACAO'  => now()->toDateString(),
        ]);

        // Lançamento contábil automático (PCASP):
        // D 3.8.2.1.01 — Depreciação de Bens Móveis
        // C 1.2.3.1.01 — (-) Depreciação Acumulada de Bens Móveis
        // (integrar com ContabilidadeService::lancar())
    }
    return ['bens_depreciados' => count($bens)];
}
```

Endpoint: `POST /patrimonio/depreciar/{competencia}` — rodar mensalmente no fechamento.
Relatório: `GET /patrimonio/depreciacao` — valor atual vs acumulado por categoria.

Adicionar contas PCASP ao seed (PcaspSeeder):
- `3.8.2.1.01` Depreciação de Bens Móveis (DEVEDORA, ANALITICA)
- `3.8.2.2.01` Depreciação de Bens Imóveis (DEVEDORA, ANALITICA)
- `1.2.3.1.01` (-) Depreciação Acumulada — Bens Móveis (CREDORA, ANALITICA)
- `1.2.3.2.01` (-) Depreciação Acumulada — Bens Imóveis (CREDORA, ANALITICA)

---

### TASK-D4 — Gestão de Contratos Administrativos

> ⚠️ As tabelas `PROCESSO_LICITATORIO` e `CONTRATO_ADMINISTRATIVO` são criadas no TASK-D1. Este módulo cria apenas a view dedicada e endpoints complementares.

**Criar:** `resources/gente-v3/src/views/administrativo/ContratosAdminView.vue`

**Endpoints adicionais:**
```
GET  /contratos-admin                     → lista de contratos com filtros
GET  /contratos-admin/{id}                → detalhes + histórico de aditivos
POST /contratos-admin/{id}/aditivo        → registrar aditivo (prazo/valor)
POST /contratos-admin/{id}/fiscalizar     → registrar fiscalização mensal
GET  /contratos-admin/vencendo            → contratos que vencem em 60 dias
GET  /contratos-admin/export/csv          → exportação para planilha
```

**View tabs:** Contratos Ativos | Vencendo | Encerrados | Fiscalização

**Critério de aceite:** alerta de contrato vencendo em 60 dias aparece no painel; aditivo registrado atualiza data de vencimento.

---

### TASK-D5 — Gestão de Frotas

**Criar:** `resources/gente-v3/src/views/administrativo/FrotasView.vue`
**Backend:** `routes/frotas.php`

**Tabelas:**
```php
Schema::create('VEICULO', function (Blueprint $table) {
    $table->increments('VEICULO_ID');
    $table->string('VEICULO_PLACA', 10)->unique();
    $table->string('VEICULO_MODELO', 100);
    $table->string('VEICULO_MARCA', 50);
    $table->integer('VEICULO_ANO');
    $table->string('VEICULO_TIPO', 30);         // CARRO|VAN|ONIBUS|CAMINHAO|MOTO
    $table->string('VEICULO_STATUS', 20)->default('DISPONIVEL');
    $table->unsignedInteger('UO_ID')->nullable();
    $table->integer('VEICULO_KM_ATUAL')->default(0);
    $table->date('VEICULO_PROX_MANUTENCAO')->nullable();
    $table->timestamps();
});

Schema::create('SAIDA_VEICULO', function (Blueprint $table) {
    $table->increments('SAIDA_ID');
    $table->unsignedInteger('VEICULO_ID');
    $table->unsignedInteger('MOTORISTA_ID');    // FUNCIONARIO_ID
    $table->unsignedInteger('UO_SOLICITANTE_ID');
    $table->string('SAIDA_DESTINO', 200);
    $table->string('SAIDA_FINALIDADE', 200);
    $table->dateTime('SAIDA_DATA_HORA');
    $table->dateTime('RETORNO_DATA_HORA')->nullable();
    $table->integer('KM_SAIDA');
    $table->integer('KM_RETORNO')->nullable();
    $table->timestamps();
});

Schema::create('MANUTENCAO_VEICULO', function (Blueprint $table) {
    $table->increments('MANUT_ID');
    $table->unsignedInteger('VEICULO_ID');
    $table->string('MANUT_TIPO', 30);           // PREVENTIVA|CORRETIVA
    $table->string('MANUT_DESCRICAO', 300);
    $table->decimal('MANUT_VALOR', 10, 2)->nullable();
    $table->date('MANUT_DATA');
    $table->date('MANUT_PROXIMA')->nullable();
    $table->timestamps();
});
```

**Endpoints:**
```
GET  /frotas/veiculos                    → frota com status e KM
POST /frotas/veiculos                    → cadastrar veículo
GET  /frotas/veiculos/disponiveis        → disponíveis para saída
POST /frotas/saidas                      → registrar saída
PATCH /frotas/saidas/{id}/retorno        → registrar retorno com KM final
POST /frotas/manutencao                  → registrar manutenção
GET  /frotas/veiculos/{id}/historico     → histórico de saídas e manutenções
GET  /frotas/manutencao/proximas         → veículos com manutenção próxima (alerta)
```

**View tabs:** Frota | Saídas | Manutenções | Alertas

**Critério de aceite:** saída registrada muda status do veículo para "EM USO"; retorno calcula KM percorrido.

---

## BLOCO E — App Mobile

### TASK-E1 — Tela Holerites

**Verificar** se `GET /api/v3/holerites` existe em `routes/folha.php`. Se não:
```php
Route::get('/holerites', function (Request $request) {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['items' => []]);
    return response()->json(['items' =>
        DB::table('DETALHE_FOLHA as df')
        ->join('FOLHA as fl', 'fl.FOLHA_ID', 'df.FOLHA_ID')
        ->where('df.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('fl.FOLHA_COMPETENCIA')
        ->select('df.DETALHE_ID', 'fl.FOLHA_COMPETENCIA', 'df.DETALHE_FOLHA_LIQUIDO')
        ->get()
    ]);
});
```

**Tela React Native:** lista de holerites ordenados por competência. Toque → abre PDF via `expo-linking` usando `GET /api/v3/holerites/{id}/pdf` (já existe).

### TASK-E2 — Tela Escala

**Verificar** endpoint de escala para o servidor logado. Criar se não existir.

**Tela:** calendário mensal mostrando turnos/plantões do servidor.

**Critério de aceite:** app exibe lista de holerites; toque abre PDF; escala mostra turnos do mês.

---

## BLOCO F — VPS (após todos os blocos anteriores validados localmente)

```bash
# Ubuntu 22.04, PHP 8.1, MySQL 8, Nginx, Certbot
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
php artisan config:cache && php artisan view:cache
php artisan storage:link
# NÃO rodar route:cache — web.php tem closures inline
# Verificar BOM no web.php antes do deploy (regra 19 do regras-gerais.md)
```

**.env produção:**
```
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
SESSION_DOMAIN=seudominio.com.br
# CORS allowed_origins → atualizar para domínio real
```

**Critério de aceite:** URL pública HTTPS, login OK, /dev/* retorna 404, holerite com dados reais.

---

## Checklist global — TUDO antes da PoC

> Decisões de produto registradas em 30/03/2026:
> - Portal do servidor: NÃO — coberto pelo App (Bloco E)
> - Painel executivo prefeito: FUTURO pós-contrato (GAP-EXE)
> - GED/Protocolo administrativo: sistema separado que integrará via API com o GENTE
> - Benefícios (PMSLz): município ainda não tem programa — não implementar agora
> - Ponto para terceirizados: SIM — implementar antes da PoC
> - Simulador de folha/impacto LRF: SIM — implementar antes da PoC
> - Servidor de homologação: SIM — para testar folha com dados reais antes do go-live

```
[x] BLOCO S  Segurança ✅ CONCLUÍDO
             S1 SecurityHeaders / S2 CAPTCHA / S3 Bloqueio IP / S4 Política senha
             S5 Upload seguro / S6 Timeout sessão / S7 CORS produção
             S8 DOMPurify v-html / S9 SQL injection raw / S10 Log segurança

[x] TASK-00  ConsignacaoView subtítulo corrigido ✅
[x] TASK-A0  Proporcional salário ✅
[x] BLOCO A  ✅ CONCLUÍDO
[x] BLOCO B  ✅ CONCLUÍDO (B-LAYOUT-EDITOR pendente pré-go-live)
[x] BLOCO C  ✅ CONCLUÍDO (ContabilidadeService integrado ao ProcessarFolhaJob)
[x] BLOCO D  D1 ✅ D2 ✅ D3 ✅ D4 ✅ D5 em execução

── FOLHA LEGAL — bloqueadores para operação real ──────────────────────────────
[ ] GAP-13   13º salário (1ª parcela nov, 2ª parcela dez, rescisório proporcional)
             Novo método FolhaParserService + FOLHA_TIPO enum
[ ] GAP-FER  Pagamento de férias + 1/3 constitucional com INSS/IRRF
             Integrar com FeriasLicencasView existente
[ ] GAP-RES  TRCT — rescisão completa (saldo + férias vencidas + férias prop + 13º prop)
             Criar RescisaoService.php + tab TRCT em ExoneracaoView
[ ] GAP-QV   Quadro de vagas — controle de vagas autorizadas por cargo/lei
             Bloquear nomeação via PSS se VAGAS_DISPONIVEIS = 0

── PONTO E TERCEIRIZADOS ───────────────────────────────────────────────────────
[ ] GAP-PONT Ponto eletrônico para terceirizados
             Módulo separado ou flag no ponto existente — registrar frequência
             sem vínculo empregatício com o município

── SIMULADOR ───────────────────────────────────────────────────────────────────
[ ] GAP-SIM  Simulador de folha — digitar reajuste % e ver impacto antes de fechar
             SimuladorFolhaView.vue + endpoint que roda FolhaParserService em modo dry-run
[ ] GAP-LRF  Simulador de impacto LRF — reajuste × RCL × limite 54%
             SimuladorLRFView.vue — depende de GAP-SIM

── OBRIGAÇÕES ACESSÓRIAS FEDERAIS ──────────────────────────────────────────────
[ ] GAP-CAG  CAGED — admissões e desligamentos mensais (prazo: dia 7 do mês seguinte)
             routes/caged.php + layout posicional MTE
[ ] GAP-GFP  SEFIP/GFIP — recolhimento FGTS para cargos RGPS (comissionados)
             routes/sefip.php + layout posicional CEF
[ ] GAP-DIR  DIRF — declaração anual de IRRF retido (prazo: último útil de fevereiro)
             routes/dirf.php + layout texto Receita Federal
[ ] GAP-RAS  RAIS — relação anual de informações sociais (prazo: janeiro/fevereiro)
             routes/rais.php + layout posicional MTE — vínculo 30 para estatutários
[ ] GAP-SIC  SICONFI — RREO bimestral + RGF quadrimestral (exigência LRF)
             routes/siconfi.php + formato XBRL STN — depende Bloco C estável

── APP MOBILE ──────────────────────────────────────────────────────────────────
[ ] BLOCO E  E1 App Holerites / E2 App Escala

── INFRAESTRUTURA ──────────────────────────────────────────────────────────────
[ ] BLOCO F  VPS São Luís + dados reais + HTTPS + servidor homologação
             Servidor homologação: ambiente espelho para testar folha real antes go-live
[ ] B-LAYOUT-EDITOR  Editor visual do mapeamento JSON de layouts Neoconsig

── FUTURO PÓS-CONTRATO ─────────────────────────────────────────────────────────
[ ] GAP-EXE  Painel executivo prefeito/secretário (LRF em tempo real)
[ ] GAP-GED  GED/Protocolo administrativo — sistema separado com API de integração
[ ] GAP-APO  Simulador de aposentadoria (EC 103/2019 vs EC 47/2005)
[ ] GAP-MT   Multi-tenancy (schema-per-tenant) — expansão outros municípios
[ ] GAP-ICP  Assinatura digital ICP-Brasil
[ ] GAP-LGPD Compliance LGPD completo
[ ] GAP-INAT Inativos e pensionistas — folha separada
```

ATUALIZAR ao concluir cada task:
- docs/MAPA_ESTADO_REAL.md → marcar como RESOLVIDO com data
- docs/PLANO_MESTRE_V3.md → atualizar tabela STATUS ATUAL
```

*GENTE v3 — Especificação de Execução v2.0 | RR TECNOL | 23/03/2026*
