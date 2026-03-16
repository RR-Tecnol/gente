# SISGEP — Mapa de Estado Real do Código
**Gerado por:** Varredura direta do filesystem em 13/03/2026  
**Propósito:** Base de referência para sessões de auditoria. Camada 1 — o que o código TEM, não o que deveria ter.  
**Atualizar sempre que:** uma sprint for executada no Antigravity e validada.

---

## 1. STACK E CONFIGURAÇÃO REAL

| Item | Valor real no código |
|------|----------------------|
| Framework backend | Laravel 8 |
| Frontend | Vue 3 SPA (Vite) + Vuetify + Pinia |
| Banco dev | SQLite (`DB_CONNECTION=sqlite`) |
| Banco prod | SQL Server (comentado no `.env`) |
| `APP_ENV` | `local` |
| `APP_URL` | `http://localhost` (sem porta — ⚠️ falta `:8000`) |
| `SESSION_DRIVER` | `file` |
| `SESSION_DOMAIN` | **não definido** no `.env` — ⚠️ problema |
| CORS `supports_credentials` | `false` — ⚠️ **bug ativo** |
| CORS `allowed_origins` | `['*']` — ⚠️ **bug ativo** |
| Autenticação | Sessão Laravel (cookie-based), sem Sanctum/JWT |
| JWT (app ponto) | `PONTO_APP_JWT_SECRET` — ✅ definido no `.env` |
| SMTP | Brevo — ✅ configurado no `.env` |

---

## 2. ESTRUTURA DE ROTAS — ESTADO REAL

### 2.1 Problema crítico confirmado: bloco `isLocal()`

O `routes/web.php` tem a seguinte estrutura de alto nível:

```
GET /                          → view('auth.login')           [público, sempre ativo]

if (isLocal() || dev/testing):
  Route::prefix('dev')->group:
    GET  /dev/ping-db
    POST /dev/echo-request
    POST /dev/echo-raw
    GET  /dev/v3                → view('v3.app')
    GET  /dev/autocadastro/{token}
    GET  /dev/csrf-cookie       ⚠️ PRESO AQUI — deveria ser /csrf-cookie
    Route::prefix('api/auth'):
      GET  /dev/api/auth/me     ⚠️ PRESO AQUI — frontend chama /api/auth/me
      POST /dev/api/auth/login  ⚠️ PRESO AQUI — frontend chama /api/auth/login
      POST /dev/api/auth/logout
      POST /dev/api/auth/change-password
    Route::prefix('api/v3') + auth:
      GET  /dev/api/v3/funcionarios/{id}  ⚠️ duplicata — existe também fora
      PUT  /dev/api/v3/funcionarios/{id}
      GET  /dev/api/v3/funcionarios/{id}/documentos
      GET  /dev/api/v3/funcionarios/{id}/historico
      GET  /dev/api/v3/funcionarios/{id}/escalas
      GET  /dev/api/v3/funcionarios
      GET  /dev/api/v3/funcionarios/{id}/dependentes
      POST /dev/api/v3/funcionarios/{id}/dependentes
      DEL  /dev/api/v3/funcionarios/{id}/dependentes/{depId}
    GET  /dev/diag-login/{login}/{senha}
    GET  /dev/criar-admin
    GET  /dev/seed-dados
    [avaliações de desempenho — GET/POST /rh/avaliacoes]
    [modelos de declaração — GET/PUT/DEL /rh/modelos/{tipo}]
    [require exoneracao, hora_extra, verba_indenizatoria, consignacao,
     esocial, rpps, diarias, estagiarios, acumulacao, transparencia,
     pss, terceirizados, sagres, banco_horas, atestados,
     orcamento, execucao_despesa, contabilidade, tesouraria,
     receita_municipal, controle_externo]

Route::prefix('api/v3') + auth:    ← grupo EXTERNO ao isLocal() ✅
  require funcionarios.php          → GET/PUT /funcionarios, GET /ponto
  require folha.php                 → GET /folhas, GET /folhas/{id}/detalhes, POST /folhas/calcular
  GET  /escalas
  GET  /escalas/{id}
  [+ resto de web.php inline: servidores/buscar, secretarias,
   notificações, holerites, progressão, etc.]
```

### 2.2 Consequência direta do bug

Porque `APP_ENV=local`, o bloco `isLocal()` está **ativo agora em dev**. As rotas existem como `/dev/api/auth/login`, não como `/api/auth/login`. O frontend (`axios.js`) chama `/api/auth/login` → **404**.

Em produção (APP_ENV=production), o bloco inteiro desaparece → **todos os módulos somem** (consignação, esocial, rpps, folha módulos avançados, etc.).

---

## 3. MÓDULOS — MAPA COMPLETO

### Legenda
- ✅ Backend + Frontend existem e estão funcionais no código
- ⚠️ Existe mas tem problema confirmado
- 🔒 Existe mas preso no `isLocal()` (inacessível em prod)
- 📄 View existe, backend a confirmar
- ❌ Não existe

| Módulo | Arquivo Backend | View Frontend | Status |
|--------|----------------|---------------|--------|
| **AUTH / SESSÃO** | `web.php` (inline) | `auth/LoginView.vue` | 🔒 Preso no isLocal() |
| CSRF cookie | `web.php` (inline) | axios.js | 🔒 Preso no isLocal() |
| Troca de senha | `web.php` (inline) | — | 🔒 Preso no isLocal() |
| **FUNCIONÁRIOS (CRUD)** | `funcionarios.php` | `rh/FuncionariosView.vue` | ✅ Fora do isLocal() |
| Perfil Funcionário | `funcionarios.php` | `rh/PerfilFuncionarioView.vue` | ✅ Fora do isLocal() |
| Dependentes | `funcionarios.php` (dev) / `web.php` (dev) | `rh/PerfilFuncionarioView.vue` | 🔒 Preso no isLocal() |
| Histórico funcional | `web.php` (dev) | `rh/PerfilFuncionarioView.vue` | 🔒 Preso no isLocal() |
| **PONTO ELETRÔNICO** | `funcionarios.php` (GET /ponto) | `ponto/PontoEletronicoView.vue` | ✅ |
| Banco de Horas | `banco_horas.php` 🔒 | `ponto/BancoHorasView.vue` | 🔒 Preso no isLocal() |
| Atestados Médicos | `atestados.php` 🔒 | `ponto/AtestadosMedicosView.vue` | 🔒 Preso no isLocal() |
| Abono de Faltas | (verificar) | `ponto/AbonoFaltasView.vue` | 📄 |
| Faltas e Atrasos | (verificar) | `ponto/FaltasAtrasosView.vue` | 📄 |
| Escala Sobreaviso | (verificar) | `ponto/EscalaSobreavisoView.vue` | 📄 |
| Plantões Extras | `hora_extra.php` 🔒 | `ponto/PlantoesExtrasView.vue` | 🔒 Preso no isLocal() |
| **FOLHA DE PAGAMENTO** | `folha.php` | `financeiro/FolhaPagamentoView.vue` | ✅ Fora do isLocal() |
| Holerites (ContraCheque) | `web.php` 🔒 | `folha/ContraChequeView.vue` | 🔒 |
| PDF Holerite | `folha.php` ou `web.php` 🔒 | — | 🔒 + ⚠️ view Blade pendente |
| **ESCALA** | `web.php` (inline, fora) | `escala/MatrizEscalaView.vue` | ✅ |
| **CONSIGNAÇÃO** | `consignacao.php` 🔒 | `rh/ConsignacaoView.vue` | 🔒 Preso no isLocal() |
| **eSocial** | `esocial.php` 🔒 | `rh/ESocialView.vue` | 🔒 Preso no isLocal() |
| **RPPS / IPAM** | `rpps.php` 🔒 | `rh/RPPSView.vue` | 🔒 Preso no isLocal() |
| **Exoneração** | `exoneracao.php` 🔒 | `rh/ExoneracaoView.vue` | 🔒 Preso no isLocal() |
| **Hora Extra** | `hora_extra.php` 🔒 | `rh/HoraExtraView.vue` | 🔒 Preso no isLocal() |
| **Verba Indenizatória** | `verba_indenizatoria.php` 🔒 | `rh/VerbaIndenizatoriaView.vue` | 🔒 Preso no isLocal() |
| **Diárias** | `diarias.php` 🔒 | `rh/DiariasView.vue` | 🔒 Preso no isLocal() |
| **Estagiários** | `estagiarios.php` 🔒 | `rh/EstagiariosView.vue` | 🔒 Preso no isLocal() |
| **Progressão Funcional** | `progressao_funcional.php` 🔒 ⚠️ BOM | `rh/ProgressaoFuncionalView.vue` | 🔒 + ⚠️ BOM causa 500 |
| **Avaliação Desempenho** | `web.php` (dev) 🔒 | `rh/AvaliacaoDesempenhoView.vue` | 🔒 Preso no isLocal() |
| **Acumulação de Cargos** | `acumulacao.php` 🔒 | `rh/AcumulacaoView.vue` | 🔒 Preso no isLocal() |
| **Transparência Pública** | `transparencia.php` 🔒 | `rh/TransparenciaView.vue` | 🔒 Preso no isLocal() |
| **PSS / Concursos** | `pss.php` 🔒 | `rh/PSSView.vue` | 🔒 Preso no isLocal() |
| **Terceirizados** | `terceirizados.php` 🔒 | `rh/TerceirizadosView.vue` | 🔒 Preso no isLocal() |
| **SAGRES / TCE-MA** | `sagres.php` 🔒 | `financeiro/SagresView.vue` | 🔒 Preso no isLocal() |
| **ERP Orçamento** | `orcamento.php` 🔒 | `financeiro/OrcamentoView.vue` | 🔒 Preso no isLocal() |
| **ERP Execução Despesa** | `execucao_despesa.php` 🔒 | `financeiro/ExecucaoDespesaView.vue` | 🔒 Preso no isLocal() |
| **ERP Contabilidade** | `contabilidade.php` 🔒 | `financeiro/ContabilidadeView.vue` | 🔒 Preso no isLocal() |
| **ERP Tesouraria** | `tesouraria.php` 🔒 | `financeiro/TesourariaView.vue` | 🔒 Preso no isLocal() |
| **ERP Receita Municipal** | `receita_municipal.php` 🔒 | `financeiro/ReceitaMunicipalView.vue` | 🔒 Preso no isLocal() |
| **ERP Controle Externo** | `controle_externo.php` 🔒 | `financeiro/ControleExternoView.vue` | 🔒 Preso no isLocal() |
| **Neoconsig** | ❌ não existe | ❌ não existe | ❌ Sprint 3 pendente |
| Cargos e Salários | (verificar) | `rh/CargosSalariosView.vue` | 📄 |
| Organograma | (verificar) | `rh/OrganogramaView.vue` | 📄 |
| Portal Gestor | (verificar) | `rh/PortalGestorView.vue` | 📄 |
| Medicina do Trabalho | (verificar) | `rh/MedicinaTrabalhoView.vue` | 📄 |
| Benefícios | (verificar) | `rh/BeneficiosView.vue` | 📄 |
| Contratos e Vínculos | (verificar) | `rh/ContratosVinculosView.vue` | 📄 |
| Treinamentos | (verificar) | `rh/TreinamentosView.vue` | 📄 |
| Segurança do Trabalho | (verificar) | `rh/SegurancaTrabalhoView.vue` | 📄 |
| Pesquisa Satisfação | (verificar) | `rh/PesquisaSatisfacaoView.vue` | 📄 |
| Ouvidoria | (verificar) | `OuvidoriaView.vue` | 📄 |
| Comunicados | (verificar) | `ComunicadosView.vue` | 📄 |
| Agenda | (verificar) | `AgendaView.vue` | 📄 |
| Notificações | `web.php` stub 🔒 | `NotificacoesView.vue` | 🔒 |

---

## 4. PROBLEMAS ATIVOS CONFIRMADOS NO CÓDIGO

| ID | Arquivo | Problema | Impacto |
|----|---------|----------|---------|
| **P-01** | `routes/web.php` | Rotas de auth (`/csrf-cookie`, `/api/auth/*`) e TODOS os módulos dentro do `isLocal()` com prefixo `dev` | 🔴 Login quebrado + todos os módulos inacessíveis em prod |
| **P-02** | `config/cors.php` | `supports_credentials: false` + `allowed_origins: ['*']` | 🔴 Cookie de sessão não persiste |
| **P-03** | `.env` | `APP_URL=http://localhost` (sem porta) + sem `SESSION_DOMAIN` | 🟠 Cookie de sessão com domínio incorreto |
| **P-04** | `routes/progressao_funcional.php` | BOM UTF-8 (`﻿` antes do `<?php`) + sem `use Carbon\Carbon` | 🔴 Causa HTTP 500 em todo o sistema quando carregado |
| **P-05** | `resources/gente-v3/src/router/index.js` | `{ path: '/', redirect: '/login' }` fixo — usuário autenticado é redirecionado para login sempre | 🟠 Loop pós-login |

---

## 5. MÓDULOS FORA DO ISLOCAL() — FUNCIONANDO EM PROD

Apenas estes endpoints existem corretamente fora do bloco `isLocal()`:

| Endpoint | Arquivo |
|----------|---------|
| `GET /` | `web.php` inline |
| `GET /api/v3/funcionarios` | `funcionarios.php` |
| `GET /api/v3/funcionarios/{id}` | `funcionarios.php` |
| `GET /api/v3/ponto` | `funcionarios.php` |
| `GET /api/v3/folhas` | `folha.php` |
| `GET /api/v3/folhas/{id}/detalhes` | `folha.php` |
| `POST /api/v3/folhas/calcular` | `folha.php` |
| `GET /api/v3/escalas` | `web.php` inline |
| `GET /api/v3/escalas/{id}` | `web.php` inline |
| + endpoints inline de escalas, substituições, feriados, turnos | `web.php` |

---

## 6. AXIOS / FRONTEND

| Item | Valor real |
|------|------------|
| `baseURL` | `/` (proxy Vite para `127.0.0.1:8000`) |
| `withCredentials` | `true` ✅ |
| Header `X-Requested-With` | `XMLHttpRequest` ✅ |
| Interceptor 401/419 | Redireciona para `/login` ✅ |
| CSRF | Busca via `GET /csrf-cookie` — mas essa rota está em `/dev/csrf-cookie` 🔴 |

---

## 7. MODELS EXISTENTES

71 models em `app/Models/`. Principais para auditoria de rotas:
`Usuario`, `Funcionario`, `Pessoa`, `Folha`, `DetalheFolha`, `Lotacao`, `Setor`, `Unidade`, `Vinculo`, `Atribuicao`, `Escala`, `DetalheEscala`, `RegistroPonto`, `Perfil`, `UsuarioPerfil`, `Ferias`, `Afastamento`

---

## 8. MIGRATIONS — ESTADO

53 migrations. Últimas relevantes (03/2026):
- `2026_03_11_add_consig_autorizacao_ocorrencia` — CONSIG_OCORRENCIA + colunas STATUS_AUTORIZACAO
- `2026_03_11_add_performance_indexes` — índices de performance
- `2026_03_11_create_usuario_unidade_acesso_rpps_config` — USUARIO_UNIDADE_ACESSO + RPPS_CONFIG
- `2026_03_11_000007_create_consignacao_tables` — tabelas de consignação
- `2026_03_11_000009_create_modulos_avancados_tables` — módulos latentes
- `2026_03_11_100000` a `100005` — ERP (orçamento, despesa, contabilidade, tesouraria, receita, controle)

---

## 9. SPRINT PENDENTE — RESUMO EXECUTIVO

| Sprint | Escopo | Bloqueante |
|--------|--------|------------|
| **Sprint 0** | Corrigir login (P-01 a P-05) | SIM — tudo depende disso |
| **Sprint 1** | Aplicar migrations pendentes + seeder MD5 | Após Sprint 0 |
| **Sprint 2** | Margem cartão 5%→10% + view Blade holerite PDF | Após Sprint 1 |
| **Sprint 3** | Neoconsig (6 endpoints novos) | Após Sprint 2 |
| **Sprint 4** | Build + deploy VPS | Após todos |

---

*Documento gerado por varredura direta do código — não baseado em documentação anterior.*  
*Próxima atualização: após execução do Sprint 0 no Antigravity.*
