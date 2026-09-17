const STYLES = {
    success: 'border-green-300 bg-green-50 text-green-800',
    error: 'border-red-300 bg-red-50 text-red-800',
};

/** Short-lived banner above the screen content: delete succeeded, or a request failed. */
export default function FlashMessage({ type = 'success', message, onDismiss }) {
    if (!message) {
        return null;
    }

    return (
        <div
            role={type === 'error' ? 'alert' : 'status'}
            className={`mb-4 flex items-start justify-between gap-4 rounded-md border px-4 py-3 text-sm ${STYLES[type]}`}
        >
            <span>{message}</span>
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Dismiss message"
                    className="shrink-0 cursor-pointer text-lg leading-none opacity-60 hover:opacity-100"
                >
                    &times;
                </button>
            )}
        </div>
    );
}
