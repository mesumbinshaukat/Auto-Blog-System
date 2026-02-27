<?php
/**
 * MCP Server for Hostinger Shared Hosting — Streamable HTTP Transport
 * Site: https://blogs.worldoftech.company
 *
 * Implements MCP 2024-11-05 Streamable HTTP transport:
 *   GET  /mcp.php  → SSE endpoint (for server-initiated messages, returns empty stream)
 *   POST /mcp.php  → JSON-RPC 2.0 endpoint
 *
 * Auth: X-MCP-Token header must match MCP_SECRET_TOKEN in .env
 */

// ─── CORS & Transport Headers ────────────────────────────────────────────────

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-MCP-Token, Mcp-Session-Id, Accept');
header('Access-Control-Expose-Headers: Mcp-Session-Id');
header('Access-Control-Allow-Credentials: true');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Load Environment Helper ─────────────────────────────────────────────────

function get_env_var($key, $default = null) {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return $default;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($name, $value) = array_pad(explode('=', $line, 2), 2, null);
        if (trim($name) === $key) {
            return trim(trim($value), '"\'');
        }
    }
    return $default;
}

// ─── Auth ────────────────────────────────────────────────────────────────────

$secret = get_env_var('MCP_SECRET_TOKEN');
if (!$secret) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['error' => 'MCP_SECRET_TOKEN not configured in .env']));
}

define('SECRET_TOKEN', $secret);
define('BASE_DIR', realpath(__DIR__ . '/..'));

$authHeader = $_SERVER['HTTP_X_MCP_TOKEN'] ?? '';
if ($authHeader !== SECRET_TOKEN) {
    header('Content-Type: application/json');
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// ─── Session ID ──────────────────────────────────────────────────────────────

$sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? bin2hex(random_bytes(16));
header('Mcp-Session-Id: ' . $sessionId);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function ok($id, $result) {
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    exit;
}

function err($id, $msg, $code = -32000) {
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]]);
    exit;
}

/** Ensure path stays inside BASE_DIR */
function safe_path($rel) {
    $rel = ltrim(str_replace(['../', '..\\'], '', $rel), '/\\');
    $full = BASE_DIR . DIRECTORY_SEPARATOR . $rel;
    $full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);
    $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, BASE_DIR);

    if (strpos(realpath($full) ?: $full, $base) !== 0) {
        return null;
    }
    return $full;
}

// ─── Tool Definitions ─────────────────────────────────────────────────────────

$tools = [
    [
        'name'        => 'read_file',
        'description' => 'Read the contents of a file (relative to the project root).',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => ['path' => ['type' => 'string', 'description' => 'Relative file path, e.g. .env']],
            'required'   => ['path'],
        ],
    ],
    [
        'name'        => 'write_file',
        'description' => 'Write (create or overwrite) a file. Use for deployments.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'path'    => ['type' => 'string', 'description' => 'Relative file path'],
                'content' => ['type' => 'string', 'description' => 'File content to write'],
            ],
            'required' => ['path', 'content'],
        ],
    ],
    [
        'name'        => 'delete_file',
        'description' => 'Delete a file.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => ['path' => ['type' => 'string']],
            'required'   => ['path'],
        ],
    ],
    [
        'name'        => 'list_dir',
        'description' => 'List files and folders in a directory.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => ['path' => ['type' => 'string', 'description' => 'Relative directory path. Use "." for root.']],
            'required'   => ['path'],
        ],
    ],
    [
        'name'        => 'run_command',
        'description' => 'Run a shell command (e.g. php artisan migrate, git pull). Only works if exec() is enabled on your host.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => ['command' => ['type' => 'string', 'description' => 'Shell command to run']],
            'required'   => ['command'],
        ],
    ],
    [
        'name'        => 'read_php_log',
        'description' => 'Read the last N lines of the PHP error log.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => ['lines' => ['type' => 'integer', 'description' => 'Number of lines from the end (default 50)']],
            'required'   => [],
        ],
    ],
    [
        'name'        => 'php_info',
        'description' => 'Return key PHP environment info: version, disabled functions, upload limits, etc.',
        'inputSchema' => ['type' => 'object', 'properties' => []],
    ],
];

// ─── GET → SSE endpoint (required by Streamable HTTP spec) ───────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return a minimal SSE stream that stays open briefly then closes.
    // The MCP client will use POST for actual RPC calls.
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    // Send a simple ping so the client knows the connection is alive, then close.
    echo ": MCP SSE endpoint ready\n\n";
    flush();
    exit;
}

// ─── POST → JSON-RPC 2.0 ─────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Method not allowed']));
}

header('Content-Type: application/json');

$raw    = file_get_contents('php://input');
$req    = json_decode($raw, true);
$id     = $req['id']     ?? null;
$method = $req['method'] ?? '';
$params = $req['params'] ?? [];

// ─── MCP Method Routing ───────────────────────────────────────────────────────

switch ($method) {

    case 'initialize':
        ok($id, [
            'protocolVersion' => '2024-11-05',
            'serverInfo'      => ['name' => 'hostinger-mcp', 'version' => '1.1.0'],
            'capabilities'    => ['tools' => new stdClass()],
        ]);

    case 'initialized':
        ok($id, new stdClass());

    case 'tools/list':
        ok($id, ['tools' => $tools]);

    case 'tools/call':
        $name      = $params['name']      ?? '';
        $arguments = $params['arguments'] ?? [];

        switch ($name) {

            case 'read_file': {
                $path = safe_path($arguments['path'] ?? '');
                if (!$path)              err($id, 'Invalid or unsafe path.');
                if (!file_exists($path)) err($id, "File not found: {$arguments['path']}");
                ok($id, ['content' => [['type' => 'text', 'text' => file_get_contents($path)]]]);
            }

            case 'write_file': {
                $path    = safe_path($arguments['path'] ?? '');
                $content = $arguments['content'] ?? '';
                if (!$path) err($id, 'Invalid or unsafe path.');
                $dir = dirname($path);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($path, $content);
                ok($id, ['content' => [['type' => 'text', 'text' => "Written: {$arguments['path']}"]]]); 
            }

            case 'delete_file': {
                $path = safe_path($arguments['path'] ?? '');
                if (!$path)              err($id, 'Invalid or unsafe path.');
                if (!file_exists($path)) err($id, 'File not found.');
                unlink($path);
                ok($id, ['content' => [['type' => 'text', 'text' => "Deleted: {$arguments['path']}"]]]); 
            }

            case 'list_dir': {
                $path = safe_path($arguments['path'] ?? '.');
                if (!$path || !is_dir($path)) err($id, 'Directory not found.');
                $entries = scandir($path);
                if ($entries === false) err($id, 'Could not read directory.');
                $out = [];
                foreach ($entries as $e) {
                    if ($e === '.' || $e === '..') continue;
                    $full   = $path . DIRECTORY_SEPARATOR . $e;
                    $prefix = is_dir($full) ? '[DIR]  ' : '[FILE] ';
                    $size   = is_file($full) ? ' (' . number_format(filesize($full)) . ' bytes)' : '';
                    $out[]  = $prefix . $e . $size;
                }
                ok($id, ['content' => [['type' => 'text', 'text' => implode("\n", $out)]]]);
            }

            case 'run_command': {
                $cmd = $arguments['command'] ?? '';
                if (empty($cmd)) err($id, 'No command provided.');
                if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
                    err($id, 'shell_exec() is disabled on this host.');
                }
                $output = shell_exec('cd ' . escapeshellarg(BASE_DIR) . ' && ' . $cmd . ' 2>&1');
                ok($id, ['content' => [['type' => 'text', 'text' => $output ?? '(no output)']]]);
            }

            case 'read_php_log': {
                $lines   = intval($arguments['lines'] ?? 50);
                $logPath = ini_get('error_log');
                if (!$logPath || !file_exists($logPath)) {
                    err($id, 'PHP error log not found or path not configured.');
                }
                $all  = file($logPath);
                $tail = array_slice($all, -$lines);
                ok($id, ['content' => [['type' => 'text', 'text' => implode('', $tail)]]]);
            }

            case 'php_info': {
                $info = [
                    'php_version'          => PHP_VERSION,
                    'server_software'      => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                    'document_root'        => $_SERVER['DOCUMENT_ROOT']   ?? 'unknown',
                    'mcp_base_dir'         => BASE_DIR,
                    'upload_max_filesize'  => ini_get('upload_max_filesize'),
                    'post_max_size'        => ini_get('post_max_size'),
                    'max_execution_time'   => ini_get('max_execution_time'),
                    'disabled_functions'   => ini_get('disable_functions') ?: 'none',
                    'error_log'            => ini_get('error_log') ?: 'not set',
                    'shell_exec_available' => function_exists('shell_exec') ? 'yes' : 'no',
                ];
                $text = '';
                foreach ($info as $k => $v) $text .= "$k: $v\n";
                ok($id, ['content' => [['type' => 'text', 'text' => $text]]]);
            }

            default:
                err($id, "Unknown tool: $name");
        }
        break;

    default:
        err($id, "Method not found: $method", -32601);
}
