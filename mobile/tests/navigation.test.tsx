import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { AppHeader } from '../src/components/AppHeader';
import { goBackOrHome } from '../src/utils/navigation';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
}));

jest.mock('expo-router', () => ({
  router: {
    back: jest.fn(),
    replace: jest.fn(),
    canGoBack: jest.fn(),
  },
  useRouter: () => ({
    push: jest.fn(),
    back: jest.fn(),
    replace: jest.fn(),
  }),
}));

// eslint-disable-next-line @typescript-eslint/no-require-imports, @typescript-eslint/no-explicit-any
const { router } = require('expo-router') as { router: { back: any; replace: any; canGoBack: any } };

beforeEach(() => { jest.clearAllMocks(); });

describe('AppHeader back button', () => {
  it('renders Volver button when showBack is true', async () => {
    const { getByLabelText } = await render(<AppHeader title="Test" showBack onBack={jest.fn()} />);
    expect(getByLabelText('Volver')).toBeTruthy();
  });

  it('does not render Volver button when showBack is false', async () => {
    const { queryByLabelText } = await render(<AppHeader title="Test" />);
    expect(queryByLabelText('Volver')).toBeNull();
  });

  it('calls onBack when Volver is pressed', async () => {
    const onBack = jest.fn();
    const { getByLabelText } = await render(<AppHeader title="Test" showBack onBack={onBack} />);
    fireEvent.press(getByLabelText('Volver'));
    expect(onBack).toHaveBeenCalledTimes(1);
  });

  it('renders title text', async () => {
    const { getByText } = await render(<AppHeader title="Mi pantalla" showBack onBack={jest.fn()} />);
    expect(getByText('Mi pantalla')).toBeTruthy();
  });

  it('renders subtitle when provided', async () => {
    const { getByText } = await render(<AppHeader title="Título" subtitle="Subtítulo" />);
    expect(getByText('Subtítulo')).toBeTruthy();
  });
});

describe('goBackOrHome', () => {
  it('calls router.back() when canGoBack is true', () => {
    router.canGoBack.mockReturnValue(true);
    goBackOrHome();
    expect(router.back).toHaveBeenCalledTimes(1);
    expect(router.replace).not.toHaveBeenCalled();
  });

  it('calls router.replace("/(app)") when canGoBack is false', () => {
    router.canGoBack.mockReturnValue(false);
    goBackOrHome();
    expect(router.replace).toHaveBeenCalledWith('/(app)');
    expect(router.back).not.toHaveBeenCalled();
  });

  it('does not call replace when back is available', () => {
    router.canGoBack.mockReturnValue(true);
    goBackOrHome();
    expect(router.replace).not.toHaveBeenCalled();
  });

  it('does not call back when canGoBack is false', () => {
    router.canGoBack.mockReturnValue(false);
    goBackOrHome();
    expect(router.back).not.toHaveBeenCalled();
  });
});
