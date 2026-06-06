<?php

namespace App\Providers;

use App\Events\Auth\TokenCreated;
use App\Events\Auth\TokenRevoked;
use App\Events\Billing\InvoiceFailed;
use App\Events\Billing\InvoiceIssued;
use App\Events\Billing\InvoicePaid;
use App\Events\Billing\InvoicePaymentPending;
use App\Events\Billing\PaymentCancelled;
use App\Events\Billing\PaymentCreated;
use App\Events\Billing\PaymentExpired;
use App\Events\Billing\PaymentFailed;
use App\Events\Billing\PaymentSucceeded;
use App\Events\Billing\WalletCredited;
use App\Events\Billing\WalletDebited;
use App\Events\Notifications\NotificationCreated;
use App\Events\Users\UserCreated;
use App\Events\Users\UserUpdated;
use App\Events\Rbac\PermissionChanged;
use App\Events\Rbac\RolePermissionsChanged;
use App\Listeners\Auth\LogTokenCreatedActivity;
use App\Listeners\Auth\LogTokenRevokedActivity;
use App\Listeners\Billing\DispatchBillingWebhookAction;
use App\Listeners\Billing\DispatchInvoiceNotificationActions;
use App\Listeners\Billing\DispatchPaymentNotificationActions;
use App\Listeners\Billing\DispatchReceiptGenerationAction;
use App\Listeners\Billing\DispatchSellerCompanyNotificationAction;
use App\Listeners\Billing\ActivateSubscriptionAfterPaymentSucceeded;
use App\Listeners\Billing\MarkSubscriptionPaymentFailed;
use App\Listeners\Notifications\LogNotificationCreatedActivity;
use App\Listeners\Rbac\InvalidatePermissionCache;
use App\Listeners\Users\LogUserCreatedActivity;
use App\Listeners\Users\LogUserUpdatedActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider.
 *
 * Registers application events and listeners.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserCreated::class => [
            LogUserCreatedActivity::class,
        ],
        UserUpdated::class => [
            LogUserUpdatedActivity::class,
        ],
        RolePermissionsChanged::class => [
            InvalidatePermissionCache::class,
        ],
        PermissionChanged::class => [
            InvalidatePermissionCache::class,
        ],
        TokenCreated::class => [
            LogTokenCreatedActivity::class,
        ],
        TokenRevoked::class => [
            LogTokenRevokedActivity::class,
        ],
        NotificationCreated::class => [
            LogNotificationCreatedActivity::class,
        ],
        PaymentCreated::class => [
            DispatchPaymentNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        PaymentSucceeded::class => [
            ActivateSubscriptionAfterPaymentSucceeded::class,
            DispatchPaymentNotificationActions::class,
            DispatchReceiptGenerationAction::class,
            DispatchBillingWebhookAction::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        PaymentFailed::class => [
            MarkSubscriptionPaymentFailed::class,
            DispatchPaymentNotificationActions::class,
            DispatchBillingWebhookAction::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        PaymentExpired::class => [
            DispatchPaymentNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        PaymentCancelled::class => [
            DispatchPaymentNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        InvoiceIssued::class => [
            DispatchInvoiceNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        InvoicePaymentPending::class => [
            DispatchInvoiceNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        InvoicePaid::class => [
            DispatchInvoiceNotificationActions::class,
            DispatchReceiptGenerationAction::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        InvoiceFailed::class => [
            DispatchInvoiceNotificationActions::class,
            DispatchSellerCompanyNotificationAction::class,
        ],
        WalletCredited::class => [
            DispatchSellerCompanyNotificationAction::class,
        ],
        WalletDebited::class => [
            DispatchSellerCompanyNotificationAction::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
