// Run with Node and sharp available (NODE_PATH may point to the local tooling bundle).
const fs = require('node:fs/promises');
const path = require('node:path');
const sharp = require('sharp');

async function main() {
  const root = path.resolve(__dirname, '..');
  const assets = path.join(root, 'mobile/assets');
  const mark = await fs.readFile(path.join(assets, 'icon-mark.svg'));
  const background = '#04AC85';
  const icon = await sharp(mark).flatten({ background }).png().toBuffer();
  await fs.writeFile(path.join(assets, 'icon.png'), icon);
  for (const name of ['android-icon-foreground.png', 'android-icon-monochrome.png', 'splash-icon.png']) {
    await sharp(mark).png().toFile(path.join(assets, name));
  }
  await sharp({ create: { width: 1024, height: 1024, channels: 3, background } }).png().toFile(path.join(assets, 'android-icon-background.png'));
  await sharp(icon).resize(64, 64).png().toFile(path.join(assets, 'favicon.png'));
  const uri = `data:image/png;base64,${icon.toString('base64')}`;
  const mono = `data:image/png;base64,${(await sharp(mark).png().toBuffer()).toString('base64')}`;
  const preview = `<svg xmlns="http://www.w3.org/2000/svg" width="1040" height="460">
    <defs><clipPath id="round"><rect x="40" y="70" width="280" height="280" rx="58"/></clipPath><clipPath id="circle"><circle cx="500" cy="210" r="140"/></clipPath></defs>
    <rect width="1040" height="460" fill="#F4F6F5"/>
    <image href="${uri}" x="40" y="70" width="280" height="280" clip-path="url(#round)"/>
    <image href="${uri}" x="360" y="70" width="280" height="280" clip-path="url(#circle)"/>
    <circle cx="820" cy="210" r="140" fill="#24252A"/><image href="${mono}" x="680" y="70" width="280" height="280"/>
    <image href="${uri}" x="320" y="390" width="48" height="48"/><image href="${uri}" x="400" y="398" width="32" height="32"/>
    <g fill="#24252A" font-family="sans-serif" font-size="19"><text x="40" y="40">CocinaComidaControl</text><text x="50" y="380">Estándar</text><text x="380" y="380">Adaptativo</text><text x="710" y="380">Monocromático</text><text x="40" y="421">Tamaño de launcher</text></g>
  </svg>`;
  await fs.mkdir(path.join(root, '.runtime'), { recursive: true });
  await sharp(Buffer.from(preview)).png().toFile(path.join(root, '.runtime/android108-icon-preview.png'));
  console.log('Generated 6 PNG assets and Android mask preview.');
}
main().catch((error) => { console.error(error); process.exitCode = 1; });
