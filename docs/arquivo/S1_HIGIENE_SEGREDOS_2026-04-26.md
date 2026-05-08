# Higiene de segredos e repositório (S1.3) — 2026-04-26

## O que o código **deve** fazer

- Ler segredos via `env('...')` (ex.: `RECAPTCHA_SECRET_KEY` em `web.php` — nunca valor literal em repositório).
- Manter `.env` fora de commits (raiz do projeto Laravel).
- Não versionar dumps de banco, credenciais de integração, certificados A1.

## Itens a rever periodicamente

```bash
cd gente
git grep -n "RECAPTCHA_SECRET" -- ':!vendor'  # deve mostrar só env(), nunca chave colada
git grep -n "password\s*=>" -- ':!vendor' ':!docs/arquivo' ':!*baseline*' ':!*route-list*' 
```

## Artefatos de desenvolvimento

- Dados em `storage/debugbar/` (quando o Debugbar estiver ativo) não devem ir para o remoto. Se ficheiros `storage/debugbar/*.json` aparecerem em `git status`, remover do tracking e reforçar ignore no repositório.

## Aceite S1.3 (declaratório)

- [ ] Nenhum segredo de produção identificado em ficheiros rastreados.
- [ ] Política de ignoral documentada (este ficheiro + revisão do time).
