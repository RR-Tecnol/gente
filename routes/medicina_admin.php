<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;

if (!Schema::hasTable('EXAME_OCUPACIONAL')) {
    Schema::create('EXAME_OCUPACIONAL', function (Blueprint $table) {
        $table->increments('EXAME_ID');
        $table->unsignedInteger('FUNCIONARIO_ID')->index();
        $table->string('EXAME_TIPO', 50)->default('Periódico');
        $table->string('EXAME_SUBTIPO', 100)->nullable();
        $table->date('EXAME_DATA_REALIZACAO');
        $table->date('EXAME_DATA_VENCIMENTO')->nullable();
        $table->string('EXAME_MEDICO', 150)->nullable();
        $table->boolean('EXAME_APTO')->default(true);
        $table->text('EXAME_OBS')->nullable();
        $table->timestamps();
    });
}

// Prefix inherits "api/v3/" from its main module registration on web.php
Route::prefix('medicina-admin')->group(function () {

    // Lista de Exames (ASOs)
    Route::get('/exames', function (Request $request) {
        try {
            $query = DB::table('EXAME_OCUPACIONAL as e')
                ->join('FUNCIONARIO as f', 'e.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID');
            
            if ($request->vencidos === 'true') {
                $query->whereNotNull('e.EXAME_DATA_VENCIMENTO')
                      ->where('e.EXAME_DATA_VENCIMENTO', '<', now()->toDateString());
            }

            if ($request->busca) {
                $termo = trim($request->busca);
                $query->where(function($q) use ($termo) {
                    $q->where('p.PESSOA_NOME', 'like', "%{$termo}%")
                      ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%{$termo}%");
                });
            }

            $exames = $query->select(
                'e.*',
                'p.PESSOA_NOME as FUNCIONARIO_NOME',
                'f.FUNCIONARIO_MATRICULA as FUNCIONARIO_MATRICULA'
            )->orderBy('e.EXAME_DATA_REALIZACAO', 'desc')->get();

            return response()->json($exames);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Registrar ou Atualizar Exame (ASO)
    Route::post('/exames', function (Request $request) {
        try {
            $data = $request->validate([
                'FUNCIONARIO_ID' => 'required|integer',
                'EXAME_TIPO' => 'required|string',
                'EXAME_SUBTIPO' => 'nullable|string',
                'EXAME_DATA_REALIZACAO' => 'required|date',
                'EXAME_DATA_VENCIMENTO' => 'nullable|date',
                'EXAME_MEDICO' => 'nullable|string',
                'EXAME_APTO' => 'required|boolean',
                'EXAME_OBS' => 'nullable|string'
            ]);

            $id = $request->input('EXAME_ID');

            if ($id) {
                $data['updated_at'] = now();
                DB::table('EXAME_OCUPACIONAL')->where('EXAME_ID', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $data['updated_at'] = now();
                $id = DB::table('EXAME_OCUPACIONAL')->insertGetId($data);
            }

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Deletar Exame
    Route::delete('/exames/{id}', function ($id) {
        try {
            DB::table('EXAME_OCUPACIONAL')->where('EXAME_ID', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Agendamentos (Vindos do App do Servidor em AFASTAMENTO)
    Route::get('/agendamentos', function () {
        try {
            $agendamentos = DB::table('AFASTAMENTO as a')
                ->join('FUNCIONARIO as f', 'a.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID')
                ->where('a.AFASTAMENTO_CID', 'like', 'Z1%')
                ->where('a.AFASTAMENTO_STATUS', 'agendado')
                ->select(
                    'a.*',
                    'p.PESSOA_NOME as FUNCIONARIO_NOME',
                    'f.FUNCIONARIO_MATRICULA as FUNCIONARIO_MATRICULA'
                )->orderBy('a.AFASTAMENTO_DATA_INICIO', 'asc')->get();
            return response()->json($agendamentos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Aprovar / Dispensar Agendamento Status (efetivar, cancelar)
    Route::post('/agendamentos/{id}/status', function (Request $request, $id) {
        try {
            $status = $request->input('status'); // 'aprovado', 'cancelado'
            DB::table('AFASTAMENTO')->where('AFASTAMENTO_ID', $id)->update(['AFASTAMENTO_STATUS' => $status, 'updated_at' => now()]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Buscar Servidores AutoComplete (Fallback modular para não depender de rotas globais)
    Route::get('/servidores', function(Request $request) {
        try {
            $termo = $request->query('busca');
            if(!$termo || strlen($termo) < 3) return response()->json([]);
            
            $lista = DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID')
                ->where('p.PESSOA_NOME', 'like', "%{$termo}%")
                ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%{$termo}%")
                ->select('f.FUNCIONARIO_ID', 'f.FUNCIONARIO_MATRICULA', 'p.PESSOA_NOME')
                ->limit(20)
                ->get();
                
            return response()->json($lista);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // KPIs Gerais para o Dashboard
    Route::get('/kpis', function () {
        try {
            $hoje = date('Y-m-d');
            $avencer = date('Y-m-d', strtotime('+30 days'));

            $query = DB::table('EXAME_OCUPACIONAL');
            
            $totalExames = DB::table('EXAME_OCUPACIONAL')->count();
            $vencidos = DB::table('EXAME_OCUPACIONAL')->whereNotNull('EXAME_DATA_VENCIMENTO')->where('EXAME_DATA_VENCIMENTO', '<', $hoje)->count();
            $proximos = DB::table('EXAME_OCUPACIONAL')->whereNotNull('EXAME_DATA_VENCIMENTO')->whereBetween('EXAME_DATA_VENCIMENTO', [$hoje, $avencer])->count();
            $agendados = DB::table('AFASTAMENTO')->where('AFASTAMENTO_CID', 'like', 'Z1%')->where('AFASTAMENTO_STATUS', 'agendado')->count();

            return response()->json([
                'total' => $totalExames,
                'vencidos' => $vencidos,
                'proximos' => $proximos,
                'agendados' => $agendados
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

});
