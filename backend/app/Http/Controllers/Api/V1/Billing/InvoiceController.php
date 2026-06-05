<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\PayInvoiceRequest;
use App\Http\Requests\Api\V1\Billing\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\Billing\VoidInvoiceRequest;
use App\Http\Resources\Invoices\InvoiceResource;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\OwnershipScopeService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvoiceController extends BaseController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly OwnershipScopeService $ownershipScopeService,
    ) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Invoice::query()->with('items')->latest();

        if (! $user->isAdmin() && ! $user->hasPermission('billing.invoices.view')) {
            $query->where('payer_user_id', $user->id);
        }

        return $this->paginatedResponse(
            $query->paginate(15),
            'Invoices fetched successfully.',
            resourceClass: InvoiceResource::class,
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payer = $request->integer('payer_user_id') > 0
            ? User::query()->findOrFail($request->integer('payer_user_id'))
            : $actor;

        try {
            $invoice = $this->invoiceService->createDraftInvoice($payer, $request->input('items', []), [
                'idempotency_key' => (string) $request->header('Idempotency-Key'),
                'company_id' => $request->integer('company_id') ?: null,
                'seller_id' => $request->integer('seller_id') ?: null,
                'subscription_id' => $request->integer('subscription_id') ?: null,
                'currency' => strtoupper((string) $request->input('currency')),
                'description' => $request->input('description'),
                'due_at' => $request->input('due_at'),
                'metadata' => (array) $request->input('metadata', []),
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Invoice creation failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse((new InvoiceResource($invoice))->resolve(), 'Invoice created successfully.', 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $this->ownershipScopeService->canActorAccessInvoice($user, $invoice)) {
            return $this->errorResponse('Invoice not found.', ['code' => 'invoice_not_found'], 404);
        }

        return $this->successResponse((new InvoiceResource($invoice->load('items')))->resolve(), 'Invoice fetched successfully.');
    }

    public function issue(Invoice $invoice): JsonResponse
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $invoice = $this->invoiceService->issueInvoice($invoice, $actor);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Invoice issue failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse((new InvoiceResource($invoice))->resolve(), 'Invoice issued successfully.');
    }

    public function void(Invoice $invoice, VoidInvoiceRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $invoice = $this->invoiceService->voidInvoice($invoice, $actor, (string) $request->input('reason'));
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Invoice void failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse((new InvoiceResource($invoice))->resolve(), 'Invoice voided successfully.');
    }

    public function pay(Invoice $invoice, PayInvoiceRequest $request): JsonResponse
    {
        /** @var User $payer */
        $payer = $request->user();

        if (! $this->ownershipScopeService->canActorAccessInvoice($payer, $invoice)) {
            return $this->errorResponse('Invoice not found.', ['code' => 'invoice_not_found'], 404);
        }

        try {
            $payment = $this->invoiceService->createPaymentForInvoice($invoice, $payer, [
                'idempotency_key' => (string) $request->header('Idempotency-Key'),
                'payment_source' => $request->input('payment_source'),
                'payment_strategy' => $request->input('payment_strategy'),
                'payment_method_id' => $request->integer('payment_method_id') ?: null,
                'currency' => $request->input('currency'),
                'callback_url' => $request->input('callback_url'),
                'description' => $request->input('description'),
                'metadata' => (array) $request->input('metadata', []),
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Invoice payment failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse((new PaymentResource($payment))->resolve(), 'Invoice payment created successfully.', 201);
    }
}
