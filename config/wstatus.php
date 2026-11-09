<?php

return [

    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    'preset' => env('WSTATUS_PRESET', 'medium'),

    'max_input_mb' => (int) env('WSTATUS_MAX_INPUT_MB', 200),
    'max_input_duration' => 300,
    'max_output_duration' => 60,
    'split_segment' => 30,

    'size_budget_bytes' => 11 * 1024 * 1024,
    'max_output_bytes' => 12 * 1024 * 1024,
    'audio_kbps' => 128,
    'max_video_kbps' => 5000,
    'min_video_kbps' => 800,

    'max_queue' => 20,
    'output_ttl_minutes' => 60,
    'download_ttl_minutes' => 30,

    'ffmpeg_timeout' => 600,
    'ffprobe_timeout' => 60,

];
