---
description: Protocolo para diagnosticar, corrigir e documentar bugs no GENTE
---

# Workflow: Resolver Bug

Use este workflow para qualquer bug — do BUG-01 ao BUG-07 do plano, ou bugs novos encontrados durante o desenvolvimento.

---

## Fase 1 — Reproduzir

Antes de alterar qualquer código, reproduzir o bug de forma consistente.

### Checklist de reprodução:
- [ ] Qual endpoint / rota está falhando? (ex: `POST /api/v3/consignacao`)
- [ ] Qual o status HTTP retornado? (200, 404, 422, 500, 419?)
- [ ] Qual a mensagem de erro exata? (copiar verbatim)
- [ ] O bug ocorre sempre ou só em condições específicas? (competência, perfil de usuário, dados específicos?)
- [ ] Consegue reproduzir com um curl/Postman? → isola se é problema de frontend ou backend

```bash
# Testar endpoint diretamente (substitua os valores):
curl -X POST http://localhost/api/v3/rota \
     -H "Content-Type: application/json" \
     -H "Cookie: laravel_session=SEU_COOKIE" \
     -H "X-XSRF-TOKEN: SEU_TOKEN" \
     -d '{"campo": "valor"}'
```

---

## Fase 2 — Diagnosticar

### Verificar logs Laravel:
```bash
tail -50 storage/logs/laravel.log
# ou para ver em tempo real:
tail -f storage/logs/laravel.log
```

### Verificar qual arquivo de rota está sendo chamado:
```bash
php artisan route:list | grep "nome-da-rota"
# Mostra: método, URI, middleware, nome
```

### Para bugs de query SQL — ativar log de queries temporariamente:
```php
// Adicionar no início do endpoint (remover após debug):
DB::listen(function($query) {
    \Log::info($query->sql, $query->bindings);
});
```

### Checklist de diagnóstico:
- [ ] O arquivo de rota existe? (`ls routes/ | grep modulo`)
- [ ] A rota está registrada? (`php artisan route:list | grep rota`)
- [ ] Os campos usados na query existem na tabela? (verificar migration)
- [ ] O grupo de rotas está duplicado? (ver §2 de regras-gerais.md)
- [ ] Está usando `fetch()` nativo em vez de axios? (ver §3 de regras-gerais.md)
- [ ] Está usando campos que não existem? (`DETALHE_TIPO`, `DETALHE_VALOR` → inexistentes, ver BUG-01)

---

## Fase 3 — Identificar causa raiz

Não corrigir o sintoma. Identificar a causa real:

| Sintoma | Causa provável | Ver |
|---------|---------------|-----|
| Margem consignação = 0 | Campos `df.DETALHE_TIPO` / `df.DETALHE_VALOR` não existem | BUG-01 + §12 |
| Usuário com menos permissão acessa rota restrita | `hasAccess()` lógica invertida em router/index.js | BUG-02 |
| Request HTTP a cada clique/navegação | `fetchUser()` sem cache TTL | BUG-03 |
| Erro 419 em formulários POST | `fetch()` nativo com CSRF manual | BUG-04 + §3 |
| Rota registrada em path errado `/api/v3/api/v3/...` | Grupo duplicado em arquivo require'd | BUG-07 + §2 |
| Query não retorna resultado para competência válida | Formato `AAAA-MM` sendo passado ao banco | §15 |
| CSV com caracteres quebrados no Excel | Ausência de BOM UTF-8 | §14 |
| Tabela não encontrada em Linux/Docker | Nome de tabela em minúsculas | §13 |

---

## Fase 4 — Implementar correção

### Antes de alterar:
1. Anotar o estado atual (código original) para registrar depois
2. Fazer a menor alteração possível que corrija o problema
3. Verificar se a correção viola alguma regra em `regras-gerais.md`

### Padrão de correção para cada tipo:

**Query com campos errados (BUG-01):**
```php
// Verificar campos reais da tabela:
// php artisan tinker → DB::select('DESCRIBE DETALHE_FOLHA');
```

**Lógica Vue invertida (BUG-02, BUG-03):**
```bash
# Recarregar o servidor de dev após alteração:
npm run dev
# Limpar cache do navegador (Ctrl+Shift+R)
```

**fetch() nativo (BUG-04):**
```js
// Buscar todas as ocorrências:
grep -rn "await fetch(" resources/gente-v3/src/
// Substituir uma a uma por api.post/api.get
```

**Grupo duplicado (BUG-07):**
```bash
# Verificar quais arquivos têm o padrão proibido:
grep -l "prefix.*api/v3" routes/*.php
# Para cada um: remover o Route::middleware()->prefix()->group() wrapper
```

---

## Fase 5 — Verificar

Após a correção:

```bash
# 1. Rotas sem duplicação:
php artisan route:list | grep nome-da-rota
# Deve aparecer uma vez, com path correto

# 2. Nenhum erro de sintaxe PHP:
php -l routes/arquivo_corrigido.php

# 3. Log sem novos erros:
tail -20 storage/logs/laravel.log

# 4. Testar o endpoint novamente:
curl ... # mesmo comando da Fase 1
```

Se o bug for em Vue, verificar no navegador com DevTools aberto (aba Network + Console).

---

## Fase 6 — Documentar

Registrar em `docs/historico-problemas.md`:

```markdown
## [YYYY-MM-DD] Descrição curta do bug

**Sprint / Módulo:** ex: Sprint 1 / Consignação

**Sintoma:**
> Mensagem exata do erro / comportamento observado

**Causa raiz:**
O que realmente estava errado (não o sintoma).

**Solução:**
```php ou js
// Código que corrigiu
```

**O que NÃO funcionou:**
- Tentativa X → por que falhou

**Arquivos alterados:**
- `routes/consignacao.php` — função calcularMargem
- `resources/.../ConsignacaoView.vue` — linha 47
```

Se a causa revelar um padrão que pode se repetir → **criar nova regra** em `regras-gerais.md` com número sequencial.

Se a abordagem inicial de diagnóstico estava errada → registrar em `docs/historico-estrategias-erradas.md`.

---

## Referência rápida — BUGs do plano

| ID | Arquivo | Descrição curta | Status |
|----|---------|----------------|--------|
| BUG-01 | routes/consignacao.php | Usa DETALHE_FOLHA_LIQUIDO (correto) | ✅ Corrigido Sprint Antigravity |
| BUG-02 | router/index.js | hasAccess() usa <= (correto) | ✅ Confirmado no código real |
| BUG-03 | store/auth.js | fetchUser() tem cache TTL 5min | ✅ Confirmado no código real |
| BUG-04 | ConsignacaoView, ExoneracaoView, HoraExtraView | fetch() nativo | ❌ Pendente — verificar views |
| BUG-05 | routes/esocial.php | Subquery O(n²) em /esocial/pendencias | ❌ Pendente |
| BUG-06 | routes/progressao_funcional.php | HISTORICO_FUNCIONAL correto | ✅ Confirmado no código real |
| BUG-07 | routes/diarias.php, routes/rpps.php | Grupo duplicado | ❌ Pendente — não verificado |
| IC-01  | routes/web.php | Rotas auth dentro de isLocal()/dev → 404 | 🔴 CAUSA RAIZ LOGIN — Sprint 0 TASK-01 |
| IC-09  | config/cors.php | supports_credentials=false + wildcard → sem sessão | 🔴 BLOQUEANTE — Sprint 0 TASK-02 |
| IC-12  | routes/progressao_funcional.php | BOM UTF-8 + sem use Carbon\Carbon | 🟠 Sprint 0 TASK-05 |
| IC-13  | router/index.js | path '/' redirect duplicado → logado vai para /login | 🟠 Sprint 0 TASK-04 |
