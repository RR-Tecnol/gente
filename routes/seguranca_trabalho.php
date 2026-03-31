<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;

if (!Schema::hasTable('EPI_REGISTRO')) {
    Schema::create('EPI_REGISTRO', function (Blueprint $table) {
        $table->increments('EPI_ID');
        $table->unsignedInteger('FUNCIONARIO_ID')->index();
        $table->string('EPI_NOME', 200);   
        $table->string('EPI_OBS', 300)->nullable();
        $table->string('EPI_CA', 30)->nullable();
        $table->integer('EPI_QUANTIDADE')->default(1);
        $table->boolean('EPI_ENTREGUE')->default(false); // Só vira true quando o SESMT/Almoxarifado aprova e entrega
        $table->date('EPI_DATA_VENCIMENTO')->nullable();
        $table->timestamps();
    });
}
if (!Schema::hasTable('ACIDENTE_TRABALHO')) {
    Schema::create('ACIDENTE_TRABALHO', function (Blueprint $table) {
        $table->increments('ACIDENTE_ID');
        $table->unsignedInteger('FUNCIONARIO_ID')->index()->nullable(); // Pode ser nulo se não achou usuário logado na hora
        $table->string('ACIDENTE_TIPO', 30); // acidente | quase
        $table->string('ACIDENTE_LOCAL', 150); 
        $table->text('ACIDENTE_DESCRICAO')->nullable();
        $table->string('ACIDENTE_CAT', 50)->nullable();
        $table->boolean('ACIDENTE_CLOSED')->default(false);
        $table->timestamps();
    });
}
if (!Schema::hasTable('LAUDO_SST')) {
    Schema::create('LAUDO_SST', function (Blueprint $table) {
        $table->increments('LAUDO_ID');
        $table->string('LAUDO_TIPO', 50); // LTCAT, PPRA, PCMSO, PGR
        $table->string('LAUDO_LOCAL', 150); // Setor aplicável
        $table->date('LAUDO_DATA_VALIDADE')->nullable();
        $table->string('LAUDO_STATUS', 20)->default('Vigente');
        $table->timestamps();
    });
}

// Prefix herda de "api/v3/" por ser chamado no web.php
Route::prefix('seguranca')->group(function () {

    // ==== Área do Servidor (SegurancaTrabalhoView.vue) ====
    Route::get('/epis', function (Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) return response()->json(['epis' => [], 'fallback' => true]);

            $epis = DB::table('EPI_REGISTRO')
                      ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                      ->where('EPI_ENTREGUE', true)
                      ->orderBy('created_at', 'desc')
                      ->get()->map(function($e) {
                          $vencido = false;
                          $aVencer = false;
                          if($e->EPI_DATA_VENCIMENTO) {
                              $diff = (strtotime($e->EPI_DATA_VENCIMENTO) - time()) / 86400;
                              if($diff < 0) $vencido = true;
                              else if($diff <= 30) $aVencer = true;
                          }
                          return [
                              'id' => $e->EPI_ID,
                              'ico' => strpos(strtolower($e->EPI_NOME), 'bota') !== false ? '🥾' : '🦺',
                              'nome' => $e->EPI_NOME,
                              'ca' => $e->EPI_CA ?? 'S/CA',
                              'validade' => $e->EPI_DATA_VENCIMENTO,
                              'quantidade' => $e->EPI_QUANTIDADE,
                              'vencido' => $vencido,
                              'aVencer' => $aVencer
                          ];
                      });
            return response()->json(['epis' => $epis, 'fallback' => count($epis) === 0]);
        } catch (\Exception $e) {
            return response()->json(['epis' => [], 'fallback' => true]);
        }
    });

    Route::post('/epis', function (Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            
            DB::table('EPI_REGISTRO')->insert([
                'FUNCIONARIO_ID' => $func ? $func->FUNCIONARIO_ID : 1, // Fallback demo se nulo
                'EPI_NOME' => $request->nome ?? 'EPI Padrão',
                'EPI_OBS' => $request->obs,
                'EPI_ENTREGUE' => false, // Solicitado, aguardando admin
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    Route::get('/incidentes', function (Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) return response()->json(['incidentes' => [], 'fallback' => true]);

            $incs = DB::table('ACIDENTE_TRABALHO')
                      ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                      ->orderBy('created_at', 'desc')
                      ->get()->map(function($i) {
                          return [
                              'id' => $i->ACIDENTE_ID,
                              'tipo' => $i->ACIDENTE_TIPO,
                              'data' => substr($i->created_at, 0, 10),
                              'descricao' => $i->ACIDENTE_DESCRICAO,
                              'local' => $i->ACIDENTE_LOCAL,
                              'cat' => $i->ACIDENTE_CAT,
                              'closed' => (bool)$i->ACIDENTE_CLOSED
                          ];
                      });
            return response()->json(['incidentes' => $incs, 'fallback' => count($incs) === 0]);
        } catch (\Exception $e) {
            return response()->json(['incidentes' => [], 'fallback' => true]);
        }
    });

    Route::post('/incidentes', function (Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            
            DB::table('ACIDENTE_TRABALHO')->insert([
                'FUNCIONARIO_ID' => $func ? $func->FUNCIONARIO_ID : 1,
                'ACIDENTE_TIPO' => $request->tipo,
                'ACIDENTE_LOCAL' => $request->local,
                'ACIDENTE_DESCRICAO' => $request->descricao,
                'ACIDENTE_CLOSED' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
});

Route::prefix('seguranca-admin')->group(function () {

    // ==== Área do Admin SESMT (SegurancaAdminView.vue) ====
    Route::get('/kpis', function () {
        try {
            return response()->json([
                'epis_pendentes' => DB::table('EPI_REGISTRO')->where('EPI_ENTREGUE', false)->count(),
                'incidentes_abertos' => DB::table('ACIDENTE_TRABALHO')->where('ACIDENTE_CLOSED', false)->count(),
                'laudos_vencidos' => DB::table('LAUDO_SST')->where('LAUDO_STATUS', 'Vencido')->orWhere(function($q){
                    $q->whereNotNull('LAUDO_DATA_VALIDADE')->where('LAUDO_DATA_VALIDADE', '<', date('Y-m-d'));
                })->count()
            ]);
        } catch (\Exception $e) {
            return response()->json(['epis_pendentes'=>0, 'incidentes_abertos'=>0, 'laudos_vencidos'=>0]);
        }
    });

    // Filtra incidentes para investigar/fechar ou emitir CAT
    Route::get('/incidentes', function (Request $request) {
        try {
            return response()->json(
                DB::table('ACIDENTE_TRABALHO as a')
                ->leftJoin('FUNCIONARIO as f', 'a.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID')
                ->select('a.*', 'p.PESSOA_NOME as FUNCIONARIO_NOME', 'f.FUNCIONARIO_MATRICULA')
                ->orderBy('a.ACIDENTE_CLOSED', 'asc')
                ->orderBy('a.created_at', 'desc')
                ->get()
            );
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    });

    Route::post('/incidentes/{id}/cat', function (Request $request, $id) {
        try {
            DB::table('ACIDENTE_TRABALHO')->where('ACIDENTE_ID', $id)->update([
                'ACIDENTE_CAT' => $request->cat_numero,
                'ACIDENTE_CLOSED' => $request->encerrar ? true : false,
                'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Filtra Solicitações de EPI
    Route::get('/epis/solicitacoes', function (Request $request) {
        try {
            return response()->json(
                DB::table('EPI_REGISTRO as e')
                ->join('FUNCIONARIO as f', 'e.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID')
                ->where('e.EPI_ENTREGUE', false)
                ->select('e.*', 'p.PESSOA_NOME as FUNCIONARIO_NOME', 'f.FUNCIONARIO_MATRICULA')
                ->orderBy('e.created_at', 'desc')
                ->get()
            );
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    });

    // Admin aprova e emite EPI
    Route::post('/epis/entregar', function (Request $request) {
        try {
            $id = $request->input('EPI_ID');
            DB::table('EPI_REGISTRO')->where('EPI_ID', $id)->update([
                'EPI_ENTREGUE' => true,
                'EPI_CA' => $request->input('EPI_CA'),
                'EPI_QUANTIDADE' => $request->input('EPI_QUANTIDADE') ?? 1,
                'EPI_DATA_VENCIMENTO' => $request->input('EPI_DATA_VENCIMENTO'),
                'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Laudos
    Route::get('/laudos', function () {
        try { return response()->json(DB::table('LAUDO_SST')->orderBy('LAUDO_DATA_VALIDADE', 'asc')->get()); } 
        catch (\Exception $e) { return response()->json([], 500); }
    });
    
    Route::post('/laudos', function (Request $request) {
        try {
            DB::table('LAUDO_SST')->insert([
                'LAUDO_TIPO' => $request->tipo,
                'LAUDO_LOCAL' => $request->local,
                'LAUDO_DATA_VALIDADE' => $request->validade,
                'LAUDO_STATUS' => 'Vigente',
                'created_at' => now(), 'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) { return response()->json(['error' => $e->getMessage()], 500); }
    });
    Route::delete('/laudos/{id}', function ($id) {
        try { DB::table('LAUDO_SST')->where('LAUDO_ID', $id)->delete(); return response()->json(['success' => true]); } 
        catch (\Exception $e) { return response()->json(['error' => $e->getMessage()], 500); }
    });
});
