/** One validation message block, rendered directly under its own field (PM-FR-58). */
export default function FieldError({ id, messages }) {
    if (!messages || messages.length === 0) {
        return null;
    }

    return (
        <ul id={id} role="alert" className="mt-1 space-y-0.5 text-sm text-red-600">
            {messages.map((message, index) => (
                <li key={index}>{message}</li>
            ))}
        </ul>
    );
}
