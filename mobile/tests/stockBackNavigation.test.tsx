import React from 'react';
import { BackHandler, Platform, Pressable, Text } from 'react-native';
import { act, fireEvent, render } from '@testing-library/react-native';
import { useStockBackNavigation } from '../src/hooks/useStockBackNavigation';

const mockRouter = { replace: jest.fn() };
let mockFocused = true;
jest.mock('expo-router', () => ({
  useRouter: () => mockRouter,
  useFocusEffect: (cb: () => void | (() => void)) => {
    jest.requireActual<typeof import('react')>('react').useEffect(() => mockFocused ? cb() : undefined, [cb, mockFocused]);
  },
}));
function Probe({ id }: { id?: number }) {
  const back = useStockBackNavigation(id);
  return <Pressable onPress={back}><Text>Volver</Text></Pressable>;
}
beforeEach(() => { jest.clearAllMocks(); mockFocused = true; });
afterEach(() => jest.restoreAllMocks());

it('returns to Mi cocina instead of hidden-tab Home history', async () => {
  const screen = await render(<Probe />);
  await fireEvent.press(screen.getByText('Volver'));
  expect(mockRouter.replace).toHaveBeenCalledWith('/(app)/stock');
});
it('returns from editing to the same stock item', async () => {
  const screen = await render(<Probe id={40} />);
  await fireEvent.press(screen.getByText('Volver'));
  expect(mockRouter.replace).toHaveBeenCalledWith({ pathname: '/(app)/stock/[id]', params: { id: '40' } });
});
it('handles Android back only while focused and cleans up the listener', async () => {
  jest.replaceProperty(Platform, 'OS', 'android');
  const remove = jest.fn();
  const listener = jest.spyOn(BackHandler, 'addEventListener').mockReturnValue({ remove });
  const screen = await render(<Probe id={40} />);
  expect(listener).toHaveBeenCalledWith('hardwareBackPress', expect.any(Function));
  await act(async () => { expect(listener.mock.calls[0][1]({ type: 'hardwareBackPress', timeStamp: 1 })).toBe(true); });
  expect(mockRouter.replace).toHaveBeenCalledWith({ pathname: '/(app)/stock/[id]', params: { id: '40' } });
  mockFocused = false;
  await screen.rerender(<Probe id={40} />);
  expect(remove).toHaveBeenCalledTimes(1);
  mockFocused = true;
  await screen.rerender(<Probe id={40} />);
  await screen.unmount();
  expect(remove).toHaveBeenCalledTimes(2);
});
