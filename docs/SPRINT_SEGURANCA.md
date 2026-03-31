# GENTE v3 — Sprint Segurança Completa
**Para:** Antygravity (agente executor)
**Data:** 23/03/2026 | **Prioridade:** ALTA — executar antes do deploy VPS

> Leitura obrigatória antes de iniciar: `.agent/workflows/regras-gerais.md`
> Cada task é independente. Confirme cada uma antes de avançar.

---

## Estado atual (o que já existe — NÃO reimplementar)

| Item | Arquivo | Status |
|------|---------|--------|
| CSRF token | `app/Http/Middleware/VerifyCsrfToken.php` | ✅ ativo |
| Cookies criptografados | `app/Http/Middleware/EncryptCookies.php` | ✅ ativo |
| Rate limit login | `web.php` throttle:10,1 no grupo api/auth | ✅ ativo |
| Rate limit global API | `Kernel.php` throttle:api | ✅ ativo |
| CORS origens explícitas | `config/cors.php` | ✅ mas só localhost |
| Auditoria de mutações | `app/Http/Middleware/AuditLog.php` | ✅ ativo |
| Log canal security | `config/logging.php` | ✅ ativo |
| Bcrypt nas senhas | `routes/web.php` login | ✅ ativo |
| Rotas /dev/* protegidas | `routes/web.php` | ✅ app()->isLocal() |

---

## SEC-PROD-01 — Headers HTTP de segurança (5 min)

**Arquivo:** criar `app/Http/Middleware/SecurityHeaders.php`

```php
<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Impede que o site seja carregado em iframe (proteção clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Impede MIME sniffing — browser obedece o Content-Type declarado
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Força HTTPS por 1 ano (ativar SÓ após HTTPS estar configurado na VPS)
        // Remover o comentário quando o deploy estiver em produção com SSL
        // $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Política de referrer — não vaza URL completa para sites externos
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Desativa funcionalidades perigosas do browser
        $response->headers->set('Permissions-Policy',
            'geolocation=(), camera=(), microphone=(), payment=(), usb=()');

        // Content Security Policy — permite apenas recursos do próprio domínio
        // Nonces de script são geridos pelo Vue no build — esta política básica
        // cobre o backend Laravel (views Blade + API responses)
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: blob:; " .
            "font-src 'self' data:; " .
            "connect-src 'self'; " .
            "frame-ancestors 'self';"
        );

        // Remove header que revela versão do servidor
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
```

**Registrar em `app/Http/Kernel.php`**, no array `$middleware` (global — roda em toda request):
```php
\App\Http\Middleware\SecurityHeaders::class,
```

**Critério de aceite:** curl -I https://dominio responde com X-Frame-Options e X-Content-Type-Options nos headers.

---

## SEC-PROD-02 — CAPTCHA no login (proteção contra bots)

**Usar:** Google reCAPTCHA v3 (invisível — sem caixa "não sou robô")

**Passos:**
1. Registrar domínio em https://www.google.com/recaptcha → obter `SITE_KEY` (frontend) e `SECRET_KEY` (backend)

**Arquivo `.env`:**
```env
RECAPTCHA_SITE_KEY=sua_site_key_aqui
RECAPTCHA_SECRET_KEY=sua_secret_key_aqui
```

**Verificação no backend** — adicionar em `routes/web.php`, no handler do login, ANTES de verificar a senha:
```php
// SEC-PROD-02: verificar reCAPTCHA v3 (score >= 0.5 = humano)
if ($request->has('recaptcha_token') && app()->isProduction()) {
    $resp = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        'secret'   => env('RECAPTCHA_SECRET_KEY'),
        'response' => $request->input('recaptcha_token'),
        'remoteip' => $request->ip(),
    ])->json();
    if (!($resp['success'] ?? false) || ($resp['score'] ?? 0) < 0.5) {
        return response()->json(['erro' => 'Verificação de segurança falhou.'], 422);
    }
}
```

**Frontend** — adicionar em `resources/gente-v3/src/views/auth/LoginView.vue`:
```javascript
// No mounted():
const script = document.createElement('script')
script.src = `https://www.google.com/recaptcha/api.js?render=${import.meta.env.VITE_RECAPTCHA_SITE_KEY}`
document.head.appendChild(script)

// Na função handleLogin(), antes de chamar a API:
const token = await new Promise(resolve =>
  window.grecaptcha.ready(() =>
    window.grecaptcha.execute(import.meta.env.VITE_RECAPTCHA_SITE_KEY, { action: 'login' })
      .then(resolve)
  )
)
// Incluir token no payload:
payload.recaptcha_token = token
```

**`.env` do frontend (Vite):**
```env
VITE_RECAPTCHA_SITE_KEY=mesma_site_key_do_backend
```

**Critério de aceite:** bot tentando login sem token recebe 422. Usuário humano loga normalmente.

---

## SEC-PROD-03 — Bloqueio temporário por IP (anti brute-force)

O throttle existente limita 10 tentativas por minuto mas não bloqueia por tempo prolongado.
Adicionar bloqueio de 15 minutos após 5 tentativas com senha errada.

**Migration:** `database/migrations/2026_03_24_000010_create_login_attempts_table.php`
```php
Schema::create('LOGIN_ATTEMPTS', function (Blueprint $table) {
    $table->id();
    $table->string('IP', 45);              // IPv4 ou IPv6
    $table->string('LOGIN', 100)->nullable();
    $table->boolean('SUCESSO')->default(false);
    $table->timestamp('TENTATIVA_EM')->useCurrent();
    $table->index(['IP', 'TENTATIVA_EM']);
    $table->index(['LOGIN', 'TENTATIVA_EM']);
});
```

**Lógica** — adicionar no início do handler de login em `web.php`:
```php
// SEC-PROD-03: verificar bloqueio por IP
$ip = $request->ip();
$janela = now()->subMinutes(15);

$tentativas = DB::table('LOGIN_ATTEMPTS')
    ->where('IP', $ip)
    ->where('SUCESSO', false)
    ->where('TENTATIVA_EM', '>=', $janela)
    ->count();

if ($tentativas >= 5) {
    Log::channel('security')->warning('login_bloqueado_ip', ['ip' => $ip, 'tentativas' => $tentativas]);
    return response()->json([
        'erro' => 'Muitas tentativas incorretas. Aguarde 15 minutos.',
        'bloqueado_ate' => now()->addMinutes(15)->toIso8601String(),
    ], 429);
}

// ... resto da lógica de login ...

// Registrar tentativa (sucesso ou falha):
DB::table('LOGIN_ATTEMPTS')->insert([
    'IP'           => $ip,
    'LOGIN'        => $login,
    'SUCESSO'      => $autenticado,
    'TENTATIVA_EM' => now(),
]);
```

**Limpeza automática** — adicionar em `app/Console/Kernel.php`:
```php
$schedule->call(function () {
    DB::table('LOGIN_ATTEMPTS')->where('TENTATIVA_EM', '<', now()->subDay())->delete();
})->daily();
```

**Critério de aceite:** 5 tentativas erradas → próxima tentativa retorna 429 com tempo de espera.

---

## SEC-PROD-04 — Política de senha mínima

**Arquivo:** `routes/web.php` — na rota `POST /api/auth/troca-senha` e em qualquer criação de usuário.

**Regras:**
```php
// Adicionar validação antes de gravar senha nova:
$request->validate([
    'nova_senha' => [
        'required',
        'string',
        'min:8',
        'regex:/[A-Z]/',        // ao menos 1 maiúscula
        'regex:/[0-9]/',        // ao menos 1 número
        'regex:/[!@#$%^&*]/',   // ao menos 1 especial
        'not_regex:/^(.)\1+$/', // não permite "aaaaaaaa"
    ],
], [
    'nova_senha.min'       => 'A senha deve ter ao menos 8 caracteres.',
    'nova_senha.regex'     => 'A senha deve conter letras maiúsculas, números e caracteres especiais.',
]);
```

**Frontend** — `LoginView.vue` modal de troca de senha — adicionar indicador visual de força:
```javascript
const forcaSenha = computed(() => {
    const s = novaSenha.value
    let pontos = 0
    if (s.length >= 8) pontos++
    if (/[A-Z]/.test(s)) pontos++
    if (/[0-9]/.test(s)) pontos++
    if (/[!@#$%^&*]/.test(s)) pontos++
    return { pontos, cor: ['#ef4444','#f59e0b','#f59e0b','#10b981','#10b981'][pontos] }
})
```

**Critério de aceite:** senha "123456" rejeitada pelo backend com mensagem clara.

---

## SEC-PROD-05 — Validação e sanitização de upload de arquivos

O sistema aceita uploads em `AtestadosMedicosView`, `DocumentoView`, `AbonoFaltasView`.

**Arquivo:** criar `app/Http/Middleware/ValidateFileUpload.php`

```php
<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class ValidateFileUpload
{
    // Tipos MIME realmente permitidos (verificados no conteúdo, não só na extensão)
    const ALLOWED = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // xlsx
    ];

    // Tamanho máximo por arquivo: 10 MB
    const MAX_SIZE = 10 * 1024 * 1024;

    public function handle(Request $request, Closure $next)
    {
        foreach ($request->allFiles() as $file) {
            $files = is_array($file) ? $file : [$file];
            foreach ($files as $f) {
                // Verificar tamanho
                if ($f->getSize() > self::MAX_SIZE) {
                    return response()->json([
                        'erro' => "Arquivo {$f->getClientOriginalName()} excede o limite de 10 MB."
                    ], 422);
                }
                // Verificar MIME real (não confia na extensão declarada pelo cliente)
                $mimeReal = mime_content_type($f->getRealPath());
                if (!in_array($mimeReal, self::ALLOWED, true)) {
                    return response()->json([
                        'erro' => "Tipo de arquivo não permitido: {$f->getClientOriginalName()} ({$mimeReal})."
                    ], 422);
                }
                // Verificar extensão duplicada (tentativa de bypass: arquivo.php.jpg)
                $nome = $f->getClientOriginalName();
                $partes = explode('.', $nome);
                if (count($partes) > 2) {
                    $extensoesSuspeitas = ['php', 'phtml', 'php3', 'php4', 'php5', 'asp', 'aspx', 'js', 'sh'];
                    foreach (array_slice($partes, 0, -1) as $parte) {
                        if (in_array(strtolower($parte), $extensoesSuspeitas)) {
                            return response()->json(['erro' => 'Nome de arquivo suspeito rejeitado.'], 422);
                        }
                    }
                }
            }
        }
        return $next($request);
    }
}
```

**Registrar em `Kernel.php`** como middleware de rota:
```php
'upload.safe' => \App\Http\Middleware\ValidateFileUpload::class,
```

**Aplicar** em todas as rotas que aceitam upload:
```php
Route::post('/atestados', ...)->middleware('upload.safe');
Route::post('/documentos', ...)->middleware('upload.safe');
Route::post('/abono-faltas', ...)->middleware('upload.safe');
```

**Critério de aceite:** upload de arquivo `shell.php.jpg` rejeitado com 422.

---

## SEC-PROD-06 — Timeout de sessão por inatividade

**Arquivo:** `config/session.php`
```php
// Sessão expira após 2 horas de inatividade (7200 segundos)
'lifetime'        => 120,      // minutos
'expire_on_close' => false,    // não expira ao fechar a aba (para tablets corporativos)
'secure'          => env('SESSION_SECURE_COOKIE', false), // true em produção (HTTPS)
'http_only'       => true,     // cookie não acessível via JavaScript
'same_site'       => 'lax',    // proteção CSRF adicional
```

**`.env` de produção:**
```env
SESSION_SECURE_COOKIE=true
SESSION_LIFETIME=120
```

**Frontend** — detectar expiração e redirecionar para login:
```javascript
// Em plugins/axios.js, no interceptor de resposta (já existe):
// Adicionar tratamento do 401:
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Sessão expirada — redirecionar para login com mensagem
            const authStore = useAuthStore()
            authStore.user = null
            window.location.href = '/login?sessao_expirada=1'
        }
        return Promise.reject(error)
    }
)
```

**LoginView.vue** — detectar parâmetro e exibir aviso:
```javascript
if (new URLSearchParams(window.location.search).get('sessao_expirada')) {
    toast.warning('Sua sessão expirou por inatividade. Faça login novamente.')
}
```

---

## SEC-PROD-07 — CORS atualizado para produção

**Arquivo:** `config/cors.php`

Adicionar domínio de produção quando disponível:
```php
'allowed_origins' => [
    'http://127.0.0.1:5173',
    'http://localhost:5173',
    'http://127.0.0.1:8000',
    'http://localhost:8000',
    // Adicionar APÓS configurar o domínio na VPS:
    // 'https://gente.pmsaoluis.ma.gov.br',
    // 'https://gente.rrtecnol.com.br',
],
```

**Regra:** NUNCA usar `'*'` com `supports_credentials = true`. É uma vulnerabilidade grave.

---

## SEC-PROD-08 — Sanitização de saídas (XSS)

Todas as strings vindas do banco que são exibidas em HTML devem ser escapadas.
O Vue.js já escapa `{{ }}` por padrão — o risco real é no `v-html`.

**Varredura — localizar todos os usos de `v-html` no frontend:**
Buscar em `resources/gente-v3/src/` pelo padrão `v-html`.

Para cada ocorrência, verificar se o conteúdo vem de input do usuário (como `ComunicadosView.vue` que usa `v-html` para renderizar o corpo do comunicado). Sanitizar com `DOMPurify`:

```javascript
// resources/gente-v3/src/plugins/sanitize.js
import DOMPurify from 'dompurify'

export const sanitize = (html) => DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'a'],
    ALLOWED_ATTR: ['href', 'target'],
})
```

**Instalar:** `npm install dompurify`

**Usar nos componentes:**
```javascript
import { sanitize } from '@/plugins/sanitize'
// No template:
<div v-html="sanitize(comunicado.conteudo)"></div>
```

---

## SEC-PROD-09 — Validação de inputs no backend (SQL Injection)

Laravel usa PDO com prepared statements — já protege contra SQL injection básico.
O risco está nas queries raw que usam `DB::statement` ou `DB::select` com interpolação de string.

**Buscar em `routes/web.php` e arquivos de rota por:**
```
DB::statement("... {$variavel}
DB::select("... {$variavel}
whereRaw("... {$variavel}
```

Substituir por bindings:
```php
// ERRADO (vulnerável):
DB::statement("UPDATE FOLHA SET STATUS = '{$request->status}'");

// CORRETO (binding):
DB::statement("UPDATE FOLHA SET STATUS = ?", [$request->status]);
// ou:
DB::table('FOLHA')->where('FOLHA_ID', $id)->update(['STATUS' => $request->status]);
```

**Critério de aceite:** varredura por interpolação direta nas queries retorna zero ocorrências.

---

## SEC-PROD-10 — Log de eventos de segurança

Expandir o canal `security` já existente para capturar mais eventos.

**Arquivo:** `config/logging.php` — verificar se o canal security está configurado para 90 dias. Se não:
```php
'security' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/security.log'),
    'level'  => 'info',
    'days'   => 90,
],
```

**Adicionar logs** nos seguintes pontos (buscar nas routes e adicionar):
```php
// Login bem-sucedido:
Log::channel('security')->info('login_sucesso', ['usuario' => $login, 'ip' => $request->ip()]);

// Login com falha:
Log::channel('security')->warning('login_falha', ['usuario' => $login, 'ip' => $request->ip()]);

// Acesso negado (403):
Log::channel('security')->warning('acesso_negado', [
    'usuario' => Auth::id(), 'rota' => $request->path(), 'ip' => $request->ip()
]);

// Operação sensível (exoneração, alteração de salário):
Log::channel('security')->info('operacao_sensivel', [
    'acao'    => 'exoneracao',
    'alvo'    => $funcionarioId,
    'usuario' => Auth::id(),
    'ip'      => $request->ip(),
]);
```

---

## Checklist de execução

```
[ ] SEC-PROD-01  SecurityHeaders middleware criado e registrado em Kernel.php
[ ] SEC-PROD-02  reCAPTCHA v3 integrado no login (backend + frontend)
[ ] SEC-PROD-03  Bloqueio por IP: migration + lógica no handler de login
[ ] SEC-PROD-04  Política de senha: validação backend + indicador visual frontend
[ ] SEC-PROD-05  ValidateFileUpload middleware criado e aplicado nas rotas de upload
[ ] SEC-PROD-06  Timeout de sessão configurado + redirect no frontend após 401
[ ] SEC-PROD-07  CORS: adicionar domínio de produção quando disponível
[ ] SEC-PROD-08  DOMPurify instalado e aplicado em todos os v-html com conteúdo do usuário
[ ] SEC-PROD-09  Varredura de queries raw com interpolação → substituir por bindings
[ ] SEC-PROD-10  Log de segurança expandido com os novos eventos

Atualizar MAPA_ESTADO_REAL.md ao concluir cada task.
```

---

## SEC-PROD-10-B — Arquivamento mensal de logs de segurança

Implementar junto com SEC-PROD-10 no mesmo `app/Console/Kernel.php`.

**Lógica:** no primeiro dia de cada mês, comprimir o log do mês anterior em `.gz`
e mover para `storage/logs/security/arquivo/`. Nunca apagar — apenas arquivar.

```php
// Em app/Console/Kernel.php, dentro do método schedule():

// Arquivamento mensal do security.log (1º dia de cada mês às 02:00)
$schedule->call(function () {
    $logPath     = storage_path('logs/security.log');
    $arquivoDir  = storage_path('logs/security/arquivo');
    $mesAnterior = now()->subMonth()->format('Y-m');
    $destino     = "$arquivoDir/security-$mesAnterior.log.gz";

    if (!file_exists($logPath)) return;
    if (!is_dir($arquivoDir)) mkdir($arquivoDir, 0755, true);

    // Comprimir e mover
    $conteudo = file_get_contents($logPath);
    $gz = gzopen($destino, 'w9');
    gzwrite($gz, $conteudo);
    gzclose($gz);

    // Apagar o original só após confirmar que o .gz foi criado com sucesso
    if (file_exists($destino) && filesize($destino) > 0) {
        file_put_contents($logPath, ''); // truncar (não apagar) — mantém o handle aberto do Laravel
        \Illuminate\Support\Facades\Log::channel('security')
            ->info('log_arquivado', ['arquivo' => $destino, 'mes' => $mesAnterior]);
    }
})->monthlyOn(1, '02:00')->name('security-log-arquivamento')->withoutOverlapping();

// Manutenção: alertar se arquivo .gz corrompido (verificação trimestral)
$schedule->call(function () {
    $arquivoDir = storage_path('logs/security/arquivo');
    if (!is_dir($arquivoDir)) return;
    foreach (glob("$arquivoDir/*.gz") as $gz) {
        $handle = @gzopen($gz, 'r');
        if (!$handle) {
            \Illuminate\Support\Facades\Log::channel('security')
                ->error('log_arquivo_corrompido', ['arquivo' => $gz]);
        } else {
            gzclose($handle);
        }
    }
})->quarterly();
```

**Resultado da política de retenção:**

| Tipo | Retenção | Formato |
|------|----------|---------|
| `security.log` (ativo) | Mês corrente | Texto plano |
| `security/arquivo/security-YYYY-MM.log.gz` | Indefinido | Comprimido |
| `LOGIN_ATTEMPTS` (tabela) | 1 dia | DB (limpo pelo schedule diário) |

**Critério de aceite:** após execução manual do schedule, existe
`storage/logs/security/arquivo/security-YYYY-MM.log.gz` com conteúdo legível via `gzcat`.

---

## O que NÃO implementar ainda (pós-VPS, pós-contrato)

| Item | Motivo de adiar |
|------|----------------|
| 2FA (TOTP via app) | Requer UX de cadastro do token — sprint separada |
| WAF (Web Application Firewall) | Infra da VPS — configurar no Nginx/Cloudflare |
| SIEM / alertas em tempo real | Requer infraestrutura de monitoramento dedicada |
| Pen test externo | Contratar empresa após o sistema ir a produção |
| Bug bounty program | Fase de expansão para outros municípios |

*GENTE v3 — Sprint Segurança | RR TECNOL | 23/03/2026*
