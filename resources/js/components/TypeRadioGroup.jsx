const TYPES = [
    { value: 'redirect', label: 'Redirect' },
    { value: 'iframe', label: 'iFrame' },
];

/** The `type` field: two radio buttons, stored as `redirect` / `iframe` (PM-FR-36). */
export default function TypeRadioGroup({ value, onChange, describedBy, disabled = false }) {
    return (
        <div role="radiogroup" aria-describedby={describedBy} className="flex gap-6">
            {TYPES.map((type) => (
                <label key={type.value} className="flex cursor-pointer items-center gap-2 text-sm text-gray-900">
                    <input
                        type="radio"
                        name="type"
                        value={type.value}
                        checked={value === type.value}
                        disabled={disabled}
                        onChange={() => onChange(type.value)}
                        className="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    {type.label}
                </label>
            ))}
        </div>
    );
}
