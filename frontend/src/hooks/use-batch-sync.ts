import { useEffect, useRef, useState } from 'react';
import {
  approveBatch,
  getSyncAttempts,
  getSyncProgress,
  retrySyncAttempt,
  startBatchSync,
  type PageResult,
  type SyncAttemptView,
  type SyncProgress,
} from '@/lib/api';

type Confirmation =
  | { kind: 'approve'; hash: string }
  | { kind: 'start' | 'resume' }
  | { kind: 'retry'; attempt: SyncAttemptView };

export function useBatchSync(id: string, active: boolean, onChanged: () => void) {
  const [progress, setProgress] = useState<SyncProgress | null>(null);
  const [attempts, setAttempts] = useState<PageResult<SyncAttemptView> | null>(null);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [commandError, setCommandError] = useState('');
  const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
  const [detail, setDetail] = useState<SyncAttemptView | null>(null);
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const lock = useRef(false);

  useEffect(() => {
    if (!active) return;
    const controller = new AbortController();
    let timer: ReturnType<typeof setTimeout>;
    async function load(first = false) {
      if (document.hidden && !first) {
        timer = setTimeout(() => void load(), 5000);
        return;
      }
      if (first) setLoading(true);
      try {
        const next = await getSyncProgress(id, controller.signal);
        const rows = await getSyncAttempts(id, page, status, controller.signal);
        if (!controller.signal.aborted) {
          setProgress(next);
          setAttempts(rows);
          setError('');
          if (next.records.active > 0) timer = setTimeout(() => void load(), 5000);
        }
      } catch (error) {
        if (!controller.signal.aborted)
          setError(error instanceof Error ? error.message : 'Status pengiriman gagal dimuat.');
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }
    void load(true);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [id, active, page, status, refresh]);

  function open(value: Confirmation) {
    setConfirmation(value);
    setConfirmed(false);
    setCommandError('');
  }
  async function submit() {
    if (!confirmation || lock.current || (confirmation.kind === 'approve' && !confirmed)) return;
    lock.current = true;
    setBusy(true);
    setCommandError('');
    try {
      if (confirmation.kind === 'approve') await approveBatch(id, confirmation.hash);
      else if (confirmation.kind === 'retry') await retrySyncAttempt(confirmation.attempt.id);
      else await startBatchSync(id);
      setConfirmation(null);
      setConfirmed(false);
      setRefresh((value) => value + 1);
      onChanged();
    } catch (error) {
      setCommandError(error instanceof Error ? error.message : 'Permintaan gagal.');
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }
  return {
    progress,
    attempts,
    page,
    status,
    loading,
    error,
    commandError,
    confirmation,
    detail,
    confirmed,
    busy,
    setPage,
    setDetail,
    setConfirmed,
    open,
    submit,
    filter: (value: string) => {
      setStatus(value);
      setPage(1);
    },
    reload: () => {
      setRefresh((value) => value + 1);
      onChanged();
    },
    close: () => {
      if (!lock.current) {
        setConfirmation(null);
        setCommandError('');
      }
    },
  };
}
