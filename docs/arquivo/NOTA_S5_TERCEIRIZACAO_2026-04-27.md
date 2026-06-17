# S5 — Terceirização (fase 1 entregue, 2026-04-27)

## S5.4 Transparência / Dossiê

- `routes/transparencia.php`:
  - mascaramento de CPF aplicado nos CSVs de exportação/download (`maskCpfTransparencia`).
  - novo endpoint `GET /api/v3/transparencia/dossie-terceirizacao` com campos públicos mínimos:
    `nome`, `funcao`, `empresa`, `contrato`, `secretaria`, `setor`, `cpf_mascarado`.
- Não expõe remuneração individual no endpoint de dossiê terceirização.

## Observações

- O endpoint está no contexto `api/v3` atual (com middleware herdado do `web.php`).
- Para publicação totalmente aberta sem autenticação, depende da decisão institucional e ajuste de grupo de rotas.

## Próximos passos S5

- Fluxo de evidências (NF/CND/FGTS/GPS) vinculado ao contrato e medição com trilha de aprovação.
- Perfis e limites de domínio (`preposto`, `gestor_contrato`, `fiscal_tecnico`, `fiscal_administrativo`) com RBAC mais explícito.
- Estratégia de identidade do preposto (Gov.br/contingência) em ambiente de homologação.
