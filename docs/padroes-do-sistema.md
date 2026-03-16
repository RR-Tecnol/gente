# Padrões do Sistema — GENTE

## Arquitetura

O GENTE é uma aplicação **Laravel + Vue.js (SPA)**:

- **Backend:** Laravel 8, PHP 8.x, SQL Server
- **Frontend:** Vue 2 + Vuetify 2, compilado com Webpack Mix
- **Banco:** SQL Server (via ODBC Driver 18) — tabelas em MAIÚSCULAS

---

## Convenções de Rotas

### Regra: usar underscore, não hífen

```php
// ✅ Correto
Route::prefix('tipo_documento')->...
Route::prefix('abono_falta')->...
Route::prefix('parametro_financeiro')->...

// ❌ Errado (vai retornar 404)
Route::prefix('tipo-documento')->...
Route::prefix('abono-falta')->...
```

### Padrão de rotas por módulo (web.php)

```php
Route::prefix('modulo')->group(function () {
    Route::get('view',    [ModuloController::class, 'view']);     // página SPA
    Route::post('inserir',[ModuloController::class, 'inserir']);  // create
    Route::post('listar', [ModuloController::class, 'listar']);   // read/list
    Route::put('alterar', [ModuloController::class, 'alterar']);  // update
    Route::delete('deletar/{id}', [ModuloController::class, 'deletar']); // delete
});
```

---

## Convenções de Banco (SQL Server)

### Nomenclatura de tabelas e colunas

- Tabelas: `NOME_TABELA` (MAIÚSCULAS com underscore)
- Colunas: `TABELA_COLUNA` (prefixo = nome da tabela)
- PKs: `TABELA_ID` (integer, autoIncrement)
- Flags de ativo: `TABELA_ATIVO` (0/1)

```sql
-- Exemplo padrão
CREATE TABLE FUNCIONARIO (
    FUNCIONARIO_ID   INT IDENTITY PRIMARY KEY,
    FUNCIONARIO_NOME NVARCHAR(200),
    FUNCIONARIO_ATIVO INT DEFAULT 1,
    ...
)
```

### Migrations seguras (padrão do projeto)

```php
// Sempre checar antes de criar/alterar
if (!Schema::hasTable('TABELA')) {
    Schema::create('TABELA', function (Blueprint $table) { ... });
}

if (Schema::hasTable('TABELA') && !Schema::hasColumn('TABELA', 'COLUNA')) {
    Schema::table('TABELA', function (Blueprint $table) {
        $table->string('COLUNA')->nullable();
    });
}
```

---

## Autenticação

### Senha armazenada como MD5

```php
// app/Models/Usuario.php
public function setPasswordAttribute($value)
{
    $this->attributes["USUARIO_SENHA"] = md5($value);
}
```

> ⚠️ MD5 é inseguro para produção. Não alterar sem refatorar toda a lógica de auth.

### Campos do login

- Login: `USUARIO_LOGIN` (geralmente CPF)
- Senha: `USUARIO_SENHA` (não `password`)
- O POST é feito via **Axios/JSON** pelo componente Vue `Login.vue`

### CSRF

Fica na `<meta name="csrf-token">` e é enviado via header `X-CSRF-TOKEN` pelo Axios.

---

## Módulo Ponto Eletrônico

Controlado por feature flag no banco:

```sql
SELECT CONFIG_VALOR FROM CONFIGURACAO_SISTEMA WHERE CONFIG_CHAVE = 'MODULO_PONTO_ATIVO'
-- '0' = desativado (padrão), '1' = ativado
```

O middleware `modulo.ativo` (`CheckModuloAtivo.php`) bloqueia as rotas com HTTP 403 se desativado.

---

## Controllers

### Método view() em controllers de rota SPA

Todo controller que tem uma rota `GET /modulo/view` precisa do método `view()`:

```php
// Versão simples (apenas entrega a SPA)
public function view()
{
    return view('home');
}

// Versão com dados pré-carregados (blade específico)
public function view()
{
    $dados = TabelaGenerica::tipo_unidade();
    return view('modulo.modulo_view', compact('dados'));
}
```

### Verificar controllers sem view()

```bash
docker compose exec app sh check_controllers.sh
```

---

## Vue Components — Estrutura

```
resources/js/components/
├── auth/           Login.vue
├── assets/         TratarErroAjax.vue, BlockUI.vue, etc.
├── cadastros/      UnidadeView.vue, SetorView.vue, ...
├── escala/         EscalaView.vue, ConfiguraEscalaView.vue, ...
├── folha/          FolhaView.vue, EventoView.vue, ...
├── ponto/          PontoView.vue, ApuracaoPontoView.vue, ...
└── rh/             FuncionarioView.vue, FeriasView.vue, ...
```

### Padrão de chamada de API nos componentes

```javascript
// Listar
axios.get(`${this.baseUrl}/modulo/listar`, { params: this.filtros })
  .then(res => { this.itens = res.data.retorno })

// Inserir
axios.post(`${this.baseUrl}/modulo/inserir`, this.form)
  .then(res => { /* ... */ })
  .catch(err => { this.tratarErro({ id: this.msgId, response: err.response }) })
```

### Retorno padrão da API

```json
{
    "cod": 1,
    "msg": "Operação realizada com sucesso",
    "retorno": { ... }
}
```

---

## TabelaGenerica — Enum do sistema

Tabela centralizada de enumerações. Consultar via `TabelaGenerica::METODO()`:

```php
TabelaGenerica::sexo()               // tipos de sexo
TabelaGenerica::escolaridade()       // níveis de escolaridade
TabelaGenerica::tipoAfastamento()    // tipos de afastamento
TabelaGenerica::tipo_feriado()       // tipos de feriado
TabelaGenerica::status_folha()       // status da folha
// ... ver app/Models/TabelaGenerica.php para lista completa
```

IDs das tabelas estão em `app/MyLibs/RTG.php`.
