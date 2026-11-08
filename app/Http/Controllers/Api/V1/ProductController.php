<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductController extends Controller
{
    use HandlesApiExceptions;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $products = Product::query()
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->when($request->filled('sku'), fn (Builder $query) => $query->where('sku', $request->string('sku')))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $product]);
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
            // El stock no se acepta por API: un producto nuevo siempre
            // arranca en 0 y entra mercadería real vía una compra, nunca
            // escribiendo el campo a mano (ver StockService).
            $product = Product::create([...$validator->validated(), 'stock' => 0]);

            return response()->json(['data' => $product], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(product: $product, updating: true));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $product->update($validator->validated());

            return response()->json(['data' => $product->fresh()]);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?Product $product = null, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'sku' => [
                $required, 'string', 'max:255',
                'unique:products,sku'.($product !== null ? ','.$product->id : ''),
            ],
            'purchase_price' => [$required, 'numeric', 'min:0'],
            'sale_price' => [$required, 'numeric', 'min:0'],
        ];
    }
}
