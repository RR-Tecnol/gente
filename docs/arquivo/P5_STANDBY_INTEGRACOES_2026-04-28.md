# P5-Standby — Integrações externas (catálogo)

## Configuração

Ficheiro `config/p5_connectors.php` lista conectores com `habilitado` mapeado a variáveis de ambiente (por defeito **false**).

Exemplos (`.env`):

```
P5_ESOCIAL_ENVIO=false
P5_CNAB_BANCARIO=false
P5_API_TERCEIRA_FOLHA=false
```

## Regra de produto

Ativação só com **demanda contratual** e runbook; modo standalone permanece íntegro com tudo `false`.
