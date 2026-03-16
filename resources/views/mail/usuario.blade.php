<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #1a2a5e, #00b4d8);
            padding: 32px;
            text-align: center;
        }

        .header h1 {
            color: #fff;
            margin: 0;
            font-size: 28px;
            letter-spacing: 2px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.8);
            margin: 4px 0 0;
            font-size: 13px;
        }

        .body {
            padding: 32px;
        }

        .body p {
            color: #444;
            line-height: 1.6;
        }

        .credentials {
            background: #f0f4ff;
            border-left: 4px solid #1a2a5e;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 24px 0;
        }

        .credentials .label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .credentials .value {
            font-size: 18px;
            font-weight: bold;
            color: #1a2a5e;
            font-family: monospace;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #1a2a5e, #00b4d8);
            color: #fff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            margin-top: 8px;
        }

        .warning {
            background: #fff8e1;
            border-left: 4px solid #f59e0b;
            border-radius: 4px;
            padding: 12px 16px;
            margin-top: 24px;
            font-size: 13px;
            color: #7a5f00;
        }

        .footer {
            background: #f4f6f9;
            padding: 16px;
            text-align: center;
            font-size: 11px;
            color: #aaa;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>GENTE</h1>
            <p>Gestão de Pessoas — RR Tecnol</p>
        </div>
        <div class="body">
            <p>Olá, <strong>{{ $user->USUARIO_NOME }}</strong>!</p>
            <p>Seu acesso ao sistema <strong>GENTE</strong> foi criado. Utilize as credenciais abaixo para o seu
                primeiro acesso:</p>

            <div class="credentials">
                <div class="label">Login (CPF)</div>
                <div class="value">{{ $user->USUARIO_LOGIN }}</div>
            </div>

            @if($senhaTemporaria)
                <div class="credentials">
                    <div class="label">Senha temporária</div>
                    <div class="value">{{ $senhaTemporaria }}</div>
                </div>
            @endif

            <a class="btn" href="{{ route('login') }}" target="_blank">Acessar o GENTE</a>

            <div class="warning">
                ⚠️ <strong>Importante:</strong> Esta é uma senha temporária. Você será solicitado a alterá-la no
                primeiro acesso.
            </div>
        </div>
        <div class="footer">
            {{ config('app.name') }} — {{ config('app.desenvolvido_por') }} &bull; Este é um e-mail automático, não
            responda.
        </div>
    </div>
</body>

</html>