<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Workdo\Timesheet\Http\Controllers\TimesheetController;
use Workdo\Timesheet\Http\Controllers\TimesheetApprovalController;
use Workdo\Timesheet\Http\Controllers\TimesheetInboundMailboxController;

Route::group([
    'middleware' => ['web','auth','verified','PlanModuleCheck:Timesheet'],
], function () {
    // CRUD principal (registra timesheet.index, .create, .store, etc.)
    Route::resource('timesheet', TimesheetController::class);

    // AJAX: clientes por proyecto / por customer
    Route::get('timesheet/clients/by-project',  [TimesheetController::class, 'getCustomerClientsByProject'])
        ->name('timesheet.clients.by-project');
    Route::get('timesheet/clients/by-customer', [TimesheetController::class, 'getCustomerClientsByCustomer'])
        ->name('timesheet.clients.by-customer');

    // Otros endpoints usados por el formulario
    Route::get('/totalhours',              [TimesheetController::class, 'totalhours'])->name('totalhours');
    Route::get('gethours/{id}',            [TimesheetController::class, 'gethours'])->name('gethours');
    Route::post('/gettask',                [TimesheetController::class, 'gettask'])->name('gettask');
    Route::get('timesheet/tickets/for', [TimesheetController::class, 'ticketsForTimesheet'])
    ->name('timesheet.tickets.for');

    Route::get('timesheet/{id}/view', [\Workdo\Timesheet\Http\Controllers\TimesheetController::class, 'view'])
        ->name('timesheet.view')
        ->middleware('auth');
    Route::post('/timesheet/bulk-validate',   [TimesheetApprovalController::class,'bulkValidate'])->name('timesheet.bulk-validate');
    Route::post('/timesheet/bulk-unvalidate', [TimesheetApprovalController::class,'bulkUnvalidate'])->name('timesheet.bulk-unvalidate');
    
    Route::post('/timesheet/{timesheet}/validate',   [TimesheetApprovalController::class,'validateOne'])->name('timesheet.validate');
    Route::post('/timesheet/{timesheet}/unvalidate', [TimesheetApprovalController::class,'unvalidateOne'])->name('timesheet.unvalidate');

    Route::post('/timesheet/verify-password', [\Workdo\Timesheet\Http\Controllers\TimesheetApprovalController::class, 'verifyPassword'])
        ->name('timesheet.verify-password')
        ->middleware('auth');
        
    // ── Configuración de correo entrante para timesheets ─────────────────────────
    Route::prefix('timesheet/email-config')
        ->name('timesheet.email.')
        ->group(function () {
    
            // Pantalla principal (mailboxes + sender maps + log)
            Route::get('/', [TimesheetInboundMailboxController::class, 'index'])
                ->name('index');
    
            // Mailboxes IMAP
            Route::post('/mailbox',              [TimesheetInboundMailboxController::class, 'storeMailbox'])
                ->name('mailbox.store');
            Route::put('/mailbox/{mailbox}',     [TimesheetInboundMailboxController::class, 'updateMailbox'])
                ->name('mailbox.update');
            Route::delete('/mailbox/{mailbox}',  [TimesheetInboundMailboxController::class, 'destroyMailbox'])
                ->name('mailbox.destroy');
    
            // Sender maps
            Route::post('/sendermap',               [TimesheetInboundMailboxController::class, 'storeSenderMap'])
                ->name('sendermap.store');
            Route::put('/sendermap/{senderMap}',    [TimesheetInboundMailboxController::class, 'updateSenderMap'])
                ->name('sendermap.update');
            Route::delete('/sendermap/{senderMap}', [TimesheetInboundMailboxController::class, 'destroySenderMap'])
                ->name('sendermap.destroy');
        });        
});
