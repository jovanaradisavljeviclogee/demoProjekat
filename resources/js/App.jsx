import { useCallback, useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { fetchPaymentMethods } from './api/paymentMethods';
import PaymentMethodListPage from './pages/PaymentMethodListPage';
import PaymentMethodFormPage from './pages/PaymentMethodFormPage';

const LIST_PATH = '/admin/payment-methods';

const bySortOrder = (a, b) => a.sortOrder - b.sortOrder;

/**
 * Remounts the form when the route switches between create and a given id, so
 * the form always starts from a fresh copy of its own initial values.
 */
function FormRoute(props) {
    const { id } = useParams();

    return <PaymentMethodFormPage key={id ?? 'create'} {...props} />;
}

/**
 * Owns the one list request of the session (PM-N-01): the allowed currency and
 * country codes arrive with it, so opening the form needs no second request.
 */
export default function App() {
    const [methods, setMethods] = useState([]);
    const [currencies, setCurrencies] = useState([]);
    const [countries, setCountries] = useState([]);
    const [status, setStatus] = useState('loading');

    useEffect(() => {
        let cancelled = false;

        fetchPaymentMethods()
            .then((payload) => {
                if (cancelled) {
                    return;
                }

                setMethods([...payload.methods].sort(bySortOrder));
                setCurrencies(payload.currencies);
                setCountries(payload.countries);
                setStatus('ready');
            })
            .catch(() => {
                if (!cancelled) {
                    setStatus('failed');
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const replaceMethod = useCallback((method) => {
        setMethods((previous) => previous.map((item) => (item.id === method.id ? method : item)).sort(bySortOrder));
    }, []);

    const addMethod = useCallback((method) => {
        setMethods((previous) => [...previous, method].sort(bySortOrder));
    }, []);

    const removeMethod = useCallback((id) => {
        setMethods((previous) => previous.filter((item) => item.id !== id));
    }, []);

    if (status === 'loading') {
        return <p className="p-8 text-sm text-gray-500">Loading payment methods…</p>;
    }

    if (status === 'failed') {
        return (
            <p className="p-8 text-sm text-red-600" role="alert">
                Payment methods could not be loaded. Please reload the page.
            </p>
        );
    }

    return (
        <BrowserRouter>
            <Routes>
                <Route
                    path={LIST_PATH}
                    element={
                        <PaymentMethodListPage
                            methods={methods}
                            onMethodChanged={replaceMethod}
                            onMethodRemoved={removeMethod}
                        />
                    }
                />
                <Route
                    path={`${LIST_PATH}/create`}
                    element={
                        <FormRoute
                            methods={methods}
                            currencies={currencies}
                            countries={countries}
                            onCreated={addMethod}
                            onUpdated={replaceMethod}
                        />
                    }
                />
                <Route
                    path={`${LIST_PATH}/:id/edit`}
                    element={
                        <FormRoute
                            methods={methods}
                            currencies={currencies}
                            countries={countries}
                            onCreated={addMethod}
                            onUpdated={replaceMethod}
                        />
                    }
                />
                <Route path="*" element={<Navigate to={LIST_PATH} replace />} />
            </Routes>
        </BrowserRouter>
    );
}
