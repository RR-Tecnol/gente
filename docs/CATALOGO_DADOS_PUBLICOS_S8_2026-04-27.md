# S8 — Catálogo de dados públicos (2026-04-27)

## Objetivo

Formalizar fontes públicas, campos permitidos e restrições LGPD para transparência ativa.

## Fontes publicadas

1. `GET /api/v3/transparencia/dossie-terceirizacao`
   - Campos: nome, função, empresa, contrato, secretaria, setor, CPF mascarado.
   - Restrição: sem CPF integral e sem remuneração individual nominal.

2. `GET /api/v3/transparencia/observabilidade-integracoes`
   - Campos: timestamp e métricas agregadas operacionais.
   - Restrição: sem identificação pessoal.

3. `GET /api/v3/transparencia/catalogo-dados`
   - Endpoint de metadados com versão de catálogo e regras de publicação.

## Governança

- Revisão trimestral do catálogo por jurídico/controladoria/tecnologia.
- Mudança de campos públicos exige registro de decisão e atualização de versão.
