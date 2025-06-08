<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cpf' => $this->cpf,
            'email' => $this->email,
            'cep' => $this->cep,
            'address' => [
                'street' => $this->logradouro,
                'neighborhood' => $this->bairro,
                'city' => $this->cidade,
                'state' => $this->estado,
            ],
            'nationality' => $this->nacionalidade,
            'cpf_status' => $this->cpf_status,
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
