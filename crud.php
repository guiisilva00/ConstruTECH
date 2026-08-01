<?php
/**
 * Helpers CRUD genéricos.
 *
 * IMPORTANTE sobre segurança:
 * - $table nunca pode vir de input do usuário: é sempre validado contra
 *   uma whitelist fixa de tabelas conhecidas do schema.
 * - $where é usado apenas para condições fixas escritas no código
 *   (ex.: "id = 5"), nunca para concatenar dado vindo de $_GET/$_POST.
 *   Para filtrar por dado do usuário, use sempre uma função específica
 *   com parâmetros bindados (veja exemplos em estoque.php/produtos.php).
 */

require_once __DIR__ . '/config/database.php';

const TABELAS_PERMITIDAS = [
    'usuarios', 'categorias', 'produtos', 'movimentacoes_estoque',
    'obras', 'obras_materiais', 'log_acessos',
];

function validar_tabela(string $table): string
{
    if (!in_array($table, TABELAS_PERMITIDAS, true)) {
        throw new InvalidArgumentException("Tabela não permitida: $table");
    }
    return $table;
}

function create(PDO $pdo, string $table, array $data): string
{
    $table = validar_tabela($table);
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $stmt = $pdo->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return $pdo->lastInsertId();
}

function readAll(PDO $pdo, string $table, ?string $where = null, array $bindings = []): array
{
    $table = validar_tabela($table);
    $sql = "SELECT * FROM `$table`";
    if ($where) {
        $sql .= " WHERE $where";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    return $stmt->fetchAll();
}

function read(PDO $pdo, string $table, ?string $where = null, array $bindings = []): ?array
{
    $table = validar_tabela($table);
    $sql = "SELECT * FROM `$table`";
    if ($where) {
        $sql .= " WHERE $where";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $resultado = $stmt->fetch();
    return $resultado ?: null;
}

function update(PDO $pdo, string $table, array $data, string $where, array $whereBindings = []): int
{
    $table = validar_tabela($table);
    $set = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));

    $stmt = $pdo->prepare("UPDATE `$table` SET $set WHERE $where");
    $stmt->execute([...array_values($data), ...$whereBindings]);
    return $stmt->rowCount();
}

function delete(PDO $pdo, string $table, string $where, array $whereBindings = []): bool
{
    $table = validar_tabela($table);
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE $where");
    return $stmt->execute($whereBindings);
}
