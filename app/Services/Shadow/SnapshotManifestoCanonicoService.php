<?php

namespace App\Services\Shadow;

/**
 * Validação alinhada ao §15.6 do plano de prontidão (gate de entrada do snapshot canónico).
 */
class SnapshotManifestoCanonicoService
{
    /**
     * @return array{ok: bool, erros: string[], manifest: ?array}
     */
    public function validar(string $snapshotDir): array
    {
        $erros = [];
        $pathManifest = $snapshotDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($pathManifest)) {
            return ['ok' => false, 'erros' => ['manifest.json ausente.'], 'manifest' => null];
        }

        $raw = file_get_contents($pathManifest) ?: '';
        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'erros' => ['manifest.json inválido (JSON).'], 'manifest' => null];
        }

        foreach (['competencia', 'schema_version', 'gerado_em', 'fonte_legacy'] as $k) {
            if (!array_key_exists($k, $manifest) || $manifest[$k] === null || $manifest[$k] === '') {
                $erros[] = "Campo obrigatório ausente no manifest: {$k}.";
            }
        }

        $arquivos = $manifest['arquivos'] ?? $manifest['files'] ?? null;
        if (!is_array($arquivos) || $arquivos === []) {
            $erros[] = 'Lista de arquivos vazia: use a chave "arquivos" (ou "files") com itens {path, rows, sha256, ...}.';
        } else {
            foreach ($arquivos as $i => $item) {
                if (!is_array($item)) {
                    $erros[] = "Item de arquivo inválido no índice {$i}.";
                    continue;
                }
                $rel = (string) ($item['path'] ?? $item['nome'] ?? '');
                if ($rel === '') {
                    $erros[] = "Item {$i} sem path/nome de arquivo.";
                    continue;
                }
                $abs = $snapshotDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
                if (!is_file($abs)) {
                    $erros[] = "Arquivo não encontrado: {$rel}.";
                    continue;
                }
                $esperado = (string) ($item['sha256'] ?? $item['hash'] ?? '');
                if ($esperado !== '') {
                    $h = hash_file('sha256', $abs);
                    if (!hash_equals(strtolower($esperado), strtolower($h))) {
                        $erros[] = "SHA-256 divergente para {$rel} (esperado vs atual).";
                    }
                }
                if (isset($item['rows']) && is_numeric($item['rows'])) {
                    $linhas = $this->contarLinhasDadosCsv($abs);
                    if ((int) $item['rows'] !== $linhas) {
                        $erros[] = "Contagem de linhas (dados) diverge para {$rel}: manifest " . (int) $item['rows'] . ", lido {$linhas}.";
                    }
                }
            }
        }

        $meta = $this->validarMetadataOpcional($snapshotDir);
        $erros = array_merge($erros, $meta['erros']);

        if ($erros !== []) {
            return ['ok' => false, 'erros' => $erros, 'manifest' => $manifest];
        }

        return ['ok' => true, 'erros' => [], 'manifest' => $manifest];
    }

    /**
     * @return array{erros: string[]}
     */
    private function validarMetadataOpcional(string $snapshotDir): array
    {
        $erros = [];
        $path = $snapshotDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (!is_file($path)) {
            return ['erros' => []];
        }
        $raw = file_get_contents($path) ?: '';
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            return ['erros' => ['metadata.json presente mas inválido (JSON).']];
        }
        if (!array_key_exists('limiar_divergencia', $meta) || $meta['limiar_divergencia'] === null || $meta['limiar_divergencia'] === '') {
            $erros[] = 'metadata.json: definir "limiar_divergencia" (§15.4 do plano).';
        }

        return ['erros' => $erros];
    }

    /**
     * Conta linhas de dados (exclui cabeçalho) em CSV com delimitador `;`.
     */
    public function contarLinhasDadosCsv(string $absFile): int
    {
        $h = fopen($absFile, 'r');
        if ($h === false) {
            return 0;
        }
        $first = fgetcsv($h, 0, ';');
        if ($first === false) {
            fclose($h);
            return 0;
        }
        $n = 0;
        while (fgetcsv($h, 0, ';') !== false) {
            $n++;
        }
        fclose($h);
        return $n;
    }
}
