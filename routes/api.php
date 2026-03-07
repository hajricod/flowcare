<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ManageAppointmentController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth)
Route::get('/branches', [BranchController::class, 'index']);
Route::get('/branches/{branch}/services', [ServiceTypeController::class, 'byBranch']);
Route::get('/branches/{branch}/services/{service}/slots', [SlotController::class, 'available']);
Route::get('/branches/{branch}/queue', [QueueController::class, 'liveQueue']);

// Auth endpoints
Route::post('/auth/register', [AuthController::class, 'register']);
Route::middleware('auth.basic.custom')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Customer routes
    Route::middleware('role:CUSTOMER')->group(function () {
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'cancel']);
        Route::put('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
    });

    // Attachment accessible to customer (owner) or staff/manager/admin
    Route::middleware('role:CUSTOMER,STAFF,BRANCH_MANAGER,ADMIN')->get('/appointments/{id}/attachment', [AppointmentController::class, 'getAttachment']);

    // Staff/Manager/Admin routes
    Route::middleware('role:STAFF,BRANCH_MANAGER,ADMIN')->group(function () {
        Route::get('/manage/appointments', [ManageAppointmentController::class, 'index']);
        Route::put('/manage/appointments/{id}/status', [ManageAppointmentController::class, 'updateStatus']);
        Route::get('/manage/audit-logs', [AuditLogController::class, 'index']);
    });

    // Manager/Admin routes
    Route::middleware('role:BRANCH_MANAGER,ADMIN')->group(function () {
        Route::post('/manage/slots', [SlotController::class, 'store']);
        Route::put('/manage/slots/{id}', [SlotController::class, 'update']);
        Route::delete('/manage/slots/{id}', [SlotController::class, 'destroy']);
        Route::get('/manage/staff', [StaffController::class, 'index']);
        Route::put('/manage/staff/{id}/assign', [StaffController::class, 'assign']);
        Route::get('/manage/customers', [CustomerController::class, 'index']);
        Route::get('/manage/customers/{id}', [CustomerController::class, 'show']);
        Route::get('/manage/customers/{id}/id-image', [CustomerController::class, 'getIdImage']);
    });

    // Admin only routes
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export']);
        Route::put('/admin/settings/retention', [SettingController::class, 'updateRetention']);
        Route::post('/admin/slots/cleanup', [SlotController::class, 'cleanup']);
    });
});