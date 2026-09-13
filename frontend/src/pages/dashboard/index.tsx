import { useEffect, useState } from 'react';
import { RefreshCcw } from 'lucide-react';
import { ErrorState, HelpTip, IconButton, LoadingState, PageHeader, Select } from '@/components/ui';
import { ReportChart } from '@/components/ui/report-chart';
import { getDashboardStatistics, type DashboardStatistics } from '@/lib/api';
import { useWorkspace } from '@/hooks/workspace-context';

const statusLabels: Record<string, string> = {
  uploaded: 'Diupload',
  parsing: 'Parsing',
  ready: 'Siap validasi',
  validated: 'Valid',
  invalid: 'Perlu perbaikan',
  dry_run_ready: 'Dry-run',
  syncing: 'Mengirim',
  synced: 'Tersinkron',
  failed: 'Gagal',
  queued: 'Antrean',
  retrying: 'Retry',
  success: 'Berhasil',
  skipped: 'Dilewati',
  pending: 'Menunggu',
  valid: 'Valid',
};
const number = (value: number) => value.toLocaleString('id-ID');
const statusColor = (status: string) =>
  ({
    failed: '#ef4444',
    invalid: '#f59e0b',
    retrying: '#f59e0b',
    valid: '#14b8a6',
    validated: '#14b8a6',
    success: '#14b8a6',
    synced: '#14b8a6',
    skipped: '#64748b',
  })[status] ?? '#3b82f6';
export function DashboardPage() {
  const { authUser, tenants } = useWorkspace();
  const [days, setDays] = useState(30);
  const [tenant, setTenant] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [data, setData] = useState<DashboardStatistics | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    getDashboardStatistics(days, tenant, controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setData(data);
      })
      .catch((error) => {
        if (!controller.signal.aborted)
          setError(error instanceof Error ? error.message : 'Statistik gagal dimuat.');
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [days, tenant, refresh]);
  const totals = data?.totals;
  return (
    <>
      <PageHeader
        title="Dashboard"
        action={
          <>
            {authUser?.role === 'admin' && (
              <label className="inline-field">
                <span className="sr-only">Kampus</span>
                <Select value={tenant} onChange={(event) => setTenant(event.target.value)}>
                  <option value="">Semua kampus</option>
                  {tenants.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </Select>
              </label>
            )}
            <label className="inline-field">
              <span className="sr-only">Aktivitas import</span>
              <Select
                value={String(days)}
                onChange={(event) => setDays(Number(event.target.value))}
              >
                {[14, 30, 90].map((day) => (
                  <option key={day} value={String(day)}>
                    {day} hari terakhir
                  </option>
                ))}
              </Select>
            </label>
            <HelpTip
              label="Cakupan statistik"
              text="Total dan komposisi memakai seluruh riwayat kampus terpilih. Rentang hari hanya berlaku pada grafik aktivitas import. Keberhasilan dihitung dari attempt yang selesai; retry dihitung sebagai attempt baru."
            />

            <IconButton
              label="Muat ulang statistik"
              icon={RefreshCcw}
              disabled={loading}
              onClick={() => setRefresh((value) => value + 1)}
            />
          </>
        }
      />
      {error ? (
        <ErrorState title="Statistik gagal dimuat" description={error} />
      ) : loading ? (
        <LoadingState label="Memuat statistik" />
      ) : data && totals ? (
        <>
          <dl className="report-metrics">
            {[
              ['Batch import', number(totals.batches)],
              ['Baris staging', number(totals.staging_rows)],
              [
                'Keberhasilan pengiriman',
                totals.sync_success_rate === null ? '-' : `${totals.sync_success_rate}%`,
              ],
              [
                'Kelengkapan referensi',
                totals.reference_endpoints_expected
                  ? `${Math.round((100 * totals.reference_endpoints_covered) / totals.reference_endpoints_expected)}%`
                  : '-',
              ],
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
          <div className="report-charts">
            <ReportChart
              title={`Aktivitas import (${days} hari)`}
              labels={data.activity.map((item) => item.date)}
              values={data.activity.map((item) => item.total)}
              type="area"
            />
            <ReportChart
              title="Status batch"
              labels={data.batch_statuses.map((item) => statusLabels[item.status] ?? item.status)}
              values={data.batch_statuses.map((item) => Number(item.total))}
              colors={data.batch_statuses.map((item) => statusColor(item.status))}
              type="donut"
            />
            <ReportChart
              title="Hasil validasi dan staging"
              labels={data.row_statuses.map((item) => statusLabels[item.status] ?? item.status)}
              values={data.row_statuses.map((item) => Number(item.total))}
              colors={data.row_statuses.map((item) => statusColor(item.status))}
            />
            <ReportChart
              title="Status pengiriman"
              labels={data.sync_statuses.map((item) => statusLabels[item.status] ?? item.status)}
              values={data.sync_statuses.map((item) => Number(item.total))}
              colors={data.sync_statuses.map((item) => statusColor(item.status))}
            />
          </div>
          <section className="report-inventory">
            <h2>Workspace</h2>
            <dl>
              {[
                ['Kampus aktif', `${number(totals.active_campuses)} / ${number(totals.campuses)}`],
                ['Operator aktif', number(totals.active_operators)],
                [
                  'Koneksi aktif',
                  `${number(totals.active_connections)} / ${number(totals.connections)}`,
                ],
                ['Data referensi', number(totals.reference_rows)],
                ['Kanal template', number(totals.channels)],
                ['Baris berperingatan', number(totals.warning_rows)],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <p className="automation-status">
              Otomatisasi <span>Belum tersedia</span>
              <HelpTip
                label="Status otomatisasi"
                text="Mapping dan penjadwalan SIAKAD masih fase 2, belum dihitung sebagai data operasional."
              />
            </p>
          </section>
        </>
      ) : null}
    </>
  );
}
