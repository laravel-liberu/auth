<?php

namespace LaravelLiberu\Auth\Http\Controllers\Auth;

use App\Models\Company;
use App\Models\Person;
use App\Models\UserSocial;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use LaravelLiberu\Core\Events\Login as Event;
use LaravelLiberu\Core\Traits\Login;
use LaravelLiberu\Core\Traits\Logout;
use LaravelLiberu\Users\Models\User;

class LoginController extends Controller
{
    use AuthenticatesUsers, Logout, Login {
        Logout::logout insteadof AuthenticatesUsers;
        Login::login insteadof AuthenticatesUsers;
    }

    protected $redirectTo = '/';

    private ?User $user;

    public function __construct()
    {
        $this->maxAttempts = Config::get('liberu.auth.maxLoginAttempts');
    }

    protected function attemptLogin(Request $request)
    {
        $this->user = $this->loggableUser($request);

        if (! $this->user) {
            return false;
        }

        if ($request->attributes->get('sanctum')) {
            Auth::guard('web')->login($this->user, $request->input('remember'));
        }

        Event::dispatch($this->user, $request->ip(), $request->header('User-Agent'));

        return true;
    }

    protected function sendLoginResponse(Request $request)
    {
        $this->clearLoginAttempts($request);

        if ($request->attributes->get('sanctum')) {
            $request->session()->regenerate();

            return [
                'auth' => Auth::check(),
                'csrfToken' => csrf_token(),
            ];
        }

        $token = $this->user->createToken($request->get('device_name'));

        return response()->json(['token' => $token->plainTextToken])
            ->cookie('webview', true)
            ->cookie('Authorization', $token->plainTextToken);
    }

    protected function validateLogin(Request $request)
    {
        $attributes = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];

        if (! $request->attributes->get('sanctum')) {
            $attributes['device_name'] = 'required|string';
        }

        $request->validate($attributes);
    }

    private function loggableUser(Request $request)
    {
        $user = User::whereEmail($request->input('email'))->first();

        if (! $user?->currentPasswordIs($request->input('password'))) {
            return;
        }

        if ($user->passwordExpired()) {
            throw ValidationException::withMessages([
                'email' => 'Password expired. Please set a new one.',
            ]);
        }

        if ($user->isInactive()) {
            throw ValidationException::withMessages([
                'email' => 'Account disabled. Please contact the administrator.',
            ]);
        }

        return $user;
    }

    public function redirectToProvider($provider)
    {
        $validated = $this->validateProvider($provider);
        if (! is_null($validated)) {
            return $validated;
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Obtain the user information from Provider.
     *
     * @param  $provider
     * @return JsonResponse
     */
    public function handleProviderCallback($provider): JsonResponse
    {
        try {
            $user = Socialite::driver($provider)->stateless()->user();
        } catch (Exception) {
            return redirect(config('settings.clientBaseUrl').'/social-callback?token=&status=false&message=Invalid credentials provided!');
        }

        $curUser = \App\Models\User::where('email', $user->getEmail())->first();

        if (! $curUser) {
            try {
                // create person
                $person = new Person();
                $name = $user->getName();
                $person->name = $name;
                $person->email = $user->getEmail();
                $person->save();
                // get user_group_id
                $user_group = UserGroup::where('name', 'Administrators')->first();
                if ($user_group === null) {
                    // create user_group
                    $user_group = UserGroup::create(['name' => 'Administrators', 'description' => 'Administrator users group']);
                }

                // get role_id
                $role = Role::where('name', 'free')->first();
                if ($role === null) {
                    $role = Role::create(['menu_id' => 1, 'name' => 'supervisor', 'display_name' => 'Supervisor', 'description' => 'Supervisor role.']);
                }

                $curUser = User::create(
                    [
                        'email' => $user->getEmail(),
                        'person_id' => $person->id,
                        'group_id' => $user_group->id,
                        'role_id' => $role->id,
                        'email_verified_at' => now(),
                        'is_active' => 1,
                    ],
                );

                $random = $this->unique_random('companies', 'name', 5);
                $company = Company::create([
                    'name' => 'company'.$random,
                    'email' => $user->getEmail(),
                    'is_tenant' => 1,
                    'status' => 1,
                ]);

                $person->companies()->attach($company->id, ['person_id' => $person->id, 'is_main' => 1, 'is_mandatary' => 1, 'company_id' => $company->id]);

            } catch (Exception) {
                return redirect(config('settings.clientBaseUrl').'/social-callback?token=&status=false&message=Something went wrong!');
            }
        }

        try {
            if ($this->needsToCreateSocial($curUser, $provider)) {
                UserSocial::create([
                    'user_id' => $curUser->id,
                    'social_id' => $user->getId(),
                    'service' => $provider,
                ]);
            }
        } catch (Exception) {
            return redirect(config('settings.clientBaseUrl').'/social-callback?token=&status=false&message=Something went wrong!');
        }

        if ($this->loggableSocialUser($curUser)) {
            Auth::guard('web')->login($curUser, true);

            return redirect(config('settings.clientBaseUrl').'/social-callback?token='.csrf_token().'&status=success&message=success');
        }

        return redirect(config('settings.clientBaseUrl').'/social-callback?token=&status=false&message=Something went wrong while we processing the login. Please try again!');
    }

    public function confirmSubscription(Request $request): JsonResponse
    {
        $request->all();
        $user = $this->loggableUser($request);

        return response()->json($user);
    }
}
