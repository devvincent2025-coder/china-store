/**
 * Mirrors Orders/Index.jsx's table row structure (order #, date, total, status badge, view link)
 * so the table doesn't visibly reflow once real rows swap in.
 */
export default function OrderTableSkeleton({ rows = 6 }) {
    return (
        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
            <table className="w-full text-sm">
                <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                    <tr>
                        <th className="px-4 py-3">Order #</th>
                        <th className="px-4 py-3">Date</th>
                        <th className="px-4 py-3">Total</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3" />
                    </tr>
                </thead>
                <tbody className="animate-pulse divide-y divide-neutral-100">
                    {Array.from({ length: rows }).map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-3"><div className="h-3 w-24 rounded bg-neutral-200" /></td>
                            <td className="px-4 py-3"><div className="h-3 w-20 rounded bg-neutral-200" /></td>
                            <td className="px-4 py-3"><div className="h-3 w-16 rounded bg-neutral-200" /></td>
                            <td className="px-4 py-3"><div className="h-5 w-16 rounded-full bg-neutral-200" /></td>
                            <td className="px-4 py-3 text-right"><div className="ml-auto h-3 w-10 rounded bg-neutral-200" /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
