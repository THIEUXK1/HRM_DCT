<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ZKTimeConnectionFactory
{
    public function make(AttendanceSource $source, string $connectionName = 'zktime_temp'): string
    {
        if (app()->environment('testing') || app()->environment('local')) {
            if (!extension_loaded('sqlsrv') || !extension_loaded('pdo_sqlsrv')) {
                return config('database.default');
            }
        }

        if (!extension_loaded('sqlsrv') || !extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException("Server chưa cài pdo_sqlsrv");
        }

        config(["database.connections.{$connectionName}" => [
            'driver' => 'sqlsrv',
            'host' => $source->host,
            'port' => $source->port ?: 1433,
            'database' => $source->database_name,
            'username' => $source->username,
            'password' => $source->password_encrypted, // Automatically decrypted by model cast
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => 'no',
            'trust_server_certificate' => true,
            'options' => [],
        ]]);

        // Purge to clear any previously resolved instances of this connection
        DB::purge($connectionName);

        return $connectionName;
    }
}
