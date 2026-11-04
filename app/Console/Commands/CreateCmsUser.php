<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a CMS account from the command line, typically the first administrator.
 * The password is entered interactively and never stored in shell history.
 */
class CreateCmsUser extends Command
{
    protected $signature = 'cms:create-user
                            {--email= : Email address}
                            {--name= : Display name}
                            {--role= : admin, editor, contributor or reviewer}';

    protected $description = 'Create a Think Ocean CMS user account';

    public function handle(ActivityLogger $activity): int
    {
        $email = $this->option('email') ?: text('Email address', required: true);
        $name = $this->option('name') ?: text('Name', required: true);
        $role = $this->option('role') ?: select('Role', collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->getLabel()])->all(), default: 'admin');
        $plain = password('Password (at least 12 characters, letters and numbers)', required: true);

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'role' => $role, 'password' => $plain],
            [
                'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
                'name' => ['required', 'string', 'max:120'],
                'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
            'role' => Role::from($role),
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $activity->log('user.created', 'Created user "'.$name.'" from the command line', $user, ['role' => $role]);

        $this->components->info("Created {$user->role->getLabel()} account for {$email}. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
