# PROMPT FIX RETROATIVO — GAP-MF-04 (códigos PCCV em InclusaoHorasExtrasService)

> **Cole este prompt no Antygravity ANTES de iniciar a Fase 3.**
> Estimativa: 5 minutos.
> Pré-condição: branch limpa (Fase 2-B mergeada). Nada pendente em `app/Services/Folha/`.

---

## CONTEXTO

Durante a auditoria pré-Fase 3, Claude verificou via MCP o `RubricasCatalogoSeeder.php` (cataloga 27 rubricas oficiais do PCCV de São Luís) e descobriu que **as constantes hardcoded em `InclusaoHorasExtrasService.php` (criado na Fase 2-A) usam códigos que NÃO existem no catálogo**:

| Constante atual | Catálogo PCCV oficial |
|-----------------|------------------------|
| `'HE_50'` | `'030'` (Hora Extra 50%) |
| `'HE_100'` | `'031'` (Hora Extra 100%) |
| `'HE_FER'` | (não existe — usar `'031'` fallback) |
| `'PLANTAO_EXTRA'` | `'032'` (Plantão Extra) |

**Impacto em produção:** quando o motor rodar a folha, `resolverRubricaIdHe()` consulta `RUBRICA WHERE RUBRICA_CODIGO='HE_50'` → retorna NULL → cai em `Log::warning('Rubrica de hora extra não encontrada')` → **HE/Plantão NÃO entram como LANCAMENTO_FOLHA**. GAP-MF-04 fica inerte.

**Solução:** trocar 4 linhas das constantes para os códigos reais do catálogo. Sem migration, sem seeder novo, sem mudança de schema.

---

## TAREFA ÚNICA — Atualizar constantes em InclusaoHorasExtrasService

**Arquivo:** `app/Services/Folha/InclusaoHorasExtrasService.php`

**Trecho atual (linhas ~22-25):**

```php
    /**
     * RUBRICA_ID padrão para hora extra (50%, 100%, feriado).
     * Buscado por código RUBRICA_CODIGO. Se não existir, cria via fallback.
     */
    private const RUBRICA_HE_50_CODIGO = 'HE_50';
    private const RUBRICA_HE_100_CODIGO = 'HE_100';
    private const RUBRICA_HE_FERIADO_CODIGO = 'HE_FER';
    private const RUBRICA_PLANTAO_CODIGO = 'PLANTAO_EXTRA';
```

**Trecho corrigido:**

```php
    /**
     * RUBRICA_CODIGO do catálogo PCCV São Luís (RubricasCatalogoSeeder.php).
     * Confirmado por Claude via MCP em 08/05/2026.
     *
     *   '030' → Hora Extra 50%        (camada 3, percentual_base, incide_prev=1)
     *   '031' → Hora Extra 100%       (camada 3, percentual_base, incide_prev=1)
     *   '032' → Plantão Extra         (camada 3, fixo, incide_prev=1)
     *
     * Não há rubrica específica de "Hora Extra Feriado" no catálogo PCCV — usamos '031' (100%) como fallback,
     * pois feriado trabalhado historicamente é remunerado com adicional de 100%.
     */
    private const RUBRICA_HE_50_CODIGO = '030';
    private const RUBRICA_HE_100_CODIGO = '031';
    private const RUBRICA_HE_FERIADO_CODIGO = '031'; // fallback — não há rubrica específica para feriado
    private const RUBRICA_PLANTAO_CODIGO = '032';
```

**Mudanças exatas:** 4 valores de string trocados + docblock atualizado. Nenhuma mudança em método, lógica ou schema.

---

## VALIDAÇÃO

```powershell
Select-String -Path "app/Services/Folha/InclusaoHorasExtrasService.php" -Pattern "HE_50|HE_100|HE_FER|PLANTAO_EXTRA"
```

**Saída esperada:** apenas referências a NOMES DE CONSTANTES PHP (`RUBRICA_HE_50_CODIGO`, `RUBRICA_HE_100_CODIGO`, etc.). **NÃO pode** haver string literal `'HE_50'`, `'HE_100'`, `'HE_FER'` ou `'PLANTAO_EXTRA'`.

```powershell
Select-String -Path "app/Services/Folha/InclusaoHorasExtrasService.php" -Pattern "'030'|'031'|'032'"
```

**Saída esperada:** 4 ocorrências (3 constantes definidas + 1 fallback HE_FERIADO=031). **Atenção:** `'031'` aparece 2 vezes pois `RUBRICA_HE_100_CODIGO` e `RUBRICA_HE_FERIADO_CODIGO` ambos usam.

---

## COMMIT

```
fix(GAP-MF-04): usar códigos PCCV (030/031/032) em InclusaoHorasExtrasService
```

---

## REPORT TEMPLATE

```
═══════════════════════════════════════════════════════════════════
FIX GAP-MF-04 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÃO:
[ ] Constantes atualizadas para 030/031/032 ........ commit: ____

VALIDAÇÕES (cole saídas reais):

V1 string literal antiga ('HE_50'/'HE_100'/'HE_FER'/'PLANTAO_EXTRA'):
   ___ ocorrências (esperado 0)

V2 string literal novo ('030'/'031'/'032'):
   ___ ocorrências (esperado 4)

V3 git log -n 3:
   ___

TEMPO TOTAL: ___ min
═══════════════════════════════════════════════════════════════════
```

---

**Após este commit, prosseguir para `PROMPT_ANTYGRAVITY_FASE3.md` (Aposentar motores legados).**
