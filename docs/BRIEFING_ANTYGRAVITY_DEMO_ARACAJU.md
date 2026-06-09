# BRIEFING ANTYGRAVITY — DEMO ARACAJU (apresentação comercial)

**Data:** 11/05/2026
**Autoridade:** Ronaldo (RR Tecnol) — Claude (auditoria)
**Branch:** `apresentacao-aracaju` (a criar a partir de `auditoria-gente`)
**Objetivo:** preparar instância de demonstração da PMA (Prefeitura Municipal de Aracaju/SE) na mesma VPS (`sistemagente.com`), em subdomínio próprio, **sem tocar em PMSL produção**.

---

## ⚠️ ESCOPO RIGOROSAMENTE LIMITADO

Esta é uma **demo comercial**, NÃO é parametrização para folha real. Não mexer em:

- ❌ `app/Services/MotorFolhaService.php` — proibido tocar (GAP P0 PMSL ainda aberto)
- ❌ `app/Services/ApuracaoPontoService.php` — proibido tocar
- ❌ Qualquer migration de schema — proibido criar migration nova
- ❌ `config/jornada.php`, `config/gente.php` (valores PMSL servem para demo)
- ❌ Qualquer arquivo da branch `producao-pmsl`

Tudo que esta tarefa pede está confinado a:

- ✅ `database/seeders/` (criar novos seeders idempotentes Aracaju)
- ✅ `database/seeders/DatabaseSeeder.php` (registrar novos seeders sob flag)
- ✅ `.env.example` (documentar variáveis novas)
- ✅ `resources/views/` (apenas se mudança de logo/cores for explicitamente solicitada — ver Onda 3)
- ✅ `docs/` (registro)

---

## CONTEXTO PRÉVIO (LER ANTES DE CODAR)

1. O sistema GENTE v3 hoje serve **uma única tenancy** (PMSL). O arquivo `config/tenancy.php` declara `TENANCY_ENABLED=false` por padrão. **NÃO ligar TENANCY_ENABLED nesta tarefa.** A demo Aracaju roda numa segunda instância física da aplicação (segundo banco SQLite/SQL Server, segundo Nginx server_block, mesmo código).

2. Padrão a seguir: estudar `database/seeders/OrganogramaPMSLzSeeder.php` (idempotente, schema-aware via `Schema::hasColumn`, com fallback de busca por sigla/nome). Replicar **exatamente** o mesmo padrão para Aracaju. **Não inventar abordagem nova.**

3. Cada seeder Aracaju deve receber o sufixo `AracajuSeeder` e ser **chamado por flag de ambiente**, nunca por padrão. Exemplo no `DatabaseSeeder`:

```php
if (env('GENTE_TENANT_DEMO', null) === 'ARACAJU') {
    $this->call(OrganogramaAracajuSeeder::class);
    $this->call(TabelaSalarialAracajuSeeder::class);
    // ...
}
```

Isso garante que `php artisan db:seed` na VPS PMSL **continua se comportando exatamente como hoje** (zero risco de contaminação).

4. **Premissa explícita**: as faixas salariais usadas nos seeders Aracaju são valores **de demonstração**, NÃO oficiais. O Ronaldo confirmará valores reais antes de qualquer go-live em Aracaju. Antygravity NÃO deve buscar valores em sites externos para validar — usar os valores deste briefing.

---

## ONDA 1 — SEEDERS ARACAJU (obrigatório para apresentação)

### Tarefa 1.1 — `OrganogramaAracajuSeeder.php`

**Arquivo:** `database/seeders/OrganogramaAracajuSeeder.php`

**Padrão:** copiar literalmente a estrutura de `OrganogramaPMSLzSeeder.php` (mesmas chaves `$temSigla`, `$temUniAtivo`, `$temSetorAtivo`, `$temUniTS`, `$temSetorTS`, mesma lógica idempotente, mesmo `info()` final).

**Conteúdo das secretarias** (Lei Complementar Nº 119/2013 da PMA + estrutura atual confirmada pelos documentos `docs/davi/aracaju_estrutura_v1.md` e `docs/davi/aracaju_arquitetural_v2.md`):

```php
$secretarias = [
    ['GP', 'Gabinete do Prefeito'],
    ['SEGOV', 'Secretaria Municipal de Governo'],
    ['SEPLOG', 'Secretaria Municipal do Planejamento, Orçamento e Gestão'],
    ['SEMFAZ', 'Secretaria Municipal da Fazenda'],
    ['SEMED', 'Secretaria Municipal da Educação'],
    ['SMS', 'Secretaria Municipal da Saúde'],
    ['SEMFAS', 'Secretaria Municipal da Assistência Social'],
    ['SEMDEC', 'Secretaria Municipal da Defesa Social'],
    ['SEMA', 'Secretaria Municipal do Meio Ambiente'],
    ['SECULT', 'Secretaria Municipal da Cultura'],
    ['SETUR', 'Secretaria Municipal do Turismo'],
    ['SEJESP', 'Secretaria Municipal do Esporte'],
    ['SECOM', 'Secretaria Municipal de Comunicação'],
    ['PGM', 'Procuradoria-Geral do Município'],
    ['CGM', 'Controladoria-Geral do Município'],
    ['SEMPI', 'Secretaria Municipal da Articulação, Parcerias e Investimentos'],
];
```

**Setores por secretaria** (mínimo viável para apresentação):

```php
$setores = [
    'GP'     => ['Chefia de Gabinete', 'Assessoria Especial'],
    'SEPLOG' => ['Superintendência de Gestão de Pessoas', 'Coordenação de Folha', 'Coordenação de Cadastro Funcional'],
    'SEMFAZ' => ['Contadoria Geral', 'Tesouraria', 'Coordenação Orçamentária'],
    'SEMED'  => ['Superintendência de Recursos Humanos', 'Departamento de Ensino Fundamental', 'Departamento de Educação Infantil', 'Departamento de Lotação Escolar'],
    'SMS'    => ['Superintendência de Atenção Básica', 'Coordenação de Urgência e Emergência', 'Coordenação de Escalas Médicas'],
    'SEMFAS' => ['Coordenação de Assistência Social'],
    'SEMDEC' => ['Comando da Guarda Municipal', 'Coordenação de Operações'],
    'SEMA'   => ['Coordenação de Meio Ambiente'],
    'SECULT' => ['Coordenação Cultural'],
    'SETUR'  => ['Coordenação de Turismo'],
    'SEJESP' => ['Coordenação de Esporte'],
    'SECOM'  => ['Coordenação de Comunicação'],
    'PGM'    => ['Subprocuradoria Administrativa'],
    'CGM'    => ['Auditoria Interna'],
    'SEGOV'  => ['Assessoria de Articulação'],
    'SEMPI'  => ['Coordenação de Parcerias'],
];
```

**Administração Indireta** (criar como UNIDADE separada, com atributo distintivo se houver coluna; caso contrário, inserir junto):

```php
$indireta = [
    ['AJUPREV',  'Aracaju Previdência'],
    ['SMTT',     'Superintendência Municipal de Transporte e Trânsito'],
    ['FUNDAT',   'Fundação Municipal do Trabalho'],
    ['FUNCAJU',  'Fundação Cultural Cidade de Aracaju'],
    ['EMSURB',   'Empresa Municipal de Serviços Urbanos'],
    ['EMURB',    'Empresa Municipal de Obras e Urbanização'],
];
```

**Mensagem final esperada:**
```
✅ OrganogramaAracajuSeeder: 16 secretarias (direta) + 6 entidades (indireta) e N setores inseridos.
```

---

### Tarefa 1.2 — `FeriadosAracajuSeeder.php`

**Arquivo:** `database/seeders/FeriadosAracajuSeeder.php`

**Importante:** NÃO modificar `Feriados2026Seeder.php` (que ainda está com bug Doctrine DBAL conhecido). Criar arquivo separado idempotente. Estrutura de detecção de coluna idêntica ao padrão dos demais seeders.

**Feriados estaduais SE + municipais Aracaju 2026:**

```php
$feriados = [
    // Estaduais Sergipe
    ['2026-07-08', 'Emancipação Política de Sergipe', 'ESTADUAL'],
    // Municipais Aracaju
    ['2026-03-17', 'Aniversário de Aracaju', 'MUNICIPAL'],
    ['2026-12-08', 'Nossa Senhora da Conceição (Padroeira de Aracaju)', 'MUNICIPAL'],
];
```

**Atenção:** os feriados nacionais já são populados pelo seeder existente. NÃO duplicar.

Se houver bug com DBAL na coluna que armazena o tipo, usar `DB::table('FERIADO')->insert($payload)` em vez de Eloquent (mesma workaround usada em outros seeders).

---

### Tarefa 1.3 — `TabelaSalarialDemoAracajuSeeder.php`

**Arquivo:** `database/seeders/TabelaSalarialDemoAracajuSeeder.php`

**Premissa explícita:** valores são **DEMONSTRATIVOS**, baseados em faixas públicas da Lei Municipal Nº 5986/2024 da PMA conforme citado em `docs/davi/aracaju_arquitetural_v2.md`. **NÃO** ir buscar valores em fontes externas. Usar exatamente os valores abaixo:

```php
$cargos_demo = [
    // [SIGLA, NOME, NIVEL_INICIAL, FAIXAS [CLASSE_A_REF, VALOR]]
    ['PROF40', 'Professor Magistério 40h (Licenciatura)', [
        ['A', 3006.35], ['B', 3206.00], ['C', 3406.00], ['D', 3606.00],
    ]],
    ['PROF40D', 'Professor Magistério 40h (Doutorado)', [
        ['A', 4985.12], ['B', 5184.00], ['C', 5383.00],
    ]],
    ['MEDPSF', 'Médico PSF (40h)', [
        ['A', 10578.66], ['B', 11000.00], ['C', 11421.00],
    ]],
    ['MEDESP20', 'Médico Especialista (20h)', [
        ['A', 5289.00], ['B', 5500.00],
    ]],
    ['GUARDA3', 'Guarda Municipal 3ª Classe', [
        ['A', 2080.00], ['B', 2200.00],
    ]],
    ['GUARDAINSP', 'Inspetor Especial da Guarda', [
        ['A', 8943.30],
    ]],
    ['ADM_AUX', 'Auxiliar Administrativo', [
        ['A', 1041.46], ['B', 1180.00], ['C', 1290.00], ['D', 1399.63],
    ]],
    ['ENG_SR', 'Engenheiro (Nível Superior - faixa alta)', [
        ['A', 6500.00], ['B', 8000.00], ['C', 14364.98],
    ]],
];
```

Esses 8 cargos cobrem as duas grandes secretarias (SEMED e SMS) + administrativo + Guarda. **Não inflar a tabela** — é demo, não folha real.

---

### Tarefa 1.4 — `FuncionariosDemoAracajuSeeder.php`

**Arquivo:** `database/seeders/FuncionariosDemoAracajuSeeder.php`

**Premissa:** 10 funcionários fictícios para a apresentação. CPFs **sintéticos** (não usar CPF real de pessoa viva). Nomes neutros.

```php
$funcionarios_demo = [
    // [NOME, CPF_SINTETICO, CARGO_SIGLA, UNIDADE_SIGLA, SETOR_NOME, MATRICULA, REGIME]
    ['Ana Silva Pereira',      '11144477735', 'PROF40',     'SEMED',  'Departamento de Ensino Fundamental',  'ARA-100001', 'ESTATUTARIO'],
    ['Bruno Oliveira Santos',  '22255588846', 'PROF40D',    'SEMED',  'Departamento de Ensino Fundamental',  'ARA-100002', 'ESTATUTARIO'],
    ['Carla Mendes Costa',     '33366699957', 'MEDPSF',     'SMS',    'Superintendência de Atenção Básica',  'ARA-100003', 'ESTATUTARIO'],
    ['Daniel Rocha Lima',      '44477700068', 'MEDESP20',   'SMS',    'Coordenação de Urgência e Emergência','ARA-100004', 'ESTATUTARIO'],
    ['Eduarda Nascimento',     '55588811179', 'MEDESP20',   'SMS',    'Coordenação de Urgência e Emergência','ARA-100005', 'ESTATUTARIO'],
    ['Felipe Araújo Souza',    '66699922280', 'GUARDA3',    'SEMDEC', 'Comando da Guarda Municipal',         'ARA-100006', 'ESTATUTARIO'],
    ['Gabriela Tavares',       '77700033391', 'GUARDAINSP', 'SEMDEC', 'Comando da Guarda Municipal',         'ARA-100007', 'ESTATUTARIO'],
    ['Henrique Barbosa',       '88811144402', 'ADM_AUX',    'SEPLOG', 'Coordenação de Cadastro Funcional',   'ARA-100008', 'ESTATUTARIO'],
    ['Isabela Carvalho',       '99922255513', 'ADM_AUX',    'SEMFAZ', 'Tesouraria',                           'ARA-100009', 'ESTATUTARIO'],
    ['João Pedro Almeida',     '10003366624', 'ENG_SR',     'SEMPI',  'Coordenação de Parcerias',            'ARA-100010', 'ESTATUTARIO'],
];
```

**ATENÇÃO acúmulo lícito:** o sistema GENTE já suporta múltiplas matrículas por CPF. Para a apresentação, **NÃO** criar segundo vínculo agora. Marcar como "demonstrável ao vivo se cliente pedir".

**Regra de senha demo:** usar senha hash de uma string conhecida (`Aracaju@2026!`) para o usuário admin de demo. **NÃO** semear senhas reais.

---

### Tarefa 1.5 — `AdminDemoAracajuSeeder.php`

**Arquivo:** `database/seeders/AdminDemoAracajuSeeder.php`

Criar **um** usuário admin para a apresentação:

```php
$admin = [
    'USUARIO_NOME'  => 'Admin Demo Aracaju',
    'USUARIO_LOGIN' => 'admin.aracaju',
    'USUARIO_EMAIL' => 'admin@demo.aracaju.local',
    'USUARIO_SENHA' => Hash::make(env('ARACAJU_DEMO_ADMIN_PASS', 'Aracaju@2026!')),
    'PERFIL_ID'     => 1, // Desenvolvedor (já existente no sistema)
    'USUARIO_ATIVO' => 1,
];
```

Idempotência: se já existe `USUARIO_LOGIN='admin.aracaju'`, **fazer update** dos demais campos, NUNCA reinserir.

---

### Tarefa 1.6 — Registrar no `DatabaseSeeder.php`

Localizar a função `run()` em `database/seeders/DatabaseSeeder.php`. Adicionar bloco **NO FINAL** da função (depois de tudo que já roda):

```php
// ====================================================================
// DEMO ARACAJU — só executa se GENTE_TENANT_DEMO=ARACAJU no .env
// ====================================================================
if (env('GENTE_TENANT_DEMO', null) === 'ARACAJU') {
    $this->command->info('🟦 Carregando seeders de demonstração ARACAJU...');
    $this->call(OrganogramaAracajuSeeder::class);
    $this->call(FeriadosAracajuSeeder::class);
    $this->call(TabelaSalarialDemoAracajuSeeder::class);
    $this->call(FuncionariosDemoAracajuSeeder::class);
    $this->call(AdminDemoAracajuSeeder::class);
}
```

**NÃO** colocar `$this->call(...)` direto fora do `if`. Isso é crítico — se vazar, contamina PMSL.

---

### Tarefa 1.7 — Documentar `.env.example`

Adicionar ao final de `.env.example`:

```env
# ===================================================================
# DEMO ARACAJU (apresentação comercial 12/05/2026)
# Setar como ARACAJU apenas em instância de demo; NUNCA em PMSL prod.
# ===================================================================
GENTE_TENANT_DEMO=
ARACAJU_DEMO_ADMIN_PASS=
```

---

## ONDA 2 — PÓS-APRESENTAÇÃO (NÃO EXECUTAR AGORA)

Esta onda fica registrada aqui para o caso de Aracaju fechar contrato. **NÃO executar nesta sprint.** Lista apenas para inventário:

- Refator de `config/gente.php` linhas 105-118 (RBAC com chumbo `GLOBAL_SEMED` / `GLOBAL_SEMAD`) → tornar tenant-aware
- Refator de `config/transparencia.php` (owner=SEMAD chumbado) → parametrizar via env
- Decisão arquitetural multi-tenant: real (Spatie/Stancl) vs. instância separada por município
- `MotorFolhaService`: extrair "alíquota previdenciária" para tabela versionada por tenant + data efetiva (PMSL=IPAM, Aracaju=AJUPREV 14% + escalonamento patronal 24%→26%→28%)
- Base de cálculo da insalubridade parametrizada (salário mínimo nacional vs. vencimento base) por tenant + data efetiva
- Adicional noturno parametrizado (PMSL Lei 4.615/2006 = 25%; Aracaju LC 153/2016 = % a confirmar)
- Triênio vs. biênio vs. quinquênio parametrizado por PCCV
- GEA SEMED Aracaju (LC 177/2022) e abono fatiado da Guarda (Lei 5986/2024) — rubricas novas
- Layout do Portal da Transparência Aracaju vs. São Luís — gerador de relatório plugável

---

## ONDA 3 — VISUAL DA DEMO (opcional, se houver tempo)

Esta onda **só executar se Ronaldo aprovar explicitamente**. Caso contrário, rodar a demo com o visual atual de PMSL (logo do GENTE neutro já serve).

- Criar diretório `public/img/aracaju/` com logo da PMA (a fornecer)
- Variável `.env`: `GENTE_TENANT_LABEL="Prefeitura de Aracaju (DEMO)"` exibida no header se setada
- Cores: deixar como está. Trocar tema é desperdício de tempo pra amanhã.

---

## ENTREGA E VERIFICAÇÃO

Ao final da Onda 1:

1. Rodar localmente:
   ```bash
   # Criar segundo banco SQLite local para demo
   touch database/aracaju_demo.sqlite

   # Setar no .env temporário:
   # DB_DATABASE=database/aracaju_demo.sqlite
   # GENTE_TENANT_DEMO=ARACAJU

   php artisan migrate --force
   php artisan db:seed --force
   ```

2. Verificar pelo `tinker`:
   ```php
   DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEPLOG')->exists(); // true
   DB::table('FUNCIONARIO')->count(); // 10
   DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin.aracaju')->exists(); // true
   ```

3. Login na UI com `admin.aracaju` / `Aracaju@2026!`.

4. **Re-verificar PMSL não foi tocado**: rodar `php artisan db:seed --force` num clone do banco PMSL (sem `GENTE_TENANT_DEMO`) e confirmar zero linhas de Aracaju inseridas.

---

## COMMIT E BRANCH

```bash
git checkout auditoria-gente
git pull origin auditoria-gente
git checkout -b apresentacao-aracaju

# Após criar todos os arquivos:
git add database/seeders/OrganogramaAracajuSeeder.php
git add database/seeders/FeriadosAracajuSeeder.php
git add database/seeders/TabelaSalarialDemoAracajuSeeder.php
git add database/seeders/FuncionariosDemoAracajuSeeder.php
git add database/seeders/AdminDemoAracajuSeeder.php
git add database/seeders/DatabaseSeeder.php
git add .env.example
git add docs/BRIEFING_ANTYGRAVITY_DEMO_ARACAJU.md

git commit -m "feat(demo-aracaju): seeders de demonstração isolados por flag GENTE_TENANT_DEMO

- 16 secretarias da admin. direta + 6 entidades da admin. indireta (LC 119/2013 PMA)
- 8 cargos demo cobrindo SEMED/SMS/Guarda/Admin
- 10 funcionários fictícios com CPF sintético
- 1 admin de demonstração (login: admin.aracaju)
- Feriados estaduais SE + municipais Aracaju 2026
- ZERO mudança em motor de folha, ponto, ou config existente
- Seeders só rodam com GENTE_TENANT_DEMO=ARACAJU (proteção contra contaminação PMSL)

Branch: apresentacao-aracaju (não-produção, apenas demo comercial 12/05/2026)
Pós-apresentação: fica como base se Aracaju fechar contrato; descartável se não fechar."

git push -u origin apresentacao-aracaju
```

---

## REPORTE OBRIGATÓRIO

Ao final, retornar a Ronaldo:

1. ✅/❌ por tarefa (1.1 a 1.7)
2. Output do `php artisan db:seed --force` com `GENTE_TENANT_DEMO=ARACAJU`
3. Output dos 3 comandos `tinker` de verificação
4. SHA do commit
5. URL da PR (ou confirmação de push da branch)

**NÃO** reportar tarefa como ✅ se houver qualquer erro. Não há crédito parcial.

**NÃO** modificar nenhum arquivo fora do escopo listado acima. Se Antygravity perceber que precisa modificar algo fora (ex: uma migration, um service), **PARAR e perguntar ao Ronaldo**. Não improvisar.
