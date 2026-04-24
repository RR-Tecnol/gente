# Verificação do `guia-correcao-programador.md` — baterias (código em 2026-04-22)

Documento satélite do [guia-correcao-programador.md](./guia-correcao-programador.md). Cada item do guia permanece no lugar; aqui registra-se **o que foi confrontado com o repositório** e o que ainda depende de **banco**, **produção** ou **teste manual**.

**Legenda — coluna Situação**

| Código | Significado |
|--------|-------------|
| **OK** | Evidência no código (grep/leitura) alinhada à correção descrita ou ao comportamento esperado. |
| **Parcial** | Parte corrigida; ainda há fallback legado, rota duplicada, ou validação manual pendente. |
| **DB/amb** | Depende de migration, seed, SQL Server ou `.env` (não verificável só com leitura estática). |
| **Manual** | Requer navegação no Vue, fluxo longo ou conferência visual. |
| **Não rev.** | Não analisado linha a linha nesta passagem; tratar como pendente de revisão. |

---

## Bateria A — FASE 0 (bloqueadores: ponto, rotas core, RH básico)

| Item no guia | Situação | Evidência / notas |
|--------------|----------|-------------------|
| Ponto — `DATE()` → `CAST(... AS DATE)` | **OK** | `routes/web.php`: `CAST(REGISTRO_DATA_HORA AS DATE)` em filtro de ponto. |
| Dashboard — avatar sem clique | **OK** | `DashboardLayout.vue`: `topbar-avatar` e `sidebar-profile` com `@click` → `/meu-perfil`. |
| Cargos — `CARGO_CBO` | **OK** | `app/Models/Cargo.php`: sem `CARGO_CBO` no model (grep vazio). |
| Progressões — `require` do arquivo | **Parcial** | `require __DIR__ . '/progressao_funcional.php'` presente **mais de uma vez** no `web.php` — possível duplicação de rotas; funcional mas candidato a limpeza. |
| Estagiários — grupo interno de middleware | **OK** | `routes/estagiarios.php`: sem `Route::middleware(...)->group` envolvendo as rotas. |
| Autocadastro — pendentes / gerar-link | **OK** | `web.php`: rotas `GET/POST` autocadastro gestão no grupo `api/v3` autenticado. |
| Afastamentos — `afastamentos_v3` | **OK** | `require` de `afastamentos_v3.php` no `web.php`. |
| Escala de trabalho — `GET /escala-trabalho` | **OK** | Rota implementada no `web.php` (bloco extenso; não é mais “404 por ausência”). |
| Escala médica — IDs `MOCK` → 500 | **Parcial** | `MatrizEscalaView.vue`: `carregarEscala()` retorna cedo se ID `MOCK*`; fallback ainda lista `MOCK1/MOCK2` se `GET /escalas` falhar. |
| `POST /escalas` e `GET /setores` | **OK** | Rotas existem; smoke HTTP (script do repo) passou com 2xx em ambiente com DB. |
| Portal gestor — equipe sem nome | **OK** | `routes/gestor.php`: uso de `PESSOA_NOME` (não `FUNCIONARIO_NOME` inventado). |
| Portal gestor — CSP dicebear | **OK** | `SecurityHeaders.php`: `img-src` inclui `https://api.dicebear.com`. |
| Hora extra — 500 / tabela | **DB/amb** | `routes/hora_extra.php` usa `\Illuminate\Http\Request`; existência da tabela `HORA_EXTRA` depende do banco. |
| Exoneração / PSS / terceirizados / acumulação | **DB/amb** | Conforme guia: rotas podem existir; 500 se tabelas não migradas. |
| `dd()` em `ChecarAcessoUsuarioUnidade` | **OK** | Sem `dd(` ativo no arquivo da Rule (apenas comentários em outros arquivos). |

---

## Bateria B — FASE 0 (organograma, perfil, holerites, diagnósticos)

| Item no guia | Situação | Evidência / notas |
|--------------|----------|-------------------|
| Organograma — diretoria some no F5 | **Manual** | `organograma_v3.php` tem CRUD de diretoria/unidade; se “some” ainda ocorre, é regra de query/estado Vue — retestar na UI. |
| Meu perfil — salvar silencioso | **Manual** | `routes/meu_perfil.php` tem `PUT /perfil`; validar persistência com dados reais. |
| Holerites — PDF / lista | **OK** (2026-04-22) | `GET /api/v3/meus-holerites` inclui `detalhe_folha_id` (bug: botão sem ação). Fallback: `/api/v3/contra-cheque/{id}/{YYYYMM}/pdf`. UTF-8 em mensagens da rota `holerite-pdf` ajustado. |
| `dd()` ChecarAcesso (repetido no guia) | **OK** | Mesma conclusão da bateria A. |

---

## Bateria C — FASE 1 (UX ponto, gestor, declarações, plantões, escala)

| Item no guia | Situação | Notas |
|--------------|----------|--------|
| Ponto UTC / data | **Manual** | Conferir `PontoEletronicoView.vue` se ainda usa `toISOString` puro. |
| Ícones corrompidos (Férias) | **Não rev.** | Requer inspeção de commit/arquivos citados. |
| Portal — “Ver ficha” | **Não rev.** | |
| Sidebar profile | **OK** | Mesmo padrão do dashboard (Bateria A). |
| Declarações / mock | **Não rev.** | |
| Sobreaviso / plantões extras | **Parcial** | Rotas ajustadas em iterações anteriores; smoke passou; UI manual recomendada. |
| Escala compartilhar 500 / controller | **Não rev.** | Ver `EscalaController.php` se ainda houver. |
| Medicina admin KPIs | **DB/amb** | |
| FASE 1 restantes | **Não rev.** | Usar testes de smoke + tela. |

---

## Bateria D — FASE 2 (melhorias)

| Item no guia | Situação | Notas |
|--------------|----------|--------|
| Vários (holerites UX, organograma cards, diárias) | **Manual** / **Não rev.** | Tratar como melhoria; validar após FASE 0/1 estável. |
| **Diárias** — `tzOffset` duplicado | **OK** | Corrigido no `DiariasView.vue` (build Vue passa). |
| **Contratos/Frotas** — `lucide` default | **OK** | `import { createIcons, icons } from 'lucide'`. |

---

## Bateria E — Sessão 4 (BUG-052 … BUG-061) — faltas/abonos/atestados

| BUG | Situação | Notas |
|-----|----------|--------|
| BUG-052/053/055–057 — abonos, GET/POST abono | **Parcial/OK** | `web.php` contém `/api/v3/abonos-gestao` e `/api/v3/abono-faltas` (trecho dedicado; comentários indicam substituição de rotas antigas). Testar com DB. |
| BUG-058/059 — atestados conflito / aprovar | **Parcial** | `atestados_v3.php` requerido; `atestados.php` comentado; rotas `atestados` no `web.php` em outro bloco — conferir conflito real em `route:list` com banco. |
| BUG-060 — PDF atestado | **Manual** | |
| Outros 061 | **Não rev.** | |

---

## Bateria F — Sessão 5 (BUG-062…069) + SEEDs 007–033

| Tema | Situação | Notas |
|------|----------|--------|
| Medicina `AGENDAMENTO` / agendar | **Parcial/OK** | Ajuste `tipo_exame` ↔ coluna em iteração recente; smoke `medicina/agendar` passou. |
| Segurança incidentes (modal) | **Manual** | |
| Folha `/calcular` | **Não rev.** | Depende de `routes/folha.php` e permissões. |
| Seeds BANCO_HORAS, escala, substituições | **DB/amb** | `SidebarCoverageSeeder` e carga local. |
| Diárias BUG-045 | **Não rev.** | |

---

## Bateria G a J — Sessões 6–11 (BUG-070 … BUG-130) e ERP

| Faixa | Situação geral |
|------|----------------|
| G — BUG-070/071 (consignação, consignatárias) | **DB/amb** + **Manual** — conferir tabelas e policies. |
| H — BUG-072–102 (OSS, eSocial, SAGRES, agenda, pesquisa, verbas…) | **Não rev. sistemático** — maior parte exige banco e migrations. |
| I — BUG-103–121 (comunicados, ouvidoria, motor, parâmetros, eventos…) | **Parcial** — `routes/motor.php` sem `PRAGMA` (tratado em código atual); resto: testar por módulo. |
| J — BUG-122–130 (orçamento, tesouraria, PCASP, SAGRES XML) | **DB/amb** | Módulo fiscal/ERP. |

---

## Bateria K — Resumos e “`require` faltando” (final do guia)

- Vários `require` listados como faltando **já constam** no `web.php` atual (módulos extraídos, ERP, `ponto_app.php` para app mobile, etc.). Em caso de dúvida: `rg "require __DIR__" routes/web.php`.
- Padronização `perfil:Administrador` vs `perfil:ADMIN` e `PRAGMA` → `Schema::` : aplicar busca global quando fizer sprint de higiene (não repetido item a item neste arquivo).

---

## Bateria L — Fase segurança / PENTEST (final do guia)

| Tema | Situação | Notas |
|------|----------|--------|
| `dd()` / logs sensíveis / SISGEP hardcoded | **Parcial** | Revisar `SpaAuthController`, `AlterarSenha`, `Usuario` fillable, conforme lista PENTEST — muitas são decisões de produto. |
| Duplicata `api/auth` | **OK** | Grupo duplicado com `SpaAuthController` removido; `logout` nomeado; vínculo admin em não-prod via `applyDevAdminFuncionarioVinculo` após login. |
| Git filter-repo / credenciais | **Processo** | Não operação de código em um passo. |
| CSP strita / HSTS | **Manual/ops** | `SecurityHeaders.php` ainda pode ter `unsafe-inline` para o Vue; decisão de deploy. |

---

## Seeds (`DatabaseSeeder` + `SidebarCoverageSeeder`)

- **`DatabaseSeeder`**: ordem lógica (tabelas genéricas → perfis → usuário admin → `SidebarCoverageSeeder`). Adequado para ambiente de desenvolvimento com SQL Server.
- **`SidebarCoverageSeeder`**: schema-aware (só insere se tabela/coluna existir); se não houver funcionário ativo, emite aviso e encerra. Cobre ponto, banco de horas, hora extra, escala, declarações, **folha 202601–202604** com `DETALHE_FOLHA` (inclui `DETALHE_FOLHA_LIQUIDO` quando a coluna existe). **Não substitui** carga completa de ERP/fiscal — módulos como PCASP/orçamento dependem de tabelas e regras locais.
- Após seed: rodar `php artisan db:seed` (ou `--class=SidebarCoverageSeeder`) com banco acessível; validar holerites com login cujo `FUNCIONARIO` tenha linha em `DETALHE_FOLHA`.

## Próximos passos sugeridos

1. Com SQL Server: rodar `php artisan route:list` (se o ambiente suportar), `python3 scripts/smoke_api_criacao_modais.py` e testes manuais dos módulos **Manual** acima.  
2. Atualizar **este** arquivo ao fechar cada BUG (data + 1 linha de evidência).  
3. Refatorar `require` duplicados de `progressao_funcional.php` / `afastamentos_v3.php` se `route:list` mostrar duplicata.

---

*Última atualização: 2026-04-22 — reconciliação automática de leitura e trechos já validados no projeto; itens “Não rev.” não implicam bug, apenas falta de escopo nesta execução.*
