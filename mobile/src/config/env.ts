import Constants from 'expo-constants';

function fromExtra(key: string): string | undefined {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const val = extra?.[key];
  return typeof val === 'string' && val.length > 0 ? val : undefined;
}

const apiUrl =
  fromExtra('EXPO_PUBLIC_API_URL') ??
  fromExtra('apiUrl') ??
  process.env.EXPO_PUBLIC_API_URL ??
  'http://localhost:8000';

const allowInsecureApi =
  (fromExtra('EXPO_PUBLIC_ALLOW_INSECURE_API') ??
    fromExtra('allowInsecureApi') ??
    process.env.EXPO_PUBLIC_ALLOW_INSECURE_API ??
    'false') === 'true';

// Fail fast in production builds if the API URL isn't HTTPS, unless an internal
// environment explicitly opts in via EXPO_PUBLIC_ALLOW_INSECURE_API=true.
if (!__DEV__ && apiUrl.startsWith('http://') && !allowInsecureApi) {
  throw new Error(
    `EXPO_PUBLIC_API_URL debe usar HTTPS en producción (recibido: ${apiUrl}). ` +
    'Si es un entorno interno intencional, definí EXPO_PUBLIC_ALLOW_INSECURE_API=true.',
  );
}

export const ENV = {
  API_URL: apiUrl,

  SHOW_DEMO_USERS:
    (fromExtra('EXPO_PUBLIC_SHOW_DEMO_USERS') ??
      process.env.EXPO_PUBLIC_SHOW_DEMO_USERS ??
      'false') === 'true',
} as const;
