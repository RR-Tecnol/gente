# P1 — Baseline de Upgrade LTS (2026-04-27)

## Estado atual observado

- Runtime local: **PHP 8.4.20**.
- Framework instalado: **laravel/framework 8.x-dev**.
- Dependências com alertas de depreciação em execução Artisan:
  - `facade/flare-client-php`
  - `facade/ignition`
  - `barryvdh/laravel-ide-helper`

## Implicações para o plano P1

- O núcleo está acima do mínimo de PHP, mas o ecossistema de suporte ainda está em stack de Laravel 8.
- Antes do salto para Laravel 10 LTS, é necessário alinhar pacotes legados de erro/debug para evitar ruído e risco em homologação.
- A baseline matemática de P3 já está sendo preparada para replay e comparação pós-upgrade.

## Próximas ações objetivas (P1)

- Travar versão-alvo (Laravel 10 LTS + matriz de libs compatíveis).
- Atualizar ferramentas de erro/debug para versões suportadas no alvo.
- Rodar replay P3 antes/depois do upgrade usando `shadow:dispatch` + `shadow:relatorio-run`.

