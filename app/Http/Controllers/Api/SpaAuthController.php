<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SpaAuthController extends Controller
{
    /**
     * Login dedicado para o SPA Vue 3.
     * Usa Auth::attempt manual e retorna sempre JSON (nunca Redirect).
     */
    public function login(Request $request)
    {
        try {
            // Garante leitura do JSON body independente do Content-Type
            if ($request->isJson() || $request->getContent()) {
                $json = json_decode($request->getContent(), true);
                if (is_array($json)) {
                    $request->merge($json);
                }
            }

            // Validação básica
            $request->validate([
                'USUARIO_LOGIN' => 'required|string',
                'USUARIO_SENHA' => 'required|string',
            ]);

            $login = $request->input('USUARIO_LOGIN');
            $password = $request->input('USUARIO_SENHA');

            // Rate limiting básico por IP
            $throttleKey = Str::lower($login) . '|' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'message' => "Muitas tentativas. Aguarde {$seconds} segundos.",
                ], 429);
            }

            // Normaliza: remover não-numéricos exceto para "admin"
            if ($login !== 'admin') {
                $login = preg_replace('/[^0-9]/', '', $login);
            }

            // Busca o usuário ativo
            $user = Usuario::where('USUARIO_LOGIN', $login)
                ->where('USUARIO_ATIVO', 1)
                ->first();

            if (!$user) {
                RateLimiter::hit($throttleKey, 60 * 30);
                return response()->json(['message' => 'Usuário não encontrado ou inativo.'], 401);
            }

            // Migração transparente MD5 → bcrypt
            if ($user->USUARIO_SENHA === md5($password)) {
                $user->USUARIO_SENHA = bcrypt($password);
                $user->USUARIO_ALTERAR_SENHA = 1;
                $user->save();
            }

            // Verifica a senha
            if (!Hash::check($password, $user->USUARIO_SENHA)) {
                RateLimiter::hit($throttleKey, 60 * 30);
                return response()->json(['message' => 'Senha incorreta.'], 401);
            }

            // Verifica vigência
            if ($user->USUARIO_VIGENCIA !== null && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
                return response()->json(['message' => 'Usuário com acesso expirado.'], 403);
            }

            // Loga o usuário na sessão Web (stateful)
            Auth::login($user, false);
            $request->session()->regenerate();

            // Atualiza último acesso (ignora erro caso coluna não exista)
            try {
                $user->USUARIO_ULTIMO_ACESSO = date('Y-m-d H:i:s');
                $user->save();
            } catch (\Exception $ex) {
                \Log::warning('SpaAuth: não foi possível atualizar USUARIO_ULTIMO_ACESSO: ' . $ex->getMessage());
            }

            // Em ambiente não-produtivo, garante vínculo do admin a um FUNCIONARIO
            // para telas que dependem de FUNCIONARIO_ID (ponto, declarações etc).
            $this->ensureAdminFuncionarioVinculo($user);

            RateLimiter::clear($throttleKey);

            return response()->json([
                'message' => 'Autenticado com sucesso.',
                'user' => [
                    'id' => $user->USUARIO_ID,
                    'nome' => $user->USUARIO_NOME,
                    'login' => $user->USUARIO_LOGIN,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Re-lança para que o Laravel retorne 422 com os erros de validação
            \Log::warning('SpaAuth: ValidationException: ' . json_encode($ve->errors()));
            throw $ve;

        } catch (\Exception $e) {
            \Log::error('SpaAuthController@login falhou: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->only('USUARIO_LOGIN'),
            ]);

            return response()->json([
                'message' => 'Erro interno no servidor.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout da sessão SPA.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.'], 200);
    }

    /**
     * Retorna o usuário autenticado atual (para o Pinia/Store).
     */
    public function me(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user = Auth::user();

        // Busca o perfil via relacionamento
        $perfilNome = null;
        try {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
        } catch (\Exception $e) {
            // Ignora erros de relacionamento
        }

        if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuário' || strtolower(trim($perfilNome)) === 'usuario') {
            $perfilNome = 'funcionario';
        }

        return response()->json([
            'id' => $user->USUARIO_ID,
            'nome' => $user->USUARIO_NOME,
            'login' => $user->USUARIO_LOGIN,
            'email' => $user->USUARIO_EMAIL,
            'perfil' => $perfilNome,
            'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
        ]);
    }

    /**
     * Chamado pelo login em routes/web.php e por {@see login()} aqui.
     * Em produção não altera dados (no-op).
     */
    public function applyDevAdminFuncionarioVinculo(Usuario $user): void
    {
        $this->ensureAdminFuncionarioVinculo($user);
    }

    private function ensureAdminFuncionarioVinculo(Usuario $user): void
    {
        if (app()->isProduction()) {
            return;
        }

        if (strtolower((string) ($user->USUARIO_LOGIN ?? '')) !== 'admin') {
            return;
        }

        if (
            !\Illuminate\Support\Facades\Schema::hasTable('FUNCIONARIO') ||
            !\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')
        ) {
            return;
        }

        $jaVinculado = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->first();
        if ($jaVinculado) {
            $this->ensureAdminLotacao((int) $jaVinculado->FUNCIONARIO_ID);
            return;
        }

        // 1) tenta reaproveitar funcionário sem usuário
        $funcLivre = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
            ->whereNull('USUARIO_ID')
            ->orderBy('FUNCIONARIO_ID')
            ->first();
        if ($funcLivre) {
            \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $funcLivre->FUNCIONARIO_ID)
                ->update(['USUARIO_ID' => $user->USUARIO_ID]);
            $this->ensureAdminLotacao((int) $funcLivre->FUNCIONARIO_ID);
            return;
        }

        // 2) fallback: cria pessoa/funcionário técnico para o admin
        if (!\Illuminate\Support\Facades\Schema::hasTable('PESSOA')) {
            return;
        }

        try {
            $pessoaCols = \Illuminate\Support\Facades\Schema::getColumnListing('PESSOA');
            $funcCols = \Illuminate\Support\Facades\Schema::getColumnListing('FUNCIONARIO');

            $cpfAdmin = '00000000000';
            $pessoaId = \Illuminate\Support\Facades\DB::table('PESSOA')
                ->where('PESSOA_CPF_NUMERO', $cpfAdmin)
                ->value('PESSOA_ID');

            if (!$pessoaId) {
                $pessoaData = [];
                if (in_array('PESSOA_NOME', $pessoaCols, true))
                    $pessoaData['PESSOA_NOME'] = $user->USUARIO_NOME ?: 'Administrador Técnico';
                if (in_array('PESSOA_CPF_NUMERO', $pessoaCols, true))
                    $pessoaData['PESSOA_CPF_NUMERO'] = $cpfAdmin;
                if (in_array('PESSOA_CPF', $pessoaCols, true))
                    $pessoaData['PESSOA_CPF'] = $cpfAdmin;
                if (in_array('PESSOA_ATIVO', $pessoaCols, true))
                    $pessoaData['PESSOA_ATIVO'] = 1;
                if (in_array('PESSOA_DATA_CADASTRO', $pessoaCols, true))
                    $pessoaData['PESSOA_DATA_CADASTRO'] = now()->toDateString();
                if (in_array('PESSOA_DATA_NASCIMENTO', $pessoaCols, true))
                    $pessoaData['PESSOA_DATA_NASCIMENTO'] = '1990-01-01';
                if (in_array('PESSOA_NASC', $pessoaCols, true))
                    $pessoaData['PESSOA_NASC'] = '1990-01-01';

                $pessoaId = \Illuminate\Support\Facades\DB::table('PESSOA')->insertGetId($pessoaData);
            }

            $funcData = ['PESSOA_ID' => $pessoaId, 'USUARIO_ID' => $user->USUARIO_ID];
            if (in_array('FUNCIONARIO_MATRICULA', $funcCols, true))
                $funcData['FUNCIONARIO_MATRICULA'] = 'ADM-FAKE-' . str_pad((string) $user->USUARIO_ID, 4, '0', STR_PAD_LEFT);
            if (in_array('FUNCIONARIO_ATIVO', $funcCols, true))
                $funcData['FUNCIONARIO_ATIVO'] = 1;
            if (in_array('FUNCIONARIO_DATA_INICIO', $funcCols, true))
                $funcData['FUNCIONARIO_DATA_INICIO'] = now()->toDateString();
            if (in_array('FUNCIONARIO_DATA_CADASTRO', $funcCols, true))
                $funcData['FUNCIONARIO_DATA_CADASTRO'] = now()->toDateString();
            if (in_array('FUNCIONARIO_DATA_ATUALIZACAO', $funcCols, true))
                $funcData['FUNCIONARIO_DATA_ATUALIZACAO'] = now()->toDateString();
            if (in_array('FUNCIONARIO_REGIME_PREV', $funcCols, true))
                $funcData['FUNCIONARIO_REGIME_PREV'] = 'RPPS';

            $funcId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->insertGetId($funcData);
            $this->ensureAdminLotacao((int) $funcId);
        } catch (\Throwable $e) {
            \Log::warning('SpaAuth: não foi possível garantir vínculo técnico do admin: ' . $e->getMessage());
        }
    }

    private function ensureAdminLotacao(int $funcionarioId): void
    {
        if (
            $funcionarioId <= 0 ||
            !\Illuminate\Support\Facades\Schema::hasTable('LOTACAO') ||
            !\Illuminate\Support\Facades\Schema::hasTable('SETOR')
        ) {
            return;
        }

        $lotAtiva = \Illuminate\Support\Facades\DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->whereNull('LOTACAO_DATA_FIM')
            ->exists();
        if ($lotAtiva) {
            return;
        }

        $setorId = \Illuminate\Support\Facades\DB::table('SETOR')->value('SETOR_ID');
        if (!$setorId) {
            return;
        }

        $dados = ['FUNCIONARIO_ID' => $funcionarioId, 'SETOR_ID' => $setorId];
        $lotCols = \Illuminate\Support\Facades\Schema::getColumnListing('LOTACAO');
        if (in_array('LOTACAO_DATA_INICIO', $lotCols, true))
            $dados['LOTACAO_DATA_INICIO'] = now()->toDateString();
        if (in_array('LOTACAO_DATA_CADASTRO', $lotCols, true))
            $dados['LOTACAO_DATA_CADASTRO'] = now()->toDateString();
        if (in_array('LOTACAO_DATA_ATUALIZACAO', $lotCols, true))
            $dados['LOTACAO_DATA_ATUALIZACAO'] = now()->toDateString();

        \Illuminate\Support\Facades\DB::table('LOTACAO')->insert($dados);
    }
}
