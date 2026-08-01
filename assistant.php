<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/assistant_engine.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    assistant_json_response(['reply' => 'Método não permitido.'], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    assistant_json_response(['reply' => 'Não consegui ler a mensagem enviada.'], 400);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    assistant_json_response(['reply' => 'Sessão expirada. Recarregue a página e tente novamente.'], 403);
}

$pdo = get_pdo();

try {
    if (!empty($input['confirm_action'])) {
        assistant_json_response(assistant_execute_pending($pdo, (string) $input['confirm_action']));
    }

    if (!empty($input['cancel_action'])) {
        unset($_SESSION['assistant_pending_actions'][(string) $input['cancel_action']]);
        assistant_json_response(['reply' => 'Tudo bem, cancelei essa ação.']);
    }

    $message = (string) ($input['message'] ?? '');
    assistant_json_response(assistant_handle_message($pdo, $message));
} catch (Throwable $e) {
    error_log('Erro no assistente: ' . $e->getMessage());
    assistant_json_response(['reply' => 'Tive um problema ao processar isso. Tente novamente em instantes.'], 500);
}
