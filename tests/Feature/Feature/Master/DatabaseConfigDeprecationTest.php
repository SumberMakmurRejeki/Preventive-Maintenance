<?php

namespace Tests\Feature\Feature\Master;

use Tests\TestCase;

/**
 * Regression test untuk memastikan config/database.php tidak menggunakan
 * PDO::MYSQL_ATTR_SSL_CA secara langsung yang deprecated di PHP 8.5.
 *
 * Test ini memeriksa source code, bukan runtime behavior, karena deprecation
 * warning hanya muncul pada PHP 8.5+ dan bergantung pada error_reporting.
 */
class DatabaseConfigDeprecationTest extends TestCase
{
    /**
     * Pastikan config/database.php menggunakan compatibility conditional
     * untuk PDO MySQL SSL attributes, bukan constant deprecated langsung.
     */
    public function test_database_config_uses_pdo_mysql_compatibility_conditional(): void
    {
        $configPath = config_path('database.php');
        $this->assertFileExists($configPath);

        $content = file_get_contents($configPath);

        // Tidak boleh ada referensi langsung ke PDO::MYSQL_ATTR_SSL_CA
        // tanpa conditional class_exists check
        $hasDeprecatedConstant = preg_match(
            '/\bPDO::MYSQL_ATTR_SSL_CA\b/',
            $content
        );

        // Jika ada referensi, harus dalam conditional Pdo\Mysql fallback
        // Pint akan mengkonversi \Pdo\Mysql::class menjadi Mysql::class dengan use Pdo\Mysql; import
        if ($hasDeprecatedConstant) {
            $hasUseImport = str_contains($content, 'use Pdo\\Mysql;');
            $hasClassExists = str_contains($content, 'class_exists(Mysql::class)') || str_contains($content, "class_exists('Pdo\\\\Mysql')");

            $this->assertTrue(
                $hasUseImport && $hasClassExists,
                'config/database.php menggunakan PDO::MYSQL_ATTR_SSL_CA tanpa compatibility conditional. '.
                'Gunakan: use Pdo\\Mysql; lalu class_exists(Mysql::class) ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA'
            );
        }


        // Verifikasi bahwa Mysql::ATTR_SSL_CA digunakan (Pdo\Mysql, PHP 8.2+ compatible)
        // Pint akan menggunakan short form Mysql::ATTR_SSL_CA dengan use Pdo\Mysql; import
        $this->assertTrue(
            str_contains($content, 'Mysql::ATTR_SSL_CA'),
            'config/database.php harus menggunakan Mysql::ATTR_SSL_CA (Pdo\\Mysql) untuk kompatibilitas PHP 8.5+'
        );

    }

    /**
     * Pastikan config dapat di-load tanpa error pada runtime saat ini.
     */
    public function test_database_config_loads_without_error(): void
    {
        // config() helper akan load config/database.php
        $config = config('database.connections.mysql');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('options', $config);

        // Options harus berupa array (mungkin kosong jika env tidak diset)
        $this->assertIsArray($config['options']);
    }
}
