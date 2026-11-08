<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Solo lectura: alcanza para resolver supplier_id al cargar una
     * compra. Alta/edición de proveedores queda fuera de esta API.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $suppliers = Supplier::query()
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($suppliers);
    }
}
