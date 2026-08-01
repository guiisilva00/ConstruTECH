<?php
/**
 * Autenticação, CSRF e controle de acesso por nível.
 * Incluir sempre no topo de páginas protegidas, depois de session_start().
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // JS não acessa o cookie de sessão
        'cookie_samesite' => 'Lax',  // mitiga CSRF vindo de sites externos
        // 'cookie_secure' => true,  // ative isso quando estiver em HTTPS
    ]);
}

/** Bloqueia acesso a quem não está logado */
function require_login(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit();
    }
}

/** Bloqueia acesso a quem não tem um dos níveis permitidos */
function require_nivel(array $niveisPermitidos): void
{
    require_login();
    if (!in_array($_SESSION['nivel_acesso'] ?? '', $niveisPermitidos, true)) {
        http_response_code(403);
        die('Acesso negado: você não tem permissão para esta ação.');
    }
}

/** Gera (ou reaproveita) o token CSRF da sessão atual */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Imprime o <input hidden> pronto para colar dentro de qualquer <form> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/** Valida o token recebido no POST/GET; encerra a requisição se for inválido */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        http_response_code(403);
        die('Requisição inválida ou expirada (CSRF). Recarregue a página e tente novamente.');
    }
}

/**
 * Rate limiting simples de login: bloqueia usuário após várias
 * tentativas falhas seguidas dentro de uma janela de tempo.
 */
function login_bloqueado(PDO $pdo, string $usuarioTentativa, int $maxTentativas = 5, int $janelaMinutos = 15): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM log_acessos
         WHERE usuario_tentativa = :usuario
           AND sucesso = 0
           AND criado_em >= (NOW() - INTERVAL :janela MINUTE)'
    );
    $stmt->execute([':usuario' => $usuarioTentativa, ':janela' => $janelaMinutos]);
    return (int) $stmt->fetchColumn() >= $maxTentativas;
}

function registrar_tentativa_login(PDO $pdo, ?int $usuarioId, string $usuarioTentativa, bool $sucesso): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO log_acessos (usuario_id, usuario_tentativa, sucesso, ip)
         VALUES (:usuario_id, :usuario_tentativa, :sucesso, :ip)'
    );
    $stmt->execute([
        ':usuario_id'        => $usuarioId,
        ':usuario_tentativa' => $usuarioTentativa,
        ':sucesso'           => $sucesso ? 1 : 0,
        ':ip'                => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
    ]);
}

/** Limpa espaços e trunca um texto livre vindo de formulário (ex.: motivo de movimentação) */
function sanitize_texto(string $valor, int $max = 255): string
{
    $valor = trim(preg_replace('/\s+/u', ' ', $valor));
    return mb_substr($valor, 0, $max);
}

/** Mensagens "flash" (um uso só) para feedback pós-redirect */
function set_flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
