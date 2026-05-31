<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StorePricingPackageRequest;
use App\Http\Requests\UpdatePricingPackageRequest;
use App\Http\Resources\PricingPackageResource;
use App\Models\PricingPackage;
use App\Services\PricingPackageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PricingPackageController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(PricingPackageService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PricingPackage::class);
        $packages = $this->service->getAll($request->all());
        return PricingPackageResource::collection($packages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePricingPackageRequest $request)
    {
        $this->authorize('create', PricingPackage::class);
        $package = $this->service->create($request->validated());
        return new PricingPackageResource($package);
    }

    /**
     * Display the specified resource.
     */
    public function show(PricingPackage $pricingPackage)
    {
        $this->authorize('view', $pricingPackage);
        return new PricingPackageResource($pricingPackage->loadMissing(['costType', 'items.costType']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePricingPackageRequest $request, PricingPackage $pricingPackage)
    {
        $this->authorize('update', $pricingPackage);
        $pricingPackage = $this->service->update($pricingPackage, $request->validated());
        return new PricingPackageResource($pricingPackage);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PricingPackage $pricingPackage)
    {
        $this->authorize('delete', $pricingPackage);
        $this->service->delete($pricingPackage);
        return response()->noContent();
    }

    public function importTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_paket_harga.csv"',
        ];

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nama_paket', 'kota_asal', 'kota_tujuan', 'harga', 'keterangan', 'is_active']);
            fputcsv($out, ['All In Avanza Bandung-Jakarta', 'Bandung', 'Jakarta', '1500000', 'Include: Driver, BBM, Tol', '1']);
            fputcsv($out, ['All In Innova Surabaya-Malang', 'Surabaya', 'Malang', '800000', '', '1']);
            fclose($out);
        }, 'template_import_paket_harga.csv', $headers);
    }

    public function import(Request $request)
    {
        $this->authorize('create', PricingPackage::class);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $result = $this->service->importFromCsv($request->file('file'));

        return response()->json([
            'message' => "Berhasil import {$result['imported']} paket, {$result['skipped']} dilewati.",
            'data'    => $result,
        ]);
    }
}
