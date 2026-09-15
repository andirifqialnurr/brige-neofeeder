import { spawn } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import net from 'node:net';

const root = fileURLToPath(new URL('../', import.meta.url));
const name = `bridge_integration_${randomUUID().replaceAll('-', '')}`;
const port = await new Promise((resolve, reject) => {
  const server = net.createServer();
  server.on('error', reject);
  server.listen(0, '127.0.0.1', () => { const p = server.address().port; server.close(() => resolve(p)); });
});
const env = {
  ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false',
  APP_KEY: `base64:${Buffer.alloc(32, 'a').toString('base64')}`,
  APP_CONFIG_CACHE: path.join(root, 'backend/storage/framework/cache/integration-config-missing.php'),
  DB_CONNECTION: 'mysql', DB_URL: '', DB_DATABASE: name,
  DB_HOST: process.env.INTEGRATION_MYSQL_HOST || '127.0.0.1',
  DB_PORT: process.env.INTEGRATION_MYSQL_PORT || '3306',
  DB_USERNAME: process.env.INTEGRATION_MYSQL_USER || 'root',
  DB_PASSWORD: process.env.INTEGRATION_MYSQL_PASSWORD || '',
  REDIS_URL: '', REDIS_HOST: process.env.INTEGRATION_REDIS_HOST || '127.0.0.1',
  REDIS_PORT: process.env.INTEGRATION_REDIS_PORT || '6379', REDIS_PASSWORD: '',
  REDIS_DB: '0', REDIS_CACHE_DB: '0', REDIS_PREFIX: `${name}:`,
  CACHE_STORE: 'redis', CACHE_PREFIX: name, QUEUE_CONNECTION: 'redis', REDIS_QUEUE: 'default',
  SIMULATOR_PORT: String(port),
};
const children = new Set();
function start(command, args) {
  const child = spawn(command, args, { cwd: path.join(root, 'backend'), env, stdio: 'inherit', shell: false });
  children.add(child);
  child.on('exit', () => children.delete(child));
  return child;
}
function run(command, args) {
  return new Promise((resolve, reject) => {
    const child = start(command, args);
    child.on('error', reject);
    child.on('exit', code => code === 0 ? resolve() : reject(new Error(`${command} exited ${code}`)));
  });
}
let provisioned = false;
try {
  await run('php', ['tests/Support/integration-database.php', 'create']);
  provisioned = true;
  await run('php', ['artisan', 'migrate', '--force']);
  const simulator = start(process.execPath, [path.join(root, 'scripts/neofeeder-simulator.mjs')]);
  simulator.on('error', err => { console.error(err.message); });
  let ready = false;
  for (let i = 0; i < 50; i++) {
    try { if ((await fetch(`http://127.0.0.1:${port}/state`)).ok) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  if (!ready) throw new Error('Simulator failed to start');
  await run('php', ['vendor/phpunit/phpunit/phpunit', '-c', 'phpunit.integration.xml']);
} catch (err) {
  console.error(err.message);
  process.exitCode = 1;
} finally {
  for (const child of children) child.kill();
  if (provisioned) {
    try { await run('php', ['tests/Support/integration-database.php', 'drop']); }
    catch (err) { console.error(err.message); process.exitCode = 1; }
  }
}
