import React from 'react';
import { act, render, renderHook, waitFor } from '@testing-library/react-native';
import RootLayout from '../app/_layout';
import { FamilyGroupProvider, useFamilyGroupContext } from '../src/auth/FamilyGroupContext';
import type { FamilyGroup } from '../src/types/familyGroup';

const mockList = jest.fn();
const mockStatus = jest.fn();
const mockGetId = jest.fn();
const mockSetId = jest.fn();
const mockRemoveId = jest.fn();
const mockClearCache = jest.fn();
const mockRouter = { replace: jest.fn() };
let mockAuth = { state: 'authenticated', user: { id: 4 } };
const mockSegments = ['(app)'];

jest.mock('../src/auth/AuthContext', () => ({
  AuthProvider: ({ children }: { children: React.ReactNode }) => children,
  useAuth: () => mockAuth,
}));
jest.mock('../src/api/endpoints', () => ({
  familyGroupsApi: { list: () => mockList() },
  onboardingApi: { status: () => mockStatus() },
}));
jest.mock('../src/storage/secureStorage', () => ({ secureStorage: {
  getSelectedGroupId: () => mockGetId(),
  setSelectedGroupId: (id: number) => mockSetId(id),
  removeSelectedGroupId: () => mockRemoveId(),
} }));
jest.mock('../src/storage/offlineCache', () => ({ offlineCache: { clearAll: () => mockClearCache() } }));
jest.mock('react-native-safe-area-context', () => ({
  SafeAreaProvider: ({ children }: { children: React.ReactNode }) => children,
}));
jest.mock('../src/components/OfflineBanner', () => ({ OfflineBanner: () => null }));
jest.mock('../src/components/LoadingScreen', () => ({ LoadingScreen: () => null }));
jest.mock('../src/components/ErrorBoundary', () => ({
  ErrorBoundary: ({ children }: { children: React.ReactNode }) => children,
}));
jest.mock('expo-router', () => ({
  useRouter: () => mockRouter,
  useSegments: () => mockSegments,
  Slot: () => {
    const { Text } = jest.requireActual<typeof import('react-native')>('react-native');
    const { useFamilyGroupContext: useGroup } = jest.requireActual<typeof import('../src/auth/FamilyGroupContext')>('../src/auth/FamilyGroupContext');
    const { selectedGroup } = useGroup();
    return <Text testID="active-group">{selectedGroup?.name ?? 'Sin grupo'}</Text>;
  },
}));

const GROUP = { id: 7, name: 'Hogar QA', owner_user_id: 4, status: 'active' } as FamilyGroup;
const OTHER = { ...GROUP, id: 8, name: 'Otro hogar' };
const wrapper = ({ children }: { children: React.ReactNode }) => <FamilyGroupProvider>{children}</FamilyGroupProvider>;

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}

beforeEach(() => {
  jest.clearAllMocks();
  mockAuth = { state: 'authenticated', user: { id: 4 } };
  mockList.mockResolvedValue({ data: [GROUP, OTHER] });
  mockStatus.mockResolvedValue({ data: { complete: true } });
  mockGetId.mockResolvedValue(7);
  mockSetId.mockResolvedValue(undefined);
  mockRemoveId.mockResolvedValue(undefined);
  mockClearCache.mockResolvedValue(undefined);
});

it('restores the saved group on a cold authenticated start without opening Groups', async () => {
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(screen.getByTestId('active-group').props.children).toBe('Hogar QA'));
  expect(mockList).toHaveBeenCalledTimes(1);
  expect(mockRouter.replace).not.toHaveBeenCalled();
});

it('loads groups only after session validation completes', async () => {
  mockAuth.state = 'initializing';
  const screen = await render(<RootLayout />);
  expect(mockList).not.toHaveBeenCalled();
  mockAuth.state = 'authenticated';
  await screen.rerender(<RootLayout />);
  await waitFor(() => expect(screen.getByTestId('active-group').props.children).toBe('Hogar QA'));
});

it('auto-selects a single available group without a stored choice', async () => {
  mockList.mockResolvedValue({ data: [GROUP] });
  mockGetId.mockResolvedValue(null);
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(screen.getByTestId('active-group').props.children).toBe('Hogar QA'));
  expect(mockSetId).toHaveBeenCalledWith(7);
});

it('leaves selection empty when the stored group no longer belongs to the user', async () => {
  mockGetId.mockResolvedValue(99);
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(mockRemoveId).toHaveBeenCalled());
  expect(screen.getByTestId('active-group').props.children).toBe('Sin grupo');
});

it('keeps navigation available if the group request fails', async () => {
  mockList.mockRejectedValue(new Error('Network unavailable'));
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(mockList).toHaveBeenCalledTimes(1));
  expect(screen.getByTestId('active-group').props.children).toBe('Sin grupo');
  expect(mockRouter.replace).not.toHaveBeenCalled();
});

it('ignores a group response arriving after logout', async () => {
  const request = deferred<{ data: FamilyGroup[] }>();
  mockList.mockReturnValue(request.promise);
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(mockList).toHaveBeenCalledTimes(1));
  mockAuth.state = 'unauthenticated';
  await screen.rerender(<RootLayout />);
  await act(async () => { request.resolve({ data: [GROUP] }); });
  expect(screen.getByTestId('active-group').props.children).toBe('Sin grupo');
  expect(mockGetId).not.toHaveBeenCalled();
});

it('does not restore a group when logout happens during the storage read', async () => {
  const stored = deferred<number | null>();
  mockGetId.mockReturnValue(stored.promise);
  const screen = await render(<RootLayout />);
  await waitFor(() => expect(mockGetId).toHaveBeenCalledTimes(1));
  mockAuth.state = 'unauthenticated';
  await screen.rerender(<RootLayout />);
  await act(async () => { stored.resolve(7); });
  expect(screen.getByTestId('active-group').props.children).toBe('Sin grupo');
});

it('does not let a late restore overwrite a manual selection', async () => {
  const stored = deferred<number | null>();
  mockGetId.mockReturnValue(stored.promise);
  const { result } = await renderHook(() => useFamilyGroupContext(), { wrapper });
  let restoring!: Promise<void>;
  await act(async () => { restoring = result.current.restoreGroup([GROUP, OTHER]); });
  await act(async () => { result.current.selectGroup(OTHER); });
  await act(async () => { stored.resolve(7); await restoring; });
  expect(result.current.selectedGroup?.id).toBe(8);
});

it('clears an obsolete selection when no groups remain', async () => {
  const { result } = await renderHook(() => useFamilyGroupContext(), { wrapper });
  await act(async () => { result.current.selectGroup(GROUP); });
  await act(async () => { await result.current.restoreGroup([]); });
  expect(result.current.selectedGroup).toBeNull();
});
