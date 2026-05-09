# DÍVIDA TÉCNICA — Pós Go-Live PMSL

Documento vivo. Cada item rastreia uma decisão pragmática (workaround) tomada
para entregar antes do PoC, com a versão correta a aplicar pós go-live.

---

## DT-MOTOR-01 — MotorFolhaService lê VINCULO_ID de FUNCIONARIO em vez de LOTACAO

**Severidade:** ⚠️ Alta — anti-pattern arquitetural com dados duplicados.

**Sintoma:** `MotorFolhaService::calcularLoteParaFuncionarios()` (linha 297) faz:
```php
->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
```
Mas em PMSL produção (e arquiteturalmente em GENTE v3), `VINCULO_ID` **vive em LOTACAO**,
não em FUNCIONARIO. Cada lotação tem seu vínculo, permitindo histórico de mudanças
(ex: efetivo vira FC quando ocupa direção).

**Workaround pré-go-live (09/05/2026):**
Migration `2026_05_09_000001_add_vinculo_id_to_funcionario.php` adiciona coluna nullable
em FUNCIONARIO. Servidores demo populam o campo. Servidores reais ficam NULL → motor cai
no fallback `$s->VINCULO_TIPO ?? 'efetivo'` na linha 357 (caminho seguro).

**Solução correta (pós go-live):**

1. Refatorar o leftJoin do motor pra resolver vínculo via LOTACAO ativa pra competência:
   ```php
   ->leftJoin('LOTACAO as l', function($j) use ($primeiroDia, $ultimoDia) {
       $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
         ->where('l.LOTACAO_DATA_INICIO', '<=', $primeiroDia)
         ->where(function($q) use ($ultimoDia) {
             $q->whereNull('l.LOTACAO_DATA_FIM')
               ->orWhere('l.LOTACAO_DATA_FIM', '>=', $ultimoDia);
         });
   })
   ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'l.VINCULO_ID')
   ```
2. Ajustar `MotorFolhaLoteContext` se ele tocar em VINCULO_ID de FUNCIONARIO em outros pontos.
3. Após validar 2-3 folhas mensais reais, criar migration `down` que remove `FUNCIONARIO.VINCULO_ID`.

**Tickets relacionados:** abrir DT-MOTOR-01 no backlog pós-PoC.

---

## DT-MOTOR-02 — Motor não descontava faltas

✅ **Resolvido em 09/05/2026** (commit `9140df7`).
`InclusaoFaltasService` converte `APURACAO_PONTO` da competência anterior em
`LANCAMENTO_FOLHA` tipo D, com idempotência via `APURACAO_STATUS='APLICADA_FOLHA'`.

---

## DT-SCHEMA-01 — ATRIBUICAO_LOTACAO sem datas de vigência em PMSL

**Severidade:** Média — limitação de auditoria histórica.

Schema atual de `ATRIBUICAO_LOTACAO` em PMSL tem APENAS:
- ATRIBUICAO_LOTACAO_ID, ATRIBUICAO_ID, LOTACAO_ID
- ATRIBUICAO_LOTACAO_CARGA_HORARIA, ATRIBUICAO_LOTACAO_ATIVO, ATRIBUICAO_LOTACAO_ATIVA

**Não tem `ATRIBUICAO_LOTACAO_INICIO` nem `ATRIBUICAO_LOTACAO_FIM`** — não dá para rastrear
quando uma atribuição foi assumida ou cessada dentro de uma mesma lotação.

**Workaround pré-go-live:** seeder schema-defensive (não preenche se a coluna não existe).

**Solução correta (pós go-live):**
Migration adicionando as duas colunas como nullable. Backfill com `LOTACAO_DATA_INICIO`
para registros existentes (premissa: atribuição vigente desde o início da lotação).

---

## DT-SCHEMA-02 — USUARIO sem coluna USUARIO_CPF em PMSL

**Severidade:** Baixa — afeta apenas relatórios cruzados.

A tabela USUARIO em PMSL produção não tem `USUARIO_CPF`. CPF do usuário é resolvido
via `PESSOA.PESSOA_CPF_NUMERO` join com `PESSOA.USUARIO_ID`.

**Workaround:** seeder schema-defensive.

**Solução correta (pós go-live):** decidir se centraliza CPF na PESSOA (mais correto, LGPD)
ou denormaliza em USUARIO. Recomendação: manter na PESSOA. Documentar nas queries.

---

## DT-MIG-01 — composer minimum-stability=dev em produção (já documentado em userMemories)

Pós go-live, investigar `composer why-not php 8.4` e fixar (provavelmente troca
`minimum-stability` pra `stable` ou pin de pacote dev). Sem isso, qualquer
`composer install/update` REinstala o `platform_check.php` original e quebra o sistema.

---

## DT-FRONT-01 — Mocks no frontend mascarando erros de API como dados de produção

**Severidade:** 🚨 **CRÍTICA** — risco de credibilidade em demonstrações reais.

**Sintoma observado (09/05/2026):** A tela de Folha de Pagamento exibia 6 folhas
históricas falsas (Set/2025 a Fev/2026) com valores como "87 servidores, R$ 543.210,00"
quando o banco real tinha apenas as 2 folhas DEMO POC vazias. Cliente consegue
abrir a tela e ver "produção funcionando" mesmo com endpoint quebrado.

**Causa raiz:** Padrão sistêmico de `try { api.get(...) } catch { folhas.value = [mocks] }`
em múltiplas views Vue 3, inserido durante desenvolvimento como técnica defensiva
("se backend não estiver pronto, mostrar layout") e nunca removido.

**Arquivos identificados (varredura `mock|fixture|fakedata` retornou 219 hits em ~10 views):**
- ✅ `views/financeiro/FolhaPagamentoView.vue` — corrigido em 09/05/2026 (sem mock; banner de erro)
- ⚠️ `views/financeiro/FolhaPagamentoView - Copia.vue` — arquivo lixo versionado, **DELETAR**
- ⚠️ `views/saude/OssView.vue`
- ⚠️ `views/AgendaView.vue`
- ⚠️ `views/rh/AcumulacaoView.vue` + `AcumulacaoView - Copia.vue` (deletar cópia)
- ⚠️ `views/rh/AbonoFaltasView.vue` + `AbonoFaltasView - Copia.vue` (deletar cópia)
- ⚠️ `views/dashboard/DashboardExecutivoView.vue` (string "simulado" hardcoded)
- ⚠️ `views/ComunicadosView.vue`

**Workaround pré-go-live (09/05/2026):**
- Removido o mock crítico de Folha de Pagamento (a primeira tela do go-live).
- Endpoint `/api/v3/folhas` corrigido (era a causa do fallback ativar).
- Banner de erro real adicionado ao `FolhaPagamentoView.vue`.
- Demais views ficam com mock até pós go-live (segunda prioridade).

**Solução correta (pós go-live):**
1. Auditar todos os 219 hits e substituir o `catch { mocks }` por exibição de erro real.
2. Deletar os arquivos `*Copia.vue` versionados (lixo de "save as" do editor).
3. Adicionar regra ao `.agent/workflows/regras-gerais.md`: **"PROIBIDO usar mocks como
   fallback de API. Se endpoint falha, mostrar erro real ao operador. Mocks são
   apenas para testes unitários — nunca em produção/staging."**
4. Criar lint rule no Vue (eslint-plugin-vue ou regex CI) que falhe ao detectar
   `catch.*=.*\[\s*\{` em arquivos `.vue`.

---

## DT-API-01 — Endpoint /api/v3/folhas usava Eloquent::with() em relação inexistente

**Severidade:** Alta — bloqueava tela principal do go-live.

**Sintoma:** `GET /api/v3/folhas` retornava 500 com erro
```
SQLSTATE[42S22]: Invalid column name 'HISTORICO_FOLHA_ULTIMO'
```

**Causa raiz:** Em `routes/api_v3_auth_part2.php` linha 1591:
```php
$folhas = \App\Models\Folha::with(['tipoFolha', 'historicoUltimo.statusEscala'])
```
A relação `historicoUltimo` no Model `Folha` tenta resolver `HISTORICO_FOLHA_ULTIMO`
que não existe no schema PMSL. Eloquent levanta PDOException.

**Workaround pré-go-live (09/05/2026):**
Endpoint reescrito sem Eloquent relations, usando `DB::table('FOLHA')` direto +
schema-defensive nos campos opcionais (`FOLHA_TOTAL_PROVENTOS`, `FOLHA_TOTAL_DESCONTOS`,
`FOLHA_TOTAL_LIQUIDO`). Try/catch retorna 500 com erro real (sem mock).
Chaves alinhadas com o que o frontend espera (`FOLHA_ID`, `qtd_funcionarios`,
`total_proventos`, etc.).

**Solução correta (pós go-live):**
1. Auditar o Model `Folha` e remover/corrigir a relação `historicoUltimo`.
2. Decidir arquiteturalmente: o "último histórico" da folha vai por cache embutido
   (`HISTORICO_FOLHA_ULTIMO` em FOLHA, mais rápido) OU por subquery em HISTORICO_FOLHA
   (mais correto, sem desnormalização). Se optar pelo cache: criar migration adicionando
   a coluna em PMSL e atualizar o motor pra escrevê-la a cada processamento.
3. Padronizar TODAS as listagens de folha pra usar a mesma forma (DB::table direto OU
   Eloquent com relações validadas no boot).

