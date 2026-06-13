<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\BillingReportsRequest;
use App\Models\User;
use App\Services\Billing\BillingReportService;
use Illuminate\Http\JsonResponse;

class AdminBillingReportController extends BaseController
{
    public function __construct(
        private readonly BillingReportService $billingReportService,
    ) {}

    public function revenueSummary(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->revenueSummary($request->reportFilters()),
            'Revenue summary fetched successfully.',
        );
    }

    public function paymentStatusSummary(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
        ])) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->paymentStatusSummary($request->reportFilters()),
            'Payment status summary fetched successfully.',
        );
    }

    public function revenueByPlan(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->revenueByPlan($request->reportFilters()),
            'Revenue by plan fetched successfully.',
        );
    }

    public function revenueByCurrency(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->revenueByCurrency($request->reportFilters()),
            'Revenue by currency fetched successfully.',
        );
    }

    public function revenueBySellerCompany(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->revenueBySellerCompany($request->reportFilters()),
            'Revenue by seller and company fetched successfully.',
        );
    }

    public function subscriptionMetrics(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
        ])) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->subscriptionMetrics($request->reportFilters()),
            'Subscription metrics fetched successfully.',
        );
    }

    public function invoiceMetrics(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->invoiceMetrics($request->reportFilters()),
            'Invoice metrics fetched successfully.',
        );
    }

    public function walletMetrics(BillingReportsRequest $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.reports.view',
            'billing.reports.view_financials',
        ], true)) {
            return $response;
        }

        return $this->successResponse(
            $this->billingReportService->walletMetrics($request->reportFilters()),
            'Wallet metrics fetched successfully.',
        );
    }

    /**
     * @param array<int, string> $permissions
     */
    private function ensureAccess(?User $actor, array $permissions, bool $requireAll = false): ?JsonResponse
    {
        if ($actor instanceof User && ($actor->isAdmin() || $this->permissionsGranted($actor, $permissions, $requireAll))) {
            return null;
        }

        return $this->errorResponse('Forbidden.', ['code' => 'forbidden'], 403, 'forbidden');
    }

    /**
     * @param array<int, string> $permissions
     */
    private function permissionsGranted(User $actor, array $permissions, bool $requireAll): bool
    {
        if ($requireAll) {
            foreach ($permissions as $permission) {
                if (! $actor->hasPermission($permission)) {
                    return false;
                }
            }

            return true;
        }

        return $actor->hasAnyPermission($permissions);
    }
}
