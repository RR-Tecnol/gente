# GRAVITY — AVISOS IMPORTANTES (15/03/2026)
## Leia este arquivo ANTES de qualquer ação no Sprint 0

---

## COLISÕES RESOLVIDAS — não use documentos antigos

| Documento | Status |
|-----------|--------|
| docs/MAPA_ESTADO_REAL.md | ✅ Atualizado em 15/03/2026 — usar este |
| docs/SPRINT_0.md | ✅ Criado em 15/03/2026 — usar este |
| PLANO_SPRINTS.md (antigo) | ⚠️ Desatualizado — não usar para Sprint 0 |
| SPRINTS_GENTE_V3_2.md | ⚠️ Referência histórica apenas |

---

## CORREÇÕES CONFIRMADAS PELA VARREDURA DE 15/03/2026

| Item | Conclusão |
|------|-----------|
| IC-03 path '/' duplicado | ✅ FALSO POSITIVO — código está correto |
| IC-06 margem cartão | 🔴 CONFIRMADO EM 5% — bug real, corrigir no Sprint 2 |
| Nome do sistema | ✅ É GENTE — SISGEP foi primeira concepção |

---

## SPRINT 0 — APENAS 4 TASKS

Consulte docs/SPRINT_0.md para o plano completo.

Resumo:
- TASK-01: Mover auth para fora do isLocal() → routes/web.php
- TASK-02: Corrigir CORS → config/cors.php
- TASK-03: Corrigir .env → APP_URL + SESSION_DOMAIN
- TASK-05: Remover BOM UTF-8 → routes/progressao_funcional.php
- TASK-04: REMOVIDA — IC-03 era falso positivo

---

*Atualizado em 15/03/2026 após varredura completa do código*
