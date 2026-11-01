<?php

namespace App\Console\Commands;

use App\Models\EncodeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupWstatus extends Command
{
    protected $signature = 'wstatus:cleanup';

    protected $description = 'Hapus job dan file Wstatus yang sudah kedaluwarsa';

    public function handle(): int
    {
        $jobs = EncodeJob::query()
            ->where(function ($query) {
                $query->where('expires_at', '<=', now())
                    ->orWhere(function ($query) {
                        $query->whereIn('status', ['done', 'failed'])
                            ->where('updated_at', '<=', now()->subHour());
                    });
            })
            ->get();

        foreach ($jobs as $job) {
            Storage::disk('local')->deleteDirectory("wstatus/{$job->id}");
            $job->delete();
        }

        $this->info("Dibersihkan: {$jobs->count()} job.");

        return self::SUCCESS;
    }
}
