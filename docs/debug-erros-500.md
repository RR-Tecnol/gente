# Debug de Erros 500 — GENTE

## Fluxo Geral

```
1. Limpar log
   ↓
2. Reproduzir o erro (testar a rota)
   ↓
3. Ler o log do Laravel
   ↓
4. Identificar o tipo de erro
   ↓
5. Aplicar a correção
   ↓
6. Repetir até log limpo
```

---

## Passo 1 — Limpar o log antes de testar

```bash
docker compose exec app sh -c "echo '' > storage/logs/laravel.log"
```

> Sempre limpe antes de reproduzir para não confundir erros antigos com novos.

---

## Passo 2 — Testar rotas autenticadas (scripts prontos)

```bash
# Testa login + todas as rotas principais em sequência
docker compose exec app sh test_login.sh

# Testa apenas /unidade/view isolado (modelo para testar rota específica)
docker compose exec app sh test_unidade.sh
```

### ⚠️ Falso Positivo: "Whoops" no HTML

O script de teste usa `grep "Whoops"` no HTML. Isso gera **falso positivo** em rotas que retornam a SPA Vue, porque o bundle JS minificado contém essa string internamente.

**O verdadeiro indicador de erro é:**
- HTTP status **diferente de 200**
- Presença de erros **no log do Laravel** (`storage/logs/laravel.log`)

---

## Passo 3 — Ler o log

```bash
# Ver erros mais recentes
docker compose exec app sh -c "tail -50 storage/logs/laravel.log | grep 'local.ERROR'"

# Extrair nomes de colunas SQL inválidas
docker compose exec app sh check_errors.sh

# Ver erros de objeto inválido (tabela inexistente)
docker compose exec app sh -c "grep 'Invalid object name' storage/logs/laravel.log | sort -u"
```

---

## Tipos de Erro Comuns e Soluções

### Tipo 1 — `SQLSTATE[42S22]: Invalid column name 'X'`

**Causa:** Coluna existe no Model/Controller mas não na tabela do banco.

**Diagnóstico:**
```bash
docker compose exec app sh check_errors.sh
# Retorna: Invalid column name 'SETOR_SIGLA'
```

**Solução — criar migration com verificação segura:**
```php
// database/migrations/YYYY_MM_DD_NNNNNN_add_coluna_x.php
public function up()
{
    if (Schema::hasTable('TABELA') && !Schema::hasColumn('TABELA', 'COLUNA')) {
        Schema::table('TABELA', function (Blueprint $table) {
            $table->string('COLUNA', 100)->nullable();
        });
    }
}
```

```bash
docker compose exec app php artisan migrate \
  --path=database/migrations/SEU_ARQUIVO.php --force
```

**Histórico de colunas adicionadas:**
| Migration | Colunas adicionadas |
|---|---|
| `2026_02_23_000003` | Colunas diversas na rodada inicial |
| `2026_02_23_000004` | `SETOR_SIGLA`, `COLUNA_DESCRICAO`, `EVENTO_IMPOSTO`, `ATRIBUICAO_DATA_EXCLUSAO`, etc. |

---

### Tipo 2 — `SQLSTATE[42S02]: Invalid object name 'X'`

**Causa:** A tabela não existe no banco.

**Diagnóstico:**
```bash
docker compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "Gente@2024" -No \
  -Q "USE gente; SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_NAME;"
```

**Solução:** Criar migration com `Schema::hasTable()`:
```php
if (!Schema::hasTable('NOME_TABELA')) {
    Schema::create('NOME_TABELA', function (Blueprint $table) {
        $table->integer('ID')->autoIncrement();
        // ... demais colunas
    });
}
```

---

### Tipo 3 — `BadMethodCallException: Method XxxController::view does not exist`

**Causa:** A rota `GET /modulo/view` está definida no `web.php`, mas o controller não tem o método `view()`.

**Diagnóstico:**
```bash
docker compose exec app sh check_controllers.sh
# Retorna: SetorController: 0  → 0 = sem view()
```

**Solução:** Adicionar o método no controller.

Se for uma rota que **serve a SPA Vue** (sem dados do banco na abertura):
```php
public function view()
{
    return view('home');  // home.blade.php = layout principal da SPA
}
```

Se for uma rota que **pré-carrega dados** (como UnidadeController):
```php
public function view()
{
    $dados = MinhaTabela::listar();
    return view('modulo.modulo_view', compact('dados'));
}
```

**Controllers corrigidos nesta sessão:** `SetorController`, `AfastamentoController`, `FeriasController`.

---

### Tipo 4 — `View [nome] not found`

**Causa:** O método `view()` retorna o nome errado da view Blade.

**Views disponíveis:**
```bash
docker compose exec app sh -c "find resources/views -name '*.blade.php' | sort"
```

| View | Uso |
|---|---|
| `home` | SPA Vue principal (usada pela maioria das rotas) |
| `auth.login` | Página de login (também SPA Vue) |
| `unidade.unidade_view` | View de Unidades com dados pré-carregados |
| `escala.escala_view` | View de Escala com dados pré-carregados |

---

### Tipo 5 — `419 Page Expired` (CSRF)

**Causa no desenvolvimento:** CSRF token expirado ou reutilizado.

**Ponto de atenção:** O sistema usa SPA Vue com Axios, então:
- O CSRF token fica em `<meta name="csrf-token" content="...">` (não em `<input hidden>`)
- Axios envia via header `X-CSRF-TOKEN`
- Em testes com `curl`, use `Content-Type: application/json` e `-H "X-CSRF-TOKEN: $TOKEN"`

---

## Scripts de Diagnóstico (raiz do projeto)

| Script | Comando | O que faz |
|---|---|---|
| `test_login.sh` | `docker compose exec app sh test_login.sh` | Login + GET de 13 rotas principais |
| `check_errors.sh` | `docker compose exec app sh check_errors.sh` | Extrai colunas SQL inválidas do log |
| `check_controllers.sh` | `docker compose exec app sh check_controllers.sh` | Lista controllers com/sem `view()` |
| `check_db.php` | `docker compose exec app php check_db.php` | Contagens do banco + dados do admin |
