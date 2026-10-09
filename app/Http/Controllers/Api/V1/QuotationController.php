<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function index(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.view');

        $query = Quotation::query()
            ->with(['customer', 'items'])
            ->where('company_id', $context->requireCompanyId());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($q) => $q->where('quotation_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        $quotations = $query->latest('id')->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success($quotations);
    }

    public function store(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.create');

        $companyId = $context->requireCompanyId();

        $data = $request->validate([
            'customer_id' => ['nullable', 'required_without:new_customer', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'new_customer' => ['nullable', 'required_without:customer_id', 'array'],
            'new_customer.name' => ['required_with:new_customer', 'string', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:50'],
            'new_customer.cnic' => ['nullable', 'string', 'max:50'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.address' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $quotation = $this->quotations->create($data, $context, $request->user());

        return ApiResponse::success($quotation, 'Quotation created successfully.', 201);
    }

    public function show(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.view');
        $quotation = $this->findQuotation($id, $context);

        return ApiResponse::success($quotation->load(['customer', 'items.product']));
    }

    public function convertToSale(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.create');
        $quotation = $this->findQuotation($id, $context);

        $data = $request->validate([
            'payment_type' => ['required', Rule::in(['cash', 'installment'])],
            'payment_method' => ['required', 'string'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer'],
            'product_item_id' => ['nullable', 'integer'],
            'installment' => ['required_if:payment_type,installment', 'array'],
            'installment.start_date' => ['required_if:payment_type,installment', 'date'],
            'installment.financial_charge_type' => ['required_if:payment_type,installment', 'string'],
            'installment.interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'installment.late_fee_type' => ['required_if:payment_type,installment', 'string'],
            'installment.late_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installment.late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'installment.maximum_late_fee' => ['nullable', 'numeric', 'min:0'],
            'installment.installment_frequency' => ['required_if:payment_type,installment', 'string'],
            'installment.custom_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'installment.number_of_installments' => ['required_if:payment_type,installment', 'integer', 'min:1', 'max:360'],
        ]);

        $sale = $this->quotations->convertToSale($quotation, $data, $context, $request->user());

        return ApiResponse::success($sale, 'Quotation converted to sale successfully.');
    }

    public function updateStatus(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.manage');
        $quotation = $this->findQuotation($id, $context);

        $data = $request->validate([
            'status' => ['required', Rule::in(['sent', 'rejected', 'expired'])],
        ]);

        $quotation = $this->quotations->updateStatus($quotation, $data['status']);

        return ApiResponse::success($quotation, 'Quotation status updated.');
    }

    public function destroy(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeQuotations($request, 'sales.manage');
        $quotation = $this->findQuotation($id, $context);

        if ($quotation->status === 'accepted') {
            abort(422, 'Cannot delete an accepted quotation.');
        }

        $quotation->delete();

        return ApiResponse::success(null, 'Quotation deleted successfully.');
    }

    private function findQuotation(int $id, CompanyContext $context): Quotation
    {
        return Quotation::query()->where('company_id', $context->requireCompanyId())->findOrFail($id);
    }

    private function authorizeQuotations(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}
