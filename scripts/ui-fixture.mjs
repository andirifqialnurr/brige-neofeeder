// Local-only visual QA API. No database or Neo Feeder requests are made.
// Run: bun scripts/ui-fixture.mjs
// Frontend: VITE_API_BASE_URL=http://localhost:2001/api bun run --cwd frontend dev --port 1001
import { createServer } from 'node:http';

const date = '2026-09-13T08:30:00+08:00';
const user = { id: 'qa-user', name: 'Operator Demo', email: 'qa@example.test', role: 'admin', status: 'active', tenant_id: null };
const tenants = [
  { id: 'campus-a', name: 'Universitas Contoh', code: '001001', status: 'active', updated_at: date },
  { id: 'campus-b', name: 'Politeknik Demo', code: '001002', status: 'draft', updated_at: date },
];
const batches = [
  ['batch-001', 'mahasiswa_baru_2026.xlsx', 'validated', 248, 0],
  ['batch-002', 'nilai_semester_genap.xlsx', 'invalid', 182, 6],
  ['batch-003', 'kelas_kuliah_2026.xlsx', 'dry_run_ready', 64, 0],
  ['batch-004', 'riwayat_pendidikan.xlsx', 'ready', undefined, undefined],
  ['batch-005', 'biodata_mahasiswa_program_studi_teknik_informatika_angkatan_2026_revisi_final.xlsx', 'uploaded', undefined, undefined],
].map(([id, name, status, valid, invalid], index) => ({
  id, tenant_id: index % 2 ? 'campus-b' : 'campus-a', status, updated_at: date,
  summary: { original_name: name, size: 24832, valid_rows: valid, invalid_rows: invalid },
}));
const connections = [{ id: 'conn-001', tenant_id: 'campus-a', base_url: 'https://feeder.example.test/ws/live2.php',
  username: 'operator-demo', password_configured: true, status: 'draft', last_checked_at: null }];

createServer(async (request, response) => {
  response.setHeader('Access-Control-Allow-Origin', 'http://localhost:1001');
  response.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
  response.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, OPTIONS');
  response.setHeader('Content-Type', 'application/json');
  if (request.method === 'OPTIONS') { response.writeHead(204).end(); return; }
  const url = new URL(request.url, 'http://localhost:2001');
  let body = '';
  for await (const chunk of request) body += chunk;
  const input = request.headers['content-type']?.includes('application/json') && body ? JSON.parse(body) : {};
  const send = (data, status = 200) => { response.writeHead(status); response.end(JSON.stringify(data)); };
  await new Promise((resolve) => setTimeout(resolve, 180));
  if (url.pathname === '/api/auth/login') {
    send(input.email === 'qa@example.test' && input.password === 'preview-only'
      ? { access_token: 'local-fixture-only', user } : { message: 'Email atau password salah.' }, input.password === 'preview-only' ? 200 : 422);
  } else if (url.pathname === '/api/auth/me') send({ user });
  else if (url.pathname === '/api/auth/logout') send({});
  else if (url.pathname === '/api/tenants') {
    if (request.method === 'POST') { const tenant = { ...input, id: `campus-${tenants.length}`, updated_at: date }; tenants.push(tenant); send({ data: tenant }, 201); }
    else send({ data: tenants });
  } else if (url.pathname === '/api/neofeeder-connections') {
    if (request.method === 'POST') { const connection = { ...input, id: `conn-${connections.length}`, password_configured: true }; connections.push(connection); send({ data: connection }, 201); }
    else send({ data: connections });
  } else if (url.pathname.startsWith('/api/neofeeder-connections/')) {
    const connection = connections.find((item) => url.pathname.includes(item.id));
    if (url.pathname.endsWith('/test')) send({ data: { ok: false, error_desc: 'Respons contoh: koneksi belum tersedia.', token_received: false, connection } });
    else { Object.assign(connection, input); send({ data: connection }); }
  } else if (url.pathname === '/api/import-batches') send({ data: batches });
  else if (url.pathname.endsWith('/dry-run')) {
    const batch = batches.find((item) => url.pathname.includes(item.id));
    const invalid = batch?.status === 'invalid';
    send({ data: { summary: { total_rows: 1, valid_rows: invalid ? 0 : 1, invalid_rows: invalid ? 1 : 0, warning_rows: 0 },
      payload_preview: [{ staging_record_id: 'row-1', sheet_name: 'mahasiswa_biodata', row_number: 2, channel: 'mahasiswa_biodata',
        action: 'InsertBiodataMahasiswa', candidate_operation: 'insert', validation_result: {
          errors: invalid ? [{ field: 'nik', message: 'NIK wajib 16 digit.' }] : [], warnings: [], info: [],
        } }],
    } });
  } else if (url.pathname === '/api/references/status') {
    send({ data: { total_rows: 12, endpoint_count: 2, synced_endpoint_count: 1, failed_endpoint_count: 1, last_synced_at: date,
      endpoints: [{ name: 'Program studi', endpoint: 'GetProdi', total_rows: 12, status: 'synced', last_synced_at: date },
        { name: 'Agama', endpoint: 'GetAgama', total_rows: 0, status: 'empty', last_synced_at: null }] } });
  } else if (url.pathname === '/api/references/sync') send({ data: { queued_endpoint_count: 2 } }, 202);
  else send({ message: 'Endpoint ini tidak disimulasikan pada fixture UI.' }, 404);
}).listen(2001, '127.0.0.1', () => console.log('UI fixture only: http://localhost:2001; login qa@example.test / preview-only'));
