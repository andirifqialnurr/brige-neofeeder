import { describe, expect, it } from 'vitest';
import { loginDestination, pagePaths, resolveRoute } from './router';
describe('workspace URLs', () => {
  it('resolves each page and keeps batch details under import', () => {
    for (const [page, path] of Object.entries(pagePaths))
      expect(resolveRoute(path)).toMatchObject({ screen: 'app', page });
    expect(resolveRoute('/import-batch/00000000-0000-4000-8000-000000000001')).toEqual({
      screen: 'app',
      page: 'import-batch',
      batchId: '00000000-0000-4000-8000-000000000001',
    });
  });
  it('redirects legacy validation and rejects unknown details', () => {
    expect(resolveRoute('/validation').screen).toBe('redirect');
    expect(resolveRoute('/import-batch/not-a-uuid').screen).toBe('not-found');
    expect(resolveRoute('/unknown').screen).toBe('not-found');
  });
  it('keeps login returns internal', () => {
    expect(loginDestination('?next=/template-excel')).toBe('/template-excel');
    expect(loginDestination('?next=https://example.com/dashboard')).toBe('/dashboard');
    expect(loginDestination('?next=//example.com/dashboard')).toBe('/dashboard');
    expect(loginDestination('?next=/%5Cexample.com/template-excel')).toBe('/dashboard');
    expect(loginDestination('?next=/login')).toBe('/dashboard');
  });
});
