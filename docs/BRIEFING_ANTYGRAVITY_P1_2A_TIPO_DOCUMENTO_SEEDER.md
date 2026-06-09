# BRIEFING ANTYGRAVITY — P1.2 (a) Fix TipoDocumentoSeeder

**Data:** 09/05/2026
**Branch:** `producao-pmsl` (NÃO mexer em master, NÃO usar `--force`)
**Tipo:** Bug fix de schema drift em seeder (1 arquivo)

## Contexto

O `TipoDocumentoSeeder` está pendente desde D5. Ele trava ao executar com erro tipo:
`SQLSTATE[42S22]: Invalid column name 'TIPO_DOCUMENTO_DESCRICAO'`.

Inspeção do schema real em produção PMSL via `INFORMATION_SCHEMA.COLUMNS` confirmou:

```
TIPO_DOCUMENTO_ID    (int, NOT NULL)
TIPO_DOCUMENTO_NOME  (nvarchar, NOT NULL)
TIPO_DOCUMENTO_ATIVO (int, NOT NULL)
```

## Causa raiz

São DOIS bugs no mesmo seeder:

1. Usa `TIPO_DOCUMENTO_DESCRICAO` em todas as 21 linhas — coluna real é `TIPO_DOCUMENTO_NOME`
2. Usa `TIPO_DOCUMENTO_OBRIGATORIO` em todas as 21 linhas — coluna **NÃO EXISTE** no schema produção

O segundo bug é mais grave: até trocar `_DESCRICAO` por `_NOME`, o seeder ainda vai falhar porque vai tentar inserir numa coluna inexistente.

## Tarefa única: corrigir 1 arquivo

**Arquivo:** `database/seeders/TipoDocumentoSeeder.php`

**Substituir o conteúdo INTEIRO do arquivo por:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoDocumentoSeeder extends Seeder {
    public function run() {
        if (!Schema::hasTable('TIPO_DOCUMENTO')) {
            $this->command->warn('Tabela TIPO_DOCUMENTO não existe — seeder ignorado.');
            return;
        }

        // Detecta se a coluna de nome é TIPO_DOCUMENTO_NOME (PMSL atual) ou
        // TIPO_DOCUMENTO_DESCRICAO (compatibilidade com schemas legados).
        $colNome = Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_NOME')
            ? 'TIPO_DOCUMENTO_NOME'
            : (Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_DESCRICAO') ? 'TIPO_DOCUMENTO_DESCRICAO' : null);

        if (!$colNome) {
            $this->command->error('TIPO_DOCUMENTO sem coluna de nome (NOME ou DESCRICAO) — seeder abortado.');
            return;
        }

        // TIPO_DOCUMENTO_OBRIGATORIO é opcional: só seta se a coluna existir.
        $temObrigatorio = Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_OBRIGATORIO');

        $tipos = [
            ['nome' => 'RG',                                                    'ativo' => 1, 'obrigatorio' => 1],
            ['nome' => 'CPF',                                                   'ativo' => 1, 'obrigatorio' => 1],
            ['nome' => 'CNH',                                                   'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE HABILITAÇÃO',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CRA',                                                   'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE TRABALHO',                                  'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CERTIDÃO NEGATIVA DE ANTECEDENTES CRIMINAIS',           'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA',                                               'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'RG2',                                                   'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'IDENTIDADE',                                            'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'COMPROVANTE DE RESIDÊNCIA COM NÚMERO DO CEP',           'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA DE ENSINO MÉDIO',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA DE ENSINO SUPERIOR',                            'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE TRABALHO (1ª FOLHA FRENTE E VERSO)',        'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE CONSELHO DE CLASSE(REGISTRO PROFISSIONAL)', 'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'TÍTULO DE ELEITOR',                                     'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'PIS/PASEP/NIT',                                         'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CERTIDÃO NASCIMENTO OU CASAMENTO',                      'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CONTA BANCÁRIA (CARTÃO)',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'ANTECEDENTE CRIMINAL',                                  'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'REGISTRO DO CONSELHO',                                  'ativo' => 1, 'obrigatorio' => 0],
        ];

        foreach ($tipos as $t) {
            $payload = [
                'TIPO_DOCUMENTO_ATIVO' => $t['ativo'],
            ];
            if ($temObrigatorio) {
                $payload['TIPO_DOCUMENTO_OBRIGATORIO'] = $t['obrigatorio'];
            }
            DB::table('TIPO_DOCUMENTO')->updateOrInsert(
                [$colNome => $t['nome']],
                $payload
            );
        }

        $this->command->info('TipoDocumentoSeeder: ' . count($tipos) . ' tipos garantidos.');
    }
}
```

## Por que essa estrutura

- **Idempotente** com `updateOrInsert` em vez de `insert` (pode rodar várias vezes sem duplicar)
- **Defensivo** — usa `Schema::hasColumn` pra detectar ambos nomes possíveis (`_NOME` ou `_DESCRICAO`)
- **Compatível** com schemas legados de outros municípios futuros (mesmo padrão de `DaviSupremoSeeder` e `TestPersonasSeeder` no projeto)
- **Não trava** se `TIPO_DOCUMENTO_OBRIGATORIO` não existir — só pula essa coluna
- **Logs claros** com `command->info` e `command->error` (já que outros seeders do projeto têm esse padrão)

## Validação

```bash
php -l database/seeders/TipoDocumentoSeeder.php
```

Esperado: `No syntax errors detected`.

## NÃO FAZER

- ❌ NÃO mudar nenhum outro arquivo
- ❌ NÃO mudar `database/seeders/DatabaseSeeder.php` (este seeder é manual, não é chamado automaticamente)
- ❌ NÃO criar migration nova (a coluna `TIPO_DOCUMENTO_OBRIGATORIO` não está faltando — ela simplesmente não existe nesse schema, e a versão defensiva trata isso)
- ❌ NÃO rodar o seeder após o commit (Ronaldo executa manualmente em produção)

## Commit

`fix(seeder): TipoDocumentoSeeder usa TIPO_DOCUMENTO_NOME defensivo + condicional TIPO_DOCUMENTO_OBRIGATORIO`

## Push

```bash
git push origin producao-pmsl
```

## Reportar para Ronaldo

1. SHA do commit
2. Output do `php -l database/seeders/TipoDocumentoSeeder.php`
3. `git diff HEAD~1 -- database/seeders/TipoDocumentoSeeder.php` (mostrar diff)
4. `git show --stat HEAD` confirmando que apenas esse 1 arquivo foi tocado
