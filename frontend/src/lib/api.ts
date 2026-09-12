const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:2000/api';
const API_TOKEN_STORAGE_KEY = 'bridge-neofeeder-api-token';
const AUTH_USER_STORAGE_KEY = 'bridge-neofeeder-auth-user';

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

export async function getReferenceStatus(): Promise<ReferenceStatus | null> {
  const headers = getAuthHeaders();

  if (!headers) {
    return null;
  }

  const response = await fetch(`${API_BASE_URL}/references/status`, {
    headers,
  });

  if (!response.ok) {
    throw new Error(`Reference status request failed: ${response.status}`);
  }

  const payload = (await response.json()) as { data: ReferenceStatus };

  return payload.data;
}
