<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    public function test_auth_flow_password_change_gate_and_uppercase_login(): void
    {
        if (! Schema::hasTable('USUARIO')) {
            $this->markTestSkipped('Tabela USUARIO inexistente neste ambiente de teste.');
        }

        $login = 'authtest+' . uniqid() . '@saoluis.ma.gov.br';
        $senhaAtual = 'AtualTeste@2026';
        $senhaNova = 'NovaTeste@2026';

        $userId = $this->criarUsuarioTeste($login, $senhaAtual);
        if ($userId === null) {
            $this->markTestSkipped('Não foi possível criar usuário de teste (schema legado incompatível).');
        }

        try {
            // 1) Login com e-mail em CAIXA ALTA deve funcionar via normalização.
            $resLogin = $this->postJson('/api/auth/login', [
                'USUARIO_LOGIN' => strtoupper($login),
                'USUARIO_SENHA' => $senhaAtual,
            ]);
            $resLogin->assertStatus(200)->assertJson(['ok' => true]);

            // 2) Com force_password_change ativo, endpoint sensível deve bloquear com 412.
            $resBloqueado = $this->getJson('/api/v3/funcionarios');
            $resBloqueado->assertStatus(412)->assertJson(['error' => 'PASSWORD_CHANGE_REQUIRED']);

            // 3) Troca de senha deve liberar fluxo.
            $resTroca = $this->postJson('/api/auth/change-password', [
                'senha_atual' => $senhaAtual,
                'senha_nova' => $senhaNova,
            ]);
            $resTroca->assertStatus(200)->assertJson(['ok' => true]);

            // 4) Após troca de senha, não pode mais bloquear por PASSWORD_CHANGE_REQUIRED.
            $resPosTroca = $this->getJson('/api/v3/funcionarios');
            $this->assertNotEquals(
                412,
                $resPosTroca->getStatusCode(),
                'Endpoint permaneceu bloqueado por PASSWORD_CHANGE_REQUIRED após troca de senha.'
            );
        } finally {
            DB::table('USUARIO')->where('USUARIO_ID', $userId)->delete();
        }
    }

    private function criarUsuarioTeste(string $login, string $senhaAtual): ?int
    {
        $dados = [
            'USUARIO_LOGIN' => strtolower(trim($login)),
            'USUARIO_SENHA' => Hash::make($senhaAtual),
            'USUARIO_NOME' => 'Auth Teste Automático',
        ];

        if (Schema::hasColumn('USUARIO', 'USUARIO_ATIVO')) {
            $dados['USUARIO_ATIVO'] = 1;
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
            $dados['USUARIO_EMAIL'] = strtolower(trim($login));
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
            $dados['USUARIO_ALTERAR_SENHA'] = 1;
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $dados['USUARIO_PRIMEIRO_ACESSO'] = 1;
        }

        try {
            return (int) DB::table('USUARIO')->insertGetId($dados);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

