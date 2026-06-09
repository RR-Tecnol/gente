# BRIEFING ANTYGRAVITY — Fase 6 D6: Build Vue 3 SPA pra Produção

**Contexto rápido:** o backend Laravel já está deployado em produção (PMSL São Luís/MA), schema migrado (157 migrations / 239 tabelas), seeders rodados. Falta apenas o build do frontend Vue 3 em `resources/gente-v3/` e a rota catch-all do Laravel pra deep links. Branch atual: `producao-pmsl`. Repo: `https://github.com/RR-Tecnol/gente`. Você executa local + commit + push; o Ronaldo + Claude fazem deploy SSH copy-paste depois.

---

## ✅ Auditoria já feita (NÃO precisa refazer)

Antes de escrever este briefing, foi auditado:

- **Imports Vue:** 85/85 imports do `resources/gente-v3/src/router/index.js` resolvem pra arquivos existentes
- **Imports gerais:** 288/288 imports relativos/aliasados em 173 arquivos `.js`+`.vue` resolvem corretamente
- **Build log antigo (`build_log.txt`/`build_log2.txt`):** o erro `Could not resolve "../views/financeiro/TesourariaView.vue"` foi **artefato de OneDrive sync issue** quando o build rodou no Windows com o arquivo ainda sincronizando. Agora está OK.
- **`resources/views/v3/app.blade.php` JÁ EXISTE** e já lê manifest do Vite em `public_path('build-v3/.vite/manifest.json')`. Esse é o ponto de cola Laravel↔Vue.
- **Rotas `/v3` e `/autocadastro/{token}`** já existem em `routes/web.php` (linhas 377 e 418) e retornam `view('v3.app')`. Em modo dev (`app()->environment('local')`), o blade carrega Vite dev server em `http://localhost:5173`. Em produção (`!local`), lê o manifest.
- **`vite.config.js` atual (49 linhas):** só tem `plugins`, `resolve.alias`, `server` (dev). NÃO tem `build.outDir`, NÃO tem `base`, NÃO tem `build.manifest`. **Por isso build atual cai em `resources/gente-v3/dist/` e o blade não acha.**
- **Variáveis de ambiente Vite (`import.meta.env.VITE_*`) usadas no código:** apenas `VITE_RECAPTCHA_SITE_KEY` em `LoginView.vue` (opcional — se não setada, login funciona sem reCAPTCHA mas alerta).

---

## 🎯 Tarefas a fazer (em ordem)

### Tarefa 1 — Ajustar `vite.config.js` para gerar build em `public/build-v3/`

**Arquivo:** `resources/gente-v3/vite.config.js`

**Adicionar dentro do `defineConfig({...})`** (após `resolve` e antes de `server`):

```js
  // Output do build de produção
  // O build vai pra ../../public/build-v3/ (relativo a resources/gente-v3/)
  // O Laravel lê o manifest em public_path('build-v3/.vite/manifest.json')
  build: {
    outDir: '../../public/build-v3',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'src/main.js',
    },
  },
  // Base URL para asset paths no manifest e no HTML do app.blade.php
  // Em produção, os assets ficam em /build-v3/assets/<hash>.js
  base: '/build-v3/',
```

**Validação:** rodar `npm run build` em `resources/gente-v3/`. Esperado:
- Cria `public/build-v3/.vite/manifest.json`
- Cria `public/build-v3/assets/main-<hash>.js`
- Cria `public/build-v3/assets/main-<hash>.css`

### Tarefa 2 — Adicionar rota catch-all SPA no `routes/web.php`

**Problema:** se um usuário fizer refresh (F5) numa URL Vue Router profunda (ex: `/funcionarios/123`), Laravel retorna 404 porque essa rota não existe no backend. Precisa de fallback que devolve `view('v3.app')` pro Vue Router resolver client-side.

**Arquivo:** `routes/web.php`

**Adicionar NO FIM do arquivo** (logo antes do `?>` ou da última linha — não dentro de grupos middleware):

```php
// =============================================================================
// SPA CATCH-ALL — Vue Router resolve deep links em /qualquer/coisa
// IMPORTANTE: precisa ser a ÚLTIMA rota do arquivo. Qualquer rota Laravel real
// (/api/*, /login, /v3, /autocadastro/{token}) é matched ANTES dessa.
// Esta rota só captura URLs que nenhuma outra pegou.
// =============================================================================
Route::fallback(function () {
    // Em SPA, qualquer URL não mapeada vai pro Vue. O Vue Router decide o que renderizar.
    // Excluir prefixos da API/storage/sanctum (esses devem retornar 404 puro)
    $path = request()->path();
    foreach (['api/', 'storage/', 'sanctum/', 'remessa/', '_debugbar'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            abort(404);
        }
    }
    return view('v3.app');
});
```

**Por que `Route::fallback` e não `Route::get('/{any?}')`:**
- `fallback` só roda quando NENHUMA outra rota matched (mais seguro)
- `Route::get('/{any?}')` poderia interceptar requests que deveriam dar 404 explícito

**Validação manual local:**
```bash
php artisan route:list --columns=method,uri,name | tail -10
# Última linha deve ser: ANY  {fallbackPlaceholder}  (...)
```

### Tarefa 3 — Garantir que Vite copia assets estáticos (`/img/favicons.png`, `/logo.png`)

**Problema:** o `index.html` do Vue tem `<link rel="icon" href="/img/favicons.png" />` e o `LoginView.vue` tem `<img src="/logo.png" />`. Esses arquivos precisam estar em `public/` do Laravel (não no `public/` interno do gente-v3).

**Verificação:**
```bash
# Local (PC, Git Bash em sisgep-job-main/gente)
ls public/img/favicons.png public/logo.png 2>&1
```

**Se algum não existir:** copiar manualmente de onde estiver (provavelmente em `resources/gente-v3/public/`). Se não estiver em lugar nenhum, criar placeholder ou pedir do design.

**Esperado:** ambos arquivos em `public/img/favicons.png` e `public/logo.png` (acessíveis em `http://sistemagente.com/img/favicons.png` e `http://sistemagente.com/logo.png` direto).

### Tarefa 4 — Testar build local antes de pushar

**Comandos sequenciais (Git Bash, em `C:\Users\joaob\Desktop\sisgep-job-main\gente`):**

```bash
# 1. Garantir que está na branch certa
git status
git branch --show-current  # deve ser producao-pmsl

# 2. Instalar deps do Vue (se ainda não fez)
cd resources/gente-v3
npm install
# ~3-5 min, instala vue, vuetify, vite, etc (~250 MB node_modules)

# 3. Buildar
npm run build
# Esperado: "✓ N modules transformed" e "✓ built in Xs"
# NÃO PODE aparecer "Could not resolve" ou erro vermelho

# 4. Validar artefatos no Laravel public/
cd ../..
ls -la public/build-v3/.vite/manifest.json   # deve existir
ls -la public/build-v3/assets/ | head -5     # deve ter main-XXX.js + main-XXX.css

# 5. Validar conteúdo do manifest
cat public/build-v3/.vite/manifest.json | head -30
# Deve ter "src/main.js" como chave principal com "file": "assets/main-XXX.js" e "css": [...]
```

### Tarefa 5 — Limpar build_log.txt e build_log2.txt do repo (opcional, recomendado)

Esses logs são artefatos de tentativas antigas no Windows com OneDrive. Não devem estar no git. **Adicionar no `.gitignore`** (na raiz do projeto):

```
# Vite build logs
resources/gente-v3/build_log*.txt
resources/gente-v3/vite_log.txt
resources/gente-v3/dist/
```

E remover do tracking:
```bash
git rm --cached resources/gente-v3/build_log.txt resources/gente-v3/build_log2.txt resources/gente-v3/vite_log.txt 2>/dev/null
```

### Tarefa 6 — Commitar tudo em commits temáticos pequenos

```bash
# Commit 1: vite config
git add resources/gente-v3/vite.config.js
git commit -m "chore(frontend): configurar Vite para gerar build em public/build-v3 com manifest"

# Commit 2: rota catch-all
git add routes/web.php
git commit -m "feat(routes): adicionar Route::fallback para SPA Vue 3 (deep links)"

# Commit 3: gitignore + remoção de logs
git add .gitignore
git rm --cached resources/gente-v3/build_log.txt resources/gente-v3/build_log2.txt resources/gente-v3/vite_log.txt 2>/dev/null
git commit -m "chore: ignorar artefatos de build do gente-v3 (build_log, vite_log, dist)"

# Commit 4 (NÃO incluir public/build-v3 no git — vai ser gerado no servidor):
echo "/public/build-v3/" >> .gitignore
git add .gitignore
git commit -m "chore: ignorar public/build-v3 (gerado no deploy via npm run build)"

# Push
git push origin producao-pmsl
```

**IMPORTANTE — NÃO commitar o `public/build-v3/`:** o build vai ser regenerado no servidor durante o deploy (mais limpo, sem artefatos de Windows), e arquivos de build versionados sempre causam conflito.

---

## ⚠️ Critério de aceite (você precisa validar antes de pushar)

✅ `npm run build` em `resources/gente-v3/` termina sem erros vermelhos
✅ Existe `public/build-v3/.vite/manifest.json` com chave `src/main.js`
✅ Existe `public/build-v3/assets/main-*.js` (e geralmente `.css`)
✅ `php artisan route:list | grep fallback` mostra a rota fallback
✅ `git log --oneline -5` mostra os 4 commits temáticos novos
✅ `git push origin producao-pmsl` retorna sucesso
✅ Repo no GitHub mostra branch atualizado: `https://github.com/RR-Tecnol/gente/tree/producao-pmsl`

**Se o build falhar com erro vermelho:** PARA. Manda print/log pro Ronaldo. Não tenta consertar imports na bruta — provavelmente é OneDrive ainda sincronizando ou problema real que precisa investigar.

---

## ❌ NÃO FAÇA estas coisas

- ❌ NÃO mexer em `routes/web.php` além do bloco `Route::fallback` (arquivo é frágil, 1700+ linhas)
- ❌ NÃO commitar `public/build-v3/` (build é gerado no servidor)
- ❌ NÃO commitar `node_modules/` ou `package-lock.json` regenerado se houver muito ruído (use `git diff` antes de stage)
- ❌ NÃO criar arquivo `.env` ou `.env.production` no `resources/gente-v3/` ainda — sem reCAPTCHA o login funciona
- ❌ NÃO mexer em `main.js`, em arquivos de `views/`, ou em qualquer `.vue`
- ❌ NÃO rodar `git push --force` em hipótese alguma
- ❌ NÃO mexer na branch `master`

---

## 📂 Estrutura final esperada após o trabalho

```
resources/gente-v3/
├── vite.config.js          # MODIFICADO (build.outDir + base)
├── package.json            # intacto
├── src/
│   ├── main.js             # intacto
│   ├── router/index.js     # intacto
│   └── ...                 # intacto

resources/views/v3/
└── app.blade.php           # intacto (já lê manifest)

routes/
└── web.php                 # MODIFICADO (Route::fallback no fim)

public/
├── build-v3/               # NOVO (gerado por npm run build, NÃO commitar)
│   ├── .vite/
│   │   └── manifest.json
│   └── assets/
│       ├── main-XXXXX.js
│       └── main-XXXXX.css
├── img/favicons.png        # validar que existe
├── logo.png                # validar que existe
└── ...
```

---

## 📞 Quando terminar

Manda mensagem pro Ronaldo (no chat com a Claude) com:

1. ✅ Lista dos commits criados (saída de `git log --oneline -5`)
2. ✅ Confirmação de `git push` bem-sucedido
3. ✅ Confirmação de que `public/build-v3/.vite/manifest.json` foi criado local
4. ✅ Tamanho aproximado do `public/build-v3/` (provavelmente 5-15MB)
5. ⚠️ Qualquer warning ou comportamento estranho do build

Aí Claude + Ronaldo retomam o D6 no servidor: `cd /var/www/sistemagente.com && git pull && cd resources/gente-v3 && npm install && npm run build` e seguem pra D7/D8/D9.

**Boa sorte!**
