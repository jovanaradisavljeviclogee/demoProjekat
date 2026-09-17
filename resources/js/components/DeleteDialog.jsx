import { useEffect, useRef } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * The only path to a DELETE request (PM-FR-60, PM-C-03).
 * Modal by PM-FR-65 and PM-N-05: the page behind stays visible but unreachable,
 * focus is trapped inside, and Esc cancels.
 */
export default function DeleteDialog({ method, busy = false, onCancel, onConfirm }) {
    const dialogRef = useRef(null);
    const cancelRef = useRef(null);

    useEffect(() => {
        const previouslyFocused = document.activeElement;
        const previousOverflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';
        cancelRef.current?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;

            const restoreTo =
                previouslyFocused instanceof HTMLElement && previouslyFocused.isConnected
                    ? previouslyFocused
                    : document.querySelector('a[href="/admin/payment-methods/create"]');

            restoreTo?.focus();
        };
    }, []);

    useEffect(() => {
        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();

                if (!busy) {
                    onCancel();
                }

                return;
            }

            if (event.key !== 'Tab' || !dialogRef.current) {
                return;
            }

            const focusable = Array.from(dialogRef.current.querySelectorAll(FOCUSABLE));

            if (focusable.length === 0) {
                event.preventDefault();
                dialogRef.current.focus();

                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            } else if (!dialogRef.current.contains(document.activeElement)) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown, true);

        return () => document.removeEventListener('keydown', handleKeyDown, true);
    }, [busy, onCancel]);

    return (
        // The overlay swallows clicks: the screen behind stays visible but inert (PM-AC-20).
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div
                ref={dialogRef}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-dialog-title"
                aria-describedby="delete-dialog-description"
                className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
            >
                <h2 id="delete-dialog-title" className="text-lg font-semibold text-gray-900">
                    Delete payment method?
                </h2>

                <div id="delete-dialog-description" className="mt-3 space-y-2 text-sm text-gray-600">
                    <p>
                        <strong className="font-semibold text-gray-900">{method.name}</strong> ({method.code}) will be
                        permanently removed, together with its currency and country assignments.
                    </p>
                    <p>This action cannot be undone.</p>
                </div>

                {/* Right aligned, Cancel secondary, destructive Delete to its right (PM-FR-64). */}
                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        ref={cancelRef}
                        onClick={onCancel}
                        disabled={busy}
                        className="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={busy}
                        className="cursor-pointer rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {busy ? 'Deleting…' : 'Delete'}
                    </button>
                </div>
            </div>
        </div>
    );
}
