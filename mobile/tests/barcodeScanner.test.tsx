import React from 'react';
import { render, fireEvent, waitFor, act } from '@testing-library/react-native';
import { BarcodeScannerScreen } from '../src/screens/BarcodeScannerScreen';
import { ApiError } from '../src/api/client';
import { consumePendingScanResult } from '../src/utils/barcodeScanResult';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

const mockBack = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ back: mockBack, push: jest.fn(), replace: jest.fn() }),
}));

let mockPermission: { granted: boolean; canAskAgain: boolean } | null = { granted: true, canAskAgain: true };
const mockRequestPermission = jest.fn();
let capturedOnScanned: ((result: { data: string; type: string }) => void) | null = null;

jest.mock('expo-camera', () => ({
  CameraView: (props: { children?: React.ReactNode; onBarcodeScanned?: (r: { data: string; type: string }) => void }) => {
    capturedOnScanned = props.onBarcodeScanned ?? null;
    const { View } = require('react-native');
    return <View testID="camera-view">{props.children}</View>;
  },
  useCameraPermissions: () => [mockPermission, mockRequestPermission],
}));

const mockFindByBarcode = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  productsApi: { findByBarcode: (...args: unknown[]) => mockFindByBarcode(...args) },
}));

beforeEach(() => {
  jest.clearAllMocks();
  mockPermission = { granted: true, canAskAgain: true };
  capturedOnScanned = null;
  consumePendingScanResult(); // drain any leftover
});

describe('BarcodeScanner — permissions', () => {
  it('shows a request button when permission is not granted and can be asked again', async () => {
    mockPermission = { granted: false, canAskAgain: true };
    const { getByText } = await render(<BarcodeScannerScreen />);
    expect(getByText('Permitir cámara')).toBeTruthy();
  });

  it('shows a blocked message with no request button when permission cannot be asked again', async () => {
    mockPermission = { granted: false, canAskAgain: false };
    const { queryByText, getByText } = await render(<BarcodeScannerScreen />);
    expect(queryByText('Permitir cámara')).toBeNull();
    expect(getByText(/permiso de cámara está bloqueado/)).toBeTruthy();
  });
});

describe('BarcodeScannerScreen — lookup flow', () => {
  it('shows the product and lets the user use it when found', async () => {
    mockFindByBarcode.mockResolvedValue({ data: { id: 5, name: 'Arroz', brand: null } });
    const { getByText, findByText } = await render(<BarcodeScannerScreen />);

    await act(async () => {
      capturedOnScanned?.({ data: '7791234567890', type: 'ean13' });
    });

    expect(await findByText('Arroz')).toBeTruthy();
    await fireEvent.press(getByText('Usar este producto'));

    expect(mockBack).toHaveBeenCalled();
    expect(consumePendingScanResult()).toEqual({ productId: 5, productName: 'Arroz', barcode: '7791234567890' });
  });

  it('shows a not-found message when the product does not exist', async () => {
    mockFindByBarcode.mockRejectedValue(new ApiError({
      status: 404, code: 'PRODUCT_NOT_FOUND', message: 'No existe.',
      fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false,
    }));
    const { findByText } = await render(<BarcodeScannerScreen />);

    await act(async () => {
      capturedOnScanned?.({ data: '000000000000', type: 'ean13' });
    });

    expect(await findByText('No encontramos un producto con este código.')).toBeTruthy();
  });

  it('ignores a duplicate scan while a lookup is already resolving', async () => {
    let resolveLookup: (v: { data: { id: number; name: string } }) => void = () => {};
    mockFindByBarcode.mockImplementation(() => new Promise((resolve) => { resolveLookup = resolve; }));
    await render(<BarcodeScannerScreen />);

    await act(async () => {
      capturedOnScanned?.({ data: '111', type: 'ean13' });
      capturedOnScanned?.({ data: '111', type: 'ean13' });
    });

    resolveLookup({ data: { id: 1, name: 'X' } });
    await waitFor(() => expect(mockFindByBarcode).toHaveBeenCalledTimes(1));
  });

  it('allows manual barcode entry and looks it up', async () => {
    mockFindByBarcode.mockResolvedValue({ data: { id: 9, name: 'Fideos', brand: null } });
    const { getByText, getByRole, findByLabelText, findByText } = await render(<BarcodeScannerScreen />);

    await fireEvent.press(getByText('Ingresar manualmente'));
    const input = await findByLabelText('Código de barras');
    await fireEvent.changeText(input, '123456789');
    await fireEvent.press(getByRole('button', { name: 'Buscar' }));

    expect(mockFindByBarcode).toHaveBeenCalledWith('123456789');
    expect(await findByText('Fideos')).toBeTruthy();
  });
});
