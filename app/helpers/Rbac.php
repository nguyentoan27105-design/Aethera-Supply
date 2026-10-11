<?php

class Rbac
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_WAREHOUSE = 'warehouse_staff';
    public const ROLE_EXPORT = 'export_staff';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SUPPLIER = 'supplier';

    public const ROLES = [
        self::ROLE_ADMIN => 'Quản trị viên',
        self::ROLE_MANAGER => 'Nhân viên quản lý',
        self::ROLE_STAFF => 'Nhân viên',
        self::ROLE_WAREHOUSE => 'Nhân viên kho',
        self::ROLE_EXPORT => 'Nhân viên xuất khẩu',
        self::ROLE_SUPPLIER => 'Nhà cung cấp',
        self::ROLE_USER => 'Khách hàng',
    ];

    private const PERMISSIONS = [
        self::ROLE_ADMIN => ['*'],
        self::ROLE_MANAGER => ['statistics', 'workflow', 'suppliers', 'categories', 'batches', 'inventory', 'order_management', 'export_orders', 'promotions', 'store'],
        self::ROLE_STAFF => ['statistics', 'workflow', 'order_management', 'store'],
        self::ROLE_WAREHOUSE => ['statistics', 'workflow', 'batches', 'inventory', 'store'],
        self::ROLE_EXPORT => ['statistics', 'workflow', 'export_orders', 'inventory', 'store'],
        self::ROLE_SUPPLIER => ['supplier_portal', 'store'],
        self::ROLE_USER => ['profile', 'orders', 'store'],
    ];

    public static function normalizeRole(?string $role): string
    {
        $role = trim((string)$role);
        return array_key_exists($role, self::ROLES) ? $role : self::ROLE_USER;
    }

    public static function label(?string $role): string
    {
        $role = self::normalizeRole($role);
        return self::ROLES[$role];
    }

    public static function can(?string $role, string $permission): bool
    {
        $role = self::normalizeRole($role);
        $permissions = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function hasAdminAreaAccess(?string $role): bool
    {
        $role = self::normalizeRole($role);
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_STAFF, self::ROLE_WAREHOUSE, self::ROLE_EXPORT], true);
    }

    public static function defaultUrl(?string $role): string
    {
        $role = self::normalizeRole($role);
        return match ($role) {
            self::ROLE_ADMIN, self::ROLE_MANAGER => '/admin/statistics',
            self::ROLE_WAREHOUSE => '/admin/inventory',
            self::ROLE_EXPORT => '/admin/export_orders',
            self::ROLE_STAFF => '/admin/order_management',
            self::ROLE_SUPPLIER => '/account/profile?tab=supplier',
            default => '/account/profile',
        };
    }

    public static function roleOptions(): array
    {
        return self::ROLES;
    }
}
