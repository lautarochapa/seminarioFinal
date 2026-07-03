import AsyncStorage from '@react-native-async-storage/async-storage';

const PREFIX = 'cccontrol:cache:';

interface CacheEnvelope<T> {
  data: T;
  cachedAt: string;
}

export const offlineCache = {
  async read<T>(key: string): Promise<{ data: T; cachedAt: string } | null> {
    try {
      const raw = await AsyncStorage.getItem(PREFIX + key);
      if (!raw) return null;
      const parsed = JSON.parse(raw) as CacheEnvelope<T>;
      return parsed;
    } catch {
      return null;
    }
  },

  async write<T>(key: string, data: T): Promise<void> {
    try {
      const envelope: CacheEnvelope<T> = { data, cachedAt: new Date().toISOString() };
      await AsyncStorage.setItem(PREFIX + key, JSON.stringify(envelope));
    } catch {
      // Storage full or unavailable — cache is best-effort, never block the app.
    }
  },

  async clear(key: string): Promise<void> {
    try {
      await AsyncStorage.removeItem(PREFIX + key);
    } catch {
      // ignore
    }
  },

  async clearAll(): Promise<void> {
    try {
      const keys = await AsyncStorage.getAllKeys();
      const ours = keys.filter((k) => k.startsWith(PREFIX));
      if (ours.length > 0) await AsyncStorage.multiRemove(ours);
    } catch {
      // ignore
    }
  },
};
