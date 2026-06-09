# BRIEFING ANTYGRAVITY — P1.2 (b) Fix EventosBaseSeeder

**Data:** 09/05/2026
**Branch:** `producao-pmsl` (NÃO mexer em master, NÃO usar `--force`)
**Tipo:** Bug fix de schema drift em seeder (1 arquivo)

## Contexto

O `EventosBaseSeeder` é executado **manualmente antes da primeira folha real** (não está no `DatabaseSeeder`). Ele alimenta o catálogo de eventos básicos (rubricas) que o `MotorFolhaService` espera encontrar.

Inspeção do schema real da tabela `EVENTO` em produção PMSL via `INFORMATION_SCHEMA.COLUMNS`:

```
EVENTO_ID          (int, NOT NULL)
EVENTO_NOME        (nvarchar, NULL)        ← coluna real é _NOME
EVENTO_TIPO        (nvarchar, NULL)
EVENTO_IMPOSTO     (int, NOT NULL)
EVENTO_INCIDENCIA  (int, NULL)
EVENTO_SISTEMA     (int, NOT NULL)
EVENTO_SALARIO     (int, NOT NULL)
EVENTO_CODIGO      (nvarchar, NULL)
EVENTO_CATEGORIA   (nvarchar, NULL)
EVENTO_INCIDE_INSS (bit, NOT NULL)         ← NOT NULL, não setado
EVENTO_INCIDE_IRRF (bit, NOT NULL)         ← NOT NULL, não setado
EVENTO_INCIDE_RPPS (bit, NOT NULL)         ← NOT NULL, não setado
EVENTO_ATIVO       (bit, NOT NULL)
```

## Causa raiz

Dois bugs no seeder atual:

1. Usa `EVENTO_DESCRICAO` na chave do `updateOrInsert` — coluna real é `EVENTO_NOME`
2. **Não preenche** `EVENTO_INCIDE_INSS`, `EVENTO_INCIDE_IRRF`, `EVENTO_INCIDE_RPPS` que são `NOT NULL`. O insert falharia com `Cannot insert the value NULL into column 'EVENTO_INCIDE_INSS'`.

## Tarefa única: corrigir 1 arquivo

**Arquivo:** `database/seeders/EventosBaseSeeder.php`

**Substituir o conteúdo INTEIRO do arquivo por:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dos eventos básicos usados pelo MotorFolhaService.
 * Idempotente: usa updateOrInsert pelo nome do evento.
 *
 * Defensivo:
 *  - Detecta nome da coluna de descrição (EVENTO_NOME ou EVENTO_DESCRICAO)
 *  - Só preenche colunas que existem no schema
 *  - Sempre preenche colunas NOT NULL com defaults seguros
 */
class EventosBaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('EVENTO')) {
            $this->command->warn('Tabela EVENTO não existe — seeder ignorado.');
            return;
        }

        // Detecta nome da coluna principal (PMSL: EVENTO_NOME, legado: EVENTO_DESCRICAO)
        $colNome = Schema::hasColumn('EVENTO', 'EVENTO_NOME')
            ? 'EVENTO_NOME'
            : (Schema::hasColumn('EVENTO', 'EVENTO_DESCRICAO') ? 'EVENTO_DESCRICAO' : null);

        if (!$colNome) {
            $this->command->error('EVENTO sem coluna de nome — seeder abortado.');
            return;
        }

        // Detecta colunas opcionais
        $temIncidencia = Schema::hasColumn('EVENTO', 'EVENTO_INCIDENCIA');
        $temSistema    = Schema::hasColumn('EVENTO', 'EVENTO_SISTEMA');
        $temIncideINSS = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_INSS');
        $temIncideIRRF = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_IRRF');
        $temIncideRPPS = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_RPPS');

        // Eventos básicos: nome, salario, imposto, incide_inss, incide_irrf, incide_rpps
        $eventos = [
            // Proventos C1
            ['nome' => 'VENCIMENTO BASE',            'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],
            ['nome' => 'ANUENIO',                    'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],

            // Descontos previdenciários
            ['nome' => 'INSS RPPS',                  'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],
            ['nome' => 'INSS RGPS',                  'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],

            // Imposto de renda
            ['nome' => 'IRRF',                       'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],

            // Outros descontos
            ['nome' => 'CONSIGNACOES',               'salario' => 0, 'imposto' => 0, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],
            ['nome' => 'COMPLEMENTO SALARIO MINIMO', 'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],
        ];

        foreach ($eventos as $e) {
            $payload = [
                'EVENTO_SALARIO' => $e['salario'],
                'EVENTO_IMPOSTO' => $e['imposto'],
                'EVENTO_ATIVO'   => 1,
            ];

            if ($temSistema)    $payload['EVENTO_SISTEMA']     = 1;
            if ($temIncidencia) $payload['EVENTO_INCIDENCIA']  = 0;
            if ($temIncideINSS) $payload['EVENTO_INCIDE_INSS'] = $e['inss'];
            if ($temIncideIRRF) $payload['EVENTO_INCIDE_IRRF'] = $e['irrf'];
            if ($temIncideRPPS) $payload['EVENTO_INCIDE_RPPS'] = $e['rpps'];

            DB::table('EVENTO')->updateOrInsert(
                [$colNome => $e['nome']],
                $payload
            );
        }

        $this->command->info('EventosBaseSeeder: ' . count($eventos) . ' eventos básicos garantidos.');
    }
}
```

## Decisões de domínio (incidências fiscais)

Os valores de `_INCIDE_INSS`, `_INCIDE_IRRF`, `_INCIDE_RPPS` foram preenchidos seguindo a regra prática brasileira:

- **Vencimento Base / Anuênio / Complemento Salário Mínimo**: tudo incide (proventos tributáveis)
- **INSS RPPS / INSS RGPS / IRRF**: nada incide (são os próprios descontos)
- **Consignações**: nada incide (descontos voluntários, não tributáveis)

Se houver regra fiscal específica diferente para PMSL (por exemplo, anuênio que não compõe base de RPPS em algum município), Ronaldo deve revisar e ajustar manualmente após o seed inicial. Esses são apenas defaults seguros para o motor não quebrar.

## Validação

```bash
php -l database/seeders/EventosBaseSeeder.php
```

Esperado: `No syntax errors detected`.

## NÃO FAZER

- ❌ NÃO mudar nenhum outro arquivo
- ❌ NÃO mudar `database/seeders/DatabaseSeeder.php`
- ❌ NÃO criar migration
- ❌ NÃO rodar o seeder após o commit (Ronaldo executa manualmente quando for processar a primeira folha)
- ❌ NÃO mexer no `app/Services/Folha/PersistenciaRubricasService.php` (que faz referência a este seeder em comentário)

## Commit

`fix(seeder): EventosBaseSeeder usa EVENTO_NOME defensivo + preenche colunas NOT NULL de incidência`

## Push

```bash
git push origin producao-pmsl
```

## Reportar para Ronaldo

1. SHA do commit
2. Output do `php -l database/seeders/EventosBaseSeeder.php`
3. `git diff HEAD~1 -- database/seeders/EventosBaseSeeder.php`
4. `git show --stat HEAD` confirmando que apenas esse 1 arquivo foi tocado
