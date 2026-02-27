<?php
/**
 * Simple MCP Server for Hostinger Shared Hosting
 * Site: https://blogs.worldoftech.company
 * 
 * This file provides Model Context Protocol (MCP) tools for remote management.
 * It is placed in the public/ directory to be accessible via web.
 * Security is handled via X-MCP-Token header matched against .env.
 */

// ─── Load Environment Helper ────────────────────────────────────────────────
function get_env_var($key, $default = null) {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return $default;
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = array_pad(explode('=', $line, 2), 2, null);
        if (trim($name) === $key) {
            $value = trim($value);
            // Handle quoted values
            return trim($value, '"\'');
        }
    }
    return $default;
}

$secret = get_env_var('MCP_SECRET_TOKEN');
if (!$secret) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['error' => 'MCP_SECRET_TOKEN not configured in .env']));
}

define('SECRET_TOKEN', $secret);
define('BASE_DIR', realpath(__DIR__ . '/..')); // Restrict all file ops to project root

// ─── Auth ───────────────────────────────────────────────────────────────────

$authHeader = $_SERVER['HTTP_X_MCP_TOKEN'] ?? '';
if ($authHeader !== SECRET_TOKEN) {
    header('Content-Type: application/json');
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// ─── Request Parsing ─────────────────────────────────────────────────────────

header('Content-Type: application/json');
$raw   = file_get_contents('php://input');
$req   = json_decode($raw, true);
$id    = $req['id']    ?? null;
$method = $req['method'] ?? '';
$params = $req['params'] ?? [];

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
    $full = realpath(BASE_DIR . DIRECTORY_SEPARATOR . ltrim($rel, '/'));
    if ($full === false) {
        // Path doesn't exist yet — construct it manually and validate prefix
        $full = BASE_DIR . DIRECTORY_SEPARATOR . ltrim($rel, '/');
    }
    // Convert to canonical path for comparison
    $full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);
    $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, BASE_DIR);
    
    if (strpos($full, $base) !== 0) {
        return null; // Path escape attempt
    }
    return $full;
}

// ─── Tool Definitions ────────────────────────────────────────────────────────

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

// ─── MCP Method Routing ──────────────────────────────────────────────────────

switch ($method) {

    // Handshake
    case 'initialize':
        ok($id, [
            'protocolVersion' => '2024-11-05',
            'serverInfo'      => ['name' => 'hostinger-mcp', 'version' => '1.0.0'],
            'capabilities'    => ['tools' => new stdClass()],
        ]);

    case 'initialized':
        ok($id, new stdClass());

    // List tools
    case 'tools/list':
        ok($id, ['tools' => $tools]);

    // Execute a tool
    case 'tools/call':
        $name      = $params['name']      ?? '';
        $arguments = $params['arguments'] ?? [];

        switch ($name) {

            // ── read_file ──────────────────────────────────────────────────
            case 'read_file': {
                $path = safe_path($arguments['path'] ?? '');
                if (!$path)          err($id, 'Invalid or unsafe path.');
                if (!file_exists($path)) err($id, "File not found: {$arguments['path']}");
                $content = file_get_contents($path);
                ok($id, ['content' => [['type' => 'text', 'text' => $content]]]);
            }

            // ── write_file ─────────────────────────────────────────────────
            case 'write_file': {
                $path    = safe_path($arguments['path'] ?? '');
                $content = $arguments['content'] ?? '';
                if (!$path) err($id, 'Invalid or unsafe path.');
                $dir = dirname($path);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($path, $content);
                ok($id, ['content' => [['type' => 'text', 'text' => "Written: {$arguments['path']}"]]]); 
            }

            // ── delete_file ────────────────────────────────────────────────
            case 'delete_file': {
                $path = safe_path($arguments['path'] ?? '');
                if (!$path)          err($id, 'Invalid or unsafe path.');
                if (!file_exists($path)) err($id, 'File not found.');
                unlink($path);
                ok($id, ['content' => [['type' => 'text', 'text' => "Deleted: {$arguments['path']}"]]]); 
            }

            // ── list_dir ───────────────────────────────────────────────────
            case 'list_dir': {
                $path = safe_path($arguments['path'] ?? '.');
                if (!$path || !is_dir($path)) err($id, 'Directory not found.');
                $entries = scandir($path);
                if ($entries === false) err($id, 'Could not read directory.');
                $out = [];
                foreach ($entries as $e) {
                    if ($e === '.' || $e === '..') continue;
                    $full = $path . DIRECTORY_SEPARATOR . $e;
                    $prefix = is_dir($full) ? '[DIR]  ' : '[FILE] ';
                    $size = is_file($full) ? ' (' . number_format(filesize($full)) . ' bytes)' : '';
                    $out[] = $prefix . $e . $size;
                }
                ok($id, ['content' => [['type' => 'text', 'text' => implode("\n", $out)]]]);
            }

            // ── run_command ────────────────────────────────────────────────
            case 'run_command': {
                $cmd = $arguments['command'] ?? '';
                if (empty($cmd)) err($id, 'No command provided.');
                if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
                    err($id, 'shell_exec() is disabled on this host. Deployment via write_file still works.');
                }
                // Run from BASE_DIR
                $output = shell_exec('cd ' . escapeshellarg(BASE_DIR) . ' && ' . $cmd . ' 2>&1');
                ok($id, ['content' => [['type' => 'text', 'text' => $output ?? '(no output)']]]);
            }

            // ── read_php_log ───────────────────────────────────────────────
            case 'read_php_log': {
                $lines   = intval($arguments['lines'] ?? 50);
                $logPath = ini_get('error_log');
                if (!$logPath || !file_exists($logPath)) {
                    err($id, 'PHP error log not found or path not configured. Check php.ini error_log directive.');
                }
                $all  = file($logPath);
                $tail = array_slice($all, -$lines);
                ok($id, ['content' => [['type' => 'text', 'text' => implode('', $tail)]]]);
            }

            // ── php_info ───────────────────────────────────────────────────
            case 'php_info': {
                $info = [
                    'php_version'       => PHP_VERSION,
                    'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                    'document_root'     => $_SERVER['DOCUMENT_ROOT']   ?? 'unknown',
                    'mcp_base_dir'      => BASE_DIR,
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size'     => ini_get('post_max_size'),
                    'max_execution_time'=> ini_get('max_execution_time'),
                    'disabled_functions'=> ini_get('disable_functions') ?: 'none',
                    'error_log'         => ini_get('error_log') ?: 'not set',
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
