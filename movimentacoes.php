<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$pdo = get_pdo();

$sql = 'SELECT m.tipo, m.quantidade_anterior, m.quantidade_nova, m.observacao, m.criado_em,
               p.nome AS produto_nome, u.nome AS usuario_nome
        FROM movimentacoes_estoque m
        JOIN produtos p ON p.id = m.produto_id
        LEFT JOIN usuarios u ON u.id = m.usuario_id
        ORDER BY m.criado_em DESC
        LIMIT 200';
$movimentacoes = $pdo->query($sql)->fetchAll();

$rotulos = [
    'entrada' => 'Ajuste de quantidade',
    'saida'   => 'Ajuste de quantidade',
    'ajuste'  => 'Ajuste manual',
    'remocao' => 'Remoção de registro',
];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Auditoria</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main class="app-shell container" id="conteudo-principal">
        <section class="page-intro reveal">
            <div>
                <p class="eyebrow">Auditoria</p>
                <h1>Histórico de alterações</h1>
                <p>Registro técnico de alterações antigas feitas nos materiais.</p>
            </div>
        </section>

        <section class="panel reveal">
            <div class="table-wrap">
                <table class="finance-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Material</th>
                            <th>Tipo</th>
                            <th>Qtd. anterior</th>
                            <th>Qtd. nova</th>
                            <th>Usuário</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$movimentacoes): ?>
                            <tr><td colspan="7">Nenhuma alteração registrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($movimentacoes as $m): ?>
                            <tr>
                                <td data-label="Data/Hora"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></td>
                                <td data-label="Material"><?php echo htmlspecialchars($m['produto_nome']); ?></td>
                                <td data-label="Tipo"><?php echo htmlspecialchars($rotulos[$m['tipo']] ?? $m['tipo']); ?></td>
                                <td data-label="Qtd. anterior"><?php echo (int) $m['quantidade_anterior']; ?></td>
                                <td data-label="Qtd. nova"><?php echo (int) $m['quantidade_nova']; ?></td>
                                <td data-label="Usuário"><?php echo htmlspecialchars($m['usuario_nome'] ?? '-'); ?></td>
                                <td data-label="Observação"><?php echo htmlspecialchars($m['observacao'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>
