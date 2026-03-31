# GENTE v3 — SPRINT 0
**Criado:** 15/03/2026 | **Baseado em varredura direta do código**
**Objetivo:** Sistema acessível — login, navegação e logout funcionando
**Estimativa:** 1 sessão (2–3 horas)
**Critério de conclusão:** Logar com usuário existente, navegar em rotas protegidas, fazer logout sem erros

> Este é o único documento de referência para o Sprint 0.
> Não consultar versões anteriores do PLANO_SPRINTS.md para estas tasks.

---

## CONTEXTO

Dois bugs bloqueiam 100% do sistema:

1. **IC-01** — Rotas de auth presas dentro de `isLocal()` → URLs com prefixo `/dev/` que o frontend nunca chama
2. **IC-02** — CORS com `supports_credentials=false` + wildcard → browser não envia cookies → sessão nunca estabelece

Corrigir IC-01 + IC-02 = todos os módulos existentes desbloqueiam simultaneamente.

---

## TASK-01 🔴 — Mover rotas de auth para fora do isLocal()

**Arquivo:** `routes/web.php`
**Problema confirmado:** /csrf-cookie e Route::prefix('api/auth') estão dentro do bloco isLocal()/dev

**O que fazer:**
1. Localizar `})->name('login');` no web.php (rota GET '/')
2. Logo APÓS essa linha, ANTES do bloco `if (app()->isLocal())`, inserir o bloco abaixo
3. Dentro do bloco `if (app()->isLocal())` — REMOVER /csrf-cookie e Route::prefix('api/auth') inteiro. Manter apenas: /ping-db, /echo-request, /echo-raw, /v3, /autocadastro/{token}

**Código a inserir:**
```php
// ══════════════════════════════════════════════════════════════
// CSRF cookie — necessário para SPA inicializar sessão
// ══════════════════════════════════════════════════════════════
Route::get('/csrf-cookie', function () {
    return response()->json(['csrfToken' => csrf_token()]);
})->middleware('web');

// ══════════════════════════════════════════════════════════════
// API DE AUTENTICAÇÃO — GENTE V3 SPA
// ══════════════════════════════════════════════════════════════
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {

    Route::get('/me', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $user = Auth::user();
        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
            if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuário') {
                $perfilNome = 'funcionario';
            }
        }
        return response()->json([
            'id'            => $user->USUARIO_ID,
            'login'         => $user->USUARIO_LOGIN,
            'nome'          => $user->USUARIO_NOME,
            'email'         => $user->USUARIO_EMAIL,
            'perfil'        => $perfilNome,
            'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
            'funcionario'   => $funcionario ? [
                'id'        => $funcionario->FUNCIONARIO_ID,
                'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                'nome'      => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
            ] : null,
        ]);
    });

    Route::post('/login', function (Request $request) {
        $login    = $request->input('USUARIO_LOGIN');
        $password = $request->input('USUARIO_SENHA');
        if (!$login || !$password) {
            return response()->json(['error' => 'Credenciais não informadas'], 422);
        }
        if ($login !== 'admin') {
            $login = preg_replace('/[^0-9]/', '', $login);
        }
        $user = \App\Models\Usuario::where('USUARIO_LOGIN', $login)
            ->where('USUARIO_ATIVO', 1)->first();
        if (!$user) {
            return response()->json(['error' => 'Credenciais inválidas ou usuário inativo'], 401);
        }
        if ($user->USUARIO_SENHA === md5($password)) {
            $user->USUARIO_SENHA        = bcrypt($password);
            $user->USUARIO_ALTERAR_SENHA = 1;
            $user->save();
        }
        if (!\Hash::check($password, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha incorreta'], 401);
        }
        if ($user->USUARIO_VIGENCIA && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
            return response()->json(['error' => 'Acesso expirado'], 401);
        }
        Auth::login($user, false);
        $request->session()->regenerate();
        try {
            $user->USUARIO_ULTIMO_ACESSO = now();
            $user->save();
        } catch (\Throwable $e) {}
        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? 'funcionario';
        }
        return response()->json([
            'ok'   => true,
            'user' => [
                'id'            => $user->USUARIO_ID,
                'login'         => $user->USUARIO_LOGIN,
                'nome'          => $user->USUARIO_NOME,
                'email'         => $user->USUARIO_EMAIL,
                'perfil'        => $perfilNome,
                'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
                'funcionario'   => $funcionario ? [
                    'id'        => $funcionario->FUNCIONARIO_ID,
                    'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                    'nome'      => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
                ] : null,
            ],
        ]);
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    });

    Route::post('/change-password', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $senhaAtual = $request->input('senha_atual');
        $senhaNova  = $request->input('senha_nova');
        if (!$senhaAtual || !$senhaNova) {
            return response()->json(['error' => 'Informe a senha atual e a nova senha'], 422);
        }
        if (strlen($senhaNova) < 6) {
            return response()->json(['error' => 'A nova senha deve ter pelo menos 6 caracteres'], 422);
        }
        $user = Auth::user();
        if (!\Hash::check($senhaAtual, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha atual incorreta'], 401);
        }
        $user->USUARIO_SENHA        = bcrypt($senhaNova);
        $user->USUARIO_ALTERAR_SENHA = 0;
        $user->save();
        return response()->json(['ok' => true, 'message' => 'Senha alterada com sucesso']);
    });

});
```

**Verificação após aplicar:**
```powershell
php artisan route:list | findstr "api/auth"
# Deve mostrar: POST api/auth/login, GET api/auth/me, POST api/auth/logout
# SEM prefixo /dev/
```

---

## TASK-02 🔴 — Corrigir CORS

**Arquivo:** `config/cors.php`
**Problema confirmado:** allowed_origins=['*'] + supports_credentials=false

**Substituir o conteúdo completo por:**
```php
<?php
return [
    'paths'                    => ['api/*', 'csrf-cookie', 'sanctum/*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8000',
        'http://localhost:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];
```

**Verificação:**
```powershell
php artisan config:clear
# Testar login via browser — deve retornar 200 com cookie
```

---

## TASK-03 🟠 — Corrigir .env (APP_URL + SESSION_DOMAIN)

**Arquivo:** `.env`

**Verificar e corrigir:**
```env
APP_URL=http://localhost:8000
SESSION_DOMAIN=localhost
```

---

## TASK-04 — REMOVIDA
IC-03 (path '/' duplicado) foi confirmado como FALSO POSITIVO pela varredura de 15/03/2026.
O código está correto. Não há nada a corrigir neste item.

---

## TASK-05 🟠 — Corrigir BOM UTF-8 em progressao_funcional.php

**Arquivo:** `routes/progressao_funcional.php`

**Verificar BOM:**
```powershell
$b = [IO.File]::ReadAllBytes('routes\progressao_funcional.php')
if ($b[0] -eq 0xEF) { Write-Host "BOM DETECTADO — remover" } else { Write-Host "OK" }
```

**Remover BOM se presente:**
```powershell
$bytes = [IO.File]::ReadAllBytes('routes\progressao_funcional.php')
if ($bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    [IO.File]::WriteAllBytes('routes\progressao_funcional.php', $bytes[3..($bytes.Length-1)])
    Write-Host "BOM removido"
}
```

**Verificar use Carbon:**
```powershell
Select-String -Path "routes\progressao_funcional.php" -Pattern "use Carbon"
# Se vazio → adicionar 'use Carbon\Carbon;' no topo do arquivo
```

---

## CHECKLIST DE CONCLUSÃO DO SPRINT 0

```
[ ] TASK-01 — Rotas auth fora do isLocal() — php artisan route:list confirma
[ ] TASK-02 — CORS corrigido — login retorna 200 com cookie no browser
[ ] TASK-03 — .env com APP_URL:8000 e SESSION_DOMAIN
[ ] TASK-05 — BOM removido de progressao_funcional.php

TESTE FINAL:
[ ] npm run dev (frontend rodando em :5173)
[ ] php artisan serve --port=8000 (backend rodando em :8000)
[ ] Abrir http://localhost:5173 → tela de login aparece
[ ] Logar com admin / senha → redireciona para dashboard
[ ] Navegar em 3 módulos diferentes sem erro 401
[ ] Fazer logout → redireciona para login
```

---

## O QUE NÃO FAZ PARTE DESTE SPRINT

- Margem cartão 10% (IC-06) → Sprint 2
- Holerite PDF (IC-07) → Sprint 2
- Neoconsig (IC-08) → Sprint 3
- Qualquer nova feature → sprints posteriores

---

*GENTE v3 | Sprint 0 | RR TECNOL | 15/03/2026*
