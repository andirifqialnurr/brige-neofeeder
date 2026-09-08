import { spawn } from 'node:child_process';

const child = spawn('docker', ['compose', '-f', 'backend/docker-compose.yml', 'down'], {
  cwd: process.cwd(),
  shell: false,
  stdio: 'inherit',
});

child.on('exit', (code) => {
  process.exit(code ?? 1);
});
