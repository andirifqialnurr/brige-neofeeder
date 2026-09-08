import { spawn } from 'node:child_process';

const args = new Set(process.argv.slice(2));
const useDocker = !args.has('--no-docker');
const withWorker = args.has('--with-worker');
const withScheduler = args.has('--with-scheduler');

const children = [];

function run(name, command, commandArgs, options = {}) {
  const child = spawn(command, commandArgs, {
    cwd: options.cwd ?? process.cwd(),
    env: {
      ...process.env,
      FORCE_COLOR: '1',
    },
    shell: false,
    stdio: ['inherit', 'pipe', 'pipe'],
  });

  child.stdout.on('data', (chunk) => {
    process.stdout.write(`[${name}] ${chunk}`);
  });

  child.stderr.on('data', (chunk) => {
    process.stderr.write(`[${name}] ${chunk}`);
  });

  child.on('exit', (code, signal) => {
    if (shuttingDown) {
      return;
    }

    console.error(`[${name}] stopped with ${signal ?? `exit code ${code}`}`);
    shutdown(code === null ? 1 : code);
  });

  children.push(child);

  return child;
}

function runDetached(name, command, commandArgs) {
  const result = spawn(command, commandArgs, {
    cwd: process.cwd(),
    shell: false,
    stdio: 'inherit',
  });

  return new Promise((resolve, reject) => {
    result.on('exit', (code) => {
      if (code === 0) {
        resolve();
        return;
      }

      reject(new Error(`${name} failed with exit code ${code}`));
    });
  });
}

let shuttingDown = false;

function shutdown(code = 0) {
  shuttingDown = true;

  for (const child of children) {
    if (!child.killed) {
      child.kill();
    }
  }

  process.exit(code);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

if (useDocker) {
  console.log('[dev] starting MySQL and Redis via Docker Compose...');
  await runDetached('docker', 'docker', ['compose', '-f', 'backend/docker-compose.yml', 'up', '-d', 'mysql', 'redis']);
}

console.log('[dev] backend: http://127.0.0.1:8000/api/health');
console.log('[dev] frontend: http://127.0.0.1:5173');
console.log('[dev] press Ctrl+C to stop Laravel/Vite processes');

run('api', 'php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], { cwd: 'backend' });

if (withWorker) {
  run('queue', 'php', ['artisan', 'queue:work', '--tries=1'], { cwd: 'backend' });
}

if (withScheduler) {
  run('scheduler', 'php', ['artisan', 'schedule:work'], { cwd: 'backend' });
}

run('web', 'bun', ['run', 'dev', '--host', '127.0.0.1', '--port', '5173'], { cwd: 'frontend' });
