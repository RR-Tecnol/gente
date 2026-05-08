<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        /**
         * SECRETARIAS-SEED — ver {@see SecretariasSeed}.
         * Perfil fundador/supremo mantém-se à parte (dados pessoais e credenciais do fundador).
         */
        $this->call([
            SecretariasSeed::class,
            DaviSupremoSeeder::class,
        ]);

        if (filter_var(env('GENTE_SEED_AUDITOR_SEMAD_STANDALONE', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(AuditorSemadHomologSeeder::class);
        }
    }
}
