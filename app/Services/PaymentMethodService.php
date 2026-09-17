<?php

namespace App\Services;

use App\Repositories\PaymentMethodRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Jedina zavisnost kontrolera (PM-FR-07).
 *
 * Zavisi od interfejsa, ne od implementacije: zamena statičkog niza bazom ne sme
 * da dotakne ovaj fajl (PM-FR-04, PM-FR-06). Ovde nema validacije — ona je u Form
 * Requestu, koji se razrešava pre ulaska u kontroler (PM-FR-50).
 */
class PaymentMethodService
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $repository,
    ) {
    }

    /**
     * Repozitorijum vraća nepoređano jer redosled nije svojstvo podataka nego prikaza;
     * rastući `sortOrder` je redosled kojim se metode nude kupcu (PM-FR-12).
     */
    public function list(): array
    {
        $methods = $this->repository->all();

        usort($methods, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $methods;
    }

    /** Stižu uz listu da forma ne bi slala drugi zahtev (PM-N-01). */
    public function allowedCurrencies(): array
    {
        return $this->repository->allowedCurrencies();
    }

    public function allowedCountries(): array
    {
        return $this->repository->allowedCountries();
    }

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $attributes): array
    {
        return $this->repository->create($attributes);
    }

    public function update(int $id, array $attributes): ?array
    {
        return $this->repository->update($id, $attributes);
    }

    /**
     * Menja isključivo `active` i nikada ne poziva `delete`: isključena metoda ostaje
     * u listi (PM-FR-14, PM-FR-69, PM-C-04). Zato i ne loguje — PM-AC-24 traži da se
     * deaktivacija ne vidi kao brisanje.
     */
    public function toggleActive(int $id, bool $active): ?array
    {
        return $this->repository->setActive($id, $active);
    }

    /**
     * Nikada ne poziva `toggleActive` (PM-FR-69, PM-C-04).
     *
     * Metoda se čita pre brisanja jer log traži `name` i `code`, kojih posle uklanjanja
     * više nema. Log se upisuje tek kad je brisanje uspelo (PM-FR-68, PM-N-06).
     */
    public function delete(int $id): bool
    {
        $method = $this->repository->find($id);

        if ($method === null || ! $this->repository->delete($id)) {
            return false;
        }

        Log::info('Payment method deleted', [
            'id' => $method['id'],
            'name' => $method['name'],
            'code' => $method['code'],
        ]);

        return true;
    }
}
