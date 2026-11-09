<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class FfmpegEncoder
{
    public function encode(
        string $input,
        string $output,
        float $start,
        float $duration,
        int $videoKbps,
        bool $isHdr,
    ): void {
        File::ensureDirectoryExists(dirname($output));

        $process = new Process([
            config('wstatus.ffmpeg'),
            '-hide_banner',
            '-loglevel', 'error',
            '-nostdin',
            '-threads', '0',
            '-ss', number_format($start, 3, '.', ''),
            '-i', $input,
            '-t', number_format($duration, 3, '.', ''),
            '-map', '0:v:0',
            '-map', '0:a:0?',
            '-vf', $this->filters($isHdr),
            '-c:v', 'libx264',
            '-preset', config('wstatus.preset'),
            '-profile:v', 'high',
            '-level', '4.1',
            '-pix_fmt', 'yuv420p',
            '-color_primaries', 'bt709',
            '-color_trc', 'bt709',
            '-colorspace', 'bt709',
            '-color_range', 'tv',
            '-crf', '20',
            '-maxrate', (int) round($videoKbps * 1.15).'k',
            '-bufsize', (int) round($videoKbps * 2).'k',
            '-g', '60',
            '-keyint_min', '60',
            '-sc_threshold', '0',
            '-c:a', 'aac',
            '-b:a', config('wstatus.audio_kbps').'k',
            '-ar', '48000',
            '-ac', '2',
            '-sn',
            '-dn',
            '-map_metadata', '-1',
            '-movflags', '+faststart',
            '-y',
            $output,
        ]);

        $process->setTimeout((float) config('wstatus.ffmpeg_timeout'));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($output)) {
            throw new RuntimeException('FFmpeg gagal: '.$process->getErrorOutput());
        }
    }

    private function filters(bool $isHdr): string
    {
        $filters = [];

        if ($isHdr) {
            $filters[] = 'zscale=t=linear:npl=100';
            $filters[] = 'format=gbrpf32le';
            $filters[] = 'zscale=p=bt709';
            $filters[] = 'tonemap=tonemap=hable:desat=0';
            $filters[] = 'zscale=t=bt709:m=bt709:r=tv';
            $filters[] = 'format=yuv420p';
        }

        $filters[] = 'scale=1080:1920:force_original_aspect_ratio=decrease';
        $filters[] = 'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black';
        $filters[] = 'setsar=1';
        $filters[] = 'fps=30';

        return implode(',', $filters);
    }
}
