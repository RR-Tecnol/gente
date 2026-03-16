---
description: Como documentar qualquer solução implementada no GENTE — template e instruções
---

# Workflow: Documentar Solução

Use este workflow ao **concluir** qualquer tarefa — bug fix, novo módulo, refatoração ou sprint. A documentação deve ser feita **na mesma sessão** em que o trabalho foi realizado, enquanto o contexto ainda está disponível.

---

## Quando usar este workflow

| Situação | Onde documentar |
|----------|----------------|
| Corrigiu um bug | `docs/historico-problemas.md` |
| Tentou algo que não funcionou | `docs/historico-estrategias-erradas.md` |
| Criou regra nova que deve ser seguida | `.agent/workflows/regras-gerais.md` |
| Concluiu um módulo (backend + frontend) | `PLANO_IMPLEMENTACAO_GENTE_V3.md` (marcar como ✅) |
| Concluiu uma sprint inteira | `PLANO_IMPLEMENTACAO_GENTE_V3.md` + `historico-problemas.md` |

---

## Template 1 — Bug corrigido (`docs/historico-problemas.md`)

```markdown
## [YYYY-MM-DD] Título curto e descritivo

**Sprint / Módulo:** ex: Sprint 1 / Consignação

**Sintoma:**
> Mensagem de erro exata, rota afetada, status HTTP, comportamento observado

**Causa raiz:**
Explicação objetiva — não o sintoma, a causa real. Ex: "campo X não existe na tabela Y"

**Solução que funcionou:**
```php
// Código exato que resolveu
```

**O que NÃO funcionou:**
- Tentativa 1 — por que falhou especificamente
- Tentativa 2 — por que falhou especificamente

**Como identificar no futuro:**
Sintoma, mensagem de erro ou comando de diagnóstico que aponta para este problema.

**Arquivos alterados:**
- `routes/modulo.php` — descrição da alteração
- `resources/.../NomeView.vue` — descrição da alteração

**Status:** ✅ Resolvido
```

---

## Template 2 — Estratégia errada (`docs/historico-estrategias-erradas.md`)

Use quando uma abordagem foi tentada, pareceu razoável, mas falhou. O objetivo é que nenhum agente futuro repita o mesmo erro.

```markdown
## [YYYY-MM-DD] Título descritivo da abordagem errada

**Módulo:** nome do módulo

**Abordagem errada tentada:**
O que foi tentado e por que parecia razoável na época.

**Por que falhou:**
Causa técnica ou conceitual exata do fracasso.

**Abordagem correta:**
O que deveria ter sido feito desde o início.

**Regra gerada:**
Se esta falha gerou uma nova regra em `regras-gerais.md`, referenciar aqui.
Ex: "Ver §13 de regras-gerais.md"
```

---

## Template 3 — Nova regra de desenvolvimento (`regras-gerais.md`)

Use quando identificar um padrão que pode se repetir e causar problemas. Adicionar ao final de `.agent/workflows/regras-gerais.md` com número sequencial.

```markdown
## N. REGRA — TÍTULO DA REGRA

> Contexto de uma linha explicando por que esta regra existe.

```php ou js
// ❌ PROIBIDO:
código errado

// ✅ CORRETO:
código certo
```

**Erro real que gerou esta regra:** referência ao historico-problemas.md
```

---

## Template 4 — Módulo concluído (`PLANO_IMPLEMENTACAO_GENTE_V3.md`)

Ao concluir backend + frontend de um módulo, atualizar duas seções do plano:

**1. Na tabela §11 (Mapa de Status), trocar o status:**
```markdown
| **Nome Módulo** | `routes/modulo.php` ✅ | `NomeView.vue` ✅ | ✅ PRONTA | ✅ COMPLETO |
```

**2. No checklist §10, marcar o item:**
```markdown
- [x] GAP-XX — Descrição do que foi feito
```

**3. Atualizar tabela §17 de `regras-gerais.md`:**
```markdown
| Módulo | ✅ NomeView.vue | ✅ routes/modulo.php | ✅ Completo |
```

---

## Checklist pós-tarefa

Antes de encerrar qualquer sessão de desenvolvimento, confirmar:

- [ ] Bugs corrigidos → registrados em `docs/historico-problemas.md`
- [ ] Abordagens que falharam → registradas em `docs/historico-estrategias-erradas.md`
- [ ] Padrões novos identificados → virou regra em `regras-gerais.md`?
- [ ] Módulos concluídos → marcados no `PLANO_IMPLEMENTACAO_GENTE_V3.md`
- [ ] Tabela §17 de `regras-gerais.md` reflete o estado atual dos módulos
- [ ] Nenhum `[GAP-BACKEND]` novo ficou sem registro em `historico-problemas.md`

---

## Por que documentar na mesma sessão

O agente não tem memória entre sessões. Todo o contexto de "por que foi feito assim", "o que foi tentado antes" e "qual era o estado anterior" existe apenas enquanto a sessão está ativa. Se não for documentado antes do encerramento, esse contexto se perde permanentemente — e a próxima sessão começa sem saber o que foi tentado, o que funcionou e o que deve ser evitado.

A documentação é a memória persistente do projeto.
