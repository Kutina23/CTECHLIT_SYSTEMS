<?php
/**
 * AI Help Chat API
 * Proxies chat messages to an OpenAI-compatible AI endpoint
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decoded = json_decode($rawInput ?: '[]', true);
$input = is_array($decoded) && !empty($decoded) ? $decoded : ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$message = trim((string) ($input['message'] ?? ''));
if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a message']);
    exit;
}

$aiApiUrl = $_ENV['AI_API_URL'] ?? '';
$aiApiKey = $_ENV['AI_API_KEY'] ?? '';
$aiModel = $_ENV['AI_MODEL'] ?? 'gpt-3.5-turbo';

if ($aiApiUrl === '' || $aiApiKey === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'AI assistant is not configured']);
    exit;
}

$helpContext = '';
try {
    if (class_exists('Database') && Database::tableExists('help_resources')) {
        $resources = Database::fetchAll(
            "SELECT resource_type, title, content
             FROM help_resources
             WHERE is_published = 1
             ORDER BY resource_type, sort_order, title
             LIMIT 50"
        );
        if (!empty($resources)) {
            $helpContext = "\n\nHelp Center Knowledge Base:\n";
            foreach ($resources as $resource) {
                $helpContext .= "- [{$resource['resource_type']}] {$resource['title']}: {$resource['content']}\n";
            }
        }
    }
} catch (Exception $e) {
    $helpContext = '';
}

$systemPrompt = <<<PROMPT
You are a helpful AI assistant for the CTECHLIT & SYSTEMS portal.
Your role is to help users with questions about the system, navigation, features, and general support.
Answer concisely and accurately based on the help center content provided below.
If you don't know the answer, direct the user to contact support at support@ctechlit.com.
Be friendly and professional.
{$helpContext}
PROMPT;

$payload = [
    'model' => $aiModel,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $message],
    ],
    'max_tokens' => 1024,
    'temperature' => 0.7,
];

$ch = curl_init($aiApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $aiApiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'AI service is temporarily unavailable. Please try again later.']);
    exit;
}

$result = json_decode($response, true);
$reply = $result['choices'][0]['message']['content'] ?? '';

if ($reply === '') {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'AI returned an empty response. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'data' => ['reply' => $reply]]);
exit;
