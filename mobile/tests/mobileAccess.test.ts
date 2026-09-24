import { hasMobileAccess, assertMobileAccess, WEB_ONLY_MESSAGE } from '../src/auth/mobileAccess';

it('allows only the common user', () => {
  expect(hasMobileAccess([{ code: 'user', name: 'Usuario' }])).toBe(true);
  expect(hasMobileAccess(['user'])).toBe(true);
});

it.each(['super_admin', 'catalog_admin', 'supermarket_admin', 'recipe_admin', 'teacher', 'dietologist', 'system_jobs'])('blocks %s alone and mixed with user', (code) => {
  expect(hasMobileAccess([code])).toBe(false);
  expect(hasMobileAccess(['user', code])).toBe(false);
  expect(() => assertMobileAccess([code])).toThrow(WEB_ONLY_MESSAGE);
});

it('fails closed for missing roles', () => {
  expect(hasMobileAccess([])).toBe(false);
  expect(hasMobileAccess(undefined)).toBe(false);
});
