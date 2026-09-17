<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\ToggleActiveRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prima zahtev, poziva servis, vraća odgovor — ništa drugo (PM-FR-07).
 *
 * `store` i `update` ne validiraju ovde: Laravel razreši Form Request pre ulaska u
 * metodu, pa neispravno telo nikada ne stigne dovde (PM-FR-41, PM-FR-50).
 *
 * `toggleActive` ima sopstveni Form Request: prekidač koji na prazno telo odgovara
 * isključivanjem metode i statusom `200` je tiši kvar od odbijenog zahteva.
 */
class PaymentMethodController extends Controller
{
    private const NOT_FOUND = 'Payment method not found.';

    public function __construct(
        private readonly PaymentMethodService $service,
    ) {
    }

    /** Dozvoljeni kodovi idu uz listu da forma ne bi slala drugi zahtev (PM-N-01). */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list(),
            'currencies' => $this->service->allowedCurrencies(),
            'countries' => $this->service->allowedCountries(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->orNotFound($this->service->find($id))]);
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->service->create($request->validated())],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdatePaymentMethodRequest $request, int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->orNotFound($this->service->update($id, $request->validated())),
        ]);
    }

    /** Odvojena putanja od brisanja duž cele linije — rute, servisa i repozitorijuma (PM-FR-69). */
    public function toggleActive(ToggleActiveRequest $request, int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->orNotFound($this->service->toggleActive($id, $request->boolean('active'))),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        abort_unless($this->service->delete($id), Response::HTTP_NOT_FOUND, self::NOT_FOUND);

        return response()->json(['message' => 'Payment method deleted.']);
    }

    /**
     * Nepostojeći `id` je HTTP ishod, ne poslovno pravilo, pa se prevodi ovde a ne u servisu —
     * servis ne sme znati za HTTP.
     */
    private function orNotFound(?array $method): array
    {
        abort_if($method === null, Response::HTTP_NOT_FOUND, self::NOT_FOUND);

        return $method;
    }
}
