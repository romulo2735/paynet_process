<?php

namespace App\DTOs;

class UserProcessDTO
{
    public string $cpf;
    public string $cep;
    public string $email;

    public function __construct(array $data)
    {
        $this->cpf = $data['cpf'];
        $this->cep = $data['cep'];
        $this->email = $data['email'];
    }
}
