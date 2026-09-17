/**
 * Multiple choice out of a fixed list of codes, shown as removable tags
 * (PM-FR-37). The offer comes from the allowed codes in the index response.
 */
export default function MultiSelectTags({ id, options, value, onChange, describedBy, addLabel = 'Add…' }) {
    const available = options.filter((option) => !value.includes(option));

    const add = (event) => {
        const code = event.target.value;

        event.target.value = '';

        if (code && !value.includes(code)) {
            onChange([...value, code]);
        }
    };

    const remove = (code) => onChange(value.filter((item) => item !== code));

    return (
        <div className="space-y-2">
            {value.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {value.map((code) => (
                        <li
                            key={code}
                            className="inline-flex items-center gap-1 rounded-full bg-indigo-50 py-1 pr-1 pl-3 text-sm font-medium text-indigo-700"
                        >
                            {code}
                            <button
                                type="button"
                                onClick={() => remove(code)}
                                aria-label={`Remove ${code}`}
                                className="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full leading-none text-indigo-500 hover:bg-indigo-100 hover:text-indigo-900"
                            >
                                &times;
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <select
                id={id}
                value=""
                onChange={add}
                aria-describedby={describedBy}
                disabled={available.length === 0}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400"
            >
                <option value="">{available.length === 0 ? 'All options selected' : addLabel}</option>
                {available.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </div>
    );
}
