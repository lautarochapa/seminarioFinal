import React from 'react';
import { Text } from 'react-native';
import { act, render, waitFor } from '@testing-library/react-native';
import { useStockItem } from '../src/hooks/useStockItem';

const mockList = jest.fn();
let mockFocused = true;
jest.mock('../src/api/endpoints', () => ({ stockApi: { list: (...args: unknown[]) => mockList(...args) } }));
jest.mock('expo-router', () => ({ useFocusEffect: (cb: () => void | (() => void)) => {
  jest.requireActual<typeof import('react')>('react').useEffect(() => mockFocused ? cb() : undefined, [cb, mockFocused]);
} }));
function Probe({ group = 7, id = 40 }: { group?: number; id?: number }) {
  const state = useStockItem(group, id);
  return <Text>{state.loading ? 'Cargando' : state.error ? state.error.message : state.data?.product?.name ?? 'No encontrado'}</Text>;
}
const page = (id: number, name: string, last = 1) => ({ data: [{ id, product: { name } }], meta: { last_page: last } });
beforeEach(() => { jest.clearAllMocks(); mockFocused = true; mockList.mockResolvedValue(page(40, 'Avena')); });
it('loads an item on a later page without a false not-found result', async () => {
  mockList.mockResolvedValueOnce(page(1, 'Otro', 2)).mockResolvedValueOnce(page(40, 'Avena', 2));
  const screen = await render(<Probe />);
  expect(screen.queryByText('No encontrado')).toBeNull();
  await waitFor(() => expect(screen.getByText('Avena')).toBeTruthy());
  expect(mockList).toHaveBeenLastCalledWith(7, { page: 2 });
});
it('refreshes when returning from an edit and when selecting another item', async () => {
  const screen = await render(<Probe />);
  await waitFor(() => expect(screen.getByText('Avena')).toBeTruthy());
  mockFocused = false; await screen.rerender(<Probe />);
  mockList.mockResolvedValue(page(40, 'Actualizado'));
  mockFocused = true; await screen.rerender(<Probe />);
  await waitFor(() => expect(screen.getByText('Actualizado')).toBeTruthy());
  mockList.mockResolvedValue(page(41, 'Arroz'));
  await screen.rerender(<Probe id={41} />);
  await waitFor(() => expect(screen.getByText('Arroz')).toBeTruthy());
});
it('ignores a delayed response from another household', async () => {
  let resolveOld!: (value: ReturnType<typeof page>) => void;
  mockList.mockReturnValueOnce(new Promise((resolve) => { resolveOld = resolve; }));
  const screen = await render(<Probe />);
  mockList.mockResolvedValue(page(40, 'Hogar nuevo'));
  await screen.rerender(<Probe group={8} />);
  await waitFor(() => expect(screen.getByText('Hogar nuevo')).toBeTruthy());
  await act(async () => resolveOld(page(40, 'Hogar viejo')));
  expect(screen.queryByText('Hogar viejo')).toBeNull();
});
