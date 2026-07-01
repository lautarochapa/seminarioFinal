// Global test setup for jest-expo
// Mock expo-secure-store
jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

// Mock expo-constants
jest.mock('expo-constants', () => ({
  default: {
    expoConfig: {
      extra: {
        EXPO_PUBLIC_API_URL: 'http://localhost:8000',
        EXPO_PUBLIC_SHOW_DEMO_USERS: 'false',
      },
    },
  },
}));

// Silence non-critical React warnings in tests
const originalConsoleError = console.error;
console.error = (...args: unknown[]) => {
  const msg = typeof args[0] === 'string' ? args[0] : '';
  if (msg.includes('Warning:')) return;
  originalConsoleError(...args);
};
