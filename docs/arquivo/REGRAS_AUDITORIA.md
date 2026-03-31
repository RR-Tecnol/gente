# SISGEP — Regras do Modelo de Auditoria

## Papéis

- **Claude (auditor):** lê o código real via filesystem, identifica problemas
  com evidência de arquivo e linha, prepara planos de sprint com trechos prontos
  para executar. Nunca altera código.
- **Antigravity (executor):** executa as tasks do PLANO_SPRINTS.md no código.
  Atualiza os documentos de controle após cada execução validada pelo usuário.
- **Usuário (validador):** testa o resultado, confirma se funcionou,
  autoriza atualização dos documentos.

---

## Regra de atualização dos documentos

O Antigravity DEVE atualizar os documentos sempre que concluir uma task,
**somente após o usuário validar** que funcionou.

### Ao concluir uma task no PLANO_SPRINTS.md:
1. Marcar o item como `[x]` na checklist
2. Registrar a data no formato `✅ YYYY-MM-DD`
3. Se a task revelou algo inesperado, adicionar nota em `> Observação:`

### Ao concluir uma task que muda o estado de um módulo no MAPA_ESTADO_REAL.md:
1. Atualizar o status na tabela de módulos para ⏳ (ex: 🔒 → ⏳)
2. **Mover** o problema da seção "Problemas Ativos" para a seção
   "Resolvidos — Aguardando Verificação", registrando:
   - O que foi feito
   - Data da execução
3. **Não marcar como ✅** — a verificação final é feita pelo Claude
   na próxima sessão de auditoria, varrendo o código diretamente

### Nunca atualizar os documentos:
- Antes de o usuário validar o comportamento
- Parcialmente (se a task foi pela metade, registrar como parcial, não concluída)
- Com base em suposição (se não testou, não marca)

---

## Ciclo de vida de um problema

```
🔴 Ativo
  ↓  Antigravity executa + usuário valida comportamento
⏳ Resolvido — Aguardando Verificação
  ↓  Claude varre o código e confirma na próxima sessão
✅ Resolvido — Verificado
```

O status ⏳ significa: "o sistema se comportou corretamente, mas o código
ainda não foi verificado de forma independente pelo auditor."

Comportamento correto não garante código correto.
Código correto não garante ausência de bug de integração.
Por isso os dois checkpoints são necessários e não se substituem.

---

## Seções obrigatórias no MAPA_ESTADO_REAL.md

O documento deve sempre conter estas seções:

- **Problemas Ativos** — problemas confirmados no código, ainda não corrigidos
- **Resolvidos — Aguardando Verificação** — executado e validado pelo usuário, aguarda varredura do Claude
- **Resolvidos — Verificados** — confirmados por varredura direta do código pelo Claude

---

## Regra de início de sessão com o Claude

Ao iniciar uma nova sessão de auditoria, enviar ao Claude:
1. O arquivo `docs/MAPA_ESTADO_REAL.md` atualizado
2. O arquivo `docs/PLANO_SPRINTS.md` atualizado
3. Descrição do que mudou desde a última sessão

Isso garante que o Claude trabalha com estado real, não com suposições
ou memória de sessões anteriores.

---

## Estrutura dos documentos

```
sisgep-job-main/
  docs/
    MAPA_ESTADO_REAL.md    ← Camada 1: estado atual do código
    PLANO_SPRINTS.md       ← Camada 2: tarefas, status, histórico
    REGRAS_AUDITORIA.md    ← Este arquivo
```

---

## Princípio fundamental

O documento reflete o código.
Se o documento diz ✅ e o código está quebrado, o documento está errado.
A atualização só acontece após validação real — nunca após execução apenas.
