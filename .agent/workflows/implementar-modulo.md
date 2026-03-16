---
description: Como implementar um novo módulo no GENTE v3 (backend + frontend)
---

# Workflow: Implementar Novo Módulo

Use este workflow ao criar qualquer backend + frontend novo. Siga os passos em ordem — não pule etapas.

---

## Passo 1 — Verificar o que já existe

Antes de criar qualquer arquivo:

```bash
# Verificar se a tabela já foi criada
ls database/migrations/ | grep -i NOME_TABELA

# Verificar se o arquivo de rota já existe
ls routes/ | grep -i nome_modulo

# Verificar se a view já existe
find resources/gente-v3/src/views -name "*NomeModulo*"

# Verificar se a rota Vue já está registrada
grep -i "nome-modulo\|NomeModulo" resources/gente-v3/src/router/index.js
```

Se qualquer um existir → **não recriar**. Atualizar o que existe.

---

## Passo 2 — Migration (se a tabela ainda não existe)

Criar `database/migrations/YYYY_MM_DD_HHMMSS_create_NOME_TABELA_table.php`

**Regras obrigatórias:**
- Nome da tabela: **MAIUSCULAS_COM_UNDERSCORE** (ver §13 de regras-gerais.md)
- Campos: **MAIUSCULAS_COM_UNDERSCORE**
- Sempre incluir `timestamps()` (`created_at` / `updated_at`)
- Chave primária: `NOME_TABELA_ID` com `->increments()`
- FK para FUNCIONARIO: `FUNCIONARIO_ID` como `unsignedInteger`

```php
// Estrutura mínima:
Schema::create('NOME_TABELA', function (Blueprint $table) {
    $table->increments('NOME_TABELA_ID');
    $table->unsignedInteger('FUNCIONARIO_ID');
    // ... campos específicos
    $table->string('STATUS', 30)->default('ATIVO');
    $table->unsignedInteger('CRIADO_POR')->nullable();
    $table->timestamps();
});
```

Rodar: `php artisan migrate`

---

## Passo 3 — Arquivo de rotas

Criar `routes/nome_modulo.php`:

```php
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
// O contexto api/v3 + auth já é herdado do web.php

Route::get('/nome-modulo', function (Request $request) {
    // listagem
});

Route::post('/nome-modulo', function (Request $request) {
    // criação
});

Route::get('/nome-modulo/{id}', function ($id) {
    // detalhe
});

Route::patch('/nome-modulo/{id}', function (Request $request, $id) {
    // atualização parcial
});

Route::delete('/nome-modulo/{id}', function ($id) {
    // remoção (soft delete se possível)
});
```

---

## Passo 4 — Registrar no web.php

No bloco de `require` do `web.php`, dentro do grupo `api/v3`:

```php
require __DIR__ . '/nome_modulo.php';
```

Verificar que está **dentro** do `Route::prefix('api/v3')->middleware([...])->group(function () { ... })` existente.

---

## Passo 5 — View Vue

Criar `resources/gente-v3/src/views/SECAO/NomeModuloView.vue`

**Seções disponíveis:** `rh/`, `financeiro/`, `ponto/`, `config/`

**Estrutura mínima da view:**

```vue
<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Nome do Módulo</h1>

    <!-- Estado de loading -->
    <div v-if="loading">Carregando...</div>

    <!-- Conteúdo principal -->
    <div v-else>
      <!-- tabela, formulário, etc -->
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'  // ← SEMPRE axios, nunca fetch()

const loading = ref(false)
const lista   = ref([])

async function carregar() {
    loading.value = true
    try {
        const { data } = await api.get('/api/v3/nome-modulo')
        lista.value = data.itens ?? []
    } catch (err) {
        console.error('Erro ao carregar:', err)
        // Fallback mock apenas se necessário em dev
    } finally {
        loading.value = false
    }
}

onMounted(() => carregar())
</script>
```

---

## Passo 6 — Registrar rota Vue no router

Em `resources/gente-v3/src/router/index.js`, no array de rotas da seção correta:

```js
{
    path: 'nome-modulo',
    component: () => import('../views/SECAO/NomeModuloView.vue'),
    meta: { roles: ['admin', 'rh'] }  // ajustar conforme perfis permitidos
},
```

**Perfis disponíveis:** `admin`, `rh`, `gestor`, `funcionario`

---

## Passo 7 — Adicionar ao menu lateral

Em `resources/gente-v3/src/components/Sidebar.vue` (ou equivalente), adicionar item no menu da seção correta.

---

## Passo 8 — Testar

```bash
# 1. Verificar se a rota foi registrada
php artisan route:list | grep nome-modulo

# 2. Testar endpoint via curl ou Postman
curl -X GET http://localhost/api/v3/nome-modulo \
     -H "Cookie: laravel_session=..." \
     -H "X-CSRF-TOKEN: ..."

# 3. Verificar no navegador: abrir /v3 e navegar para o módulo
```

---

## Passo 9 — Documentar

Registrar em `docs/historico-problemas.md` qualquer problema encontrado durante a implementação.

Atualizar a tabela §17 de `.agent/workflows/regras-gerais.md` com o novo status do módulo.

Atualizar `PLANO_IMPLEMENTACAO_GENTE_V3.md` marcando os itens resolvidos.
