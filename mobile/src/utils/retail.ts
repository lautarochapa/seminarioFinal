import type { SupermarketBranch, SupermarketProduct, DataOrigin } from '@/types/retail';

export function toNumber(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null;
  const parsed = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

export function formatMoney(value: number | string | null | undefined, currency = 'ARS'): string {
  const amount = toNumber(value);
  if (amount === null) return 'Sin precio';
  return `${currency} ${amount.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return 'Sin fecha';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('es-AR');
}

export function branchHasCoordinates(branch: SupermarketBranch): boolean {
  return toNumber(branch.latitude) !== null && toNumber(branch.longitude) !== null;
}

export function dataOriginForProduct(product: SupermarketProduct): DataOrigin {
  return product.current_price?.source || (product.source_name ? 'scraping' : 'demo');
}

export function originLabel(origin: DataOrigin | null | undefined): string {
  if (origin === 'manual') return 'manual';
  if (origin === 'scraping') return 'scraping';
  if (origin === 'demo') return 'demo';
  return origin || 'demo';
}

export function mapsUrl(branch: SupermarketBranch): string {
  const lat = toNumber(branch.latitude);
  const lng = toNumber(branch.longitude);
  if (lat !== null && lng !== null) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}`;
  }
  const label = [branch.address, branch.city?.name, branch.chain?.name].filter(Boolean).join(', ');
  return `https://maps.apple.com/?q=${encodeURIComponent(label || branch.name)}`;
}
