<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'full_name'         => $this->faker->name(),
        'email'             => $this->faker->unique()->safeEmail(),
        'phone_number'      => '091' . $this->faker->numberBetween(1000000, 9999999),
        'password_hash'     => bcrypt('password'), // متوافق مع جدولك image_60b0d2.png
        'role_id'           => 2, // الافتراضي سائق أو مستخدم عادي
        'is_active'         => 1,
        'email_verified_at' => now(),
        'remember_token'    => Str::random(10),
    ];
}

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
