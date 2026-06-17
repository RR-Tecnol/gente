# PROMPT ANTYGRAVITY — FASE 6 SYNC GIT (Branch producao-pmsl)

> **Cole este prompt no Antygravity ANTES do prompt de deploy.**
> Estimativa: ~30-45 min Antygravity (auditoria + commits temáticos + push).
> **CRÍTICO:** Não tocar em `master`. Não fazer `git push --force`. Não commitar `.env` real.
> **Toda a operação é LOCAL no PC** em `C:\Users\joaob\Desktop\sisgep-job-main\gente`. Nada no servidor de produção ainda.

---

## CONTEXTO

A pasta de trabalho `C:\Users\joaob\Desktop\sisgep-job-main\gente` está em estado avançado:

- **Branch atual:** `auditoria-gente`
- **Commits locais não pushados:** 34
- **Arquivos modificados:** ~120
- **Arquivos untracked (novos):** ~250+
- **Trabalho representado:** Fases 1, 2-A, 2-B, 3, 4, 5 inteirinhas
- **GitHub `RR-Tecnol/gente` master:** parado em abril, sem nenhuma das fases novas

**Objetivo:** consolidar todo esse trabalho em um branch dedicado `producao-pmsl` no GitHub, com commits temáticos e tag de rollback. Master permanece intocado.

**Por que branch dedicada:** o sistema GENTE será comercializado para outras prefeituras (Aracaju já está no roadmap). Cada município terá seu próprio branch de produção (`producao-aracaju`, `producao-floriano`, etc).

---

## REGRAS CRÍTICAS

1. **NUNCA** rodar `git push --force` ou `git push --force-with-lease`.
2. **NUNCA** commitar arquivo `.env` (validar antes de cada commit).
3. **NUNCA** sobrescrever `master`.
4. **NUNCA** colocar credenciais reais (senhas, API keys, chaves SSH) em arquivos commitados ou em mensagens de commit.
5. Se `git push` der conflito, **PARAR** e reportar.
6. Validar com `git status` antes e depois de cada operação.
7. **Trabalhar em ORDEM ESTRITA:** T-S1 → T-S9.

---

## T-S1 — Auditoria de segurança

```powershell
cd C:\Users\joaob\Desktop\sisgep-job-main\gente

# Verificar que .env real NAO esta no working tree (deve estar listado em .gitignore)
git check-ignore -v .env
# Esperado: .gitignore:5:.env	.env (significa que esta sendo ignorado)

# Buscar senhas hardcoded em arquivos a serem commitados
# (substitua os termos pelos placeholders se aparecer alguma coisa)
git status --porcelain | Select-String "^\?\?" | ForEach-Object {
    $f = ($_ -split "\?\? ")[1].Trim()
    if (Test-Path $f -PathType Leaf -and ($f -notmatch "(node_modules|vendor)")) {
        $c = Get-Content $f -Raw -ErrorAction SilentlyContinue
        if ($c -match "(?i)(password\s*=\s*['""][^'""]{8,}|api[_-]?key\s*=\s*['""][^'""]{16,})") {
            Write-Host "REVISAR: $f" -ForegroundColor Yellow
        }
    }
}
```

**Esperado:** vazio ou apenas falsos positivos óbvios (ex: arquivos `.example`).
**Se aparecer arquivo real com senha:** PARAR. Substituir credencial por placeholder antes de commitar.

---

## T-S2 — Branch dedicado

```powershell
git checkout auditoria-gente
git checkout -b producao-pmsl
git branch --show-current
# Esperado: producao-pmsl
```

---

## T-S3 — Commits temáticos

A ideia é fazer **13 commits temáticos** ao invés de 1 mega-commit.

**ESTRATÉGIA:** ao invés de listar arquivo a arquivo, agrupar por diretório/tipo. Antygravity, use sua inteligência: olhe o `git status`, agrupe os arquivos modificados/untracked por feature lógica e crie commits descritivos.

**Sugestão de agrupamento:**

| Commit | Foco | Diretórios principais |
|---|---|---|
| chore | scripts ad-hoc + .gitignore | `scripts-debug/`, `.gitignore` |
| feat(fase1-seguranca) | PII LGPD + audit chain + honeytokens | migrations PII/honeytoken, app/Casts, GenteSecurePiiCommand |
| feat(fase2-motor-folha) | Jornada ledger + parametros | migrations jornada, app/Services/Jornada, config/jornada.php |
| feat(fase3-pccv) | PCCV + Progressao + RPPS | migrations progressao/rpps, app/Domain, app/Services/Pccv |
| feat(fase4-esocial) | eSocial DLQ + saude/seguranca + treinamentos | migrations esocial/exame/seguranca/treinamento |
| feat(fase4t8-fase5) | CNAB + parametros + escala v3 | migrations cnab/parametro/escala, app/Domain/Escala |
| feat(fase5-rbac-shadow) | RBAC matricial + Shadow Engine | migrations rbac/shadow, app/Services/Shadow, configs |
| feat(ops-tooling) | comandos artisan ops | app/Console/Commands |
| feat(api-v3-seguranca) | controllers V3 + middlewares seg | app/Http/Controllers/Api/V3, app/Http/Middleware |
| feat(seeders) | seeders catalogo + RBAC matrix | database/seeders, database/data, database/rbac |
| feat(tests-deploy) | tests + scripts deploy + frontend | tests/, scripts/, resources/gente-v3 |
| docs | auditorias + prompts Antygravity | docs/ |
| feat(misc) | restante (mobile, modificados, configs) | tudo que sobrar |

**REGRA DE OURO:** após cada commit, rodar `git status` e validar.

**Exemplo de commit message:**

```
feat(fase1-seguranca): PII LGPD + audit log assinado + honeytokens

- Migrations PII (CPF blind index HMAC), audit log encadeado, honeytokens + IP blocklist
- Casts PiiCpf, comandos gente:secure-pii e gente:audit-verify-chain
- Suporte a config GENTE_PII_BLIND_SALT (.env do servidor)
```

---

## T-S4 — Validação intermediária

```powershell
git status
git log --oneline producao-pmsl ^master
```

**Reportar:**
- Total de commits criados: ___ (esperado ~13)
- Working tree clean (ou só `.env`/`vendor`/`node_modules`): SIM/NÃO

---

## T-S5 — Tag de rollback

```powershell
git tag -a pre-fase6-deploy-pmsl -m "Estado anterior ao deploy Fase 6 PMSL — rollback target

Branch: producao-pmsl
Servidor alvo: gente-prod (KVM 8 Hostinger, 2.24.87.95)
Dominio: sistemagente.com
Municipio: Sao Luis/MA (PMSL)"

git tag -l pre-fase6-deploy-pmsl
```

---

## T-S6 — Push pro GitHub

```powershell
git push origin producao-pmsl
git push origin pre-fase6-deploy-pmsl
```

**Se rejected (non-fast-forward):** PARAR e reportar.
**Se Permission denied:** PARAR e reportar.

---

## T-S7 — Validação final no remote

```powershell
git ls-remote --heads origin producao-pmsl
git ls-remote --tags origin pre-fase6-deploy-pmsl
git log --oneline origin/master..origin/producao-pmsl | Measure-Object -Line
```

**Reportar URLs:**
- Branch: `https://github.com/RR-Tecnol/gente/tree/producao-pmsl`
- Tag: `https://github.com/RR-Tecnol/gente/releases/tag/pre-fase6-deploy-pmsl`

---

## T-S8 — Auditoria de segurança final

```powershell
# Confirmar que .env nao foi para o branch
git ls-tree -r origin/producao-pmsl | Select-String "\.env$"
# Esperado: vazio (apenas .env.example e .env.docker)

# Validar que master continua em abril
git log --oneline origin/master | Select-Object -First 3
```

**Se aparecer `.env` no remote, PARAR IMEDIATAMENTE.**

---

## T-S9 — Report final

```
═══════════════════════════════════════════════════════════════════
FASE 6 SYNC GIT — REPORT
═══════════════════════════════════════════════════════════════════

T-S1 Auditoria seguranca: OK / FALHOU
T-S2 Branch producao-pmsl criado: SIM/NAO
T-S3 Commits criados: ___ (esperado ~13)
T-S4 Working tree clean: SIM/NAO
T-S5 Tag pre-fase6-deploy-pmsl: SIM/NAO
T-S6 Push branch: OK/FALHOU
T-S6 Push tag: OK/FALHOU
T-S7 Validacao remote: OK/FALHOU
T-S8 .env vazado no remote: SIM/NAO (esperado: NAO)
T-S8 Master intocado: SIM/NAO

URLs:
- Branch: https://github.com/RR-Tecnol/gente/tree/producao-pmsl
- Tag:    https://github.com/RR-Tecnol/gente/releases/tag/pre-fase6-deploy-pmsl

PROBLEMAS:
___

PRONTO PARA FASE 6 DEPLOY (próximo prompt): SIM / NÃO
═══════════════════════════════════════════════════════════════════
```

---

## CONSIDERAÇÕES FINAIS

1. **Próximo passo:** deploy do código no servidor PMSL (modo copy-paste, Ronaldo no SSH).
2. **Master fica congelado em abril** como referência histórica.
3. **Quando começar Aracaju:** criar `producao-aracaju` a partir de master ou auditoria-gente.
4. **Nunca mergear `producao-pmsl` em `master`** sem revisão.

**FIM DO PROMPT.**
