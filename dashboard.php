<?php
session_start();
if (!isset($_SESSION['autenticado'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>CT | Dashboard</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>
    <main class="dashboard-container">

        <div class="dashboard-titulo">
            <h1>Bem-vindo ao Painel Constru<span>TECH!</span></h1>
            <p>O que você deseja fazer hoje, Sr. Vicente?</p>
        </div>

        <a href="estoque.php" class="card-opcao">
            <h2>Gerenciar Estoque</h2>
            <p>Ver produtos, consultar quantidades e filtrar itens.</p>
        </a>

        <a href="produtos.php" class="card-opcao">
            <h2>Novo Produto</h2>
            <p>Adicionar um novo item ao catálogo da loja.</p>
        </a>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>