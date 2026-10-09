<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeSales($request, 'sales.view');
        $query = Sale::query()
            ->with(['customer', 'items.warehouse', 'items.productItem', 'invoice', 'installmentPlan', 'returns'])
            ->where('company_id', $context->requireCompanyId());

        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('payment_type')) $query->where('payment_type', $request->input('payment_type'));
        if ($request->filled('customer_id')) $query->where('customer_id', $request->integer('customer_id'));
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($q) => $q->where('sale_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                ->orWhereHas('items', fn ($item) => $item->where('product_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")));
        }

        $sales = $query->latest('id')->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success(SaleResource::collection($sales)->response()->getData(true));
    }

    public function store(StoreSaleRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeSales($request, 'sales.create');
        $data = $request->validated();
        $receipt = $data['receipt'] ?? null;
        unset($data['receipt']);

        $receiptPath = null;
        try {
            if ($receipt) {
                $receiptDisk = config('filesystems.documents_disk');
                $receiptPath = $receipt->store('sales/'.$context->requireCompanyId().'/receipts', $receiptDisk);
                if (! $receiptPath) {
                    throw new RuntimeException('The uploaded sale receipt could not be stored.');
                }
                $data['receipt_path'] = $receiptPath;
                $data['receipt_disk'] = $receiptDisk;
            }

            $sale = $this->sales->create($data, $context, $request->user());
        } catch (Throwable $exception) {
            if ($receiptPath) {
                Storage::disk(config('filesystems.documents_disk'))->delete($receiptPath);
            }
            throw $exception;
        }

        return ApiResponse::success(new SaleResource($sale), 'Sale created successfully.', 201);
    }

    public function show(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeSales($request, 'sales.view');
        $sale = $this->findSale($id, $context);

        return ApiResponse::success(new SaleResource($sale->load([
            'customer', 'items.warehouse', 'items.productItem', 'invoice.items', 'installmentPlan.installments', 'returns.items',
        ])));
    }

    public function cancel(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeSales($request, 'sales.manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $sale = $this->sales->cancel($this->findSale($id, $context), $reason, $request->user());

        return ApiResponse::success(new SaleResource($sale), 'Sale cancelled and stock restored.');
    }

    public function return(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeSales($request, 'sales.manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $sale = $this->sales->return($this->findSale($id, $context), $reason, $request->user());

        return ApiResponse::success(new SaleResource($sale), 'Sale returned and stock restored.');
    }

    public function invoice(Request $request, int $id, CompanyContext $context): Response
    {
        $this->authorizeSales($request, 'sales.view');
        $sale = $this->findSale($id, $context)->load(['customer', 'items', 'invoice.items', 'installmentPlan']);
        abort_if(! $sale->invoice, 404, 'No invoice exists until an installment sale is approved.');

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $sale->invoice,
            'sale' => $sale,
            'plan' => $sale->installmentPlan,
            'customer' => $sale->customer,
            'company' => $sale->company,
        ]);

        return $pdf->stream("{$sale->invoice->invoice_number}.pdf");
    }

    public function agreement(Request $request, int $id, CompanyContext $context): Response
    {
        $this->authorizeSales($request, 'sales.view');
        $sale = $this->findSale($id, $context)->load(['customer', 'company', 'items', 'installmentPlan.installments']);
        abort_if(! $sale->installmentPlan, 404, 'This sale has no installment agreement.');

        $pdf = Pdf::loadView('pdf.sale-agreement', [
            'sale' => $sale,
            'plan' => $sale->installmentPlan,
            'customer' => $sale->customer,
            'company' => $sale->company,
        ]);

        return $pdf->stream("agreement_{$sale->sale_number}.pdf");
    }

    public function receiptAttachment(Request $request, int $id, CompanyContext $context): StreamedResponse
    {
        $this->authorizeSales($request, 'sales.view');
        $sale = $this->findSale($id, $context);

        abort_unless($sale->receipt_path && $sale->receipt_disk, 404);

        $disk = Storage::disk($sale->receipt_disk);
        abort_unless($disk->exists($sale->receipt_path), 404);

        $extension = pathinfo($sale->receipt_path, PATHINFO_EXTENSION);
        $filename = $sale->sale_number.($extension ? ".{$extension}" : '');

        return $disk->response($sale->receipt_path, $filename, [
            'Content-Type' => $disk->mimeType($sale->receipt_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function findSale(int $id, CompanyContext $context): Sale
    {
        return Sale::query()->where('company_id', $context->requireCompanyId())->findOrFail($id);
    }

    private function authorizeSales(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}