<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegistroPonto;
use App\Models\Funcionario;
use App\Models\Pessoa;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PontoEletronicoController extends Controller
{
    /**
     * Retorna a view principal do Módulo de Ponto Eletrônico.
     */
    public function view()
    {
        return view('ponto.index');
    }

    /**
     * Lista os registros de ponto do período com base no Funcionário.
     */
    public function listar(Request $request)
    {
        $query = RegistroPonto::with(['funcionario.pessoa'])
            ->orderBy('REGISTRO_DATA_HORA', 'desc');

        if ($request->filled('DATA_INICIO')) {
            $query->whereDate('REGISTRO_DATA_HORA', '>=', $request->DATA_INICIO);
        }
        if ($request->filled('DATA_FIM')) {
            $query->whereDate('REGISTRO_DATA_HORA', '<=', $request->DATA_FIM);
        }

        $registros = $query->paginate(50);

        return response()->json([
            'cod' => 1,
            'msg' => 'Registros listados com sucesso',
            'retorno' => $registros
        ]);
    }

    /**
     * Importador de Arquivo Fonte de Dados (AFD) Padrão Inmetro.
     * Tipo 1: Cabeçalho
     * Tipo 2: Empresa
     * Tipo 3: Registro de Ponto (NSR: 0-9, Tipo: 9, Data: 10-17, Hora: 18-21, PIS: 22-33)
     */
    public function importarAfd(Request $request)
    {
        $request->validate([
            'arquivo_afd' => 'required|file|mimes:txt'
        ]);

        $file = $request->file('arquivo_afd');
        $conteudo = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $registrosAdicionados = 0;
        $erros = [];

        DB::beginTransaction();

        try {
            foreach ($conteudo as $index => $linha) {
                // No formato AFD, o tipo do registro é o 10º caractere (índice 9)
                // Layout Exemplo Tipo 3: 0000000003180520240800123456789012 (34 posições)
                // NSR (9 pos), Tipo (1 pos -> '3'), Data (8 pos), Hora (4 pos), PIS (12 pos)

                $tipoRegistro = substr($linha, 9, 1);

                if ($tipoRegistro === '3') {
                    $nsr = substr($linha, 0, 9);
                    $dataStr = substr($linha, 10, 8); // ddmmyyyy
                    $horaStr = substr($linha, 18, 4); // hhmm
                    $pis = substr($linha, 22, 12);

                    if (strlen($linha) < 34) {
                        $erros[] = "Linha " . ($index + 1) . " inválida (Layout corrompido).";
                        continue;
                    }

                    // Verifica se já inseriu este NSR
                    $existe = RegistroPonto::where('REGISTRO_NSR', $nsr)->exists();
                    if ($existe) {
                        continue; // Evita duplicidade do mesmo relógio
                    }

                    // Busca funcionário pelo PIS
                    // 1. Busca CPF pelo PIS na tabela Pessoa? O sistema pode ter PIS no Funcionario ou Pessoa.
                    // Ajuste: Depende de onde o PIS (Ou PASEP/NIT) fica. No GENTE, PIS costuma ficar em PESSOA_PIS_PASEP ou FUNCIONARIO_PIS
                    $pessoaDb = Pessoa::where('PESSOA_PIS_PASEP', $pis)
                        ->orWhere('PESSOA_CPF_NUMERO', $pis) // Tem AFD que exporta CPF no lugar do PIS
                        ->first();

                    if (!$pessoaDb) {
                        // Limpa zeros à esquerda e tenta de novo
                        $pisLimpo = ltrim($pis, '0');
                        $pessoaDb = Pessoa::where('PESSOA_PIS_PASEP', $pisLimpo)
                            ->orWhere('PESSOA_CPF_NUMERO', $pisLimpo)
                            ->first();
                    }

                    if (!$pessoaDb) {
                        $erros[] = "PIS/CPF {$pis} na linha " . ($index + 1) . " não encontrado no cadastro de Pessoas.";
                        continue;
                    }

                    $funcionarioDb = Funcionario::where('PESSOA_ID', $pessoaDb->PESSOA_ID)
                        ->where('FUNCIONARIO_ATIVO', 1)->first();

                    if (!$funcionarioDb) {
                        $erros[] = "Funcionário inativo ou não existe para a pessoa (PIS {$pis}).";
                        continue;
                    }

                    $dataHoraParsed = Carbon::createFromFormat('dmYHi', $dataStr . $horaStr);

                    RegistroPonto::create([
                        'FUNCIONARIO_ID' => $funcionarioDb->FUNCIONARIO_ID,
                        'TERMINAL_ID' => null, // Opcional: extrair do Header Tipo 1
                        'REGISTRO_DATA_HORA' => $dataHoraParsed,
                        'REGISTRO_TIPO' => 'ENTRADA', // Na importação em lote bruta (Tipo 3), o Apurador de ponto é quem classifica ENTRADA/SAIDA cronologicamente depoís.
                        'REGISTRO_ORIGEM' => 'REP_P',
                        'REGISTRO_NSR' => $nsr,
                        'REGISTRO_OBSERVACAO' => 'Importado via AFD',
                    ]);

                    $registrosAdicionados++;
                }
            }

            DB::commit();

            return response()->json([
                'cod' => 1,
                'msg' => "$registrosAdicionados batida(s) importada(s) com sucesso.",
                'erros' => $erros
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'cod' => 0,
                'msg' => "Falha ao processar arquivo: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe a interface de Quiosque PWA baseada no Token do Terminal.
     */
    public function quiosqueView($token)
    {
        $terminal = \App\Models\TerminalPonto::with('unidade')->where('TERMINAL_TOKEN', $token)->first();

        if (!$terminal || !$terminal->TERMINAL_ATIVO) {
            return response("Terminal Invalido ou Inativo. Token fornecido: " . htmlentities($token), 403);
        }

        // View blade super limpa (isolada do resto do painel admin)
        return view('ponto.quiosque', compact('terminal'));
    }

    /**
     * Registra o ponto (CPF e Senha) via interface Quiosque PWA.
     */
    public function registrarQuiosque(Request $request, $token)
    {
        $terminal = \App\Models\TerminalPonto::where('TERMINAL_TOKEN', $token)->first();
        if (!$terminal || !$terminal->TERMINAL_ATIVO) {
            return response()->json(['cod' => 0, 'msg' => 'Terminal Inativo ou Token Revogado.'], 403);
        }

        $request->validate([
            'cpf' => 'required|string',
            'senha' => 'required|string'
        ]);

        $cpfLimpo = preg_replace('/[^0-9]/', '', $request->cpf);

        // 1. Tentar Autenticação do Usuário no DB
        $usuario = \App\Models\Usuario::where('USUARIO_LOGIN', $cpfLimpo)
            ->where('USUARIO_ATIVO', 1)->first();

        if (!$usuario || !\Illuminate\Support\Facades\Hash::check($request->senha, $usuario->USUARIO_SENHA)) {
            // Se o bcrypt falhar, checar md5 pra legados igual já fazemos no login geral
            if (!$usuario || md5($request->senha) !== $usuario->USUARIO_SENHA) {
                if ($usuario && $request->senha === $usuario->USUARIO_SENHA) {
                    // Hacky plain-text check for admin if not fixed yet, but should be blocked on production real users
                } else {
                    return response()->json(['cod' => 0, 'msg' => 'Credenciais incorretas.'], 401);
                }
            }
        }

        // 2. Localiza o Funcionario associado à pessoa do Usuário
        // A tabela USUARIO tem PESSOA_ID ou a gente cruza pelo CPF na PESSOA
        $pessoa = \App\Models\Pessoa::where('PESSOA_CPF_NUMERO', $cpfLimpo)->first();
        if (!$pessoa) {
            return response()->json(['cod' => 0, 'msg' => 'Pessoa não encontrada no sistema com este CPF.'], 404);
        }

        $funcionario = \App\Models\Funcionario::where('PESSOA_ID', $pessoa->PESSOA_ID)
            ->where('FUNCIONARIO_ATIVO', 1)->first();

        if (!$funcionario) {
            return response()->json(['cod' => 0, 'msg' => 'Nenhum contrato de trabalho ativo localizado.'], 404);
        }

        // 3. Cadastra o Ponto
        DB::beginTransaction();
        try {
            $registro = RegistroPonto::create([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'TERMINAL_ID' => $terminal->TERMINAL_ID,
                'REGISTRO_DATA_HORA' => Carbon::now(),
                'REGISTRO_TIPO' => 'ENTRADA', // Avaliação posterior do cronograma
                'REGISTRO_ORIGEM' => 'REP_A_SENHA',
                'REGISTRO_NSR' => null,
                'REGISTRO_OBSERVACAO' => 'Registrado via Quiosque Web',
            ]);

            DB::commit();

            return response()->json([
                'cod' => 1,
                'msg' => 'Ponto Registrado com Sucesso!',
                'retorno' => [
                    'funcionario_nome' => $pessoa->PESSOA_NOME,
                    'hora' => $registro->REGISTRO_DATA_HORA->format('d/m/Y H:i:s'),
                    'protocolo' => str_pad($registro->REGISTRO_PONTO_ID, 6, "0", STR_PAD_LEFT)
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['cod' => 0, 'msg' => 'Erro interno ao salvar marcação: ' . $e->getMessage()], 500);
        }
    }

    /**
     * MÉTODOS DE SUPORTE ÀS ABAS DO PONTO VIEW
     */

    public function salvarManual(Request $request)
    {
        $request->validate([
            'FUNCIONARIO_ID' => 'required|integer',
            'REGISTRO_DATA_HORA' => 'required|date',
            'REGISTRO_TIPO' => 'required|string',
        ]);

        $ponto = RegistroPonto::create([
            'FUNCIONARIO_ID' => $request->FUNCIONARIO_ID,
            'REGISTRO_DATA_HORA' => $request->REGISTRO_DATA_HORA,
            'REGISTRO_TIPO' => $request->REGISTRO_TIPO,
            'REGISTRO_ORIGEM' => 'MANUAL',
            'REGISTRO_OBSERVACAO' => $request->REGISTRO_OBSERVACAO,
        ]);

        return response()->json(['cod' => 1, 'msg' => 'Registro inserido manualmente.', 'retorno' => $ponto]);
    }

    public function excluirManual($id)
    {
        $registro = RegistroPonto::findOrFail($id);
        if ($registro->REGISTRO_ORIGEM === 'MANUAL') {
            $registro->delete();
            return response()->json(['cod' => 1, 'msg' => 'Registro manual excluído.']);
        }
        return response()->json(['cod' => 0, 'msg' => 'Apenas registros manuais podem ser excluídos.'], 403);
    }

    public function listarApuracao(Request $request)
    {
        $query = \App\Models\ApuracaoPonto::with('funcionario.pessoa');
        if ($request->filled('APURACAO_STATUS')) {
            $query->where('APURACAO_STATUS', $request->APURACAO_STATUS);
        }
        $apuracoes = $query->paginate(50);
        return response()->json(['cod' => 1, 'retorno' => $apuracoes]);
    }

    public function listarJustificativas()
    {
        // Retorna justificativas de status PENDENTE (se houver coluna) ou todas
        $justificativas = \App\Models\JustificativaPonto::with([
            'apuracao.funcionario.pessoa'
        ])->where('JUSTIFICATIVA_STATUS', 'PENDENTE')
            ->orderBy('JUSTIFICATIVA_DATA', 'asc')
            ->get();

        return response()->json(['cod' => 1, 'retorno' => $justificativas]);
    }

    public function aprovarJustificativa($id)
    {
        $justificativa = \App\Models\JustificativaPonto::findOrFail($id);
        $justificativa->update(['JUSTIFICATIVA_STATUS' => 'APROVADO']);

        // Aqui também atualizaríamos a Apuração relacionada
        if ($justificativa->APURACAO_PONTO_ID) {
            \App\Models\ApuracaoPonto::where('APURACAO_PONTO_ID', $justificativa->APURACAO_PONTO_ID)
                ->update(['APURACAO_STATUS' => 'FECHADA']);
        }

        return response()->json(['cod' => 1, 'msg' => 'Aprovado']);
    }

    public function rejeitarJustificativa(Request $request, $id)
    {
        $obs = $request->input('obs', 'Rejeitado pelo Gestor.');
        $justificativa = \App\Models\JustificativaPonto::findOrFail($id);
        $justificativa->update([
            'JUSTIFICATIVA_STATUS' => 'REJEITADO',
            'JUSTIFICATIVA_OBSERVACAO' => $obs
        ]);
        return response()->json(['cod' => 1, 'msg' => 'Rejeitado']);
    }

    public function listarTerminais()
    {
        $terminais = \App\Models\TerminalPonto::with('unidade')->get();
        return response()->json(['cod' => 1, 'retorno' => $terminais]);
    }
}
