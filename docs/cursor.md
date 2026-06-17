# Cursor no GENTE — Guia Essencial

Este arquivo existe para garantir que o Cursor sempre use o Brain correto antes de qualquer tarefa.

## Objetivo

- economizar tokens;
- aumentar precisão;
- evitar retrabalho em bugs já mapeados.

## Vault Obsidian oficial (memória fixa)

- Caminho oficial do vault: `/home/DK/brain/Obsidian-Brain-v6/`
- Sempre que houver documentação de sprint, revisão, auditoria ou melhorias no GENTE, manter espelho neste vault quando o ambiente tiver acesso ao caminho.

## Leitura silenciosa obrigatória (antes de implementar)

### Projeto GENTE (Brain)
- `_Global/PROJETOS/RRTECNOL/GENTE/00-INDICE.md`
- `_Global/PROJETOS/RRTECNOL/GENTE/overview.gente.md`
- `_Global/PROJETOS/RRTECNOL/GENTE/auditorias/auditoria-unificada-2026-04-21.md`

### Global-for-every-project
- `_Global/GUIAS/IA/cursor.md`
- `_Global/Global-for-every-project/overview.universais.md`
- `_Global/Global-for-every-project/02-Prompts_Otimizados/economia-de-tokens.md`
- `_Global/Global-for-every-project/02-Prompts_Otimizados/prompt-base-claude-web.md`
- `_Global/Global-for-every-project/02-Prompts_Otimizados/prompt-nova-tela.md`
- `_Global/Global-for-every-project/02-Prompts_Otimizados/prompt-novo-componente.md`

## Fluxo recomendado no Cursor

1. Definir objetivo único e escopo.
2. Ler notas mínimas do Brain (acima).
3. Executar mudança cirúrgica.
4. Validar com evidência (código + endpoint + banco quando aplicável).
5. Atualizar documentação correspondente.

## Regra de ouro para bugs/segurança

- Não considerar “resolvido” sem evidência real.
- Frontend sozinho não valida segurança; backend precisa bloquear.
- Em caso de dúvida de contexto, voltar ao `00-INDICE`.

## Formatos de relatório

- `RELATORIO-SPRINT-[N]`
- `RELATORIO-COMPONENTE`
- `RELATORIO-EXECUCAO-CURTA`
