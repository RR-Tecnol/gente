<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portaria de Lotação</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 34px; }
        .topo { text-align: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px; margin-bottom: 24px; }
        .topo h1 { margin: 0; font-size: 16px; letter-spacing: .04em; }
        .topo p { margin: 3px 0 0; font-size: 11px; color: #475569; }
        .titulo { text-align: center; font-size: 14px; font-weight: 700; text-transform: uppercase; margin: 20px 0 18px; }
        .corpo { line-height: 1.65; text-align: justify; }
        .bloco { margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; background: #f8fafc; }
        .bloco table { width: 100%; border-collapse: collapse; }
        .bloco td { padding: 4px 2px; vertical-align: top; }
        .bloco td:first-child { width: 190px; font-weight: 700; color: #475569; }
        .assinatura { margin-top: 56px; text-align: center; }
        .linha { width: 280px; margin: 0 auto 6px; border-top: 1px solid #1f2937; }
        .rodape { margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 10px; color: #64748b; text-align: center; }
        .autenticidade { margin-top: 18px; border: 1px dashed #94a3b8; border-radius: 8px; padding: 10px; font-size: 10px; color: #334155; display: table; width: 100%; }
        .aut-col { display: table-cell; vertical-align: top; }
        .aut-col.qr { width: 145px; text-align: center; }
        .aut-col.info { padding-left: 8px; }
        .aut-hash { font-family: monospace; font-size: 11px; font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>
    <div class="topo">
        <h1>PREFEITURA DE SÃO LUÍS</h1>
        <p>Secretaria Municipal de Educação — SEMED</p>
    </div>

    <div class="titulo">Portaria de Lotação / Remanejamento</div>

    <div class="corpo">
        <p>
            A Secretaria Municipal de Educação, no uso de suas atribuições legais, resolve formalizar a movimentação funcional
            do(a) servidor(a) abaixo identificado(a), conforme necessidade administrativa e fundamento legal aplicável.
        </p>
    </div>

    <div class="bloco">
        <table>
            <tr><td>Servidor(a)</td><td>{{ $funcionario_nome }}</td></tr>
            <tr><td>Matrícula</td><td>{{ $matricula }}</td></tr>
            <tr><td>Cargo</td><td>{{ $cargo }}</td></tr>
            <tr><td>Origem</td><td>{{ $origem_unidade }} — {{ $origem_setor }}</td></tr>
            <tr><td>Destino</td><td>{{ $destino_unidade }} — {{ $destino_setor }}</td></tr>
            <tr><td>Data de Vigência</td><td>{{ \Carbon\Carbon::parse($vigencia)->format('d/m/Y') }}</td></tr>
            <tr><td>Justificativa</td><td>{{ $justificativa }}</td></tr>
            <tr><td>Fundamento</td><td>{{ $fundamento_legal }}</td></tr>
            <tr><td>Emitido em</td><td>{{ $emitido_em }}</td></tr>
        </table>
    </div>

    <div class="assinatura">
        <div class="linha"></div>
        <div><strong>Autoridade Competente — SEMED</strong></div>
        <div style="font-size:11px;color:#64748b">Documento administrativo gerado eletronicamente pelo GENTE v3</div>
    </div>

    <div class="autenticidade">
        <div class="aut-col qr">
            @if(!empty($qrcode_svg_base64))
                <img src="data:image/svg+xml;base64,{{ $qrcode_svg_base64 }}" alt="QR Code de validação" width="120" height="120">
            @else
                <div style="font-size:10px;color:#94a3b8;padding-top:44px;">QR indisponível</div>
            @endif
        </div>
        <div class="aut-col info">
            <div><strong>Autenticação Digital — GENTE v3</strong></div>
            <div>Documento assinado eletronicamente via GENTE v3.</div>
            <div>A autenticidade pode ser confirmada em:</div>
            <div>{{ $verificacao_url ?? '-' }}</div>
            <div style="margin-top:6px;">Código de Autenticação:</div>
            <div class="aut-hash">{{ $hash_autenticidade_curto ?? '-' }}</div>
        </div>
    </div>

    <div class="rodape">
        GENTE v3 · Prefeitura de São Luís · Portaria de Lotação
    </div>
</body>
</html>
