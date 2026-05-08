<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Fase 8A — Popula sisfolha_cargo_depara a partir de database/data/sisfolha_cargo_depara.v1.yaml.
 *
 * php artisan db:seed --class=SisfolhaCargoDeparaSeeder
 */
class SisfolhaCargoDeparaSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('sisfolha_cargo_depara')) {
            $this->command?->warn('Tabela sisfolha_cargo_depara inexistente — migrate primeiro.');

            return;
        }

        $path = database_path('data/sisfolha_cargo_depara.v1.yaml');
        if (! is_readable($path)) {
            $this->command?->warn('Ficheiro em falta: database/data/sisfolha_cargo_depara.v1.yaml');

            return;
        }

        $parsed = Yaml::parseFile($path);
        $entries = is_array($parsed) ? ($parsed['entries'] ?? []) : [];
        if (! is_array($entries) || $entries === []) {
            $this->command?->info('YAML sem entries — nada a inserir.');

            return;
        }

        $now = now();
        foreach ($entries as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cod = isset($row['codigo_sisfolha']) ? trim((string) $row['codigo_sisfolha']) : '';
            if ($cod === '') {
                continue;
            }
            $cargoId = isset($row['cargo_id']) ? (int) $row['cargo_id'] : 0;
            $pccv = isset($row['pccv_sigla']) ? (string) $row['pccv_sigla'] : null;
            $ativo = array_key_exists('ativo', $row) ? (bool) $row['ativo'] : true;

            $payload = [
                'cargo_id' => $cargoId > 0 ? $cargoId : null,
                'pccv_sigla' => $pccv !== '' ? $pccv : null,
                'ativo' => $ativo,
                'updated_at' => $now,
            ];
            if (DB::table('sisfolha_cargo_depara')->where('codigo_sisfolha', $cod)->exists()) {
                DB::table('sisfolha_cargo_depara')->where('codigo_sisfolha', $cod)->update($payload);
            } else {
                DB::table('sisfolha_cargo_depara')->insert(array_merge(
                    ['codigo_sisfolha' => $cod, 'created_at' => $now],
                    $payload
                ));
            }
        }

        $this->command?->info('sisfolha_cargo_depara actualizado a partir do YAML.');
    }
}
