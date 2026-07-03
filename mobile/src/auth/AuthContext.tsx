import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useReducer,
} from 'react';
import { authApi } from '@/api/endpoints';
import { ApiError, registerUnauthorizedHandler } from '@/api/client';
import { sessionStorage } from '@/storage/sessionStorage';
import { offlineCache } from '@/storage/offlineCache';
import type { AuthState, AuthUser, LoginRequest, RegisterRequest } from '@/types/auth';

interface AuthContextValue {
  state: AuthState;
  user: AuthUser | null;
  isLoading: boolean;
  error: string | null;
  login: (payload: LoginRequest) => Promise<void>;
  register: (payload: RegisterRequest) => Promise<void>;
  logout: () => Promise<void>;
  restoreSession: () => Promise<void>;
  refreshCurrentUser: () => Promise<void>;
}

interface StateShape {
  state: AuthState;
  user: AuthUser | null;
  isLoading: boolean;
  error: string | null;
}

type Action =
  | { type: 'INIT' }
  | { type: 'AUTHENTICATED'; user: AuthUser }
  | { type: 'UNAUTHENTICATED' }
  | { type: 'LOADING' }
  | { type: 'ERROR'; message: string }
  | { type: 'CLEAR_ERROR' };

function reducer(prev: StateShape, action: Action): StateShape {
  switch (action.type) {
    case 'INIT':
      return { ...prev, state: 'initializing', isLoading: true, error: null };
    case 'AUTHENTICATED':
      return { state: 'authenticated', user: action.user, isLoading: false, error: null };
    case 'UNAUTHENTICATED':
      return { state: 'unauthenticated', user: null, isLoading: false, error: null };
    case 'LOADING':
      return { ...prev, isLoading: true, error: null };
    case 'ERROR':
      return { ...prev, isLoading: false, error: action.message };
    case 'CLEAR_ERROR':
      return { ...prev, error: null };
    default:
      return prev;
  }
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [{ state, user, isLoading, error }, dispatch] = useReducer(reducer, {
    state: 'initializing',
    user: null,
    isLoading: true,
    error: null,
  });

  const clearSession = useCallback(async () => {
    await sessionStorage.clear();
    await offlineCache.clearAll();
    dispatch({ type: 'UNAUTHENTICATED' });
  }, []);

  // Register global 401 handler so the client can kick the user out
  useEffect(() => {
    registerUnauthorizedHandler(() => {
      clearSession();
    });
  }, [clearSession]);

  const restoreSession = useCallback(async () => {
    dispatch({ type: 'INIT' });
    try {
      const stored = await sessionStorage.getSession();
      if (!stored) {
        dispatch({ type: 'UNAUTHENTICATED' });
        return;
      }
      // Validate token by calling /me
      const meRes = await authApi.me();
      await sessionStorage.save({
        accessToken: stored.accessToken,
        user: {
          id: meRes.data.id,
          name: meRes.data.name,
          lastname: meRes.data.lastname,
          email: meRes.data.email,
          roles: meRes.data.roles,
          permissions: meRes.data.permissions,
        },
      });
      dispatch({ type: 'AUTHENTICATED', user: meRes.data });
    } catch {
      await clearSession();
    }
  }, [clearSession]);

  const login = useCallback(async (payload: LoginRequest) => {
    dispatch({ type: 'LOADING' });
    const res = await authApi.login(payload);
    await sessionStorage.save({
      accessToken: res.token.access_token,
      user: {
        id: res.data.id,
        name: res.data.name,
        lastname: res.data.lastname,
        email: res.data.email,
        roles: res.data.roles,
        permissions: res.data.permissions,
      },
    });
    dispatch({ type: 'AUTHENTICATED', user: res.data });
  }, []);

  const register = useCallback(async (payload: RegisterRequest) => {
    dispatch({ type: 'LOADING' });
    const res = await authApi.register(payload);
    await sessionStorage.save({
      accessToken: res.token.access_token,
      user: {
        id: res.data.id,
        name: res.data.name,
        lastname: res.data.lastname,
        email: res.data.email,
        roles: res.data.roles,
        permissions: res.data.permissions,
      },
    });
    dispatch({ type: 'AUTHENTICATED', user: res.data });
  }, []);

  const logout = useCallback(async () => {
    dispatch({ type: 'LOADING' });
    try {
      await authApi.logout();
    } catch {
      // Even if the server call fails, clear local session
    }
    await clearSession();
  }, [clearSession]);

  const refreshCurrentUser = useCallback(async () => {
    try {
      const meRes = await authApi.me();
      dispatch({ type: 'AUTHENTICATED', user: meRes.data });
    } catch (err) {
      if (err instanceof ApiError && err.normalized.status === 401) {
        await clearSession();
      }
    }
  }, [clearSession]);

  useEffect(() => {
    restoreSession();
  }, [restoreSession]);

  return (
    <AuthContext.Provider
      value={{ state, user, isLoading, error, login, register, logout, restoreSession, refreshCurrentUser }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
