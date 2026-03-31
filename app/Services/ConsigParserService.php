<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsigParserService
{
    /**
     * Processa um arquivo recebido de operadora conforme o layout cadastrado.
     * Retorna array com: linhas parseadas, total, erros, log.
     */
    public function processar(int $consignatariaId, int $layoutId, UploadedFile $arquivo): array
    {
        // 1. Carregar layout — só aceita ENTRADA
        $layout = DB::table('LAYOUT_CONSIGNATARIA')
            ->where('LAYOUT_ID', $layoutId)
            ->where('CONSIGNATARIA_ID', $consignatariaId)
            ->where('LAYOUT_DIRECAO', 'ENTRADA')
            ->where('LAYOUT_ATIVO', true)
            ->first();

        if (!$layout) {
            throw new \RuntimeException('Layout não encontrado ou não é do tipo ENTRADA.');
        }

        $mapeamento = $layout->LAYOUT_MAPEAMENTO
            ? json_decode($layout->LAYOUT_MAPEAMENTO, true)
            : [];

        if (empty($mapeamento)) {
            throw new \RuntimeException('Layout sem mapeamento de campos definido.');
        }

        // 2. Ler arquivo respeitando encoding
        $encoding  = $layout->LAYOUT_ENCODING ?? 'UTF-8';
        $conteudo  = file_get_contents($arquivo->getRealPath());

        if (strtoupper($encoding) !== 'UTF-8') {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        }

        $linhas        = explode("\n", str_replace("\r\n", "\n", $conteudo));
        $tamanhoLinha  = $layout->LAYOUT_TAMANHO_LINHA;
        $registros     = [];
        $erros         = [];
        $logLinhas     = [];

        // 3. Processar linha por linha
        foreach ($linhas as $numLinha => $linha) {
            $linha = rtrim($linha); // remove \r residual
            if (empty($linha)) continue;

            // Validar tamanho se definido
            if ($tamanhoLinha && mb_strlen($linha) < $tamanhoLinha) {
                $msg = "Linha " . ($numLinha + 1) . ": tamanho {$tamanhoLinha} esperado, " . mb_strlen($linha) . " recebido.";
                $erros[] = $msg;
                $logLinhas[] = "[ERRO] {$msg}";
                continue;
            }

            // Extrair campos pelo mapeamento [inicio, fim] — posições 1-based
            $registro = [];
            foreach ($mapeamento as $campo => $posicoes) {
                if (!is_array($posicoes) || count($posicoes) < 2) continue;
                [$ini, $fim] = $posicoes;
                // mb_substr usa offset 0-based; posições do layout são 1-based
                $registro[$campo] = trim(mb_substr($linha, $ini - 1, $fim - $ini + 1));
            }

            $registros[] = $registro;
            $logLinhas[] = "[OK] Linha " . ($numLinha + 1);
        }

        return [
            'layout_nome'    => $layout->LAYOUT_NOME,
            'total_linhas'   => count($registros) + count($erros),
            'total_registros'=> count($registros),
            'total_erros'    => count($erros),
            'log'            => implode("\n", $logLinhas),
            'registros'      => $registros, // retornado ao frontend para preview
        ];
    }
}
