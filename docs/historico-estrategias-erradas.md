# Histórico de Estratégias Erradas — GENTE

> Leia este arquivo antes de adotar qualquer abordagem não-trivial.
> Se você está pensando em uma solução e encontrar algo parecido aqui — ela já falhou antes.

---

## [2026-03-11] Usar /exoneracao/buscar como autocomplete genérico

**Módulo:** DiariasView.vue, ConsignacaoView.vue

**Abordagem errada:**
Reutilizar o endpoint `/api/v3/exoneracao/buscar` para busca de servidor em outras views por ser o único existente no momento.

**Por que falhou:**
Cria acoplamento entre módulos sem relação. Se o endpoint de exoneração mudar (ex: adicionar filtro por status "não exonerado"), quebra silenciosamente todas as outras views que o usam.

**Abordagem correta:**
Criar endpoint genérico `/api/v3/servidores/buscar` e usar em todos os módulos exceto ExoneracaoView.

**Regra gerada:** §4 de `.agent/workflows/regras-gerais.md`

---

## [2026-03-11] Abrir grupo de rotas dentro de arquivo require'd

**Módulo:** routes/diarias.php, routes/rpps.php

**Abordagem errada:**
```php
// dentro de diarias.php:
Route::middleware(['web', 'auth'])->prefix('api/v3')->group(function () {
    Route::get('/diarias', ...);
});
```

**Por que falhou:**
O web.php já inclui esses arquivos dentro de um grupo `prefix('api/v3')`. Resultado: rota registrada em `/api/v3/api/v3/diarias` e middleware duplicado. Ocorreu em `diarias.php` e `rpps.php`.

**Abordagem correta:**
Arquivos require'd herdam o contexto do grupo pai. Usar apenas `Route::get/post/patch/delete` diretos.

**Regra gerada:** §2 de `.agent/workflows/regras-gerais.md`

---

## [2026-03-11] Usar fetch() nativo para chamadas autenticadas

**Módulo:** ConsignacaoView.vue, ExoneracaoView.vue, HoraExtraView.vue

**Abordagem errada:**
```js
const csrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
const r = await fetch('/api/v3/rota', {
    headers: { 'X-CSRF-TOKEN': decodeURIComponent(csrf) }
})
```

**Por que falhou:**
O token pode estar URL-encoded de formas diferentes dependendo do browser. Causa erro 419 silencioso em produção que não aparece em dev. O plugin axios já faz isso corretamente.

**Abordagem correta:**
```js
import api from '@/plugins/axios'
const { data } = await api.post('/api/v3/rota', payload)
```

**Regra gerada:** §3 de `.agent/workflows/regras-gerais.md`

---

## [2026-03-11] Calcular margem de consignação com campos inexistentes

**Módulo:** routes/consignacao.php

**Abordagem errada:**
```php
// Os campos DETALHE_TIPO e DETALHE_VALOR não existem na tabela DETALHE_FOLHA
->where('df.DETALHE_TIPO', 'LIQUIDO')
->select('df.DETALHE_VALOR as liquido')
```

**Por que falhou:**
Os campos reais são `DETALHE_FOLHA_PROVENTOS`, `DETALHE_FOLHA_DESCONTOS` e `DETALHE_FOLHA_LIQUIDO`. Qualquer cálculo de margem retorna zero — sem erro explícito, falha silenciosa.

**Abordagem correta:**
```php
->select(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0) as liquido'))
```

**Regra gerada:** BUG-01 no `PLANO_IMPLEMENTACAO_GENTE_V3.md`

---

## [2026-03-11] Tratar margem de consignação como valor único (35%)

**Módulo:** routes/consignacao.php, ConsignacaoView.vue

**Abordagem errada:**
Calcular uma única margem de 35% e validar qualquer tipo de consignação contra ela.

**Por que falhou:**
A legislação distingue duas margens separadas e independentes:
- 30% para empréstimos (BANCO, SINDICATO, COOPERATIVA)
- 5% para cartão consignado (CARTAO)

Um servidor pode ter 30% comprometido em empréstimos e ainda ter 5% de cartão disponível. Tratar como 35% único permite situações ilegais.

**Abordagem correta:**
Calcular e validar as duas margens separadamente por `CONVENIO_TIPO`.

**Regra gerada:** §12 de `.agent/workflows/regras-gerais.md` + CONSIG-01 no `PLANO_IMPLEMENTACAO_GENTE_V3.md`

---

## [2026-03-11] Ignorar o sistema legado sem documentar

**Módulo:** web.php (lints de CertidaoController, CartorioController, etc.)

**Abordagem errada:**
Ao registrar módulos novos no web.php e ver os lint warnings dos controllers legados, simplesmente ignorá-los sem documentar e sem verificar se algum require acidental está sendo adicionado.

**Por que falhou:**
Em uma sprint, um require de arquivo legado foi adicionado como efeito colateral de outra alteração, reintroduzindo código que deveria estar isolado.

**Abordagem correta:**
1. Confirmar que os erros são pré-existentes (não causados pelo seu código)
2. Registrar em `docs/historico-problemas.md` como "erro pré-existente não relacionado"
3. NÃO adicionar nenhum require que toque esses controllers

**Regra gerada:** §1 de `.agent/workflows/regras-gerais.md`

---

## [2026-03-11] Usar APP_KEY como segredo HMAC do JWT do app de ponto

**Módulo:** routes/ponto_app.php

**Abordagem errada:**
```php
$secret = config('app.key'); // reutilizar a chave principal do Laravel como segredo JWT
```

**Por que falhou:**
O `APP_KEY` é compartilhado com sessões, cookies e criptografia de dados no banco. Se o app mobile vazar a chave (ex: engenharia reversa do APK, log de erro exposto), todo o sistema fica comprometido — não apenas o app de ponto.

**Abordagem correta:**
Criar variável separada `PONTO_APP_JWT_SECRET` no `.env` e ler via `config('services.ponto_app.jwt_secret')`.

**Regra gerada:** SEC-03 no `PLANO_IMPLEMENTACAO_GENTE_V3.md` + §7 de `.agent/workflows/regras-gerais.md`

---

## [2026-03-15] Editar vite.config.js sem autorização do Tech Lead

**Módulo:** `resources/gente-v3/vite.config.js`

**Abordagem errada:**
Ao encontrar a porta 8000 bloqueada pelo processo `Manager` (XAMPP), o agente editou autonomamente o proxy do Vite:
```diff
- target: 'http://127.0.0.1:8000',
+ target: 'http://127.0.0.1:8080',
```

**Por que foi errado:**
`vite.config.js` é arquivo de configuração de infraestrutura crítica. A regra absoluta do `GRAVITY_GENTE_BRAIN.md` é: **NUNCA implemente sem autorização explícita do Tech Lead**. A mudança deveria ter sido proposta, não executada.

**O que deveria ter sido feito:**
> "Porta 8000 bloqueada pelo processo Manager (XAMPP). Opções:
> 1. Encerrar o XAMPP e liberar a porta 8000
> 2. Usar porta alternativa :8080 — requer alterar `vite.config.js` (aguardo autorização)
> 3. Configurar o XAMPP para usar outra porta
> Qual prefere, Tech Lead?"

**Resultado:**
Tecnicamente funcionou (login entrou no dashboard), mas o processo está errado. Arquivos que exigem autorização prévia obrigatória:
- `vite.config.js` / `vite.config.ts`
- `config/cors.php` *(exceção: Sprint 0 com autorização explícita)*
- `.env` *(exceção: Sprint 0 com autorização explícita)*
- qualquer arquivo de infraestrutura fora do código de negócio

**Regra reforçada:** §1 de `.agent/workflows/regras-gerais.md` — perguntar antes de editar arquivos de configuração.

---

*Adicionar novas entradas SEMPRE que uma abordagem falhar durante a implementação, antes de tentar a próxima.*
