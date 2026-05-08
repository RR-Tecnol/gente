<?php

/**
 * Teste isolado de conectividade SQL Server (PDO sqlsrv), sem bootstrap Laravel.
 * Uso: php tests/db_test.php
 *      DB_HOST=127.0.0.1 php tests/db_test.php   # forçar host na linha de comando
 *
 * Lê variáveis de gente/.env (mesmas chaves que o Laravel).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root.'/.env';

function loadDotEnv(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "Arquivo não encontrado: {$path}\n");

        exit(2);
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $q = $v[0];
            if (substr($v, -1) === $q) {
                $v = substr($v, 1, -1);
            }
        }
        $out[$k] = $v;
    }

    return $out;
}

function envOr(array $e, string $key, string $default): string
{
    return isset($e[$key]) && $e[$key] !== '' ? (string) $e[$key] : $default;
}

$e = loadDotEnv($envPath);

$host = getenv('DB_HOST') !== false ? (string) getenv('DB_HOST') : envOr($e, 'DB_HOST', '127.0.0.1');
$port = envOr($e, 'DB_PORT', '1433');
$database = envOr($e, 'DB_DATABASE', 'gente');
$username = envOr($e, 'DB_USERNAME', 'sa');
$password = envOr($e, 'DB_PASSWORD', '');
$encrypt = envOr($e, 'DB_ENCRYPT', 'no');
$loginTimeout = (int) envOr($e, 'DB_LOGIN_TIMEOUT', '8');
$trust = filter_var(
    envOr($e, 'DB_TRUST_SERVER_CERT', '1'),
    FILTER_VALIDATE_BOOLEAN,
    ['flags' => FILTER_NULL_ON_FAILURE]
);
if ($trust === null) {
    $trust = true;
}

// Aligne ao config/database.php (sqlsrv): trust_server_certificate => true
$trustArg = $trust ? '1' : '0';

$serverPart = $host.','.$port;
$dsn = 'sqlsrv:'.implode(';', [
    'Server='.$serverPart,
    'Database='.$database,
    'Encrypt='.$encrypt,
    'TrustServerCertificate='.$trustArg,
    'LoginTimeout='.(string) $loginTimeout,
]);

echo "DSN (sem senha): sqlsrv:Server={$serverPart};Database=...;Encrypt={$encrypt};TrustServerCertificate={$trustArg};LoginTimeout={$loginTimeout}\n";
echo "User: {$username}\n\n";

if (! in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "Extensão PDO sqlsrv não carregada. Instale php-pdo_sqlsrv / Microsoft ODBC Driver.\n");

    exit(3);
}

$t0 = microtime(true);
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $st = $pdo->query('SELECT 1 AS ok');
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
    $ms = (int) round((microtime(true) - $t0) * 1000);
    echo "OK — conexão PDO estabelecida em {$ms} ms.\n";
    echo 'Teste: '.json_encode($row, JSON_UNESCAPED_UNICODE)."\n";

    exit(0);
} catch (Throwable $ex) {
    $ms = (int) round((microtime(true) - $t0) * 1000);
    fwrite(STDERR, "FALHA após {$ms} ms: ".$ex->getMessage()."\n");
    fwrite(STDERR, "\nDica: no host (fora do Docker), use DB_HOST=127.0.0.1 se a porta 1433 estiver publicada no compose.\n");

    exit(1);
}
