# BRIEFING ANTYGRAVITY — Fase 6 D10: Remoção definitiva do Vue 2 legado

**Data:** 08/05/2026 22h50  
**Branch:** `producao-pmsl`  
**Contexto:** Deploy PMSL São Luís rodando em http://sistemagente.com retornou 200 OK em todas as rotas, MAS quando o browser tenta acessar a tela de login, o Laravel UI legado (Vue 2) intercepta a rota `/login` antes do `Route::fallback`, renderiza `resources/views/auth/login.blade.php` que carrega `/css/app.css` e `/js/app.js` (assets Vue 2 inexistentes em produção pois rodamos `composer install --no-dev` e nunca buildamos o Mix antigo). Resultado: tela em branco com 6 erros de CSP + MIME no console.

**Histórico:** Ronaldo solicitou remoção do Vue 2 múltiplas vezes em fases anteriores, mas restaram artefatos zumbis (rotas, controllers, blade, middlewares). A migração Vue 2 → Vue 3 SPA foi declarada concluída em Mar/2026 mas o `Auth::routes()` do Laravel UI nunca foi removido, e a rota raiz `/` continua renderizando o blade Vue 2.

---

## Inventário do Vue 2 zumbi

Foram encontrados 9 pontos ativos:

| # | Arquivo | Linha | O que faz |
|---|---|---|---|
| 1 | `routes/web.php` | 104-117 | Rota `/` raiz renderiza `view('auth.login')` |
| 2 | `routes/web.php` | 899 | `Auth::routes();` registra `/login`, `/login` POST, `/logout`, `/register`, `/password/*` |
| 3 | `app/Http/Controllers/Auth/LoginController.php` | inteiro | Controller do Laravel UI; `showLoginForm()` retorna `view('auth.login')` |
| 4 | `app/Http/Controllers/Auth/*Controller.php` | inteiro | RegisterController, ForgotPasswordController, ResetPasswordController, ConfirmPasswordController, VerificationController — todos do Laravel UI |
| 5 | `resources/views/auth/login.blade.php` | 1-27 | Blade Vue 2 com `<login>` componente, carrega `/css/app.css` e `/js/app.js` (404 em prod) |
| 6 | `app/Http/Middleware/Authenticate.php` | 16-19 | `redirectTo` retorna `route('login')` que aponta pra rota raiz Vue 2 |
| 7 | `app/Http/Middleware/RedirectIfAuthenticated.php` | 28 | Redireciona pra `RouteServiceProvider::HOME = '/home'` (rota inexistente no Vue 3) |
| 8 | `app/Providers/RouteServiceProvider.php` | 21 | `const HOME = '/home'` aponta pra rota inexistente |
| 9 | `resources/gente-v3/src/plugins/axios.js` | 113 | Em 401/419, faz `window.location.href = '/login?sessao_expirada=1'` que cai no Laravel UI Vue 2 |

**Sources Vue 2 (não usados em runtime mas poluem repo):**
- `resources/sass/app.scss`, `resources/sass/_variables.scss`
- `resources/js/` (se existir)
- `webpack.mix.js`
- `package.json` na raiz (Mix; o do Vue 3 está em `resources/gente-v3/package.json`)
- `public/css/app.css`, `public/js/app.js`, `public/mix-manifest.json` (commitados? checar `.gitignore`)

**Pacote Composer dispensável:**
- `laravel/ui: ^3.2` no `composer.json:21` — provê `Auth::routes()`. Pode ser removido.

---

## Tarefas

Execute na ordem. Antes de cada commit, rode `php artisan route:list 2>&1 | head -20` pra confirmar que nada quebrou.

### Tarefa 1 — Trocar rota raiz para servir o SPA Vue 3

**Arquivo:** `routes/web.php`

**Localizar (linhas ~104-117):**

```php
Route::get('/', function (Request $request) {

    $loginWebKey = null;
    $sessionData = $request->session()->all();
    foreach ($sessionData as $key => $value) {
        if (str_starts_with($key, 'login_web_')) {
            $loginWebKey = $key;
            $request->session()->forget($loginWebKey);
            break;
        }
    }

    return view('auth.login');
})->name('login');
```

**Substituir por:**

```php
Route::get('/', function (Request $request) {
    // Limpeza de chaves de sessão legadas do Vue 2 (login_web_*)
    $sessionData = $request->session()->all();
    foreach ($sessionData as $key => $value) {
        if (str_starts_with($key, 'login_web_')) {
            $request->session()->forget($key);
        }
    }

    // Vue 3 SPA — Vue Router redireciona / → /login internamente
    return view('v3.app');
})->name('login');
```

### Tarefa 2 — Remover `Auth::routes()` do Laravel UI

**Arquivo:** `routes/web.php` linha ~899

**Localizar:**

```php
Auth::routes();
```

**Substituir por:**

```php
// Auth::routes() do Laravel UI removido em Fase 6 D10 — autenticação 100% via /api/auth/* (SPA)
// Compatibilidade: rota nomeada 'login' apontando pra raiz para Authenticate middleware funcionar
// (já definida acima em Route::get('/')->name('login'))

// Logout HTTP nativo (Laravel UI tinha POST /logout): redireciona pra rota /api/auth/logout do SPA
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');
```

### Tarefa 3 — Atualizar `redirectTo` do middleware Authenticate

**Arquivo:** `app/Http/Middleware/Authenticate.php`

**Substituir o método `redirectTo` por:**

```php
protected function redirectTo($request)
{
    if (! $request->expectsJson()) {
        // Rota raiz '/' agora serve o SPA Vue 3 que resolve /login via Vue Router
        return '/';
    }
    // JSON requests: retorna 401 sem redirect (SPA trata via axios interceptor)
    return null;
}
```

### Tarefa 4 — Atualizar `RouteServiceProvider::HOME`

**Arquivo:** `app/Providers/RouteServiceProvider.php` linha 21

**Substituir:**

```php
public const HOME = '/home';
```

**Por:**

```php
// Vue 3 SPA: dashboard renderizado via Vue Router em '/dashboard'
// '/' serve o SPA que via Vue Router decide se mostra login ou dashboard
public const HOME = '/dashboard';
```

### Tarefa 5 — Atualizar interceptor axios pra navegar via Vue Router

**Arquivo:** `resources/gente-v3/src/plugins/axios.js` linhas ~109-117

**Localizar:**

```javascript
        if (error.response && [401, 419].includes(error.response.status)) {
            import('@/store/auth').then(({ useAuthStore }) => {
                try { useAuthStore().clearCache() } catch (e) { /* empty */ }
            }).catch(() => { })

            // Só redireciona se não estamos já na tela de login
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login?sessao_expirada=1'
            }
        }
```

**Substituir por:**

```javascript
        if (error.response && [401, 419].includes(error.response.status)) {
            import('@/store/auth').then(({ useAuthStore }) => {
                try { useAuthStore().clearCache() } catch (e) { /* empty */ }
            }).catch(() => { })

            // Só redireciona se não estamos já na tela de login
            // Usa router push em vez de location.href para evitar reload do SPA
            if (!window.location.pathname.includes('/login')) {
                import('@/router').then(({ default: router }) => {
                    router.push({ name: 'Login', query: { sessao_expirada: '1' } })
                }).catch(() => {
                    // Fallback se import dinâmico falhar
                    window.location.href = '/login?sessao_expirada=1'
                })
            }
        }
```

### Tarefa 6 — Relaxar CSP para permitir Vuetify e fontes externas

**Arquivo:** `app/Http/Middleware/SecurityHeaders.php`

**Localizar (linhas ~33-42):**

```php
$response->headers->set('Content-Security-Policy',
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: blob: https://api.dicebear.com; " .
    "font-src 'self' data:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'self';"
);
```

**Substituir por:**

```php
$response->headers->set('Content-Security-Policy',
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "img-src 'self' data: blob: https://api.dicebear.com https://*.tile.openstreetmap.org; " .
    "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
    "connect-src 'self' https://viacep.com.br https://www.google.com; " .
    "frame-src 'self' https://www.google.com; " .
    "frame-ancestors 'self';"
);
```

**Justificativa de cada alteração:**
- `script-src` + `https://www.google.com https://www.gstatic.com` → necessário para reCAPTCHA v3 (login)
- `style-src` + `https://fonts.googleapis.com` → fonte Inter usada no LoginView/Vuetify
- `style-src` + `https://cdn.jsdelivr.net` → MaterialDesignIcons (`@mdi/font`) usado em todo o SPA
- `style-src-elem` explícito → Chrome novo exige declaração separada quando `style-src` é restritivo
- `font-src` + `https://fonts.gstatic.com` → arquivos `.woff2` da fonte Inter
- `connect-src` + `https://viacep.com.br` → AutocadastroView faz CEP lookup
- `connect-src` + `https://www.google.com` → reCAPTCHA siteverify (mesmo que feito no backend, browser pode pré-validar)
- `frame-src` + `https://www.google.com` → reCAPTCHA renderiza iframe

### Tarefa 7 — Excluir blade Vue 2 e controllers Laravel UI

**Arquivos a DELETAR:**

```
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php       (se existir)
resources/views/auth/passwords/                (pasta inteira, se existir)
app/Http/Controllers/Auth/LoginController.php
app/Http/Controllers/Auth/RegisterController.php
app/Http/Controllers/Auth/ResetPasswordController.php
app/Http/Controllers/Auth/ForgotPasswordController.php
app/Http/Controllers/Auth/ConfirmPasswordController.php
app/Http/Controllers/Auth/VerificationController.php
```

**ATENÇÃO:** Antes de deletar `Auth/LoginController.php`, fazer `grep -r "LoginController" app/ routes/ config/` para confirmar que nenhum outro lugar referencia. **Se referenciar, NÃO delete e me avise.**

### Tarefa 8 — Remover `laravel/ui` do composer

**Arquivo:** `composer.json` linha ~21

**Remover:**

```json
"laravel/ui": "^3.2",
```

Após editar, **NÃO rodar `composer update`** (mexe em platform_check). Apenas commit do `.json`. O servidor já tem `vendor/` instalado sem usar UI; rodar update agora pode quebrar.

### Tarefa 9 — Sources Vue 2 do Laravel Mix (não-críticas)

**Arquivos a DELETAR (sources que não são mais usados):**

```
webpack.mix.js
resources/sass/app.scss
resources/sass/_variables.scss
resources/js/app.js                    (se existir)
resources/js/bootstrap.js              (se existir)
resources/js/components/Login.vue      (se existir)
package.json                           (raiz — Mix; o do Vue 3 está em resources/gente-v3/)
package-lock.json                      (raiz)
```

**ATENÇÃO:** O `package.json` da **raiz** é do Mix antigo. O do **Vue 3 ativo** está em `resources/gente-v3/package.json` e `resources/gente-v3/package-lock.json`. **NÃO delete os de gente-v3/.**

Antes de deletar `webpack.mix.js`, `grep -r "webpack.mix\|laravel-mix" .` para garantir nenhuma referência ativa.

### Tarefa 10 — Limpar `public/` de assets Vue 2 mortos

**Arquivos a DELETAR (assets Mix antigos commitados):**

```
public/css/app.css                    (se commitado)
public/js/app.js                      (se commitado)
public/mix-manifest.json              (se commitado)
public/img/brasao-2.png               (se for legado Vue 2 não-usado)
```

Verificar antes:
```bash
git ls-files public/css/ public/js/ | grep -E "(app\.css|app\.js|mix-manifest)"
```

Se aparecer no listing, deletar e commit.

### Tarefa 11 — Criar seeder de admin user para produção

**Contexto:** A rota `/dev/criar-admin` existe mas está dentro de `if (app()->isLocal())` e por isso não funciona em produção. Precisamos de um seeder dedicado, idempotente, repetível, que crie um único usuário admin canônico para o GO-LIVE PMSL.

**Arquivo a CRIAR:** `database/seeders/AdminProdSeeder.php`

**Conteúdo:**

```php
<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AdminProdSeeder — cria usuário administrador para ambiente de produção.
 *
 * Diferença para o /dev/criar-admin (que só roda em isLocal()):
 * - Roda em production sem precisar de rota dev exposta
 * - Idempotente: se o admin já existe, atualiza apenas senha+vigência
 * - Forçar troca de senha no primeiro acesso (USUARIO_ALTERAR_SENHA=1)
 * - Audita criação no log
 *
 * Uso em produção:
 *   sudo -u www-data php artisan db:seed --class=AdminProdSeeder --force
 *
 * Credenciais geradas:
 *   USUARIO_LOGIN: admin
 *   USUARIO_SENHA: GenteAdmin@2026!PMSL
 *
 * IMPORTANTE: senha deve ser trocada no primeiro acesso pela equipe PMSL.
 */
class AdminProdSeeder extends Seeder
{
    public function run(): void
    {
        $loginAdmin = 'admin';
        $senhaInicial = 'GenteAdmin@2026!PMSL';

        $existing = Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();

        $payload = [
            'USUARIO_LOGIN'           => $loginAdmin,
            'USUARIO_NOME'            => 'Administrador GENTE — PMSL São Luís',
            'USUARIO_SENHA'           => Hash::make($senhaInicial),
            'USUARIO_EMAIL'           => 'admin@sistemagente.com',
            'USUARIO_ATIVO'           => 1,
            'USUARIO_VIGENCIA'        => null,
            'USUARIO_ALTERAR_SENHA'   => 1,
        ];

        // USUARIO_PRIMEIRO_ACESSO existe na tabela? (migration tardia)
        if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $payload['USUARIO_PRIMEIRO_ACESSO'] = 1;
        }

        if (! $existing) {
            $user = Usuario::create($payload);
            Log::info('AdminProdSeeder: admin criado', [
                'usuario_id' => $user->USUARIO_ID,
                'login' => $loginAdmin,
            ]);
            $this->command->info("Admin criado: USUARIO_ID={$user->USUARIO_ID}");
            $this->command->warn("Senha inicial: {$senhaInicial} — DEVE SER TROCADA NO PRIMEIRO ACESSO");
        } else {
            // Idempotente: atualiza senha + reseta flags de troca
            $existing->USUARIO_SENHA = Hash::make($senhaInicial);
            $existing->USUARIO_ALTERAR_SENHA = 1;
            $existing->USUARIO_ATIVO = 1;
            $existing->USUARIO_VIGENCIA = null;
            if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                $existing->USUARIO_PRIMEIRO_ACESSO = 1;
            }
            $existing->save();
            Log::info('AdminProdSeeder: admin atualizado', [
                'usuario_id' => $existing->USUARIO_ID,
            ]);
            $this->command->info("Admin atualizado: USUARIO_ID={$existing->USUARIO_ID}");
            $this->command->warn("Senha resetada para: {$senhaInicial} — DEVE SER TROCADA NO PRIMEIRO ACESSO");
        }

        // Atribuir role admin no RBAC v3 (gente_assignment) se tabela existir
        if (Schema::hasTable('gente_assignment') && Schema::hasTable('gente_role')) {
            $user = Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();
            $adminRole = DB::table('gente_role')->where('role_slug', 'admin')->first();

            if ($user && $adminRole) {
                $exists = DB::table('gente_assignment')
                    ->where('USUARIO_ID', $user->USUARIO_ID)
                    ->where('role_id', $adminRole->role_id)
                    ->exists();

                if (! $exists) {
                    DB::table('gente_assignment')->insert([
                        'USUARIO_ID' => $user->USUARIO_ID,
                        'role_id' => $adminRole->role_id,
                        'scope_type' => 'global',
                        'scope_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info("Role admin atribuída em gente_assignment");
                } else {
                    $this->command->info("Role admin já atribuída em gente_assignment (idempotente)");
                }
            } else {
                $this->command->warn("Tabelas RBAC v3 vazias — role admin NÃO atribuída automaticamente. Cadastre via UI.");
            }
        }
    }
}
```

**Validações antes do commit:**

```bash
php -l database/seeders/AdminProdSeeder.php
```

**ATENÇÃO:** Este seeder NÃO entra no `DatabaseSeeder.php` (não roda automaticamente em `db:seed` geral). Só roda quando explicitamente chamado com `--class=AdminProdSeeder`. Isso é intencional pra evitar criar admin em ambientes de teste sem querer.

**Se o nome de coluna `role_slug` não bater no schema real:** verifique a tabela `gente_role` — pode ser `slug` ou `role_name`. Use `DESC gente_role` ou olhe a migration `2026_05_01_100200_create_gente_role_table.php` e ajuste o seeder.

---

## Validação após cada tarefa

Após Tarefas 1-6 (mudanças PHP), rodar:

```bash
php -l routes/web.php
php -l app/Http/Middleware/Authenticate.php
php -l app/Http/Middleware/SecurityHeaders.php
php -l app/Providers/RouteServiceProvider.php
```

Após Tarefa 5 (axios.js), rodar build local:

```bash
cd resources/gente-v3
npm run build
```

O build DEVE terminar com `✓ built in Xs` sem erros vermelhos. Mantenha o build local — Ronaldo NÃO precisa que você faça push do `public/build-v3/` (gerado no servidor durante deploy).

Após Tarefas 7-10 (deletes), rodar:

```bash
grep -r "Auth::routes" routes/ app/                            # deve não retornar nada
grep -r "view('auth.login')" routes/ app/                      # deve não retornar nada
grep -r "view('auth\\.login')" routes/ app/                    # deve não retornar nada
grep -r "/js/app.js" resources/views/                          # deve não retornar nada
grep -r "Auth\\\\LoginController" routes/ app/ config/         # deve não retornar nada
```

---

## Commits sugeridos (atômicos)

Faça commits separados para facilitar rollback se precisar:

1. `feat(routes): rota raiz / serve SPA Vue 3 + remove Auth::routes legado`  
   Tarefas 1, 2

2. `feat(middleware): Authenticate e RouteServiceProvider apontam para SPA Vue 3`  
   Tarefas 3, 4

3. `feat(spa): axios interceptor usa Vue Router em vez de window.location.href`  
   Tarefa 5

4. `feat(security): CSP relaxado para permitir Vuetify, MDI, Google Fonts e ViaCep`  
   Tarefa 6

5. `chore: remove Vue 2 blades e controllers Laravel UI`  
   Tarefa 7

6. `chore: remove dependência laravel/ui do composer`  
   Tarefa 8

7. `chore: remove sources Laravel Mix Vue 2 (webpack.mix, sass, js, package raiz)`  
   Tarefas 9, 10

**Push:**

```bash
git push origin producao-pmsl
```

**Commit 8 — Admin seeder (Tarefa 11):**

`feat(seed): AdminProdSeeder idempotente para criar admin canônico em produção`

**NÃO mexa em master, NÃO use `--force`.**

---

## Reportar para Ronaldo

Após push, retorne:

1. Lista dos commits criados (SHA + mensagem)
2. Output do `npm run build` (último ✓ built in)
3. Quaisquer arquivos que você decidiu NÃO deletar e por quê (Tarefa 7 e 9 têm grep prévio)
4. Output dos greps de validação (devem ser todos vazios)
5. Confirmação de que `php -l` passou em todos os arquivos PHP modificados/criados
6. Confirmação de que `AdminProdSeeder.php` foi criado e o nome da coluna em `gente_role` foi confirmado (`role_slug`, `slug` ou outro)

Ronaldo + Claude vão validar no servidor com:

```bash
cd /var/www/sistemagente.com
git pull origin producao-pmsl --ff-only

# Frontend
cd resources/gente-v3
sudo -u www-data npm run build
cd /var/www/sistemagente.com
chown -R www-data:www-data public/build-v3

# Backend caches
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan view:cache
systemctl restart php8.2-fpm

# Criar admin user (Tarefa 11)
sudo -u www-data php artisan db:seed --class=AdminProdSeeder --force
```

E testar em janela anônima:
- `http://sistemagente.com/` → deve retornar SPA Vue 3 (não mais blade Vue 2)
- `http://sistemagente.com/login` → deve renderizar `LoginView.vue` do Vue Router (tela bonita)
- Login com `admin` / `GenteAdmin@2026!PMSL` deve funcionar e exigir troca de senha
- Console do browser deve estar limpo (zero erros de CSP, zero MIME, zero `/js/app.js`)

---

## O que NÃO fazer

- ❌ NÃO remover `composer.json: "minimum-stability": "dev"` agora — outra issue separada (platform_check.php fix), tratamos pós-go-live
- ❌ NÃO rodar `composer update` ou `composer install` — vendor está estável, qualquer mexida regenera platform_check
- ❌ NÃO mexer em `routes/api_v3_*.php`, `routes/api.php` ou em controllers fora de `Auth/`
- ❌ NÃO deletar `resources/views/v3/app.blade.php` (esse é o entry do SPA Vue 3, MUITO importante)
- ❌ NÃO mexer em `resources/views/portarias/`, `resources/views/pdfs/`, `resources/views/emails/` (não são Vue 2, são Blade puro pra outros usos)
- ❌ NÃO usar `--force` em git push, NÃO criar branches novas
