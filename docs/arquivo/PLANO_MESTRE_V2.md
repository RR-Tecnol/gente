# GENTE v3 — PLANO MESTRE DE IMPLEMENTAÇÃO
**Versão:** 2.3 | **Vigência:** 23/03/2026
**Projeto:** GENTE — Gestão de Pessoas | Prefeitura Municipal de São Luís / MA
**Empresa:** RR TECNOL
**Stack:** Laravel 10 + Vue 3 + Vite + Vuetify + SQLite (dev) / SQL Server (prod)

> Fonte de verdade única. Substitui todos os planos anteriores.
> Todo o conteúdo relevante está aqui. Docs técnicos auxiliares contêm apenas código
> de implementação para o Antygravity — nunca decisões ou status.
> Atualizar após cada sprint concluído.

---

## VISÃO GERAL DO PRODUTO

**Horizonte 1 — PoC (~15/04/2026)**
Folha de pagamento funcionando com dados reais da PMSLz. Objetivo: fechar contrato.

**Horizonte 2 — Produto Comercial (pós-contrato)**
ERP municipal completo: RH + Tesouraria + Patrimônio + Contabilidade + Orçamento.

---

## STATUS ATUAL (23/03/2026)

| Item | Status |
|------|--------|
| Login e autenticação | ✅ Funcionando |
| Dashboard | ✅ Funcionando |
| Funcionários (CRUD) | ✅ Funcionando |
| Folha de pagamento — Motor | ✅ E2E passou — R$24.743 proventos, R$2.614 descontos (17/03) |
| Holerite PDF | 🔴 View Blade ausente — Sprint Cleanup IC-07 |
| Consignação | ✅ 500 corrigido — 🔴 margem cartão 5%→10% pendente (Sprint Cleanup IC-06) |
| Progressão Funcional | 🟡 Funcionando sem tabela salarial (TASK-14/15 pendentes) |
| RPPS/IPAM | ✅ Funcionando |
| Motor de Folha | ✅ E2E passou com vencimento real (17/03/2026) |
| Autocadastro — aprovação | ✅ 6 bugs corrigidos e validados (17/03) — 🔴 BUG-AC-07 dependentes pendente |
| Sidebar — perfis e módulos | 🔴 8 módulos sem entrada (BUG-SIDEBAR-01) — Sprint Cleanup |
| Seeds (RubricasCatalogo, FuncionariosPMSLz) | 🔴 Falham com campos inexistentes (BUG-SEED-01/02) — Sprint Cleanup |
| Neoconsig | 🔴 Movido para Sprint 6 |
| ERP (6 módulos) | 🟡 Stubs — pós-contrato |
| VPS / Deploy | 🔴 Não contratado (Sprint 5) |

---

## SPRINTS DA POC

### ✅ SPRINT 0 — Login e infraestrutura (CONCLUÍDO 15/03/2026)

| Task | Status | O que foi feito |
|------|--------|----------------|
| IC-01 Auth fora do isLocal() | ✅ | routes/web.php corrigido |
| IC-02 CORS | ✅ | config/cors.php corrigido |
| IC-04 BOM UTF-8 | ✅ | progressao_funcional.php limpo |
| IC-05 APP_URL / SESSION | ✅ | .env corrigido |
| Porta XAMPP | ✅ | Backend em :8080 |
| Proxy Vite /login | ✅ | vite.config.js corrigido |
| Legado Vue 2 — Fase 1 | ✅ | 12 arquivos removidos, 3 blocos limpos |

---

### ✅ SPRINT 1 — Validação de módulos RH (CONCLUÍDO 16/03/2026)

| Task | Módulo | Status | O que foi corrigido |
|------|--------|--------|--------------------|
| VAL-01 | Funcionários | ✅ | BUG-S1-04: atribuicaoLotacoes em web.php |
| VAL-02 | Folha | ✅ | Sem erros |
| VAL-03 | Holerite PDF | ✅ | Sem erros |
| VAL-04 | Consignação | ✅ | BUG-S1-01: Request Facade FQCN |
| VAL-05 | Progressão | ✅ | BUG-S1-03: 2 migrations SQLite |
| VAL-06 | Ponto | ✅ | Sem erros |
| VAL-07 | Banco de Horas | ✅ | Sem erros |
| VAL-08 | Férias | ✅ | BUG-S1-02: arquivoFerias ref |
| VAL-09 | RPPS/IPAM | ✅ | Sem erros |
| VAL-10 | Exoneração | ✅ | Sem erros |

---

### ✅ SPRINT 2 — Correções críticas + Holerite (CONCLUÍDO 17/03/2026)

> **Antygravity:** antes de implementar qualquer item desta seção, leia os arquivos
> de especificação listados em cada subseção. Eles contêm o código completo, os campos
> exatos do banco e os critérios de aceite.

#### Autocadastro — 6 bugs críticos (BUG-AC)
> **Especificação completa:** `docs/AUTOCADASTRO_ANALISE.md` — contém o endpoint inteiro
> reescrito com Modo A (recadastramento) e Modo B (novo cadastro), campos exatos da PESSOA,
> lógica de matrícula, seed de diagnóstico para corrigir a Raissa.

| Task | Prioridade | O que corrigir | Arquivo |
|------|-----------|---------------|---------|
| BUG-AC-01 Matrícula nunca gerada | 🔴 | Adicionar geração sequencial YYYY-NNNN no INSERT FUNCIONARIO | routes/web.php — POST /autocadastro/{token}/aprovar |
| BUG-AC-02 Campo CPF errado | 🔴 | `PESSOA_CPF` → `PESSOA_CPF_NUMERO` | routes/web.php |
| BUG-AC-03 5 campos PESSOA errados | 🔴 | PESSOA_NASC→DATA_NASCIMENTO, PESSOA_SEXO→SEXO_ID, PESSOA_RG→RG_NUMERO, PESSOA_ORG_EMISSOR→RG_ORG_EMISSOR, ESTADO_CIVIL→ESTADO_CIVIL_ID | routes/web.php |
| BUG-AC-04 USUARIO não vinculado | 🔴 | Trocar insert() por insertGetId(), salvar ID em PESSOA e FUNCIONARIO | routes/web.php |
| BUG-AC-05 USUARIO_PERFIL não criado | 🔴 | Inserir PERFIL_ID=5 (Externo) após criar usuário | routes/web.php |
| BUG-AC-06 Senha MD5 vs bcrypt | 🔴 | Trocar Hash::make() por md5($dados['senha']) | routes/web.php |

**Lógica de geração de matrícula:**
```php
$ano = date('Y');
$ultima = DB::table('FUNCIONARIO')
    ->where('FUNCIONARIO_MATRICULA', 'like', "{$ano}-%")
    ->orderByDesc('FUNCIONARIO_MATRICULA')->value('FUNCIONARIO_MATRICULA');
$seq = $ultima ? ((int) explode('-', $ultima)[1] + 1) : 1;
$matricula = $ano . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
// Resultado: 2026-0019, 2026-0020, etc.
```

**Dois modos de aprovação:**
- **Modo A — Recadastramento** (FUNCIONARIO_ID preenchido no token): atualiza PESSOA existente, preserva matrícula
- **Modo B — Novo cadastro** (FUNCIONARIO_ID nulo): cria PESSOA + USUARIO + FUNCIONARIO + matrícula nova

**Corrigir também Raissa:** rodar SQL de diagnóstico para verificar se FUNCIONARIO foi criado sem matrícula:
```sql
SELECT at.TOKEN_STATUS, at.FUNCIONARIO_ID, f.FUNCIONARIO_MATRICULA, p.PESSOA_NOME
FROM AUTOCADASTRO_TOKEN at
LEFT JOIN FUNCIONARIO f ON f.FUNCIONARIO_ID = at.FUNCIONARIO_ID
LEFT JOIN PESSOA p ON p.PESSOA_ID = f.PESSOA_ID
WHERE at.TOKEN_STATUS = 'aprovado' ORDER BY at.created_at DESC;
```

#### Sidebar — perfis e módulos (BUG-SIDEBAR)
> **Especificação completa:** `docs/SIDEBAR_REORGANIZACAO.md` — contém o `ALL_NAV_ITEMS`
> inteiro para substituição, `routeMap` atualizado e critérios de aceite por perfil.
> **Usuários de teste e seed:** `docs/PERFIS_SIDEBAR_USUARIOS_SEED.md` — contém o código
> PHP completo do `UsuariosPMSLzSeeder.php` e as funções `userRoleLevel()`/`userRole()` corrigidas.

| Task | Prioridade | O que corrigir | Arquivo |
|------|-----------|---------------|---------|
| BUG-SIDEBAR-01 8 módulos sem sidebar | 🔴 | Adicionar em ALL_NAV_ITEMS: /rpps, /diarias, /acumulacao-cargos, /transparencia, /pss, /estagiarios, /terceirizados, /sagres-tce | DashboardLayout.vue |
| BUG-SIDEBAR-02 7 módulos config sem sidebar | 🟠 | Adicionar seção "Configurações" com: /configuracao-sistema, /parametros-financeiros, /vinculos, /turnos, /feriados, /tabelas-auxiliares, /eventos-folha | DashboardLayout.vue |
| BUG-SIDEBAR-03 Agrupamento confuso | 🟠 | Reorganizar em 9 seções: Visão Geral, Minha Área, Minha Equipe, RH, Frequência, Saúde Ocup., Fin. e Folha, Compliance, Desenvolvimento, Comunicação, Configurações, ERP | DashboardLayout.vue |
| BUG-SIDEBAR-04 userRoleLevel() não reconhece perfis reais | 🔴 | Adicionar mapeamento dos 15 perfis do banco para os 4 roles Vue — ver tabela abaixo | DashboardLayout.vue + router/index.js |

**Mapeamento de perfis (atualizar userRoleLevel e userRole):**

| Perfil no banco | Role Vue |
|----------------|----------|
| Desenvolvedor, Administrador, Manutenção, Equipe SISGEP | admin |
| RH Folha, RH Unidade, RH APS, RH Rede, Operacional, Direitos e Deveres, Recrutador | rh |
| Gestão, Coordenador de Setor, Diretor / Gestor de Unidade | gestor |
| Externo e qualquer outro | funcionario |

**Usuários de teste — criar UsuariosPMSLzSeeder.php (após FuncionariosPMSLzSeeder):**

| Login | Nome | Perfil (ID) | Role | Func # |
|-------|------|------------|------|--------|
| 2026-0001 | Ana Cristina Barros | Externo (5) | funcionario | #1 |
| 2026-0002 | José Carlos Lima | RH Folha (6) | rh | #2 |
| 2026-0003 | Maria das Dores Silva | Operacional (3) | rh | #3 |
| 2026-0004 | Francisco Ramos Costa | RH Rede (15) | rh | #4 |
| 2026-0005 | Antônia Pereira Nunes | RH APS (14) | rh | #5 |
| 2026-0006 | Raimundo Sousa Farias | Gestão (7) | gestor | #6 |
| 2026-0007 | Luciana Moura Castro | Coordenador de Setor (11) | gestor | #7 |
| 2026-0008 | Pedro Henrique Alves | Diretor/Gestor Unidade (12) | gestor | #8 |
| 2026-0009 | Cláudia Regina Santos | RH Unidade (8) | rh | #9 |
| 2026-0010 | Roberto Fonseca Melo | Externo (5) | funcionario | #10 |
| 2026-0012 | Geraldo Augusto Reis | Externo (5) | funcionario | #12 |
| 2026-0013 | Silvana Monteiro Cruz | Direitos e Deveres (9) | rh | #13 |
| 2026-0014 | Marcos Vinícius Neto | Gestão (7) | gestor | #14 |
| 2026-0016 | Carlos Eduardo Brito | Recrutador (10) | rh | #16 |
| 2026-0018 | Danielle Souza Cunha | Manutenção (4) | admin | #18 |
| sisgep | Equipe SISGEP | Equipe SISGEP (13) | admin | — |

Senha padrão: `md5('gente@2026')` | Equipe SISGEP: `md5('sisgep@2026')`


#### Holerite PDF e motor de folha (BUG-S2)

| Task | Prioridade | O que corrigir | Arquivo |
|------|-----------|---------------|---------|
| IC-06 Margem cartão 5%→10% | 🔴 | 2 lugares em consignacao.php — Decreto 57.477/2021 | routes/consignacao.php |
| IC-07 Holerite PDF | 🔴 | Validar view holerite-pdf.blade.php | resources/views/ |
| BUG-S2-05 Proporcionalidade mês | 🔴 | Mês completo = salário integral; parcial = dias reais | FolhaParserService |
| BUG-S2-06 Divisor 30 fixo | 🔴 | Admissão/exoneração usa dias reais do mês | FolhaParserService |
| BUG-S2-07 Afastamentos ignorados | 🔴 | Motor ignora AFASTAMENTO — licença médica paga zero | FolhaParserService |
| BUG-S2-08 IRRF 2024 em vez de 2025 | 🔴 | Isenção R$2.259 → correto R$2.824 | TabelasImpostoService |
| BUG-S2-09 Holerite classifica por texto | 🔴 | strpos no nome → usar RUBRICA_TIPO | ContraChequeService |
| BUG-S2-10 Holerite recalcula líquido | 🔴 | Recalcula prov-desc → usar DETALHE_FOLHA_LIQUIDO | ContraChequeService |
| BUG-S2-11 Bases zeradas no holerite | 🟠 | Base IRRF e Prev hardcoded como 0,00 | ContraChequeService |
| BUG-S2-15 ProcessarFolhaJob quebrado | 🔴 | Folha::processarFolha() não existe | ProcessarFolhaJob |

#### Bugs funcionais de outros módulos (detectados em auditoria)

| Bug | Módulo | Prioridade | Causa | Arquivo |
|-----|--------|-----------|-------|---------|
| BUG-HE-01 Divisor 220h fixo | Hora Extra | 🔴 | Usar VINCULO_HORAS_MES em vez de 220 | hora_extra.php |
| BUG-HE-02 Hora extra não entra na folha | Hora Extra | 🔴 | Motor ignora STATUS=APROVADA | hora_extra.php |
| BUG-HE-03 SQL Server syntax | Hora Extra | 🟠 | Aspas duplas quebram no SQLite | hora_extra.php |
| BUG-HE-04 Plantão sem sobreposição | Plantão | 🟠 | Sem validação de conflito de horário | plantoes.php |
| BUG-PROG-01 Penalidade nunca detectada | Progressão | 🔴 | LOWER() em inteiro retorna vazio | progressao_funcional.php |
| BUG-PROG-02 N+1 no impacto LRF | Progressão | 🟠 | Sem eager loading | progressao_funcional.php |
| BUG-PROG-03 Crash no teto da carreira | Progressão | 🟠 | Progride para null sem validação | progressao_funcional.php |
| BUG-RPPS-01 Base RPPS errada | RPPS | 🔴 | Usa FOLHA_PROVENTOS → correto DETALHE_BASE_PREV | rpps.php |
| BUG-RPPS-02 CADPREV stub vazio | RPPS | 🔴 | exportar-cadprev não gera arquivo real | rpps.php |
| BUG-PONTO-01 1h fantasma por dia | Ponto | 🔴 | ApuracaoPontoService ignora almoço | ApuracaoPontoService |
| BUG-PONTO-02 Falta contada 2x | Ponto | 🟠 | Falta parcial cai em dois caminhos | ApuracaoPontoService |
| BUG-ESOCIAL-01 XML inválido | eSocial | 🔴 | XML_GERADO é comentário HTML | esocial.php |
| BUG-ESOCIAL-02 CPF nulo | eSocial | 🔴 | p.CPF não existe → PESSOA_CPF_NUMERO | esocial.php |

#### Bugs UX — relatório do estagiário (validar após Sprint 2)
> **Classificação completa com causa e arquivo:** `docs/BUGS_UX_ESTAGIARIO.md`

| Bug | Prioridade | Descrição |
|-----|-----------|-----------|
| BUG-EST-01 Downloads quebrados | 🔴 | Declarações e Contratos não geram arquivo — responseType blob ausente |
| BUG-EST-02 Dependentes 404 | 🔴 | POST /api/v3/dependentes não registrado ou path errado |
| BUG-EST-04 confirm() nativo | 🔴 | Revogar autocadastro e aprovar progressão usam window.confirm() em vez de modal do sistema |
| BUG-EST-05 Sistema trava ao aprovar progressão | 🔴 | Relacionado ao BUG-PROG-03 — progride para null, job falha silenciosamente |
| BUG-EST-06 Ponto: clique em falta sem detalhe | 🟠 | @click não propaga ou dado não carregado |
| BUG-EST-07 Filtro ativo/inativo muda por página | 🔴 | Contagem calculada na página atual, não no total |
| BUG-EST-09 Autocadastro sem "Ver Perfil" | 🟡 | Botão redirecionar para /funcionarios/{id} após aprovação |
| BUG-EST-10 Autocadastro sem validação de campos | 🟠 | PIS/PASEP 11 dígitos, CEP ViaCEP, máscara telefone |

#### Melhorias de UX — implementar pós-PoC

| Melhoria | Descrição |
|----------|-----------|
| MEL-EST-01 Sidebar colapsável | Botão para modo só-ícones — ícones se tornam identificáveis com o uso |
| MEL-EST-03 Audit feed do gestor | Tabela AUDIT_LOG já existe — implementar interface de visualização |
| MEL-EST-04 Dashboard consolidado de frequência | "X faltaram, X no horário, X atrasados" — cálculo batch |
| MEL-EST-14 Férias: sobreposição por setor | Alertar quando % do setor ausente ultrapassa limite |
| MEL-EST-15 Banco de horas: visão da equipe | Gestor ver banco de horas dos servidores do setor |


---

### 🔜 SPRINT 3a — Tabela Salarial e Ciclo de Progressão (pré-requisito do motor)

**Status:** ✅ Concluído em 17/03/2026

| Task | Status |
|------|--------|
| TASK-13 Colunas FUNCIONARIO_CLASSE/REFERENCIA/CARREIRA_ID | ✅ Já existiam — seed atualizado com valores reais |
| TASK-14 Restaurar JOIN TABELA_SALARIAL no MotorFolhaService | ✅ |
| TASK-15 Aba Tabela Salarial no ProgressaoAdminView | ⏳ Pendente (TASK-15/16 opcionais para PoC) |
| TASK-16 Seção progressão no PerfilFuncionarioView | ⏳ Pendente |
| TASK-17 E2E com total_proventos > 0 | ✅ R$24.743 proventos, R$2.614 descontos |

---

### 🔜 SPRINT 3 — Motor de Folha de Pagamento

> **Antygravity:** leia `docs/SPRINT_3_MOTOR_FOLHA.md` integralmente antes de qualquer ação.
> O arquivo contém 21 partes com migrations, seeds, código do MotorFolhaService,
> interfaces Vue, bugs a corrigir e critérios de aceite mensuráveis.

O motor é implementado em `app/Services/MotorFolhaService.php` com algoritmo batch em 3 camadas.
Endpoint principal: `POST /api/v3/folhas/calcular-proventos`

**Resumo das 21 partes:**

| Parte | Conteúdo |
|-------|---------|
| 1 | Migration motor de folha — tabelas ADICIONAL_SERVIDOR, LANCAMENTO_FOLHA + alterações |
| 2 | Seed catálogo de rubricas (27 rubricas com CAMADA, CALCULO, PREV, IRRF, FGTS) |
| 3 | Seed vínculos PMSLz (10 vínculos reais com horas/mês e regime previdenciário) |
| 4 | Seed organograma PMSLz (26 secretarias reais + setores detalhados) |
| 5 | Seed 15 funcionários de teste — matrículas 2026-0001 a 2026-0018 com lacunas intencionais (cobertura dos 15 perfis) |
| 6 | Seed tabelas salariais reais (Lei 7.731/2025 + Lei 7.727/2025 — 3 carreiras) |
| 7 | MotorFolhaService.php — algoritmo batch 3 camadas (base, adicionais, lançamentos) |
| 8 | Interfaces admin — 3 views: ConfiguracaoSistema, PerfilFuncionario, FolhaPagamento |
| 9 | Ordem de execução + 15 critérios de aceite mensuráveis |
| 10 | Piso salarial — complementação automática para SP/PSS/CC |
| 11 | Folha suplementar + folha extra + reajuste retroativo (opção proxima_folha/suplementar) |
| 12 | Controle do salário mínimo vigente em CONFIGURACAO_SISTEMA |
| 13 | Critérios de aceite consolidados (SM, suplementar, próxima folha) |
| 14 | BUG-S2-05/06: proporcionalidade de fevereiro — divisor por dias reais do mês |
| 15 | Integração afastamentos — modelo mesclado: enum fixo + extensível pelo admin |
| 16 | Bugs adicionais: BUG-S2-08 a S2-15 |
| 17 | Alinhamento dos seeds (FevereiroDemoSeeder, TgTiposAfastamento, ConfiguracaoSistema) |
| 18 | Holerite PDF — 4 bugs (template errado, nome rubrica, campo Ref., complementação SM) |
| 19 | Carga horária por vínculo + acordo entre partes + banco de horas integrado ao motor |
| 20 | FUTURA: modo sem ponto eletrônico — registro manual de faltas pelo gestor |
| 21 | FUTURA: sidebar dinâmica — ocultar módulos de ponto quando MODULO_PONTO_ATIVO=0 |

**DatabaseSeeder — ordem de execução do Sprint 3:**
```
1. TabelaGenericaSeeder       ✅ executado
2. PerfilSeeder               ✅ executado
3. ConfiguracaoSistemaSeeder  ✅ executado
4. MenuSeeder                 ✅ executado
5. UsuarioSeeder              ✅ executado
6. FuncionariosPMSLzSeeder    ✅ executado (Sprint 2)
7. UsuariosPMSLzSeeder        ✅ executado (Sprint 2)
8. RubricasCatalogoSeeder     ✅ executado (Sprint 3)
9. VinculosPMSLzSeeder        ✅ executado (Sprint 3)
10. OrganogramaPMSLzSeeder    ✅ executado (Sprint 3)
11. TabelaSalarialPMSLzSeeder ✅ executado (Sprint 3)
```

**DatabaseSeeder precisa ser atualizado** após criar os seeds faltantes:
```php
$this->call([
    TabelaGenericaSeeder::class,
    PerfilSeeder::class,
    ConfiguracaoSistemaSeeder::class,
    MenuSeeder::class,
    UsuarioSeeder::class,
    RubricasCatalogoSeeder::class,      // ← adicionar
    VinculosPMSLzSeeder::class,         // ← adicionar
    OrganogramaPMSLzSeeder::class,      // ← criar + adicionar
    TabelaSalarialPMSLzSeeder::class,   // ← criar + adicionar
    FuncionariosPMSLzSeeder::class,
    UsuariosPMSLzSeeder::class,
]);
```

---

### ✅ SPRINT 4 — Engine de Folha por tipo de vínculo (concluído 17/03/2026)

**Contexto legal — 4 modalidades de remuneração:**

| Modalidade | Proventos | Progressão | Base RPPS |
|-----------|-----------|-----------|----------|
| Efetivo geral | Vencimento + adicionais | ✅ | Vencimento |
| CC-M1 (afastado para CC) | Remuneração CC + vantagens efetivo | ❌ suspenso | Vencimento efetivo |
| CC-M2 (opção pelo efetivo) | Vencimento efetivo + % do CC | ✅ | Vencimento efetivo |
| Comissionado puro | Apenas remuneração do CC | ❌ | Sobre o CC |

| Task | Prioridade | O que implementar |
|------|-----------|------------------|
| TASK-16 | ✅ | Switch por VINCULO_TIPO no loop do motor (comissao_puro, efetivo_cc_m1, efetivo_cc_m2, funcao_confianca, default) |
| TASK-17 | ✅ | Snapshot DETALHE_VINCULO_TIPO confirmado — coluna já existia e sendo populada |
| TASK-18 | ✅ | DETALHE_VINCULO_TIPO confirmado no banco (Schema::hasColumn = SIM) |
| TASK-19 | ✅ | Interface parâmetros composição salarial — já implementada no Sprint 3 (sub-aba Vínculos com VINCULO_ANUENIO_PCT editável + endpoint PATCH já salva o campo) |

---

### 🔜 SPRINT 5 — Dados reais + Deploy (Semana de 06/04/2026)

| Task | O que fazer |
|------|-------------|
| Contratar VPS | Ubuntu 22 + PHP 8.1 + MySQL + Nginx |
| Importar dados reais | Script importação FUNCIONARIO + FOLHA |
| Deploy | Build + migrations + seed produção |
| Segurança | APP_DEBUG=false, HTTPS, CORS produção |

---

### 🔜 SPRINT 4b — UX: Organograma + Filtro Sidebar (próxima sprint)

**Contexto:** Duas melhorias de UX identificadas em 17/03/2026 — sidebar com filtro de busca
e organograma com layout accordion mais limpo. Ambas são visíveis no PoC.

#### Filtro de busca na Sidebar

| Task | O que implementar |
|------|------------------|
| TASK-SB-01 | `ref sidebarBusca` + campo input discreto acima da navegação em `DashboardLayout.vue` |
| TASK-SB-02 | Computed que filtra `navItemsFiltrados` por label, preservando seção pai quando há resultado |
| TASK-SB-03 | Estilo: fundo semitransparente sobre o dark da sidebar, limpar ao pressionar Escape |

#### Organograma — refatoração visual

**Problema atual:** 26 secretarias + setores todos expandidos ao mesmo tempo = parede de 200+ cards.

**Solução — 3 mudanças cirúrgicas no `OrganogramaView.vue`:**

| Task | O que implementar |
|------|------------------|
| TASK-ORG-01 | **Colapso padrão fechado** — inicializar `colapsados` com todos os IDs de diretoria; usuário abre o que quer |
| TASK-ORG-02 | **Layout accordion vertical** — substituir `dir-level` horizontal por lista vertical com header clicável e setores abaixo em grid 3 colunas; remover linhas de conexão SVG |
| TASK-ORG-03 | **Card de setor minimalista** — exibir só ícone + nome + contador em repouso; responsável e barra de ocupação aparecem no hover via CSS (`::after` ou `title`) |

**Critérios de aceite:**
```
- [ ] Sidebar: digitar "folha" filtra e mostra "Folha de Pagamento" com seção "Financeiro e Folha"
- [ ] Sidebar: ESC limpa o filtro; navegar para módulo limpa o filtro
- [ ] Organograma: abre com todas as secretarias colapsadas
- [ ] Organograma: clicar em uma secretaria expande só ela
- [ ] Organograma: busca por nome filtra sem expandir tudo
- [ ] Organograma: card compacto em repouso, detalhe no hover
```

---

### 🔜 SPRINT 6 — Neoconsig (após dados reais validados)

**Base legal:** Decreto Municipal nº 57.477/2021 — margem 40% (30% empréstimo + 10% cartão)

| Task | O que implementar |
|------|------------------|
| Migration | NEOCONSIG_ID_OPERACAO + NEOCONSIG_VINCULO em CONSIG_CONTRATO |
| TASK-10a | POST /neoconsig/importar-debitos — posição fixa 115 chars/linha |
| TASK-10b | POST /neoconsig/importar-retorno — RETFINANCEIRO e RETQUITADAS |
| TASK-10c | GET /neoconsig/gerar-cadastro — 523 chars/linha |
| TASK-10d | GET /neoconsig/gerar-financeiro — 66 chars/linha, valor sem separador 2 decimais |
| TASK-10e | GET /neoconsig/gerar-retorno-quitadas |
| TASK-10f | GET /neoconsig/gerar-retorno-pendentes — col 67-106 motivo não desconto |

Códigos: `1111`=empréstimo (30%) · `2222`=cartão (10%) · `3333`=sindical
Prefixo matrícula: `I`=inativo · `P`=pensionista

---

### 🔜 SPRINT 7 — PoC (~15/04/2026)

| Critério | Status |
|---------|--------|
| Login < 3s | a validar |
| Dashboard com dados reais | a validar |
| Holerite PDF gerado na hora | a validar |
| Valores corretos vs sistema atual | a validar |
| Neoconsig: importação e geração | a validar |


---

## IMPLEMENTAÇÕES FUTURAS (pós-PoC)

### Modo sem ponto eletrônico
Configuração `MODULO_PONTO_ATIVO = 0` em CONFIGURACAO_SISTEMA.
Quando desligado: menus de ponto somem da sidebar, motor assume presença integral,
gestor registra faltas manualmente via nova tela FrequenciaView.vue.
Nova tabela: `FALTA_MANUAL` (FUNCIONARIO_ID, FALTA_DATA, FALTA_TIPO, REGISTRADO_POR).
> **Especificação técnica:** `docs/SPRINT_3_MOTOR_FOLHA.md` Partes 20 e 21.
Implementar após Sprint 4 estar validado em produção.

### Sidebar colapsável (modo ícones)
Botão dentro da sidebar para minimizar — mostrar apenas ícones.
Ao passar o mouse, exibe tooltip com o nome do módulo.
Os ícones se tornam identificáveis com o uso — boa UX a longo prazo.
Implementar junto com o modo sem ponto.

### Audit feed do gestor
Tabela AUDIT_LOG já existe e o middleware AuditLog.php está criado.
Falta: gravar ações e implementar interface de visualização com filtros por tipo, usuário e período.
O gestor precisa ver: cadastros, aprovações, rejeições, progressões, exonerações.
Alta valor para gestão pública — delega funções mas mantém rastreabilidade.

### Controle de sobreposição de férias por setor
Ao agendar férias, verificar % de ausência do setor no período.
Alertar se ultrapassar limite configurável (ex: 30% do setor ausente simultaneamente).
Evita que setores pequenos fiquem sem cobertura.

### Dashboard consolidado de frequência
Card no Portal do Gestor com resumo diário calculado em batch:
"X faltaram hoje, X chegaram no horário, X chegaram atrasados"
Não notificação por pessoa — visão gerencial consolidada.
Script assíncrono (job noturno ou polling de 1h) — não trava o sistema.

### Cargos e funções vinculados ao eSocial
S-2200 exige jornada contratual e cargo como campos obrigatórios.
Alteração de cargo gera S-2206 automaticamente.
Acordo de jornada (especificado na Parte 19 do motor de folha) já define esse fluxo.

---

## HORIZONTE 2 — APP MOBILE

### Estado atual (15/03/2026)

| Feature | Status |
|---------|--------|
| Login com CPF + JWT | ✅ Completo |
| Bater ponto com câmera frontal | ✅ Completo |
| Reconhecimento facial local | ✅ Fase 1 pronta |
| Geolocalização na batida | ✅ Implementado |
| Histórico de ponto | ✅ Existe |

### Para a PoC

| Feature | Prioridade |
|---------|-----------|
| Tela de Holerites | 🔴 |
| Tela de Escala | 🔴 |
| Georreferenciamento na web | 🔴 |
| Solicitações (férias, abono) | 🟠 |
| Notificações push | 🟠 |

---

## HORIZONTE 3 — ERP MUNICIPAL (pós-contrato)

Módulos existem como stubs. Implementar após assinatura do contrato com São Luís.

| ERP Sprint | Módulo |
|-----------|--------|
| ERP S1 | Orçamento (PPA/LOA) |
| ERP S2 | Execução de Despesa (empenho, liquidação, pagamento) |
| ERP S3 | Contabilidade PCASP + SINC-Folha / TCE-MA |
| ERP S4 | Tesouraria (fluxo de caixa, conciliação bancária) |
| ERP S5 | Patrimônio (bens, depreciação, inventário) |
| ERP S6 | SAGRES / TCE-MA (geração e transmissão automática) |

---

## ROADMAP VISUAL

```
SEMANA        SISTEMA WEB                         APP MOBILE
──────────────────────────────────────────────────────────────
15/03  ✔ S0 Login + infra
16/03  ✔ S1 Validação RH
23/03  S2 Autocadastro + Sidebar + Bugs         App: Holerites + Escala
??/03  S3a Tabela Salarial/Progressão
??/03  S3 Motor de Folha                         (paralelo)
??/04  S4 Engine Folha por vínculo               App: Solicitações + Push
??/04  S5 Dados reais + VPS                      Conectar ao VPS
??/04  S6 Neoconsig
15/04  S7 PoC 🎯                                 Demo ao vivo
──────────────────────────────────────────────────────────────
PÓS-CONTRATO
├─ Modo sem ponto + Sidebar colapsável
├─ Audit feed do gestor
├─ ERP S1-S6
└─ App: Digital (quando hardware definido)
```

---

---

## PENDÊNCIAS PÓS-IMPLEMENTAÇÃO — AUTOCADASTRO

*Identificadas em auditoria de código em 16/03/2026 após implementação do Sprint 2*

| # | Problema | Prioridade | Arquivo | O que corrigir |
|---|---------|-----------|---------|---------------|
| BUG-SEED-01 | RubricasCatalogoSeeder falha — `updated_at`/`created_at` no array base do foreach | 🔴 | `database/seeders/RubricasCatalogoSeeder.php` | Remover `'updated_at' => now()` e `'created_at' => now()` do array `$data` dentro do foreach — RUBRICA não tem timestamps. Migration 000010 já foi executada e colunas extras estão OK |
| BUG-SEED-01b | BUG-SEED-01 tinha diagnóstico errado (registrado anteriormente como "colunas do motor ausentes") | 🔴 | `database/seeders/RubricasCatalogoSeeder.php` | Migration `2026_03_16_000010_create_motor_folha_tables.php` (Parte 1 do Sprint 3) precisa ser criada e executada ANTES deste seed — ela adiciona `RUBRICA_CAMADA`, `RUBRICA_CALCULO`, `RUBRICA_INCIDE_PREV`, `RUBRICA_INCIDE_IRRF`, `RUBRICA_INCIDE_FGTS`, `RUBRICA_SAGRES_COD`, `RUBRICA_ORDEM` na tabela RUBRICA |
| BUG-MOTOR-01 | MotorFolhaService join com TABELA_SALARIAL inexistente | 🔴 | `app/Services/MotorFolhaService.php` | `TABELA_SALARIAL` e `PROGRESSAO_CONFIG` não existem nas migrations. `CARREIRA_ID`, `FUNCIONARIO_CLASSE`, `FUNCIONARIO_REFERENCIA` não existem no FUNCIONARIO. Motor calcula vencimento zero para todos. Usar `CARGO_SALARIO_BASE` de `CARGO` via `CARGO_ID` até `TabelaSalarialPMSLzSeeder` ser criada. Remover leftJoin com `PROGRESSAO_CONFIG`. |
| BUG-MOTOR-02 | DETALHE_FOLHA recebe `updated_at` | 🕓4 | `app/Services/MotorFolhaService.php` | Remover `'updated_at' => now()` do array `$resultados` — `DETALHE_FOLHA` não tem timestamps |
| BUG-SEED-02 | FuncionariosPMSLzSeeder reescrito com campos inexistentes | 🔴 | `database/seeders/FuncionariosPMSLzSeeder.php` | Reverter para versão simples anterior. Campos inexistentes usados: `PESSOA_NATURALIDADE`, `PESSOA_NATURALIDADE_UF`, `PESSOA_ENDERECO_CIDADE`, `PESSOA_ENDERECO_UF`, `FUNCIONARIO_CLASSE`, `FUNCIONARIO_REFERENCIA`, `FUNCIONARIO_ESTAVEL`, `FUNCIONARIO_BANCO`, `FUNCIONARIO_BANCO_AGENCIA`, `FUNCIONARIO_BANCO_CONTA`, `CARREIRA_ID` no FUNCIONARIO. Timestamps em PESSOA, FUNCIONARIO e LOTACAO. PESSOA_SEXO deve ser integer (1/2), não string 'M'/'F'. PESSOA_STATUS deve ser integer, não string 'ATIVO' |
| BUG-AC-07 | Dependentes salvos na tabela errada | 🔴 | `routes/web.php` — POST /autocadastro/{token}/aprovar passo 6 | `DEPENDENTE` legado não tem os campos usados — trocar por `PESSOA_DEPENDENTE` com colunas: `FUNCIONARIO_ID`, `PESSOA_DEPENDENTE_NOME`, `PESSOA_DEPENDENTE_CPF`, `PESSOA_DEPENDENTE_NASCIMENTO`, `PESSOA_DEPENDENTE_PARENTESCO`, `PESSOA_DEPENDENTE_DEDUCAO_IRRF` |
| BUG-AC-08 | PESSOA inserida sem `PESSOA_ATIVO` e campos legados | 🟠 | `routes/web.php` — mesmo endpoint passo 4 | Adicionar `PESSOA_ATIVO => 1`, `PESSOA_CPF`, `PESSOA_NASC`, `PESSOA_ESTADO_CIVIL`, `PESSOA_ESCOLARIDADE`, `PESSOA_RG`, `PESSOA_RG_EXPEDIDOR` em paralelo com os campos novos |
| BUG-AC-09 | Notificação para o RH removida | 🟡 | `routes/web.php` — mesmo endpoint passo 7 | Implementar notificação usando campos reais: `USUARIO_ID` individual, `NOTIFICACAO_BODY`, `NOTIFICACAO_LIDA`, `NOTIFICACAO_DT_CRIACAO` — ver `docs/MAPA_CAMPOS_TABELAS.md` seção NOTIFICACAO |

**Correção BUG-AC-07 — substituir passo 6 inteiro:**
```php
// ANTES (errado — DEPENDENTE não tem esses campos):
DB::table('DEPENDENTE')->insert([ 'DEPENDENTE_NOME' => ... ]);

// DEPOIS (correto — usar PESSOA_DEPENDENTE):
DB::table('PESSOA_DEPENDENTE')->insert([
    'FUNCIONARIO_ID'                 => $funcId,
    'PESSOA_DEPENDENTE_NOME'         => $dep['nome'],
    'PESSOA_DEPENDENTE_CPF'          => preg_replace('/\D/', '', $dep['cpf'] ?? ''),
    'PESSOA_DEPENDENTE_NASCIMENTO'   => $dep['data_nasc'] ?? null,
    'PESSOA_DEPENDENTE_PARENTESCO'   => $dep['parentesco'] ?? null,
    'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => (int)($dep['deducao_irrf'] ?? 0),
    // PESSOA_DEPENDENTE tem timestamps — não precisa informar
]);
```

---

*GENTE v3 | Plano Mestre v2.3 | RR TECNOL | São Luís — MA*
*Atualizado: 16/03/2026*
*Próxima atualização: ao concluir Sprint 2*

---

## DOCUMENTOS TÉCNICOS DE IMPLEMENTAÇÃO

> Estes arquivos contêm o código e os detalhes que o Antygravity precisa para implementar.
> O Plano Mestre contém decisões e status — os docs abaixo contêm o como.

| Arquivo | Leitura obrigatória em | Contém |
|---------|----------------------|--------|
| `docs/SPRINT_3_MOTOR_FOLHA.md` | Sprint 3 | 21 partes: migrations, seeds, MotorFolhaService, interfaces, bugs |
| `docs/AUTOCADASTRO_ANALISE.md` | Sprint 2 — BUG-AC | Endpoint corrigido completo, Modo A/B, lógica de matrícula, SQL diagnóstico |
| `docs/SIDEBAR_REORGANIZACAO.md` | Sprint 2 — BUG-SIDEBAR | ALL_NAV_ITEMS completo, routeMap, critérios de aceite |
| `docs/PERFIS_SIDEBAR_USUARIOS_SEED.md` | Sprint 2 — BUG-SIDEBAR | Código PHP UsuariosPMSLzSeeder, funções userRoleLevel/userRole corrigidas |
| `docs/BUGS_UX_ESTAGIARIO.md` | Sprint 2+ | 21 itens classificados com causa, arquivo e prioridade |
| `docs/MAPA_CAMPOS_TABELAS.md` | **Sempre — antes de qualquer INSERT** | Colunas reais de PESSOA, USUARIO, FUNCIONARIO, DEPENDENTE e outras tabelas principais |
