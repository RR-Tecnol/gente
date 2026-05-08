<?php

namespace Tests\Unit;

use App\Support\RbacResolver;
use Tests\TestCase;

/**
 * {@see RbacResolver::semadMantaUiEnforceReadonly}: sem assignment SEMAD → false.
 */
class RbacResolverSemadMantaUiTest extends TestCase
{
    public function test_sem_auditor_semad_retorna_false(): void
    {
        $r = new RbacResolver();

        $this->assertFalse($r->semadMantaUiEnforceReadonly(999999));
    }
}
