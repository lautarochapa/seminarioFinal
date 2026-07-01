import * as SecureStore from 'expo-secure-store';

const KEYS = {
  ACCESS_TOKEN: 'cc_access_token',
  USER: 'cc_user',
} as const;

async function set(key: string, value: string): Promise<void> {
  await SecureStore.setItemAsync(key, value);
}

async function get(key: string): Promise<string | null> {
  return SecureStore.getItemAsync(key);
}

async function remove(key: string): Promise<void> {
  await SecureStore.deleteItemAsync(key);
}

export const secureStorage = {
  async setToken(token: string): Promise<void> {
    await set(KEYS.ACCESS_TOKEN, token);
  },
  async getToken(): Promise<string | null> {
    return get(KEYS.ACCESS_TOKEN);
  },
  async removeToken(): Promise<void> {
    await remove(KEYS.ACCESS_TOKEN);
  },
  async setUser(user: unknown): Promise<void> {
    await set(KEYS.USER, JSON.stringify(user));
  },
  async getUser<T>(): Promise<T | null> {
    const raw = await get(KEYS.USER);
    if (!raw) return null;
    try { return JSON.parse(raw) as T; } catch { return null; }
  },
  async removeUser(): Promise<void> {
    await remove(KEYS.USER);
  },
  async clearAll(): Promise<void> {
    await Promise.all([remove(KEYS.ACCESS_TOKEN), remove(KEYS.USER)]);
  },
};
