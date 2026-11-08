<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SupplierController extends Controller
{
    use HandlesApiExceptions;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $suppliers = Supplier::query()
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($suppliers);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $supplier = Supplier::create($validator->validated());

            return response()->json(['data' => $supplier], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }
}
