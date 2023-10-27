<?php

namespace App\Http\Controllers\Auth;

use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Traits\ActivationTrait;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use LaravelEnso\Roles\Models\Role;
use LaravelEnso\UserGroups\Models\UserGroup;

class RegisterController extends Controller
{
    use RegistersUsers;
    use ActivationTrait;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:5', 'confirmed'],
        ]);
    }

    public function create(Request $request)
    {
        $validator = $this->validator($request->all());
        if ($validator->fails()) {
            return $validator->errors();
        }

        $person = new Person();
        $name = $request['first_name'] . ' ' . $request['last_name'];
        $person->name = $name;
        $person->email = $request['email'];
        $person->save();

        $user_group = UserGroup::where('name', 'Administrators')->first();
        if ($user_group === null) {
            $user_group = UserGroup::create(['name' => 'Administrators', 'description' => 'Administrator users group']);
        }

        $role = Role::where('name', 'free')->first();
        if ($role === null) {
            $role = Role::create(['menu_id' => 1, 'name' => 'free', 'display_name' => 'Supervisor', 'description' => 'Supervisor role.']);
        }

        $user = User::create([
            'email' => $request['email'],
            'password' => bcrypt($request['password']),
            'person_id' => $person->id,
            'group_id' => $user_group->id,
            'role_id' => $role->id,
            'is_active' => 1,
        ]);

        return $user;
    }
}
