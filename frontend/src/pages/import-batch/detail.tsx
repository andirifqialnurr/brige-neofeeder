import { BatchInspection } from '@/components/batch-workspace';
import { useWorkspace } from '@/hooks/workspace-context';
import { goTo } from '@/lib/router';
export function ImportBatchDetailPage({ id }: { id: string }) {
  const { setImportRevision } = useWorkspace();
  return (
    <BatchInspection
      key={id}
      id={id}
      onBack={() => goTo('/import-batch')}
      onUpdated={() => setImportRevision((value) => value + 1)}
    />
  );
}
