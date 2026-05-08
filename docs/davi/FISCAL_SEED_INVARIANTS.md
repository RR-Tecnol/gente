# Domínio 4 — Invariantes para seeds fiscais / PCASP (homolog)

Objetivo: qualquer expansão de massa em **Orçamento**, **Execução da despesa**, **Contabilidade (PCASP)**, **Tesouraria** e **Receita** deve respeitar estas regras, para evitar demonstrações que um auditor classifique como incoerentes.

## 1. Orçamento (PPA / LOA)

- **Hierarquia:** PPA → Programa → Ação → LOA (quando o modelo relacional existir). Não inserir `ORCAMENTO_LOA` órfã de `ACAO_ID` / `PROGRAMA_ID` se a API ou a view esperar a cadeia completa.
- **Verba:** a soma das **dotações** utilizadas por empenhos do mesmo exercício não deve exceder o que a UI/API expõe como “disponível” (definir campo-fonte no ETL ou usar `ORCAMENTO_LOA` valor explícito e respeitar em seeds).

## 2. Execução da despesa (empenho → liquidação → pagamento)

- **Encadeamento:** todo `EMPENHO` de demonstração deve ter `LOA_ID` válido (já materializado por `SidebarCoverageSeeder` ou equivalente).
- **Valores:** `LIQUIDACAO_VALOR` ≤ `EMPENHO_VALOR` (liquidação parcial é aceitável); `PAGAMENTO_VALOR` ≤ soma das liquidações vinculadas ao mesmo empenho, salvo regra explícita de pagamento antecipado documentada no código.
- **Estado:** alinhar `EMPENHO_STATUS` com o passo da pipeline (ex.: não marcar `PAGO` sem registo de `PAGAMENTO_DESPESA`).
- **Idempotência:** usar chave natural estável (`EMPENHO_NUMERO` fixo no seed, como `2026NE000123`) para `updateOrInsert` / “find or create”, evitando duplicar empenhos a cada `db:seed`.

## 3. PCASP e lançamentos contábeis

- **Partida simples:** cada `LANCAMENTO_CONTABIL` deve ter débito e crédito com **mesmo valor** na visão de homologação actual (`LANCAMENTO_VALOR` único com `CONTA_DEBITO_ID` e `CONTA_CREDITO_ID`).
- **Natureza:** contas de natureza **DEVEDORA** vs **CREDORA** devem refletir o movimento esperado (ex.: despesa de material vs caixa); não trocar só para “fechar gráfico”.
- **Códigos:** preferir contas **analíticas** já alinhadas ao plano mínimo (`PcaspSeeder`) antes de inventar novos códigos PCASP; novas contas analíticas exigem revisão de sintaxe do código MCASP da STN.

## 4. Tesouraria e receita

- **CONTA_BANCARIA / MOVIMENTACAO_BANCARIA:** movimentos devem referenciar `CONTA_ID` existente; saldo agregado da conta não deve ficar negativo sem cenário documentado (ex.: cheque especial simulado).
- **RECEITA_LANCAMENTO / RECEITA_DIVIDA_ATIVA:** totais exibidos em `/receita-municipal` devem ser consistentes com filtros por `ano` usados na API.

## 5. Compliance (SAGRES / SICONFI / transparência)

- **RREO / RGF / SICONFI:** valores são **agregados de relatório**; não precisam bater centavo a centavo com cada empenho, mas não devem contradizer narrativa (ex.: despesa empenhada maior que dotação atualizada sem nota de suplementação).
- **SAGRES_EXPORTACAO / TRANSPARENCIA_EXPORTACAO:** manter `COMPETENCIA` e `STATUS` coerentes com o que a view lista (`VALIDADO`, `PUBLICADO`).

## 6. Verificação recomendada (pós-seed)

- Script SQL ou teste automatizado: contar `EMPENHO` por `LOA_ID` e comparar soma de `EMPENHO_VALOR` ao teto da LOA (quando coluna existir).
- Para `LANCAMENTO_CONTABIL`: soma por mês de valores a débito = soma a crédito por competência de homolog.

O seeder dedicado [`ErpFiscalCoverageSeeder`](../../database/seeders/ErpFiscalCoverageSeeder.php) implementa apenas a **cadeia mínima** que satisfaz estes invariantes na base actual.
