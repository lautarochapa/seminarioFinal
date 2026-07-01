export interface AuthUserRole {
  code: string;
  name: string;
}

export interface AuthUser {
  id: number;
  name: string;
  lastname: string;
  username: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
  status: string;
  last_login_at: string | null;
  email_verified_at: string | null;
  roles: AuthUserRole[];
  permissions: string[];
  created_at: string;
}

export interface AuthToken {
  access_token: string;
  token_type: string;
  expires_at: string;
  roles: AuthUserRole[];
  permissions: string[];
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  data: AuthUser;
  token: AuthToken;
  trace_id: string;
}

export interface MeResponse {
  data: AuthUser;
  token_payload: Record<string, unknown>;
  trace_id: string;
}

export type AuthState = 'initializing' | 'authenticated' | 'unauthenticated';

export interface StoredSession {
  accessToken: string;
  user: Pick<AuthUser, 'id' | 'name' | 'lastname' | 'email' | 'roles' | 'permissions'>;
}
