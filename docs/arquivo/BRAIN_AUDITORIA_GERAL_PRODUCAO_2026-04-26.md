# BRAIN — Auditoria Geral de Prontidão para Produção (2026-04-26)

> Auditoria técnica sênior cruzando Brain (8 documentos) com o estado real do código,
> rotas, services, migrations, seeders, views Vue e middlewares.
> Substitui parcialmente o `RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md` no
> que diz respeito ao **estado de prontidão para produção** — itens corrigidos lá
> permanecem válidos; este documento foca no que **ainda falta**.
>
> Convenção: ✅ confirmado em código · 🟡 parcial · 🔴 ausente / quebrado · ⚠️ risco / dívida.

---

## 0. RESUMO EXECUTIVO

**Veredito Go/No-Go: 🔴 NO-GO PARA PRODUÇÃO.**
O sistema está **funcionalmente rico e relativamente coerente para PoC**, mas tem
**quatro classes de bloqueadores reais** que impedem deploy responsável:

1. **RBAC efetivo é nulo** — apenas 1 das ~78 rotas modulares aplica
   `middleware('perfil:...')`. Qualquer usuário autenticado acessa qualquer endpoint
   `/api/v3/*` (admin RH, folha, SESMT, ERP, contabilidade etc.).
2. **Bypass de admin sobrevive em produção lógica** — `CheckPerfil`,
   `/api/auth/me` e quatro funções `resolveFuncionarioComFallbackDev` concedem
   privilégios totais e/ou modificam vínculos no banco se o login for `'admin'`
   e `app()->isProduction() === false`. Um deploy com `APP_ENV != production` (ou
   um `admin` real em produção) recria o BUG-017.
3. **Trilha de auditoria não está plugada** — middleware `audit` existe
   (`AuditLog.php`) e está registrado no Kernel, mas **não é aplicado em
   nenhum grupo de rotas** (`grep -n "'audit'"` em `routes/*` retorna zero
   resultados úteis). A tabela `AUDIT_LOG` recebe zero gravações reais.
4. **Higiene de repositório / segredos** — `web.php.bak` (278 kB), 67 arquivos
   `*View - Copia.vue`, 88 scripts `.ps1/.php` em `scripts-debug/`, dezenas de
   artefatos de debug na raiz (`temp.diff`, `error_trace.txt`, `route_err.txt`,
   `refresh.txt`, `results.txt`, `commit.txt`, `migrate_out.txt`,
   `rollback_out.txt`, `lint_out.txt`, `patch_routes.php`, `scan_routes*.php`),
   senha SQL Server `Gente@2024` em `.env` e `.env.docker`, 17 usuários de teste
   com senha pública `gente@2026`.

Os três motores principais (folha, jornada, calendário) **estão funcionando e
testados** (E2E folha R$24.743; smoke 37/37 modais; integração ponto×banco
horas×feriados validada). O que falta é **endurecer a casca** (segurança,
auditoria, RBAC, higiene) antes de expor a um ambiente real.

---

## 1. METODOLOGIA E EVIDÊNCIAS

### 1.1 Documentos do Brain consultados (ordem solicitada)
1. `docs/arquivo/GRAVITY_GENTE_BRAIN.md` (cérebro do agente, regras absolutas, IC-01..08)
2. `docs/arquivo/BRAIN_ATUALIZACAO_TEIA_MODULAR_2026-04-26.md` (estado da teia, gaps remanescentes)
3. `docs/MAPA_ESTADO_REAL.md` (status varrido 23/03 e 30/03, refatoração routes/)
4. `docs/PLANO_MESTRE_V3.md` (plano por blocos S/A/B/C/D/E/F)
5. `docs/GAPS_ESTRATEGICOS.md` (GAP-13/FER/RES/QV, compliance, multi-tenant)
6. `docs/RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md` (lotes 1–11 de correções)
7. `gente/.cursorrules` (regras canônicas, BUG-004/017, status BLOQUEADO)
8. `docs/cursor.md` (fluxo, fonte de verdade)

### 1.2 Varredura executada no código (este turno)
- `routes/web.php` (7 416 linhas, scans em linhas 1–250, 813, 5081, 7355).
- 78 arquivos em `routes/` (cabeçalho de cada um, `php -l` sem erros).
- `app/Http/Middleware/{Authenticate, CheckPerfil, AuditLog, SecurityHeaders, ValidateFileUpload}.php`.
- `app/Http/Kernel.php` (middleware groups + alias).
- `app/Services/{MotorFolha, DecimoTerceiro, Ferias, Rescisao, FolhaParser, ApuracaoPonto, HolidayCalendar, FeriadoService, EsocialXml}.php`.
- `app/Http/Controllers/Api/SpaAuthController.php` (existe, é chamado no login).
- `database/seeders/DatabaseSeeder.php` + 22 seeders.
- 101 migrations (mais recente: `2026_04_26_051500_create_calendar_overrides_table.php`).
- 150 `.vue` em `resources/gente-v3/src/views/` (+ 67 `*- Copia*.vue`).
- `resources/gente-v3/src/router/index.js`, `layouts/DashboardLayout.vue` (sidebar).
- `config/cors.php` (✅ supports_credentials=true, origens explícitas).
- `.env`, `.env.docker`, `.env.example`, `phpunit.xml`, `tests/`.

### 1.3 Testes funcionais não-rerodáveis (DB/Docker exigidos)
Esta auditoria **não levantou Docker/SQL Server**. Suíte phpunit existe mas só
contém `ExampleTest`. Smoke `scripts/smoke_api_criacao_modais.py` requer login
ativo. As asserções abaixo de prontidão são feitas **sobre o código**; a
validação dinâmica é responsabilidade da bateria já mantida em
`docs/RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md`.

---

## 2. MAPA POR DOMÍNIO (estado real cruzado com Brain)

### 2.1 RH / Jornada
| Componente | Backend | Frontend | Banco / Seed | Cascata | Observação |
|---|---|---|---|---|---|
| Funcionários CRUD | ✅ `routes/funcionarios.php` | ✅ `FuncionariosView.vue` | ✅ `FuncionariosPMSLzSeeder` (18 ativos) | ✅ alimenta folha, ponto, escalas | OK |
| Lotação / Setor / Vínculo | ✅ via web.php + organograma_v3 | ✅ `OrganogramaView.vue` | ✅ `OrganogramaPMSLzSeeder` + lote 10 saneamento | ✅ | Saneamento aplicado em 24/04 |
| Escala de Trabalho | ✅ `escala_trabalho.php` (schema-aware) | ✅ `EscalaTrabalhoView.vue` | ✅ via SidebarCoverageSeeder | ✅ alimenta ponto / banco horas | OK |
| Escala Saúde / Furos | ✅ `escala_saude.php` (cobertura, furos) | ✅ `MatrizEscalaView.vue` | ✅ | ✅ → substituições | OK |
| Substituições | ✅ web.php + lote 6/7 | ✅ `SubstituicoesView.vue` (abas histórico) | ✅ | ✅ notificação envolvidos | OK |
| Ponto Eletrônico | ✅ `ponto_eletronico.php` + ApuracaoPontoService usa `PONTO_CONFIG_FUNCIONARIO` | ✅ `PontoEletronicoView.vue` | ✅ `REGISTRO_PONTO`, `JORNADA_LEDGER` (nova) | ✅ → banco horas, folha | TASK-PONTO-CONFIG resolvido |
| Banco de Horas | ✅ `banco_horas.php` (ledger preferencial) | ✅ `BancoHorasView.vue` | ✅ `JORNADA_LEDGER` 2026-04-25 | ✅ saldo cumulativo | OK |
| Hora Extra | ✅ `hora_extra.php` (schema-aware) | ✅ `HoraExtraView.vue` | ✅ massa via SidebarCoverageSeeder | 🟡 não cruza com banco horas explicitamente | gap |
| Plantões / Sobreaviso | ✅ `plantoes_sobreaviso.php` (lote 8) | ✅ `PlantoesExtrasView.vue` / `EscalaSobreavisoView.vue` | ✅ | ✅ acionamento + cálculo | OK |
| Férias / Licenças | ✅ `ferias_v3.php` + `FeriasService` | ✅ `FeriasLicencasView.vue` | ✅ | 🟡 cálculo financeiro existe mas **não há gatilho automático** ao aprovar férias para gerar `DETALHE_FOLHA` do mês de início | GAP-FER-INTEG |
| Afastamentos | ✅ `afastamentos_v3.php` | ✅ via `AtestadosMedicosView` | ✅ | 🟡 integração com folha (descontos) ainda manual | gap |
| Atestados Médicos | ✅ `atestados.php` + `atestados_v3.php` | ✅ | ✅ | ✅ valida atestado | OK |
| Feriados / Folgas | ✅ `feriados_v3.php` + `HolidayCalendarService` + `calendar_overrides` (escopos global/sector/user, pay_multiplier, impacts_bank_of_hours) | ✅ `FeriadosView.vue` | ✅ migration 2026-04-26 | ✅ ponto/banco horas | Implementado conforme Brain TEIA |
| Faltas / Abono | ✅ `web.php` | ✅ `FaltasAtrasosView.vue` / `AbonoFaltasView.vue` | ✅ | 🟡 vinculação com folha por verba | gap menor |
| Diárias | ✅ `diarias.php` | ✅ `DiariasView.vue` | ✅ | ✅ | OK |
| Estagiários | ✅ `estagiarios.php` | ✅ `EstagiariosView.vue` | ✅ | ✅ | OK |
| Acumulação de Cargos | ✅ `acumulacao.php` | ✅ `AcumulacaoView.vue` | ✅ | ✅ | OK |
| Autocadastro / Recadastramento | ✅ `autocadastro_admin.php` + grupos públicos `api/v3` (web only) linhas 5081 e 7355 | ✅ `AutocadastroView.vue` / `AutocadastroGestaoView.vue` | ✅ `AUTOCADASTRO_TOKEN` | ✅ cria PESSOA/USUARIO/FUNCIONARIO | **bloco duplicado** linhas 5081 e 7355 (limpeza) |
| Progressão Funcional | ✅ `progressao_funcional.php` (lote 4 com `affected rows`) | ✅ `ProgressaoFuncionalView` / `ProgressaoAdminView` | ✅ TABELA_SALARIAL | ✅ aplica em FUNCIONARIO | OK |

### 2.2 Financeiro / Folha
| Componente | Backend | Frontend | Observação |
|---|---|---|---|
| Eventos / Verbas | ✅ `eventos_folha_v3.php` (mas mono-linha, baixa legibilidade) | ✅ `EventosView.vue` | reformatação recomendada |
| Folha de Pagamento | ✅ `folha.php` + `MotorFolhaService` (3 camadas C1/C2/C3, batch em memória) | ✅ `FolhaPagamentoView.vue` | E2E R$24.743 validado |
| FolhaParserService (legacy) | ✅ | — | substituído gradualmente pelo Motor |
| Motor (vínculos / rubricas) | ✅ `motor.php` | — | OK |
| 13º Salário | ✅ `decimo_terceiro.php` + `DecimoTerceiroService` (3 tipos) + migration 2026_03_30_000045 | ✅ via Folha | GAP-13 implementado |
| Férias (financeiro) | ✅ `FeriasService` (1/3 + INSS RPPS + IRRF) | — | GAP-FER **cálculo OK**, **integração com folha pendente** |
| Rescisão / TRCT | ✅ `RescisaoService` + `exoneracao.php` | ✅ `ExoneracaoView.vue` | GAP-RES implementado; TRCT PDF a validar |
| Hora Extra (financeiro) | ✅ `hora_extra.php` (lote 3 affected_rows) | ✅ | OK |
| Verba Indenizatória | ✅ `verba_indenizatoria.php` (lote 2) | ✅ `VerbaIndenizatoriaView.vue` | OK |
| Benefícios | ✅ `beneficios.php` (**auto-migration 2 tabelas**) | ✅ `BeneficiosView` + `BeneficiosAdminView` | ⚠️ `Schema::create` em rota — anti-pattern |
| Consignação | ✅ `consignacao.php` (margem cartão 0.10 ✅) | ✅ `ConsignacaoView.vue` | OK |
| Consignatárias (multi-operadora) | 🟡 `consignatarias.php` (29 linhas, controllers); migration `LAYOUT_CONSIGNATARIA` existe; `ConsignatariaNeoconsigSeeder` presente | ✅ `ConsignatariasView.vue` | **único bloco com `perfil:Administrador`** — backend mínimo; **importação/processamento layout não exercitado** |
| Neoconsig (módulo 1-1) | 🔴 absorvido pelo módulo Consignatárias multi-operadora (PLANO_MESTRE V3) | — | aceitável |
| RPPS / IPAM | ✅ `rpps.php` | ✅ | OK |
| Holerite PDF | ✅ `resources/views/v3/holerite-pdf.blade.php` (251 linhas) | — | OK |
| CNAB 240 | ✅ `cnab.php` (**auto-migration `CNAB_REMESSA`**) | ✅ `RemessaCnabView.vue` | ⚠️ schema fora de migration canônica |
| Contra-Cheque (servidor) | ✅ `ContraChequeService` | ✅ `ContraChequeView.vue` | OK |
| eSocial | ✅ `EsocialXmlService.php` | ✅ `ESocialView.vue` | XML inválido segundo Brain — pendente |
| SAGRES / TCE-MA | ✅ `sagres.php` cruza DETALHE_FOLHA × `SAGRES_EVENTO_DEPARA` (seed) | ✅ `SagresView.vue` | OK |
| Decreto 57.477/2021 (margem) | ✅ 30%+10% | — | OK |

### 2.3 Compliance / Obrigações Acessórias
| GAP doc | Estado | Observação |
|---|---|---|
| GAP-13 (13º) | ✅ implementado | service + rota + migration |
| GAP-FER (férias financeiro) | 🟡 | service OK; faltar gatilho ao aprovar férias |
| GAP-RES (rescisão) | ✅ service criado | TRCT PDF a validar |
| GAP-QV (Quadro de Vagas) | ✅ rota + migration `quadro_vagas.php` | validar regra de bloqueio em PSS |
| GAP-DIR (DIRF) | ✅ rota + migration | validar layout posicional |
| GAP-RAS (RAIS) | ✅ rota + migration | validar layout |
| GAP-GFP (SEFIP) | ✅ rota + migration | validar |
| GAP-SIC (SICONFI) | ✅ rota + migration | depende de ERP-C real |
| GAP-CAG (CAGED) | ✅ rota + migration | validar |
| GAP-APO (Aposentadoria) | 🔴 não existe | só simulador genérico |
| GAP-EXE (Painel Executivo) | 🔴 não existe | KPIs LRF |
| GAP-PROJ (Simulador LRF) | 🟡 `simulador_folha.php` (156 linhas) | validar projeção LRF |
| GAP-MT (Multi-tenancy) | 🔴 single-tenant | sprint dedicada |
| GAP-ICP (assinatura) | 🔴 | |
| GAP-LGPD (compliance) | 🟡 logs + senha + reCAPTCHA | falta consent, portabilidade, retenção |
| GAP-API (REST + OAuth2) | 🔴 | |

### 2.4 Administrativo
| Componente | Backend | Frontend | Observação |
|---|---|---|---|
| Contratos Administrativos | ✅ `contratos_admin.php` + migration | ✅ `ContratosAdminView.vue` | OK |
| Monitor OSS | 🟡 `oss.php` (dados reais TERCEIRO_EMPRESA, **indicadores são stub**) | ✅ `OssView.vue` | declarado: "indicadores qualitativos em fase de integração" |
| Almoxarifado | ✅ `almoxarifado.php` (corrigido leftJoinSub) | ✅ `AlmoxarifadoView.vue` | OK |
| Frotas | ✅ `frotas.php` (lote 4 affected_rows transação) | ✅ `FrotasView.vue` | OK |
| Compras / Licitações | ✅ `compras.php` | ✅ `ComprasView.vue` | OK |
| Patrimônio | ✅ `patrimonio.php` + `DepreciacaoService` (TASK-D3 mencionada como "não existe" no Plano, mas **service existe no código** — divergência Brain×Código) | ✅ `PatrimonioView.vue` | divergência docs |

### 2.5 SESMT / Desenvolvimento
| Componente | Backend | Frontend | Observação |
|---|---|---|---|
| Medicina do Trabalho (servidor) | ✅ `medicina.php` (auto-migration) | ✅ `MedicinaTrabalhoView.vue` | ⚠️ schema em runtime |
| Medicina (admin SESMT) | ✅ `medicina_admin.php` (auto-migration) | ✅ `MedicinaAdminView.vue` | ⚠️ schema em runtime |
| Segurança do Trabalho | ✅ `seguranca_trabalho.php` (3 auto-migrations: EPI, ACIDENTE, etc.) | ✅ `SegurancaTrabalhoView` + `SegurancaAdminView` | ⚠️ schema em runtime |
| Treinamentos | ✅ `treinamentos.php` (2 auto-migrations) | ✅ `TreinamentosView` + `TreinamentosAdminView` | ⚠️ schema em runtime |
| Avaliação de Desempenho | ✅ `avaliacao_desempenho.php` (2 auto-migrations) | ✅ `AvaliacaoDesempenhoView` + `AvaliacaoGestorView` | ⚠️ schema em runtime |
| Pesquisa de Satisfação | ✅ `pesquisa.php` (3 auto-migrations) | ✅ `PesquisaSatisfacaoView` + `PesquisaAdminView` | ⚠️ schema em runtime |
| Ouvidoria | ✅ `ouvidoria.php` + `ouvidoria_admin.php` | ✅ `OuvidoriaView` + `OuvidoriaAdminView` | OK |

### 2.6 ERP Financeiro / Fiscal
| Componente | Backend | Frontend | Observação |
|---|---|---|---|
| PCASP | ✅ `contabilidade.php` + `PcaspSeeder` (mínimo) | ✅ `ContabilidadeView.vue` | seed só com plano mínimo (15 contas) |
| Lançamento contábil | ✅ POST `/lancamentos` | ✅ | sem partida-dobrada validada (não verifica saldo D=C) |
| Orçamento (PPA/LOA) | ✅ `orcamento.php` | ✅ `OrcamentoView.vue` | OK |
| Execução Despesa | ✅ `execucao_despesa.php` (empenho/liquidação/pagamento) | ✅ `ExecucaoDespesaView.vue` | OK |
| Tesouraria | ✅ `tesouraria.php` | ✅ `TesourariaView.vue` | saldo calculado em tempo real (sem snapshot) |
| Receita Municipal | 🟡 `receita_municipal.php` | ✅ `ReceitaMunicipalView.vue` | stub conforme PLANO |
| Controle Externo | 🟡 `controle_externo.php` | ✅ `ControleExternoView.vue` | stub |

### 2.7 Governança / Permissões / Trilha
| Componente | Estado | Observação |
|---|---|---|
| Perfis (15 perfis) | ✅ `PerfilSeeder` | OK |
| Hierarquia roles (router) | ✅ admin > rh > gestor > funcionario | router/index.js |
| Sidebar por role | 🟡 4 roles + 2 itens com `'sesmt'` (não mapeado em `userRole()`) | inconsistência |
| RBAC backend | 🔴 **apenas 1 rota** com `perfil:Administrador` (`consignatarias.php`); todas as demais `/api/v3/*` ficam só no `auth` | **CRÍTICO** |
| Trilha de auditoria | 🔴 middleware `audit` registrado mas **nunca aplicado** | **CRÍTICO** |
| Login antibrute | ✅ 5/15min | OK |
| Rate limit login | ✅ throttle:10,1 | OK |
| reCAPTCHA v3 | ✅ só produção | OK |
| Política de senha | ✅ | OK |
| MD5 → bcrypt transparente | ✅ | OK |
| Bypass admin (CheckPerfil) | 🔴 ativo se !isProduction() && login=admin | BUG-017 reafirmado |
| Bypass admin (`/api/auth/me`) | 🔴 perfil = 'admin' por strtolower(login) — sem checar PERFIL_ID | BUG complementar |
| `resolveFuncionarioComFallbackDev` | ⚠️ duplicado em `ferias_v3`, `atestados`, `atestados_v3`, `ponto_eletronico`, `afastamentos_v3` (helpers semelhantes) — modifica banco em runtime | **CRÍTICO** |
| Relatórios CSV | ✅ BOM UTF-8 + `;` | OK |

### 2.8 Mobile
| Componente | Estado |
|---|---|
| `ponto_app.php` (JWT) | ✅ carregado em `api/v3` com `web` apenas (intencional) |
| Tela holerites | 🔴 pendente |
| Tela escala | 🔴 pendente |

---

## 3. FLUXOS POR PERFIL DE USUÁRIO

> Baseado em `DashboardLayout.vue` (sidebar) + router/index.js + middleware
> backend. Observação central: **o frontend respeita `roles`, mas o backend
> não diferencia perfis** — qualquer logado consegue chamar qualquer endpoint
> `/api/v3/*` por axios. Isso significa que toda a tabela abaixo descreve
> **fluxo intencional**, não aplicação de segurança real.

### 3.1 Admin RH
- Atua em: Funcionários, Cargos/Salários, Contratos/Vínculos, Progressões,
  PSS, Estagiários, Terceirizados, Acumulação, Diárias, Avaliações, Benefícios,
  Treinamentos, Frequência (Faltas/Abono/Atestados), Folha, Consignação, Verbas,
  RPPS, CNAB, Declarações.
- Gera: cadastros, lançamentos de folha, autorizações, holerites.
- Cascata esperada: cadastro → escala → ponto → folha → CNAB → relatórios.
- **Quebras detectadas:**
  - Aprovar férias **não** gera lançamento automático em `DETALHE_FOLHA` (GAP-FER-INTEG).
  - Aprovação de afastamento **não** gera desconto automático na folha.
  - Hora extra aprovada não vira evento variável da folha automaticamente
    (precisa lançamento manual).

### 3.2 Gestor de Setor
- Atua em: Portal do Gestor, Avaliações da Equipe, Substituições, Plantões,
  Sobreaviso, Visões da equipe (escala/ponto/atestados).
- Gera: aprovações de substituição/plantão, avaliações, decisões.
- Cascata esperada: aprovação → notificação → impacto em escala/ponto.
- **Quebras detectadas:**
  - Backend **não restringe** ações por escopo de subordinados (qualquer logado
    aprova/recusa qualquer registro).
  - "Decisão de gestor" tem tabela própria (`gestor_decisao_historico`) mas
    cobertura ainda parcial entre módulos.

### 3.3 Operador Financeiro / Folha
- Atua em: Folha, Eventos/Verbas, Consignação, Consignatárias, CNAB,
  13º, Rescisórias, RPPS, SAGRES, eSocial, DIRF, RAIS, SEFIP.
- Gera: folhas mensais, remessa CNAB, arquivos fiscais.
- Cascata esperada: fechamento folha → 13º proporcional → CNAB → SAGRES → eSocial.
- **Quebras detectadas:**
  - Único bloco com `perfil:Administrador` é Consignatárias — paradoxal porque
    operador de folha precisa abrir consignatária mas não é "Administrador".
  - eSocial: XML ainda inválido (estado documentado).
  - Lançamento contábil não valida partida-dobrada (D = C).

### 3.4 SESMT
- Atua em: Medicina do Trabalho (admin), Segurança Admin, Treinamentos.
- Gera: ASOs, EPIs, CATs, treinamentos.
- Cascata esperada: ASO vencido → bloqueio funcionário; treinamento concluído
  → atualização de competência.
- **Quebras detectadas:**
  - Sidebar tem itens com `roles: ['admin','rh','sesmt']` mas `userRole()` no
    router só mapeia 4 níveis (admin/rh/gestor/funcionario) — perfil "SESMT"
    não é reconhecido como nível distinto, cai como `funcionario`.
  - Schemas de EPI/ACIDENTE/TREINAMENTO criados em runtime nas rotas (anti-padrão).

### 3.5 Servidor / Funcionário
- Atua em: Meu Perfil, Ponto, Banco de Horas, Atestados, Férias, Holerites,
  Declarações, Comunicados, Avaliação (responder), Pesquisa, Ouvidoria,
  Treinamentos (inscrição), Benefícios.
- Gera: batidas, solicitações, atestados, requerimentos.
- Cascata esperada: batida → apuração → banco horas; férias solicitadas →
  aprovação RH → lançamento financeiro.
- **Quebras detectadas:**
  - **Sem RBAC backend**, um servidor pode chamar diretamente endpoints de
    folha/CNAB/contabilidade via axios.
  - O fallback dev modifica banco — funcionário sem vínculo, ao logar como
    `admin`, "sequestra" um FUNCIONARIO livre. Em produção, se admin real ou
    `APP_ENV != production`, há risco real.

### 3.6 Controle / Auditoria
- Esperava: trilha completa, filtros por usuário/data/IP, leitura de
  `AUDIT_LOG`.
- **Quebras detectadas:**
  - `AUDIT_LOG` existe (migration), middleware `AuditLog` existe e está
    aliasado como `audit`, **mas não é aplicado em nenhum grupo** → tabela
    permanece vazia → controle externo cego.
  - `LoggingServiceProvider` expandiu logs de segurança (security.log) — bom,
    mas não substitui audit trail estruturado.

---

## 4. MATRIZ DE GAPS (catálogo único)

> Severidade: 🔴 Crítico · 🟠 Alto · 🟡 Médio · 🟢 Baixo
> Esforço: P (≤1d) · M (1–3d) · G (≥1 sprint)
> Status guarda apenas o que **não está coberto** por correções já documentadas
> em `RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md`.

### 4.1 Segurança / RBAC / Auditoria

| ID | Módulo(s) | Tipo | Sev | Evidência | Impacto | Risco prod | Correção | Esforço | Dep | Prio |
|---|---|---|---|---|---|---|---|---|---|---|
| GP-SEC-01 | Todos `/api/v3/*` | Segurança | 🔴 | `routes/web.php:374,818,2464` — só `['web','auth']`; só `consignatarias.php` usa `perfil:Administrador` | RBAC inexistente — qualquer logado acessa folha, CNAB, contabilidade | Vazamento massivo de dados pessoais/financeiros | Aplicar `perfil:...` por bloco temático no `web.php` (RH, Folha, ERP, SESMT, Admin) ou criar middleware composto `Acesso::folha`, `Acesso::sesmt`, etc. | G | nenhum | 1 |
| GP-SEC-02 | `app/Http/Middleware/CheckPerfil.php:47` + `routes/web.php:138-140,243-247` | Segurança | 🔴 | Bypass `if (!isProduction() && login==='admin')` | BUG-017 reafirmado | Se APP_ENV cair de "production" → admin total | Remover bypass; substituir por flag explícita `IS_DEV_BYPASS=true` em `.env` (false em prod) com banner visível no UI | P | nenhum | 1 |
| GP-SEC-03 | `routes/{ferias_v3,atestados,atestados_v3,ponto_eletronico,afastamentos_v3}.php` | Segurança | 🔴 | 4× `resolveFuncionarioComFallbackDev` que faz `update FUNCIONARIO set USUARIO_ID = X` em runtime | Sequestro de identidade de funcionário em ambiente !prod | Mesmo BUG-017 com lateral em DB | Centralizar em `App\Services\FuncionarioResolverService` com flag dura; remover writes laterais; produzir 422 explícito quando vínculo ausente | M | GP-SEC-02 | 1 |
| GP-SEC-04 | Todo o sistema | Auditoria | 🔴 | `grep "'audit'" routes/*` = 0 ocorrências de aplicação | `AUDIT_LOG` permanece vazia | Sem rastro para inspeção/CGU/TCE | Aplicar middleware `audit` no grupo principal `Route::prefix('api/v3')->middleware(['web','auth','audit'])` (linha 818) e remover do `funcionarios.php`/`folha.php` se redundante | P | — | 1 |
| GP-SEC-05 | `.env`, `.env.docker` versionados | Segurança | 🔴 | DB password `Gente@2024` em commit | Vazamento de credencial | Pivot lateral SQL Server | `git rm --cached .env .env.docker`; rotacionar senha; usar Docker secrets em prod | M | nenhum | 1 |
| GP-SEC-06 | `database/seeders/UsuariosPMSLzSeeder.php` (17 usuários, senha `gente@2026`) | Segurança | 🟠 | seed permanente | Login indevido em prod | Médio | Mover para seeder `dev-only`, pular em `--env=production` | P | — | 2 |
| GP-SEC-07 | `app/Http/Middleware/SecurityHeaders.php:35` | Segurança | 🟠 | CSP com `'unsafe-inline'` + `'unsafe-eval'` | XSS exploitável | Médio | Migrar para nonces; remover `unsafe-eval` (Vue 3 não precisa) | M | build SPA | 2 |
| GP-SEC-08 | `app/Http/Middleware/SecurityHeaders.php:24` | Segurança | 🟡 | HSTS comentado | HTTPS downgrade | Baixo até VPS HTTPS | Ativar quando deploy HTTPS estável | P | infra | 3 |
| GP-SEC-09 | `routes/web.php:5081` e `:7355` | Segurança | 🟡 | dois grupos `api/v3` com `web` apenas para autocadastro pré-login (duplicados) | Token autocadastro exposto sem auth — OK por design — porém **duplicado** | Confusão e drift | Remover bloco linha 7355; manter linha 5081; documentar | P | — | 2 |
| GP-SEC-10 | `scripts-debug/`, `scripts/`, raiz | Segurança | 🟠 | 88 .ps1/.php em scripts-debug; raiz com `temp.diff`, `error_trace.txt`, `route_err.txt`, `refresh.txt`, `results.txt`, `commit.txt`, `migrate_out.txt`, `rollback_out.txt`, `lint_out.txt`, `patch_routes.php`, `scan_routes.php`, `scan_routes_multiline.php` | Vaza estado interno; código pode rodar inadvertidamente | Médio-alto | Mover scripts úteis para `scripts/dev/` documentados; deletar artefatos de debug; ampliar `.gitignore` | M | — | 2 |
| GP-SEC-11 | `routes/web.php.bak` (278 kB) | Segurança | 🟠 | backup binário versionado | Contém código histórico c/ possíveis credenciais ou bypasses | Médio | Deletar; usar git para histórico | P | — | 2 |

### 4.2 Integração / Cascata entre módulos

| ID | Módulo(s) | Tipo | Sev | Evidência | Impacto | Risco prod | Correção | Esforço | Dep | Prio |
|---|---|---|---|---|---|---|---|---|---|---|
| GP-INT-01 | Férias → Folha | Integração | 🔴 | `FeriasService::calcular()` existe mas `routes/ferias_v3.php` não dispara após aprovação | Servidor entra em férias e não recebe lançamento financeiro | Reclamação trabalhista | Implementar listener `FeriasAprovadas` que chama `FeriasService::lancarEmFolha($mesInicio)` | M | — | 1 |
| GP-INT-02 | Afastamento → Folha | Integração | 🔴 | sem gatilho automático de desconto | Folha sai sem refletir afastamento | Pagamento indevido | Idem GP-INT-01 com `AfastamentoAprovado` | M | — | 1 |
| GP-INT-03 | Hora Extra aprovada → Folha (evento variável) | Integração | 🟠 | sem gatilho automático | Lançamento manual sujeito a erro | Médio-alto | Listener `HoraExtraAprovada` cria `EVENTO_DETALHE_FOLHA` na competência | M | — | 2 |
| GP-INT-04 | Folha fechada → PCASP (lançamento contábil) | Integração | 🟠 | `routes/contabilidade.php:/lancamentos` POST avulso, sem gatilho ao fechar folha | Conciliação contábil manual | Médio | Job `GerarLancamentosFolha($folhaId)` ao confirmar folha | G | PCASP completo | 2 |
| GP-INT-05 | ASO vencido → bloqueio funcionário | Integração | 🟡 | sem regra | Funcionário ativo sem ASO | Trabalhista | Cron + view "vencimentos" + flag `FUNCIONARIO_BLOQUEADO_ASO` | M | — | 3 |
| GP-INT-06 | Treinamento concluído → competência funcionário | Integração | 🟡 | sem cascata | Plano de carreira impreciso | Baixo | Listener atualiza tabela de competências | M | — | 3 |
| GP-INT-07 | PSS edital → Quadro de Vagas (bloqueio) | Integração | 🟠 | `pss.php` lote 2 valida edital existente; bloqueio de vagas não confirmado | Nomeação acima da vaga | Alto (LRF/CGU) | Validar `VAGAS_DISPONIVEIS > 0` antes de convocar | M | GAP-QV | 2 |
| GP-INT-08 | Cadastro funcionário → vínculos automáticos | Integração | ✅ | lote 9/10 cobriu | — | — | — | — | — | — |
| GP-INT-09 | Substituição de plantão → Notificação | Integração | ✅ | lote 7 | — | — | — | — | — | — |

### 4.3 Dados / Schema / Seed

| ID | Tipo | Sev | Evidência | Impacto | Correção | Esforço | Prio |
|---|---|---|---|---|---|---|---|
| GP-DAT-01 | Dados | 🔴 | 12 arquivos de rota com `Schema::create` no parse (`pesquisa`, `seguranca_trabalho` 3x; `treinamentos`, `beneficios`, `avaliacao_desempenho` 2x; `feriados_v3`, `medicina`, `medicina_admin`, `afastamentos_v3`, `parametros_financeiros_v3`, `cnab` 1x) | Schema fora de migrations canônicas; impossível `migrate:rollback`; bootstrap exige DB up | Mover toda criação para `database/migrations/2026_05_*` e remover blocos `if (!Schema::hasTable)` das rotas | G | 1 |
| GP-DAT-02 | Dados | 🟠 | `tests/` só com `ExampleTest` | Zero cobertura | Adicionar `Tests/Feature/SmokeRotasTest.php` que cobre os 18 endpoints do smoke + asserts de RBAC + asserts de audit insert | G | 2 |
| GP-DAT-03 | Dados | 🟡 | `Feriados2026Seeder` carregado, mas `HolidayCalendarService` calcula móveis em runtime | Possível duplicidade | Definir o serviço como única fonte; seed só para overrides | P | 3 |
| GP-DAT-04 | Dados | 🟡 | Sidebar referencia `roles: ['sesmt']` mas `userRole()` não retorna 'sesmt' | Itens nunca aparecem para perfil SESMT real | Adicionar mapeamento ou alinhar com hierarquia | P | 2 |
| GP-DAT-05 | Dados | 🟡 | `eventos_folha_v3.php` em uma linha (illegible) | Manutenção | Reformatar (P) | P | 3 |

### 4.4 Funcional / UX

| ID | Sev | Evidência | Correção | Esforço | Prio |
|---|---|---|---|---|---|
| GP-FUN-01 | 🟠 | 67 arquivos `*View - Copia.vue` versionados | Deletar; aumentar `.gitignore`; CI bloqueia novos | P | 2 |
| GP-FUN-02 | 🟡 | Comentários com encoding UTF-8 corrompido em `routes/web.php` (`âš ï¸?` etc.) | Reescrever cabeçalhos; passar arquivo por `iconv` | P | 3 |
| GP-FUN-03 | 🟡 | 4× rota `/declaracoes` duplicada (Laravel ignora) | Deduplicar bloco L1850–L3552 conforme MAPA_ESTADO_REAL | M | 3 |
| GP-FUN-04 | 🟠 | `routes/web.php` com 7 416 linhas | Continuar extração modular (objetivo ≤2 000 linhas) | G | 2 |
| GP-FUN-05 | 🟡 | OSS: indicadores são stub | Integração com sistema operacional OSS pós-contrato | G | 3 |
| GP-FUN-06 | 🟡 | eSocial XML inválido | Implementar S-1200/S-2200/S-2206/S-2299 conforme leiaute | G | 2 |
| GP-FUN-07 | 🟡 | Receita Municipal e Controle Externo são stubs | Implementar conforme PLANO BLOCO C | G | 3 |
| GP-FUN-08 | 🟠 | Sem Painel Executivo (GAP-EXE) | Implementar `PainelExecutivoView` + endpoint `/api/v3/kpis/lrf` | M | 2 |

### 4.5 Performance / Observabilidade

| ID | Sev | Evidência | Correção | Esforço | Prio |
|---|---|---|---|---|---|
| GP-PER-01 | 🟡 | Sem cache de PCASP / TABELA_SALARIAL / VINCULO (consultadas a cada folha) | Cache em `app/Services/MotorFolhaService.php` por competência | M | 3 |
| GP-OBS-01 | 🟠 | Sem APM / métricas de latência por endpoint | Instrumentar com `laravel-telescope` em dev e Prometheus exporter em prod | M | 2 |
| GP-OBS-02 | 🟠 | Sem dashboard de saúde sistêmica ("organismo autossuficiente" do Brain) | Endpoint `/healthz` (DB + queue + cache) + view `SaudeView` | M | 2 |
| GP-OBS-03 | 🟡 | `LoggingServiceProvider` expande security.log mas sem rotação configurada explícita | Validar `config/logging.php` com daily/14 | P | 3 |

### 4.6 Compliance / Legal

| ID | Sev | Origem | Correção | Esforço | Prio |
|---|---|---|---|---|---|
| GP-COMP-01 | 🟠 | LGPD: sem registro de consentimento, portabilidade, retenção | Implementar GAP-LGPD do plano | G | 2 |
| GP-COMP-02 | 🟡 | Holerites/declarações sem assinatura digital ICP-Brasil | GAP-ICP | G | 3 |
| GP-COMP-03 | 🟡 | DIRF/RAIS/SEFIP/SICONFI/CAGED implementados mas **layout posicional não validado** com arquivo padrão | Comparar saída byte-a-byte com leiaute oficial | M | 2 |
| GP-COMP-04 | 🟡 | eSocial XML ainda inválido | TASK-A7 do plano | G | 2 |

---

## 5. DIVERGÊNCIAS BRAIN × CÓDIGO

| Item | Brain diz | Código mostra | Veredito |
|---|---|---|---|
| Neoconsig | "Sprint 6 — não existe" (`MAPA_ESTADO_REAL`) | renomeado para "Gestão de Consignatárias" multi-operadora (`PLANO_MESTRE_V3`) e implementado parcialmente em `consignatarias.php` + seed `ConsignatariaNeoconsigSeeder` | Brain antigo mais conservador. **Atualizar `MAPA_ESTADO_REAL`** |
| Banco de Horas | "Funcional" (MAPA_ESTADO_REAL) | Funcional **e** com nova `JORNADA_LEDGER` (migration 2026-04-25) | OK; reforço |
| TASK-PONTO-CONFIG | "ApuracaoPontoService ignora config" (PLANO_MESTRE) | Service usa `PONTO_CONFIG_FUNCIONARIO` (`ApuracaoPontoService.php:52`) | **Resolvido — atualizar PLANO** |
| TASK-D3 Depreciação | "não existe" (PLANO_MESTRE) | `app/Services/DepreciacaoService.php` existe | **Resolvido — atualizar PLANO** |
| Notificações | "stub 404" | `routes/web.php` lote 7 leitura real `NOTIFICACAO` + `PUT /lida` | **Resolvido** |
| `AUDIT_LOG` | Brain trata como "expandido" via `LoggingServiceProvider` | Middleware `audit` existe mas não é aplicado | **Conflito de status — middleware nunca usado** |
| BUG-017 fallback admin | Brain `.cursorrules:46` = "ainda ativo" | Confirmado em `CheckPerfil:47`, `web.php:138`, fallback dev em 4 rotas | **Confirmado** |

---

## 6. CRITÉRIOS DE PRONTIDÃO PARA PRODUÇÃO

### 6.1 Veredito
**🔴 NO-GO**. Vetar deploy até **Onda 1** abaixo estar fechada.

### 6.2 Top 10 bloqueios (ordem de execução)

1. **GP-SEC-01** — aplicar RBAC efetivo por bloco temático em `web.php`.
2. **GP-SEC-02** — eliminar bypass de admin em `CheckPerfil` e `/api/auth/me`.
3. **GP-SEC-03** — centralizar resolução de funcionário; remover writes laterais.
4. **GP-SEC-04** — aplicar middleware `audit` no grupo principal `api/v3+auth`.
5. **GP-SEC-05** — remover `.env*` do git, rotacionar `Gente@2024`.
6. **GP-DAT-01** — migrar todas as `Schema::create` de rota para migrations.
7. **GP-INT-01/02** — gatilhos automáticos férias/afastamento → folha.
8. **GP-SEC-10/11** — limpar `web.php.bak`, `scripts-debug/`, artefatos da raiz.
9. **GP-COMP-04** — eSocial XML válido (S-1200 mínimo).
10. **GP-FUN-01** — apagar 67 arquivos `*View - Copia*.vue`.

### 6.3 Checklist mínimo de produção (DoD release)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` rotacionada.
- [ ] Senha SQL Server rotacionada e injetada via Docker secret / Vault.
- [ ] `.env*` removidos do git; `.gitignore` cobre `.env`, `.env.docker`,
      `*.bak`, `error_*.txt`, `*.diff`, `lint_out*.txt`, `route_err*.txt`,
      `refresh.txt`, `results.txt`, `migrate_out.txt`, `rollback_out.txt`,
      `commit.txt`, `scan_routes*.php`, `patch_routes.php`, `*- Copia*.vue`.
- [ ] `routes/web.php.bak` deletado; `scripts-debug/` movido/removido.
- [ ] Middleware `audit` aplicado e validado (uma mutação produz uma linha
      em `AUDIT_LOG`).
- [ ] RBAC efetivo: cada bloco com `perfil:` apropriado; teste automatizado
      garante 403 quando perfil insuficiente.
- [ ] Bypass dev removido (CheckPerfil + me) ou amarrado a env explícito
      `IS_DEV_BYPASS=true` (false em prod) com banner UI.
- [ ] `resolveFuncionarioComFallbackDev` substituído por service único sem
      escrita lateral; resposta 422 explícita quando vínculo ausente.
- [ ] Todas as `Schema::create` de rota migradas; `php artisan migrate:fresh`
      reproduz schema completo sem dependência de runtime.
- [ ] Listeners férias/afastamento/hora-extra → folha entregam evento de domínio.
- [ ] Smoke `scripts/smoke_api_criacao_modais.py` 37/37 + suíte phpunit
      mínima `Tests/Feature/SmokeRotasTest.php`.
- [ ] HSTS ativo (após HTTPS na VPS).
- [ ] CSP sem `'unsafe-eval'`.
- [ ] PRA / PCM: backup automatizado SQL Server + plano restore documentado.
- [ ] Migração transparente MD5→bcrypt validada em todos os perfis ativos.
- [ ] eSocial S-1200 validado contra leiaute oficial.

### 6.4 Plano de ataque por ondas

#### Onda 1 — bloqueadores críticos (1 sprint, ~5 dias úteis)
- GP-SEC-01, 02, 03, 04, 05, 10, 11
- GP-DAT-01 (migração das auto-migrations)
- GP-FUN-01 (limpeza copias)
- GP-INT-01, 02 (gatilhos férias/afastamento)
- saída: deploy interno controlado em VPS de homologação.

#### Onda 2 — estabilidade e confiabilidade (2 sprints)
- GP-SEC-06, 07
- GP-INT-03, 04, 07
- GP-FUN-04 (continuar extração de `web.php`)
- GP-DAT-02 (suíte phpunit smoke + audit + RBAC)
- GP-OBS-01, 02 (APM + healthz)
- GP-COMP-03, 04 (validação leiaute + eSocial)
- saída: piloto com 1 secretaria real.

#### Onda 3 — excelência operacional (3+ sprints)
- GP-COMP-01 (LGPD completo)
- GP-COMP-02 (ICP-Brasil)
- GAP-MT (multi-tenancy)
- GAP-EXE (Painel Executivo) + GAP-PROJ (LRF)
- App mobile telas restantes
- ledger imutável de jornada/financeiro
- mecanismo preditivo (GRAVITY_BRAIN seção 5.3)

---

## 7. PRÓXIMAS 15 AÇÕES RECOMENDADAS

| # | Ação | Onda | Owner sugerido | Esforço |
|---|---|---|---|---|
| 1 | Aplicar `audit` em `Route::prefix('api/v3')->middleware(['web','auth','audit'])` (web.php:818, 2464) e validar com 1 mutação | 1 | backend | P |
| 2 | Remover bypass `if (!isProduction() && login==='admin')` de `CheckPerfil:47` e bloco `/api/auth/me` (linhas 138 e 243); criar flag `IS_DEV_BYPASS` em `.env` | 1 | backend | P |
| 3 | Centralizar `resolveFuncionarioComFallbackDev` em service único sem writes laterais | 1 | backend | M |
| 4 | Aplicar `perfil:Administrador,RH Folha,Operacional,Equipe SISGEP` em blocos de folha; `perfil:Administrador,RH Folha,RH Unidade,Operacional` em blocos de RH; `perfil:Administrador,Equipe SISGEP` em ERP/contabilidade; `perfil:Administrador,Operacional` em SESMT | 1 | backend | M-G |
| 5 | `git rm --cached .env .env.docker`; rotacionar `Gente@2024`; usar Docker secrets | 1 | infra | M |
| 6 | Deletar `routes/web.php.bak`, mover `scripts-debug/`, deletar `temp.diff/error_trace.txt/...` da raiz; expandir `.gitignore` | 1 | dev ops | P |
| 7 | Deletar 67 arquivos `*View - Copia*.vue` (com `git mv` para histórico se necessário) | 1 | front | P |
| 8 | Migrar 13 `Schema::create` de rotas para migrations `2026_05_*_create_*_tables.php` | 1 | backend | G |
| 9 | Implementar listener `FeriasAprovadas → FeriasService::lancarEmFolha($competencia)` | 1 | backend | M |
| 10 | Implementar listener `AfastamentoAprovado → DescontoFolhaService::lancar()` | 1 | backend | M |
| 11 | Remover bloco duplicado de autocadastro pré-login em web.php:7355 | 1 | backend | P |
| 12 | Criar `Tests/Feature/RbacTest.php` cobrindo 6 perfis × 10 endpoints (espera 200 ou 403 conforme matriz) | 2 | QA | M |
| 13 | Endpoint `/healthz` (DB ping + queue + cache) e dashboard `SaudeView` | 2 | full-stack | M |
| 14 | Validar leiaute eSocial S-1200 byte-a-byte com XSD oficial; fix `EsocialXmlService` | 2 | backend | G |
| 15 | Painel Executivo (`PainelExecutivoView` + `/api/v3/kpis/lrf`) | 2 | full-stack | G |

---

## 8. MÉTRICAS DE ACOMPANHAMENTO SEMANAL

Manter em `docs/arquivo/SEMANAL_AUDITORIA.md` (criar arquivo a cada sprint
fechado), com snapshot dos números abaixo:

1. **% de rotas `/api/v3/*` com `perfil:` aplicado** — meta: 100% até Onda 1.
2. **Linhas em `routes/web.php`** — meta: ≤ 2 000.
3. **Quantidade de `Schema::create` em `routes/*.php`** — meta: 0.
4. **Quantidade de arquivos `*View - Copia*.vue`** — meta: 0.
5. **Linhas escritas/dia em `AUDIT_LOG`** — meta: ≥ N (calibrar pelo uso real).
6. **Cobertura phpunit (% de rotas críticas com smoke)** — meta: 80%.
7. **Quantidade de bypasses dev no código** (`grep -r "isProduction.*admin"`) — meta: 0 fora de service centralizado com flag.
8. **Tempo de cálculo `MotorFolhaService::calcularFolha` (ms p/ 18 servidores)** — baseline a estabelecer.
9. **Taxa de sucesso smoke 37/37 a cada build** — meta: 100% por 4 semanas seguidas.
10. **Quantidade de credenciais em arquivos versionados** (`grep -E 'PASSWORD|SECRET' --include='.env*'`) — meta: 0.

---

## 9. DEFINIÇÃO DE "TEIA VIVA" (DoD de Integração)

Reafirma e amplia a versão do `BRAIN_ATUALIZACAO_TEIA_MODULAR_2026-04-26.md §6`.
Uma integração só é considerada concluída quando **todos** os critérios abaixo
forem atendidos:

1. **Ação propaga**: ação do usuário em módulo A altera os dados/relatórios de
   B/C dependentes sem nenhuma intervenção manual ou job tardio (≤ 5 s).
2. **UI espelha verdade**: a alteração aparece na UI, no relatório e no KPI
   sem F5 manual nem cache stale.
3. **Auditável fim-a-fim**: existe linha em `AUDIT_LOG` com `(quem, quando,
   antes, depois, IP)` para a mutação principal **e** para cascatas
   automáticas (jobs/listeners), distinguíveis por `ACAO`.
4. **RBAC**: o backend retorna 403 quando o perfil chamador não tem permissão,
   testado por `Tests/Feature/RbacTest.php`.
5. **Schema versionado**: tabelas envolvidas vêm de migration canônica
   (`database/migrations/`); `php artisan migrate:fresh --seed` reproduz o
   estado em ambiente limpo.
6. **Seed mínimo viável**: `SidebarCoverageSeeder` ou seeder próprio do
   módulo cobre o caminho feliz com ≥ 1 registro consistente.
7. **Smoke automatizado**: cenário principal coberto por `phpunit feature` ou
   por `scripts/smoke_*.py`; cenário de borda crítica coberto por unit test.
8. **Idempotência**: a ação executada duas vezes não produz duplicidade
   nem corrompe estado (ex.: aprovar férias 2× não gera 2 lançamentos).
9. **Mensagem de erro específica**: nunca retornar `ok: true` quando nada
   foi alterado (lotes 1–5 já cobrem em ~20 endpoints; estender ao restante).
10. **Observabilidade**: a operação aparece em `security.log` se for
    sensível, ou em log estruturado padrão se for operacional; latência
    é capturável por APM.

---

## 10. APÊNDICES

### 10.1 Inventário rápido (números reais)
- `routes/`: 78 arquivos (1 `web.php` 7 416 linhas + 1 `web.php.bak` 278 kB + 76 módulos).
- `app/Http/Controllers/`: ~70 controllers legados (PreCadastro, Pessoa, etc.) + `Api/SpaAuthController`.
- `app/Models/`: ~80 models.
- `app/Services/`: 17 services (folha, ponto, holiday, depreciação, eSocial, CNAB, etc.).
- `database/migrations/`: 101 migrations.
- `database/seeders/`: 22 seeders.
- `resources/gente-v3/src/views/`: 150 `.vue` (83 ativos + 67 `*- Copia*`).
- `tests/`: 2 ExampleTest.

### 10.2 Pasta inesperada no repo
- `gente/C:\dev\sisgep-cache/` — diretório com nome de path Windows criado por engano. Validar se tem conteúdo útil; em caso negativo, deletar.

### 10.3 Perfis (PerfilSeeder)
1. Desenvolvedor · 2. Administrador · 3. Operacional · 4. Manutenção
· 5. Externo · 6. RH Folha · 7. Gestão · 8. RH Unidade
· 9. Direitos e Deveres · 10. Recrutador · 11. Coordenador de Setor
· 12. Diretor / Gestor de Unidade · 13. Equipe SISGEP
· 14. RH APS · 15. RH Rede.

### 10.4 Mapa requires (web.php)
`ponto_app` (linha 814, web only — JWT) →
`funcionarios + folha` (824–825, auth) →
`motor` (1148, auth) →
bloco grande auth 1215–1289: esocial, consignacao, diarias, rpps, exoneracao, hora_extra, verba_indenizatoria, pesquisa, ouvidoria_admin, relatorios, estagiarios, acumulacao, transparencia, pss, terceirizados, sagres, banco_horas, progressao_funcional, afastamentos_v3, parametros_financeiros_v3, turnos_v3, feriados_v3, tabelas_auxiliares, eventos_folha_v3, cargos_salarios, ferias_v3, comunicados, meu_perfil, ponto_eletronico, plantoes_sobreaviso, atestados_v3, contratos_v3, medicina, declaracoes, ouvidoria, gestor, organograma_v3, beneficios, medicina_admin, seguranca_trabalho, treinamentos, consignatarias, compras, almoxarifado, patrimonio, contratos_admin, frotas, escala_saude, decimo_terceiro, quadro_vagas, simulador_folha, caged, sefip, dirf, rais, siconfi, ponto_terceirizado, escala_trabalho, autocadastro_admin, avaliacao_desempenho, orcamento, execucao_despesa, contabilidade, tesouraria, receita_municipal, controle_externo, cnab, oss.

### 10.5 Por que esta auditoria não substitui o ambiente up
Sem `docker compose up && php artisan migrate:fresh --seed`, não pude validar:
- valor numérico exato das folhas de teste,
- reprodutibilidade do smoke 37/37 desta data,
- integridade real de FKs em SQL Server (validação foi de código + migrations + seeders).
Recomendo executar a bateria do `RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md`
imediatamente após Onda 1 fechada para regressão.

---

*Documento gerado em 2026-04-26. Próxima revisão: pós-Onda 1.*
*Salvar evidências em `docs/arquivo/SEMANAL_AUDITORIA.md` ao fechar cada item.*

---

## 11. Sincronização documental (repo + Obsidian)

Sincronização executada em 2026-04-26 para reduzir drift entre documentação e código:

- Brain local (repo):
  - `docs/arquivo/BRAIN_ATUALIZACAO_TEIA_MODULAR_2026-04-26.md` atualizado com fechamento `m3/m4/m5/m6`.
  - `docs/arquivo/GRAVITY_GENTE_BRAIN.md` e `docs/cursor.md` atualizados com vault oficial.
- Obsidian vault:
  - caminho oficial: `/home/DK/brain/Obsidian-Brain-v6/`
  - notas atualizadas: `overview.gente.md`, `00-INDICE.md`, `planos/estado-atual.md`, `sprints/estado-atual.md`
  - nota criada: `_Global/PROJETOS/RRTECNOL/GENTE/auditorias/auditoria-geral-producao-2026-04-26.md`

---

## 12. Implementações executadas do plano (2026-04-26)

Execuções práticas realizadas com base nos bloqueadores descritos nesta auditoria:

- **GP-SEC-04 (parcial) — audit middleware aplicado**
  - grupos principais `Route::prefix('api/v3')->middleware(['web','auth', ...])` em `routes/web.php` passaram a incluir `audit`.
- **GP-SEC-02 (parcial) — bypass admin reduzido**
  - removido bypass global em `app/Http/Middleware/CheckPerfil.php`.
  - removido atalho de perfil automático para login `admin` em `/api/auth/me` e resposta de login (`routes/web.php`).
  - removida chamada `applyDevAdminFuncionarioVinculo()` no fluxo de login em `routes/web.php`.
  - `SpaAuthController` mantido com compatibilidade, sem aplicar bypass.
- **GP-SEC-03 (parcial) — fallback dev com escrita lateral removido**
  - funções `resolveFuncionarioComFallbackDev()` em:
    - `routes/ponto_eletronico.php`
    - `routes/ferias_v3.php`
    - `routes/atestados_v3.php`
    - `routes/plantoes_sobreaviso.php`
  - agora retornam apenas vínculo real `USUARIO_ID -> FUNCIONARIO`, sem `UPDATE` automático em `FUNCIONARIO`.
- **Correção de fluxo por perfil (frontend)**
  - role `sesmt` adicionada na hierarquia/mapeamento em `resources/gente-v3/src/router/index.js`.

Validação técnica das mudanças:
- `php -l` nos arquivos PHP alterados: **sem erros de sintaxe**.
- build frontend (`vite build`): **PASS**.

---

## 13. Cobertura adicional de seeds críticos (2026-04-26 madrugada)

Execução complementar realizada para fechar lacunas funcionais de dados nos módulos solicitados:

- **Arquivo alterado**
  - `database/seeders/SystemPhase2CoverageSeeder.php`
- **Novos blocos de seed**
  - `seedMedicinaOcupacionalDetalhada()`
  - `seedSegurancaTrabalhoDetalhada()`
  - `seedTreinamentosDetalhados()`
  - `seedAvaliacoesDesempenhoDetalhadas()`
  - `seedAbonoFaltasHistorico()`
- **Encadeamento no run()**
  - os cinco blocos acima foram conectados diretamente no fluxo principal do seeder fase 2.
- **Compatibilidade SQL Server**
  - ajuste de nota de critérios de avaliação para tipo inteiro (`tinyint`) quando aplicável.
  - adaptação dinâmica de `ABONO_FALTA_TIPO` e `ABONO_FALTA_STATUS` para bancos que usam tipo numérico.
  - proteção de coluna opcional `USUARIO_ID` em `ABONO_FALTA`.
- **Execução validada**
  - comando: `php artisan db:seed --class=SystemPhase2CoverageSeeder` (**PASS**).
  - verificação por contagem após seed:
    - `EXAME_OCUPACIONAL=8`
    - `AGENDAMENTO_EXAME=5`
    - `SEGURANCA_EPI=6`
    - `SEGURANCA_INCIDENTE=6`
    - `TREINAMENTO=4`
    - `TREINAMENTO_INSCRICAO=24`
    - `AVALIACAO_DESEMPENHO=12`
    - `AVALIACAO_CRITERIO=39`
    - `ABONO_FALTA=8`
- **Higiene de repositório (GP-SEC-11 parcial)**
  - `routes/web.php.bak` removido do workspace/repo local.
  - permanece pendente somente a limpeza de `scripts-debug/` e artefatos soltos da raiz (GP-SEC-10).

Impacto esperado:
- telas de Medicina Ocupacional e Segurança do Trabalho deixam estado vazio e passam a exibir histórico realista;
- Gestão de Treinamentos e Avaliações passam a ter massa de uso por ciclo;
- Abono de Faltas / Histórico de Abonos ganha trilha consistente para funcionário e gestão.
