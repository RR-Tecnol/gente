<?php

namespace App\Database;

use Illuminate\Database\Connectors\SqlServerConnector;

/**
 * Conector SQL Server customizado para ODBC Driver 18+ em ambiente Docker sem TLS.
 *
 * O ODBC Driver 18+ exige Encrypt=yes por padrão.
 * O pdo_sqlsrv ACEITA TrustServerCertificate e Encrypt diretamente no DSN string.
 * Ex: sqlsrv:Server=...;TrustServerCertificate=1;Encrypt=0
 *
 * Testado e confirmado: pdo_sqlsrv suporta essas opções no DSN.
 */
class TrustSqlServerConnector extends SqlServerConnector
{
    /**
     * Sobrescreve getDsn para adicionar TrustServerCertificate=1 e Encrypt=0
     * ao DSN string gerado pelo SqlServerConnector do Laravel.
     *
     * @see Illuminate\Database\Connectors\SqlServerConnector::getDsn()
     */
    protected function getDsn(array $config): string
    {
        // Obtém o DSN padrão do Laravel (ex: sqlsrv:Server=host,port;Database=db)
        $dsn = parent::getDsn($config);

        // Adiciona as opções TLS apenas se não estiverem presentes
        if (!str_contains($dsn, 'TrustServerCertificate')) {
            $dsn .= ';TrustServerCertificate=1';
        }
        if (!str_contains($dsn, 'Encrypt=')) {
            $dsn .= ';Encrypt=0';
        }

        return $dsn;
    }
}
