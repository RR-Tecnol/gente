# Governança educacional e fiscal — São Luís (âncora para o GENTE v3)

**Objetivo:** servir de referência rápida para auditoria de código e alinhamento com a Lei Orgânica do Município e o PCCV do magistério, sem duplicar o texto longo do tratado.

## Onde está o “Tratado Analítico” completo

O texto analítico sobre governança educacional, SISFOLHA, MDE, improbidade e TCE-MA encontra-se **integralmente** em:

- `gente/docs/BUSINESS_RULES.md` — secção **“Tratado Analítico sobre a Governança Educacional em São Luís…”** (a partir da linha em que essa secção inicia, após as *Regras de Ouro*).

As **regras de implementação** operacionais (glossário SEMED/SISFOLHA, hierarquia legal, 25% MDE, Lei 4.928/2008) estão no **início** do mesmo ficheiro.

## Mapa normativo (resumo)

| Âncora | Tema | Exigência para sistemas (GENTE) |
|--------|------|--------------------------------|
| **LO arts. 135–136** | Oferta e gratuidade | Planejamento da rede e rastreio de alocação de serviço (educação como dever do Município). |
| **LO art. 136 §3º** | Conjuntos habitacionais + escolas | Unidades e setores cadastráveis alinhados ao organograma oficial. |
| **LO art. 139** | Mínimo 25% receitas de impostos em MDE | **Não** é calculado no módulo de Escala; exige módulo orçamentário / classificação de despesa e relatórios confrontando SISFOLHA. |
| **Lei nº 4.928/2008** | PCCV do magistério | Cargos, progressões e tabelas vinculadas a domínio normativo; ver `PCCV_DOMINIO` e `CARGO.PCCV_ID`. |
| **SISFOLHA (legado)** | Folha e lotação | Fonte de fidelidade para pagamento; GENTE deve manter trilha de coerência com lotação e vínculo ativo. |

## Controles de conformidade (checklist de produto)

1. **Integridade de pessoal em telas de escala:** listagens devem restringir a servidores com lotação ativa (e, quando existir, vínculo funcional ativo) — ver implementação em `routes/escala_trabalho.php` e regras em `BUSINESS_RULES.md`.
2. **Alterações sensíveis:** trilha de auditoria (`AUDIT_LOG`) e justificativa em alterações retroativas de escala — ver `routes/escala_trabalho.php` e UI `EscalaTrabalhoView.vue`.
3. **Diferenciação de regime (Magistério / Saúde / Geral):** domínio canónico `PCCV_DOMINIO` + atributos em `CARGO` / `FUNCIONARIO` conforme migrações e seeders oficiais.

## Relação com `.cursorrules`

Regras de stack, segurança e *brain path* do projeto: `gente/.cursorrules`. O estado **BLOQUEADO PARA PRODUÇÃO** (rotas sem `auth`, credenciais, etc.) afeta **governança e responsabilidade do gestor** até a Fase 0/1 de segurança.

## Autenticação e escopo (GENTE v3)

- O SPA (Vue) usa o **guard padrão de sessão** `auth` (middleware `web` + `auth` em `routes/web.php`); o equivalente a *token* Sanctum em APIs stateless seria outro *stack* — o que importa é **não** expor escrita de escala/substituição sem `USUARIO_ID` rastreável.
- **Escala** e **substituição** restringem por `USUARIO_UNIDADE` (setores da secretaria), exceto perfis **Desenvolvedor/Administrador** (visão global), via `App\Support\UnidadeEscopoUsuario`.
- `App\Support\GenteAuditWriter::requireAuthenticatedUserId()` força `USUARIO_ID` em operações de escala que gravam `AUDIT_LOG`.

---

*Documento de apoio à auditoria técnica e jurídica; não substitui parecer jurídico administrativo.*
