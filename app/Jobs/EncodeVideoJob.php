<?php

namespace App\Jobs;

use App\Models\EncodeJob;
use App\Services\EncodePlan;
use App\Services\FfmpegEncoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EncodeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public string $jobId)
    {
    }

    public function handle(FfmpegEncoder $encoder): void
    {
        $job = EncodeJob::find($this->jobId);

        if ($job === null || $job->status !== 'queued') {
            return;
        }

        $job->update(['status' => 'processing', 'progress' => 5]);

        $input = Storage::disk('local')->path($job->input_path);
        $directory = "wstatus/{$job->id}";
        $segments = EncodePlan::segmentDurations((float) $job->duration, $job->mode);
        $segmentCount = max(1, count($segments));
        $start = (float) ($job->meta['start'] ?? 0);
        $isHdr = (bool) ($job->meta['is_hdr'] ?? false);
        $outputs = [];
        $offset = 0.0;

        try {
            foreach ($segments as $index => $segmentDuration) {
                $outputName = 'wstatus-'.($index + 1).'.mp4';
                $outputPath = "{$directory}/{$outputName}";
                $absoluteOutput = Storage::disk('local')->path($outputPath);
                $segmentStart = $start + $offset;
                $videoKbps = EncodePlan::videoKbps($segmentDuration);

                $encode = function (int $kbps) use ($encoder, $input, $absoluteOutput, $segmentStart, $segmentDuration, $isHdr): void {
                    $encoder->encode(
                        input: $input,
                        output: $absoluteOutput,
                        start: $segmentStart,
                        duration: $segmentDuration,
                        videoKbps: $kbps,
                        isHdr: $isHdr,
                    );
                };

                $encode($videoKbps);

                $size = (int) (filesize($absoluteOutput) ?: 0);

                if ($size > config('wstatus.max_output_bytes')) {
                    Storage::disk('local')->delete($outputPath);
                    $videoKbps = max(
                        (int) config('wstatus.min_video_kbps'),
                        (int) floor($videoKbps * 0.85),
                    );

                    $encode($videoKbps);
                    $size = (int) (filesize($absoluteOutput) ?: 0);

                    if ($size > config('wstatus.max_output_bytes')) {
                        throw new RuntimeException('Hasil encode melebihi batas ukuran.');
                    }
                }

                $outputs[] = [
                    'name' => $outputName,
                    'path' => $outputPath,
                    'size' => $size,
                ];

                $job->update([
                    'progress' => (int) round((($index + 1) / $segmentCount) * 100),
                ]);

                $offset += $segmentDuration;
            }

            $job->update([
                'status' => 'done',
                'progress' => 100,
                'outputs' => $outputs,
                'error' => null,
                'expires_at' => now()->addMinutes(config('wstatus.output_ttl_minutes')),
            ]);

            Storage::disk('local')->delete($job->input_path);
        } catch (Throwable $e) {
            Log::error('Wstatus encode failed', ['job' => $job->id, 'exception' => $e]);
            Storage::disk('local')->deleteDirectory($directory);
            Storage::disk('local')->delete($job->input_path);

            $job->update([
                'status' => 'failed',
                'progress' => 0,
                'error' => 'Gagal memproses video. Coba lagi dengan file lain.',
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        $job = EncodeJob::find($this->jobId);

        if ($job === null) {
            return;
        }

        Storage::disk('local')->deleteDirectory("wstatus/{$job->id}");
        Storage::disk('local')->delete($job->input_path);

        $job->update([
            'status' => 'failed',
            'progress' => 0,
            'error' => 'Proses terlalu lama atau terhenti. Coba video yang lebih pendek.',
        ]);
    }
}
