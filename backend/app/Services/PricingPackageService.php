<?php

namespace App\Services;

use App\Models\PricingPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PricingPackageService
{
    public function getAll(array $filters = [])
    {
        $query = PricingPackage::query()
            ->with(['costType', 'items.costType'])
            ->where('tenant_id', Auth::user()->tenant_id);

        // Branch scope
        if (isset($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $query->where('branch_id', $filters['branch_id']);
        } else if (Auth::user()->role !== 'superadmin') {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama_paket', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('keterangan', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['tenant_id'] = Auth::user()->tenant_id;

            if (!isset($data['branch_id'])) {
                $data['branch_id'] = Auth::user()->branch_id;
            }

            $package = PricingPackage::create($data);
            $this->syncItems($package, $items);

            return $package->load(['costType', 'items.costType']);
        });
    }

    public function update(PricingPackage $pricingPackage, array $data)
    {
        return DB::transaction(function () use ($pricingPackage, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $pricingPackage->update($data);

            if (is_array($items)) {
                $this->syncItems($pricingPackage, $items);
            }

            return $pricingPackage->load(['costType', 'items.costType']);
        });
    }

    public function delete(PricingPackage $pricingPackage)
    {
        return $pricingPackage->delete();
    }

    public function importFromCsv($file): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $handle = fopen($file->getRealPath(), 'r');

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['File kosong atau format tidak valid']];
        }

        $header = array_map('trim', $header);
        $required = ['nama_paket', 'harga'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                return ['imported' => 0, 'skipped' => 0, 'errors' => ["Kolom wajib '$col' tidak ditemukan"]];
            }
        }

        $row = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $record = array_combine($header, array_pad($data, count($header), null));

            if (empty(trim($record['nama_paket'] ?? ''))) {
                $skipped++;
                continue;
            }

            $harga = intval(str_replace([',', '.', ' '], '', $record['harga'] ?? 0));

            try {
                DB::transaction(function () use ($record, $harga) {
                    $package = PricingPackage::create([
                        'tenant_id'   => Auth::user()->tenant_id,
                        'branch_id'   => Auth::user()->branch_id,
                        'nama_paket'  => trim($record['nama_paket']),
                        'kota_asal'   => trim($record['kota_asal'] ?? '') ?: null,
                        'kota_tujuan' => trim($record['kota_tujuan'] ?? '') ?: null,
                        'harga'       => $harga,
                        'keterangan'  => trim($record['keterangan'] ?? '') ?: null,
                        'is_active'   => isset($record['is_active']) ? filter_var($record['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
                    ]);
                });
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Baris $row: " . $e->getMessage();
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    protected function syncItems(PricingPackage $pricingPackage, array $items): void
    {
        $pricingPackage->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $pricingPackage->items()->create([
                'cost_type_id' => $item['cost_type_id'] ?? null,
                'type'         => $item['type'] ?? 'biaya',
                'label'        => $item['label'],
                'amount'       => $item['amount'],
                'keterangan'   => $item['keterangan'] ?? null,
                'sort_order'   => $index + 1,
            ]);
        }
    }
}
