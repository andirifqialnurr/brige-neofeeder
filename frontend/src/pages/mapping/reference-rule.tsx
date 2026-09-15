import { useEffect, useState } from 'react';
import { AppButton, FormDialog } from '@/components/ui';
import { Select } from '@/components/ui/select';
import { requestApi } from '@/lib/api';
import type { MappingRule } from '@/lib/mapping';

export function ReferenceRule({
  tenantId,
  channel,
  rule,
  disabled,
  onChange,
}: {
  tenantId: string;
  channel: string;
  rule: MappingRule;
  disabled: boolean;
  onChange: (changes: Partial<MappingRule>) => void;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [choices, setChoices] = useState<{ value: string; label: string }[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  useEffect(() => {
    if (!open) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setError('');
      setTo('');
      const query = new URLSearchParams({
        tenant_id: tenantId,
        channel,
        field: rule.target,
        search,
      });
      requestApi<{ data: typeof choices }>(`mapping/references?${query}`, {
        signal: controller.signal,
      })
        .then((result) => {
          if (!controller.signal.aborted) setChoices(result.data);
        })
        .catch((err) => {
          if (!controller.signal.aborted) {
            setError(err.message);
            setChoices([]);
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 250);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [open, search, tenantId, channel, rule.target]);
  return (
    <>
      <AppButton variant="secondary" disabled={disabled} onClick={() => setOpen(true)}>
        Padanan ID ({rule.overrides?.length ?? 0})
      </AppButton>
      {open && (
        <FormDialog open title={`Padanan referensi ${rule.target}`} onClose={() => setOpen(false)}>
          <p>
            Nama dicocokkan tanpa membedakan huruf besar. Padanan berikut menggantikan pencocokan
            untuk nilai asal yang sama persis setelah spasi tepi dibuang.
          </p>
          <label className="mapping-field">
            Nilai asal dari file
            <input value={from} maxLength={255} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="mapping-field">
            Cari nama atau ID referensi
            <input
              value={search}
              maxLength={100}
              onChange={(e) => {
                setSearch(e.target.value);
                setTo('');
              }}
            />
          </label>
          <label className="mapping-field">
            Referensi kampus (maksimal 100 hasil)
            <Select value={to} disabled={loading} onChange={(e) => setTo(e.target.value)}>
              <option value="">{loading ? 'Memuat...' : 'Pilih ID yang benar'}</option>
              {choices.map((choice) => (
                <option key={choice.value} value={choice.value}>
                  {choice.label} — {choice.value}
                </option>
              ))}
            </Select>
          </label>
          {error && <p role="alert">{error}</p>}
          <AppButton
            disabled={
              disabled || loading || !from.trim() || !to || (rule.overrides?.length ?? 0) >= 100
            }
            onClick={() => {
              onChange({
                overrides: [
                  ...(rule.overrides ?? []).filter((pair) => pair.from !== from.trim()),
                  { from: from.trim(), to },
                ],
              });
              setFrom('');
              setTo('');
            }}
          >
            Tambahkan padanan
          </AppButton>
          {(rule.overrides ?? []).map((pair) => (
            <div className="mapping-actions" key={pair.from}>
              <span>
                {pair.from} → {pair.to}
              </span>
              <AppButton
                variant="secondary"
                disabled={disabled}
                onClick={() =>
                  onChange({ overrides: rule.overrides?.filter((item) => item.from !== pair.from) })
                }
              >
                Hapus
              </AppButton>
            </div>
          ))}
          <p>Simpan versi baru dan ulang preview setelah mengubah padanan.</p>
        </FormDialog>
      )}
    </>
  );
}
