<?php

namespace Tests\Unit;

use App\Support\GenteAssignmentValidator;
use App\Support\GenteTenantType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenteAssignmentValidatorTest extends TestCase
{
    public function test_tipo_invalido_lanca(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GenteAssignmentValidator::assertTenantRefExists('NAO_EXISTE', 1);
    }

    public function test_unidade_existente_ok(): void
    {
        if (! Schema::hasTable('UNIDADE')) {
            $this->markTestSkipped('UNIDADE indisponível.');
        }

        DB::beginTransaction();
        try {
            $nome = 'GENTE_ASSIGNMENT_VALIDATOR_TEST '.uniqid('', true);
            $id = (int) DB::table('UNIDADE')->insertGetId([
                'UNIDADE_NOME' => $nome,
                'UNIDADE_SIGLA' => 'TVAL',
                'UNIDADE_ATIVA' => 1,
            ]);

            GenteAssignmentValidator::assertTenantRefExists(GenteTenantType::UNIDADE, $id);
            $this->addToAssertionCount(1);
        } finally {
            DB::rollBack();
        }
    }
}
