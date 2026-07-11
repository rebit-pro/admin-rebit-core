<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class Identity
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public string $role,
        public string $login,
        public ?string $phone,
        public ?string $address,
    ) {}

    /** @return array{id:int,email:string,name:string,role:string,login:string,phone:?string,address:?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'login' => $this->login,
            'phone' => $this->phone,
            'address' => $this->address,
        ];
    }
}
