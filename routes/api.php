<?php
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\AdminConversationController;
use App\Http\Controllers\Api\AdminProjectController;
use App\Http\Controllers\Api\AdminUserManagementController;
use App\Http\Controllers\Api\AdminHistoryController;
use App\Http\Controllers\Api\AdminProfileController;
use App\Http\Controllers\Api\AdminManagementController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\AdminConsultationController;

Route::pattern('id', '[0-9]+');
/*
|--------------------------------------------------------------------------
| User Authentication
|--------------------------------------------------------------------------
*/

Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])
    ->middleware('throttle:user-send-otp');

Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])
    ->middleware('throttle:user-verify-otp');

Route::post('/consultations', [ConsultationController::class, 'store'])
    ->middleware('throttle:consultation-submit');

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('admin/auth')->group(function () {

    // ارسال OTP مدیر
    Route::post('/send-otp', [AdminAuthController::class, 'sendOtp'])
        ->middleware('throttle:admin-send-otp');

    // تأیید OTP مدیر
    Route::post('/verify-otp', [AdminAuthController::class, 'verifyOtp'])
         ->middleware('throttle:admin-verify-otp');

    // ورود نهایی با Password
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login');
    Route::post('/complete-profile', [AdminAuthController::class, 'completeProfile'])
        ->middleware(['auth:sanctum', 'admin.setup']);
});


/*
|--------------------------------------------------------------------------
| Authenticated User
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // اطلاعات کاربر
    Route::get('/me', [AuthController::class, 'me']);

    // پروفایل
    Route::put('/profile', [ProfileController::class, 'update']);

    // خروج کاربر
    Route::post('/auth/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | Approved User
    |--------------------------------------------------------------------------
    */

    Route::middleware(['profile.completed', 'approved'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/history', [HistoryController::class, 'index']);
        Route::get('/conversations', [ConversationController::class, 'index']);

        Route::post('/conversations', [ConversationController::class, 'store']);

        Route::get('/conversations/{id}', [ConversationController::class, 'show']);

        Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
        //Route::get('/test-approved', function () {
            //return response()->json([
                //'success' => true,
                //'message' => 'کاربر تأیید شده است.',
            //]);
        //});
        Route::apiResource('projects', ProjectController::class)
            ->whereNumber('project');
    });


    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
    ->middleware('admin')
    ->group(function () {

        // =========================
        // Dashboard
        // =========================
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        // Admin Management
        Route::get('/admins', [AdminManagementController::class, 'index']);
        Route::get('/admins/{id}', [AdminManagementController::class, 'show']);
        Route::post('/admins', [AdminManagementController::class, 'store']);
        Route::patch('/admins/{id}/toggle-active', [AdminManagementController::class, 'toggleActive']);

        Route::get('/profile', [AdminProfileController::class, 'show']);
        Route::put('/profile', [AdminProfileController::class, 'update']);

        // =========================
        // Conversations / نظرات کاربران
        // =========================
        Route::get('/conversations', [AdminConversationController::class, 'index']);

        Route::get('/conversations/{id}', [AdminConversationController::class, 'show']);

        Route::post('/conversations/{id}/messages', [AdminConversationController::class, 'sendMessage']);

        Route::patch('/conversations/{id}/close', [AdminConversationController::class, 'close']);

        
        // =========================
        // Projects
        Route::get('/projects', [AdminProjectController::class, 'index']);
        Route::get('/projects/{id}', [AdminProjectController::class, 'show']);
        Route::put('/projects/{id}', [AdminProjectController::class, 'update']);
        Route::patch('/projects/{id}/status', [AdminProjectController::class, 'updateStatus']);
        Route::delete('/projects/{id}', [AdminProjectController::class, 'destroy']);

        // History
        Route::get('/history/projects', [AdminHistoryController::class, 'projects']);
        Route::get('/history/users', [AdminHistoryController::class, 'users']);
        // =========================
        // Users / کاربران
        // =========================
        Route::get('/users', [AdminUserManagementController::class, 'index']);

        Route::get('/users/{id}', [AdminUserManagementController::class, 'show']);

        Route::patch('/users/{id}/toggle-active', [AdminUserManagementController::class, 'toggleActive']);

        // =========================
        // Requests / درخواست‌ها
        // =========================
        Route::get('/requests', [AdminUserController::class, 'index']);

        Route::patch('/requests/{id}/approve', [AdminUserController::class, 'approve']);

        Route::patch('/requests/{id}/reject', [AdminUserController::class, 'reject']);

        
        // =========================
        // اطلاعات مدیر
        // =========================
        Route::get('/me', [AdminAuthController::class, 'me']);


        // =========================
        // خروج مدیر
        // =========================
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        // =========================
        // Requests / درخواست‌های ثبت‌نام
        // =========================
        //Route::get('/requests', [AdminUserController::class, 'index']);
        //Route::patch('/requests/{id}/approve', [AdminUserController::class, 'approve']);
        //Route::patch('/requests/{id}/reject', [AdminUserController::class, 'reject']);
        Route::get('/consultations', [AdminConsultationController::class, 'index']);

        Route::get('/consultations/{id}', [AdminConsultationController::class, 'show']);

        Route::patch('/consultations/{id}/status', [AdminConsultationController::class, 'updateStatus']);
        // =========================
        // Requests / درخواست‌ها
        // فعلاً Routeهای قبلی اینجا قرار می‌گیرند
        // بعداً به /requests منتقل می‌کنیم
        // =========================
        Route::patch(
        '/users/{id}/role',
        [AdminUserManagementController::class, 'changeRole']
        )->middleware('superadmin');
    });

});