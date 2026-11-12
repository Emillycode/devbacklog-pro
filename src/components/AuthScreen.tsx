import React, { useState } from 'react';
import {
  Lock,
  Unlock,
  KeyRound,
  Mail,
  UserPlus,
  ArrowLeft,
  CheckCircle2,
  AlertTriangle,
  Sun,
  Moon,
  ShieldCheck
} from 'lucide-react';
import type { ThemeMode } from '../utils/backlog-logic.ts';
import type { AppRoutePath } from './ProtectedRoute.tsx';

interface AuthScreenProps {
  route: AppRoutePath;
  navigate: (to: AppRoutePath, replace?: boolean) => void;
  theme: ThemeMode;
  onToggleTheme: () => void;
  fontFamilyCss: string;
  supabaseEnabled: boolean;
  onLogin: (email: string, password: string) => Promise<{ ok: boolean; error?: string }>;
  onForgotPassword: (
    email: string
  ) => Promise<{ ok: boolean; error?: string; message?: string }>;
  onResetPassword: (
    newPassword: string
  ) => Promise<{ ok: boolean; error?: string; message?: string }>;
}

export function AuthScreen({
  route,
  navigate,
  theme,
  onToggleTheme,
  fontFamilyCss,
  supabaseEnabled,
  onLogin,
  onForgotPassword,
  onResetPassword
}: AuthScreenProps) {
  const activeView: 'login' | 'forgot' | 'reset' =
    route === '/forgot-password'
      ? 'forgot'
      : route === '/reset-password'
        ? 'reset'
        : 'login';

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const switchView = (nextRoute: AppRoutePath) => {
    setErrorMsg('');
    setSuccessMsg('');
    setPassword('');
    setConfirmPassword('');
    navigate(nextRoute);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    if (activeView === 'login') {
      if (!email.trim() || !password) return;
      setSubmitting(true);
      try {
        const res = await onLogin(email.trim(), password);
        if (!res.ok) {
          setErrorMsg(res.error || 'E-mail ou senha inválidos.');
        } else {
          setPassword('');
          navigate('/', true);
        }
      } finally {
        setSubmitting(false);
      }
      return;
    }

    if (activeView === 'forgot') {
      if (!email.trim()) return;
      setSubmitting(true);
      try {
        const res = await onForgotPassword(email.trim());
        if (!res.ok) {
          setErrorMsg(res.error || 'Não foi possível enviar o e-mail de recuperação.');
        } else {
          setSuccessMsg(
            res.message ||
              'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.'
          );
        }
      } finally {
        setSubmitting(false);
      }
      return;
    }

    if (activeView === 'reset') {
      if (password.length < 6) {
        setErrorMsg('A nova senha deve ter pelo menos 6 caracteres.');
        return;
      }
      if (password !== confirmPassword) {
        setErrorMsg('As senhas digitadas não coincidem.');
        return;
      }
      setSubmitting(true);
      try {
        const res = await onResetPassword(password);
        if (!res.ok) {
          setErrorMsg(res.error || 'Erro ao atualizar a senha.');
        } else {
          setSuccessMsg(res.message || 'Senha atualizada com sucesso!');
          setPassword('');
          setConfirmPassword('');
          setTimeout(() => navigate('/', true), 1200);
        }
      } finally {
        setSubmitting(false);
      }
    }
  };

  const inputBaseClass = `w-full text-sm rounded-xl border focus:outline-none focus:border-blue-500 transition-colors ${
    theme === 'dark'
      ? 'bg-[#18181c] border-zinc-800 text-zinc-100 placeholder-zinc-600'
      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
  }`;

  return (
    <div
      style={{ fontFamily: fontFamilyCss }}
      className={`min-h-screen flex items-center justify-center p-4 transition-colors relative ${
        theme === 'dark' ? 'bg-[#09090b] text-zinc-100' : 'bg-[#eaedf1] text-slate-900'
      }`}
    >
      <button
        type="button"
        onClick={onToggleTheme}
        className={`absolute top-4 right-4 p-2.5 rounded-xl border transition-colors cursor-pointer ${
          theme === 'dark'
            ? 'bg-[#121215] border-zinc-800 text-zinc-300 hover:bg-zinc-800'
            : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100 shadow-2xs'
        }`}
        title={theme === 'dark' ? 'Alternar para modo claro' : 'Alternar para modo escuro'}
      >
        {theme === 'dark' ? (
          <Sun className="w-4 h-4 text-amber-400" />
        ) : (
          <Moon className="w-4 h-4 text-slate-700" />
        )}
      </button>

      <div
        className={`w-full max-w-sm rounded-2xl border p-6 sm:p-8 shadow-2xl ${
          theme === 'dark' ? 'bg-[#121215] border-zinc-800' : 'bg-white border-slate-300'
        }`}
      >
        <div className="flex flex-col items-center text-center mb-6">
          <div className="w-12 h-12 rounded-2xl bg-blue-600/15 border border-blue-500/30 flex items-center justify-center text-blue-500 mb-3 shadow-inner">
            <Lock className="w-6 h-6" />
          </div>
          <h1 className="text-lg font-bold tracking-tight">
            {activeView === 'login' && 'Acesso Restrito'}
            {activeView === 'forgot' && 'Recuperar Senha'}
            {activeView === 'reset' && 'Redefinir Senha'}
          </h1>
          <p className="text-xs text-zinc-500 mt-1">
            {activeView === 'login' && 'Plataforma exclusiva • Acesso restrito ao proprietário'}
            {activeView === 'forgot' && 'Enviaremos um link de recuperação para o seu e-mail'}
            {activeView === 'reset' && 'Escolha uma nova senha segura para sua conta'}
          </p>

          <div
            className={`mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[0.68rem] font-medium border ${
              supabaseEnabled
                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/25'
                : 'bg-amber-500/10 text-amber-400 border-amber-500/25'
            }`}
          >
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>
              {supabaseEnabled ? 'Protegido por Supabase Auth' : 'Modo Local (.env) — Configure o Supabase'}
            </span>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {activeView !== 'reset' && (
            <div>
              <label className="block text-[0.75rem] font-semibold uppercase tracking-wider text-zinc-500 mb-1.5">
                E-mail
              </label>
              <div className="relative">
                <Mail className="w-4 h-4 text-zinc-500 absolute left-3.5 top-3" />
                <input
                  type="email"
                  autoFocus
                  required
                  value={email}
                  onChange={e => setEmail(e.target.value)}
                  placeholder="seu@email.com"
                  className={`${inputBaseClass} pl-10 pr-4 py-2.5`}
                />
              </div>
            </div>
          )}

          {activeView !== 'forgot' && (
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="block text-[0.75rem] font-semibold uppercase tracking-wider text-zinc-500">
                  {activeView === 'reset' ? 'Nova Senha' : 'Senha'}
                </label>
                {activeView === 'login' && supabaseEnabled && (
                  <button
                    type="button"
                    onClick={() => switchView('/forgot-password')}
                    className="text-[0.7rem] text-blue-500 hover:underline cursor-pointer"
                  >
                    Esqueceu a senha?
                  </button>
                )}
              </div>
              <div className="relative">
                <KeyRound className="w-4 h-4 text-zinc-500 absolute left-3.5 top-3" />
                <input
                  type="password"
                  required
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className={`${inputBaseClass} pl-10 pr-4 py-2.5`}
                />
              </div>
            </div>
          )}

          {(activeView === 'signup' || activeView === 'reset') && (
            <div>
              <label className="block text-[0.75rem] font-semibold uppercase tracking-wider text-zinc-500 mb-1.5">
                Confirmar Senha
              </label>
              <div className="relative">
                <KeyRound className="w-4 h-4 text-zinc-500 absolute left-3.5 top-3" />
                <input
                  type="password"
                  required
                  value={confirmPassword}
                  onChange={e => setConfirmPassword(e.target.value)}
                  placeholder="••••••••"
                  className={`${inputBaseClass} pl-10 pr-4 py-2.5`}
                />
              </div>
            </div>
          )}

          {errorMsg && (
            <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          {successMsg && (
            <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 shrink-0" />
              <span>{successMsg}</span>
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 disabled:opacity-60 text-white font-semibold text-xs rounded-xl shadow-md transition-colors flex items-center justify-center gap-2 cursor-pointer"
          >
            {activeView === 'login' && (
              <>
                <Unlock className="w-4 h-4" />
                <span>{submitting ? 'Autenticando...' : 'Entrar'}</span>
              </>
            )}
            {activeView === 'forgot' && (
              <>
                <Mail className="w-4 h-4" />
                <span>{submitting ? 'Enviando...' : 'Enviar Link de Recuperação'}</span>
              </>
            )}
            {activeView === 'reset' && (
              <>
                <KeyRound className="w-4 h-4" />
                <span>{submitting ? 'Salvando...' : 'Salvar Nova Senha'}</span>
              </>
            )}
          </button>

          {(activeView === 'forgot' || activeView === 'reset') && (
            <button
              type="button"
              onClick={() => switchView('/login')}
              className="w-full py-2 text-xs text-zinc-400 hover:text-zinc-200 flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              <span>Voltar para o login</span>
            </button>
          )}
        </form>
      </div>
    </div>
  );
}
