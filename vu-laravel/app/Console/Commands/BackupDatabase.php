<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseBackupService;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--type=full : Type of backup (full, incremental)}';
    protected $description = 'Create a database backup';

    private $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle()
    {
        $type = $this->option('type');
        
        $this->info("Creating {$type} database backup...");
        
        $result = $this->backupService->createBackup($type);
        
        if ($result['success']) {
            $this->info("✓ Backup created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Filename', $result['filename']],
                    ['Size', $result['size_formatted']],
                    ['Type', $result['type']],
                    ['Compressed', $result['compressed'] ? 'Yes' : 'No'],
                ]
            );
        } else {
            $this->error("✗ Backup failed: {$result['error']}");
            return 1;
        }
        
        return 0;
    }
}

