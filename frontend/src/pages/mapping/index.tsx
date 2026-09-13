import { FileSpreadsheet, Waypoints } from 'lucide-react';

import { AppButton, EmptyState, PageHeader } from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
export function MappingPage() {
  const { navigate } = useWorkspace();
  return (
    <>
      <PageHeader
        title="Mapping SIAKAD"
        action={
          <AppButton
            variant="secondary"
            icon={FileSpreadsheet}
            onClick={() => navigate('template-excel')}
          >
            Template Excel
          </AppButton>
        }
      />
      <EmptyState
        icon={Waypoints}
        title="Otomatisasi dalam rencana"
        description="Mapping sumber SIAKAD akan tersedia pada fase 2."
      />
    </>
  );
}
