import { validateLoginForm } from '../src/validation/loginSchema';

describe('validateLoginForm', () => {
  it('passes with valid email and password', () => {
    const errors = validateLoginForm({ email: 'test@test.com', password: 'password123' });
    expect(errors).toEqual({});
  });

  it('requires email', () => {
    const errors = validateLoginForm({ email: '', password: 'password123' });
    expect(errors.email).toBeDefined();
  });

  it('rejects malformed email', () => {
    const errors = validateLoginForm({ email: 'not-an-email', password: 'password123' });
    expect(errors.email).toBeDefined();
  });

  it('requires password', () => {
    const errors = validateLoginForm({ email: 'test@test.com', password: '' });
    expect(errors.password).toBeDefined();
  });

  it('rejects password shorter than 6 chars', () => {
    const errors = validateLoginForm({ email: 'test@test.com', password: 'abc' });
    expect(errors.password).toBeDefined();
  });

  it('rejects both fields empty', () => {
    const errors = validateLoginForm({ email: '', password: '' });
    expect(errors.email).toBeDefined();
    expect(errors.password).toBeDefined();
  });
});
