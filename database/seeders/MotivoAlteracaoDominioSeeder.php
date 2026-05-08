<?php

namespace Database\Seeders;

use App\Domain\Escala\MotivoAlteracaoEscala;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza o banco com as regras canónicas definidas em {@see MotivoAlteracaoEscala}.
 * O banco é espelho (ID estável, FK, listagem); a verdade de comportamento está no domínio.
 */
class MotivoAlteracaoDominioSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            return;
        }

        $cols = Schema::getColumnListing('MOTIVO_ALTERACAO_DOMINIO');
        $temSigla = in_array('SIGLA', $cols, true);

        foreach (MotivoAlteracaoEscala::definicoesCanonicas() as $d) {
            $payload = [
                'TITULO' => $d['titulo'],
                'DESCRICAO' => $d['descricao'],
                'EXIGE_DOCUMENTO' => $d['exige_documento'] ? 1 : 0,
                'ATIVO' => 1,
            ];
            if ($temSigla) {
                $payload['SIGLA'] = $d['sigla'];
            }

            if ($temSigla) {
                $exists = DB::table('MOTIVO_ALTERACAO_DOMINIO')
                    ->where('SIGLA', $d['sigla'])
                    ->first();
                if ($exists) {
                    DB::table('MOTIVO_ALTERACAO_DOMINIO')
                        ->where('MOTIVO_ALTERACAO_ID', $exists->MOTIVO_ALTERACAO_ID)
                        ->update($payload);
                } else {
                    DB::table('MOTIVO_ALTERACAO_DOMINIO')->insert($payload);
                }
            } else {
                $exists = DB::table('MOTIVO_ALTERACAO_DOMINIO')
                    ->where('TITULO', $d['titulo'])
                    ->first();
                if ($exists) {
                    DB::table('MOTIVO_ALTERACAO_DOMINIO')
                        ->where('MOTIVO_ALTERACAO_ID', $exists->MOTIVO_ALTERACAO_ID)
                        ->update($payload);
                } else {
                    DB::table('MOTIVO_ALTERACAO_DOMINIO')->insert($payload);
                }
            }
        }
    }
}
