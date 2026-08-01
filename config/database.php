<?php
/**
 * Conexão central com o banco (PDO).
 * Nunca faça "new PDO" espalhado pelo código: sempre chame get_pdo().
 *
 * Em produção, mova host/usuario/senha para variáveis de ambiente
 * (getenv()) em vez de deixar hardcoded aqui.
 */

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host   = 'localhost';
    $port   = 3306;
    $dbname = 'db_2td';
    $user   = 'root';
    $pass   = '';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements de verdade no servidor
        ]);
    } catch (PDOException $e) {
        // Nunca exponha $e->getMessage() (pode revelar usuário/senha/host) para o usuário final.
        error_log('Falha de conexão com o banco: ' . $e->getMessage());
        http_response_code(500);
        die('Erro interno. Tente novamente mais tarde.');
    }

    return $pdo;
}
