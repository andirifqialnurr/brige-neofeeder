import { copyFileSync, existsSync, readFileSync } from 'node:fs';
import { spawn } from 'node:child_process';

function run(name, command, commandArgs, options = {}) {
  console.log(`[setup] ${name}`);

  const child = spawn(command, commandArgs, {
    cwd: options.cwd ?? process.cwd(),
    shell: false,
    stdio: 'inherit',
  });

  return new Promise((resolve, reject) => {
    child.on('exit', (code) => {
      if (code === 0) {
        resolve();
        return;
      }

      reject(new Error(`${name} failed with exit code ${code}`));
    });
  });
}

if (!existsSync('backend/.env')) {
  copyFileSync('backend/.env.example', 'backend/.env');
  console.log('[setup] created backend/.env from backend/.env.example');
}

await run('install backend dependencies', 'composer', ['install'], { cwd: 'backend' });
await run('install frontend dependencies with Bun', 'bun', ['install'], { cwd: 'frontend' });
await run('start MySQL and Redis', 'docker', ['compose', '-f', 'backend/docker-compose.yml', 'up', '-d', 'mysql', 'redis']);

const envContent = readFileSync('backend/.env', 'utf8');
const appKeyMatch = envContent.match(/^APP_KEY=(.*)$/m);

if (appKeyMatch === null || appKeyMatch[1].trim() === '') {
  await run('generate Laravel app key', 'php', ['artisan', 'key:generate'], { cwd: 'backend' });
} else {
  console.log('[setup] APP_KEY already exists');
}

await run('run migrations and seed admin user', 'php', ['artisan', 'migrate', '--seed'], { cwd: 'backend' });

console.log('[setup] local environment is ready');
console.log('[setup] run: bun run dev');
