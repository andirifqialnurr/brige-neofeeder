import { Download } from 'lucide-react';

import {
  AppButton,
  DataTable,
  ErrorState,
  PageHeader,
  SectionHeader,
  WorkspacePanel,
} from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
import { templateColumns } from '@/hooks/use-workspace-controller';
export function TemplatePage() {
  const { templateDownloadState, templateDownloadError, handleDownloadTemplate } = useWorkspace();
  return (
    <>
      <PageHeader
        title="Template Excel"
        action={
          <AppButton
            disabled={templateDownloadState === 'loading'}
            icon={Download}
            onClick={handleDownloadTemplate}
          >
            {templateDownloadState === 'loading' ? 'Menyiapkan...' : 'Download template'}
          </AppButton>
        }
      />
      {templateDownloadState === 'error' ? (
        <ErrorState title="Download gagal" description={templateDownloadError} />
      ) : null}
      <WorkspacePanel>
        <SectionHeader
          title="Isi workbook"
          description="Isi data pada sheet yang dibutuhkan. Petunjuk pengisian tersedia di sheet README."
        />
        <DataTable
          columns={templateColumns}
          rows={[
            ['README', 'Petunjuk pengisian'],
            ...[
              ['mahasiswa_biodata', 'Biodata mahasiswa'],
              ['mahasiswa_riwayat_pendidikan', 'Riwayat pendidikan'],
              ['mata_kuliah', 'Mata kuliah'],
              ['kurikulum', 'Kurikulum'],
              ['matkul_kurikulum', 'Mata kuliah kurikulum'],
              ['kelas_kuliah', 'Kelas kuliah'],
              ['peserta_kelas', 'Peserta kelas'],
              ['dosen_pengajar_kelas', 'Dosen pengajar kelas'],
              ['nilai_perkuliahan', 'Nilai kelas'],
              ['perkuliahan_mahasiswa_akm', 'Aktivitas kuliah mahasiswa'],
              ['mahasiswa_lulus_do', 'Mahasiswa lulus / DO'],
              ['ref_*', 'Daftar referensi'],
            ],
          ]}
        />
      </WorkspacePanel>
    </>
  );
}
