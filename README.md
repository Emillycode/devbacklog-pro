# Backlog

Plataforma completa de gerenciamento de projetos, backlog e ideias com visualização em **Quadro Kanban** e **Lista**, autenticação integrada ao **Supabase Auth**, banco **PostgreSQL (Supabase)** com políticas de **Row Level Security (RLS)**, cache/backup local em JSON e suporte persistente a anexos (imagens, PDFs e vídeos).

---

## Sumário

- [Funcionalidades da Plataforma](#funcionalidades-da-plataforma)
- [Ferramentas e Tecnologias Utilizadas](#ferramentas-e-tecnologias-utilizadas)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Variáveis de Ambiente (.env)](#variáveis-de-ambiente-env)
- [Configuração do Banco de Dados (Supabase)](#configuração-do-banco-de-dados-supabase)
- [Como Executar Localmente](#como-executar-localmente)
- [Deploy com Docker e Render](#deploy-com-docker-e-render)
- [Testes e Verificações](#testes-e-verificações)
- [Segurança Implementada](#segurança-implementada)

---

## Funcionalidades da Plataforma

### 1. Autenticação, Controle de Acesso e Sessão
- **Acesso Restrito por Lista de E-mails:** apenas os endereços configurados em `ALLOWED_EMAILS` / `ADMIN_EMAIL` (no `.env`) têm permissão para iniciar sessão.
- **Integração com Supabase Auth:** login autenticado via `/auth/v1/token` retornando JWT (`access_token` e `refresh_token`), com renovação automática antes da expiração e nova tentativa transparente em respostas `401`.
- **Fallback Local (`.env`):** caso o Supabase não esteja configurado ou em cenários de fallback local, valida contra `ADMIN_PASSWORD` ou `ADMIN_PASSWORD_HASH` (BCrypt), exibindo aviso na interface quando operando sem token ativo do Supabase.
- **Proteção contra Força Bruta e CSRF:** bloqueio temporário por 3 minutos após 5 tentativas inválidas (`auth_config.json`) e validação de `csrf_token` em todas as mutações.
- **Encerramento Real de Sessão (Botão Trancar):** revoga o token no Supabase (`/auth/v1/logout`), limpa variáveis de sessão, regenera ID e expira o cookie `PHPSESSID`.

### 2. Organização por Pastas de Projetos
- Criação e exclusão de pastas com **nome**, **descrição** e **identificação por cor** (`blue`, `emerald`, `purple`, `amber`, `rose`, `cyan`).
- Barra lateral com visão consolidada (**Todas**) ou filtrada por pasta específica, contabilizando tarefas pendentes/em andamento em cada pasta e calculando o percentual total de progresso concluído.

### 3. Gestão de Ideias e Tarefas (Kanban & Lista)
- **Modos de Visualização:** alternância instantânea entre **Quadro Kanban** (`Pendente`, `Em Andamento`, `Concluído`) e **Lista**.
- **Drag & Drop + Controles Rápidos:** movimentação de cards entre colunas por arrastar-e-soltar (`handleDrop` -> `api.php?action=update_status`) ou pelos botões rápidos do rodapé (`Iniciar`, `Concluir`, `Reabrir`).
- **Criação e Edição Completa:** formulário rápido e modal de edição com título, pasta destino, coluna/status, prioridade (`Baixa`, `Média`, `Alta`), data limite/lembrete, descrição detalhada e anexo.
- **Monitoramento de Prazos e KPIs:** cálculo automático de vencimento (*Vencido*, *Vence hoje*, *Vence amanhã*, *Em X dias*) e indicadores no cabeçalho (`Pendentes`, `Em Andamento`, `Concluídas`, `Urgentes / Hoje`).
- **Filtros e Busca:** filtragem por status e pesquisa textual em tempo real no título e na descrição.

### 4. Anexos de Imagens, PDFs e Vídeos com Persistência
- **Formatos Suportados:**
  - **Imagens:** `jpg`, `jpeg`, `jfif`, `png`, `webp`, `gif`, `bmp`, `avif`, `heic`
  - **Documentos:** `pdf`
  - **Vídeos:** `mp4`, `webm`, `mov`, `m4v`
- **Validação Rigorosa com Feedback Claro:** verificação de extensão, tamanho máximo (25 MB na aplicação; limites de servidor PHP configurados para 64 MB), detecção de MIME real (`finfo`) com checagem de assinatura mágica de bytes (`verify_media_magic_bytes`) e alertas visuais caso ocorra qualquer erro de upload ou permissão no banco.
- **Exibição Direta nos Cards:**
  - **Imagens:** miniatura no card, ampliação em Lightbox, abertura em nova guia e download.
  - **PDFs:** cartão visual dedicado exibindo nome, tamanho e botões **Abrir** (nova guia) e **Baixar**.
  - **Vídeos:** player HTML5 embutido.
  - Botão **Ocultar / Exibir** em cada card e gerenciamento completo (visualizar/substituir/remover) no modal de edição.
- **Persistência em Containers Efêmeros (Render + Supabase):** arquivos de até **4 MB** são salvos em `uploads/` e também armazenados de forma compactada (`gzdeflate` + Base64) dentro da coluna `JSONB` `anexo` no Supabase. Ao carregar os dados (`normalize_app_data`), a função `restore_media_file_if_missing` recria automaticamente o arquivo na pasta `uploads/` caso o servidor tenha reiniciado.

### 5. Personalização Visual e Backup
- **Tema:** alternância rápida entre **Modo Black** (`dark`) e **Modo White** (`light`).
- **Tipografia e Escala:** seleção de fonte (`Helvetica / Arial`, `Montserrat`, `Roboto`, `Open Sans / Lato`) e tamanho base (`Pequena` 13px, `Média` 15px, `Grande` 17px) com pré-visualização em tempo real e persistência via `localStorage`, cookies e sessão PHP.
- **Backup e Sincronização:** exportação completa de projetos e tarefas em `.json` (`api.php?action=export_backup`), restauração via upload JSON (`import_backup`) e ação de sincronização com o banco (`sync_supabase`).

---

## Ferramentas e Tecnologias Utilizadas

### Backend e Servidor Web
- **PHP 8.2+** (`declare(strict_types=1)`): core unificado em [`api.php`](api.php) e interface principal em [`index.php`](index.php) utilizando cURL, Fileinfo (`finfo`) e Zlib (`gzdeflate`/`gzinflate`).
- **Apache HTTP Server:** servido com `mod_rewrite`, `mod_headers`, `.htaccess` e `.user.ini` para roteamento, cabeçalhos de segurança e limites de upload (64 MB).

### Banco de Dados e Autenticação
- **Supabase (PostgreSQL + PostgREST + Auth):** persistência principal nas tabelas `public.projetos` e `public.tarefas` com políticas de **Row Level Security (RLS)** definidas em [`supabase_schema.sql`](supabase_schema.sql).
- **Persistência Local em JSON:** espelho e fallback em `data.json` utilizando escrita com trava (`LOCK_EX`).

### Interface Web e Ecossistema Frontend
- **Tailwind CSS:** estilização responsiva e temas Dark/Light (via CDN no `index.php` e `@tailwindcss/vite` v4 no frontend SPA).
- **Google Fonts:** `Roboto`, `Montserrat`, `Open Sans` e `Lato`.
- **JavaScript Vanilla (ES6+):** manipulação de modais, Drag & Drop do Kanban, Lightbox de mídia, preferências reativas e requisições assíncronas via Fetch API.
- **Stack SPA / Desenvolvimento (em `src/` e `package.json`):**
  - **React 19** (`react`, `react-dom`) e **TypeScript 7**
  - **Vite 8** (`vite`, `@vitejs/plugin-react`) com proxy configurado para `/api.php` e `/uploads`
  - **`@supabase/supabase-js`**, **`lucide-react`**, **`motion`**, **`express`**, **`dotenv`**, **`tsx`**, **`@google/genai`**

### Infraestrutura e Deploy
- **Docker:** imagem oficial `php:8.2-apache` configurada no [`Dockerfile`](Dockerfile) com extensões PHP, permissões em `uploads/`, limites de upload de 64 MB e adaptação dinâmica à variável `PORT`.
- **Render:** provisionamento automático via [`render.yaml`](render.yaml) (Web Service Docker com `autoDeploy: true`).

---

## Estrutura do Projeto

```text
.
├── api.php                     # Backend REST JSON, autenticação, cliente Supabase, upload seguro e restauração de mídia
├── index.php                   # Aplicação web principal (login, dashboard, Kanban, lista, modais e configurações)
├── supabase_schema.sql         # Script SQL das tabelas projetos/tarefas, índices e políticas RLS no Supabase
├── Dockerfile                  # Container PHP 8.2 + Apache para deploy em nuvem
├── render.yaml                 # Manifesto de deploy no Render
├── .htaccess                   # Regras Apache (DirectoryIndex, bloqueio de arquivos sensíveis, limites mod_php e headers)
├── .user.ini                   # Limites de upload e memória para ambientes PHP CGI/FPM
├── .env.example                # Modelo de configuração das variáveis de ambiente
├── uploads/
│   └── .htaccess               # Bloqueia execução de scripts dentro da pasta de uploads
├── src/                        # Camada React + TypeScript (frontend SPA alternativo e lógica testável)
│   ├── App.tsx                 # Interface React integrada à API PHP e ao cliente Supabase
│   ├── main.tsx                # Ponto de entrada React
│   ├── index.css               # Estilos Tailwind v4
│   ├── components/
│   │   ├── AuthScreen.tsx      # Tela de login React
│   │   └── ProtectedRoute.tsx  # Guarda de rota autenticada React
│   ├── lib/
│   │   └── supabase.ts         # Inicialização do cliente @supabase/supabase-js
│   └── utils/
│       ├── backlog-logic.ts    # Regras puras (filtros, prazos, categorias de mídia e temas)
│       └── backlog-logic.test.ts # Testes unitários em Node.js
├── tests/
│   └── test_backend.php        # Testes de integração/unidade do backend PHP (auth, CSRF, normalização)
├── index.html                  # Entrada HTML para o build Vite/React
├── vite.config.ts              # Configuração Vite + Tailwind + proxy para PHP
├── tsconfig.json               # Configuração do compilador TypeScript
├── package.json                # Dependências e scripts Node/Vite
└── metadata.json               # Metadados do aplicativo
```

---

## Variáveis de Ambiente (`.env`)

Copie o arquivo `.env.example` para `.env` e preencha os valores do seu projeto:

```env
# Credenciais e controle de acesso
ADMIN_EMAIL="seu-email-principal@exemplo.com"
ALLOWED_EMAILS="seu-email-principal@exemplo.com,outro-acesso@exemplo.com"
ADMIN_PASSWORD="sua_senha_forte_de_fallback"
ADMIN_PASSWORD_HASH=""

# Configurações do Supabase (Frontend React + Backend PHP)
VITE_SUPABASE_URL="https://SEU_PROJETO_ID.supabase.co"
VITE_SUPABASE_PUBLISHABLE_KEY="sua_chave_publishable_ou_anon"

SUPABASE_URL="https://SEU_PROJETO_ID.supabase.co"
SUPABASE_ANON_KEY="sua_chave_publishable_ou_anon"
SUPABASE_KEY="sua_chave_publishable_ou_anon"
```

> **Atenção:** Nunca exponha chaves `service_role` / `sb_secret_` em variáveis `VITE_*` ou arquivos públicos. O arquivo `.env` é ignorado pelo `.gitignore` e bloqueado pelo `.htaccess`.

---

## Configuração do Banco de Dados (Supabase)

1. Acesse o painel do seu projeto no [Supabase](https://supabase.com/dashboard).
2. No menu lateral, vá em **SQL Editor** -> **New query**.
3. Copie todo o conteúdo do arquivo [`supabase_schema.sql`](supabase_schema.sql) e execute (**Run**).
4. Em **Authentication -> Users**, cadastre os usuários autorizados correspondentes aos e-mails informados em `ALLOWED_EMAILS` / `ADMIN_EMAIL` para que cada acesso possua token JWT válido e consiga gravar diretamente nas tabelas protegidas por RLS.

---

## Como Executar Localmente

### Opção 1: Servidor Apache / WAMP (PHP) — Interface Principal

1. Coloque o projeto dentro do diretório web do seu servidor (ex.: `htdocs/novoapp` ou `www/novoapp`).
2. Crie o arquivo `.env` a partir do `.env.example`.
3. Certifique-se de que as extensões `curl`, `fileinfo` e `openssl` estejam habilitadas no PHP e que a pasta `uploads/` tenha permissão de escrita.
4. Acesse no navegador:
   ```text
   http://localhost/novoapp/
   ```

### Opção 2: Modo Desenvolvimento SPA (Vite + React + Backend PHP)

1. Com o Apache/PHP servindo `api.php` localmente (ou ajustando `PHP_BACKEND_URL` se necessário), instale as dependências Node.js:
   ```bash
   npm install
   ```
2. Inicie o servidor de desenvolvimento Vite (porta `3000` com proxy automático para `/api.php` e `/uploads`):
   ```bash
   npm run dev
   ```
3. Para gerar o build estático ou verificar tipos TypeScript:
   ```bash
   npm run build
   npm run lint
   ```

---

## Deploy com Docker e Render

### Docker Local

```bash
docker build -t backlog-app .
docker run -p 8080:80 --env-file .env backlog-app
```

Acesse em `http://localhost:8080`.

### Render (`render.yaml`)

1. Conecte o repositório no **Render** como um serviço **Docker** (ou usando Blueprint via `render.yaml`).
2. Configure no painel **Environment Variables** do Render as variáveis `ADMIN_EMAIL`, `ALLOWED_EMAILS`, `ADMIN_PASSWORD`, `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_KEY`, `VITE_SUPABASE_URL` e `VITE_SUPABASE_PUBLISHABLE_KEY`.
3. O `Dockerfile` ajusta automaticamente a porta `${PORT}`, configura os limites de upload do PHP (`64M`) e inicia o Apache.

---

## Testes e Verificações

- **Verificação de sintaxe PHP:**
  ```bash
  php -l api.php
  php -l index.php
  ```
- **Testes do Backend PHP (autenticação, CSRF e estrutura de dados):**
  ```bash
  php tests/test_backend.php
  ```
- **Testes unitários da lógica TypeScript (`node:test`):**
  ```bash
  node --experimental-strip-types --test src/utils/backlog-logic.test.ts
  ```
- **Checagem de tipos TypeScript:**
  ```bash
  npm run lint
  ```

---

## Segurança Implementada

- Bloqueio HTTP direto via `.htaccess` para `.env*`, `.git*`, `.user.ini`, `Dockerfile`, `render.yaml`, `data.json`, `auth_config.json`, `package.json`, `vite.config.ts` e arquivos `.sql`.
- Bloqueio de execução de scripts (`.php`, `.phtml`, `.py`, `.sh`, `.html`, etc.) dentro do diretório [`uploads/.htaccess`](uploads/.htaccess).
- Prevenção de Path Traversal na restauração e salvamento de anexos (`basename`, nomes aleatórios `media_<hex>.<ext>` e validação estrita de regex).
- Escaping HTML (`htmlspecialchars` com `ENT_QUOTES`) e encoding seguro de JSON em atributos (`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`).
- Cabeçalhos HTTP `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy` e `X-XSS-Protection`.
