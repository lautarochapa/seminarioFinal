import React from 'react';
import { Text } from 'react-native';
import { act, render, waitFor } from '@testing-library/react-native';
import { useShoppingListDetail } from '../src/hooks/useShoppingListDetail';

const mockGet = jest.fn();
const mockItems = jest.fn();
let mockFocused = true;
let state: ReturnType<typeof useShoppingListDetail>;
jest.mock('../src/api/endpoints', () => ({
  shoppingListsApi: { get: (...args: unknown[]) => mockGet(...args) },
  shoppingListItemsApi: { list: (...args: unknown[]) => mockItems(...args) },
}));
jest.mock('expo-router', () => ({ useFocusEffect: (cb: () => void | (() => void)) => {
  jest.requireActual<typeof import('react')>('react').useEffect(() => mockFocused ? cb() : undefined, [cb, mockFocused]);
} }));
function Probe({ group = 7, id = 9 }: { group?: number | null; id?: number }) {
  const current = useShoppingListDetail(group, id);
  React.useEffect(() => { state = current; }, [current]);
  return <Text>{current.loading ? 'Cargando' : current.error ? current.error.message : current.list?.status ?? 'Sin lista'}</Text>;
}
const list = (status: string) => ({ data: { id: 9, status } });
beforeEach(() => {
  jest.clearAllMocks(); mockFocused = true;
  mockGet.mockResolvedValue(list('in_progress'));
  mockItems.mockResolvedValue({ data: [{ id: 1, purchase_item_id: null }] });
});

it('refreshes the status and stock links on return from finishing a session', async () => {
  const screen = await render(<Probe />);
  await waitFor(() => expect(screen.getByText('in_progress')).toBeTruthy());
  mockFocused = false; await screen.rerender(<Probe />);
  mockGet.mockResolvedValue(list('completed'));
  mockItems.mockResolvedValue({ data: [{ id: 1, purchase_item_id: 10 }] });
  mockFocused = true; await screen.rerender(<Probe />);
  await waitFor(() => expect(screen.getByText('completed')).toBeTruthy());
  expect(state.items[0].purchase_item_id).toBe(10);
  expect(mockGet).toHaveBeenCalledTimes(2);
});
it('ignores delayed responses from the previous household', async () => {
  let resolve!: (value: unknown) => void;
  mockGet.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
  const screen = await render(<Probe />);
  mockGet.mockResolvedValue(list('completed'));
  await screen.rerender(<Probe group={8} />);
  await waitFor(() => expect(screen.getByText('completed')).toBeTruthy());
  await act(async () => resolve(list('in_progress')));
  expect(state.list?.status).toBe('completed');
  expect(mockGet).toHaveBeenLastCalledWith(8, 9);
});
it('returns a refresh promise that waits for the data and ignores superseded requests', async () => {
  await render(<Probe />);
  let resolve!: (value: unknown) => void;
  mockGet.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
  let pending!: Promise<void>;
  let settled = false;
  await act(async () => { pending = state.refresh().then(() => { settled = true; }); });
  expect(settled).toBe(false);
  mockGet.mockResolvedValue(list('completed'));
  await act(async () => { await state.refresh(); });
  await act(async () => { resolve(list('in_progress')); await pending; });
  expect(settled).toBe(true);
  expect(state.list?.status).toBe('completed');
});
it('clears loading and data when no household is selected', async () => {
  let resolve!: (value: unknown) => void;
  mockGet.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
  const screen = await render(<Probe />);
  await screen.rerender(<Probe group={null} />);
  expect(state.loading).toBe(false);
  expect(state.list).toBeNull();
  expect(state.items).toEqual([]);
  await act(async () => resolve(list('completed')));
  expect(state.list).toBeNull();
});
it('ignores a late error from a blurred screen', async () => {
  let reject!: (error: Error) => void;
  mockGet.mockReturnValueOnce(new Promise((_done, fail) => { reject = fail; }));
  const screen = await render(<Probe />);
  mockFocused = false; await screen.rerender(<Probe />);
  await act(async () => reject(new Error('late error')));
  expect(state.error).toBeNull();
  mockFocused = true; await screen.rerender(<Probe />);
  await waitFor(() => expect(state.loading).toBe(false));
  expect(state.list?.status).toBe('in_progress');
});
