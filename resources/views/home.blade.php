<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WSTATUS — Biar Status WhatsApp nggak pecah!</title>
    <meta name="description" content="Optimalkan video supaya tetap jernih saat di-upload ke WhatsApp Status.">
    @if (file_exists(public_path('css/app.css')))
        <style>
            {!! file_get_contents(public_path('css/app.css')) !!}
        </style>
    @else
        <link rel="stylesheet" href="/css/app.css">
    @endif
</head>
<body>
    <div class="page">
        <header class="hero">
            <div class="brand">
                <span class="brand-mark">W</span>
                <span>WSTATUS</span>
            </div>

            <h1>Biar Status WhatsApp <span>nggak pecah!</span></h1>

            <p class="lede">
                Pernah upload video ke Status WhatsApp, tapi hasilnya malah buram?
                Tenang, kami bantu optimalkan videomu supaya tetap jernih saat di-upload.
            </p>
        </header>

        <main class="card">
            <form id="encode-form"
                  data-max-mb="{{ $maxInputMb }}"
                  data-max-seconds="{{ $maxOutputSeconds }}"
                  data-split-seconds="{{ $splitSegment }}"
                  novalidate>
                <label class="dropzone" id="dropzone" for="video-input">
                    <input id="video-input" name="video" type="file" accept="video/*" hidden>

                    <svg class="drop-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 16V4m0 0 4 4m-4-4L8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>

                    <span class="drop-title">Pilih video atau masukkan ke sini</span>
                    <span class="drop-hint">MP4, MOV, MKV, WebM · Maksimal {{ $maxInputMb }} MB</span>
                </label>

                <section id="editor" class="editor" hidden>
                    <div class="preview">
                        <video id="preview" controls playsinline preload="metadata"></video>
                        <div class="file-line">
                            <span id="file-name" class="file-name"></span>
                            <span id="file-duration" class="pill"></span>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="control-head">
                            <span>Rentang</span>
                            <strong id="range-label">00:00 – 00:00 (00:00)</strong>
                        </div>

                        <label class="sr-only" for="start-range">Mulai</label>
                        <input id="start-range" type="range" min="0" max="1" step="0.1" value="0">

                        <label class="sr-only" for="end-range">Selesai</label>
                        <input id="end-range" type="range" min="0" max="1" step="0.1" value="1">

                        <p id="trim-hint" class="hint"></p>

                        <fieldset class="mode">
                            <legend>Hasil</legend>

                            <label class="choice">
                                <input type="radio" name="mode" value="single" checked>
                                <span>
                                    <strong>1 video</strong>
                                    <small>Maksimal 60 detik, satu posting</small>
                                </span>
                            </label>

                            <label class="choice" id="split-choice">
                                <input type="radio" name="mode" value="split">
                                <span>
                                    <strong>2 video</strong>
                                    <small>30 detik per file, kualitas terbaik</small>
                                </span>
                            </label>
                        </fieldset>
                    </div>
                </section>

                <button id="submit-button" class="primary" type="submit" disabled>
                    Bikin Video Lebih Jernih
                </button>
            </form>

            <section id="progress-wrap" class="progress-card" hidden aria-live="polite">
                <div class="progress-head">
                    <span id="progress-label">Mengunggah…</span>
                    <strong id="progress-value">0%</strong>
                </div>
                <div class="progress-track">
                    <div id="progress-bar" class="progress-bar"></div>
                </div>
            </section>

            <div id="error-wrap" class="alert" role="alert" hidden></div>

            <section id="result-wrap" class="result-card" hidden>
                <div class="result-head">
                    <h2>Selesai</h2>
                    <span class="check" aria-hidden="true">✓</span>
                </div>
                <p class="hint">Unduh, lalu upload satu per satu ke WhatsApp Status.</p>
                <div id="downloads" class="downloads"></div>
            </section>
        </main>

        <footer class="foot">
            <p>Video kamu akan diproses otomatis dan dihapus dari server maksimal dalam 1 jam.</p>
            <p>WhatsApp memang tetap akan mengompres video saat di-upload, tetapi kami berusaha menjaga kualitasnya agar tetap jernih.</p>
        </footer>
    </div>

    <noscript>
        <p class="noscript">Wstatus butuh JavaScript untuk mengunggah dan memproses video.</p>
    </noscript>

    @if (file_exists(public_path('js/app.js')))
        <script>
            {!! file_get_contents(public_path('js/app.js')) !!}
        </script>
    @else
        <script src="/js/app.js" defer></script>
    @endif
</body>
</html>
