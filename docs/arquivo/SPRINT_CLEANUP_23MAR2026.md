# SPRINT CLEANUP — 23/03/2026
**Preparado por:** Gravity (auditoria) | **Executor:** Antygravity
**Gerado em:** 23/03/2026 | **PoC deadline:** ~15/04/2026

> Leia antes de agir: `.agent/workflows/regras-gerais.md` (v4.3) e `docs/MAPA_ESTADO_REAL.md`.
> Execute as tasks em ordem. Não avance para a próxima sem confirmar a atual.

---

## TASK-C1 🔴 — IC-06: Margem cartão 5% → 10%
**Arquivo:** `routes/consignacao.php`
**Base legal:** Decreto Municipal nº 57.477/2021, Art. 4º

Localizar e aplicar DUAS substituições:

**Substituição 1** (endpoint POST /consignacao — criação de contrato):
```php
// DE:
$margem_cartao = $liquido * 0.05;
// mensagem: 'Margem cartão insuficiente (5%)'

// PARA:
$margem_cartao = $liquido * 0.10;
// mensagem: 'Margem cartão insuficiente (10% — Decreto 57.477/2021)'
```

**Substituição 2** (endpoint GET /consignacao/margem/{funcionario_id}):
```php
// DE:
$margem_cartao = round($liquido * 0.05, 2);

// PARA:
$margem_cartao = round($liquido * 0.10, 2);
```

**Critério de aceite:** `GET /api/v3/consignacao/margem/{id}` retorna
`margem_cartao_total = liquido × 0.10` (não × 0.05).

---

## TASK-C2 🔴 — BUG-SEED-01: RubricasCatalogoSeeder falha
**Arquivo:** `database/seeders/RubricasCatalogoSeeder.php`

**Problema:** array `$data` dentro do foreach contém `'updated_at' => now()` e
`'created_at' => now()` — mas a tabela RUBRICA não possui colunas de timestamp.

**Ação:** ler o arquivo, localizar o array `$data` no foreach e remover
as duas chaves `created_at` e `updated_at` do array.
NÃO remover timestamps de outras tabelas — apenas do array de RUBRICA.

**Critério de aceite:** `php artisan db:seed --class=RubricasCatalogoSeeder`
executa sem `SQLSTATE[HY000]: General error`.

---

## TASK-C3 🔴 — BUG-SEED-02: FuncionariosPMSLzSeeder com campos inexistentes
**Arquivo:** `database/seeders/FuncionariosPMSLzSeeder.php`

**Problema:** seeder reescrito com campos que não existem nas tabelas.
Campos inexistentes identificados:

- **Em PESSOA:** `PESSOA_NATURALIDADE`, `PESSOA_NATURALIDADE_UF`,
  `PESSOA_ENDERECO_CIDADE`, `PESSOA_ENDERECO_UF`, `created_at`, `updated_at`
- **Em FUNCIONARIO:** `FUNCIONARIO_CLASSE`, `FUNCIONARIO_REFERENCIA`,
  `FUNCIONARIO_ESTAVEL`, `FUNCIONARIO_BANCO`, `FUNCIONARIO_BANCO_AGENCIA`,
  `FUNCIONARIO_BANCO_CONTA`, `CARREIRA_ID`, `created_at`, `updated_at`
- **Em LOTACAO:** `created_at`, `updated_at`
- `PESSOA_SEXO` deve ser integer (1=masculino, 2=feminino), não string `'M'`/`'F'`
- `PESSOA_STATUS` deve ser integer (1=ativo), não string `'ATIVO'`

**Ação:** ler o arquivo completo, remover TODOS os campos listados acima
dos arrays de INSERT. Manter apenas os campos reais confirmados nas migrations.
Consultar `docs/MAPA_CAMPOS_TABELAS.md` seção PESSOA e FUNCIONARIO antes de editar.

**Critério de aceite:** `php artisan db:seed --class=FuncionariosPMSLzSeeder`
executa sem `SQLSTATE[HY000]`.

---

## TASK-C4 🔴 — BUG-AC-07: Dependentes salvos na tabela errada
**Arquivo:** `routes/web.php` — endpoint `POST /autocadastro/{token}/aprovar`

**Problema:** código atual faz INSERT em tabela `DEPENDENTE` com campos inexistentes.
A tabela correta é `PESSOA_DEPENDENTE`.

**Localizar** o bloco de INSERT de dependentes no endpoint e substituir por:

```php
DB::table('PESSOA_DEPENDENTE')->insert([
    'FUNCIONARIO_ID'                 => $funcId,
    'PESSOA_DEPENDENTE_NOME'         => $dep['nome'],
    'PESSOA_DEPENDENTE_CPF'          => preg_replace('/\D/', '', $dep['cpf'] ?? ''),
    'PESSOA_DEPENDENTE_NASCIMENTO'   => $dep['data_nasc'] ?? null,
    'PESSOA_DEPENDENTE_PARENTESCO'   => $dep['parentesco'] ?? null,
    'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => (int)($dep['deducao_irrf'] ?? 0),
]);
// PESSOA_DEPENDENTE tem timestamps — não precisa informar manualmente.
```

**Critério de aceite:** aprovar autocadastro com dependentes não gera
`SQLSTATE[42S22]` (coluna inexistente).

---

## TASK-C5 🔴 — BUG-SIDEBAR-01: 8 módulos sem entrada na sidebar
**Arquivo:** `resources/gente-v3/src/views/layout/DashboardLayout.vue`

**Problema:** os seguintes módulos têm View e rota no backend mas não aparecem
na sidebar para nenhum perfil:
`/rpps`, `/diarias`, `/acumulacao-cargos`, `/transparencia`,
`/pss`, `/estagiarios`, `/terceirizados`, `/sagres-tce`

**Ação:**
1. Ler o arquivo `DashboardLayout.vue` completo.
2. Localizar o array de navegação (`ALL_NAV_ITEMS`, `navItems` ou similar).
3. Adicionar os 8 módulos ausentes nas seções corretas:
   - `/rpps` → seção "Financeiro e Folha" (junto com Consignação, Folha)
   - `/diarias` → seção "RH" (junto com Hora Extra, Banco de Horas)
   - `/acumulacao-cargos` → seção "RH"
   - `/estagiarios` → seção "RH"
   - `/terceirizados` → seção "RH"
   - `/pss` → seção "RH" (ou "Recrutamento" se existir)
   - `/transparencia` → seção "Compliance" (ou "Transparência")
   - `/sagres-tce` → seção "Compliance"
4. Verificar se as rotas estão no `router/index.js`. Se não estiverem,
   adicionar com o componente correto e `meta.requiresAuth = true`.
5. Aplicar controle de perfil consistente com o padrão existente.

---

## TASK-C5B 🔴 — BUG-SIDEBAR-04: userRoleLevel() não reconhece perfis reais
**Arquivo:** `resources/gente-v3/src/views/layout/DashboardLayout.vue`
(função `userRoleLevel()` ou `userRole()`)

**Mapeamento correto dos 15 perfis do banco para os 4 roles Vue:**

| Perfil no banco | ID | Role Vue |
|---|---|---|
| Desenvolvedor | 1 | admin |
| Administrador | 2 | admin |
| Manutenção | 4 | admin |
| Equipe SISGEP | 13 | admin |
| Operacional | 3 | rh |
| RH Folha | 6 | rh |
| RH Unidade | 8 | rh |
| Direitos e Deveres | 9 | rh |
| Recrutador | 10 | rh |
| RH APS | 14 | rh |
| RH Rede | 15 | rh |
| Gestão | 7 | gestor |
| Coordenador de Setor | 11 | gestor |
| Diretor / Gestor de Unidade | 12 | gestor |
| Externo | 5 | funcionario |

**Critério de aceite:**
- Login como `RH Folha` exibe `/rpps` e `/diarias` na sidebar
- Login como `admin` exibe todos os 8 módulos adicionados
- Nenhuma rota nova retorna 404

---

## TASK-C6 🟡 — IC-07: Holerite PDF — verificar e criar view Blade
**Arquivo:** `resources/views/pdf/holerite.blade.php`

**Verificar** se o arquivo existe. Se não existir, criar com estrutura mínima:
- Cabeçalho: "Prefeitura Municipal de São Luís / MA", competência,
  nome do servidor, matrícula, cargo, lotação
- Tabela proventos: código | descrição | referência | valor
- Tabela descontos: código | descrição | referência | valor
- Rodapé: total proventos | total descontos | valor líquido | linha de assinatura

Variáveis disponíveis: `$funcionario`, `$competencia`,
`$proventos` (collection), `$descontos` (collection), `$liquido`

**Critério de aceite:** `GET /api/v3/holerite/{id}/pdf` retorna PDF renderizado
sem erro 500.

---

## PÓS-EXECUÇÃO — DOCUMENTAÇÃO OBRIGATÓRIA

1. Atualizar `docs/MAPA_ESTADO_REAL.md` marcando cada bug resolvido:
   `✅ RESOLVIDO — Sprint Cleanup 23/03/2026`
2. Atualizar `docs/PLANO_MESTRE_V2.md` tabela STATUS ATUAL:
   - Consignação: remover "🔴 margem cartão"
   - Sidebar: marcar como ✅
   - Seeds: marcar como ✅
   - Autocadastro BUG-AC-07: marcar como ✅
3. NÃO modificar nenhum outro arquivo além dos listados nas tasks.
4. Confirmar via terminal: `php artisan db:seed` executa sem erro.

---

## ORDEM DE EXECUÇÃO

```
C1 (margem cartão) → C2 (seed rubricas) → C3 (seed funcionários)
→ C4 (dependentes) → C5 + C5B (sidebar) → C6 (holerite PDF)
```

PARAR e reportar se encontrar qualquer obstáculo não previsto.

---

*GENTE v3 | Sprint Cleanup | RR TECNOL | São Luís — MA | 23/03/2026*
