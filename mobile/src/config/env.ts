import Constants from 'expo-constants';

function getEnv(key: string, fallback: string): string {
  const value = (Constants.expoConfig?.extra as Record<string, unknown> | undefined)?.[key];
  if (typeof value === 'string' && value.length > 0) return value;
  // Expo public env vars are inlined at build time
  const pub = process.env[key];
  if (typeof pub === 'string' && pub.length > 0) return pub;
  return fallback;
}

export const ENV = {
  API_URL: getEnv('EXPO_PUBLIC_API_URL', 'http://10.0.2.2:8000'),
  SHOW_DEMO_USERS: getEnv('EXPO_PUBLIC_SHOW_DEMO_USERS', 'false') === 'true',
} as const;
