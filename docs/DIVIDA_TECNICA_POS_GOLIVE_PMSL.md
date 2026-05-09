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
