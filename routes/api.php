<?

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use LaravelLiberu\Auth\Http\Controllers\Guest;
use LaravelLiberu\Auth\Http\Controllers\Auth\LoginController;
use LaravelLiberu\Auth\Http\Controllers\Auth\ResetPasswordController;
use LaravelLiberu\Auth\Http\Controllers\Auth\ForgotPasswordController;


    Route::middleware('api')
    ->group(function () {
        Route::middleware('guest')->group(function () {
            Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
                ->name('password.email');
            Route::post('password/reset', [ResetPasswordController::class, 'attemptReset'])
                ->name('password.reset');
        });

        Route::middleware('auth')->group(function () {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        });
    });


    Route::namespace('Auth')
    ->middleware('api')
    ->group(function () {
        Route::middleware('guest')->group(function () {
            Route::post('login', fn(Request $request) => (new LoginController())->login($request));
            Route::get('login/{provider}', fn($provider) => (new LoginController())->redirectToProvider($provider));
            Route::get('login/{provider}/callback', fn($provider) => (new LoginController())->handleProviderCallback($provider));
        });

        Route::middleware('auth')->group(function () {
            Route::post('logout', fn(Request $request) => (new LoginController())->logout($request));
        });
        Route::post('confirm_checkout', fn(Request $request) => (new LoginController())->confirmSubscription($request));

        // Route::post('register', [RegisterController::class, 'create']);
        //  Route::get('get-subscription-plan', [RegisterController::class, 'getSubscriptionPlan']);
        // Route::post('verify', [RegisterController::class, 'verify_user']);
    });
