# GENTE v3 — Arquitetura de Folha de Pagamento
**Análise completa: módulo a módulo | 13/03/2026**

---

## VISÃO GERAL — O QUE O SISTEMA FAZ HOJE

```
FUNCIONARIO ──→ CARGO ──→ TABELA_SALARIAL ──→ VENCIMENTO BASE
     │                                               │
     │ (Progressão Funcional)                        │ + ANUÊNIO (anos × %)
     │                                               │
     ↓                                               ↓
DETALHE_FOLHA ←── importação/entrada manual ←── SALÁRIO CALCULADO
     │
     │  POST /folhas/calcular:
     │  LÍQUIDO = PROVENTOS − DESCONTOS
     │         − parcelas CONSIG_PARCELA (competência atual)
     ↓
FOLHA (total mensal por competência)
```

---

## MÓDULO 1 — CARGOS E SALÁRIOS
**Arquivo:** `routes/web.php` (rotas `/cargos-salarios/*`)
**Tabelas:** `CARGO`, `TABELA_SALARIAL`, `CARREIRA`

### O que faz:
- Define o **vencimento base** de cada cargo
- Tabela salarial por carreira (classe + referência → valor em R$)
- Ex: Carreira Efetiva → Classe A → Referência 3 → R$ 4.800,00

### O que determina na folha:
```
CARGO_SALARIO  ←── valor padrão se não houver tabela de carreira
TABELA_SALARIAL ←── valor preciso por classe/referência (progressão funcional)
```

### Limitação atual:
- ❌ Não calcula automaticamente INSS/IRRF sobre o salário
- ❌ Não gera DETALHE_FOLHA automaticamente — só armazena a tabela

---

## MÓDULO 2 — PROGRESSÃO FUNCIONAL
**Arquivo:** `routes/progressao_funcional.php`
**Tabelas:** `FUNCIONARIO`, `TABELA_SALARIAL`, `CARREIRA`, `PROGRESSAO_CONFIG`, `HISTORICO_FUNCIONAL`

### O que faz:
Calcula o **salário total do servidor** com todos os componentes:

```php
$venc_base = TABELA_SALARIAL (carreira + classe + referência)
           // ou CARGO_SALARIO se não tiver tabela de carreira

$anos_servico = Carbon::now()->diffInYears(FUNCIONARIO_DATA_INICIO)

$anuenio = $venc_base × (CONFIG_ANUENIO_PCT / 100) × $anos_servico

$salario_total = $venc_base + $anuenio
```

### O que este módulo JÁ calcula:
| Componente | Status | Como |
|---|---|---|
| Vencimento base | ✅ | TABELA_SALARIAL por classe/referência |
| Anuênio (triênio/quinquênio) | ✅ | % configurável × anos de serviço |
| Estágio probatório | ✅ | Bloqueia progressão |
| Nota de avaliação | ✅ | Critério de elegibilidade |
| Penalidade disciplinar | ✅ | Bloqueia progressão |

### O que NÃO calcula:
| Componente | Status | Motivo |
|---|---|---|
| INSS patronal/servidor | ❌ | Faixas não cadastradas |
| IRRF | ❌ | Tabela de IR não implementada |
| Insalubridade/Periculosidade | ❌ | Sem flag no CARGO ou FUNCIONARIO |
| Gratificações por função | ❌ | Sem tabela de gratificações |
| Horas extras | ❌ | Módulo separado, não alimenta folha |

---

## MÓDULO 3 — FOLHA DE PAGAMENTO
**Arquivo:** `routes/folha.php`
**Tabelas:** `FOLHA`, `DETALHE_FOLHA`

### Estrutura da tabela DETALHE_FOLHA:
```
DETALHE_FOLHA
  ├── FOLHA_ID              → qual competência
  ├── FUNCIONARIO_ID        → qual servidor
  ├── DETALHE_FOLHA_PROVENTOS  → total de proventos (R$) ← NÚMERO ÚNICO
  ├── DETALHE_FOLHA_DESCONTOS  → total de descontos  (R$) ← NÚMERO ÚNICO
  └── DETALHE_FOLHA_LIQUIDO    → proventos − descontos − consig
```

### Como o cálculo ocorre:
```
1. Dados entram na DETALHE_FOLHA (importação ou entrada manual)
   (não há cálculo automático de itemização aqui)

2. POST /folhas/calcular:
   UPDATE DETALHE_FOLHA
   SET LIQUIDO = PROVENTOS - DESCONTOS
   WHERE FOLHA_ID = ?

3. Para cada parcela CONSIG_PARCELA com STATUS=PENDENTE e competência atual:
   DESCONTOS += parcela.VALOR_PARCELA
   LIQUIDO   -= parcela.VALOR_PARCELA
   parcela.STATUS = 'DESCONTADA'
```

### Problema central:
**O sistema não sabe de onde vêm os R$ de PROVENTOS.**
Isso precisa ser importado ou calculado por outro módulo que ainda não está ligado.

---

## MÓDULO 4 — CONSIGNAÇÕES
**Arquivo:** `routes/consignacao.php`
**Tabelas:** `CONSIG_CONTRATO`, `CONSIG_PARCELA`, `CONSIG_CONVENIO`

### Como usa a folha:
```
Margem consignável = DETALHE_FOLHA_LIQUIDO (última folha do servidor)

Limite 30% → empréstimos (BANCO, SINDICATO, COOPERATIVA)
Limite 10% → cartão de crédito consignado (Decreto 57.477/2021)

No cálculo da folha (POST /folhas/calcular):
  Para cada parcela PENDENTE da competência:
    DETALHE_FOLHA.DESCONTOS += VALOR_PARCELA
    DETALHE_FOLHA.LIQUIDO   -= VALOR_PARCELA
    CONSIG_PARCELA.STATUS    = 'DESCONTADA'
```

### Está completo:
- ✅ Contratos com prazo, parcelas, saldo devedor
- ✅ Margem separada empréstimo/cartão
- ✅ Fluxo de autorização (STATUS_AUTORIZACAO)
- ✅ Integração automática com cálculo da folha (CONSIG-03)
- ⏳ Neoconsig (Sprint 3 — aguarda layout de posição fixa)

---

## MÓDULO 5 — HORA EXTRA
**Arquivo:** `routes/hora_extra.php`
**Tabela:** `HORA_EXTRA`

### Status atual:
- ✅ Registra horas extras aprovadas com valor calculado
- ❌ **NÃO alimenta automaticamente o DETALHE_FOLHA**
- O valor calculado fica em `HORA_EXTRA.VALOR_TOTAL` mas não é somado a PROVENTOS

### Lacuna:
```
HORA_EXTRA.VALOR_TOTAL ──× (não conectado) ──→ DETALHE_FOLHA.PROVENTOS
```

---

## MÓDULO 6 — VERBA INDENIZATÓRIA / DIÁRIAS
**Arquivo:** `routes/verba_indenizatoria.php`, `routes/diarias.php`

### Status atual:
- ✅ Registra os valores aprovados
- ❌ **NÃO alimenta automaticamente o DETALHE_FOLHA**
- Mesma lacuna do módulo de Hora Extra

---

## MÓDULO 7 — RPPS/IPAM
**Arquivo:** `routes/rpps.php`
**Tabela:** `RPPS_CONFIG`

### O que faz:
- Configuração de alíquotas RPPS (INSS patronal para regime próprio)
- Tabela `RPPS_CONFIG` com: ALIQUOTA_SERVIDOR, ALIQUOTA_PATRONAL, TETO_BENEFICIO

### Status:
- ✅ Tabela de configuração existe
- ❌ **NÃO aplica a alíquota no cálculo da folha** — está disponível mas não conectada
- Se fosse conectada: `INSS_SERVIDOR = vencimento_base × ALIQUOTA_SERVIDOR / 100`

---

## DIAGRAMA COMPLETO — O QUE EXISTE vs. O QUE FALTA

```
┌─────────────────────────────────────────────────────────┐
│                   ENTRADAS (o que existe)                │
├─────────────────────────────────────────────────────────┤
│  CARGO / TABELA_SALARIAL ──→ VENCIMENTO BASE ✅          │
│  PROGRESSAO_CONFIG.ANUENIO_PCT ──→ ANUÊNIO ✅           │
│  RPPS_CONFIG.ALIQUOTA_SERVIDOR ──→ disponível ✅        │
│  CONSIG_PARCELA ──→ descontos mensais ✅                 │
│  HORA_EXTRA.VALOR_TOTAL ──→ calculado ✅ / desconect. ❌ │
│  VERBA_INDENIZATORIA ──→ registrado ✅ / desconect. ❌   │
└─────────────────────────────────────────────────────────┘
              ↓ O QUE ESTÁ FALTANDO
┌─────────────────────────────────────────────────────────┐
│           ENGINE DE CÁLCULO (não existe ainda)           │
├─────────────────────────────────────────────────────────┤
│  ❌ IRRF — tabela de alíquotas progressivas (federal)    │
│  ❌ INSS aplicado automaticamente sobre vencimento       │
│  ❌ Insalubridade/Periculosidade (% por cargo)           │
│  ❌ Gratificações por função/cargo                       │
│  ❌ Ligação hora_extra → DETALHE_FOLHA                   │
│  ❌ Ligação verba_indenizatoria → DETALHE_FOLHA          │
│  ❌ Geração automática de DETALHE_FOLHA a partir do      │
│     VENCIMENTO BASE + todos os adicionais acima          │
└─────────────────────────────────────────────────────────┘
              ↓ O QUE O CÁLCULO ATUAL FAZ
┌─────────────────────────────────────────────────────────┐
│              POST /folhas/calcular (atual)               │
├─────────────────────────────────────────────────────────┤
│  LIQUIDO = PROVENTOS - DESCONTOS (dados já importados)   │
│  + desconta parcelas de consig. da competência           │
│  (não calcula proventos nem descontos do zero)           │
└─────────────────────────────────────────────────────────┘
```

---

## A TABELA DE EVENTOS PMSL TEM UTILIDADE PARA O GENTE?

### Resposta direta: **Sim — como REFERÊNCIA, não como importação direta**

| Uso | Serventia | Como usar |
|-----|-----------|-----------|
| **Mapeamento de rubricas** | ✅ Alta | Identificar quais proventos/descontos a PMSL usa → criar equivalência no GENTE |
| **Holerite discriminado** | ✅ Alta | Se quisermos mostrar cada rubrica no holerite (não só o total), os códigos servem como referência |
| **Importação de folha** | ✅ Média | Ao importar a folha da PMSL, usar o código do evento para classificar Provento/Desconto/Neutro |
| **Engine próprio** | ✅ Futura | Se o GENTE implementar cálculo interno, os 817 eventos servem como plano de configuração |
| **Importar como está** | ❌ Não | O formato de posição fixa do sistema da PMSL não é compatível direto — precisa de parser |

### Os 3 eventos mais importantes para começar:
| Código | Nome | Equivalente no GENTE |
|--------|------|---------------------|
| 101 | VENCIMENTO | TABELA_SALARIAL → vencimento base |
| 134 | ANUÊNIO | `CONFIG_ANUENIO_PCT` × anos |
| 511 | PENSÃO ALIMENTÍCIA | CONSIG_CONTRATO (tipo JUDICIAL) |

---

## CONCLUSÃO — O QUE FALTA PARA O GENTE CALCULAR A FOLHA COMPLETO

**Prioridade 1 (para holerite real):**
1. Tabela IRRF federal (alíquotas progressivas por faixa de salário)
2. Aplicar alíquota RPPS_CONFIG automaticamente sobre vencimento base
3. Flag de insalubridade/periculosidade por cargo → % sobre vencimento

**Prioridade 2 (para folha completa interna):**
4. Engine que gera DETALHE_FOLHA automaticamente a partir de TABELA_SALARIAL
5. Conectar HORA_EXTRA.VALOR_TOTAL → DETALHE_FOLHA.PROVENTOS
6. Conectar VERBA_INDENIZATORIA → DETALHE_FOLHA.PROVENTOS

**Prioridade 3 (para substituir o sistema da PMSL):**
7. Motor de eventos configurável (similar aos 817 eventos da PMSL, mas simplificado)
8. Holerite com itemização por rubrica (não só totais)

---

*Gerado por análise de código em 13/03/2026 | Nenhuma alteração realizada*
*Módulos analisados: folha.php, consignacao.php, progressao_funcional.php, rpps.php, hora_extra.php, verba_indenizatoria.php, diarias.php*

---

## APÊNDICE A — EVENTOS PMSL: ESTRUTURA E DADOS REAIS

**Fonte:** `docs/eventos PMSL.xlsx` — extraído em 13/03/2026
**Total:** 817 eventos | 206 proventos ativos | 353 descontos ativos | 258 neutros

### Estrutura das colunas do arquivo de eventos:

| Coluna | O que significa | Relevância para GENTE v3 |
|--------|----------------|--------------------------|
| `Código` | ID numérico da rubrica | Chave primária para mapear no holerite |
| `Evento` | Nome da rubrica | Descrição que aparece no holerite |
| `Tipo` | Provento / Desconto / Neutro | Classifica se soma ou subtrai do líquido |
| `Ativo` | Sim / Não | Se o evento ainda é utilizado |
| `Tipo Folha` | Pagamento / Simulação / 13º | Em qual tipo de folha aparece |
| `Ordem Execucao` | Número inteiro | Sequência de cálculo (importante para dependências) |
| `Base INSS` | Sim / Não | Se entra na base de cálculo do INSS |
| `Base IRRF` | Sim / Não | Se entra na base de cálculo do IRRF |
| `Base PREV` | Sim / Não | Se entra na base previdenciária |
| `Base FGTS` | Sim / Não | Se entra no FGTS |
| `Base IRRF 13°` | Sim / Não | Base para IRRF do 13º salário |
| `Tipo DIRF` | Rendimentos Tributáveis / Isento / etc. | Classificação para DIRF |
| `Sta Insalubridade` | Sim / Não | Se o evento é adicional de insalubridade |
| `Sta Periculosidade` | Sim / Não | Se o evento é adicional de periculosidade |
| `Grau Insalubridade` | 10% / 20% / 40% | Grau do adicional |

### Proventos mais relevantes para o GENTE v3:

| Código | Evento | Base INSS | Base IRRF | Equivalente no GENTE v3 |
|--------|--------|-----------|-----------|------------------------|
| 101 | VENCIMENTO | Sim | Sim | `TABELA_SALARIAL` → vencimento base |
| 102 | VENCIMENTO CLT | Sim | Sim | `CARGO_SALARIO` (celetistas) |
| 99 | FERIAS | Sim | Sim | Módulo Férias e Licenças |
| 111 | 13.SALARIO | Não | Sim | Folha de 13º (fora do escopo atual) |
| 115 | ADIANT 13.SALARIO | Não | Não | Adiantamento 13º |
| 132 | SALARIO FAMILIA | Não | Não | Benefício por dependente |
| 134 | ANUÊNIO | Sim | Sim | `CONFIG_ANUENIO_PCT × anos` ✅ já implementado |
| 137 | INSALUBRIDADE CLT | Sim | Sim | ❌ não implementado (grau 20%) |
| 138 | PERICULOSIDADE/L4616 | Sim | Sim | ❌ não implementado |
| 203 | INSALUBRIDADE/L4616 | Sim | Sim | ❌ não implementado (grau 20%) |
| 198 | SALARIO MINIMO | Não | Não | Base para cálculo de insalubridade |
| 147 | SUBSIDIO | Sim | Sim | Cargos comissionados |
| 105 | SUBST. REMUNERADA | Sim | Sim | Substituição de cargo |
| 117 | PRODUTIVIDADE/LEI4616 | Sim | Sim | ❌ não implementado |

### Descontos mais relevantes para o GENTE v3:

| Código | Evento | Equivalente no GENTE v3 |
|--------|--------|------------------------|
| 511 | PENSÃO ALIMENTÍCIA | `CONSIG_CONTRATO` tipo JUDICIAL |
| 395 | CIASPREV EMPRÉSTIMO | `CONSIG_CONTRATO` tipo BANCO |
| 433 | FUTURO PREV. - CARTÃO | `CONSIG_CONTRATO` tipo CARTAO |
| 396 | CIASPREV MENSALIDADE | Desconto fixo de associação |
| 526 | DEVOL. ERÁRIO PÚBLICO | Desconto de reposição ao erário |
| 501 | DESCONTO SINTTEL | Desconto sindical |
| 521 | SINDEDUCAÇÃO | Desconto sindical |

### Observação sobre insalubridade:
- PMSL usa **grau 20%** como padrão (eventos 137 e 203)
- Base de cálculo: **salário mínimo federal** (não o vencimento base)
- Fórmula: `insalubridade = salario_minimo × 0.20`
- O GENTE v3 precisaria de: flag `CARGO.CARGO_INSALUBRE` + `CARGO.GRAU_INSALUBRIDADE` (10/20/40%)

---

## APÊNDICE B — PROMPT PRONTO PARA PLANEJAMENTO DA ENGINE DE CÁLCULO

> **Instruções de uso:** Copie todo o conteúdo abaixo e cole no Claude (ou outro LLM) para gerar o plano de implementação da engine de cálculo de folha. Depois traga o plano gerado de volta para implementação.

---

```
# Briefing: Sprint "Engine de Cálculo de Folha" — GENTE v3

## Contexto do Projeto
Sistema: GENTE v3 — Gestão de Pessoas para Municípios
Stack: Laravel 8 + Vue 3 (Vite) + SQLite (dev) / SQL Server (produção)
Cliente: Prefeitura Municipal de São Luís / MA

## O que já está implementado (não reimplementar)

### Tabelas existentes no banco:
- `CARGO` (CARGO_ID, CARGO_NOME, CARGO_SALARIO)
- `CARREIRA` (CARREIRA_ID, CARREIRA_NOME, CARREIRA_REGIME)
- `TABELA_SALARIAL` (CARREIRA_ID, TABELA_CLASSE, TABELA_REFERENCIA, TABELA_VENCIMENTO_BASE)
- `FUNCIONARIO` (FUNCIONARIO_ID, CARGO_ID, CARREIRA_ID, FUNCIONARIO_CLASSE, FUNCIONARIO_REFERENCIA, FUNCIONARIO_DATA_INICIO)
- `FOLHA` (FOLHA_ID, FOLHA_COMPETENCIA, FOLHA_STATUS)
- `DETALHE_FOLHA` (DETALHE_FOLHA_ID, FOLHA_ID, FUNCIONARIO_ID, DETALHE_FOLHA_PROVENTOS, DETALHE_FOLHA_DESCONTOS, DETALHE_FOLHA_LIQUIDO)
- `CONSIG_CONTRATO`, `CONSIG_PARCELA`, `CONSIG_CONVENIO` (consignação completa)
- `RPPS_CONFIG` (ALIQUOTA_SERVIDOR, ALIQUOTA_PATRONAL, TETO_BENEFICIO)
- `PROGRESSAO_CONFIG` (CONFIG_ANUENIO_PCT, CONFIG_INTERSTICIO_MESES)
- `HORA_EXTRA` (HORA_EXTRA_ID, FUNCIONARIO_ID, VALOR_TOTAL, STATUS)
- `VERBA_INDENIZATORIA` (registros de verbas pagas)

### Lógica já implementada (routes/progressao_funcional.php):
```php
// Vencimento base:
$tabela = DB::table('TABELA_SALARIAL')
    ->where('CARREIRA_ID', $func->CARREIRA_ID)
    ->where('TABELA_CLASSE', $func->FUNCIONARIO_CLASSE)
    ->where('TABELA_REFERENCIA', $func->FUNCIONARIO_REFERENCIA)
    ->value('TABELA_VENCIMENTO_BASE');
// ou fallback:
$venc = DB::table('CARGO')->where('CARGO_ID', $func->CARGO_ID)->value('CARGO_SALARIO');

// Anuênio:
$anos = Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO));
$anuenio = $venc * ($cfg->CONFIG_ANUENIO_PCT / 100) * $anos;

// Salário total atual:
$salario = $venc + $anuenio;
```

### Cálculo atual da folha (routes/folha.php):
```php
// POST /api/v3/folhas/calcular — apenas isso:
UPDATE DETALHE_FOLHA
SET LIQUIDO = PROVENTOS - DESCONTOS
WHERE FOLHA_ID = ?
// + desconta parcelas de consignação da competência
```

**Problema:** PROVENTOS e DESCONTOS precisam ser inseridos manualmente ou importados.
**Objetivo da Sprint:** fazer o sistema CALCULAR esses valores automaticamente.

## Dados reais da Prefeitura (para referência de regras)

### Estrutura de eventos PMSL (817 rubricas no sistema atual):
- Cada evento tem: Código, Nome, Tipo (Provento/Desconto/Neutro), flags Base INSS/IRRF/PREV/FGTS
- Insalubridade: grau 20% sobre salário mínimo federal (base, não vencimento)
- Periculosidade: 30% sobre o vencimento base
- IRRF: tabela progressiva federal (atualizada anualmente)
- INSS/RPPS: alíquota configurada em RPPS_CONFIG

### Regras de negócio confirmadas:
- Insalubridade grau mínimo (10%): NR-15 anexo XIII e XIV
- Insalubridade grau médio (20%): NR-15 principal
- Insalubridade grau máximo (40%): NR-15 anexo I, II, III, XIII
- Base do adicional de insalubridade = salário mínimo federal (não o vencimento)
- Base da periculosidade = vencimento base (não salário total)
- Anuênio (triênio/quinquênio) = configurável — já implementado no PROGRESSAO_CONFIG

### Tabela IRRF 2025 (referência para implementação):
| Base de cálculo (R$) | Alíquota | Dedução (R$) |
|----------------------|----------|--------------|
| Até 2.259,20 | Isento | — |
| 2.259,21 a 2.826,65 | 7,5% | 169,44 |
| 2.826,66 a 3.751,05 | 15% | 381,44 |
| 3.751,06 a 4.664,68 | 22,5% | 662,77 |
| Acima de 4.664,68 | 27,5% | 896,00 |
| Dedução por dependente: R$ 189,59 |

## O que a Sprint deve implementar

### Escopo prioritário (mínimo viável):

1. **Migration:** Adicionar campos ao CARGO:
   - `CARGO_INSALUBRE` BOOLEAN DEFAULT FALSE
   - `GRAU_INSALUBRIDADE` VARCHAR(3) NULL (valores: '10', '20', '40')
   - `CARGO_PERICULOSO` BOOLEAN DEFAULT FALSE

2. **Migration:** Criar tabela `ITEM_FOLHA`:
   - `ITEM_ID`, `FOLHA_ID`, `FUNCIONARIO_ID`
   - `ITEM_CODIGO` (código da rubrica, ex: 101, 134)
   - `ITEM_DESCRICAO` (nome da rubrica)
   - `ITEM_TIPO` (Provento/Desconto)
   - `ITEM_REFERENCIA` (ex: "24 dias", "Classe A Ref. 3")
   - `ITEM_VALOR` DECIMAL(10,2)

3. **Endpoint:** `POST /api/v3/folhas/{competencia}/gerar`
   Para cada FUNCIONARIO ativo, calcular e gravar em `ITEM_FOLHA`:
   - Código 101 — VENCIMENTO: TABELA_SALARIAL value
   - Código 134 — ANUÊNIO: vencimento × (CONFIG_ANUENIO_PCT/100) × anos
   - Código 137 — INSALUBRIDADE: sal_minimo × (grau/100) se CARGO_INSALUBRE
   - Código 138 — PERICULOSIDADE: vencimento × 0.30 se CARGO_PERICULOSO
   - Código INSS: vencimento × (RPPS_CONFIG.ALIQUOTA_SERVIDOR/100)
   - Código IRRF: calcular com tabela progressiva após deduções
   - Código CONSIG: parcelas CONSIG_PARCELA da competência
   - Código HORA_EXTRA: somar HORA_EXTRA.VALOR_TOTAL aprovadas da competência
   Depois: agregar em DETALHE_FOLHA (PROVENTOS = soma proventos, DESCONTOS = soma descontos)

4. **Endpoint:** `GET /api/v3/holerite/{funcionario_id}/{competencia}`
   Retornar lista de ITEM_FOLHA para o holerite discriminado.

### Regras de cálculo obrigatórias:
- Respeitar ORDEM_EXECUCAO: vencimento base primeiro, depois adicionais, depois deduções
- INSS é calculado ANTES do IRRF (IRRF incide sobre vencimento − INSS)
- Insalubridade base = salário mínimo (não o vencimento)
- Se servidor tem férias no mês: calcular sobre os dias proporcionais
- Dados do salário mínimo devem vir de tabela configurável (não hardcoded)

### O que NÃO está no escopo desta Sprint:
- 13º salário (folha separada)
- Rescisão contratual
- Importação dos 817 eventos da PMSL
- Interface Vue (apenas API backend)

## Arquivos a criar/modificar:

| Arquivo | Ação |
|---------|------|
| `database/migrations/YYYY_engine_folha.php` | Nova migration (CARGO + ITEM_FOLHA) |
| `routes/folha.php` | Adicionar endpoint POST /gerar e GET /holerite |
| `database/seeders/SalarioMinimoSeeder.php` | Tabela de salário mínimo por ano |

## Constraints técnicas:
- SQLite em dev (sem stored procedures)
- Sem Eloquent ORM nas routes — usar DB::table() diretamente
- NÃO abrir Route::middleware()->prefix()->group() nos arquivos de rota
- Usar Carbon para cálculos de data
- Usar DB::transaction() em todo o endpoint /gerar
- Testar com php artisan serve + curl antes de considerar pronto

## Output esperado do plano:
Um documento SPRINTS_ENGINE_FOLHA.md com:
- Migration exata (código PHP pronto)
- Algoritmo de cálculo por rubrica (pseudocódigo → PHP real)
- Endpoints com assinaturas e retornos JSON
- Ordem de execução das tarefas
- Checklist de testes
```

---

*Apêndices adicionados em 13/03/2026*

