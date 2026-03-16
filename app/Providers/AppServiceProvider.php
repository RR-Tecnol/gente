<?php

namespace App\Providers;

use App\Database\TrustSqlServerConnector;
use App\Observers\Auditables;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Intercepta a criação do DatabaseManager para registrar nosso driver customizado.
        // Necessário para ODBC Driver 18+ sem TLS em ambiente Docker de desenvolvimento.
        $this->app->resolving(DatabaseManager::class, function (DatabaseManager $db) {
            $db->extend('sqlsrv', function (array $config, string $name) {
                $connector = new TrustSqlServerConnector();
                $connection = $connector->connect($config);

                return new SqlServerConnection(
                    $connection,
                    $config['database'],
                    $config['prefix'] ?? '',
                    $config
                );
            });
        });
    }

    public function boot()
    {
        $versao = "2.4.4";
        Config::set(['versao' => $versao]);
        $caracteresRemocao = ["/[^0-9]/"];
        Config::set(['vcp' => $versao]);
        Config::set(['vsp' => preg_replace($caracteresRemocao, "", $versao)]);
        Config::set(['APP_NAME' => 'SISGEP']);
        Config::set(['APP_DESCRICAO' => 'Sistema de Gestao de Pessoal']);

        Auditables::register();
    }
}
