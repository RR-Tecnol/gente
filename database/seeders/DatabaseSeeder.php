<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            TabelaGenericaSeeder::class,        // 1º: Enums do sistema
            PerfilSeeder::class,                // 2º: Perfis de acesso (15 perfis)
            ConfiguracaoSistemaSeeder::class,   // 3º: Configurações do sistema
            MenuSeeder::class,                  // 4º: APLICACAO + ACESSO (permissões)
            UsuarioSeeder::class,               // 5º: Usuário admin padrão (admin / admin123)
            RubricasCatalogoSeeder::class,      // 6º: 28 rubricas C1/C2/C3 (Sprint 3)
            VinculosPMSLzSeeder::class,         // 7º: 10 tipos de vínculo PMSLz (Sprint 3)
            OrganogramaPMSLzSeeder::class,      // 8º: 26 secretarias + setores PMSLz (Sprint 3)
            TabelaSalarialPMSLzSeeder::class,   // 9º: Tabela salarial 3 carreiras (Sprint 3)
            FuncionariosPMSLzSeeder::class,     // 10º: 18 funcionários de teste
            UsuariosPMSLzSeeder::class,         // 11º: 17 usuários de teste (gente@2026)
        ]);
    }
}
