# Backlog oficial de performance e escala (Fase 13+)

**Origem:** auditoria estática da Fase 12 (Eixo 2) — diagnóstico sem implementação na Fase 12.  
**Objectivo:** preparar o GENTE v3 para carga municipal (~90k servidores, fechamento de folha, exportações) sem OOM, timeouts nem bloqueio total do browser.

**Regra de governança:** nenhum item abaixo faz parte do *merge* da Fase 12; a Fase 12 codifica **apenas** o Eixo 1 (RBAC / acesso homologação). Este ficheiro é o *mapa de guerra* para sprints seguintes.

---

## P0 — Letal em produção (memória / tempo de resposta)

| ID | Área | Sintoma | Referência de código | Direcção de solução (Fase 13) |
|----|------|---------|------------------------|--------------------------------|
| P0-1 | Motor de folha | Carrega **todos** os servidores activos em memória antes do cálculo (`->get()->keyBy`). | `App\Services\MotorFolhaService` — PASSO 1 | **Fase 13 (implementado):** `calcularFolha` por `chunkById(500)` + `ProcessarLoteFolhaJob` / `Bus::batch`; `POST /api/v3/folha/processar` (202). **Produção:** `QUEUE_CONNECTION=database` ou `redis` + `php artisan queue:work` (o default `sync` executa o batch no mesmo pedido HTTP). |
| P0-2 | CNAB / remessa | `DetalheFolha::with([...])->get()` para a folha inteira. | `CNAB240Builder` + download em [`RemessaBancariaController`](gente/app/Http/Controllers/RemessaBancariaController.php); [`RemessaBancariaService::streamGerarPorFolha`](gente/app/Services/RemessaBancariaService.php) | **Fase 13.5 (implementado):** `cursor()` + `streamRemessa` / `response()->streamDownload` escrita linha a linha em `php://output`; memória acotada. |
| P0-3 | Export / SAGRES | `->get()` sobre `DETALHE_FOLHA` ligado à folha para download/relógio. | `routes/sagres.php` (ex.: download por id) | Mesmo padrão: ficheiro temporário, `LazyCollection`, ou job assíncrono + link de download. |
| P0-4 | Export transparência | Geração CSV/JSON por competência sem padrão de streaming documentado. | `routes/transparencia.php` | Chunk + `StreamedResponse` ou job + artefacto em `storage`. |

---

## P1 — Alto (transacções, N+1, carga mista)

| ID | Área | Sintoma | Referência | Direcção |
|----|------|---------|--------------|-----------|
| P1-1 | Folha × escala | Loops Eloquent aninhados (`Escala` → `DetalheEscala` → `apurarFuncionario`) dentro de transacção longa. | `App\Services\FolhaParserService` | Reduzir janela de transacção; pré-carga em lote; ou pipeline por competência em fila. |
| P1-2 | Locks conceptuais | Fechamento de folha + leituras massivas + exports simultâneos em `DETALHE_FOLHA` / `FOLHA`. | Arquitectura | Janelas de fechamento, filas, índices alinhados a queries de competência; read replicas (documentação infra). |
| P1-3 | ERP / Saúde / Frotas / Almoxarifado | Risco N+1 não inventariado na Fase 12. | Vários controllers | Grep sistemático: `foreach` + query dentro do loop; `load()` sem `with`; listagens sem `limit`. |

---

## P2 — Médio (frontend / UX em listas grandes)

| ID | Área | Sintoma | Referência | Direcção |
|----|------|---------|--------------|-----------|
| P2-1 | Lista de funcionários | Tabela clássica; depende de paginação API com tecto seguro de `per_page`. | `resources/gente-v3/src/views/rh/FuncionariosView.vue` | Garantir tecto no backend; virtual scroll se páginas grandes forem necessárias na UI. |
| P2-2 | Outras views do manifesto | Possível renderização massiva de nós DOM. | `navManifest.js` + views por rota | Inventário: `v-for` + tamanho de payload; padrão único de tabela virtual ou paginação estrita. |

---

## Critérios de aceitação (por item, quando forem para sprint)

- **P0:** execução de smoke com `GENTE_STRESS_SEED` (ou dataset sintético N≥50k) sem OOM e sem timeout HTTP na operação mapeada (ou métrica equivalente em job).
- **P1:** tempo de transacção ou número de queries por funcionário documentado antes/depois.
- **P2:** Lighthouse ou teste manual com N linhas visíveis sem bloqueio perceptível da UI.

---

## Ligações úteis

- Matriz de rotas / smoke: `database/scripts/smoke_routes_matrix.json` (onde aplicável).
- Regras de negócio e seeds opt-in: `docs/davi/BUSINESS_RULES.md`.
- Fase 12 (acesso RBAC homologação): secção **Fase 12** em `docs/davi/BUSINESS_RULES.md` e `GENTE_SEED_AUDITOR_SUPREMO` no `DaviSupremoSeeder`.
