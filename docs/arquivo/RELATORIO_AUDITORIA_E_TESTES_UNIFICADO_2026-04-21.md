# RELATORIO DE AUDITORIA E TESTES UNIFICADO — GENTE (2026-04-21, reconciliado com código em 2026-04-23)

## Objetivo

Consolidar em um só lugar o estado de bugs, seed/banco e segurança, com base em evidência real de código e ambiente, incluindo o resultado técnico dos testes das correções.

## Verificacao recente (2026-04-23)

Foi feita nova conferencia usando:

- `docs/guia-correcao-programador.md`
- `docs/guia-baterias-verificacao-2026-04-22.md`
- Brain: `_Global/PROJETOS/RRTECNOL/GENTE/bugs-catalogados/guia-correcao-programador 1.md`

Conclusao atual: **nao e possivel marcar 100% das correcoes como aplicadas apenas pelo codigo**.

Motivos objetivos:

1. O proprio mapeamento em `guia-baterias-verificacao-2026-04-22.md` ainda possui itens `DB/amb`, `Manual` e `Nao rev.` em varios blocos (Sessoes 6+ e parte de ERP/fiscal/seguranca).
2. Parte relevante do guia depende de execucao de migration/seed e validacao em SQL Server real (nao apenas leitura estatica de repositorio).
3. O bloco final de seguranca inclui etapas operacionais (rotacao de credenciais, hardening de ambiente, revisao de deploy) que nao podem ser consideradas "aplicadas no codigo" de forma isolada.

Decisao documental desta revisao:

- **Nao remover** `docs/guia-correcao-programador.md` nem `docs/guia-baterias-verificacao-2026-04-22.md` nesta data.
- Manter os dois como rastreabilidade de pendencias abertas ate fechamento completo (codigo + banco + validacao manual).

## Fontes de verdade (projeto + Brain)

No repositório (sempre preferir para diff e CI):

- `docs/guia-correcao-programador.md` — guia técnico completo (FASE 0/1/2, BUG-001+).
- `docs/guia-baterias-verificacao-2026-04-22.md` — mapa por baterias: item do guia × situação no código (reconciliação).
- Este arquivo: `docs/RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md`.

No Obsidian (contexto ampliado / histórico):

- `_Global/PROJETOS/RRTECNOL/GENTE/auditorias/auditoria-unificada-2026-04-21.md`
- `_Global/PROJETOS/RRTECNOL/GENTE/bugs-catalogados/estado-atual.md`
- `_Global/PROJETOS/RRTECNOL/GENTE/seguranca/estado-atual.md`
- `_Global/PROJETOS/RRTECNOL/GENTE/bugs-catalogados/guia-correcao-programador 1.md` (espelho do guia; manter alinhado ao `.md` local)

## Atualização de referência (guia + reconciliação com código)

O `docs/guia-correcao-programador.md` organiza prioridades (FASE 0 → 2), seeds pendentes (`BUG-008/009/010/022/033/066/122..130`), rotas de Configurações/ERP e hardening.

Em **2026-04-22** o conteúido deste relatório foi **reconciliado com o código atual** (grep/leitura de `routes/web.php`, `routes/*.php`, `resources/gente-v3`, `SpaAuthController`, seeders). Itens do guia que já estão atendidos no repositório passam a constar como **corrigidos no código** abaixo; o que ainda aparece no grep permanece como **pendência explícita**.

## Escopo executado

- Build frontend (Vite) — **2026-04-22**: `npm run build` conclui sem erro após correção em `DiariasView.vue` e imports `lucide` em Contratos/Frotas.
- Validação estática de rotas e segurança (`routes/web.php`, includes, middleware).
- Seeds e `SidebarCoverageSeeder` (execução em ambiente com SQL Server acessível).
- Smoke HTTP autenticado (Vite → Laravel) para POSTs críticos de jornada/RH após correções — script `scripts/smoke_api_criacao_modais.py`, **37/37** 2xx em **2026-04-22** (ver seção dedicada no final do relatório).
- Verificação de inclusão de módulos citados no guia (`progressao_funcional`, `afastamentos_v3`, `estagiarios`, autocadastro gestão, `CAST` no ponto).

## Limitações de ambiente (atualizado)

1. **Docker / SQL Server**: dependem da máquina; com `docker compose up` e `DB_HOST` resolvendo o container, `php artisan db:seed` e smoke HTTP funcionam. Sem banco, qualquer bootstrap que execute SQL falha (`Login timeout`).
2. **`routes/pesquisa.php` no carregamento**: ao ser `require` dentro do grupo autenticado (`routes/web.php` ~L1198), o arquivo executa `Schema::hasTable` / `Schema::create` no **parse** do arquivo. Isso exige conexão com o SQL Server durante `php artisan route:list`, `config:cache`, etc., quando essas rotas são carregadas — comportamento ainda relevante para ambientes sem DB.
3. **Mobile**: `routes/ponto_app.php` é carregado por `web.php` no prefixo `api/v3` com middleware `web` apenas (JWT).

---

## Reconciliação guia ↔ código (amostra FASE 0 — 2026-04-22)

Referência: `docs/guia-correcao-programador.md` (início FASE 0). Status verificado no repositório atual:

| Tema no guia | Evidência no código | Status |
|--------------|---------------------|--------|
| Ponto — `DATE()` inválido no SQL Server | `routes/web.php` usa `CAST(REGISTRO_DATA_HORA AS DATE)` (~L5179) | **Corrigido** |
| Dashboard — avatar sem navegação | `DashboardLayout.vue`: `topbar-avatar` e `sidebar-profile` com `@click="$router.push('/meu-perfil')"` | **Corrigido** |
| Cargos — `CARGO_CBO` inexistente | `app/Models/Cargo.php`: sem `CARGO_CBO` no model (grep) | **Corrigido** (no estado atual) |
| Progressões — `progressao_funcional.php` não incluído | `require __DIR__ . '/progressao_funcional.php'` em `web.php` (~L1211, ~L1252) | **Corrigido** |
| Estagiários — grupo interno quebrando prefix | `routes/estagiarios.php`: sem `Route::middleware(...)->group` envolvendo as rotas | **Corrigido** |
| Autocadastro gestão — rotas inexistentes | `web.php` dentro de `api/v3` + `auth`: `GET /autocadastro/pendentes`, `POST /autocadastro/gerar-link` (~L815–827) | **Corrigido** |
| Afastamentos — POST inexistente | `require __DIR__ . '/afastamentos_v3.php'` (~L1212) + arquivo presente | **Corrigido** |
| Escala de trabalho — GET inexistente | Rota `GET /escala-trabalho` definida no grupo autenticado (~L829+) | **Corrigido** |
| Hora extra — GET/POST 500 por `Request` facade | `routes/hora_extra.php`: assinaturas `\Illuminate\Http\Request` | **Corrigido** |
| Plantões extras (sidebar) — POST 500 / colunas | `routes/web.php` + `routes/plantoes_sobreaviso.php`: insert/listagem schema-aware | **Corrigido** |
| Sobreaviso — acionamento 500 / calendário | `routes/web.php`: `Carbon::endOfMonth()`, POST schema-aware | **Corrigido** |

O guia contém **dezenas** de itens adicionais (BUG-052+, Configurações, ERP, eSocial, etc.). Este relatório não substitui o guia linha a linha: use o guia como backlog e este arquivo como **estado auditado** + links para as seções técnicas já corrigidas (escalas, substituições, hora extra, plantões, etc.).

---

## Resultado consolidado (técnico) — revisão 2026-04-22

### A) Itens com evidência de correção (mantidos / atualizados)

1. **`ChecarAcessoUsuarioUnidade` sem `dd()` ativo**
   - status: **corrigido** (somente `dd` comentado encontrado no app).

2. **`SpaAuthController` e vínculo admin para testes**
   - status: **corrigido para fluxo real em não-produção**
   - evidência: garantia de vínculo `USUARIO` admin → `FUNCIONARIO` / lotação (`ensureAdminFuncionarioVinculo` e afins); checagens por login `admin` aparecem de forma **explicitamente delimitada** (não é o antipadrão `$isAdmin = true` global no `web.php`).

3. **Padrão `$isAdmin = true` no `web.php`**
   - status: **corrigido** (padrão não encontrado).

4. **Login local destravado para teste técnico**
   - status: **corrigido**
   - evidência:
     - proxy do Vite ajustado para backend correto (`8081`);
     - fallback de reCAPTCHA aplicado em dev/local;
     - limpeza de BOM em rotas que quebravam sessão/cookie.

5. **Sidebar alinhado com rotas válidas**
   - status: **corrigido parcialmente**
   - evidência:
     - adicionado atalho de `Notificações` no menu;
     - removida aba órfã `Controle de Frequência` (`/frequencia`) sem rota correspondente.

### B) Itens com evidência de não corrigido (confirmado no código em 2026-04-22)

1. **Blocos `api/v3` apenas com `middleware(['web'])` (sem `auth`)**
   - status: **não corrigido** (auditoria rota a rota ainda necessária)
   - evidência: `Route::prefix('api/v3')->middleware(['web'])` em `routes/web.php` (autocadastro público e blocos dedicados). O grupo **novo** com `['web']` que carrega `ponto_app.php` é **intencional** (login mobile por JWT, sem sessão Laravel). Demais URIs nesse prefixo sem `auth` exigem revisão de segurança antes de produção.

2. **Fluxo reCAPTCHA em produção**
   - status: **parcialmente endereçado em dev**
   - evidência: fallback em ambiente local; produção exige chave válida e validação ponta a ponta.

### B2) Itens corrigidos nesta revisão (2026-04-22, pós-reconciliação)

1. **Build frontend (`DiariasView.vue`)** — removida declaração duplicada de `tzOffset` / `hoje`; `npm run build` OK.
2. **Ícones Lucide (Contratos / Frotas)** — `import { createIcons, icons } from 'lucide'` + `createIcons({ icons })` (pacote `lucide` ESM não exporta default).
3. **Duplicidade `Route::prefix('api/auth')`** — removido o segundo grupo redundante (`SpaAuthController`); mantido o grupo com throttle e `LOGIN_ATTEMPTS`; nome `api.auth.logout` aplicado ao `POST /logout` do grupo principal; após `Auth::login` chama-se `SpaAuthController::applyDevAdminFuncionarioVinculo` para manter vínculo admin→funcionário em **não produção**.
4. **App mobile de ponto** — `require __DIR__ . '/ponto_app.php'` em grupo `Route::prefix('api/v3')->middleware(['web'])` **antes** do grupo autenticado; em `ponto_app.php`: `use` de `Request`, `DB`, `Route`.

### C) Itens bloqueados / não validados

1. **Seed / Artisan sem banco**
   - status: **depende de ambiente**
   - com SQL Server acessível: `docker compose exec app php artisan db:seed --class=SidebarCoverageSeeder` é o caminho validado.

2. **Smoke HTTP de modais (API apenas)**
   - status: **validado em 2026-04-22** contra `BASE` descoberto automaticamente (`5173` ou `8081`); ver seção [Validação automática (smoke HTTP) — 2026-04-22](#validação-automática-smoke-http--2026-04-22).
   - não substitui teste manual de Vue, uploads `multipart/form-data`, regras por perfil nem reCAPTCHA de produção.

3. **Mobile de ponto — roteamento**
   - status: **corrigido em 2026-04-22**
   - evidência: `routes/web.php` inclui `ponto_app.php` no prefixo `api/v3` com middleware `web` apenas (JWT no app; `/ponto/app/login` sem `auth` de sessão).

---

## Correções de seed iniciadas (cobertura de abas)

Com base no `docs/guia-correcao-programador.md` (e no espelho do Brain), foi iniciada a trilha de massa mínima para cobrir abas críticas do sidebar:

1. **Bloqueio estrutural do seeder corrigido**
   - ajuste em `VinculosPMSLzSeeder` para só gravar `VINCULO_ATIVO` quando a coluna existir.

2. **Seeder de cobertura criado**
   - arquivo: `database/seeders/SidebarCoverageSeeder.php`
   - objetivo: garantir dados mínimos para módulos críticos com dependência de seed.

3. **`DatabaseSeeder` atualizado**
   - inclusão de:
     - `PcaspSeeder`
     - `SagresDeParaSeeder`
     - `SidebarCoverageSeeder`

4. **Escopo da cobertura mínima (seed)**
   - vínculo funcional do admin;
   - `PONTO_CONFIG_FUNCIONARIO`;
   - `REGISTRO_PONTO`;
   - `BANCO_HORAS` (com criação da tabela se ausente);
   - `TURNO` (siglas base para escala) + `ESCALA` / `DETALHE_ESCALA` / `DETALHE_ESCALA_ITEM` + `SUBSTITUICAO_ESCALA` (múltiplas competências/setores);
   - `HORA_EXTRA` e `PLANTAO_EXTRA` (massa mínima para telas de jornada);
   - `DECLARACAO`;
   - `FOLHA`/`DETALHE_FOLHA` para competências de 2026;
   - `ORCAMENTO_PPA/PROGRAMA/ACAO/LOA`;
   - `CONTA_BANCARIA`/`MOVIMENTACAO_BANCARIA`;
   - `RECEITA_LANCAMENTO`.

5. **Correção de vínculo técnico para login admin (fluxo real)**
   - `SpaAuthController` passou a garantir vínculo `USUARIO(admin) -> FUNCIONARIO` em ambiente não produtivo;
   - fallback aplicado também em endpoints críticos (`/meus-holerites`, `/perfil`, `/ponto`, `/atestados`, `/ferias`, `/plantoes-extras`, `/sobreaviso`) para evitar bloqueio por ausência de vínculo.

6. **Varredura UTF-8 em mensagens de API**
   - corrigidos textos corrompidos (`NÃ£o`, `FuncionÃ¡rio`, `SolicitaÃ§Ã£o`, etc.) nas respostas JSON de módulos críticos;
   - foco em mensagens exibidas diretamente no frontend durante testes de fluxo de produção.

---

## Status atual (resumo executivo)

- **Grande parte dos bloqueadores FASE 0 descritos no início do guia** (ponto `CAST`, progressão, estagiários, afastamentos, escala-trabalho, autocadastro gestão, CARGO_CBO, avatar) está **atendida no código atual**, conforme tabela de reconciliação acima.
- **Permanecem riscos reais de release**: auditoria de rotas `api/v3` só com `web` (exceto blocos intencionais: autocadastro público, `ponto_app`), reCAPTCHA em produção, seeds ERP/ORÇAMENTO, e segredos/credenciais versionados.
- **Seeds**: `SidebarCoverageSeeder` cobre bem jornada/RH crítica; itens BUG-122..130 e módulos fiscais/ERP exigem validação caso a caso (guia completo).

## Pendências críticas (prioridade)

1. Revisar e documentar cada rota nos blocos `Route::prefix('api/v3')->middleware(['web'])` sem `auth` (autocadastro público; grupo `ponto_app` intencional — demais: sem vazamento).
2. Validação manual final de reCAPTCHA em ambiente com chave de produção.
3. Executar `php artisan db:seed --class=SidebarCoverageSeeder` (e `DatabaseSeeder`) sempre que o banco for recriado; validar módulos ERP/ORÇAMENTO do guia separadamente.
4. Credenciais/artefatos sensíveis versionados (conforme auditoria de segurança no Brain).

---

## Checklist único de validação manual

Para itens do guia fora desta lista (BUG-052+, ERP, eSocial, Configurações), usar `docs/guia-correcao-programador.md` como lista mestra e registrar aqui apenas o resultado de cada sprint.

### Automação já executada (não repetir como “falta de API”)

- [x] Smoke `python3 scripts/smoke_api_criacao_modais.py` — **37/37** 2xx em **2026-04-22** (detalhe na seção [Validação automática (smoke HTTP) — 2026-04-22](#validação-automática-smoke-http--2026-04-22)); repetir após mudanças em rotas ou seeds.

### Ambiente
- [ ] Docker ativo
- [ ] SQL Server container acessível
- [ ] `php artisan db:seed --class=DatabaseSeeder` roda sem timeout e sem erro estrutural

### Frontend web
- [ ] `npm run build` conclui sem erro *(validado na máquina de dev em 2026-04-22)*
- [ ] Tela Diárias abre sem erro de compilação
- [ ] Contratos/Frotas sem erro de `lucide default export` *(build passou após `createIcons` + `icons`)*

### API/Segurança
- [ ] Rotas sensíveis em `/api/v3` exigem login
- [ ] Sem duplicidade funcional em `/api/auth` *(removido segundo `Route::prefix('api/auth')` duplicado em 2026-04-22)*
- [ ] Sem credenciais/artefatos sensíveis versionados em arquivos críticos

### Login/reCAPTCHA
- [ ] Sem erro `No reCAPTCHA clients exist` no login local
- [ ] Login funciona com e sem chave (conforme ambiente)

### Mobile
- [ ] Endpoint `/api/v3/ponto/app/login` responde (200/401, nunca 404)
- [ ] Endpoint `/api/v3/ponto/app/registrar` responde com token válido
- [ ] Fluxo de batida com câmera/localização funcional

### Sidebar/Admin
- [ ] Sidebar do admin exibe todas as seções esperadas (RH, Financeiro/Folha, Administrativo, ERP/Fiscal, Configurações).
- [ ] Nenhuma aba do sidebar aponta para rota inexistente.
- [ ] Rotas de menu críticas carregam sem 403 indevido por perfil em ambiente local de teste.

---

## Atualização técnica — Escala de Trabalho (2026-04-21)

### Status atual
- **Corrigido em código**: modal de novo registro da `Escala de Trabalho` volta a receber lista real de funcionários (`/api/v3/escala-trabalho`).
- **Corrigido em código**: persistência da criação/edição diária da escala (`POST /api/v3/escala-trabalho`) agora grava em `ESCALA`, `DETALHE_ESCALA` e `DETALHE_ESCALA_ITEM`.
- **Corrigido em código**: criação de escala (`POST /api/v3/escalas`) aceita `mes/ano`, devolve `escala_id`/`competencia` e pré-popula funcionários do setor.
- **Corrigido em seed**: `SidebarCoverageSeeder` agora garante turnos-base (`M/V/N/I/F/SO/AT`) e itens diários em `DETALHE_ESCALA_ITEM`.

### Fluxo/Implementação
1. Backend da tela de Escala agora retorna:
   - `funcionarios` (opções do select),
   - `setores` (filtro),
   - `escala` agregada por funcionário com mapa de `dias`.
2. Salvamento da escala:
   - resolve competência pela data,
   - cria escala do mês se não existir,
   - cria vínculo em `DETALHE_ESCALA`,
   - grava/atualiza turno por dia em `DETALHE_ESCALA_ITEM`.
3. Seed:
   - garante turnos existentes para evitar falha de sigla no POST,
   - injeta massa de escala mensal para evitar telas vazias em módulos correlatos.

### Checklist
- [ ] Abrir `Escala de Trabalho` e confirmar select de funcionário preenchido no modal.
- [ ] Criar novo registro (funcionário + data + turno) e recarregar tela para validar persistência.
- [ ] Criar uma nova escala em `Escalas Médicas` e validar se já nasce com profissionais (sem grade vazia).
- [ ] Abrir `Substituições de Plantão` e validar lista de funcionários após selecionar escala recém-criada.

### Riscos/Não fazer
- Não remover os turnos seedados (`M/V/N/I/F/SO/AT`) sem ajustar o mapeamento de siglas no frontend.
- Não voltar `POST /api/v3/escala-trabalho` para stub (`ok: true`) ou volta o problema de “salva e some”.
- Não criar escala sem vincular `DETALHE_ESCALA`, pois isso quebra modais dependentes de profissionais.

---

## Atualização técnica — Escalas Médicas e varredura de sidebar (2026-04-22)

### Status atual
- **Corrigido**: modal “Nova Escala” com setores em branco (mapeamento frontend ajustado para aceitar `id/nome` e `SETOR_ID/SETOR_NOME`).
- **Corrigido**: `POST /api/v3/escalas` com erro `Invalid column name 'ESCALA_SITUACAO'` por rota duplicada antiga.
- **Corrigido**: `GET /api/v3/escalas` e `GET /api/v3/escalas/{id}` que estavam em `500` por dependência de colunas não existentes no schema atual.
- **Corrigido (hotfix complementar)**: `POST /api/v3/sobreaviso/acionamento` robustecido para ambientes com schema heterogêneo (`ACIONAMENTO` ou `ACIONAMENTO_SOBREAVISO`), aceitando payload em `snake_case` e `camelCase`, com retorno padronizado (`acionamento`) para atualização imediata da UI.
- **Corrigido**: remoção de dependência de `cal_days_in_month()` no backend (ambientes sem extensão `calendar`) para evitar fallback indevido/erro silencioso em consultas por competência mensal.

### Fluxo/Implementação
1. `resources/gente-v3/src/views/escala/MatrizEscalaView.vue`
   - normalização de retorno de setores para evitar `<option>` vazia.
2. `routes/escala_saude.php`
   - rota duplicada de criação de escala atualizada para schema-aware e resposta padrão (`escala_id`, `competencia`).
3. `routes/web.php`
   - versão final de `/escalas` e `/escalas/{id}` reescrita em query builder (sem dependência de campos ausentes como `HISTORICO_ESCALA_ULTIMO` e `FUNCAO_ID`);
   - ajustes defensivos no módulo sobreaviso para ausência/variação de tabela (`ACIONAMENTO` x `ACIONAMENTO_SOBREAVISO`);
   - cálculo de fim de competência migrado para `Carbon::endOfMonth()` (sem dependência de extensão PHP opcional).
4. Validação de fluxo (smoke via HTTP autenticado):
   - `POST /api/v3/sobreaviso/acionamento` => `201`;
   - `POST /api/v3/substituicoes` => `201`;
   - `POST /api/v3/escalas` => `201`;
   - `POST /api/v3/escala-trabalho` => `201`;
   - `POST /api/v3/afastamentos` => `201`;
   - `POST /api/v3/ferias` => `201`;
   - `POST /api/v3/ponto/registro` => `200` (com persistência de protocolo/registro).

### Checklist
- [ ] Abrir modal de “Nova Escala” e confirmar setores com nomes corretos.
- [ ] Criar escala e validar que aparece no seletor sem recarregar.
- [ ] Selecionar escala criada e validar carregamento da grade sem erro.
- [ ] Registrar acionamento em Sobreaviso e validar retorno/atualização imediata na lista.
- [ ] Repetir acionamento usando payload de horário com variação de chave (`hora_ini/hora_fim` e `horaIni/horaFim`) e confirmar ausência de `500`.

### Riscos/Não fazer
- Não reintroduzir respostas com chaves diferentes do frontend (`ESCALA_ID`, `ESCALA_COMPETENCIA`) sem adaptar `MatrizEscalaView`.
- Não deixar múltiplas rotas com mesmo path e payload divergente para `/escalas`.
- Não assumir existência de tabela/coluna sem `Schema::hasTable`/`Schema::hasColumn` no ambiente atual.

---

## Atualização técnica — Substituições e cobertura de seeds (2026-04-22)

### Status atual
- **Corrigido**: modal de `Substituições` passa a receber escalas com payload normalizado (frontend robusto para formatos `ESCALA_ID`/`escala_id`).
- **Corrigido**: endpoint `GET /api/v3/substituicoes` refeito para schema real (`SUBSTITUICAO_ESCALA` sem `DETALHE_ESCALA_ITEM_ID`), eliminando cards quebrados com `Invalid Date`/`?`.
- **Corrigido**: inclusão de `POST /api/v3/substituicoes` e `PUT /api/v3/substituicoes/{id}` para fluxo real de solicitação/atualização.
- **Corrigido**: seed de cobertura expandido para gerar massa útil de escalas médicas + detalhes + itens + substituições em múltiplas competências.

### Fluxo/Implementação
1. `SidebarCoverageSeeder`:
   - cria escalas para mês atual, mês anterior e próximo mês;
   - distribui funcionários em vários setores;
   - gera itens de escala por dia útil;
   - cria substituições consistentes para cada escala seedada.
2. `SubstituicoesView`:
   - normalização de payload de escalas e substituições;
   - tratamento de datas inválidas sem quebrar visual.
3. API de substituições:
   - leitura por `Query Builder` com joins compatíveis ao banco atual;
   - persistência de nova substituição com fallback de colunas opcionais.

### Checklist
- [ ] Em `Substituições > Nova Substituição`, confirmar lista de escalas preenchida.
- [ ] Selecionar escala e confirmar lista de profissionais (ausente/substituto) preenchida.
- [ ] Enviar nova substituição e validar se o card aparece sem `Invalid Date`.
- [ ] Validar transição de status (aprovar/recusar) sem erro no console.

### Riscos/Não fazer
- Não usar o model `SubstituicaoEscala` assumindo campos inexistentes no banco atual.
- Não reduzir o seed para uma única escala/mês, pois volta a “falsa cobertura” do fluxo diário.

---

## Atualização técnica — Hora Extra/Plantão Extra (2026-04-22)

### Status atual
- **Corrigido**: `GET /api/v3/hora-extra` com erro `500` (`Undefined property: Illuminate\Support\Facades\Request::$competencia`) causado por tipagem incorreta do request no arquivo de rotas.
- **Corrigido**: endpoints do módulo de plantão extra adaptados para schema real do banco (`PLANTAO_DATA`, `PLANTAO_HORAS`, `PLANTAO_STATUS`, ausência de `UNIDADE_ID`) para evitar novos `500`.
- **Cobertura de seed ampliada**: `SidebarCoverageSeeder` agora cria massa de `HORA_EXTRA` e `PLANTAO_EXTRA` para não deixar as telas vazias durante validação funcional.

### Fluxo/Implementação
1. `routes/hora_extra.php`
   - atualização de todas as assinaturas para `\Illuminate\Http\Request`;
   - `GET/POST /plantao-extra` tornados schema-aware (colunas opcionais e nomes alternativos).
2. `database/seeders/SidebarCoverageSeeder.php`
   - nova rotina `seedHoraExtraEPlantao` para popular os módulos com registros reais no mês atual.
3. Validação executada:
   - `GET /api/v3/hora-extra?competencia=2026-04` => `200` com lista populada;
   - `GET /api/v3/plantao-extra?competencia=2026-04` => `200` com lista populada.

### Checklist
- [ ] Abrir aba `Hora Extra` e confirmar que a lista carrega sem tela em branco.
- [ ] Validar filtro por competência e por secretaria sem erro no console/network.
- [ ] Abrir fluxo de `Plantão Extra` e confirmar retorno `200` na listagem.

### Riscos/Não fazer
- Não voltar a usar `Request` sem namespace completo em arquivos de rota sem `use`.
- Não assumir colunas legadas (`UNIDADE_ID`, `DATA_PLANTAO`, `TOTAL_HORAS`) em `PLANTAO_EXTRA` sem checagem de schema.

---

## Atualização técnica — Plantões Extras (2026-04-22)

### Status atual
- **Corrigido**: `POST /api/v3/plantoes-extras` que retornava `500` ao tentar inserir colunas inexistentes no schema atual (`PLANTAO_SETOR`, `PLANTAO_HORA_INI`, `PLANTAO_HORA_FIM`, `PLANTAO_TIPO`, `PLANTAO_JUSTIFICATIVA`).
- **Corrigido**: persistência do novo plantão em **Meus Plantões** (lista após `GET`); o **Histórico** na UI (`PlantoesExtrasView.vue`) só agrega registros com status **aprovado** — pendentes não aparecem no gráfico por desenho atual, não por falta de gravação.
- **Corrigido**: listagem `GET /api/v3/plantoes-extras` normalizada para cenários de schema legada/atual.

### Fluxo/Implementação
1. `routes/web.php`
   - `GET /plantoes-extras`: leitura robusta com fallback de colunas (`PLANTAO_DATA`/`DATA_PLANTAO`, `PLANTAO_STATUS`/`STATUS`).
   - `POST /plantoes-extras`: insert schema-aware, cálculo de duração por `horaIni/horaFim`, persistência apenas em colunas existentes.
2. `routes/plantoes_sobreaviso.php`
   - rotas espelhadas com mesma proteção para evitar regressão por arquivo duplicado.
3. Varredura pós-correção (smoke):
   - `POST /api/v3/plantoes-extras` => `201`;
   - `GET /api/v3/plantoes-extras` => `200`;
   - `POST /api/v3/sobreaviso/acionamento` => `201`;
   - `POST /api/v3/hora-extra` => `200`;
   - `POST /api/v3/plantao-extra` => `200`;
   - `POST /api/v3/substituicoes` => `201`.

### Checklist
- [ ] Em `Plantões Extras > Solicitar`, enviar novo plantão e confirmar retorno sem `500`.
- [ ] Voltar para `Meus Plantões` e validar o novo registro.
- [ ] Abrir `Histórico` e confirmar atualização coerente após aprovação/fluxo.
- [ ] Validar no Network ausência de `500` nos POSTs de jornada (plantões/sobreaviso/hora extra).

### Riscos/Não fazer
- Não fixar inserts de `PLANTAO_EXTRA` com colunas rígidas sem checagem do schema.
- Não manter duas implementações divergentes para o mesmo endpoint (`web.php` x rota extraída).

---

## Validação automática (smoke HTTP) — 2026-04-22

Script: `scripts/smoke_api_criacao_modais.py`. Executar na raiz do app Laravel (pasta `gente/gente`):

```bash
python3 scripts/smoke_api_criacao_modais.py
# ou: python3 scripts/smoke_api_criacao_modais.py --base http://127.0.0.1:8081
```

Pré-requisitos: Vite (`5173`) ou backend (`8081`) respondendo em `/csrf-cookie`; banco SQL Server com sessão válida; usuário `admin` / `admin123` (ambiente de desenvolvimento). Saída: `docs/smoke-api-criacao-RESULTADO.json` + resumo no terminal.

**Última execução registrada:** `37/37` respostas HTTP **2xx** (sem falhas). Isso comprova **apenas** que o backend aceita os payloads JSON usados pelo script após `POST /api/auth/login` (cookie de sessão + `X-XSRF-TOKEN`), e que os GETs de listagem correspondentes retornam **200**. Não prova layout, validação de formulário no browser, sidebars por papel, nem todos os modais do produto.

### Endpoints exercitados com sucesso (POST criação / registro)

| Chamada | Status típico | Observação |
|---------|----------------|------------|
| `POST /api/v3/ponto/registro` | 200 | Batida de teste com `funcionario_id` resolvido da busca. |
| `POST /api/v3/escala-trabalho` | 201 | Item diário de escala administrativa. |
| `POST /api/v3/escalas` | 201 | Nova escala médica (mês corrente). |
| `POST /api/v3/substituicoes` | 201 | Substituição entre servidores retornados pela busca. |
| `POST /api/v3/ferias` | 201 | Agendamento de férias. |
| `POST /api/v3/afastamentos` | 201 | Novo afastamento. |
| `POST /api/v3/organograma/setor` | 201 | Novo setor (nome smoke + sigla). |
| `POST /api/v3/declaracoes` | 200 | Declaração / requerimento. |
| `POST /api/v3/avaliacoes` | 201 | Avaliação de desempenho. |
| `POST /api/v3/plantoes-extras` | 201 | Plantão extra. |
| `POST /api/v3/sobreaviso/acionamento` | 201 | Acionamento de sobreaviso. |
| `POST /api/v3/hora-extra` | 200 | Lançamento de hora extra. |
| `POST /api/v3/plantao-extra` | 200 | Plantão extra (módulo financeiro/jornada). |
| `POST /api/v3/diarias` | 200 | Solicitação de diárias. |
| `POST /api/v3/estagiarios` | 200 | Cadastro de estagiário + contrato (`cpf` **único por execução** no script; `setor_id` do primeiro setor da lista). |
| `POST /api/v3/seguranca/epis` | 200 | Cadastro mínimo de EPI. |
| `POST /api/v3/seguranca/incidentes` | 200 | Incidente de segurança. |
| `POST /api/v3/medicina/agendar` | 201 | Agendamento com `tipo_exame` (backend mapeia para `AGENDAMENTO_TIPO`; evita `NULL` no insert). |

### GETs de leitura (sidebar / listas) validados no mesmo run

`GET` com **200**: `/api/v3/ponto`, `/banco-horas`, `/ferias`, `/afastamentos`, `/organograma`, `/escala-trabalho`, `/escalas`, `/substituicoes`, `/hora-extra`, `/plantao-extra`, `/plantoes-extras`, `/sobreaviso`, `/declaracoes`, `/avaliacoes`, `/diarias`, `/estagiarios`.

### Pré-requisitos de dados usados pelo script

- `GET /api/v3/setores` — primeiro `id` como `setor_id` padrão.
- `GET /api/v3/servidores/buscar?q=a` — dois primeiros `id` como substituto/titular e afins.
- `GET /api/v3/escalas` — primeira escala para vínculos que exigem `escala_id`.

### Correções de backend ligadas a este smoke

- **`POST /api/v3/medicina/agendar`**: coluna `AGENDAMENTO_TIPO` passou a ser preenchida a partir de `tipo_exame` (com fallback), alinhado ao payload do frontend (`MedicinaTrabalhoView.vue`).

### O que continua só no teste manual (checklist)

Use em conjunto com [Checklist único de validação manual](#checklist-único-de-validação-manual) e com os checklists por módulo mais abaixo.

- Abrir **cada modal no Vue**, preencher todos os campos (incluindo anexos), conferir máscaras, mensagens de erro de validação client-side e feedback visual após sucesso.
- Perfis **não admin** (servidor, gestor de setor, RH restrito): permissões e itens de **sidebar** diferentes do script (o smoke usa só `admin`).
- **Build do SPA**: o smoke não compila o Vue; após alterações em views, rodar `npm run build` na pasta `resources/gente-v3`.
- **Uploads** (`multipart`), assinaturas digitais, exportações PDF/Excel e integrações externas.
- **reCAPTCHA** e headers de produção (CORS, cookies `Secure`).
- Modais/POSTs **não incluídos** no script (exemplos: comunicados internos, verbas adicionais, PSS, treinamentos/inscrição, folha fechada, ERP fiscal completo, configurações sensíveis) — expandir o script ou testar manualmente conforme `docs/guia-correcao-programador.md`.
- Navegação real: **transição entre rotas**, estado do **pinia/vue-router**, e menus colapsados/expandidos no layout.

---

## Atualização 2026-04-24 (varredura rotas modulares: sucesso falso)

Aplicado novo lote de endurecimento em rotas fora de `web.php`, removendo cenários de `ok: true` sem validação de alteração real e removendo fallback de sucesso fictício.

### Correções aplicadas

- `routes/consignacao.php`
  - `PATCH /consignacao/{id}/status`, `PATCH /consignacao/{id}/autorizar` e `PATCH /consignacao/{id}/rejeitar` agora validam `affected rows` no contrato principal.
  - Se não houver contrato alvo para atualização, retorna `404` com mensagem explícita (em vez de sucesso silencioso).
- `routes/ouvidoria_admin.php`
  - `PATCH /ouvidoria/{id}/responder` agora valida atualização efetiva e retorna `404` quando o protocolo/id não existe.
- `routes/acumulacao.php`
  - `PATCH /acumulacao/{id}/analisar` agora valida atualização efetiva e retorna `404` quando a declaração não existe.
- `routes/comunicados.php`
  - `POST /comunicados`: removido retorno de sucesso em “modo demo” quando `COMUNICADO` não existe; agora retorna erro explícito.
  - `PUT /comunicados/{id}` e `DELETE /comunicados/{id}`: validação de alteração/remoção real com retorno `404` quando não houver registro.
  - Quando a tabela `COMUNICADO` não existir, os endpoints retornam erro explícito (sem mascarar com sucesso).

### Validação técnica do lote

- Sintaxe PHP validada com `php -l` em todos os arquivos alterados.
- Sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 2: rotas financeiras/rh)

Nova rodada de endurecimento aplicada em rotas modulares adicionais para impedir `ok: true` sem alteração real:

- `routes/folha.php`
  - `POST /folhas/{id}/confirmar`: retorna `404` quando a folha não existe.
  - `DELETE /folhas/{id}/lancamentos/{lancId}`: valida remoção efetiva; retorna `404` quando não houver lançamento alvo.
- `routes/motor.php`
  - `PATCH /vinculos/{id}`, `PUT /vinculos/{id}`, `DELETE /vinculos/{id}`: agora retornam `404` quando o vínculo não existe (ou sem alteração).
- `routes/patrimonio.php`
  - `PUT /patrimonio/bens/{id}`: valida `affected rows`; retorna `404` para bem inexistente/sem alteração.
- `routes/pss.php`
  - `POST /pss/editais/{id}/publicar` e `PATCH /pss/candidatos/{id}/convocar`: agora retornam `404` quando o alvo não existe/sem alteração.
- `routes/verba_indenizatoria.php`
  - `POST /verba-indenizatoria/tipos` (modo edição): retorna `404` quando tipo não existe/sem alteração.
  - `PATCH /verba-indenizatoria/{id}/status`: retorna `404` quando lançamento não existe/sem alteração.
  - `DELETE /verba-indenizatoria/{id}`: valida exclusão efetiva e retorna `404` quando aplicável.

Validação do lote 2:

- `php -l` sem erros em todos os arquivos alterados.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 3: operações de status e vínculo)

Nova varredura aplicada em rotas operacionais onde havia atualização com retorno de sucesso sem garantir alteração real:

- `routes/hora_extra.php`
  - `PATCH /hora-extra/{id}/status` agora valida `affected rows` e retorna `404` para registro inexistente/sem alteração.
- `routes/compras.php`
  - `PATCH /compras/pedidos/{id}/vincular` agora valida atualização efetiva e retorna `404` quando o pedido não existir/sem alteração.
- `routes/atestados.php`
  - `PATCH /atestados/{id}/validar` agora retorna `404` quando não houver atestado alvo para alteração.
- `routes/esocial.php`
  - `PATCH /esocial/eventos/{id}` agora valida atualização efetiva e retorna `404` quando o evento não existir/sem alteração.

Validação do lote 3:

- `php -l` sem erros nos arquivos alterados.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 4: progressão e frota)

Nova rodada aplicada em pontos ainda sensíveis de atualização silenciosa:

- `routes/progressao_funcional.php`
  - `POST /progressao-funcional/aplicar/{id}`: atualização de `FUNCIONARIO` agora valida `affected rows`; retorna `404` quando não houver alteração alvo.
  - `POST /progressao-funcional/promover/{id}`: mesma validação de atualização efetiva com `404` em ausência de alteração.
  - `DELETE /progressao-funcional/tabela-salarial/{id}`: valida exclusão efetiva; retorna `404` quando o registro não existir.
- `routes/frotas.php`
  - `POST /frotas/saidas`: dentro da transação, atualização de status do veículo agora é validada; falha explícita quando não houver linha atualizada.
  - `PATCH /frotas/saidas/{id}/retorno`: valida atualização da saída e do veículo dentro da transação; em caso de inconsistência, gera erro explícito e rollback.

Validação do lote 4:

- `php -l` sem erros nos arquivos alterados.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 5: sobreaviso e férias)

Ajustes adicionais para remover sucesso fictício e melhorar semântica de erro em operações de escrita:

- `routes/plantoes_sobreaviso.php`
  - `POST /sobreaviso/acionamento`: removido fallback de sucesso em exceção (`"modo demo"`). Agora falhas reais retornam `500` com erro explícito.
- `routes/ferias_v3.php`
  - `PUT /ferias/{id}`:
    - troca de `findOrFail` por verificação explícita (`404` quando não encontrado);
    - retorna `422` quando nenhuma alteração válida foi enviada (evita “sucesso vazio”).
  - `DELETE /ferias/{id}`:
    - troca de `findOrFail` por verificação explícita (`404` quando não encontrado);
    - valida retorno do `delete()` para garantir consistência da resposta.

Validação do lote 5:

- `php -l` sem erros nos arquivos alterados.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 6: correção regressão substituições + sobreaviso)

Correções direcionadas aos incidentes reportados em teste manual:

- `PUT /api/v3/substituicoes/{id}` com `500` na aprovação
  - Arquivo: `routes/web.php`
  - Ajustado fluxo de histórico para ser resiliente a variações de schema da tabela `SUBSTITUICAO_ESCALA_HISTORICO`.
  - Removida dependência rígida de coluna/chave fixa (`ID`) e de conjunto exato de colunas; agora o payload histórico é montado de forma schema-aware.
  - Em caso de falha no registro do histórico, a decisão principal de status não é perdida (com log explícito e retorno de contexto `historico_ok/historico_erro`).

- Sobreaviso: acionamento sem cálculo e perda visual após `F5`
  - Arquivos: `routes/plantoes_sobreaviso.php`, `resources/gente-v3/src/views/ponto/EscalaSobreavisoView.vue`
  - Backend (`POST /sobreaviso/acionamento`):
    - aceitação de `horaIni/horaFim` e `hora_ini/hora_fim`;
    - cálculo de duração (incluindo virada de dia);
    - cálculo de valor de hora extra (regra padrão atual) e persistência quando colunas existirem;
    - retorno do objeto `acionamento` completo para consumo do frontend;
    - removido fallback de sucesso fictício em exceção.
  - Frontend (`EscalaSobreavisoView.vue`):
    - removido uso de mocks como fallback para ausência/erro de API (evita “sumir após F5” por substituição de estado);
    - após salvar acionamento, a tela recarrega da API (`fetchDados`) para refletir dados persistidos;
    - incluído `fim` no mapeamento de sobreaviso para consistência do calendário.

Validação do lote 6:

- `php -l` sem erros em `routes/web.php` e `routes/plantoes_sobreaviso.php`.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 7: teia de comunicação substituições)

Aprimorado fluxo ponta-a-ponta de substituições para refletir decisões, histórico e comunicação entre envolvidos:

- `routes/web.php` — notificações persistentes
  - substituído stub de `/notificacoes` por leitura real da tabela `NOTIFICACAO` (quando disponível);
  - `PUT /notificacoes/{id}/lida` e `PUT /notificacoes/lidas` agora marcam leitura em banco com validação de existência.

- `routes/web.php` — substituições integradas com histórico/notificação
  - `GET /substituicoes` passou a enriquecer `historico` com nomes dos envolvidos, data, turno e setor;
  - `POST /substituicoes` gera notificações para envolvidos diretos (solicitante/substituto), quando houver vínculo de usuário;
  - `PUT /substituicoes/{id}` gera notificações de decisão (aprovação/recusa) para envolvidos, incluindo justificativa quando recusada.

- `resources/gente-v3/src/views/escala/SubstituicoesView.vue`
  - adicionada navegação em duas abas logo abaixo do subtítulo: `Substituições` e `Histórico`;
  - nova visualização de histórico com filtros `Todos`, `Aprovados` e `Recusados`;
  - histórico consumido diretamente do payload `historico` da API.

Validação do lote 7:

- `php -l` sem erros em `routes/web.php`.
- sem erros de lint nos arquivos alterados.

### Atualização 2026-04-24 (lote 8: sobreaviso produção)

Correção completa do fluxo de `Sobreaviso` para compatibilidade com schema real e eliminação de rota conflitante:

- `routes/plantoes_sobreaviso.php`
  - `GET /sobreaviso` agora aceita schema legado (`SOBREAVISO`) e schema atual (`ESCALA_SOBREAVISO`), com mapeamento único para o frontend;
  - adicionada validação de competência (`YYYY-MM`);
  - `acionamentos` passam a usar `ACIONAMENTO` (preferencial) com fallback em `ACIONAMENTO_SOBREAVISO`, incluindo normalização de horário/duração;
  - respostas de erro agora retornam mensagem explícita (500 com `erro`) em vez de falha silenciosa.
  - `POST /sobreaviso/acionamento` endurecido para produção:
    - valida `data` e `motivo`;
    - grava em `ACIONAMENTO` (preferencial) ou `ACIONAMENTO_SOBREAVISO` (fallback);
    - removida criação dinâmica de tabela em runtime.

- `routes/web.php`
  - removidas rotas duplicadas de `sobreaviso` que colidiam com o módulo extraído em `routes/plantoes_sobreaviso.php`.

- `resources/gente-v3/src/views/ponto/EscalaSobreavisoView.vue`
  - adicionada faixa de erro na UI (`erroPagina`) para exibir falha real da API;
  - removido comportamento de ocultação de erro no `catch` de carga e de gravação.

Validação do lote 8:

- `php -l` sem erros em `routes/plantoes_sobreaviso.php` e `routes/web.php`.
- sem erros de lint nos arquivos alterados.
- `npm run build` do frontend concluído com sucesso.

### Atualização 2026-04-24 (lote 9: cadastro de funcionários robusto para módulos financeiros)

Correções de integração entre `Funcionários` e módulos de cálculo (Hora Extra/Sobreaviso/Folha), eliminando falhas por dados incompletos e incompatibilidade de tipos:

- `routes/web.php` — `/api/v3/apoio`
  - adicionado retorno de `cargos` (id, nome, salário e carga horária) para suportar cadastro completo.

- `routes/web.php` — `POST /api/v3/funcionarios`
  - validações explícitas (422) para campos mínimos críticos:
    - `PESSOA_NOME`
    - `FUNCIONARIO_DATA_INICIO`
    - `CARGO_ID`
  - persistência de `CARGO_ID` em `FUNCIONARIO`;
  - removido `UF_ID_RG` de `fill()` direto da `PESSOA` e aplicada normalização:
    - aceita ID numérico;
    - aceita sigla (`MA`, `SP`, etc.) com resolução para `UF_ID`;
  - `CIDADE_ID_NATURAL` só grava quando numérico, evitando erro de conversão em banco.

- `routes/web.php` — `PUT /api/v3/funcionarios/{id}` (fluxo completo)
  - inclusão de `CARGO_ID` na atualização de `FUNCIONARIO`;
  - normalização de `UF_ID_RG`/`CIDADE_ID_NATURAL` com mesma regra do cadastro.

- `resources/gente-v3/src/views/rh/FuncionariosView.vue`
  - nova seleção obrigatória de **Cargo** na seção de dados funcionais;
  - estado de apoio ampliado para consumir `apoio.cargos`;
  - validação no frontend para bloquear envio sem cargo.

Validação do lote 9:

- `php -l` sem erros em `routes/web.php`.
- sem erros de lint nos arquivos alterados.
- `npm run build` do frontend concluído com sucesso.

### Atualização 2026-04-24 (lote 10: saneamento de base para fluxo financeiro)

Executado saneamento transacional de dados para eliminar inconsistências que geravam efeito cascata entre módulos:

- Base encontrada antes do saneamento:
  - `CARGO`: tabela vazia;
  - `ATRIBUICAO`: tabela vazia;
  - `FUNCIONARIO` ativos sem `CARGO_ID`: 18;
  - `LOTACAO` ativa sem `VINCULO_ID`: 1;
  - `LOTACAO` ativa sem vínculo em `ATRIBUICAO_LOTACAO`: 18.

- Ações aplicadas em transação SQL:
  - criação de `CARGO` padrão ativo (`Cargo Padrão Operacional`, salário `3200.00`, carga `220h`) quando inexistente;
  - criação de `ATRIBUICAO` padrão ativa (`Atribuição Padrão Operacional`) quando inexistente;
  - preenchimento de `FUNCIONARIO.CARGO_ID` ausente para funcionários ativos;
  - preenchimento de `LOTACAO.VINCULO_ID` ausente para lotações ativas com vínculo padrão (`VINCULO_ID = 1`);
  - criação de registros faltantes em `ATRIBUICAO_LOTACAO` para todas as lotações ativas sem atribuição.

Validação do lote 10 (pós-saneamento):

- funcionários ativos sem `CARGO_ID`: `0`;
- lotações ativas sem `VINCULO_ID`: `0`;
- lotações ativas sem `ATRIBUICAO_LOTACAO`: `0`.

### Atualização 2026-04-24 (lote 11: padronização de documentos/contatos + correção schema CONTATO)

Correções aplicadas para evitar erro 500 no cadastro e padronizar entrada de dados sensíveis:

- `routes/web.php` — criação de contatos no cadastro de funcionário
  - corrigida incompatibilidade com schema real da tabela `CONTATO`:
    - banco atual usa `TIPO_CONTATO_ID` e `CONTATO_VALOR` (não `CONTATO_TIPO`/`CONTATO_CONTEUDO`);
  - inserção agora é schema-aware (funciona em ambos formatos);
  - telefone/celular são persistidos apenas com dígitos.

- `resources/gente-v3/src/views/rh/FuncionariosView.vue`
  - adicionadas máscaras/padronização de entrada:
    - CPF: `000.000.000-00`;
    - PIS/PASEP: `000.00000.00-0`;
    - Celular: `(00) 00000-0000`;
    - Telefone: `(00) 0000-0000`;
  - campos numéricos sem máscara textual:
    - RG: somente números;
    - CNH: somente números (11 dígitos);
  - e-mail normalizado para minúsculo no envio.

Validação do lote 11:

- `php -l` sem erros em `routes/web.php`.
- sem erros de lint nos arquivos alterados.
- `npm run build` do frontend concluído com sucesso.

---

## Próximo passo sugerido (pós-validação manual)

Depois do checklist marcado:
1) corrigir build frontend,
2) ajustar rotas `auth`/`api-v3`,
3) normalizar seed/banco,
4) corrigir fluxo de login com reCAPTCHA,
5) concluir integração mobile.

## Observação operacional

Este documento é o atalho local no projeto. A versão evolutiva e detalhada permanece no Brain.
