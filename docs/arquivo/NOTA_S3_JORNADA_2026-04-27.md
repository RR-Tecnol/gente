# S3 — Jornada / frequência (fase 1 entregue, 2026-04-27)

## S3.1 Parâmetros com vigência

- `config/jornada.php` com defaults e `env()` opcional.
- Tabela `JORNADA_REGRA_PARAM` + seed inicial (`database/migrations/2026_04_27_120000_*` e `120001_*`).
- Serviço `App\Services\Jornada\JornadaRegraParametros` lê a tabela (vigência aberta ou intervalo) e cai no `config` se não houver linha.

## S3.2 Sobreaviso (acionamento)

- `POST /api/v3/sobreaviso/acionamento`: valida duração ≤ teto (24h padrão); valor sugerido = `duração × valor_hora_referencia × (1/3)` (substitui o `74.0` fixo antigo).
- `GET /api/v3/sobreaviso`: inclui `parametros_jornada` (teto, fração, valor hora ref).

> **Pendência de produto:** amarrar `valor_hora_referencia_rs` a rubrica/vínculo real; convênio e folha (eSocial) continuam fora desta fase.

## S3.3 DSR / banco (decaimento)

- Comando `php artisan jornada:banco-horas-decaimento` — modo relatório; mutação ainda **não** implementada (depende regra SEMAD).
- Agendamento mensal `03:00` com `--dry-run` e `withoutOverlapping` (Kernel).

## S3.4 Scheduler

- Entrada de rotina agendada para banco de horas (inventário); ponto/DSR adicionais podem seguir o mesmo padrão.

## Teste

- `tests/Unit/JornadaRegraParametrosPureTest.php` — fórmula 1/3 sem boot Laravel.

## Deploy

- Correr `php artisan migrate` para criar/semear `JORNADA_REGRA_PARAM`.
