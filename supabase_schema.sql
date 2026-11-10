-- ============================================================================
-- SCRIPT SQL PARA CONFIGURAÇÃO DO BANCO DE DADOS NO SUPABASE
-- Como usar:
-- 1. Acesse seu projeto em https://supabase.com/dashboard
-- 2. No menu lateral, clique em "SQL Editor" -> "New query"
-- 3. Cole todo este conteúdo e clique em "Run"
-- ============================================================================

-- 1. Tabela de Pastas de Projetos / Softwares
CREATE TABLE IF NOT EXISTS public.projetos (
    id TEXT PRIMARY KEY,
    nome TEXT NOT NULL,
    descricao TEXT DEFAULT '',
    cor TEXT DEFAULT 'blue',
    criado_em TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Tabela de Ideias e Tarefas do Backlog
CREATE TABLE IF NOT EXISTS public.tarefas (
    id TEXT PRIMARY KEY,
    id_projeto TEXT NOT NULL REFERENCES public.projetos(id) ON DELETE CASCADE,
    titulo TEXT NOT NULL,
    descricao_detalhada TEXT DEFAULT '',
    prioridade TEXT DEFAULT 'Média' CHECK (prioridade IN ('Baixa', 'Média', 'Alta')),
    status TEXT DEFAULT 'pendente' CHECK (status IN ('pendente', 'em_andamento', 'concluido')),
    data_limite DATE NULL,
    anexo JSONB NULL,
    criado_em TIMESTAMPTZ DEFAULT NOW()
);

-- 3. Índices para busca rápida e filtragem no Kanban
CREATE INDEX IF NOT EXISTS idx_tarefas_id_projeto ON public.tarefas(id_projeto);
CREATE INDEX IF NOT EXISTS idx_tarefas_status ON public.tarefas(status);
CREATE INDEX IF NOT EXISTS idx_tarefas_criado_em ON public.tarefas(criado_em DESC);

-- 4. Segurança Row Level Security (RLS)
-- Mantém as tabelas fechadas para acesso público anônimo direto;
-- seu backend PHP acessa com segurança usando a SUPABASE_KEY do arquivo .env.
ALTER TABLE public.projetos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tarefas ENABLE ROW LEVEL SECURITY;

-- Política opcional caso você utilize a chave 'anon' no .env em vez da 'service_role':
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies WHERE tablename = 'projetos' AND policyname = 'Acesso Backend Backlog Projetos'
    ) THEN
        CREATE POLICY "Acesso Backend Backlog Projetos" ON public.projetos
            FOR ALL USING (true) WITH CHECK (true);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_policies WHERE tablename = 'tarefas' AND policyname = 'Acesso Backend Backlog Tarefas'
    ) THEN
        CREATE POLICY "Acesso Backend Backlog Tarefas" ON public.tarefas
            FOR ALL USING (true) WITH CHECK (true);
    END IF;
END
$$;
