import { useState } from 'react';
import { AppButton, FormDialog } from '@/components/ui';
import type { MappingRule } from '@/lib/mapping';

export function TransformRule({
  rule,
  headers,
  disabled,
  onChange,
}: {
  rule: MappingRule;
  headers: string[];
  disabled: boolean;
  onChange: (changes: Partial<MappingRule>) => void;
}) {
  const [open, setOpen] = useState(false);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  return (
    <>
      <AppButton variant="secondary" disabled={disabled} onClick={() => setOpen(true)}>
        Atur transformasi
      </AppButton>
      {open && (
        <FormDialog open title={`Transformasi ${rule.target}`} onClose={() => setOpen(false)}>
          {rule.transform === 'lookup' ? (
            <>
              <p>Padanan membedakan huruf besar. Nilai yang belum dipetakan akan menjadi error.</p>
              <label className="mapping-field">
                Nilai asal
                <input maxLength={255} value={from} onChange={(e) => setFrom(e.target.value)} />
              </label>
              <label className="mapping-field">
                Nilai tujuan
                <input maxLength={255} value={to} onChange={(e) => setTo(e.target.value)} />
              </label>
              <AppButton
                disabled={
                  disabled || !from.trim() || !to.trim() || (rule.pairs?.length ?? 0) >= 100
                }
                onClick={() => {
                  onChange({
                    pairs: [
                      ...(rule.pairs ?? []).filter((pair) => pair.from !== from.trim()),
                      { from: from.trim(), to: to.trim() },
                    ],
                  });
                  setFrom('');
                  setTo('');
                }}
              >
                Tambahkan padanan
              </AppButton>
              {(rule.pairs ?? []).map((pair) => (
                <div className="mapping-actions" key={pair.from}>
                  <span>
                    {pair.from} → {pair.to}
                  </span>
                  <AppButton
                    variant="secondary"
                    disabled={disabled}
                    onClick={() =>
                      onChange({ pairs: rule.pairs?.filter((item) => item.from !== pair.from) })
                    }
                  >
                    Hapus
                  </AppButton>
                </div>
              ))}
            </>
          ) : (
            <>
              <label className="mapping-field">
                Pemisah (spasi diperbolehkan)
                <input
                  maxLength={20}
                  value={rule.separator ?? ''}
                  disabled={disabled}
                  onChange={(e) => onChange({ separator: e.target.value })}
                />
              </label>
              {rule.transform === 'split' ? (
                <label className="mapping-field">
                  Nomor bagian, mulai dari 1
                  <input
                    type="number"
                    min={1}
                    max={64}
                    value={rule.part ?? 1}
                    disabled={disabled}
                    onChange={(e) => onChange({ part: Number(e.target.value) })}
                  />
                </label>
              ) : (
                <>
                  <p>
                    Kolom utama digabung dengan kolom tambahan menurut urutan pilihan. Nilai kosong
                    dilewati.
                  </p>
                  <p>
                    Urutan:{' '}
                    {[rule.source ?? 'Nilai tetap', ...(rule.append_sources ?? [])].join(' → ')}
                  </p>
                  {headers
                    .filter((header) => header !== rule.source)
                    .map((header) => (
                      <label className="mapping-field" key={header}>
                        <span>
                          <input
                            type="checkbox"
                            checked={rule.append_sources?.includes(header) ?? false}
                            disabled={
                              disabled ||
                              (!rule.append_sources?.includes(header) &&
                                (rule.append_sources?.length ?? 0) >= 7)
                            }
                            onChange={(e) =>
                              onChange({
                                append_sources: e.target.checked
                                  ? [...(rule.append_sources ?? []), header]
                                  : rule.append_sources?.filter((item) => item !== header),
                              })
                            }
                          />{' '}
                          {header}
                        </span>
                      </label>
                    ))}
                </>
              )}
            </>
          )}
          <p>Simpan versi baru dan ulang preview setelah mengubah aturan.</p>
        </FormDialog>
      )}
    </>
  );
}
