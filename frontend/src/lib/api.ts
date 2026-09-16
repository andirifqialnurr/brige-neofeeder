import type { MappingWorkspaceData, SourceFile } from './mapping';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:2000/api';
const API_TOKEN_STORAGE_KEY = 'bridge-neofeeder-api-token';
const AUTH_USER_STORAGE_KEY = 'bridge-neofeeder-auth-user';

export async function requestApi<T>(path: string, options: RequestInit = {}): Promise<T> {
  const authHeaders = getAuthHeaders();
  if (!authHeaders) throw new Error('Sesi login belum tersedia.');
  const response = await fetch(`${API_BASE_URL}/${path}`, {
    ...options,
    cache: 'no-store',
    headers: { ...authHeaders, ...options.headers },
  });
  if (!response.ok) throw new Error(await parseApiError(response));
  return response.json() as Promise<T>;
}

export type OperationalSnapshot = {
  status: 'ok' | 'degraded';
  checked_at: string;
  checks: {
    key: string;
    label: string;
    status: 'ok' | 'unavailable' | 'stale' | 'missing';
    last_seen_at?: string | null;
    pending_jobs?: number;
  }[];
  failed_jobs_count: number | null;
  recent_failed_jobs: { uuid: string; connection: string; queue: string; failed_at: string }[];
};

export type StatisticGroup = { status: string; total: number };
export type DashboardStatistics = {
  tenant_id: string | null;
  generated_at: string;
  timezone: string;
  days: number;
  totals: {
    campuses: number;
    active_campuses: number;
    active_operators: number;
    connections: number;
    active_connections: number;
    reference_rows: number;
    reference_endpoints_covered: number;
    reference_endpoints_expected: number;
    channels: number;
    batches: number;
    staging_rows: number;
    warning_rows: number;
    sync_success_rate: number | null;
  };
  batch_statuses: StatisticGroup[];
  row_statuses: StatisticGroup[];
  sync_statuses: StatisticGroup[];
  channels: { channel: string; total: number }[];
  activity: { date: string; total: number }[];
  automation_available: boolean;
};
export async function getDashboardStatistics(
  days: number,
  tenantId: string,
  signal?: AbortSignal,
): Promise<DashboardStatistics> {
  const headers = getAuthHeaders();
  if (!headers) throw new Error('Sesi login belum tersedia.');
  const query = new URLSearchParams({
    days: String(days),
    ...(tenantId ? { tenant_id: tenantId } : {}),
  });
  const response = await fetch(`${API_BASE_URL}/dashboard/statistics?${query}`, {
    headers,
    signal,
  });
  if (!response.ok) throw new Error(await parseApiError(response));
  return ((await response.json()) as { data: DashboardStatistics }).data;
}

export type AuthUser = {
  id: string;
  tenant_id: string | null;
  name: string;
  email: string;
  role: string;
  status: string;
};

export type TenantStatus = 'active' | 'inactive' | 'draft';

export type Tenant = {
  id: string;
  name: string;
  code: string;
  status: TenantStatus;
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
};

export type NeoFeederConnectionStatus = 'draft' | 'active' | 'inactive' | 'error';

export type NeoFeederConnection = {
  timeout_ms?: number;
  id: string;
  tenant_id: string;
  base_url: string;
  username: string | null;
  password_configured: boolean;
  status: NeoFeederConnectionStatus;
  last_token_refreshed_at: string | null;
  last_checked_at: string | null;
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
};

export type NeoFeederConnectionTestResult = {
  ok: boolean;
  error_code: string | null;
  error_desc: string | null;
  token_received: boolean;
  connection: NeoFeederConnection;
};

export type SchemaDiscoveryStatus =
  'idle' | 'queued' | 'discovering' | 'pending' | 'ready' | 'failed';

export type SnapshotStatus = 'idle' | 'queued' | 'refreshing' | 'pending' | 'ready' | 'failed';

export type SourceSchemaStatus = {
  id: string;
  tenant_id: string;
  type: 'file' | 'database';
  name: string;
  schema_discovery_status: SchemaDiscoveryStatus;
  schema_discovery_started_at: string | null;
  schema_discovered_at: string | null;
  schema_discovery_error: string | null;
  snapshot_status: SnapshotStatus;
  snapshot_started_at: string | null;
  snapshot_refreshed_at: string | null;
  snapshot_error: string | null;
};

export type SourceSchemaColumn = {
  id: string;
  name: string;
  ordinal_position: number;
  data_type: string;
  column_type: string | null;
  is_nullable: boolean;
  is_primary_key: boolean;
  is_unique_key: boolean;
  is_candidate_primary_key: boolean;
  is_foreign_key: boolean;
  is_candidate_relation: boolean;
  relation_confidence: 'foreign_key' | 'name_pattern' | null;
  referenced_table: string | null;
  referenced_column: string | null;
  sample_values: string[];
};

export type IncrementalReadinessStatus = 'ready' | 'needs_review' | 'not_ready';
export type IncrementalTimestampCandidate = {
  name: string;
  data_type: string;
  confidence: 'high' | 'medium';
};
export type IncrementalReadiness = {
  status: IncrementalReadinessStatus;
  key_columns: string[];
  timestamp_columns: IncrementalTimestampCandidate[];
  reasons: string[];
  activation_allowed: false;
  blocking_reasons: string[];
};

export type SourceSchemaTable = {
  id: string;
  table_name: string;
  table_type: string;
  estimated_rows: number | null;
  primary_key_columns: string[];
  candidate_key_columns: string[];
  incremental_readiness?: IncrementalReadiness;
  columns: SourceSchemaColumn[];
};

export type SourceSchemaCatalog = {
  source: SourceSchemaStatus;
  tables: SourceSchemaTable[];
};

export type ReferenceEndpointStatus = {
  name: string;
  endpoint: string;
  total_rows: number;
  last_synced_at: string | null;
  status: 'synced' | 'empty';
};

export type ReferenceStatus = {
  tenant_id: string | null;
  endpoint_count: number;
  synced_endpoint_count: number;
  failed_endpoint_count: number;
  total_rows: number;
  last_synced_at: string | null;
  endpoints: ReferenceEndpointStatus[];
};

export type ReferenceSyncResult = {
  tenant_id: string;
  queued_endpoint_count: number;
  endpoints: string[];
};

export type ImportBatchStatus =
  | 'uploaded'
  | 'parsing'
  | 'ready'
  | 'validated'
  | 'invalid'
  | 'dry_run_ready'
  | 'syncing'
  | 'synced'
  | 'failed';

export type ImportBatch = {
  id: string;
  tenant_id: string;
  tenant_name: string | null;
  source_type: string;
  file_path: string;
  template_version: string;
  status: ImportBatchStatus;
  summary: {
    original_name?: string;
    size?: number;
    total_rows?: number;
    valid_rows?: number;
    invalid_rows?: number;
    warning_rows?: number;
    missing_sheets?: string[];
    [key: string]: unknown;
  };
  staging_records_count: number;
  created_at: string;
  updated_at: string;
};

export type DryRunIssue = {
  field: string | null;
  rule?: string;
  message?: string;
};

export type DryRunPayloadPreview = {
  staging_record_id: string;
  channel: string;
  sheet_name: string;
  row_number: number;
  status: string;
  candidate_operation: string;
  action: string | null;
  payload: Record<string, unknown> | null;
  validation_result: {
    errors?: DryRunIssue[];
    warnings?: DryRunIssue[];
    info?: DryRunIssue[];
  };
};

export type DryRunPreview = {
  import_batch_id: string;
  summary: {
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    warning_rows: number;
  };
  dependency_order: string[];
  payload_preview: DryRunPayloadPreview[];
  missing_references: Array<{
    staging_record_id: string;
    channel: string;
    sheet_name: string;
    row_number: number;
    field: string | null;
    message: string | null;
  }>;
  requires_operator_approval: boolean;
  approved: boolean;
};

function getApiToken() {
  return import.meta.env.VITE_API_TOKEN ?? localStorage.getItem(API_TOKEN_STORAGE_KEY);
}

export function hasStoredApiToken() {
  return Boolean(getApiToken());
}

export function getStoredAuthUser(): AuthUser | null {
  const rawUser = localStorage.getItem(AUTH_USER_STORAGE_KEY);

  if (!rawUser) {
    return null;
  }

  try {
    return JSON.parse(rawUser) as AuthUser;
  } catch {
    localStorage.removeItem(AUTH_USER_STORAGE_KEY);
    return null;
  }
}

function storeAuthSession(token: string, user: AuthUser) {
  localStorage.setItem(API_TOKEN_STORAGE_KEY, token);
  localStorage.setItem(AUTH_USER_STORAGE_KEY, JSON.stringify(user));
}

function storeAuthUser(user: AuthUser) {
  localStorage.setItem(AUTH_USER_STORAGE_KEY, JSON.stringify(user));
}

export function clearAuthSession() {
  localStorage.removeItem(API_TOKEN_STORAGE_KEY);
  localStorage.removeItem(AUTH_USER_STORAGE_KEY);
}

function getAuthHeaders() {
  const token = getApiToken();

  if (!token) {
    return null;
  }

  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
  };
}

function getJsonHeaders() {
  const headers = getAuthHeaders();

  if (!headers) {
    return null;
  }

  return {
    ...headers,
    'Content-Type': 'application/json',
  };
}

async function parseApiError(response: Response): Promise<string> {
  try {
    const payload = (await response.json()) as { message?: string };
    return payload.message ?? `Request gagal: ${response.status}`;
  } catch {
    return `Request gagal: ${response.status}`;
  }
}

export async function getHealth(): Promise<{ status: string; service: string }> {
  const response = await fetch(`${API_BASE_URL}/health`);

  if (!response.ok) {
    throw new Error(`API health check failed: ${response.status}`);
  }

  return response.json();
}

export async function login(email: string, password: string): Promise<AuthUser> {
  const response = await fetch(`${API_BASE_URL}/auth/login`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });

  const payload = (await response.json()) as {
    access_token?: string;
    message?: string;
    user?: AuthUser;
  };

  if (!response.ok || !payload.access_token || !payload.user) {
    throw new Error(payload.message ?? 'Login gagal.');
  }

  storeAuthSession(payload.access_token, payload.user);

  return payload.user;
}

export async function logout(): Promise<void> {
  const headers = getAuthHeaders();

  try {
    if (headers) {
      await fetch(`${API_BASE_URL}/auth/logout`, {
        method: 'POST',
        headers,
      });
    }
  } catch {
    // Local logout must still clear stale credentials if the API is unreachable.
  } finally {
    clearAuthSession();
  }
}

export async function getCurrentUser(): Promise<AuthUser> {
  const headers = getAuthHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/auth/me`, {
    headers,
  });

  const payload = (await response.json()) as { user?: AuthUser; message?: string };

  if (!response.ok || !payload.user) {
    clearAuthSession();
    throw new Error(payload.message ?? 'Sesi login tidak valid.');
  }

  storeAuthUser(payload.user);

  return payload.user;
}

export async function discoverSourceSchema(sourceId: string): Promise<SourceSchemaStatus> {
  const payload = await requestApi<{ data: SourceSchemaStatus }>(
    `mapping/sources/${sourceId}/discover-schema`,
    { method: 'POST' },
  );

  return payload.data;
}

export async function getSourceSchema(
  sourceId: string,
  signal?: AbortSignal,
): Promise<SourceSchemaCatalog> {
  const payload = await requestApi<{ data: SourceSchemaCatalog }>(
    `mapping/sources/${sourceId}/schema`,
    { signal },
  );

  return payload.data;
}

export async function snapshotDatabaseSource(
  sourceId: string,
  input: { table: string; columns: string[] },
): Promise<SourceFile> {
  const payload = await requestApi<{ data: SourceFile }>(`mapping/sources/${sourceId}/snapshot`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function refreshDatabaseSource(sourceId: string): Promise<SourceSchemaStatus> {
  const payload = await requestApi<{ data: SourceSchemaStatus }>(
    `mapping/sources/${sourceId}/refresh-snapshot`,
    { method: 'POST' },
  );

  return payload.data;
}

export type AutomationScheduleFrequency = 'hourly' | 'daily' | 'weekly';
export type AutomationScheduleMode = 'full';
export type AutomationScheduleStatus = 'idle' | 'queued' | 'running' | 'success' | 'failed';
export type AutomationSchedule = {
  id: string;
  tenant_id: string;
  name: string;
  frequency: AutomationScheduleFrequency;
  mode: AutomationScheduleMode;
  error_rate_threshold: number;
  alert: {
    active: boolean;
    threshold: number;
    run_count: number;
    failed_count: number;
    error_rate: number | null;
    minimum_runs: number;
    window_days: number;
    triggered_at: string | null;
  };
  is_active: boolean;
  status: AutomationScheduleStatus;
  next_run_at: string | null;
  last_started_at: string | null;
  last_completed_at: string | null;
  last_error: string | null;
  last_batch_id: string | null;
  source: {
    id: string;
    name: string;
    type: 'database';
    row_count: number;
    snapshot_status: SnapshotStatus;
    snapshot_refreshed_at: string | null;
  } | null;
  profile: { id: string; name: string; channel: string; version: number } | null;
  created_at: string;
  updated_at: string;
};

export type AutomationScheduleRunStatus = 'running' | 'success' | 'failed';
export type AutomationScheduleRun = {
  id: string;
  status: AutomationScheduleRunStatus;
  started_at: string;
  completed_at: string | null;
  duration_ms: number | null;
  last_batch_id: string | null;
  error_message: string | null;
};

export async function getMappingWorkspace(
  tenantId: string,
  signal?: AbortSignal,
): Promise<MappingWorkspaceData> {
  const payload = await requestApi<{ data: MappingWorkspaceData }>(
    `mapping/workspace?tenant_id=${encodeURIComponent(tenantId)}`,
    { signal },
  );

  return payload.data;
}

export async function getAutomationSchedules(
  tenantId: string,
  signal?: AbortSignal,
): Promise<AutomationSchedule[]> {
  const payload = await requestApi<{ data: AutomationSchedule[] }>(
    `automation/schedules?tenant_id=${encodeURIComponent(tenantId)}`,
    { signal },
  );

  return payload.data;
}

export async function createAutomationSchedule(input: {
  tenant_id: string;
  source_connection_id: string;
  mapping_profile_id: string;
  name: string;
  frequency: AutomationScheduleFrequency;
  mode?: AutomationScheduleMode;
  error_rate_threshold?: number;
}): Promise<AutomationSchedule> {
  const payload = await requestApi<{ data: AutomationSchedule }>('automation/schedules', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function updateAutomationSchedule(
  id: string,
  input: {
    frequency?: AutomationScheduleFrequency;
    mode?: AutomationScheduleMode;
    is_active?: boolean;
    name?: string;
  },
): Promise<AutomationSchedule> {
  const payload = await requestApi<{ data: AutomationSchedule }>(`automation/schedules/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function runAutomationSchedule(id: string): Promise<AutomationSchedule> {
  const payload = await requestApi<{ data: AutomationSchedule }>(`automation/schedules/${id}/run`, {
    method: 'POST',
  });

  return payload.data;
}

export async function getAutomationScheduleRuns(
  scheduleId: string,
  options: {
    page?: number;
    status?: AutomationScheduleRunStatus;
    signal?: AbortSignal;
  } = {},
): Promise<PageResult<AutomationScheduleRun>> {
  const query = new URLSearchParams();
  if (options.page && options.page > 1) query.set('page', String(options.page));
  if (options.status) query.set('status', options.status);
  const queryString = query.toString();
  const payload = await requestApi<PageResult<AutomationScheduleRun>>(
    `automation/schedules/${encodeURIComponent(scheduleId)}/runs${queryString ? `?${queryString}` : ''}`,
    { signal: options.signal },
  );

  return payload;
}

export async function getTenants(): Promise<Tenant[]> {
  const headers = getAuthHeaders();

  if (!headers) {
    return [];
  }

  const response = await fetch(`${API_BASE_URL}/tenants`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: Tenant[] };

  return payload.data;
}

export async function createTenant(input: {
  name: string;
  code: string;
  status?: TenantStatus;
}): Promise<Tenant> {
  const headers = getJsonHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/tenants`, {
    method: 'POST',
    headers,
    body: JSON.stringify(input),
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: Tenant };

  return payload.data;
}

export async function updateTenant(
  tenantId: string,
  input: {
    name?: string;
    code?: string;
    status?: TenantStatus;
  },
): Promise<Tenant> {
  const headers = getJsonHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/tenants/${tenantId}`, {
    method: 'PUT',
    headers,
    body: JSON.stringify(input),
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: Tenant };

  return payload.data;
}

export async function getNeoFeederConnections(): Promise<NeoFeederConnection[]> {
  const headers = getAuthHeaders();

  if (!headers) {
    return [];
  }

  const response = await fetch(`${API_BASE_URL}/neofeeder-connections`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: NeoFeederConnection[] };

  return payload.data;
}

export async function createNeoFeederConnection(input: {
  timeout_ms?: number;
  tenant_id: string;
  base_url: string;
  username?: string;
  password?: string;
  status?: NeoFeederConnectionStatus;
}): Promise<NeoFeederConnection> {
  const headers = getJsonHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/neofeeder-connections`, {
    method: 'POST',
    headers,
    body: JSON.stringify(input),
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: NeoFeederConnection };

  return payload.data;
}

export async function updateNeoFeederConnection(
  connectionId: string,
  input: {
    timeout_ms?: number;
    base_url?: string;
    username?: string;
    password?: string;
    clear_password?: boolean;
    status?: NeoFeederConnectionStatus;
  },
): Promise<NeoFeederConnection> {
  const headers = getJsonHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/neofeeder-connections/${connectionId}`, {
    method: 'PUT',
    headers,
    body: JSON.stringify(input),
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: NeoFeederConnection };

  return payload.data;
}

export async function testNeoFeederConnection(
  connectionId: string,
): Promise<NeoFeederConnectionTestResult> {
  const headers = getAuthHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/neofeeder-connections/${connectionId}/test`, {
    method: 'POST',
    headers,
  });

  const payload = (await response.json()) as {
    data?: NeoFeederConnectionTestResult;
    message?: string;
  };

  if (!payload.data) {
    throw new Error(payload.message ?? `Request gagal: ${response.status}`);
  }

  return payload.data;
}

function getFilenameFromDisposition(disposition: string | null) {
  if (!disposition) {
    return null;
  }

  const match = disposition.match(/filename="?([^"]+)"?/);

  return match?.[1] ?? null;
}

export async function downloadNeoFeederTemplate(): Promise<void> {
  const headers = getAuthHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/templates/neofeeder-workbook`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download =
    getFilenameFromDisposition(response.headers.get('Content-Disposition')) ??
    'bridge-neofeeder-template.xlsx';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export async function getReferenceStatus(tenantId?: string): Promise<ReferenceStatus | null> {
  const headers = getAuthHeaders();

  if (!headers) {
    return null;
  }

  const query = tenantId ? `?tenant_id=${encodeURIComponent(tenantId)}` : '';
  const response = await fetch(`${API_BASE_URL}/references/status${query}`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(`Reference status request failed: ${response.status}`);
  }

  const payload = (await response.json()) as { data: ReferenceStatus };

  return payload.data;
}

export async function syncReferences(input: {
  tenantId: string;
  endpoint?: string;
}): Promise<ReferenceSyncResult> {
  const headers = getJsonHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/references/sync`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      tenant_id: input.tenantId,
      ...(input.endpoint ? { endpoint: input.endpoint } : {}),
    }),
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: ReferenceSyncResult };

  return payload.data;
}

export async function getImportBatches(tenantId?: string): Promise<ImportBatch[]> {
  const headers = getAuthHeaders();

  if (!headers) {
    return [];
  }

  const query = tenantId ? `?tenant_id=${encodeURIComponent(tenantId)}` : '';
  const response = await fetch(`${API_BASE_URL}/import-batches${query}`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: ImportBatch[] };

  return payload.data;
}

export async function uploadImportBatch(input: {
  tenantId: string;
  file: File;
}): Promise<ImportBatch> {
  const headers = getAuthHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const formData = new FormData();
  formData.append('tenant_id', input.tenantId);
  formData.append('file', input.file);

  const response = await fetch(`${API_BASE_URL}/import-batches/upload`, {
    method: 'POST',
    headers,
    body: formData,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: ImportBatch };

  return payload.data;
}

export async function runImportBatchDryRun(importBatchId: string): Promise<DryRunPreview> {
  const headers = getAuthHeaders();

  if (!headers) {
    throw new Error('Sesi login belum tersedia.');
  }

  const response = await fetch(`${API_BASE_URL}/import-batches/${importBatchId}/dry-run`, {
    method: 'POST',
    headers,
  });

  if (!response.ok) {
    throw new Error(await parseApiError(response));
  }

  const payload = (await response.json()) as { data: DryRunPreview };

  return payload.data;
}

export type PageMeta = { current_page: number; last_page: number; per_page: number; total: number };
export type PageResult<T> = { data: T[]; meta: PageMeta };
export type BatchDetail = Omit<ImportBatch, 'file_path'> & {
  approval: {
    dry_run_hash: string | null;
    approved: boolean;
    approved_at: string | null;
    approved_by_name: string | null;
  };
  is_demo: boolean;
  sheets: { sheet_name: string; total_rows: number }[];
  dependency_order: string[];
};
export type BatchRow = {
  id: string;
  channel: string;
  sheet_name: string;
  row_number: number;
  status: string;
  errors: number;
  warnings: number;
};
export type RowDetail = DryRunPayloadPreview & {
  source_lineage?: {
    source_name: string;
    source_sheet: string | null;
    source_row: number;
    mapping_version: number;
  } | null;
  raw_row: Record<string, unknown> | null;
  normalized_row: Record<string, unknown>;
  can_reveal_sensitive: boolean;
  sensitive_revealed: boolean;
};

export type AuditEntry = {
  id: string;
  event: string;
  subject_id: string | null;
  created_at: string;
  tenant_name: string;
  actor_name: string;
  metadata: Record<string, unknown>;
};

export async function downloadApiFile(path: string, filename: string) {
  const headers = getAuthHeaders();
  if (!headers) throw new Error('Sesi login belum tersedia.');
  const response = await fetch(`${API_BASE_URL}/${path}`, { headers, cache: 'no-store' });
  if (!response.ok) throw new Error(await parseApiError(response));
  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

async function batchRequest<T>(path: string, signal?: AbortSignal): Promise<T> {
  const headers = getAuthHeaders();
  if (!headers) throw new Error('Sesi login belum tersedia.');
  const response = await fetch(`${API_BASE_URL}/import-batches${path}`, { headers, signal });
  if (!response.ok) throw new Error(await parseApiError(response));
  return response.json() as Promise<T>;
}

export function getBatchPage(page: number, search: string, signal?: AbortSignal) {
  return batchRequest<PageResult<ImportBatch>>(
    `?${new URLSearchParams({ page: String(page), per_page: '20', search })}`,
    signal,
  );
}
export async function getBatchDetail(id: string, signal?: AbortSignal) {
  return (await batchRequest<{ data: BatchDetail }>(`/${id}`, signal)).data;
}
export function getBatchRows(
  id: string,
  page: number,
  sheet: string,
  status: string,
  signal?: AbortSignal,
) {
  return batchRequest<PageResult<BatchRow>>(
    `/${id}/rows?${new URLSearchParams({ page: String(page), sheet, status })}`,
    signal,
  );
}
export async function getBatchRow(id: string, row: string, signal?: AbortSignal) {
  return (await batchRequest<{ data: RowDetail }>(`/${id}/rows/${row}`, signal)).data;
}
export async function downloadBatchReport(id: string) {
  const headers = getAuthHeaders();
  if (!headers) throw new Error('Sesi login belum tersedia.');
  const response = await fetch(`${API_BASE_URL}/import-batches/${id}/report`, { headers });
  if (!response.ok) throw new Error(await parseApiError(response));
  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = `temuan-${id}.xlsx`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export type SyncProgress = {
  import_batch_id: string;
  status: ImportBatchStatus;
  started_at: string | null;
  total_attempts: number;
  queued: number;
  syncing: number;
  retrying: number;
  success: number;
  failed: number;
  unknown: number;
  has_credentials: boolean;
  is_demo: boolean;
  records: { total: number; success: number; failed: number; unknown: number; active: number };
};
export type SyncAttemptView = {
  id: string;
  staging_record_id: string;
  sheet_name: string;
  row_number: number;
  action: string;
  status: string;
  error_code: string | null;
  error_desc: string | null;
  identity_payload: Record<string, unknown> | null;
  created_at: string;
  attempted_at: string | null;
  completed_at: string | null;
  retry_of: string | null;
  can_retry: boolean;
};
export async function getSyncProgress(id: string, signal?: AbortSignal) {
  return (await batchRequest<{ data: SyncProgress }>(`/${id}/sync-progress`, signal)).data;
}
export function getSyncAttempts(id: string, page: number, status: string, signal?: AbortSignal) {
  return batchRequest<PageResult<SyncAttemptView>>(
    `/${id}/sync-attempts?${new URLSearchParams({ page: String(page), status })}`,
    signal,
  );
}
async function syncCommand(path: string, body: Record<string, unknown> = {}) {
  const headers = getAuthHeaders();
  if (!headers) throw new Error('Sesi login belum tersedia.');
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: 'POST',
    headers: { ...headers, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!response.ok) throw new Error(await parseApiError(response));
  return response.json();
}
export function approveBatch(id: string, hash: string) {
  return syncCommand(`/import-batches/${id}/approve`, { confirmed: true, dry_run_hash: hash });
}
export function startBatchSync(id: string) {
  return syncCommand(`/import-batches/${id}/sync`);
}
export function retrySyncAttempt(id: string) {
  return syncCommand(`/sync-attempts/${id}/retry`);
}
