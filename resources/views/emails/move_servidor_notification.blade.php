<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portaria de Lotação</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
<p>Olá, {{ $dados['funcionario_nome'] ?? 'Servidor(a)' }}.</p>

<p>
    Sua lotação foi atualizada para a unidade
    <strong>{{ $dados['destino_unidade'] ?? '—' }}</strong>
    (setor: <strong>{{ $dados['destino_setor'] ?? '—' }}</strong>).
</p>

<p>
    A Portaria de Lotação oficial segue em anexo neste e-mail e já está disponível no Dossiê Digital do portal.
</p>

<p style="font-size: 12px; color: #64748b;">
    GENTE v3 · Prefeitura de São Luís · SEMED
</p>
</body>
</html>

