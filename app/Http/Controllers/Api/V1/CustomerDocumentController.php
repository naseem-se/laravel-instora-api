<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerDocumentRequest;
use App\Http\Resources\CustomerDocumentResource;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerDocumentController extends Controller
{
    public function index(Request $request, int $customerId, CompanyContext $context): JsonResponse
    {
        $customer = $this->customer($customerId, $context);
        $this->authorize('view', $customer);

        $documents = $customer->documents()->latest()->paginate(min(max($request->integer('per_page', 10), 1), 100));

        return ApiResponse::success(CustomerDocumentResource::collection($documents)->response()->getData(true));
    }

    public function store(StoreCustomerDocumentRequest $request, int $customerId, CompanyContext $context): JsonResponse
    {
        $customer = $this->customer($customerId, $context);
        $this->authorize('update', $customer);

        $document = new CustomerDocument([
            'document_type' => $request->string('document_type')->toString(),
            'document_number' => $request->input('document_number'),
            'issued_at' => $request->input('issued_at'),
            'expires_at' => $request->input('expires_at'),
            'file_path' => $request->file('file')->store('customer-documents', config('filesystems.documents_disk')),
            'storage_disk' => config('filesystems.documents_disk'),
        ]);
        $document->company_id = $context->requireCompanyId();
        $document->customer_id = $customer->id;
        $document->created_by = $request->user()->id;
        $document->save();

        return ApiResponse::success(new CustomerDocumentResource($document), 'Document uploaded successfully.', 201);
    }

    public function destroy(int $customerId, int $documentId, CompanyContext $context): JsonResponse
    {
        $customer = $this->customer($customerId, $context);
        $document = $customer->documents()->whereKey($documentId)->firstOrFail();
        /** @var CustomerDocument $document */
        $this->authorize('delete', $document);

        Storage::disk($document->storage_disk)->delete($document->file_path);
        $document->delete();

        return ApiResponse::success(null, 'Document deleted successfully.');
    }

    public function download(int $customerId, int $documentId, CompanyContext $context)
    {
        $customer = $this->customer($customerId, $context);
        $document = $customer->documents()->whereKey($documentId)->firstOrFail();
        /** @var CustomerDocument $document */
        $this->authorize('view', $customer);

        $fileName = basename($document->file_path);
        return Storage::disk($document->storage_disk)->response($document->file_path, $fileName);
    }

    private function customer(int $id, CompanyContext $context): Customer
    {
        return Customer::where('company_id', $context->requireCompanyId())->findOrFail($id);
    }
}