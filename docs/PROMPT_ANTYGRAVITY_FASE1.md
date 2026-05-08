# PROMPT ANTYGRAVITY — FASE 1 GENTE v3 (correções pontuais bloqueadoras)

> **Cole este prompt inteiro no Antygravity (Cursor/Gemini).**
> Estimativa total: 2h45 a 3h15.
> Pré-condição: branch limpa, working tree sem alterações pendentes.
> Você (Antygravity) é o executor. O auditor é Claude (via MCP). Todo report seu deve conter saída REAL de PowerShell/Artisan, sem inventar.

---

## REGRAS CRÍTICAS DE EXECUÇÃO (LEIA ANTES DE COMEÇAR)

1. **NÃO mexer em `routes/web.php`.** Adições inline no bloco dashboard são bug recorrente — Fase 4 trata disso.
2. **Um commit por correção.** Mensagem padrão: `fix(R##): descrição curta`.
3. **Validar cada correção isoladamente** com o comando indicado antes de seguir para a próxima.
4. **Se algum trecho do código NÃO bater exatamente com o esperado neste prompt** (linhas mudaram, código já foi corrigido por outra branch), **PARAR e reportar** — não improvisar.
5. **NUNCA simular saída de PowerShell/Artisan.** Sempre executar e colar a saída real.
6. Trabalhar em ordem: T1.1 → T1.2 → T1.3 → T1.4 → T1.5 → T1.6 → T1.7 → T1.8.
7. Preferir **aspas simples PHP** ao invés de aspas duplas onde já existirem aspas simples — manter estilo do arquivo.

---

## T1.1 — R70: cast `'integer'` → `'decimal:2'` em Folha.php (1 min)

**Arquivo:** `app/Models/Folha.php`

**Trecho atual (linhas ~47-53):**

```php
    protected $casts = [
        'FOLHA_TIPO' => 'integer',
        'VINCULO_ID' => 'integer',
        'FOLHA_COMPETENCIA' => Periodo::class,
        'FOLHA_QTD_SERVIDORES' => 'integer',
        'FOLHA_VALOR_TOTAL' => 'integer',
    ];
```

**Trecho corrigido:**

```php
    protected $casts = [
        'FOLHA_TIPO' => 'integer',
        'VINCULO_ID' => 'integer',
        'FOLHA_COMPETENCIA' => Periodo::class,
        'FOLHA_QTD_SERVIDORES' => 'integer',
        'FOLHA_VALOR_TOTAL' => 'decimal:2',
    ];
```

**Validação:**

```powershell
php artisan tinker --execute="echo \App\Models\Folha::first()?->FOLHA_VALOR_TOTAL ?? 'sem registros';"
```
Saída esperada: string com 2 decimais (ex.: `1234.56`) ou `sem registros`. **NÃO pode** retornar inteiro truncado.

**Commit:** `fix(R70): FOLHA_VALOR_TOTAL cast decimal:2 (preserva centavos)`

---

## T1.2 — R72: remover path debug `/home/DK/...` em EscalaAusenciaService (3 min)

**Arquivo:** `app/Domain/Escala/EscalaAusenciaService.php`

**Problema:** Linha 12 contém path hardcoded de máquina dev:
```php
private const DEBUG_LOG_PATH = '/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log';
```

**Solução:** Tornar o método `debugLog` um **no-op silencioso em produção**. Não tentar substituir o path — o método era pra debug de cursor session e não precisa rodar mais.

**Edit 1 — Trocar a constante (linha ~12):**

```php
    private const DEBUG_LOG_PATH = '/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log';
```

por

```php
    // R72: path debug removido — log em arquivo só se DEBUG_LOG_PATH existir como diretório (no-op em produção)
    private const DEBUG_LOG_PATH = null;
```

**Edit 2 — Atualizar método `debugLog` (linhas ~301-318) para no-op se a constante for null:**

Trecho atual:
```php
    private static function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try {
            $payload = [
                'sessionId' => 'f94096',
                'runId' => 'runtime-http-validation',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            @file_put_contents(self::DEBUG_LOG_PATH, json_encode($payload, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // no-op em modo debug
        }
    }
```

Trecho corrigido:
```php
    private static function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        // R72: no-op em produção (DEBUG_LOG_PATH = null)
        if (self::DEBUG_LOG_PATH === null) {
            return;
        }

        try {
            $payload = [
                'sessionId' => 'f94096',
                'runId' => 'runtime-http-validation',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            @file_put_contents(self::DEBUG_LOG_PATH, json_encode($payload, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // no-op em modo debug
        }
    }
```

**Validação:**

```powershell
Select-String -Path "app/Domain/Escala/EscalaAusenciaService.php" -Pattern "/home/DK"
```
Saída esperada: **0 ocorrências** (apenas a constante agora é `null`).

```powershell
Get-ChildItem -Path "app" -Recurse -Filter "*.php" | Select-String -Pattern "/home/DK" | Measure-Object | Select-Object -ExpandProperty Count
```
Saída esperada: `0`.

**Commit:** `fix(R72): EscalaAusenciaService debugLog vira no-op em produção`

---

## T1.3 — R69: SQL injection em Lotacao::getDadosRelatorioImprimirLotacao (5 min)

**Arquivo:** `app/Models/Lotacao.php`

**Trecho atual (final do arquivo, método `getDadosRelatorioImprimirLotacao`):**

O método está vulnerável porque concatena `$lotacaoId` direto na string SQL (`WHERE L.LOTACAO_ID = $lotacaoId`) e depois passa para `DB::select(DB::raw($sql))`.

**Edit — Trocar o método inteiro:**

Trecho atual:
```php
    public static function getDadosRelatorioImprimirLotacao($lotacaoId)
    {
        $sql = "
        SELECT
            S.SETOR_NOME,
            U.UNIDADE_NOME,
            P.PESSOA_NOME,
            A.ATRIBUICAO_NOME,
            V.VINCULO_SIGLA,
            L.LOTACAO_DATA_INICIO,
            P.PESSOA_RG_NUMERO AS RG,
            P.PESSOA_CPF_NUMERO AS CPF,
            P.PESSOA_ENDERECO,
            P.PESSOA_COMPLEMENTO,
            B.BAIRRO_NOME,
            C.CIDADE_NOME,
            CEL.CONTATO_CONTEUDO AS CELULAR,
            TEL.CONTATO_CONTEUDO AS TEL,
            EMAIL.CONTATO_CONTEUDO AS EMAIL

        FROM LOTACAO L
        INNER JOIN SETOR S ON S.SETOR_ID = L.SETOR_ID
        INNER JOIN UNIDADE U ON U.UNIDADE_ID = S.UNIDADE_ID
        INNER JOIN FUNCIONARIO F ON F.FUNCIONARIO_ID = L.FUNCIONARIO_ID
        INNER JOIN PESSOA P ON P.PESSOA_ID = F.PESSOA_ID
        LEFT JOIN BAIRRO B ON B.BAIRRO_ID = P.BAIRRO_ID
        LEFT JOIN CIDADE C ON C.CIDADE_ID = P.CIDADE_ID
        INNER JOIN ATRIBUICAO_LOTACAO AL ON AL.LOTACAO_ID = L.LOTACAO_ID
        INNER JOIN ATRIBUICAO A ON A.ATRIBUICAO_ID = AL.ATRIBUICAO_ID
        INNER JOIN VINCULO V ON V.VINCULO_ID = L.VINCULO_ID

        LEFT JOIN CONTATO CEL ON CEL.PESSOA_ID = P.PESSOA_ID AND CEL.CONTATO_TIPO = 3
        LEFT JOIN CONTATO TEL ON TEL.PESSOA_ID = P.PESSOA_ID AND TEL.CONTATO_TIPO = 1
        LEFT JOIN CONTATO EMAIL ON EMAIL.PESSOA_ID = P.PESSOA_ID AND EMAIL.CONTATO_TIPO = 2

        WHERE L.LOTACAO_ID = $lotacaoId
    ";

        return DB::select(DB::raw($sql));
    }
```

Trecho corrigido:
```php
    public static function getDadosRelatorioImprimirLotacao($lotacaoId)
    {
        // R69: SQL injection corrigido — bind parameter ao invés de interpolação
        $lotacaoId = (int) $lotacaoId;

        $sql = "
        SELECT
            S.SETOR_NOME,
            U.UNIDADE_NOME,
            P.PESSOA_NOME,
            A.ATRIBUICAO_NOME,
            V.VINCULO_SIGLA,
            L.LOTACAO_DATA_INICIO,
            P.PESSOA_RG_NUMERO AS RG,
            P.PESSOA_CPF_NUMERO AS CPF,
            P.PESSOA_ENDERECO,
            P.PESSOA_COMPLEMENTO,
            B.BAIRRO_NOME,
            C.CIDADE_NOME,
            CEL.CONTATO_CONTEUDO AS CELULAR,
            TEL.CONTATO_CONTEUDO AS TEL,
            EMAIL.CONTATO_CONTEUDO AS EMAIL

        FROM LOTACAO L
        INNER JOIN SETOR S ON S.SETOR_ID = L.SETOR_ID
        INNER JOIN UNIDADE U ON U.UNIDADE_ID = S.UNIDADE_ID
        INNER JOIN FUNCIONARIO F ON F.FUNCIONARIO_ID = L.FUNCIONARIO_ID
        INNER JOIN PESSOA P ON P.PESSOA_ID = F.PESSOA_ID
        LEFT JOIN BAIRRO B ON B.BAIRRO_ID = P.BAIRRO_ID
        LEFT JOIN CIDADE C ON C.CIDADE_ID = P.CIDADE_ID
        INNER JOIN ATRIBUICAO_LOTACAO AL ON AL.LOTACAO_ID = L.LOTACAO_ID
        INNER JOIN ATRIBUICAO A ON A.ATRIBUICAO_ID = AL.ATRIBUICAO_ID
        INNER JOIN VINCULO V ON V.VINCULO_ID = L.VINCULO_ID

        LEFT JOIN CONTATO CEL ON CEL.PESSOA_ID = P.PESSOA_ID AND CEL.CONTATO_TIPO = 3
        LEFT JOIN CONTATO TEL ON TEL.PESSOA_ID = P.PESSOA_ID AND TEL.CONTATO_TIPO = 1
        LEFT JOIN CONTATO EMAIL ON EMAIL.PESSOA_ID = P.PESSOA_ID AND EMAIL.CONTATO_TIPO = 2

        WHERE L.LOTACAO_ID = ?
        ";

        return DB::select($sql, [$lotacaoId]);
    }
```

**Mudanças exatas:** (a) cast `(int)` defensivo na primeira linha, (b) `WHERE L.LOTACAO_ID = $lotacaoId` → `WHERE L.LOTACAO_ID = ?`, (c) `DB::select(DB::raw($sql))` → `DB::select($sql, [$lotacaoId])`.

**Validação:**

```powershell
php artisan tinker --execute="\$r = \App\Models\Lotacao::getDadosRelatorioImprimirLotacao(1); echo 'rows='.count(\$r);"
```
Saída esperada: `rows=N` onde N >= 0 (sem erro de SQL).

```powershell
php artisan tinker --execute="try { \App\Models\Lotacao::getDadosRelatorioImprimirLotacao('1; DROP TABLE LOTACAO'); echo 'NAO_DEVERIA_RODAR'; } catch (\Throwable \$e) { echo 'PROTEGIDO_OK'; }"
```
Saída esperada: o cast `(int)` converte `'1; DROP TABLE LOTACAO'` para `1`, então o resultado será `rows=N` (não erro). O importante é que o ataque NÃO foi executado. Cole a saída no report.

**Commit:** `fix(R69): SQL injection Lotacao::getDadosRelatorioImprimirLotacao via bind parameter + cast int`

---

## T1.4 — R39: crase MySQL em ProgressaoFuncionalListagemService (5 min)

**Arquivo:** `app/Services/Progressao/ProgressaoFuncionalListagemService.php`

**Trecho atual (no método `applyBusca`, ~linha 320):**

```php
                foreach (['PESSOA_CPF_NUMERO', 'PESSOA_CPF'] as $col) {
                    if (Schema::hasColumn('PESSOA', $col)) {
                        $w->orWhereRaw('REPLACE(REPLACE(REPLACE(COALESCE(p.`' . $col . "`,''),'.',''),'-',''),' ','') like ?", [$onlyDigits . '%']);
                    }
                }
```

**Problema:** crase \` em torno de `$col` é sintaxe MySQL. Em SQL Server quebra. Solução cross-driver: remover as crases (deixar identificador "limpo" — funciona em SQLite, MySQL e SQL Server).

**Trecho corrigido:**

```php
                foreach (['PESSOA_CPF_NUMERO', 'PESSOA_CPF'] as $col) {
                    if (Schema::hasColumn('PESSOA', $col)) {
                        // R39: remove crase MySQL — identificador sem quoting funciona em SQLite/MySQL/SQL Server
                        $w->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(p." . $col . ",''),'.',''),'-',''),' ','') like ?", [$onlyDigits . '%']);
                    }
                }
```

**Mudanças exatas:** (a) string literal mudou de aspas simples concatenadas para aspas duplas única, (b) removidos os caracteres backtick (\`) ao redor de `'.$col.'`, (c) o `$col` continua interpolado normalmente.

**Validação:**

```powershell
Select-String -Path "app/Services/Progressao/ProgressaoFuncionalListagemService.php" -Pattern '`'
```
Saída esperada: 0 ocorrências (ou apenas em comentários, se houver).

```powershell
Get-ChildItem -Path "app" -Recurse -Filter "*.php" | Select-String -Pattern '`[A-Z_]+`' | Measure-Object | Select-Object -ExpandProperty Count
```
Saída esperada: `0`.

**Commit:** `fix(R39): remove crase MySQL em applyBusca (cross-driver SQLite/SQL Server)`

---

## T1.5 — R40: whereYear/whereMonth → whereBetween em ApuracaoPontoService (10 min)

**Arquivo:** `app/Services/ApuracaoPontoService.php`

**Problema:** `whereYear` e `whereMonth` Eloquent geram funções específicas por driver — em SQL Server podem retornar resultados inconsistentes ou usar índice errado. Solução cross-driver: `whereBetween` com `Carbon` range.

**Edit 1 — método `calcular` (linhas ~25-39):**

Trecho atual:
```php
    public function calcular(int $funcionarioId, string $competencia): ApuracaoPonto
    {
        [$ano, $mes] = explode('-', $competencia);

        // Busca todos os registros de ponto do mês
        $registros = RegistroPonto::where('FUNCIONARIO_ID', $funcionarioId)
            ->whereYear('REGISTRO_DATA_HORA', $ano)
            ->whereMonth('REGISTRO_DATA_HORA', $mes)
            ->orderBy('REGISTRO_DATA_HORA')
            ->get();

        // Busca os itens de escala do mês (turnos esperados)
        $itensEscala = DetalheEscalaItem::with('turno')
            ->whereHas('detalheEscala', fn($q) => $q->where('FUNCIONARIO_ID', $funcionarioId))
            ->whereYear('DETALHE_ESCALA_ITEM_DATA', $ano)
            ->whereMonth('DETALHE_ESCALA_ITEM_DATA', $mes)
            ->get()
            ->keyBy('DETALHE_ESCALA_ITEM_DATA');
```

Trecho corrigido:
```php
    public function calcular(int $funcionarioId, string $competencia): ApuracaoPonto
    {
        [$ano, $mes] = explode('-', $competencia);

        // R40: whereYear/whereMonth → whereBetween (cross-driver SQLite/MySQL/SQL Server)
        $inicioMes = Carbon::create((int) $ano, (int) $mes, 1)->startOfMonth();
        $fimMes = $inicioMes->copy()->endOfMonth();

        // Busca todos os registros de ponto do mês
        $registros = RegistroPonto::where('FUNCIONARIO_ID', $funcionarioId)
            ->whereBetween('REGISTRO_DATA_HORA', [$inicioMes, $fimMes])
            ->orderBy('REGISTRO_DATA_HORA')
            ->get();

        // Busca os itens de escala do mês (turnos esperados)
        $itensEscala = DetalheEscalaItem::with('turno')
            ->whereHas('detalheEscala', fn($q) => $q->where('FUNCIONARIO_ID', $funcionarioId))
            ->whereBetween('DETALHE_ESCALA_ITEM_DATA', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->get()
            ->keyBy('DETALHE_ESCALA_ITEM_DATA');
```

**Atenção:** `REGISTRO_DATA_HORA` é datetime → passar Carbon completo. `DETALHE_ESCALA_ITEM_DATA` é date → passar `toDateString()`.

**Validação:**

```powershell
Select-String -Path "app/Services/ApuracaoPontoService.php" -Pattern "whereYear|whereMonth"
```
Saída esperada: 0 ocorrências.

```powershell
php artisan tinker --execute="\$svc = new \App\Services\ApuracaoPontoService(); echo 'service_ok';"
```
Saída esperada: `service_ok` (sem erro de classe/sintaxe).

**Commit:** `fix(R40): whereYear/Month → whereBetween cross-driver em ApuracaoPontoService`

---

## T1.6 — R56: strftime SQLite → whereBetween em DepreciacaoService (5 min)

**Arquivo:** `app/Services/DepreciacaoService.php`

**Trecho atual (linhas ~28-34):**

```php
        $bens = DB::table('BEM_PATRIMONIAL')
            ->where('BEM_STATUS', 'ATIVO')
            ->where(function ($q) use ($anoMes) {
                $q->whereNull('BEM_DATA_ULTIMA_DEPRECIACAO')
                  ->orWhereRaw("strftime('%Y-%m', BEM_DATA_ULTIMA_DEPRECIACAO) < ?", [$anoMes]);
            })
            ->get();
```

**Problema:** `strftime` é função SQLite. Não existe em SQL Server.

**Solução:** Comparar a coluna `BEM_DATA_ULTIMA_DEPRECIACAO` diretamente com o **primeiro dia do mês de competência**. Se a data de última depreciação é menor que o início do mês de competência, é elegível para depreciação.

**Trecho corrigido:**

```php
        // R56: strftime SQLite → comparação direta de data (cross-driver)
        $inicioMesCompetencia = Carbon::createFromFormat('Y-m', $anoMes)->startOfMonth()->toDateString();

        $bens = DB::table('BEM_PATRIMONIAL')
            ->where('BEM_STATUS', 'ATIVO')
            ->where(function ($q) use ($inicioMesCompetencia) {
                $q->whereNull('BEM_DATA_ULTIMA_DEPRECIACAO')
                  ->orWhere('BEM_DATA_ULTIMA_DEPRECIACAO', '<', $inicioMesCompetencia);
            })
            ->get();
```

**Adicione no topo do arquivo se não tiver:**

```php
use Carbon\Carbon;
```

(verifique no `use` block — só adicionar se não existir)

**Validação:**

```powershell
Select-String -Path "app/Services/DepreciacaoService.php" -Pattern "strftime|julianday"
```
Saída esperada: 0 ocorrências.

```powershell
php artisan tinker --execute="\$svc = new \App\Services\DepreciacaoService(); echo 'service_ok';"
```

**Commit:** `fix(R56): strftime SQLite → comparação direta cross-driver em DepreciacaoService`

---

## T1.7 — R57: date+'+N days' SQLite em DashboardOperacionalService (10 min)

**Arquivo:** `app/Services/Dashboard/DashboardOperacionalService.php`

**Trecho atual (método `buscarAtestadosPeriodo`, ~linha 154-162):**

```php
    private function buscarAtestadosPeriodo(Collection $funcionarioIds, string $periodoInicio, string $periodoFim): Collection
    {
        if (! Schema::hasTable('ATESTADO_MEDICO') || $funcionarioIds->isEmpty()) {
            return collect();
        }
        try {
            return DB::table('ATESTADO_MEDICO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds->all())
                ->where('STATUS', 'VALIDADO')
                ->where('ATESTADO_DATA', '<=', $periodoFim)
                ->whereRaw("date(ATESTADO_DATA, '+' || ATESTADO_DIAS || ' days') >= ?", [$periodoInicio])
                ->select('FUNCIONARIO_ID', 'ATESTADO_DATA', 'ATESTADO_DIAS')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }
```

**Problema:** `date(col, '+' || col || ' days')` é sintaxe SQLite (`||` concatenação + função `date`). Não funciona em SQL Server.

**Solução:** Trazer **todos os atestados que começam até o fim do período** e filtrar o "fim do atestado" no PHP usando o `funcionarioAusenteNoDia` que já existe (já faz isso, então o filtro SQL pode ser simplificado).

**Trecho corrigido:**

```php
    private function buscarAtestadosPeriodo(Collection $funcionarioIds, string $periodoInicio, string $periodoFim): Collection
    {
        if (! Schema::hasTable('ATESTADO_MEDICO') || $funcionarioIds->isEmpty()) {
            return collect();
        }
        try {
            // R57: removido `date(col, '+' || col || ' days')` SQLite-only.
            // Filtramos atestados que começam até o fim do período (limite máximo razoável: 365d antes).
            // O filtro fino "atestado cobre o dia" é feito em funcionarioAusenteNoDia() no PHP.
            $limiteInicioBusca = Carbon::parse($periodoInicio)->subDays(365)->toDateString();

            return DB::table('ATESTADO_MEDICO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds->all())
                ->where('STATUS', 'VALIDADO')
                ->where('ATESTADO_DATA', '>=', $limiteInicioBusca)
                ->where('ATESTADO_DATA', '<=', $periodoFim)
                ->select('FUNCIONARIO_ID', 'ATESTADO_DATA', 'ATESTADO_DIAS')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }
```

**Justificativa:** o filtro original tentava limitar `(ATESTADO_DATA + ATESTADO_DIAS) >= periodoInicio` no SQL. Como o método `funcionarioAusenteNoDia` (linha ~219-232 do mesmo arquivo) já faz exatamente esse cálculo no PHP via `date('Y-m-d', strtotime(...))`, podemos buscar atestados com data até 365 dias antes do início do período (atestado mais longo possível na prática) e deixar o filtro fino rolar no PHP. **Trade-off:** pode trazer mais linhas (irrelevante para PMSL — atestados raros). **Vantagem:** funciona em SQLite, MySQL e SQL Server.

**Validação:**

```powershell
Select-String -Path "app/Services/Dashboard/DashboardOperacionalService.php" -Pattern "strftime|julianday|date\(.*\+"
```
Saída esperada: 0 ocorrências.

```powershell
php artisan tinker --execute="\$svc = new \App\Services\Dashboard\DashboardOperacionalService(); \$out = \$svc->build('2026-05-07', null); echo json_encode(\$out['taxa_furo_escala'] ?? 'sem dados');"
```
Saída esperada: JSON com `taxa`/`total_furos`/`total_slots_escala` (ou null/0 se não há escala — também aceitável).

**Commit:** `fix(R57): date+'+N days' SQLite-only → busca ampla + filtro PHP cross-driver`

---

## T1.8 — R7-R10: ContabilidadeService idempotência (~30 min)

**Arquivo:** `app/Services/ContabilidadeService.php`

**Problema:** Método `lancarFolha` faz `insertGetId` direto na `LANCAMENTO_CONTABIL`. Cada chamada para a mesma `FOLHA_ID` adiciona 2 lançamentos novos. Reprocessar folha 3x = 6 lançamentos contábeis duplicados.

**Solução simples e segura:** No início do método (após validar que a folha existe), apagar lançamentos contábeis prévios da mesma origem `(ORIGEM_TIPO='FOLHA_PAGAMENTO', ORIGEM_ID=$folhaId)`. Os campos `ORIGEM_TIPO` e `ORIGEM_ID` já estão sendo gravados nos inserts → temos a chave natural de idempotência sem precisar criar UNIQUE constraint.

**Edit — adicionar limpeza prévia logo após o early return de folha não encontrada:**

Trecho atual (linhas ~14-22):
```php
    public function lancarFolha(int $folhaId, string $competencia): array
    {
        // Buscar totais da folha
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (!$folha) {
            throw new \RuntimeException("Folha #{$folhaId} não encontrada.");
        }

        $totais = DB::table('DETALHE_FOLHA')
```

Trecho corrigido:
```php
    public function lancarFolha(int $folhaId, string $competencia): array
    {
        // Buscar totais da folha
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (!$folha) {
            throw new \RuntimeException("Folha #{$folhaId} não encontrada.");
        }

        // R7-R10: idempotência — remove lançamentos prévios da mesma folha antes de re-inserir.
        // Chave natural: (ORIGEM_TIPO='FOLHA_PAGAMENTO', ORIGEM_ID=$folhaId).
        // Reprocessar a mesma folha N vezes resulta nos mesmos N lançamentos (não acumula).
        $deletados = DB::table('LANCAMENTO_CONTABIL')
            ->where('ORIGEM_TIPO', 'FOLHA_PAGAMENTO')
            ->where('ORIGEM_ID', $folhaId)
            ->delete();

        if ($deletados > 0) {
            Log::info('ContabilidadeService: lançamentos prévios removidos antes de re-lançar folha', [
                'folha_id' => $folhaId,
                'deletados' => $deletados,
            ]);
        }

        $totais = DB::table('DETALHE_FOLHA')
```

**Mudança 2 — envolver o restante do método em transação para atomicidade (opcional mas recomendado):**

Substituir a estrutura `$lancamentos = []; ...; return [...];` por:

Trecho atual (todo o restante do método após o `$totais`):
```php
        $totalProventos = (float) ($totais->total_proventos ?? 0);
        // ... [todo o resto até o return final]
        return [
            'lancamentos_criados' => count($lancamentos),
            'ids'                 => $lancamentos,
            'total_proventos'     => $totalProventos,
            'total_patronal'      => $patronal,
        ];
    }
```

Trecho corrigido — apenas envolver em DB::transaction:
```php
        $totalProventos = (float) ($totais->total_proventos ?? 0);
        $totalDescontos = (float) ($totais->total_descontos ?? 0);
        $totalLiquido   = $totalProventos - $totalDescontos;

        // Buscar IDs das contas necessárias
        $contas = DB::table('PCASP_CONTA')
            ->whereIn('CONTA_CODIGO', [
                '3.1.1.1.01', // Vencimentos e Vantagens Fixas (débito)
                '2.1.3.1.01', // Salários e Vantagens a Pagar (crédito)
                '3.1.2.1.01', // Contribuição Patronal IPAM (débito)
                '2.1.3.2.01', // RPPS/IPAM a Recolher (crédito)
            ])
            ->pluck('CONTA_ID', 'CONTA_CODIGO');

        $lancamentos = [];
        $dt = now()->format('Y-m-d');
        $ano = (int) now()->format('Y');
        $mes = (int) now()->format('n');
        $historico = "Folha de pagamento — competência {$competencia}";
        $patronal = round($totalProventos * 0.14, 2);

        // R7-R10: inserts dentro de transação para garantir atomicidade (se 1 falhar, nenhum entra)
        DB::transaction(function () use ($folhaId, $totalProventos, $patronal, $contas, &$lancamentos, $dt, $ano, $mes, $historico) {
            // Lançamento 1: D 3.1.1.1.01 / C 2.1.3.1.01 (vencimentos brutos)
            if ($totalProventos > 0 && isset($contas['3.1.1.1.01'], $contas['2.1.3.1.01'])) {
                $lancamentos[] = DB::table('LANCAMENTO_CONTABIL')->insertGetId([
                    'LANCAMENTO_DATA'      => $dt,
                    'LANCAMENTO_ANO'       => $ano,
                    'LANCAMENTO_MES'       => $mes,
                    'LANCAMENTO_HISTORICO' => $historico . ' — vencimentos',
                    'LANCAMENTO_VALOR'     => $totalProventos,
                    'CONTA_DEBITO_ID'      => $contas['3.1.1.1.01'],
                    'CONTA_CREDITO_ID'     => $contas['2.1.3.1.01'],
                    'ORIGEM_TIPO'          => 'FOLHA_PAGAMENTO',
                    'ORIGEM_ID'            => $folhaId,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Lançamento 2: D 3.1.2.1.01 / C 2.1.3.2.01 (patronal IPAM — estimativa 14%)
            if ($patronal > 0 && isset($contas['3.1.2.1.01'], $contas['2.1.3.2.01'])) {
                $lancamentos[] = DB::table('LANCAMENTO_CONTABIL')->insertGetId([
                    'LANCAMENTO_DATA'      => $dt,
                    'LANCAMENTO_ANO'       => $ano,
                    'LANCAMENTO_MES'       => $mes,
                    'LANCAMENTO_HISTORICO' => $historico . ' — contribuição patronal IPAM',
                    'LANCAMENTO_VALOR'     => $patronal,
                    'CONTA_DEBITO_ID'      => $contas['3.1.2.1.01'],
                    'CONTA_CREDITO_ID'     => $contas['2.1.3.2.01'],
                    'ORIGEM_TIPO'          => 'FOLHA_PAGAMENTO',
                    'ORIGEM_ID'            => $folhaId,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        });

        Log::info('ContabilidadeService: folha lançada', [
            'folha_id'    => $folhaId,
            'competencia' => $competencia,
            'lancamentos' => count($lancamentos),
            'proventos'   => $totalProventos,
            'patronal'    => $patronal,
        ]);

        return [
            'lancamentos_criados' => count($lancamentos),
            'ids'                 => $lancamentos,
            'total_proventos'     => $totalProventos,
            'total_patronal'      => $patronal,
        ];
    }
```

**Validação (idempotência):**

```powershell
php artisan tinker --execute="
\$svc = new \App\Services\ContabilidadeService();
\$folhaId = (int) (\App\Models\Folha::first()?->FOLHA_ID ?? 0);
if (\$folhaId === 0) { echo 'sem_folhas'; exit; }
\$comp = '202604';
\$r1 = \$svc->lancarFolha(\$folhaId, \$comp);
\$r2 = \$svc->lancarFolha(\$folhaId, \$comp);
\$r3 = \$svc->lancarFolha(\$folhaId, \$comp);
\$count = \DB::table('LANCAMENTO_CONTABIL')->where('ORIGEM_TIPO','FOLHA_PAGAMENTO')->where('ORIGEM_ID',\$folhaId)->count();
echo 'lancamentos_apos_3_chamadas='.\$count.' (esperado: 0, 1 ou 2)';
"
```

Saída esperada: `lancamentos_apos_3_chamadas=N` onde **N ∈ {0, 1, 2}** — NÃO pode ser 6 nem múltiplo de 2 maior que 2.

**Commit:** `fix(R7-R10): ContabilidadeService idempotência via DELETE+INSERT em transação`

---

## VALIDAÇÃO FINAL — Smoke test pós-Fase 1

Execute todos os comandos abaixo em sequência e cole as saídas no report:

```powershell
# 1. Confirmar zero strftime/julianday em código novo (FolhaParserService será aposentado, ok ficar lá)
Get-ChildItem -Path "app/Services" -Recurse -Filter "*.php" `
  | Select-String -Pattern "strftime|julianday" `
  | Where-Object { $_.Path -notmatch "FolhaParserService" } `
  | Measure-Object | Select-Object -ExpandProperty Count
# Esperado: 0
```

```powershell
# 2. Confirmar zero path /home/DK
Get-ChildItem -Path "app" -Recurse -Filter "*.php" | Select-String -Pattern "/home/DK" | Measure-Object | Select-Object -ExpandProperty Count
# Esperado: 0
```

```powershell
# 3. Confirmar zero crase em queries (excluindo comentários)
Get-ChildItem -Path "app" -Recurse -Filter "*.php" | Select-String -Pattern '`[A-Z_]+`' | Where-Object { $_.Line -notmatch "^\s*//" } | Measure-Object | Select-Object -ExpandProperty Count
# Esperado: 0
```

```powershell
# 4. Confirmar migrations todas rodaram
php artisan migrate:status | Select-String "Pending" | Measure-Object | Select-Object -ExpandProperty Count
# Esperado: 0
```

```powershell
# 5. Cast decimal:2 OK
php artisan tinker --execute="\$f = \App\Models\Folha::first(); if (!\$f) { echo 'sem_folhas'; exit; } \$v = \$f->FOLHA_VALOR_TOTAL; echo 'valor=\"'.\$v.'\" tipo='.gettype(\$v);"
# Esperado: tipo=string com 2 decimais OU sem_folhas
```

```powershell
# 6. Sentinela
php artisan gente:sentinela-run --json | Select-String '"status"'
# Esperado: status "ok" ou similar
```

```powershell
# 7. Healthcheck
php artisan gente:healthcheck --json
# Cole saída completa
```

```powershell
# 8. Git log das 8 correções
git log --oneline -n 10
# Esperado: 8 commits novos com prefixo "fix(R##):"
```

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 1 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T1.1 R70 cast decimal:2 ............ commit: ____
[ ] T1.2 R72 path debug ................ commit: ____
[ ] T1.3 R69 SQL injection ............. commit: ____
[ ] T1.4 R39 crase MySQL ............... commit: ____
[ ] T1.5 R40 whereYear/Month ........... commit: ____
[ ] T1.6 R56 strftime DepreciacaoService commit: ____
[ ] T1.7 R57 date+'+N days' DashOpera ... commit: ____
[ ] T1.8 R7-R10 ContabilidadeService ... commit: ____

SMOKE TESTS PÓS-FASE (cole saídas reais do PowerShell):

1. strftime/julianday em app/Services (excluindo FolhaParser):
   ___ ocorrências (esperado 0)

2. /home/DK em app/:
   ___ ocorrências (esperado 0)

3. crase em queries:
   ___ ocorrências (esperado 0)

4. migrations pendentes:
   ___ (esperado 0)

5. Folha::first()->FOLHA_VALOR_TOTAL:
   valor="___" tipo=___

6. sentinela-run:
   ___

7. healthcheck (JSON completo):
   ___

8. git log --oneline -n 10:
   ___

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

**Próximo briefing:** Fase 2-A — Ampliação MotorFolha (gaps críticos: frequência + abono + pró-rata + HE/plantão). Aguardar Claude auditar este report antes de iniciar.
