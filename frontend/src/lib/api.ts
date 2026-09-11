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

export function clearAuthSession() {
  localStorage.removeItem(API_TOKEN_STORAGE_KEY);
  localStorage.removeItem(AUTH_USER_STORAGE_KEY);
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
  const token = getApiToken();

  try {
    if (token) {
      await fetch(`${API_BASE_URL}/auth/logout`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      });
    }
  } finally {
    clearAuthSession();
  }
}

export async function getReferenceStatus(): Promise<ReferenceStatus | null> {
  const token = getApiToken();

  if (!token) {
    return null;
  }

  const response = await fetch(`${API_BASE_URL}/references/status`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Reference status request failed: ${response.status}`);
  }

  const payload = (await response.json()) as { data: ReferenceStatus };

  return payload.data;
}
