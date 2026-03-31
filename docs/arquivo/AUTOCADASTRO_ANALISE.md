# AUTOCADASTRO — ANÁLISE COMPLETA E ESPECIFICAÇÃO DE CORREÇÃO
**Data:** 16/03/2026 | **Arquivo:** `routes/web.php` — endpoint POST /autocadastro/{token}/aprovar

> Bug confirmado: aprovação do autocadastro não gera matrícula, não vincula usuário,
> usa campos errados e senha incompatível. Raissa foi aprovada mas ficou sem matrícula,
> sem vínculo e provavelmente sem conseguir logar.

---

## PARTE 1 — BUGS CONFIRMADOS NA APROVAÇÃO

### BUG-AC-01 🔴 Matrícula nunca gerada
**Localização:** `POST /autocadastro/{token}/aprovar` — passo 2
```php
// ATUAL (errado) — FUNCIONARIO_MATRICULA ausente:
$funcId = DB::table('FUNCIONARIO')->insertGetId([
    'PESSOA_ID'               => $pessoaId,
    'FUNCIONARIO_DATA_INICIO' => now()->format('Y-m-d'),
    'FUNCIONARIO_ATIVO'       => 1,
    // ← matrícula não existe aqui
]);
```
**Impacto:** Servidor aprovado fica sem matrícula. Sem matrícula não entra na folha,
não aparece corretamente nos relatórios e o eSocial rejeita o S-2200.
Este é o diferencial central do sistema — precisa funcionar.

---

### BUG-AC-02 🔴 CPF salvo no campo errado
```php
// ATUAL (errado):        'PESSOA_CPF'      → campo não existe
// CORRETO:               'PESSOA_CPF_NUMERO'
```

---

### BUG-AC-03 🔴 Cinco campos com nome errado na PESSOA
```php
// ATUAL → CORRETO
'PESSOA_NASC'         → 'PESSOA_DATA_NASCIMENTO'
'PESSOA_SEXO'         → 'PESSOA_SEXO_ID'           (integer)
'PESSOA_RG'           → 'PESSOA_RG_NUMERO'
'PESSOA_ORG_EMISSOR'  → 'PESSOA_RG_ORG_EMISSOR'
'ESTADO_CIVIL'        → 'ESTADO_CIVIL_ID'           (integer)
```
**Impacto:** Transação pode falhar silenciosamente no SQLite ou lançar exceção
que é capturada pelo catch genérico — servidor aprovado com PESSOA vazia.

---

### BUG-AC-04 🔴 USUARIO não vinculado a PESSOA nem a FUNCIONARIO
```php
// ATUAL — usa insert() sem capturar o ID:
DB::table('USUARIO')->insert([...])
// ← USUARIO_ID nunca salvo
// ← PESSOA.USUARIO_ID = null
// ← FUNCIONARIO.USUARIO_ID = null
// ← servidor não consegue logar (sistema busca via USUARIO_ID)
```

---

### BUG-AC-05 🔴 USUARIO_PERFIL nunca criado
Usuário inserido sem nenhuma entrada em USUARIO_PERFIL.
Sem perfil: sidebar vazia, router bloqueia todas as rotas, acesso zero.

---

### BUG-AC-06 🔴 Senha incompatível com o sistema
```php
// ATUAL (errado — mistura Hash::make com sistema MD5):
'USUARIO_SENHA' => $dados['senha_hash'] ?? Hash::make('mudar@123')

// O formulário envia: form.senha (texto puro, não 'senha_hash')
// $dados['senha_hash'] nunca existe → cai no fallback
// Hash::make() gera bcrypt — sistema valida MD5
// Resultado: senha nunca bate, servidor não consegue logar jamais
```


---

## PARTE 2 — DOIS MODOS DE USO DO AUTOCADASTRO

Antes de especificar a correção, é preciso definir os dois modos que o sistema deve suportar:

### Modo A — Recadastramento (servidor já existe)
O RH envia o link para um servidor que **já está no banco** (importado do sistema legado).
O servidor preenche/confirma os dados pessoais.
Ao aprovar: **atualiza** PESSOA existente, não cria nova.
Matrícula já existe — apenas confirma.

### Modo B — Novo cadastro (servidor novo)
O RH envia o link para alguém que **ainda não existe** no banco.
Ao aprovar: **cria** PESSOA + FUNCIONARIO + USUARIO.
Matrícula é **gerada automaticamente**.

**Distinção:** o token pode ter `FUNCIONARIO_ID` pré-preenchido (Modo A)
ou nulo (Modo B — novo cadastro como a Raissa).

A AUTOCADASTRO_TOKEN já tem o campo `FUNCIONARIO_ID` — basta usá-lo para diferenciar.

---

## PARTE 3 — LÓGICA DE GERAÇÃO DE MATRÍCULA

Formato: `ANO-SEQUENCIAL` → ex: `2026-0019`

```php
function gerarMatricula(): string {
    $ano = date('Y');

    // Busca a maior matrícula do ano corrente
    $ultima = DB::table('FUNCIONARIO')
        ->where('FUNCIONARIO_MATRICULA', 'like', "{$ano}-%")
        ->orderByDesc('FUNCIONARIO_MATRICULA')
        ->value('FUNCIONARIO_MATRICULA');

    if ($ultima) {
        // Extrai o sequencial e incrementa
        $seq = (int) explode('-', $ultima)[1] + 1;
    } else {
        // Primeiro do ano
        $seq = 1;
    }

    return $ano . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    // Resultado: 2026-0001, 2026-0019, 2026-0120, etc.
}
```

**Regras da matrícula:**
- Única e imutável após geração
- Baseada no ano de admissão
- Sequencial por ano (reinicia em 0001 a cada ano)
- Prefixos opcionais por secretaria são configuração futura — por ora, padrão único municipal
- Compatível com o formato já usado nos 18 funcionários do seed (`2026-0001` a `2026-0018`)

---

## PARTE 4 — ENDPOINT CORRIGIDO (especificação para Antygravity)

> ⚠️ **ATENÇÃO TIMESTAMPS:** `USUARIO`, `USUARIO_PERFIL`, `FUNCIONARIO`, `DEPENDENTE` e `PESSOA`
> **NÃO têm `created_at`/`updated_at`** — não inserir esses campos. Apenas `AUTOCADASTRO_TOKEN` tem.
>
> ⚠️ **ATENÇÃO CAMPOS PESSOA — nomes reais nas migrations:**
>
> | Usar no INSERT | Nome real na tabela |
> |---------------|-------------------|
> | CPF | `PESSOA_CPF_NUMERO` + `PESSOA_CPF` (legada) |
> | Nascimento | `PESSOA_DATA_NASCIMENTO` + `PESSOA_NASC` (legada) |
> | Sexo | `PESSOA_SEXO` (integer) — NÃO existe PESSOA_SEXO_ID |
> | RG | `PESSOA_RG` (legada) + `PESSOA_RG_NUMERO` |
> | Org. Emissor RG | `PESSOA_ORG_EMISSOR` (legada) + `PESSOA_RG_EXPEDIDOR` |
> | PIS/PASEP | `PESSOA_PIS_PASEP` ✅ |
> | Estado Civil | `ESTADO_CIVIL` (legada) + `PESSOA_ESTADO_CIVIL` |
> | Grau Instrução | `ESCOLARIDADE_ID` — NÃO existe PESSOA_GRAU_INSTRUCAO |
> | Raça/Cor | `PESSOA_RACA` — NÃO existe PESSOA_RACA_COR |
> | CEP | `PESSOA_CEP` ✅ |
> | Endereço | `PESSOA_ENDERECO` ✅ |
> | Bairro | `BAIRRO_ID` (FK integer) — NÃO existe PESSOA_BAIRRO |
> | USUARIO_ID | `USUARIO_ID` ✅ |
> | Email | **NÃO existe na PESSOA** — ignorar por ora |
> | Telefone | **NÃO existe na PESSOA** — ignorar por ora |
> | Nome Social | **NÃO existe na PESSOA** — ignorar por ora |
> | Nº Endereço | **NÃO existe na PESSOA** — ignorar por ora |

**Substituir** o bloco `Route::post('/autocadastro/{token}/aprovar', ...)` atual
pelo código abaixo:

```php
Route::post('/autocadastro/{token}/aprovar', function ($token) {
    try {
        $reg = DB::table('AUTOCADASTRO_TOKEN')
            ->where('TOKEN', $token)
            ->where('TOKEN_STATUS', 'preenchido')
            ->first();

        if (!$reg)
            return response()->json(['erro' => 'Registro não encontrado ou já aprovado'], 404);

        $dados = json_decode($reg->TOKEN_DADOS, true);
        if (!$dados)
            return response()->json(['erro' => 'Sem dados para aprovar'], 422);

        $resultado = DB::transaction(function () use ($reg, $dados, $token) {

            // ═══════════════════════════════════════════════════════
            // MODO A: Recadastramento — servidor já existe no banco
            // ═══════════════════════════════════════════════════════
            if ($reg->FUNCIONARIO_ID) {
                $func = DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $reg->FUNCIONARIO_ID)->first();

                // Atualiza PESSOA com dados confirmados pelo servidor
                DB::table('PESSOA')
                    ->where('PESSOA_ID', $func->PESSOA_ID)
                    ->update([
                        'PESSOA_NOME'            => $dados['nome'],
                        'PESSOA_NOME_SOCIAL'      => $dados['nome_social'] ?? null,
                        'PESSOA_CPF_NUMERO'       => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
                        'PESSOA_CPF'              => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
                        'PESSOA_DATA_NASCIMENTO'  => $dados['data_nasc'] ?? null,
                        'PESSOA_NASC'             => $dados['data_nasc'] ?? null,
                        'PESSOA_SEXO'             => (int)($dados['sexo'] ?? 0) ?: null,
                        'PESSOA_RG'               => $dados['rg'] ?? null,
                        'PESSOA_RG_NUMERO'        => $dados['rg'] ?? null,
                        'PESSOA_ORG_EMISSOR'      => $dados['org_emissor'] ?? null,
                        'PESSOA_RG_EXPEDIDOR'     => $dados['org_emissor'] ?? null,
                        'PESSOA_PIS_PASEP'        => preg_replace('/\D/', '', $dados['pis'] ?? ''),
                        'ESTADO_CIVIL'            => (int)($dados['estado_civil'] ?? 0) ?: null,
                        'PESSOA_ESTADO_CIVIL'     => (int)($dados['estado_civil'] ?? 0) ?: null,
                        'ESCOLARIDADE_ID'         => (int)($dados['grau_instrucao'] ?? 0) ?: null,
                        'PESSOA_ESCOLARIDADE'     => (int)($dados['grau_instrucao'] ?? 0) ?: null,
                        'PESSOA_RACA'             => (int)($dados['raca_cor'] ?? 0) ?: null,
                        'PESSOA_CEP'              => preg_replace('/\D/', '', $dados['cep'] ?? ''),
                        'PESSOA_ENDERECO'         => $dados['logradouro'] ?? null,
                        // email/telefone/bairro/numero: sem coluna na PESSOA — ignorar
                    ]);

                // Atualiza senha do USUARIO se enviada
                if (!empty($dados['senha'])) {
                    $pessoaAtual = DB::table('PESSOA')->where('PESSOA_ID', $func->PESSOA_ID)->first();
                    if ($pessoaAtual?->USUARIO_ID) {
                        DB::table('USUARIO')
                            ->where('USUARIO_ID', $pessoaAtual->USUARIO_ID)
                            ->update(['USUARIO_SENHA' => md5($dados['senha'])]);
                    }
                }

                DB::table('AUTOCADASTRO_TOKEN')
                    ->where('TOKEN', $token)
                    ->update(['TOKEN_STATUS' => 'aprovado', 'updated_at' => now()]);

                return [
                    'modo'      => 'recadastramento',
                    'matricula' => $func->FUNCIONARIO_MATRICULA,
                    'msg'       => 'Dados atualizados com sucesso.',
                ];
            }

            // ═══════════════════════════════════════════════════════
            // MODO B: Novo cadastro — criar PESSOA + FUNCIONARIO + USUARIO
            // ═══════════════════════════════════════════════════════

            // 1. Gerar matrícula única
            $ano    = date('Y');
            $ultima = DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_MATRICULA', 'like', "{$ano}-%")
                ->orderByDesc('FUNCIONARIO_MATRICULA')
                ->value('FUNCIONARIO_MATRICULA');
            $seq = $ultima ? ((int) explode('-', $ultima)[1] + 1) : 1;
            $matricula = $ano . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // 2. Criar USUARIO
            $senhaHash = md5($dados['senha'] ?? 'mudar@123');
            $loginUsuario = preg_replace('/\D/', '', $dados['cpf'] ?? '') ?: $dados['email'];
            // ⚠️ USUARIO não tem timestamps
            $usuarioId = DB::table('USUARIO')->insertGetId([
                'USUARIO_NOME'   => $dados['nome'],
                'USUARIO_LOGIN'  => $loginUsuario,
                'USUARIO_SENHA'  => $senhaHash,
                'USUARIO_ATIVO'  => 1,
            ]);

            // 3. Vincular perfil padrão: Externo (PERFIL_ID = 5)
            DB::table('USUARIO_PERFIL')->insert([
                'USUARIO_ID'           => $usuarioId,
                'PERFIL_ID'            => 5,
                'USUARIO_PERFIL_ATIVO' => 1,
            ]);

            // 4. Criar PESSOA — usar campos reais das migrations
            $pessoaId = DB::table('PESSOA')->insertGetId([
                'PESSOA_NOME'            => $dados['nome'],
                'PESSOA_NOME_SOCIAL'     => $dados['nome_social'] ?? null,
                'PESSOA_CPF_NUMERO'      => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
                'PESSOA_CPF'             => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
                'PESSOA_DATA_NASCIMENTO' => $dados['data_nasc'] ?? null,
                'PESSOA_NASC'            => $dados['data_nasc'] ?? null,
                'PESSOA_SEXO'            => (int)($dados['sexo'] ?? 0) ?: null,
                'PESSOA_RG'              => $dados['rg'] ?? null,
                'PESSOA_RG_NUMERO'       => $dados['rg'] ?? null,
                'PESSOA_ORG_EMISSOR'     => $dados['org_emissor'] ?? null,
                'PESSOA_RG_EXPEDIDOR'    => $dados['org_emissor'] ?? null,
                'PESSOA_PIS_PASEP'       => preg_replace('/\D/', '', $dados['pis'] ?? ''),
                'ESTADO_CIVIL'           => (int)($dados['estado_civil'] ?? 0) ?: null,
                'PESSOA_ESTADO_CIVIL'    => (int)($dados['estado_civil'] ?? 0) ?: null,
                'ESCOLARIDADE_ID'        => (int)($dados['grau_instrucao'] ?? 0) ?: null,
                'PESSOA_ESCOLARIDADE'    => (int)($dados['grau_instrucao'] ?? 0) ?: null,
                'PESSOA_RACA'            => (int)($dados['raca_cor'] ?? 0) ?: null,
                'PESSOA_CEP'             => preg_replace('/\D/', '', $dados['cep'] ?? ''),
                'PESSOA_ENDERECO'        => $dados['logradouro'] ?? null,
                'USUARIO_ID'             => $usuarioId,
                'PESSOA_ATIVO'           => 1,
                'PESSOA_DATA_CADASTRO'   => now()->format('Y-m-d'),
                // email/telefone/bairro/numero: sem coluna na PESSOA — ignorar
                // timestamps: PESSOA não tem created_at/updated_at
            ]);

            // 5. Criar FUNCIONARIO com matrícula gerada
            // ⚠️ FUNCIONARIO não tem timestamps
            $funcId = DB::table('FUNCIONARIO')->insertGetId([
                'PESSOA_ID'               => $pessoaId,
                'FUNCIONARIO_MATRICULA'   => $matricula,
                'FUNCIONARIO_DATA_INICIO' => now()->format('Y-m-d'),
                'FUNCIONARIO_ATIVO'       => 1,
                'USUARIO_ID'              => $usuarioId,
            ]);

            // 6. Salvar dependentes se enviados
            $dependentes = $dados['dependentes'] ?? [];
            foreach ($dependentes as $dep) {
                if (empty($dep['nome'])) continue;
                // Usar PESSOA_DEPENDENTE (tem os campos certos) em vez de DEPENDENTE (legado sem campos)
                DB::table('PESSOA_DEPENDENTE')->insert([
                    'FUNCIONARIO_ID'                  => $funcId,
                    'PESSOA_DEPENDENTE_NOME'           => $dep['nome'],
                    'PESSOA_DEPENDENTE_CPF'            => preg_replace('/\D/', '', $dep['cpf'] ?? ''),
                    'PESSOA_DEPENDENTE_NASCIMENTO'     => $dep['data_nasc'] ?? null,
                    'PESSOA_DEPENDENTE_PARENTESCO'     => $dep['parentesco'] ?? null,
                    'PESSOA_DEPENDENTE_DEDUCAO_IRRF'   => (int)($dep['deducao_irrf'] ?? 0),
                    // PESSOA_DEPENDENTE tem timestamps ✓
                ]);
            }

            // 7. Notificar usuários do RH (NOTIFICACAO exige USUARIO_ID individual)
            // Busca todos os usuários com perfil RH (PERFIL_ID = 3,6,8,9,14,15)
            $usuariosRh = DB::table('USUARIO_PERFIL')
                ->whereIn('PERFIL_ID', [3, 6, 8, 9, 14, 15])
                ->where('USUARIO_PERFIL_ATIVO', 1)
                ->pluck('USUARIO_ID');
            foreach ($usuariosRh as $uid) {
                DB::table('NOTIFICACAO')->insert([
                    'USUARIO_ID'          => $uid,
                    'NOTIFICACAO_TITULO'  => "Autocadastro aprovado — {$dados['nome']}",
                    'NOTIFICACAO_BODY'    => "Matrícula: {$matricula} | Login: {$loginUsuario}",
                    'NOTIFICACAO_TIPO'    => 'success',
                    'NOTIFICACAO_ICONE'   => '✅',
                    'NOTIFICACAO_URL'     => "/funcionario/{$funcId}",
                    'NOTIFICACAO_LIDA'    => 0,
                    'NOTIFICACAO_DT_CRIACAO' => now(),
                    // NOTIFICACAO não tem created_at/updated_at
                ]);
            }

            // 8. Marcar token como aprovado
            DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->update([
                    'TOKEN_STATUS'   => 'aprovado',
                    'FUNCIONARIO_ID' => $funcId,
                    'updated_at'     => now(),
                ]);

            return [
                'modo'           => 'novo_cadastro',
                'matricula'      => $matricula,
                'funcionario_id' => $funcId,
                'login'          => $loginUsuario,
                'msg'            => "Cadastro aprovado! Matrícula: {$matricula}",
            ];
        });

        return response()->json(['ok' => true, ...$resultado]);

    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```


---

## PARTE 5 — AJUSTES NO FRONTEND (AutocadastroGestaoView.vue)

Após aprovar, o drawer deve exibir a matrícula gerada:

```js
// aprovar() — atualizar após o await:
const aprovar = async (token) => {
  aprovando.value = true; erroAprovacao.value = ''
  try {
    const { data } = await api.post(`/api/v3/autocadastro/${token}/aprovar`)

    const idx = tokens.value.findIndex(t => t.TOKEN === token)
    if (idx !== -1) tokens.value[idx].TOKEN_STATUS = 'aprovado'
    tokenAberto.value = null

    // Mostrar matrícula gerada no toast
    const msg = data.matricula
      ? `✅ Aprovado! Matrícula: ${data.matricula} — Login: ${data.login}`
      : '✅ Cadastro aprovado com sucesso.'
    showToast(msg)

  } catch (e) {
    erroAprovacao.value = e.response?.data?.erro || 'Erro ao aprovar.'
  } finally {
    aprovando.value = false
  }
}
```

**Adicionar no drawer** — exibir matrícula após aprovação:
```html
<!-- Dentro do drawer-footer, após aprovação: -->
<div v-if="matriculaGerada" class="matricula-result">
  <span class="mat-label">Matrícula gerada</span>
  <span class="mat-valor">{{ matriculaGerada }}</span>
  <span class="mat-login">Login: {{ loginGerado }}</span>
</div>
```

---

## PARTE 6 — AJUSTE NO FORMULÁRIO PÚBLICO (AutocadastroView.vue)

O formulário envia `form.senha` mas o backend esperava `senha_hash`.
Com a correção, o backend lê `$dados['senha']` diretamente — nenhuma mudança
no formulário é necessária. Apenas confirmar que `fd.append('senha', form.value.senha)`
está presente (já está no código existente).

---

## PARTE 7 — TOKEN COM FUNCIONÁRIO VINCULADO (Modo A — recadastramento)

Para suportar recadastramento de servidores existentes, o RH deve poder
vincular o token a um funcionário já cadastrado:

**Atualizar endpoint de geração de link:**
```php
Route::post('/autocadastro/gerar-link', function (Request $request) {
    // Adicionar suporte a funcionario_id opcional:
    DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN'          => $token,
        'TOKEN_EMAIL'    => $request->email ?? null,
        'TOKEN_NOME'     => $request->nome ?? null,
        'FUNCIONARIO_ID' => $request->funcionario_id ?? null, // ← NOVO
        'TOKEN_STATUS'   => 'pendente',
        'CRIADO_POR'     => auth()->id(),
        'expira_em'      => $expira,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    // ...
});
```

**Atualizar frontend de geração:**
Adicionar campo de busca de funcionário no gerador de link.
Quando selecionado → modo recadastramento, exibe aviso:
`"Este link atualiza os dados do servidor existente. A matrícula não será alterada."`

---

## PARTE 8 — CRITÉRIOS DE ACEITE

```
- [ ] Ao aprovar novo cadastro: PESSOA criada com todos os campos corretos
- [ ] Matrícula gerada no formato YYYY-NNNN (ex: 2026-0019)
- [ ] Matrícula é sequencial — não pula nem duplica
- [ ] USUARIO criado com login = CPF (sem pontos/traço) ou email se CPF ausente
- [ ] USUARIO_SENHA = md5(senha digitada pelo servidor no formulário)
- [ ] USUARIO_PERFIL criado com PERFIL_ID = 5 (Externo)
- [ ] PESSOA.USUARIO_ID preenchido com o ID criado
- [ ] FUNCIONARIO.USUARIO_ID preenchido com o ID criado
- [ ] Dependentes salvos na tabela DEPENDENTE
- [ ] Notificação disparada para RH com matrícula e login
- [ ] Toast no frontend exibe matrícula e login após aprovação
- [ ] Modo A (recadastramento): PESSOA atualizada, matrícula não alterada
- [ ] Servidor consegue logar imediatamente após aprovação
- [ ] Sidebar exibe apenas "Minha Área" (perfil Externo)
- [ ] Testar com usuária Raissa: re-aprovar ou criar novo cadastro para corrigir
```

---

## PARTE 9 — CORREÇÃO DA RAISSA

A Raissa foi aprovada mas ficou com dados incompletos. O RH deve:
1. Localizar o registro dela em AUTOCADASTRO_TOKEN (status = 'aprovado')
2. Verificar se FUNCIONARIO foi criado (provavelmente não, ou sem matrícula)
3. Se FUNCIONARIO existe mas sem matrícula: atualizar manualmente via script de correção
4. Se não existe: o RH pode regerar um link e pedir que ela preencha novamente,
   ou o admin insere manualmente via `FuncionariosView.vue`

**Script de diagnóstico** (para o admin rodar uma vez):
```sql
-- Verificar situação da Raissa
SELECT
    at.TOKEN_STATUS,
    at.FUNCIONARIO_ID,
    f.FUNCIONARIO_MATRICULA,
    p.PESSOA_NOME,
    p.PESSOA_CPF_NUMERO,
    u.USUARIO_LOGIN,
    u.USUARIO_ID
FROM AUTOCADASTRO_TOKEN at
LEFT JOIN FUNCIONARIO f ON f.FUNCIONARIO_ID = at.FUNCIONARIO_ID
LEFT JOIN PESSOA p ON p.PESSOA_ID = f.PESSOA_ID
LEFT JOIN USUARIO u ON u.USUARIO_ID = p.USUARIO_ID
WHERE at.TOKEN_STATUS = 'aprovado'
ORDER BY at.created_at DESC;
```

---

*AUTOCADASTRO_ANALISE.md | GENTE v3 | RR TECNOL | 16/03/2026*
*6 bugs críticos identificados no endpoint de aprovação*
*Especificação completa da correção — Modo A (recadastramento) + Modo B (novo)*
*Geração automática de matrícula sequencial por ano*
