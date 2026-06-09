# BRIEFING ANTYGRAVITY — Fix typo SQL: UNIDADE_ATIVO → UNIDADE_ATIVA

**Data:** 09/05/2026
**Branch:** `producao-pmsl`
**Tipo:** Bug fix de typo SQL (1 arquivo, 1 linha, sem mudança de lógica)

## Sintoma

Múltiplas telas do GENTE v3 retornam erro 500 ao carregar (Folha de Pagamento, Orçamento, Escala de Trabalho, Medicina Admin, Exoneração, e provavelmente outras). No log do servidor (`storage/logs/laravel.log`) aparece o erro raiz:

```
[2026-05-08 23:47:52] production.ERROR: SQLSTATE[42S22]: 
[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]
Invalid column name 'UNIDADE_ATIVO'. 
(SQL: select [UNIDADE_ID], [UNIDADE_NOME] from [UNIDADE] 
where [UNIDADE_ATIVO] = 1 order by [UNIDADE_NOME] asc)

#6 /var/www/sistemagente.com/routes/api_v3_auth_part1.php(447): get()
"userId":1
```

## Causa raiz

Typo na rota `/api/v3/secretarias` em `routes/api_v3_auth_part1.php` linhas ~443-450. A query usa `UNIDADE_ATIVO` (masculino, com `O`), mas a coluna real do banco da PMSL é `UNIDADE_ATIVA` (feminino, com `A`). Confirmado pelo phpDoc do Model `app/Models/Unidade.php` linha 19 e pelos `protected $fillable` linha 36.

Outros 3 arquivos (`DaviSupremoSeeder.php`, `TestPersonasSeeder.php`, `RbacResolver.php`) já têm proteção defensiva tipo `if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) { ... } elseif (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) { ... }`. Só o `api_v3_auth_part1.php` está hardcoded sem fallback.

A rota `/api/v3/secretarias` é consumida pelo `FolhaPagamentoView` e provavelmente por dropdowns de unidade em várias outras telas. Como o backend retorna 500, o front renderiza erro genérico — mesmo que a tela em si não tenha bug.

## Tarefa única: corrigir 1 linha em 1 arquivo

**Arquivo:** `routes/api_v3_auth_part1.php`

**Localizar (linhas ~443-450):**

```php
    Route::get('/secretarias', function () {
        return response()->json([
            'unidades' => \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ATIVO', 1)
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME'])
        ]);
    });
```

**Substituir por (mesmo padrão defensivo dos outros 3 arquivos do projeto):**

```php
    Route::get('/secretarias', function () {
        $col = \Illuminate\Support\Facades\Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')
            ? 'UNIDADE_ATIVA'
            : (\Illuminate\Support\Facades\Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO') ? 'UNIDADE_ATIVO' : null);
        $q = \Illuminate\Support\Facades\DB::table('UNIDADE');
        if ($col) {
            $q->where($col, 1);
        }
        return response()->json([
            'unidades' => $q->orderBy('UNIDADE_NOME')->get(['UNIDADE_ID', 'UNIDADE_NOME']),
        ]);
    });
```

**Por que não simplesmente trocar `'UNIDADE_ATIVO'` por `'UNIDADE_ATIVA'`:** porque o projeto suporta múltiplos clientes com schemas legados diferentes (ver os 3 outros arquivos com `if/elseif`). PMSL tem `UNIDADE_ATIVA`, mas outros municípios futuros podem ter `UNIDADE_ATIVO`. Manter o padrão defensivo do projeto.

## Validação

```bash
php -l routes/api_v3_auth_part1.php
```

Esperado: `No syntax errors detected`.

Não precisa rodar build de frontend — é só PHP.

## NÃO FAZER

- ❌ NÃO mudar nenhum outro arquivo `.php`
- ❌ NÃO mudar nenhum arquivo Vue
- ❌ NÃO mexer em outros seletores ou rotas
- ❌ NÃO rodar `composer install/update`
- ❌ NÃO mudar o Model `app/Models/Unidade.php` (ele já está correto, usa `UNIDADE_ATIVA`)

## Commit

`fix(api): /secretarias usa UNIDADE_ATIVA com fallback defensivo (alinha com Davi/TestPersonas/RbacResolver)`

## Push

```bash
git push origin producao-pmsl
```

NÃO mexer em master, NÃO usar `--force`.

## Reportar para Ronaldo

1. SHA do commit
2. Output do `php -l`
3. Confirmação de que mudou APENAS `routes/api_v3_auth_part1.php`
