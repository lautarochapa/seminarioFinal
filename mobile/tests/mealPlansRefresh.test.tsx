import { act, renderHook, waitFor } from '@testing-library/react-native';
import { useMealPlans } from '../src/hooks/useMealPlans';
const mockList = jest.fn();
let mockFocused = true;
jest.mock('../src/api/endpoints', () => ({ mealPlansApi: { list: (...args: unknown[]) => mockList(...args) } }));
jest.mock('expo-router', () => ({ useFocusEffect: (cb: () => void | (() => void)) => { jest.requireActual<typeof import('react')>('react').useEffect(() => mockFocused ? cb() : undefined, [cb, mockFocused]); } }));
beforeEach(() => { jest.clearAllMocks(); mockFocused = true; });

it('loads every page of plans in the requested week and refreshes when returning', async () => {
  mockList.mockResolvedValueOnce({ data: [{ id: 1, items: [] }], meta: { last_page: 2 } }).mockResolvedValueOnce({ data: [{ id: 2, items: [] }], meta: { last_page: 2 } });
  const hook = await renderHook(() => useMealPlans(4, '2026-09-21', '2026-09-27'));
  await waitFor(() => expect(hook.result.current.data).toHaveLength(2));
  expect(mockList).toHaveBeenLastCalledWith(4, { page: 2, per_page: 100, date_from: '2026-09-21', date_to: '2026-09-27' });
  mockFocused = false;
  await hook.rerender({});
  mockList.mockResolvedValueOnce({ data: [{ id: 1, items: [{ id: 8 }] }], meta: { last_page: 1 } });
  mockFocused = true;
  await hook.rerender({});
  await waitFor(() => expect(hook.result.current.data[0]?.items).toHaveLength(1));
});

it('does not apply a late response from the previous household', async () => {
  let finish!: (value: unknown) => void;
  mockList.mockReturnValueOnce(new Promise((resolve) => { finish = resolve; })).mockResolvedValueOnce({ data: [{ id: 20 }], meta: { last_page: 1 } });
  const hook = await renderHook(({ group }: { group: number | null }) => useMealPlans(group), { initialProps: { group: 4 as number | null } });
  await hook.rerender({ group: 8 });
  await waitFor(() => expect(hook.result.current.data[0]?.id).toBe(20));
  await act(async () => finish({ data: [{ id: 10 }], meta: { last_page: 1 } }));
  expect(hook.result.current.data[0]?.id).toBe(20);
  await hook.rerender({ group: null });
  expect(hook.result.current.data).toEqual([]);
});
