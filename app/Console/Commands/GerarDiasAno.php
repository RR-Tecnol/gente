<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GerarDiasAno extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dias:gerar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera os dias do próximo ano com base no último ano presente na tabela DIASANO';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Verifica a última data cadastrada
        $ultimaData = DB::table('DIASANO')->max('DATA');

        if (!$ultimaData) {
            $this->error('Tabela DIASANO está vazia.');
            return 1;
        }

        $ultimoAno = Carbon::parse($ultimaData)->year;
        $proximoAno = $ultimoAno + 1;

        $this->info("Última data encontrada: $ultimaData. Gerando dias para o ano $proximoAno...");

        $dataInicio = Carbon::createFromDate($proximoAno, 1, 1);
        $dataFim = Carbon::createFromDate($proximoAno, 12, 31);

        $datas = [];

        while ($dataInicio <= $dataFim) {
            $datas[] = ['DATA' => $dataInicio->format('Y-m-d')];
            $dataInicio->addDay();
        }

        DB::table('DIASANO')->insert($datas);

        $this->info("Foram inseridas " . count($datas) . " datas para o ano de $proximoAno.");
        return 0;
    }
}
