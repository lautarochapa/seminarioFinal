import type { StockItem } from '@/types/stock';

export interface StockAlertMessage {
  key: string;
  kind: 'low_stock' | 'expiring' | 'expired';
  text: string;
}

function productName(item: StockItem): string {
  return item.product?.name ?? `Producto #${item.product_id}`;
}

function daysUntil(dateStr: string): number {
  const target = new Date(`${dateStr}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.round((target.getTime() - today.getTime()) / 86_400_000);
}

/**
 * Convierte los items devueltos por /stock/low-stock y /stock/expiring en
 * mensajes cortos y legibles para mostrar al usuario. No hace llamadas de red.
 */
export function buildStockAlertMessages(
  lowStock: StockItem[],
  expiring: StockItem[],
): StockAlertMessage[] {
  const messages: StockAlertMessage[] = [];

  for (const item of lowStock) {
    messages.push({
      key: `low-${item.id}`,
      kind: 'low_stock',
      text: `${productName(item)} está por debajo de tu stock mínimo.`,
    });
  }

  for (const item of expiring) {
    if (!item.expiration_date) continue;
    const days = daysUntil(item.expiration_date);
    let text: string;
    let kind: StockAlertMessage['kind'] = 'expiring';
    if (days < 0) {
      kind = 'expired';
      text = `${productName(item)} está vencido.`;
    } else if (days === 0) {
      text = `${productName(item)} vence hoy.`;
    } else if (days === 1) {
      text = `${productName(item)} vence mañana.`;
    } else {
      text = `${productName(item)} vence en ${days} días.`;
    }
    messages.push({ key: `exp-${item.id}`, kind, text });
  }

  return messages;
}
