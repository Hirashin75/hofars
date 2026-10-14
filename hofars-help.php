<?php
declare(strict_types=1);

/**
 * JSON endpoint for the chat widget.
 *   GET  hofars-help.php                         -> saved conversation, mode and suggestions
 *   POST hofars-help.php  message=...  csrf=...  -> send a message
 *   POST hofars-help.php  action=clear csrf=...  -> clear the conversation
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/chatbot.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chat_reply(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if ($user === null) {
    chat_reply(['error' => 'Please log in again.'], 401);
}
$role = $user['role'];
$history = $_SESSION['chat'] ?? [];

if (!is_post()) {
    chat_reply([
        'messages'    => $history,
        'mode'        => chatbot_ai_enabled() ? 'ai' : 'basic',
        'suggestions' => chatbot_suggestions($role),
    ]);
}

csrf_check();

if (($_POST['action'] ?? '') === 'clear') {
    unset($_SESSION['chat']);
    chat_reply(['ok' => true]);
}

$text = trim((string) ($_POST['message'] ?? ''));
if ($text === '' || !mb_check_encoding($text, 'UTF-8')) {
    chat_reply(['error' => 'Please type a message.'], 422);
}
if (mb_strlen($text) > CHAT_MAX_INPUT) {
    chat_reply(['error' => 'Please keep your message under ' . CHAT_MAX_INPUT . ' characters.'], 422);
}
$text = preg_replace('/[^\P{C}\n]+/u', '', $text) ?? $text; // strip control characters, keep newlines

$answer = null;
$notice = null;
$mode = 'basic';

if (chatbot_ai_enabled()) {
    if (chatbot_quota_ok((int) $user['id'])) {
        chatbot_record_usage((int) $user['id']);
        $messages = array_merge($history, [['role' => 'user', 'content' => $text]]);

        // Release the session lock while waiting for the API so other tabs are not blocked.
        session_write_close();
        $answer = chatbot_call_claude(chatbot_system_prompt($role), $messages);
        session_start();

        if ($answer !== null) {
            $mode = 'ai';
        } else {
            $notice = 'The AI service is unavailable right now, so this is a built-in answer.';
        }
    } else {
        $notice = 'The AI answer limit has been reached for now, so this is a built-in answer.';
    }
}

if ($answer === null) {
    $answer = chatbot_basic_reply($text, $role);
}

$history = $_SESSION['chat'] ?? [];
$history[] = ['role' => 'user', 'content' => $text];
$history[] = ['role' => 'assistant', 'content' => $answer];
$_SESSION['chat'] = array_slice($history, -CHAT_MAX_HISTORY);

chat_reply(['reply' => $answer, 'mode' => $mode, 'notice' => $notice]);
