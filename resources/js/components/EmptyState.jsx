import { Link } from 'react-router-dom';

/** Replaces the table when no payment method exists (PM-FR-19, PM-FR-20). */
export default function EmptyState() {
    return (
        <div className="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
            <h2 className="text-base font-semibold text-gray-900">No payment methods yet</h2>
            <p className="mx-auto mt-2 max-w-md text-sm text-gray-500">
                Payment methods decide what a customer can pay with at checkout. Add the first one to get started.
            </p>
            <Link
                to="/admin/payment-methods/create"
                className="mt-6 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            >
                Add payment method
            </Link>
        </div>
    );
}
