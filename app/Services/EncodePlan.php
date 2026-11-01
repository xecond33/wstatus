<?php

namespace App\Services;

class EncodePlan
{
    /**
     * @return list<float>
     */
    public static function segmentDurations(float $duration, string $mode): array
    {
        if ($mode === 'single') {
            return [$duration];
        }

        $segments = [];
        $remaining = $duration;
        $segmentLength = (float) config('wstatus.split_segment');

        while ($remaining > 0.01) {
            $segments[] = min($segmentLength, $remaining);
            $remaining -= $segmentLength;
        }

        return $segments;
    }

    public static function videoKbps(float $seconds): int
    {
        $seconds = max(1.0, $seconds);
        $totalKbps = (config('wstatus.size_budget_bytes') * 8) / 1000 / $seconds;
        $videoKbps = (int) floor($totalKbps - config('wstatus.audio_kbps'));

        return max(
            (int) config('wstatus.min_video_kbps'),
            min((int) config('wstatus.max_video_kbps'), $videoKbps),
        );
    }
}
