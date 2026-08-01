<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

$erro = '';
$pdo  = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario'], $_POST['senha'])) {
    verificar_csrf();

    $usuarioDigitado = trim($_POST['usuario']);
    $senhaDigitada   = $_POST['senha'];

    if (login_bloqueado($pdo, $usuarioDigitado)) {
        $erro = 'Muitas tentativas falhas. Aguarde alguns minutos e tente novamente.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, senha_hash, nivel_acesso FROM usuarios WHERE usuario = :usuario AND ativo = 1');
        $stmt->execute([':usuario' => $usuarioDigitado]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senhaDigitada, $usuario['senha_hash'])) {
            session_regenerate_id(true);

            $_SESSION['usuario_id']    = $usuario['id'];
            $_SESSION['usuario_nome']  = $usuario['nome'];
            $_SESSION['nivel_acesso']  = $usuario['nivel_acesso'];
            $_SESSION['autenticado']   = true;

            registrar_tentativa_login($pdo, $usuario['id'], $usuarioDigitado, true);

            header('Location: dashboard.php');
            exit();
        }

        registrar_tentativa_login($pdo, $usuario['id'] ?? null, $usuarioDigitado, false);
        $erro = 'Acesso negado. Confira usuário e senha.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Acesso</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main id="conteudo-principal" class="login-page">
        <div class="login-card reveal">
            <p class="eyebrow">Acesso restrito</p>
            <h2>Gastos da piscina</h2>
            <p class="login-copy">Entre para acompanhar os materiais comprados e o investimento da obra.</p>

            <?php if (!empty($erro)): ?>
                <p class="msg-erro"><?php echo htmlspecialchars($erro); ?></p>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <?php echo csrf_field(); ?>

                <label for="usuario">Usuário(a)</label>
                <input type="text" name="usuario" id="usuario" placeholder="ex: admin" required autocomplete="username">

                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" placeholder="ex: 1234xyz" required autocomplete="current-password">

                <button type="submit" class="btn-login">Entrar</button>
            </form>
        </div>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>
