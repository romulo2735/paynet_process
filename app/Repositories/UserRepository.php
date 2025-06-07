<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function storeOrUpdate(array $data): User
    {
        return User::query()->updateOrCreate(
            ['cpf' => $data['cpf']],
            $data
        );
    }

    public function findByCpf(string $cpf): ?User
    {
        return User::query()->where('cpf', $cpf)->first();
    }
}
