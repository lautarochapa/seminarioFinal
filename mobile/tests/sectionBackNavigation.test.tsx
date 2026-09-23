import React from 'react';
import { BackHandler, Platform, Pressable, Text } from 'react-native';
import { act, fireEvent, render } from '@testing-library/react-native';
import { useSectionBackNavigation } from '../src/hooks/useSectionBackNavigation';
const mockRouter = { replace: jest.fn() };
let mockFocused = true;
jest.mock('expo-router', () => ({ useRouter: () => mockRouter, useFocusEffect: (cb: () => void | (() => void)) => { jest.requireActual<typeof import('react')>('react').useEffect(() => mockFocused ? cb() : undefined, [cb, mockFocused]); } }));
function Probe({ path, params }: { path: string; params?: Record<string, string> }) {
  const back = useSectionBackNavigation(path, params);
  return <Pressable onPress={back}><Text>Volver</Text></Pressable>;
}
beforeEach(() => { jest.clearAllMocks(); mockFocused = true; });
afterEach(() => jest.restoreAllMocks());
it.each(['recipes', 'meal-plans', 'shopping-lists'])('returns to %s instead of Inicio', async (section) => {
  const screen = await render(<Probe path={`/(app)/${section}`} />);
  await fireEvent.press(screen.getByText('Volver'));
  expect(mockRouter.replace).toHaveBeenCalledWith({ pathname: `/(app)/${section}`, params: {} });
});
it('preserves the originating plan and recipe and removes Android listeners on blur', async () => {
  jest.replaceProperty(Platform, 'OS', 'android');
  const remove = jest.fn();
  const listener = jest.spyOn(BackHandler, 'addEventListener').mockReturnValue({ remove });
  const params = { id: '7', addRecipeId: '12', addRecipeName: 'Tarta' };
  const screen = await render(<Probe path="/(app)/meal-plans/[id]" params={params} />);
  await act(async () => { expect(listener.mock.calls[0][1]({ type: 'hardwareBackPress', timeStamp: 1 })).toBe(true); });
  expect(mockRouter.replace).toHaveBeenCalledWith({ pathname: '/(app)/meal-plans/[id]', params });
  mockFocused = false;
  await screen.rerender(<Probe path="/(app)/meal-plans/[id]" params={params} />);
  expect(remove).toHaveBeenCalledTimes(1);
});
