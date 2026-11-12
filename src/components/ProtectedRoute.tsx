import React, { useEffect, useState, useCallback } from 'react';
import { Lock } from 'lucide-react';
import type { ThemeMode } from '../utils/backlog-logic.ts';

export type AppRoutePath =
  | '/'
  | '/kanban'
  | '/lista'
  | '/login'
  | '/signup'
  | '/forgot-password'
  | '/reset-password';

const AUTH_PATHS: AppRoutePath[] = [
  '/login',
  '/signup',
  '/forgot-password',
  '/reset-password'
];

export function isPublicAuthPath(pathname: string): boolean {
  const normalized = normalizePathname(pathname);
  return AUTH_PATHS.includes(normalized);
}

export function normalizePathname(rawPath: string): AppRoutePath {
  const clean = rawPath.replace(/\/+$/, '') || '/';
  if (clean.endsWith('/login')) return '/login';
  if (clean.endsWith('/signup')) return '/signup';
  if (clean.endsWith('/forgot-password')) return '/forgot-password';
  if (clean.endsWith('/reset-password')) return '/reset-password';
  if (clean.endsWith('/lista')) return '/lista';
  if (clean.endsWith('/kanban')) return '/kanban';
  return '/';
}

export function useAppRouter() {
  const [route, setRoute] = useState<AppRoutePath>(() => {
    if (typeof window === 'undefined') return '/';
    // Detect Supabase recovery hash (#access_token=...&type=recovery)
    if (window.location.hash.includes('type=recovery')) {
      return '/reset-password';
    }
    return normalizePathname(window.location.pathname);
  });

  const navigate = useCallback((to: AppRoutePath, replace = false) => {
    if (typeof window === 'undefined') return;
    // Preserve base directory when running under WAMP subfolder (e.g., /novoapp)
    const baseMatch = window.location.pathname.match(/^(\/novoapp)(?:\/.*)?$/i);
    const basePrefix = baseMatch ? baseMatch[1] : '';
    const targetUrl = to === '/' ? `${basePrefix}/` : `${basePrefix}${to}`;

    if (replace) {
      window.history.replaceState({}, '', targetUrl);
    } else {
      window.history.pushState({}, '', targetUrl);
    }
    setRoute(to);
  }, []);

  useEffect(() => {
    const onPopState = () => {
      if (window.location.hash.includes('type=recovery')) {
        setRoute('/reset-password');
        return;
      }
      setRoute(normalizePathname(window.location.pathname));
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  return { route, navigate };
}

interface ProtectedRouteProps {
  isAuthenticated: boolean;
  isLoading: boolean;
  route: AppRoutePath;
  navigate: (to: AppRoutePath, replace?: boolean) => void;
  theme: ThemeMode;
  fontFamilyCss: string;
  fallbackAuthScreen: React.ReactNode;
  children: React.ReactNode;
}

/**
 * Componente Guardião de Rotas Protegidas (ProtectedRoute)
 * - Exibe estado de carregamento seguro enquanto valida a sessão JWT do Supabase Auth
 * - Redireciona automaticamente rotas privadas (/, /kanban, /lista) para /login quando não autenticado
 * - Redireciona rotas públicas (/login, /signup) para / quando já autenticado
 */
export function ProtectedRoute({
  isAuthenticated,
  isLoading,
  route,
  navigate,
  theme,
  fontFamilyCss,
  fallbackAuthScreen,
  children
}: ProtectedRouteProps) {
  useEffect(() => {
    if (isLoading) return;

    if (!isAuthenticated && !isPublicAuthPath(route)) {
      navigate('/login', true);
    } else if (
      isAuthenticated &&
      (route === '/login' || route === '/signup' || route === '/forgot-password')
    ) {
      navigate('/', true);
    }
  }, [isAuthenticated, isLoading, route, navigate]);

  if (isLoading) {
    return (
      <div
        style={{ fontFamily: fontFamilyCss }}
        className={`min-h-screen flex flex-col items-center justify-center p-4 transition-colors ${
          theme === 'dark' ? 'bg-[#09090b] text-zinc-200' : 'bg-[#eaedf1] text-slate-800'
        }`}
      >
        <div className="w-12 h-12 rounded-2xl bg-blue-600/15 border border-blue-500/30 flex items-center justify-center text-blue-500 mb-3 animate-pulse">
          <Lock className="w-6 h-6" />
        </div>
        <span className="text-xs text-zinc-500 font-medium">
          Verificando sessão segura...
        </span>
      </div>
    );
  }

  if (!isAuthenticated || route === '/reset-password') {
    return <>{fallbackAuthScreen}</>;
  }

  return <>{children}</>;
}
