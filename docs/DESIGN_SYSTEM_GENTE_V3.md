# GENTE v3 — Design System & UX Spec
**Versão:** 1.0 | **Data:** 30/03/2026 | **Para:** Antygravity (executor)
**Autoridade:** este documento define o padrão visual obrigatório para TODAS as views novas.
Ler antes de implementar qualquer view, sem exceção.

---

## 1. Filosofia de design

O GENTE serve servidores municipais que usam o sistema todos os dias.
O objetivo não é impressionar — é **eliminar atrito**. Cada tela deve responder a:
- O usuário entende onde está em 2 segundos?
- A ação principal está óbvia sem tutorial?
- O sistema comunica estado (loading, erro, vazio, sucesso) de forma clara?
- A tela é consistente com as outras do sistema?

**Princípios:**
1. **Hierarquia visual clara** — hero → toolbar → conteúdo → ações
2. **Feedback imediato** — toda ação tem resposta visual (loading, sucesso, erro)
3. **Animações funcionais** — só animar o que guia o olhar; nunca decorativo puro
4. **Consistência absoluta** — mesmos componentes, mesmas cores, mesmas distâncias

---

## 2. Tokens de design (CSS variables obrigatórias)

```css
/* Cores primárias */
--c-indigo:        #6366f1;   /* Ação principal, foco, seleção */
--c-indigo-dark:   #4f46e5;   /* Hover de botão primário */
--c-indigo-light:  #e0e7ff;   /* Background de badge indigo */
--c-violet:        #8b5cf6;   /* Gradiente do botão novo */
--c-purple-faint:  #f5f3ff;   /* Background de badge purple */

/* Cores de estado */
--c-green:         #10b981;   /* Ativo, sucesso, ponto verde */
--c-green-faint:   #f0fdf4;   /* Background badge ativo */
--c-green-border:  #86efac;
--c-red:           #f43f5e;   /* Inativo, erro, perigo */
--c-red-faint:     #fef2f2;   /* Background badge erro */
--c-red-border:    #fca5a5;
--c-amber:         #f59e0b;   /* Alerta, pendente */
--c-amber-faint:   #fffbeb;
--c-amber-border:  #fcd34d;
--c-blue:          #3b82f6;   /* Info, link */
--c-blue-faint:    #eff6ff;
--c-blue-border:   #bfdbfe;

/* Neutros (Slate scale) */
--c-slate-900:     #0f172a;
--c-slate-800:     #1e293b;
--c-slate-700:     #334155;
--c-slate-600:     #475569;
--c-slate-500:     #64748b;
--c-slate-400:     #94a3b8;
--c-slate-300:     #cbd5e1;
--c-slate-200:     #e2e8f0;
--c-slate-100:     #f1f5f9;
--c-slate-50:      #f8fafc;
--c-white:         #ffffff;

/* Hero gradient padrão */
--hero-bg: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #312e81 100%);

/* Sombras */
--shadow-sm:   0 1px 3px rgba(0,0,0,0.08);
--shadow-md:   0 4px 16px rgba(0,0,0,0.10);
--shadow-lg:   0 8px 32px rgba(0,0,0,0.15);
--shadow-btn:  0 4px 16px rgba(99,102,241,0.4);
--shadow-btn-h:0 8px 24px rgba(99,102,241,0.5);

/* Border radius */
--r-sm:  8px;
--r-md:  10px;
--r-lg:  14px;
--r-xl:  16px;
--r-2xl: 20px;
--r-3xl: 24px;
--r-pill:999px;

/* Tipografia */
--font-body: 'Inter', system-ui, -apple-system, sans-serif;
--font-mono: 'JetBrains Mono', 'Fira Code', monospace;
```

---

## 3. Tipografia

| Uso | Size | Weight | Color |
|-----|------|--------|-------|
| Hero title | 30px | 900 | #ffffff |
| Hero eyebrow (label acima do título) | 11px | 700, uppercase, tracking 0.1em | #a78bfa |
| Hero subtitle | 13px | 400 | #94a3b8 |
| Page title (sem hero) | 24px | 800 | #1e293b |
| Section heading | 16px | 700 | #1e293b |
| Section label (dentro de form-section) | 11px | 800, uppercase, tracking 0.1em | #94a3b8 |
| Body text | 14px | 400 | #334155 |
| Body small | 13px | 400 | #475569 |
| Caption / label | 12px | 600 | #64748b |
| Eyebrow / micro label | 11px | 700, uppercase | #94a3b8 |
| Table header | 10px | 800, uppercase, tracking 0.08em | #94a3b8 |
| Monospace (matrícula, CPF, protocolo) | 12-13px | 700 | contextual |

**Regra:** font-family sempre `'Inter', system-ui, sans-serif`. Nunca usar a fonte padrão do browser.

---

## 4. Estrutura obrigatória de view

Toda view DEVE seguir esta estrutura em ordem:

```
[HERO]       → Identidade do módulo + KPIs + botão CTA
[TOOLBAR]    → Busca + filtros + contagem de resultados
[CONTEÚDO]   → Tabela / cards / formulário
[PAGINAÇÃO]  → Se houver lista paginada
```

Views de detalhe (ex: perfil de funcionário):
```
[HERO]       → Nome + cargo + status
[TABS]       → Seções: Dados Pessoais / Contratos / Histórico / etc.
[CONTEÚDO]   → Conteúdo da tab ativa
```

---

## 5. Componente: HERO

```css
.hero {
  position: relative;
  background: var(--hero-bg);   /* Sempre este gradiente */
  border-radius: var(--r-3xl);  /* 24px */
  padding: 32px 40px;
  overflow: hidden;
  /* Entrada animada */
  opacity: 0;
  transform: translateY(-12px);
  transition: all 0.5s cubic-bezier(0.22,1,0.36,1);
}
.hero.loaded { opacity: 1; transform: none; }
```

**Estrutura interna obrigatória:**
```html
<div class="hero" :class="{ loaded }">
  <!-- Shapes decorativas: sempre presentes, nunca remover -->
  <div class="hero-shapes">
    <div class="hs hs1"></div>  <!-- blob indigo, canto superior direito -->
    <div class="hs hs2"></div>  <!-- blob verde, canto inferior -->
  </div>
  <div class="hero-inner">
    <!-- Lado esquerdo: identidade -->
    <div>
      <span class="hero-eyebrow">Nome da Seção (ex: Recursos Humanos)</span>
      <h1 class="hero-title">Nome do Módulo</h1>
      <p class="hero-sub">Texto de contexto com contagens ou datas</p>
    </div>
    <!-- Lado direito: chips + CTA -->
    <div class="hero-chips">
      <!-- chips de KPIs -->
      <button class="btn-novo">+ Novo Item</button>
    </div>
  </div>
</div>
```

**Shapes CSS obrigatórias:**
```css
.hero-shapes { position: absolute; inset: 0; pointer-events: none; }
.hs { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.15; }
.hs1 { width: 320px; height: 320px; background: #6366f1; top: -100px; right: -80px; }
.hs2 { width: 200px; height: 200px; background: #10b981; bottom: -60px; right: 280px; }
```

**Chips de KPI:**
```css
.chip {
  display: flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px; padding: 8px 14px;
  font-size: 13px; color: #e2e8f0;
}
.chip-dot { width: 8px; height: 8px; border-radius: 50%; }
.chip-dot.green { background: #10b981; }
.chip-dot.red   { background: #f43f5e; }
.chip-dot.amber { background: #f59e0b; }
.chip strong    { color: #fff; }
```

**Variações de hero por módulo** (cor da hs2):
- RH genérico: `#10b981` (verde)
- Folha/financeiro: `#f59e0b` (âmbar)
- Segurança/medicina: `#3b82f6` (azul)
- Avaliação/desempenho: `#a78bfa` (violeta)
- ERP orçamento: `#06b6d4` (cyan)

---

## 6. Componente: BOTÃO PRIMÁRIO (btn-novo)

```css
.btn-novo {
  display: flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff; border: none;
  border-radius: var(--r-lg); /* 14px */
  padding: 10px 18px;
  font-size: 14px; font-weight: 700; cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-btn);
}
.btn-novo:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-btn-h);
}
.btn-novo:active { transform: translateY(0); }
```

**Variantes de botão:**

| Classe | Uso | BG | Color |
|--------|-----|----|-------|
| `.btn-novo` | Criar/Adicionar | gradiente indigo-violet | branco |
| `.btn-secondary` | Ação secundária | `#f8fafc` | `#475569` |
| `.btn-danger` | Excluir/Inativar | `#fef2f2` | `#dc2626` |
| `.btn-ghost` | Cancelar | transparent | `#64748b` |

```css
.btn-secondary {
  display: flex; align-items: center; gap: 6px;
  background: #f8fafc; color: #475569;
  border: 1px solid #e2e8f0; border-radius: var(--r-lg);
  padding: 9px 16px; font-size: 14px; font-weight: 600; cursor: pointer;
  transition: all 0.15s;
}
.btn-secondary:hover { background: #f1f5f9; border-color: #cbd5e1; }

.btn-danger {
  background: #fef2f2; color: #dc2626;
  border: 1px solid #fca5a5; border-radius: var(--r-lg);
  padding: 9px 16px; font-size: 14px; font-weight: 600; cursor: pointer;
  transition: all 0.15s;
}
.btn-danger:hover { background: #fee2e2; }

.btn-ghost {
  background: none; border: none; color: #64748b;
  padding: 9px 14px; font-size: 14px; font-weight: 600; cursor: pointer;
  border-radius: var(--r-md); transition: all 0.15s;
}
.btn-ghost:hover { background: #f1f5f9; color: #334155; }
```

---

## 7. Componente: TOOLBAR

```css
.toolbar {
  display: flex; align-items: center; gap: 14px;
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: var(--r-xl); padding: 12px 18px;
  opacity: 0; transform: translateY(6px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.05s;
}
.toolbar.loaded { opacity: 1; transform: none; }
```

```html
<div class="toolbar" :class="{ loaded }">
  <!-- Campo de busca (sempre presente) -->
  <div class="search-wrap">
    <!-- ícone lupa SVG -->
    <input v-model="busca" class="search-input"
           placeholder="Buscar por nome, matrícula ou setor..."
           @input="debounceBusca" />
    <button v-if="busca" class="search-clear" @click="limparBusca">✕</button>
  </div>
  <!-- Filtros à direita -->
  <div class="toolbar-right">
    <select v-model="filtroStatus" class="filter-select" @change="fetchDados()">
      <option value="">Todos</option>
      <option value="ATIVO">Ativos</option>
      <option value="PENDENTE">Pendentes</option>
    </select>
    <span class="result-count">{{ lista.length }} resultado{{ lista.length !== 1 ? 's' : '' }}</span>
  </div>
</div>
```

**Debounce obrigatório:**
```js
let timer
const debounceBusca = () => {
  clearTimeout(timer)
  timer = setTimeout(() => fetchDados(1), 380)
}
```

---

## 8. Componente: ESTADOS (loading / erro / vazio)

Estes três estados são OBRIGATÓRIOS em toda view com dados assíncronos.

```html
<!-- Loading -->
<div v-if="loading" class="state-box">
  <div class="spinner"></div>
  <p>Carregando...</p>
</div>

<!-- Erro -->
<div v-else-if="erro" class="state-box error">
  <!-- ícone de aviso SVG -->
  <p>{{ erro }}</p>
  <button class="retry-btn" @click="fetchDados">Tentar novamente</button>
</div>

<!-- Vazio -->
<div v-else-if="lista.length === 0" class="state-box empty">
  <!-- ícone de busca SVG -->
  <p>Nenhum resultado encontrado<span v-if="busca"> para "{{ busca }}"</span></p>
</div>
```

```css
.state-box {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 80px 20px;
  text-align: center; color: #64748b; gap: 12px;
}
.state-box p { font-size: 15px; font-weight: 500; margin: 0; }

.spinner {
  width: 48px; height: 48px;
  border: 3px solid #e2e8f0; border-top-color: #6366f1;
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.retry-btn {
  background: #6366f1; color: #fff; border: none;
  border-radius: var(--r-md); padding: 10px 22px;
  font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 4px;
}
```

---

## 9. Componente: TABLE CARD

```css
.table-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: var(--r-2xl); overflow: hidden; overflow-x: auto;
  opacity: 0; transform: translateY(12px);
  transition: all 0.4s cubic-bezier(0.22,1,0.36,1) 0.1s;
}
.table-card.loaded { opacity: 1; transform: none; }

/* Cabeçalho */
.data-table thead tr { background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.data-table th {
  padding: 12px 16px;
  font-size: 10px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.08em; color: #94a3b8; text-align: left; white-space: nowrap;
}

/* Linhas */
.data-row { border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.data-row:hover { background: #f8fafc; }
.data-row:last-child { border-bottom: none; }

/* Animação de entrada linha a linha */
.data-row.row-visible td {
  animation: rowIn 0.35s cubic-bezier(0.22,1,0.36,1) var(--row-delay) both;
}
@keyframes rowIn {
  from { opacity: 0; transform: translateX(-6px); }
  to   { opacity: 1; transform: none; }
}

.data-table td { padding: 13px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
```

**Aplicar delay nas linhas:**
```html
<tr v-for="(item, i) in lista" :key="item.ID"
    class="data-row"
    :class="{ 'row-visible': loaded }"
    :style="{ '--row-delay': `${i * 30}ms` }">
```

---

## 10. Componente: BADGES de status

```css
/* Badge base */
.badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: var(--r-pill);
  font-size: 11px; font-weight: 700; white-space: nowrap;
}

/* Variantes */
.badge-green  { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.badge-red    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-amber  { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
.badge-blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-purple { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.badge-gray   { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

/* Dot dentro do badge */
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
```

**Mapeamento semântico obrigatório:**

| Estado | Badge | Classe |
|--------|-------|--------|
| Ativo / Aprovado / Concluído | 🟢 Verde | `.badge-green` |
| Inativo / Reprovado / Cancelado | 🔴 Vermelho | `.badge-red` |
| Pendente / Em análise / Aguardando | 🟡 Âmbar | `.badge-amber` |
| Em andamento / Processando | 🔵 Azul | `.badge-blue` |
| Rascunho / Novo / Não iniciado | ⚪ Cinza | `.badge-gray` |
| Vínculo / Tipo / Categoria | 🟣 Purple | `.badge-purple` |

---

## 11. Componente: AVATAR

```css
.avatar {
  width: 38px; height: 38px; border-radius: 12px;
  background: hsl(var(--hue), 60%, 92%);
  color: hsl(var(--hue), 60%, 35%);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; flex-shrink: 0;
}
/* Variante maior (perfil) */
.avatar-lg { width: 56px; height: 56px; border-radius: 16px; font-size: 18px; }
```

```js
// Gerar hue determinístico por ID (mesmo ID = mesma cor sempre)
const avatarHue = (id) => ((id * 47) % 360)

// Iniciais do nome
const iniciais = (nome) => {
  if (!nome) return '?'
  return nome.trim().split(' ').filter(Boolean)
    .slice(0, 2).map(n => n[0].toUpperCase()).join('')
}
```

```html
<div class="avatar" :style="{ '--hue': avatarHue(item.ID) }">
  {{ iniciais(item.NOME) }}
</div>
```

---

## 12. Componente: MODAL

```css
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
  z-index: 1000; display: flex; align-items: center;
  justify-content: center; padding: 20px;
}
.modal-box {
  background: #fff; border-radius: var(--r-3xl);
  width: 100%; max-width: 780px; max-height: 90vh;
  display: flex; flex-direction: column;
  box-shadow: 0 32px 80px rgba(0,0,0,0.25);
}
/* Tamanhos */
.modal-sm  { max-width: 440px; }  /* confirmação, alerta */
.modal-md  { max-width: 600px; }  /* formulário simples */
.modal-lg  { max-width: 780px; }  /* formulário completo */
.modal-xl  { max-width: 960px; }  /* formulário com tabs */

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 22px 28px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0;
}
.modal-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border: 1px solid #e2e8f0;
  border-radius: var(--r-md); background: #fff; cursor: pointer;
  color: #64748b; transition: all 0.15s;
}
.modal-close:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

.modal-body {
  overflow-y: auto; padding: 24px 28px;
  flex: 1; display: flex; flex-direction: column; gap: 24px;
}
.modal-footer {
  padding: 16px 28px; border-top: 1px solid #f1f5f9;
  display: flex; align-items: center; justify-content: flex-end;
  gap: 10px; flex-shrink: 0; flex-wrap: wrap;
}
.modal-erro   { flex: 1; font-size: 13px; font-weight: 600; color: #dc2626; margin: 0; }
.modal-sucesso{ flex: 1; font-size: 13px; font-weight: 600; color: #15803d; margin: 0; }
```

**Regra de abertura/fechamento de modal:**
```js
// Sempre resetar erros e modo ao abrir
const abrirModal = () => {
  form.value     = formVazio()
  modoEdicao.value = false
  erroModal.value  = ''
  successoModal.value = ''
  modalAberto.value = true
}
// Fechar com ESC
onMounted(() => {
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalAberto.value) modalAberto.value = false
  })
})
```

---

## 13. Componente: FORM SECTIONS

Para formulários com múltiplos grupos de campos:

```css
.form-section { border: 1px solid #f1f5f9; border-radius: var(--r-xl); padding: 20px; }
.section-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 0 0 14px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.col-2 { grid-column: span 2; }
.form-group.col-full { grid-column: 1 / -1; }
.form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.form-input {
  width: 100%; padding: 9px 12px;
  border: 1.5px solid #e2e8f0; border-radius: var(--r-md);
  font-size: 13px; font-family: inherit; color: #1e293b;
  outline: none; background: #fff;
  transition: border-color 0.15s; box-sizing: border-box;
}
.form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.form-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
.form-input.has-error { border-color: #f43f5e; }
```

---

## 14. Componente: ACTION BUTTONS (em tabela)

```css
.row-actions { display: flex; gap: 5px; }
.act-btn {
  display: flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 9px;
  border: 1px solid #e2e8f0; background: #f8fafc;
  cursor: pointer; transition: all 0.15s; color: #64748b;
}
.act-btn:hover { transform: translateY(-1px); }
.act-btn.act-blue:hover   { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.act-btn.act-green:hover  { background: #f0fdf4; border-color: #86efac; color: #15803d; }
.act-btn.act-purple:hover { background: #f5f3ff; border-color: #ddd6fe; color: #7c3aed; }
.act-btn.act-amber:hover  { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.act-btn.act-red:hover    { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
```

**Ícones por ação:**
- Editar: lápis (pencil SVG)
- Visualizar/Abrir: olho (eye SVG) → `.act-btn.act-blue`
- Baixar PDF: download SVG → `.act-btn.act-green`
- Aprovar: check SVG → `.act-btn.act-green`
- Reprovar/Inativar: X ou slash → `.act-btn.act-red`
- Mais opções: `...` → `.act-btn` padrão

---

## 15. Componente: KPI CARDS (para dashboards e páginas admin)

Para views admin que precisam mostrar totais/métricas no topo:

```css
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.kpi-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: var(--r-xl); padding: 20px 24px;
  display: flex; flex-direction: column; gap: 8px;
}
.kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
.kpi-value { font-size: 28px; font-weight: 900; color: #1e293b; line-height: 1; }
.kpi-sub   { font-size: 12px; color: #64748b; }
.kpi-trend { display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; }
.kpi-trend.up   { color: #10b981; }
.kpi-trend.down { color: #f43f5e; }
/* Acento colorido no topo */
.kpi-card::before {
  content: ''; display: block; width: 40px; height: 3px;
  border-radius: 2px; background: var(--kpi-color, #6366f1); margin-bottom: 4px;
}
```

```html
<div class="kpi-card" style="--kpi-color: #10b981">
  <div class="kpi-label">Servidores Ativos</div>
  <div class="kpi-value">1.247</div>
  <div class="kpi-sub">+12 este mês</div>
</div>
```

---

## 16. Animações — regras

| Elemento | Entrada | Delay |
|----------|---------|-------|
| Hero | `translateY(-12px) → 0` | 0ms |
| Toolbar | `translateY(6px) → 0` | 50ms |
| Table card | `translateY(12px) → 0` | 100ms |
| KPI cards | `translateY(8px) → 0` escalonado | 0, 60, 120, 180ms |
| Linhas de tabela | `translateX(-6px) → 0` escalonado | `i * 30ms` |
| Modal | `scale(0.96) translateY(8px) → 0` | 0ms |

**Easing padrão para tudo:** `cubic-bezier(0.22, 1, 0.36, 1)` (spring out)
**Duração:** 0.35s para linhas; 0.4–0.5s para blocos maiores.
**Ativar com:** classe `.loaded` adicionada via `setTimeout(() => { loaded.value = true }, 80)` no `onMounted`.

---

## 17. Paginação

```css
.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 12px; padding: 16px; border-top: 1px solid #f1f5f9;
}
.pg-btn {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border: 1px solid #e2e8f0;
  border-radius: 9px; background: #fff; cursor: pointer;
  transition: all 0.15s; color: #475569;
}
.pg-btn:hover:not(:disabled) { border-color: #6366f1; color: #6366f1; }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pg-info { font-size: 13px; font-weight: 600; color: #475569; }
```

---

## 18. Toasts / Notificações inline

Para ações que não abrem modal (ex: aprovação direta na lista):

```css
.toast-success {
  display: flex; align-items: center; gap: 8px;
  background: #f0fdf4; border: 1px solid #86efac;
  border-radius: var(--r-md); padding: 10px 16px;
  font-size: 13px; font-weight: 600; color: #15803d;
}
.toast-error {
  background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
  border-radius: var(--r-md); padding: 10px 16px;
  font-size: 13px; font-weight: 600;
}
```

---

## 19. Chips de filtro rápido (tabs horizontais)

Para alternar entre visões dentro de uma mesma tela (ex: Pendentes / Aprovados / Todos):

```css
.filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.ftab {
  padding: 6px 14px; border-radius: var(--r-pill);
  font-size: 13px; font-weight: 600; cursor: pointer;
  border: 1px solid #e2e8f0; background: #fff; color: #475569;
  transition: all 0.15s;
}
.ftab.active {
  background: #6366f1; color: #fff;
  border-color: #6366f1; box-shadow: 0 2px 8px rgba(99,102,241,0.3);
}
.ftab:not(.active):hover { background: #f8fafc; border-color: #cbd5e1; }
```

---

## 20. Responsividade mínima

O GENTE é desktop-first (uso interno em prefeitura), mas deve funcionar em tablets.

```css
/* Breakpoint único — tudo abaixo de 768px */
@media (max-width: 768px) {
  .hero { padding: 24px 20px; }
  .hero-inner { flex-direction: column; align-items: flex-start; }
  .hero-chips { width: 100%; justify-content: flex-start; }
  .hero-title { font-size: 22px; }

  .toolbar { flex-direction: column; align-items: stretch; }
  .toolbar-right { justify-content: space-between; }

  .form-grid { grid-template-columns: 1fr; }
  .form-group.col-2, .form-group.col-full { grid-column: 1; }

  .kpi-grid { grid-template-columns: 1fr 1fr; }

  .modal-box { border-radius: 20px 20px 0 0; max-height: 95vh; align-self: flex-end; }
}
```

---

## 21. Spec das 5 Views Admin faltando

### 21.1 AvaliacaoGestorView.vue
**Perfil:** gestor avalia seus subordinados diretos.
**Hero:** "Gestão" eyebrow | "Avaliações de Desempenho" | chips: ciclo ativo, pendentes, concluídas.
**Toolbar:** filtro por status (Pendente / Concluída), busca por nome.
**Tabela:** Servidor | Cargo | Ciclo | Prazo | Status | Nota Final | Ações (avaliar / ver).
**Modal de avaliação:** formulário com critérios (exibir nome + peso), slider ou select 0–10 por critério, campo de observação, nota calculada automaticamente.
**KPI:** total pendentes em badge âmbar no hero chip.

### 21.2 BeneficiosAdminView.vue
**Perfil:** RH gerencia catálogo de benefícios e vê quem tem o quê.
**Hero:** "Recursos Humanos" | "Benefícios" | chips: total servidores com benefício, custo mensal.
**Tabs:** `Catálogo` | `Por Servidor` | `Relatório de Custo`.
**Tab Catálogo:** tabela com nome, tipo (VT, VR, Plano Saúde…), valor, status. Botão "Novo Benefício".
**Tab Por Servidor:** busca por servidor, lista benefícios ativos com vigência e valor.
**Modal inclusão:** select servidor + select benefício + data início + valor + dependentes.

### 21.3 MedicinaAdminView.vue
**Perfil:** SESMT registra e acompanha ASOs (Atestados de Saúde Ocupacional).
**Hero:** "Medicina do Trabalho" | "ASOs e Exames" | chips: vencidos, vencendo em 30 dias.
**Toolbar:** filtro por status (Válido / Vencido / Próximo do Vencimento), busca.
**Tabela:** Servidor | Cargo | Tipo de Exame | Data | Validade | Status (badge: válido/vencido/expirando) | Ações.
**Alerta visual:** linha com background âmbar claro para ASOs vencendo em 30 dias.
**Modal novo ASO:** servidor + tipo (admissional/periódico/demissional) + data + validade + resultado (apto/inapto) + arquivo PDF upload.

### 21.4 SegurancaAdminView.vue
**Perfil:** SESMT controla entrega de EPIs e laudos de insalubridade/periculosidade.
**Hero:** "Segurança do Trabalho" | "EPIs e Laudos" | chips: EPIs entregues, laudos ativos, CATs no ano.
**Tabs:** `EPIs` | `Laudos` | `Acidentes / CAT`.
**Tab EPIs:** busca por servidor, lista com EPI, data entrega, CA, vencimento, assinatura de recebimento.
**Tab Laudos:** tabela com setor, tipo (insalubridade/periculosidade), grau, validade, status.
**Tab Acidentes:** tabela de CATs com data, servidor, CID, gravidade, status eSocial.

### 21.5 TreinamentosAdminView.vue
**Perfil:** RH cadastra cursos e emite certificados.
**Hero:** "Treinamentos" | "Capacitações" | chips: cursos ativos, inscrições abertas, certificados emitidos.
**Tabs:** `Cursos` | `Inscrições` | `Certificados`.
**Tab Cursos:** tabela com nome, carga horária, modalidade, instrutor, vagas, status. Botão "Novo Curso".
**Tab Inscrições:** busca por curso ou servidor, lista inscrições com status (inscrito/concluído/reprovado).
**Tab Certificados:** busca por servidor, lista de certificados emitidos com botão de download PDF.
**Modal novo curso:** nome, descrição, carga horária, modalidade (presencial/online), instrutor, vagas, data início/fim.

---

## 22. Spec das views de ERP Financeiro (Bloco C)

Estas views têm backend stub — quando o backend for implementado, seguir este layout.

### Padrão Hero ERP:
- `--hero-bg`: gradiente com acento cyan → `linear-gradient(135deg, #0f172a 0%, #164e63 50%, #0e7490 100%)`
- Eyebrow: "ERP Financeiro"

### OrcamentoView — estrutura:
**Tabs:** `LOA` | `PPA` | `Execução por Fonte` | `Saldo por Unidade`
**Tabela LOA:** Unidade Orçamentária | Ação | Função | Dotação Inicial | Dotação Atualizada | Empenhado | Liquidado | Pago | % Execução (progress bar inline).
**Progress bar inline:**
```css
.progress-bar { height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; width: 80px; }
.progress-fill { height: 100%; background: #6366f1; border-radius: 2px; transition: width 0.3s; }
```

### ExecucaoDespesaView — estrutura:
**Tabs:** `Empenhos` | `Liquidações` | `Pagamentos`
**Pipeline visual** (horizontal): Empenho → Liquidação → Pagamento (setas entre etapas, cor por status).

### ContabilidadeView — estrutura:
**Tabs:** `Lançamentos PCASP` | `Balancete` | `Razão`
**Tabela lançamentos:** Data | Documento | Conta Débito | Conta Crédito | Histórico | Valor.
**Conta PCASP:** exibir sempre em fonte monospace com formatação `X.X.X.X.XX.XX`.

---

## 23. Checklist pré-entrega de qualquer view

Antes de marcar uma task como concluída, verificar:

- [ ] Hero presente com gradiente padrão, shapes decorativas e eyebrow correto
- [ ] Toolbar com busca e debounce de 380ms
- [ ] Estados loading / erro / vazio implementados
- [ ] Animações `.loaded` aplicadas em hero, toolbar e table-card
- [ ] Badges seguindo o mapeamento semântico (§10)
- [ ] Modal com ESC para fechar e reset de form ao abrir
- [ ] Font-family explicitamente `'Inter', system-ui, sans-serif`
- [ ] Nenhum `fetch()` direto — sempre `import api from '@/plugins/axios'`
- [ ] Campos monetários formatados: `R$ 1.234,56`
- [ ] Datas formatadas: `DD/MM/YYYY` (não ISO)
- [ ] Responsivo mínimo testado em 768px

---

*RR TECNOL | São Luís — MA | 30/03/2026*
*Próxima revisão: após conclusão do Bloco A*
