import { readFileSync, writeFileSync } from 'node:fs';
const source = readFileSync(new URL('./Caddyfile', import.meta.url), 'utf8');
const result = source.replaceAll('/shepherd', '').replace('root * /var/www/html', 'root * /var/www/html/shepherd');
writeFileSync(new URL('./Caddyfile.root.test', import.meta.url), result);
