<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\HolidayCalendarService;

if (!function_exists('ensureCalendarOverridesTable')) {
    /**
     * DDL canónico: database/migrations/*_create_calendar_overrides_table.php
     */
    function ensureCalendarOverridesTable(): void
    {
        if (Schema::hasTable('calendar_overrides')) {
            return;
        }
        throw new \RuntimeException(
            'Tabela calendar_overrides inexistente. Execute: php artisan migrate (migration create_calendar_overrides_table).'
        );
    }
}

Route::get('/feriados/manager', function (Request $request) {
    try {
        ensureCalendarOverridesTable();
        /** @var HolidayCalendarService $holidayService */
        $holidayService = app(HolidayCalendarService::class);
        $ano = (int) ($request->ano ?? now()->year);
        $inicio = sprintf('%04d-01-01', $ano);
        $fim = sprintf('%04d-12-31', $ano);

        $base = collect($holidayService->getBaseHolidaysForYear($ano))
            ->map(fn($f, $idx) => [
                'id' => 'base-' . ($idx + 1),
                'source' => 'base',
                'holiday_name' => $f['title'],
                'date' => $f['date'],
                'scope' => $f['scope'],
                'type' => $f['type'],
                'is_point_facultative' => (bool) $f['is_point_facultative'],
                'impacts_bank_of_hours' => true,
                'pay_multiplier' => (float) $f['pay_multiplier'],
                'target_id' => null,
                'note' => null,
            ])
            ->filter(fn($f) => $f['date'] >= $inicio && $f['date'] <= $fim)
            ->values();

        $overrides = DB::table('calendar_overrides')
            ->whereBetween('date', [$inicio, $fim])
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'id' => 'ovr-' . (int) $h->id,
                'source' => 'override',
                'holiday_name' => (string) $h->title,
                'date' => (string) $h->date,
                'scope' => (string) ($h->scope ?? 'global'),
                'type' => (string) ($h->type ?? 'holiday'),
                'is_point_facultative' => (bool) ($h->is_point_facultative ?? false),
                'impacts_bank_of_hours' => (bool) ($h->impacts_bank_of_hours ?? true),
                'pay_multiplier' => (float) ($h->pay_multiplier ?? 2.0),
                'target_id' => $h->target_id,
                'note' => $h->note,
            ]);

        return response()->json([
            'ano' => $ano,
            'feriados' => $base->concat($overrides)->sortBy('date')->values(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['feriados' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::post('/feriados/manager', function (Request $request) {
    try {
        ensureCalendarOverridesTable();
        $dados = $request->validate([
            'holiday_name' => ['required', 'string', 'max:180'],
            'date' => ['required', 'date'],
            'scope' => ['required', 'in:global,sector,user'],
            'type' => ['nullable', 'in:holiday,sector_off,individual_off'],
            'is_point_facultative' => ['nullable', 'boolean'],
            'impacts_bank_of_hours' => ['nullable', 'boolean'],
            'target_id' => ['nullable', 'integer'],
            'pay_multiplier' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $id = DB::table('calendar_overrides')->insertGetId([
            'title' => $dados['holiday_name'],
            'date' => $dados['date'],
            'scope' => $dados['scope'],
            'type' => $dados['type'] ?? ($dados['scope'] === 'sector' ? 'sector_off' : ($dados['scope'] === 'user' ? 'individual_off' : 'holiday')),
            'is_point_facultative' => (bool) ($dados['is_point_facultative'] ?? false),
            'impacts_bank_of_hours' => (bool) ($dados['impacts_bank_of_hours'] ?? true),
            'target_id' => $dados['target_id'] ?? null,
            'pay_multiplier' => $dados['pay_multiplier'] ?? 2.0,
            'note' => $dados['note'] ?? null,
            'created_by' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::put('/feriados/manager/{id}', function (int $id, Request $request) {
    try {
        ensureCalendarOverridesTable();
        $dados = $request->validate([
            'holiday_name' => ['required', 'string', 'max:180'],
            'date' => ['required', 'date'],
            'scope' => ['required', 'in:global,sector,user'],
            'type' => ['nullable', 'in:holiday,sector_off,individual_off'],
            'is_point_facultative' => ['nullable', 'boolean'],
            'impacts_bank_of_hours' => ['nullable', 'boolean'],
            'target_id' => ['nullable', 'integer'],
            'pay_multiplier' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::table('calendar_overrides')->where('id', $id)->update([
            'title' => $dados['holiday_name'],
            'date' => $dados['date'],
            'scope' => $dados['scope'],
            'type' => $dados['type'] ?? ($dados['scope'] === 'sector' ? 'sector_off' : ($dados['scope'] === 'user' ? 'individual_off' : 'holiday')),
            'is_point_facultative' => (bool) ($dados['is_point_facultative'] ?? false),
            'impacts_bank_of_hours' => (bool) ($dados['impacts_bank_of_hours'] ?? true),
            'target_id' => $dados['target_id'] ?? null,
            'pay_multiplier' => $dados['pay_multiplier'] ?? 2.0,
            'note' => $dados['note'] ?? null,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/feriados/manager/{id}', function (int $id) {
    try {
        ensureCalendarOverridesTable();
        DB::table('calendar_overrides')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Compatibilidade com tela antiga
Route::get('/feriados', function () {
    try {
        return response()->json(['feriados' => DB::table('FERIADO')->orderBy('FERIADO_DATA')->get()->map(fn($f)=>[
            'id'=>$f->FERIADO_ID,'nome'=>$f->FERIADO_NOME??$f->FERIADO_DESCRICAO??'','data'=>$f->FERIADO_DATA,'tipo'=>$f->FERIADO_TIPO??'N','recorrente'=>(bool)($f->FERIADO_RECORRENTE??false)
        ])]);
    } catch (\Throwable $e) {
        return response()->json(['feriados'=>[]]);
    }
});
Route::post('/feriados', function (Request $request) {
    try {
        $id = DB::table('FERIADO')->insertGetId([
            'FERIADO_NOME'=>$request->FERIADO_NOME,
            'FERIADO_DATA'=>$request->FERIADO_DATA,
            'FERIADO_TIPO'=>$request->FERIADO_TIPO??'N',
            'FERIADO_RECORRENTE'=>$request->FERIADO_RECORRENTE?1:0
        ]);
        return response()->json(['ok'=>true,'id'=>$id],201);
    } catch (\Throwable $e) {
        return response()->json(['erro'=>$e->getMessage()],422);
    }
});
Route::put('/feriados/{id}', function (int $id, Request $request) {
    try {
        DB::table('FERIADO')->where('FERIADO_ID',$id)->update([
            'FERIADO_NOME'=>$request->FERIADO_NOME,
            'FERIADO_DATA'=>$request->FERIADO_DATA,
            'FERIADO_TIPO'=>$request->FERIADO_TIPO??'N',
            'FERIADO_RECORRENTE'=>$request->FERIADO_RECORRENTE?1:0
        ]);
        return response()->json(['ok'=>true]);
    } catch (\Throwable $e) {
        return response()->json(['erro'=>$e->getMessage()],500);
    }
});
Route::delete('/feriados/{id}', function (int $id) {
    try {
        DB::table('FERIADO')->where('FERIADO_ID',$id)->delete();
        return response()->json(['ok'=>true]);
    } catch (\Throwable $e) {
        return response()->json(['erro'=>$e->getMessage()],500);
    }
});
