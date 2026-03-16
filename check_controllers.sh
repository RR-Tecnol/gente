#!/bin/sh
cd /var/www
for C in SetorController AfastamentoController FeriasController EscalaController FolhaController EventoController PerfilController AplicacaoController; do
    FILE="app/Http/Controllers/${C}.php"
    COUNT=$(grep -c "function view(" "$FILE" 2>/dev/null || echo 0)
    echo "$C: $COUNT"
done
