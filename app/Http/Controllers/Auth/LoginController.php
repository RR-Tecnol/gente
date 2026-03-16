<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Quantidade máxima de tentativas erradas de senha
     */
    protected function maxAttempts()
    {
        return 5;
    }

    /**
     * Minutos que o login ficará bloqueado após os erros
     */
    protected function decayMinutes()
    {
        return 30; // Bloqueia IP e login por 30 minutos após 5 erros
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function username(): string
    {
        return "USUARIO_LOGIN";
    }

    protected function attemptLogin(Request $request): bool
    {
        $loginWebKey = null;
        $sessionData = $request->session()->all();
        foreach ($sessionData as $key => $value) {
            if (str_starts_with($key, 'login_web_')) {
                $loginWebKey = $key;
                $request->session()->forget($loginWebKey);
                break;
            }
        }

        $login = $request->post('USUARIO_LOGIN');
        $password = $request->post('USUARIO_SENHA');

        if ($login !== 'admin') {
            $login = preg_replace('/[^0-9]/', '', $login);
        }

        $user = Usuario::where('USUARIO_LOGIN', $login)
            ->where('USUARIO_ATIVO', 1)
            ->first();

        if ($user) {
            // Migração transparente de MD5 para bcrypt
            if ($user->USUARIO_SENHA === md5($password)) {
                $user->USUARIO_SENHA = $password; // o mutator setPasswordAttribute usará bcrypt()
                $user->USUARIO_ALTERAR_SENHA = 1;
                $user->save();
            }

            if (\Hash::check($password, $user->USUARIO_SENHA)) {
                if ($user->USUARIO_VIGENCIA >= date('Y-m-d') || $user->USUARIO_VIGENCIA == null) {
                    Auth::login($user, false);
                    $user->USUARIO_ULTIMO_ACESSO = date("Y-m-d H:i:s");
                    $user->save();
                    return true;
                }
            }
        }

        return false;
    }

    protected function validateLogin(Request $request)
    {
        // rate limit protection (aproveita o ThrottlesLogins que exige validação básica primeiro)
        $request->validate([
            $this->username() => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === 'admin') {
                        return; // Bypass exclusívo para o setup seeder
                    }

                    $cpf = preg_replace('/[^0-9]/', '', $value);

                    // Validação de tamanho ou sequência de dígitos iguais (ex: 111.111.111-11)
                    if (strlen($cpf) != 11 || (preg_match('/(\d)\1{10}/', $cpf) && $cpf !== '00000000000')) {
                        $fail('O campo Login deve ser um CPF válido (Formato ou tamanho incorreto).');
                        return;
                    }

                    // Ignora o cálculo do dígito verificador se for o usuário master '00000000000'
                    if ($cpf === '00000000000') {
                        return;
                    }

                    // Validação dos Dígitos Verificadores do CPF
                    for ($t = 9; $t < 11; $t++) {
                        for ($d = 0, $c = 0; $c < $t; $c++) {
                            $d += $cpf[$c] * (($t + 1) - $c);
                        }
                        $d = ((10 * $d) % 11) % 10;
                        if ($cpf[$c] != $d) {
                            $fail('O CPF informado possui dígitos matemáticos inválidos.');
                            return;
                        }
                    }
                }
            ],
            'USUARIO_SENHA' => 'required|string'
        ]);
    }
}
