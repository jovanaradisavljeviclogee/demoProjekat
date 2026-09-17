import { Link } from 'react-router-dom';
import ActiveSwitch from './ActiveSwitch';

const TYPE_LABELS = {
    redirect: 'Redirect',
    iframe: 'iFrame',
};

const codeList = (codes) => (codes && codes.length > 0 ? codes.join(', ') : '—');

/** The list table. Column order is fixed by PM-FR-11 and must not be rearranged. */
export default function PaymentMethodTable({ methods, pendingIds, onToggleActive, onDelete }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase">
                    <tr>
                        <th scope="col" className="px-4 py-3">Order</th>
                        <th scope="col" className="px-4 py-3">Name</th>
                        <th scope="col" className="px-4 py-3">Code</th>
                        <th scope="col" className="px-4 py-3">Type</th>
                        <th scope="col" className="px-4 py-3">Currencies</th>
                        <th scope="col" className="px-4 py-3">Countries</th>
                        <th scope="col" className="px-4 py-3">Active</th>
                        <th scope="col" className="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {methods.map((method) => (
                        // Switching a method off never removes its row; it only dims it (PM-FR-14, PM-FR-15).
                        <tr key={method.id} className={method.active ? 'text-gray-900' : 'text-gray-900 opacity-50'}>
                            <td className="px-4 py-3 tabular-nums">{method.sortOrder}</td>
                            <td className="px-4 py-3 font-medium">{method.name}</td>
                            <td className="px-4 py-3 font-mono text-xs text-gray-600">{method.code}</td>
                            <td className="px-4 py-3">{TYPE_LABELS[method.type] ?? method.type}</td>
                            <td className="px-4 py-3">{codeList(method.currencies)}</td>
                            <td className="px-4 py-3">{codeList(method.countries)}</td>
                            <td className="px-4 py-3">
                                <ActiveSwitch
                                    checked={method.active}
                                    disabled={pendingIds.has(method.id)}
                                    label={`${method.active ? 'Deactivate' : 'Activate'} ${method.name}`}
                                    onChange={(next) => onToggleActive(method, next)}
                                />
                            </td>
                            <td className="px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <Link
                                        to={`/admin/payment-methods/${method.id}/edit`}
                                        className="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Edit
                                    </Link>
                                    {/* Opens the confirmation dialog only — nothing is deleted here (PM-FR-18). */}
                                    <button
                                        type="button"
                                        onClick={() => onDelete(method)}
                                        className="cursor-pointer font-medium text-red-600 hover:text-red-800"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
