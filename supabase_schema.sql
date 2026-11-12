-- ============================================================================
-- SCRIPT SQL PARA CONFIGURAÇÃO DO BANCO DE DADOS E AUTH RLS NO SUPABASE
-- Como usar:
-- 1. Acesse seu projeto em https://supabase.com/dashboard
-- 2. No menu lateral, clique em "SQL Editor" -> "New query"
-- 3. Cole todo este conteúdo e clique em "Run"
-- ============================================================================

-- 1. Tabela de Pastas de Projetos / Softwares (isolada por usuário via user_id)
CREATE TABLE IF NOT EXISTS public.projetos (
    id TEXT PRIMARY KEY,
    user_id UUID NOT NULL DEFAULT auth.uid() REFERENCES auth.users(id) ON DELETE CASCADE,
    nome TEXT NOT NULL,
    descricao TEXT DEFAULT '',
    cor TEXT DEFAULT 'blue',
    criado_em TIMESTAMPTZ DEFAULT NOW()
);

-- Garante a coluna user_id caso a tabela já tenha sido criada anteriormente
ALTER TABLE public.projetos
    ADD COLUMN IF NOT EXISTS user_id UUID DEFAULT auth.uid() REFERENCES auth.users(id) ON DELETE CASCADE;

-- 2. Tabela de Ideias e Tarefas do Backlog (isolada por usuário via user_id)
CREATE TABLE IF NOT EXISTS public.tarefas (
    id TEXT PRIMARY KEY,
    user_id UUID NOT NULL DEFAULT auth.uid() REFERENCES auth.users(id) ON DELETE CASCADE,
    id_projeto TEXT NOT NULL REFERENCES public.projetos(id) ON DELETE CASCADE,
    titulo TEXT NOT NULL,
    descricao_detalhada TEXT DEFAULT '',
    prioridade TEXT DEFAULT 'Média' CHECK (prioridade IN ('Baixa', 'Média', 'Alta')),
    status TEXT DEFAULT 'pendente' CHECK (status IN ('pendente', 'em_andamento', 'concluido')),
    data_limite DATE NULL,
    anexo JSONB NULL,
    criado_em TIMESTAMPTZ DEFAULT NOW()
);

-- Garante a coluna user_id caso a tabela já tenha sido criada anteriormente
ALTER TABLE public.tarefas
    ADD COLUMN IF NOT EXISTS user_id UUID DEFAULT auth.uid() REFERENCES auth.users(id) ON DELETE CASCADE;

-- 3. Índices para busca rápida, filtragem no Kanban e performance de RLS
CREATE INDEX IF NOT EXISTS idx_projetos_user_id ON public.projetos(user_id);
CREATE INDEX IF NOT EXISTS idx_tarefas_user_id ON public.tarefas(user_id);
CREATE INDEX IF NOT EXISTS idx_tarefas_id_projeto ON public.tarefas(id_projeto);
CREATE INDEX IF NOT EXISTS idx_tarefas_status ON public.tarefas(status);
CREATE INDEX IF NOT EXISTS idx_tarefas_criado_em ON public.tarefas(criado_em DESC);

-- 4. Permissões da Data API (PostgREST)
REVOKE ALL ON public.projetos FROM anon;
REVOKE ALL ON public.tarefas FROM anon;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.projetos TO authenticated;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.tarefas TO authenticated;

-- 5. Segurança Row Level Security (RLS) com isolamento por usuário (TO authenticated + (select auth.uid()) = user_id)
ALTER TABLE public.projetos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tarefas ENABLE ROW LEVEL SECURITY;

-- Remove políticas antigas permissivas caso existam
DROP POLICY IF EXISTS "Acesso Backend Backlog Projetos" ON public.projetos;
DROP POLICY IF EXISTS "Acesso Backend Backlog Tarefas" ON public.tarefas;

-- Políticas de RLS para public.projetos
DROP POLICY IF EXISTS "projetos_select_own" ON public.projetos;
CREATE POLICY "projetos_select_own" ON public.projetos
    FOR SELECT
    TO authenticated
    USING ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "projetos_insert_own" ON public.projetos;
CREATE POLICY "projetos_insert_own" ON public.projetos
    FOR INSERT
    TO authenticated
    WITH CHECK ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "projetos_update_own" ON public.projetos;
CREATE POLICY "projetos_update_own" ON public.projetos
    FOR UPDATE
    TO authenticated
    USING ( (select auth.uid()) = user_id )
    With CHECK ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "projetos_delete_own" ON public.projetos;
CREATE POLICY "projetos_delete_own" ON public.projetos
    FOR DELETE
    TO authenticated
    USING ( (select auth.uid()) = user_id );

-- Políticas de RLS para public.tarefas
DROP POLICY IF EXISTS "tarefas_select_own" ON public.tarefas;
CREATE POLICY "tarefas_select_own" ON public.tarefas
    FOR SELECT
    TO authenticated
    USING ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "tarefas_insert_own" ON public.tarefas;
CREATE POLICY "tarefas_insert_own" ON public.tarefas
    FOR INSERT
    TO authenticated
    WITH CHECK ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "tarefas_update_own" ON public.tarefas;
CREATE POLICY "tarefas_update_own" ON public.tarefas
    FOR UPDATE
    TO authenticated
    USING ( (select auth.uid()) = user_id )
    WITH CHECK ( (select auth.uid()) = user_id );

DROP POLICY IF EXISTS "tarefas_delete_own" ON public.tarefas;
CREATE POLICY "tarefas_delete_own" ON public.tarefas
    FOR DELETE
    TO authenticated
    USING ( (select auth.uid()) = user_id );
