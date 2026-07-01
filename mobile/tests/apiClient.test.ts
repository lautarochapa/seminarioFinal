import * as SecureStore from 'expo-secure-store';

// We test api/client indirectly through fetch mocks
const mockFetch = jest.fn();
(globalThis as typeof globalThis & { fetch: typeof fetch }).fetch = mockFetch;

const mockGetToken = SecureStore.getItemAsync as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('apiClient Bearer token', () => {
  it('adds Authorization header when token is stored', async () => {
    mockGetToken.mockResolvedValueOnce('my-bearer-token');
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: { test: true }, trace_id: 'x' }),
    });

    const { apiClient } = require('../src/api/client');
    await apiClient.get('/api/v1/test');

    const callArgs = mockFetch.mock.calls[0];
    const headers = callArgs[1].headers;
    expect(headers['Authorization']).toBe('Bearer my-bearer-token');
  });

  it('does NOT add Authorization header when skipAuth is true', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: {}, trace_id: 'x' }),
    });

    const { apiClient } = require('../src/api/client');
    await apiClient.post('/api/v1/auth/login', { email: 'x', password: 'y' }, { skipAuth: true });

    const callArgs = mockFetch.mock.calls[0];
    const headers = callArgs[1].headers;
    expect(headers['Authorization']).toBeUndefined();
  });

  it('throws ApiError on 401', async () => {
    mockGetToken.mockResolvedValueOnce('expired-token');
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 401,
      headers: new Headers(),
      json: async () => ({
        error: { code: 'AUTH_INVALID_TOKEN', message: 'Token inválido.', details: [], field_errors: {} },
        trace_id: 'err-trace',
      }),
    });

    const { apiClient, ApiError } = require('../src/api/client');
    await expect(apiClient.get('/api/v1/protected')).rejects.toThrow(ApiError);
  });

  it('throws ApiError with isNetworkError on fetch failure', async () => {
    mockGetToken.mockResolvedValueOnce(null);
    mockFetch.mockRejectedValueOnce(new TypeError('Network request failed'));

    const { apiClient, ApiError } = require('../src/api/client');
    try {
      await apiClient.get('/api/v1/test');
      fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as InstanceType<typeof ApiError>).normalized.isNetworkError).toBe(true);
    }
  });

  it('returns undefined on 204', async () => {
    mockGetToken.mockResolvedValueOnce('token');
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 204,
      headers: new Headers(),
    });

    const { apiClient } = require('../src/api/client');
    const result = await apiClient.post('/api/v1/auth/logout');
    expect(result).toBeUndefined();
  });
});

describe('apiClient 429 handling', () => {
  it('throws ApiError with status 429 and retryAfter', async () => {
    mockGetToken.mockResolvedValueOnce('token');
    const headers = new Headers({ 'Retry-After': '60' });
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 429,
      headers,
      json: async () => ({
        error: { code: 'TOO_MANY_REQUESTS', message: 'Rate limited.', details: [], field_errors: {} },
        trace_id: '',
      }),
    });

    const { apiClient, ApiError } = require('../src/api/client');
    try {
      await apiClient.post('/api/v1/auth/login', {}, { skipAuth: true });
      fail('should throw');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as InstanceType<typeof ApiError>).normalized.status).toBe(429);
      expect((err as InstanceType<typeof ApiError>).normalized.retryAfter).toBe(60);
    }
  });
});
