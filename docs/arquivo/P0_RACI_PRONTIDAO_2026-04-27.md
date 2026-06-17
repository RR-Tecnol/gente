# P0 — Matriz RACI de prontidão (2026-04-27)

## Papéis

- **SEMAD**: dono funcional RH/folha/jornada
- **IPAM**: dono funcional RPPS/prova de vida
- **SEMFAZ**: dono de impactos fiscais e validação financeira
- **CGM**: controle e conformidade de processo
- **SI/TI**: segurança, infraestrutura e continuidade
- **Equipe GENTE (RRTecnol)**: implementação técnica, suporte e evidências

## RACI por trilha

| Trilha | R | A | C | I |
|---|---|---|---|---|
| Baseline matemática de folha (P3) | Equipe GENTE | SEMAD | SEMFAZ, CGM | SI/TI, IPAM |
| Prova de vida RPPS e bloqueios | Equipe GENTE | IPAM | SEMAD, SEMFAZ | CGM, SI/TI |
| Transparência/LGPD (dossiê) | Equipe GENTE | SEMAD | PGM, CGM, SI/TI | IPAM, SEMFAZ |
| Segurança plataforma/LTS | Equipe GENTE | SI/TI | SEMAD | CGM, IPAM, SEMFAZ |
| Deploy/rollback produção | Equipe GENTE, SI/TI | SI/TI | SEMAD | CGM, IPAM, SEMFAZ |
| Certificação go-live | Equipe GENTE | Comitê (SEMAD/IPAM/SEMFAZ/CGM/SI) | PGM | Operação |

## Regra de decisão

Nenhum gate crítico é considerado concluído sem aprovação explícita do responsável **A** e evidência técnica anexada.
