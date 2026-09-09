import type { ReactNode } from 'react';

type DataTableProps = {
  columns: string[];
  emptyState?: ReactNode;
};

export function DataTable({ columns, emptyState }: DataTableProps) {
  return (
    <div className="table-shell">
      <table>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column}>{column}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {emptyState ? (
            <tr>
              <td colSpan={columns.length}>{emptyState}</td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  );
}
