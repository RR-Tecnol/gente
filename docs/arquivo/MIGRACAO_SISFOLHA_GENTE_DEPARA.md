# De-para e riscos — Migração SISFOLHA → GENTE v3 (homologação de carga)

**Contexto:** preparar *Super-Seeder* / ETL alinhado ao legado, teste de estresse (ordem de grandeza 30k servidores, histórico longo) e evitar distorção do **Art. 139 (MDE)**, **PCCV (Lei 4.928/2008)** e desempenho do **Kanban / auditoria**.

**Limitação honesta:** o repositório **não** inclui o *schema* do SISFOLHA. A coluna `MATRICULA_COMPLETA` e nomes de tabelas do legado devem ser cruzados com o **dicionário de dados** e **ERD** oficiais da prefeitura. Abaixo: **como o GENTE v3 modela** o domínio e **o que mapear na entrada**.

---

## 1. Identidade (FUNCIONARIO + PESSOA)

| Origem conceitual (SISFOLHA / RH) | Destino GENTE | Notas / FK |
|-----------------------------------|---------------|------------|
| Matrícula (várias máscaras possíveis) | `FUNCIONARIO.FUNCIONARIO_MATRICULA` (string) | **Não** há, no código analisado, algoritmo de dígito verificador. Normalizar **uma** máscara canónica no ETL + índice único se regra de negócio exigir. |
| CPF | `PESSOA.PESSOA_CPF_NUMERO` + validação | API de cadastro: regex de formato, **unicidade** no fluxo de criação. **Múltiplos vínculos** no mesmo CPF: no modelo, uma `PESSOA` pode teoricamente ligar a **vários** `FUNCIONARIO`? Padrão usual é 1:1; confirmar regra SISFOLHA (ex.: reingresso = novo `FUNCIONARIO_ID`). |
| Nome, nasc., filiação, RG, PIS, título | Colunas `PESSOA` (ver `Pessoa::$fillable` e migrations) | Muitas colunas são **nullable** nas adições incrementais — validar `NOT NULL` no SQL Server alvo. |
| Situação “ativo/afast/aposent” | **Não** existe `STATUS_VINCULO` canónico no modelo lido | **Fallback:** `FUNCIONARIO_DATA_FIM` (nulo ou futuro) + lotação ativa `LOTACAO.LOTACAO_DATA_FIM` nula. Migração “suja” que marcar tudo ativo distorce MDE/headcount. |
| Cargo, carreira, classe (magistério) | `FUNCIONARIO.CARGO_ID`, `CARREIRA_ID`, `FUNCIONARIO_CLASSE/REFERENCIA` | **PCCV:** `CARGO.PCCV_ID` → `PCCV_DOMINIO` (se coluna existir). Erro na entrada = passivo (professor pago como “geral”). |
| Carga horária | `CARGO.CARGO_CARGA_HORARIA` e/ou `FUNCIONARIO_CARGA_HORARIA` (se coluna) | Escala aplica 20/24/40h em regra de regência; alinhar ao PCCV. |

**Riscos Art. 139 / MDE:** o cálculo dos 25% **não** está no módulo de `FUNCIONARIO` — depende de **classificação orçamentária** (secretaria, natureza de despesa, vinculação MDE) e de **receita** (`RECEITA_MUNICIPIO` é placeholder de desenvolvimento). O *seed* de carga deve marcar `UNIDADE`/`SETOR` corretos para **educação**, não só “um setor qualquer ativo”.

---

## 2. Lotação (LOTACAO + VINCULO + UNIDADE)

| Legado (conceito) | Destino GENTE | Campos críticos |
|-------------------|---------------|-----------------|
| Lotação principal / histórica | `LOTACAO` | `FUNCIONARIO_ID`, `SETOR_ID`, `VINCULO_ID`, `LOTACAO_DATA_INICIO`, `LOTACAO_DATA_FIM` |
| Secretaria / órgão | `SETOR` → `UNIDADE` | `UNIDADE.UNIDADE_SIGLA` (ex.: `SEMED`, `SEMUS`) usada no *seed* PMSLz. |
| Código exógeno (ex. `SEC001`) | **Não** há, no repositório, tabela padrão `UNIDADE_CODIGO_LEGADO` mapeada para toda a base | **Ação ETL:** criar tabela de *staging* ou coluna `UNIDADE_CODIGO_EXTERNO` (projeto) + *join* para `UNIDADE_ID` **antes** de inserir `LOTACAO`. Hoje o GENTE opera com **sigla canónica** (`OrganogramaPMSLzSeeder`). |
| Múltiplas lotações | Várias linhas `LOTACAO` | Definir qual é “ativa” para fila de escala: tipicamente `LOTACAO_DATA_FIM` **nula** e, se houver conflito, a mais recente por regra de negócio. |

---

## 3. Cargo (CARGO + PCCV)

| Legado | Destino GENTE | Risco |
|--------|---------------|--------|
| Código cargo / tabela | `CARGO` (`CARGO_NOME`, `CARGO_ID`, `CARGO_CARGA_HORARIA`, `CARGO_SALARIO` / remuneração) | *De-para* por nome **frágil**; preferir tabela de equivalência CódigoSISFOLHA → `CARGO_ID`. |
| Estatuto (Magistério / Saúde / Geral) | `CARGO.PCCV_ID` → `PCCV_DOMINIO` | `PccvDominioSeeder` + coluna de vínculo. Erro = folha e escala incompatíveis com Lei 4.928. |

---

## 4. Escala (ESCALA + DETALHE_ESCALA + DETALHE_ESCALA_ITEM) — *Kanban*

| Conceito | Tabela GENTE | Chaves / observação |
|----------|-------------|----------------------|
| Competência (mês) | `ESCALA.ESCALA_COMPETENCIA` (formato `Y-m`) + `ESCALA.SETOR_ID` | |
| Grade por servidor | `DETALHE_ESCALA` (funcionário x escala) + `DETALHE_ESCALA_ITEM` (dia) | `TURNO_ID` (FK `TURNO.TURNO_SIGLA` M/V/N/…), opcional `MOTIVO_ALTERACAO_ID` (justificativas) |
| “Escala **realizada**” (planejamento) | Células com `TURNO_ID` em `DETALHE_ESCALA_ITEM` | É **operacional**; **não** paga ninguém sozinha. |
| Escala pública / workflow | `ESCALA.ESCALA_STATUS` (se existir) | Usado em regra de *delete* (publicada exige justificativa). |

**Carga 30k + 10 anos:** a query de leitura da escala (ver `routes/escala_trabalho.php`) faz *join* pesado por competência e setor. **Recomendação** (a confirmar DBA no SQL Server):

- Índice composto em `ESCALA(ESCALA_COMPETENCIA, SETOR_ID)` + em `DETALHE_ESCALA(ESCALA_ID, FUNCIONARIO_ID)`.
- Índice em `DETALHE_ESCALA_ITEM(DETALHE_ESCALA_ID, DETALHE_ESCALA_ITEM_DATA)`.
- Evitar *full scan* em `AUDIT_LOG` em relatórios; particionar por data em produção se volume explodir.

O código **não** lista esses índices como migrations versionadas; são **necessidade de produção** para o SLA &lt; 2s.

---

## 5. “Frequência” e folha paga (substituição **paga** vs **planejada**)

O GENTE **separa** estes domínios:

| Domínio | Tabelas / trilha | Diferença |
|---------|------------------|-----------|
| **Planejamento** (turno do dia) | `DETALHE_ESCALA_ITEM` + `TURNO` | *Kanban*; pode existir **sem** lançamento de folha. |
| **Convite / troca** | `SUBSTITUICAO_ESCALA` (e rotas *substituicoes*) | Fluxo de aceite; **não** é, por si, “valor pago”. |
| **Pagamento** (proventos) | `FOLHA` → `DETALHE_FOLHA` + rubricas; `EventoDetalheFolha` / `EVENTO` conforme motor | A “substituição **paga**” aparece como **rubrica** em folha (ex. HE, extra, adicional) mapeada em `RUBRICA` / *Motor* e **SAGRES** `SAGRES_EVENTO_DEPARA` (códigos `RUBRICA_SISTEMA` → TCE-MA), **não** duplicada na escala. |
| **Registro de ponto** (batida) | `REGISTRO_PONTO` (real) + `PONTO_CONFIG_FUNCIONARIO` (esperado/tolerância) | *Frequência* = batidas, não a mesma tabela que escala. |
| **Banco de horas (ledger)** | `JORNADA_LEDGER` (se existir) | Alternativo a *ponto* para saldo. |
| **Histórico funcional** (vida) | `HISTORICO_FUNCIONAL` (progressão, `HISTORICO_TIPO` ingresso/progressao/…) | **Não** substitui o histórico de **folha**; complementa. |
| **Histórico de folha** | `HISTORICO_FOLHA` (model) | |
| **Evento de remessa SAGRES** | `SAGRES_GERACAO`, `SAGRES_EVENTO_DEPARA` | TCE-MA, não o “evento de escala”. |

**Escala “realizada”** ≠ **substituição paga**: a primeira é **turno** no calendário; a segunda, **valor** no `DETALHE_FOLHA` (via eventos/rubricas) quando o SISFOLHA processar o mês.

---

## 6. Eventos de folha (referência técnica GENTE)

- **Cálculo / lançamentos:** `FOLHA`, `DETALHE_FOLHA` (`app/Models/DetalheFolha.php`), itens de evento `EVENTO_DETALHE_FOLHA` (liga detalhe a valor por evento).
- **Mapeamento TCE:** `SAGRES_EVENTO_DEPARA` (seed `SagresDeParaSeeder`: `RUBRICA_SISTEMA` ↔ `SAGRES_COD`).

O legado SISFOLHA deve mapear **códigos de verba** para `RUBRICA`/`EVENTO` GENTE **antes** de simular carga; caso contrário o *Motor* e o SAGRES divergem.

---

## 7. Nulos e fallbacks (migração “suja”)

| Situação típica | Risco | Fallback sugerido (a validar com RH) |
|-----------------|--------|--------------------------------------|
| CPF nulo no legado | Pessoa incompleta | *Staging*; não promover a produção sem CPF se política for 1:1 SISFOLHA |
| Matrícula nula / duplicada | Quebra unicidade / relatório | Gerar *surrogate* só se regra local permitir; preferir rejeitar lote |
| Lotação sem fim, mas funcionário inativo | Headcount MDE errado | Fechar `LOTACAO_DATA_FIM` + `FUNCIONARIO_DATA_FIM` coerentes |
| Código de unidade desconhecido | FK `SETOR_ID` inválida | Tabela *staging* + `UNIDADE` default “A CLASSIFICAR” (projeto) |
| `CARGO` sem `PCCV_ID` | Escala aceita, folha errada | *Job* de enriquecimento pós-carga a partir de planilha magistério |
| Escala / item sem `TURNO` no legado | Não bate TURNO_ID | *Map* de código legado → `TURNO.TURNO_SIGLA` + defaults auditados |

---

## 8. Resumo: 5 tabelas críticas — cadeia de *Foreign Keys* lógica

1. **PESSOA** ← `PESSOA_ID` ← **FUNCIONARIO**
2. **UNIDADE** / **SETOR** ← `SETOR_ID` ← **LOTACAO** → **FUNCIONARIO**
3. **CARGO** (e **PCCV_DOMINIO** via `PCCV_ID`) ← **FUNCIONARIO**
4. **ESCALA** → **DETALHE_ESCALA** → **DETALHE_ESCALA_ITEM** → **TURNO** (+ opcional `MOTIVO_ALTERACAO_DOMINIO`)
5. **Frequência real** **REGISTRO_PONTO**; **FOLHA** / **DETALHE_FOLHA** para **pagamento**; **HISTORICO_FUNCIONAL** para **vida** (não confundir com ponto)

---

## 9. Checklist homologação 30k / 10 anos

- [ ] Índices no SQL Server (escala, item, folha, `REGISTRO_PONTO` por `FUNCIONARIO_ID`+data)
- [ ] Tabela **de-para** unidade legado → `UNIDADE_ID`
- [ ] Tabela de-para matrícula / cargo / PCCV
- [ ] Regra explícita “quem entra no denominador MDE (educação)” no **dado**, não só “ativo”
- [ ] `AUDIT_LOG`: tamanho de *batch* inserções; arquivamento por ano; timeout em *trigger* (se houver)
- [ ] *Super-Seeder* que gera a **mesma cardinalidade** e FKs que o ETL real, em *staging* primeiro

*Documento interno de alinhamento técnico; revisar com equipe SISFOLHA/Dados antes de executar carga plena.*

---

## 10. Super-Seeder de estresse (homolog)

Com `GENTE_STRESS_SEED=1`, o `SecretariasSeed` (invocado pelo `DatabaseSeeder`) chama `SuperSeederEstresseMigracao` no final. Execução isolada (sem repor o resto da massa):

`GENTE_STRESS_SEED=1 GENTE_STRESS_N=30000 GENTE_STRESS_AUDIT=50000 php artisan db:seed --class=SuperSeederEstresseMigracao`

Variáveis opcionais: `GENTE_STRESS_CHUNK` (padrão 500), `GENTE_STRESS_COMP` (competência `Y-m`). Índices sugeridos para a grade: `gente/database/scripts/sqlserver_kanban_stress_indexes.sql`. Verificação pós-carga (SQL corrigido para `UNIDADE`/`SETOR`): `gente/database/scripts/verify_pos_seeder_integridade.sql`.
