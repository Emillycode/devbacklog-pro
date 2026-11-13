<?php
/**
 * API Backend Unificada, Autenticação Exclusiva (.env) & Cliente Supabase REST
 * Suporta tanto o Frontend React (src/App.tsx) quanto o Frontend WAMP (index.php)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$ENV_FILE = __DIR__ . '/.env';
$DATA_FILE = __DIR__ . '/data.json';
$AUTH_FILE = __DIR__ . '/auth_config.json';
$UPLOAD_DIR = __DIR__ . '/uploads';

if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0775, true);
}

/**
 * Carrega variáveis do arquivo .env de forma segura sem expor valores
 */
function get_env_var(string $key): string {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($val) ? trim($val) : '';
}

function load_env_config(string $env_file): array {
    $vars = [
        'ADMIN_EMAIL' => get_env_var('ADMIN_EMAIL'),
        'ADMIN_PASSWORD' => get_env_var('ADMIN_PASSWORD'),
        'ADMIN_PASSWORD_HASH' => get_env_var('ADMIN_PASSWORD_HASH'),
        'VITE_SUPABASE_URL' => get_env_var('VITE_SUPABASE_URL'),
        'VITE_SUPABASE_PUBLISHABLE_KEY' => get_env_var('VITE_SUPABASE_PUBLISHABLE_KEY'),
        'VITE_SUPABASE_ANON_KEY' => get_env_var('VITE_SUPABASE_ANON_KEY'),
        'SUPABASE_URL' => get_env_var('SUPABASE_URL'),
        'SUPABASE_ANON_KEY' => get_env_var('SUPABASE_ANON_KEY'),
        'SUPABASE_KEY' => get_env_var('SUPABASE_KEY'),
    ];

    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                $parts = explode('=', $trimmed, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    if (
                        (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))
                    ) {
                        $val = substr($val, 1, -1);
                    }
                    $vars[$key] = $val;
                }
            }
        }
    }

    if (empty($vars['SUPABASE_URL']) && !empty($vars['VITE_SUPABASE_URL'])) {
        $vars['SUPABASE_URL'] = $vars['VITE_SUPABASE_URL'];
    }
    if (empty($vars['SUPABASE_ANON_KEY'])) {
        $vars['SUPABASE_ANON_KEY'] = $vars['VITE_SUPABASE_PUBLISHABLE_KEY'] ?: ($vars['VITE_SUPABASE_ANON_KEY'] ?? '');
    }
    if (empty($vars['SUPABASE_KEY']) && !empty($vars['SUPABASE_ANON_KEY'])) {
        $vars['SUPABASE_KEY'] = $vars['SUPABASE_ANON_KEY'];
    }

    return $vars;
}

$ENV = load_env_config($ENV_FILE);

/**
 * Verifica se uma chave do Supabase é segura para o cliente (Publishable / Anon)
 * Nunca expõe chaves secret / service_role ao navegador
 */
function is_safe_publishable_key(string $key): bool {
    $k = trim($key);
    if ($k === '' || str_contains($k, 'sua_chave_')) {
        return false;
    }
    if (str_starts_with($k, 'sb_secret_')) {
        return false;
    }
    if (str_starts_with($k, 'sb_publishable_')) {
        return true;
    }
    // Se for JWT legado (3 partes), verifica que role === 'anon'
    $parts = explode('.', $k);
    if (count($parts) === 3) {
        $payload_raw = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload_raw !== false) {
            $payload = json_decode($payload_raw, true);
            if (is_array($payload) && ($payload['role'] ?? '') === 'service_role') {
                return false;
            }
        }
    }
    return true;
}

function get_public_supabase_key(array $env): string {
    $candidates = [
        $env['VITE_SUPABASE_PUBLISHABLE_KEY'] ?? '',
        $env['SUPABASE_ANON_KEY'] ?? '',
        $env['VITE_SUPABASE_ANON_KEY'] ?? '',
        $env['SUPABASE_KEY'] ?? ''
    ];
    foreach ($candidates as $cand) {
        if (is_safe_publishable_key((string)$cand)) {
            return trim((string)$cand);
        }
    }
    return '';
}

/**
 * Verifica se o Supabase está configurado no .env
 */
function is_supabase_configured(array $env): bool {
    $url = trim($env['SUPABASE_URL'] ?? $env['VITE_SUPABASE_URL'] ?? '');
    $key = trim($env['SUPABASE_KEY'] ?? '');
    if ($key === '') {
        $key = trim($env['SUPABASE_ANON_KEY'] ?? $env['VITE_SUPABASE_PUBLISHABLE_KEY'] ?? $env['VITE_SUPABASE_ANON_KEY'] ?? '');
    }
    return $url !== ''
        && $key !== ''
        && !str_contains($url, 'SEU_PROJETO_ID')
        && !str_contains($key, 'sua_chave_');
}

/**
 * Extrai o Bearer token (JWT do Supabase Auth) enviado pelo cliente React
 */
function get_bearer_token(): string {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth_header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strtolower((string)$k) === 'authorization') {
                    $auth_header = (string)$v;
                    break;
                }
            }
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', trim($auth_header), $matches)) {
        return trim($matches[1]);
    }
    return (string)($_SESSION['supabase_access_token'] ?? '');
}

/**
 * Valida o JWT do usuário diretamente na API do Supabase Auth (/auth/v1/user)
 */
function verify_supabase_jwt(array $env, string $jwt): ?array {
    if ($jwt === '' || !is_supabase_configured($env) || !function_exists('curl_init')) {
        return null;
    }

    // Cache em memória da requisição atual
    static $verified_cache = [];
    if (array_key_exists($jwt, $verified_cache)) {
        return $verified_cache[$jwt];
    }

    $base_url = rtrim(trim($env['SUPABASE_URL']), '/');
    $api_key = get_public_supabase_key($env) ?: trim($env['SUPABASE_KEY']);
    $url = $base_url . '/auth/v1/user';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key,
        'Authorization: Bearer ' . $jwt,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        $verified_cache[$jwt] = null;
        return null;
    }

    $user = json_decode($response, true);
    if (!is_array($user) || empty($user['id'])) {
        $verified_cache[$jwt] = null;
        return null;
    }

    $verified_cache[$jwt] = [
        'id' => (string)$user['id'],
        'email' => (string)($user['email'] ?? ''),
        'jwt' => $jwt
    ];
    return $verified_cache[$jwt];
}

/**
 * Autentica usuário com e-mail e senha no Supabase Auth (/auth/v1/token?grant_type=password)
 */
function supabase_auth_sign_in(array $env, string $email, string $password): array {
    if (!is_supabase_configured($env) || !function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Supabase não configurado ou cURL ausente.'];
    }

    $base_url = rtrim(trim($env['SUPABASE_URL']), '/');
    $api_key = get_public_supabase_key($env) ?: trim($env['SUPABASE_KEY']);
    $url = $base_url . '/auth/v1/token?grant_type=password';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'password' => $password
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Não foi possível conectar ao servidor do Supabase.'];
    }

    $decoded = json_decode($response, true);
    if ($status >= 200 && $status < 300 && !empty($decoded['access_token'])) {
        return ['ok' => true, 'session' => $decoded];
    }

    $err_desc = $decoded['error_description'] ?? ($decoded['msg'] ?? ($decoded['message'] ?? 'E-mail ou senha inválidos.'));
    if ($err_desc === 'Invalid login credentials') {
        $err_desc = 'E-mail ou senha inválidos no Supabase.';
    }
    return ['ok' => false, 'error' => $err_desc];
}

/**
 * Cria nova conta com e-mail e senha no Supabase Auth (/auth/v1/signup)
 */
function supabase_auth_sign_up(array $env, string $email, string $password): array {
    if (!is_supabase_configured($env) || !function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Supabase não configurado ou cURL ausente.'];
    }

    $base_url = rtrim(trim($env['SUPABASE_URL']), '/');
    $api_key = get_public_supabase_key($env) ?: trim($env['SUPABASE_KEY']);
    $url = $base_url . '/auth/v1/signup';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'password' => $password
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Não foi possível conectar ao servidor do Supabase.'];
    }

    $decoded = json_decode($response, true);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'data' => $decoded];
    }

    $err_desc = $decoded['error_description'] ?? ($decoded['msg'] ?? ($decoded['message'] ?? 'Erro ao cadastrar usuário no Supabase.'));
    return ['ok' => false, 'error' => $err_desc];
}

/**
 * Executa requisição HTTPS para a API REST (PostgREST) do Supabase
 * Quando há um JWT de usuário autenticado, repassa no header Authorization para respeitar o RLS!
 */
function supabase_request(
    array $env,
    string $method,
    string $table,
    string $query = '',
    ?array $payload = null,
    string $prefer = 'return=representation'
): array {
    if (!is_supabase_configured($env) || !function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Supabase não configurado ou cURL ausente.'];
    }

    $base_url = rtrim(trim($env['SUPABASE_URL']), '/');
    $api_key = get_public_supabase_key($env) ?: trim($env['SUPABASE_KEY']);
    $user_jwt = get_bearer_token();
    $bearer = $user_jwt !== '' ? $user_jwt : trim($env['SUPABASE_KEY']);
    $url = $base_url . '/rest/v1/' . $table . ($query !== '' ? '?' . $query : '');

    $ch = curl_init($url);
    $headers = [
        'apikey: ' . $api_key,
        'Authorization: Bearer ' . $bearer,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: ' . $prefer
    ];

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $curl_err];
    }

    $decoded = json_decode($response, true);
    $is_ok = ($status >= 200 && $status < 300);

    return [
        'ok' => $is_ok,
        'status' => $status,
        'data' => $decoded,
        'error' => $is_ok ? null : ($decoded['message'] ?? $response)
    ];
}

// Fuso horário
$user_tz = $_COOKIE['user_timezone'] ?? 'America/Sao_Paulo';
if (!in_array($user_tz, DateTimeZone::listIdentifiers(), true)) {
    $user_tz = 'America/Sao_Paulo';
}
date_default_timezone_set($user_tz);

/**
 * Gera ou retorna o token CSRF da sessão atual
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF enviado via header ou POST
 */
function verify_csrf_token(?string $token): bool {
    $session_token = get_csrf_token();
    if (empty($token)) {
        return false;
    }
    return hash_equals($session_token, $token);
}

/**
 * Controle de tentativas de login (proteção contra força bruta) e credenciais
 */
function load_auth_config(string $auth_file): array {
    global $ENV;
    $env_email = trim($ENV['ADMIN_EMAIL'] ?? '');
    $env_pass = $ENV['ADMIN_PASSWORD'] ?? '';
    $env_hash = trim($ENV['ADMIN_PASSWORD_HASH'] ?? '');

    $default_email = $env_email !== '' ? $env_email : 'admin@meusoftware.com';
    $default_hash = $env_hash !== ''
        ? $env_hash
        : password_hash($env_pass !== '' ? $env_pass : 'admin123', PASSWORD_BCRYPT);

    if (!file_exists($auth_file)) {
        $initial = [
            'username' => 'admin',
            'email' => $default_email,
            'password_hash' => $default_hash,
            'pin_hash' => password_hash('1234', PASSWORD_BCRYPT),
            'require_auth' => true,
            'is_default_password' => ($env_pass === 'admin123' || $env_pass === ''),
            'failed_attempts' => 0,
            'lockout_until' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($auth_file, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $initial;
    }

    $raw = file_get_contents($auth_file);
    $decoded = $raw ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        $decoded = [];
    }

    // As credenciais do .env têm prioridade máxima quando definidas
    if ($env_email !== '') {
        $decoded['email'] = $env_email;
    } elseif (empty($decoded['email'])) {
        $decoded['email'] = 'admin@meusoftware.com';
    }

    if ($env_hash !== '') {
        $decoded['password_hash'] = $env_hash;
    } elseif ($env_pass !== '') {
        $decoded['password_hash'] = password_hash($env_pass, PASSWORD_BCRYPT);
    } elseif (empty($decoded['password_hash'])) {
        $decoded['password_hash'] = $default_hash;
    }

    $decoded['require_auth'] = $decoded['require_auth'] ?? true;
    $decoded['failed_attempts'] = (int)($decoded['failed_attempts'] ?? 0);
    $decoded['lockout_until'] = (int)($decoded['lockout_until'] ?? 0);
    $decoded['is_default_password'] = ($env_pass === 'admin123' && $env_email === 'admin@meusoftware.com');

    return $decoded;
}

function save_auth_config(string $auth_file, array $config): bool {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    return file_put_contents($auth_file, $json, LOCK_EX) !== false;
}

/**
 * Valida E-mail e Senha estritamente contra o configurado no .env
 */
function verify_admin_credentials(string $input_email, string $input_password, array $auth_config, array $env): bool {
    $expected_email = trim($env['ADMIN_EMAIL'] ?? ($auth_config['email'] ?? 'admin@meusoftware.com'));
    $input_email_clean = trim($input_email);

    if ($input_email_clean === '' || $input_password === '') {
        return false;
    }

    // Comparação de e-mail em tempo constante (case-insensitive)
    $email_matches = hash_equals(
        mb_strtolower($expected_email, 'UTF-8'),
        mb_strtolower($input_email_clean, 'UTF-8')
    );

    if (!$email_matches) {
        return false;
    }

    $env_hash = trim($env['ADMIN_PASSWORD_HASH'] ?? '');
    $env_pass = $env['ADMIN_PASSWORD'] ?? '';

    if ($env_hash !== '') {
        return password_verify($input_password, $env_hash);
    }

    if ($env_pass !== '') {
        return hash_equals($env_pass, $input_password);
    }

    return password_verify($input_password, $auth_config['password_hash'] ?? '');
}

/**
 * Obtém o usuário autenticado via Supabase Auth JWT ou Sessão PHP
 */
function get_authenticated_user(array $auth_config, array $env): ?array {
    $jwt = get_bearer_token();
    if ($jwt !== '') {
        $sb_user = verify_supabase_jwt($env, $jwt);
        if ($sb_user !== null) {
            return [
                'id' => $sb_user['id'],
                'email' => $sb_user['email'],
                'provider' => 'supabase'
            ];
        }
    }

    if (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return [
            'id' => (string)($_SESSION['supabase_user_id'] ?? 'local_admin'),
            'email' => (string)($_SESSION['admin_email'] ?? ($env['ADMIN_EMAIL'] ?? 'admin@meusoftware.com')),
            'provider' => !empty($_SESSION['supabase_user_id']) ? 'supabase' : 'env'
        ];
    }

    if (empty($auth_config['require_auth'])) {
        return [
            'id' => 'local_admin',
            'email' => (string)($env['ADMIN_EMAIL'] ?? 'admin@meusoftware.com'),
            'provider' => 'env'
        ];
    }

    return null;
}

/**
 * Verifica se o usuário está autenticado via Supabase Auth JWT ou na sessão PHP
 */
function is_authenticated(array $auth_config): bool {
    global $ENV;
    return get_authenticated_user($auth_config, $ENV ?? []) !== null;
}

/**
 * Normaliza arrays de projetos e tarefas
 */
function normalize_app_data(array $projetos, array $tarefas): array {
    foreach ($projetos as &$p) {
        if (empty($p['cor'])) {
            $p['cor'] = 'blue';
        }
        if (!empty($p['criado_em']) && str_contains($p['criado_em'], 'T')) {
            $p['criado_em'] = substr(str_replace('T', ' ', $p['criado_em']), 0, 19);
        }
    }
    unset($p);

    foreach ($tarefas as &$t) {
        if (!in_array($t['status'] ?? '', ['pendente', 'em_andamento', 'concluido'], true)) {
            $t['status'] = 'pendente';
        }
        if (empty($t['data_limite'])) {
            $t['data_limite'] = '';
        }
        if (!array_key_exists('anexo', $t)) {
            $t['anexo'] = null;
        } elseif (is_string($t['anexo'])) {
            $t['anexo'] = json_decode($t['anexo'], true);
        }
        if (!empty($t['criado_em']) && str_contains($t['criado_em'], 'T')) {
            $t['criado_em'] = substr(str_replace('T', ' ', $t['criado_em']), 0, 19);
        }
    }
    unset($t);

    return [
        'projetos' => array_values($projetos),
        'tarefas' => array_values($tarefas)
    ];
}

/**
 * Carrega dados prioritariamente do Supabase (quando configurado no .env) com cache/fallback em data.json
 */
function load_app_data(string $file): array {
    global $ENV;

    if (is_supabase_configured($ENV)) {
        $res_proj = supabase_request($ENV, 'GET', 'projetos', 'select=*&order=criado_em.asc');
        $res_task = supabase_request($ENV, 'GET', 'tarefas', 'select=*&order=criado_em.desc');

        if ($res_proj['ok'] && $res_task['ok'] && is_array($res_proj['data']) && is_array($res_task['data'])) {
            $normalized = normalize_app_data($res_proj['data'], $res_task['data']);
            // Atualiza espelho local de segurança
            save_local_json($file, $normalized);
            return $normalized;
        }
    }

    if (!file_exists($file)) {
        $default = [
            'projetos' => [],
            'tarefas' => []
        ];
        save_local_json($file, $default);
        return $default;
    }

    $content = file_get_contents($file);
    if ($content === false || trim($content) === '') {
        return ['projetos' => [], 'tarefas' => []];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return ['projetos' => [], 'tarefas' => []];
    }

    return normalize_app_data(
        is_array($decoded['projetos'] ?? null) ? $decoded['projetos'] : [],
        is_array($decoded['tarefas'] ?? null) ? $decoded['tarefas'] : []
    );
}

/**
 * Gravação atômica local em data.json com LOCK_EX
 */
function save_local_json(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

/**
 * Compatibilidade para gravação de dados completos e sincronização com Supabase
 */
function save_app_data(string $file, array $data): bool {
    return save_local_json($file, $data);
}

/**
 * Sincroniza todos os projetos e tarefas locais com o banco Supabase (Upsert)
 */
function sync_all_to_supabase(array $env, array $data): array {
    if (!is_supabase_configured($env)) {
        return ['ok' => false, 'error' => 'Configure SUPABASE_URL e SUPABASE_KEY no arquivo .env primeiro.'];
    }

    $sb_user = verify_supabase_jwt($env, get_bearer_token());
    $user_id = $sb_user['id'] ?? null;

    if (!empty($data['projetos'])) {
        $proj_payload = array_map(function($p) use ($user_id) {
            $row = [
                'id' => $p['id'],
                'nome' => $p['nome'],
                'descricao' => $p['descricao'] ?? '',
                'cor' => $p['cor'] ?? 'blue',
                'criado_em' => $p['criado_em'] ?? date('Y-m-d H:i:s')
            ];
            if ($user_id !== null) {
                $row['user_id'] = $user_id;
            }
            return $row;
        }, $data['projetos']);

        $res_p = supabase_request(
            $env,
            'POST',
            'projetos',
            'on_conflict=id',
            $proj_payload,
            'resolution=merge-duplicates,return=minimal'
        );
        if (!$res_p['ok']) {
            return ['ok' => false, 'error' => 'Erro ao sincronizar pastas no Supabase: ' . ($res_p['error'] ?? '')];
        }
    }

    if (!empty($data['tarefas'])) {
        $task_payload = array_map(function($t) use ($user_id) {
            $row = [
                'id' => $t['id'],
                'id_projeto' => $t['id_projeto'],
                'titulo' => $t['titulo'],
                'descricao_detalhada' => $t['descricao_detalhada'] ?? '',
                'prioridade' => $t['prioridade'] ?? 'Média',
                'status' => $t['status'] ?? 'pendente',
                'data_limite' => !empty($t['data_limite']) ? $t['data_limite'] : null,
                'anexo' => $t['anexo'] ?? null,
                'criado_em' => $t['criado_em'] ?? date('Y-m-d H:i:s')
            ];
            if ($user_id !== null) {
                $row['user_id'] = $user_id;
            }
            return $row;
        }, $data['tarefas']);

        $res_t = supabase_request(
            $env,
            'POST',
            'tarefas',
            'on_conflict=id',
            $task_payload,
            'resolution=merge-duplicates,return=minimal'
        );
        if (!$res_t['ok']) {
            return ['ok' => false, 'error' => 'Erro ao sincronizar tarefas no Supabase: ' . ($res_t['error'] ?? '')];
        }
    }

    return ['ok' => true];
}

/**
 * Upload seguro de mídia com validação de extensão e MIME real
 */
function handle_secure_media_upload(array $file_post, string $upload_dir): ?array {
    if (!isset($file_post['error']) || $file_post['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file_post['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $max_bytes = 25 * 1024 * 1024;
    if ((int)$file_post['size'] > $max_bytes) {
        return null;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov'];
    $original_name = basename($file_post['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions, true)) {
        return null;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file_post['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        $allowed_mimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/quicktime'
        ];
        if ($mime && !in_array($mime, $allowed_mimes, true)) {
            return null;
        }
    }

    $tipo = in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video' : 'imagem';
    $safe_name = 'media_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $upload_dir . '/' . $safe_name;

    if (!move_uploaded_file($file_post['tmp_name'], $target_path)) {
        return null;
    }

    $bytes = (int)$file_post['size'];
    $formatted_size = ($bytes >= 1048576)
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int)round($bytes / 1024)) . ' KB';

    return [
        'nome_original' => $original_name,
        'caminho' => 'uploads/' . $safe_name,
        'tipo' => $tipo,
        'tamanho_formatado' => $formatted_size
    ];
}

// ============================================================================
// ENDPOINT REST JSON (api.php)
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'api.php') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');

    $auth_config = load_auth_config($AUTH_FILE);
    $supabase_active = is_supabase_configured($ENV);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    $json_body = [];
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') !== false) {
        $raw_input = file_get_contents('php://input');
        if ($raw_input) {
            $parsed = json_decode($raw_input, true);
            if (is_array($parsed)) {
                $json_body = $parsed;
                if ($action === '' && isset($json_body['action'])) {
                    $action = (string)$json_body['action'];
                }
            }
        }
    }

    $param = function(string $key, $default = '') use ($json_body) {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($json_body[$key])) return $json_body[$key];
        if (isset($_GET[$key])) return $_GET[$key];
        return $default;
    };

    // 1. Estado inicial da sessão e dados
    if ($method === 'GET' && ($action === 'state' || $action === '')) {
        $auth_user = get_authenticated_user($auth_config, $ENV);
        $public_sb_url = $supabase_active ? trim($ENV['SUPABASE_URL'] ?? '') : '';
        $public_sb_key = $supabase_active ? get_public_supabase_key($ENV) : '';

        if ($auth_user === null) {
            echo json_encode([
                'authenticated' => false,
                'require_auth' => true,
                'supabase_configured' => $supabase_active,
                'supabase_url' => $public_sb_url,
                'supabase_publishable_key' => $public_sb_key,
                'is_default_password' => (bool)($auth_config['is_default_password'] ?? false),
                'csrf_token' => get_csrf_token()
            ]);
            exit;
        }

        $data = load_app_data($DATA_FILE);
        echo json_encode([
            'authenticated' => true,
            'require_auth' => true,
            'user' => $auth_user,
            'admin_email' => $auth_user['email'],
            'supabase_configured' => $supabase_active,
            'supabase_url' => $public_sb_url,
            'supabase_publishable_key' => $public_sb_key,
            'is_default_password' => (bool)($auth_config['is_default_password'] ?? false),
            'csrf_token' => get_csrf_token(),
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // 2. Login exclusivo com E-mail e Senha configurados no .env
    if ($method === 'POST' && $action === 'login') {
        $now = time();
        if (($auth_config['lockout_until'] ?? 0) > $now) {
            $wait = (int)$auth_config['lockout_until'] - $now;
            http_response_code(429);
            echo json_encode([
                'ok' => false,
                'error' => "Muitas tentativas falhas. Aguarde {$wait} segundos por segurança."
            ]);
            exit;
        }

        $email = trim((string)$param('email', ''));
        $password = (string)$param('password', (string)$param('credential', ''));
        $admin_email = trim($ENV['ADMIN_EMAIL'] ?? 'lima7emilly12@gmail.com');

        // Bloqueio rigoroso: Apenas o e-mail do proprietário pode acessar
        if (mb_strtolower($email, 'UTF-8') !== mb_strtolower($admin_email, 'UTF-8')) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'error' => 'Acesso negado: Plataforma de uso exclusivo do administrador.'
            ]);
            exit;
        }

        $auth_success = false;

        // 1. Tenta autenticar pelo Supabase Auth
        if ($supabase_active) {
            $sb_auth = supabase_auth_sign_in($ENV, $email, $password);
            if ($sb_auth['ok']) {
                $auth_success = true;
                $_SESSION['supabase_access_token'] = $sb_auth['session']['access_token'] ?? '';
                $_SESSION['supabase_user_id'] = $sb_auth['session']['user']['id'] ?? '';
            }
        }

        // 2. Fallback para verificação local de credenciais
        if (!$auth_success && verify_admin_credentials($email, $password, $auth_config, $ENV)) {
            $auth_success = true;
        }

        if ($auth_success) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['admin_email'] = $email;
            $_SESSION['login_at'] = $now;
            $auth_config['failed_attempts'] = 0;
            $auth_config['lockout_until'] = 0;
            save_auth_config($AUTH_FILE, $auth_config);

            $data = load_app_data($DATA_FILE);
            echo json_encode([
                'ok' => true,
                'authenticated' => true,
                'admin_email' => $email,
                'supabase_configured' => $supabase_active,
                'is_default_password' => (bool)($auth_config['is_default_password'] ?? false),
                'csrf_token' => get_csrf_token(),
                'projetos' => $data['projetos'],
                'tarefas' => $data['tarefas']
            ]);
            exit;
        }

        $auth_config['failed_attempts'] = (int)($auth_config['failed_attempts'] ?? 0) + 1;
        if ($auth_config['failed_attempts'] >= 5) {
            $auth_config['lockout_until'] = $now + 180; // Bloqueia por 3 minutos após 5 erros
            $auth_config['failed_attempts'] = 0;
        }
        save_auth_config($AUTH_FILE, $auth_config);

        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Acesso negado: E-mail ou senha inválidos.'
        ]);
        exit;
    }

    // 3. Logout / Trancar Sessão
    if ($method === 'POST' && $action === 'logout') {
        $_SESSION['authenticated'] = false;
        unset($_SESSION['admin_email']);
        session_regenerate_id(true);
        echo json_encode([
            'ok' => true,
            'authenticated' => false,
            'csrf_token' => get_csrf_token()
        ]);
        exit;
    }

    $current_auth_user = get_authenticated_user($auth_config, $ENV);

    // Bloqueia qualquer outra ação se não estiver autenticado
    if ($current_auth_user === null) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Sessão expirada ou bloqueada. Faça login novamente.']);
        exit;
    }

    // Validação de token CSRF em mutações POST baseadas em cookie (ou Bearer JWT validado do Supabase)
    if ($method === 'POST' && ($current_auth_user['provider'] ?? '') !== 'supabase') {
        $header_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $body_csrf = (string)$param('csrf_token', '');
        $token_to_check = $header_csrf ?: $body_csrf;
        if (!verify_csrf_token($token_to_check)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token de segurança (CSRF) inválido. Recarregue a página.']);
            exit;
        }
    }

    // Salvar Preferências Visuais (Tema, Fonte, Escala)
    if ($method === 'POST' && $action === 'save_preferences') {
        $p_theme = (string)$param('theme', 'dark');
        if (!in_array($p_theme, ['dark', 'light'], true)) {
            $p_theme = 'dark';
        }

        $p_font_size = (string)$param('font_size', 'medium');
        if (!in_array($p_font_size, ['small', 'medium', 'large'], true)) {
            $p_font_size = 'medium';
        }

        $p_font_family = (string)$param('font_family', 'Roboto');
        if (!in_array($p_font_family, ['Helvetica / Arial', 'Montserrat', 'Roboto', 'Open Sans / Lato'], true)) {
            $p_font_family = 'Roboto';
        }

        // Salva na sessão PHP
        $_SESSION['app_theme'] = $p_theme;
        $_SESSION['app_font_size'] = $p_font_size;
        $_SESSION['app_font_family'] = $p_font_family;

        // Define cookies com 1 ano de validade
        $cookie_opts = [
            'expires' => time() + 31536000,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax'
        ];
        setcookie('app_theme', $p_theme, $cookie_opts);
        setcookie('app_font_size', $p_font_size, $cookie_opts);
        setcookie('app_font_family', rawurlencode($p_font_family), $cookie_opts);

        echo json_encode([
            'ok' => true,
            'message' => 'Preferências visuais salvas com sucesso!',
            'theme' => $p_theme,
            'font_size' => $p_font_size,
            'font_family' => $p_font_family
        ]);
        exit;
    }

    // Exportar Backup JSON
    if ($action === 'export_backup') {
        $data = load_app_data($DATA_FILE);
        header('Content-Disposition: attachment; filename="backlog_backup_' . date('Y-m-d_His') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Sincronizar dados locais existentes para o Supabase
    if ($method === 'POST' && $action === 'sync_supabase') {
        $local_raw = file_exists($DATA_FILE) ? json_decode((string)file_get_contents($DATA_FILE), true) : ['projetos' => [], 'tarefas' => []];
        $sync_res = sync_all_to_supabase($ENV, is_array($local_raw) ? $local_raw : ['projetos' => [], 'tarefas' => []]);
        if (!$sync_res['ok']) {
            http_response_code(400);
            echo json_encode($sync_res);
            exit;
        }
        $fresh = load_app_data($DATA_FILE);
        echo json_encode([
            'ok' => true,
            'message' => 'Todos os projetos e ideias foram sincronizados com o Supabase!',
            'projetos' => $fresh['projetos'],
            'tarefas' => $fresh['tarefas']
        ]);
        exit;
    }

    $data = load_app_data($DATA_FILE);

    // Criar Pasta de Projeto
    if ($method === 'POST' && $action === 'create_project') {
        $nome = trim((string)$param('nome', ''));
        $descricao = trim((string)$param('descricao', ''));
        $cor = trim((string)$param('cor', 'blue'));
        if (!in_array($cor, ['blue', 'emerald', 'purple', 'amber', 'rose', 'cyan'], true)) {
            $cor = 'blue';
        }

        if ($nome === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'O nome da pasta é obrigatório.']);
            exit;
        }

        $new_proj = [
            'id' => 'proj_' . bin2hex(random_bytes(4)),
            'nome' => $nome,
            'descricao' => $descricao,
            'cor' => $cor,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        if ($supabase_active) {
            $sb_proj = $new_proj;
            if (($current_auth_user['provider'] ?? '') === 'supabase') {
                $sb_proj['user_id'] = $current_auth_user['id'];
            }
            supabase_request($ENV, 'POST', 'projetos', '', $sb_proj);
        }

        $data['projetos'][] = $new_proj;
        save_local_json($DATA_FILE, $data);

        echo json_encode([
            'ok' => true,
            'project' => $new_proj,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Excluir Pasta de Projeto
    if ($method === 'POST' && $action === 'delete_project') {
        $project_id = trim((string)$param('project_id', ''));
        if ($project_id !== '') {
            if ($supabase_active) {
                supabase_request($ENV, 'DELETE', 'projetos', 'id=eq.' . rawurlencode($project_id));
            }
            $data['projetos'] = array_values(array_filter($data['projetos'], fn($p) => $p['id'] !== $project_id));
            $data['tarefas'] = array_values(array_filter($data['tarefas'], fn($t) => $t['id_projeto'] !== $project_id));
            save_local_json($DATA_FILE, $data);
        }
        echo json_encode([
            'ok' => true,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Criar Nova Ideia / Tarefa
    if ($method === 'POST' && $action === 'create_task') {
        $titulo = trim((string)$param('titulo', ''));
        $descricao_detalhada = trim((string)$param('descricao_detalhada', ''));
        $prioridade = (string)$param('prioridade', 'Média');
        $status = (string)$param('status', 'pendente');
        $data_limite = trim((string)$param('data_limite', ''));
        $id_projeto = trim((string)$param('id_projeto', ''));

        if ($titulo === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'O título da ideia é obrigatório.']);
            exit;
        }

        if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'], true)) {
            $prioridade = 'Média';
        }
        if (!in_array($status, ['pendente', 'em_andamento', 'concluido'], true)) {
            $status = 'pendente';
        }
        if ($id_projeto === '' || $id_projeto === 'all') {
            $id_projeto = !empty($data['projetos']) ? $data['projetos'][0]['id'] : 'proj_default';
        }

        $anexo = null;
        if (isset($_FILES['anexo'])) {
            $anexo = handle_secure_media_upload($_FILES['anexo'], $UPLOAD_DIR);
        }

        $new_task = [
            'id' => 'task_' . bin2hex(random_bytes(5)),
            'id_projeto' => $id_projeto,
            'titulo' => $titulo,
            'descricao_detalhada' => $descricao_detalhada,
            'prioridade' => $prioridade,
            'status' => $status,
            'data_limite' => $data_limite,
            'criado_em' => date('Y-m-d H:i:s'),
            'anexo' => $anexo
        ];

        if ($supabase_active) {
            $sb_task = $new_task;
            $sb_task['data_limite'] = $data_limite !== '' ? $data_limite : null;
            if (($current_auth_user['provider'] ?? '') === 'supabase') {
                $sb_task['user_id'] = $current_auth_user['id'];
            }
            supabase_request($ENV, 'POST', 'tarefas', '', $sb_task);
        }

        array_unshift($data['tarefas'], $new_task);
        save_local_json($DATA_FILE, $data);

        echo json_encode([
            'ok' => true,
            'task' => $new_task,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Atualizar Status (Kanban Drag & Drop ou Botão Rápido)
    if ($method === 'POST' && ($action === 'update_status' || $action === 'toggle_status')) {
        $task_id = trim((string)$param('task_id', ''));
        $new_status = trim((string)$param('status', ''));

        foreach ($data['tarefas'] as &$task) {
            if ($task['id'] === $task_id) {
                if (in_array($new_status, ['pendente', 'em_andamento', 'concluido'], true)) {
                    $task['status'] = $new_status;
                } else {
                    $task['status'] = ($task['status'] === 'concluido') ? 'pendente' : 'concluido';
                }

                if ($supabase_active) {
                    supabase_request(
                        $ENV,
                        'PATCH',
                        'tarefas',
                        'id=eq.' . rawurlencode($task_id),
                        ['status' => $task['status']]
                    );
                }
                break;
            }
        }
        unset($task);
        save_local_json($DATA_FILE, $data);

        echo json_encode([
            'ok' => true,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Atualizar Tarefa Completa
    if ($method === 'POST' && $action === 'update_task') {
        $task_id = trim((string)$param('task_id', ''));
        $titulo = trim((string)$param('titulo', ''));
        $descricao_detalhada = trim((string)$param('descricao_detalhada', ''));
        $prioridade = (string)$param('prioridade', 'Média');
        $status = (string)$param('status', '');
        $data_limite = trim((string)$param('data_limite', ''));
        $id_projeto = trim((string)$param('id_projeto', ''));
        $remover_anexo = (string)$param('remover_anexo', '0') === '1';

        if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'], true)) {
            $prioridade = 'Média';
        }

        if ($task_id !== '' && $titulo !== '') {
            foreach ($data['tarefas'] as &$task) {
                if ($task['id'] === $task_id) {
                    $task['titulo'] = $titulo;
                    $task['descricao_detalhada'] = $descricao_detalhada;
                    $task['prioridade'] = $prioridade;
                    $task['data_limite'] = $data_limite;
                    if ($id_projeto !== '') {
                        $task['id_projeto'] = $id_projeto;
                    }
                    if (in_array($status, ['pendente', 'em_andamento', 'concluido'], true)) {
                        $task['status'] = $status;
                    }
                    if ($remover_anexo) {
                        $task['anexo'] = null;
                    }
                    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
                        $novo_anexo = handle_secure_media_upload($_FILES['anexo'], $UPLOAD_DIR);
                        if ($novo_anexo) {
                            $task['anexo'] = $novo_anexo;
                        }
                    }

                    if ($supabase_active) {
                        supabase_request(
                            $ENV,
                            'PATCH',
                            'tarefas',
                            'id=eq.' . rawurlencode($task_id),
                            [
                                'id_projeto' => $task['id_projeto'],
                                'titulo' => $task['titulo'],
                                'descricao_detalhada' => $task['descricao_detalhada'],
                                'prioridade' => $task['prioridade'],
                                'status' => $task['status'],
                                'data_limite' => $task['data_limite'] !== '' ? $task['data_limite'] : null,
                                'anexo' => $task['anexo']
                            ]
                        );
                    }
                    break;
                }
            }
            unset($task);
            save_local_json($DATA_FILE, $data);
        }

        echo json_encode([
            'ok' => true,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Excluir Tarefa
    if ($method === 'POST' && $action === 'delete_task') {
        $task_id = trim((string)$param('task_id', ''));
        if ($task_id !== '') {
            if ($supabase_active) {
                supabase_request($ENV, 'DELETE', 'tarefas', 'id=eq.' . rawurlencode($task_id));
            }
            $data['tarefas'] = array_values(array_filter($data['tarefas'], fn($t) => $t['id'] !== $task_id));
            save_local_json($DATA_FILE, $data);
        }
        echo json_encode([
            'ok' => true,
            'projetos' => $data['projetos'],
            'tarefas' => $data['tarefas']
        ]);
        exit;
    }

    // Importar Backup JSON
    if ($method === 'POST' && $action === 'import_backup') {
        $backup_json = (string)$param('backup_json', '');
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $backup_json = (string)file_get_contents($_FILES['backup_file']['tmp_name']);
        }
        $decoded = json_decode($backup_json, true);
        if (!is_array($decoded) || !isset($decoded['projetos']) || !isset($decoded['tarefas'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Arquivo de backup inválido.']);
            exit;
        }
        $normalized = normalize_app_data($decoded['projetos'], $decoded['tarefas']);
        save_local_json($DATA_FILE, $normalized);
        if ($supabase_active) {
            sync_all_to_supabase($ENV, $normalized);
        }
        $fresh = load_app_data($DATA_FILE);
        echo json_encode([
            'ok' => true,
            'projetos' => $fresh['projetos'],
            'tarefas' => $fresh['tarefas']
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ação desconhecida.']);
    exit;
}
