import { availableAndroidUpdate } from '../src/utils/appVersion';

const release = { platform: 'android', version: '1.0.6', build: 7, download_page_url: 'https://cocinacomidacontrol.com.ar/#descarga-app' };

it('offers a higher build, even when the visible version is unchanged', () => {
  expect(availableAndroidUpdate(release, '6')).toEqual(release);
  expect(availableAndroidUpdate({ ...release, version: '1.0.5' }, '6')?.build).toBe(7);
  expect(availableAndroidUpdate({ ...release, version: '1.0.10', build: 11 }, '9')?.version).toBe('1.0.10');
});

it.each(['7', '8', '11'])('does not offer a downgrade for installed build %s', (installed) => {
  expect(availableAndroidUpdate(release, installed)).toBeNull();
});

it.each([null, '', '0', '-1', '1.2', 'unknown', '9007199254740992'])('ignores unknown native build %s', (installed) => {
  expect(availableAndroidUpdate(release, installed)).toBeNull();
});

it.each([null, {}, { ...release, platform: 'ios' }, { ...release, build: '7' }, { ...release, build: 7.5 }, { ...release, build: Infinity }, { ...release, version: '' }, { ...release, version: null }])('ignores malformed metadata %#', (data) => {
  expect(availableAndroidUpdate(data, '6')).toBeNull();
});

it.each(['http://example.test', 'javascript:alert(1)', 'file:///test', '/download', 'https://user:password@example.test', '', 'not a url'])('rejects unsafe download page %s', (url) => {
  expect(availableAndroidUpdate({ ...release, download_page_url: url }, '6')).toBeNull();
});
