<?php

namespace App\Http\Controllers;

use App\Jobs\EncodeVideoJob;
use App\Models\EncodeJob;
use App\Services\VideoProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class EncodeController extends Controller
{
    public function index()
    {
        return view('home', [
            'maxInputMb' => config('wstatus.max_input_mb'),
            'maxOutputSeconds' => config('wstatus.max_output_duration'),
            'splitSegment' => config('wstatus.split_segment'),
        ]);
    }

    public function store(Request $request, VideoProbe $probe): JsonResponse
    {
        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'max:'.(config('wstatus.max_input_mb') * 1024),
                'mimes:mp4,mov,mkv,webm,avi,3gp',
            ],
            'start' => ['required', 'numeric', 'min:0'],
            'end' => ['required', 'numeric', 'gt:start'],
            'mode' => ['required', 'in:single,split'],
        ]);

        if (EncodeJob::whereIn('status', ['queued', 'processing'])->count() >= config('wstatus.max_queue')) {
            return response()->json([
                'message' => 'Server sedang sibuk. Coba lagi beberapa menit.',
            ], 503);
        }

        $id = (string) Str::uuid();
        $file = $request->file('video');
        $path = $file->storeAs("wstatus/{$id}", 'input.'.$file->extension(), 'local');

        try {
            $meta = $probe->probe(Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Storage::disk('local')->deleteDirectory("wstatus/{$id}");
            Log::warning('Wstatus probe failed', ['exception' => $e]);

            return response()->json([
                'message' => 'File video tidak bisa dibaca. Coba file lain.',
            ], 422);
        }

        $start = (float) $validated['start'];
        $end = min((float) $validated['end'], $meta['duration']);
        $duration = $end - $start;

        $error = $this->validateRange($duration, $validated['mode'], $meta['duration']);

        if ($error !== null) {
            Storage::disk('local')->deleteDirectory("wstatus/{$id}");

            return response()->json(['message' => $error], 422);
        }

        $job = EncodeJob::create([
            'id' => $id,
            'status' => 'queued',
            'mode' => $validated['mode'],
            'progress' => 0,
            'duration' => round($duration, 2),
            'input_path' => $path,
            'meta' => [
                'start' => $start,
                'end' => $end,
                'source_duration' => $meta['duration'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'has_audio' => $meta['has_audio'],
                'is_hdr' => $meta['is_hdr'],
            ],
        ]);

        EncodeVideoJob::dispatch($job->id);

        return response()->json([
            'id' => $job->id,
            'status_url' => route('encode.show', $job),
        ], 202);
    }

    public function show(EncodeJob $job): JsonResponse
    {
        return response()->json($this->payload($job));
    }

    public function download(EncodeJob $job, int $index): BinaryFileResponse
    {
        $output = $job->outputs[$index] ?? null;

        abort_unless($job->status === 'done' && $output !== null, 404);

        $path = Storage::disk('local')->path($output['path']);

        abort_unless(is_file($path), 404);

        return response()->download($path, $output['name']);
    }

    private function validateRange(float $duration, string $mode, float $sourceDuration): ?string
    {
        if ($sourceDuration > config('wstatus.max_input_duration')) {
            return 'Video terlalu panjang. Maksimal 5 menit.';
        }

        if ($duration < 1) {
            return 'Pilih rentang minimal 1 detik.';
        }

        if ($duration > config('wstatus.max_output_duration')) {
            return 'Maksimal 60 detik per proses. Potong dulu videonya.';
        }

        if ($mode === 'split' && $duration <= config('wstatus.split_segment')) {
            return 'Mode 2 video butuh durasi lebih dari 30 detik. Pakai mode 1 video.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EncodeJob $job): array
    {
        $downloads = [];

        if ($job->status === 'done') {
            foreach (array_values($job->outputs ?? []) as $index => $output) {
                $downloads[] = [
                    'name' => $output['name'],
                    'size' => $output['size'],
                    'url' => URL::temporarySignedRoute(
                        'encode.download',
                        now()->addMinutes(config('wstatus.download_ttl_minutes')),
                        ['job' => $job->id, 'index' => $index],
                    ),
                ];
            }
        }

        return [
            'id' => $job->id,
            'status' => $job->status,
            'progress' => $job->progress,
            'mode' => $job->mode,
            'duration' => $job->duration,
            'downloads' => $downloads,
            'error' => $job->error,
        ];
    }
}
