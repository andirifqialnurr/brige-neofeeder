import { describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { DatabaseSchemaPanel } from './database-schema-panel';

describe('database schema panel', () => {
  it('renders discovered tables and safe sample values', () => {
    const html = renderToStaticMarkup(
      <DatabaseSchemaPanel
        source={{
          id: 'source-1',
          tenant_id: 'tenant-1',
          type: 'database',
          name: 'siakad',
          headers: [],
          sheet_name: null,
          row_count: 0,
          schema_discovery_status: 'ready',
        }}
        catalog={{
          source: {
            id: 'source-1',
            tenant_id: 'tenant-1',
            type: 'database',
            name: 'siakad',
            schema_discovery_status: 'ready',
            schema_discovery_started_at: null,
            schema_discovered_at: '2026-09-16T00:00:00Z',
            schema_discovery_error: null,
          },
          tables: [
            {
              id: 'table-1',
              table_name: 'mahasiswa',
              table_type: 'BASE TABLE',
              estimated_rows: 12,
              primary_key_columns: ['id'],
              candidate_key_columns: [],
              columns: [
                {
                  id: 'column-1',
                  name: 'nama_mahasiswa',
                  ordinal_position: 1,
                  data_type: 'varchar',
                  column_type: 'varchar(120)',
                  is_nullable: false,
                  is_primary_key: false,
                  is_unique_key: false,
                  is_candidate_primary_key: false,
                  is_foreign_key: false,
                  is_candidate_relation: false,
                  relation_confidence: null,
                  referenced_table: null,
                  referenced_column: null,
                  sample_values: ['[masked]'],
                },
              ],
            },
          ],
        }}
        onDiscover={vi.fn()}
      />,
    );

    expect(html).toContain('Schema siap');
    expect(html).toContain('mahasiswa');
    expect(html).toContain('[masked]');
    expect(html).not.toContain('connection_config');
  });
});
