<?php
session_start();

if (isset($_SESSION['autenticado'])) {
    header("Location: dashboard.php");
    exit();
}

$usuario_mestre = "Vicente"; //usuario
$senha_mestre = "vicente777"; //senha

if (isset($_POST['usuario']) && isset($_POST['senha'])) {

    $usuario_digitado = $_POST['usuario'];
    $senha_digitada = $_POST['senha'];

    if ($usuario_digitado === $usuario_mestre && $senha_digitada === $senha_mestre) {

        $_SESSION['autenticado'] = true;

        header("Location: dashboard.php");
        exit();
    } else {
        $erro = "Acesso negado! Dados incorretos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT | Login</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php
    require_once 'partials/header.php';
    ?>

    <main>

        <div class="login-card">
            <h2>Login</h2>

            <?php if (!empty($erro)): ?>
                <p class="msg-erro"><?php echo $erro; ?></p>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <label for="usuario">Usuário(a)*</label>
                <input type="text" name="usuario" placeholder="ex: admin" required>

                <label for="senha">Senha*</label>
                <input type="password" name="senha" placeholder="ex: 1234xyz" required>

                <button type="submit" class="btn-login">Entrar</button>
            </form>
        </div>
    </main>

    <?php
    require_once 'partials/footer.php';
    ?>
</body>

</html>