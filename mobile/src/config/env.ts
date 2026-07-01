import Constants from 'expo-constants';

function fromExtra(key: string): string | undefined {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const val = extra?.[key];
  return typeof val === 'string' && val.length > 0 ? val : undefined;
}

export const ENV = {
  API_URL:
    fromExtra('EXPO_PUBLIC_API_URL') ??
    process.env.EXPO_PUBLIC_API_URL ??
    'http://10.0.2.2:8000',

  SHOW_DEMO_USERS:
    (fromExtra('EXPO_PUBLIC_SHOW_DEMO_USERS') ??
      process.env.EXPO_PUBLIC_SHOW_DEMO_USERS ??
      'false') === 'true',
} as const;
