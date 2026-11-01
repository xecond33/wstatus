<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoProbe
{
    /**
     * @return array{duration: float, width: int, height: int, has_audio: bool, is_hdr: bool}
     */
    public function probe(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('File video tidak ditemukan.');
        }

        $process = new Process([
            config('wstatus.ffprobe'),
            '-v', 'error',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path,
        ]);

        $process->setTimeout((float) config('wstatus.ffprobe_timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Gagal membaca video: '.$process->getErrorOutput());
        }

        $data = json_decode($process->getOutput(), true);

        if (! is_array($data)) {
            throw new RuntimeException('Metadata video tidak valid.');
        }

        $video = null;
        $hasAudio = false;

        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && $video === null) {
                $video = $stream;
            }

            if (($stream['codec_type'] ?? null) === 'audio') {
                $hasAudio = true;
            }
        }

        if ($video === null) {
            throw new RuntimeException('File tidak memiliki stream video.');
        }

        $duration = (float) ($data['format']['duration'] ?? $video['duration'] ?? 0);

        if ($duration <= 0) {
            throw new RuntimeException('Durasi video tidak terbaca.');
        }

        $colorTransfer = strtolower((string) ($video['color_transfer'] ?? ''));
        $colorPrimaries = strtolower((string) ($video['color_primaries'] ?? ''));

        return [
            'duration' => $duration,
            'width' => (int) ($video['width'] ?? 0),
            'height' => (int) ($video['height'] ?? 0),
            'has_audio' => $hasAudio,
            'is_hdr' => in_array($colorTransfer, ['smpte2084', 'arib-std-b67'], true)
                || $colorPrimaries === 'bt2020',
        ];
    }
}
