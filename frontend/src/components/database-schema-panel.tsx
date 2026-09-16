import { useEffect, useState } from 'react';
import { Database, RefreshCcw, Table2 } from 'lucide-react';
import {
  AppButton,
  DataTable,
  EmptyState,
  SectionHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';
import type { SchemaDiscoveryStatus, SourceSchemaCatalog, SourceSchemaTable } from '@/lib/api';
import type { SourceFile } from '@/lib/mapping';

const statusLabels: Record<SchemaDiscoveryStatus, string> = {
  idle: 'Belum dipelajari',
  queued: 'Menunggu worker',
  discovering: 'Sedang dipelajari',
  pending: 'Menunggu percobaan ulang',
  ready: 'Schema siap',
  failed: 'Discovery gagal',
};

const statusTones: Record<
  SchemaDiscoveryStatus,
  'neutral' | 'info' | 'success' | 'warning' | 'destructive'
> = {
  idle: 'neutral',
  queued: 'info',
  discovering: 'info',
  pending: 'warning',
  ready: 'success',
  failed: 'destructive',
};

function tableKeys(table: SourceSchemaTable): string {
  const keys = [...table.primary_key_columns, ...table.candidate_key_columns].filter(
    (key, index, values) => values.indexOf(key) === index,
  );

  return keys.length ? keys.join(', ') : '—';
}

export function DatabaseSchemaPanel({
  source,
  catalog,
  busy = false,
  error = '',
  onDiscover,
  onSelectTable,
  onSnapshot,
}: {
  source: SourceFile;
  catalog: SourceSchemaCatalog | null;
  busy?: boolean;
  error?: string;
  onDiscover: () => void;
  onSelectTable?: (table: SourceSchemaTable) => void;
  onSnapshot?: (table: SourceSchemaTable) => void;
}) {
  const status = source.schema_discovery_status ?? 'idle';
  const [selectedTableId, setSelectedTableId] = useState(catalog?.tables[0]?.id ?? '');
  const selectedTable = catalog?.tables.find((table) => table.id === selectedTableId);
  const firstTableId = catalog?.tables[0]?.id ?? '';

  useEffect(() => {
    setSelectedTableId(firstTableId);
  }, [catalog?.source.id, catalog?.source.schema_discovered_at, firstTableId]);

  const actionLabel =
    status === 'ready'
      ? 'Perbarui schema'
      : status === 'failed' || status === 'pending'
        ? 'Coba lagi'
        : 'Pelajari schema';
  const actionBusy = busy || status === 'queued' || status === 'discovering';

  return (
    <WorkspacePanel>
      <SectionHeader
        title="Schema sumber"
        action={
          <AppButton
            variant="secondary"
            icon={RefreshCcw}
            disabled={actionBusy}
            onClick={onDiscover}
          >
            {busy ? 'Memulai...' : actionLabel}
          </AppButton>
        }
      />
      <div className="schema-status-line">
        <StatusBadge tone={statusTones[status]}>{statusLabels[status]}</StatusBadge>
        <span className="muted">
          {catalog ? `${catalog.tables.length} tabel ditemukan` : 'Koneksi belum dipelajari'}
        </span>
      </div>
      {error && <p className="error-text">{error}</p>}
      {source.schema_discovery_error && (
        <p className="error-text">{source.schema_discovery_error}</p>
      )}
      {catalog?.tables.length ? (
        <div className="schema-browser">
          <DataTable
            columns={['Tabel', 'Tipe', 'Baris', 'Kunci']}
            rows={catalog.tables.map((table) => [
              <button
                type="button"
                className={`schema-table-button${table.id === selectedTableId ? ' is-selected' : ''}`}
                aria-pressed={table.id === selectedTableId}
                onClick={() => {
                  setSelectedTableId(table.id);
                  onSelectTable?.(table);
                }}
              >
                <Table2 size={16} aria-hidden="true" />
                <span>{table.table_name}</span>
              </button>,
              table.table_type,
              table.estimated_rows === null ? '—' : table.estimated_rows.toLocaleString('id-ID'),
              tableKeys(table),
            ])}
          />
          {selectedTable ? (
            <div className="schema-detail">
              <div className="schema-detail-heading">
                <div>
                  <strong>{selectedTable.table_name}</strong>
                  <span className="muted">{selectedTable.columns.length} kolom</span>
                </div>
                {(onSelectTable || onSnapshot) && (
                  <AppButton
                    variant="ghost"
                    icon={Database}
                    onClick={() => {
                      if (onSnapshot) onSnapshot(selectedTable);
                      else onSelectTable?.(selectedTable);
                    }}
                  >
                    {onSnapshot ? 'Buat snapshot' : 'Gunakan tabel'}
                  </AppButton>
                )}
              </div>
              <DataTable
                columns={['Kolom', 'Tipe', 'Null', 'Relasi', 'Sample']}
                rows={selectedTable.columns.map((column) => [
                  <span className="schema-column-name">
                    {column.name}
                    {(column.is_primary_key || column.is_candidate_primary_key) && (
                      <small>{column.is_primary_key ? 'PK' : 'Candidate key'}</small>
                    )}
                  </span>,
                  column.column_type ?? column.data_type,
                  column.is_nullable ? 'Ya' : 'Tidak',
                  column.is_foreign_key
                    ? `${column.referenced_table ?? 'FK'}.${column.referenced_column ?? ''}`
                    : column.is_candidate_relation
                      ? 'Kandidat'
                      : '—',
                  <span className="schema-sample">
                    {column.sample_values.length ? column.sample_values.join(' · ') : '—'}
                  </span>,
                ])}
                emptyState={<EmptyState icon={Table2} title="Tidak ada kolom" />}
              />
            </div>
          ) : null}
        </div>
      ) : status === 'ready' ? (
        <EmptyState icon={Table2} title="Tidak ada tabel yang ditemukan" />
      ) : null}
    </WorkspacePanel>
  );
}
