import { ENV } from '@/config/env';
import type { ProductImage } from '@/types/product';

export function productImageUrl(images?: ProductImage[]): string | null {
  if (!Array.isArray(images)) return null;
  const candidates = images.filter((image) => image && (!image.status || image.status === 'active'));
  const ordered = [...candidates.filter((image) => image.is_primary), ...candidates.filter((image) => !image.is_primary)];
  for (const image of ordered) {
    if (typeof image.image_url !== 'string' || !image.image_url.trim()) continue;
    try {
      const url = new URL(image.image_url.trim(), ENV.API_URL);
      if (url.protocol !== 'https:' && !(__DEV__ && url.protocol === 'http:')) continue;
      if (!url.hostname || url.username || url.password) continue;
      return url.href;
    } catch { /* Try the next image when an imported URL is invalid. */ }
  }
  return null;
}
