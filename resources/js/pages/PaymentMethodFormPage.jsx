import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { createPaymentMethod, updatePaymentMethod, validationErrors } from '../api/paymentMethods';
import FieldError from '../components/FieldError';
import FlashMessage from '../components/FlashMessage';
import ActiveSwitch from '../components/ActiveSwitch';
import MultiSelectTags from '../components/MultiSelectTags';
import TypeRadioGroup from '../components/TypeRadioGroup';

const LIST_PATH = '/admin/payment-methods';

const EMPTY_VALUES = {
    name: '',
    code: '',
    type: '',
    currencies: [],
    countries: [],
    sortOrder: '',
    active: false,
};

const toFormValues = (method) =>
    method
        ? {
              name: method.name,
              code: method.code,
              type: method.type,
              currencies: [...method.currencies],
              countries: [...method.countries],
              sortOrder: String(method.sortOrder),
              active: method.active,
          }
        : EMPTY_VALUES;

const labelClass = 'block text-sm font-medium text-gray-900';
const inputClass =
    'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500';

/** One form for both create and edit (PM-FR-30); the route decides which (PM-FR-31). */
export default function PaymentMethodFormPage({ methods, currencies, countries, onCreated, onUpdated }) {
    const { id } = useParams();
    const navigate = useNavigate();

    const isEdit = id !== undefined;
    const method = isEdit ? methods.find((item) => String(item.id) === id) : null;

    // Captured once, on mount: the title and the "has anything changed?" baseline.
    const [initialValues] = useState(() => toFormValues(method));
    const [values, setValues] = useState(initialValues);
    const [errors, setErrors] = useState({});
    const [saveError, setSaveError] = useState(null);
    const [saving, setSaving] = useState(false);

    if (isEdit && !method) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-10">
                <p className="text-sm text-gray-600">This payment method no longer exists.</p>
                <Link to={LIST_PATH} className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Back to payment methods
                </Link>
            </div>
        );
    }

    const isDirty = JSON.stringify(values) !== JSON.stringify(initialValues);
    const setField = (field, value) => setValues((previous) => ({ ...previous, [field]: value }));

    const handleSubmit = async (event) => {
        event.preventDefault();

        setErrors({});
        setSaveError(null);
        setSaving(true);

        const payload = {
            name: values.name,
            code: values.code,
            type: values.type,
            currencies: values.currencies,
            countries: values.countries,
            sortOrder: values.sortOrder === '' ? null : Number(values.sortOrder),
            active: values.active,
        };

        try {
            if (isEdit) {
                onUpdated(await updatePaymentMethod(method.id, payload));
            } else {
                onCreated(await createPaymentMethod(payload));
            }

            navigate(LIST_PATH);
        } catch (error) {
            // On 422 every message goes back to its own field and nothing typed is lost (PM-FR-59).
            const fieldErrors = validationErrors(error);

            if (fieldErrors) {
                setErrors(fieldErrors);
            } else {
                setSaveError('The payment method could not be saved. Please try again.');
            }

            setSaving(false);
        }
    };

    /** Only a changed form asks before leaving (PM-FR-42, PM-AC-16, PM-AC-17). */
    const handleCancel = () => {
        if (isDirty && !window.confirm('Discard your changes? Anything you entered will be lost.')) {
            return;
        }

        navigate(LIST_PATH);
    };

    return (
        <div className="mx-auto max-w-2xl px-6 py-10">
            <h1 className="mb-6 text-2xl font-semibold text-gray-900">
                {isEdit ? initialValues.name : 'New payment method'}
            </h1>

            <FlashMessage type="error" message={saveError} onDismiss={() => setSaveError(null)} />

            <form noValidate onSubmit={handleSubmit} className="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <label htmlFor="name" className={labelClass}>Name</label>
                    <input
                        id="name"
                        type="text"
                        value={values.name}
                        onChange={(event) => setField('name', event.target.value)}
                        aria-invalid={Boolean(errors.name)}
                        aria-describedby={errors.name ? 'name-error' : undefined}
                        className={inputClass}
                    />
                    <FieldError id="name-error" messages={errors.name} />
                </div>

                <div>
                    <label htmlFor="code" className={labelClass}>Code</label>
                    <input
                        id="code"
                        type="text"
                        value={values.code}
                        onChange={(event) => setField('code', event.target.value)}
                        aria-invalid={Boolean(errors.code)}
                        aria-describedby={errors.code ? 'code-error' : undefined}
                        className={inputClass}
                    />
                    <FieldError id="code-error" messages={errors.code} />
                </div>

                <fieldset>
                    <legend className={labelClass}>Type</legend>
                    <div className="mt-2">
                        <TypeRadioGroup
                            value={values.type}
                            onChange={(type) => setField('type', type)}
                            describedBy={errors.type ? 'type-error' : undefined}
                        />
                    </div>
                    <FieldError id="type-error" messages={errors.type} />
                </fieldset>

                <div>
                    <label htmlFor="currencies" className={labelClass}>Currencies</label>
                    <div className="mt-1">
                        <MultiSelectTags
                            id="currencies"
                            options={currencies}
                            value={values.currencies}
                            onChange={(next) => setField('currencies', next)}
                            describedBy={errors.currencies ? 'currencies-error' : undefined}
                            addLabel="Add a currency…"
                        />
                    </div>
                    <FieldError id="currencies-error" messages={errors.currencies} />
                </div>

                <div>
                    <label htmlFor="countries" className={labelClass}>Countries</label>
                    <div className="mt-1">
                        <MultiSelectTags
                            id="countries"
                            options={countries}
                            value={values.countries}
                            onChange={(next) => setField('countries', next)}
                            describedBy={errors.countries ? 'countries-error' : undefined}
                            addLabel="Add a country…"
                        />
                    </div>
                    <FieldError id="countries-error" messages={errors.countries} />
                </div>

                <div>
                    <label htmlFor="sortOrder" className={labelClass}>Sort order</label>
                    <input
                        id="sortOrder"
                        type="number"
                        step="1"
                        value={values.sortOrder}
                        onChange={(event) => setField('sortOrder', event.target.value)}
                        aria-invalid={Boolean(errors.sortOrder)}
                        aria-describedby={errors.sortOrder ? 'sortOrder-error' : undefined}
                        className={`${inputClass} max-w-32`}
                    />
                    <FieldError id="sortOrder-error" messages={errors.sortOrder} />
                </div>

                <div>
                    <span className={labelClass}>Active</span>
                    <div className="mt-2">
                        <ActiveSwitch
                            checked={values.active}
                            onChange={(active) => setField('active', active)}
                            label="Active"
                        />
                    </div>
                    <FieldError id="active-error" messages={errors.active} />
                </div>

                <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                    <button
                        type="button"
                        onClick={handleCancel}
                        className="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="cursor-pointer rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {saving ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </form>
        </div>
    );
}
