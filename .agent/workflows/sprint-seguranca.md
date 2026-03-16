---
description: Passo a passo da Sprint 0 — SEC-01 a SEC-05 (segurança imediata antes do deploy)
---

# Workflow: Sprint 0 — Segurança

Execute estes passos em ordem **antes de qualquer deploy em servidor acessível**. Cada item tem diagnóstico + correção + verificação.

---

## SEC-01 — Remover `/dev/set-senha/{login}/{senha}`

### Diagnóstico:
```bash
grep -n "set-senha" routes/web.php
```

Se retornar resultado → rota existe e precisa ser removida.

### Correção:
Localizar o bloco e **deletar completamente** a linha do `Route::get('/dev/set-senha/...')`. Não mover, não comentar — deletar.

### Verificação:
```bash
grep -n "set-senha" routes/web.php
# Deve retornar vazio
php artisan route:list | grep set-senha
# Deve retornar vazio
```

---

## SEC-02 — Envolver todas as rotas `/dev/*` em `app()->isLocal()`

### Diagnóstico:
```bash
grep -n "app()->environment('production')" routes/web.php
```

Se encontrar → condição frágil. Um `APP_ENV` errado expõe as rotas.

### Correção:

Substituir o bloco atual por:

```php
// ✅ Proteção correta — só ativa em ambiente local/dev:
if (app()->isLocal() || app()->environment('development', 'testing')) {
    Route::prefix('dev')->group(function () {
        Route::get('/ping-db', function () { /* ... */ });
        Route::get('/echo-request', function () { /* ... */ });
        Route::get('/echo-raw', function () { /* ... */ });
        Route::get('/diag-login/{login}/{senha}', function () { /* ... */ });
        Route::get('/criar-admin', function () { /* ... */ });
        Route::get('/seed-dados', function () { /* ... */ });
        // ⚠️ NÃO incluir set-senha aqui — foi deletado no SEC-01
    });
}
```

### Verificação:
```bash
php artisan route:list | grep "dev/"
# Em produção (APP_ENV=production) → deve retornar vazio
```

---

## SEC-03 — Chave JWT separada para app de ponto

### Diagnóstico:
```bash
grep -n "app.key\|APP_KEY" routes/ponto_app.php
```

Se encontrar `config('app.key')` como segredo JWT → vulnerabilidade crítica.

### Correção:

**1. Gerar chave separada:**
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
# Copiar o output → PONTO_APP_JWT_SECRET
```

**2. Adicionar no `.env`:**
```env
PONTO_APP_JWT_SECRET=valor_gerado_acima
```

**3. Adicionar em `config/services.php`:**
```php
'ponto_app' => [
    'jwt_secret' => env('PONTO_APP_JWT_SECRET'),
],
```

**4. Substituir em `routes/ponto_app.php`:**
```php
// Antes:
$secret = config('app.key');

// Depois:
$secret = config('services.ponto_app.jwt_secret');
if (!$secret) abort(500, 'PONTO_APP_JWT_SECRET não configurado no .env');
```

### Verificação:
```bash
grep -n "app.key" routes/ponto_app.php
# Deve retornar vazio
```

---

## SEC-04 — Criar chave JWT e adicionar AUDIT_LOG middleware

### Diagnóstico da auditoria:
```bash
grep -rn "AUDIT_LOG" app/Http/Middleware/
# Se vazio → middleware não existe ainda
```

### Correção — criar middleware:

Criar `app/Http/Middleware/AuditLog.php`:

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && Auth::check()) {
            try {
                DB::table('AUDIT_LOG')->insert([
                    'USUARIO_ID'  => Auth::id(),
                    'ACAO'        => $request->method() . ' ' . $request->path(),
                    'TABELA'      => $this->inferirTabela($request->path()),
                    'DADOS_NOVOS' => json_encode($request->except(['_token', 'USUARIO_SENHA', 'password'])),
                    'IP'          => $request->ip(),
                    'USER_AGENT'  => substr($request->userAgent() ?? '', 0, 200),
                    'created_at'  => now(),
                ]);
            } catch (\Exception $e) {
                // Não deixar falha de auditoria quebrar a requisição
                \Log::error('AuditLog falhou: ' . $e->getMessage());
            }
        }

        return $response;
    }

    private function inferirTabela(string $path): string
    {
        $segmentos = explode('/', trim($path, '/'));
        return strtoupper($segmentos[2] ?? 'DESCONHECIDO');
    }
}
```

Registrar em `app/Http/Kernel.php` no grupo `api` ou no alias:
```php
'audit' => \App\Http\Middleware\AuditLog::class,
```

Adicionar ao grupo `api/v3` no `web.php`:
```php
Route::prefix('api/v3')->middleware(['web', 'auth', 'audit'])->group(function () {
    // ...
});
```

---

## SEC-05 — Rate limiting no login

### Diagnóstico:
```bash
grep -n "throttle\|rate" routes/web.php | grep "auth/login"
# Se vazio → sem rate limiting
```

### Correção:

Localizar a rota de login em `web.php` e adicionar `throttle:10,1`:

```php
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {
    Route::post('/login', function (Request $request) {
        // código existente
    });
    Route::post('/logout', function () {
        // código existente
    });
    Route::get('/me', function () {
        // código existente
    });
});
```

O `throttle:10,1` permite 10 tentativas por minuto por IP. Após isso, retorna 429.

---

## Verificação Final da Sprint 0

Execute após concluir todos os itens:

```bash
# 1. Nenhuma rota de dev exposta:
php artisan route:list | grep "dev/"

# 2. Nenhuma referência ao APP_KEY como JWT:
grep -rn "app\.key" routes/ponto_app.php

# 3. Middleware de auditoria registrado:
grep -n "AuditLog\|audit" app/Http/Kernel.php

# 4. Rate limiting no login:
php artisan route:list | grep "auth/login"
# Deve mostrar throttle na coluna de middleware

# 5. set-senha deletado:
php artisan route:list | grep "set-senha"
# Deve retornar vazio
```

Registrar conclusão em `docs/historico-problemas.md` com data e resultados dos checks.
