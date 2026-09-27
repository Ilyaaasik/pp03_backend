<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string, token_type: string}
     */
    public function register(array $data): array
    {
        try {
            return DB::transaction(function () use ($data): array {
                // Cast 'hashed' в User автоматически хеширует пароль.
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                return $this->issueToken($user->refresh());
            });
        } catch (UniqueConstraintViolationException) {
            // Защита от одновременной регистрации одного email.
            throw ValidationException::withMessages([
                'email' => ['Этот email уже зарегистрирован.'],
            ]);
        }
    }

    /** @return array{user: User, token: string, token_type: string} */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        abort_unless($user->is_active, 403, 'Аккаунт заблокирован.');

        return $this->issueToken($user);
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        // Выходим только с текущего устройства.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /** @return array{user: User, token: string, token_type: string} */
    private function issueToken(User $user): array
    {
        $token = $user->createToken('api', ['*'], now()->addDays(7));

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
