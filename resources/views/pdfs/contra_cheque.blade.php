<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Contra Cheque</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .container {
            width: 100%;
            border: 1px solid #000;
            padding: 5px;
            margin-bottom: 20px;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }

        .header table {
            width: 100%;
        }

        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            border: 1px solid #000;
            padding: 3px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 2px 4px;
        }

        .data-table th {
            background-color: #f0f0f0;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .footer-table td {
            border: 1px solid #000;
            padding: 4px;
        }

        .signature-box {
            height: 40px;
            border-bottom: 1px solid #000;
            margin-top: 20px;
            width: 80%;
            margin-left: 10%;
        }
    </style>
</head>

<body>

    <!-- Renderizamos duas vias (Empregador e Empregado) -->
    @for($via = 1; $via <= 2; $via++)
        <div class="container">
            <div class="header">
                <table>
                    <tr>
                        <td width="70%">
                            <b>{{ $dadosGerais['empresa_nome'] }}</b><br>
                            CNPJ: {{ $dadosGerais['empresa_cnpj'] }}
                        </td>
                        <td width="30%" class="text-right">
                            <b>Recibo de Pagamento de Salário</b><br>
                            Mês/Ano: {{ $dadosGerais['mes_ano'] }}<br>
                            <small>{{ $via == 1 ? 'Via Empregador' : 'Via Empregado' }}</small>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="info-table">
                <tr>
                    <td width="15%"><b>Matrícula:</b><br>{{ $dadosGerais['matricula'] }}</td>
                    <td width="45%"><b>Nome do Funcionário:</b><br>{{ $dadosGerais['nome'] }}</td>
                    <td width="20%"><b>CPF:</b><br>{{ $dadosGerais['cpf'] }}</td>
                    <td width="20%"><b>Admissão:</b><br>{{ $dadosGerais['admissao'] }}</td>
                </tr>
                <tr>
                    <td colspan="2"><b>Cargo:</b><br>{{ $dadosGerais['cargo'] }}</td>
                    <td colspan="2"><b>Lotação:</b><br>{{ $dadosGerais['lotacao'] }}</td>
                </tr>
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">Cód.</th>
                        <th width="40%">Descrição</th>
                        <th width="10%">Ref.</th>
                        <th width="20%">Proventos</th>
                        <th width="20%">Descontos</th>
                    </tr>
                </thead>
                <tbody>
                    @php $linhas = max(count($dadosGerais['proventos']), count($dadosGerais['descontos']), 12); @endphp
                    @for ($i = 0; $i < $linhas; $i++)
                        <tr>
                            @if(isset($dadosGerais['proventos'][$i]))
                                <td class="text-center">{{ $dadosGerais['proventos'][$i]['codigo'] }}</td>
                                <td>{{ $dadosGerais['proventos'][$i]['descricao'] }}</td>
                                <td class="text-center">{{ $dadosGerais['proventos'][$i]['referencia'] }}</td>
                                <td class="text-right">{{ $dadosGerais['proventos'][$i]['valor'] }}</td>
                                <td></td>
                            @elseif(isset($dadosGerais['descontos'][$i - count($dadosGerais['proventos'])]))
                                @php $desc = $dadosGerais['descontos'][$i - count($dadosGerais['proventos'])]; @endphp
                                <td class="text-center">{{ $desc['codigo'] }}</td>
                                <td>{{ $desc['descricao'] }}</td>
                                <td class="text-center">{{ $desc['referencia'] }}</td>
                                <td></td>
                                <td class="text-right">{{ $desc['valor'] }}</td>
                            @else
                                <!-- Linhas vazias para preencher o grid -->
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>

            <table class="footer-table">
                <tr>
                    <td width="60%" rowspan="2" style="vertical-align: top;">
                        <p>Declaro ter recebido a importância líquida discriminada neste recibo.</p>
                        <br>
                        <div class="signature-box"></div>
                        <div class="text-center"><small>Assinatura do Funcionário</small></div>
                    </td>
                    <td width="20%"><b>Total de Proventos:</b><br>R$ {{ $dadosGerais['total_proventos'] }}</td>
                    <td width="20%"><b>Total de Descontos:</b><br>R$ {{ $dadosGerais['total_descontos'] }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right" style="background-color: #f0f0f0;">
                        <b>Líquido a Receber:</b> &nbsp;&nbsp;&nbsp;&nbsp; <b>R$ {{ $dadosGerais['liquido'] }}</b>
                    </td>
                </tr>
            </table>

            <table class="footer-table" style="font-size: 8px;">
                <tr>
                    <td>Salário Base: R$ {{ $dadosGerais['total_proventos'] }}</td>
                    <td>Base Calc. INSS: R$ {{ $dadosGerais['base_prev'] }}</td>
                    <td>Base Calc. FGTS: R$ {{ $dadosGerais['base_fgts'] }}</td>
                    <td>FGTS do Mês: R$ {{ $dadosGerais['fx_irrf'] }}</td>
                    <td>Base Calc. IRRF: R$ {{ $dadosGerais['base_irrf'] }}</td>
                </tr>
            </table>
        </div>

        @if($via == 1)
            <br>
            <hr style="border-top: 1px dashed #000;"><br>
        @endif

    @endfor

</body>

</html>