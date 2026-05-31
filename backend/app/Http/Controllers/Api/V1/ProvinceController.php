<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinceResource;
use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index(Request $request)
    {
        $query = Province::query();

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        return ProvinceResource::collection(
            $query->orderBy('nama')->paginate($request->input('per_page', 20))
        );
    }
}
