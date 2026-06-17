<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class PortariaLotacaoService
{
    /** @return array<string,mixed>|null */
    public static function buildData(int $funcionarioId): ?array
    {
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('f.FUNCIONARIO_ID', 'f.FUNCIONARIO_MATRICULA', 'p.PESSOA_NOME', 'c.CARGO_NOME')
            ->first();
        if (! $func) {
            return null;
        }

        $temObsLotacao = Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO');

        $selectAtual = ['l.LOTACAO_ID', 'l.LOTACAO_DATA_INICIO', 's.SETOR_NOME as setor_nome', 'u.UNIDADE_NOME as unidade_nome'];
        if ($temObsLotacao) {
            $selectAtual[] = 'l.LOTACAO_OBSERVACAO';
        }
        $lotacaoAtual = DB::table('LOTACAO as l')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('l.FUNCIONARIO_ID', $funcionarioId)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->orderByDesc('l.LOTACAO_ID')
            ->select($selectAtual)
            ->first();
        if (! $lotacaoAtual) {
            return null;
        }

        $selectAnterior = ['l.LOTACAO_ID', 'l.LOTACAO_DATA_INICIO', 'l.LOTACAO_DATA_FIM', 's.SETOR_NOME as setor_nome', 'u.UNIDADE_NOME as unidade_nome'];
        if ($temObsLotacao) {
            $selectAnterior[] = 'l.LOTACAO_OBSERVACAO';
        }
        $lotacaoAnterior = DB::table('LOTACAO as l')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('l.FUNCIONARIO_ID', $funcionarioId)
            ->whereNotNull('l.LOTACAO_DATA_FIM')
            ->orderByDesc('l.LOTACAO_ID')
            ->select($selectAnterior)
            ->first();

        $justificativa = self::resolveJustificativa($funcionarioId)
            ?: trim((string) ($lotacaoAtual->LOTACAO_OBSERVACAO ?? $lotacaoAnterior->LOTACAO_OBSERVACAO ?? 'Movimentação administrativa.'));

        return [
            'funcionario_id' => (int) $funcionarioId,
            'funcionario_nome' => $func->PESSOA_NOME ?? 'Servidor',
            'matricula' => $func->FUNCIONARIO_MATRICULA ?? '—',
            'cargo' => $func->CARGO_NOME ?? '—',
            'origem_setor' => $lotacaoAnterior->setor_nome ?? '—',
            'origem_unidade' => $lotacaoAnterior->unidade_nome ?? '—',
            'destino_setor' => $lotacaoAtual->setor_nome ?? '—',
            'destino_unidade' => $lotacaoAtual->unidade_nome ?? '—',
            'vigencia' => $lotacaoAtual->LOTACAO_DATA_INICIO ?? now()->toDateString(),
            'justificativa' => $justificativa,
            'emitido_em' => now()->format('d/m/Y H:i'),
            'fundamento_legal' => 'Lei Municipal nº 4.928/2008 e normativos internos da SEMED.',
        ];
    }

    public static function renderPdfBinary(array $dados): string
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.portaria_lotacao', $dados)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /** @return array<string,mixed> */
    public static function decorateWithAuthenticity(array $dados, ?int $usuarioId = null): array
    {
        $hash = self::generateAuthenticityHash($dados, $usuarioId);
        $verifyUrl = url('/verificar-portaria/' . $hash);
        $qrSvgBase64 = self::generateQrSvgBase64($verifyUrl);

        $dados['hash_autenticidade'] = $hash;
        $dados['hash_autenticidade_curto'] = strtoupper(substr($hash, 0, 12));
        $dados['verificacao_url'] = $verifyUrl;
        $dados['qrcode_svg_base64'] = $qrSvgBase64;

        return $dados;
    }

    /** @return string caminho relativo no disco */
    public static function storePdf(int $funcionarioId, string $pdfBinary): string
    {
        $fileName = 'portaria-lotacao-' . $funcionarioId . '-' . now()->format('YmdHis') . '.pdf';
        $path = 'portarias/' . date('Y/m') . '/' . $fileName;
        Storage::disk('local')->put($path, $pdfBinary);
        return $path;
    }

    public static function persistDossie(int $funcionarioId, string $path, array $dados, string $statusEmail = 'pendente', ?string $erroEmail = null): void
    {
        if (! Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return;
        }
        $payload = [
            'FUNCIONARIO_ID' => $funcionarioId,
            'TIPO_DOCUMENTO' => 'PORTARIA_LOTACAO',
            'ARQUIVO_PATH' => $path,
            'TITULO' => 'Portaria de Lotação',
            'METADADOS_JSON' => json_encode([
                'origem_unidade' => $dados['origem_unidade'] ?? null,
                'origem_setor' => $dados['origem_setor'] ?? null,
                'destino_unidade' => $dados['destino_unidade'] ?? null,
                'destino_setor' => $dados['destino_setor'] ?? null,
                'justificativa' => $dados['justificativa'] ?? null,
                'verificacao_url' => $dados['verificacao_url'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'STATUS_ENVIO_EMAIL' => $statusEmail,
            'ERRO_ENVIO_EMAIL' => $erroEmail,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('DOCUMENTOS_SERVIDOR', 'HASH_AUTENTICIDADE')) {
            $payload['HASH_AUTENTICIDADE'] = $dados['hash_autenticidade'] ?? null;
        }
        DB::table('DOCUMENTOS_SERVIDOR')->insert($payload);
    }

    public static function updateDossieEmailStatusByPath(string $path, string $status, ?string $erro = null): void
    {
        if (! Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return;
        }
        DB::table('DOCUMENTOS_SERVIDOR')
            ->where('ARQUIVO_PATH', $path)
            ->update([
                'STATUS_ENVIO_EMAIL' => $status,
                'ERRO_ENVIO_EMAIL' => $erro,
                'updated_at' => now(),
            ]);
    }

    public static function resolveEmailInstitucional(int $funcionarioId): ?string
    {
        $email = DB::table('USUARIO')->where('FUNCIONARIO_ID', $funcionarioId)->value('USUARIO_EMAIL');
        if ($email) {
            return strtolower(trim((string) $email));
        }
        if (Schema::hasTable('CONTATO')) {
            $byContato = DB::table('CONTATO as c')
                ->join('FUNCIONARIO as f', 'f.PESSOA_ID', '=', 'c.PESSOA_ID')
                ->where('f.FUNCIONARIO_ID', $funcionarioId)
                ->where(function ($q) {
                    if (Schema::hasColumn('CONTATO', 'CONTATO_TIPO')) {
                        $q->where('c.CONTATO_TIPO', 2);
                    }
                })
                ->value(Schema::hasColumn('CONTATO', 'CONTATO_CONTEUDO') ? 'c.CONTATO_CONTEUDO' : 'c.CONTATO_VALOR');
            if ($byContato) {
                return strtolower(trim((string) $byContato));
            }
        }
        return null;
    }

    private static function resolveJustificativa(int $funcionarioId): ?string
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return null;
        }
        $auditCols = Schema::getColumnListing('AUDIT_LOG');
        $auditIdCol = in_array('AUDIT_LOG_ID', $auditCols, true)
            ? 'AUDIT_LOG_ID'
            : (in_array('id', $auditCols, true) ? 'id' : null);
        $rows = DB::table('AUDIT_LOG')
            ->where('ACAO', 'MOVIMENTACAO_SETOR')
            ->when($auditIdCol, fn ($q) => $q->orderByDesc($auditIdCol))
            ->limit(120)
            ->get(['DADOS_NOVOS']);
        foreach ($rows as $row) {
            $dados = json_decode((string) ($row->DADOS_NOVOS ?? '{}'), true);
            if (! is_array($dados)) {
                continue;
            }
            $movs = $dados['movimentacoes'] ?? [];
            $achou = collect($movs)->contains(fn ($m) => (int) ($m['funcionario_id'] ?? 0) === $funcionarioId);
            if ($achou) {
                return trim((string) ($dados['justificativa'] ?? '')) ?: null;
            }
        }
        return null;
    }

    private static function generateAuthenticityHash(array $dados, ?int $usuarioId = null): string
    {
        $secret = (string) (env('PORTARIA_SIGNING_KEY') ?: config('app.key') ?: 'gente-portaria-local');
        $base = implode('|', [
            (string) ($dados['funcionario_id'] ?? ''),
            (string) ($dados['matricula'] ?? ''),
            (string) ($dados['vigencia'] ?? ''),
            (string) ($dados['origem_setor'] ?? ''),
            (string) ($dados['destino_setor'] ?? ''),
            (string) ($dados['justificativa'] ?? ''),
            (string) ($usuarioId ?? 0),
            now()->toIso8601String(),
        ]);

        return hash_hmac('sha256', $base, $secret);
    }

    private static function generateQrSvgBase64(string $url): ?string
    {
        try {
            if (! class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                return null;
            }
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(130)
                ->margin(1)
                ->generate($url);
            $svgStr = (string) $svg;
            if (trim($svgStr) === '') {
                return null;
            }
            return base64_encode($svgStr);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

