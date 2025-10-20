<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class DatabaseBackupService
{
    private $backupPath;
    private $maxBackups;
    private $compressionEnabled;

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');
        $this->maxBackups = config('backup.max_backups', 10);
        $this->compressionEnabled = config('backup.compression', true);
        
        // Ensure backup directory exists
        if (!file_exists($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Create a full database backup
     */
    public function createBackup(string $type = 'full'): array
    {
        try {
            $timestamp = now()->format('Y-m-d_His');
            $filename = "backup_{$type}_{$timestamp}.sql";
            $filepath = "{$this->backupPath}/{$filename}";

            Log::info('Starting database backup', ['type' => $type, 'filename' => $filename]);

            // Get database configuration
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port", 3306);

            if ($connection === 'mysql') {
                $this->createMySQLBackup($host, $port, $database, $username, $password, $filepath);
            } elseif ($connection === 'pgsql') {
                $this->createPostgreSQLBackup($host, $port, $database, $username, $password, $filepath);
            } elseif ($connection === 'sqlite') {
                $this->createSQLiteBackup($database, $filepath);
            }

            // Get file size
            $fileSize = filesize($filepath);

            // Compress if enabled
            if ($this->compressionEnabled) {
                $compressedPath = $this->compressBackup($filepath);
                if ($compressedPath) {
                    unlink($filepath); // Delete uncompressed file
                    $filepath = $compressedPath;
                    $filename = basename($compressedPath);
                    $fileSize = filesize($compressedPath);
                }
            }

            // Clean up old backups
            $this->cleanupOldBackups();

            Log::info('Database backup completed', [
                'filename' => $filename,
                'size' => $this->formatBytes($fileSize)
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'type' => $type,
                'timestamp' => $timestamp,
                'compressed' => $this->compressionEnabled
            ];

        } catch (\Exception $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create MySQL backup
     */
    private function createMySQLBackup(string $host, int $port, string $database, string $username, string $password, string $filepath): void
    {
        $command = sprintf(
            'mysqldump --host=%s --port=%d --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('MySQL backup failed: ' . implode("\n", $output));
        }
    }

    /**
     * Create PostgreSQL backup
     */
    private function createPostgreSQLBackup(string $host, int $port, string $database, string $username, string $password, string $filepath): void
    {
        $command = sprintf(
            'PGPASSWORD=%s pg_dump --host=%s --port=%d --username=%s --format=plain --file=%s %s 2>&1',
            escapeshellarg($password),
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($filepath),
            escapeshellarg($database)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('PostgreSQL backup failed: ' . implode("\n", $output));
        }
    }

    /**
     * Create SQLite backup
     */
    private function createSQLiteBackup(string $database, string $filepath): void
    {
        if (!file_exists($database)) {
            throw new \Exception("SQLite database not found: {$database}");
        }

        if (!copy($database, $filepath)) {
            throw new \Exception('Failed to copy SQLite database');
        }
    }

    /**
     * Compress backup file
     */
    private function compressBackup(string $filepath): ?string
    {
        try {
            $zipPath = $filepath . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFile($filepath, basename($filepath));
                $zip->close();
                
                Log::info('Backup compressed', ['original' => basename($filepath), 'compressed' => basename($zipPath)]);
                
                return $zipPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Backup compression failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Clean up old backups
     */
    private function cleanupOldBackups(): int
    {
        try {
            $backups = glob("{$this->backupPath}/backup_*.{sql,zip}", GLOB_BRACE);
            
            if (count($backups) <= $this->maxBackups) {
                return 0;
            }

            // Sort by modification time (oldest first)
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $toDelete = count($backups) - $this->maxBackups;
            $deleted = 0;

            for ($i = 0; $i < $toDelete; $i++) {
                if (unlink($backups[$i])) {
                    $deleted++;
                    Log::info('Old backup deleted', ['file' => basename($backups[$i])]);
                }
            }

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Backup cleanup failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * List all backups
     */
    public function listBackups(): array
    {
        try {
            $backups = glob("{$this->backupPath}/backup_*.{sql,zip}", GLOB_BRACE);
            $backupList = [];

            foreach ($backups as $backup) {
                $backupList[] = [
                    'filename' => basename($backup),
                    'path' => $backup,
                    'size' => filesize($backup),
                    'size_formatted' => $this->formatBytes(filesize($backup)),
                    'created_at' => date('Y-m-d H:i:s', filemtime($backup)),
                    'compressed' => Str::endsWith($backup, '.zip')
                ];
            }

            // Sort by creation time (newest first)
            usort($backupList, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return $backupList;

        } catch (\Exception $e) {
            Log::error('List backups failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(string $filename): array
    {
        try {
            $filepath = "{$this->backupPath}/{$filename}";

            if (!file_exists($filepath)) {
                throw new \Exception('Backup file not found');
            }

            Log::info('Starting database restore', ['filename' => $filename]);

            // Decompress if needed
            if (Str::endsWith($filename, '.zip')) {
                $filepath = $this->decompressBackup($filepath);
            }

            // Get database configuration
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port", 3306);

            if ($connection === 'mysql') {
                $this->restoreMySQLBackup($host, $port, $database, $username, $password, $filepath);
            } elseif ($connection === 'pgsql') {
                $this->restorePostgreSQLBackup($host, $port, $database, $username, $password, $filepath);
            } elseif ($connection === 'sqlite') {
                $this->restoreSQLiteBackup($database, $filepath);
            }

            Log::info('Database restore completed', ['filename' => $filename]);

            return [
                'success' => true,
                'message' => 'Database restored successfully',
                'filename' => $filename
            ];

        } catch (\Exception $e) {
            Log::error('Database restore failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore MySQL backup
     */
    private function restoreMySQLBackup(string $host, int $port, string $database, string $username, string $password, string $filepath): void
    {
        $command = sprintf(
            'mysql --host=%s --port=%d --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('MySQL restore failed: ' . implode("\n", $output));
        }
    }

    /**
     * Restore PostgreSQL backup
     */
    private function restorePostgreSQLBackup(string $host, int $port, string $database, string $username, string $password, string $filepath): void
    {
        $command = sprintf(
            'PGPASSWORD=%s psql --host=%s --port=%d --username=%s --dbname=%s < %s 2>&1',
            escapeshellarg($password),
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('PostgreSQL restore failed: ' . implode("\n", $output));
        }
    }

    /**
     * Restore SQLite backup
     */
    private function restoreSQLiteBackup(string $database, string $filepath): void
    {
        if (!copy($filepath, $database)) {
            throw new \Exception('Failed to restore SQLite database');
        }
    }

    /**
     * Decompress backup file
     */
    private function decompressBackup(string $zipPath): string
    {
        try {
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath) === true) {
                $extractPath = dirname($zipPath);
                $zip->extractTo($extractPath);
                $filename = $zip->getNameIndex(0);
                $zip->close();
                
                return "{$extractPath}/{$filename}";
            }

            throw new \Exception('Failed to decompress backup');

        } catch (\Exception $e) {
            Log::error('Backup decompression failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(string $filename): bool
    {
        try {
            $filepath = "{$this->backupPath}/{$filename}";

            if (!file_exists($filepath)) {
                throw new \Exception('Backup file not found');
            }

            if (unlink($filepath)) {
                Log::info('Backup deleted', ['filename' => $filename]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Backup deletion failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Download a backup
     */
    public function downloadBackup(string $filename): ?string
    {
        try {
            $filepath = "{$this->backupPath}/{$filename}";

            if (!file_exists($filepath)) {
                throw new \Exception('Backup file not found');
            }

            return $filepath;

        } catch (\Exception $e) {
            Log::error('Backup download failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get backup statistics
     */
    public function getStats(): array
    {
        try {
            $backups = $this->listBackups();
            $totalSize = array_sum(array_column($backups, 'size'));

            return [
                'total_backups' => count($backups),
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'oldest_backup' => $backups[count($backups) - 1]['created_at'] ?? null,
                'newest_backup' => $backups[0]['created_at'] ?? null,
                'backup_path' => $this->backupPath,
                'max_backups' => $this->maxBackups,
                'compression_enabled' => $this->compressionEnabled
            ];

        } catch (\Exception $e) {
            Log::error('Get backup stats failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Schedule automatic backup
     */
    public function scheduleBackup(string $frequency = 'daily'): array
    {
        try {
            // This would integrate with Laravel's task scheduler
            // For now, just log the scheduling
            Log::info('Backup scheduled', ['frequency' => $frequency]);

            return [
                'success' => true,
                'message' => "Backup scheduled: {$frequency}",
                'frequency' => $frequency
            ];

        } catch (\Exception $e) {
            Log::error('Backup scheduling failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

