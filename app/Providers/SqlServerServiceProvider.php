<?php

namespace App\Providers;

use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Support\ServiceProvider;

/**
 * Conector SQL Server customizado que força TrustServerCertificate e Encrypt
 * para compatibilidade com ODBC Driver 18 em ambiente Docker sem TLS.
 */
class TrustSqlServerConnector extends SqlServerConnector
{
    /**
     * Sobrescreve getOptions para injetar TrustServerCertificate e Encrypt
     * antes de o PDO ser criado.
     */
    public function createConnection($dsn, array $config, array $options)
    {
        // Injeta as opções de TLS diretamente no dsn estendido
        if (strpos($dsn, 'TrustServerCertificate') === false) {
            $dsn .= ';TrustServerCertificate=1';
        }
        if (strpos($dsn, 'Encrypt') === false) {
            $dsn .= ';Encrypt=0';
        }

        return parent::createConnection($dsn, $config, $options);
    }
}

class SqlServerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->resolving('db.connector.sqlsrv', function () {
            return new TrustSqlServerConnector();
        });
    }

    public function register(): void
    {
        $this->app->bind('db.connector.sqlsrv', TrustSqlServerConnector::class);
    }
}
