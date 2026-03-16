<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            TabelaGenericaSeeder::class,       // 1º: Enums do sistema (tipos, status, etc.)
            PerfilSeeder::class,               // 2º: Perfis de acesso (15 perfis)
            ConfiguracaoSistemaSeeder::class,  // 3º: Configurações do sistema (módulos, thresholds)
            MenuSeeder::class,                 // 4º: APLICACAO (árvore de menu) + ACESSO (permissões)
            UsuarioSeeder::class,              // 5º: Usuário admin padrão (login: admin / senha: admin123)
        ]);
    }
}
