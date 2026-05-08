# P1 — Instalação de BCMath e cibersegurança (2026-04-27)

## É seguro instalar `php-bcmath`?

Sim, **desde que** a instalação siga o canal de software oficial da distribuição e o processo de mudança controlada do ambiente (homologação antes de produção).

- **O que é:** extensão padrão do ecossistema PHP para aritmética decimal; amplamente usada em sistemas financeiros.
- **Superfície de ataque:** baixa em relação a pacotes genéricos; não abre portas nem adiciona serviço de rede por si só.
- **Riscos reais a mitigar:** elevação de privilégio (`sudo`), supply chain do repositório, janela de manutenção e impacto em **outros** sites PHP no mesmo host (FPM compartilhado).

## Controles mínimos recomendados (cibersegurança)

1. **Fonte de pacote:** apenas repositórios oficiais do SO (Fedora/RHEL). Evitar RPMs de terceiros sem assinatura verificável.
2. **Janela e rollback:** instalar em homologação, validar preflight, só então produção; registrar rollback (snapshot/imagem ou release anterior).
3. **Princípio do menor privilégio:** quem executa `dnf` deve ser operação/TI com auditoria (ticket + log).
4. **Verificação pós-instalação:** `php -m`, preflight (veja abaixo), `php artisan gente:prontidao-certificar --json`.
5. **Hardening compartilhado:** se o servidor hospeda múltiplas aplicações PHP, planejar restart do FPM em janela controlada.

## Comando típico (Fedora)

```bash
sudo dnf install -y php-bcmath
sudo systemctl restart php-fpm
```

Ajuste o nome do serviço PHP conforme a imagem do servidor (`php-fpm`, `php82-php-fpm`, etc.).

## Onde rodar o preflight

O script fica em **`gente/scripts/`** (projeto Laravel). Na raiz do mono-repositório (`GENTE/`), use por exemplo:

```bash
cd gente && ./scripts/preflight_prontidao.sh
```

Se aparecer `arquivo ou diretório inexistente`, você está em outro diretório — confira com `pwd`.

## Evidência de desbloqueio

- `gente:prontidao-certificar` deve retornar `go_live_decisao=go` sem `BLOQ-P1-BCMATH`.
