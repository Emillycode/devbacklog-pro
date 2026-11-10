<?php
/**
 * Test script for PHP data storage, .env exclusive auth, and Supabase / Kanban rules
 */

declare(strict_types=1);

require_once __DIR__ . '/../api.php';

$test_file = __DIR__ . '/test_data.json';
$test_auth_file = __DIR__ . '/test_auth.json';
$test_env_file = __DIR__ . '/test.env';

if (file_exists($test_file)) unlink($test_file);
if (file_exists($test_auth_file)) unlink($test_auth_file);
if (file_exists($test_env_file)) unlink($test_env_file);

// 1. Test .env loading and exclusive email + password authentication
file_put_contents($test_env_file, "ADMIN_EMAIL=\"dev@meusoftware.com\"\nADMIN_PASSWORD=\"MinhaSenhaForte#2026\"\nSUPABASE_URL=\"https://xyzcompany.supabase.co\"\nSUPABASE_KEY=\"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9\"\n");

$parsed_env = load_env_config($test_env_file);
assert($parsed_env['ADMIN_EMAIL'] === 'dev@meusoftware.com', 'Env email mismatch');
assert(is_supabase_configured($parsed_env) === true, 'Supabase should be detected as configured');

$auth = load_auth_config($test_auth_file);
assert(verify_admin_credentials('dev@meusoftware.com', 'MinhaSenhaForte#2026', $auth, $parsed_env) === true, 'Valid admin email+password should pass');
assert(verify_admin_credentials('outro@meusoftware.com', 'MinhaSenhaForte#2026', $auth, $parsed_env) === false, 'Wrong email must be rejected');
assert(verify_admin_credentials('dev@meusoftware.com', 'SenhaErrada', $auth, $parsed_env) === false, 'Wrong password must be rejected');

// 2. Test CSRF token generation and verification
$token = get_csrf_token();
assert(strlen($token) === 64, 'CSRF token should be 64 hex chars');
assert(verify_csrf_token($token) === true, 'Valid CSRF token should pass');
assert(verify_csrf_token('invalid_token') === false, 'Invalid CSRF token should fail');

// 3. Test App Data with Kanban statuses and Folder colors
$initial_data = [
    'projetos' => [
        [
            'id' => 'proj_test_1',
            'nome' => 'Projeto Teste Alfa',
            'descricao' => 'Descrição do projeto de teste',
            'cor' => 'emerald',
            'criado_em' => date('Y-m-d H:i:s')
        ]
    ],
    'tarefas' => [
        [
            'id' => 'task_test_1',
            'id_projeto' => 'proj_test_1',
            'titulo' => 'Ideia Teste Kanban',
            'descricao_detalhada' => 'Detalhes da ideia em andamento',
            'prioridade' => 'Alta',
            'status' => 'em_andamento',
            'data_limite' => date('Y-m-d', strtotime('+1 day')),
            'criado_em' => date('Y-m-d H:i:s'),
            'anexo' => null
        ]
    ]
];

assert(save_local_json($test_file, $initial_data) === true, 'Failed to save local JSON');
$loaded = normalize_app_data($initial_data['projetos'], $initial_data['tarefas']);
assert(count($loaded['projetos']) === 1, 'Project count mismatch');
assert($loaded['projetos'][0]['cor'] === 'emerald', 'Project color mismatch');
assert($loaded['tarefas'][0]['status'] === 'em_andamento', 'Kanban status mismatch');

// Clean up
unlink($test_file);
unlink($test_auth_file);
unlink($test_env_file);

echo "All .env Exclusive Auth, Supabase Config & Kanban Backend Tests Passed Successfully!\n";
