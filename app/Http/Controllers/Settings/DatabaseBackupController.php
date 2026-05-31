<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    /**
     * Display the database backup settings page.
     */
    public function show(): Response
    {
        return Inertia::render('settings/DatabaseBackup', [
            'database' => [
                'connection' => config('database.default'),
                'name' => config('database.connections.'.config('database.default').'.database'),
                'driver' => config('database.connections.'.config('database.default').'.driver'),
            ],
        ]);
    }

    /**
     * Generate a SQL dump of the current database and return it as a download.
     */
    public function download(): BinaryFileResponse
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        abort_if($driver !== 'mysql' && $driver !== 'mariadb', 422, 'Database backup is only supported for MySQL/MariaDB.');

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $databaseName = (string) config("database.connections.{$connectionName}.database");
        $filename = sprintf(
            'backup-%s-%s.sql',
            Str::slug($databaseName, '_'),
            now()->format('Ymd_His'),
        );

        $tempPath = tempnam(sys_get_temp_dir(), 'db-backup-');
        $handle = fopen($tempPath, 'wb');

        try {
            $this->writeDump($handle, $databaseName);
        } finally {
            fclose($handle);
        }

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/sql',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Write a SQL dump of the given database to the provided file handle.
     *
     * @param  resource  $handle
     */
    private function writeDump($handle, string $databaseName): void
    {
        $connection = DB::connection();

        fwrite($handle, "-- ----------------------------------------------------------\n");
        fwrite($handle, "-- Database backup for: {$databaseName}\n");
        fwrite($handle, '-- Generated at: '.now()->toDateTimeString()."\n");
        fwrite($handle, "-- ----------------------------------------------------------\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        $tables = $connection->select('SHOW FULL TABLES');
        $tableKey = 'Tables_in_'.$databaseName;
        $typeKey = 'Table_type';

        foreach ($tables as $tableRow) {
            $row = (array) $tableRow;
            $tableName = $row[$tableKey] ?? array_values($row)[0];
            $tableType = $row[$typeKey] ?? 'BASE TABLE';

            if ($tableType !== 'BASE TABLE') {
                continue;
            }

            $this->dumpTableStructure($handle, $tableName);
            $this->dumpTableData($handle, $tableName);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * Write CREATE TABLE statement for the given table.
     *
     * @param  resource  $handle
     */
    private function dumpTableStructure($handle, string $tableName): void
    {
        $quoted = '`'.str_replace('`', '``', $tableName).'`';

        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Table structure for {$tableName}\n");
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");

        $createRow = (array) DB::selectOne("SHOW CREATE TABLE {$quoted}");
        $createSql = $createRow['Create Table'] ?? null;

        if ($createSql !== null) {
            fwrite($handle, $createSql.";\n\n");
        }
    }

    /**
     * Stream all rows of the given table as INSERT statements using an unbuffered query.
     *
     * @param  resource  $handle
     */
    private function dumpTableData($handle, string $tableName): void
    {
        $quoted = '`'.str_replace('`', '``', $tableName).'`';

        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Records of {$tableName}\n");
        fwrite($handle, "-- ----------------------------\n");

        $pdo = $this->makeStreamingPdo();
        $statement = $pdo->query("SELECT * FROM {$quoted}");

        if ($statement === false) {
            fwrite($handle, "\n");

            return;
        }

        $batchSize = 200;
        $batch = [];
        $columnList = null;

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($columnList === null) {
                $columnList = collect(array_keys($row))
                    ->map(fn (string $column): string => '`'.str_replace('`', '``', $column).'`')
                    ->implode(', ');
            }

            $batch[] = $this->formatRow($pdo, $row);

            if (count($batch) >= $batchSize) {
                $this->flushBatch($handle, $quoted, (string) $columnList, $batch);
                $batch = [];
            }
        }

        if (! empty($batch) && $columnList !== null) {
            $this->flushBatch($handle, $quoted, $columnList, $batch);
        }

        $statement->closeCursor();

        fwrite($handle, "\n");
    }

    /**
     * Format a single database row as a SQL VALUES tuple.
     *
     * @param  array<string, mixed>  $row
     */
    private function formatRow(PDO $pdo, array $row): string
    {
        $escaped = array_map(function ($value) use ($pdo) {
            if ($value === null) {
                return 'NULL';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return $pdo->quote((string) $value);
        }, array_values($row));

        return '('.implode(', ', $escaped).')';
    }

    /**
     * Write a batched INSERT statement to the dump.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $batch
     */
    private function flushBatch($handle, string $quotedTable, string $columnList, array $batch): void
    {
        fwrite(
            $handle,
            "INSERT INTO {$quotedTable} ({$columnList}) VALUES\n  ".implode(",\n  ", $batch).";\n",
        );
    }

    /**
     * Build a dedicated PDO connection with unbuffered queries enabled so large
     * tables can be streamed without buffering the entire result in memory.
     */
    private function makeStreamingPdo(): PDO
    {
        $connectionName = (string) config('database.default');
        $config = (array) config("database.connections.{$connectionName}");

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $unixSocket = $config['unix_socket'] ?? null;

        $dsn = $unixSocket
            ? "mysql:unix_socket={$unixSocket};dbname={$database};charset={$charset}"
            : "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
    }
}
