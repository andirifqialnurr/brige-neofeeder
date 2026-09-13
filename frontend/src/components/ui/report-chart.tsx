import { useMemo } from 'react';
import Chart from 'react-apexcharts';
import type { ApexOptions } from 'apexcharts';
import { useTheme } from '@/hooks/use-theme';

export function ReportChart({
  title,
  labels,
  values,
  type = 'bar',
  colors = ['#3b82f6'],
}: {
  title: string;
  labels: string[];
  values: number[];
  type?: 'bar' | 'area' | 'donut';
  colors?: string[];
}) {
  const { resolvedTheme } = useTheme();
  const options = useMemo<ApexOptions>(
    () => ({
      chart: {
        fontFamily: 'inherit',
        foreColor: resolvedTheme === 'dark' ? '#a8b2c3' : '#64748b',
        background: 'transparent',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches },
      },
      theme: { mode: resolvedTheme },
      colors,
      labels,
      xaxis: {
        type: type === 'area' ? 'datetime' : 'category',
        categories: type === 'area' ? undefined : labels,
        tickAmount: 5,
        labels: {
          trim: false,
          rotate: 0,
          hideOverlappingLabels: true,
          datetimeUTC: true,
          format: type === 'area' ? 'dd MMM' : undefined,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
      },
      yaxis: {
        min: 0,
        forceNiceScale: true,
        decimalsInFloat: 0,
        labels: { formatter: (value) => Math.round(value).toLocaleString('id-ID'), maxWidth: 52 },
      },
      grid: { borderColor: resolvedTheme === 'dark' ? '#263344' : '#e2e8f0', strokeDashArray: 4 },
      stroke: { width: type === 'area' ? 2 : 0, curve: 'straight' },
      fill: { type: 'solid', opacity: type === 'area' ? 0.12 : 1 },
      dataLabels: { enabled: false },
      plotOptions: {
        bar: { borderRadius: 3, columnWidth: '48%', distributed: true },
        pie: { donut: { size: '70%' } },
      },
      legend: {
        show: type === 'donut',
        position: 'bottom',
        fontSize: '12px',
        markers: { size: 5 },
      },
      tooltip: {
        theme: resolvedTheme,
        x: { format: 'dd MMM yyyy' },
        y: { formatter: (value) => value.toLocaleString('id-ID') },
      },
      noData: { text: 'Belum ada data' },
    }),
    [resolvedTheme, labels, type, colors],
  );
  const empty = values.length === 0 || values.every((value) => value === 0);
  return (
    <section className="report-chart" aria-label={title}>
      <h2>{title}</h2>
      <div
        className="chart-canvas"
        role="img"
        aria-label={
          empty
            ? `${title}: belum ada data`
            : `${title}. ${labels.map((label, index) => `${label}: ${values[index]}`).join(', ')}`
        }
      >
        {empty ? (
          <div className="chart-empty">Belum ada data</div>
        ) : (
          <Chart
            key={`${resolvedTheme}-${type}`}
            options={options}
            series={
              type === 'donut'
                ? values
                : [
                    {
                      name: title,
                      data:
                        type === 'area'
                          ? values.map((value, index) => ({
                              x: Date.parse(labels[index] + 'T00:00:00Z'),
                              y: value,
                            }))
                          : values,
                    },
                  ]
            }
            type={type}
            height={280}
            width="100%"
          />
        )}
      </div>
    </section>
  );
}
