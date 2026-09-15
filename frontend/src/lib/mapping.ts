import type { DryRunIssue } from './api';

export type SourceFile = {
  id: string;
  tenant_id: string;
  name: string;
  headers: string[];
  sheet_name: string | null;
  row_count: number;
};
export type MappingRule = {
  target: string;
  kind: 'source' | 'constant';
  source?: string | null;
  constant?: string | null;
  transform: 'trim' | 'date_dmy' | 'excel_date' | 'gender';
};
export type MappingProfile = {
  id: string;
  name: string;
  channel: string;
  version: number;
  rules: MappingRule[];
};
export type MappingField = { name: string; label: string; required?: boolean; type: string };
export type MappingWorkspaceData = {
  sources: SourceFile[];
  profiles: MappingProfile[];
  channels: { key: string; fields: MappingField[] }[];
};
export type MappingPreviewRow = {
  row_number: number;
  normalized_row: Record<string, unknown>;
  status: string;
  validation_result: { errors: DryRunIssue[]; warnings: DryRunIssue[]; info: DryRunIssue[] };
};
export type MappingPreview = {
  preview_hash: string;
  version: number;
  summary: { total_rows: number; valid_rows: number; invalid_rows: number };
  rows: MappingPreviewRow[];
};

export function matchMappingHeaders(
  headers: string[],
  fields: MappingField[],
): Record<string, MappingRule> {
  const rules: Record<string, MappingRule> = {};
  for (const field of fields) {
    const header = headers.find((item) => item.toLowerCase() === field.name.toLowerCase());
    if (header)
      rules[field.name] = { target: field.name, kind: 'source', source: header, transform: 'trim' };
  }
  return rules;
}
