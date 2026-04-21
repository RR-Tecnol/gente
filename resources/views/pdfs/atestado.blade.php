<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>body{font-family:Arial;font-size:12px;} .header{text-align:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;} .field{margin-bottom:8px;} label{font-weight:bold;}</style></head>
<body>
<div class="header"><h2>PREFEITURA MUNICIPAL DE SÃO LUÍS</h2><h3>Atestado Médico — Registro de Afastamento</h3></div>
<div class="field"><label>Tipo:</label> {{ $afastamento->AFASTAMENTO_TIPO ?? '—' }}</div>
<div class="field"><label>Período:</label> {{ $afastamento->AFASTAMENTO_DATA_INICIO ?? '—' }} a {{ $afastamento->AFASTAMENTO_DATA_FIM ?? '—' }}</div>
<div class="field"><label>Status:</label> {{ $afastamento->AFASTAMENTO_STATUS ?? '—' }}</div>
<div class="field"><label>Observações:</label> {{ $afastamento->AFASTAMENTO_OBS ?? '—' }}</div>
<div style="margin-top:60px;text-align:center;"><p>_______________________________</p><p>Assinatura / Carimbo</p></div>
</body>
</html>
