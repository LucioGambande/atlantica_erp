<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesApiExceptions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CustomerController extends Controller
{
    use HandlesApiExceptions;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $customers = Customer::query()
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->when($request->filled('tax_id'), fn (Builder $query) => $query->where('tax_id', $request->string('tax_id')))
            ->when($request->filled('customer_type'), fn (Builder $query) => $query->where('customer_type', $request->string('customer_type')))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['data' => $customer]);
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
            $customer = Customer::create($validator->validated());

            return response()->json(['data' => $customer], 201);
        } catch (Throwable $exception) {
            return $this->respondToException($exception);
        }
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(updating: true));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $customer->update($validator->validated());

            return response()->json(['data' => $customer->fresh()]);
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
            'customer_type' => [$required, 'in:horeca,individual'],
            'fiscal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'fiscal_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'price_list_id' => ['nullable', 'integer', 'exists:price_lists,id'],
        ];
    }
}
