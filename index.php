<?php
/**
 * Backlog & Ideias - Plataforma Web Segura & Visual (WAMP + API Unificada)
 * Integrado ao core de segurança e persistência (api.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/api.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$auth_config = load_auth_config($AUTH_FILE);
$csrf_token = get_csrf_token();

// Configurações Visuais via Cookie
$theme = $_COOKIE['app_theme'] ?? 'dark';
if (!in_array($theme, ['dark', 'light'], true)) {
    $theme = 'dark';
}

$font_size = $_COOKIE['app_font_size'] ?? 'medium';
if (!in_array($font_size, ['small', 'medium', 'large'], true)) {
    $font_size = 'medium';
}

$font_family = $_COOKIE['app_font_family'] ?? 'Roboto';
if (!in_array($font_family, ['Helvetica / Arial', 'Montserrat', 'Roboto', 'Open Sans / Lato'], true)) {
    $font_family = 'Roboto';
}

$view_mode = $_GET['view'] ?? ($_COOKIE['app_view_mode'] ?? 'kanban');
if (!in_array($view_mode, ['kanban', 'list'], true)) {
    $view_mode = 'kanban';
}
if (isset($_GET['view'])) {
    setcookie('app_view_mode', $view_mode, time() + 31536000, '/');
}

function h(?string $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function get_due_status(?string $date_str, string $task_status): array {
    if (empty($date_str)) {
        return [
            'status' => 'none',
            'label' => 'Sem prazo',
            'is_urgent' => false,
            'badge_class' => 'text-zinc-500 bg-zinc-100 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800'
        ];
    }

    if ($task_status === 'concluido') {
        return [
            'status' => 'none',
            'label' => 'Concluído',
            'is_urgent' => false,
            'badge_class' => 'text-zinc-400 bg-zinc-100 dark:bg-zinc-900/40 border-zinc-200 dark:border-zinc-800'
        ];
    }

    $today = new DateTime('today');
    $due = DateTime::createFromFormat('Y-m-d', $date_str);
    if (!$due) {
        return [
            'status' => 'none',
            'label' => $date_str,
            'is_urgent' => false,
            'badge_class' => 'text-zinc-500 bg-zinc-100 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800'
        ];
    }
    $due->setTime(0, 0, 0);

    $interval = $today->diff($due);
    $days = (int)$interval->format('%r%a');

    if ($days < 0) {
        $abs = abs($days);
        return [
            'status' => 'expired',
            'label' => ($abs === 1) ? 'Venceu ontem' : "Venceu há {$abs} dias",
            'is_urgent' => true,
            'badge_class' => 'text-rose-700 bg-rose-50 border-rose-200 dark:text-rose-300 dark:bg-rose-950/60 dark:border-rose-900/60 font-medium'
        ];
    }

    if ($days === 0) {
        return [
            'status' => 'today',
            'label' => 'Vence hoje',
            'is_urgent' => true,
            'badge_class' => 'text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-300 dark:bg-amber-950/60 dark:border-amber-900/50 font-medium'
        ];
    }

    if ($days === 1) {
        return [
            'status' => 'soon',
            'label' => 'Vence amanhã',
            'is_urgent' => true,
            'badge_class' => 'text-sky-700 bg-sky-50 border-sky-200 dark:text-sky-300 dark:bg-sky-950/60 dark:border-sky-900/50'
        ];
    }

    if ($days <= 3) {
        return [
            'status' => 'soon',
            'label' => "Em {$days} dias",
            'is_urgent' => false,
            'badge_class' => 'text-sky-700 bg-sky-50 border-sky-200 dark:text-sky-300 dark:bg-sky-950/60 dark:border-sky-900/50'
        ];
    }

    return [
        'status' => 'future',
        'label' => date('d/m/Y', strtotime($date_str)),
        'is_urgent' => false,
        'badge_class' => 'text-zinc-600 bg-zinc-100 border-zinc-200 dark:text-zinc-400 dark:bg-zinc-900/60 dark:border-zinc-800'
    ];
}

$login_error = '';
$sec_feedback = $_SESSION['sec_feedback'] ?? null;
unset($_SESSION['sec_feedback']);
$supabase_active = is_supabase_configured($ENV);

// ==========================================
// CONTROLLER: PROCESSAMENTO DE FORMULÁRIOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_project = $_POST['current_project'] ?? 'all';
    $redirect_filter = $_POST['current_filter'] ?? 'all';

    // Login exclusivo por E-mail e Senha configurados no .env
    if ($action === 'login') {
        $now = time();
        if (($auth_config['lockout_until'] ?? 0) > $now) {
            $wait = (int)$auth_config['lockout_until'] - $now;
            $login_error = "Muitas tentativas falhas. Aguarde {$wait} segundos por segurança.";
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (verify_admin_credentials($email, $password, $auth_config, $ENV)) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['admin_email'] = $email;
                $_SESSION['login_at'] = $now;
                $auth_config['failed_attempts'] = 0;
                $auth_config['lockout_until'] = 0;
                save_auth_config($AUTH_FILE, $auth_config);
                header("Location: index.php");
                exit;
            } else {
                $auth_config['failed_attempts'] = (int)($auth_config['failed_attempts'] ?? 0) + 1;
                if ($auth_config['failed_attempts'] >= 5) {
                    $auth_config['lockout_until'] = $now + 180;
                    $auth_config['failed_attempts'] = 0;
                }
                save_auth_config($AUTH_FILE, $auth_config);
                $login_error = 'Acesso negado: E-mail ou senha inválidos.';
            }
        }
    }

    // Trancar / Logout
    if ($action === 'logout') {
        $_SESSION['authenticated'] = false;
        unset($_SESSION['admin_email']);
        session_regenerate_id(true);
        header("Location: index.php");
        exit;
    }

    // Demais ações exigem sessão autenticada e CSRF válido
    if ($action !== 'login' && is_authenticated($auth_config)) {
        $posted_csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($posted_csrf)) {
            die('Token de segurança CSRF inválido. Por favor, volte e recarregue a página.');
        }

        $data = load_app_data($DATA_FILE);

        // 1. Criar Pasta de Projeto
        if ($action === 'create_project') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $cor = $_POST['cor'] ?? 'blue';
            if (!in_array($cor, ['blue', 'emerald', 'purple', 'amber', 'rose', 'cyan'], true)) {
                $cor = 'blue';
            }

            if ($nome !== '') {
                $new_project_id = 'proj_' . bin2hex(random_bytes(4));
                $new_proj = [
                    'id' => $new_project_id,
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'cor' => $cor,
                    'criado_em' => date('Y-m-d H:i:s')
                ];
                if ($supabase_active) {
                    supabase_request($ENV, 'POST', 'projetos', '', $new_proj);
                }
                $data['projetos'][] = $new_proj;
                save_local_json($DATA_FILE, $data);
                $redirect_project = $new_project_id;
            }
        }

        // 2. Excluir Pasta
        if ($action === 'delete_project') {
            $project_id = $_POST['project_id'] ?? '';
            if ($project_id !== '') {
                if ($supabase_active) {
                    supabase_request($ENV, 'DELETE', 'projetos', 'id=eq.' . rawurlencode($project_id));
                }
                $data['projetos'] = array_values(array_filter($data['projetos'], fn($p) => $p['id'] !== $project_id));
                $data['tarefas'] = array_values(array_filter($data['tarefas'], fn($t) => $t['id_projeto'] !== $project_id));
                save_local_json($DATA_FILE, $data);
                $redirect_project = 'all';
            }
        }

        // 3. Criar Nova Tarefa / Ideia
        if ($action === 'create_task') {
            $id_projeto = trim($_POST['id_projeto'] ?? '');
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao_detalhada = trim($_POST['descricao_detalhada'] ?? '');
            $prioridade = $_POST['prioridade'] ?? 'Média';
            $status = $_POST['status'] ?? 'pendente';
            $data_limite = trim($_POST['data_limite'] ?? '');

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

            if ($titulo !== '') {
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
                    supabase_request($ENV, 'POST', 'tarefas', '', $sb_task);
                }
                array_unshift($data['tarefas'], $new_task);
                save_local_json($DATA_FILE, $data);
            }
        }

        // 4. Atualizar Status Rápido
        if ($action === 'toggle_status' || $action === 'update_status') {
            $task_id = $_POST['task_id'] ?? '';
            $target_status = $_POST['status'] ?? '';
            foreach ($data['tarefas'] as &$task) {
                if ($task['id'] === $task_id) {
                    if (in_array($target_status, ['pendente', 'em_andamento', 'concluido'], true)) {
                        $task['status'] = $target_status;
                    } else {
                        $task['status'] = ($task['status'] === 'concluido') ? 'pendente' : 'concluido';
                    }
                    if ($supabase_active) {
                        supabase_request($ENV, 'PATCH', 'tarefas', 'id=eq.' . rawurlencode($task_id), ['status' => $task['status']]);
                    }
                    break;
                }
            }
            unset($task);
            save_local_json($DATA_FILE, $data);
        }

        // 5. Atualizar Tarefa Completa
        if ($action === 'update_task') {
            $task_id = $_POST['task_id'] ?? '';
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao_detalhada = trim($_POST['descricao_detalhada'] ?? '');
            $prioridade = $_POST['prioridade'] ?? 'Média';
            $status = $_POST['status'] ?? 'pendente';
            $data_limite = trim($_POST['data_limite'] ?? '');
            $id_projeto = trim($_POST['id_projeto'] ?? '');
            $remover_anexo = isset($_POST['remover_anexo']) && $_POST['remover_anexo'] === '1';

            if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'], true)) {
                $prioridade = 'Média';
            }
            if (!in_array($status, ['pendente', 'em_andamento', 'concluido'], true)) {
                $status = 'pendente';
            }

            if ($task_id !== '' && $titulo !== '') {
                foreach ($data['tarefas'] as &$task) {
                    if ($task['id'] === $task_id) {
                        $task['titulo'] = $titulo;
                        $task['descricao_detalhada'] = $descricao_detalhada;
                        $task['prioridade'] = $prioridade;
                        $task['status'] = $status;
                        $task['data_limite'] = $data_limite;
                        if ($id_projeto !== '') {
                            $task['id_projeto'] = $id_projeto;
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
                            supabase_request($ENV, 'PATCH', 'tarefas', 'id=eq.' . rawurlencode($task_id), [
                                'id_projeto' => $task['id_projeto'],
                                'titulo' => $task['titulo'],
                                'descricao_detalhada' => $task['descricao_detalhada'],
                                'prioridade' => $task['prioridade'],
                                'status' => $task['status'],
                                'data_limite' => $task['data_limite'] !== '' ? $task['data_limite'] : null,
                                'anexo' => $task['anexo']
                            ]);
                        }
                        break;
                    }
                }
                unset($task);
                save_local_json($DATA_FILE, $data);
            }
        }

        // 6. Excluir Tarefa
        if ($action === 'delete_task') {
            $task_id = $_POST['task_id'] ?? '';
            if ($task_id !== '') {
                if ($supabase_active) {
                    supabase_request($ENV, 'DELETE', 'tarefas', 'id=eq.' . rawurlencode($task_id));
                }
                $data['tarefas'] = array_values(array_filter($data['tarefas'], fn($t) => $t['id'] !== $task_id));
                save_local_json($DATA_FILE, $data);
            }
        }

        // 7. Sincronizar com Supabase
        if ($action === 'sync_supabase') {
            $local_raw = file_exists($DATA_FILE) ? json_decode((string)file_get_contents($DATA_FILE), true) : ['projetos' => [], 'tarefas' => []];
            $sync_res = sync_all_to_supabase($ENV, is_array($local_raw) ? $local_raw : ['projetos' => [], 'tarefas' => []]);
            if ($sync_res['ok']) {
                $_SESSION['sec_feedback'] = ['type' => 'ok', 'text' => 'Todas as pastas e ideias foram sincronizadas com o Supabase!'];
            } else {
                $_SESSION['sec_feedback'] = ['type' => 'err', 'text' => $sync_res['error'] ?? 'Falha ao sincronizar com o Supabase.'];
            }
        }

        // 7. Salvar Configurações Visuais
        if ($action === 'save_settings') {
            $new_theme = $_POST['theme'] ?? 'dark';
            $new_font_size = $_POST['font_size'] ?? 'medium';
            $new_font_family = $_POST['font_family'] ?? 'Roboto';

            setcookie('app_theme', $new_theme, time() + 31536000, '/');
            setcookie('app_font_size', $new_font_size, time() + 31536000, '/');
            setcookie('app_font_family', $new_font_family, time() + 31536000, '/');
        }

        // 8. Salvar Configurações de Segurança (Senha / PIN)
        if ($action === 'update_security') {
            $current_pass = trim($_POST['current_password'] ?? '');
            $new_pass = trim($_POST['new_password'] ?? '');
            $new_pin = trim($_POST['new_pin'] ?? '');
            $require_auth = isset($_POST['require_auth']) && $_POST['require_auth'] === '1';

            $valid_current = password_verify($current_pass, $auth_config['password_hash'] ?? '')
                || (!empty($auth_config['pin_hash']) && password_verify($current_pass, $auth_config['pin_hash']));

            if (!$valid_current) {
                $_SESSION['sec_feedback'] = ['type' => 'err', 'text' => 'Senha ou PIN atual incorreto.'];
            } else {
                if ($new_pass !== '' && strlen($new_pass) >= 4) {
                    $auth_config['password_hash'] = password_hash($new_pass, PASSWORD_BCRYPT);
                    $auth_config['is_default_password'] = false;
                }
                if ($new_pin !== '' && preg_match('/^\d{4,8}$/', $new_pin)) {
                    $auth_config['pin_hash'] = password_hash($new_pin, PASSWORD_BCRYPT);
                    $auth_config['is_default_password'] = false;
                }
                $auth_config['require_auth'] = $require_auth;
                $auth_config['updated_at'] = date('Y-m-d H:i:s');
                save_auth_config($AUTH_FILE, $auth_config);
                $_SESSION['sec_feedback'] = ['type' => 'ok', 'text' => 'Credenciais de segurança atualizadas com sucesso!'];
            }
        }

        // 9. Importar Backup JSON
        if ($action === 'import_backup' && isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $raw_json = (string)file_get_contents($_FILES['backup_file']['tmp_name']);
            $decoded = json_decode($raw_json, true);
            if (is_array($decoded) && isset($decoded['projetos']) && isset($decoded['tarefas'])) {
                save_app_data($DATA_FILE, [
                    'projetos' => is_array($decoded['projetos']) ? $decoded['projetos'] : [],
                    'tarefas' => is_array($decoded['tarefas']) ? $decoded['tarefas'] : []
                ]);
                $_SESSION['sec_feedback'] = ['type' => 'ok', 'text' => 'Backup restaurado com sucesso!'];
            } else {
                $_SESSION['sec_feedback'] = ['type' => 'err', 'text' => 'Arquivo JSON de backup inválido.'];
            }
        }

        header("Location: index.php?project_id=" . urlencode($redirect_project) . "&filter=" . urlencode($redirect_filter));
        exit;
    }
}

$font_family_css = match($font_family) {
    'Montserrat' => '"Montserrat", sans-serif',
    'Roboto' => '"Roboto", sans-serif',
    'Open Sans / Lato' => '"Open Sans", "Lato", sans-serif',
    default => '"Helvetica Neue", Helvetica, Arial, sans-serif'
};

$font_size_px = match($font_size) {
    'small' => '13px',
    'large' => '17px',
    default => '15px'
};

$is_user_authenticated = is_authenticated($auth_config);

// Dados se autenticado
$data = $is_user_authenticated ? load_app_data($DATA_FILE) : ['projetos' => [], 'tarefas' => []];

$selected_project_id = $_GET['project_id'] ?? 'all';
if ($selected_project_id === '') {
    $selected_project_id = 'all';
}

$status_filter = $_GET['filter'] ?? 'all';
if (!in_array($status_filter, ['all', 'pendente', 'em_andamento', 'concluido'], true)) {
    $status_filter = 'all';
}

$search_query = trim($_GET['q'] ?? '');

$active_counts_by_project = [];
$total_active_all = 0;
foreach ($data['tarefas'] as $t) {
    if (($t['status'] ?? 'pendente') !== 'concluido') {
        $p_id = $t['id_projeto'] ?? '';
        $active_counts_by_project[$p_id] = ($active_counts_by_project[$p_id] ?? 0) + 1;
        $total_active_all++;
    }
}

$current_project = null;
if ($selected_project_id !== 'all') {
    foreach ($data['projetos'] as $p) {
        if ($p['id'] === $selected_project_id) {
            $current_project = $p;
            break;
        }
    }
}

// Métricas do escopo selecionado
$scope_tasks = array_filter($data['tarefas'], fn($t) => $selected_project_id === 'all' || ($t['id_projeto'] ?? '') === $selected_project_id);
$metric_total = count($scope_tasks);
$metric_pendentes = 0;
$metric_andamento = 0;
$metric_concluidas = 0;
$metric_urgentes = 0;

foreach ($scope_tasks as $st) {
    $s = $st['status'] ?? 'pendente';
    if ($s === 'pendente') $metric_pendentes++;
    elseif ($s === 'em_andamento') $metric_andamento++;
    elseif ($s === 'concluido') $metric_concluidas++;

    if ($s !== 'concluido') {
        $due_check = get_due_status($st['data_limite'] ?? null, $s);
        if (in_array($due_check['status'], ['expired', 'today'], true)) {
            $metric_urgentes++;
        }
    }
}
$metric_progress_pct = $metric_total > 0 ? (int)round(($metric_concluidas / $metric_total) * 100) : 0;

$filtered_tasks = array_filter($data['tarefas'], function($task) use ($selected_project_id, $status_filter, $search_query) {
    if ($selected_project_id !== 'all' && ($task['id_projeto'] ?? '') !== $selected_project_id) {
        return false;
    }
    if ($status_filter !== 'all' && ($task['status'] ?? 'pendente') !== $status_filter) {
        return false;
    }
    if ($search_query !== '') {
        $q = mb_strtolower($search_query);
        $title_match = mb_strpos(mb_strtolower($task['titulo'] ?? ''), $q) !== false;
        $desc_match = mb_strpos(mb_strtolower($task['descricao_detalhada'] ?? ''), $q) !== false;
        if (!$title_match && !$desc_match) {
            return false;
        }
    }
    return true;
});

$folder_colors = [
    'blue' => ['dot' => 'bg-blue-500', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800/50'],
    'emerald' => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/50'],
    'purple' => ['dot' => 'bg-purple-500', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800/50'],
    'amber' => ['dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/50'],
    'rose' => ['dot' => 'bg-rose-500', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/50'],
    'cyan' => ['dot' => 'bg-cyan-500', 'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/50 dark:text-cyan-300 dark:border-cyan-800/50'],
];

/**
 * Renderiza o card de uma tarefa (usado tanto no Kanban quanto na Lista)
 */
function render_task_card_html(array $task, array $projetos, array $folder_colors, string $selected_project_id, string $status_filter, string $csrf_token): void {
    $status = $task['status'] ?? 'pendente';
    $is_done = ($status === 'concluido');
    $is_prog = ($status === 'em_andamento');
    $due = get_due_status($task['data_limite'] ?? null, $status);

    $prio_class = match($task['prioridade'] ?? 'Média') {
        'Alta' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/50',
        'Média' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/50',
        default => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700/60'
    };

    $card_border = match($due['status']) {
        'expired' => 'border-rose-300 bg-rose-50/40 dark:border-rose-900/60 dark:bg-rose-950/15',
        'today' => 'border-amber-300 bg-amber-50/40 dark:border-amber-900/60 dark:bg-amber-950/15',
        default => 'border-light-border bg-light-card hover:border-slate-300 dark:border-dark-border dark:bg-dark-card dark:hover:bg-dark-cardHover shadow-2xs'
    };

    $folder_name = '';
    $folder_cor = 'blue';
    foreach ($projetos as $proj) {
        if ($proj['id'] === ($task['id_projeto'] ?? '')) {
            $folder_name = $proj['nome'];
            $folder_cor = $proj['cor'] ?? 'blue';
            break;
        }
    }
    $f_style = $folder_colors[$folder_cor] ?? $folder_colors['blue'];
    ?>
    <div draggable="true"
         ondragstart="handleDragStart(event, '<?= h($task['id']) ?>')"
         ondragend="handleDragEnd(event)"
         id="task-card-<?= h($task['id']) ?>"
         class="group border rounded-xl p-3.5 transition-all flex flex-col gap-2.5 cursor-grab active:cursor-grabbing <?= $card_border ?>">
        <div class="flex items-start justify-between gap-2.5">
            <div class="flex items-start gap-2.5 flex-1 min-w-0">
                <form method="POST" action="index.php" class="mt-0.5">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="task_id" value="<?= h($task['id']) ?>">
                    <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                    <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">
                    <button type="submit"
                            class="w-4 h-4 rounded border flex items-center justify-center transition-colors cursor-pointer <?= $is_done ? 'bg-emerald-600 border-emerald-600 text-white' : ($is_prog ? 'border-amber-500 bg-amber-500/15 text-amber-500' : 'border-slate-400 hover:border-slate-600 bg-white dark:border-zinc-600 dark:hover:border-zinc-400 dark:bg-dark-input') ?>"
                            title="<?= $is_done ? 'Reabrir ideia' : 'Concluir ideia' ?>">
                        <?php if ($is_done): ?>
                            <svg class="w-3 h-3 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        <?php elseif ($is_prog): ?>
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <?php endif; ?>
                    </button>
                </form>

                <div class="flex-1 min-w-0">
                    <div class="font-semibold tracking-tight leading-snug <?= $is_done ? 'line-through text-slate-400 dark:text-zinc-500' : 'text-slate-900 dark:text-zinc-100' ?>">
                        <?= h($task['titulo']) ?>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                        <span class="text-[10px] px-1.5 py-0.5 rounded border uppercase tracking-wider font-mono <?= $prio_class ?>">
                            <?= h($task['prioridade']) ?>
                        </span>

                        <?php if (!empty($task['data_limite'])): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded border font-mono flex items-center gap-1 <?= $due['badge_class'] ?>">
                                <svg class="w-2.5 h-2.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z"></path></svg>
                                <?= h($due['label']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($selected_project_id === 'all' && $folder_name !== ''): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded border flex items-center gap-1.5 <?= $f_style['badge'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $f_style['dot'] ?>"></span>
                                <span class="truncate max-w-[120px]"><?= h($folder_name) ?></span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($task['descricao_detalhada'])): ?>
                        <p class="mt-2 leading-relaxed text-xs <?= $is_done ? 'line-through text-slate-400 dark:text-zinc-600' : 'text-slate-600 dark:text-zinc-400' ?>">
                            <?= nl2br(h($task['descricao_detalhada'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($task['anexo'])): ?>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-zinc-800/80">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button"
                                        onclick="toggleTaskMedia('<?= h($task['id']) ?>')"
                                        class="text-xs px-2.5 py-1 rounded-lg border font-medium bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:border-zinc-700 dark:text-zinc-300 flex items-center gap-1.5 cursor-pointer transition-colors">
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span id="btn-text-<?= h($task['id']) ?>">Anexo</span>
                                    <span class="text-[10px] opacity-75 font-mono truncate max-w-[110px]">(<?= h($task['anexo']['nome_original']) ?>)</span>
                                </button>
                                <span class="text-[10px] text-zinc-500 font-mono"><?= h($task['anexo']['tamanho_formatado'] ?? '') ?></span>
                            </div>

                            <div id="media-box-<?= h($task['id']) ?>" class="hidden mt-2 p-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-[#eef2f6] dark:bg-[#101012]">
                                <?php if ($task['anexo']['tipo'] === 'imagem'): ?>
                                    <div class="relative inline-block">
                                        <img src="<?= h($task['anexo']['caminho']) ?>"
                                             alt="<?= h($task['anexo']['nome_original']) ?>"
                                             onclick="openImageLightbox('<?= h($task['anexo']['caminho']) ?>')"
                                             class="max-h-52 rounded-lg object-contain border border-slate-300 dark:border-zinc-700 bg-black/5 dark:bg-black/40 cursor-pointer hover:opacity-95 transition-opacity">
                                    </div>
                                <?php elseif ($task['anexo']['tipo'] === 'video'): ?>
                                    <video src="<?= h($task['anexo']['caminho']) ?>" controls playsinline class="w-full max-h-56 rounded-lg border border-slate-300 dark:border-zinc-700 bg-black"></video>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Rodapé do Card com Ações Rápidas de Etapa -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mt-2.5 pt-2 border-t border-slate-200/50 dark:border-zinc-800/50">
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-mono">
                            <?= date('d/m/Y H:i', strtotime($task['criado_em'])) ?>
                        </span>

                        <div class="flex items-center gap-1">
                            <?php if ($status === 'pendente'): ?>
                                <button type="button"
                                        onclick="quickMoveStatus('<?= h($task['id']) ?>', 'em_andamento')"
                                        class="text-[10px] px-2 py-0.5 rounded-md border bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/30 dark:hover:bg-amber-900/50 dark:text-amber-300 dark:border-amber-800/50 flex items-center gap-1 cursor-pointer">
                                    <span>▶ Iniciar</span>
                                </button>
                            <?php elseif ($status === 'em_andamento'): ?>
                                <button type="button"
                                        onclick="quickMoveStatus('<?= h($task['id']) ?>', 'pendente')"
                                        class="text-[10px] px-1.5 py-0.5 rounded-md border bg-slate-100 hover:bg-slate-200 text-slate-600 border-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-700 cursor-pointer"
                                        title="Voltar para Pendente">
                                    ←
                                </button>
                                <button type="button"
                                        onclick="quickMoveStatus('<?= h($task['id']) ?>', 'concluido')"
                                        class="text-[10px] px-2 py-0.5 rounded-md border bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 dark:text-emerald-300 dark:border-emerald-800/50 flex items-center gap-1 cursor-pointer">
                                    <span>✓ Concluir</span>
                                </button>
                            <?php else: ?>
                                <button type="button"
                                        onclick="quickMoveStatus('<?= h($task['id']) ?>', 'pendente')"
                                        class="text-[10px] px-2 py-0.5 rounded-md border bg-slate-100 hover:bg-slate-200 text-slate-600 border-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-400 dark:border-zinc-700 cursor-pointer">
                                    ↺ Reabrir
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões Editar e Excluir -->
            <div class="flex items-center gap-0.5 shrink-0">
                <button type="button"
                        onclick='openEditTaskModal(<?= json_encode($task, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                        class="p-1.5 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-200 dark:text-zinc-400 dark:hover:text-zinc-200 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                        title="Editar ideia">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>

                <form method="POST" action="index.php" onsubmit="return confirm('Excluir esta ideia de backlog?');">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="task_id" value="<?= h($task['id']) ?>">
                    <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                    <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">
                    <button type="submit"
                            class="p-1.5 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:text-zinc-500 dark:hover:text-rose-400 dark:hover:bg-rose-950/30 transition-colors cursor-pointer"
                            title="Excluir ideia">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $theme === 'dark' ? 'dark' : '' ?>" style="font-size: <?= $font_size_px ?>;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backlog & Ideias — Plataforma Segura & Kanban</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Open+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            base: '#09090b',
                            panel: '#121215',
                            card: '#18181c',
                            cardHover: '#1f1f25',
                            border: '#27272a',
                            borderSubtle: '#1f1f23',
                            input: '#151518',
                        },
                        light: {
                            base: '#eaedf1',
                            panel: '#f4f6f8',
                            card: '#ffffff',
                            cardHover: '#f8fafc',
                            border: '#d5dbe1',
                            borderSubtle: '#e2e8f0',
                            input: '#f8fafc',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        html { font-size: <?= $font_size_px ?> !important; }
        body, button, input, select, textarea {
            font-family: <?= $font_family_css ?> !important;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        .dark ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    </style>
</head>
<body class="bg-light-base dark:bg-dark-base text-slate-800 dark:text-zinc-200 min-h-screen flex flex-col antialiased">

<?php if (!$is_user_authenticated): ?>
    <!-- TELA DE BLOQUEIO / LOGIN EXCLUSIVO COM E-MAIL E SENHA -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border bg-white dark:bg-dark-panel border-light-border dark:border-dark-border p-6 sm:p-8 shadow-2xl">
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-12 h-12 rounded-2xl bg-blue-600/15 border border-blue-500/30 flex items-center justify-center text-blue-500 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-zinc-100">Acesso Restrito — Backlog & Ideias</h1>
                <p class="text-xs text-zinc-500 mt-1">Acesso exclusivo com o E-mail e Senha configurados no seu arquivo seguro <code class="font-mono">.env</code>.</p>
            </div>

            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-500 mb-1.5">E-mail Autorizado</label>
                    <input type="email" name="email" autofocus required placeholder="seu_email@dominio.com"
                           class="w-full text-sm px-4 py-2.5 rounded-xl border bg-slate-50 dark:bg-dark-card border-slate-300 dark:border-dark-border text-slate-900 dark:text-zinc-100 focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-500 mb-1.5">Senha de Acesso</label>
                    <input type="password" name="password" required placeholder="Digite sua senha..."
                           class="w-full text-sm px-4 py-2.5 rounded-xl border bg-slate-50 dark:bg-dark-card border-slate-300 dark:border-dark-border text-slate-900 dark:text-zinc-100 focus:outline-none focus:border-blue-500">
                </div>

                <?php if ($login_error !== ''): ?>
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 text-xs">
                        <?= h($login_error) ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-md transition-colors cursor-pointer">
                    Entrar na Plataforma
                </button>

                <div class="p-3 rounded-xl border bg-slate-100 dark:bg-zinc-900/80 border-slate-200 dark:border-zinc-800 text-[11px] text-slate-600 dark:text-zinc-400 leading-relaxed">
                    <strong class="text-blue-500 block mb-0.5">Segurança de Credenciais (.env + .gitignore):</strong>
                    Suas credenciais (<code class="font-mono">ADMIN_EMAIL</code> e <code class="font-mono">ADMIN_PASSWORD</code>) e chaves do Supabase ficam isoladas exclusivamente no arquivo local <code class="font-mono">.env</code>, protegido contra acesso web via <code class="font-mono">.htaccess</code> e ignorado pelo Git.
                </div>
            </form>
        </div>
    </div>
<?php else: ?>

    <!-- Top Header -->
    <header class="h-14 border-b border-light-border dark:border-dark-border bg-light-panel dark:bg-dark-panel flex items-center justify-between px-3 sm:px-4 lg:px-6 sticky top-0 z-20 transition-colors">
        <div class="flex items-center gap-2 sm:gap-3">
            <button id="mobile-menu-btn" class="md:hidden p-2 rounded text-slate-600 dark:text-zinc-400 hover:bg-slate-200 dark:hover:bg-dark-card">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>

            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-600/15 border border-blue-500/30 flex items-center justify-center text-blue-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                </div>
                <div>
                    <h1 class="font-bold text-xs sm:text-sm tracking-tight text-slate-900 dark:text-zinc-100 leading-none">Backlog & Ideias</h1>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full <?= $supabase_active ? 'bg-emerald-500' : 'bg-blue-500' ?>"></span>
                        <span class="text-[10px] text-zinc-500 font-mono hidden xs:inline">
                            <?= $supabase_active ? 'Supabase Cloud Ativo' : 'Backend Seguro (.env)' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Seletor de Visão: Kanban vs Lista -->
            <div class="hidden sm:flex items-center p-0.5 rounded-lg border bg-slate-200/80 dark:bg-zinc-900 border-slate-300 dark:border-zinc-800 ml-3">
                <a href="index.php?project_id=<?= urlencode($selected_project_id) ?>&filter=<?= urlencode($status_filter) ?>&view=kanban"
                   class="px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition-all <?= $view_mode === 'kanban' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-2xs' : 'text-zinc-500 hover:text-zinc-300' ?>">
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    <span>Kanban</span>
                </a>
                <a href="index.php?project_id=<?= urlencode($selected_project_id) ?>&filter=<?= urlencode($status_filter) ?>&view=list"
                   class="px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition-all <?= $view_mode === 'list' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-2xs' : 'text-zinc-500 hover:text-zinc-300' ?>">
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    <span>Lista</span>
                </a>
            </div>
        </div>

        <!-- Direita: Backup, Trancar Tela e Engrenagem -->
        <div class="flex items-center gap-2 ml-auto">
            <a href="api.php?action=export_backup"
               class="text-xs px-2.5 py-1.5 rounded-lg bg-white dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 border border-light-border dark:border-dark-border hidden md:flex items-center gap-1.5 transition-colors"
               title="Exportar cópia de segurança (JSON)">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Backup</span>
            </a>

            <form method="POST" action="index.php" class="inline">
                <input type="hidden" name="action" value="logout">
                <button type="submit"
                        class="text-xs px-2.5 py-1.5 rounded-lg bg-white dark:bg-zinc-900 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-700 dark:text-zinc-300 hover:text-rose-600 dark:hover:text-rose-300 border border-light-border dark:border-dark-border flex items-center gap-1.5 transition-colors cursor-pointer"
                        title="Trancar tela imediatamente">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <span class="hidden sm:inline">Trancar</span>
                </button>
            </form>

            <button onclick="openModal('settings-modal')"
                    class="p-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-zinc-900 text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-center transition-colors cursor-pointer group"
                    title="Configurações Visuais, Senha/PIN e Backups">
                <svg class="w-4 h-4 text-zinc-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </button>
        </div>
    </header>

    <!-- PAINEL PRINCIPAL -->
    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar de Pastas -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-72 border-r border-light-border dark:border-dark-border bg-light-panel dark:bg-dark-panel transform -translate-x-full md:translate-x-0 md:static transition-transform duration-200 ease-in-out flex flex-col pt-14 md:pt-0">
            <div class="p-4 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Pastas de Projetos</span>
                <button onclick="openModal('modal-new-project')" class="text-xs px-2.5 py-1 rounded-lg border bg-blue-600 hover:bg-blue-500 text-white border-blue-600 flex items-center gap-1 transition-colors cursor-pointer">
                    <span>+ Nova Pasta</span>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto p-2.5 space-y-1">
                <a href="index.php?project_id=all&filter=<?= urlencode($status_filter) ?>"
                   class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-xs transition-colors <?= ($selected_project_id === 'all') ? 'bg-slate-200 dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-200/50 dark:hover:bg-zinc-900/60' ?>">
                    <div class="flex items-center gap-2.5 truncate">
                        <svg class="w-4 h-4 <?= ($selected_project_id === 'all') ? 'text-blue-500' : 'text-zinc-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        <span class="truncate">Visão Geral (Todas)</span>
                    </div>
                    <?php if ($total_active_all > 0): ?>
                        <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-white dark:bg-zinc-900 border border-slate-300 dark:border-dark-border text-slate-600 dark:text-zinc-400 font-mono"><?= $total_active_all ?></span>
                    <?php endif; ?>
                </a>

                <div class="pt-3 pb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Suas Pastas (<?= count($data['projetos']) ?>)</div>

                <?php foreach ($data['projetos'] as $p):
                    $p_id = $p['id'];
                    $is_active = ($selected_project_id === $p_id);
                    $p_active_count = $active_counts_by_project[$p_id] ?? 0;
                    $p_cor = $p['cor'] ?? 'blue';
                    $dot_cls = $folder_colors[$p_cor]['dot'] ?? 'bg-blue-500';
                ?>
                    <a href="index.php?project_id=<?= urlencode($p_id) ?>&filter=<?= urlencode($status_filter) ?>"
                       class="group flex items-center justify-between rounded-xl px-3 py-2.5 text-xs transition-colors <?= $is_active ? 'bg-slate-200 dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-200/50 dark:hover:bg-zinc-900/60' ?>">
                        <div class="flex items-center gap-2.5 truncate flex-1 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 <?= $dot_cls ?>"></span>
                            <span class="truncate"><?= h($p['nome']) ?></span>
                        </div>
                        <?php if ($p_active_count > 0): ?>
                            <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-white dark:bg-zinc-900 border border-slate-300 dark:border-dark-border text-slate-600 dark:text-zinc-400 font-mono"><?= $p_active_count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="p-3.5 border-t border-light-border dark:border-dark-border space-y-2 bg-light-panel dark:bg-dark-panel">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-zinc-500 font-medium">Progresso Concluído</span>
                    <span class="font-mono font-semibold text-emerald-500"><?= $metric_progress_pct ?>%</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-zinc-800 overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all duration-300" style="width: <?= $metric_progress_pct ?>%;"></div>
                </div>
                <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-1">
                    <span>Total: <?= count($data['tarefas']) ?> ideias</span>
                    <button type="button" onclick="openModal('settings-modal'); switchSettingsTab('security');" class="text-blue-500 hover:underline cursor-pointer">Segurança & Backup</button>
                </div>
            </div>
        </aside>

        <div id="mobile-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-20 hidden md:hidden" onclick="toggleMobileMenu()"></div>

        <!-- Área Principal -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <!-- Cabeçalho da Pasta + KPIs -->
            <div class="p-4 sm:p-5 border-b border-light-border dark:border-dark-border bg-light-panel/80 dark:bg-dark-panel/40 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-zinc-100">
                            <?= ($selected_project_id === 'all') ? 'Todas as Ideias & Backlog' : h($current_project['nome'] ?? 'Projeto') ?>
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            <?= ($selected_project_id !== 'all' && !empty($current_project['descricao'])) ? h($current_project['descricao']) : 'Visualização produtiva em Quadro Kanban (Arrastar e Soltar) e Lista.' ?>
                        </p>
                    </div>

                    <?php if ($selected_project_id !== 'all' && $current_project): ?>
                        <form method="POST" action="index.php" onsubmit="return confirm('Excluir esta pasta e todas as suas tarefas vinculadas?');">
                            <input type="hidden" name="action" value="delete_project">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="project_id" value="<?= h($current_project['id']) ?>">
                            <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-900/40 cursor-pointer">
                                Excluir Pasta
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- KPIs Visuais -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <div class="p-3 rounded-xl border bg-white dark:bg-dark-card border-slate-200 dark:border-dark-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-mono text-zinc-500 block">Pendentes</span>
                            <span class="text-lg font-bold text-blue-500 font-mono"><?= $metric_pendentes ?></span>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    </div>
                    <div class="p-3 rounded-xl border bg-white dark:bg-dark-card border-slate-200 dark:border-dark-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-mono text-zinc-500 block">Em Andamento</span>
                            <span class="text-lg font-bold text-amber-500 font-mono"><?= $metric_andamento ?></span>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    </div>
                    <div class="p-3 rounded-xl border bg-white dark:bg-dark-card border-slate-200 dark:border-dark-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-mono text-zinc-500 block">Concluídas</span>
                            <span class="text-lg font-bold text-emerald-500 font-mono"><?= $metric_concluidas ?></span>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    </div>
                    <div class="p-3 rounded-xl border bg-white dark:bg-dark-card border-slate-200 dark:border-dark-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-mono text-zinc-500 block">Urgentes / Hoje</span>
                            <span class="text-lg font-bold <?= $metric_urgentes > 0 ? 'text-rose-500' : 'text-zinc-400' ?> font-mono"><?= $metric_urgentes ?></span>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full <?= $metric_urgentes > 0 ? 'bg-rose-500' : 'bg-zinc-500' ?>"></span>
                    </div>
                </div>

                <!-- Formulário Rápido -->
                <div class="p-3.5 sm:p-4 rounded-xl border bg-white dark:bg-[#16161a] border-light-border dark:border-dark-border shadow-2xs">
                    <form method="POST" action="index.php" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action" value="create_task">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                        <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                        <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">

                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" name="titulo" required placeholder="Adicionar nova ideia, tarefa ou funcionalidade..."
                                   onfocus="document.getElementById('quick-form-details').classList.remove('hidden')"
                                   class="flex-1 text-xs px-3.5 py-2.5 border rounded-xl bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-900 dark:text-zinc-100 focus:outline-none focus:border-blue-500">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="document.getElementById('quick-form-details').classList.toggle('hidden')"
                                        class="text-xs px-3 py-2.5 rounded-xl border border-slate-300 dark:border-zinc-800 bg-slate-100 dark:bg-zinc-900 text-slate-600 dark:text-zinc-400 cursor-pointer">
                                    + Detalhes / Anexo
                                </button>
                                <button type="submit" class="text-xs px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-semibold cursor-pointer whitespace-nowrap">
                                    + Adicionar
                                </button>
                            </div>
                        </div>

                        <div id="quick-form-details" class="hidden space-y-3 pt-2 border-t border-slate-100 dark:border-dark-borderSubtle">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                                <div>
                                    <label class="block text-[10px] uppercase font-mono text-zinc-500 mb-1">Pasta Destino</label>
                                    <select name="id_projeto" class="w-full text-xs px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-800 dark:text-zinc-300">
                                        <?php foreach ($data['projetos'] as $p): ?>
                                            <option value="<?= h($p['id']) ?>" <?= ($selected_project_id === $p['id']) ? 'selected' : '' ?>><?= h($p['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-mono text-zinc-500 mb-1">Prioridade</label>
                                    <select name="prioridade" class="w-full text-xs px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-800 dark:text-zinc-300">
                                        <option value="Baixa">Baixa</option>
                                        <option value="Média" selected>Média</option>
                                        <option value="Alta">Alta</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-mono text-zinc-500 mb-1">Coluna Inicial</label>
                                    <select name="status" class="w-full text-xs px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-800 dark:text-zinc-300">
                                        <option value="pendente" selected>Pendente</option>
                                        <option value="em_andamento">Em Andamento</option>
                                        <option value="concluido">Concluído</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-mono text-zinc-500 mb-1">Prazo / Lembrete</label>
                                    <input type="date" name="data_limite" class="w-full text-xs px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-800 dark:text-zinc-300">
                                </div>
                            </div>

                            <textarea name="descricao_detalhada" rows="2" placeholder="Descrição detalhada, checklist ou especificações (opcional)..."
                                      class="w-full text-xs px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-900 dark:text-zinc-100"></textarea>

                            <div>
                                <label class="text-xs px-3 py-1.5 rounded-lg border inline-flex items-center gap-1.5 cursor-pointer bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-600 dark:text-zinc-400">
                                    <span id="quick-file-label">📎 Anexar Foto ou Vídeo</span>
                                    <input type="file" name="anexo" accept="image/*,video/*" class="hidden" onchange="document.getElementById('quick-file-label').textContent = this.files[0] ? this.files[0].name : '📎 Anexar Foto ou Vídeo'">
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Barra de Filtros e Busca -->
            <div class="p-3 sm:p-4 border-b border-light-border dark:border-dark-border flex flex-col sm:flex-row items-center justify-between gap-3 bg-light-panel/60 dark:bg-dark-panel/20">
                <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
                    <?php
                    $tabs = [
                        'all' => ['label' => 'Todas', 'count' => $metric_total],
                        'pendente' => ['label' => 'Pendentes', 'count' => $metric_pendentes],
                        'em_andamento' => ['label' => 'Em Andamento', 'count' => $metric_andamento],
                        'concluido' => ['label' => 'Concluídas', 'count' => $metric_concluidas],
                    ];
                    foreach ($tabs as $tid => $tinfo):
                    ?>
                        <a href="index.php?project_id=<?= urlencode($selected_project_id) ?>&filter=<?= urlencode($tid) ?>"
                           class="text-xs px-3 py-1.5 rounded-lg border whitespace-nowrap transition-colors <?= ($status_filter === $tid) ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 border-slate-300 dark:border-zinc-700 font-medium shadow-2xs' : 'text-zinc-500 border-transparent hover:bg-slate-200/60 dark:hover:bg-zinc-900' ?>">
                            <?= $tinfo['label'] ?> <span class="ml-1 text-[11px] opacity-70 font-mono">(<?= $tinfo['count'] ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="GET" action="index.php" class="relative w-full sm:w-64 lg:w-72">
                    <input type="hidden" name="project_id" value="<?= h($selected_project_id) ?>">
                    <input type="hidden" name="filter" value="<?= h($status_filter) ?>">
                    <input type="text" name="q" value="<?= h($search_query) ?>" placeholder="Buscar ideia ou palavra-chave..."
                           class="w-full text-xs pl-3 pr-7 py-1.5 border rounded-lg bg-white dark:bg-dark-input border-slate-300 dark:border-dark-border text-slate-800 dark:text-zinc-200">
                </form>
            </div>

            <!-- Conteúdo Principal: Kanban ou Lista -->
            <div class="flex-1 p-3 sm:p-4 lg:p-6">
                <?php if ($view_mode === 'kanban'): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                        <?php
                        $columns = [
                            'pendente' => ['title' => 'Pendente', 'sub' => 'Ideias e backlog para iniciar', 'dot' => 'bg-blue-500'],
                            'em_andamento' => ['title' => 'Em Andamento', 'sub' => 'Sendo executado agora', 'dot' => 'bg-amber-500'],
                            'concluido' => ['title' => 'Concluído', 'sub' => 'Finalizado e validado', 'dot' => 'bg-emerald-500'],
                        ];
                        foreach ($columns as $col_status => $col_meta):
                            $col_tasks = array_filter($filtered_tasks, fn($t) => ($t['status'] ?? 'pendente') === $col_status);
                        ?>
                            <div ondragover="handleDragOver(event)"
                                 ondrop="handleDrop(event, '<?= $col_status ?>')"
                                 class="rounded-2xl border p-3 min-h-[340px] flex flex-col gap-3 bg-[#f4f6f8] dark:bg-[#121215]/90 border-[#d5dbe1] dark:border-zinc-800/90 transition-all">
                                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200 dark:border-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full <?= $col_meta['dot'] ?>"></span>
                                        <div>
                                            <h3 class="text-xs font-bold text-slate-900 dark:text-zinc-100"><?= $col_meta['title'] ?></h3>
                                            <p class="text-[10px] text-zinc-500"><?= $col_meta['sub'] ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-mono font-semibold border bg-white dark:bg-zinc-900 border-slate-300 dark:border-zinc-800 text-slate-700 dark:text-zinc-300">
                                        <?= count($col_tasks) ?>
                                    </span>
                                </div>

                                <div class="space-y-2.5 flex-1">
                                    <?php if (empty($col_tasks)): ?>
                                        <div class="h-36 border border-dashed rounded-xl flex flex-col items-center justify-center text-center p-4 border-slate-300 dark:border-zinc-800 text-slate-400 dark:text-zinc-600">
                                            <span class="text-xs">Arraste um card para cá</span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($col_tasks as $task): ?>
                                            <?php render_task_card_html($task, $data['projetos'], $folder_colors, $selected_project_id, $status_filter, $csrf_token); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php if (empty($filtered_tasks)): ?>
                            <div class="py-14 text-center border border-dashed rounded-xl border-slate-300 dark:border-dark-border">
                                <h3 class="text-sm font-medium text-slate-600 dark:text-zinc-400">Nenhuma ideia encontrada</h3>
                            </div>
                        <?php else: ?>
                            <?php foreach ($filtered_tasks as $task): ?>
                                <?php render_task_card_html($task, $data['projetos'], $folder_colors, $selected_project_id, $status_filter, $csrf_token); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- MODAL DE CONFIGURAÇÕES & SEGURANÇA -->
    <div id="settings-modal" class="fixed inset-0 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 z-50 <?= $sec_feedback ? '' : 'hidden' ?>">
        <div class="w-full max-w-2xl rounded-2xl border bg-[#f4f6f8] dark:bg-[#121215] border-slate-300 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#16161a] flex items-center justify-between">
                <h3 class="font-semibold text-sm">Configurações da Plataforma</h3>
                <div class="flex items-center gap-1">
                    <button type="button" id="tab-btn-visual" onclick="switchSettingsTab('visual')" class="text-xs px-3 py-1.5 rounded-lg font-medium bg-blue-600 text-white cursor-pointer">Estilo & Visual</button>
                    <button type="button" id="tab-btn-security" onclick="switchSettingsTab('security')" class="text-xs px-3 py-1.5 rounded-lg font-medium text-zinc-400 hover:text-zinc-200 cursor-pointer">Segurança & Backup</button>
                    <button type="button" onclick="closeModal('settings-modal')" class="text-zinc-400 hover:text-zinc-200 p-1.5 ml-2">✕</button>
                </div>
            </div>

            <!-- ABA VISUAL -->
            <form id="settings-tab-visual" method="POST" action="index.php" class="p-4 sm:p-6 overflow-y-auto space-y-5 text-xs <?= $sec_feedback ? 'hidden' : '' ?>">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">

                <div>
                    <label class="font-semibold uppercase tracking-wider text-zinc-500 text-[11px] block mb-2">Tema da Interface</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="p-3 rounded-xl border flex items-center gap-3 cursor-pointer <?= ($theme === 'dark') ? 'border-blue-500 bg-blue-500/10' : 'border-zinc-700' ?>">
                            <input type="radio" name="theme" value="dark" <?= ($theme === 'dark') ? 'checked' : '' ?>>
                            <div>
                                <div class="font-medium text-xs">Tema Black</div>
                                <div class="text-[10px] text-zinc-500">Noturno chumbo sóbrio</div>
                            </div>
                        </label>
                        <label class="p-3 rounded-xl border flex items-center gap-3 cursor-pointer <?= ($theme === 'light') ? 'border-blue-600 bg-blue-50' : 'border-slate-300' ?>">
                            <input type="radio" name="theme" value="light" <?= ($theme === 'light') ? 'checked' : '' ?>>
                            <div>
                                <div class="font-medium text-xs">Tema White Suave</div>
                                <div class="text-[10px] text-zinc-500">Fosco e relaxante aos olhos</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="font-semibold uppercase tracking-wider text-zinc-500 text-[11px] block mb-2">Estilo de Fonte</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <?php
                        $font_options = [
                            'Helvetica / Arial' => 'Clássica e neutra',
                            'Montserrat' => 'Moderna e geométrica',
                            'Roboto' => 'Equilibrada e legível',
                            'Open Sans / Lato' => 'Humanista suave'
                        ];
                        foreach ($font_options as $fid => $fdesc):
                        ?>
                            <label class="p-3 rounded-xl border cursor-pointer <?= ($font_family === $fid) ? 'border-blue-500 bg-blue-500/10' : 'border-slate-300 dark:border-zinc-800' ?>">
                                <input type="radio" name="font_family" value="<?= h($fid) ?>" <?= ($font_family === $fid) ? 'checked' : '' ?>>
                                <span class="font-semibold text-xs ml-1"><?= h($fid) ?></span>
                                <div class="text-[10px] text-zinc-500 mt-0.5"><?= h($fdesc) ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="font-semibold uppercase tracking-wider text-zinc-500 text-[11px] block mb-2">Tamanho da Letra</label>
                    <div class="grid grid-cols-3 gap-2.5">
                        <?php foreach (['small' => 'Pequena (13px)', 'medium' => 'Média (15px)', 'large' => 'Grande (17px)'] as $sid => $slabel): ?>
                            <label class="p-3 rounded-xl border text-center cursor-pointer <?= ($font_size === $sid) ? 'border-blue-500 bg-blue-500/10' : 'border-slate-300 dark:border-zinc-800' ?>">
                                <input type="radio" name="font_size" value="<?= $sid ?>" <?= ($font_size === $sid) ? 'checked' : '' ?>>
                                <div class="font-semibold text-xs mt-1"><?= $slabel ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-dark-border flex justify-end">
                    <button type="submit" class="text-xs px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium cursor-pointer">
                        Salvar Estilo Visual
                    </button>
                </div>
            </form>

            <!-- ABA SEGURANÇA, .ENV & SUPABASE -->
            <div id="settings-tab-security" class="p-4 sm:p-6 overflow-y-auto space-y-6 text-xs <?= $sec_feedback ? '' : 'hidden' ?>">
                <?php if ($sec_feedback): ?>
                    <div class="p-3 rounded-xl border <?= $sec_feedback['type'] === 'ok' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-500' ?>">
                        <?= h($sec_feedback['text']) ?>
                    </div>
                <?php endif; ?>

                <div class="p-4 rounded-xl border border-blue-500/30 bg-blue-500/5 space-y-1.5">
                    <div class="font-semibold text-xs text-blue-500">Proteção de Credenciais (.env + .gitignore + .htaccess)</div>
                    <p class="text-[11px] text-zinc-500 leading-relaxed">
                        Seu E-mail (<code class="font-mono">ADMIN_EMAIL</code>) e Senha (<code class="font-mono">ADMIN_PASSWORD</code>) ficam isolados no arquivo <code class="font-mono">.env</code> na raiz do projeto, protegido pelo <code class="font-mono">.htaccess</code> contra acesso externo e listado no <code class="font-mono">.gitignore</code> para jamais ser exposto em repositórios Git.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#18181c] space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-xs">Banco de Dados na Nuvem — Supabase</h4>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-mono border <?= $supabase_active ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30' : 'bg-amber-500/10 text-amber-500 border-amber-500/30' ?>">
                            <?= $supabase_active ? 'Supabase Conectado' : 'Aguardando chaves no .env' ?>
                        </span>
                    </div>
                    <ol class="list-decimal list-inside space-y-1 text-[11px] text-zinc-500">
                        <li>Execute o script <code class="font-mono text-blue-500">supabase_schema.sql</code> no <strong>SQL Editor</strong> do seu projeto no Supabase.</li>
                        <li>Preencha <code class="font-mono">SUPABASE_URL</code> e <code class="font-mono">SUPABASE_KEY</code> no seu arquivo <code class="font-mono">.env</code>.</li>
                        <li>Clique no botão abaixo para sincronizar todas as suas pastas e ideias com o Supabase.</li>
                    </ol>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="sync_supabase">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                        <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                        <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">
                        <button type="submit" class="px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium cursor-pointer">
                            Sincronizar Dados com o Supabase Agora
                        </button>
                    </form>
                </div>

                <div class="pt-4 border-t border-slate-200 dark:border-zinc-800 space-y-3">
                    <h4 class="font-semibold text-xs">Backup Local e Restauração (JSON)</h4>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="api.php?action=export_backup" class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium inline-flex items-center gap-1.5">
                            Exportar Backup Agora (.json)
                        </a>

                        <form method="POST" action="index.php" enctype="multipart/form-data" class="inline">
                            <input type="hidden" name="action" value="import_backup">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <label class="px-3.5 py-2 rounded-lg border border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer inline-flex items-center gap-1.5">
                                <span>Restaurar Backup (.json)</span>
                                <input type="file" name="backup_file" accept=".json,application/json" class="hidden" onchange="this.form.submit()">
                            </label>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE NOVA PASTA -->
    <div id="modal-new-project" class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="w-full max-w-md rounded-2xl border bg-white dark:bg-dark-panel border-light-border dark:border-dark-border p-5 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-dark-borderSubtle mb-4">
                <h3 class="font-semibold text-sm">Nova Pasta de Projeto</h3>
                <button onclick="closeModal('modal-new-project')" class="text-zinc-400">✕</button>
            </div>

            <form method="POST" action="index.php" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="create_project">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Nome da Pasta</label>
                    <input type="text" name="nome" required placeholder="Ex: Gestão Advocacia, Mobile, API..."
                           class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                </div>

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Cor da Pasta</label>
                    <select name="cor" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                        <option value="blue">Azul</option>
                        <option value="emerald">Verde Esmeralda</option>
                        <option value="purple">Roxo</option>
                        <option value="amber">Âmbar</option>
                        <option value="rose">Rosa</option>
                        <option value="cyan">Ciano</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Descrição</label>
                    <textarea name="descricao" rows="2" placeholder="Resumo do escopo da pasta..."
                              class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('modal-new-project')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-dark-border">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium">Criar Pasta</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE EDIÇÃO DE TAREFA -->
    <div id="modal-edit-task" class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="w-full max-w-lg rounded-2xl border bg-white dark:bg-dark-panel border-light-border dark:border-dark-border p-5 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-dark-borderSubtle mb-4">
                <h3 class="font-semibold text-sm">Editar Ideia</h3>
                <button onclick="closeModal('modal-edit-task')" class="text-zinc-400">✕</button>
            </div>

            <form method="POST" action="index.php" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="task_id" id="edit-task-id" value="">
                <input type="hidden" name="current_project" value="<?= h($selected_project_id) ?>">
                <input type="hidden" name="current_filter" value="<?= h($status_filter) ?>">
                <input type="hidden" name="remover_anexo" id="edit-task-remover-anexo" value="0">

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Título</label>
                    <input type="text" name="titulo" id="edit-task-titulo" required
                           class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-slate-600 dark:text-zinc-400 mb-1">Pasta</label>
                        <select name="id_projeto" id="edit-task-projeto" class="w-full px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                            <?php foreach ($data['projetos'] as $p): ?>
                                <option value="<?= h($p['id']) ?>"><?= h($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-zinc-400 mb-1">Etapa / Status</label>
                        <select name="status" id="edit-task-status" class="w-full px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                            <option value="pendente">Pendente</option>
                            <option value="em_andamento">Em Andamento</option>
                            <option value="concluido">Concluído</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-zinc-400 mb-1">Prioridade</label>
                        <select name="prioridade" id="edit-task-prioridade" class="w-full px-2.5 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Data Limite / Lembrete</label>
                    <input type="date" name="data_limite" id="edit-task-datalimite" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border">
                </div>

                <div>
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1">Descrição Detalhada</label>
                    <textarea name="descricao_detalhada" id="edit-task-descricao" rows="3"
                              class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-dark-input border-slate-300 dark:border-dark-border"></textarea>
                </div>

                <div class="pt-2 border-t border-slate-200 dark:border-dark-borderSubtle">
                    <label class="block text-slate-600 dark:text-zinc-400 mb-1.5">Arquivo Anexado</label>
                    <div id="edit-media-preview-container" class="flex items-center justify-between p-2 rounded-lg border border-slate-300 dark:border-zinc-700 mb-2 hidden">
                        <span id="edit-media-preview-name" class="truncate text-xs"></span>
                        <button type="button" onclick="document.getElementById('edit-task-remover-anexo').value='1'; document.getElementById('edit-media-preview-container').classList.add('hidden');" class="text-xs text-rose-500 hover:underline ml-2">Remover</button>
                    </div>
                    <label class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-zinc-700 bg-slate-100 dark:bg-zinc-800 inline-flex items-center gap-1.5 cursor-pointer">
                        <span>📎 Substituir ou Adicionar Arquivo</span>
                        <input type="file" name="anexo" accept="image/*,video/*" class="hidden">
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('modal-edit-task')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-dark-border">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium cursor-pointer">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL LIGHTBOX -->
    <div id="lightbox-modal" class="fixed inset-0 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 z-50 hidden" onclick="closeModal('lightbox-modal')">
        <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
            <img id="lightbox-img" src="" alt="Imagem ampliada" class="max-h-[85vh] max-w-full rounded-xl border border-zinc-800 object-contain shadow-2xl">
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
        let draggedTaskId = null;

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        }

        function switchSettingsTab(tab) {
            const vTab = document.getElementById('settings-tab-visual');
            const sTab = document.getElementById('settings-tab-security');
            const vBtn = document.getElementById('tab-btn-visual');
            const sBtn = document.getElementById('tab-btn-security');
            if (tab === 'security') {
                vTab.classList.add('hidden');
                sTab.classList.remove('hidden');
                sBtn.className = 'text-xs px-3 py-1.5 rounded-lg font-medium bg-blue-600 text-white cursor-pointer';
                vBtn.className = 'text-xs px-3 py-1.5 rounded-lg font-medium text-zinc-400 hover:text-zinc-200 cursor-pointer';
            } else {
                sTab.classList.add('hidden');
                vTab.classList.remove('hidden');
                vBtn.className = 'text-xs px-3 py-1.5 rounded-lg font-medium bg-blue-600 text-white cursor-pointer';
                sBtn.className = 'text-xs px-3 py-1.5 rounded-lg font-medium text-zinc-400 hover:text-zinc-200 cursor-pointer';
            }
        }

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const mobileBtn = document.getElementById('mobile-menu-btn');
        function toggleMobileMenu() {
            if (sidebar && overlay) {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        }
        if (mobileBtn) mobileBtn.addEventListener('click', toggleMobileMenu);

        function toggleTaskMedia(taskId) {
            const mediaBox = document.getElementById('media-box-' + taskId);
            const btnText = document.getElementById('btn-text-' + taskId);
            if (mediaBox) {
                const isHidden = mediaBox.classList.toggle('hidden');
                if (btnText) btnText.textContent = isHidden ? 'Anexo' : 'Ocultar';
            }
        }

        function openEditTaskModal(task) {
            document.getElementById('edit-task-id').value = task.id;
            document.getElementById('edit-task-titulo').value = task.titulo;
            document.getElementById('edit-task-projeto').value = task.id_projeto;
            document.getElementById('edit-task-status').value = task.status || 'pendente';
            document.getElementById('edit-task-prioridade').value = task.prioridade;
            document.getElementById('edit-task-datalimite').value = task.data_limite || '';
            document.getElementById('edit-task-descricao').value = task.descricao_detalhada || '';
            document.getElementById('edit-task-remover-anexo').value = '0';

            const mediaPreview = document.getElementById('edit-media-preview-container');
            const mediaName = document.getElementById('edit-media-preview-name');
            if (task.anexo && task.anexo.nome_original) {
                mediaName.textContent = task.anexo.nome_original;
                mediaPreview.classList.remove('hidden');
            } else {
                mediaPreview.classList.add('hidden');
            }
            openModal('modal-edit-task');
        }

        function openImageLightbox(url) {
            const img = document.getElementById('lightbox-img');
            if (img) img.src = url;
            openModal('lightbox-modal');
        }

        // Drag & Drop Kanban + Transição Rápida via API Unificada
        function handleDragStart(e, taskId) {
            draggedTaskId = taskId;
            e.currentTarget.style.opacity = '0.5';
        }
        function handleDragEnd(e) {
            e.currentTarget.style.opacity = '1';
        }
        function handleDragOver(e) {
            e.preventDefault();
        }
        async function handleDrop(e, targetStatus) {
            e.preventDefault();
            if (!draggedTaskId) return;
            await quickMoveStatus(draggedTaskId, targetStatus);
        }

        async function quickMoveStatus(taskId, targetStatus) {
            try {
                const res = await fetch('api.php?action=update_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        status: targetStatus,
                        csrf_token: CSRF_TOKEN
                    })
                });
                if (res.ok) {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            }
        }
    </script>
<?php endif; ?>
</body>
</html>
