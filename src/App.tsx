import React, { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import {
  Folder,
  FolderPlus,
  CheckSquare,
  Plus,
  Trash2,
  Edit3,
  Search,
  Sun,
  Moon,
  Menu,
  X,
  Calendar,
  Check,
  Settings,
  Image as ImageIcon,
  Video,
  Paperclip,
  ZoomIn,
  Eye,
  EyeOff,
  Sparkles,
  LayoutGrid,
  List,
  Lock,
  Unlock,
  KeyRound,
  Download,
  Upload,
  AlertTriangle,
  Clock,
  ArrowLeft,
  Play,
  RotateCcw
} from 'lucide-react';
import {
  type Projeto,
  type Tarefa,
  type AnexoMedia,
  type ThemeMode,
  type FontSize,
  type FontFamily,
  type ViewMode,
  type TaskStatus,
  type FolderColor,
  calculateDueStatus,
  filterTasks,
  countPendingByProject,
  getMediaCategory,
  getFontFamilyCss,
  getFontSizePx,
  formatDateTime
} from './utils/backlog-logic.ts';
import {
  getSupabaseClient,
  initSupabaseClient,
  isSupabaseClientConfigured
} from './lib/supabase.ts';
import {
  useAppRouter,
  ProtectedRoute
} from './components/ProtectedRoute.tsx';
import { AuthScreen } from './components/AuthScreen.tsx';

const FOLDER_COLOR_MAP: Record<FolderColor, { dot: string; badgeDark: string; badgeLight: string }> = {
  blue: {
    dot: 'bg-blue-500',
    badgeDark: 'bg-blue-950/50 text-blue-300 border-blue-800/50',
    badgeLight: 'bg-blue-50 text-blue-700 border-blue-200'
  },
  emerald: {
    dot: 'bg-emerald-500',
    badgeDark: 'bg-emerald-950/50 text-emerald-300 border-emerald-800/50',
    badgeLight: 'bg-emerald-50 text-emerald-700 border-emerald-200'
  },
  purple: {
    dot: 'bg-purple-500',
    badgeDark: 'bg-purple-950/50 text-purple-300 border-purple-800/50',
    badgeLight: 'bg-purple-50 text-purple-700 border-purple-200'
  },
  amber: {
    dot: 'bg-amber-500',
    badgeDark: 'bg-amber-950/50 text-amber-300 border-amber-800/50',
    badgeLight: 'bg-amber-50 text-amber-700 border-amber-200'
  },
  rose: {
    dot: 'bg-rose-500',
    badgeDark: 'bg-rose-950/50 text-rose-300 border-rose-800/50',
    badgeLight: 'bg-rose-50 text-rose-700 border-rose-200'
  },
  cyan: {
    dot: 'bg-cyan-500',
    badgeDark: 'bg-cyan-950/50 text-cyan-300 border-cyan-800/50',
    badgeLight: 'bg-cyan-50 text-cyan-700 border-cyan-200'
  }
};

const INITIAL_PROJECTS: Projeto[] = [
  {
    id: 'proj_infra',
    nome: 'Infraestrutura & DevOps',
    descricao: 'Melhorias de CI/CD, monitoramento e arquitetura de servidores.',
    cor: 'blue',
    criado_em: '2026-10-25 09:30:00'
  },
  {
    id: 'proj_ui',
    nome: 'Design System & UI',
    descricao: 'Refatoração de componentes, tokens de cor e acessibilidade.',
    cor: 'purple',
    criado_em: '2026-10-26 14:15:00'
  },
  {
    id: 'proj_api',
    nome: 'API & Integrações',
    descricao: 'Endpoints REST, autenticação e documentação técnica.',
    cor: 'emerald',
    criado_em: '2026-10-27 11:00:00'
  }
];

const INITIAL_TASKS: Tarefa[] = [
  {
    id: 'task_1',
    id_projeto: 'proj_infra',
    titulo: 'Implementar rotação automática de logs e backup diário',
    descricao_detalhada: 'Configurar rotina cron com compressão gzip e retenção de 30 dias para segurança concorrente.',
    prioridade: 'Alta',
    status: 'em_andamento',
    data_limite: new Date().toISOString().split('T')[0],
    criado_em: '2026-10-27 10:00:00',
    anexo: null
  },
  {
    id: 'task_2',
    id_projeto: 'proj_ui',
    titulo: 'Novo layout com tema White suave e Quadro Kanban',
    descricao_detalhada: 'Paleta matte confortável que evita cansaço visual, com tipografia customizável e anexo sob demanda.',
    prioridade: 'Alta',
    status: 'pendente',
    data_limite: new Date(Date.now() + 86400000).toISOString().split('T')[0],
    criado_em: '2026-10-27 15:00:00',
    anexo: {
      nome_original: 'mockup_dashboard_v2.png',
      caminho: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80',
      tipo: 'imagem',
      tamanho_formatado: '420 KB'
    }
  },
  {
    id: 'task_3',
    id_projeto: 'proj_api',
    titulo: 'Validação segura de uploads de fotos e vídeos com player HTML5',
    descricao_detalhada: 'Gravação demonstrando o upload seguro com visualização sob demanda.',
    prioridade: 'Média',
    status: 'concluido',
    data_limite: new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0],
    criado_em: '2026-10-28 11:20:00',
    anexo: null
  }
];

export default function App() {
  // Customization States with localStorage persistence
  const [theme, setTheme] = useState<ThemeMode>(() => {
    try {
      const saved = localStorage.getItem('app_theme');
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {
      // ignore storage errors
    }
    return 'dark';
  });

  const [fontSize, setFontSize] = useState<FontSize>(() => {
    try {
      const saved = localStorage.getItem('app_font_size');
      if (saved === 'small' || saved === 'medium' || saved === 'large') return saved;
    } catch {
      // ignore storage errors
    }
    return 'medium';
  });

  const [fontFamily, setFontFamily] = useState<FontFamily>(() => {
    try {
      const saved = localStorage.getItem('app_font_family');
      if (
        saved === 'Helvetica / Arial' ||
        saved === 'Montserrat' ||
        saved === 'Roboto' ||
        saved === 'Open Sans / Lato'
      ) {
        return saved;
      }
    } catch {
      // ignore storage errors
    }
    return 'Roboto';
  });

  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    try {
      const saved = localStorage.getItem('app_view_mode');
      if (saved === 'list' || saved === 'kanban') return saved;
    } catch {
      // ignore
    }
    return 'kanban';
  });

  const { route, navigate } = useAppRouter();

  // Backend & Supabase Auth States
  const [backendConnected, setBackendConnected] = useState<boolean>(false);
  const [supabaseEnabled, setSupabaseEnabled] = useState<boolean>(() =>
    isSupabaseClientConfigured()
  );
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [currentUserId, setCurrentUserId] = useState<string>('');
  const [currentUserEmail, setCurrentUserEmail] = useState<string>('');
  const [supabaseAccessToken, setSupabaseAccessToken] = useState<string>('');
  const [csrfToken, setCsrfToken] = useState<string>('');
  const [isLoadingAuth, setIsLoadingAuth] = useState<boolean>(true);

  // Settings Modal States
  const [settingsTab, setSettingsTab] = useState<'visual' | 'backup'>('visual');
  const [showSettingsModal, setShowSettingsModal] = useState<boolean>(false);

  // Track expanded media per task
  const [expandedMediaIds, setExpandedMediaIds] = useState<Record<string, boolean>>({});

  // Data States
  const [projetos, setProjetos] = useState<Projeto[]>(() => {
    try {
      const saved = localStorage.getItem('app_projetos_backup');
      if (saved) return JSON.parse(saved);
    } catch {
      // fallback
    }
    return INITIAL_PROJECTS;
  });

  const [tarefas, setTarefas] = useState<Tarefa[]>(() => {
    try {
      const saved = localStorage.getItem('app_tarefas_backup');
      if (saved) return JSON.parse(saved);
    } catch {
      // fallback
    }
    return INITIAL_TASKS;
  });

  const [selectedProjectId, setSelectedProjectId] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<'all' | TaskStatus>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Drag and Drop State for Kanban
  const [draggedTaskId, setDraggedTaskId] = useState<string | null>(null);
  const [dragOverColumn, setDragOverColumn] = useState<TaskStatus | null>(null);

  // Mobile drawer
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Modals
  const [showNewProjectModal, setShowNewProjectModal] = useState(false);
  const [showEditTaskModal, setShowEditTaskModal] = useState(false);
  const [previewMediaUrl, setPreviewMediaUrl] = useState<string | null>(null);

  // Form states (Quick Creation)
  const [newProjectNome, setNewProjectNome] = useState('');
  const [newProjectDesc, setNewProjectDesc] = useState('');
  const [newProjectCor, setNewProjectCor] = useState<FolderColor>('blue');

  const [newTaskTitulo, setNewTaskTitulo] = useState('');
  const [newTaskProjeto, setNewTaskProjeto] = useState('');
  const [newTaskPrioridade, setNewTaskPrioridade] = useState<'Baixa' | 'Média' | 'Alta'>('Média');
  const [newTaskStatus, setNewTaskStatus] = useState<TaskStatus>('pendente');
  const [newTaskDataLimite, setNewTaskDataLimite] = useState('');
  const [newTaskDesc, setNewTaskDesc] = useState('');
  const [newTaskFile, setNewTaskFile] = useState<File | null>(null);
  const [showQuickFormDetails, setShowQuickFormDetails] = useState<boolean>(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Edit Task State
  const [editingTask, setEditingTask] = useState<Tarefa | null>(null);
  const [editTaskNewFile, setEditTaskNewFile] = useState<File | null>(null);
  const [editTaskRemoveMedia, setEditTaskRemoveMedia] = useState<boolean>(false);
  const editFileInputRef = useRef<HTMLInputElement>(null);
  const backupInputRef = useRef<HTMLInputElement>(null);

  // Synchronize URL route (/kanban or /lista) with viewMode
  useEffect(() => {
    if (route === '/lista' && viewMode !== 'list') {
      setViewMode('list');
    } else if (route === '/kanban' && viewMode !== 'kanban') {
      setViewMode('kanban');
    }
  }, [route, viewMode]);

  // Set official application title strictly as "Backlog"
  useEffect(() => {
    document.title = 'Backlog';
  }, []);

  // Apply Theme (Dark / Light) to <html> and <body> with .dark, .light and data-theme
  useEffect(() => {
    const root = document.documentElement;
    const body = document.body;

    root.classList.remove('dark', 'light');
    root.classList.add(theme);
    root.setAttribute('data-theme', theme);
    root.style.colorScheme = theme;

    if (body) {
      body.classList.remove('dark', 'light');
      body.classList.add(theme);
      body.setAttribute('data-theme', theme);
    }

    try {
      localStorage.setItem('app_theme', theme);
    } catch {
      // ignore
    }
    document.cookie = `app_theme=${theme};path=/;max-age=31536000;SameSite=Lax`;
  }, [theme]);

  // Apply Font Family and Font Size Scale (13px / 15px / 17px) globally and reactively
  useEffect(() => {
    const familyCss = getFontFamilyCss(fontFamily);
    const sizePx = getFontSizePx(fontSize);
    const root = document.documentElement;
    const body = document.body;

    root.style.setProperty('--app-font-family', familyCss);
    root.style.setProperty('--app-font-size', sizePx);
    root.style.fontSize = sizePx;
    root.style.fontFamily = familyCss;

    if (body) {
      body.style.fontFamily = familyCss;
    }

    let styleEl = document.getElementById('app-dynamic-typography');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'app-dynamic-typography';
      document.head.appendChild(styleEl);
    }

    styleEl.textContent = `
      :root {
        --app-font-family: ${familyCss};
        --app-font-size: ${sizePx};
      }
      html {
        font-size: ${sizePx} !important;
        font-family: ${familyCss} !important;
      }
      body, #root, button, input, select, textarea {
        font-family: ${familyCss} !important;
      }
    `;

    try {
      localStorage.setItem('app_font_size', fontSize);
      localStorage.setItem('app_font_family', fontFamily);
    } catch {
      // ignore
    }
    document.cookie = `app_font_size=${encodeURIComponent(fontSize)};path=/;max-age=31536000;SameSite=Lax`;
    document.cookie = `app_font_family=${encodeURIComponent(fontFamily)};path=/;max-age=31536000;SameSite=Lax`;
  }, [fontFamily, fontSize]);

  useEffect(() => {
    try {
      localStorage.setItem('app_view_mode', viewMode);
    } catch {
      // ignore
    }
    document.cookie = `app_view_mode=${viewMode};path=/;max-age=31536000;SameSite=Lax`;
  }, [viewMode]);

  // Save local backup cache whenever projects/tasks change
  useEffect(() => {
    try {
      localStorage.setItem('app_projetos_backup', JSON.stringify(projetos));
    } catch {
      // ignore
    }
  }, [projetos]);

  useEffect(() => {
    try {
      localStorage.setItem('app_tarefas_backup', JSON.stringify(tarefas));
    } catch {
      // ignore
    }
  }, [tarefas]);

  const getAuthHeaders = useCallback(
    (extra: Record<string, string> = {}): Record<string, string> => {
      const headers: Record<string, string> = { ...extra };
      if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
      }
      if (supabaseAccessToken) {
        headers['Authorization'] = `Bearer ${supabaseAccessToken}`;
      }
      return headers;
    },
    [csrfToken, supabaseAccessToken]
  );

  // Carrega dados diretamente do Supabase (protegidos por RLS por user_id)
  const loadFromSupabaseDirect = useCallback(async () => {
    const sb = getSupabaseClient();
    if (!sb) return;
    try {
      const [{ data: projData, error: projErr }, { data: taskData, error: taskErr }] =
        await Promise.all([
          sb.from('projetos').select('*').order('criado_em', { ascending: true }),
          sb.from('tarefas').select('*').order('criado_em', { ascending: false })
        ]);

      if (!projErr && Array.isArray(projData)) {
        setProjetos(
          projData.map((p: any) => ({
            id: p.id,
            nome: p.nome,
            descricao: p.descricao || '',
            cor: p.cor || 'blue',
            criado_em: p.criado_em
              ? String(p.criado_em).replace('T', ' ').substring(0, 19)
              : ''
          }))
        );
      }
      if (!taskErr && Array.isArray(taskData)) {
        setTarefas(
          taskData.map((t: any) => ({
            id: t.id,
            id_projeto: t.id_projeto,
            titulo: t.titulo,
            descricao_detalhada: t.descricao_detalhada || '',
            prioridade: t.prioridade || 'Média',
            status: t.status || 'pendente',
            data_limite: t.data_limite || '',
            criado_em: t.criado_em
              ? String(t.criado_em).replace('T', ' ').substring(0, 19)
              : '',
            anexo: typeof t.anexo === 'string' ? JSON.parse(t.anexo) : t.anexo || null
          }))
        );
      }
    } catch {
      // fallback to local state
    }
  }, []);

  // Inicialização unificada: Backend PHP + Supabase Auth Session
  const initializeAuthAndState = useCallback(async () => {
    setIsLoadingAuth(true);
    let sb = getSupabaseClient();
    let phpAvailable = false;
    let phpData: any = null;

    try {
      const res = await fetch('./api.php?action=state', {
        credentials: 'same-origin'
      });
      if (res.ok) {
        phpData = await res.json();
        phpAvailable = true;
        setBackendConnected(true);
        if (phpData.csrf_token) setCsrfToken(phpData.csrf_token);
        if (!sb && phpData.supabase_url && phpData.supabase_publishable_key) {
          sb = initSupabaseClient(phpData.supabase_url, phpData.supabase_publishable_key);
        }
      } else {
        setBackendConnected(false);
      }
    } catch {
      setBackendConnected(false);
    }

    if (sb) {
      setSupabaseEnabled(true);
      const {
        data: { session }
      } = await sb.auth.getSession();

      if (session?.user) {
        setIsAuthenticated(true);
        setCurrentUserId(session.user.id);
        setCurrentUserEmail(session.user.email || '');
        setSupabaseAccessToken(session.access_token);
        await loadFromSupabaseDirect();
      } else {
        setIsAuthenticated(false);
        setCurrentUserId('');
        setCurrentUserEmail('');
        setSupabaseAccessToken('');
      }
      setIsLoadingAuth(false);
      return;
    }

    setSupabaseEnabled(false);
    if (phpAvailable && phpData) {
      if (phpData.authenticated === false) {
        setIsAuthenticated(false);
      } else {
        setIsAuthenticated(true);
        setCurrentUserEmail(phpData.admin_email || '');
        if (Array.isArray(phpData.projetos)) setProjetos(phpData.projetos);
        if (Array.isArray(phpData.tarefas)) setTarefas(phpData.tarefas);
      }
    } else {
      const localLocked = sessionStorage.getItem('app_local_locked') === '1';
      setIsAuthenticated(!localLocked);
      if (!localLocked) {
        setCurrentUserEmail(
          localStorage.getItem('app_local_email') || 'admin@meusoftware.com'
        );
      }
    }
    setIsLoadingAuth(false);
  }, [loadFromSupabaseDirect]);

  useEffect(() => {
    initializeAuthAndState();
  }, [initializeAuthAndState]);

  // Listener em tempo real do Supabase Auth (login, logout, refresh token, recovery)
  useEffect(() => {
    const sb = getSupabaseClient();
    if (!sb) return;

    const {
      data: { subscription }
    } = sb.auth.onAuthStateChange((event, session) => {
      if (event === 'PASSWORD_RECOVERY') {
        navigate('/reset-password');
      }

      if (session?.user) {
        setIsAuthenticated(true);
        setCurrentUserId(session.user.id);
        setCurrentUserEmail(session.user.email || '');
        setSupabaseAccessToken(session.access_token);
        loadFromSupabaseDirect();
      } else {
        setIsAuthenticated(false);
        setCurrentUserId('');
        setCurrentUserEmail('');
        setSupabaseAccessToken('');
      }
    });

    return () => {
      subscription.unsubscribe();
    };
  }, [supabaseEnabled, navigate, loadFromSupabaseDirect]);

  const currentProject = useMemo(() => {
    if (selectedProjectId === 'all') return null;
    return projetos.find(p => p.id === selectedProjectId) || null;
  }, [projetos, selectedProjectId]);

  const pendingCounts = useMemo(() => countPendingByProject(tarefas), [tarefas]);

  const metrics = useMemo(() => {
    const scopeTasks =
      selectedProjectId === 'all'
        ? tarefas
        : tarefas.filter(t => t.id_projeto === selectedProjectId);

    const total = scopeTasks.length;
    const pendentes = scopeTasks.filter(t => t.status === 'pendente').length;
    const emAndamento = scopeTasks.filter(t => t.status === 'em_andamento').length;
    const concluidas = scopeTasks.filter(t => t.status === 'concluido').length;
    const urgentes = scopeTasks.filter(t => {
      if (t.status === 'concluido') return false;
      const due = calculateDueStatus(t.data_limite, t.status);
      return due.status === 'expired' || due.status === 'today';
    }).length;
    const progressPct = total > 0 ? Math.round((concluidas / total) * 100) : 0;

    return { total, pendentes, emAndamento, concluidas, urgentes, progressPct };
  }, [tarefas, selectedProjectId]);

  const totalActiveGlobal = useMemo(
    () => tarefas.filter(t => t.status !== 'concluido').length,
    [tarefas]
  );

  const filteredTasks = useMemo(() => {
    return filterTasks(tarefas, selectedProjectId, statusFilter, searchQuery);
  }, [tarefas, selectedProjectId, statusFilter, searchQuery]);

  const toggleMediaExpand = (taskId: string) => {
    setExpandedMediaIds(prev => ({
      ...prev,
      [taskId]: !prev[taskId]
    }));
  };

  // Authentication Handlers (Supabase Auth + Fallback PHP/Local)
  const handleAuthLogin = async (
    email: string,
    password: string
  ): Promise<{ ok: boolean; error?: string }> => {
    const adminEmail = 'lima7emilly12@gmail.com';
    if (email.trim().toLowerCase() !== adminEmail.toLowerCase()) {
      return {
        ok: false,
        error: 'Acesso negado: Plataforma de uso exclusivo do administrador.'
      };
    }

    const sb = getSupabaseClient();
    if (sb) {
      const { data, error } = await sb.auth.signInWithPassword({ email, password });
      if (error || !data.session) {
        return {
          ok: false,
          error:
            error?.message === 'Invalid login credentials'
              ? 'E-mail ou senha inválidos.'
              : error?.message || 'Não foi possível autenticar no Supabase.'
        };
      }
      setIsAuthenticated(true);
      setCurrentUserId(data.user.id);
      setCurrentUserEmail(data.user.email || email);
      setSupabaseAccessToken(data.session.access_token);
      await loadFromSupabaseDirect();
      return { ok: true };
    }

    if (backendConnected) {
      try {
        const res = await fetch('./api.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          return { ok: false, error: data.error || 'E-mail ou senha inválidos.' };
        }
        if (data.csrf_token) setCsrfToken(data.csrf_token);
        if (Array.isArray(data.projetos)) setProjetos(data.projetos);
        if (Array.isArray(data.tarefas)) setTarefas(data.tarefas);
        setCurrentUserEmail(data.admin_email || email);
        setIsAuthenticated(true);
        return { ok: true };
      } catch {
        return { ok: false, error: 'Erro de conexão com o servidor.' };
      }
    }

    const savedEmail = localStorage.getItem('app_local_email') || 'lima7emilly12@gmail.com';
    const savedPass = localStorage.getItem('app_local_pass') || '14021976Ee@';
    if (email.toLowerCase() === savedEmail.toLowerCase() && password === savedPass) {
      sessionStorage.removeItem('app_local_locked');
      setCurrentUserEmail(email);
      setIsAuthenticated(true);
      return { ok: true };
    }
    return { ok: false, error: 'E-mail ou senha inválidos.' };
  };

  const handleAuthForgotPassword = async (
    email: string
  ): Promise<{ ok: boolean; error?: string; message?: string }> => {
    const sb = getSupabaseClient();
    if (!sb) {
      return {
        ok: false,
        error: 'Configure o Supabase no arquivo .env para usar a recuperação de senha por e-mail.'
      };
    }

    const basePrefix = window.location.pathname.match(/^(\/novoapp)(?:\/.*)?$/i)?.[1] || '';
    const redirectTo = `${window.location.origin}${basePrefix}/reset-password`;

    const { error } = await sb.auth.resetPasswordForEmail(email, { redirectTo });
    if (error) {
      return { ok: false, error: error.message };
    }
    return {
      ok: true,
      message: 'Enviamos um link de redefinição de senha para o seu e-mail.'
    };
  };

  const handleAuthResetPassword = async (
    newPassword: string
  ): Promise<{ ok: boolean; error?: string; message?: string }> => {
    const sb = getSupabaseClient();
    if (!sb) {
      return { ok: false, error: 'Cliente Supabase não configurado.' };
    }

    const { error } = await sb.auth.updateUser({ password: newPassword });
    if (error) {
      return { ok: false, error: error.message };
    }
    return { ok: true, message: 'Sua senha foi atualizada com sucesso!' };
  };

  const handleLockScreen = async () => {
    const sb = getSupabaseClient();
    if (sb) {
      await sb.auth.signOut();
    }
    if (backendConnected) {
      try {
        const res = await fetch('./api.php?action=logout', {
          method: 'POST',
          headers: getAuthHeaders(),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.csrf_token) setCsrfToken(data.csrf_token);
      } catch {
        // ignore
      }
    } else {
      sessionStorage.setItem('app_local_locked', '1');
    }
    setIsAuthenticated(false);
    setCurrentUserId('');
    setCurrentUserEmail('');
    setSupabaseAccessToken('');
    navigate('/login', true);
  };

  // Project Handlers
  const handleCreateProject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newProjectNome.trim()) return;

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      const newId = `proj_${Date.now()}`;
      const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);
      const newProj: Projeto = {
        id: newId,
        nome: newProjectNome.trim(),
        descricao: newProjectDesc.trim(),
        cor: newProjectCor,
        criado_em: nowStr
      };
      const { error } = await sb.from('projetos').insert({
        id: newId,
        user_id: currentUserId,
        nome: newProj.nome,
        descricao: newProj.descricao,
        cor: newProj.cor
      });
      if (!error) {
        setProjetos(prev => [...prev, newProj]);
        setSelectedProjectId(newId);
      }
    } else if (backendConnected) {
      try {
        const res = await fetch('./api.php?action=create_project', {
          method: 'POST',
          headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
          credentials: 'same-origin',
          body: JSON.stringify({
            nome: newProjectNome.trim(),
            descricao: newProjectDesc.trim(),
            cor: newProjectCor,
            csrf_token: csrfToken
          })
        });
        const data = await res.json();
        if (data.ok) {
          setProjetos(data.projetos);
          if (data.project?.id) setSelectedProjectId(data.project.id);
        }
      } catch {
        // fallback
      }
    } else {
      const newId = `proj_${Date.now()}`;
      const newProj: Projeto = {
        id: newId,
        nome: newProjectNome.trim(),
        descricao: newProjectDesc.trim(),
        cor: newProjectCor,
        criado_em: new Date().toISOString().replace('T', ' ').substring(0, 19)
      };
      setProjetos(prev => [...prev, newProj]);
      setSelectedProjectId(newId);
    }

    setNewProjectNome('');
    setNewProjectDesc('');
    setNewProjectCor('blue');
    setShowNewProjectModal(false);
  };

  const handleDeleteProject = async (projId: string) => {
    if (!window.confirm('Excluir esta pasta de projeto e todas as suas ideias vinculadas?')) {
      return;
    }

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      await sb.from('projetos').delete().eq('id', projId);
      setProjetos(prev => prev.filter(p => p.id !== projId));
      setTarefas(prev => prev.filter(t => t.id_projeto !== projId));
    } else if (backendConnected) {
      try {
        const res = await fetch('./api.php?action=delete_project', {
          method: 'POST',
          headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
          credentials: 'same-origin',
          body: JSON.stringify({ project_id: projId, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.ok) {
          setProjetos(data.projetos);
          setTarefas(data.tarefas);
        }
      } catch {
        // fallback
      }
    } else {
      setProjetos(prev => prev.filter(p => p.id !== projId));
      setTarefas(prev => prev.filter(t => t.id_projeto !== projId));
    }

    if (selectedProjectId === projId) {
      setSelectedProjectId('all');
    }
  };

  // Task Handlers
  const handleCreateTask = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTaskTitulo.trim()) return;
    const targetProj =
      newTaskProjeto ||
      (selectedProjectId !== 'all' ? selectedProjectId : projetos[0]?.id || 'proj_default');

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      let mediaObj: AnexoMedia | null = null;
      if (newTaskFile) {
        const category = getMediaCategory(newTaskFile.name);
        mediaObj = {
          nome_original: newTaskFile.name,
          caminho: URL.createObjectURL(newTaskFile),
          tipo: category,
          tamanho_formatado: `${(newTaskFile.size / (1024 * 1024)).toFixed(1)} MB`
        };
      }
      const newId = `task_${Date.now()}`;
      const newTask: Tarefa = {
        id: newId,
        id_projeto: targetProj,
        titulo: newTaskTitulo.trim(),
        descricao_detalhada: newTaskDesc.trim(),
        prioridade: newTaskPrioridade,
        status: newTaskStatus,
        data_limite: newTaskDataLimite,
        criado_em: new Date().toISOString().replace('T', ' ').substring(0, 19),
        anexo: mediaObj
      };
      const { error } = await sb.from('tarefas').insert({
        id: newId,
        user_id: currentUserId,
        id_projeto: targetProj,
        titulo: newTask.titulo,
        descricao_detalhada: newTask.descricao_detalhada,
        prioridade: newTask.prioridade,
        status: newTask.status,
        data_limite: newTaskDataLimite || null,
        anexo: mediaObj
      });
      if (!error) {
        setTarefas(prev => [newTask, ...prev]);
      }
    } else if (backendConnected) {
      try {
        const formData = new FormData();
        formData.append('action', 'create_task');
        formData.append('csrf_token', csrfToken);
        formData.append('id_projeto', targetProj);
        formData.append('titulo', newTaskTitulo.trim());
        formData.append('descricao_detalhada', newTaskDesc.trim());
        formData.append('prioridade', newTaskPrioridade);
        formData.append('status', newTaskStatus);
        formData.append('data_limite', newTaskDataLimite);
        if (newTaskFile) {
          formData.append('anexo', newTaskFile);
        }

        const res = await fetch('./api.php', {
          method: 'POST',
          headers: getAuthHeaders(),
          credentials: 'same-origin',
          body: formData
        });
        const data = await res.json();
        if (data.ok) {
          setTarefas(data.tarefas);
        }
      } catch {
        // fallback
      }
    } else {
      let mediaObj: AnexoMedia | null = null;
      if (newTaskFile) {
        const category = getMediaCategory(newTaskFile.name);
        mediaObj = {
          nome_original: newTaskFile.name,
          caminho: URL.createObjectURL(newTaskFile),
          tipo: category,
          tamanho_formatado: `${(newTaskFile.size / (1024 * 1024)).toFixed(1)} MB`
        };
      }

      const newTask: Tarefa = {
        id: `task_${Date.now()}`,
        id_projeto: targetProj,
        titulo: newTaskTitulo.trim(),
        descricao_detalhada: newTaskDesc.trim(),
        prioridade: newTaskPrioridade,
        status: newTaskStatus,
        data_limite: newTaskDataLimite,
        criado_em: new Date().toISOString().replace('T', ' ').substring(0, 19),
        anexo: mediaObj
      };
      setTarefas(prev => [newTask, ...prev]);
    }

    setNewTaskTitulo('');
    setNewTaskDesc('');
    setNewTaskDataLimite('');
    setNewTaskStatus('pendente');
    setNewTaskFile(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleUpdateTaskStatus = async (taskId: string, newStatus: TaskStatus) => {
    setTarefas(prev =>
      prev.map(t => (t.id === taskId ? { ...t, status: newStatus } : t))
    );

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      await sb.from('tarefas').update({ status: newStatus }).eq('id', taskId);
    } else if (backendConnected) {
      try {
        const res = await fetch('./api.php?action=update_status', {
          method: 'POST',
          headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
          credentials: 'same-origin',
          body: JSON.stringify({
            task_id: taskId,
            status: newStatus,
            csrf_token: csrfToken
          })
        });
        const data = await res.json();
        if (data.ok && Array.isArray(data.tarefas)) {
          setTarefas(data.tarefas);
        }
      } catch {
        // optimistic update kept
      }
    }
  };

  const handleDeleteTask = async (taskId: string) => {
    if (!window.confirm('Excluir esta ideia permanentemente?')) return;

    setTarefas(prev => prev.filter(t => t.id !== taskId));

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      await sb.from('tarefas').delete().eq('id', taskId);
    } else if (backendConnected) {
      try {
        await fetch('./api.php?action=delete_task', {
          method: 'POST',
          headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
          credentials: 'same-origin',
          body: JSON.stringify({ task_id: taskId, csrf_token: csrfToken })
        });
      } catch {
        // ignore
      }
    }
  };

  const handleOpenEdit = (task: Tarefa) => {
    setEditingTask({ ...task });
    setEditTaskNewFile(null);
    setEditTaskRemoveMedia(false);
    setShowEditTaskModal(true);
  };

  const handleSaveEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTask || !editingTask.titulo.trim()) return;

    const sb = getSupabaseClient();
    if (sb && currentUserId && !backendConnected) {
      await sb
        .from('tarefas')
        .update({
          id_projeto: editingTask.id_projeto,
          titulo: editingTask.titulo.trim(),
          descricao_detalhada: editingTask.descricao_detalhada || '',
          prioridade: editingTask.prioridade,
          status: editingTask.status,
          data_limite: editingTask.data_limite || null,
          anexo: editingTask.anexo
        })
        .eq('id', editingTask.id);
      setTarefas(prev =>
        prev.map(t => (t.id === editingTask.id ? editingTask : t))
      );
    } else if (backendConnected) {
      try {
        const formData = new FormData();
        formData.append('action', 'update_task');
        formData.append('csrf_token', csrfToken);
        formData.append('task_id', editingTask.id);
        formData.append('id_projeto', editingTask.id_projeto);
        formData.append('titulo', editingTask.titulo.trim());
        formData.append('descricao_detalhada', editingTask.descricao_detalhada || '');
        formData.append('prioridade', editingTask.prioridade);
        formData.append('status', editingTask.status);
        formData.append('data_limite', editingTask.data_limite || '');
        formData.append('remover_anexo', editTaskRemoveMedia ? '1' : '0');
        if (editTaskNewFile) {
          formData.append('anexo', editTaskNewFile);
        }

        const res = await fetch('./api.php', {
          method: 'POST',
          headers: getAuthHeaders(),
          credentials: 'same-origin',
          body: formData
        });
        const data = await res.json();
        if (data.ok && Array.isArray(data.tarefas)) {
          setTarefas(data.tarefas);
        }
      } catch {
        // fallback
      }
    } else {
      setTarefas(prev =>
        prev.map(t => (t.id === editingTask.id ? editingTask : t))
      );
    }

    setShowEditTaskModal(false);
    setEditingTask(null);
    setEditTaskNewFile(null);
    setEditTaskRemoveMedia(false);
  };

  // Backup Export & Import
  const handleExportBackup = () => {
    const payload = JSON.stringify({ projetos, tarefas }, null, 2);
    const blob = new Blob([payload], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `backlog_backup_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleImportBackup = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      const text = await file.text();
      const parsed = JSON.parse(text);
      if (!Array.isArray(parsed.projetos) || !Array.isArray(parsed.tarefas)) {
        alert('Arquivo de backup inválido. Deve conter "projetos" e "tarefas".');
        return;
      }

      if (backendConnected) {
        const res = await fetch('./api.php?action=import_backup', {
          method: 'POST',
          headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
          credentials: 'same-origin',
          body: JSON.stringify({ backup_json: text, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.ok) {
          setProjetos(data.projetos);
          setTarefas(data.tarefas);
          alert('Backup restaurado com sucesso!');
        }
      } else {
        setProjetos(parsed.projetos);
        setTarefas(parsed.tarefas);
        alert('Backup restaurado com sucesso!');
      }
    } catch {
      alert('Não foi possível ler o arquivo JSON.');
    } finally {
      if (backupInputRef.current) backupInputRef.current.value = '';
    }
  };

  const activeFontFamilyCss = getFontFamilyCss(fontFamily);

  // Render Task Card
  const renderTaskCard = (task: Tarefa) => {
    const isDone = task.status === 'concluido';
    const isInProgress = task.status === 'em_andamento';
    const due = calculateDueStatus(task.data_limite, task.status);
    const proj = projetos.find(p => p.id === task.id_projeto);
    const projColor = FOLDER_COLOR_MAP[proj?.cor || 'blue'] || FOLDER_COLOR_MAP.blue;
    const isMediaOpen = Boolean(expandedMediaIds[task.id]);

    const prioClass =
      task.prioridade === 'Alta'
        ? theme === 'dark'
          ? 'bg-rose-950/50 text-rose-300 border-rose-800/50'
          : 'bg-rose-50 text-rose-700 border-rose-200'
        : task.prioridade === 'Média'
          ? theme === 'dark'
            ? 'bg-amber-950/40 text-amber-300 border-amber-800/50'
            : 'bg-amber-50 text-amber-700 border-amber-200'
          : theme === 'dark'
            ? 'bg-zinc-800 text-zinc-400 border-zinc-700/60'
            : 'bg-slate-100 text-slate-600 border-slate-200';

    const dueBadgeClass =
      due.status === 'expired'
        ? theme === 'dark'
          ? 'text-rose-300 bg-rose-950/70 border-rose-800/60 font-medium'
          : 'text-rose-700 bg-rose-50 border-rose-200 font-medium'
        : due.status === 'today'
          ? theme === 'dark'
            ? 'text-amber-300 bg-amber-950/70 border-amber-800/50 font-medium'
            : 'text-amber-800 bg-amber-50 border-amber-200 font-medium'
          : due.status === 'soon'
            ? theme === 'dark'
              ? 'text-sky-300 bg-sky-950/60 border-sky-800/50'
              : 'text-sky-700 bg-sky-50 border-sky-200'
            : theme === 'dark'
              ? 'text-zinc-400 bg-zinc-900/60 border-zinc-800'
              : 'text-slate-600 bg-slate-100 border-slate-200';

    const cardBorder =
      due.status === 'expired'
        ? theme === 'dark'
          ? 'border-rose-900/60 bg-rose-950/15 hover:border-rose-800'
          : 'border-rose-300 bg-rose-50/40 hover:border-rose-400'
        : due.status === 'today'
          ? theme === 'dark'
            ? 'border-amber-900/60 bg-amber-950/15 hover:border-amber-800'
            : 'border-amber-300 bg-amber-50/40 hover:border-amber-400'
          : theme === 'dark'
            ? 'border-zinc-800/90 bg-[#18181c] hover:bg-[#1e1e24] hover:border-zinc-700'
            : 'border-[#d5dbe1] bg-white hover:border-slate-300 shadow-2xs';

    return (
      <div
        key={task.id}
        draggable
        onDragStart={() => setDraggedTaskId(task.id)}
        onDragEnd={() => {
          setDraggedTaskId(null);
          setDragOverColumn(null);
        }}
        className={`group border rounded-xl p-3.5 transition-all flex flex-col gap-2.5 cursor-grab active:cursor-grabbing ${cardBorder} ${
          draggedTaskId === task.id ? 'opacity-50 scale-[0.98]' : ''
        }`}
      >
        <div className="flex items-start justify-between gap-2.5">
          <div className="flex items-start gap-2.5 flex-1 min-w-0">
            <button
              type="button"
              onClick={() =>
                handleUpdateTaskStatus(task.id, isDone ? 'pendente' : 'concluido')
              }
              className={`mt-0.5 w-4 h-4 rounded border flex items-center justify-center transition-colors cursor-pointer shrink-0 ${
                isDone
                  ? 'bg-emerald-600 border-emerald-600 text-white'
                  : isInProgress
                    ? 'border-amber-500 bg-amber-500/15 text-amber-500'
                    : theme === 'dark'
                      ? 'border-zinc-600 hover:border-zinc-400 bg-[#151518]'
                      : 'border-slate-400 hover:border-slate-600 bg-white'
              }`}
              title={isDone ? 'Reabrir ideia' : 'Marcar como concluída'}
            >
              {isDone && <Check className="w-3 h-3 stroke-[3]" />}
              {isInProgress && <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />}
            </button>

            <div className="flex-1 min-w-0">
              <div className="flex flex-wrap items-center gap-1.5">
                <span
                  className={`text-sm font-semibold tracking-tight leading-snug ${
                    isDone
                      ? theme === 'dark'
                        ? 'line-through text-zinc-500'
                        : 'line-through text-slate-400'
                      : theme === 'dark'
                        ? 'text-zinc-100'
                        : 'text-slate-900'
                  }`}
                >
                  {task.titulo}
                </span>
              </div>

              {/* Badges Row */}
              <div className="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span
                  className={`text-[0.7rem] px-1.5 py-0.5 rounded border uppercase tracking-wider font-mono ${prioClass}`}
                >
                  {task.prioridade}
                </span>

                {task.data_limite && (
                  <span
                    className={`text-[0.7rem] px-1.5 py-0.5 rounded border font-mono flex items-center gap-1 ${dueBadgeClass}`}
                  >
                    <Calendar className="w-2.5 h-2.5 opacity-75" />
                    <span>{due.label}</span>
                  </span>
                )}

                {selectedProjectId === 'all' && proj && (
                  <span
                    className={`text-[0.7rem] px-1.5 py-0.5 rounded border flex items-center gap-1.5 ${
                      theme === 'dark' ? projColor.badgeDark : projColor.badgeLight
                    }`}
                  >
                    <span className={`w-1.5 h-1.5 rounded-full ${projColor.dot}`} />
                    <span className="truncate max-w-[8rem]">{proj.nome}</span>
                  </span>
                )}
              </div>

              {task.descricao_detalhada && (
                <p
                  className={`mt-2 leading-relaxed text-xs whitespace-pre-line ${
                    isDone
                      ? 'line-through opacity-60'
                      : theme === 'dark'
                        ? 'text-zinc-400'
                        : 'text-slate-600'
                  }`}
                >
                  {task.descricao_detalhada}
                </p>
              )}

              {/* Anexo sob demanda */}
              {task.anexo && (
                <div className="mt-2.5 pt-2 border-t border-zinc-200/60 dark:border-zinc-800/80">
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => toggleMediaExpand(task.id)}
                      className={`text-xs px-2.5 py-1 rounded-lg border font-medium flex items-center gap-1.5 cursor-pointer transition-colors ${
                        isMediaOpen
                          ? 'bg-blue-600/10 border-blue-500/40 text-blue-500'
                          : theme === 'dark'
                            ? 'bg-[#151518] hover:bg-zinc-800 border-zinc-700/80 text-zinc-300'
                            : 'bg-[#f4f6f8] hover:bg-slate-200/80 border-slate-300 text-slate-700'
                      }`}
                    >
                      {isMediaOpen ? (
                        <>
                          <EyeOff className="w-3.5 h-3.5 text-blue-500" />
                          <span>Ocultar</span>
                        </>
                      ) : (
                        <>
                          <Eye className="w-3.5 h-3.5 text-blue-500" />
                          <span>Anexo</span>
                        </>
                      )}
                      <span className="text-[0.7rem] opacity-75 font-mono truncate max-w-[8rem]">
                        ({task.anexo.nome_original})
                      </span>
                    </button>

                    {task.anexo.tamanho_formatado && (
                      <span className="text-[0.7rem] text-zinc-500 font-mono">
                        {task.anexo.tamanho_formatado}
                      </span>
                    )}
                  </div>

                  {isMediaOpen && (
                    <div
                      className={`mt-2 p-2 rounded-xl border ${
                        theme === 'dark'
                          ? 'bg-[#101012] border-zinc-800'
                          : 'bg-[#eef2f6] border-[#d5dbe1]'
                      }`}
                    >
                      {task.anexo.tipo === 'imagem' && (
                        <div className="space-y-1.5">
                          <div className="relative inline-block">
                            <img
                              src={task.anexo.caminho}
                              alt={task.anexo.nome_original}
                              className="max-h-52 rounded-lg object-contain border border-zinc-300 dark:border-zinc-700 bg-black/5 dark:bg-black/40 cursor-pointer hover:opacity-95 transition-opacity"
                              onClick={() => setPreviewMediaUrl(task.anexo!.caminho)}
                            />
                            <button
                              type="button"
                              onClick={() => setPreviewMediaUrl(task.anexo!.caminho)}
                              className="absolute bottom-2 right-2 p-1.5 rounded-md bg-black/75 hover:bg-black text-white text-xs flex items-center gap-1 cursor-pointer"
                            >
                              <ZoomIn className="w-3 h-3" />
                              <span className="text-[0.7rem]">Ampliar</span>
                            </button>
                          </div>
                        </div>
                      )}

                      {task.anexo.tipo === 'video' && (
                        <div className="max-w-md space-y-1.5">
                          <video
                            src={task.anexo.caminho}
                            controls
                            playsInline
                            className="w-full max-h-56 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-black"
                          />
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Rodapé do Card: Controles Rápidos de Status + Data */}
              <div className="flex flex-wrap items-center justify-between gap-2 mt-2.5 pt-2 border-t border-zinc-200/40 dark:border-zinc-800/50">
                <span className="text-[0.7rem] text-zinc-400 dark:text-zinc-500 font-mono">
                  {formatDateTime(task.criado_em)}
                </span>

                <div className="flex items-center gap-1">
                  {task.status === 'pendente' && (
                    <button
                      type="button"
                      onClick={() => handleUpdateTaskStatus(task.id, 'em_andamento')}
                      className={`text-[0.7rem] px-2 py-0.5 rounded-md border flex items-center gap-1 transition-colors cursor-pointer ${
                        theme === 'dark'
                          ? 'bg-amber-950/30 hover:bg-amber-900/50 text-amber-300 border-amber-800/50'
                          : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200'
                      }`}
                      title="Mover para Em Andamento"
                    >
                      <Play className="w-2.5 h-2.5" />
                      <span>Iniciar</span>
                    </button>
                  )}

                  {task.status === 'em_andamento' && (
                    <>
                      <button
                        type="button"
                        onClick={() => handleUpdateTaskStatus(task.id, 'pendente')}
                        className={`text-[0.7rem] px-1.5 py-0.5 rounded-md border flex items-center gap-1 transition-colors cursor-pointer ${
                          theme === 'dark'
                            ? 'bg-zinc-800 hover:bg-zinc-700 text-zinc-300 border-zinc-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-600 border-slate-200'
                        }`}
                        title="Voltar para Pendente"
                      >
                        <ArrowLeft className="w-2.5 h-2.5" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleUpdateTaskStatus(task.id, 'concluido')}
                        className={`text-[0.7rem] px-2 py-0.5 rounded-md border flex items-center gap-1 transition-colors cursor-pointer ${
                          theme === 'dark'
                            ? 'bg-emerald-950/40 hover:bg-emerald-900/60 text-emerald-300 border-emerald-800/50'
                            : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200'
                        }`}
                        title="Concluir Ideia"
                      >
                        <Check className="w-2.5 h-2.5" />
                        <span>Concluir</span>
                      </button>
                    </>
                  )}

                  {task.status === 'concluido' && (
                    <button
                      type="button"
                      onClick={() => handleUpdateTaskStatus(task.id, 'pendente')}
                      className={`text-[0.7rem] px-2 py-0.5 rounded-md border flex items-center gap-1 transition-colors cursor-pointer ${
                        theme === 'dark'
                          ? 'bg-zinc-800 hover:bg-zinc-700 text-zinc-400 border-zinc-700'
                          : 'bg-slate-100 hover:bg-slate-200 text-slate-600 border-slate-200'
                      }`}
                      title="Reabrir ideia"
                    >
                      <RotateCcw className="w-2.5 h-2.5" />
                      <span>Reabrir</span>
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Botões Editar / Excluir */}
          <div className="flex items-center gap-0.5 shrink-0">
            <button
              type="button"
              onClick={() => handleOpenEdit(task)}
              className={`p-1.5 rounded-md transition-colors cursor-pointer ${
                theme === 'dark'
                  ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200'
              }`}
              title="Editar ideia"
            >
              <Edit3 className="w-3.5 h-3.5" />
            </button>

            <button
              type="button"
              onClick={() => handleDeleteTask(task.id)}
              className={`p-1.5 rounded-md transition-colors cursor-pointer ${
                theme === 'dark'
                  ? 'text-zinc-500 hover:text-rose-400 hover:bg-rose-950/30'
                  : 'text-slate-400 hover:text-rose-600 hover:bg-rose-50'
              }`}
              title="Excluir ideia"
            >
              <Trash2 className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <ProtectedRoute
      isAuthenticated={isAuthenticated}
      isLoading={isLoadingAuth}
      route={route}
      navigate={navigate}
      theme={theme}
      fontFamilyCss={activeFontFamilyCss}
      fallbackAuthScreen={
        <AuthScreen
          route={route}
          navigate={navigate}
          theme={theme}
          onToggleTheme={() => setTheme(prev => (prev === 'dark' ? 'light' : 'dark'))}
          fontFamilyCss={activeFontFamilyCss}
          supabaseEnabled={supabaseEnabled}
          onLogin={handleAuthLogin}
          onForgotPassword={handleAuthForgotPassword}
          onResetPassword={handleAuthResetPassword}
        />
      }
    >
      <div
        style={{ fontFamily: activeFontFamilyCss }}
        className={`min-h-screen flex flex-col transition-colors duration-200 ${
          theme === 'dark'
            ? 'bg-[#09090b] text-zinc-200'
            : 'bg-[#eaedf1] text-slate-800'
        }`}
      >
      {/* Top Header */}
      <header
        className={`h-14 border-b flex items-center justify-between px-3 sm:px-4 lg:px-6 sticky top-0 z-20 transition-colors ${
          theme === 'dark'
            ? 'bg-[#121215] border-zinc-800'
            : 'bg-[#f4f6f8] border-[#d8dee4]'
        }`}
      >
        <div className="flex items-center gap-2 sm:gap-3">
          <button
            onClick={() => setSidebarOpen(prev => !prev)}
            className={`md:hidden p-2 rounded transition-colors ${
              theme === 'dark'
                ? 'text-zinc-400 hover:text-white hover:bg-zinc-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200'
            }`}
            aria-label="Abrir menu de pastas"
          >
            <Menu className="w-5 h-5" />
          </button>

          <div className="flex items-center gap-2.5">
            <div
              className={`w-8 h-8 rounded-lg border flex items-center justify-center ${
                theme === 'dark'
                  ? 'bg-blue-600/15 border-blue-500/30 text-blue-400'
                  : 'bg-blue-600 text-white border-blue-600 shadow-2xs'
              }`}
            >
              <CheckSquare className="w-4 h-4" />
            </div>
            <h1
              className={`font-bold text-sm sm:text-base tracking-tight leading-none ${
                theme === 'dark' ? 'text-zinc-100' : 'text-slate-900'
              }`}
            >
              Backlog
            </h1>
          </div>

          {/* Seletor Rápido de Visualização no Header: Kanban vs Lista */}
          <div
            className={`hidden sm:flex items-center p-0.5 rounded-lg border ml-3 ${
              theme === 'dark'
                ? 'bg-zinc-900 border-zinc-800'
                : 'bg-slate-200/80 border-slate-300'
            }`}
          >
            <button
              type="button"
              onClick={() => setViewMode('kanban')}
              className={`px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer ${
                viewMode === 'kanban'
                  ? theme === 'dark'
                    ? 'bg-zinc-800 text-white shadow-2xs'
                    : 'bg-white text-slate-900 shadow-2xs'
                  : 'text-zinc-500 hover:text-zinc-300'
              }`}
            >
              <LayoutGrid className="w-3.5 h-3.5 text-blue-500" />
              <span>Kanban</span>
            </button>
            <button
              type="button"
              onClick={() => setViewMode('list')}
              className={`px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer ${
                viewMode === 'list'
                  ? theme === 'dark'
                    ? 'bg-zinc-800 text-white shadow-2xs'
                    : 'bg-white text-slate-900 shadow-2xs'
                  : 'text-zinc-500 hover:text-zinc-300'
              }`}
            >
              <List className="w-3.5 h-3.5 text-blue-500" />
              <span>Lista</span>
            </button>
          </div>
        </div>

        {/* Direita: Alternar Tema, Backup, Trancar e Configurações */}
        <div className="flex items-center gap-2 ml-auto">
          <button
            type="button"
            onClick={() => setTheme(prev => (prev === 'dark' ? 'light' : 'dark'))}
            className={`p-2 rounded-lg border transition-colors cursor-pointer ${
              theme === 'dark'
                ? 'bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border-zinc-800'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300 shadow-2xs'
            }`}
            title={theme === 'dark' ? 'Mudar para Tema White' : 'Mudar para Tema Black'}
            aria-label="Alternar tema"
          >
            {theme === 'dark' ? (
              <Sun className="w-4 h-4 text-amber-400" />
            ) : (
              <Moon className="w-4 h-4 text-slate-700" />
            )}
          </button>

          <button
            type="button"
            onClick={handleExportBackup}
            className={`text-xs px-2.5 py-1.5 rounded-lg border hidden md:flex items-center gap-1.5 transition-colors cursor-pointer ${
              theme === 'dark'
                ? 'bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border-zinc-800'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'
            }`}
            title="Exportar cópia de segurança (JSON)"
          >
            <Download className="w-3.5 h-3.5 text-emerald-500" />
            <span>Backup</span>
          </button>

          <button
            type="button"
            onClick={handleLockScreen}
            className={`text-xs px-2.5 py-1.5 rounded-lg border flex items-center gap-1.5 transition-colors cursor-pointer ${
              theme === 'dark'
                ? 'bg-zinc-900 hover:bg-rose-950/40 text-zinc-300 hover:text-rose-300 border-zinc-800 hover:border-rose-900/50'
                : 'bg-white hover:bg-rose-50 text-slate-700 hover:text-rose-700 border-slate-300'
            }`}
            title="Trancar sessão"
          >
            <Lock className="w-3.5 h-3.5 text-amber-500" />
            <span className="hidden sm:inline">Trancar</span>
          </button>

          <button
            type="button"
            onClick={() => {
              setSettingsTab('visual');
              setShowSettingsModal(true);
            }}
            className={`p-2 rounded-lg border transition-all cursor-pointer relative group ${
              theme === 'dark'
                ? 'bg-zinc-900 hover:bg-zinc-800 text-zinc-300 hover:text-white border-zinc-800 hover:border-zinc-700'
                : 'bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 border-slate-300 shadow-2xs'
            }`}
            aria-label="Abrir Configurações"
            title="Configurações Visuais e Backup"
          >
            <Settings className="w-4 h-4 text-zinc-400 group-hover:text-blue-500 transition-colors" />
          </button>
        </div>
      </header>

      {/* PAINEL PRINCIPAL: PASTAS & IDEIAS */}
      <div className="flex-1 flex overflow-hidden">
        {/* Sidebar Lateral de Pastas */}
        <aside
          className={`fixed inset-y-0 left-0 z-30 w-72 border-r transform md:translate-x-0 md:static transition-transform duration-200 ease-in-out flex flex-col pt-14 md:pt-0 ${
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
          } ${
            theme === 'dark'
              ? 'bg-[#121215] border-zinc-800'
              : 'bg-[#f4f6f8] border-[#d8dee4]'
          }`}
        >
          <div
            className={`p-4 border-b flex items-center justify-between ${
              theme === 'dark' ? 'border-zinc-800/80' : 'border-slate-200'
            }`}
          >
            <span className="text-xs font-semibold uppercase tracking-wider text-zinc-500">
              Pastas de Projetos
            </span>
            <button
              type="button"
              onClick={() => setShowNewProjectModal(true)}
              className={`text-xs px-2.5 py-1 rounded-lg border flex items-center gap-1 transition-colors cursor-pointer ${
                theme === 'dark'
                  ? 'bg-blue-600/15 hover:bg-blue-600/25 text-blue-300 border-blue-500/30'
                  : 'bg-blue-600 hover:bg-blue-500 text-white border-blue-600'
              }`}
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Nova Pasta</span>
            </button>
          </div>

          <nav className="flex-1 overflow-y-auto p-2.5 space-y-1">
            <button
              type="button"
              onClick={() => {
                setSelectedProjectId('all');
                setSidebarOpen(false);
              }}
              className={`w-full group flex items-center justify-between px-3 py-2.5 rounded-xl text-xs transition-colors text-left cursor-pointer ${
                selectedProjectId === 'all'
                  ? theme === 'dark'
                    ? 'bg-zinc-800 text-zinc-100 font-semibold'
                    : 'bg-slate-200 text-slate-900 font-semibold'
                  : theme === 'dark'
                    ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/60'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
              }`}
            >
              <div className="flex items-center gap-2.5 truncate">
                <Folder
                  className={`w-4 h-4 ${
                    selectedProjectId === 'all' ? 'text-blue-500' : 'text-zinc-400'
                  }`}
                />
                <span className="truncate">Visão Geral (Todas)</span>
              </div>
              {totalActiveGlobal > 0 && (
                <span
                  className={`text-[0.75rem] px-1.5 py-0.5 rounded-full font-mono border ${
                    theme === 'dark'
                      ? 'bg-zinc-900 border-zinc-700 text-zinc-300'
                      : 'bg-white border-slate-300 text-slate-700'
                  }`}
                >
                  {totalActiveGlobal}
                </span>
              )}
            </button>

            <div className="pt-3 pb-1 px-3 text-[0.7rem] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
              Suas Pastas ({projetos.length})
            </div>

            {projetos.map(p => {
              const isActive = selectedProjectId === p.id;
              const activeCount = pendingCounts[p.id] || 0;
              const folderStyle = FOLDER_COLOR_MAP[p.cor || 'blue'] || FOLDER_COLOR_MAP.blue;
              return (
                <button
                  key={p.id}
                  type="button"
                  onClick={() => {
                    setSelectedProjectId(p.id);
                    setSidebarOpen(false);
                  }}
                  className={`w-full group flex items-center justify-between rounded-xl px-3 py-2.5 text-xs transition-colors text-left cursor-pointer ${
                    isActive
                      ? theme === 'dark'
                        ? 'bg-zinc-800 text-zinc-100 font-semibold'
                        : 'bg-slate-200 text-slate-900 font-semibold'
                      : theme === 'dark'
                        ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/60'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                  }`}
                >
                  <div className="flex items-center gap-2.5 truncate flex-1 min-w-0">
                    <span className={`w-2.5 h-2.5 rounded-full shrink-0 ${folderStyle.dot}`} />
                    <span className="truncate">{p.nome}</span>
                  </div>
                  {activeCount > 0 && (
                    <span
                      className={`text-[0.75rem] px-1.5 py-0.5 rounded-full font-mono border shrink-0 ${
                        theme === 'dark'
                          ? 'bg-zinc-900 border-zinc-800 text-zinc-400'
                          : 'bg-white border-slate-300 text-slate-600'
                      }`}
                    >
                      {activeCount}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>

          {/* Barra de Progresso Geral na Sidebar */}
          <div
            className={`p-3.5 border-t space-y-2 ${
              theme === 'dark'
                ? 'bg-[#121215] border-zinc-800'
                : 'bg-[#f4f6f8] border-slate-200'
            }`}
          >
            <div className="flex items-center justify-between text-[0.75rem]">
              <span className="text-zinc-500 font-medium">Progresso Concluído</span>
              <span className="font-mono font-semibold text-emerald-500">
                {metrics.progressPct}%
              </span>
            </div>
            <div className="w-full h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
              <div
                className="h-full bg-emerald-500 transition-all duration-300"
                style={{ width: `${metrics.progressPct}%` }}
              />
            </div>
            <div className="flex items-center justify-between text-[0.7rem] text-zinc-500 pt-1">
              <span>Total: {tarefas.length} itens</span>
              <button
                type="button"
                onClick={() => {
                  setSettingsTab('visual');
                  setShowSettingsModal(true);
                }}
                className="text-blue-500 hover:underline flex items-center gap-1 cursor-pointer"
              >
                <Settings className="w-3 h-3" />
                <span>Preferências</span>
              </button>
            </div>
          </div>
        </aside>

        {/* Overlay mobile */}
        {sidebarOpen && (
          <div
            onClick={() => setSidebarOpen(false)}
            className="fixed inset-0 bg-black/60 z-20 md:hidden backdrop-blur-2xs"
          />
        )}

        {/* Área Central */}
        <main className="flex-1 flex flex-col overflow-y-auto">
          {/* Cabeçalho da Pasta + KPIs Visuais */}
          <div
            className={`p-4 sm:p-5 border-b space-y-4 ${
              theme === 'dark'
                ? 'border-zinc-800 bg-[#121215]/50'
                : 'border-[#d8dee4] bg-[#f4f6f8]/80'
            }`}
          >
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div className="flex items-center gap-2">
                  <Folder className="w-5 h-5 text-blue-500" />
                  <h2
                    className={`text-base sm:text-lg font-bold tracking-tight ${
                      theme === 'dark' ? 'text-zinc-100' : 'text-slate-900'
                    }`}
                  >
                    {currentProject ? currentProject.nome : 'Backlog'}
                  </h2>
                </div>
                <p
                  className={`text-xs mt-0.5 ${
                    theme === 'dark' ? 'text-zinc-400' : 'text-slate-600'
                  }`}
                >
                  {currentProject
                    ? currentProject.descricao || 'Pasta de ideias e tarefas do projeto.'
                    : 'Visualização consolidada em Quadro Kanban e Lista.'}
                </p>
              </div>

              <div className="flex items-center gap-2">
                {currentProject && (
                  <button
                    type="button"
                    onClick={() => handleDeleteProject(currentProject.id)}
                    className={`text-xs px-2.5 py-1.5 rounded-lg border transition-colors flex items-center gap-1 cursor-pointer ${
                      theme === 'dark'
                        ? 'border-zinc-800 text-rose-400 hover:bg-rose-950/30 hover:border-rose-900'
                        : 'border-slate-300 text-rose-600 hover:bg-rose-50 hover:border-rose-200'
                    }`}
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>Excluir Pasta</span>
                  </button>
                )}
              </div>
            </div>

            {/* Cards Visuais de Métricas (KPIs) */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
              <div
                className={`p-3 rounded-xl border flex items-center justify-between ${
                  theme === 'dark'
                    ? 'bg-[#18181c] border-zinc-800/90'
                    : 'bg-white border-slate-200/90 shadow-2xs'
                }`}
              >
                <div>
                  <span className="text-[0.7rem] uppercase font-mono text-zinc-500 block">
                    Pendentes
                  </span>
                  <span className="text-lg font-bold text-blue-500 font-mono">
                    {metrics.pendentes}
                  </span>
                </div>
                <div className="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-500">
                  <Clock className="w-4 h-4" />
                </div>
              </div>

              <div
                className={`p-3 rounded-xl border flex items-center justify-between ${
                  theme === 'dark'
                    ? 'bg-[#18181c] border-zinc-800/90'
                    : 'bg-white border-slate-200/90 shadow-2xs'
                }`}
              >
                <div>
                  <span className="text-[0.7rem] uppercase font-mono text-zinc-500 block">
                    Em Andamento
                  </span>
                  <span className="text-lg font-bold text-amber-500 font-mono">
                    {metrics.emAndamento}
                  </span>
                </div>
                <div className="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-500">
                  <Play className="w-4 h-4" />
                </div>
              </div>

              <div
                className={`p-3 rounded-xl border flex items-center justify-between ${
                  theme === 'dark'
                    ? 'bg-[#18181c] border-zinc-800/90'
                    : 'bg-white border-slate-200/90 shadow-2xs'
                }`}
              >
                <div>
                  <span className="text-[0.7rem] uppercase font-mono text-zinc-500 block">
                    Concluídas
                  </span>
                  <span className="text-lg font-bold text-emerald-500 font-mono">
                    {metrics.concluidas}
                  </span>
                </div>
                <div className="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                  <Check className="w-4 h-4" />
                </div>
              </div>

              <div
                className={`p-3 rounded-xl border flex items-center justify-between ${
                  theme === 'dark'
                    ? 'bg-[#18181c] border-zinc-800/90'
                    : 'bg-white border-slate-200/90 shadow-2xs'
                }`}
              >
                <div>
                  <span className="text-[0.7rem] uppercase font-mono text-zinc-500 block">
                    Urgentes / Hoje
                  </span>
                  <span
                    className={`text-lg font-bold font-mono ${
                      metrics.urgentes > 0 ? 'text-rose-500' : 'text-zinc-400'
                    }`}
                  >
                    {metrics.urgentes}
                  </span>
                </div>
                <div
                  className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                    metrics.urgentes > 0
                      ? 'bg-rose-500/10 text-rose-500'
                      : 'bg-zinc-500/10 text-zinc-400'
                  }`}
                >
                  <AlertTriangle className="w-4 h-4" />
                </div>
              </div>
            </div>

            {/* Formulário Rápido de Adição */}
            <div
              className={`p-3.5 sm:p-4 rounded-xl border ${
                theme === 'dark'
                  ? 'border-zinc-800 bg-[#16161a]'
                  : 'border-[#d5dbe1] bg-white shadow-2xs'
              }`}
            >
              <form onSubmit={handleCreateTask} className="space-y-3">
                <div className="flex flex-col sm:flex-row gap-2">
                  <input
                    type="text"
                    required
                    value={newTaskTitulo}
                    onChange={e => setNewTaskTitulo(e.target.value)}
                    onFocus={() => setShowQuickFormDetails(true)}
                    placeholder="Adicionar novo item ao backlog..."
                    className={`flex-1 text-xs px-3.5 py-2.5 border rounded-xl focus:outline-none focus:border-blue-500 transition-colors ${
                      theme === 'dark'
                        ? 'bg-[#101013] border-zinc-800 text-zinc-100 placeholder-zinc-500'
                        : 'bg-[#f8fafc] border-slate-300 text-slate-900 placeholder-slate-400'
                    }`}
                  />
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setShowQuickFormDetails(prev => !prev)}
                      className={`text-xs px-3 py-2.5 rounded-xl border transition-colors cursor-pointer ${
                        theme === 'dark'
                          ? 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                          : 'border-slate-300 bg-slate-100 text-slate-600 hover:text-slate-900'
                      }`}
                    >
                      {showQuickFormDetails ? 'Menos opções' : '+ Detalhes / Anexo'}
                    </button>
                    <button
                      type="submit"
                      className="text-xs px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-semibold flex items-center gap-1.5 transition-colors cursor-pointer whitespace-nowrap shadow-xs"
                    >
                      <Plus className="w-4 h-4" />
                      <span>Adicionar</span>
                    </button>
                  </div>
                </div>

                {showQuickFormDetails && (
                  <div className="space-y-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-800/80 animate-in fade-in duration-150">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                      <div>
                        <label className="block text-[0.7rem] uppercase font-mono text-zinc-500 mb-1">
                          Pasta Destino
                        </label>
                        <select
                          value={
                            newTaskProjeto ||
                            (selectedProjectId !== 'all' ? selectedProjectId : '')
                          }
                          onChange={e => setNewTaskProjeto(e.target.value)}
                          className={`w-full text-xs px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                            theme === 'dark'
                              ? 'bg-[#101013] border-zinc-800 text-zinc-200'
                              : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                          }`}
                        >
                          {projetos.map(p => (
                            <option key={p.id} value={p.id}>
                              {p.nome}
                            </option>
                          ))}
                        </select>
                      </div>

                      <div>
                        <label className="block text-[0.7rem] uppercase font-mono text-zinc-500 mb-1">
                          Prioridade
                        </label>
                        <select
                          value={newTaskPrioridade}
                          onChange={e => setNewTaskPrioridade(e.target.value as any)}
                          className={`w-full text-xs px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                            theme === 'dark'
                              ? 'bg-[#101013] border-zinc-800 text-zinc-200'
                              : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                          }`}
                        >
                          <option value="Baixa">Baixa</option>
                          <option value="Média">Média</option>
                          <option value="Alta">Alta</option>
                        </select>
                      </div>

                      <div>
                        <label className="block text-[0.7rem] uppercase font-mono text-zinc-500 mb-1">
                          Coluna Inicial
                        </label>
                        <select
                          value={newTaskStatus}
                          onChange={e => setNewTaskStatus(e.target.value as TaskStatus)}
                          className={`w-full text-xs px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                            theme === 'dark'
                              ? 'bg-[#101013] border-zinc-800 text-zinc-200'
                              : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                          }`}
                        >
                          <option value="pendente">Pendente</option>
                          <option value="em_andamento">Em Andamento</option>
                          <option value="concluido">Concluído</option>
                        </select>
                      </div>

                      <div>
                        <label className="block text-[0.7rem] uppercase font-mono text-zinc-500 mb-1">
                          Prazo / Lembrete
                        </label>
                        <input
                          type="date"
                          value={newTaskDataLimite}
                          onChange={e => setNewTaskDataLimite(e.target.value)}
                          className={`w-full text-xs px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                            theme === 'dark'
                              ? 'bg-[#101013] border-zinc-800 text-zinc-200'
                              : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                          }`}
                        />
                      </div>
                    </div>

                    <textarea
                      rows={2}
                      value={newTaskDesc}
                      onChange={e => setNewTaskDesc(e.target.value)}
                      placeholder="Descrição detalhada, checklist ou anotações (opcional)..."
                      className={`w-full text-xs px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 transition-colors ${
                        theme === 'dark'
                          ? 'bg-[#101013] border-zinc-800 text-zinc-200 placeholder-zinc-500'
                          : 'bg-[#f8fafc] border-slate-300 text-slate-800 placeholder-slate-400'
                      }`}
                    />

                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <input
                          type="file"
                          ref={fileInputRef}
                          accept="image/*,video/*"
                          onChange={e => {
                            if (e.target.files && e.target.files[0]) {
                              setNewTaskFile(e.target.files[0]);
                            }
                          }}
                          className="hidden"
                          id="new-task-file-input"
                        />
                        <label
                          htmlFor="new-task-file-input"
                          className={`text-xs px-3 py-1.5 rounded-lg border flex items-center gap-1.5 cursor-pointer transition-colors ${
                            newTaskFile
                              ? 'border-blue-500/60 bg-blue-500/10 text-blue-500 font-medium'
                              : theme === 'dark'
                                ? 'border-zinc-800 hover:border-zinc-700 bg-[#101013] text-zinc-400 hover:text-zinc-200'
                                : 'border-slate-300 hover:border-slate-400 bg-[#f8fafc] text-slate-600 hover:text-slate-800'
                          }`}
                        >
                          <Paperclip className="w-3.5 h-3.5 text-blue-500" />
                          <span>
                            {newTaskFile ? newTaskFile.name : 'Anexar Foto ou Vídeo'}
                          </span>
                        </label>
                        {newTaskFile && (
                          <button
                            type="button"
                            onClick={() => {
                              setNewTaskFile(null);
                              if (fileInputRef.current) fileInputRef.current.value = '';
                            }}
                            className="text-rose-500 hover:underline text-xs cursor-pointer"
                          >
                            Remover
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </form>
            </div>
          </div>

          {/* Barra de Filtro e Busca */}
          <div
            className={`p-3 sm:p-4 border-b flex flex-col sm:flex-row items-center justify-between gap-3 ${
              theme === 'dark'
                ? 'border-zinc-800 bg-[#121215]/30'
                : 'border-[#d8dee4] bg-[#f4f6f8]/60'
            }`}
          >
            <div className="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
              {[
                { id: 'all', label: 'Todas', count: metrics.total },
                { id: 'pendente', label: 'Pendentes', count: metrics.pendentes },
                { id: 'em_andamento', label: 'Em Andamento', count: metrics.emAndamento },
                { id: 'concluido', label: 'Concluídas', count: metrics.concluidas }
              ].map(tab => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => setStatusFilter(tab.id as any)}
                  className={`text-xs px-3 py-1.5 rounded-lg border whitespace-nowrap transition-colors cursor-pointer ${
                    statusFilter === tab.id
                      ? theme === 'dark'
                        ? 'bg-zinc-800 text-zinc-100 border-zinc-700 font-medium'
                        : 'bg-white text-slate-900 border-slate-300 font-medium shadow-2xs'
                      : 'text-zinc-500 border-transparent hover:bg-slate-200/60 dark:hover:bg-zinc-900'
                  }`}
                >
                  {tab.label}{' '}
                  <span className="ml-1 text-[0.75rem] opacity-70 font-mono">
                    ({tab.count})
                  </span>
                </button>
              ))}
            </div>

            <div className="flex items-center gap-2 w-full sm:w-auto">
              {/* Seletor Mobile Kanban / Lista */}
              <div
                className={`flex sm:hidden items-center p-0.5 rounded-lg border ${
                  theme === 'dark'
                    ? 'bg-zinc-900 border-zinc-800'
                    : 'bg-slate-200 border-slate-300'
                }`}
              >
                <button
                  type="button"
                  onClick={() => setViewMode('kanban')}
                  className={`p-1.5 rounded-md ${
                    viewMode === 'kanban'
                      ? 'bg-blue-600 text-white'
                      : 'text-zinc-500'
                  }`}
                  title="Visão Kanban"
                >
                  <LayoutGrid className="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('list')}
                  className={`p-1.5 rounded-md ${
                    viewMode === 'list'
                      ? 'bg-blue-600 text-white'
                      : 'text-zinc-500'
                  }`}
                  title="Visão em Lista"
                >
                  <List className="w-3.5 h-3.5" />
                </button>
              </div>

              <div className="relative flex-1 sm:w-64 lg:w-72">
                <Search className="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-2.5" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  placeholder="Buscar no backlog..."
                  className={`w-full text-xs pl-8 pr-7 py-1.5 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#151518] border-zinc-800 text-zinc-200 placeholder-zinc-500'
                      : 'bg-white border-slate-300 text-slate-800 placeholder-slate-400'
                  }`}
                />
                {searchQuery && (
                  <button
                    type="button"
                    onClick={() => setSearchQuery('')}
                    className="absolute right-2 top-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* ÁREA DE EXIBIÇÃO: QUADRO KANBAN OU LISTA */}
          <div className="flex-1 p-3 sm:p-4 lg:p-6">
            {viewMode === 'kanban' ? (
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                {(
                  [
                    {
                      status: 'pendente' as TaskStatus,
                      title: 'Pendente',
                      subtitle: 'Para iniciar',
                      dotColor: 'bg-blue-500'
                    },
                    {
                      status: 'em_andamento' as TaskStatus,
                      title: 'Em Andamento',
                      subtitle: 'Em execução',
                      dotColor: 'bg-amber-500'
                    },
                    {
                      status: 'concluido' as TaskStatus,
                      title: 'Concluído',
                      subtitle: 'Finalizado',
                      dotColor: 'bg-emerald-500'
                    }
                  ] as const
                ).map(col => {
                  const colTasks = filteredTasks.filter(t => t.status === col.status);
                  const isDragOver = dragOverColumn === col.status;

                  return (
                    <div
                      key={col.status}
                      onDragOver={e => {
                        e.preventDefault();
                        if (dragOverColumn !== col.status) {
                          setDragOverColumn(col.status);
                        }
                      }}
                      onDragLeave={() => setDragOverColumn(null)}
                      onDrop={e => {
                        e.preventDefault();
                        setDragOverColumn(null);
                        if (draggedTaskId) {
                          handleUpdateTaskStatus(draggedTaskId, col.status);
                          setDraggedTaskId(null);
                        }
                      }}
                      className={`rounded-2xl border p-3 transition-all min-h-[340px] flex flex-col gap-3 ${
                        isDragOver
                          ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-500/5'
                          : theme === 'dark'
                            ? 'bg-[#121215]/90 border-zinc-800/90'
                            : 'bg-[#f4f6f8] border-[#d5dbe1]'
                      }`}
                    >
                      <div
                        className={`flex items-center justify-between pb-2.5 border-b ${
                          theme === 'dark' ? 'border-zinc-800' : 'border-slate-200'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <span className={`w-2.5 h-2.5 rounded-full ${col.dotColor}`} />
                          <div>
                            <h3
                              className={`text-xs font-bold tracking-tight ${
                                theme === 'dark' ? 'text-zinc-100' : 'text-slate-900'
                              }`}
                            >
                              {col.title}
                            </h3>
                            <p className="text-[0.7rem] text-zinc-500">{col.subtitle}</p>
                          </div>
                        </div>
                        <span
                          className={`text-xs px-2 py-0.5 rounded-full font-mono font-semibold border ${
                            theme === 'dark'
                              ? 'bg-zinc-900 border-zinc-800 text-zinc-300'
                              : 'bg-white border-slate-300 text-slate-700'
                          }`}
                        >
                          {colTasks.length}
                        </span>
                      </div>

                      <div className="space-y-2.5 flex-1">
                        {colTasks.length === 0 ? (
                          <div
                            className={`h-40 border border-dashed rounded-xl flex flex-col items-center justify-center text-center p-4 ${
                              theme === 'dark'
                                ? 'border-zinc-800/80 text-zinc-600'
                                : 'border-slate-300 text-slate-400'
                            }`}
                          >
                            <span className="text-xs">Arraste um card para cá</span>
                          </div>
                        ) : (
                          colTasks.map(task => renderTaskCard(task))
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : filteredTasks.length === 0 ? (
              <div
                className={`py-14 text-center border border-dashed rounded-xl ${
                  theme === 'dark'
                    ? 'border-zinc-800 bg-zinc-900/20'
                    : 'border-slate-300 bg-white/70'
                }`}
              >
                <CheckSquare className="w-9 h-9 mx-auto text-zinc-400 dark:text-zinc-600 mb-2.5 opacity-60" />
                <h3
                  className={`text-sm font-medium ${
                    theme === 'dark' ? 'text-zinc-400' : 'text-slate-600'
                  }`}
                >
                  Nenhum item encontrado
                </h3>
                <p className="text-xs text-zinc-400 dark:text-zinc-600 mt-1">
                  {searchQuery
                    ? `Nenhum resultado para "${searchQuery}".`
                    : 'Adicione um item pelo formulário acima.'}
                </p>
              </div>
            ) : (
              <div className="space-y-2.5">
                {filteredTasks.map(task => renderTaskCard(task))}
              </div>
            )}
          </div>
        </main>
      </div>

      {/* MODAL DE CONFIGURAÇÕES VISUAIS & BACKUP */}
      {showSettingsModal && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 z-50 animate-in fade-in duration-150">
          <div
            className={`w-full max-w-2xl rounded-2xl border shadow-2xl overflow-hidden flex flex-col max-h-[90vh] ${
              theme === 'dark'
                ? 'bg-[#121215] border-zinc-800 text-zinc-100'
                : 'bg-[#f4f6f8] border-slate-300 text-slate-900'
            }`}
          >
            {/* Cabeçalho do Modal com Abas */}
            <div
              className={`p-4 border-b flex items-center justify-between ${
                theme === 'dark'
                  ? 'border-zinc-800 bg-[#16161a]'
                  : 'border-slate-200 bg-white'
              }`}
            >
              <div className="flex items-center gap-2">
                <Settings className="w-4 h-4 text-blue-500" />
                <h3 className="font-semibold text-sm">Configurações</h3>
              </div>

              <div className="flex items-center gap-1">
                <button
                  type="button"
                  onClick={() => setSettingsTab('visual')}
                  className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer ${
                    settingsTab === 'visual'
                      ? 'bg-blue-600 text-white'
                      : 'text-zinc-400 hover:text-zinc-200'
                  }`}
                >
                  Estilo & Visual
                </button>
                <button
                  type="button"
                  onClick={() => setSettingsTab('backup')}
                  className={`text-xs px-3 py-1.5 rounded-lg font-medium flex items-center gap-1 transition-colors cursor-pointer ${
                    settingsTab === 'backup'
                      ? 'bg-blue-600 text-white'
                      : 'text-zinc-400 hover:text-zinc-200'
                  }`}
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Backup</span>
                </button>
                <button
                  type="button"
                  onClick={() => setShowSettingsModal(false)}
                  className="text-zinc-400 hover:text-zinc-100 p-1.5 ml-2 rounded-md transition-colors cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>

            {settingsTab === 'visual' ? (
              <div className="p-4 sm:p-6 overflow-y-auto space-y-6 text-xs">
                {/* 1. Tema Black / White */}
                <div>
                  <label className="font-semibold uppercase tracking-wider text-zinc-500 text-[0.75rem] block mb-2">
                    Tema da Interface
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <button
                      type="button"
                      onClick={() => setTheme('dark')}
                      className={`p-3 rounded-xl border flex items-center gap-3 transition-all cursor-pointer text-left ${
                        theme === 'dark'
                          ? 'border-blue-500 bg-blue-500/10 text-white ring-2 ring-blue-500/20 font-semibold'
                          : 'border-zinc-700 bg-zinc-900/60 text-zinc-400 hover:border-zinc-600'
                      }`}
                    >
                      <div className="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-700 flex items-center justify-center shrink-0">
                        <Moon className="w-4 h-4 text-blue-400" />
                      </div>
                      <div>
                        <div className="font-medium text-xs">Modo Black</div>
                        <div className="text-[0.7rem] text-zinc-500 mt-0.5">
                          Noturno chumbo sóbrio
                        </div>
                      </div>
                    </button>

                    <button
                      type="button"
                      onClick={() => setTheme('light')}
                      className={`p-3 rounded-xl border flex items-center gap-3 transition-all cursor-pointer text-left ${
                        theme === 'light'
                          ? 'border-blue-600 bg-blue-50 text-slate-900 ring-2 ring-blue-600/20 font-semibold'
                          : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400'
                      }`}
                    >
                      <div className="w-8 h-8 rounded-lg bg-slate-100 border border-slate-300 flex items-center justify-center shrink-0">
                        <Sun className="w-4 h-4 text-amber-500" />
                      </div>
                      <div>
                        <div className="font-medium text-xs">Modo White</div>
                        <div className="text-[0.7rem] text-slate-500 mt-0.5">
                          Fosco e confortável aos olhos
                        </div>
                      </div>
                    </button>
                  </div>
                </div>

                {/* 2. Estilo de Fonte */}
                <div>
                  <label className="font-semibold uppercase tracking-wider text-zinc-500 text-[0.75rem] block mb-2">
                    Família Tipográfica
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {[
                      {
                        id: 'Helvetica / Arial',
                        name: 'Helvetica / Arial',
                        desc: 'Clássica e neutra de sistema'
                      },
                      {
                        id: 'Montserrat',
                        name: 'Montserrat',
                        desc: 'Moderna e geométrica'
                      },
                      {
                        id: 'Roboto',
                        name: 'Roboto',
                        desc: 'Equilibrada e legível'
                      },
                      {
                        id: 'Open Sans / Lato',
                        name: 'Open Sans / Lato',
                        desc: 'Humanista suave'
                      }
                    ].map(f => {
                      const isSelected = fontFamily === f.id;
                      const sampleFontCss = getFontFamilyCss(f.id as FontFamily);
                      return (
                        <button
                          key={f.id}
                          type="button"
                          onClick={() => setFontFamily(f.id as FontFamily)}
                          style={{ fontFamily: sampleFontCss }}
                          className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                            isSelected
                              ? theme === 'dark'
                                ? 'bg-blue-600/20 border-blue-500 text-blue-300 ring-2 ring-blue-500/20 font-semibold'
                                : 'bg-blue-50 border-blue-600 text-blue-900 ring-2 ring-blue-600/20 font-semibold'
                              : theme === 'dark'
                                ? 'bg-[#18181c] border-zinc-800 text-zinc-400 hover:border-zinc-700'
                                : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300'
                          }`}
                        >
                          <div className="font-semibold text-xs">{f.name}</div>
                          <div className="text-[0.7rem] text-zinc-500 mt-0.5">{f.desc}</div>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* 3. Controle de Escala Tipográfica */}
                <div>
                  <label className="font-semibold uppercase tracking-wider text-zinc-500 text-[0.75rem] block mb-2">
                    Escala Tipográfica
                  </label>
                  <div className="grid grid-cols-3 gap-2.5">
                    {[
                      { id: 'small', label: 'Pequena', size: '13px' },
                      { id: 'medium', label: 'Média', size: '15px' },
                      { id: 'large', label: 'Grande', size: '17px' }
                    ].map(sz => {
                      const isSelected = fontSize === sz.id;
                      return (
                        <button
                          key={sz.id}
                          type="button"
                          onClick={() => setFontSize(sz.id as FontSize)}
                          className={`p-3 rounded-xl border text-center transition-all cursor-pointer ${
                            isSelected
                              ? theme === 'dark'
                                ? 'bg-blue-600/20 border-blue-500 text-blue-300 ring-2 ring-blue-500/20 font-semibold'
                                : 'bg-blue-50 border-blue-600 text-blue-900 ring-2 ring-blue-600/20 font-semibold'
                              : theme === 'dark'
                                ? 'bg-[#18181c] border-zinc-800 text-zinc-400 hover:border-zinc-700'
                                : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300'
                          }`}
                        >
                          <div className="font-semibold text-xs">{sz.label}</div>
                          <div className="text-[0.7rem] text-zinc-500 font-mono mt-0.5">
                            {sz.size}
                          </div>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* 4. Pré-visualização */}
                <div
                  className={`p-4 rounded-xl border ${
                    theme === 'dark'
                      ? 'bg-[#18181c] border-zinc-800'
                      : 'bg-white border-[#d5dbe1] shadow-xs'
                  }`}
                >
                  <div className="flex items-center justify-between mb-2.5">
                    <span className="text-[0.75rem] font-semibold uppercase tracking-wider text-zinc-500 flex items-center gap-1.5">
                      <Sparkles className="w-3.5 h-3.5 text-blue-500" />
                      <span>Pré-visualização em Tempo Real</span>
                    </span>
                    <span className="text-[0.7rem] px-2 py-0.5 rounded font-mono border bg-blue-500/10 text-blue-500 border-blue-500/20">
                      {fontFamily} • {getFontSizePx(fontSize)} •{' '}
                      {theme === 'dark' ? 'Black' : 'White'}
                    </span>
                  </div>

                  <div
                    style={{ fontFamily: activeFontFamilyCss }}
                    className={`p-3.5 rounded-xl border transition-all ${
                      theme === 'dark'
                        ? 'bg-[#101012] border-zinc-800'
                        : 'bg-[#f4f6f8] border-slate-300'
                    }`}
                  >
                    <span className="font-semibold text-xs block mb-1">
                      Backlog
                    </span>
                    <p className="text-xs text-zinc-400 leading-relaxed">
                      Este texto reflete instantaneamente a fonte selecionada ({fontFamily}), a
                      escala tipográfica ({getFontSizePx(fontSize)}) e o contraste do tema.
                    </p>
                  </div>
                </div>
              </div>
            ) : (
              /* ABA DE BACKUP E RESTAURAÇÃO */
              <div className="p-4 sm:p-6 overflow-y-auto space-y-4 text-xs">
                <div
                  className={`p-4 rounded-xl border space-y-3 ${
                    theme === 'dark'
                      ? 'bg-[#18181c] border-zinc-800'
                      : 'bg-white border-slate-200'
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <Download className="w-4 h-4 text-emerald-500" />
                    <h4 className="font-semibold text-xs">
                      Backup e Restauração (JSON)
                    </h4>
                  </div>
                  <p className="text-[0.75rem] text-zinc-500">
                    Exporte ou restaure uma cópia de segurança das suas pastas e itens do backlog.
                  </p>
                  <div className="flex flex-wrap items-center gap-3 pt-1">
                    <button
                      type="button"
                      onClick={handleExportBackup}
                      className="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium flex items-center gap-1.5 cursor-pointer transition-colors"
                    >
                      <Download className="w-3.5 h-3.5" />
                      <span>Exportar Backup (.json)</span>
                    </button>

                    <input
                      type="file"
                      ref={backupInputRef}
                      accept=".json,application/json"
                      onChange={handleImportBackup}
                      className="hidden"
                      id="import-backup-file"
                    />
                    <label
                      htmlFor="import-backup-file"
                      className={`px-3.5 py-2 rounded-lg border font-medium flex items-center gap-1.5 cursor-pointer transition-colors ${
                        theme === 'dark'
                          ? 'border-zinc-700 bg-zinc-800 hover:bg-zinc-700 text-zinc-200'
                          : 'border-slate-300 bg-white hover:bg-slate-100 text-slate-800'
                      }`}
                    >
                      <Upload className="w-3.5 h-3.5 text-blue-500" />
                      <span>Restaurar Backup (.json)</span>
                    </label>
                  </div>
                </div>
              </div>
            )}

            {/* Rodapé do Modal */}
            <div
              className={`p-4 border-t flex items-center justify-between ${
                theme === 'dark'
                  ? 'border-zinc-800 bg-[#16161a]'
                  : 'border-slate-200 bg-white'
              }`}
            >
              <span className="text-[0.75rem] text-zinc-500">
                Preferências salvas automaticamente no navegador.
              </span>
              <button
                type="button"
                onClick={() => setShowSettingsModal(false)}
                className="text-xs px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium cursor-pointer transition-colors"
              >
                Fechar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL DE CRIAÇÃO DE PASTA */}
      {showNewProjectModal && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4 z-50 animate-in fade-in duration-150">
          <div
            className={`w-full max-w-md rounded-2xl border p-5 shadow-2xl ${
              theme === 'dark'
                ? 'bg-[#151518] border-zinc-800 text-zinc-100'
                : 'bg-white border-slate-300 text-slate-900'
            }`}
          >
            <div className="flex items-center justify-between pb-3 border-b border-zinc-800/80 mb-4">
              <div className="flex items-center gap-2">
                <FolderPlus className="w-4 h-4 text-blue-500" />
                <h3 className="font-semibold text-sm">Nova Pasta de Projeto</h3>
              </div>
              <button
                type="button"
                onClick={() => setShowNewProjectModal(false)}
                className="text-zinc-400 hover:text-zinc-100 p-1 rounded-md"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateProject} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-zinc-400 mb-1">Nome da Pasta</label>
                <input
                  type="text"
                  required
                  value={newProjectNome}
                  onChange={e => setNewProjectNome(e.target.value)}
                  placeholder="Ex: Gestão Advocacia, Financeiro, Mobile App..."
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                      : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                  }`}
                />
              </div>

              <div>
                <label className="block text-zinc-400 mb-1">Cor de Identificação</label>
                <div className="flex items-center gap-2">
                  {(
                    [
                      { id: 'blue', label: 'Azul', cls: 'bg-blue-500' },
                      { id: 'emerald', label: 'Verde', cls: 'bg-emerald-500' },
                      { id: 'purple', label: 'Roxo', cls: 'bg-purple-500' },
                      { id: 'amber', label: 'Âmbar', cls: 'bg-amber-500' },
                      { id: 'rose', label: 'Rosa', cls: 'bg-rose-500' },
                      { id: 'cyan', label: 'Ciano', cls: 'bg-cyan-500' }
                    ] as const
                  ).map(c => (
                    <button
                      key={c.id}
                      type="button"
                      onClick={() => setNewProjectCor(c.id)}
                      className={`w-7 h-7 rounded-full flex items-center justify-center transition-transform cursor-pointer ${
                        c.cls
                      } ${
                        newProjectCor === c.id
                          ? 'ring-2 ring-offset-2 ring-blue-500 scale-110'
                          : 'opacity-70 hover:opacity-100'
                      }`}
                      title={c.label}
                    >
                      {newProjectCor === c.id && <Check className="w-3.5 h-3.5 text-white" />}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-zinc-400 mb-1">Descrição</label>
                <textarea
                  rows={2}
                  value={newProjectDesc}
                  onChange={e => setNewProjectDesc(e.target.value)}
                  placeholder="Resumo do escopo da pasta..."
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                      : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                  }`}
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowNewProjectModal(false)}
                  className={`px-3 py-1.5 rounded-lg border transition-colors ${
                    theme === 'dark'
                      ? 'border-zinc-800 text-zinc-400 hover:bg-zinc-800'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition-colors cursor-pointer"
                >
                  Criar Pasta
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL DE EDIÇÃO DE TAREFA */}
      {showEditTaskModal && editingTask && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4 z-50 animate-in fade-in duration-150">
          <div
            className={`w-full max-w-lg rounded-2xl border p-5 shadow-2xl ${
              theme === 'dark'
                ? 'bg-[#151518] border-zinc-800 text-zinc-100'
                : 'bg-white border-slate-300 text-slate-900'
            }`}
          >
            <div className="flex items-center justify-between pb-3 border-b border-zinc-800/80 mb-4">
              <div className="flex items-center gap-2">
                <Edit3 className="w-4 h-4 text-blue-500" />
                <h3 className="font-semibold text-sm">Editar Item</h3>
              </div>
              <button
                type="button"
                onClick={() => {
                  setShowEditTaskModal(false);
                  setEditingTask(null);
                }}
                className="text-zinc-400 hover:text-zinc-100 p-1 rounded-md"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleSaveEdit} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-zinc-400 mb-1">Título</label>
                <input
                  type="text"
                  required
                  value={editingTask.titulo}
                  onChange={e =>
                    setEditingTask({ ...editingTask, titulo: e.target.value })
                  }
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                      : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                  }`}
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label className="block text-zinc-400 mb-1">Pasta</label>
                  <select
                    value={editingTask.id_projeto}
                    onChange={e =>
                      setEditingTask({ ...editingTask, id_projeto: e.target.value })
                    }
                    className={`w-full px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                      theme === 'dark'
                        ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                        : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                    }`}
                  >
                    {projetos.map(p => (
                      <option key={p.id} value={p.id}>
                        {p.nome}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-zinc-400 mb-1">Etapa / Status</label>
                  <select
                    value={editingTask.status}
                    onChange={e =>
                      setEditingTask({
                        ...editingTask,
                        status: e.target.value as TaskStatus
                      })
                    }
                    className={`w-full px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                      theme === 'dark'
                        ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                        : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                    }`}
                  >
                    <option value="pendente">Pendente</option>
                    <option value="em_andamento">Em Andamento</option>
                    <option value="concluido">Concluído</option>
                  </select>
                </div>

                <div>
                  <label className="block text-zinc-400 mb-1">Prioridade</label>
                  <select
                    value={editingTask.prioridade}
                    onChange={e =>
                      setEditingTask({
                        ...editingTask,
                        prioridade: e.target.value as any
                      })
                    }
                    className={`w-full px-2.5 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                      theme === 'dark'
                        ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                        : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                    }`}
                  >
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-zinc-400 mb-1">Data Limite / Lembrete</label>
                <input
                  type="date"
                  value={editingTask.data_limite || ''}
                  onChange={e =>
                    setEditingTask({ ...editingTask, data_limite: e.target.value })
                  }
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                      : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                  }`}
                />
              </div>

              <div>
                <label className="block text-zinc-400 mb-1">Descrição Detalhada</label>
                <textarea
                  rows={3}
                  value={editingTask.descricao_detalhada || ''}
                  onChange={e =>
                    setEditingTask({
                      ...editingTask,
                      descricao_detalhada: e.target.value
                    })
                  }
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 ${
                    theme === 'dark'
                      ? 'bg-[#1c1c20] border-zinc-800 text-zinc-200'
                      : 'bg-[#f8fafc] border-slate-300 text-slate-800'
                  }`}
                />
              </div>

              {/* Gerenciamento do Arquivo Anexado */}
              <div className="pt-2 border-t border-zinc-800/80">
                <label className="block text-zinc-400 mb-1.5">Arquivo Anexado</label>
                {editingTask.anexo && !editTaskRemoveMedia ? (
                  <div className="flex items-center justify-between p-2 rounded-lg border border-zinc-700 bg-zinc-900/60">
                    <div className="flex items-center gap-2 truncate">
                      {editingTask.anexo.tipo === 'imagem' ? (
                        <ImageIcon className="w-4 h-4 text-blue-500" />
                      ) : (
                        <Video className="w-4 h-4 text-emerald-500" />
                      )}
                      <span className="truncate">{editingTask.anexo.nome_original}</span>
                    </div>
                    <button
                      type="button"
                      onClick={() => {
                        setEditTaskRemoveMedia(true);
                        setEditingTask({ ...editingTask, anexo: null });
                      }}
                      className="text-xs text-rose-400 hover:underline ml-2 cursor-pointer"
                    >
                      Remover
                    </button>
                  </div>
                ) : (
                  <div>
                    <input
                      type="file"
                      ref={editFileInputRef}
                      accept="image/*,video/*"
                      onChange={e => {
                        if (e.target.files && e.target.files[0]) {
                          const file = e.target.files[0];
                          setEditTaskNewFile(file);
                          setEditTaskRemoveMedia(false);
                          const cat = getMediaCategory(file.name);
                          setEditingTask({
                            ...editingTask,
                            anexo: {
                              nome_original: file.name,
                              caminho: URL.createObjectURL(file),
                              tipo: cat,
                              tamanho_formatado: `${(file.size / (1024 * 1024)).toFixed(1)} MB`
                            }
                          });
                        }
                      }}
                      className="hidden"
                      id="edit-task-file-input"
                    />
                    <label
                      htmlFor="edit-task-file-input"
                      className="text-xs px-3 py-1.5 rounded-lg border border-zinc-700 bg-zinc-800/80 hover:bg-zinc-700 text-zinc-300 flex items-center gap-1.5 cursor-pointer inline-flex"
                    >
                      <Paperclip className="w-3.5 h-3.5 text-blue-500" />
                      <span>Adicionar Foto ou Vídeo</span>
                    </label>
                  </div>
                )}
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowEditTaskModal(false);
                    setEditingTask(null);
                  }}
                  className={`px-3 py-1.5 rounded-lg border transition-colors ${
                    theme === 'dark'
                      ? 'border-zinc-800 text-zinc-400 hover:bg-zinc-800'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition-colors cursor-pointer"
                >
                  Salvar Alterações
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL LIGHTBOX PARA VISUALIZAR FOTO EXPANDIDA */}
      {previewMediaUrl && (
        <div
          onClick={() => setPreviewMediaUrl(null)}
          className="fixed inset-0 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 z-50 animate-in fade-in duration-150"
        >
          <div
            className="relative max-w-4xl max-h-[90vh] flex flex-col items-center"
            onClick={e => e.stopPropagation()}
          >
            <button
              type="button"
              onClick={() => setPreviewMediaUrl(null)}
              className="absolute -top-10 right-0 text-white/80 hover:text-white p-1 rounded-full bg-black/40 hover:bg-black/80 transition-colors"
            >
              <X className="w-6 h-6" />
            </button>
            <img
              src={previewMediaUrl}
              alt="Visualização ampliada do anexo"
              className="max-h-[85vh] max-w-full rounded-xl border border-zinc-800 object-contain shadow-2xl"
            />
          </div>
        </div>
      )}
    </div>
    </ProtectedRoute>
  );
}
