import type { LoginResponse } from '../src/types/auth';

describe('LoginResponse token extraction', () => {
  const mockResponse: LoginResponse = {
    data: {
      id: 1,
      name: 'Usuario',
      lastname: 'Demo',
      username: 'demo_user',
      email: 'usuario@cccontrol.test',
      phone: null,
      avatar_url: null,
      status: 'active',
      last_login_at: '2026-07-01T19:06:09+00:00',
      email_verified_at: '2026-07-01T13:46:14+00:00',
      roles: [{ code: 'user', name: 'Usuario comun' }],
      permissions: ['profile.manage', 'family.manage'],
      created_at: '2026-07-01T13:46:14+00:00',
    },
    token: {
      access_token: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.test',
      token_type: 'Bearer',
      expires_at: '2026-08-01T00:00:00+00:00',
      roles: [{ code: 'user', name: 'Usuario comun' }],
      permissions: ['profile.manage'],
    },
    trace_id: 'test-trace-id',
  };

  it('extracts access_token from response.token (not response.data)', () => {
    const token = mockResponse.token.access_token;
    expect(token).toBe('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.test');
    // Verify token is NOT inside data
    expect((mockResponse.data as unknown as Record<string, unknown>)['access_token']).toBeUndefined();
  });

  it('has trace_id at root level', () => {
    expect(mockResponse.trace_id).toBe('test-trace-id');
  });

  it('user data is inside data field', () => {
    expect(mockResponse.data.id).toBe(1);
    expect(mockResponse.data.email).toBe('usuario@cccontrol.test');
  });

  it('roles are present in both data and token', () => {
    expect(mockResponse.data.roles[0].code).toBe('user');
    expect(mockResponse.token.roles[0].code).toBe('user');
  });
});
