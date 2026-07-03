type GlobalWithDev = typeof globalThis & { __DEV__: boolean };

function loadEnvWith(apiUrl: string, isDev: boolean, allowInsecure?: string) {
  jest.resetModules();
  process.env.EXPO_PUBLIC_API_URL = apiUrl;
  if (allowInsecure !== undefined) {
    process.env.EXPO_PUBLIC_ALLOW_INSECURE_API = allowInsecure;
  } else {
    delete process.env.EXPO_PUBLIC_ALLOW_INSECURE_API;
  }
  (globalThis as GlobalWithDev).__DEV__ = isDev;
  return require('../src/config/env');
}

describe('ENV production HTTPS guard', () => {
  const originalDev = (globalThis as GlobalWithDev).__DEV__;
  const originalApiUrl = process.env.EXPO_PUBLIC_API_URL;
  const originalAllowInsecure = process.env.EXPO_PUBLIC_ALLOW_INSECURE_API;

  afterEach(() => {
    (globalThis as GlobalWithDev).__DEV__ = originalDev;
    process.env.EXPO_PUBLIC_API_URL = originalApiUrl;
    process.env.EXPO_PUBLIC_ALLOW_INSECURE_API = originalAllowInsecure;
  });

  it('allows http:// API URLs in development', () => {
    expect(() => loadEnvWith('http://10.0.2.2:8000', true)).not.toThrow();
  });

  it('throws when the API URL is http:// in production without an explicit override', () => {
    expect(() => loadEnvWith('http://api.example.com', false)).toThrow(/HTTPS en producción/);
  });

  it('allows http:// in production when EXPO_PUBLIC_ALLOW_INSECURE_API is set', () => {
    expect(() => loadEnvWith('http://internal.local', false, 'true')).not.toThrow();
  });

  it('allows https:// API URLs in production', () => {
    expect(() => loadEnvWith('https://api.example.com', false)).not.toThrow();
  });
});
