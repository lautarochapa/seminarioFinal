import { secureStorage } from './secureStorage';
import type { StoredSession } from '@/types/auth';

export const sessionStorage = {
  async save(session: StoredSession): Promise<void> {
    await Promise.all([
      secureStorage.setToken(session.accessToken),
      secureStorage.setUser(session.user),
    ]);
  },

  async getToken(): Promise<string | null> {
    return secureStorage.getToken();
  },

  async getSession(): Promise<StoredSession | null> {
    const [token, user] = await Promise.all([
      secureStorage.getToken(),
      secureStorage.getUser<StoredSession['user']>(),
    ]);
    if (!token || !user) return null;
    return { accessToken: token, user };
  },

  async clear(): Promise<void> {
    await secureStorage.clearAll();
  },
};
