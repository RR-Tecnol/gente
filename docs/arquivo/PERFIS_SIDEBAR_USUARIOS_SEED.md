# PERFIS, SIDEBAR POR PERFIL E SEED DE USUÁRIOS
**Data:** 16/03/2026 | **Projeto:** GENTE v3 — PMSLz

> Este documento define os perfis de acesso, o que cada um vê na sidebar,
> e os usuários de teste do seed. O Antygravity deve incluir esses usuários
> no FuncionariosPMSLzSeeder ou em um UsuariosSeedExtendido separado.

---

## PARTE 1 — PERFIS EXISTENTES NO SISTEMA

O PerfilSeeder já criou 15 perfis. Este documento os mapeia para os 4 roles
do Vue (`admin`, `rh`, `gestor`, `funcionario`) e define o que cada um vê.

| PERFIL_ID | Nome no Banco | Role Vue | Equivalente real na PMSLz |
|-----------|--------------|----------|--------------------------|
| 1 | Desenvolvedor | admin | RR TECNOL — acesso total |
| 2 | Administrador | admin | Coordenador SEMAD / TI |
| 3 | Operacional | rh | RH operacional de secretaria |
| 4 | Manutenção | admin | Equipe de suporte do sistema |
| 5 | Externo | funcionario | Servidor sem acesso expandido |
| 6 | RH Folha | rh | Equipe de folha de pagamento |
| 7 | Gestão | gestor | Gestor de setor/coordenação |
| 8 | RH Unidade | rh | RH descentralizado por secretaria |
| 9 | Direitos e Deveres | rh | RH jurídico / funcional |
| 10 | Recrutador | rh | Responsável por PSS/concurso |
| 11 | Coordenador de Setor | gestor | Coordenador / chefe de setor |
| 12 | Diretor / Gestor de Unidade | gestor | Secretário adjunto / diretor |
| 13 | Equipe SISGEP | admin | Equipe interna PMSLz que gerencia o sistema |
| 14 | RH APS | rh | RH da Atenção Primária à Saúde (SEMUS) |
| 15 | RH Rede | rh | RH hospitalar (SEMUS — hospitais) |

---

## PARTE 2 — SIDEBAR POR PERFIL

### 🔴 ADMINISTRADOR / DESENVOLVEDOR (roles: admin)
*SEMAD — TI — RR TECNOL*

Vê **tudo**. Sidebar completa sem exceção.

```
Visão Geral       Dashboard
Minha Área        Meu Perfil, Ponto, Holerites, Férias, Banco de Horas,
                  Declarações, Minha Progressão
Minha Equipe      Portal do Gestor, Organograma, Escala, Escalas Hospitalares,
                  Substituições, Sobreaviso, Hora Extra, Plantões
Recursos Humanos  Funcionários, Autocadastro, Cargos e Salários,
                  Contratos e Vínculos, Gerir Progressões, Exoneração/Rescisão,
                  PSS/Concurso, Estagiários, Terceirizados,
                  Acumulação de Cargos, Diárias
Frequência        Faltas e Atrasos, Abono de Faltas, Atestados Médicos
Saúde Ocup.       Medicina do Trabalho, Segurança do Trabalho
Fin. e Folha      Folha de Pagamento, Consignações, Verbas Indenizatórias,
                  Benefícios, RPPS/IPAM, Remessa CNAB, Gestão de Declarações
Compliance        eSocial, SAGRES/TCE-MA, Transparência Pública
Desenvolvimento   Avaliação, Treinamentos, Pesquisa, Gerenciar Pesquisas
Comunicação       Agenda, Comunicados, Ouvidoria, Painel Ouvidoria, Relatórios
Configurações     Configurações Gerais, Motor de Folha, Parâmetros Financeiros,
                  Vínculos, Turnos, Feriados, Tabelas Auxiliares, Eventos de Folha
ERP / Fiscal      Orçamento, Execução da Despesa, Contabilidade, Tesouraria,
                  Receita Municipal, Controle Externo
```

---

### 🟠 RH FOLHA (perfil 6) — role: rh
*Equipe de folha de pagamento — SEMAD*

Foco em folha, consignações e previdência. Não acessa ERP nem configurações.

```
Visão Geral       Dashboard
Minha Área        Meu Perfil, Ponto, Holerites, Férias, Banco de Horas,
                  Declarações, Minha Progressão
Recursos Humanos  Funcionários, Contratos e Vínculos
Frequência        Faltas e Atrasos, Abono de Faltas, Atestados Médicos
Fin. e Folha      Folha de Pagamento, Consignações, Verbas Indenizatórias,
                  Benefícios, RPPS/IPAM, Remessa CNAB, Gestão de Declarações
Compliance        eSocial, SAGRES/TCE-MA, Transparência Pública
Comunicação       Agenda, Comunicados, Ouvidoria, Painel Ouvidoria, Relatórios
```

**Sugestão de restrição futura:** RH Folha não deveria ver PSS/Concurso,
Estagiários ou Saúde Ocupacional — esses são do RH Operacional.
Por enquanto o role `rh` unifica todos os perfis RH.

---

### 🟠 RH UNIDADE / RH APS / RH REDE (perfis 8, 14, 15) — role: rh
*RH descentralizado por secretaria / SEMUS*

Igual ao RH Folha mas com acesso adicional a:
```
Recursos Humanos  + Autocadastro, Cargos e Salários, Gerir Progressões,
                    Exoneração/Rescisão, Acumulação de Cargos, Diárias
Saúde Ocup.       Medicina do Trabalho, Segurança do Trabalho
Desenvolvimento   Avaliação, Treinamentos, Pesquisa
```

RH Rede (perfil 15) foca nos hospitais — acessa Escalas Hospitalares
e tem visibilidade sobre plantões e sobreaviso.

---

### 🟠 OPERACIONAL / DIREITOS E DEVERES / RECRUTADOR (perfis 3, 9, 10) — role: rh
*Funções específicas de RH*

- **Operacional (3):** acesso RH geral — igual RH Unidade
- **Direitos e Deveres (9):** foco em contratos, vínculos, progressão, exoneração
- **Recrutador (10):** foco em PSS/Concurso, Autocadastro, Funcionários (novo cadastro)

Por enquanto todos mapeiam para role `rh` — granularidade futura por perfil específico.

---

### 🟡 GESTOR DE SETOR / COORDENADOR (perfis 7, 11) — role: gestor
*Chefes de setor, coordenadores, gerentes*

```
Visão Geral       Dashboard
Minha Área        Meu Perfil, Ponto, Holerites, Férias, Banco de Horas,
                  Declarações, Minha Progressão
Minha Equipe      Portal do Gestor, Organograma, Escala de Trabalho,
                  Substituições, Sobreaviso, Hora Extra, Plantões Extras
Frequência        Abono de Faltas, Atestados Médicos
Comunicação       Agenda, Comunicados, Ouvidoria
```

Não acessa folha, consignações, eSocial, configurações.
Vê a equipe, aprova escalas, lança hora extra e plantão.

---

### 🟡 DIRETOR / GESTOR DE UNIDADE (perfil 12) — role: gestor
*Secretários adjuntos, diretores de hospital, coordenadores de área*

Igual ao Gestor de Setor mas com visibilidade adicional:
```
+ Minha Equipe    Escalas Hospitalares (se SEMUS)
+ Comunicação     Painel Ouvidoria, Relatórios
```

Diretor de unidade vê relatórios consolidados da secretaria —
não edita, apenas consulta.

---

### 🟢 FUNCIONÁRIO (perfil 5 — Externo) — role: funcionario
*Servidor público sem função de gestão ou RH*

```
Visão Geral       Dashboard
Minha Área        Meu Perfil, Ponto, Holerites, Férias, Banco de Horas,
                  Declarações, Minha Progressão
Comunicação       Agenda, Comunicados, Ouvidoria
```

Só vê o próprio. Não vê nenhuma informação de outros servidores.

---

## PARTE 3 — USUÁRIOS DE TESTE (seed)

Um usuário por perfil. Todos vinculados aos funcionários do FuncionariosPMSLzSeeder.
Senhas no padrão MD5 do sistema. Login = matrícula do servidor.

**Arquivo:** adicionar ao `FuncionariosPMSLzSeeder.php` após inserir os funcionários,
ou criar `UsuariosPMSLzSeeder.php` separado chamado pelo DatabaseSeeder.

| # | Nome | Login | Senha | Perfil (ID) | Role Vue | Secretaria | Funcionário # |
|---|------|-------|-------|-------------|----------|-----------|--------------|
| U01 | Ronaldo Admin | `admin` | `admin123` | Administrador (2) | admin | SEMAD | — (já existe) |
| U02 | Ana Cristina Barros | `2026-0001` | `gente@2026` | Externo (5) | funcionario | SEMAD | #1 |
| U03 | José Carlos Lima | `2026-0002` | `gente@2026` | RH Folha (6) | rh | SEMAD | #2 |
| U04 | Maria das Dores Silva | `2026-0003` | `gente@2026` | Operacional (3) | rh | SEMFAZ | #3 |
| U05 | Francisco Ramos Costa | `2026-0004` | `gente@2026` | RH Rede (15) | rh | SEMUS | #4 |
| U06 | Antônia Pereira Nunes | `2026-0005` | `gente@2026` | RH APS (14) | rh | SEMUS | #5 |
| U07 | Raimundo Sousa Farias | `2026-0006` | `gente@2026` | Gestão (7) | gestor | SEMED | #6 |
| U08 | Luciana Moura Castro | `2026-0007` | `gente@2026` | Coordenador de Setor (11) | gestor | SEMED | #7 |
| U09 | Pedro Henrique Alves | `2026-0008` | `gente@2026` | Diretor/Gestor de Unidade (12) | gestor | SEMUSC | #8 |
| U10 | Cláudia Regina Santos | `2026-0009` | `gente@2026` | RH Unidade (8) | rh | SEMIT | #9 |
| U11 | Roberto Fonseca Melo | `2026-0010` | `gente@2026` | Externo (5) | funcionario | SEMAD | #10 (SP) |
| U12 | Geraldo Augusto Reis | `2026-0012` | `gente@2026` | Externo (5) | funcionario | GABPREF | #12 (CC) |
| U13 | Silvana Monteiro Cruz | `2026-0013` | `gente@2026` | Direitos e Deveres (9) | rh | SEPLAN | #13 |
| U14 | Marcos Vinícius Neto | `2026-0014` | `gente@2026` | Gestão (7) | gestor | SEMOSP | #14 |
| U15 | Carlos Eduardo Brito | `2026-0016` | `gente@2026` | Recrutador (10) | rh | SEMFAZ | #16 |
| U16 | Danielle Souza Cunha | `2026-0018` | `gente@2026` | Manutenção (4) | admin | SEMAD | #18 |
| U17 | equipe.sisgep | `sisgep` | `sisgep@2026` | Equipe SISGEP (13) | admin | SEMAD | — (técnico) |

**Cobertura de perfis pelo seed:**

| Perfil | Coberto? | Usuário |
|--------|---------|---------|
| 1 — Desenvolvedor | ✅ | admin (já tem perfil 1+2) |
| 2 — Administrador | ✅ | admin |
| 3 — Operacional | ✅ | U04 — Maria das Dores |
| 4 — Manutenção | ✅ | U16 — Danielle |
| 5 — Externo | ✅ | U02, U11, U12 |
| 6 — RH Folha | ✅ | U03 — José Carlos |
| 7 — Gestão | ✅ | U07, U14 |
| 8 — RH Unidade | ✅ | U10 — Cláudia |
| 9 — Direitos e Deveres | ✅ | U13 — Silvana |
| 10 — Recrutador | ✅ | U15 — Carlos Eduardo |
| 11 — Coordenador de Setor | ✅ | U08 — Luciana |
| 12 — Diretor/Gestor de Unidade | ✅ | U09 — Pedro Henrique |
| 13 — Equipe SISGEP | ✅ | U17 — equipe.sisgep |
| 14 — RH APS | ✅ | U06 — Antônia |
| 15 — RH Rede | ✅ | U05 — Francisco |

---

## PARTE 4 — CÓDIGO DO SEED (UsuariosPMSLzSeeder.php)

**Arquivo:** `database/seeders/UsuariosPMSLzSeeder.php`
**Chamar no DatabaseSeeder após FuncionariosPMSLzSeeder.**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria usuários de teste para todos os 15 perfis do sistema.
 * Vincula cada usuário ao funcionário correspondente do FuncionariosPMSLzSeeder.
 * Senhas: md5('gente@2026') para todos exceto admin e sisgep.
 *
 * php artisan db:seed --class=UsuariosPMSLzSeeder
 */
class UsuariosPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        $senhaPadrao = md5('gente@2026');
        $senhaSisgep = md5('sisgep@2026');

        // Mapa: matricula → [login, nome, perfil_id, funcionario_matricula]
        $usuarios = [
            // Externo — funcionário simples
            ['2026-0001', 'Ana Cristina Barros',   5,  '2026-0001'],
            // RH Folha
            ['2026-0002', 'José Carlos Lima',       6,  '2026-0002'],
            // Operacional
            ['2026-0003', 'Maria das Dores Silva',  3,  '2026-0003'],
            // RH Rede (hospitalar)
            ['2026-0004', 'Francisco Ramos Costa', 15,  '2026-0004'],
            // RH APS
            ['2026-0005', 'Antônia Pereira Nunes', 14,  '2026-0005'],
            // Gestão (gestor de setor)
            ['2026-0006', 'Raimundo Sousa Farias',  7,  '2026-0006'],
            // Coordenador de Setor
            ['2026-0007', 'Luciana Moura Castro',  11,  '2026-0007'],
            // Diretor/Gestor de Unidade
            ['2026-0008', 'Pedro Henrique Alves',  12,  '2026-0008'],
            // RH Unidade
            ['2026-0009', 'Cláudia Regina Santos',  8,  '2026-0009'],
            // Externo (Serviço Prestado)
            ['2026-0010', 'Roberto Fonseca Melo',   5,  '2026-0010'],
            // Externo (Comissionado)
            ['2026-0012', 'Geraldo Augusto Reis',   5,  '2026-0012'],
            // Direitos e Deveres
            ['2026-0013', 'Silvana Monteiro Cruz',  9,  '2026-0013'],
            // Gestão (FC)
            ['2026-0014', 'Marcos Vinícius Neto',   7,  '2026-0014'],
            // Recrutador
            ['2026-0016', 'Carlos Eduardo Brito',  10,  '2026-0016'],
            // Manutenção
            ['2026-0018', 'Danielle Souza Cunha',   4,  '2026-0018'],
        ];

        foreach ($usuarios as [$login, $nome, $perfilId, $matricula]) {
            // Busca o FUNCIONARIO_ID pela matrícula
            $funcId = DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_MATRICULA', $matricula)
                ->value('FUNCIONARIO_ID');

            // Cria ou atualiza o usuário
            $existente = DB::table('USUARIO')
                ->where('USUARIO_LOGIN', $login)->first();

            if (!$existente) {
                $usuarioId = DB::table('USUARIO')->insertGetId([
                    'USUARIO_LOGIN'  => $login,
                    'USUARIO_SENHA'  => $senhaPadrao,
                    'USUARIO_NOME'   => $nome,
                    'USUARIO_ATIVO'  => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                $usuarioId = $existente->USUARIO_ID;
            }

            // Vincula ao funcionário
            if ($funcId) {
                DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->update(['USUARIO_ID' => $usuarioId]);
            }

            // Vincula o perfil
            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $perfilId],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );

            $this->command->line("  ✓ [{$login}] {$nome} → Perfil {$perfilId}");
        }

        // Usuário técnico — Equipe SISGEP (sem vínculo com funcionário)
        $sisgepExiste = DB::table('USUARIO')
            ->where('USUARIO_LOGIN', 'sisgep')->first();
        if (!$sisgepExiste) {
            $sisgepId = DB::table('USUARIO')->insertGetId([
                'USUARIO_LOGIN' => 'sisgep',
                'USUARIO_SENHA' => $senhaSisgep,
                'USUARIO_NOME'  => 'Equipe SISGEP',
                'USUARIO_ATIVO' => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            DB::table('USUARIO_PERFIL')->insert([
                'USUARIO_ID' => $sisgepId, 'PERFIL_ID' => 13,
                'USUARIO_PERFIL_ATIVO' => 1,
            ]);
            $this->command->line('  ✓ [sisgep] Equipe SISGEP → Perfil 13');
        }

        $this->command->info('✅ ' . (count($usuarios) + 1) . ' usuários criados/verificados.');
        $this->command->warn('   Senhas padrão: gente@2026 | sisgep@2026');
        $this->command->warn('   Trocar em produção!');
    }
}
```

---

## PARTE 5 — MAPEAMENTO PERFIL → ROLE VUE

O sistema Vue usa 4 roles simples. O banco tem 15 perfis detalhados.
A função `userRoleLevel()` no `DashboardLayout.vue` precisa ser atualizada
para mapear os nomes reais dos perfis:

```js
// DashboardLayout.vue — função userRoleLevel() — atualizar:
function userRoleLevel(perfil) {
  if (!perfil) return 3
  const p = perfil.toLowerCase().trim()

  // ADMIN (role 0) — acesso total
  if (['admin', 'administrador', 'administrator',
       'desenvolvedor', 'developer',
       'manutenção', 'manutencao',
       'equipe sisgep'].includes(p)) return 0

  // RH (role 1) — recursos humanos e compliance
  if (['rh', 'rh folha', 'rh unidade', 'rh aps', 'rh rede',
       'operacional', 'direitos e deveres', 'recrutador',
       'recursos humanos'].includes(p)) return 1

  // GESTOR (role 2) — gestão de equipe e setor
  if (['gestor', 'gestão', 'gestao',
       'coordenador de setor', 'coordenador',
       'diretor / gestor de unidade',
       'diretor', 'gestor de unidade'].includes(p)) return 2

  // FUNCIONÁRIO (role 3) — acesso básico
  return 3 // externo, outros
}
```

**Mesma lógica no `router/index.js` — função `userRole()`:**
```js
// router/index.js — função userRole() — atualizar:
function userRole(perfil) {
  if (!perfil) return null
  const p = perfil.toLowerCase().trim()
  if (['admin','administrador','desenvolvedor',
       'manutenção','manutencao','equipe sisgep'].includes(p)) return 'admin'
  if (['rh','rh folha','rh unidade','rh aps','rh rede',
       'operacional','direitos e deveres','recrutador'].includes(p)) return 'rh'
  if (['gestor','gestão','gestao','coordenador de setor',
       'coordenador','diretor / gestor de unidade',
       'diretor','gestor de unidade'].includes(p)) return 'gestor'
  return 'funcionario'
}
```

---

## PARTE 6 — ATUALIZAR DatabaseSeeder

```php
public function run()
{
    $this->call([
        TabelaGenericaSeeder::class,       // 1. Enums + tipos afastamento
        PerfilSeeder::class,               // 2. 15 perfis
        ConfiguracaoSistemaSeeder::class,  // 3. Configs + SM + parâmetros motor
        MenuSeeder::class,                 // 4. Menu + permissões
        UsuarioSeeder::class,              // 5. Admin padrão (já existe)

        // Sprint 3 — Motor de folha
        RubricasCatalogoSeeder::class,     // 6. 27 rubricas
        VinculosPMSLzSeeder::class,        // 7. 10 vínculos reais
        OrganogramaPMSLzSeeder::class,     // 8. 26 secretarias + setores
        TabelaSalarialPMSLzSeeder::class,  // 9. 3 carreiras + tabelas reais
        FuncionariosPMSLzSeeder::class,    // 10. 18 funcionários de teste

        // Usuários de teste — deve vir APÓS funcionários
        UsuariosPMSLzSeeder::class,        // 11. 17 usuários, 1 por perfil
    ]);
}
```

---

## PARTE 7 — GUIA DE TESTES POR PERFIL

Ao testar o sistema, usar estes usuários para verificar o que cada perfil vê:

| O que testar | Login | O que deve aparecer |
|-------------|-------|---------------------|
| Funcionário básico | `2026-0001` | Apenas "Minha Área" + Comunicação |
| Gestor de setor | `2026-0006` | + "Minha Equipe" (sem folha/RH) |
| Coordenador | `2026-0007` | Igual gestor + Relatórios |
| Diretor de unidade | `2026-0008` | Igual coordenador + Painel Ouvidoria |
| RH Folha | `2026-0002` | Folha, Consignações, RPPS, eSocial |
| RH Unidade | `2026-0009` | RH completo sem ERP |
| RH APS (saúde) | `2026-0005` | Idem + Saúde Ocupacional |
| Recrutador | `2026-0016` | PSS/Concurso, Autocadastro, Funcionários |
| Admin | `admin` | Tudo incluindo Configurações e ERP |
| Equipe SISGEP | `sisgep` | Tudo — igual admin |
| Serviço Prestado | `2026-0010` | Apenas "Minha Área" (mesma visão do funcionário) |

---

*PERFIS_SIDEBAR_USUARIOS_SEED.md | GENTE v3 | RR TECNOL | 16/03/2026*
*15 perfis mapeados | 17 usuários de seed | 4 roles Vue | Sidebar por perfil*
