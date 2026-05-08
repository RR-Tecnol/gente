<?php

namespace Tests\Unit;

use App\Services\Progressao\ProgressaoFuncionalListagemService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgressaoFuncionalListagemCacheTest extends TestCase
{
    public function test_invalidate_elegiveis_total_cache_increments_version(): void
    {
        Cache::forever('pf_eleg_cache_ver', 5);
        ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache();
        $this->assertSame(6, (int) Cache::get('pf_eleg_cache_ver'));

        ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache();
        $this->assertSame(7, (int) Cache::get('pf_eleg_cache_ver'));
    }
}
