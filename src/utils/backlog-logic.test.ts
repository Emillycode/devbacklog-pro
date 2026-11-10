import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateDueStatus, filterTasks, countPendingByProject, formatDateTime, getMediaCategory, getFontFamilyCss, getFontSizePx, type Tarefa } from './backlog-logic.ts';

test('calculateDueStatus identifies expired, today, soon and future tasks correctly', () => {
  const refDate = '2026-10-28';

  // Expired
  const expired = calculateDueStatus('2026-10-26', 'pendente', refDate);
  assert.equal(expired.status, 'expired');
  assert.equal(expired.isUrgent, true);

  // Today
  const today = calculateDueStatus('2026-10-28', 'pendente', refDate);
  assert.equal(today.status, 'today');
  assert.equal(today.isUrgent, true);

  // Tomorrow
  const tomorrow = calculateDueStatus('2026-10-29', 'pendente', refDate);
  assert.equal(tomorrow.status, 'soon');
  assert.equal(tomorrow.label, 'Vence amanhã');

  // In 3 days
  const soon = calculateDueStatus('2026-10-31', 'pendente', refDate);
  assert.equal(soon.status, 'soon');
  assert.equal(soon.label, 'Em 3 dias');

  // Future
  const future = calculateDueStatus('2026-11-15', 'pendente', refDate);
  assert.equal(future.status, 'future');

  // Completed tasks are not urgent
  const completed = calculateDueStatus('2026-10-20', 'concluido', refDate);
  assert.equal(completed.isUrgent, false);
});

test('filterTasks correctly filters by project, status and search query', () => {
  const sampleTasks: Tarefa[] = [
    {
      id: 'task_1',
      id_projeto: 'proj_1',
      titulo: 'Criar autenticação JWT',
      descricao_detalhada: 'Implementar login com token stateless',
      prioridade: 'Alta',
      status: 'pendente',
      data_limite: '2026-10-30',
      criado_em: '2026-10-28 10:00:00',
    },
    {
      id: 'task_2',
      id_projeto: 'proj_1',
      titulo: 'Refatorar middleware de logs',
      descricao_detalhada: 'Padronizar saída em formato JSON',
      prioridade: 'Média',
      status: 'concluido',
      data_limite: '2026-10-27',
      criado_em: '2026-10-28 11:00:00',
    },
    {
      id: 'task_3',
      id_projeto: 'proj_2',
      titulo: 'Configurar tema dark mode',
      descricao_detalhada: 'Ajustar variáveis CSS com paleta sóbria',
      prioridade: 'Baixa',
      status: 'pendente',
      data_limite: '2026-11-01',
      criado_em: '2026-10-28 12:00:00',
    },
  ];

  // Filter by project
  const p1Tasks = filterTasks(sampleTasks, 'proj_1', 'all');
  assert.equal(p1Tasks.length, 2);

  // Filter by status 'pendente'
  const pendingTasks = filterTasks(sampleTasks, 'all', 'pendente');
  assert.equal(pendingTasks.length, 2);

  // Filter by status 'concluido'
  const doneTasks = filterTasks(sampleTasks, 'all', 'concluido');
  assert.equal(doneTasks.length, 1);
  assert.equal(doneTasks[0].id, 'task_2');

  // Search by query
  const searched = filterTasks(sampleTasks, 'all', 'all', 'JWT');
  assert.equal(searched.length, 1);
  assert.equal(searched[0].id, 'task_1');
});

test('countPendingByProject tallies active pending tasks accurately', () => {
  const sampleTasks: Tarefa[] = [
    { id: '1', id_projeto: 'p1', titulo: 'A', descricao_detalhada: '', prioridade: 'Alta', status: 'pendente', data_limite: '', criado_em: '' },
    { id: '2', id_projeto: 'p1', titulo: 'B', descricao_detalhada: '', prioridade: 'Média', status: 'concluido', data_limite: '', criado_em: '' },
    { id: '3', id_projeto: 'p1', titulo: 'C', descricao_detalhada: '', prioridade: 'Baixa', status: 'pendente', data_limite: '', criado_em: '' },
    { id: '4', id_projeto: 'p2', titulo: 'D', descricao_detalhada: '', prioridade: 'Alta', status: 'pendente', data_limite: '', criado_em: '' },
  ];

  const counts = countPendingByProject(sampleTasks);
  assert.equal(counts['p1'], 2);
  assert.equal(counts['p2'], 1);
  assert.equal(counts['p3'], undefined);
});

test('formatDateTime formats date and time with timezone support', () => {
  const formatted = formatDateTime('2026-10-28T14:30:00Z', 'UTC');
  assert.match(formatted, /28\/10\/2026/);
  assert.match(formatted, /14:30/);
});

test('getMediaCategory identifies image and video extensions correctly', () => {
  assert.equal(getMediaCategory('screenshot.png'), 'imagem');
  assert.equal(getMediaCategory('photo.jpeg'), 'imagem');
  assert.equal(getMediaCategory('demo.mp4'), 'video');
  assert.equal(getMediaCategory('screencast.webm'), 'video');
  assert.equal(getMediaCategory('archive.zip'), 'outro');
});

test('getFontFamilyCss and getFontSizePx return correct CSS values', () => {
  assert.equal(getFontFamilyCss('Helvetica / Arial'), '"Helvetica Neue", Helvetica, Arial, sans-serif');
  assert.equal(getFontFamilyCss('Montserrat'), '"Montserrat", sans-serif');
  assert.equal(getFontFamilyCss('Roboto'), '"Roboto", sans-serif');
  assert.equal(getFontFamilyCss('Open Sans / Lato'), '"Open Sans", "Lato", sans-serif');

  assert.equal(getFontSizePx('small'), '13px');
  assert.equal(getFontSizePx('medium'), '15px');
  assert.equal(getFontSizePx('large'), '17px');
});
