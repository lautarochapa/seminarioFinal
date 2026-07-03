// Simple in-memory handoff for the barcode scanner: the scanner screen writes the
// resolved product here and navigates back (router.back()), and the screen that
// opened it reads/clears the value on focus (useFocusEffect). This avoids losing
// form state that a route param round-trip would cause (the caller isn't remounted).

export interface PendingBarcodeScanResult {
  productId: number;
  productName: string;
  barcode: string;
}

let pending: PendingBarcodeScanResult | null = null;

export function setPendingScanResult(result: PendingBarcodeScanResult): void {
  pending = result;
}

export function consumePendingScanResult(): PendingBarcodeScanResult | null {
  const value = pending;
  pending = null;
  return value;
}
