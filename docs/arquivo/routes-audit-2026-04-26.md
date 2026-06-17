# Auditoria de rotas `api/v3` — P0 Fase F (F.0.1 + F.0.2)

**Data:** 2026-04-26  
**Escopo:** duplicidade **plantões extras**, **escala de trabalho**, **funcionários** (F.1) + baseline `route:list`.

## Baseline (F.0.2)

- Snapshot JSON: `gente/docs/arquivo/route-list-api-v3-2026-04-26.json` (regenerar após alterações de rotas).
- Comando: `php artisan route:list --path=api/v3 --json` (executar na raiz do projeto Laravel, pasta `gente/`).

## Pares duplicados removidos (F.1) — *winner* e origem

| Método + path | Definição morta (sobrescrevida) | *Winner* (mantido) | Ficheiro vencedor |
|---------------|----------------------------------|--------------------|--------------------|
| `GET/POST /api/v3/escala-trabalho` | *Closure* cedo no `web.php` (após `funcionarios.php` / autocadastro) | Idem, registado depois | `routes/escala_trabalho.php` (e agora com filtro de funcionário ativo alinhado ao bloco antigo) |
| `GET/POST /api/v3/plantoes-extras` | Idem, bloco no `web.php` depois de `require plantoes_sobreaviso.php` | A última definição (web) vencia | Conteúdo vencedor consolidado em `routes/plantoes_sobreaviso.php`; bloco no `web.php` removido |
| `GET/POST/PUT/DELETE/… /api/v3/funcionarios*`, `/apoio` | `routes/funcionarios.php` cedo; duplicata grande no `web.php` | A duplicata final no `web.php` (RH completo) | Tudo reagrupado em `routes/funcionarios.php`; bloco no `web.php` removido |

## Outras ocorrências (não alteradas neste PR)

- **`GET /api/v3/ponto`:** continuam a existir vários registos (`web.php`, `funcionarios.php`, `ponto_eletronico.php`) — **P1** a tratar; ver `route:list` (última rota ganha).
- **Bloco `dev/api/v3/...`:** apenas ambiente `local/development` — outra árvore de rotas; não confundir com o grupo principal.
- **F.2 (agregador `api_v3.php`):** pendente; `web.php` ainda contém muitas *closures* (objectivo: só *wiring* + *requires* ordenados).

## Critério de aceite F.0.1

- Tabela preenchida com ficheiro e regra *última rota ganha* para os três alvos P0.
- Sem segunda `Route::` ativa no Laravel para o mesmo `METHOD+uri` nos caminhos acima (verificar via JSON do `route:list`).

*Fim.*
