import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { PageHeader, Topbar } from './layout';

describe('workspace page headers', () => {
  it('keeps the topbar without a duplicate breadcrumb', () => {
    const html = renderToStaticMarkup(<Topbar action={<button>Keluar</button>} />);
    expect(html).toContain('class="topbar"');
    expect(html).toContain('Keluar');
    expect(html).not.toContain('Breadcrumb');
  });

  it('replaces the visible title with a breadcrumb and retains an accessible heading', () => {
    const html = renderToStaticMarkup(
      <PageHeader title="Kampus" action={<button>Tambah kampus</button>} />,
    );
    expect(html).toContain('aria-label="Breadcrumb"');
    expect(html).toContain('<h1 class="sr-only">Kampus</h1>');
    expect(html).toContain('aria-current="page" title="Kampus"');
    expect(html).toContain('<div class="page-actions"><button>Tambah kampus</button></div>');
  });

  it('links batch detail back to its parent without adding a second toolbar', () => {
    const html = renderToStaticMarkup(
      <PageHeader title="demo.xlsx" parents={[{ label: 'Import Batch', href: '/import-batch' }]} />,
    );
    expect(html).toContain('href="/import-batch"');
    expect(html).toContain('aria-current="page" title="demo.xlsx"');
    expect(html).not.toContain('class="page-actions"');
  });
});
