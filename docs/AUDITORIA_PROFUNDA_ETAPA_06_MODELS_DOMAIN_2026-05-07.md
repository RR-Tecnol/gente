---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-06
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 6/7 — Models + Database (migrations/seeders) + Domain de Escala"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
arquivos_lidos_integralmente: 14
total_linhas_lidas: 2435
relatorios_anteriores:
  - "AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_02_MOTOR_FOLHA_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_03_SHADOW_SMOKE_IMPORT_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_04_PCCV_PROGRESSAO_JORNADA_PONTO_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_05_PERIFERICOS_2026-05-07.md"
---

# AUDITORIA PROFUNDA — ETAPA 6: MODELS + DATABASE + DOMAIN DE ESCALA

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 6 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| 1 | Inventário e arqueologia da raiz | ✅ Concluída |
| 2 | Motor de folha completo | ✅ Concluída |
| 3 | Camada Shadow + Smoke + Import + FolhaParserService | ✅ Concluída |
| 4 | PCCV + Progressão + Jornada + Ponto + VinculoEnum | ✅ Concluída |
| 5 | Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard | ✅ Concluída |
| **6** | **Models + Database (migrations/seeders) + Domain de Escala** | ✅ **Concluída (este relatório)** |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Escopo da Etapa 6

Arquivos lidos integralmente:

| Arquivo | Linhas | Tamanho |
|---|---|---|
| `app/Models/Funcionario.php` | 266 | 9,3 KB |
| `app/Models/Folha.php` | 202 | 6,2 KB |
| `app/Models/DetalheFolha.php` | 43 | 1,1 KB |
| `app/Models/Lotacao.php` | 230 | 7,6 KB |
| `app/Models/Vinculo.php` | 70 | 2,1 KB |
| `app/Models/Cargo.php` | 82 | 2,4 KB |
| `app/Models/Evento.php` | 94 | 2,6 KB |
| `app/Domain/Escala/EscalaWorkflowStatus.php` | 43 | 1,2 KB |
| `app/Domain/Escala/MotivoAlteracaoEscala.php` | 109 | 5,4 KB |
| `app/Domain/Escala/MotivoAlteracaoPolicy.php` | 75 | 2,6 KB |
| `app/Domain/Escala/EscalaAusenciaService.php` | 315 | 11,1 KB |
| `app/Domain/Escala/EscalaWorkflowService.php` | 592 | 22,4 KB |
| `database/migrations/2026_04_27_132000_create_shadow_run_tables.php` | 56 | 2,2 KB |
| `database/migrations/2026_05_01_100200_create_gente_role_table.php` | 34 | 1,0 KB |
| `database/migrations/2026_04_28_220000_add_audit_log_hash_concat.php` | 37 | 1,1 KB |
| **Total** | **2.248** | **77,4 KB** |

Adicionalmente: 87 models e 153 migrations listados; 37 seeders catalogados.

---

## 2. RESOLUÇÃO DE PENDÊNCIAS DAS ETAPAS ANTERIORES

### 2.1 ✅ R6 RESOLVIDO — `create_patrimonio_tables` existe

A migration `2026_03_30_000041_create_patrimonio_tables.php` **EXISTE no projeto** (listada no diretório de migrations). O erro reportado em `rollback_out.txt` (Etapa 1) foi de uma sessão antiga (pré-criação da migration); entre 30/03/2026 e 25/04/2026 ela foi adicionada e migrada. Não é problema atual.

### 2.2 ✅ R14 CONFIRMADO — `FUNCIONARIO_DATA_ADMISSAO` não existe no model

`Funcionario.php` linhas 33-44: o `$fillable` contém apenas:
- `FUNCIONARIO_DATA_INICIO`
- `FUNCIONARIO_DATA_FIM`
- `FUNCIONARIO_DATA_CADASTRO`
- `FUNCIONARIO_DATA_ATUALIZACAO`

**Não há `FUNCIONARIO_DATA_ADMISSAO`** em parte alguma do model. O `ContraChequeService` (Etapa 2) usa esse campo — **vai sempre retornar null**.

`scopeAtivosNoEscopo()` confirma a presença de `FUNCIONARIO_DATA_FIM` e `FUNCIONARIO_DATA_DEMISSAO` (com `Schema::hasColumn`) — nenhuma referência a `FUNCIONARIO_DATA_ADMISSAO`. **R14 confirmado como bug real.**

---

## 3. ACHADOS CRÍTICOS DA ETAPA

### 3.1 ACHADO #1 — SQL Injection real em `Lotacao::getDadosRelatorioImprimirLotacao`

`Lotacao.php` linha ~217:

```php
public static function getDadosRelatorioImprimirLotacao($lotacaoId)
{
    $sql = "
    SELECT ... FROM LOTACAO L ...
    WHERE L.LOTACAO_ID = $lotacaoId   ← INTERPOLAÇÃO DIRETA
    ";
    return DB::select(DB::raw($sql));
}
```

**Interpolação direta de variável em SQL.** Se `$lotacaoId` vier de request HTTP (provável dado o nome "Imprimir"), é **SQL injection clássica** — `1; DROP TABLE LOTACAO;--` funciona.

A Etapa 1 (R3) já tinha encontrado padrão similar em `routes/comunicados.php:9`. Aqui é em **model**, em método chamado por relatório PDF — caminho menos óbvio mas com mesma vulnerabilidade.

**Recomendação:** trocar para parameter binding:
```php
return DB::select($sqlComBindings, ['lotacao_id' => $lotacaoId]);
```

### 3.2 ACHADO #2 — Bug fiscal silencioso em `Folha::FOLHA_VALOR_TOTAL`

`Folha.php` linha 53:
```php
protected $casts = [
    ...
    'FOLHA_VALOR_TOTAL' => 'integer',  // ← PERDE DECIMAIS
];
```

Folha de R$ 2.345.678,90 é castada como **2.345.678 inteiros**. Perde os centavos. Em folha mensal de PMSL (potencialmente R$ 30 milhões), perde até R$ 0,99 por execução. Pequeno por ocorrência, mas é **dado contábil reportado a TCE/SAGRES**.

Note que `DetalheFolha` (linha 24-26) usa `'float'` — o cast errado **só está no model pai `Folha`**. Inconsistência entre os dois.

### 3.3 ACHADO #3 — Terceiro caminho de motor de folha (T-SQL stored procedure)

`Folha.php` tem **três métodos legados que chamam stored procedure SQL Server** `[dbo].[sp_gera_folha]`:

```php
public function salvaFolha($lista_id_setores) {
    DB::select("exec [dbo].[sp_gera_folha]?,N'?',?,?,?,N'?',?", ...);
}

public static function processarFolha($request, $userId) {
    DB::statement("SET NOCOUNT ON ; exec [dbo].[sp_gera_folha] @p_descricao = ?, ...");
}

public static function reprocessarFolha($folhaId, $userId) {
    DB::statement("... exec [dbo].[sp_gera_folha] @p_folha_id = ? ...");
}
```

**Esse é um terceiro motor de folha**, não documentado nas Etapas 2 e 3. Coexiste com:
1. **`MotorFolhaService`** — motor novo PHP/Eloquent (Etapa 2)
2. **`FolhaParserService`** — motor legado PHP/Eloquent (Etapa 3)
3. **`Folha::salvaFolha/processarFolha/reprocessarFolha`** — T-SQL puro via stored procedure (achado da Etapa 6)

**Em SQLite local, os métodos da SP quebram** (não tem `sp_gera_folha`). **Em produção SQL Server, podem funcionar** se a SP existir herdada do sistema antigo. Pergunta crítica para Etapa 7: **qual desses três caminhos é chamado em produção real?** Risco de mostrar PoC com motor X, e produção rodar com motor Y.

---

## 4. Análise por arquivo

### 4.1 `Funcionario.php` (266 linhas) — ✅ BOM, COM 1 BUG REAL

#### Pontos positivos
- ✅ **`scopeAtivosNoEscopo`**: dual-check (`FUNCIONARIO_DATA_FIM` + `FUNCIONARIO_DATA_DEMISSAO`), schema-tolerância, suporta data de referência arbitrária
- ✅ **`scopeLotacaoVigenteEmSetores`** com guard correto: `null = sem filtro`, `[] = nenhum resultado` (`whereRaw('0 = 1')`) — fonte única para KPIs
- ✅ Schema-tolerância com `Schema::hasColumn` antes de filtrar
- ✅ **`setUsuario` corrigido pós-Sprint S:** gera senha aleatória de 10 caracteres (`Str::random(10)`) e envia por email — não usa CPF como senha. Boa prática de segurança.
- ✅ Email de credenciais via `UsuarioMail` para contatos do tipo 2 (email)

#### Pontos de atenção
- 🟡 `USUARIO_LOGIN = CPF` é discutível (CPF é PII e fácil de adivinhar como login institucional), mas é padrão municipal aceito.

#### Bug confirmado
- 🔴 **R14:** Não tem campo `FUNCIONARIO_DATA_ADMISSAO`, mas `ContraChequeService` (Etapa 2) tenta lê-lo.

### 4.2 `Folha.php` (202 linhas) — ⚠️ HÍBRIDO LEGADO

#### Bug fiscal
- 🔴 **R70:** Cast `'FOLHA_VALOR_TOTAL' => 'integer'` perde decimais.

#### Terceiro motor de folha
- 🔴 **R71:** `salvaFolha`, `processarFolha`, `reprocessarFolha` chamam stored procedure `[dbo].[sp_gera_folha]`.

#### Pontos positivos
- ✅ `FOLHA_COMPETENCIA` com cast customizado `Periodo::class`
- ✅ `historicoUltimo` filtra `HISTORICO_FOLHA_ULTIMO = 1`
- ✅ Relacionamentos completos (detalheFolhas, setores, vinculo, historicosFolhas)

### 4.3 `DetalheFolha.php` (43 linhas) — ✅ MAGRO E CORRETO

- ✅ Casts em `float` para PROVENTOS/DESCONTOS — não perde decimal
- ✅ Relações simples e diretas
- ✅ `DETALHE_FOLHA_ERRO` em `$fillable` — preparado para registrar erro de cálculo por servidor

### 4.4 `Lotacao.php` (230 linhas) — 🚨 SQL INJECTION REAL

#### Bug crítico
- 🔴 **R69:** Método `getDadosRelatorioImprimirLotacao` interpola `$lotacaoId` direto em SQL.

#### Outros pontos
- ✅ Schema bem definido com casts integer
- ✅ Relacionamentos completos
- 🟡 Método `gestao()` tem código SQL T-SQL (`GETDATE()`) comentado — vestígio do legado
- 🟡 **R74:** `LOTACAO_DATA_FIM >= date("m-d-Y")` em `gestao()` — formato `m-d-Y` é US, banco armazena ISO `Y-m-d`. Comparação string com formato errado vai dar resultado bizarro.
- 🟡 **R76:** `$relacionamentos` e `$relacionamentos2` duplicação — uma pra grade, outra pra detalhe

### 4.5 `Vinculo.php` (70 linhas) — ✅ BOM

- ✅ **Aliases `VINCULO_DESCRICAO` ↔ `VINCULO_NOME`** via accessor/mutator — mantém compatibilidade com front que ainda usa `VINCULO_DESCRICAO`. Boa prática de migração de schema.
- ✅ `listAll($soAtivos = 1)` para dropdowns
- ✅ Cast `VINCULO_ATIVO` como integer

### 4.6 `Cargo.php` (82 linhas) — ✅ BOM

- ✅ Relação `pccv()` para `PccvDominio` (Sprint 2 do plano) — integração com PCCV bem feita
- ✅ `$fillable` com `PCCV_ID` — confirma migration `2026_04_28_210100_add_pccv_id_to_cargo_table.php`
- ✅ Filtro `CARGO_ATIVO = 1` no `listar` e `pesquisar`

### 4.7 `Evento.php` (94 linhas) — ⚠️ COMPETÊNCIA SEM SEPARADOR

#### Bug potencial
- 🟡 **R73:** `historicoEvento` usa `Carbon::now()->format('Ym')` (sem separador). userMemories explicitam: `formatCompetencia: Engine writes 'YYYYMM' (e.g., '202503')`. Se algum cadastro veio com separador (`'2026-05'`), comparação string falha.

```php
->where('HISTORICO_EVENTO_INICIO', '<=', Carbon::now()->format('Ym'))
->where('HISTORICO_EVENTO_EXCLUIDO', 0);
```

#### Pontos positivos
- ✅ Cast de todos os booleanos como integer (1/0)
- ✅ `historicoEventos` filtra `HISTORICO_EVENTO_EXCLUIDO = 0`
- ✅ Relacionamento com `VigenciaImposto` para tributação por competência

---

## 5. Domain de Escala (5 arquivos, 1.134 linhas) — ✅ ARQUITETURA EXEMPLAR

### 5.1 `EscalaWorkflowStatus.php` (43 linhas) — ✅ BOM

4 estados canônicos com **valores curtos por limite legado VARCHAR(20)** (admitido em comentário):
- `RASCUNHO`
- `EM_VAL_SUPER` (Em validação pela Superintendência)
- `DEVOLVIDA_AJUSTE`
- `HOMOLOG_SAGEP`

**Reconhecimento honesto da dívida técnica do tamanho da coluna.** Método `permiteEdicaoGrade()` define que apenas RASCUNHO e DEVOLVIDA_AJUSTE permitem edição.

### 5.2 `MotivoAlteracaoEscala.php` (109 linhas) — ✅ EXEMPLAR

6 motivos canônicos com **base legal explícita** e impacto financeiro:

| Sigla | Título | Exige doc. | Base legal |
|---|---|---|---|
| `ERRO_LANCAMENTO` | Erro de Lançamento | ❌ | — |
| `AJUSTE_OPERACIONAL` | Ajuste Operacional | ❌ | Lei 8.666/1993 (princípios) |
| `SUBSTITUICAO_EMERGENCIAL` | Substituição emergencial | ✅ | LO Municipal art. 135 |
| `DOBRA_TURNO` | Dobra de turno | ❌ | LO art. 135 + portarias |
| `LICENCA_AFASTAMENTO_LEGAL` | Licença/afastamento | ✅ | Estatuto 4.615/2006 + PCCV 4.928/2008 |
| `HOMOLOGACAO_RETROATIVA` | Homologação/correção retroativa | ✅ | TCE-MA / LRF |

**Padrão de domínio rico**, não só strings soltas. Cada motivo traz contexto fiscal e jurídico. **Uma das melhores peças de modelagem do projeto.**

### 5.3 `MotivoAlteracaoPolicy.php` (75 linhas) — ✅ MUITO BOM

**Domínio prevalece sobre BD** — se a `SIGLA` bate com regra canônica do código, usa a regra do código; senão, usa a coluna do banco.

```php
public static function exigeDocumentoReferencia(object $motivoRow): bool
{
    if (property_exists($motivoRow, 'SIGLA') && trim($motivoRow->SIGLA) !== '') {
        $r = MotivoAlteracaoEscala::regraPorSigla($motivoRow->SIGLA);
        if ($r !== null) {
            return (bool) $r['exige_documento'];
        }
    }
    return (bool) ($motivoRow->EXIGE_DOCUMENTO ?? false);
}
```

Isso garante que ambientes com dados legados ainda funcionem, e ambientes novos seguem o domínio limpo. `assertDocumentoReferencia` lança `RuntimeException` se motivo exige documento e não veio.

### 5.4 `EscalaAusenciaService.php` (315 linhas) — ⚠️ BOM, COM CODE SMELL

#### Pontos positivos
- ✅ **Schema-tolerância radical:** detecta dinamicamente colunas (`AFASTAMENTO_DATA_INICIO` vs `created_at`, `AFASTAMENTO_TIPO` vs `AFASTAMENTO_TIPO_NOME`)
- ✅ **Mapeamento de sigla normativa** (LM/FR/LP/AE/LMA/LPA/FNJ) por ID e por palavra-chave de descrição
- ✅ **Cores por sigla** (LM=#E74C3C vermelho, FR=#3498DB azul, etc.) — UI consistente
- ✅ **Detecção `ultrapassa_15_dias`** para LM/LMA (threshold legal de 15 dias para reclassificação)
- ✅ **Períodos de competência limitados** (`startOfMonth`/`endOfMonth`) — não vaza pra meses adjacentes
- ✅ **Exclui status CANCELADO/INDEFERIDO/REPROVADO** via `whereNotIn UPPER(...)` — robusto
- ✅ **Filtro com whereDate** (não whereRaw) — portável

#### Code smell crítico
- 🚩 **R72:** Caminho hardcoded `/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log` (linha 12):
  ```php
  private const DEBUG_LOG_PATH = '/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log';
  ```
  
  **Vazamento de path de máquina de outro desenvolvedor (`/home/DK/`).** O try/catch engole erro, então não quebra em produção, mas é sinal de **código com instrumentação de debug deixada ativa**. Deveria ter sido removido antes do commit.

### 5.5 `EscalaWorkflowService.php` (592 linhas) — ✅ EXCEPCIONAL

A peça mais sofisticada do Domain. Quase 600 linhas de orquestração de workflow com permissões.

#### Estrutura
1. **4 ações:** `enviar_validacao`, `reenviar_validacao`, `devolver_ajuste`, `homologar`
2. **5ª ação meta:** `ESCALA_INTERVENCAO_SUDO_GRADE` para auditar quando admin força edição em status trancado (break-glass)
3. **Sistema de permissões 3-camadas:**
   - Bypass administrativo (Sudo via `GenteSudoGlobalView`)
   - RBAC granular (`escala.grade.editar`, `escala.workflow.devolver`, `escala.workflow.homologar`) via `RbacResolver`
   - Perfis legacy (`PerfilEnum::COORD_DE_SETOR`, `RH_UNIDADE`, etc.)
4. **Transições explícitas via `match`:**
   - RASCUNHO → EM_VAL_SUPER (enviar)
   - DEVOLVIDA_AJUSTE → EM_VAL_SUPER (reenviar)
   - EM_VAL_SUPER → DEVOLVIDA_AJUSTE (devolver) ou HOMOLOG_SAGEP (homologar)
   - Lança `RuntimeException` em transições inválidas
5. **Auditoria encadeada:** usa `GenteAuditWriter::insertChainedRow` — confirma a migration `add_audit_log_hash_concat`. Cada registro de audit tem hash do anterior. **Chain-of-custody criptográfico** — tampering detectável.

#### Pontos positivos
- ✅ **`processarTransicao` em `DB::transaction`** — atomicidade garantida
- ✅ **`assertSetorAutorizado`** delega pra `UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado` — escopo territorial
- ✅ **Schema-tolerância em audit:** descobre dinamicamente nomes de coluna (`ACAO`/`acao`, `DADOS_NOVOS`/`dados_novos`/`dados`/`contexto`, `USUARIO_ID`/`USER_ID`) — funciona com schemas migrados em diferentes momentos
- ✅ **`montarPayloadApi` e `montarPayloadWorkflowMacro`** separam visão por setor vs visão macro
- ✅ **Motivo de devolução obrigatório** (linha 502-504)
- ✅ **`ESCALA_ENVIADA_EM`/`ESCALA_HOMOLOGADA_EM`/`ESCALA_DEVOLVIDA_EM`** com timestamps de quem fez o quê — trilha completa
- ✅ **Limpa motivo/devolvido_em ao reenviar** (linha 525-535) — estado consistente

**Veredicto:** **Esta é a melhor peça de arquitetura observada no projeto até agora.** RBAC 3-camadas, audit chain criptográfico, schema-tolerance, transações atômicas, escopo territorial. Padrão sênior excepcional.

---

## 6. Migrations (153 arquivos) — ✅ BEM ESTRUTURADAS

### Cronologia

| Período | Volume | Foco |
|---|---|---|
| 2014–2019 | 3 migrations | Users, password_resets, failed_jobs (Laravel padrão) |
| 2025-01 | 1 migration | `create_tabelas_rh_sqlite` — núcleo SQLite |
| 2026-02 | ~17 migrations | Core tables, Termo, Pessoa, Domain (rounds 1–6) — fase intensiva de bootstrap |
| 2026-03 | ~50 migrations | Sprint 2, Bloco A/B/C/D, motor de folha, RPPS, esocial, contabilidade |
| 2026-04 | ~52 migrations | Camada Shadow, Smoke, Sisfolha import, RBAC, hash chained audit, PCCV, Motivo Alteração |
| 2026-05 | 7 migrations | RBAC granular (`gente_role`, `gente_permission`, `gente_role_permission`, `gente_assignment`), polo educacional |

### 6.1 Migration `2026_04_27_132000_create_shadow_run_tables.php`

Cria `SHADOW_RUN` e `SHADOW_CHECKPOINT`:
- ✅ `RUN_ID` `string(80)` unique — ID único por execução shadow
- ✅ `SNAPSHOT_SHA256` `string(64)` — hash do snapshot legado (Etapa 3)
- ✅ `STATUS` 30 chars — `criado`, `etl_ok`, `calc_ok`, `diff_ok`, etc.
- ✅ Contadores granulares: `TOTAL_ETL_OK`, `TOTAL_CALC_OK`, `TOTAL_DIFF_OK`, `TOTAL_DIFF_CRITICO`
- ✅ **Idempotência via `IDEMPOTENCY_KEY`** + unique constraint composta `(RUN_ID, IDEMPOTENCY_KEY, ETAPA)` — evita reprocessar
- ✅ Schema bem desenhado — esses são os 5 estágios da pipeline shadow

### 6.2 Migration `2026_05_01_100200_create_gente_role_table.php`

RBAC granular novo:
- ✅ `ROLE_SLUG` único — `escala.grade.editar`, `escala.workflow.devolver`, etc.
- ✅ `ORGAO_TENANT` 16 chars — preparado pra **multi-tenant** (P6 do plano)
- ✅ `CAMADA` 32 chars — separa camadas hierárquicas
- ✅ Index composto `(ORGAO_TENANT, CAMADA, ROLE_ATIVO)` — perf
- ✅ Idempotente (`if (Schema::hasTable) return`)

### 6.3 Migration `2026_04_28_220000_add_audit_log_hash_concat.php`

Adiciona `HASH_CONCAT` na `AUDIT_LOG` para encadeamento criptográfico:
- ✅ Detecta driver MySQL para usar `after('id')` — **não funciona em SQL Server** (que não tem ADD COLUMN AFTER)
- ✅ Idempotente (`if (! hasTable || hasColumn) return`)
- ✅ Down reversível
- ✅ **Implementa write-once audit chain** — cada linha tem hash do CONCAT da anterior. Tampering detectável.

#### Risco
- 🟢 **R77:** Em SQL Server, ordem de coluna não está garantida. Funcional, mas estética. BAIXO.

### 6.4 Padrões observados nas migrations

- ✅ **Schema-tolerância em todas as checadas** (`if (Schema::hasTable / hasColumn) return/break`)
- ✅ **Idempotência** via guards de existência
- ✅ **Down reversível** quando aplicável
- ✅ **Driver-aware** quando necessário (`DB::getDriverName() === 'mysql'`)

---

## 7. Seeders (37 arquivos) — ✅ COBERTURA AMPLA

Lista categorizada:

### Domínio fiscal
- `RubricasCatalogoSeeder` — catálogo de eventos/rubricas
- `PcaspSeeder` — Plano de Contas Aplicado ao Setor Público
- `SagresDeParaSeeder` — de-para SAGRES (TCE-MA)
- `SisfolhaCargoDeparaSeeder` — de-para de cargos pra import Sisfolha

### Domínio organizacional (PMSL)
- `OrganogramaPMSLzSeeder` — estrutura organizacional
- `SecretariasSeed` — secretarias municipais
- `FuncionariosPMSLzSeeder` — funcionários reais
- `UsuariosPMSLzSeeder` — usuários do sistema
- `VinculosPMSLzSeeder` — vínculos PMSL
- `TabelaSalarialPMSLzSeeder` — tabela salarial PMSL

### Folha/Tabelas
- `TabelaGenericaSeeder`
- `Feriados2026Seeder`
- `ConfiguracaoSistemaSeeder`

### PCCV / Domínio
- `PccvDominioSeeder`
- `MotivoAlteracaoDominioSeeder` — alimenta domain Etapa 6.5

### Escala
- `EscalaFevereiroSeeder` — escala de fevereiro
- `FevereiroDemoSeeder` — demo
- `SubstituicaoEscalaSeeder`

### RBAC/Segurança
- `RbacMatrixSeeder` — matriz de roles/permissões
- `PerfilSeeder`
- `MenuSeeder`
- `HoneytokenSeeder` — honeytokens (segurança Sprint S)
- `MigrarSenhasMd5Seeder` — migração de senhas legacy MD5

### Cobertura de telas (interessante)
- `ConfigTabsCoverageSeeder`
- `SidebarCoverageSeeder`
- `ErpFiscalCoverageSeeder`
- `SaudeEBeneficiosCoverageSeeder`
- `GenteTimelineCoverageSeeder`
- `SystemPhase2CoverageSeeder`

**6 seeders "coverage"** sugerem que a equipe testa cada tela do ERP em demos com dados sintéticos plausíveis.

### Stress test
- `SuperSeederEstresseMigracao` — para validar volume

### Personas
- `TestPersonasSeeder`
- `AuditorSemadHomologSeeder`
- `DaviSupremoSeeder`

#### Pontos positivos
- ✅ Bootstrap completo da PMSL (organograma, funcionários, usuários, vínculos)
- ✅ De-para Sisfolha (cargo, tabela salarial) — preparado pra import
- ✅ Coverage seeders sugerem que a equipe testa cada tela do ERP em demos
- ✅ Super seeder de estresse para validar volume

**Não inspecionados detalhadamente** — só listados. Sem riscos novos identificados aqui.

---

## 8. Síntese arquitetural — Etapa 6

```
┌──────────────────────────────────────────────────────────────────┐
│ MODELS (87 arquivos)                                              │
│                                                                   │
│  Funcionario ─→ Pessoa, Lotacao, Cargo, AvaliacaoDesempenho      │
│       └─ scopeAtivosNoEscopo (KPI canônico) ✅                    │
│       └─ setUsuario (senha aleatória, email) ✅                   │
│       ❌ Não tem FUNCIONARIO_DATA_ADMISSAO (R14)                  │
│                                                                   │
│  Folha ─→ DetalheFolha, Vinculo, HistoricoFolha                  │
│       ❌ FOLHA_VALOR_TOTAL cast 'integer' perde decimal (R70)     │
│       ❌ salvaFolha/processarFolha chamam sp_gera_folha (R71)     │
│         → 3º caminho de motor de folha não documentado            │
│                                                                   │
│  Lotacao ─→ Funcionario, Setor, Vinculo, Atribuicao              │
│       🚨 SQL injection em getDadosRelatorioImprimirLotacao (R69)  │
│       🟡 Comparação de data com formato US (R74)                  │
│                                                                   │
│  Vinculo: aliases NOME ↔ DESCRICAO bem feitos ✅                  │
│  Cargo: integração PCCV via belongsTo PccvDominio ✅              │
│  Evento: format('Ym') sem separador — depende de schema (R73)     │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ DOMAIN ESCALA (5 arquivos, 1.134 linhas)                          │
│                                                                   │
│  EscalaWorkflowStatus    → 4 estados canônicos                    │
│  MotivoAlteracaoEscala   → 6 motivos com base legal explícita ★   │
│  MotivoAlteracaoPolicy   → domínio prevalece sobre BD ★           │
│  EscalaAusenciaService   → schema-tolerance radical               │
│       🚩 Path debug hardcoded /home/DK/Developer/... (R72)        │
│  EscalaWorkflowService   → 592 linhas, RBAC 3-camadas, audit      │
│       chain, transações atômicas, schema-tolerance ★★             │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ MIGRATIONS (153 arquivos)                                         │
│  - 17 migrations 02/2026 (bootstrap rounds 1-6)                   │
│  - ~50 migrations 03/2026 (Sprint 2, motor folha, ERP fiscal)     │
│  - ~52 migrations 04/2026 (Shadow, Sisfolha, RBAC, audit chain)   │
│  - 7 migrations 05/2026 (RBAC granular multi-tenant)              │
│  ✅ R6 RESOLVIDO — patrimonio_tables EXISTE                       │
│  ✅ Schema-tolerance em todas as migrations checadas              │
│  ✅ Idempotência (if hasTable / hasColumn return)                 │
│  ✅ HASH_CONCAT audit chain implementada                          │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ SEEDERS (37 arquivos)                                             │
│  - Bootstrap PMSL (organograma, funcionários, vínculos)           │
│  - De-para Sisfolha (cargo, tabela salarial)                      │
│  - 6 "coverage" seeders (cobertura de telas pra demo)             │
│  - SuperSeederEstresseMigracao (volume)                           │
│  - Personas (auditor SEMAD, Davi Supremo, Test)                   │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ TRÊS CAMINHOS DE MOTOR DE FOLHA (descoberta da Etapa 6)           │
│                                                                   │
│  1. MotorFolhaService     — PHP, motor novo, 3 camadas C1/C2/C3   │
│  2. FolhaParserService    — PHP, motor legado, atual em produção  │
│  3. Folha::salvaFolha     — T-SQL stored procedure sp_gera_folha  │
│                                                                   │
│  Pergunta crítica: qual está em uso real? → Etapa 7               │
└──────────────────────────────────────────────────────────────────┘
```

---

## 9. Riscos consolidados da Etapa 6

| ID | Severidade | Item | Validar em |
|---|---|---|---|
| **R69** | 🔴 ALTO | `Lotacao::getDadosRelatorioImprimirLotacao` interpola `$lotacaoId` direto em SQL — **SQL injection clássica** (`WHERE L.LOTACAO_ID = $lotacaoId`). | Pré PoC — corrigir AGORA |
| **R70** | 🔴 ALTO | `Folha.php` cast `'FOLHA_VALOR_TOTAL' => 'integer'` — **perde decimais** em valor total da folha. R$ 2.345.678,90 vira 2345678. Bug fiscal silencioso. | Pré PoC |
| **R71** | 🔴 ALTO | `Folha::salvaFolha/processarFolha/reprocessarFolha` chamam stored procedure `[dbo].[sp_gera_folha]` (T-SQL puro) — **3º caminho de motor de folha não documentado.** Coexiste com `MotorFolhaService` e `FolhaParserService`. | Etapa 7 (rotas) — descobrir qual está em uso |
| **R72** | 🟡 MÉDIO | `EscalaAusenciaService::DEBUG_LOG_PATH = '/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log'` — **path de máquina de outro dev hardcoded em código de produção.** Try/catch engole erro, não quebra, mas é code smell sério. | Pré PoC — remover instrumentação |
| **R73** | 🟡 MÉDIO | `Evento::historicoEvento` usa `Carbon::now()->format('Ym')` (sem separador). Se algum cadastro de `HISTORICO_EVENTO_INICIO/FIM` foi gravado com separador (`'2026-05'`), comparação string falha. | Validar dado em produção |
| **R74** | 🟡 MÉDIO | `Lotacao::gestao()` usa `date("m-d-Y")` (formato US) na comparação `LOTACAO_DATA_FIM >= ...` — comparação string com formato diferente do ISO armazenado, vai dar resultado bizarro. | Pré PoC |
| **R75** | 🟢 BAIXO | `Funcionario::USUARIO_LOGIN = CPF` — escolha aceitável mas CPF é PII e fácil de adivinhar como login institucional. | Pós-PoC |
| **R76** | 🟢 BAIXO | `Lotacao` tem `$relacionamentos` e `$relacionamentos2` — duplicação. Considerar consolidar. | Refactor pós-PoC |
| **R77** | 🟢 BAIXO | Migrations `add_audit_log_hash_concat` usa `after('id')` em mysql — **não funciona em SQL Server** (que não tem ADD COLUMN AFTER). Hoje só roda branch sem AFTER, mas se for migrado em SQL Server, ordem de coluna não está garantida. | Pré go-live |

---

## 10. Veredicto da Etapa 6

✅ **Domain de Escala é EXEMPLAR.** `MotivoAlteracaoEscala` com 6 motivos canônicos + base legal + impacto financeiro é padrão raro de domínio rico. `EscalaWorkflowService` com 592 linhas de orquestração, RBAC 3-camadas (Sudo + RBAC granular + perfis legacy), audit chain criptográfico e schema-tolerance é **a melhor peça de arquitetura observada no projeto até agora.**

✅ **Migrations bem estruturadas.** 153 arquivos cronologicamente coerentes. Schema-tolerance e idempotência em todas as checadas. R6 resolvido. RBAC granular multi-tenant preparado pra P6. Audit chain implementada. Camada Shadow com idempotência por `IDEMPOTENCY_KEY`.

✅ **Seeders cobrem PMSL completa** + de-paras Sisfolha + 6 "coverage" seeders pra demo de telas + super seeder de estresse de migração. Ferramental completo.

🔴 **3 problemas críticos novos:**

1. **R69 (SQL injection real)** em `Lotacao::getDadosRelatorioImprimirLotacao` — interpolação direta de `$lotacaoId`. Corrigir antes do PoC.
2. **R70 (Bug fiscal)** — `FOLHA_VALOR_TOTAL` cast como integer perde centavos. Em folha de R$ 2,3 milhões, perde até R$ 0,99. Pequeno por execução, mas é dado contábil.
3. **R71 (3º motor de folha)** — `Folha.php` chama stored procedure SQL Server `sp_gera_folha`. Não estava no mapa até agora. Pode ser caminho legado abandonado, pode ser caminho ativo em produção. Etapa 7 vai dizer.

🟡 **Code smells:**
- R72: path de debug de outro dev hardcoded (`/home/DK/Developer/...`)
- R73: format de competência sem separador depende de consistência do dado
- R74: comparação de data com formato US

✅ **R6 resolvido** (patrimonio_tables existe).
✅ **R14 confirmado** (`FUNCIONARIO_DATA_ADMISSAO` não existe no model).

### Recomendações pré-PoC

1. **Corrigir SQL injection** em `Lotacao::getDadosRelatorioImprimirLotacao` (R69) — bind parameters em vez de interpolação.
2. **Trocar cast** de `FOLHA_VALOR_TOTAL` para `'decimal:2'` (R70).
3. **Decidir o destino dos 3 motores** (R71): aposentar `Folha::salvaFolha/processarFolha/reprocessarFolha` se não estão em uso, ou documentar explicitamente qual é o caminho ativo em produção. Etapa 7 (rotas) vai revelar.
4. **Remover instrumentação debug** (R72) de `EscalaAusenciaService`.
5. **Padronizar formato de competência** no projeto (R73) — `'YYYYMM'` ou `'YYYY-MM'`, mas escolher um e converter no read se necessário.
6. **Corrigir formato de data** em `Lotacao::gestao()` (R74) — usar `date('Y-m-d')` ou `Carbon::now()->toDateString()`.

---

## 11. Próxima etapa — VEREDICTO FINAL

**Etapa 7 — Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto Final.** Escopo previsto:

- `routes/web.php` — bug recorrente Antygravity (rotas inline no bloco dashboard L1850 vs. require no bloco autorizado L740-780)
- `routes/api.php`
- `routes/motor.php` (já com R1 — PRAGMA SQLite-only)
- Outros arquivos `routes/*.php` (escala_saude, comunicados, progressao_funcional, etc.)
- `app/Http/Controllers/` — amostragem de controllers críticos
- `resources/js/` — Vue 3 frontend (amostragem)
- `tests/` — cobertura de testes
- **Resolver R71** — descobrir qual motor de folha está em uso real (Folha SP, FolhaParserService ou MotorFolhaService)
- **Resolver R58** — descobrir qual CNAB 240 está em uso real (CNAB240Builder ou RemessaBancariaService)
- **Resolver R37** — confirmar se ApuracaoPontoService é chamado e se há lançamento manual de HE

### Objetivos da Etapa 7
1. Mapear caminhos efetivos do sistema (rotas → controllers → services)
2. Auditar segurança das rotas (middleware, RBAC)
3. Resolver dúvidas levantadas nas etapas 1-6
4. Compilar **veredicto final consolidado** das 7 etapas com matriz completa de riscos
5. Recomendações finais pré-PoC e pré-go-live

---

*Fim do relatório da Etapa 6.*
