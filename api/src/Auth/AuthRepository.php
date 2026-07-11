<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;
use PDO;

final readonly class AuthRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string}|null */
    public function findUserByEmailOrLogin(string $identifier): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, password_hash, name, role, login, phone, address, status
             FROM auth_users
             WHERE lower(email) = lower(:identifier) OR lower(login) = lower(:identifier)
             LIMIT 1'
        );
        $statement->execute(['identifier' => $identifier]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $this->normalizeUserRow($row);
    }

    /** @return array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string}|null */
    public function findUserByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.email, u.password_hash, u.name, u.role, u.login, u.phone, u.address, u.status
             FROM auth_access_tokens t
             INNER JOIN auth_users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash AND t.expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $this->normalizeUserRow($row);
    }

    public function storeAccessToken(string $tokenHash, int $userId, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_access_tokens (token_hash, user_id, expires_at)
             VALUES (:token_hash, :user_id, :expires_at)'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'user_id' => $userId,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    public function deleteAccessToken(string $tokenHash): void
    {
        $statement = $this->pdo->prepare('DELETE FROM auth_access_tokens WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => $tokenHash]);
    }

    public function storeRegistrationCode(
        string $email,
        string $passwordHash,
        string $name,
        string $code,
        DateTimeImmutable $codeExpiresAt,
        DateTimeImmutable $resendAvailableAt,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_registration_codes (email, password_hash, name, code, code_expires_at, resend_available_at)
             VALUES (:email, :password_hash, :name, :code, :code_expires_at, :resend_available_at)
             ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                name = EXCLUDED.name,
                code = EXCLUDED.code,
                code_expires_at = EXCLUDED.code_expires_at,
                resend_available_at = EXCLUDED.resend_available_at,
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
            'code' => $code,
            'code_expires_at' => $codeExpiresAt->format(DATE_ATOM),
            'resend_available_at' => $resendAvailableAt->format(DATE_ATOM),
        ]);
    }

    /** @return array{email:string,password_hash:string,name:string,code:string,code_expires_at:string,resend_available_at:string}|null */
    public function findRegistrationCode(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT email, password_hash, name, code, code_expires_at, resend_available_at
             FROM auth_registration_codes
             WHERE lower(email) = lower(:email)
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (false === $row) {
            return null;
        }

        return [
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'code_expires_at' => (string) $row['code_expires_at'],
            'resend_available_at' => (string) $row['resend_available_at'],
        ];
    }

    /** @return array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string} */
    public function upsertUser(
        string $email,
        string $passwordHash,
        string $name,
        string $login,
        ?string $phone = null,
        ?string $address = null,
        string $role = 'admin',
    ): array {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_users (email, password_hash, name, role, login, phone, address)
             VALUES (:email, :password_hash, :name, :role, :login, :phone, :address)
             ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                name = EXCLUDED.name,
                role = EXCLUDED.role,
                login = EXCLUDED.login,
                phone = EXCLUDED.phone,
                address = EXCLUDED.address,
                updated_at = CURRENT_TIMESTAMP
             RETURNING id, email, password_hash, name, role, login, phone, address'
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
            'role' => $role,
            'login' => $login,
            'phone' => $phone,
            'address' => $address,
        ]);

        /** @var array{id:mixed,email:mixed,password_hash:mixed,name:mixed,role:mixed,login:mixed,phone:mixed,address:mixed} $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $this->normalizeUserRow($row);
    }

    public function deleteRegistrationCode(string $email): void
    {
        $statement = $this->pdo->prepare('DELETE FROM auth_registration_codes WHERE lower(email) = lower(:email)');
        $statement->execute(['email' => $email]);
    }

    /** @return array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string}|null */
    public function findUserById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, password_hash, name, role, login, phone, address
             FROM auth_users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $this->normalizeUserRow($row);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['password_hash' => $passwordHash, 'id' => $id]);
    }

    public function updateLogin(int $id, string $login): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_users SET login = :login, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['login' => $login, 'id' => $id]);
    }

    public function updateEmail(int $id, string $email): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_users SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['email' => $email, 'id' => $id]);
    }

    public function isLoginTaken(string $login, int $exceptId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM auth_users WHERE lower(login) = lower(:login) AND id <> :id LIMIT 1'
        );
        $statement->execute(['login' => $login, 'id' => $exceptId]);

        return false !== $statement->fetchColumn();
    }

    public function isEmailTaken(string $email, int $exceptId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM auth_users WHERE lower(email) = lower(:email) AND id <> :id LIMIT 1'
        );
        $statement->execute(['email' => $email, 'id' => $exceptId]);

        return false !== $statement->fetchColumn();
    }

    public function deleteAllAccessTokensForUser(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM auth_access_tokens WHERE user_id = :id');
        $statement->execute(['id' => $id]);
    }

    public function createManagedUser(
        string $email,
        string $passwordHash,
        string $name,
        string $login,
        string $role,
        ?string $phone,
        ?string $address,
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO auth_users (email, password_hash, name, role, login, phone, address, status)
             VALUES (:email, :password_hash, :name, :role, :login, :phone, :address, 'active')
             RETURNING id"
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
            'role' => $role,
            'login' => $login,
            'phone' => $phone,
            'address' => $address,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listUsers(int $limit, int $offset, ?string $search): array
    {
        [$where, $params] = $this->searchClause($search);
        $sql = sprintf(
            'SELECT id, email, login, name, role, status, phone, address, created_at
             FROM auth_users %s ORDER BY id LIMIT %d OFFSET %d',
            $where,
            max(0, $limit),
            max(0, $offset),
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map($this->managedRow(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countUsers(?string $search): int
    {
        [$where, $params] = $this->searchClause($search);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM auth_users ' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function managedUserById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, login, name, role, status, phone, address, created_at
             FROM auth_users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $this->managedRow($row);
    }

    public function countActiveOwners(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM auth_users WHERE role = 'owner' AND status = 'active'")
            ->fetchColumn();
    }

    public function updateUserRole(int $id, string $role): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_users SET role = :role, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['role' => $role, 'id' => $id]);
    }

    public function setUserStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    public function deleteUser(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM auth_users WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @return array{0:string, 1:array<string, string>}
     */
    private function searchClause(?string $search): array
    {
        if (null === $search || '' === trim($search)) {
            return ['', []];
        }

        return [
            'WHERE email ILIKE :s OR login ILIKE :s OR name ILIKE :s',
            ['s' => '%' . trim($search) . '%'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function managedRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'login' => (string) $row['login'],
            'name' => (string) $row['name'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'phone' => null === $row['phone'] ? null : (string) $row['phone'],
            'address' => null === $row['address'] ? null : (string) $row['address'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array{id:mixed,email:mixed,password_hash:mixed,name:mixed,role:mixed,login:mixed,phone:mixed,address:mixed} $row
     * @return array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string}
     */
    private function normalizeUserRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'name' => (string) $row['name'],
            'role' => (string) $row['role'],
            'login' => (string) $row['login'],
            'phone' => null === $row['phone'] ? null : (string) $row['phone'],
            'address' => null === $row['address'] ? null : (string) $row['address'],
            'status' => (string) ($row['status'] ?? 'active'),
        ];
    }
}
