import { buildStockAlertMessages } from '../src/utils/stockAlerts';
import type { StockItem } from '../src/types/stock';

function item(overrides: Partial<StockItem>): StockItem {
  return {
    id: 1,
    family_group_id: 1,
    product_id: 1,
    stock_location_id: null,
    quantity: 1,
    unit_id: 1,
    expiration_date: null,
    purchase_price: null,
    status: 'active',
    product: { id: 1, name: 'Producto' },
    location: null,
    unit: null,
    created_at: '',
    updated_at: '',
    deleted_at: null,
    ...overrides,
  };
}

function dateInDays(days: number): string {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

describe('buildStockAlertMessages', () => {
  it('describes low-stock items', () => {
    const messages = buildStockAlertMessages(
      [item({ id: 5, product: { id: 9, name: 'Arroz Largo Fino' } })],
      [],
    );
    expect(messages).toEqual([
      { key: 'low-5', kind: 'low_stock', text: 'Arroz Largo Fino está por debajo de tu stock mínimo.' },
    ]);
  });

  it('describes expiring items with relative days', () => {
    const messages = buildStockAlertMessages([], [
      item({ id: 1, product: { id: 1, name: 'Huevos x6' }, expiration_date: dateInDays(2) }),
      item({ id: 2, product: { id: 2, name: 'Leche' }, expiration_date: dateInDays(1) }),
      item({ id: 3, product: { id: 3, name: 'Yogur' }, expiration_date: dateInDays(0) }),
      item({ id: 4, product: { id: 4, name: 'Pan' }, expiration_date: dateInDays(-1) }),
    ]);
    expect(messages.map((m) => m.text)).toEqual([
      'Huevos x6 vence en 2 días.',
      'Leche vence mañana.',
      'Yogur vence hoy.',
      'Pan está vencido.',
    ]);
    expect(messages[3].kind).toBe('expired');
  });

  it('skips expiring items without a date', () => {
    expect(buildStockAlertMessages([], [item({ expiration_date: null })])).toEqual([]);
  });
});
