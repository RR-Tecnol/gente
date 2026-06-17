<?php

namespace Tests\Unit;

use App\Services\Shadow\SnapshotManifestoCanonicoService;
use Tests\TestCase;

class SnapshotManifestoCanonicoServiceTest extends TestCase
{
    public function test_valida_manifest_com_hash_e_rows(): void
    {
        $dir = sys_get_temp_dir() . '/gente-manifest-test-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $csv = $dir . DIRECTORY_SEPARATOR . 'pessoas.csv';
        file_put_contents($csv, "cpf;nome\n11111111111;X\n");
        $hash = hash_file('sha256', $csv);
        $manifest = [
            'competencia' => '2026-04',
            'schema_version' => 1,
            'gerado_em' => '2026-04-27T00:00:00Z',
            'fonte_legacy' => 'test',
            'arquivos' => [
                [
                    'path' => 'pessoas.csv',
                    'rows' => 1,
                    'sha256' => $hash,
                ],
            ],
        ];
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));

        $s = new SnapshotManifestoCanonicoService();
        $r = $s->validar($dir);

        $this->assertTrue($r['ok'], implode('; ', $r['erros']));

        $bad = $manifest;
        $bad['arquivos'][0]['sha256'] = str_repeat('0', 64);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($bad, JSON_UNESCAPED_UNICODE));
        $r2 = $s->validar($dir);
        $this->assertFalse($r2['ok']);
    }
}
