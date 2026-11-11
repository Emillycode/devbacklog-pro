/**
 * Pure business logic for Backlog & Ideias (PHP & Client)
 * Used for deterministic unit testing and validations.
 */

export type FolderColor = 'blue' | 'emerald' | 'purple' | 'amber' | 'rose' | 'cyan';
export type TaskStatus = 'pendente' | 'em_andamento' | 'concluido';

export interface Projeto {
  id: string;
  nome: string;
  descricao: string;
  cor?: FolderColor;
  criado_em: string;
}

export interface AnexoMedia {
  nome_original: string;
  caminho: string;
  tipo: 'imagem' | 'video' | 'outro';
  tamanho_formatado?: string;
}

export interface Tarefa {
  id: string;
  id_projeto: string;
  titulo: string;
  descricao_detalhada: string;
  prioridade: 'Baixa' | 'Média' | 'Alta';
  status: TaskStatus;
  data_limite: string; // YYYY-MM-DD
  criado_em: string;
  anexo?: AnexoMedia | null;
}

export type ThemeMode = 'dark' | 'light';
export type FontSize = 'small' | 'medium' | 'large';
export type FontFamily = 'Helvetica / Arial' | 'Montserrat' | 'Roboto' | 'Open Sans / Lato';
export type ViewMode = 'kanban' | 'list';

export function getFontFamilyCss(family: FontFamily): string {
  switch (family) {
    case 'Helvetica / Arial':
      return '"Helvetica Neue", Helvetica, Arial, sans-serif';
    case 'Montserrat':
      return '"Montserrat", "Helvetica Neue", Helvetica, Arial, sans-serif';
    case 'Roboto':
      return '"Roboto", "Helvetica Neue", Helvetica, Arial, sans-serif';
    case 'Open Sans / Lato':
      return '"Open Sans", "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif';
    default:
      return '"Helvetica Neue", Helvetica, Arial, sans-serif';
  }
}

export function getFontSizePx(size: FontSize): string {
  switch (size) {
    case 'small':
      return '13px';
    case 'large':
      return '17px';
    case 'medium':
    default:
      return '15px';
  }
}

export function getMediaCategory(fileName: string): 'imagem' | 'video' | 'outro' {
  const ext = fileName.split('.').pop()?.toLowerCase() || '';
  if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) {
    return 'imagem';
  }
  if (['mp4', 'webm', 'mov', 'ogg'].includes(ext)) {
    return 'video';
  }
  return 'outro';
}

export interface DueStatus {
  status: 'expired' | 'today' | 'soon' | 'future' | 'none';
  label: string;
  isUrgent: boolean;
}

/**
 * Calculates due date status relative to a given reference date
 */
export function calculateDueStatus(
  dateStr: string | null | undefined,
  taskStatus: TaskStatus,
  referenceDateStr?: string
): DueStatus {
  if (!dateStr || dateStr.trim() === '') {
    return { status: 'none', label: 'Sem prazo', isUrgent: false };
  }

  if (taskStatus === 'concluido') {
    return { status: 'none', label: 'Concluído', isUrgent: false };
  }

  const refDate = referenceDateStr ? new Date(referenceDateStr + 'T00:00:00') : new Date();
  refDate.setHours(0, 0, 0, 0);

  const targetDate = new Date(dateStr + 'T00:00:00');
  targetDate.setHours(0, 0, 0, 0);

  const diffTime = targetDate.getTime() - refDate.getTime();
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays < 0) {
    const daysAgo = Math.abs(diffDays);
    return {
      status: 'expired',
      label: daysAgo === 1 ? 'Venceu ontem' : `Venceu há ${daysAgo} dias`,
      isUrgent: true,
    };
  }

  if (diffDays === 0) {
    return { status: 'today', label: 'Vence hoje', isUrgent: true };
  }

  if (diffDays === 1) {
    return { status: 'soon', label: 'Vence amanhã', isUrgent: true };
  }

  if (diffDays <= 3) {
    return { status: 'soon', label: `Em ${diffDays} dias`, isUrgent: false };
  }

  return { status: 'future', label: `Prazo: ${dateStr}`, isUrgent: false };
}

/**
 * Filter tasks by project, status filter, and optional search query
 */
export function filterTasks(
  tasks: Tarefa[],
  projectId: string | null,
  statusFilter: 'all' | TaskStatus,
  searchQuery: string = ''
): Tarefa[] {
  const query = searchQuery.trim().toLowerCase();

  return tasks.filter((task) => {
    // Project filter (null or 'all' matches all projects)
    if (projectId && projectId !== 'all' && task.id_projeto !== projectId) {
      return false;
    }

    // Status filter
    if (statusFilter !== 'all' && task.status !== statusFilter) {
      return false;
    }

    // Search query
    if (query) {
      const matchTitle = task.titulo.toLowerCase().includes(query);
      const matchDesc = (task.descricao_detalhada || '').toLowerCase().includes(query);
      if (!matchTitle && !matchDesc) {
        return false;
      }
    }

    return true;
  });
}

/**
 * Count active (pending or in-progress) tasks per project
 */
export function countPendingByProject(tasks: Tarefa[]): Record<string, number> {
  const counts: Record<string, number> = {};
  for (const task of tasks) {
    if (task.status !== 'concluido') {
      counts[task.id_projeto] = (counts[task.id_projeto] || 0) + 1;
    }
  }
  return counts;
}

/**
 * Format ISO or datetime string respecting timezone
 */
export function formatDateTime(dateStr: string, timeZone?: string): string {
  if (!dateStr || dateStr.trim() === '') return '';
  const cleanStr = dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return dateStr;

  return d.toLocaleString('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: timeZone || undefined,
  });
}
