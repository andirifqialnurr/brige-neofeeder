import http from 'node:http';

// Synthetic test service only. Bind loopback and never forward any request.
let mode = 'success';
let requests = [];
const server = http.createServer(async (req, res) => {
  res.setHeader('Content-Type', 'application/json');
  if (req.url === '/state' && req.method === 'GET') {
    res.end(JSON.stringify({ mode, requests }));
    return;
  }
  let body = '';
  for await (const chunk of req) {
    body += chunk;
    if (body.length > 65536) { res.writeHead(413).end(); return; }
  }
  let data;
  try { data = JSON.parse(body || '{}'); } catch { res.writeHead(400).end(); return; }
  if (req.url === '/scenario' && req.method === 'POST') {
    mode = data.mode;
    requests = [];
    res.end('{}');
    return;
  }
  if (req.url !== '/ws/live2.php' || req.method !== 'POST') { res.writeHead(404).end(); return; }
  requests.push({ act: data.act });
  if (data.act === 'GetToken') {
    res.end(JSON.stringify({ error_code: '0', data: { token: 'synthetic-token' } }));
    return;
  }
  if (mode === 'timeout' || mode === 'kill') {
    await new Promise((resolve) => setTimeout(resolve, mode === 'kill' ? 30000 : 2500));
  } else {
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  res.end(JSON.stringify(mode === 'rejected'
    ? { error_code: '123', error_desc: 'Synthetic rejection', data: {} }
    : { error_code: '0', data: { id_mahasiswa: '00000000-0000-4000-8000-000000000001' } }));
});
server.listen(Number(process.env.SIMULATOR_PORT || 19082), '127.0.0.1', () => console.log('Simulator ready'));
