import { CalendarClock, Pause, Play, Plus, RefreshCcw, RotateCw } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  IconButton,
  LoadingState,
  PageHeader,
  SectionHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';
import { Select } from '@/components/ui/select';
import { useWorkspace } from '@/hooks/workspace-context';
import {
  createAutomationSchedule,
  getAutomationSchedules,
  getMappingWorkspace,
  runAutomationSchedule,
  updateAutomationSchedule,
  type AutomationSchedule,
  type AutomationScheduleFrequency,
} from '@/lib/api';
import { formatDateTime } from '@/hooks/use-workspace-controller';

const frequencyLabels: Record<AutomationScheduleFrequency, string> = {
  hourly: 'Setiap jam',
  daily: 'Setiap hari',
  weekly: 'Setiap minggu',
};
const statusLabels: Record<AutomationSchedule['status'], string> = {
  idle: 'Siap',
  queued: 'Diantrekan',
  running: 'Berjalan',
  success: 'Berhasil',
  failed: 'Gagal',
};
const statusTones: Record<
  AutomationSchedule['status'],
  'neutral' | 'info' | 'success' | 'warning' | 'destructive'
> = {
  idle: 'neutral',
  queued: 'info',
  running: 'info',
  success: 'success',
  failed: 'destructive',
};

export function AutomationPage() {
  const { authUser, tenants } = useWorkspace();
  const [tenant, setTenant] = useState('');
  const [revision, setRevision] = useState(0);
  const activeTenant = tenant || authUser?.tenant_id || tenants[0]?.id || '';

  return (
    <>
      <PageHeader
        title="Schedule SIAKAD"
        action={
          <>
            {authUser?.role === 'admin' ? (
              <label className="inline-field">
                <span className="sr-only">Kampus automation</span>
                <Select value={activeTenant} onChange={(event) => setTenant(event.target.value)}>
                  {!tenants.length && <option value="">Belum ada kampus</option>}
                  {tenants.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </Select>
              </label>
            ) : null}
            <IconButton
              label="Muat ulang schedule"
              icon={RefreshCcw}
              onClick={() => setRevision((value) => value + 1)}
            />
          </>
        }
      />
      {activeTenant ? (
        <AutomationWorkspace key={`${activeTenant}:${revision}`} tenantId={activeTenant} />
      ) : (
        <EmptyState icon={CalendarClock} title="Buat kampus terlebih dahulu" />
      )}
    </>
  );
}

function AutomationWorkspace({ tenantId }: { tenantId: string }) {
  const [schedules, setSchedules] = useState<AutomationSchedule[]>([]);
  const [sources, setSources] = useState<{ id: string; name: string; row_count: number }[]>([]);
  const [profiles, setProfiles] = useState<{ id: string; name: string; channel: string }[]>([]);
  const [sourceId, setSourceId] = useState('');
  const [profileId, setProfileId] = useState('');
  const [name, setName] = useState('');
  const [frequency, setFrequency] = useState<AutomationScheduleFrequency>('daily');
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');

  const load = useCallback(
    async (signal?: AbortSignal) => {
      setState('loading');
      try {
        const [workspace, items] = await Promise.all([
          getMappingWorkspace(tenantId, signal),
          getAutomationSchedules(tenantId, signal),
        ]);
        const readySources = workspace.sources
          .filter(
            (source) =>
              source.type === 'database' &&
              source.snapshot_status === 'ready' &&
              source.row_count > 0,
          )
          .map((source) => ({ id: source.id, name: source.name, row_count: source.row_count }));
        setSources(readySources);
        setProfiles(
          workspace.profiles.map((profile) => ({
            id: profile.id,
            name: profile.name,
            channel: profile.channel,
          })),
        );
        setSchedules(items);
        setSourceId((current) => current || readySources[0]?.id || '');
        setProfileId((current) => current || workspace.profiles[0]?.id || '');
        setState('ready');
        setError('');
      } catch (loadError) {
        if (loadError instanceof DOMException && loadError.name === 'AbortError') return;
        setState('error');
        setError(loadError instanceof Error ? loadError.message : 'Schedule belum bisa dimuat.');
      }
    },
    [tenantId],
  );

  useEffect(() => {
    const controller = new AbortController();
    void load(controller.signal);
    return () => controller.abort();
  }, [load]);

  const availableProfiles = useMemo(() => profiles, [profiles]);
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!sourceId || !profileId || !name.trim()) {
      setError('Lengkapi nama, source database, dan mapping profile.');
      return;
    }
    setBusy('create');
    setError('');
    try {
      const schedule = await createAutomationSchedule({
        tenant_id: tenantId,
        source_connection_id: sourceId,
        mapping_profile_id: profileId,
        name: name.trim(),
        frequency,
      });
      setSchedules((current) => [schedule, ...current]);
      setName('');
    } catch (createError) {
      setError(createError instanceof Error ? createError.message : 'Schedule belum bisa dibuat.');
    } finally {
      setBusy('');
    }
  };

  const toggle = async (schedule: AutomationSchedule) => {
    setBusy(schedule.id);
    setError('');
    try {
      const updated = await updateAutomationSchedule(schedule.id, {
        is_active: !schedule.is_active,
      });
      setSchedules((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    } catch (toggleError) {
      setError(
        toggleError instanceof Error ? toggleError.message : 'Status schedule belum bisa diubah.',
      );
    } finally {
      setBusy('');
    }
  };

  const run = async (schedule: AutomationSchedule) => {
    setBusy(schedule.id);
    setError('');
    try {
      const updated = await runAutomationSchedule(schedule.id);
      setSchedules((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    } catch (runError) {
      setError(runError instanceof Error ? runError.message : 'Schedule belum bisa dijalankan.');
    } finally {
      setBusy('');
    }
  };

  if (state === 'loading') return <LoadingState label="Memuat schedule" />;
  if (state === 'error') return <ErrorState title="Schedule gagal dimuat" description={error} />;

  return (
    <div className="automation-workspace">
      <WorkspacePanel>
        <SectionHeader title="Tambah schedule" />
        <form className="automation-form" onSubmit={submit}>
          <label>
            Nama
            <input
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Contoh: Mahasiswa harian"
            />
          </label>
          <label>
            Source database
            <Select value={sourceId} required onChange={(event) => setSourceId(event.target.value)}>
              {!sources.length && <option value="">Belum ada snapshot siap</option>}
              {sources.map((source) => (
                <option key={source.id} value={source.id}>
                  {source.name} - {source.row_count} baris
                </option>
              ))}
            </Select>
          </label>
          <label>
            Mapping profile
            <Select
              value={profileId}
              required
              onChange={(event) => setProfileId(event.target.value)}
            >
              {!availableProfiles.length && <option value="">Belum ada profile</option>}
              {availableProfiles.map((profile) => (
                <option key={profile.id} value={profile.id}>
                  {profile.name} - {profile.channel}
                </option>
              ))}
            </Select>
          </label>
          <label>
            Frekuensi
            <Select
              value={frequency}
              onChange={(event) => setFrequency(event.target.value as AutomationScheduleFrequency)}
            >
              {Object.entries(frequencyLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
          </label>
          <AppButton
            icon={Plus}
            type="submit"
            disabled={busy !== '' || !sources.length || !profiles.length}
          >
            Tambah
          </AppButton>
        </form>
        {error ? (
          <p className="auth-error" role="alert">
            {error}
          </p>
        ) : null}
      </WorkspacePanel>

      <WorkspacePanel>
        <SectionHeader title="Daftar schedule" />
        <DataTable
          columns={['Schedule', 'Source', 'Profile', 'Frekuensi', 'Status', 'Berikutnya', 'Aksi']}
          rows={schedules.map((schedule) => [
            <span className="table-primary" key={schedule.id}>
              {schedule.name}
            </span>,
            schedule.source?.name ?? '-',
            schedule.profile?.name ?? '-',
            frequencyLabels[schedule.frequency],
            <StatusBadge key={schedule.id} tone={statusTones[schedule.status]}>
              {statusLabels[schedule.status]}
            </StatusBadge>,
            formatDateTime(schedule.next_run_at),
            <div className="automation-row-actions" key={schedule.id}>
              <IconButton
                label={schedule.is_active ? 'Jeda schedule' : 'Aktifkan schedule'}
                icon={schedule.is_active ? Pause : Play}
                disabled={
                  busy === schedule.id ||
                  schedule.status === 'queued' ||
                  schedule.status === 'running'
                }
                onClick={() => void toggle(schedule)}
              />
              <IconButton
                label="Jalankan sekarang"
                icon={RotateCw}
                disabled={
                  busy === schedule.id ||
                  !schedule.is_active ||
                  schedule.status === 'queued' ||
                  schedule.status === 'running'
                }
                onClick={() => void run(schedule)}
              />
            </div>,
          ])}
          emptyState={
            <EmptyState
              icon={CalendarClock}
              title="Belum ada schedule"
              description={
                sources.length
                  ? 'Tambahkan schedule untuk mulai menjalankan snapshot dan mapping otomatis.'
                  : 'Siapkan snapshot database terlebih dahulu.'
              }
            />
          }
        />
      </WorkspacePanel>
    </div>
  );
}
