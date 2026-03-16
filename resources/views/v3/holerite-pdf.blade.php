<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #222;
            background: #fff;
        }

        .header {
            background: #003a6b;
            color: #fff;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 16px;
            font-weight: bold;
        }

        .header .sub {
            font-size: 10px;
            opacity: 0.85;
        }

        .box {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 8px 0;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #003a6b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #003a6b;
            padding-bottom: 2px;
            margin-bottom: 6px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px 12px;
        }

        .info-item label {
            font-size: 9px;
            color: #888;
            display: block;
        }

        .info-item span {
            font-size: 11px;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #003a6b;
            color: #fff;
        }

        thead th {
            padding: 5px 8px;
            text-align: left;
            font-size: 10px;
        }

        tbody tr:nth-child(even) {
            background: #f5f7fa;
        }

        tbody td {
            padding: 4px 8px;
            font-size: 10px;
            border-bottom: 1px solid #eee;
        }

        .valor {
            text-align: right;
        }

        .totais-row td {
            font-weight: bold;
            border-top: 2px solid #003a6b;
        }

        .liquido-box {
            background: #003a6b;
            color: #fff;
            padding: 6px 14px;
            border-radius: 4px;
            text-align: right;
            margin-top: 6px;
        }

        .liquido-box .label {
            font-size: 10px;
            opacity: 0.85;
        }

        .liquido-box .value {
            font-size: 18px;
            font-weight: bold;
        }

        .footer {
            font-size: 9px;
            color: #aaa;
            text-align: center;
            margin-top: 14px;
            border-top: 1px solid #eee;
            padding-top: 6px;
        }

        .assinatura {
            margin-top: 22px;
            text-align: center;
        }

        .assinatura hr {
            width: 220px;
            display: inline-block;
            border: none;
            border-top: 1px solid #666;
        }
    </style>
</head>

<body>
    {{-- HEADER --}}
    <div class="header">
        <div>
            <div class="sub">GENTE — Sistema de Gestão de Pessoas</div>
            <h1>HOLERITE / CONTRACHEQUE</h1>
            <div class="sub">Competência: {{ $competencia }}</div>
        </div>
        <div style="text-align:right">
            <div class="sub">Emitido em: {{ $emitido_em }}</div>
            <div class="sub">Página {{ $page ?? 1 }}</div>
        </div>
    </div>

    {{-- DADOS DO SERVIDOR --}}
    <div class="box" style="margin-top:10px">
        <div class="section-title">Dados do Servidor</div>
        <div class="info-grid">
            <div class="info-item"><label>Nome</label><span>{{ $servidor['nome'] }}</span></div>
            <div class="info-item"><label>Matrícula</label><span>{{ $servidor['matricula'] ?? '—' }}</span></div>
            <div class="info-item"><label>CPF</label><span>{{ $servidor['cpf'] ?? '—' }}</span></div>
            <div class="info-item"><label>Cargo</label><span>{{ $servidor['cargo'] ?? '—' }}</span></div>
            <div class="info-item"><label>Lotação</label><span>{{ $servidor['lotacao'] ?? '—' }}</span></div>
            <div class="info-item"><label>Regime Prev.</label><span>{{ $servidor['regime_prev'] ?? '—' }}</span></div>
            <div class="info-item"><label>Banco</label><span>{{ $servidor['banco'] ?? '—' }}</span></div>
            <div class="info-item"><label>Agência</label><span>{{ $servidor['agencia'] ?? '—' }}</span></div>
            <div class="info-item"><label>Conta</label><span>{{ $servidor['conta'] ?? '—' }}</span></div>
        </div>
    </div>

    {{-- RUBRICAS --}}
    <div class="box">
        <div class="section-title">Rubricas</div>
        <table>
            <thead>
                <tr>
                    <th style="width:8%">Código</th>
                    <th style="width:42%">Descrição</th>
                    <th style="width:10%" class="valor">Ref.</th>
                    <th style="width:20%" class="valor">Proventos (R$)</th>
                    <th style="width:20%" class="valor">Descontos (R$)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rubricas as $r)
                    <tr>
                        <td>{{ $r['codigo'] ?? '—' }}</td>
                        <td>{{ $r['descricao'] }}</td>
                        <td class="valor">{{ $r['referencia'] ?? '' }}</td>
                        <td class="valor">{{ $r['tipo'] === 'P' ? number_format($r['valor'], 2, ',', '.') : '' }}</td>
                        <td class="valor">{{ $r['tipo'] === 'D' ? number_format($r['valor'], 2, ',', '.') : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; color:#aaa; padding:12px">Sem rubricas disponíveis</td>
                    </tr>
                @endforelse
                <tr class="totais-row">
                    <td colspan="3">TOTAIS</td>
                    <td class="valor">{{ number_format($total_proventos, 2, ',', '.') }}</td>
                    <td class="valor">{{ number_format($total_descontos, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="liquido-box">
            <div class="label">VALOR LÍQUIDO A RECEBER</div>
            <div class="value">R$ {{ number_format($liquido, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- BASE DE CÁLCULO --}}
    @if(isset($bases) && count($bases))
        <div class="box">
            <div class="section-title">Base de Cálculo</div>
            <table>
                <tbody>
                    @foreach($bases as $b)
                        <tr>
                            <td>{{ $b['descricao'] }}</td>
                            <td class="valor">R$ {{ number_format($b['valor'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ASSINATURA --}}
    <div class="assinatura">
        <hr>
        <div style="font-size:10px; color:#555">Assinatura do Servidor / Data</div>
    </div>

    <div class="footer">
        Documento gerado eletronicamente — GENTE v3 — {{ $emitido_em }} |
        A autenticidade deste documento pode ser verificada no portal da Prefeitura.
    </div>
</body>

</html>
