# Arquitetura GENTE v3 / SISGEP — Documento Mestre

> **Status:** v1.0 — 09/05/2026 02h25 (sessão produção PMSL)
> **Autor:** Claude (auditoria + Ronaldo direção)
> **Escopo:** referência canônica do sistema. Todo planejamento, briefing pro Antygravity ou decisão arquitetural deve consultar este doc primeiro. Em caso de divergência entre este doc e o código real, **o código ganha** — mas atualize este doc no mesmo commit.
>
> **Como atualizar:** Quando uma feature for adicionada ou um campo for renomeado, edite a seção correspondente. Sempre incremente o número de versão no topo, com data e nota curta do que mudou. Mantenha o **changelog** no final.

---

## Índice

1. [Visão geral do sistema](#1-visão-geral-do-sistema)
2. [Camadas e princípios arquiteturais](#2-camadas-e-princípios-arquiteturais)
3. [Mapa de domínios e tabelas (visão alta)](#3-mapa-de-domínios-e-tabelas-visão-alta)
4. [Domínio: Pessoa, RH e Lotação (catálogo de servidores)](#4-domínio-pessoa-rh-e-lotação-catálogo-de-servidores)
5. [Domínio: PCCV, Cargo, Carreira e Vencimentos](#5-domínio-pccv-cargo-carreira-e-vencimentos)
6. [Domínio: Vínculos e Regimes Previdenciários](#6-domínio-vínculos-e-regimes-previdenciários)
7. [Domínio: Organograma — Unidade, Setor, Lotação](#7-domínio-organograma--unidade-setor-lotação)
8. [Domínio: RBAC — Usuários, Perfis, Acessos](#8-domínio-rbac--usuários-perfis-acessos)
9. [Domínio: Escala, Turno, Frequência e Apuração de Ponto](#9-domínio-escala-turno-frequência-e-apuração-de-ponto)
10. [Domínio: Afastamentos, Férias, Abonos](#10-domínio-afastamentos-férias-abonos)
11. [Domínio: Folha — Estrutura e Arquitetura de 3 Camadas](#11-domínio-folha--estrutura-e-arquitetura-de-3-camadas)
12. [Domínio: Eventos e Rubricas](#12-domínio-eventos-e-rubricas)
13. [Domínio: Consignações e Empréstimos](#13-domínio-consignações-e-empréstimos)
14. [Domínio: Adicionais Permanentes (C2)](#14-domínio-adicionais-permanentes-c2)
15. [Domínio: Lançamentos Variáveis (C3)](#15-domínio-lançamentos-variáveis-c3)
16. [Domínio: Hora Extra, Plantão e Inclusão Automática](#16-domínio-hora-extra-plantão-e-inclusão-automática)
17. [Domínio: Tributação, Impostos, RPPS](#17-domínio-tributação-impostos-rpps)
18. [Domínio: eSocial / Contabilidade / Sentinela](#18-domínio-esocial--contabilidade--sentinela)
19. [Tabelas Genéricas (TABELA_GENERICA + RTG)](#19-tabelas-genéricas-tabela_generica--rtg)
20. [Fluxo End-to-End: Cadastro → Contratação → Lotação → Folha](#20-fluxo-end-to-end-cadastro--contratação--lotação--folha)
21. [Fluxo End-to-End: Frequência → Falta → Desconto na Folha](#21-fluxo-end-to-end-frequência--falta--desconto-na-folha)
22. [GAPs Identificados](#22-gaps-identificados)
23. [Decisões Arquiteturais](#23-decisões-arquiteturais)
24. [Convenções de Schema](#24-convenções-de-schema)
25. [Glossário de Termos](#25-glossário-de-termos)
26. [Changelog](#26-changelog)

---

## 1. Visão geral do sistema

**Nome:** GENTE v3 (também referido como SISGEP)
**Cliente atual:** PMSL — Prefeitura Municipal de São Luís/MA
**Capacidade alvo:** ~22.000 servidores
**Stack:** Laravel 10, Vue 3 + Vite (SPA `/v3`), SQL Server 2022 produção (SQLite em dev), React Native/Expo (mobile)
**Repositório:** github.com/RR-Tecnol/gente (público) — branch `producao-pmsl` ativa

**Escopo funcional (módulos):**
- Cadastro de pessoa, dependentes, documentos, contatos
- Cadastro de funcionário, vínculos, lotações, atribuições, escala
- Folha de pagamento (Motor v3)
- Ponto eletrônico, escalas, frequência, apuração mensal
- Afastamentos, férias, abonos
- Consignações
- Avaliação de desempenho, progressão funcional (PCCV)
- Adicionais permanentes
- Hora extra e plantão
- Saúde ocupacional, treinamentos
- Pesquisa/ouvidoria
- eSocial (XML)
- Contabilidade (integração)
- Importação de dados legados (Sisfolha)
- RBAC com perfis (DESENVOLVEDOR, ADMIN, COORD_DE_SETOR, EXTERNO etc.)
- Sentinela de integridade (validação automática de dados)
- Auditoria (audit log)

**Não-escopo desta versão:**
- Painel executivo de BI (pós-contrato)
- GED/Protocolo (sistema separado, integração via API)
- Benefícios (não aplicável a PMSL)

---

## 2. Camadas e princípios arquiteturais

**Princípio do "schema defensivo":** todo código que toca o banco usa `Schema::hasTable` / `Schema::hasColumn` antes de assumir que algo existe. Isso permite migrar para clientes futuros (Aracaju, etc.) com schemas levemente diferentes. **Visto em:** `MotorFolhaService::aplicarFiltroServidorAtivoParaMotor`, `EventosBaseSeeder`, `Feriados2026Seeder` (após fix P1.2), `ProgressaoFuncionalElegibilidadeService`.

**Princípio dos "3 caminhos do salário":**
- **C1 — Estrutural** (vencimento base + anuênio) → calculado em runtime a partir de `TABELA_SALARIAL` ou `CARGO`
- **C2 — Permanente** (ADICIONAL_SERVIDOR) → adicionais que vigem por meses/anos (ex: insalubridade, gratificação)
- **C3 — Variável** (LANCAMENTO_FOLHA) → lançamentos da competência (ex: hora extra, desconto de falta, IRRF, INSS)

**Princípio do "motor sem efeito colateral":** o `MotorFolhaService` **lê** dados (AFASTAMENTO, ADICIONAL_SERVIDOR, LANCAMENTO_FOLHA, CONSIG_PARCELA) e **escreve apenas** em `DETALHE_FOLHA` e `EVENTO_DETALHE_FOLHA`. Conversões "ponto → desconto" / "HE → provento" acontecem em **services dedicados** (`InclusaoHorasExtrasService`) chamados antes do motor processar.

**Princípio da "auditoria pré-deploy":** Antygravity (Gemini em Cursor) executa código e commits, mas não faz deploy. Claude audita via MCP (lendo o arquivo real) antes de Ronaldo puxar em produção. Ver [DIVIDA_TECNICA_POS_GOLIVE_PMSL.md] e workflow `.agent/workflows/regras-gerais.md`.

**Princípio da "branch por município":** master é referência base. Cada cliente tem branch `producao-<sigla>` (PMSL → `producao-pmsl`, Aracaju → `producao-aracaju`). Diferenças de config/schema vão na branch do município, código compartilhado vai pra master.

---

## 3. Mapa de domínios e tabelas (visão alta)

```
┌───────────────────────────────────────────────────────────────────────┐
│                        DOMÍNIO: PESSOAS                               │
│  PESSOA ──┬── DOCUMENTO          (RG, CPF, CNH, PIS, etc.)            │
│           ├── CONTATO            (telefone, email)                    │
│           ├── DEPENDENTE         (filhos, cônjuge — IRRF)             │
│           ├── PESSOA_BANCO       (conta para depósito)                │
│           ├── PESSOA_CONSELHO    (CRM, OAB, CRA, etc.)                │
│           ├── CERTIDAO           (nascimento, casamento)              │
│           └── FUNCIONARIO ◄──── 1 PESSOA pode ter N FUNCIONARIO       │
│                                  (vínculos múltiplos)                 │
└───────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│                       DOMÍNIO: RH / LOTAÇÃO                           │
│  FUNCIONARIO ──┬── LOTACAO ───┬── SETOR ── UNIDADE                    │
│                │              ├── VINCULO                             │
│                │              ├── ATRIBUICAO_LOTACAO ── ATRIBUICAO    │
│                │              └── LOTACAO_EVENTO ── EVENTO            │
│                ├── CARGO (FK opcional)                                │
│                ├── CARREIRA (efetivo, magistério, guarda, etc.)       │
│                ├── FUNCIONARIO_CLASSE / FUNCIONARIO_REFERENCIA        │
│                ├── FUNCIONARIO_REGIME_PREV (RPPS / RGPS)              │
│                ├── USUARIO  (1:1, opcional)                           │
│                ├── DETALHE_ESCALA ── ESCALA ── SETOR                  │
│                ├── AFASTAMENTO                                        │
│                ├── FERIAS                                             │
│                ├── AVALIACAO_DESEMPENHO                               │
│                └── ADICIONAL_SERVIDOR (C2 da folha)                   │
└───────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│                       DOMÍNIO: PONTO / FREQUÊNCIA                     │
│  TURNO ──► DETALHE_ESCALA_ITEM ──┬── ABONO_FALTA                      │
│                                  ├── REGISTRO_PONTO (batidas)         │
│                                  └── APURACAO_PONTO (mensal por func) │
│                                                                       │
│  HORA_EXTRA, PLANTAO_EXTRA  (com STATUS=APROVADA → vira LANCAMENTO)   │
└───────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│                          DOMÍNIO: FOLHA                               │
│  FOLHA (1 por VINCULO+COMPETENCIA+TIPO)                               │
│   └── DETALHE_FOLHA (1 por FUNCIONARIO)                               │
│         └── EVENTO_DETALHE_FOLHA (rubricas detalhadas) ── EVENTO      │
│                                                                       │
│  Inputs:                                                              │
│    • TABELA_SALARIAL × CARREIRA × CLASSE × REFERENCIA → C1 (vencimento)│
│    • PROGRESSAO_CONFIG → CONFIG_ANUENIO_PCT (taxa por ano)            │
│    • ADICIONAL_SERVIDOR → C2 (adicionais permanentes)                 │
│    • LANCAMENTO_FOLHA → C3 (variáveis: HE, plantão, faltas, etc.)     │
│    • CONSIG_PARCELA × CONSIG_CONTRATO → desconto consignação          │
│    • RPPS_CONFIG → alíquota previdenciária servidor                   │
│    • TabelaImpostoService → INSS RGPS progressivo + IRRF progressivo  │
└───────────────────────────────────────────────────────────────────────┘
```

**Quantidade de tabelas em produção PMSL:** 239 tabelas (após migrate 157/157 limpo).
**Quantidade de Models Eloquent:** 89 (em `app/Models/`).
**Quantidade de Services:** 32 (em `app/Services/`, sem contar legacy/_legacy).

---

## 4. Domínio: Pessoa, RH e Lotação (catálogo de servidores)

### Tabela `PESSOA` (40+ campos — schema completo)

| Coluna | Tipo | Notas |
|---|---|---|
| `PESSOA_ID` | int PK | autoincrement |
| `PESSOA_NOME` | nvarchar | obrigatório |
| `PESSOA_ENDERECO` | nvarchar | logradouro |
| `BAIRRO_ID` | int FK | → BAIRRO |
| `PESSOA_COMPLEMENTO` | nvarchar | apto, bloco |
| `PESSOA_CEP` | nvarchar | sem máscara |
| `PESSOA_DATA_NASCIMENTO` | date | |
| `PESSOA_ESCOLARIDADE` | int | → TABELA_GENERICA(RTG::ESCOLARIDADE=1) |
| `PESSOA_SEXO` | int | → TABELA_GENERICA(RTG::SEXO=2) |
| `PESSOA_ESTADO_CIVIL` | int | → TABELA_GENERICA(RTG::ESTADO_CIVIL=13) |
| `PESSOA_TIPO_SANGUE` | int | → TABELA_GENERICA(RTG::TIPO_SANGUINEO=14) |
| `PESSOA_RH_MAIS` | int | → TABELA_GENERICA(RTG::RH_MAIS=15) |
| `PESSOA_NOME_PAI` | nvarchar | |
| `PESSOA_NOME_MAE` | nvarchar | |
| `CIDADE_ID` | int FK | residência |
| `CIDADE_ID_NATURAL` | int FK | naturalidade |
| `PESSOA_NACIONALIDADE` | int | → TABELA_GENERICA(43) |
| `PESSOA_RACA` | int | → TABELA_GENERICA(44) |
| `PESSOA_GENERO` | int | → TABELA_GENERICA(45) |
| `PESSOA_PCD` | int | → TABELA_GENERICA(46) |
| `PESSOA_CPF_NUMERO` | nvarchar | criptografado via PiiCpf cast |
| `PESSOA_CPF_HASH` | nvarchar | blind index pra busca |
| `PESSOA_RG_NUMERO`, `PESSOA_RG_EXPEDIDOR`, `UF_ID_RG` | misc | RG completo |
| `PESSOA_TITULO_NUMERO`, `_ZONA`, `_SECAO`, `UF_ID_TITULO` | misc | título de eleitor |
| `PESSOA_CERTIFICADO_*` | misc | certificado militar |
| `PESSOA_CNH_NUMERO`, `_CATEGORIA`, `_VALIDADE`, `UF_ID_CNH` | misc | CNH |
| `PESSOA_PIS_PASEP` | nvarchar | PIS/PASEP/NIT |
| `PESSOA_DEPENDENTES_IRRF` | int | (migration `2026_03_16_000010`) usado pelo MotorFolhaService |
| `PESSOA_STATUS` | int | 0=incompleto, 1=completo |
| `PESSOA_PRE_CADASTRO` | int | autocadastro pendente aprovação |
| `PESSOA_LINK` | int | flag de email enviado |
| `PESSOA_AUDITORIA`, `PESSOA_DT_AUDITORIA`, `USUARIO_AUDITORIA` | misc | auditoria |
| `USUARIO_ID` | int FK | → USUARIO (1:1, criado via Pessoa::setUsuario) |

**Casts relevantes:** `PESSOA_CPF_NUMERO` → `App\Casts\PiiCpf` (criptografia AES). Hash gerado em `Pessoa::booted()` saving event via `PiiBlindIndex::cpfHash`.

**Métodos importantes (model `App\Models\Pessoa`):**
- `Pessoa::setUsuario($pessoaId)` → cria USUARIO + UsuarioPerfil(EXTERNO) + envia email com senha temp.
- `Pessoa::atualizarStatus($pessoaId)` → marca STATUS=1 quando tem PIS, contato, banco oficial, lotação.
- `Pessoa::excluir(Pessoa $p)` → cascade explícito (não há FK CASCADE no banco): documentos, contatos, certidoes, dependentes, lotacoes, detalheEscalas, usuario, etc.

### Tabela `FUNCIONARIO`

| Coluna | Tipo | Notas |
|---|---|---|
| `FUNCIONARIO_ID` | int PK | |
| `PESSOA_ID` | int FK | → PESSOA |
| `FUNCIONARIO_MATRICULA` | nvarchar | matrícula (pode haver várias por pessoa) |
| `FUNCIONARIO_DATA_INICIO` | date | admissão (usado pra anuênio + pró-rata C1) |
| `FUNCIONARIO_DATA_FIM` | date | exoneração/desligamento (usado pra pró-rata) |
| `FUNCIONARIO_TIPO_ENTRADA` | int | → TABELA_GENERICA(RTG::TIPO_ENTRADA_FUNCIONARIO=22) |
| `FUNCIONARIO_TIPO_SAIDA` | int | → TABELA_GENERICA(RTG::TIPO_SAIDA_FUNCIONARIO=23) |
| `FUNCIONARIO_OBSERVACAO` | nvarchar | |
| `FUNCIONARIO_DATA_CADASTRO`, `_DATA_ATUALIZACAO` | datetime | |
| `USUARIO_ID` | int FK | → USUARIO |
| `CARGO_ID` | int FK | → CARGO (opcional, pode usar TABELA_SALARIAL no lugar) |
| `CARREIRA_ID` | int FK | → CARREIRA (migration `2026_03_10_000001`) |
| `FUNCIONARIO_CLASSE` | varchar(5) | classe ('A','B','C',...) |
| `FUNCIONARIO_REFERENCIA` | varchar(5) | referência ('I','II',...) |
| `FUNCIONARIO_ESTAVEL` | bool | |
| `FUNCIONARIO_ESTAGIO_PROBATORIO` | bool | |
| `FUNCIONARIO_DATA_ULTIMA_PROGRESSAO` | date | |
| `FUNCIONARIO_REGIME_PREV` | varchar(10) | 'RPPS' (default) ou 'RGPS' (migration `2026_03_10_100000`) |
| `FUNCIONARIO_VINCULO_TIPO` | nvarchar | (legado — alguns lugares ainda usam) |
| `VINCULO_ID` | int FK | → VINCULO (preferencial atual) |
| `FUNCIONARIO_ATIVO` | bool | (criado em phase 11/12, talvez nem todos schemas tenham) |
| `FUNCIONARIO_DATA_DEMISSAO` | date | (alternativa a DATA_FIM em alguns deploys) |

**Relações Eloquent (`App\Models\Funcionario`):**
- `pessoa()` → 1:1
- `usuario()` → 1:1
- `lotacoes()` → 1:N (LOTACAO)
- `detalheEscalas()` → 1:N (DETALHE_ESCALA)
- `ferias()` → 1:N
- `afastamentos()` → 1:N
- `cargo()` → N:1 (CARGO_ID)
- `avaliacoesDesempenho()` → 1:N
- `funcionarioTipoEntrada()`, `funcionarioTipoSaida()` → TabelaGenerica

**Scopes importantes:**
- `scopeAtivosNoEscopo($query, ?array $setoresIds, ?string $dataReferencia)` → filtra por DATA_FIM/DATA_DEMISSAO + lotação vigente em setores. Usado pra KPIs e listagens RBAC.
- `scopeLotacaoVigenteEmSetores` → variante só territorial.

**Método `Funcionario::setUsuario($funcionarioId)`** → cria/vincula USUARIO ao funcionário. Mesma lógica de `Pessoa::setUsuario` mas mantém `FUNCIONARIO_ID` em USUARIO.

---

## 5. Domínio: PCCV, Cargo, Carreira e Vencimentos

### Tabela `CARGO`

| Coluna | Tipo | Notas |
|---|---|---|
| `CARGO_ID` | int PK | |
| `CARGO_NOME` | nvarchar | |
| `CARGO_SIGLA` | nvarchar | |
| `CARGO_ESCOLARIDADE` | int | → TABELA_GENERICA |
| `CARGO_GESTAO` | int | flag: cargo de gestão? (usado em `Lotacao::gestao`) |
| `CARGO_ATIVO` | int | |
| `PCCV_ID` | int FK | → PCCV_DOMINIO |
| `CARGO_SALARIO` ou `CARGO_SALARIO_BASE` | decimal | usado em `MotorFolhaService::prepararContextoLote` como **fallback** quando `TABELA_SALARIAL` não retorna valor (linhas 102-115). Schema-defensivo: usa `CARGO_SALARIO` se existir, senão `CARGO_SALARIO_BASE`. |

### Tabela `FUNCAO`

Função tem schema mais simples — usado em escala (`DetalheEscala.FUNCAO_ID`):

| Coluna | Tipo |
|---|---|
| `FUNCAO_ID` | int PK |
| `FUNCAO_NOME` | nvarchar |
| `FUNCAO_SIGLA` | nvarchar |
| `FUNCAO_ATIVA` | int |

### Tabelas de PCCV (Plano de Cargos, Carreira e Vencimentos)

`CARREIRA`, `TABELA_SALARIAL`, `PROGRESSAO_CONFIG` foram criadas pela migration `2026_03_10_000001_create_progressao_tables`:

**`CARREIRA`:**

| Coluna | Tipo | Notas |
|---|---|---|
| `CARREIRA_ID` | int PK | |
| `CARREIRA_NOME` | varchar(100) | ex: "Geral", "Magistério", "Guarda" |
| `CARREIRA_REGIME` | varchar(20) | 'efetivo' ou 'comissionado' |
| `created_at`, `updated_at` | datetime | |

**`TABELA_SALARIAL`** (a famosa "tabela de vencimentos"):

| Coluna | Tipo | Notas |
|---|---|---|
| `TABELA_ID` | int PK | (NÃO confundir com TABELA_GENERICA.TABELA_ID) |
| `CARREIRA_ID` | int FK | → CARREIRA |
| `TABELA_CLASSE` | varchar(5) | 'A', 'B', 'C', 'PNS1', 'GMA1', etc. |
| `TABELA_REFERENCIA` | varchar(5) | 'I', 'II', 'III' ou 1, 2, 3 |
| `TABELA_REFERENCIA_ORDEM` | int | pra ordenação numérica |
| `TABELA_VENCIMENTO_BASE` | decimal | valor R$ |
| `TABELA_TITULACAO` | varchar(20) | 'medio', 'graduacao', 'especializacao', 'mestrado' |

**Lookup do vencimento C1 (linhas 277-284 do MotorFolhaService):**
```sql
LEFT JOIN TABELA_SALARIAL ts ON
    ts.CARREIRA_ID = f.CARREIRA_ID
    AND ts.TABELA_CLASSE = f.FUNCIONARIO_CLASSE
    AND ts.TABELA_REFERENCIA = f.FUNCIONARIO_REFERENCIA
```

**`PROGRESSAO_CONFIG`** (parametriza anuênio/progressão por carreira):

| Coluna | Tipo | Notas |
|---|---|---|
| `CONFIG_ID` | int PK | |
| `CARREIRA_ID` | int FK | nullable (default global se nulo) |
| `CONFIG_INTERSTICIO_MESES` | int | default 24 |
| `CONFIG_NOTA_MINIMA` | decimal(5,2) | default 7.00 |
| `CONFIG_ANUENIO_PCT` | decimal | percentual por ano (1.00 = 1%, 2.00 = 2%) |

**Cálculo do anuênio (MotorFolhaService linha ~330):**
```php
$aliqAnuenio = (VINCULO.VINCULO_ANUENIO_PCT ?? PROGRESSAO_CONFIG.CONFIG_ANUENIO_PCT ?? 1.00) / 100;
$anoServ = now()->diffInYears(FUNCIONARIO_DATA_INICIO);
$anuenioVal = $vencBase * $aliqAnuenio * $anoServ * $fatorDesempenho;
```

**Cargos PMSL exemplos (referência apenas, a tabela real está nos seeders):**
- Magistério: PNS1 a PNS6 (Professor Nível Superior), classes A-D, referências I-V
- Guarda: GMA1 a GMA3 (Guarda Municipal Armado), classes A-C
- Geral: A, B, C, D (técnico-administrativo), referências I-X

---

## 6. Domínio: Vínculos e Regimes Previdenciários

### Tabela `VINCULO`

| Coluna | Tipo | Notas |
|---|---|---|
| `VINCULO_ID` | int PK | |
| `VINCULO_NOME` | nvarchar | (alias `VINCULO_DESCRICAO` — backward compat) |
| `VINCULO_SIGLA` | nvarchar | |
| `VINCULO_ATIVO` | int | |
| `VINCULO_TIPO` | varchar | 'efetivo', 'comissao_puro', 'efetivo_cc_m1', 'efetivo_cc_m2', 'funcao_confianca', 'pss', 'servico_prestado' |
| `VINCULO_REGIME` | varchar | 'RPPS' ou 'RGPS' |
| `VINCULO_FGTS` | bool | tem FGTS? (RGPS sim, RPPS não) |
| `VINCULO_INSS` | bool | sofre desconto previdenciário? |
| `VINCULO_IRRF` | bool | sofre IRRF? |
| `VINCULO_ANUENIO_PCT` | decimal | % do anuênio (override da PROGRESSAO_CONFIG) |

**Tipos de VINCULO (visto no `MotorFolhaService` switch linhas 320-360):**

| Tipo | C1 (vencimento) | Anuênio? | INSS típico | Anotações |
|---|---|---|---|---|
| `efetivo` | TABELA_SALARIAL | Sim | RPPS 14% | Estatutário comum |
| `comissao_puro` | CARGO_SALARIO | Não | RGPS progressivo | Cargo em comissão sem efetivo |
| `efetivo_cc_m1` | TABELA_SALARIAL | Não | RPPS 14% | Efetivo em CC, modalidade 1 |
| `efetivo_cc_m2` | TABELA_SALARIAL | Sim | RPPS 14% | Efetivo em CC, modalidade 2 |
| `funcao_confianca` | TABELA_SALARIAL | Sim | RPPS 14% | Função de confiança |
| `pss` | CARGO_SALARIO | Não | RGPS | Servidor temporário PSS |
| `servico_prestado` | CARGO_SALARIO | Não | RGPS | Tem direito a piso salário mínimo |

**`VINCULOS_PISO`** (constante MotorFolhaService linha 25): `['servico_prestado', 'pss', 'comissao_puro']`. Recebem `complementoSM` se `bruto < salário mínimo`.

---

## 7. Domínio: Organograma — Unidade, Setor, Lotação

### Tabela `UNIDADE`

| Coluna | Tipo | Notas |
|---|---|---|
| `UNIDADE_ID` | int PK | |
| `UNIDADE_NOME` | nvarchar | "SECRETARIA MUNICIPAL DE EDUCACAO" |
| `UNIDADE_SIGLA` | nvarchar | "SEMED" (em alguns seeders) |
| `UNIDADE_CNES` | nvarchar | (saúde) |
| `UNIDADE_ENDERECO`, `_COMPLEMENTO`, `_TELEFONE` | misc | |
| `BAIRRO_ID` | int FK | |
| `UNIDADE_ATIVA` | int | ⚠️ feminino — fix `2c59fbb` corrigiu typo `UNIDADE_ATIVO` em rotas |
| `UNIDADE_TIPO` | int | → TABELA_GENERICA(RTG::TIPO_UNIDADE=10) |
| `UNIDADE_PORTE` | int | → TABELA_GENERICA(RTG::UNIDADE_PORTE=21) |

### Tabela `SETOR`

| Coluna | Tipo | Notas |
|---|---|---|
| `SETOR_ID` | int PK | |
| `UNIDADE_ID` | int FK | → UNIDADE |
| `SETOR_PAI_ID` | int FK nullable | hierarquia (setor dentro de setor) |
| `SETOR_NOME` | nvarchar | |
| `SETOR_SIGLA` | nvarchar | |
| `SETOR_ATIVO` | int | |

**Convenção:** todo UNIDADE tem ao menos um SETOR chamado "GERAL" (aparece primeiro na ordenação — `Unidade::setores()` orderByRaw `CASE WHEN SETOR_NOME = 'GERAL' THEN 0 ELSE 1 END`).

### Tabela `LOTACAO`

| Coluna | Tipo | Notas |
|---|---|---|
| `LOTACAO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `VINCULO_ID` | int FK | |
| `SETOR_ID` | int FK | |
| `LOTACAO_DATA_INICIO` | date | |
| `LOTACAO_DATA_FIM` | date nullable | seeds usam '2099-12-31' como "vigente" |
| `LOTACAO_TIPO_FIM` | int | → TABELA_GENERICA(RTG::LOTACAO_TIPO_FIM=26) |
| `LOTACAO_OBSERVACAO` | nvarchar | |
| `LOTACAO_DESVIO_FUNCAO` | int | flag |

**Tabelas filhas de LOTACAO:**
- `ATRIBUICAO_LOTACAO` (N) → `ATRIBUICAO` (cargo+função+carga horária)
- `LOTACAO_EVENTO` (N) → `EVENTO` (eventos específicos da lotação — gratificações, etc.)

**Hierarquia visual:**
```
PESSOA
  └── FUNCIONARIO
        └── LOTACAO (vínculo + setor + período)
              ├── ATRIBUICAO_LOTACAO (cargo/função efetivos)
              └── LOTACAO_EVENTO (lançamentos permanentes vinculados ao posto)
```

---

## 8. Domínio: RBAC — Usuários, Perfis, Acessos

### Tabela `USUARIO`

| Coluna | Tipo | Notas |
|---|---|---|
| `USUARIO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK nullable | nem todo usuário é funcionário (admin externo) |
| `USUARIO_LOGIN` | nvarchar | normalmente CPF |
| `USUARIO_SENHA` | nvarchar | bcrypt |
| `USUARIO_NOME` | nvarchar | |
| `USUARIO_CPF` | nvarchar | |
| `USUARIO_EMAIL` | nvarchar | |
| `USUARIO_ATIVO` | int | |
| `USUARIO_VIGENCIA` | date | |
| `USUARIO_PRIMEIRO_ACESSO` | int | flag pra forçar troca de senha |
| `USUARIO_ALTERAR_SENHA` | int | flag manual |

**Auth Laravel:** `App\Models\Usuario` extends `Authenticatable`. Senha via `getAuthPassword()` retornando `USUARIO_SENHA`.

### Tabela `PERFIL`

| Coluna | Tipo | Notas |
|---|---|---|
| `PERFIL_ID` | int PK | |
| `PERFIL_NOME` | nvarchar | |
| `PERFIL_ATIVO` | int | |
| `PERFIL_DASHBOARD_LINK` | nvarchar | rota inicial padrão |

**Perfis seedados (`App\MyLibs\PerfilEnum`):** ler arquivo pra constantes exatas. Inclui DESENVOLVEDOR, ADMIN, COORD_DE_SETOR, EXTERNO (cidadão/pré-cadastro).

### Tabela `USUARIO_PERFIL`

Liga `USUARIO` a `PERFIL` (N:N).

| Coluna | Tipo |
|---|---|
| `USUARIO_PERFIL_ID` | int PK |
| `USUARIO_ID` | int FK |
| `PERFIL_ID` | int FK |
| `USUARIO_PERFIL_ATIVO` | int |

### Tabelas de escopo territorial

`USUARIO_UNIDADE` (USUARIO_ID + UNIDADE_ID) e `USUARIO_SETOR` (USUARIO_ID + SETOR_ID) restringem qual território o usuário enxerga.

**Decisão de escopo:** `App\Support\UnidadeEscopoUsuario::podeAcessarSetor`. Se PERFIL=DESENVOLVEDOR, ignora restrição. Outros perfis filtram via UsuarioUnidade/UsuarioSetor.

### Tabelas `APLICACAO`, `ACESSO`, `RBAC_MATRIX`

`APLICACAO` → tela/feature; `ACESSO` → permissão (PERFIL, APLICACAO, CRUD); `RBAC_MATRIX` (preenchida via `RbacMatrixSeeder`) — matriz aplicacao×perfil consolidada.

**Resolução em runtime:** `App\Support\RbacResolver` (ou similar — confirmar nome).

---

## 9. Domínio: Escala, Turno, Frequência e Apuração de Ponto

### Tabela `ESCALA`

| Coluna | Tipo | Notas |
|---|---|---|
| `ESCALA_ID` | int PK | |
| `SETOR_ID` | int FK | |
| `ESCALA_COMPETENCIA` | nvarchar | YYYYMM, cast Periodo |
| `ESCALA_DESCRICAO` | nvarchar | |
| `ESCALA_OBSERVACAO` | nvarchar | |
| `TIPO_ESCALA_ID` | int | → TABELA_GENERICA(RTG::TIPO_ESCALA=52) |

Fluxo de status: cadastrada → atualizada → avaliada → deferida (via `HISTORICO_ESCALA` + `STATUS_ESCALA`).

### Tabela `DETALHE_ESCALA`

Escala individualizada por funcionário no setor:

| Coluna | Tipo |
|---|---|
| `DETALHE_ESCALA_ID` | int PK |
| `ESCALA_ID` | int FK |
| `FUNCIONARIO_ID` | int FK |
| `ATRIBUICAO_ID` | int FK |
| `DETALHE_ESCALA_OBSERVACAO` | nvarchar |
| `DETALHE_ESCALA_SALDO` | int |
| `DETALHE_ESCALA_VALOR` | decimal |
| `TIPO_CALCULO_ID` | int |

### Tabela `DETALHE_ESCALA_ITEM`

Linha do dia-a-dia da escala:

| Coluna | Tipo | Notas |
|---|---|---|
| `DETALHE_ESCALA_ITEM_ID` | int PK | |
| `DETALHE_ESCALA_ID` | int FK | |
| `TURNO_ID` | int FK | turno esperado |
| `DETALHE_ESCALA_ITEM_DATA` | date | dia |
| `DETALHE_ESCALA_ITEM_FALTA` | int | **0 ou 1 — flag de falta** |
| `DETALHE_ESCALA_ITEM_ATRASO` | int | flag |
| `DETALHE_ESCALA_ITEM_OBSERVACAO` | nvarchar | |

⚠️ **Não tem timestamps** (`public $timestamps = false`) — atenção em testes.

### Tabela `TURNO`

| Coluna | Tipo |
|---|---|
| `TURNO_ID` | int PK |
| `TURNO_DESCRICAO` | nvarchar |
| `TURNO_SIGLA` | nvarchar |
| `TURNO_HORA_INICIO`, `_HORA_FIM` | time |
| `TURNO_INTERVALO` | int → TABELA_GENERICA(RTG::INTERVALO=54) |
| `TURNO_INTERVALO_MINUTOS` | int (default 60) |
| `TURNO_ATIVO` | int |
| `TURNO_DATA_EXCLUSAO` | datetime (SoftDeletes) |

### Tabela `REGISTRO_PONTO` (batidas)

| Coluna | Tipo | Notas |
|---|---|---|
| `REGISTRO_PONTO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `TERMINAL_ID` | int FK | → TERMINAL_PONTO |
| `REGISTRO_DATA_HORA` | datetime | |
| `REGISTRO_TIPO` | enum | ENTRADA \| PAUSA \| RETORNO \| SAIDA |
| `REGISTRO_ORIGEM` | enum | REP_P \| REP_A_SENHA \| MANUAL |
| `REGISTRO_NSR` | nvarchar | número sequencial (REP) |
| `REGISTRO_OBSERVACAO` | nvarchar | |

### Tabela `APURACAO_PONTO`

Fechamento mensal do funcionário:

| Coluna | Tipo | Notas |
|---|---|---|
| `APURACAO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `APURACAO_COMPETENCIA` | varchar | "YYYY-MM" |
| `APURACAO_HORAS_TRAB` | float | horas efetivamente trabalhadas |
| `APURACAO_HORAS_EXTRA` | float | excedente da escala |
| `APURACAO_HORAS_FALTA` | float | déficit (faltas + atrasos não justificados) |
| `APURACAO_STATUS` | varchar | ABERTA \| FECHADA \| AJUSTADA |
| `APURACAO_FECHADA_EM` | datetime | |
| `APURACAO_FECHADA_POR` | int FK | → USUARIO |

### Tabela `ABONO_FALTA`

| Coluna | Tipo | Notas |
|---|---|---|
| `DETALHE_ESCALA_ITEM_ID` | int PK | (PK = FK pra ESCALA_ITEM!) |
| `USUARIO_ID` | int FK | quem aprovou |
| `ABONO_FALTA_DATA` | date | |
| `ABONO_FALTA_JUSTIFICATIVA` | nvarchar | |

→ tabela `ANEXO_ABONO_FALTA` (anexos do abono).

### Tabela `JUSTIFICATIVA_PONTO`

Justificativa por dia (não por item de escala) — usada quando funcionário esquece batida:

| Coluna | Tipo |
|---|---|
| `JUSTIFICATIVA_ID` | int PK |
| `APURACAO_ID` | int FK |
| ... (confirmar campos) |

Aprovar justificativa **recalcula** APURACAO_HORAS_FALTA (visto em `JustificativaPontoController:62-63`).

### Service: `App\Services\ApuracaoPontoService`

**Métodos principais:**

`calcular(int $funcionarioId, string $competencia)` — gera/atualiza APURACAO_PONTO.
- Lê REGISTRO_PONTO da competência
- Lê DETALHE_ESCALA_ITEM (turnos esperados)
- Para cada dia, soma horas trabalhadas (entrada→saída − intervalo)
- Compara com horas esperadas → calcula HORAS_EXTRA / HORAS_FALTA
- Suporta regimes "2 batidas" e "4 batidas" (via PONTO_CONFIG_FUNCIONARIO)
- Dia com escala mas sem ponto → **desconta jornada esperada como falta integral**

`fechar(int $apuracaoId)` — fecha apuração.
- Atualiza APURACAO_STATUS='FECHADA'
- 🔴 **TODO crítico (linha 144):** "Integração real com DetalheFolha — busca o detalhe da folha do mês". Comentário explícito: "A integração completa com EventoDetalheFolha requer o DETALHE_FOLHA_ID que vem do processamento da folha." → **Não gera EVENTO_DETALHE_FOLHA nem LANCAMENTO_FOLHA de DESCONTO_FALTA**.

---

## 10. Domínio: Afastamentos, Férias, Abonos

### Tabela `AFASTAMENTO`

| Coluna | Tipo | Notas |
|---|---|---|
| `AFASTAMENTO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `AFASTAMENTO_DATA_INICIO` | date | |
| `AFASTAMENTO_DATA_FIM` | date nullable | nullable = ainda aberto |
| `AFASTAMENTO_TIPO` | int | → TABELA_GENERICA(RTG::TIPO_AFASTAMENTO=5) |

→ tabela `ANEXO_AFASTAMENTO`.

**Tipos de afastamento (constantes em `MotorFolhaLoteContext::diasAbonadosNoMes`):** `LICENCA_MEDICA`, `LICENCA_SAUDE`, `LICENCA_MATERNIDADE`, `LICENCA_PATERNIDADE`, `LICENCA_NOJO`, `LICENCA_GALA`, `AFASTAMENTO_JUDICIAL`, `AFASTAMENTO_REMUNERADO`. **Esses tipos não geram desconto — são considerados "dias abonados" / dias trabalhados.**

⚠️ **Discrepância importante:** o método `diasAbonadosNoMes` compara `AFASTAMENTO_TIPO` com strings ('LICENCA_MEDICA' etc.), mas no schema `AFASTAMENTO_TIPO` é um int (FK pra TABELA_GENERICA). Provável que ainda não funcione 100% até ser atualizado pra fazer JOIN com TABELA_GENERICA. **Marcar como GAP**.

**Método `Afastamento::alertaExpirar(?int $setorId)`** — retorna afastamentos vencidos/críticos/atenção (60 dias).

### Tabela `FERIAS`

| Coluna | Tipo | Notas |
|---|---|---|
| `FERIAS_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `FERIAS_DATA_INICIO` | date nullable | nullable = aquisitivo aberto, gozo não marcado |
| `FERIAS_DATA_FIM` | date nullable | |
| `FERIAS_AQUISITIVO_INICIO` | int | YYYY |
| `FERIAS_AQUISITIVO_FIM` | int | YYYY |

**Regra legal (Ferias::alertaVencer):** prazo legal pra gozo = 31/dez do ano seguinte ao FERIAS_AQUISITIVO_FIM. Ex: aquisitivo 2023/2024 → prazo até 31/12/2025.

### Tabela `FERIADO`

| Coluna | Tipo | Notas |
|---|---|---|
| `FERIADO_ID` | int PK | |
| `FERIADO_DATA` | date | |
| `FERIADO_DESCRICAO` | nvarchar | (NÃO `FERIADO_NOME` em PMSL) |
| `UNIDADE_ID` | int nullable | NULL = todos. Confirmar se é por unidade ou universal |
| `FERIADO_ATIVO` | int | |
| `FERIADO_DATA_EXCLUSAO` | date nullable | |
| `FERIADO_TIPO` | int nullable | → TABELA_GENERICA(RTG::TIPO_FERIADO=28) — **inteiro em PMSL, string em legados** |

---

## 11. Domínio: Folha — Estrutura e Arquitetura de 3 Camadas

### Tabela `FOLHA` (mestre)

| Coluna | Tipo | Notas |
|---|---|---|
| `FOLHA_ID` | int PK | |
| `FOLHA_DESCRICAO` | nvarchar | "Folha de Maio/2026 — SEMED" |
| `FOLHA_TIPO` | int | → TABELA_GENERICA(RTG::TIPOS_FOLHA=32) |
| `VINCULO_ID` | int FK | folha por vínculo (efetivo, comissão, PSS...) |
| `FOLHA_COMPETENCIA` | varchar(6) cast Periodo | YYYYMM |
| `FOLHA_QTD_SERVIDORES` | int | preenchido após cálculo |
| `FOLHA_VALOR_TOTAL` | decimal(2) | |
| `FOLHA_ARQUIVO` | nvarchar | path do PDF de contracheque consolidado |
| `FOLHA_CHECKSUM` | nvarchar | integridade |

### Tabela `FOLHA_SETOR` (N:N — quais setores compõem a folha)

| Coluna | Tipo |
|---|---|
| `FOLHA_ID` | int FK |
| `SETOR_ID` | int FK |

### Tabela `DETALHE_FOLHA` (1 por servidor)

| Coluna | Tipo | Notas |
|---|---|---|
| `DETALHE_FOLHA_ID` | int PK | |
| `FOLHA_ID` | int FK | |
| `FUNCIONARIO_ID` | int FK | (chave única com FOLHA_ID — upsert no motor) |
| `PENSIONISTA_ID` | int FK nullable | |
| `DETALHE_FOLHA_PROVENTOS` | float | bruto |
| `DETALHE_FOLHA_DESCONTOS` | float | total descontos |
| `DETALHE_FOLHA_LIQUIDO` | float | bruto - descontos |
| `DETALHE_FOLHA_ERRO` | nvarchar | |
| `DETALHE_BASE_PREV`, `_BASE_IRRF` | float | bases tributáveis |
| `DETALHE_DESC_PREV`, `_DESC_IRRF`, `_DESC_OUTROS` | float | breakdown descontos |
| `DETALHE_VINCULO_TIPO` | varchar | snapshot do tipo na época (audit) |
| `DETALHE_COMPLEMENTO_SM` | float | complemento salário mínimo |

### Tabela `EVENTO_DETALHE_FOLHA` (rubricas detalhadas)

| Coluna | Tipo |
|---|---|
| `EVENTO_DETALHE_FOLHA_ID` | int PK |
| `EVENTO_ID` | int FK → EVENTO |
| `DETALHE_FOLHA_ID` | int FK |
| `EVENTO_DETALHE_FOLHA_VALOR` | float |

### Service: `App\Services\MotorFolhaService` (motor canônico)

**API pública:**
- `despacharProcessamentoAssincrono(int $folhaId, ?int $usuarioId)` → cria batch de jobs (CHUNK_SIZE=500). Driver não-sync obrigatório pra produção.
- `calcularFolha(int $folhaId)` → caminho síncrono (chunked).
- `calcularLoteParaFuncionarios(int $folhaId, array $funcIds, MotorFolhaLoteContext $ctx)` → core do cálculo.

**Inputs lidos:**
- `FOLHA` (competência)
- `FUNCIONARIO` filtrado por `FUNCIONARIO_ATIVO=1` ou DATA_FIM/DATA_DEMISSAO nulas/futuras
- JOIN `PESSOA` (PESSOA_DEPENDENTES_IRRF)
- JOIN `VINCULO` (VINCULO_TIPO, VINCULO_REGIME, VINCULO_FGTS, VINCULO_INSS, VINCULO_IRRF, VINCULO_ANUENIO_PCT)
- JOIN `TABELA_SALARIAL` (TABELA_VENCIMENTO_BASE) via (CARREIRA_ID, FUNCIONARIO_CLASSE, FUNCIONARIO_REFERENCIA)
- JOIN `PROGRESSAO_CONFIG` (CONFIG_ANUENIO_PCT)
- `ADICIONAL_SERVIDOR` (vigentes na competência)
- `LANCAMENTO_FOLHA` (da FOLHA_ID)
- `CONSIG_PARCELA` × `CONSIG_CONTRATO` (PENDENTE + ATIVO)
- `RPPS_CONFIG` (alíquota servidor)
- Via contexto: `AFASTAMENTO`, `AVALIACAO_DESEMPENHO`, `CARGO`, `FUNCIONARIO_DATA_INICIO/FIM`

**Sequência de cálculo (por funcionário):**
1. **Pró-rata** por dias contratuais no mês (admissão/exoneração): `razao = diasContratuais / diasMes`. Se nada, 1.0.
2. **C1 estrutural:**
   - Se `VINCULO_TIPO ∈ {comissao_puro, efetivo_cc_m1, pss, servico_prestado}` → só vencimento, sem anuênio
   - Senão → `provC1 = vencBase + (vencBase × aliqAnuenio × anosServico × fatorDesempenho)`
3. **C2 adicionais:** soma de `ADICIONAL_VALOR` por tipo (`fixo`, `percentual` sobre vencBase, `percentual_sm` sobre salário mínimo).
4. **C3 lançamentos:** soma `LANCAMENTO_VALOR_TOTAL` separando tipo `'P'` → provento, `'D'` → desconto.
5. **Bruto** = C1 + C2 + C3_proventos.
6. **Complemento salário mínimo** se `vinculo ∈ VINCULOS_PISO` e bruto < SM 2025 (R$ 1.518).
7. **Base prev** = bruto + adicionais com `ADICIONAL_INCIDE_PREV=1` + lançamentos com `LANCAMENTO_INCIDE_PREV=1`.
8. **INSS:** se `regime=RPPS` → `basePrev × aliqRPPS` (RPPS_CONFIG). Se `RGPS` → `TabelasImpostoService::calcularInssRgps(basePrev)` progressivo.
9. **IRRF:** `TabelasImpostoService::calcularIrrf(bruto - inss, dependentes)`.
10. **Consignações:** soma CONSIG_PARCELA pendentes da competência.
11. **Líquido** = bruto - INSS - IRRF - lançamentos descontos C3 - consignações.

**Persistência:**
- Upsert em `DETALHE_FOLHA` por (FUNCIONARIO_ID, FOLHA_ID).
- Após upsert, `PersistenciaRubricasService::persistirRubricasLote` grava `EVENTO_DETALHE_FOLHA` (1 por componente).

**Pré-processadores chamados antes do cálculo:**
- `InclusaoHorasExtrasService::incluirParaFolha` (linha 246) → converte HORA_EXTRA + PLANTAO_EXTRA aprovados em LANCAMENTO_FOLHA.

🔴 **Não chama `ApuracaoPontoService::fechar` nem nada que converta APURACAO_HORAS_FALTA em LANCAMENTO_FOLHA tipo 'D'.**

### Service: `App\Services\Folha\PersistenciaRubricasService`

Constantes:
```php
const EVENTO_VENCIMENTO_BASE = 'VENCIMENTO BASE';
const EVENTO_ANUENIO         = 'ANUENIO';
const EVENTO_INSS_RPPS       = 'INSS RPPS';
const EVENTO_INSS_RGPS       = 'INSS RGPS';
const EVENTO_IRRF            = 'IRRF';
const EVENTO_CONSIGNACOES    = 'CONSIGNACOES';
const EVENTO_COMPLEMENTO_SM  = 'COMPLEMENTO SALARIO MINIMO';
```

🔴 **Linha 137:** busca evento em `EVENTO.EVENTO_DESCRICAO` mas no schema PMSL real a coluna se chama `EVENTO_NOME`. **Quando rodar folha em PMSL pela primeira vez, vai logar warning "evento não encontrado" e não persistir EVENTO_DETALHE_FOLHA**. DETALHE_FOLHA continua certo (só perde o breakdown granular).

---

## 12. Domínio: Eventos e Rubricas

### Tabela `EVENTO` (catálogo de rubricas/eventos do motor)

| Coluna | Tipo | Notas |
|---|---|---|
| `EVENTO_ID` | int PK | |
| `EVENTO_NOME` | nvarchar nullable | (PMSL — não `EVENTO_DESCRICAO`!) |
| `EVENTO_TIPO` | nvarchar | |
| `EVENTO_IMPOSTO` | int NOT NULL | flag (0=não é imposto, 1=é) |
| `EVENTO_INCIDENCIA` | int nullable | → TABELA_GENERICA(RTG::TIPO_INCIDENCIA=31) |
| `EVENTO_SISTEMA` | int NOT NULL | flag (1=evento do sistema, 0=customizado) |
| `EVENTO_SALARIO` | int NOT NULL | flag (1=compõe base salarial) |
| `EVENTO_CODIGO` | nvarchar nullable | código gerencial |
| `EVENTO_CATEGORIA` | nvarchar nullable | |
| `EVENTO_INCIDE_INSS` | bit NOT NULL | |
| `EVENTO_INCIDE_IRRF` | bit NOT NULL | |
| `EVENTO_INCIDE_RPPS` | bit NOT NULL | |
| `EVENTO_ATIVO` | bit NOT NULL | |

### Tabela `RUBRICA` (catálogo PCCV gerencial)

Catálogo separado de rubricas do PCCV (PMSL): código, descrição, camada (1/2/3), forma de cálculo, EVENTO_ID associado.

| Coluna | Tipo | Notas |
|---|---|---|
| `RUBRICA_ID` | int PK | |
| `RUBRICA_CODIGO` | varchar | '030', '031', '032' (HE 50%, HE 100%, Plantão) |
| `RUBRICA_DESCRICAO` | nvarchar | |
| `EVENTO_ID` | int FK nullable | (se Schema::hasColumn) |
| ... | | |

(Detalhes a confirmar lendo `RubricasCatalogoSeeder.php`)

### Tabela `LANCAMENTO_FOLHA` (C3)

| Coluna | Tipo | Notas |
|---|---|---|
| `LANCAMENTO_FOLHA_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `FOLHA_ID` | int FK | |
| `RUBRICA_ID` | int FK | |
| `LANCAMENTO_TIPO` | char(1) | 'P' provento ou 'D' desconto |
| `LANCAMENTO_QTDE` | float | (horas, fatores) |
| `LANCAMENTO_VALOR_UNIT` | float | |
| `LANCAMENTO_VALOR_TOTAL` | float | |
| `LANCAMENTO_INCIDE_PREV` | bool | |
| `LANCAMENTO_INCIDE_IRRF` | bool | |
| `LANCAMENTO_ORIGEM` | varchar | 'manual', 'ponto', 'consig', etc. |
| `LANCAMENTO_OBS` | nvarchar | |
| `created_at`, `updated_at` | datetime | |

### Tabela `LOTACAO_EVENTO`

Eventos vinculados à lotação (não à folha) — gratificações permanentes:

| Coluna | Tipo | Notas |
|---|---|---|
| `LOTACAO_EVENTO_ID` | int PK | |
| `LOTACAO_ID` | int FK | |
| `EVENTO_ID` | int FK | |
| `LOTACAO_EVENTO_INFO` | nvarchar | |
| `LOTACAO_EVENTO_INICIO`, `_FIM` | varchar(6) cast Periodo | YYYYMM |
| `LOTACAO_EVENTO_VALOR` | decimal | |
| `LOTACAO_EVENTO_EXCLUIDO` | int | flag |
| `LOTACAO_EVENTO_DATA_CADASTRO` | datetime | |
| `USUARIO_ID` | int FK | quem cadastrou |

⚠️ **Status atual:** `LOTACAO_EVENTO` parece ser legado da v2. O motor v3 (`MotorFolhaService`) não lê esta tabela diretamente. Pode estar abandonado ou ser convertido em `ADICIONAL_SERVIDOR` em runtime — **a confirmar**.

---

## 13. Domínio: Consignações e Empréstimos

### Tabela `CONSIG_CONTRATO`

| Coluna | Tipo | Notas |
|---|---|---|
| `CONTRATO_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `STATUS` | varchar | 'ATIVO', 'QUITADO', 'CANCELADO' |
| `BANCO_ID` | int FK | banco consignante |
| `VALOR_TOTAL`, `PARCELAS_TOTAL`, `VALOR_PARCELA` | decimal/int | |
| (outros campos a confirmar com `ConsigParserService`) | | |

### Tabela `CONSIG_PARCELA`

| Coluna | Tipo | Notas |
|---|---|---|
| `PARCELA_ID` | int PK | |
| `CONTRATO_ID` | int FK | |
| `COMPETENCIA` | varchar(7) | "YYYY-MM" |
| `VALOR_PARCELA` | decimal | |
| `STATUS` | varchar | 'PENDENTE', 'PAGA', 'CANCELADA' |

**Lido pelo MotorFolhaService linhas 268-276:** soma parcelas PENDENTE da competência onde contrato ATIVO → vira `descConsig` (subtraído no líquido).

### Services

- `App\Services\ConsigParserService` — importa arquivo de consignante (CSV, layout específico)
- `App\Services\ConsigGeradorService` — gera arquivo de retorno pra consignante

---

## 14. Domínio: Adicionais Permanentes (C2)

### Tabela `ADICIONAL_SERVIDOR`

| Coluna | Tipo | Notas |
|---|---|---|
| `ADICIONAL_ID` | int PK | |
| `FUNCIONARIO_ID` | int FK | |
| `RUBRICA_ID` | int FK | (opcional, para resolver EVENTO_ID) |
| `ADICIONAL_TIPO` | varchar | 'fixo', 'percentual', 'percentual_sm' |
| `ADICIONAL_VALOR` | decimal | valor R$ ou % |
| `ADICIONAL_VIGENCIA_INICIO` | date | |
| `ADICIONAL_VIGENCIA_FIM` | date nullable | |
| `ADICIONAL_INCIDE_PREV` | bool | |
| `ADICIONAL_INCIDE_IRRF` | bool | |
| `ADICIONAL_OBS` | nvarchar | |

**Cálculo (motor linhas 380-395):**
```php
$val = match ($ad->ADICIONAL_TIPO) {
    'fixo'          => (float) $ad->ADICIONAL_VALOR,
    'percentual'    => $vencBase * ($ad->ADICIONAL_VALOR / 100),
    'percentual_sm' => $salarioMin * ($ad->ADICIONAL_VALOR / 100),
};
```

---

## 15. Domínio: Lançamentos Variáveis (C3)

Já documentado em [§12 — LANCAMENTO_FOLHA](#12-domínio-eventos-e-rubricas).

**Origens canônicas dos lançamentos:**
- `manual` → cadastrado por RH na tela de folha
- `ponto` → gerado por `InclusaoHorasExtrasService` (HE/Plantão)
- `consig` → não usado atualmente (consignação é lida direto de CONSIG_PARCELA)
- `falta` → 🔴 **NÃO IMPLEMENTADO** (gap APURACAO_PONTO → folha)
- `judicial` → futuro (decisões judiciais)

---

## 16. Domínio: Hora Extra, Plantão e Inclusão Automática

### Tabelas `HORA_EXTRA` e `PLANTAO_EXTRA`

(Schema completo a confirmar — reading a fazer)

Campos importantes lidos pelo `InclusaoHorasExtrasService`:
- `HORA_EXTRA_ID`, `FUNCIONARIO_ID`, `COMPETENCIA` (YYYY-MM), `STATUS` ('APROVADA' → 'INCLUIDA_FOLHA'), `TOTAL_HORAS`, `PERCENTUAL`, `TIPO_HORA_EXTRA`, `VALOR_CALCULADO`
- `PLANTAO_EXTRA_ID`, idem mas STATUS 'APROVADO' → 'INCLUIDO_FOLHA'

### Service: `App\Services\Folha\InclusaoHorasExtrasService`

**Idempotente** via STATUS. Re-execução pra mesma (folha, funcionário) não duplica porque registros já incluídos têm STATUS='INCLUIDA_FOLHA'/'INCLUIDO_FOLHA' e são filtrados.

**RUBRICA_CODIGO mapping (PMSL):**
- `030` → HE 50%
- `031` → HE 100% (e fallback de feriado)
- `032` → Plantão Extra

Insere em LANCAMENTO_FOLHA com `LANCAMENTO_INCIDE_PREV=1`, `LANCAMENTO_INCIDE_IRRF=1`, `LANCAMENTO_ORIGEM='ponto'`.

**Fluxo:**
```
Funcionário bate ponto extra → Coordenador aprova → HORA_EXTRA.STATUS='APROVADA'
   ↓ (cron diário ou ao iniciar fechamento de folha)
MotorFolhaService::calcularLoteParaFuncionarios chama
   ↓
InclusaoHorasExtrasService::incluirParaFolha
   ↓
INSERT LANCAMENTO_FOLHA tipo 'P' + UPDATE HORA_EXTRA.STATUS='INCLUIDA_FOLHA'
   ↓
Motor lê LANCAMENTO_FOLHA da folha e soma em provC3
   ↓
Folha reflete HE como provento bruto → INSS/IRRF aplicados
```

**Esse padrão é a referência arquitetural para implementar DESCONTO_FALTA quando o gap for fechado.**

---

## 17. Domínio: Tributação, Impostos, RPPS

### Service: `App\Services\TabelasImpostoService`

Autoridade única de tabelas fiscais 2026 (GAP-MF-08).

**Métodos:**
- `calcularInssRgps(float $base): float` — alíquotas progressivas INSS RGPS 2024+
- `calcularIrrf(float $base, int $dependentes): float` — IRRF progressivo, dedução por dependente R$ 189,59 (2026)

### Tabela `RPPS_CONFIG`

| Coluna | Tipo | Notas |
|---|---|---|
| `RPPS_CONFIG_ID` | int PK | |
| `VIGENCIA_INICIO`, `_FIM` | date | |
| `ALIQUOTA_SERVIDOR` | decimal | % (default 14) |
| `ALIQUOTA_PATRONAL` | decimal | informativo |

Motor lê: "última vigência" via `orderByDesc('VIGENCIA_INICIO')->value('ALIQUOTA_SERVIDOR')` com fallback 14.

### Tabela `TABELA_IMPOSTO`

Catálogo de tabelas de IRRF/INSS por vigência. Não é lida diretamente pelo motor (que delega ao `TabelasImpostoService`).

### Tabela `VIGENCIA_IMPOSTO`

Liga EVENTO a TABELA_IMPOSTO (qual evento usa qual tabela em qual período).

### Tabela `TRIBUTACAO`

Liga eventos a impostos a vínculos (qual desconto aplica em qual evento pra qual vínculo).

(Detalhes precisam ser lidos do model `App\Models\TabelaImposto`, `Tributacao`, `VigenciaImposto`.)

---

## 18. Domínio: eSocial / Contabilidade / Sentinela

### Service: `App\Services\EsocialXmlService`

Geração de XML do eSocial (eventos S-1000 etc.). Detalhes a documentar em iteração futura.

### Service: `App\Services\ContabilidadeService`

Integrado ao `ProcessarFolhaJob` (Bloco C concluído). Gera lançamentos contábeis a partir da folha processada.

### Sentinela de Integridade

Service: `IntegritySentinelService` (em `app/Services/Shadow/`?) — confirmar.
Job: `gente-sentinela-integridade` (cron a cada 5min). **Esperado falhar em base vazia** (P2.4 dívida técnica).

### `App\Services\Shadow\SnapshotManifestoCanonicoService`

Snapshot manifesto canônico — provavelmente versionamento de schema/dados (auditoria TCE-MA).

### `App\Services\Smoke\SmokeTeiaFolhaRunner`

Roda smoke E2E da folha em produção. Útil pós-deploy.

---

## 19. Tabelas Genéricas (TABELA_GENERICA + RTG)

`TABELA_GENERICA` é uma única tabela que armazena **muitos enums** identificados por `TABELA_ID`. Cada combinação `(TABELA_ID, COLUNA_ID)` é uma linha. `COLUNA_ID=0` representa o "nome da tabela" em si.

**Arquivo de constantes:** `app/MyLibs/RTG.php`. Constantes documentadas:

| Const | TABELA_ID | Descrição |
|---|---|---|
| TABELA_GENERICA | 0 | meta |
| ESCOLARIDADE | 1 | |
| SEXO | 2 | |
| CONTATO_TIPO | 3 | telefone, email, celular |
| DOCUMENTO | 4 | ATENÇÃO: legado, novo é TIPO_DOCUMENTO (tabela própria) |
| TIPO_AFASTAMENTO | 5 | LM, LMA, LP, etc. |
| HISTORICO | 6 | |
| MOTIVO | 7 | |
| STATUS | 8 | |
| PROGRESSO | 9 | |
| TIPO_UNIDADE | 10 | escola, posto saúde, secretaria, etc. |
| TIPO_DEPENDENTE | 11 | filho, cônjuge, etc. |
| TIPO_FINALIZACAO_DEPENDENTE | 12 | morte, maioridade, etc. |
| ESTADO_CIVIL | 13 | |
| TIPO_SANGUINEO | 14 | |
| RH_MAIS | 15 | + ou - |
| TIPO_CONSELHO_CLASSE | 18 | |
| TIPO_CONTA_BANCARIA | 19 | |
| TIPO_PIX | 20 | |
| UNIDADE_PORTE | 21 | |
| TIPO_ENTRADA_FUNCIONARIO | 22 | concurso, comissão, contratação direta |
| TIPO_SAIDA_FUNCIONARIO | 23 | exoneração, demissão, aposentadoria |
| TIPOS_DE_ATRIBUICOES | 24 | |
| ATRIBUICAO_LOTACAO_CARGA_HORARIA | 25 | 20h, 30h, 40h |
| LOTACAO_TIPO_FIM | 26 | |
| TIPO_STATUS_ESCALA | 27 | |
| TIPO_FERIADO | 28 | nacional, estadual, municipal, facultativo |
| FORMA_CALCULO | 29 | |
| TIPO_PARAMETRO_FINANCEIRO | 30 | |
| TIPO_INCIDENCIA | 31 | |
| TIPOS_FOLHA | 32 | folha mensal, 13o, férias, rescisão, etc. |
| STATUS_FOLHA | 33 | aberta, processando, processada, fechada |
| PESSOA_NACIONALIDADE | 43 | |
| PESSOA_RACA | 44 | |
| PESSOA_GENERO | 45 | |
| PESSOA_PCD | 46 | |
| PESSOA_RG_EXPEDIDOR | 47 | |
| PESSOA_CERTIFICADO_CATEGORIA | 48 | |
| PESSOA_CERTIFICADO_ORGAO | 49 | |
| PESSOA_CNH_CATEGORIA | 50 | |
| CERTIDAO_TIPO | 51 | |
| TIPO_ESCALA | 52 | |
| TIPO_CALCULO | 53 | |
| INTERVALO | 54 | |

**Schema da tabela:**
```
TABELA_GENERICA_ID (PK) | TABELA_ID | COLUNA_ID | DESCRICAO | ATIVO
```

⚠️ **Confirmado em produção PMSL (D5) que existe `COLUNA_DESCRICAO`** — o nome da coluna pode variar entre `DESCRICAO` e `COLUNA_DESCRICAO` em diferentes deploys. Schema-defensivo recomendado.

---

## 20. Fluxo End-to-End: Cadastro → Contratação → Lotação → Folha

### Cenário "feliz": novo servidor entra na folha de Maio/2026

1. **Cadastro de PESSOA**
   - Tela de cadastro de pessoa: nome, CPF, RG, endereço, contatos, escolaridade.
   - Eventualmente PESSOA_PRE_CADASTRO=1 (cidadão preenche autocadastro pelo portal).
   - RH valida e marca PESSOA_PRE_CADASTRO=0, PESSOA_STATUS=1 (após PIS/contato/banco/lotação completos).
   - `Pessoa::setUsuario(pessoaId)` é chamado → cria USUARIO + UsuarioPerfil(EXTERNO) + envia email com senha temporária.

2. **Contratação como FUNCIONARIO**
   - Cria FUNCIONARIO ligado à PESSOA com:
     - `FUNCIONARIO_DATA_INICIO` = data de admissão
     - `FUNCIONARIO_TIPO_ENTRADA` = (TABELA_GENERICA RTG::TIPO_ENTRADA_FUNCIONARIO=22)
     - `CARREIRA_ID` = ex: Magistério
     - `FUNCIONARIO_CLASSE` = ex: 'PNS1'
     - `FUNCIONARIO_REFERENCIA` = ex: 'A'
     - `FUNCIONARIO_REGIME_PREV` = 'RPPS' (default) ou 'RGPS'
     - `VINCULO_ID` = → VINCULO efetivo (ou comissao_puro etc.)
     - `FUNCIONARIO_ESTAGIO_PROBATORIO` = true (3 anos típico)
   - PESSOA agora tem 1 FUNCIONARIO ativo (DATA_FIM null).

3. **Lotação em SETOR**
   - Cria LOTACAO ligando FUNCIONARIO_ID + VINCULO_ID + SETOR_ID:
     - `LOTACAO_DATA_INICIO` = igual ou posterior à admissão
     - `LOTACAO_DATA_FIM` = null (vigente)
   - Cria ATRIBUICAO_LOTACAO ligando ATRIBUICAO_ID (Cargo+Função+Carga horária).
   - Pode ter mais de 1 lotação simultânea (ex: dois turnos em escolas diferentes).

4. **Escala** (se cargo bate ponto)
   - Cria DETALHE_ESCALA ligando ESCALA_ID + FUNCIONARIO_ID + ATRIBUICAO_ID.
   - Cria N DETALHE_ESCALA_ITEM (1 por dia da competência) ligando TURNO_ID.

5. **Adicionais permanentes** (opcional)
   - Cria ADICIONAL_SERVIDOR (insalubridade, gratificação de regência, etc.) com vigência aberta.

6. **Cadastro de DEPENDENTES** (impactam IRRF)
   - Cria DEPENDENTE (filho, cônjuge) com tipo (TABELA_GENERICA RTG::TIPO_DEPENDENTE=11).
   - **Atenção**: `PESSOA.PESSOA_DEPENDENTES_IRRF` é coluna numérica simples (qty) — preencher manualmente OU via lógica que conte DEPENDENTE elegíveis. **Confirmar fluxo**.

7. **Cadastro de banco**
   - PESSOA_BANCO com BANCO_ID oficial → necessário pra PESSOA_STATUS=1 e remessa bancária.

8. **Mês de Maio/2026 — Fechamento da Folha**
   - RH cria FOLHA com VINCULO_ID, FOLHA_COMPETENCIA="202605", FOLHA_TIPO=1 (mensal).
   - Adiciona setores via FOLHA_SETOR.
   - Pré-processadores rodam (ou são chamados pelo motor):
     - InclusaoHorasExtrasService → HE/Plantão aprovados viram LANCAMENTO_FOLHA tipo 'P'
     - 🔴 **(GAP)** ApuracaoPontoService::fechar → DEVERIA gerar LANCAMENTO_FOLHA tipo 'D' de DESCONTO_FALTA — não faz hoje.
   - RH dispara `MotorFolhaService::despacharProcessamentoAssincrono(folhaId, userId)` (ou síncrono `calcularFolha`).
   - Motor processa por chunks de 500, persiste DETALHE_FOLHA + EVENTO_DETALHE_FOLHA.
   - FOLHA_QTD_SERVIDORES e FOLHA_VALOR_TOTAL atualizados.
   - HISTORICO_FOLHA registra status 'PROCESSADA'.

9. **Geração de contracheques + remessa bancária**
   - `ContraChequeService` gera PDFs (talvez por demanda no portal).
   - `RemessaBancariaService` gera arquivo CNAB240 (`CNAB240Builder`).

10. **Integração contábil**
    - `ContabilidadeService` gera lançamentos contábeis (chamado pelo `ProcessarFolhaJob`).

11. **eSocial**
    - `EsocialXmlService` gera XML mensal.

---

## 21. Fluxo End-to-End: Frequência → Falta → Desconto na Folha

### Estado **atual** (com gap)

```
Funcionário registra batidas em REP eletrônico → REGISTRO_PONTO
   │
   ▼
RH gera ESCALA do mês com DETALHE_ESCALA + DETALHE_ESCALA_ITEM (turnos esperados)
   │
   ▼
Mensalmente: ApuracaoPontoService::calcular(funcionarioId, "202604")
   - Compara registros com escala
   - Calcula APURACAO_HORAS_TRAB / EXTRA / FALTA
   - Persiste APURACAO_PONTO STATUS='ABERTA'
   │
   ▼
Coordenador revisa, aprova justificativas (JUSTIFICATIVA_PONTO) → recalc
   │
   ▼
Coordenador FECHA: ApuracaoPontoService::fechar(apuracaoId)
   - Atualiza APURACAO_STATUS='FECHADA'
   - 🔴 TODO: gerar EVENTO_DETALHE_FOLHA de DESCONTO_FALTA → NÃO IMPLEMENTADO
   │
   ▼
Folha de Maio/2026 é processada
   - MotorFolhaService LÊ AFASTAMENTO (informativo, sem desconto direto)
   - MotorFolhaService LÊ LANCAMENTO_FOLHA → mas não há nada de "DESCONTO DE FALTA"
   - APURACAO_PONTO é IGNORADA pelo motor
   - 🔴 RESULTADO: servidor que faltou 5 dias em Abril recebe salário cheio em Maio
```

### Estado **desejado** (após fecho do gap)

Duas alternativas arquiteturais:

**Alternativa A — Service intermediário ("InclusaoFaltasService")**

Análogo ao `InclusaoHorasExtrasService`:

```
ApuracaoPontoService::fechar(apuracaoId)  ──gera evento──►  Listener (futuro)
                                                                  │
                                                                  ▼
                                          OU chamada direta no MotorFolhaService::calcular
                                                                  │
                                                                  ▼
                                          InclusaoFaltasService::incluirParaFolha(folhaId, funcIds, comp)
                                                                  │
                                                                  ▼
   Lê APURACAO_PONTO da competência ANTERIOR à folha (ou da mesma — depende da regra)
   Para cada funcionário com APURACAO_HORAS_FALTA > 0:
     diasFalta = horasFalta / 8 (ou jornada do contrato)
     valorDesconto = (vencBase / diasMes) * diasFalta
     INSERT LANCAMENTO_FOLHA tipo 'D' com:
       LANCAMENTO_OBS = "Desconto faltas competência anterior — ApuracaoId X"
       LANCAMENTO_INCIDE_PREV = false (questão fiscal a confirmar)
       LANCAMENTO_INCIDE_IRRF = false
       LANCAMENTO_ORIGEM = 'ponto'
   UPDATE APURACAO_PONTO SET APURACAO_STATUS='APLICADA_FOLHA'
                                                                  │
                                                                  ▼
   MotorFolhaService prossegue normalmente — vê LANCAMENTO_FOLHA tipo 'D' e desconta no líquido
```

**Vantagens:** padrão arquitetural já existe (InclusaoHorasExtras). Idempotente via STATUS. Auditável (LANCAMENTO_OBS aponta APURACAO_ID).
**Desvantagens:** decidir se desconta no mês corrente ou seguinte (Ronaldo confirmou que **deveria ser no seguinte** — ex: faltas Janeiro descontam folha Fevereiro). Implica que o motor lê APURACAO da competência anterior, não da mesma.

**Alternativa B — Gerar AFASTAMENTO automaticamente para "FALTA_INJUSTIFICADA"**

Cada dia com `DETALHE_ESCALA_ITEM_FALTA=1` E sem `ABONO_FALTA` E sem `AFASTAMENTO` cobrindo → cria AFASTAMENTO tipo `FALTA_INJUSTIFICADA` no fechamento do ponto.

E aí o `MotorFolhaLoteContext::diasAbonadosNoMes` é estendido para retornar `diasFaltaInjustificada` — usado pra calcular `(diasContratuais - diasFaltaInjustificada) / diasMes` como nova razão de pró-rata.

**Vantagens:** unifica modelo (tudo passa por AFASTAMENTO). Sem nova tabela.
**Desvantagens:** AFASTAMENTO tem semântica de "afastamento legítimo" — mistura conceitos. E ainda assim precisa que o motor SUBTRAIA dias de FALTA_INJUSTIFICADA da pró-rata (não basta listar como abonado).

**Decisão pendente — Ronaldo escolhe.**

---

## 22. GAPs Identificados

> Nesta seção, marcamos com prioridade: **P0** (bloqueia primeira folha real), **P1** (bloqueia go-live final), **P2** (pós-go-live), **P3** (melhoria).

### P0-1 🔴 APURACAO_PONTO → FOLHA não converte falta em desconto

**Local:** `app/Services/ApuracaoPontoService.php:144` — TODO sem implementação.
**Impacto:** servidor que falta no mês não tem desconto correspondente na folha do mês seguinte.
**Diagnóstico:** documentado em §21.
**Status:** decisão arquitetural pendente (Alternativa A vs B). Sem fix, primeira folha real PMSL ficará incorreta.

### P0-2 🔴 PersistenciaRubricasService usa EVENTO_DESCRICAO mas schema PMSL é EVENTO_NOME

**Local:** `app/Services/Folha/PersistenciaRubricasService.php:137`
```php
$id = DB::table('EVENTO')
    ->where('EVENTO_DESCRICAO', $descricao)  // 🔴
    ...
```
**Impacto:** quando MotorFolhaService rodar em PMSL, a query não encontra eventos pelo nome, loga warning "evento não encontrado" e EVENTO_DETALHE_FOLHA fica sem rubricas (DETALHE_FOLHA fica certo mas perde breakdown granular).
**Fix sugerido:** schema-defensive — `Schema::hasColumn('EVENTO', 'EVENTO_NOME') ? 'EVENTO_NOME' : 'EVENTO_DESCRICAO'`. Mesmo padrão dos seeders P1.2 já corrigidos.

### P0-3 ⚠️ MotorFolhaLoteContext::diasAbonadosNoMes compara TIPO string

**Local:** `app/Services/MotorFolha/MotorFolhaLoteContext.php:170`
```php
$tipo = (string) ($a->AFASTAMENTO_TIPO ?? '');
if (! in_array($tipo, $tiposAbonados, true)) { ... }
```
**Impacto:** `AFASTAMENTO_TIPO` no schema é INT (FK pra TABELA_GENERICA). `(string)123` nunca vai bater com `'LICENCA_MEDICA'`. **Resultado: zero dias abonados, zero ajuste de pró-rata por afastamento.**
**Fix:** JOIN com TABELA_GENERICA(RTG::TIPO_AFASTAMENTO=5) e comparar pelo COLUNA_DESCRICAO ou ID conhecido.

### P1-1 ⚠️ Motor pode não respeitar pró-rata de exoneração

**Local:** `MotorFolhaService.php:148-154` (`prepararContextoLote`)
**Suspeita:** lê `FUNCIONARIO_DATA_FIM` mas o filtro `aplicarFiltroServidorAtivoParaMotor` exclui DATA_FIM passada → exonerados não entram na folha. Se for exonerado **dentro do mês corrente**, deve estar incluído mas com pró-rata. **A confirmar lendo testes/seeders.**

### P1-2 ⚠️ Sentinela esperada falhar sem dados

Memória do projeto: "P2.4 Sentinela `gente-sentinela-integridade` falha a cada 5min (esperado em fresh deploy, base vazia)". Validar comportamento após popular dados demo.

### P2-1 (já mapeado) /api/v3/secretarias estrito vs defensivo

Antygravity fix `2c59fbb` corrigiu typo mas usou substituição estrita (não defensiva). Aceito como dívida técnica.

### P2-2 (já mapeado) route:cache falha por nome [login] duplicado

(Memória)

### P2-3 (já mapeado) composer.json minimum-stability dev + platform_check neutralizado

(Memória — investigar `composer why-not php 8.4`)

### P3-1 LOTACAO_EVENTO parece abandonado

**Local:** `app/Models/LotacaoEvento.php`
**Suspeita:** model existe e tem relações (lotacao, evento) mas o motor v3 (`MotorFolhaService`) não lê esta tabela. Possivelmente legado da v2 — confirmar e:
- Migrar dados pra ADICIONAL_SERVIDOR (se ainda usado)
- Ou marcar como deprecated formalmente

### P3-2 EVENTO.EVENTO_TIPO é nvarchar mas alguns lugares usam int

Schema PMSL: `EVENTO_TIPO (nvarchar, NULL)`. Em alguns places (a confirmar) é tratado como int. Não vi conflito ativo, mas marcar.

### P3-3 Mensagem CSP com typo `htts://` (não `https://`)

Já presente no Content-Security-Policy do Nginx (visto em curl). Não bloqueia hoje, vai bloquear se gstatic for usado.

### P3-4 Testes automatizados antes da primeira folha real

(Memória — sem testes, regressão é silenciosa.)

---

## 23. Decisões Arquiteturais

**DA-01 — 3 camadas de salário (C1/C2/C3).** Ver §11. Permite separar lógica estrutural (TABELA_SALARIAL) de lógica permanente (ADICIONAL_SERVIDOR) e variável (LANCAMENTO_FOLHA). Cada camada é injetável, testável, auditável.

**DA-02 — Branches por município.** Ver §2. Trade-off: mais branches pra manter, mas isolamento total de configurações.

**DA-03 — Schema defensivo via Schema::hasColumn.** Ver §2. Reduz acoplamento código↔migration, permite migrar gradualmente.

**DA-04 — Antygravity executa, Claude audita, Ronaldo decide.** Workflow comum às últimas sprints. Reduz risco de regressão silenciosa.

**DA-05 — TabelasImpostoService como autoridade fiscal única.** GAP-MF-08 centralizou cálculo INSS/IRRF. Antes era espalhado.

**DA-06 — MotorFolhaLoteContext sem queries lazy.** Pré-carregamento em batch evita N+1 (1 chunk de 500 servidores → 1 query AFASTAMENTO + 1 CARGO + 1 AVALIACAO em vez de 1500).

**DA-07 — Contracheque pode ser regenerado.** Não é gerado no fechamento de folha (gargalo). Gerado on-demand via ContraChequeService.

**DA-08 — Idempotência via UPDATE STATUS.** InclusaoHorasExtrasService marca HORA_EXTRA.STATUS='INCLUIDA_FOLHA' depois de inserir LANCAMENTO. Re-execução vê novo status e pula. Mesmo padrão deve ser usado em InclusaoFaltasService futuro.

**DA-09 — PII (CPF) com cast + blind index.** PESSOA_CPF_NUMERO criptografado em runtime via cast `App\Casts\PiiCpf`. PESSOA_CPF_HASH armazena blind index pra busca exata.

**DA-10 — Ferramenta `Periodo` cast.** Conversor entre `YYYYMM` (DB) ↔ `YYYY-MM` (PHP). Aplicado em `FOLHA_COMPETENCIA`, `ESCALA_COMPETENCIA`, `LOTACAO_EVENTO_INICIO`, etc.

---

## 24. Convenções de Schema

- **PKs e nomes de coluna em UPPERCASE**: `PESSOA_ID`, `FUNCIONARIO_NOME`, etc. Heritage do banco original SQL Server.
- **PKs sempre `<TABELA>_ID`**: PESSOA_ID, FUNCIONARIO_ID. Exceção: ABONO_FALTA usa `DETALHE_ESCALA_ITEM_ID` como PK (porque é 1:1).
- **Eloquent `protected $table` em uppercase** + `$primaryKey` em uppercase + `public $timestamps = false` (a maioria das tabelas legadas não tem created_at/updated_at; novas tem).
- **`$snakeAttributes = false`** em todos os models — preserva os nomes ALL_CAPS sem converter pra snake_case.
- **FKs em uppercase**: `FUNCIONARIO_ID`, `SETOR_ID`. Sem convenção de cascade — exclusões são feitas por código (`Pessoa::excluir`, `Escala::excluir`).
- **Soft deletes** apenas em `TURNO` (via `SoftDeletes` trait com `TURNO_DATA_EXCLUSAO`). Maioria usa flag `XXX_ATIVO=0` ou `XXX_EXCLUIDO=1`.
- **Datas em SQL Server**: `date` ou `datetime`. Cast Eloquent quando necessário pra Carbon.
- **TABELA_GENERICA é onipresente** — qualquer enum simples vai pra lá. Constantes em `App\MyLibs\RTG`.
- **Periodo formato YYYYMM** (string ou int) cast pra `App\Casts\Periodo`.

---

## 25. Glossário de Termos

- **Anuênio**: adicional por tempo de serviço (ex: 1% por ano). PMSL: 1% padrão.
- **Atribuição**: cargo+função+carga horária dentro de uma lotação (3-tupla).
- **C1/C2/C3**: 3 camadas do motor de folha — estrutural / permanente / variável.
- **CCD**: Cargo em Comissão (vínculo `comissao_puro`).
- **Competência**: mês de referência da folha. Formato YYYYMM no banco, "MM/YYYY" no front.
- **EFD-Reinf / eSocial**: obrigações fiscais federais (geração de XML).
- **Escala**: planejamento de turnos por funcionário em um setor por mês.
- **Folha**: pacote de pagamento de um conjunto de servidores em uma competência (FOLHA + N DETALHE_FOLHA + N EVENTO_DETALHE_FOLHA).
- **GAP-MF-XX**: gaps numerados durante development do MotorFolha (referenciados no código).
- **Gente** (em comentários): nome interno do produto.
- **HE**: hora extra (`HORA_EXTRA` table).
- **Lotação**: vínculo (efetivo, comissão, etc.) de um FUNCIONARIO em um SETOR por um período.
- **Magistério**: carreira específica (PNS1-PNS6, classes A-D).
- **PCCV**: Plano de Cargos, Carreira e Vencimentos.
- **PMSL/PMSLz**: Prefeitura Municipal de São Luís (z = sufixo nos nomes de seeders, ex: `FuncionariosPMSLzSeeder`).
- **PSS**: Processo Seletivo Simplificado (servidor temporário, RGPS).
- **REP**: Registrador Eletrônico de Ponto (Portaria 671). Origem do REGISTRO_PONTO.
- **RPPS**: Regime Próprio de Previdência Social (servidor estatutário). Em PMSL = IPAM.
- **RGPS**: Regime Geral (INSS). Para temporários, comissionados puros, etc.
- **RTG**: Relação de Tabelas Genéricas (`App\MyLibs\RTG`).
- **Sentinela**: serviço de monitoramento de integridade que valida dados periodicamente.
- **Servidor**: termo genérico para "funcionário público". Sinônimo de FUNCIONARIO no domínio.
- **TABELA_SALARIAL**: matriz vencimento por carreira×classe×referência.
- **TCE-MA**: Tribunal de Contas do Estado do Maranhão (auditor externo).
- **VINCULO_TIPO**: classificação do funcionário pra fins de motor (efetivo, comissao_puro, etc.).

---

## 26. Changelog

- **v1.0 — 09/05/2026 02h25** — Documento criado durante sessão produção PMSL. Mapeamento de 24 domínios + 89 models + 32 services + GAPs P0/P1/P2/P3. Trigger: descoberta do gap P0-1 (APURACAO_PONTO → folha sem implementação) durante planejamento de seeder demo PMSL. Autor: Claude (auditoria sistemática) sob direção de Ronaldo. Referência base para todo trabalho futuro no projeto.
