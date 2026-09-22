export interface AndroidRelease {
  platform: 'android';
  version: string;
  build: number;
  download_page_url: string;
}

export function availableAndroidUpdate(data: unknown, installedBuild: string | null): AndroidRelease | null {
  if (!installedBuild || !/^\d+$/.test(installedBuild)) return null;
  const current = Number(installedBuild);
  if (!Number.isSafeInteger(current) || current < 1) return null;
  if (!data || typeof data !== 'object') return null;
  const release = data as Partial<AndroidRelease>;
  if (release.platform !== 'android'
    || typeof release.build !== 'number' || !Number.isSafeInteger(release.build) || release.build <= current
    || typeof release.version !== 'string' || !/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(release.version)
    || typeof release.download_page_url !== 'string') return null;
  try {
    const url = new URL(release.download_page_url);
    if (url.protocol !== 'https:' || !url.hostname || url.username || url.password) return null;
  } catch {
    return null;
  }
  return release as AndroidRelease;
}
