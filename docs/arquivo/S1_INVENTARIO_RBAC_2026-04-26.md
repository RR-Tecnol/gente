# Inventário RBAC / perfil (S1.1) — ponto de partida

O plano exige **403** em acessos indevidos. O GENTE dispõe de:

- Middleware `perfil` → `App\Http\Middleware\CheckPerfil` (lista de nomes de perfis normalizados).
- Grupo padrão `api/v3` com `auth` + `audit` em `routes/web.php`.

## Lacuna

Grande parte de `api/v3` **não** aplica `perfil:` por rota: qualquer autenticado acessa conforme a lógica interna de cada controller.

## Trabalho recomendado (incremental, por domínio)

1. **Inventariar** rotas/métodos com impacto (folha, pessoal, configuração, V2/V3).
2. **Definir** matriz perfil → capacidade (alinhada a negócio SEMAD).
3. **Aplicar** `->middleware('perfil:...')` em grupos estreitos e testar 403.
4. **Documentar** exceções (ex.: integrações machine-to-machine) e substitutos (token, IP allowlist).

## Rastreio mínimo (revisar periodicamente)

Comando (ajustar padrão conforme necessidade):

```bash
cd gente
rg "middleware\('perfil" routes/
```

---

**Estado 2026-04-26:** S1.1 aberto; este ficheiro substitui o “plano aprovado” do aceite S1 quando a matriz e os grupos forem preenchidos.
