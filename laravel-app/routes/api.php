<?php

use App\Http\Controllers\Api\V1\ArtworkController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessSettingsController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JobcardMasterController;
use App\Http\Controllers\Api\V1\PlatformSubscriptionController;
use App\Http\Controllers\Api\V1\PrintOrderController;
use App\Http\Controllers\Api\V1\ProductionController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TaxController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('auth.login');
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('/auth/workspaces', [AuthController::class, 'workspaces'])->name('auth.workspaces');
        Route::put('/auth/workspaces/current', [AuthController::class, 'switchWorkspace'])->name('auth.workspaces.current');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Both the PC web client and Flutter app operate on the same authenticated workspace API.
        Route::middleware('tenant.context')->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');

            Route::get('/settings', [BusinessSettingsController::class, 'show'])
                ->middleware('tenant.permission:settings.manage')->name('settings.show');
            Route::patch('/settings', [BusinessSettingsController::class, 'update'])
                ->middleware(['subscription.writes', 'tenant.permission:settings.manage'])->name('settings.update');
            Route::post('/settings/logo', [BusinessSettingsController::class, 'uploadLogo'])
                ->middleware(['subscription.writes', 'tenant.permission:settings.manage'])->name('settings.logo');

            Route::get('/contacts', [CustomerController::class, 'index'])
                ->middleware('tenant.permission:contacts.view')->name('contacts.index');
            Route::get('/contacts/{customerId}', [CustomerController::class, 'show'])->whereNumber('customerId')
                ->middleware('tenant.permission:contacts.view')->name('contacts.show');
            Route::post('/contacts', [CustomerController::class, 'store'])
                ->middleware(['subscription.writes', 'tenant.permission:contacts.manage'])->name('contacts.store');
            Route::patch('/contacts/{customerId}', [CustomerController::class, 'update'])->whereNumber('customerId')
                ->middleware(['subscription.writes', 'tenant.permission:contacts.manage'])->name('contacts.update');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customerId}', [CustomerController::class, 'show'])->whereNumber('customerId')->name('customers.show');
            Route::get('/customers/{customerId}/outstanding', [CustomerController::class, 'outstanding'])->whereNumber('customerId')->name('customers.outstanding');
            Route::post('/customers', [CustomerController::class, 'store'])
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('customers.store');
            Route::patch('/customers/{customerId}', [CustomerController::class, 'update'])->whereNumber('customerId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('customers.update');

            Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
            Route::get('/quotes/{quoteId}', [QuoteController::class, 'show'])->whereNumber('quoteId')->name('quotes.show');
            Route::post('/quotes', [QuoteController::class, 'store'])
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('quotes.store');
            Route::patch('/quotes/{quoteId}', [QuoteController::class, 'update'])->whereNumber('quoteId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('quotes.update');
            Route::patch('/quotes/{quoteId}/status', [QuoteController::class, 'updateStatus'])->whereNumber('quoteId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('quotes.status');
            Route::post('/quotes/{quoteId}/convert', [QuoteController::class, 'convert'])->whereNumber('quoteId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier', 'plan.limit:orders_per_month'])
                ->name('quotes.convert');

            Route::get('/tax/rates', [TaxController::class, 'rates'])
                ->middleware('tenant.permission:taxes.view')->name('tax.rates.index');
            Route::post('/tax/rates', [TaxController::class, 'storeRate'])
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.rates.store');
            Route::patch('/tax/rates/{rateId}', [TaxController::class, 'updateRate'])->whereNumber('rateId')
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.rates.update');
            Route::delete('/tax/rates/{rateId}', [TaxController::class, 'archiveRate'])->whereNumber('rateId')
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.rates.archive');
            Route::get('/tax/groups', [TaxController::class, 'groups'])
                ->middleware('tenant.permission:taxes.view')->name('tax.groups.index');
            Route::get('/tax/groups/{groupId}', [TaxController::class, 'showGroup'])->whereNumber('groupId')
                ->middleware('tenant.permission:taxes.view')->name('tax.groups.show');
            Route::post('/tax/groups', [TaxController::class, 'storeGroup'])
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.groups.store');
            Route::patch('/tax/groups/{groupId}', [TaxController::class, 'updateGroup'])->whereNumber('groupId')
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.groups.update');
            Route::delete('/tax/groups/{groupId}', [TaxController::class, 'archiveGroup'])->whereNumber('groupId')
                ->middleware(['subscription.writes', 'tenant.permission:taxes.manage'])->name('tax.groups.archive');
            Route::post('/tax/preview', [TaxController::class, 'previewCalculation'])
                ->middleware('tenant.permission:taxes.view')->name('tax.preview');

            Route::get('/jobcard-masters', [JobcardMasterController::class, 'index'])->name('jobcard-masters.index');
            Route::post('/jobcard-masters', [JobcardMasterController::class, 'storeMaster'])
                ->middleware(['subscription.writes', 'tenant.role:owner,manager'])->name('jobcard-masters.store');
            Route::post('/jobcard-masters/digital-rates', [JobcardMasterController::class, 'storeDigitalRate'])
                ->middleware(['subscription.writes', 'tenant.role:owner,manager'])->name('jobcard-masters.digital-rates.store');
            Route::patch('/jobcard-masters/digital-rates/{rateId}', [JobcardMasterController::class, 'updateDigitalRate'])
                ->whereNumber('rateId')->middleware(['subscription.writes', 'tenant.role:owner,manager'])->name('jobcard-masters.digital-rates.update');
            Route::patch('/jobcard-masters/{masterId}', [JobcardMasterController::class, 'updateMaster'])
                ->whereNumber('masterId')->middleware(['subscription.writes', 'tenant.role:owner,manager'])->name('jobcard-masters.update');

            Route::get('/orders', [PrintOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{orderId}', [PrintOrderController::class, 'show'])->whereNumber('orderId')->name('orders.show');
            Route::post('/orders', [PrintOrderController::class, 'store'])
                ->middleware(['tenant.role:owner,manager,cashier', 'subscription.writes', 'plan.limit:orders_per_month'])
                ->name('orders.store');
            Route::patch('/orders/{orderId}', [PrintOrderController::class, 'update'])
                ->whereNumber('orderId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,designer,cashier'])
                ->name('orders.update');
            Route::patch('/orders/{orderId}/stage', [ProductionController::class, 'move'])
                ->whereNumber('orderId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,designer,production'])
                ->name('orders.stage');
            Route::post('/orders/{orderId}/artwork', [ArtworkController::class, 'upload'])
                ->whereNumber('orderId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,designer,production'])
                ->name('orders.artwork.upload');
            Route::get('/orders/{orderId}/artwork', [ArtworkController::class, 'download'])
                ->whereNumber('orderId')->middleware('tenant.role:owner,manager,designer,production,cashier')
                ->name('orders.artwork.download');

            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show'])->whereNumber('invoiceId')->name('invoices.show');
            Route::post('/orders/{orderId}/invoice', [InvoiceController::class, 'createFromOrder'])
                ->whereNumber('orderId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('invoices.from-order');
            Route::post('/invoices/{invoiceId}/payments', [InvoiceController::class, 'recordPayment'])
                ->whereNumber('invoiceId')
                ->middleware(['subscription.writes', 'tenant.role:owner,manager,cashier'])
                ->name('invoices.payments.store');

            Route::get('/subscription/current', [SubscriptionController::class, 'current'])->name('subscription.current');
            Route::get('/subscription/payments', [SubscriptionController::class, 'payments'])->name('subscription.payments');
            Route::post('/subscription/requests', [SubscriptionController::class, 'requestPlan'])
                ->middleware('tenant.role:owner,manager')->name('subscription.request');
            Route::post('/subscription/payments/{paymentId}/receipt', [SubscriptionController::class, 'submitReceipt'])
                ->whereNumber('paymentId')->middleware('tenant.role:owner,manager,cashier')->name('subscription.receipt');
        });

        // Platform admin is separate from a print-shop tenant context.
        Route::prefix('platform')->name('platform.')->middleware('platform.admin')->group(function (): void {
            Route::get('/subscription-plans', [PlatformSubscriptionController::class, 'plans'])->name('plans.index');
            Route::post('/subscription-plans', [PlatformSubscriptionController::class, 'createPlan'])->name('plans.store');
            Route::put('/subscription-plans/{planId}', [PlatformSubscriptionController::class, 'updatePlan'])->whereNumber('planId')->name('plans.update');
            Route::get('/subscription-payments/pending', [PlatformSubscriptionController::class, 'pendingPayments'])->name('payments.pending');
            Route::get('/subscription-payments/{paymentId}/receipt', [PlatformSubscriptionController::class, 'receipt'])->whereNumber('paymentId')->name('payments.receipt');
            Route::post('/subscription-payments/{paymentId}/approve', [PlatformSubscriptionController::class, 'approvePayment'])->whereNumber('paymentId')->name('payments.approve');
            Route::post('/subscription-payments/{paymentId}/reject', [PlatformSubscriptionController::class, 'rejectPayment'])->whereNumber('paymentId')->name('payments.reject');
        });
    });
});
