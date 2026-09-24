import type { AuthUserRole } from '@/types/auth';

export const WEB_ONLY_MESSAGE = 'Esta cuenta es exclusiva de la web. En la app solo pueden ingresar usuarios comunes.';

export function hasMobileAccess(roles: readonly (AuthUserRole | string)[] | undefined): boolean {
  return !!roles?.length && roles.every((role) => (typeof role === 'string' ? role : role.code) === 'user');
}

export function assertMobileAccess(roles: readonly (AuthUserRole | string)[] | undefined): void {
  if (!hasMobileAccess(roles)) throw new Error(WEB_ONLY_MESSAGE);
}
