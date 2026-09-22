import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { StockAlertsBanner } from '../src/components/StockAlertsBanner';
import type { StockItem } from '../src/types/stock';

const mockExpiring = jest.fn();
jest.mock('../src/api/endpoints', () => ({ stockApi: { lowStock: () => Promise.resolve({ data: [] }), expiring: () => mockExpiring() } }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
it('removes the old expiration alert when the stock list refreshes after deletion', async () => {
  const item = { id: 40, product: { name: 'Avena' }, expiration_date: '2026-09-23' } as StockItem;
  mockExpiring.mockResolvedValueOnce({ data: [item] }).mockResolvedValue({ data: [] });
  const screen = await render(<StockAlertsBanner groupId={7} stockItems={[item]} />);
  await waitFor(() => expect(screen.getByText(/Avena/)).toBeTruthy());
  await screen.rerender(<StockAlertsBanner groupId={7} stockItems={[]} />);
  await waitFor(() => expect(screen.queryByText(/Avena/)).toBeNull());
  expect(mockExpiring).toHaveBeenCalledTimes(2);
});
