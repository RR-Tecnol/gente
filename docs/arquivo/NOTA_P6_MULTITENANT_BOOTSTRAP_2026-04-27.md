# P6 — Bootstrap Multi-tenant (2026-04-27)

## Entregáveis iniciais implementados

- Configuração de tenancy em `config/tenancy.php`:
  - `TENANCY_ENABLED`;
  - estratégia de resolução (`subdomain`/`header`);
  - header de fallback (`X-Tenant-Id`).
- Contexto tipado de tenant em `app/Support/Tenancy/TenantContext.php`.
- Middleware `ResolveTenantContext` criado e registrado como alias:
  - alias: `tenant.resolve`
  - arquivo: `app/Http/Middleware/ResolveTenantContext.php`
- Piloto aplicado em rotas públicas de transparência:
  - `/transparencia/dossie-terceirizacao`
  - `/transparencia/observabilidade-integracoes`
  - `/transparencia/catalogo-dados`
- Testes de resolução de contexto adicionados:
 - Testes de resolução de contexto adicionados:
  - `tests/Feature/TenantResolveMiddlewareTest.php`
  - cenários: resolução por subdomínio, por header e não-vazamento entre requisições.

## Escopo desta etapa

- Somente resolução e injeção de contexto no container.
- Sem troca automática de conexão/schema nesta entrega.
- Objetivo: preparar trilho de isolamento sem impacto regressivo no monólito atual.

## Próximo passo P6

- Aplicar `tenant.resolve` no grupo de rotas alvo (piloto controlado).
- Introduzir testes de isolamento cross-tenant antes de habilitar troca de banco/schema.

