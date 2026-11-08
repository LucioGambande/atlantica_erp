<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WarehouseController extends Controller
{
    use HandlesApiExceptions;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($warehouses);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json(['data' => $warehouse]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $warehouse = Warehouse::create($validator->validated());

            return response()->json(['data' => $warehouse], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(updating: true));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $warehouse->update($validator->validated());

            return response()->json(['data' => $warehouse->fresh()]);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'type' => [$required, 'in:'.implode(',', array_keys(Warehouse::TYPES))],
            'is_active' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string'],
        ];
    }
}
