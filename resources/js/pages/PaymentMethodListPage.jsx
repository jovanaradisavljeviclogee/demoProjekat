import { useState } from 'react';
import { Link } from 'react-router-dom';
import { deletePaymentMethod, setPaymentMethodActive } from '../api/paymentMethods';
import DeleteDialog from '../components/DeleteDialog';
import EmptyState from '../components/EmptyState';
import FlashMessage from '../components/FlashMessage';
import PaymentMethodTable from '../components/PaymentMethodTable';

/** The list screen: table or empty state, the immediate switch, and the delete dialog. */
export default function PaymentMethodListPage({ methods, onMethodChanged, onMethodRemoved }) {
    const [flash, setFlash] = useState(null);
    const [pendingIds, setPendingIds] = useState(() => new Set());
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const markPending = (id, pending) =>
        setPendingIds((previous) => {
            const next = new Set(previous);

            if (pending) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });

    /** Deactivating is its own request; it never touches the delete path (PM-FR-69). */
    const handleToggleActive = async (method, active) => {
        markPending(method.id, true);

        try {
            onMethodChanged(await setPaymentMethodActive(method.id, active));
            setFlash(null);
        } catch {
            setFlash({ type: 'error', message: `${method.name} could not be updated. Please try again.` });
        } finally {
            markPending(method.id, false);
        }
    };

    const handleConfirmDelete = async () => {
        setDeleting(true);

        try {
            const message = await deletePaymentMethod(deleteTarget.id);

            onMethodRemoved(deleteTarget.id);
            setDeleteTarget(null);
            setFlash({ type: 'success', message });
        } catch {
            setFlash({ type: 'error', message: `${deleteTarget.name} could not be deleted. Please try again.` });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    return (
        <div className="mx-auto max-w-6xl px-6 py-10">
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold text-gray-900">Payment methods</h1>
                {methods.length > 0 && (
                    <Link
                        to="/admin/payment-methods/create"
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        Add payment method
                    </Link>
                )}
            </div>

            <FlashMessage type={flash?.type} message={flash?.message} onDismiss={() => setFlash(null)} />

            {methods.length === 0 ? (
                <EmptyState />
            ) : (
                <PaymentMethodTable
                    methods={methods}
                    pendingIds={pendingIds}
                    onToggleActive={handleToggleActive}
                    onDelete={setDeleteTarget}
                />
            )}

            {deleteTarget && (
                <DeleteDialog
                    method={deleteTarget}
                    busy={deleting}
                    onCancel={() => setDeleteTarget(null)}
                    onConfirm={handleConfirmDelete}
                />
            )}
        </div>
    );
}
