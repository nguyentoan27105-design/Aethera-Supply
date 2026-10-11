<?php
require_once __DIR__ . '/../helpers/Rbac.php';

class AccountModel
{
    private PDO $conn;
    private string $table = 'account';

    public function __construct($db)
    {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureRoleSchema();
    }

    private function ensureRoleSchema(): void
    {
        try {
            $this->conn->exec("
                ALTER TABLE {$this->table}
                MODIFY role ENUM('user','admin','staff','warehouse_staff','export_staff','manager','supplier') DEFAULT 'user'
            ");
        } catch (Throwable $e) {
            error_log('Cannot widen account.role: ' . $e->getMessage());
        }

        foreach ([
            'supplier_id' => 'INT NULL',
            'verification_code' => 'VARCHAR(20) NULL',
            'verification_expires_at' => 'DATETIME NULL',
            'verified_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            $this->addColumnIfMissing($column, $definition);
        }
    }

    private function addColumnIfMissing(string $column, string $definition): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$this->table, $column]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->conn->exec("ALTER TABLE {$this->table} ADD {$column} {$definition}");
            }
        } catch (Throwable $e) {
            error_log("Cannot add account column {$column}: " . $e->getMessage());
        }
    }

    public function findByUserOrEmail($keyword)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE username = :keyword OR email = :keyword LIMIT 1");
            $stmt->execute([':keyword' => trim((string)$keyword)]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log('findByUserOrEmail error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAccountById($id)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log('getAccountById error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAccountByUsername($username)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => trim((string)$username)]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            return null;
        }
    }

    public function emailExists(string $email): bool
    {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = ? LIMIT 1");
            $stmt->execute([trim($email)]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('emailExists error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateInfo($id, $fullname, $email, $phone, $address, $avatar = null)
    {
        try {
            $sql = "UPDATE {$this->table}
                    SET fullname = :fullname,
                        email = :email,
                        phone = :phone,
                        address = :address";
            if (!empty($avatar)) {
                $sql .= ", avatar = :avatar";
            }
            $sql .= " WHERE id = :id";

            $params = [
                ':fullname' => trim((string)$fullname),
                ':email' => trim((string)$email),
                ':phone' => trim((string)$phone),
                ':address' => trim((string)$address),
                ':id' => (int)$id,
            ];
            if (!empty($avatar)) {
                $params[':avatar'] = $avatar;
            }

            return $this->conn->prepare($sql)->execute($params);
        } catch (PDOException $e) {
            error_log('updateInfo error: ' . $e->getMessage());
            return false;
        }
    }

    public function updatePassword($id, $hashedPassword)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET password = :pw WHERE id = :id");
            return $stmt->execute([
                ':pw' => $hashedPassword,
                ':id' => (int)$id,
            ]);
        } catch (PDOException $e) {
            error_log('updatePassword error: ' . $e->getMessage());
            return false;
        }
    }

    public function updatePasswordByEmail($email, $password)
    {
        try {
            $email = trim((string)$email);
            $password = (string)$password;
            if ($email === '' || $password === '') {
                return false;
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET password = :pw,
                    reset_token = NULL,
                    reset_token_expiry = NULL
                WHERE email = :email
            ");
            return $stmt->execute([
                ':pw' => password_hash($password, PASSWORD_DEFAULT),
                ':email' => $email,
            ]);
        } catch (PDOException $e) {
            error_log('updatePasswordByEmail error: ' . $e->getMessage());
            return false;
        }
    }

    public function register($username, $email, $password, $fullname, bool $requireVerification = true)
    {
        try {
            $code = $requireVerification ? (string)random_int(100000, 999999) : null;
            $status = $requireVerification ? 0 : 1;
            $expiresAt = $requireVerification ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $verifiedAt = $requireVerification ? null : date('Y-m-d H:i:s');

            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (
                    username, email, password, fullname, role, status,
                    verification_code, verification_expires_at, verified_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ok = $stmt->execute([
                trim((string)$username),
                trim((string)$email),
                password_hash((string)$password, PASSWORD_DEFAULT),
                trim((string)$fullname),
                Rbac::ROLE_USER,
                $status,
                $code,
                $expiresAt,
                $verifiedAt,
            ]);
            if (!$ok) {
                return false;
            }

            return [
                'account_id' => (int)$this->conn->lastInsertId(),
                'verification_code' => $code,
            ];
        } catch (PDOException $e) {
            error_log('register error: ' . $e->getMessage());
            return false;
        }
    }

    public function createSupplierRegistration(array $data)
    {
        $started = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $started = true;
            }

            $supplierStmt = $this->conn->prepare("
                INSERT INTO supplier (
                    name, phone, address, contact_person, farm_location,
                    cultivation_area, products, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $supplierStmt->execute([
                trim((string)($data['supplier_name'] ?? $data['fullname'] ?? '')),
                trim((string)($data['phone'] ?? '')),
                trim((string)($data['address'] ?? '')),
                trim((string)($data['contact_person'] ?? $data['fullname'] ?? '')),
                trim((string)($data['farm_location'] ?? '')),
                max(0, (float)($data['cultivation_area'] ?? 0)),
                trim((string)($data['products'] ?? '')),
            ]);
            $supplierId = (int)$this->conn->lastInsertId();

            $code = (string)random_int(100000, 999999);
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (
                    username, email, password, fullname, phone, address,
                    role, status, supplier_id, verification_code, verification_expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([
                trim((string)($data['username'] ?? '')),
                trim((string)($data['email'] ?? '')),
                password_hash((string)($data['password'] ?? ''), PASSWORD_DEFAULT),
                trim((string)($data['fullname'] ?? $data['contact_person'] ?? '')),
                trim((string)($data['phone'] ?? '')),
                trim((string)($data['address'] ?? '')),
                Rbac::ROLE_SUPPLIER,
                $supplierId,
                $code,
                date('Y-m-d H:i:s', time() + 15 * 60),
            ]);
            $accountId = (int)$this->conn->lastInsertId();

            if ($started && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return [
                'account_id' => $accountId,
                'supplier_id' => $supplierId,
                'verification_code' => $code,
            ];
        } catch (Throwable $e) {
            if ($started && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('createSupplierRegistration error: ' . $e->getMessage());
            return false;
        }
    }

    public function activateAccountByCode(int $accountId, string $code): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, verification_code, verification_expires_at
                FROM {$this->table}
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$account) {
                return false;
            }
            if (!hash_equals((string)($account->verification_code ?? ''), trim($code))) {
                return false;
            }
            if (!empty($account->verification_expires_at) && strtotime((string)$account->verification_expires_at) < time()) {
                return false;
            }

            $update = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = 1,
                    verified_at = NOW(),
                    verification_code = NULL,
                    verification_expires_at = NULL
                WHERE id = ?
            ");
            return $update->execute([$accountId]);
        } catch (PDOException $e) {
            error_log('activateAccountByCode error: ' . $e->getMessage());
            return false;
        }
    }

    public function refreshVerificationCode(int $accountId): ?string
    {
        try {
            $code = (string)random_int(100000, 999999);
            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET verification_code = ?,
                    verification_expires_at = ?,
                    status = 0
                WHERE id = ?
            ");
            $stmt->execute([$code, date('Y-m-d H:i:s', time() + 15 * 60), $accountId]);
            return $code;
        } catch (PDOException $e) {
            error_log('refreshVerificationCode error: ' . $e->getMessage());
            return null;
        }
    }
}
