# BRIEFING ANTYGRAVITY — Fix CSS: vazamento de `.act-btn` global

**Data:** 09/05/2026
**Branch:** `producao-pmsl`
**Tipo:** Bug fix de CSS (1 arquivo, sem mudança de lógica)

## Sintoma

Botões de ação em múltiplas telas estão renderizando com texto cortado/sobreposto. Reportado pelo Ronaldo em `Gerir Progressões` (todas as 6 abas afetadas):

- "Selecionar todos (0)" aparece como `Selec...`
- "Salvar" aparece como `Salv` cortado verticalmente
- "Recalcular", "Exportar", "Aplicar Selecionados" todos colapsados

Provavelmente afeta também: `ProgressaoPaginacaoBar`, e botões "Anterior/Próximo" em qualquer lista paginada.

## Causa raiz

`resources/gente-v3/src/responsive.css` linhas 246-273 define um seletor GLOBAL com nome muito genérico:

```css
.row-actions {
    display: flex;
    gap: 6px;
}

.act-btn {                ← ❌ GLOBAL — vaza pra TODOS os botões com classe .act-btn
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 30px;          ← Trava largura em 30px
    height: 30px;         ← Trava altura em 30px
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    cursor: pointer;
}

.act-btn.act-blue { color: #2563eb; }
.act-btn.act-green { color: #16a34a; }
.act-btn.act-red { color: #dc2626; }
```

A intenção original era estilizar botões pequenos de ação **dentro de `.row-actions`** (ícones em células de tabela). Mas o seletor sem prefixo aplica em qualquer elemento `.act-btn` de qualquer tela.

Múltiplas views (`ProgressaoAdminView.vue`, `ProgressaoPaginacaoBar.vue`, etc.) definem `.act-btn` próprio no `<style scoped>` para botões grandes com texto (padding 9px 16px, min-height 38px, white-space nowrap). Estas regras scoped têm especificidade `(0,2,0)` (selector + atributo data-v-xxx), maior que a global `(0,1,0)` — MAS as regras scoped não definem `width` nem `height` explicitamente. Resultado: o `width: 30px; height: 30px` do CSS global vence por falta de override → todos os botões com `.act-btn` ficam 30x30 → texto cortado.

## Tarefa única: corrigir 1 arquivo

**Arquivo:** `resources/gente-v3/src/responsive.css`

**Localizar (linhas ~246-273):**

```css
.row-actions {
    display: flex;
    gap: 6px;
}

.act-btn {
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    cursor: pointer;
}

.act-btn.act-blue {
    color: #2563eb;
}

.act-btn.act-green {
    color: #16a34a;
}

.act-btn.act-red {
    color: #dc2626;
}
```

**Substituir por:**

```css
.row-actions {
    display: flex;
    gap: 6px;
}

/* Botão pequeno de ação (apenas dentro de .row-actions ou .actions — containers de ações de linha de tabela) */
.row-actions .act-btn,
.actions .act-btn {
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    cursor: pointer;
}

.row-actions .act-btn.act-blue,
.actions .act-btn.act-blue {
    color: #2563eb;
}

.row-actions .act-btn.act-green,
.actions .act-btn.act-green {
    color: #16a34a;
}

.row-actions .act-btn.act-red,
.actions .act-btn.act-red {
    color: #dc2626;
}
```

**Diferença em uma linha:** o seletor global `.act-btn` virou `.row-actions .act-btn, .actions .act-btn`. As regras 30x30 só aplicam dentro desses containers. Botões `.act-btn` em outros contextos (cards, modais, paginação) deixam de receber as regras globais e voltam a respeitar suas próprias regras `<style scoped>`.

## Por que `.row-actions` E `.actions`

Vi que algumas views usam `.actions` em vez de `.row-actions`. Exemplo: `views/financeiro/ExecucaoDespesaView.vue:163` declara `.actions { white-space: nowrap; }`. Cobrir os dois containers garante que os botões 30x30 dessas tabelas continuam funcionando.

## NÃO MUDAR

- ❌ NÃO mudar nenhum arquivo `.vue`
- ❌ NÃO mudar nenhum HTML
- ❌ NÃO criar arquivos novos
- ❌ NÃO mexer em outros seletores em `responsive.css` (só o bloco `.act-btn`)
- ❌ NÃO rodar `composer install/update`

## Validação

Como é só CSS, a única validação é o build:

```bash
cd resources/gente-v3
npm run build
```

Esperado: `✓ built in Xs` sem erros.

## Commit

`fix(css): scope .act-btn global ao container .row-actions/.actions para não cortar botões grandes`

## Push

```bash
git push origin producao-pmsl
```

NÃO mexer em master, NÃO usar `--force`.

## Reportar para Ronaldo

1. SHA do commit
2. Output do `npm run build` (último ✓ built in)
3. Confirmação de que MUDOU APENAS `responsive.css` (e nenhum outro arquivo)
