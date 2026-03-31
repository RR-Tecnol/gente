# SPRINT 3 — Motor de Folha de Pagamento
**Versão:** 1.0 | **Data:** 16/03/2026
**Projeto:** GENTE v3 | Prefeitura Municipal de São Luís / MA | RR TECNOL
**Executor:** Antygravity (Gravity)
**Auditor:** Claude (Tech Lead)

> Leia este documento integralmente antes de qualquer ação.
> Nenhuma implementação deve começar sem autorização explícita do Tech Lead.
> Este documento substitui qualquer instrução anterior sobre motor de folha.

---

## VISÃO GERAL

O motor de folha do GENTE v3 usa arquitetura de **3 camadas + catálogo de rubricas**.
Rubrica é um **tipo** (catálogo fixo ~30 itens), não uma ocorrência por servidor.
O resultado mensal é compacto: 1 linha em DETALHE_FOLHA + itens sob demanda em ITEM_FOLHA.

### Princípios inegociáveis
- Zero queries dentro do loop de cálculo por servidor
- Batch de dados antes do loop, cálculo PHP puro dentro
- ITEM_FOLHA gerado sob demanda (holerite/TCE), não no cálculo
- Cada camada tem interface admin própria — nada é hardcoded
- Suporta 25.000+ servidores em < 60s

---

## PARTE 1 — BANCO DE DADOS

### 1.1 Tabelas a CRIAR (novas)

#### ADICIONAL_SERVIDOR — Camada 2 (adicionais permanentes por servidor)
```sql
CREATE TABLE ADICIONAL_SERVIDOR (
    ADICIONAL_ID          INTEGER PRIMARY KEY AUTOINCREMENT,
    FUNCIONARIO_ID        INTEGER NOT NULL,
    RUBRICA_ID            INTEGER NOT NULL,
    ADICIONAL_TIPO        VARCHAR(20) NOT NULL, -- fixo | percentual | percentual_sm
    ADICIONAL_VALOR       DECIMAL(12,2) NOT NULL DEFAULT 0,
    ADICIONAL_BASE        VARCHAR(30) NULL,     -- null | salario_base | salario_minimo
    ADICIONAL_INCIDE_PREV BOOLEAN DEFAULT 1,
    ADICIONAL_INCIDE_IRRF BOOLEAN DEFAULT 1,
    ADICIONAL_INCIDE_FGTS BOOLEAN DEFAULT 0,
    ADICIONAL_VIGENCIA_INICIO DATE NOT NULL,
    ADICIONAL_VIGENCIA_FIM    DATE NULL,        -- null = permanente
    ADICIONAL_ATO_ADM     VARCHAR(100) NULL,    -- ex: Portaria 123/2026
    ADICIONAL_OBS         TEXT NULL,
    created_at            DATETIME,
    updated_at            DATETIME
);
```

#### LANCAMENTO_FOLHA — Camada 3 (variáveis mensais)
```sql
CREATE TABLE LANCAMENTO_FOLHA (
    LANCAMENTO_ID         INTEGER PRIMARY KEY AUTOINCREMENT,
    FUNCIONARIO_ID        INTEGER NOT NULL,
    FOLHA_ID              INTEGER NOT NULL,
    RUBRICA_ID            INTEGER NOT NULL,
    LANCAMENTO_TIPO       CHAR(1) NOT NULL,     -- P=provento D=desconto
    LANCAMENTO_QTDE       DECIMAL(8,2) DEFAULT 1,
    LANCAMENTO_VALOR_UNIT DECIMAL(12,2) NOT NULL,
    LANCAMENTO_VALOR_TOTAL DECIMAL(12,2) NOT NULL, -- qtde × unit (calculado ao salvar)
    LANCAMENTO_INCIDE_PREV BOOLEAN DEFAULT 1,
    LANCAMENTO_INCIDE_IRRF BOOLEAN DEFAULT 1,
    LANCAMENTO_ORIGEM     VARCHAR(20) DEFAULT 'manual', -- manual|ponto|judicial|consignacao
    LANCAMENTO_OBS        TEXT NULL,
    created_at            DATETIME,
    updated_at            DATETIME
);
```

### 1.2 Tabelas a ALTERAR (adicionar colunas)

#### RUBRICA — adicionar campos de controle
```sql
ALTER TABLE RUBRICA ADD COLUMN RUBRICA_CAMADA      INTEGER DEFAULT 1; -- 1|2|3
ALTER TABLE RUBRICA ADD COLUMN RUBRICA_CALCULO     VARCHAR(30) NULL;
-- valores: fixo|tabela_salarial|percentual_base|percentual_sm|irrf|inss_rgps|inss_rpps
ALTER TABLE RUBRICA ADD COLUMN RUBRICA_INCIDE_FGTS BOOLEAN DEFAULT 0;
ALTER TABLE RUBRICA ADD COLUMN RUBRICA_SAGRES_COD  VARCHAR(10) NULL;
ALTER TABLE RUBRICA ADD COLUMN RUBRICA_ORDEM       INTEGER DEFAULT 0;
```

#### ITEM_FOLHA — adicionar campos de detalhamento
```sql
ALTER TABLE ITEM_FOLHA ADD COLUMN ITEM_CAMADA         INTEGER DEFAULT 1;
ALTER TABLE ITEM_FOLHA ADD COLUMN ITEM_QTDE           DECIMAL(8,2) DEFAULT 1;
ALTER TABLE ITEM_FOLHA ADD COLUMN ITEM_VALOR_UNIT     DECIMAL(12,2) NULL;
ALTER TABLE ITEM_FOLHA ADD COLUMN ITEM_INCIDE_PREV    BOOLEAN DEFAULT 1;
ALTER TABLE ITEM_FOLHA ADD COLUMN ITEM_INCIDE_IRRF    BOOLEAN DEFAULT 1;
```

#### VINCULO — adicionar flags do motor
```sql
ALTER TABLE VINCULO ADD COLUMN VINCULO_TIPO        VARCHAR(30) DEFAULT 'efetivo';
-- valores: efetivo|servico_prestado|comissao_puro|efetivo_cc_m1|efetivo_cc_m2|funcao_confianca|pss
ALTER TABLE VINCULO ADD COLUMN VINCULO_REGIME      VARCHAR(10) DEFAULT 'RPPS'; -- RPPS|RGPS
ALTER TABLE VINCULO ADD COLUMN VINCULO_FGTS        BOOLEAN DEFAULT 0;
ALTER TABLE VINCULO ADD COLUMN VINCULO_INSS        BOOLEAN DEFAULT 1;
ALTER TABLE VINCULO ADD COLUMN VINCULO_IRRF        BOOLEAN DEFAULT 1;
ALTER TABLE VINCULO ADD COLUMN VINCULO_ANUENIO_PCT DECIMAL(5,2) DEFAULT 1.00;
```

#### DETALHE_FOLHA — expandir para base de cálculo previdência
```sql
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_BASE_PREV    DECIMAL(12,2) NULL;
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_BASE_IRRF    DECIMAL(12,2) NULL;
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_DESC_PREV    DECIMAL(12,2) DEFAULT 0;
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_DESC_IRRF    DECIMAL(12,2) DEFAULT 0;
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_DESC_OUTROS  DECIMAL(12,2) DEFAULT 0;
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_VINCULO_TIPO VARCHAR(30) NULL; -- snapshot
```

### 1.3 Migration a criar
**Arquivo:** `database/migrations/2026_03_16_000010_create_motor_folha_tables.php`

Criar as tabelas ADICIONAL_SERVIDOR e LANCAMENTO_FOLHA com `if (!Schema::hasTable(...))`.
Alterar RUBRICA, ITEM_FOLHA, VINCULO, DETALHE_FOLHA com `if (!Schema::hasColumn(...))`.
Não usar `Schema::drop` — apenas addColumn seguro.


---

## PARTE 2 — CATÁLOGO DE RUBRICAS (seed)

**Arquivo:** `database/seeders/RubricasCatalogoSeeder.php`

Inserir apenas se `RUBRICA` estiver vazia. Usar `updateOrInsert` pelo código.

| CODIGO | DESCRICAO | TIPO | CAMADA | CALCULO | PREV | IRRF | FGTS | SAGRES |
|--------|-----------|------|--------|---------|------|------|------|--------|
| 001 | Vencimento Base | P | 1 | tabela_salarial | 1 | 1 | 0 | 01001 |
| 002 | Anuênio / Adicional por Tempo de Serviço | P | 1 | percentual_base | 1 | 1 | 0 | 01002 |
| 010 | Gratificação de Função (FC) | P | 2 | fixo | 1 | 1 | 0 | 01010 |
| 011 | Vantagem Pessoal Incorporada (Art.17 ADCT) | P | 2 | fixo | 1 | 1 | 0 | 01011 |
| 020 | Adicional de Insalubridade 20% | P | 2 | percentual_sm | 0 | 1 | 0 | 01020 |
| 021 | Adicional de Insalubridade 40% | P | 2 | percentual_sm | 0 | 1 | 0 | 01021 |
| 022 | Adicional de Periculosidade 30% | P | 2 | percentual_base | 0 | 1 | 0 | 01022 |
| 023 | Adicional Noturno | P | 2 | percentual_base | 1 | 1 | 0 | 01023 |
| 024 | Adicional de Urgência e Emergência | P | 2 | fixo | 1 | 1 | 0 | 01024 |
| 025 | Adicional de Saúde | P | 2 | fixo | 1 | 1 | 0 | 01025 |
| 026 | Adicional de Informática | P | 2 | fixo | 1 | 1 | 0 | 01026 |
| 030 | Hora Extra 50% | P | 3 | percentual_base | 1 | 1 | 0 | 01030 |
| 031 | Hora Extra 100% | P | 3 | percentual_base | 1 | 1 | 0 | 01031 |
| 032 | Plantão Extra | P | 3 | fixo | 1 | 1 | 0 | 01032 |
| 033 | Substituição (FC temporária) | P | 3 | fixo | 1 | 1 | 0 | 01033 |
| 040 | Diária | P | 3 | fixo | 0 | 0 | 0 | 01040 |
| 041 | Ajuda de Custo | P | 3 | fixo | 0 | 0 | 0 | 01041 |
| 042 | Vale Transporte | P | 3 | fixo | 0 | 0 | 0 | 01042 |
| 900 | Desconto RPPS (IPAM) | D | 1 | inss_rpps | 0 | 0 | 0 | 09001 |
| 901 | Desconto RGPS (INSS) | D | 1 | inss_rgps | 0 | 0 | 0 | 09002 |
| 902 | Desconto IRRF | D | 1 | irrf | 0 | 0 | 0 | 09003 |
| 903 | Desconto Consignação (Empréstimo) | D | 3 | fixo | 0 | 0 | 0 | 09004 |
| 904 | Desconto Consignação (Cartão) | D | 3 | fixo | 0 | 0 | 0 | 09005 |
| 905 | Pensão Alimentícia Judicial | D | 3 | percentual_base | 0 | 0 | 0 | 09006 |
| 906 | Desconto Faltas | D | 3 | fixo | 0 | 0 | 0 | 09007 |
| 907 | FGTS (recolhimento — não desconta do servidor) | D | 1 | percentual_base | 0 | 0 | 1 | 09008 |

**Regra SAGRES:** código de-para para TCE-MA. Consultar `SAGRES_EVENTO_DEPARA` existente.
**Ordem de exibição no holerite:** rubricas P ordenadas por RUBRICA_ORDEM ASC, depois D.


---

## PARTE 3 — SEED DE VÍNCULOS PMSLz

**Arquivo:** `database/seeders/VinculosPMSLzSeeder.php`

Inserir vínculos reais da Prefeitura Municipal de São Luís com flags corretas.
Usar `updateOrInsert(['VINCULO_NOME' => ...], [...])`.

| NOME | SIGLA | TIPO | REGIME | FGTS | INSS | IRRF | ANUENIO_PCT |
|------|-------|------|--------|------|------|------|-------------|
| Estatutário Efetivo | EFT | efetivo | RPPS | 0 | 1 | 1 | 1.00 |
| Serviço Prestado (Art.19 ADCT) | SP | servico_prestado | RGPS | 1 | 1 | 1 | 0.00 |
| Cargo em Comissão — Puro | CC | comissao_puro | RGPS | 0 | 1 | 1 | 0.00 |
| Efetivo em CC — Modalidade M1 | CC-M1 | efetivo_cc_m1 | RPPS | 0 | 1 | 1 | 0.00 |
| Efetivo em CC — Modalidade M2 | CC-M2 | efetivo_cc_m2 | RPPS | 0 | 1 | 1 | 1.00 |
| Função de Confiança / FG | FC | funcao_confianca | RPPS | 0 | 1 | 1 | 1.00 |
| PSS / Temporário | PSS | pss | RGPS | 1 | 1 | 1 | 0.00 |
| Guarda Municipal Efetivo | GM | efetivo | RPPS | 0 | 1 | 1 | 1.00 |
| Professor Municipal Efetivo | PROF | efetivo | RPPS | 0 | 1 | 1 | 1.00 |
| Empregado Público (CLT) | CLT | pss | RGPS | 1 | 1 | 1 | 0.00 |

---

## PARTE 4 — SEED DO ORGANOGRAMA PMSLz

**Arquivo:** `database/seeders/OrganogramaPMSLzSeeder.php`

Inserir secretarias reais + setores principais. Usar `updateOrInsert(['UNIDADE_NOME' => ...])`.
Estrutura: UNIDADE (secretaria) → SETOR (superintendência/coordenação).

### Secretarias (UNIDADE)

| SIGLA | NOME | ATIVO |
|-------|------|-------|
| GABPREF | Gabinete do Prefeito | 1 |
| SEMAD | Secretaria Municipal de Administração | 1 |
| SEMFAZ | Secretaria Municipal de Fazenda | 1 |
| SEMED | Secretaria Municipal de Educação | 1 |
| SEMUS | Secretaria Municipal de Saúde | 1 |
| SEMCAS | Secretaria Municipal da Criança e Assistência Social | 1 |
| SEMUSC | Secretaria Municipal de Segurança com Cidadania | 1 |
| SEMOSP | Secretaria Municipal de Obras e Serviços Públicos | 1 |
| SEMIT | Secretaria Municipal de Informação e Tecnologia | 1 |
| SEPLAN | Secretaria Municipal de Planejamento e Desenvolvimento | 1 |
| SMTT | Secretaria Municipal de Trânsito e Transporte | 1 |
| SEMURH | Secretaria Municipal de Urbanismo e Habitação | 1 |
| SEMAPA | Secretaria Municipal de Agricultura, Pesca e Abastecimento | 1 |
| SECULT | Secretaria Municipal de Cultura | 1 |
| SEMDEL | Secretaria Municipal de Desportos e Lazer | 1 |
| SEMMAM | Secretaria Municipal de Meio Ambiente | 1 |
| SEMGOV | Secretaria Municipal de Governo | 1 |
| SEMISPE | Secretaria Municipal de Inovação, Sustentabilidade e Projetos Especiais | 1 |
| SECOM | Secretaria Municipal de Comunicação | 1 |
| SETUR | Secretaria Municipal de Turismo | 1 |
| SEMSA | Secretaria Municipal de Segurança Alimentar | 1 |
| SEMGOP | Secretaria Municipal de Governança Solidária e Orçamento Participativo | 1 |
| SADEM | Secretaria Municipal de Articulação e Desenvolvimento Metropolitano | 1 |
| SEMAI | Secretaria Municipal de Articulação Institucional | 1 |
| SEMAP | Secretaria Municipal de Assuntos Políticos | 1 |
| SEMEPED | Secretaria Municipal Extraordinária da Pessoa com Deficiência | 1 |

### Setores por secretaria (SETOR) — inserir vinculado ao UNIDADE_ID

Inserir ao menos 3 setores por secretaria prioritária (SEMAD, SEMFAZ, SEMUS, SEMED):

**SEMAD:**
- Gabinete do Secretário
- Superintendência de Recursos Humanos
- Coordenação de Folha de Pagamento
- Coordenação de Cadastro Funcional

**SEMFAZ:**
- Gabinete do Secretário
- Superintendência de Lançamentos e Arrecadação
- Contadoria Geral do Município
- Coordenação de Orçamento e Finanças

**SEMUS:**
- Gabinete do Secretário
- Superintendência de Assistência à Rede
- Hospital Municipal Djalma Marques
- Hospital Municipal Socorrão II
- Hospital Municipal Socorrão III

**SEMED:**
- Gabinete do Secretário
- Superintendência de Recursos Humanos
- Superintendência de Ensino Fundamental
- Superintendência de Educação Infantil

**SEMIT:**
- Gabinete do Secretário
- Superintendência de Recursos Tecnológicos
- Superintendência de Sistemas
- Coordenação de Banco de Dados

**Para as demais secretarias:** inserir apenas Gabinete do Secretário + 1 setor administrativo.


---

## PARTE 5 — SEED DE FUNCIONÁRIOS DE TESTE

**Arquivo:** `database/seeders/FuncionariosPMSLzSeeder.php`

Inserir 18 funcionários fictícios cobrindo todos os tipos de vínculo e cenários de cálculo.
Usar CPFs fictícios válidos. Matriculas no formato `YYYY-NNNN`.

### Critérios de cobertura obrigatória

| # | Nome | Secretaria | Vínculo | Classe | Ref | Adicional | Observação |
|---|------|-----------|---------|--------|-----|-----------|------------|
| 1 | Ana Cristina Barros | SEMAD | Efetivo | V | C | — | Base simples para testar C1 |
| 2 | José Carlos Lima | SEMAD | Efetivo | VIII | F | Insalubridade 20% | Testa C2 insalubridade |
| 3 | Maria das Dores Silva | SEMFAZ | Efetivo | X | A | Vantagem Pessoal R$850 | Testa C2 vantagem pessoal |
| 4 | Francisco Ramos Costa | SEMUS | Efetivo | VII | D | Adicional Urgência R$1.200 | Testa C2 adicional saúde |
| 5 | Antônia Pereira Nunes | SEMUS | Efetivo | VI | B | Insalubridade 40% | Testa 40% vs 20% |
| 6 | Raimundo Sousa Farias | SEMED | Professor | III | E | — | Carreira magistério |
| 7 | Luciana Moura Castro | SEMED | Professor | V | B | — | Carreira magistério nível superior |
| 8 | Pedro Henrique Alves | SEMUSC | Guarda Municipal | GIV | C | — | Carreira própria guarda |
| 9 | Cláudia Regina Santos | SEMIT | Efetivo | IX | A | Adicional Informática R$500 | Testa Art.116 Estatuto |
| 10 | Roberto Fonseca Melo | SEMAD | Serviço Prestado | — | — | — | Testa RGPS + FGTS, sem progressão |
| 11 | Francisca Leal Pinto | SEMCAS | Serviço Prestado | — | — | Vantagem Pessoal R$320 | SP com vantagem |
| 12 | Geraldo Augusto Reis | GABPREF | CC Puro | — | — | — | Comissionado puro RGPS |
| 13 | Silvana Monteiro Cruz | SEPLAN | Efetivo CC-M2 | VI | A | — | Efetivo + 55% do CC |
| 14 | Marcos Vinícius Neto | SEMOSP | Efetivo FC | V | D | FG R$1.800 | Função de confiança |
| 15 | Ana Paula Ferreira | SEMED | PSS | — | — | — | Temporário RGPS |
| 16 | Carlos Eduardo Brito | SEMFAZ | Efetivo | XI | G | — | Topo da carreira, IRRF alto |
| 17 | Benedita Araújo Lima | SEMUS | Efetivo | IV | A | Pensão Judicial 25% | Testa C3 pensão |
| 18 | Danielle Souza Cunha | SEMAD | Efetivo | III | B | — | Em estágio probatório |

### Dados comuns a todos
- `FUNCIONARIO_DATA_INICIO`: variar entre 2005 e 2023
- `FUNCIONARIO_DATA_ULTIMA_PROGRESSAO`: calcular retroativamente (24 meses antes)
- `FUNCIONARIO_ESTAGIO_PROBATORIO`: true apenas para #18
- `FUNCIONARIO_ESTAVEL`: false para #15 (PSS), #12 (CC), true para demais
- `CARREIRA_ID`: vincular conforme tabela (efetivos → carreira geral; prof → magistério; guarda → guarda)
- `FUNCIONARIO_REGIME_PREV`: RPPS para efetivos/FC, RGPS para SP/PSS/CC
- Endereço: São Luís, MA
- Dados bancários: banco fictício para seed

### Adicionais (ADICIONAL_SERVIDOR) a inserir junto
Para cada funcionário com adicional listado acima, inserir registro em ADICIONAL_SERVIDOR:
- `ADICIONAL_VIGENCIA_INICIO`: mesma data de admissão do funcionário
- `ADICIONAL_VIGENCIA_FIM`: null (permanente)
- `ADICIONAL_ATO_ADM`: 'Seed de desenvolvimento — Sprint 3'

### Lançamentos variáveis (LANCAMENTO_FOLHA) — para a folha de teste
Para a competência de teste (primeiro mês após rodar os seeds), inserir:
- Funcionário #2: 4 horas extras 50% (RUBRICA 030)
- Funcionário #4: 1 plantão extra R$380 (RUBRICA 032)
- Funcionário #17: pensão judicial 25% (RUBRICA 905) — calcular sobre líquido estimado
- Funcionário #18: 1 falta injustificada (RUBRICA 906) — 1 dia

---

## PARTE 6 — TABELA SALARIAL (seed)

**Arquivo:** `database/seeders/TabelaSalarialPMSLzSeeder.php`

### Carreira 1 — Servidores Efetivos Gerais (Lei 4.616/2006 — reajuste 6% — Lei 7.731/2025)

```
NÍVEIS I–XI × REFERÊNCIAS A–I
Vigência: maio/2025

| Nível | A        | B        | C        | D        | E        | F        | G        | H        | I        |
|-------|----------|----------|----------|----------|----------|----------|----------|----------|----------|
| I     |  782,42  |  801,95  |  822,03  |  842,56  |  863,61  |  885,24  |  907,36  |  930,05  |  953,29  |
| II    |  863,61  |  885,24  |  907,36  |  930,05  |  953,30  |  977,15  | 1001,55  | 1026,61  | 1052,28  |
| III   |  953,30  |  977,15  | 1001,57  | 1026,61  | 1052,28  | 1078,60  | 1105,55  | 1133,16  | 1161,54  |
| IV    | 1052,29  | 1078,60  | 1105,56  | 1133,19  | 1161,55  | 1190,58  | 1220,30  | 1250,83  | 1282,09  |
| V     | 1161,55  | 1190,58  | 1220,31  | 1250,86  | 1282,09  | 1314,17  | 1347,00  | 1380,70  | 1415,22  |
| VI    | 1115,83  | 1143,74  | 1172,34  | 1201,69  | 1231,70  | 1262,50  | 1294,04  | 1326,42  | 1359,56  |
| VII   | 1428,35  | 1464,05  | 1500,68  | 1538,20  | 1576,67  | 1616,07  | 1656,48  | 1697,89  | 1740,34  |
| VIII  | 1828,45  | 1874,19  | 1921,01  | 1969,03  | 2018,25  | 2068,74  | 2120,42  | 2173,45  | 2227,79  |
| IX    | 2615,32  | 2706,84  | 2801,59  | 2899,64  | 3001,14  | 3106,16  | 3214,87  | 3327,41  | 3443,86  |
| X     | 3689,13  | 3818,31  | 3951,92  | 4090,23  | 4233,39  | 4381,56  | 4534,95  | 4693,63  | 4857,92  |
| XI    | 5203,94  | 5386,07  | 5574,55  | 5769,66  | 5971,63  | 6180,64  | 6396,96  | 6620,84  | 6852,60  |
```

**PROGRESSAO_CONFIG para esta carreira:**
- Interstício: 24 meses | Nota mínima: 7,0 | Anuênio: 1% ao ano
- Estágio probatório: 36 meses | Referência máxima: I | Classe final: XI

### Carreira 2 — Guarda Municipal (Lei 5.509/2011 — reajuste 6% — Lei 7.731/2025)

```
CARGOS × REFERÊNCIAS A–H

| Cargo            | Nível | A        | B        | C        | D        | E        | F        | G        | H        |
|-----------------|-------|----------|----------|----------|----------|----------|----------|----------|----------|
| 2ª Classe       | GI    | 1115,85  | 1127,02  | 1138,30  | 1149,70  | 1161,15  | 1172,78  | 1184,50  | 1196,38  |
| 1ª Classe       | GII   | 1208,33  | 1220,39  | 1232,59  | 1244,94  | 1257,38  | 1269,93  | 1282,65  | 1295,48  |
| Classe Distinta A | GIII | 1308,43  | 1334,60  | 1361,29  | 1388,54  | 1416,30  | 1444,63  | 1473,52  | 1502,97  |
| Classe Distinta B | GIV  | 1533,05  | 1563,73  | 1594,97  | 1626,88  | 1659,43  | 1692,60  | 1726,46  | 1760,97  |
| Subinspetor     | GV    | 1796,21  | 1832,13  | 1868,80  | 1906,17  | 1944,26  | 1983,15  | 2022,81  | 2063,28  |
| Inspetor 2ª Cl  | GVI   | 2104,56  | 2178,22  | 2254,44  | 2333,35  | 2415,03  | 2499,55  | 2587,04  | 2677,58  |
| Inspetor 1ª Cl  | GVII  | 2771,27  | 2868,30  | 2968,68  | 3072,57  | 3180,11  | 3291,41  | 3406,61  | 3525,84  |
| Inspetor Regional | GVIII| 3649,25  | 3776,98  | 3909,18  | 4046,01  | 4187,61  | 4334,17  | 4485,89  | 4642,87  |
```

**PROGRESSAO_CONFIG para Guarda Municipal:**
- Interstício: 24 meses | Estágio probatório: 36 meses | Referência máxima: H

### Carreira 3 — Magistério (Lei 4.931/2008 — reajuste 6,5% — Lei 7.727/2025)

Criar estrutura simplificada para seed de desenvolvimento.
Usar padrões PNM (Nível Médio) e PNS (Nível Superior) com 5 referências (A–E).

**Valores estimados para seed** (baseados na Lei 4.931 com reajuste 6,5%):
```
| Padrão | A        | B        | C        | D        | E        |
|--------|----------|----------|----------|----------|----------|
| PNM-I  | 1412,00  | 1448,30  | 1485,51  | 1523,65  | 1562,74  |
| PNM-II | 1562,74  | 1601,81  | 1641,85  | 1682,90  | 1724,97  |
| PNM-III| 1724,97  | 1768,09  | 1812,29  | 1857,60  | 1904,04  |
| PNS-I  | 2118,00  | 2170,95  | 2225,22  | 2280,85  | 2337,87  |
| PNS-II | 2337,87  | 2396,32  | 2456,23  | 2517,63  | 2580,57  |
| PNS-III| 2580,57  | 2645,08  | 2711,21  | 2779,00  | 2848,47  |
```

**Nota:** valores exatos do Magistério precisam ser validados com a tabela oficial da Lei 7.727/2025.
Registrar em PROGRESSAO_CONFIG com interstício de 24 meses.


---

## PARTE 7 — MOTOR DE CÁLCULO

**Arquivo:** `app/Services/MotorFolhaService.php`

### Algoritmo completo

```php
public function calcularFolha(int $folhaId): array
{
    // ── PASSO 1: Carregar TUDO em memória antes do loop ──────────────────
    $folha       = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->firstOrFail();
    $competencia = $folha->FOLHA_COMPETENCIA; // AAAAMM

    // Carregar servidores ativos com todos os joins necessários
    $servidores = DB::table('FUNCIONARIO as f')
        ->join('PESSOA as p',          'p.PESSOA_ID',    '=', 'f.PESSOA_ID')
        ->join('ATRIBUICAO_LOTACAO as al', 'al.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
        ->join('VINCULO as v',         'v.VINCULO_ID',   '=', 'al.VINCULO_ID')
        ->leftJoin('CARREIRA as c',    'c.CARREIRA_ID',  '=', 'f.CARREIRA_ID')
        ->leftJoin('TABELA_SALARIAL as ts', function($j) {
            $j->on('ts.CARREIRA_ID',       '=', 'f.CARREIRA_ID')
              ->on('ts.TABELA_CLASSE',     '=', 'f.FUNCIONARIO_CLASSE')
              ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
        })
        ->leftJoin('PROGRESSAO_CONFIG as pc', 'pc.CARREIRA_ID', '=', 'f.CARREIRA_ID')
        ->whereNull('f.FUNCIONARIO_DATA_FIM')
        ->whereNull('al.ATRIBUICAO_DATA_FIM')
        ->select([
            'f.FUNCIONARIO_ID', 'f.FUNCIONARIO_DATA_INICIO',
            'f.FUNCIONARIO_CLASSE', 'f.FUNCIONARIO_REFERENCIA',
            'f.PESSOA_DEPENDENTES_IRRF', // campo a adicionar — nº dependentes
            'v.VINCULO_TIPO', 'v.VINCULO_REGIME', 'v.VINCULO_FGTS',
            'v.VINCULO_INSS', 'v.VINCULO_IRRF', 'v.VINCULO_ANUENIO_PCT',
            'al.ATRIBUICAO_LOTACAO_VALOR', // salário base se não usar tabela
            'ts.TABELA_VENCIMENTO_BASE',
            'pc.CONFIG_ANUENIO_PCT',
        ])
        ->get()
        ->keyBy('FUNCIONARIO_ID');

    // Carregar adicionais ativos de todos os servidores
    $adicionais = DB::table('ADICIONAL_SERVIDOR as ads')
        ->join('RUBRICA as r', 'r.RUBRICA_ID', '=', 'ads.RUBRICA_ID')
        ->whereNull('ads.ADICIONAL_VIGENCIA_FIM')
        ->orWhere('ads.ADICIONAL_VIGENCIA_FIM', '>=', now())
        ->whereIn('ads.FUNCIONARIO_ID', $servidores->keys())
        ->select(['ads.*', 'r.RUBRICA_CALCULO', 'r.RUBRICA_INCIDE_FGTS'])
        ->get()
        ->groupBy('FUNCIONARIO_ID');

    // Carregar lançamentos variáveis desta competência
    $lancamentos = DB::table('LANCAMENTO_FOLHA')
        ->where('FOLHA_ID', $folhaId)
        ->whereIn('FUNCIONARIO_ID', $servidores->keys())
        ->get()
        ->groupBy('FUNCIONARIO_ID');

    // Carregar consignações ativas
    $consignacoes = DB::table('CONSIG_PARCELA as cp')
        ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
        ->where('cp.COMPETENCIA', substr($competencia, 0, 4).'-'.substr($competencia, 4, 2))
        ->where('cp.STATUS', 'PENDENTE')
        ->where('cc.STATUS', 'ATIVO')
        ->whereIn('cc.FUNCIONARIO_ID', $servidores->keys())
        ->select(['cc.FUNCIONARIO_ID', DB::raw('SUM(cp.VALOR_PARCELA) as total_consig')])
        ->groupBy('cc.FUNCIONARIO_ID')
        ->get()
        ->keyBy('FUNCIONARIO_ID');

    // Carregar configuração RPPS e salário mínimo vigente
    $rppsConfig  = DB::table('RPPS_CONFIG')->orderByDesc('VIGENCIA_INICIO')->first();
    $aliqRPPS    = ($rppsConfig->ALIQUOTA_SERVIDOR ?? 14) / 100;
    $salarioMin  = 1518.00; // 2025 — idealmente vir de CONFIGURACAO_SISTEMA

    // ── PASSO 2: Loop de cálculo — ZERO queries adicionais ───────────────
    $resultados = [];

    foreach ($servidores as $funcId => $s) {
        $vencBase = (float)($s->TABELA_VENCIMENTO_BASE ?? $s->ATRIBUICAO_LOTACAO_VALOR ?? 0);
        $anoServ  = now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO));

        // C1 — Proventos estruturais
        $anuenio    = ($s->VINCULO_ANUENIO_PCT ?? 1) / 100;
        $provC1     = $vencBase + ($vencBase * $anuenio * $anoServ);

        // C2 — Adicionais permanentes
        $provC2 = 0;
        foreach (($adicionais[$funcId] ?? []) as $ad) {
            $val = match ($ad->ADICIONAL_TIPO) {
                'fixo'          => (float) $ad->ADICIONAL_VALOR,
                'percentual'    => $vencBase * ((float)$ad->ADICIONAL_VALOR / 100),
                'percentual_sm' => $salarioMin * ((float)$ad->ADICIONAL_VALOR / 100),
                default         => 0,
            };
            $provC2 += $val;
        }

        // C3 — Lançamentos variáveis
        $provC3 = 0; $descC3 = 0;
        foreach (($lancamentos[$funcId] ?? []) as $lanc) {
            if ($lanc->LANCAMENTO_TIPO === 'P') $provC3 += (float)$lanc->LANCAMENTO_VALOR_TOTAL;
            else                                $descC3 += (float)$lanc->LANCAMENTO_VALOR_TOTAL;
        }

        $bruto = $provC1 + $provC2 + $provC3;

        // Base previdência (excluir adicionais sem incidência)
        $basePrev = $vencBase + $anuenio * $anoServ * $vencBase;
        foreach (($adicionais[$funcId] ?? []) as $ad) {
            if ($ad->ADICIONAL_INCIDE_PREV) {
                $basePrev += match ($ad->ADICIONAL_TIPO) {
                    'fixo'          => (float) $ad->ADICIONAL_VALOR,
                    'percentual'    => $vencBase * ((float)$ad->ADICIONAL_VALOR / 100),
                    'percentual_sm' => $salarioMin * ((float)$ad->ADICIONAL_VALOR / 100),
                    default         => 0,
                };
            }
        }

        // Desconto previdência
        $descPrev = 0;
        if ($s->VINCULO_INSS) {
            $descPrev = ($s->VINCULO_REGIME === 'RPPS')
                ? $basePrev * $aliqRPPS
                : self::calcularINSS_RGPS($basePrev); // tabela progressiva 2025
        }

        // Base IRRF
        $baseIrrf = $bruto - $descPrev;
        $deducDep = ($s->PESSOA_DEPENDENTES_IRRF ?? 0) * 189.59; // dedução 2025
        $baseIrrf -= $deducDep;

        $descIRRF = $s->VINCULO_IRRF ? self::calcularIRRF($baseIrrf) : 0;

        // Consignações
        $descConsig = (float)($consignacoes[$funcId]->total_consig ?? 0);

        // Descontos C3 (faltas, pensão judicial)
        $descOutros = $descC3 + $descConsig;

        $liquido = $bruto - $descPrev - $descIRRF - $descOutros;

        $resultados[$funcId] = [
            'FUNCIONARIO_ID'          => $funcId,
            'FOLHA_ID'                => $folhaId,
            'DETALHE_FOLHA_PROVENTOS' => round($bruto, 2),
            'DETALHE_BASE_PREV'       => round($basePrev, 2),
            'DETALHE_BASE_IRRF'       => round($baseIrrf, 2),
            'DETALHE_DESC_PREV'       => round($descPrev, 2),
            'DETALHE_DESC_IRRF'       => round($descIRRF, 2),
            'DETALHE_DESC_OUTROS'     => round($descOutros, 2),
            'DETALHE_FOLHA_DESCONTOS' => round($descPrev + $descIRRF + $descOutros, 2),
            'DETALHE_FOLHA_LIQUIDO'   => round($liquido, 2),
            'DETALHE_VINCULO_TIPO'    => $s->VINCULO_TIPO,
            'updated_at'              => now(),
        ];
    }

    // ── PASSO 3: Gravar resultados em batch ──────────────────────────────
    foreach (array_chunk($resultados, 500) as $chunk) {
        DB::table('DETALHE_FOLHA')->upsert(
            $chunk,
            ['FUNCIONARIO_ID', 'FOLHA_ID'],
            array_keys(reset($chunk))
        );
    }

    return [
        'ok'                => true,
        'servidores'        => count($resultados),
        'total_proventos'   => collect($resultados)->sum('DETALHE_FOLHA_PROVENTOS'),
        'total_descontos'   => collect($resultados)->sum('DETALHE_FOLHA_DESCONTOS'),
        'total_liquido'     => collect($resultados)->sum('DETALHE_FOLHA_LIQUIDO'),
    ];
}

// Tabela INSS RGPS 2025 — progressiva
private static function calcularINSS_RGPS(float $base): float
{
    $faixas = [
        [1518.00,  0.075, 0],
        [2666.68,  0.09,  0],
        [4000.03,  0.12,  0],
        [7786.02,  0.14,  0],
    ];
    // Cálculo progressivo por faixa
    $desconto = 0; $anterior = 0;
    foreach ($faixas as [$teto, $aliq]) {
        if ($base <= $anterior) break;
        $faixa     = min($base, $teto) - $anterior;
        $desconto += $faixa * $aliq;
        $anterior  = $teto;
        if ($base <= $teto) break;
    }
    return round(min($desconto, $base), 2);
}

// Tabela IRRF 2025
private static function calcularIRRF(float $base): float
{
    if ($base <= 2259.20) return 0;
    if ($base <= 2826.65) return round($base * 0.075 - 169.44, 2);
    if ($base <= 3751.05) return round($base * 0.15  - 381.44, 2);
    if ($base <= 4664.68) return round($base * 0.225 - 662.77, 2);
    return round($base * 0.275 - 896.00, 2);
}
```

### Endpoint a criar em routes/folha.php
```php
Route::post('/folhas/calcular-proventos', function (Request $request) {
    $folhaId = $request->folha_id;
    $motor   = new \App\Services\MotorFolhaService();
    $result  = $motor->calcularFolha($folhaId);
    return response()->json($result);
});
```

---

## PARTE 8 — INTERFACES DE ADMINISTRADOR

### 8.1 ConfiguracaoSistemaView.vue — nova aba "Motor de Folha"

Deve ter 3 sub-abas:

**Sub-aba: Vínculos**
- Listar todos os vínculos com campos editáveis:
  - Tipo (select: efetivo/servico_prestado/comissao_puro/efetivo_cc_m1/efetivo_cc_m2/funcao_confianca/pss)
  - Regime previdenciário (RPPS/RGPS)
  - Checkboxes: Incide FGTS / Incide INSS / Incide IRRF
  - Campo numérico: % Anuênio
- Botão Salvar por linha (PATCH /api/v3/admin/vinculos/{id})

**Sub-aba: Catálogo de Rubricas**
- Listar todas as rubricas com campos editáveis:
  - Código, Descrição, Tipo (P/D), Camada (1/2/3)
  - Tipo de cálculo (select)
  - Checkboxes incidências + código SAGRES
- Botão novo + botão inativar
- Endpoints: GET/POST/PATCH /api/v3/admin/rubricas

**Sub-aba: Parâmetros Gerais**
- Salário mínimo vigente
- Data de referência da tabela salarial
- Alíquota RPPS (leitura — editar em RPPS_CONFIG)
- Dedução por dependente IRRF
- Faixas IRRF e INSS (exibir, link para editar em VigenciaImposto)

### 8.2 PerfilFuncionarioView.vue — nova aba "Adicionais e Vantagens"

- Listar adicionais ativos do servidor (ADICIONAL_SERVIDOR)
- Formulário de novo adicional:
  - Select: Rubrica (filtrado por RUBRICA_CAMADA = 2)
  - Tipo: fixo | percentual | percentual_sm
  - Valor + Base de cálculo
  - Checkboxes: incide previdência / IRRF / FGTS
  - Vigência início + fim (null = permanente)
  - Ato administrativo (portaria/decreto)
- Botão inativar (seta ADICIONAL_VIGENCIA_FIM = hoje)
- Endpoints: GET/POST/PATCH /api/v3/servidores/{id}/adicionais

### 8.3 FolhaPagamentoView.vue — botão "Lançamentos" por servidor

- Modal/drawer por servidor na competência aberta
- Listar lançamentos existentes (LANCAMENTO_FOLHA)
- Formulário de novo lançamento:
  - Select: Rubrica (filtrado por RUBRICA_CAMADA = 3)
  - Tipo: P/D
  - Qtde + Valor unitário → total calculado no frontend
  - Origem (manual/judicial)
  - Observação
- Botão excluir lançamento (apenas se folha não fechada)
- Endpoints: GET/POST/DELETE /api/v3/folhas/{folhaId}/lancamentos

---

## PARTE 9 — ORDEM DE EXECUÇÃO NO SPRINT 3

O Antygravity deve executar nesta ordem exata:

```
1. Migration 2026_03_16_000010_create_motor_folha_tables.php
   php artisan migrate

2. RubricasCatalogoSeeder.php
   php artisan db:seed --class=RubricasCatalogoSeeder

3. VinculosPMSLzSeeder.php
   php artisan db:seed --class=VinculosPMSLzSeeder

4. OrganogramaPMSLzSeeder.php
   php artisan db:seed --class=OrganogramaPMSLzSeeder

5. TabelaSalarialPMSLzSeeder.php
   php artisan db:seed --class=TabelaSalarialPMSLzSeeder

6. FuncionariosPMSLzSeeder.php
   php artisan db:seed --class=FuncionariosPMSLzSeeder

7. MotorFolhaService.php (criar em app/Services/)

8. Endpoint POST /folhas/calcular-proventos em routes/folha.php

9. Interface admin ConfiguracaoSistemaView.vue — aba Motor de Folha

10. Interface PerfilFuncionarioView.vue — aba Adicionais e Vantagens

11. Interface FolhaPagamentoView.vue — modal Lançamentos

12. Testar: criar folha competência 202503, rodar calcular-proventos,
    verificar DETALHE_FOLHA dos 18 funcionários de teste
```

### Critérios de aceite do Sprint 3

- [ ] `php artisan migrate` sem erros
- [ ] Todos os seeders rodam sem erros
- [ ] 18 funcionários visíveis no sistema com secretarias reais
- [ ] `POST /folhas/calcular-proventos` retorna totais corretos para os 18 servidores
- [ ] Funcionário #10 (SP) usa RGPS, não RPPS
- [ ] Funcionário #16 (XI-G) tem IRRF calculado pela tabela progressiva
- [ ] Funcionário #17 tem pensão judicial descontada no líquido
- [ ] Admin consegue editar flags de vínculo sem tocar em código
- [ ] Admin consegue adicionar adicional de insalubridade no perfil do servidor
- [ ] RH consegue lançar hora extra avulsa sem criar rubrica nova

---

## NOTAS FINAIS PARA O ANTYGRAVITY

1. **Nunca hardcode** alíquotas, salário mínimo ou deduções — sempre ler de tabela/config
2. **Sempre usar** `if (!Schema::hasColumn(...))` nas alterações de tabela existente
3. **Nunca dropar** tabelas ou colunas existentes — apenas adicionar
4. **Registrar** em `gente-memory` o CARREIRA_ID gerado por cada seeder para referência cruzada
5. **Dúvida sobre regra de negócio** → PARAR e perguntar ao Tech Lead antes de presumir
6. **Campos de dependentes IRRF:** adicionar `PESSOA_DEPENDENTES_IRRF INTEGER DEFAULT 0` em PESSOA se não existir

---

*SPRINT_3_MOTOR_FOLHA.md | GENTE v3 | RR TECNOL | 16/03/2026*
*Elaborado por: Claude (Tech Lead Auditor)*
*Executor: Antygravity (Gravity)*

---

## PARTE 10 — PISO SALARIAL (SALÁRIO MÍNIMO)

### Contexto legal
Art. 7º, IV da CF/88 — salário mínimo nacionalmente unificado, vedada vinculação para qualquer fim.
Na prática: se o vencimento base + adicionais do servidor for inferior ao SM vigente,
a Prefeitura paga a **complementação** para atingir o mínimo.
Afeta principalmente: Serviço Prestado, PSS, CC Puro com vencimentos antigos.

### Campo novo na DETALHE_FOLHA
```sql
ALTER TABLE DETALHE_FOLHA ADD COLUMN DETALHE_COMPLEMENTO_SM DECIMAL(12,2) DEFAULT 0;
-- valor pago a mais para atingir o salário mínimo (zero se não precisou)
```

### Lógica no MotorFolhaService — inserir APÓS calcular proventos C1+C2+C3
```php
// Piso salarial — verificar ANTES de calcular descontos
$salarioMin = 1518.00; // 2025 — vir de CONFIGURACAO_SISTEMA

// Verifica se o vínculo tem direito ao piso (efetivos em carreira não têm — o cargo garante)
$vinculosPiso = ['servico_prestado', 'pss', 'comissao_puro'];
$complementoSM = 0;

if (in_array($s->VINCULO_TIPO, $vinculosPiso) && $bruto < $salarioMin) {
    $complementoSM = round($salarioMin - $bruto, 2);
    $bruto         = $salarioMin;
}
// Gravar DETALHE_COMPLEMENTO_SM no resultado
```

### Interface admin — botão "Controle de Piso Salarial"

**Localização:** `FolhaPagamentoView.vue` — aba lateral ou botão na competência aberta

**Funcionalidade:**
- Listar todos os servidores com `DETALHE_COMPLEMENTO_SM > 0` da competência selecionada
- Exibir: nome, matrícula, secretaria, vínculo, vencimento base, complementação, total recebido
- Totalizador: quantos servidores complementados + custo total da complementação
- Exportar CSV para controle do RH
- Alerta visual se salário mínimo configurado estiver desatualizado

**Endpoint:** `GET /api/v3/folhas/{competencia}/piso-salarial`

```php
Route::get('/folhas/{competencia}/piso-salarial', function ($comp) {
    $dados = DB::table('DETALHE_FOLHA as df')
        ->join('FOLHA as f',        'f.FOLHA_ID',       '=', 'df.FOLHA_ID')
        ->join('FUNCIONARIO as fu', 'fu.FUNCIONARIO_ID','=', 'df.FUNCIONARIO_ID')
        ->join('PESSOA as p',       'p.PESSOA_ID',      '=', 'fu.PESSOA_ID')
        ->join('LOTACAO as l', function($j) {
            $j->on('l.FUNCIONARIO_ID','=','fu.FUNCIONARIO_ID')
              ->whereNull('l.LOTACAO_DATA_FIM');
        })
        ->join('SETOR as s',    's.SETOR_ID',   '=', 'l.SETOR_ID')
        ->join('UNIDADE as u',  'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
        ->where('f.FOLHA_COMPETENCIA', $comp)
        ->where('df.DETALHE_COMPLEMENTO_SM', '>', 0)
        ->select([
            'p.PESSOA_NOME as nome',
            'fu.FUNCIONARIO_MATRICULA as matricula',
            'u.UNIDADE_NOME as secretaria',
            'df.DETALHE_VINCULO_TIPO as vinculo',
            'df.DETALHE_FOLHA_PROVENTOS as proventos',
            'df.DETALHE_COMPLEMENTO_SM as complemento',
            'df.DETALHE_FOLHA_LIQUIDO as liquido',
        ])
        ->orderBy('u.UNIDADE_NOME')
        ->get();

    return response()->json([
        'servidores'         => $dados,
        'qtd_complementados' => $dados->count(),
        'custo_total_sm'     => $dados->sum('complemento'),
    ]);
});
```


---

## PARTE 11 — FOLHA SUPLEMENTAR E FOLHA EXTRA

### Tipos de folha — enum FOLHA_TIPO

Adicionar coluna se não existir:
```sql
ALTER TABLE FOLHA ADD COLUMN FOLHA_TIPO VARCHAR(20) DEFAULT 'normal';
-- valores: normal | suplementar | decimo_terceiro | ferias | rescisao | extra
ALTER TABLE FOLHA ADD COLUMN FOLHA_REFERENCIA_ID INTEGER NULL;
-- para suplementar: ID da folha normal que originou o reajuste
ALTER TABLE FOLHA ADD COLUMN FOLHA_PORTARIA VARCHAR(100) NULL;
-- ex: "Portaria SEMAD nº 042/2026 — efeito 01/01/2026"
ALTER TABLE FOLHA ADD COLUMN FOLHA_COMPETENCIA_REFERENCIA VARCHAR(6) NULL;
-- competência original do reajuste retroativo (ex: 202601)
```

---

### 11.1 REAJUSTE RETROATIVO COM FOLHA SUPLEMENTAR

#### Fluxo completo

```
ADMIN INFORMA:
  - Portaria/Decreto: "Portaria SEMAD 042/2026"
  - Competência inicial do efeito: 2026-01
  - Competência final do efeito: 2026-02  (meses retroativos)
  - Quem é afetado: carreira X | vínculo Y | todos
  - Novo valor da tabela salarial (já atualizado na TABELA_SALARIAL)

SISTEMA FAZ:
  1. Para cada mês retroativo:
     a. Recalcula proventos com a tabela NOVA
     b. Subtrai o que foi pago (DETALHE_FOLHA_PROVENTOS original)
     c. Acumula a diferença por servidor
  2. Cria FOLHA tipo='suplementar' na competência ATUAL
  3. Grava DETALHE_FOLHA da suplementar com as diferenças
  4. Calcula IRRF sobre a diferença (tabela normal — não há 13º aqui)
  5. Desconta consignações? NÃO — suplementar não desconta consig
  6. Gera holerite suplementar separado
```

#### Tabela nova: REAJUSTE_RETROATIVO
```sql
CREATE TABLE REAJUSTE_RETROATIVO (
    REAJUSTE_ID           INTEGER PRIMARY KEY AUTOINCREMENT,
    REAJUSTE_PORTARIA     VARCHAR(200) NOT NULL,  -- identificação legal
    REAJUSTE_PCT          DECIMAL(8,4) NULL,       -- % de reajuste (se for %); null se novo valor fixo
    REAJUSTE_COMP_INICIO  VARCHAR(6) NOT NULL,     -- competência inicial efeito (AAAAMM)
    REAJUSTE_COMP_FIM     VARCHAR(6) NOT NULL,     -- competência final efeito
    REAJUSTE_FOLHA_ID     INTEGER NULL,            -- folha suplementar gerada
    REAJUSTE_FILTRO_TIPO  VARCHAR(30) NULL,        -- null=todos | vinculo | carreira | secretaria
    REAJUSTE_FILTRO_VALOR VARCHAR(100) NULL,       -- valor do filtro
    REAJUSTE_STATUS       VARCHAR(20) DEFAULT 'pendente', -- pendente|calculado|pago
    REAJUSTE_CALCULADO_POR INTEGER NULL,
    REAJUSTE_TOTAL_DIFERENCA DECIMAL(15,2) NULL,   -- total a pagar (calculado)
    REAJUSTE_QTD_SERVIDORES INTEGER NULL,
    created_at DATETIME,
    updated_at DATETIME
);
```

#### Endpoint: calcular e gerar folha suplementar
```
POST /api/v3/folhas/reajuste-retroativo

Body: {
  portaria: "Portaria SEMAD 042/2026",
  comp_inicio: "202601",
  comp_fim: "202602",
  pct_reajuste: 6.0,          // ou null se tabela já foi atualizada
  filtro_tipo: "carreira",    // null | carreira | vinculo | secretaria
  filtro_valor: "1",          // CARREIRA_ID
  destino: "suplementar"      // "suplementar" (padrão) | "proxima_folha"
}

Response: {
  reajuste_id: 1,
  servidores_afetados: 1247,
  total_diferenca: 284930.50,
  folha_suplementar_id: 99,
  preview: [ // primeiros 10 para conferência
    { nome, matricula, secretaria, diferenca_por_mes: [{comp, valor_antes, valor_novo, diferenca}] }
  ]
}
```

#### Lógica PHP em MotorFolhaService::calcularReajusteRetroativo()

O campo `destino` controla o que acontece com a diferença calculada:

| destino | comportamento |
|---------|--------------|
| `suplementar` | Cria folha tipo='suplementar' na competência atual — paga imediatamente separado |
| `proxima_folha` | Acumula a diferença em `LANCAMENTO_FOLHA` da próxima folha normal aberta — entra no holerite regular do mês |

**Quando usar `proxima_folha`:**
- Portaria saiu perto do fechamento e a folha do mês ainda não foi paga
- Valor pequeno que não justifica emissão de folha separada
- Decisão administrativa de absorver na folha seguinte

**Lógica de destino no serviço:**
```php
if ($params['destino'] === 'proxima_folha') {
    // Buscar próxima folha normal aberta (ou criar se não existir)
    $proxFolha = DB::table('FOLHA')
        ->where('FOLHA_TIPO', 'normal')
        ->where('FOLHA_SITUACAO', 'aberta')
        ->where('FOLHA_COMPETENCIA', '>', now()->format('Ym'))
        ->orderBy('FOLHA_COMPETENCIA')
        ->first();

    if (!$proxFolha) {
        // Criar folha do próximo mês automaticamente
        $proxComp  = now()->addMonth()->format('Ym');
        $proxFolhaId = DB::table('FOLHA')->insertGetId([
            'FOLHA_COMPETENCIA' => $proxComp,
            'FOLHA_TIPO'        => 'normal',
            'FOLHA_SITUACAO'    => 'aberta',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    } else {
        $proxFolhaId = $proxFolha->FOLHA_ID;
    }

    // Inserir diferenças como LANCAMENTO_FOLHA (C3) na folha destino
    $rubricaReajusteId = DB::table('RUBRICA')
        ->where('RUBRICA_CODIGO', '035') // rubrica específica: Reajuste Retroativo
        ->value('RUBRICA_ID');

    $lotes = [];
    foreach ($diferencas as $funcId => $diferenca) {
        if ($diferenca <= 0) continue;
        $lotes[] = [
            'FUNCIONARIO_ID'       => $funcId,
            'FOLHA_ID'             => $proxFolhaId,
            'RUBRICA_ID'           => $rubricaReajusteId,
            'LANCAMENTO_TIPO'      => 'P',
            'LANCAMENTO_QTDE'      => 1,
            'LANCAMENTO_VALOR_UNIT'  => $diferenca,
            'LANCAMENTO_VALOR_TOTAL' => $diferenca,
            'LANCAMENTO_INCIDE_PREV' => true,
            'LANCAMENTO_INCIDE_IRRF' => true,
            'LANCAMENTO_ORIGEM'    => 'reajuste',
            'LANCAMENTO_OBS'       => $params['portaria'],
            'created_at'           => now(),
            'updated_at'           => now(),
        ];
    }
    foreach (array_chunk($lotes, 500) as $chunk) {
        DB::table('LANCAMENTO_FOLHA')->insert($chunk);
    }

    return [
        'destino'             => 'proxima_folha',
        'folha_destino_id'    => $proxFolhaId,
        'folha_destino_comp'  => $proxFolha->FOLHA_COMPETENCIA ?? $proxComp,
        'servidores_afetados' => count($diferencas),
        'total_diferenca'     => array_sum($diferencas),
        'observacao'          => 'Diferenças lançadas como C3 na próxima folha. Serão incluídas no holerite regular.',
    ];
}
// else: fluxo suplementar existente (já especificado acima)
```

**Adicionar rubrica 035 ao catálogo (Parte 2):**

| CODIGO | DESCRICAO | TIPO | CAMADA | CALCULO | PREV | IRRF | FGTS | SAGRES |
|--------|-----------|------|--------|---------|------|------|------|--------|
| 035 | Reajuste Retroativo / Diferença Salarial | P | 3 | fixo | 1 | 1 | 0 | 01035 |
```php
public function calcularReajusteRetroativo(array $params): array
{
    $compInicio = $params['comp_inicio']; // AAAAMM
    $compFim    = $params['comp_fim'];
    $pct        = ($params['pct_reajuste'] ?? 0) / 100;

    // 1. Buscar todas as folhas normais no período
    $folhasRetro = DB::table('FOLHA')
        ->where('FOLHA_TIPO', 'normal')
        ->whereBetween('FOLHA_COMPETENCIA', [$compInicio, $compFim])
        ->pluck('FOLHA_ID');

    // 2. Buscar o que foi pago em cada competência por servidor
    $pagamentos = DB::table('DETALHE_FOLHA')
        ->whereIn('FOLHA_ID', $folhasRetro)
        ->select('FUNCIONARIO_ID', 'FOLHA_ID',
                 'DETALHE_FOLHA_PROVENTOS as pago',
                 'DETALHE_VINCULO_TIPO as vinculo_tipo')
        ->get();

    // 3. Aplicar filtro (carreira/vínculo/secretaria) se informado
    // [filtro aplicado aqui sobre $pagamentos]

    // 4. Calcular diferença por servidor (soma de todos os meses)
    $diferencas = [];
    foreach ($pagamentos as $pag) {
        $novoProvento  = $pag->pago * (1 + $pct); // se reajuste %
        // se tabela atualizada: buscar TABELA_SALARIAL para o período
        $diferenca     = round($novoProvento - $pag->pago, 2);
        $funcId        = $pag->FUNCIONARIO_ID;

        $diferencas[$funcId] = ($diferencas[$funcId] ?? 0) + $diferenca;
    }

    // 5. Criar folha suplementar na competência atual
    $folhaSupId = DB::table('FOLHA')->insertGetId([
        'FOLHA_COMPETENCIA'          => now()->format('Ym'),
        'FOLHA_TIPO'                 => 'suplementar',
        'FOLHA_SITUACAO'             => 'aberta',
        'FOLHA_PORTARIA'             => $params['portaria'],
        'FOLHA_COMPETENCIA_REFERENCIA' => $compInicio . '-' . $compFim,
        'created_at'                 => now(),
        'updated_at'                 => now(),
    ]);

    // 6. Gravar DETALHE_FOLHA da suplementar (sem descontar consignações)
    $lote = [];
    foreach ($diferencas as $funcId => $diferenca) {
        if ($diferenca <= 0) continue;
        $irrf    = self::calcularIRRF($diferenca); // IRRF sobre a diferença
        $liquido = $diferenca - $irrf;
        $lote[]  = [
            'FUNCIONARIO_ID'          => $funcId,
            'FOLHA_ID'                => $folhaSupId,
            'DETALHE_FOLHA_PROVENTOS' => $diferenca,
            'DETALHE_DESC_IRRF'       => $irrf,
            'DETALHE_FOLHA_DESCONTOS' => $irrf,
            'DETALHE_FOLHA_LIQUIDO'   => $liquido,
            'updated_at'              => now(),
        ];
    }

    foreach (array_chunk($lote, 500) as $chunk) {
        DB::table('DETALHE_FOLHA')->insert($chunk);
    }

    return [
        'folha_suplementar_id' => $folhaSupId,
        'servidores_afetados'  => count($diferencas),
        'total_diferenca'      => array_sum($diferencas),
    ];
}
```

---

### 11.2 FOLHA EXTRA (13º, Férias, Rescisão)

Cada tipo tem regras próprias de cálculo e IRRF. Especificação mínima para o Sprint 3:

#### Tabela FOLHA_EXTRA_CONFIG (para tipos especiais)
```sql
CREATE TABLE FOLHA_EXTRA_TIPO (
    TIPO_ID       INTEGER PRIMARY KEY AUTOINCREMENT,
    TIPO_CODIGO   VARCHAR(20) UNIQUE, -- decimo_terceiro | ferias | rescisao
    TIPO_NOME     VARCHAR(100),
    TIPO_BASE_CALC VARCHAR(30), -- media_12_meses | vencimento_atual | rescisao
    TIPO_DESCONTA_IRRF     BOOLEAN DEFAULT 1,
    TIPO_DESCONTA_PREV     BOOLEAN DEFAULT 1,
    TIPO_DESCONTA_CONSIG   BOOLEAN DEFAULT 0,
    TIPO_ATIVO    BOOLEAN DEFAULT 1
);
```

#### Seed dos tipos especiais
```
decimo_terceiro → base: vencimento_atual | IRRF: sim (tabela exclusiva 13º) | Prev: sim | Consig: não
ferias          → base: vencimento_atual + 1/3 | IRRF: sim | Prev: não | Consig: não
rescisao        → base: cálculo específico | IRRF: sim | Prev: sim | Consig: não
```

#### Interface admin — botão "Nova Folha Extra"
Em `FolhaPagamentoView.vue`:
- Dropdown tipo: 13º Salário | Férias | Folha Suplementar | Rescisão
- Para suplementar: abre modal com campos:
  ```
  ┌─────────────────────────────────────────────────────────────┐
  │  📋 REAJUSTE RETROATIVO                                      │
  │  Portaria/Decreto: [___________________________]             │
  │  Competência inicial: [____]  Final: [____]                 │
  │  % de reajuste: [____]  ou  ☐ Tabela já atualizada         │
  │  Abrangência: ◉ Todos  ○ Por carreira  ○ Por vínculo        │
  │                                                              │
  │  Destino do pagamento:                                       │
  │  ◉ Folha Suplementar (emitir separado agora)                │
  │  ○ Próxima Folha Vigente (incluir no holerite regular)      │
  │                                                              │
  │  ℹ️  "Próxima folha" lança as diferenças como               │
  │     lançamentos variáveis (C3) na folha aberta              │
  │     mais próxima — o servidor recebe tudo no                │
  │     mesmo holerite do mês.                                   │
  │                                                              │
  │  [Pré-visualizar]  [Confirmar e Gerar]                      │
  └─────────────────────────────────────────────────────────────┘
  ```
- Para 13º/Férias: seleciona competência + quem recebe (todos | secretaria | servidor)
- Botão "Pré-visualizar" → chama endpoint de preview antes de confirmar
- Botão "Gerar Folha" → cria folha e detalhes

#### Endpoints
```
POST /api/v3/folhas/nova-extra
  body: { tipo, competencia, filtro?, portaria? }
  → retorna folha_id + totais

POST /api/v3/folhas/reajuste-retroativo
  → já especificado acima

GET  /api/v3/folhas/{folhaId}/preview-suplementar
  → retorna os primeiros 20 servidores com diferença para conferência antes de confirmar
```

---

## PARTE 12 — ATUALIZAÇÃO DO SALÁRIO MÍNIMO (CONFIGURAÇÃO)

### Campo em CONFIGURACAO_SISTEMA
```sql
-- Inserir/atualizar chave no seed de configuração
INSERT OR REPLACE INTO CONFIGURACAO_SISTEMA (CONFIG_CHAVE, CONFIG_VALOR, CONFIG_DESCRICAO)
VALUES ('salario_minimo_vigente', '1518.00', 'Salário mínimo nacional vigente — atualizar a cada reajuste');
```

### Interface admin — painel de configuração do motor

Em `ConfiguracaoSistemaView.vue`, sub-aba "Parâmetros Gerais", adicionar campo destacado:

```
┌─────────────────────────────────────────────────────────┐
│  💰 SALÁRIO MÍNIMO VIGENTE                               │
│  R$ [  1.518,00  ]   Vigência: desde 01/01/2025         │
│  [Atualizar]  ← abre modal com novo valor + data vigor  │
│                                                          │
│  ⚠️  23 servidores recebem complementação de piso       │
│  [Ver relatório de piso salarial →]                     │
└─────────────────────────────────────────────────────────┘
```

Ao atualizar o SM:
1. Gravar novo valor em CONFIGURACAO_SISTEMA
2. Registrar histórico: `CONFIGURACAO_HISTORICO (chave, valor_anterior, valor_novo, vigencia, usuario_id)`
3. Exibir alerta: "Na próxima folha calculada, servidores abaixo do novo mínimo serão complementados automaticamente"
4. NÃO recalcular folhas passadas automaticamente — apenas folhas futuras

### Tabela CONFIGURACAO_HISTORICO (nova — para auditoria)
```sql
CREATE TABLE CONFIGURACAO_HISTORICO (
    HIST_ID          INTEGER PRIMARY KEY AUTOINCREMENT,
    CONFIG_CHAVE     VARCHAR(100),
    VALOR_ANTERIOR   TEXT,
    VALOR_NOVO       TEXT,
    VIGENCIA_INICIO  DATE,
    USUARIO_ID       INTEGER,
    created_at       DATETIME
);
```

---

## PARTE 13 — ATUALIZAÇÃO DO DOCUMENTO (sprint 3 — itens adicionais)

### Adicionar à Ordem de Execução (Parte 9) — após item 8:

```
8b. Migration adicional: folha_tipo, reajuste_retroativo, configuracao_historico, folha_extra_tipo
    php artisan migrate

8c. Seed FOLHA_EXTRA_TIPO com os 3 tipos padrão

8d. MotorFolhaService::calcularReajusteRetroativo() — implementar

8e. Endpoint POST /folhas/reajuste-retroativo

8f. Endpoint POST /folhas/nova-extra

8g. Interface FolhaPagamentoView.vue — botão "Nova Folha Extra" + modal reajuste retroativo

8h. Interface ConfiguracaoSistemaView.vue — campo SM vigente + histórico
```

### Adicionar aos Critérios de Aceite (Parte 9):

```
- [ ] Salário mínimo configurável pelo admin sem tocar em código
- [ ] Funcionários SP/PSS com vencimento < SM recebem complementação automática
- [ ] Relatório de piso salarial mostra quantos servidores foram complementados e custo total
- [ ] Admin consegue gerar folha suplementar informando portaria + período + % reajuste
- [ ] Preview da suplementar mostra primeiros servidores antes de confirmar
- [ ] Opção "jogar na próxima folha" disponível no modal de reajuste retroativo
- [ ] Ao escolher "próxima folha", diferenças aparecem como lançamentos C3 no holerite regular
- [ ] Ao escolher "suplementar", gera folha separada com holerite próprio
- [ ] IRRF da suplementar calculado sobre a diferença (não sobre o total do salário)
- [ ] Consignações NÃO são descontadas na folha suplementar
```

---

*Adicionado em 16/03/2026 — Partes 10, 11, 12, 13*
*Requisitos: piso salarial, reajuste retroativo, folha suplementar, folha extra, controle SM*

---

## PARTE 14 — BUG-S2-05 e BUG-S2-06: Proporcionalidade de dias

**Arquivo afetado:** `app/Services/FolhaParserService.php`
**Classificação:** 🔴 Crítico — passivo trabalhista real para todos os servidores
**Descoberto em:** auditoria 16/03/2026

### O bug

O motor atual usa divisor **30 fixo** em todas as situações:

```php
// Como está — ERRADO
$vencimentoBruto = round($salario / 30 * ($diasTrabalhados + $faltas), 2);
```

**Impacto em fevereiro (28 dias):**
Servidor com salário R$ 1.428,35 que trabalhou o mês inteiro
→ Motor paga: `1428,35 / 30 × 28 = R$ 1.333,13`
→ Deveria pagar: `R$ 1.428,35`
→ Diferença: **−R$ 95,22 por servidor** — prejuízo ao servidor, passivo da prefeitura

**Impacto em janeiro/março/maio (31 dias):**
→ Motor paga: `1428,35 / 30 × 31 = R$ 1.476,00`
→ Deveria pagar: `R$ 1.428,35`
→ Diferença: **+R$ 47,65 por servidor** — pagamento a maior

### A regra correta para servidor estatutário

O vencimento é **mensal fixo**. A proporcionalidade só se aplica em dois casos:

| Situação | Divisor correto | Quando usar |
|----------|----------------|-------------|
| Mês completo | — | Paga salário integral, sem divisão |
| Admissão/exoneração no meio do mês | dias reais do mês (28, 29, 30 ou 31) | Proporcional aos dias de exercício |
| Desconto de falta | **30 fixo** (mês comercial) | Sempre — padrão estatutário |

### A correção no FolhaParserService

Substituir o cálculo de vencimento bruto nos três métodos
(`calcularServidorEstatutario`, `calcularCargoComissao`, `calcularGenerico`):

```php
// ANTES (nas 3 funções) — ERRADO:
$vencimentoBruto = round($salario / 30 * ($diasTrabalhados + $faltas), 2);

// DEPOIS — CORRETO:
// Extrair o ano/mês da competência da folha e calcular dias reais
$competencia = $folha->FOLHA_COMPETENCIA; // AAAAMM
$ano  = substr($competencia, 0, 4);
$mes  = substr($competencia, 4, 2);
$diasReaisDoMes = \Carbon\Carbon::createFromDate($ano, $mes, 1)->daysInMonth;

// Mês completo = salário integral (sem divisão)
$diasDeExercicio = $diasTrabalhados + $faltas;
if ($diasDeExercicio >= $diasReaisDoMes) {
    $vencimentoBruto = $salario; // 100% — não proporcionaliza
} else {
    // Admissão ou exoneração no meio do mês
    $vencimentoBruto = round($salario / $diasReaisDoMes * $diasDeExercicio, 2);
}

// Desconto de falta: sempre sobre 30 — mês comercial estatutário (MANTÉM IGUAL)
$descontoFalta = round($salario / 30 * $faltas, 2);
```

### O que muda na prática

| Situação | Antes (errado) | Depois (correto) |
|----------|---------------|-----------------|
| Fevereiro (28 dias), mês cheio, sal. R$ 1.428,35 | R$ 1.333,13 | R$ 1.428,35 ✅ |
| Janeiro (31 dias), mês cheio, sal. R$ 1.428,35 | R$ 1.476,00 | R$ 1.428,35 ✅ |
| Admitido dia 15/fev (14 dias), sal. R$ 1.428,35 | R$ 666,56 | R$ 714,18 (1428,35/28×14) ✅ |
| 1 falta em março, sal. R$ 1.428,35 | R$ 1.380,74 | R$ 1.380,74 ✅ (desconto /30 mantido) |

### Como passar a competência para os métodos privados

O `FolhaParserService` precisa receber a competência dentro dos métodos privados.
A forma mais limpa: adicionar parâmetro `string $competencia` em `calcularRubricas()`
e repassar para `calcularServidorEstatutario()`, `calcularCargoComissao()` e `calcularGenerico()`.

```php
// Assinatura corrigida:
private function calcularServidorEstatutario(
    float $salario,
    int $diasTrabalhados,
    int $faltas,
    string $competencia  // ← novo parâmetro
): array { ... }
```

### Adicionar ao critério de aceite do Sprint 2

```
- [ ] BUG-S2-05: Servidor que trabalhou fevereiro completo recebe salário integral (não proporcional)
- [ ] BUG-S2-06: Servidor admitido no dia 15/fev recebe proporcional sobre 28 dias (não 30)
- [ ] Desconto de falta continua usando divisor 30 (mês comercial)
- [ ] Testar com os 18 funcionários de seed: nenhum deve receber valor diferente do salário da tabela em mês completo
```

---

*Adicionado em 16/03/2026 — Parte 14*
*BUG-S2-05 e BUG-S2-06: proporcionalidade de dias no FolhaParserService*

---

## PARTE 15 — INTEGRAÇÃO COM AFASTAMENTOS NA PROPORCIONALIDADE

**Arquivos afetados:**
- `app/Services/FolhaParserService.php`
- `app/Services/MotorFolhaService.php` (novo)

**Classificação:** 🔴 Crítico — motor atual ignora completamente AFASTAMENTO
**Descoberto em:** auditoria 16/03/2026

---

### O problema atual

O motor conta dias exclusivamente pelo ponto (escala). A tabela `AFASTAMENTO` é ignorada.
Isso provoca três erros graves:

| Situação | Comportamento atual | Comportamento correto |
|----------|--------------------|-----------------------|
| Licença médica (mês inteiro) | Paga proporcional (0 dias → R$ 0) | Paga 100% |
| Retorno de afastamento dia 20 | Conta só dias após retorno → salário baixo | Período afastado = remunerado; só desconta faltas pós-retorno |
| Licença sem vencimento | Paga proporcional pelos dias no ponto | Deve pagar R$ 0 no período |

---

### Classificação dos tipos de afastamento por impacto na folha

Adicionar campo `AFASTAMENTO_IMPACTO_FOLHA` na `TABELA_GENERICA` ou criar
enum fixo no motor. Recomendado: enum fixo por `COLUNA_ID` para evitar
dependência de configuração:

```php
// Em MotorFolhaService ou FolhaParserService
private const AFASTAMENTO_IMPACTO = [
    // COLUNA_ID => impacto
    1  => 'remunerado',     // Doença (legado)
    2  => 'remunerado',     // Acidente de Trabalho — 100% estatuto Art. 212
    3  => 'remunerado',     // Gestação (legado)
    4  => 'remunerado',     // Licença Atestado
    5  => 'remunerado',     // Licença Prêmio
    6  => 'sem_vencimento', // Licença sem Vencimento — R$ 0
    7  => 'remunerado',     // Mandato Eletivo — paga vantagens permanentes
    8  => 'remunerado',     // Licença Médica
    9  => 'remunerado',     // Gestação/Adoção/Paternidade
    10 => 'remunerado',     // Mandato Classista
    11 => 'remunerado',     // Serviço Militar Obrigatório
    12 => 'remunerado',     // Capacitação Profissional
    // tipos não mapeados → tratar como 'remunerado' por segurança
];
```

**Valores possíveis de impacto:**
- `remunerado` → servidor recebe salário integral no período, independente do ponto
- `sem_vencimento` → servidor não recebe nada no período afastado
- `proporcional` → desconta os dias afastados (suspensão disciplinar — adicionar tipo)

---

### Lógica de integração no motor

**Passo a ser inserido ANTES do cálculo de vencimento bruto:**

```php
// Carregar afastamentos ativos na competência — no batch inicial, fora do loop
$competenciaInicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
$competenciaFim    = Carbon::createFromDate($ano, $mes, 1)->endOfMonth();

$afastamentos = DB::table('AFASTAMENTO')
    ->whereIn('FUNCIONARIO_ID', $servidores->keys())
    ->where('AFASTAMENTO_DATA_INICIO', '<=', $competenciaFim)
    ->where(function($q) use ($competenciaInicio) {
        $q->whereNull('AFASTAMENTO_DATA_FIM')
          ->orWhere('AFASTAMENTO_DATA_FIM', '>=', $competenciaInicio);
    })
    ->select('FUNCIONARIO_ID', 'AFASTAMENTO_DATA_INICIO',
             'AFASTAMENTO_DATA_FIM', 'AFASTAMENTO_TIPO')
    ->get()
    ->groupBy('FUNCIONARIO_ID');

// Dentro do loop, antes de calcular vencimentoBruto:
$afastServidor = $afastamentos[$funcId] ?? collect();
$situacaoFolha = self::resolverSituacaoAfastamento(
    $afastServidor,
    $competenciaInicio,
    $competenciaFim,
    $diasReaisDoMes
);
```

---

### Método resolverSituacaoAfastamento()

```php
private static function resolverSituacaoAfastamento(
    $afastamentos,
    Carbon $compInicio,
    Carbon $compFim,
    int $diasMes
): array {
    // Sem afastamento no mês → situação normal
    if ($afastamentos->isEmpty()) {
        return ['tipo' => 'normal', 'dias_afastado' => 0, 'dias_exercicio' => $diasMes];
    }

    $diasSemVencimento = 0;
    $temAfastRemunerado = false;

    foreach ($afastamentos as $af) {
        $inicio = Carbon::parse($af->AFASTAMENTO_DATA_INICIO)->max($compInicio);
        $fim    = $af->AFASTAMENTO_DATA_FIM
            ? Carbon::parse($af->AFASTAMENTO_DATA_FIM)->min($compFim)
            : $compFim;

        $diasAfastamento = $inicio->diffInDays($fim) + 1;
        $impacto = self::AFASTAMENTO_IMPACTO[$af->AFASTAMENTO_TIPO] ?? 'remunerado';

        if ($impacto === 'sem_vencimento') {
            $diasSemVencimento += $diasAfastamento;
        } elseif ($impacto === 'remunerado') {
            $temAfastRemunerado = true;
        }
    }

    // Mês inteiro em licença remunerada → salário integral
    if ($temAfastRemunerado && $diasSemVencimento === 0) {
        return ['tipo' => 'remunerado_integral', 'dias_afastado' => 0, 'dias_exercicio' => $diasMes];
    }

    // Licença sem vencimento cobre o mês inteiro → R$ 0
    if ($diasSemVencimento >= $diasMes) {
        return ['tipo' => 'sem_vencimento', 'dias_afastado' => $diasMes, 'dias_exercicio' => 0];
    }

    // Misto: parte em licença sem vencimento, parte em exercício
    $diasExercicio = $diasMes - $diasSemVencimento;
    return ['tipo' => 'parcial', 'dias_afastado' => $diasSemVencimento, 'dias_exercicio' => $diasExercicio];
}
```

---

### Aplicação do resultado no cálculo de vencimento

```php
// Substituir o bloco de cálculo de vencimentoBruto (Parte 14) por:

switch ($situacaoFolha['tipo']) {

    case 'remunerado_integral':
        // Licença médica, gestação, etc. — mês inteiro afastado com remuneração
        $vencimentoBruto = $salario; // 100% sem questionamento
        break;

    case 'sem_vencimento':
        // Licença sem vencimento — não recebe nada
        $vencimentoBruto = 0;
        break;

    case 'parcial':
        // Parte do mês em licença sem vencimento, parte em exercício
        $diasExercicio   = $situacaoFolha['dias_exercicio'];
        $vencimentoBruto = round($salario / $diasReaisDoMes * $diasExercicio, 2);
        break;

    case 'normal':
    default:
        // Sem afastamento — lógica da Parte 14 (admissão/exoneração)
        $diasDeExercicio = $diasTrabalhados + $faltas;
        $vencimentoBruto = ($diasDeExercicio >= $diasReaisDoMes)
            ? $salario
            : round($salario / $diasReaisDoMes * $diasDeExercicio, 2);
        break;
}

// Desconto de falta: sempre sobre 30 — independente do tipo de afastamento
$descontoFalta = round($salario / 30 * $faltas, 2);
```

---

### Casos de teste para critério de aceite

```
- [ ] Servidor com licença médica o mês inteiro → recebe 100% do salário
- [ ] Servidor que retornou dia 20/fev de licença médica → recebe 100% (período afastado é remunerado)
- [ ] Servidor com licença sem vencimento o mês inteiro → recebe R$ 0
- [ ] Servidor com licença sem vencimento de 1 a 15/mar (15 dias) → recebe 50% (16 dias / 31)
- [ ] Servidor com licença médica + 2 faltas no mês → 100% salário menos 2 × (salário/30)
- [ ] Servidor sem nenhum afastamento que trabalhou o mês inteiro → recebe 100% (Parte 14)
- [ ] Afastamentos carregados em batch antes do loop — zero queries adicionais dentro do loop
```

---

### Modelo mesclado — decisão do Tech Lead (16/03/2026)

**Regra:** Base fixa no código para os 12 tipos do estatuto. Extensível pelo admin do sistema via banco para tipos novos. A PMSLz testa novos tipos no servidor de homologação sem precisar de deploy.

#### Estrutura do modelo mesclado

**1. Enum fixo no motor — 12 tipos do estatuto (imutável, zero query)**

```php
private const AFASTAMENTO_IMPACTO_BASE = [
    1  => 'remunerado',     // Doença
    2  => 'remunerado',     // Acidente de Trabalho
    3  => 'remunerado',     // Gestação (legado)
    4  => 'remunerado',     // Licença Atestado
    5  => 'remunerado',     // Licença Prêmio
    6  => 'sem_vencimento', // Licença sem Vencimento
    7  => 'remunerado',     // Mandato Eletivo
    8  => 'remunerado',     // Licença Médica
    9  => 'remunerado',     // Gestação/Adoção/Paternidade
    10 => 'remunerado',     // Mandato Classista
    11 => 'remunerado',     // Serviço Militar Obrigatório
    12 => 'remunerado',     // Capacitação Profissional
];
```

**2. Campo novo na TABELA_GENERICA — tipos adicionais criados pelo admin**

```sql
ALTER TABLE TABELA_GENERICA ADD COLUMN COLUNA_IMPACTO_FOLHA VARCHAR(20) NULL;
-- valores: remunerado | sem_vencimento | proporcional
-- null = não gerenciado pelo admin (usa enum fixo ou fallback)
```

Atualizar seed `TgTiposAfastamento`: os 12 tipos existentes recebem `COLUNA_IMPACTO_FOLHA = null`
(motor usa enum fixo para eles — banco não interfere).

**3. Carga do mapa no batch — UMA query antes do loop**

```php
// Carregar mapa dinâmico do banco (apenas tipos com COLUNA_IMPACTO_FOLHA preenchido)
$mapadinamico = DB::table('TABELA_GENERICA')
    ->where('TABELA_ID', RTG::TIPO_AFASTAMENTO)
    ->whereNotNull('COLUNA_IMPACTO_FOLHA')
    ->pluck('COLUNA_IMPACTO_FOLHA', 'COLUNA_ID')
    ->toArray();

// Mesclar: enum fixo tem prioridade sobre banco para os 12 tipos do estatuto
$mapaAfastamento = array_merge($mapaDinamico, self::AFASTAMENTO_IMPACTO_BASE);
// Resultado: tipos 1-12 sempre seguem o enum; novos tipos seguem o banco
```

**4. Uso dentro do loop — sem query adicional**

```php
// Substituir self::AFASTAMENTO_IMPACTO[$af->AFASTAMENTO_TIPO] por:
$impacto = $mapaAfastamento[$af->AFASTAMENTO_TIPO] ?? 'remunerado';
// fallback 'remunerado' garante segurança para tipo não mapeado
```

#### Interface admin — "Tipos de Afastamento" em ConfiguracaoSistemaView.vue

Nova sub-aba (ou seção em "Parâmetros Gerais"):

```
┌─────────────────────────────────────────────────────────────┐
│  📋 TIPOS DE AFASTAMENTO                                     │
│                                                              │
│  Tipos do estatuto (protegidos — não editáveis)             │
│  ✅ Licença Médica          → Remunerado                    │
│  ✅ Licença sem Vencimento  → Sem vencimento                │
│  ✅ Acidente de Trabalho    → Remunerado                    │
│  ... (12 tipos — somente leitura)                           │
│                                                              │
│  Tipos personalizados (editáveis pelo admin do sistema)     │
│  [+ Adicionar novo tipo]                                    │
│                                                              │
│  Nome: [_______________]                                    │
│  Impacto na folha: [Remunerado ▼]                          │
│                    Remunerado                               │
│                    Sem vencimento                           │
│                    Proporcional (desconta dias)             │
│  [Salvar]                                                   │
└─────────────────────────────────────────────────────────────┘
```

**Regras da interface:**
- Tipos 1–12 do estatuto: exibidos em modo somente leitura com badge "Estatuto"
- Tipos novos criados pelo admin: editáveis e removíveis
- Remoção só permitida se não houver afastamentos ativos com aquele tipo
- Endpoint: `GET/POST/DELETE /api/v3/admin/tipos-afastamento`

#### Seed atualizado — TgTiposAfastamento

Adicionar `COLUNA_IMPACTO_FOLHA => null` nos 12 tipos existentes para sinalizar
que o motor usa o enum fixo. Não preencher com o valor real — isso evita
conflito entre banco e código se alguém editar o banco acidentalmente.

#### Critérios de aceite adicionais

```
- [ ] Motor carrega mapa dinâmico em UMA query antes do loop — não dentro
- [ ] Tipos 1-12 sempre seguem o enum fixo independente do que estiver no banco
- [ ] Admin do sistema consegue adicionar tipo novo com impacto configurável
- [ ] Novo tipo criado no servidor de homologação funciona na próxima folha calculada sem deploy
- [ ] Tipo não mapeado → fallback 'remunerado' + log de aviso para o RH revisar
- [ ] Interface exibe tipos do estatuto como somente leitura com badge "Estatuto"
```

---

*Adicionado em 16/03/2026 — Parte 15*
*Integração de afastamentos (admissão, retorno, licença sem vencimento) com proporcionalidade da folha*

---

## PARTE 16 — AUDITORIA DE CÓDIGO: TRANSGRESSÕES ADICIONAIS AO MOTOR

*Varrida realizada em 16/03/2026 — arquivos: FolhaParserService, TabelasImpostoService,
ContraChequeService, ProcessarFolhaJob, FevereiroDemoSeeder, EscalaFevereiroSeeder*

---

### BUG-S2-08 🔴 — TabelasImpostoService com tabelas de 2024

**Arquivo:** `app/Services/TabelasImpostoService.php`

Tabela IRRF usa isenção de R$ 2.259,20 (2024). Em 2025 a isenção é R$ 2.824,00 (Lei 14.879/2024).
Todo servidor com salário entre R$ 2.259,20 e R$ 2.824,00 está pagando IRRF indevido.

```php
// ANTES (errado — 2024):
[2259.20, 0.00, 0.00],
[2826.65, 0.075, 169.44],

// DEPOIS (correto — 2025):
[2824.00, 0.00,    0.00   ],
[3751.05, 0.075, 142.80   ],
[4664.68, 0.15,  354.80   ],
[6101.06, 0.225, 636.13   ],
[INF,     0.275, 869.36   ],
// Fonte: MP 1.294/2024 convertida em Lei — vigência janeiro/2025
```

Salário mínimo base do INSS RGPS também deve ser atualizado de R$ 1.412,00 para R$ 1.518,00.

---

### BUG-S2-09 🔴 — ContraChequeService classifica rubricas por texto

**Arquivo:** `app/Services/ContraChequeService.php` (~linha 40)

Usa `strpos` em `EVENTO_DESCRICAO` para decidir se é provento ou desconto.
Com o novo catálogo de RUBRICA (`RUBRICA_TIPO = 'P'|'D'`), deve usar o campo direto.

**Correção:** substituir o bloco `if (strpos...)` por:
```php
if ($item->rubrica->RUBRICA_TIPO === 'D') {
    $descontos[] = $evento;
} else {
    $proventos[] = $evento;
}
```
Requer join de `ITEM_FOLHA` com `RUBRICA` na query do `ContraChequeService`.

---

### BUG-S2-10 🔴 — ContraChequeService recalcula líquido ignorando o campo real

**Arquivo:** `app/Services/ContraChequeService.php` (~linha 60)

```php
// ANTES (errado — ignora piso salarial, complementações):
'liquido' => number_format(
    $detalheFolha->DETALHE_FOLHA_PROVENTOS - $detalheFolha->DETALHE_FOLHA_DESCONTOS,
    2, ',', '.'
),

// DEPOIS (correto — usa o campo calculado pelo motor):
'liquido' => number_format($detalheFolha->DETALHE_FOLHA_LIQUIDO, 2, ',', '.'),
```

---

### BUG-S2-11 🟠 — ContraChequeService com bases de cálculo hardcoded como zero

**Arquivo:** `app/Services/ContraChequeService.php` (~linha 65)

```php
// ANTES (mock):
'base_irrf' => '0,00',
'base_fgts' => '0,00',
'fx_irrf'   => '0,00',
'base_prev' => number_format($detalheFolha->DETALHE_FOLHA_PROVENTOS, 2, ',', '.'),

// DEPOIS (usa campos reais do motor):
'base_irrf' => number_format($detalheFolha->DETALHE_BASE_IRRF ?? 0, 2, ',', '.'),
'base_prev' => number_format($detalheFolha->DETALHE_BASE_PREV ?? 0, 2, ',', '.'),
'base_fgts' => number_format($detalheFolha->DETALHE_BASE_PREV ?? 0, 2, ',', '.'),
// DETALHE_BASE_IRRF e DETALHE_BASE_PREV adicionados na migration da Parte 1
```

---

### BUG-S2-15 🔴 — ProcessarFolhaJob chama métodos estáticos que não existem no Model

**Arquivo:** `app/Jobs/ProcessarFolhaJob.php`

```php
// ANTES (quebra em produção com BadMethodCallException):
Folha::processarFolha($this->request, $this->userId);
Folha::reprocessarFolha($this->request["FOLHA_ID"], $this->userId);

// DEPOIS (delega ao service correto):
$motor = new \App\Services\MotorFolhaService();
if ($this->request["FOLHA_ID"] == null) {
    $folha = \App\Models\Folha::create([...]);
    $motor->calcularFolha($folha->FOLHA_ID);
} else {
    $motor->calcularFolha($this->request["FOLHA_ID"]);
}
```

---

## PARTE 17 — ESTADO DOS SEEDS: ALINHAMENTO COM O SPRINT 3

### Seeds a CORRIGIR (existem mas conflitam)

#### FevereiroDemoSeeder — 3 problemas críticos

**Problema 1 — INSS hardcoded 11%:**
```php
// ANTES: $inss = round($salario * 0.11, 2);
// DEPOIS: delegar ao TabelasImpostoService (após correção do BUG-S2-08)
$impostos = new \App\Services\TabelasImpostoService();
$inss = $impostos->calcularInssRpps($salario); // ou calcularInssRgps dependendo do vínculo
```

**Problema 2 — IRRF simplificado e errado:**
```php
// ANTES: $irrf = $salario > 4664.68 ? ... : 0.0;
// DEPOIS:
$baseIrrf = $salario - $inss;
$irrf = $impostos->calcularIrrf($baseIrrf, 0);
```

**Problema 3 — Salários fictícios não ligados à tabela:**
Após rodar `TabelaSalarialPMSLzSeeder`, o `FevereiroDemoSeeder` deve buscar
o salário da `TABELA_SALARIAL` via `CARREIRA_ID + FUNCIONARIO_CLASSE + FUNCIONARIO_REFERENCIA`
em vez de usar o array hardcoded `$salariosCargo`.

#### TgTiposAfastamento — falta COLUNA_IMPACTO_FOLHA

Atualizar `database/seeders/tgs/TgTiposAfastamento.php`:
```php
// Adicionar 'COLUNA_IMPACTO_FOLHA' => null em todos os 12 tipos existentes
// null = motor usa o enum fixo (não lê do banco para esses tipos)
["TABELA_ID" => 5, "COLUNA_ID" => 4, "DESCRICAO" => "Licença Atestado",
 "ATIVO" => 1, "COLUNA_IMPACTO_FOLHA" => null],
// ... repetir para todos os 12
```

#### ConfiguracaoSistemaSeeder — falta salário mínimo e parâmetros do motor

Adicionar ao array de configs:
```php
['CONFIG_CHAVE' => 'salario_minimo_vigente',
 'CONFIG_VALOR' => '1518.00',
 'CONFIG_DESCRICAO' => 'Salário mínimo nacional vigente — atualizar a cada reajuste',
 'CONFIG_TIPO' => 'DECIMAL'],

['CONFIG_CHAVE' => 'deducao_dependente_irrf',
 'CONFIG_VALOR' => '189.59',
 'CONFIG_DESCRICAO' => 'Dedução por dependente para cálculo IRRF mensal',
 'CONFIG_TIPO' => 'DECIMAL'],

['CONFIG_CHAVE' => 'rpps_aliquota_servidor',
 'CONFIG_VALOR' => '14.00',
 'CONFIG_DESCRICAO' => 'Alíquota RPPS/IPAM do servidor — % sobre base de contribuição',
 'CONFIG_TIPO' => 'DECIMAL'],

['CONFIG_CHAVE' => 'fgts_aliquota',
 'CONFIG_VALOR' => '8.00',
 'CONFIG_DESCRICAO' => 'Alíquota FGTS para vínculos RGPS — % sobre remuneração bruta',
 'CONFIG_TIPO' => 'DECIMAL'],
```

### DatabaseSeeder — ordem de execução atualizada

```php
public function run()
{
    $this->call([
        // ── Base do sistema (existentes) ──────────────────────────
        TabelaGenericaSeeder::class,       // 1. Enums + tipos afastamento atualizados
        PerfilSeeder::class,               // 2. Perfis de acesso
        ConfiguracaoSistemaSeeder::class,  // 3. Configs + SM + parâmetros motor
        MenuSeeder::class,                 // 4. Menu + permissões
        UsuarioSeeder::class,              // 5. Admin padrão

        // ── Motor de folha (Sprint 3 — novos) ────────────────────
        RubricasCatalogoSeeder::class,     // 6. Catálogo de 27 rubricas
        VinculosPMSLzSeeder::class,        // 7. 10 vínculos reais PMSLz
        OrganogramaPMSLzSeeder::class,     // 8. 26 secretarias + setores
        TabelaSalarialPMSLzSeeder::class,  // 9. 3 carreiras + tabelas reais
        FuncionariosPMSLzSeeder::class,    // 10. 18 funcionários de teste

        // ── Demo (após motor estar configurado) ──────────────────
        // FevereiroDemoSeeder::class,     // 11. Só rodar após corrigir BUG-S2-12/13/14
    ]);
}
```

### Critérios de aceite adicionais (Parte 16 e 17)

```
- [ ] BUG-S2-08: Tabela IRRF 2025 corrigida — isenção R$ 2.824,00
- [ ] BUG-S2-08: SM base INSS atualizado para R$ 1.518,00
- [ ] BUG-S2-09: ContraChequeService usa RUBRICA_TIPO em vez de strpos no nome
- [ ] BUG-S2-10: Líquido no holerite vem de DETALHE_FOLHA_LIQUIDO, não recalculado
- [ ] BUG-S2-11: Bases IRRF e PREV no holerite vêm dos campos reais do motor
- [ ] BUG-S2-15: ProcessarFolhaJob delega ao MotorFolhaService, não ao Model
- [ ] FevereiroDemoSeeder: INSS e IRRF calculados via TabelasImpostoService
- [ ] FevereiroDemoSeeder: salários buscados da TABELA_SALARIAL, não hardcoded
- [ ] TgTiposAfastamento: COLUNA_IMPACTO_FOLHA = null em todos os 12 tipos
- [ ] ConfiguracaoSistemaSeeder: SM, dedução dependente, alíquota RPPS e FGTS presentes
- [ ] DatabaseSeeder: 5 novos seeders na ordem correta
```

---

*Adicionado em 16/03/2026 — Partes 16 e 17*
*Varrida de código: 8 bugs novos identificados + alinhamento completo dos seeds*

---

## PARTE 18 — HOLERITE PDF: PROBLEMAS E ESPECIFICAÇÃO FINAL

*Auditoria dos templates realizada em 16/03/2026*
*Arquivos analisados: resources/views/pdfs/contra_cheque.blade.php e resources/views/v3/holerite-pdf.blade.php*

---

### Situação atual — dois templates, comportamentos diferentes

| | `pdfs/contra_cheque.blade.php` | `v3/holerite-pdf.blade.php` |
|--|-------------------------------|----------------------------|
| Quem usa | `ContraChequeService` (ativo) | Ninguém (existe mas não é chamado) |
| Layout | Proventos primeiro, descontos depois em linhas separadas | Colunas lado a lado na mesma linha ✅ |
| Classificação rubrica | `strpos` no nome (errado) | `$r['tipo'] === 'P'\|'D'` (correto) ✅ |
| Referência (qtde/dias) | `'00'` hardcoded | Campo dinâmico `$r['referencia']` ✅ |
| Complementação SM | Não exibe | Não exibe |
| Bases de cálculo | Mockadas como `'0,00'` | Campo `$bases` dinâmico ✅ |

**Decisão:** usar `v3/holerite-pdf.blade.php` como base. É o template correto.
O `pdfs/contra_cheque.blade.php` deve ser mantido apenas como legado — não remover.

---

### BUG-HOL-01 🔴 — ContraChequeService chama o template errado

**Arquivo:** `app/Services/ContraChequeService.php`

```php
// ANTES (template antigo com problemas):
$pdf = Pdf::loadView('pdfs.contra_cheque', compact('dadosGerais'));

// DEPOIS (template correto v3):
$pdf = Pdf::loadView('v3.holerite-pdf', [
    'servidor'       => $dadosServidor,   // array reformulado (ver abaixo)
    'rubricas'       => $rubricas,        // array de ITEM_FOLHA com RUBRICA_TIPO
    'bases'          => $bases,           // bases de cálculo reais do motor
    'total_proventos'=> $totalProventos,
    'total_descontos'=> $totalDescontos,
    'liquido'        => $liquido,         // de DETALHE_FOLHA_LIQUIDO (não recalculado)
    'competencia'    => $competenciaFormatada,
    'emitido_em'     => now()->format('d/m/Y H:i'),
]);
```

---

### BUG-HOL-02 🔴 — Nome de cada rubrica: sim, deve aparecer, mas vindo do lugar certo

O template `v3/holerite-pdf.blade.php` já exibe o nome corretamente via `$r['descricao']`.
O problema atual é que `$r['descricao']` vem de `EVENTO_DESCRICAO` (texto livre, frágil).

**Com o novo catálogo de RUBRICA, deve vir de `RUBRICA_DESCRICAO`:**

```php
// Query no ContraChequeService — substituir EventosDetalhesFolhas por ITEM_FOLHA:
$itens = DB::table('ITEM_FOLHA as if')
    ->join('RUBRICA as r', 'r.RUBRICA_ID', '=', 'if.RUBRICA_ID')
    ->where('if.DETALHE_FOLHA_ID', $detalheFolha->DETALHE_FOLHA_ID)
    ->orderBy('r.RUBRICA_ORDEM')
    ->select(
        'r.RUBRICA_CODIGO as codigo',
        'r.RUBRICA_DESCRICAO as descricao',  // ← nome correto do catálogo
        'r.RUBRICA_TIPO as tipo',            // P ou D — não mais strpos
        'if.ITEM_QTDE as referencia',        // dias, horas, quantidade
        'if.ITEM_VALOR_UNIT as valor_unit',
        'if.ITEM_VALOR_TOTAL as valor',
        'if.ITEM_CAMADA as camada',
    )
    ->get()
    ->map(fn($i) => (array) $i)
    ->toArray();
```

---

### BUG-HOL-03 🟠 — Referência (Ref.) hardcoded como '00'

**O campo `Ref.` do holerite deve exibir:**

| Rubrica | O que exibir em Ref. |
|---------|---------------------|
| Vencimento Base (C1) | Dias do mês (ex: `30`) |
| Anuênio (C1) | Anos de serviço (ex: `12 anos`) |
| Insalubridade 20% (C2) | `20%` |
| Hora Extra 50% (C3) | Quantidade de horas (ex: `8h`) |
| Falta (C3) | Quantidade de dias (ex: `2 dias`) |
| RPPS/INSS/IRRF (C1) | Alíquota efetiva (ex: `14%`) |
| Consignação (C3) | Nº da parcela (ex: `12/36`) |

**Como preencher:** o campo `ITEM_REFERENCIA` da `LANCAMENTO_FOLHA` / `ITEM_FOLHA`
já existe e deve ser populado pelo motor no momento do cálculo:

```php
// No MotorFolhaService, ao gravar ITEM_FOLHA:
// Rubrica 001 — Vencimento Base:
'ITEM_REFERENCIA' => $diasReaisDoMes . ' dias',

// Rubrica 002 — Anuênio:
'ITEM_REFERENCIA' => $anoServ . ' anos',

// Rubrica 020/021 — Insalubridade:
'ITEM_REFERENCIA' => $ad->ADICIONAL_VALOR . '%',

// Rubrica 030/031 — Hora Extra:
'ITEM_REFERENCIA' => number_format($lanc->LANCAMENTO_QTDE, 1) . 'h',

// Rubrica 900 — RPPS:
'ITEM_REFERENCIA' => '14%',

// Rubrica 901 — RGPS/INSS:
'ITEM_REFERENCIA' => $aliqEfetiva . '%',
```

---

### BUG-HOL-04 🟠 — Complementação de piso salarial não aparece no holerite

Quando `DETALHE_COMPLEMENTO_SM > 0`, o servidor precisa ver no holerite
que recebeu complementação para atingir o salário mínimo.

**Adicionar rubrica virtual no ContraChequeService:**

```php
// Após carregar os ITEM_FOLHA, verificar se há complementação:
if (($detalheFolha->DETALHE_COMPLEMENTO_SM ?? 0) > 0) {
    $rubricas[] = [
        'codigo'     => 'SM',
        'descricao'  => 'Complementação Salário Mínimo',
        'tipo'       => 'P',
        'referencia' => 'R$ 1.518,00',   // SM vigente
        'valor'      => $detalheFolha->DETALHE_COMPLEMENTO_SM,
        'camada'     => 1,
    ];
}
```

---

### Estrutura do array `$bases` para o template v3

O template v3 já tem a seção "Base de Cálculo" implementada via `$bases`.
O `ContraChequeService` deve montar esse array com os campos reais do motor:

```php
$bases = [
    ['descricao' => 'Salário Base',           'valor' => $detalheFolha->DETALHE_FOLHA_PROVENTOS],
    ['descricao' => 'Base Cálculo RPPS/INSS', 'valor' => $detalheFolha->DETALHE_BASE_PREV ?? 0],
    ['descricao' => 'Base Cálculo IRRF',      'valor' => $detalheFolha->DETALHE_BASE_IRRF ?? 0],
    ['descricao' => 'Base Cálculo FGTS',      'valor' => $detalheFolha->DETALHE_BASE_PREV ?? 0],
];

// Exibir FGTS apenas se VINCULO_TIPO tiver FGTS = true
// Exibir complementação SM se DETALHE_COMPLEMENTO_SM > 0
if (($detalheFolha->DETALHE_COMPLEMENTO_SM ?? 0) > 0) {
    $bases[] = [
        'descricao' => 'Complementação Salário Mínimo',
        'valor'     => $detalheFolha->DETALHE_COMPLEMENTO_SM,
    ];
}
```

---

### Estrutura do array `$dadosServidor` para o template v3

O template v3 usa `$servidor['campo']`. O `ContraChequeService` deve montar:

```php
$dadosServidor = [
    'nome'        => $detalheFolha->funcionario->pessoa->PESSOA_NOME,
    'matricula'   => $detalheFolha->funcionario->FUNCIONARIO_MATRICULA
                     ?? str_pad($detalheFolha->FUNCIONARIO_ID, 6, '0', STR_PAD_LEFT),
    'cpf'         => $this->maskCpf(...),
    'cargo'       => $lotacao?->atribuicaoLotacoes->last()?->atribuicao?->ATRIBUICAO_NOME ?? '—',
    'lotacao'     => ($lotacao?->setor?->SETOR_NOME ?? '—')
                     . ' / ' . ($lotacao?->setor?->unidade?->UNIDADE_NOME ?? '—'),
    'regime_prev' => $detalheFolha->DETALHE_VINCULO_TIPO === 'efetivo' ? 'RPPS (IPAM)' : 'RGPS (INSS)',
    'banco'       => $pessoaBanco?->banco?->BANCO_NOME ?? '—',
    'agencia'     => $pessoaBanco?->PESSOA_BANCO_AGENCIA ?? '—',
    'conta'       => $pessoaBanco?->PESSOA_BANCO_CONTA ?? '—',
    'admissao'    => \Carbon\Carbon::parse($detalheFolha->funcionario->FUNCIONARIO_DATA_INICIO)
                     ->format('d/m/Y'),
];
```

---

### Critérios de aceite do holerite

```
- [ ] ContraChequeService usa v3.holerite-pdf (não mais pdfs.contra_cheque)
- [ ] Nome de cada rubrica vem de RUBRICA_DESCRICAO (catálogo), não de EVENTO_DESCRICAO
- [ ] Tipo P/D vem de RUBRICA_TIPO, não de strpos no nome
- [ ] Campo Ref. exibe informação relevante: dias, horas, %, nº parcela
- [ ] Servidor que recebeu complementação SM vê rubrica "Complementação Salário Mínimo"
- [ ] Base RPPS, Base IRRF, Base FGTS exibem valores reais do motor (não zeros)
- [ ] Líquido exibido vem de DETALHE_FOLHA_LIQUIDO (não recalculado no service)
- [ ] Holerite dos 18 funcionários de teste gera sem erros
- [ ] Funcionário SP (RGPS) exibe "RGPS (INSS)" em Regime Prev.
- [ ] Funcionário #17 (pensão judicial) exibe rubrica "Pensão Alimentícia Judicial" em descontos
```

---

*Adicionado em 16/03/2026 — Parte 18*
*Especificação completa do holerite PDF: template unificado, rubricas corretas, referências, complementação SM*

---

## PARTE 19 — CARGA HORÁRIA, ACORDO ENTRE PARTES E BANCO DE HORAS INTEGRADO AO MOTOR

*Especificado em 16/03/2026*

---

### 19.1 Contexto e decisão de arquitetura

O servidor público pode ter sua jornada alterada por acordo formal com a administração.
Enquanto a carga horária contratual for cumprida (ou coberta pelo banco de horas acumulado),
o salário é pago integralmente. Só desconta se o déficit não for coberto pelo banco.

Essa lógica tem três dimensões que precisam estar conectadas:
- **eSocial:** jornada contratual é campo obrigatório no S-2200. Alteração gera S-2206.
- **Motor de folha:** divisor de hora extra, proporcionalidade e desconto dependem da carga.
- **Banco de horas:** compensação automática do déficit antes de qualquer desconto.

---

### 19.2 Banco de dados — tabelas a CRIAR

#### VINCULO_CARGA_HORARIA — padrão por tipo de vínculo
```sql
ALTER TABLE VINCULO ADD COLUMN VINCULO_HORAS_MES    DECIMAL(6,2) DEFAULT 220.00;
ALTER TABLE VINCULO ADD COLUMN VINCULO_HORAS_SEMANA DECIMAL(5,2) DEFAULT 44.00;
-- Valores padrão por tipo:
-- efetivo 40h/sem  → 173h/mês
-- efetivo 30h/sem  → 130h/mês (saúde)
-- efetivo 20h/sem  → 87h/mês  (magistério parcial)
-- comissao_puro    → 220h/mês (regime CLT-like)
-- servico_prestado → conforme contrato original
```

#### ATRIBUICAO_LOTACAO — adicionar carga horária individual
```sql
ALTER TABLE ATRIBUICAO_LOTACAO ADD COLUMN ATRIBUICAO_HORAS_MES    DECIMAL(6,2) NULL;
-- null = herda do VINCULO_HORAS_MES
-- preenchido apenas quando há acordo individual diferente do padrão do vínculo
ALTER TABLE ATRIBUICAO_LOTACAO ADD COLUMN ATRIBUICAO_HORAS_SEMANA DECIMAL(5,2) NULL;
```

#### ACORDO_CARGA_HORARIA — registro formal do acordo entre partes
```sql
CREATE TABLE ACORDO_CARGA_HORARIA (
    ACORDO_ID               INTEGER PRIMARY KEY AUTOINCREMENT,
    FUNCIONARIO_ID          INTEGER NOT NULL,

    -- Jornada anterior (para histórico)
    ACORDO_HORAS_MES_ANTES  DECIMAL(6,2) NOT NULL,
    ACORDO_HORAS_SEM_ANTES  DECIMAL(5,2) NOT NULL,

    -- Jornada nova acordada
    ACORDO_HORAS_MES_NOVO   DECIMAL(6,2) NOT NULL,
    ACORDO_HORAS_SEM_NOVO   DECIMAL(5,2) NOT NULL,

    -- Vigência
    ACORDO_DATA_INICIO      DATE NOT NULL,
    ACORDO_DATA_FIM         DATE NULL,         -- null = por tempo indeterminado

    -- Tipo e motivação
    ACORDO_TIPO             VARCHAR(30) NOT NULL,
    -- valores: reducao_temporaria | reducao_permanente | ampliacao | regime_especial

    ACORDO_MOTIVO           TEXT NULL,

    -- Rastreabilidade jurídica
    ACORDO_PORTARIA         VARCHAR(200) NULL,  -- ex: Portaria SEMAD 015/2026
    ACORDO_PROTOCOLO        VARCHAR(50) NULL,   -- número de protocolo SEI/SIGAP

    -- Status do fluxo de aprovação
    ACORDO_STATUS           VARCHAR(20) DEFAULT 'pendente',
    -- pendente | aprovado | vigente | encerrado | cancelado

    -- Partes envolvidas
    SOLICITADO_POR          INTEGER NULL,       -- USUARIO_ID do servidor (ou RH)
    APROVADO_RH_POR         INTEGER NULL,       -- USUARIO_ID do RH
    APROVADO_RH_EM          DATETIME NULL,
    APROVADO_GESTOR_POR     INTEGER NULL,       -- USUARIO_ID do gestor
    APROVADO_GESTOR_EM      DATETIME NULL,

    -- eSocial
    ESOCIAL_S2206_GERADO    BOOLEAN DEFAULT 0,  -- se já gerou o evento de alteração
    ESOCIAL_EVENTO_ID       INTEGER NULL,       -- FK para ESOCIAL_EVENTO

    created_at              DATETIME,
    updated_at              DATETIME
);
```

#### PONTO_CONFIG_FUNCIONARIO — adicionar carga horária calculada
```sql
ALTER TABLE PONTO_CONFIG_FUNCIONARIO ADD COLUMN HORAS_MES_VIGENTE  DECIMAL(6,2) NULL;
-- Calculado ao salvar: vem de ACORDO_CARGA_HORARIA vigente ou VINCULO_HORAS_MES
-- Usado pelo motor como META do mês — não recalcular no loop
ALTER TABLE PONTO_CONFIG_FUNCIONARIO ADD COLUMN ACORDO_ID_VIGENTE  INTEGER NULL;
-- FK para ACORDO_CARGA_HORARIA — qual acordo está ativo agora
```

#### BANCO_HORAS — adicionar campo de meta e vínculo com folha
```sql
ALTER TABLE BANCO_HORAS ADD COLUMN HORAS_META_MES    DECIMAL(6,2) NULL;
-- Meta do mês quando o registro foi criado — referência para auditoria
ALTER TABLE BANCO_HORAS ADD COLUMN HORAS_REALIZADAS  DECIMAL(6,2) NULL;
-- Total de horas trabalhadas no mês (do ponto)
ALTER TABLE BANCO_HORAS ADD COLUMN DEFICIT_COBERTO   DECIMAL(6,2) DEFAULT 0;
-- Horas do banco usadas para cobrir déficit no motor de folha
ALTER TABLE BANCO_HORAS ADD COLUMN FOLHA_ID          INTEGER NULL;
-- FK para FOLHA — em qual folha esse saldo foi consumido/gerado
```

---

### 19.3 Carga horária padrão por vínculo — seed de atualização

Ao rodar `VinculosPMSLzSeeder`, popular `VINCULO_HORAS_MES` e `VINCULO_HORAS_SEMANA`:

| VINCULO_TIPO | HORAS_SEMANA | HORAS_MES | Base legal |
|-------------|-------------|----------|-----------|
| efetivo (geral) | 40h | 173h | Lei 4.615/2006 Art. 19 |
| efetivo (saúde) | 30h | 130h | Lei específica SEMUS |
| efetivo (magistério) | 25h | 108h | Lei 4.931/2008 |
| servico_prestado | 40h | 173h | contrato original |
| comissao_puro | 44h | 220h | regime livre |
| pss | 40h | 173h | contrato temporário |
| funcao_confianca | 40h | 173h | mesmo do efetivo |
| guarda_municipal | 40h | 173h | Lei 5.509/2011 |

---

### 19.4 Lógica do motor — integração banco de horas + carga horária

**Adicionar ao batch inicial do `MotorFolhaService` (antes do loop):**

```php
// Carregar meta de horas e saldo do banco para todos os servidores
$metasHoras = DB::table('PONTO_CONFIG_FUNCIONARIO')
    ->whereIn('FUNCIONARIO_ID', $servidores->keys())
    ->pluck('HORAS_MES_VIGENTE', 'FUNCIONARIO_ID');

$saldosBanco = DB::table('BANCO_HORAS')
    ->whereIn('FUNCIONARIO_ID', $servidores->keys())
    ->selectRaw('FUNCIONARIO_ID,
        SUM(COALESCE(HORAS_CREDITADAS,0)) - SUM(COALESCE(HORAS_DEBITADAS,0)) as saldo')
    ->groupBy('FUNCIONARIO_ID')
    ->pluck('saldo', 'FUNCIONARIO_ID');

// Carregar horas realizadas no mês (do ponto — ApuracaoPonto)
$horasRealizadas = DB::table('APURACAO_PONTO')
    ->whereIn('FUNCIONARIO_ID', $servidores->keys())
    ->where('COMPETENCIA', substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2))
    ->pluck('TOTAL_HORAS_TRABALHADAS', 'FUNCIONARIO_ID');
```

**Dentro do loop — após calcular `$vencimentoBruto`:**

```php
$metaMes      = (float)($metasHoras[$funcId]
    ?? $s->VINCULO_HORAS_MES
    ?? 173.0);   // fallback 40h/sem

$horasFeitas  = (float)($horasRealizadas[$funcId] ?? $metaMes); // sem apuração = mês cheio
$saldoBanco   = (float)($saldosBanco[$funcId] ?? 0);

$deficit      = max(0, $metaMes - $horasFeitas);
$cobertoBanco = min($deficit, max(0, $saldoBanco));
$descoberto   = $deficit - $cobertoBanco;

// Desconto apenas sobre o que não foi coberto pelo banco
$descontoJornada = 0;
if ($descoberto > 0 && $metaMes > 0) {
    $descontoJornada = round($vencimentoBruto / $metaMes * $descoberto, 2);
    $vencimentoBruto -= $descontoJornada;
}

// Atualizar banco de horas: creditar saldo positivo ou debitar o que foi compensado
$saldoFinal = $saldoBanco - $cobertoBanco;
if ($horasFeitas > $metaMes) {
    $saldoFinal = $saldoBanco + ($horasFeitas - $metaMes); // crédito de horas extras
}

// Gravar movimento no banco de horas desta competência
$movBanco = [
    'FUNCIONARIO_ID'   => $funcId,
    'FOLHA_ID'         => $folhaId,
    'COMPETENCIA'      => substr($competencia,0,4).'-'.substr($competencia,4,2),
    'HORAS_META_MES'   => $metaMes,
    'HORAS_REALIZADAS' => $horasFeitas,
    'HORAS_CREDITADAS' => max(0, $horasFeitas - $metaMes),
    'HORAS_DEBITADAS'  => $cobertoBanco,
    'DEFICIT_COBERTO'  => $cobertoBanco,
    'TIPO'             => $cobertoBanco > 0 ? 'COMPENSACAO' : ($horasFeitas >= $metaMes ? 'CREDITO' : 'EXPIRADO'),
    'OBSERVACAO'       => "Motor folha competência {$competencia}",
    'created_at'       => now(),
    'updated_at'       => now(),
];
// Acumular para inserção em batch após o loop
$movimentosBanco[] = $movBanco;
```

**Após o loop — inserir movimentos do banco em batch:**
```php
foreach (array_chunk($movimentosBanco, 500) as $chunk) {
    DB::table('BANCO_HORAS')->insert($chunk);
}
```

---

### 19.5 Correção do ApuracaoPontoService — BUG-PONTO-01 integrado

O `ApuracaoPontoService` ignora o intervalo de almoço e usa só ENTRADA/SAIDA.
Corrigir para usar `TURNO_INTERVALO` e as 4 batidas quando regime = '4_batidas':

```php
// ANTES (errado — ignora almoço):
$entrada = $batidas->firstWhere('REGISTRO_TIPO', 'ENTRADA');
$saida   = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA');
$trabalhado = Carbon::parse($entrada->REGISTRO_DATA_HORA)
    ->diffInMinutes(Carbon::parse($saida->REGISTRO_DATA_HORA)) / 60;

// DEPOIS (correto — desconta intervalo):
$config = $configsPonto[$funcionarioId] ?? null;
$regime = $config?->REGIME ?? '4_batidas';

if ($regime === '4_batidas') {
    $entrada       = $batidas->firstWhere('REGISTRO_TIPO', 'ENTRADA');
    $saidaAlmoco   = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA_ALMOCO');
    $retornoAlmoco = $batidas->firstWhere('REGISTRO_TIPO', 'RETORNO_ALMOCO');
    $saida         = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA');

    if (!$entrada || !$saida) continue;

    // Período manhã: ENTRADA → SAIDA_ALMOCO (se existir) ou usa turno
    $manha = ($entrada && $saidaAlmoco)
        ? Carbon::parse($entrada->REGISTRO_DATA_HORA)
            ->diffInMinutes(Carbon::parse($saidaAlmoco->REGISTRO_DATA_HORA)) / 60
        : 0;

    // Período tarde: RETORNO_ALMOCO → SAIDA (se existir)
    $tarde = ($retornoAlmoco && $saida)
        ? Carbon::parse($retornoAlmoco->REGISTRO_DATA_HORA)
            ->diffInMinutes(Carbon::parse($saida->REGISTRO_DATA_HORA)) / 60
        : 0;

    // Se não tem batidas intermediárias, usa ENTRADA→SAIDA menos intervalo do turno
    if ($manha === 0 && $tarde === 0) {
        $intervaloMin = $itensEscala[$dia]?->turno?->TURNO_INTERVALO ?? 60;
        $bruto = Carbon::parse($entrada->REGISTRO_DATA_HORA)
            ->diffInMinutes(Carbon::parse($saida->REGISTRO_DATA_HORA)) / 60;
        $trabalhado = max(0, $bruto - ($intervaloMin / 60));
    } else {
        $trabalhado = $manha + $tarde;
    }
} else {
    // 2 batidas — sem almoço (guardas noturnos, médicos de plantão)
    $entrada = $batidas->firstWhere('REGISTRO_TIPO', 'ENTRADA');
    $saida   = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA');
    if (!$entrada || !$saida) continue;
    $trabalhado = Carbon::parse($entrada->REGISTRO_DATA_HORA)
        ->diffInMinutes(Carbon::parse($saida->REGISTRO_DATA_HORA)) / 60;
}
```

**Pré-carregar configs de ponto antes do loop (batch):**
```php
$configsPonto = DB::table('PONTO_CONFIG_FUNCIONARIO')
    ->whereIn('FUNCIONARIO_ID', $funcIds)
    ->get()->keyBy('FUNCIONARIO_ID');
```

---

### 19.6 Correção do divisor de hora extra — BUG-HE-01 integrado

```php
// hora_extra.php — POST /hora-extra
// ANTES (errado — 220 fixo):
$valorHora = $salario > 0 ? $salario / 220 : 0;

// DEPOIS (correto — busca carga horária real do servidor):
$cargaHoraria = DB::table('PONTO_CONFIG_FUNCIONARIO')
    ->where('FUNCIONARIO_ID', $funcId)
    ->value('HORAS_MES_VIGENTE');

if (!$cargaHoraria) {
    // Fallback: busca pelo vínculo ativo
    $cargaHoraria = DB::table('ATRIBUICAO_LOTACAO as al')
        ->join('VINCULO as v', 'v.VINCULO_ID', '=', 'al.VINCULO_ID')
        ->where('al.FUNCIONARIO_ID', $funcId)
        ->whereNull('al.ATRIBUICAO_DATA_FIM')
        ->value('v.VINCULO_HORAS_MES') ?? 173.0;
}

$valorHora = $salario > 0 ? round($salario / $cargaHoraria, 4) : 0;
```

---

### 19.7 Fluxo de aprovação do acordo — estados e transições

```
SERVIDOR SOLICITA
        ↓
  status: 'pendente'
        ↓ RH analisa
  status: 'aprovado_rh'
        ↓ Gestor da secretaria confirma
  status: 'aprovado' → gera S-2206 automaticamente
        ↓ na data de início
  status: 'vigente'  → atualiza PONTO_CONFIG_FUNCIONARIO.HORAS_MES_VIGENTE
                      → atualiza ATRIBUICAO_LOTACAO.ATRIBUICAO_HORAS_MES
        ↓ na data de fim (ou cancelamento)
  status: 'encerrado' → restaura carga horária anterior
                       → gera novo S-2206 de retorno
```

**Endpoint de criação do acordo:**
```
POST /api/v3/servidores/{id}/acordo-carga-horaria
Body: {
  horas_semana_novo: 30,
  horas_mes_novo: 130,
  data_inicio: "2026-04-01",
  data_fim: null,
  tipo: "reducao_permanente",
  motivo: "Acordo administrativo — saúde",
  portaria: "Portaria SEMAD 018/2026"
}
→ Cria ACORDO_CARGA_HORARIA com status 'pendente'
→ Notifica RH por email
```

**Endpoint de aprovação:**
```
PATCH /api/v3/acordo-carga-horaria/{id}/aprovar
Body: { etapa: "rh" | "gestor" }
→ Se etapa = gestor e já aprovado pelo RH:
  → status = 'aprovado'
  → Dispara geração do S-2206
  → Agenda atualização do PONTO_CONFIG_FUNCIONARIO na data_inicio via job
```

---

### 19.8 Integração com eSocial — S-2206 automático

Quando o acordo é aprovado, criar evento eSocial automaticamente:

```php
// Ao aprovar acordo (gestor confirma):
if ($acordo->ACORDO_STATUS === 'aprovado' && !$acordo->ESOCIAL_S2206_GERADO) {
    $eventoId = DB::table('ESOCIAL_EVENTO')->insertGetId([
        'FUNCIONARIO_ID'  => $acordo->FUNCIONARIO_ID,
        'TIPO_EVENTO'     => 'S-2206',
        'DATA_REFERENCIA' => $acordo->ACORDO_DATA_INICIO,
        'STATUS'          => 'PENDENTE',
        'XML_GERADO'      => null, // gerado pelo módulo eSocial completo
        'OBSERVACAO'      => "Alteração de jornada — Acordo {$acordo->ACORDO_ID} | {$acordo->ACORDO_PORTARIA}",
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    DB::table('ACORDO_CARGA_HORARIA')->where('ACORDO_ID', $acordo->ACORDO_ID)->update([
        'ESOCIAL_S2206_GERADO' => true,
        'ESOCIAL_EVENTO_ID'    => $eventoId,
    ]);
}
```

---

### 19.9 Interface de administrador — módulo de acordos

**Localização:** nova aba "Acordos de Jornada" em `PerfilFuncionarioView.vue`

```
┌─────────────────────────────────────────────────────────────────┐
│  ⏱️ JORNADA CONTRATUAL                                          │
│  Carga atual: 40h/semana → 173h/mês                            │
│  Base legal: Lei 4.615/2006 — Estatuto Municipal               │
│                                                                  │
│  [+ Solicitar alteração de jornada]                            │
│                                                                  │
│  Histórico de acordos                                           │
│  ─────────────────────────────────────────────────────          │
│  ✅ Portaria 018/2026 — 30h/sem (130h/mês)                     │
│     Vigência: 01/04/2026 → indeterminado                       │
│     Aprovado: RH em 18/03 | Gestor em 20/03                    │
│     eSocial S-2206: ✅ gerado                                   │
│                                                                  │
│  ⏳ Redução temporária 20h/sem — PENDENTE APROVAÇÃO RH          │
│     Solicitado em: 16/03/2026                                   │
│     [Aprovar RH]  [Rejeitar]                                    │
└─────────────────────────────────────────────────────────────────┘
```

**Endpoints necessários:**
```
GET    /api/v3/servidores/{id}/acordos-jornada
POST   /api/v3/servidores/{id}/acordo-carga-horaria
PATCH  /api/v3/acordo-carga-horaria/{id}/aprovar
PATCH  /api/v3/acordo-carga-horaria/{id}/cancelar
GET    /api/v3/acordos-jornada/pendentes   ← painel RH com todos pendentes
```

---

### 19.10 Holerite — exibição do banco de horas

Quando houver movimento de banco no mês, exibir seção informativa no holerite:

```
┌──────────────────────────────────────────────────────┐
│  BANCO DE HORAS — Competência 03/2026                │
│  Meta do mês:          173h00                        │
│  Horas realizadas:     168h30                        │
│  Déficit:               4h30                         │
│  Compensado pelo banco: 4h30  ✅ sem desconto        │
│  Saldo após compensação: 12h45                       │
└──────────────────────────────────────────────────────┘
```

Se houver desconto (banco insuficiente):
```
│  Descoberto:            2h00                         │
│  Desconto proporcional: R$ 16,50 (rubrica 906)       │
```

---

### 19.11 Critérios de aceite da Parte 19

```
- [ ] VINCULO.VINCULO_HORAS_MES populado para todos os 10 vínculos do seed
- [ ] ACORDO_CARGA_HORARIA criado com migration segura (if not exists)
- [ ] Fluxo pendente → aprovado_rh → aprovado → vigente funciona via endpoints
- [ ] Aprovação pelo gestor dispara criação do S-2206 em ESOCIAL_EVENTO
- [ ] Motor usa PONTO_CONFIG_FUNCIONARIO.HORAS_MES_VIGENTE como meta
- [ ] Deficit coberto pelo banco não gera desconto na folha
- [ ] Deficit não coberto gera desconto proporcional (rubrica 906)
- [ ] Banco de horas atualizado em batch após cálculo da folha
- [ ] Hora extra usa divisor correto da carga horária do servidor (não 220 fixo)
- [ ] ApuracaoPontoService desconta intervalo de almoço corretamente (4 batidas)
- [ ] ApuracaoPontoService usa 2 batidas para guarda noturno / médico plantão
- [ ] Holerite exibe seção "Banco de Horas" quando houver movimentação no mês
- [ ] Funcionário #2 do seed (hora extra) tem valor/hora calculado sobre 173h, não 220h
```

---

*Adicionado em 16/03/2026 — Parte 19*
*Carga horária por vínculo, acordo entre partes, banco de horas integrado ao motor,*
*correção ApuracaoPontoService (BUG-PONTO-01), correção divisor hora extra (BUG-HE-01)*
*Integração automática com eSocial S-2206 ao aprovar alteração de jornada*

---

## PARTE 20 — FUTURA IMPLEMENTAÇÃO: MODO SEM PONTO ELETRÔNICO

*Registrado em 16/03/2026 — NÃO implementar no Sprint 3. Documentar para sprint futuro.*

---

### 20.1 Contexto

Nem toda instituição que usa o GENTE v3 terá infraestrutura de ponto eletrônico
(relógios, aplicativo mobile, biometria). Para esses casos, o sistema deve operar
em modo simplificado onde o controle de frequência é feito manualmente pelo gestor.

A chave já existe: `CONFIGURACAO_SISTEMA` tem `MODULO_PONTO_ATIVO = 0`.
O que falta é o que acontece quando esse módulo está desligado.

---

### 20.2 Comportamento esperado quando MODULO_PONTO_ATIVO = 0

**O que muda no sistema:**
- Menus de ponto eletrônico ficam ocultos para todos os perfis
- Terminal de ponto e batidas são desabilitados
- `ApuracaoPontoService` não é chamado pelo motor de folha
- Motor assume **presença integral** para todos os servidores por padrão
- Única fonte de ausência: registro manual de falta pelo gestor

**O que permanece igual:**
- Módulo de escala (continua sendo usado para organização da equipe)
- Banco de horas (pode ser alimentado manualmente)
- Afastamentos (AFASTAMENTO — já integrado ao motor)
- Férias (FERIAS — já integrado ao motor)

---

### 20.3 Novo módulo: Registro Manual de Falta

Quando o ponto está desligado, o gestor registra faltas diretamente:

#### Tabela FALTA_MANUAL (criar quando implementar)
```sql
CREATE TABLE FALTA_MANUAL (
    FALTA_ID            INTEGER PRIMARY KEY AUTOINCREMENT,
    FUNCIONARIO_ID      INTEGER NOT NULL,
    FALTA_DATA          DATE NOT NULL,
    FALTA_TIPO          VARCHAR(30) NOT NULL,
    -- valores: injustificada | justificada | abono | meio_periodo_manha | meio_periodo_tarde
    FALTA_JUSTIFICATIVA TEXT NULL,
    FALTA_DOCUMENTO     VARCHAR(300) NULL,  -- caminho do comprovante anexado
    REGISTRADO_POR      INTEGER NOT NULL,   -- USUARIO_ID do gestor
    APROVADO_RH_POR     INTEGER NULL,
    APROVADO_RH_EM      DATETIME NULL,
    COMPETENCIA         VARCHAR(7) NOT NULL, -- YYYY-MM
    created_at          DATETIME,
    updated_at          DATETIME,
    UNIQUE (FUNCIONARIO_ID, FALTA_DATA)     -- um registro por dia por servidor
);
```

---

### 20.4 Integração com o motor de folha (modo sem ponto)

Quando `MODULO_PONTO_ATIVO = 0`, o motor substitui a leitura do `APURACAO_PONTO`
pela leitura da `FALTA_MANUAL`:

```php
// No MotorFolhaService — detectar modo de operação uma vez, fora do loop:
$pontoAtivo = DB::table('CONFIGURACAO_SISTEMA')
    ->where('CONFIG_CHAVE', 'MODULO_PONTO_ATIVO')
    ->value('CONFIG_VALOR') === '1';

if ($pontoAtivo) {
    // Camada existente — lê APURACAO_PONTO (Parte 19)
    $horasRealizadas = DB::table('APURACAO_PONTO')...
} else {
    // Modo simplificado — assume presença integral menos faltas registradas
    $faltasPorFuncionario = DB::table('FALTA_MANUAL')
        ->where('COMPETENCIA', $competenciaFormatada)
        ->whereIn('FUNCIONARIO_ID', $servidores->keys())
        ->get()
        ->groupBy('FUNCIONARIO_ID')
        ->map(fn($faltas) => [
            'dias_falta'        => $faltas->whereIn('FALTA_TIPO', ['injustificada','justificada'])->count(),
            'meio_periodo'      => $faltas->whereIn('FALTA_TIPO', ['meio_periodo_manha','meio_periodo_tarde'])->count() * 0.5,
            'dias_abono'        => $faltas->where('FALTA_TIPO', 'abono')->count(),
        ]);
    // No loop: $diasFalta = $faltasPorFuncionario[$funcId]['dias_falta'] ?? 0;
    // Desconto apenas de faltas injustificadas não abonadas
}
```

---

### 20.5 Interface do gestor — modo sem ponto

**Nova view ou aba em `FeriasLicencasView.vue` ou view dedicada `FrequenciaView.vue`:**

```
┌────────────────────────────────────────────────────────────┐
│  📋 FREQUÊNCIA — Março/2026          [Modo: Sem Ponto]     │
│  Equipe: Coordenação de Folha de Pagamento — SEMAD         │
│                                                             │
│  Servidor              | Situação       | Ação             │
│  ─────────────────────────────────────────────────────     │
│  Ana Cristina Barros   | ✅ Presente    | [Lançar falta]   │
│  José Carlos Lima      | ⚠️ 1 falta    | [Ver] [Editar]   │
│  Maria das Dores Silva | 🏖️ Férias     | —                │
│  Francisco Ramos Costa | 🏥 Afastado   | —                │
│                                                             │
│  [Exportar frequência do mês]                              │
└────────────────────────────────────────────────────────────┘
```

**Regras da interface:**
- Servidores em férias ou afastamento aparecem com status automático — não editáveis
- Gestor só registra faltas para servidores sem afastamento vigente
- RH pode revisar e abonar faltas registradas
- Relatório de frequência exportável para arquivo de controle interno

---

### 20.6 Configuração no admin do sistema

Adicionar ao painel de configuração (`ConfiguracaoSistemaView.vue`):

```
┌────────────────────────────────────────────────────────────┐
│  ⏱️ MÓDULO DE PONTO ELETRÔNICO                             │
│                                                             │
│  Status: [● Desligado ▼]                                   │
│          ○ Ligado — batidas eletrônicas                    │
│          ● Desligado — apenas registro manual de faltas    │
│                                                             │
│  ⚠️ Ao desligar: menus de ponto ficam ocultos.            │
│  O motor de folha assumirá presença integral e             │
│  descontará apenas faltas registradas pelo gestor.         │
│                                                             │
│  [Salvar configuração]                                     │
└────────────────────────────────────────────────────────────┘
```

---

### 20.7 Impacto nos outros módulos

| Módulo | Com ponto ligado | Com ponto desligado |
|--------|-----------------|---------------------|
| Motor de folha | Lê APURACAO_PONTO | Assume presença — lê FALTA_MANUAL |
| Banco de horas | Alimentado automaticamente | Alimentado manualmente (gestor) |
| Hora extra | Lançamento manual continua | Lançamento manual continua |
| Afastamentos | Integrado ao motor | Integrado ao motor (igual) |
| Férias | Integrado ao motor | Integrado ao motor (igual) |
| eSocial | S-2206 por jornada | S-2206 por jornada (igual) |
| Escala | Usada para ponto | Usada apenas para organização |

---

### 20.8 O que NÃO muda

- Toda a estrutura de carga horária e acordos (Parte 19) continua válida
- O banco de horas continua existindo — pode ser alimentado manualmente
- A lógica de desconto proporcional e cobertura pelo banco é a mesma
- Afastamentos e férias continuam sendo processados automaticamente pelo motor
- O módulo pode ser **ligado e desligado sem migração** — apenas configuração

---

### 20.9 Sprint sugerido para implementação

Implementar após Sprint 4 (Engine de Folha) estar validado em produção.
Depende de:
- `CONFIGURACAO_SISTEMA.MODULO_PONTO_ATIVO` já existindo (✅ já existe)
- Motor de folha com suporte à leitura condicional (APURACAO_PONTO vs FALTA_MANUAL)
- Interface do gestor de frequência (`FrequenciaView.vue` — nova view)
- Endpoints: `GET/POST/PATCH /api/v3/frequencia/faltas`

**Estimativa:** 1 sprint de ~1 semana após o motor estar estável.

---

*Adicionado em 16/03/2026 — Parte 20*
*Futura implementação: modo sem ponto eletrônico — registro manual de faltas pelo gestor*
*Não implementar no Sprint 3 — documentado para sprint futuro pós-produção*

---

## PARTE 21 — SIDEBAR DINÂMICA: MODO SEM PONTO ELETRÔNICO

*Futura implementação — junto com a Parte 20. NÃO implementar no Sprint 3.*

---

### 21.1 Contexto

O `DashboardLayout.vue` já tem toda a infraestrutura necessária:
- `ALL_NAV_ITEMS` — array com todos os itens, cada um com `roles`
- `navItemsFiltrados` — computed que filtra por perfil do usuário
- `authStore` — store de autenticação com dados do usuário

A sidebar precisa de uma **segunda dimensão de filtro**: além do perfil,
também filtrar pela configuração `MODULO_PONTO_ATIVO` do sistema.

---

### 21.2 O que muda na sidebar quando ponto está desligado

**Itens que SOMEM** (`ocultarSemPonto: true`):
- Ponto Eletrônico (`/ponto`) — Minha Área
- Faltas e Atrasos (`/faltas-atrasos`) — Frequência
- Abono de Faltas (`/abono-faltas`) — Frequência
- Escalas Hospitalares (`/escala-matriz-v3`) — Gestão
- A seção "Frequência" inteira se ficar vazia

**Itens que APARECEM** (`apenasModoPonto: false`, ou seja, sempre visíveis incluindo sem ponto):
- Banco de Horas (`/banco-horas`) — continua visível para gestão manual
- Escala de Trabalho (`/escala-trabalho`) — continua para organização

**Item NOVO que aparece apenas sem ponto** (`apenasModoSemPonto: true`):
- Controle de Frequência (`/frequencia`) — nova tela de registro manual de faltas

---

### 21.3 Alterações no DashboardLayout.vue

#### Passo 1 — Buscar configuração do sistema no store/auth

Adicionar ao `authStore` (ou criar `useConfigStore`):

```js
// store/config.js (novo arquivo pequeno)
import { defineStore } from 'pinia'
import api from '@/plugins/axios'

export const useConfigStore = defineStore('config', {
  state: () => ({
    pontoAtivo: true,    // default true até carregar
    carregado: false,
  }),
  actions: {
    async carregar() {
      if (this.carregado) return
      try {
        const { data } = await api.get('/api/v3/configuracoes/publica')
        this.pontoAtivo = data.modulo_ponto_ativo !== '0'
        this.carregado = true
      } catch {
        this.pontoAtivo = true // fallback seguro
      }
    }
  }
})
```

#### Passo 2 — Endpoint público de configuração

```php
// routes/web.php — dentro do grupo api/v3 + auth
Route::get('/configuracoes/publica', function () {
    $ponto = DB::table('CONFIGURACAO_SISTEMA')
        ->where('CONFIG_CHAVE', 'MODULO_PONTO_ATIVO')
        ->value('CONFIG_VALOR') ?? '1';
    return response()->json([
        'modulo_ponto_ativo' => $ponto,
    ]);
});
```

#### Passo 3 — Adicionar flag `ocultarSemPonto` em ALL_NAV_ITEMS

```js
// DashboardLayout.vue — modificar ALL_NAV_ITEMS:

// Itens que somem quando ponto está desligado:
{ type: 'item', to: '/ponto',
  label: 'Ponto Eletrônico', icon: 'clock',
  roles: [],
  ocultarSemPonto: true },          // ← FLAG NOVA

{ type: 'item', to: '/faltas-atrasos',
  label: 'Faltas e Atrasos', icon: 'warning',
  roles: ['admin', 'rh'],
  ocultarSemPonto: true },

{ type: 'item', to: '/abono-faltas',
  label: 'Abono de Faltas', icon: 'check',
  roles: ['admin', 'rh'],
  ocultarSemPonto: true },

{ type: 'item', to: '/escala-matriz-v3',
  label: 'Escalas Hospitalares', icon: 'calendar-week',
  roles: ['admin', 'rh', 'gestor'],
  ocultarSemPonto: true },

// Item que aparece APENAS sem ponto (futura view):
{ type: 'item', to: '/frequencia',
  label: 'Controle de Frequência', icon: 'clipboard-check',
  roles: ['admin', 'rh', 'gestor'],
  apenasModoSemPonto: true },       // ← FLAG NOVA — oculto quando ponto ativo
```

#### Passo 4 — Atualizar navItemsFiltrados

```js
// DashboardLayout.vue — importar configStore
import { useConfigStore } from '@/store/config.js'
const configStore = useConfigStore()

// No onMounted, carregar config:
onMounted(async () => {
  await authStore.fetchUser()
  await configStore.carregar()   // ← adicionar esta linha
  // ... resto do onMounted existente
})

// Atualizar navItemsFiltrados para incluir filtro de ponto:
const navItemsFiltrados = computed(() => {
  const perfil = authStore.user?.perfil ?? ''
  const pontoAtivo = configStore.pontoAtivo
  const result = []
  let lastSection = null

  for (const item of ALL_NAV_ITEMS) {
    if (item.type === 'section') {
      lastSection = item
    } else if (itemVisivel(item, perfil)) {

      // Ocultar se ponto desligado e item requer ponto
      if (!pontoAtivo && item.ocultarSemPonto) continue

      // Ocultar se ponto ativo e item é exclusivo do modo sem ponto
      if (pontoAtivo && item.apenasModoSemPonto) continue

      if (lastSection) {
        result.push(lastSection)
        lastSection = null
      }
      result.push(item)
    }
  }
  return result
})
```

#### Passo 5 — Badge visual no modo sem ponto

Quando ponto estiver desligado, exibir um indicador sutil no rodapé da sidebar:

```html
<!-- Adicionar antes do sidebar-footer no template -->
<div v-if="!configStore.pontoAtivo" class="sidebar-modo-badge">
  <span class="modo-icon">📋</span>
  <span class="modo-texto">Modo: Sem Ponto</span>
</div>
```

```css
/* Adicionar ao <style scoped> */
.sidebar-modo-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 12px 8px;
  padding: 6px 12px;
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.2);
  border-radius: 10px;
}
.modo-icon { font-size: 12px; }
.modo-texto {
  font-size: 10px;
  font-weight: 700;
  color: #fbbf24;
  letter-spacing: 0.04em;
}
```

---

### 21.4 Atualizar routeMap

Adicionar a rota nova ao `routeMap` existente:

```js
'/frequencia': { label: 'Controle de Frequência', icon: 'clipboard-check' },
```

---

### 21.5 Rota Vue no router/index.js

```js
// Adicionar junto às outras rotas protegidas:
{
  path: 'frequencia',
  component: () => import('../views/ponto/FrequenciaView.vue'),
  meta: { roles: ['admin', 'rh', 'gestor'] }
},
```

---

### 21.6 Comportamento esperado — resumo

| Situação | `/ponto` | `/faltas-atrasos` | `/frequencia` | Badge |
|----------|---------|------------------|--------------|-------|
| Ponto ativo (padrão) | ✅ visível | ✅ visível | ❌ oculto | Sem badge |
| Ponto desligado | ❌ oculto | ❌ oculto | ✅ visível | 📋 "Modo: Sem Ponto" |

A troca é **reativa**: quando o admin altera a configuração e salva,
o frontend deve recarregar o `configStore` — a sidebar muda sem reload de página.

Para garantir isso, ao salvar a configuração em `ConfiguracaoSistemaView.vue`:
```js
// Após salvar com sucesso:
configStore.carregado = false  // invalida o cache
await configStore.carregar()   // recarrega
```

---

### 21.7 Critérios de aceite (futura implementação)

```
- [ ] store/config.js criado com estado pontoAtivo
- [ ] Endpoint GET /configuracoes/publica retorna modulo_ponto_ativo
- [ ] FLAGS ocultarSemPonto e apenasModoSemPonto adicionadas em ALL_NAV_ITEMS
- [ ] navItemsFiltrados aplica os dois filtros (perfil + modo ponto)
- [ ] Badge "Modo: Sem Ponto" aparece no rodapé da sidebar quando desligado
- [ ] /frequencia aparece na sidebar apenas com ponto desligado
- [ ] /ponto, /faltas-atrasos e /abono-faltas somem com ponto desligado
- [ ] Seção "Frequência" some completamente se todos os itens forem ocultados
- [ ] Após salvar config no admin, sidebar atualiza sem reload de página
- [ ] Em mobile o comportamento é idêntico ao desktop
```

---

*Adicionado em 16/03/2026 — Parte 21*
*Sidebar dinâmica: filtro por MODULO_PONTO_ATIVO via configStore*
*Futura implementação — junto com Parte 20 (FrequenciaView.vue)*
