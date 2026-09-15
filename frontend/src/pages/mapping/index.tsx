import { useEffect, useRef, useState } from 'react';
import { Download, FileSpreadsheet, RefreshCcw, Save, Upload, Waypoints } from 'lucide-react';
import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  FormDialog,
  IconButton,
  LoadingState,
  PageHeader,
  Pagination,
  SectionHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';
import { Select } from '@/components/ui/select';
import { useWorkspace } from '@/hooks/workspace-context';
import { downloadApiFile, requestApi } from '@/lib/api';
import { goTo } from '@/lib/router';
import { ReferenceRule } from './reference-rule';
import { TransformRule } from './transform-rule';
import {
  matchMappingHeaders,
  type MappingField,
  type MappingPreview,
  type MappingPreviewRow,
  type MappingProfile,
  type MappingRule,
  type MappingWorkspaceData,
  type SourceFile,
} from '@/lib/mapping';

const channelLabels: Record<string, string> = {
  mahasiswa_biodata: 'Biodata mahasiswa',
  mahasiswa_riwayat_pendidikan: 'Riwayat pendidikan mahasiswa',
  mata_kuliah: 'Mata kuliah',
  kelas_kuliah: 'Kelas kuliah',
};
const jsonPost = (body: unknown): RequestInit => ({
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(body),
});

export function MappingPage() {
  const { authUser, tenants } = useWorkspace();
  const [tenant, setTenant] = useState('');
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);
  const activeTenant = tenant || authUser?.tenant_id || tenants[0]?.id || '';
  return (
    <>
      <PageHeader
        title="Mapping SIAKAD"
        action={
          <>
            {authUser?.role === 'admin' && (
              <label className="inline-field">
                <span className="sr-only">Kampus mapping</span>
                <Select
                  value={activeTenant}
                  disabled={busy}
                  onChange={(event) => setTenant(event.target.value)}
                >
                  {!tenants.length && <option value="">Belum ada kampus</option>}
                  {tenants.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </Select>
              </label>
            )}
            <IconButton
              label="Muat ulang mapping"
              icon={RefreshCcw}
              disabled={busy}
              onClick={() => setRevision((value) => value + 1)}
            />
          </>
        }
      />
      {activeTenant ? (
        <FileMappingWorkspace
          key={`${activeTenant}:${revision}`}
          tenantId={activeTenant}
          onBusy={setBusy}
        />
      ) : (
        <EmptyState icon={Waypoints} title="Buat kampus terlebih dahulu" />
      )}
    </>
  );
}

function FileMappingWorkspace({
  tenantId,
  onBusy,
}: {
  tenantId: string;
  onBusy: (value: boolean) => void;
}) {
  const [workspace, setWorkspace] = useState<MappingWorkspaceData | null>(null);
  const [sourceId, setSourceId] = useState('');
  const [profile, setProfile] = useState<MappingProfile | null>(null);
  const [channel, setChannel] = useState('mahasiswa_biodata');
  const [name, setName] = useState('');
  const [rules, setRules] = useState<Record<string, MappingRule>>({});
  const [dirty, setDirty] = useState(true);
  const [optional, setOptional] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [sheet, setSheet] = useState('');
  const [delimiter, setDelimiter] = useState(',');
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [preview, setPreview] = useState<MappingPreview | null>(null);
  const [previewRow, setPreviewRow] = useState<MappingPreviewRow | null>(null);
  const [previewStatus, setPreviewStatus] = useState('');
  const [previewSearch, setPreviewSearch] = useState('');
  const [previewPage, setPreviewPage] = useState(1);
  const lock = useRef(false);
  const alive = useRef(true);
  useEffect(() => {
    alive.current = true;
    const controller = new AbortController();
    requestApi<{ data: MappingWorkspaceData }>(`mapping/workspace?tenant_id=${tenantId}`, {
      signal: controller.signal,
    })
      .then((result) => {
        if (!controller.signal.aborted) setWorkspace(result.data);
      })
      .catch((err) => {
        if (!controller.signal.aborted) setError(err.message);
      });
    return () => {
      alive.current = false;
      controller.abort();
      onBusy(false);
    };
  }, [tenantId, onBusy]);
  const source = workspace?.sources.find((item) => item.id === sourceId);
  const fields = workspace?.channels.find((item) => item.key === channel)?.fields ?? [];
  const edit = () => {
    setDirty(true);
    setPreview(null);
    setPreviewPage(1);
  };
  function changeRule(field: MappingField, changes: Partial<MappingRule>) {
    setRules((current) => ({
      ...current,
      [field.name]: {
        ...(current[field.name] ?? { target: field.name, kind: 'source', transform: 'trim' }),
        ...changes,
      },
    }));
    edit();
  }
  function selectProfile(id: string) {
    const selected = workspace?.profiles.find((item) => item.id === id) ?? null;
    setProfile(selected);
    setName(selected?.name ?? '');
    setPreview(null);
    setPreviewPage(1);
    setDirty(!selected);
    if (selected) {
      setChannel(selected.channel);
      setRules(Object.fromEntries(selected.rules.map((rule) => [rule.target, rule])));
    } else setRules(matchMappingHeaders(source?.headers ?? [], fields));
  }
  async function act(
    action: 'upload' | 'save' | 'preview' | 'stage',
    previewOptions: { page?: number; status?: string; search?: string } = {},
  ) {
    if (lock.current) return;
    lock.current = true;
    setBusy(action);
    onBusy(true);
    setError('');
    try {
      if (action === 'upload' && file) {
        const form = new FormData();
        form.append('tenant_id', tenantId);
        form.append('file', file);
        form.append('delimiter', delimiter);
        if (sheet) form.append('sheet_name', sheet);
        const result = await requestApi<{ data: SourceFile }>('mapping/sources', {
          method: 'POST',
          body: form,
        });
        if (!alive.current) return;
        setWorkspace(
          (current) => current && { ...current, sources: [result.data, ...current.sources] },
        );
        setSourceId(result.data.id);
        setFile(null);
        setPreview(null);
        if (!profile) {
          setRules(matchMappingHeaders(result.data.headers, fields));
          setDirty(true);
        }
      } else if (action === 'save') {
        const result = await requestApi<{ data: MappingProfile }>(
          'mapping/profiles',
          jsonPost({
            tenant_id: tenantId,
            name,
            channel,
            rules: Object.values(rules),
            ...(profile ? { profile_id: profile.id, expected_version: profile.version } : {}),
          }),
        );
        if (!alive.current) return;
        setProfile(result.data);
        setDirty(false);
        setPreview(null);
        setWorkspace(
          (current) =>
            current && {
              ...current,
              profiles: [
                result.data,
                ...current.profiles.filter((item) => item.id !== result.data.id),
              ],
            },
        );
      } else if (action === 'preview' && profile && source) {
        const page = previewOptions.page ?? previewPage;
        const status = previewOptions.status ?? previewStatus;
        const search = previewOptions.search ?? previewSearch;
        const query = new URLSearchParams({
          page: String(page),
          per_page: '25',
          ...(status ? { status } : {}),
          ...(search ? { search } : {}),
        });
        const result = await requestApi<{ data: MappingPreview }>(
          `mapping/profiles/${profile.id}/preview?${query}`,
          jsonPost({ source_id: source.id, version: profile.version }),
        );
        if (alive.current) {
          setPreview(result.data);
          setPreviewPage(result.data.meta.current_page);
        }
      } else if (action === 'stage' && profile && source && preview) {
        const result = await requestApi<{ data: { id: string } }>(
          `mapping/profiles/${profile.id}/stage`,
          jsonPost({
            source_id: source.id,
            version: profile.version,
            preview_hash: preview.preview_hash,
          }),
        );
        if (alive.current) goTo(`/import-batch/${result.data.id}`);
      }
    } catch (err) {
      if (alive.current) setError(err instanceof Error ? err.message : 'Permintaan gagal.');
    } finally {
      lock.current = false;
      onBusy(false);
      if (alive.current) setBusy('');
    }
  }
  async function exportPreview() {
    if (lock.current || !profile || !source || !preview) return;
    lock.current = true;
    setBusy('export');
    onBusy(true);
    setError('');
    try {
      const query = new URLSearchParams({
        source_id: source.id,
        version: String(profile.version),
        ...(previewStatus ? { status: previewStatus } : {}),
        ...(previewSearch ? { search: previewSearch } : {}),
      });
      await downloadApiFile(
        `mapping/profiles/${profile.id}/preview-report?${query}`,
        `mapping-preview-${profile.id}.csv`,
      );
    } catch (err) {
      if (alive.current) setError(err instanceof Error ? err.message : 'Ekspor gagal.');
    } finally {
      lock.current = false;
      onBusy(false);
      if (alive.current) setBusy('');
    }
  }
  if (!workspace)
    return error ? (
      <ErrorState title="Mapping gagal dimuat" description={error} />
    ) : (
      <LoadingState label="Memuat mapping" />
    );
  return (
    <>
      {error && <ErrorState title="Permintaan gagal" description={error} />}
      {channel === 'kelas_kuliah' && (
        <p className="muted">
          Siapkan referensi prodi, semester, dan mata kuliah terlebih dahulu. Mata kuliah baru harus
          memperoleh ID Neo Feeder sebelum dipakai untuk kelas.
        </p>
      )}
      <WorkspacePanel>
        <SectionHeader
          title="Sumber data"
          description="CSV UTF-8 atau XLSX berisi nilai, maksimal 2 MB, 2.000 baris, dan 64 kolom. Daftar menampilkan 100 sumber terbaru."
        />
        <div className="mapping-form-grid">
          <label className="mapping-field">
            Sumber tersimpan
            <Select
              value={sourceId}
              disabled={!!busy}
              onChange={(event) => {
                setSourceId(event.target.value);
                setPreview(null);
                if (!profile) {
                  const selected = workspace.sources.find((item) => item.id === event.target.value);
                  setRules(matchMappingHeaders(selected?.headers ?? [], fields));
                  edit();
                }
              }}
            >
              <option value="">Pilih sumber</option>
              {workspace.sources.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name} ({item.row_count} baris)
                </option>
              ))}
            </Select>
          </label>
          <label className="mapping-field">
            File baru
            <input
              type="file"
              accept=".csv,.xlsx"
              disabled={!!busy}
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          </label>
          <label className="mapping-field">
            Pemisah CSV
            <Select
              value={delimiter}
              disabled={!!busy}
              onChange={(event) => setDelimiter(event.target.value)}
            >
              <option value=",">Koma (,)</option>
              <option value=";">Titik koma (;)</option>
              <option value={'\t'}>Tab</option>
            </Select>
          </label>
          <label className="mapping-field">
            Sheet XLSX (opsional)
            <input
              value={sheet}
              maxLength={31}
              disabled={!!busy}
              placeholder="Kosong = sheet pertama"
              onChange={(event) => setSheet(event.target.value)}
            />
          </label>
        </div>
        <div className="mapping-actions">
          <span className="muted">
            {source
              ? `${source.name} · ${source.row_count} baris · ${source.headers.length} kolom`
              : 'Pilih sumber tersimpan atau upload file kampus.'}
          </span>
          <AppButton icon={Upload} disabled={!!busy || !file} onClick={() => act('upload')}>
            {busy === 'upload' ? 'Membaca file...' : 'Upload sumber'}
          </AppButton>
        </div>
      </WorkspacePanel>
      <WorkspacePanel>
        <SectionHeader
          title="Mapping kolom"
          description="Pilih kolom sumber atau nilai tetap untuk field tujuan. Setiap penyimpanan membuat versi baru; batch lama tetap memakai versi semula."
          action={
            profile && (
              <StatusBadge tone="info">{`Versi ${profile.version}${dirty ? ' · belum disimpan' : ''}`}</StatusBadge>
            )
          }
        />
        <div className="mapping-form-grid">
          <label className="mapping-field">
            Profil mapping
            <Select
              value={profile?.id ?? ''}
              disabled={!!busy}
              onChange={(event) => selectProfile(event.target.value)}
            >
              <option value="">Profil baru</option>
              {workspace.profiles.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name} · v{item.version}
                </option>
              ))}
            </Select>
          </label>
          <label className="mapping-field">
            Nama profil
            <input
              value={name}
              maxLength={120}
              disabled={!!busy}
              placeholder="Contoh: Mahasiswa SIAKAD"
              onChange={(event) => {
                setName(event.target.value);
                edit();
              }}
            />
          </label>
          <label className="mapping-field">
            Kanal tujuan
            <Select
              value={channel}
              disabled={!!busy || !!profile}
              onChange={(event) => {
                setChannel(event.target.value);
                const next = workspace.channels.find((item) => item.key === event.target.value);
                setRules(matchMappingHeaders(source?.headers ?? [], next?.fields ?? []));
                edit();
              }}
            >
              {workspace.channels.map((item) => (
                <option key={item.key} value={item.key}>
                  {channelLabels[item.key]}
                </option>
              ))}
            </Select>
          </label>
        </div>
        <div className="mapping-actions">
          <label>
            <input
              type="checkbox"
              checked={optional}
              onChange={(event) => setOptional(event.target.checked)}
            />{' '}
            Tampilkan kolom opsional
          </label>
          <span className="muted">{Object.keys(rules).length} field dipetakan</span>
        </div>
        {source ? (
          <DataTable
            columns={['Field tujuan', 'Kolom sumber / nilai tetap', 'Normalisasi']}
            rows={fields
              .filter((field) => optional || field.required || rules[field.name])
              .map((field) => {
                const rule = rules[field.name];
                return [
                  <span title={field.name}>
                    {field.label}
                    {field.required ? ' *' : ''}
                    <small className="mapping-field-code">{field.name}</small>
                  </span>,
                  <div className="mapping-rule-cell">
                    <Select
                      aria-label={`Sumber ${field.name}`}
                      value={
                        !rule
                          ? 'skip'
                          : rule.kind === 'constant'
                            ? 'fixed'
                            : `column:${rule.source}`
                      }
                      disabled={!!busy}
                      onChange={(event) => {
                        const value = event.target.value;
                        if (value === 'skip') {
                          setRules((current) => {
                            const next = { ...current };
                            delete next[field.name];
                            return next;
                          });
                          edit();
                        } else
                          changeRule(
                            field,
                            value === 'fixed'
                              ? { kind: 'constant', constant: rule?.constant ?? '' }
                              : { kind: 'source', source: value.slice(7) },
                          );
                      }}
                    >
                      <option value="skip">Belum dipetakan</option>
                      <option value="fixed">Nilai tetap</option>
                      {source.headers.map((header) => (
                        <option key={header} value={`column:${header}`}>
                          {header}
                        </option>
                      ))}
                    </Select>
                    {rule?.kind === 'constant' && (
                      <input
                        aria-label={`Nilai tetap ${field.name}`}
                        maxLength={255}
                        value={rule.constant ?? ''}
                        disabled={!!busy}
                        onChange={(event) => changeRule(field, { constant: event.target.value })}
                      />
                    )}
                  </div>,
                  <div className="mapping-rule-cell">
                    <Select
                      aria-label={`Normalisasi ${field.name}`}
                      disabled={!!busy || !rule}
                      value={rule?.transform ?? 'trim'}
                      onChange={(event) =>
                        changeRule(field, {
                          transform: event.target.value as MappingRule['transform'],
                          part: rule?.part ?? 1,
                        })
                      }
                    >
                      <option value="trim">Rapikan spasi</option>
                      <option value="lookup">Tabel padanan / enum</option>
                      <option value="concat">Gabung kolom</option>
                      <option value="split">Ambil bagian teks</option>
                      <option value="date_dmy">Tanggal dd/mm/yyyy</option>
                      <option value="excel_date">Tanggal angka Excel</option>
                      <option value="gender">Gender → L/P</option>
                      {field.reference && (
                        <option value="reference_label">Nama referensi → ID</option>
                      )}
                      {['GetProdi', 'GetListMataKuliah'].includes(field.reference ?? '') && (
                        <option value="reference_code">Kode referensi → ID</option>
                      )}
                    </Select>
                    {rule && ['lookup', 'concat', 'split'].includes(rule.transform) && (
                      <TransformRule
                        rule={rule}
                        headers={source.headers}
                        disabled={!!busy}
                        onChange={(changes) => changeRule(field, changes)}
                      />
                    )}
                    {rule?.transform.startsWith('reference_') && (
                      <ReferenceRule
                        tenantId={tenantId}
                        channel={channel}
                        rule={rule}
                        disabled={!!busy}
                        onChange={(changes) => changeRule(field, changes)}
                      />
                    )}
                  </div>,
                ];
              })}
          />
        ) : (
          <EmptyState icon={FileSpreadsheet} title="Pilih sumber untuk memetakan kolom" />
        )}
        <div className="mapping-actions">
          <AppButton
            icon={Save}
            disabled={!!busy || !name.trim() || !Object.keys(rules).length || !dirty}
            onClick={() => act('save')}
          >
            {busy === 'save' ? 'Menyimpan...' : profile ? 'Simpan versi baru' : 'Simpan profil'}
          </AppButton>
          <AppButton
            variant="secondary"
            disabled={!!busy || !source || !profile || dirty}
            onClick={() => act('preview')}
          >
            {busy === 'preview' ? 'Memeriksa...' : 'Preview hasil'}
          </AppButton>
        </div>
      </WorkspacePanel>
      {preview && (
        <WorkspacePanel>
          <SectionHeader
            title="Preview hasil mapping"
            description="Validasi mencakup seluruh file. Telusuri baris dengan filter status atau pencarian, lalu ekspor hasil yang sedang ditampilkan."
          />
          <dl className="summary-strip">
            {[
              ['Baris', preview.summary.total_rows],
              ['Valid', preview.summary.valid_rows],
              ['Perlu perbaikan', preview.summary.invalid_rows],
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
          <div className="mapping-form-grid">
            <label className="mapping-field">
              Status preview
              <Select
                value={previewStatus}
                disabled={!!busy}
                onChange={(event) => setPreviewStatus(event.target.value)}
              >
                <option value="">Semua status</option>
                <option value="valid">Valid</option>
                <option value="invalid">Perlu perbaikan</option>
              </Select>
            </label>
            <label className="mapping-field">
              Cari baris, field, atau pesan
              <input
                value={previewSearch}
                maxLength={100}
                disabled={!!busy}
                placeholder="Contoh: NIK atau tanggal"
                onChange={(event) => setPreviewSearch(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    setPreviewPage(1);
                    void act('preview', {
                      page: 1,
                      status: previewStatus,
                      search: event.currentTarget.value,
                    });
                  }
                }}
              />
            </label>
          </div>
          <div className="mapping-actions">
            <AppButton
              variant="secondary"
              disabled={!!busy}
              onClick={() => {
                setPreviewPage(1);
                void act('preview', { page: 1 });
              }}
            >
              Terapkan filter
            </AppButton>
            <AppButton
              icon={Download}
              disabled={!!busy || !preview.meta.total}
              onClick={() => void exportPreview()}
            >
              {busy === 'export' ? 'Mengekspor...' : 'Ekspor CSV'}
            </AppButton>
            <span className="muted">{preview.meta.total} baris cocok</span>
          </div>
          <DataTable
            columns={['Baris sumber', 'Status', 'Temuan']}
            rows={preview.rows.map((row) => [
              <button className="table-link" onClick={() => setPreviewRow(row)}>
                Baris {row.row_number}
              </button>,
              <StatusBadge tone={row.status === 'valid' ? 'success' : 'warning'}>
                {row.status === 'valid' ? 'Valid' : 'Perlu perbaikan'}
              </StatusBadge>,
              [...row.validation_result.errors, ...row.validation_result.warnings]
                .map((issue) => `${issue.field ?? 'Baris'}: ${issue.message}`)
                .join('; ') || '—',
            ])}
          />
          <div className="mapping-actions">
            <span className="muted">
              Batch akan dibuka untuk validasi dan dry-run. Pengiriman tetap memerlukan persetujuan
              terpisah.
            </span>
            <AppButton disabled={!!busy || dirty} onClick={() => act('stage')}>
              {busy === 'stage' ? 'Membuat batch...' : 'Buat batch validasi'}
            </AppButton>
          </div>
          <Pagination
            meta={preview.meta}
            disabled={!!busy}
            onChange={(page) => {
              setPreviewPage(page);
              void act('preview', { page });
            }}
          />
        </WorkspacePanel>
      )}
      {previewRow && (
        <FormDialog
          open
          title={`Hasil mapping baris ${previewRow.row_number}`}
          onClose={() => setPreviewRow(null)}
        >
          <dl className="row-data">
            {Object.entries(previewRow.normalized_row)
              .filter(([, value]) => value !== null)
              .map(([field, value]) => (
                <div key={field}>
                  <dt>{field}</dt>
                  <dd>{String(value)}</dd>
                </div>
              ))}
          </dl>
        </FormDialog>
      )}
    </>
  );
}
