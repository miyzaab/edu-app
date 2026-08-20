<?php
/**
 * Database Singleton Class
 * Menyediakan koneksi tunggal PDO berkinerja tinggi untuk modul Kantin & E-Wallet
 */
class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Mengembalikan instance tunggal PDO
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            require_once __DIR__ . '/../config/koneksi.php';
            self::$instance = getConnection();
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$instance;
    }
}
