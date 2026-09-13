import { BatchList } from '@/components/batch-workspace';
import { useWorkspace } from '@/hooks/workspace-context';
import { goTo } from '@/lib/router';
export function ImportBatchPage() {
  const { importRevision, setDialog } = useWorkspace();
  return (
    <BatchList
      revision={importRevision}
      onUpload={() => setDialog('upload')}
      onSelect={(id) => goTo(`/import-batch/${id}`)}
    />
  );
}
