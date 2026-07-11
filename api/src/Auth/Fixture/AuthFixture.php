<?php

declare(strict_types=1);

namespace App\Auth\Fixture;

final readonly class AuthFixture
{
    public function __construct(private \PDO $pdo) {}

    public function load(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_users (id, email, password_hash, name, role, login, phone, address)
             VALUES (:id, :email, :password_hash, :name, :role, :login, :phone, :address)
             ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                name = EXCLUDED.name,
                role = EXCLUDED.role,
                login = EXCLUDED.login,
                phone = EXCLUDED.phone,
                address = EXCLUDED.address,
                updated_at = CURRENT_TIMESTAMP',
        );

        foreach ($this->users() as $user) {
            $statement->execute([
                'id' => $user['id'],
                'email' => $user['email'],
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'name' => $user['name'],
                'role' => $user['role'],
                'login' => $user['login'],
                'phone' => $user['phone'],
                'address' => $user['address'],
            ]);
        }

        if ('pgsql' === $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            $this->pdo->exec("SELECT setval(pg_get_serial_sequence('auth_users', 'id'), GREATEST((SELECT max(id) FROM auth_users), 1))");
        }
    }

    /**
     * @return list<array{id:int,email:string,password:string,name:string,role:string,login:string,phone:string,address:string}>
     */
    private function users(): array
    {
        return [
            [
                'id' => 1,
                'email' => 'owner@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u0410\u0434\u043c\u0438\u043d\u0438\u0441\u0442\u0440\u0430\u0442\u043e\u0440"'),
                'role' => 'owner',
                'login' => 'owner',
                'phone' => '+7 900 100-10-01',
                'address' => $this->text('"\u041c\u043e\u0441\u043a\u0432\u0430, \u0443\u043b. \u0422\u0432\u0435\u0440\u0441\u043a\u0430\u044f, 1"'),
            ],
            [
                'id' => 1002,
                'email' => 'manager@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u041c\u0435\u043d\u0435\u0434\u0436\u0435\u0440"'),
                'role' => 'admin',
                'login' => 'manager',
                'phone' => '+7 900 100-10-02',
                'address' => $this->text('"\u041c\u043e\u0441\u043a\u0432\u0430, \u041f\u0440\u0435\u0441\u043d\u0435\u043d\u0441\u043a\u0430\u044f \u043d\u0430\u0431., 10"'),
            ],
            [
                'id' => 1003,
                'email' => 'editor@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u0420\u0435\u0434\u0430\u043a\u0442\u043e\u0440"'),
                'role' => 'admin',
                'login' => 'editor',
                'phone' => '+7 900 100-10-03',
                'address' => $this->text('"\u0421\u0430\u043d\u043a\u0442-\u041f\u0435\u0442\u0435\u0440\u0431\u0443\u0440\u0433, \u041d\u0435\u0432\u0441\u043a\u0438\u0439 \u043f\u0440., 24"'),
            ],
            [
                'id' => 1004,
                'email' => 'viewer@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u041d\u0430\u0431\u043b\u044e\u0434\u0430\u0442\u0435\u043b\u044c"'),
                'role' => 'user',
                'login' => 'viewer',
                'phone' => '+7 900 100-10-04',
                'address' => $this->text('"\u041a\u0430\u0437\u0430\u043d\u044c, \u0443\u043b. \u0411\u0430\u0443\u043c\u0430\u043d\u0430, 7"'),
            ],
            [
                'id' => 1005,
                'email' => 'support@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u0421\u0430\u043f\u043f\u043e\u0440\u0442"'),
                'role' => 'admin',
                'login' => 'support',
                'phone' => '+7 900 100-10-05',
                'address' => $this->text('"\u0415\u043a\u0430\u0442\u0435\u0440\u0438\u043d\u0431\u0443\u0440\u0433, \u0443\u043b. \u041c\u0430\u043b\u044b\u0448\u0435\u0432\u0430, 51"'),
            ],
            [
                'id' => 1006,
                'email' => 'client@rebit.test',
                'password' => 'secret123',
                'name' => $this->text('"\u041a\u043b\u0438\u0435\u043d\u0442"'),
                'role' => 'user',
                'login' => 'client',
                'phone' => '+7 900 100-10-06',
                'address' => $this->text('"\u041d\u043e\u0432\u043e\u0441\u0438\u0431\u0438\u0440\u0441\u043a, \u041a\u0440\u0430\u0441\u043d\u044b\u0439 \u043f\u0440., 15"'),
            ],
        ];
    }

    private function text(string $json): string
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
