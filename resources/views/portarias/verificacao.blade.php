<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Portaria</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .wrap { max-width: 760px; margin: 36px auto; padding: 0 14px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
        .ok { color: #166534; }
        .no { color: #991b1b; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.no { background: #fee2e2; color: #991b1b; }
        .grid { display: grid; grid-template-columns: 180px 1fr; gap: 8px; font-size: 14px; margin-top: 12px; }
        .grid div:nth-child(odd) { color: #475569; font-weight: 700; }
        .hash { font-family: monospace; font-size: 12px; word-break: break-all; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @if($valido ?? false)
                <h2 class="ok">Documento Válido</h2>
                <span class="badge ok">AUTÊNTICO</span>
                <div class="grid">
                    <div>Servidor</div><div>{{ $funcionario_nome ?? '—' }} (ID {{ $funcionario_id ?? '—' }})</div>
                    <div>Origem</div><div>{{ $origem_unidade ?? '—' }} — {{ $origem_setor ?? '—' }}</div>
                    <div>Destino</div><div>{{ $destino_unidade ?? '—' }} — {{ $destino_setor ?? '—' }}</div>
                    <div>Justificativa</div><div>{{ $justificativa ?? '—' }}</div>
                    <div>Emitido em</div><div>{{ $emitido_em ?? '—' }}</div>
                    <div>Documento</div><div>#{{ $documento_id ?? '—' }}</div>
                    <div>Arquivo</div><div>{{ $arquivo_path ?? '—' }}</div>
                </div>
                <p style="margin:12px 0 6px;font-weight:700;color:#334155;">Hash de autenticidade</p>
                <div class="hash">{{ $hash ?? '—' }}</div>
            @else
                <h2 class="no">Documento Inválido</h2>
                <span class="badge no">NÃO VERIFICADO</span>
                <p>{{ $motivo ?? 'Não foi possível validar este documento.' }}</p>
            @endif
        </div>
    </div>
</body>
</html>

