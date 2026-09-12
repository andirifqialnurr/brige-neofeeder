import type { ReactNode } from 'react';

type DataTableProps = {
  columns: string[];
  emptyState?: ReactNode;
  rows?: ReactNode[][];
};

export function DataTable({ columns, emptyState, rows = [] }: DataTableProps) {
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
          {rows.length > 0 ? (
            rows.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {row.map((cell, cellIndex) => (
                  <td key={`${rowIndex}-${cellIndex}`}>{cell}</td>
                ))}
              </tr>
            ))
          ) : emptyState ? (
            <tr>
              <td colSpan={columns.length}>{emptyState}</td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  );
}
