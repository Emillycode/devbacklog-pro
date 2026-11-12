import { createClient, type SupabaseClient } from '@supabase/supabase-js';

const envUrl = (import.meta.env.VITE_SUPABASE_URL || '').trim();
const envKey = (
  import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY ||
  import.meta.env.VITE_SUPABASE_ANON_KEY ||
  ''
).trim();

function isValidConfig(url: string, key: string): boolean {
  if (!url || !key) return false;
  if (url.includes('SEU_PROJETO_ID') || key.includes('sua_chave_')) return false;
  // Security safeguard: never initialize browser client with a secret / service_role key
  if (key.startsWith('sb_secret_')) {
    console.error('[Segurança Supabase] Nunca utilize uma chave sb_secret_ no navegador.');
    return false;
  }
  return true;
}

let supabaseInstance: SupabaseClient | null = null;
let currentUrl = '';
let currentKey = '';

if (isValidConfig(envUrl, envKey)) {
  currentUrl = envUrl;
  currentKey = envKey;
  supabaseInstance = createClient(currentUrl, currentKey, {
    auth: {
      persistSession: true,
      autoRefreshToken: true,
      detectSessionInUrl: true,
      storageKey: 'backlog_supabase_auth'
    }
  });
}

/**
 * Inicializa ou retorna o cliente Supabase caso as credenciais públicas
 * venham dinamicamente do backend (api.php) ou variáveis de ambiente VITE_.
 */
export function initSupabaseClient(url?: string, publishableOrAnonKey?: string): SupabaseClient | null {
  const cleanUrl = (url || envUrl || '').trim();
  const cleanKey = (publishableOrAnonKey || envKey || '').trim();

  if (!isValidConfig(cleanUrl, cleanKey)) {
    return supabaseInstance;
  }

  if (!supabaseInstance || cleanUrl !== currentUrl || cleanKey !== currentKey) {
    currentUrl = cleanUrl;
    currentKey = cleanKey;
    supabaseInstance = createClient(cleanUrl, cleanKey, {
      auth: {
        persistSession: true,
        autoRefreshToken: true,
        detectSessionInUrl: true,
        storageKey: 'backlog_supabase_auth'
      }
    });
  }

  return supabaseInstance;
}

export function getSupabaseClient(): SupabaseClient | null {
  return supabaseInstance;
}

export function isSupabaseClientConfigured(): boolean {
  return supabaseInstance !== null;
}
