(() => {
    'use strict';

    const form = document.getElementById('encode-form');
    if (!form) return;

    const maxMb = Number(form.dataset.maxMb);
    const maxSeconds = Number(form.dataset.maxSeconds);
    const splitSeconds = Number(form.dataset.splitSeconds);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('video-input');
    const editor = document.getElementById('editor');
    const preview = document.getElementById('preview');
    const fileName = document.getElementById('file-name');
    const fileDuration = document.getElementById('file-duration');
    const startRange = document.getElementById('start-range');
    const endRange = document.getElementById('end-range');
    const rangeLabel = document.getElementById('range-label');
    const trimHint = document.getElementById('trim-hint');
    const splitChoice = document.getElementById('split-choice');
    const splitRadio = splitChoice.querySelector('input');
    const singleRadio = document.querySelector('input[name="mode"][value="single"]');
    const submitButton = document.getElementById('submit-button');
    const progressWrap = document.getElementById('progress-wrap');
    const progressLabel = document.getElementById('progress-label');
    const progressValue = document.getElementById('progress-value');
    const progressBar = document.getElementById('progress-bar');
    const errorWrap = document.getElementById('error-wrap');
    const resultWrap = document.getElementById('result-wrap');
    const downloads = document.getElementById('downloads');

    let file = null;
    let duration = 0;
    let previewUrl = null;
    let busy = false;

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    function formatTime(seconds) {
        const total = Math.max(0, Math.round(Number(seconds) || 0));
        const minutes = Math.floor(total / 60);
        const rest = total % 60;

        return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
    }

    function formatSize(bytes) {
        if (!bytes) return '';

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function showError(message) {
        errorWrap.textContent = message;
        errorWrap.hidden = false;
    }

    function clearError() {
        errorWrap.textContent = '';
        errorWrap.hidden = true;
    }

    function setProgress(value, label) {
        const percent = clamp(Math.round(value), 0, 100);
        progressWrap.hidden = false;
        progressBar.style.width = `${percent}%`;
        progressValue.textContent = `${percent}%`;
        progressLabel.textContent = label;
    }

    function setBusy(value) {
        busy = value;
        submitButton.disabled = value || !file;
        submitButton.textContent = value ? 'Memproses…' : 'Bikin Video Lebih Jernih';
    }

    function resetResults() {
        resultWrap.hidden = true;
        downloads.replaceChildren();
        progressWrap.hidden = true;
        progressBar.style.width = '0%';
        progressValue.textContent = '0%';
    }

    function updateRange(changed) {
        let start = Number(startRange.value);
        let end = Number(endRange.value);

        if (changed === 'start') {
            if (end - start > maxSeconds) end = start + maxSeconds;
            if (end <= start) end = Math.min(duration, start + 1);
        } else {
            if (end - start > maxSeconds) start = end - maxSeconds;
            if (end <= start) start = Math.max(0, end - 1);
        }

        start = clamp(start, 0, duration);
        end = clamp(end, 0, duration);
        startRange.value = start.toFixed(1);
        endRange.value = end.toFixed(1);

        const length = end - start;
        rangeLabel.textContent = `${formatTime(start)} – ${formatTime(end)} (${formatTime(length)})`;
        splitRadio.disabled = length <= splitSeconds;
        splitChoice.classList.toggle('is-disabled', splitRadio.disabled);

        if (splitRadio.disabled && splitRadio.checked) {
            singleRadio.checked = true;
        }

        if (duration > maxSeconds) {
            trimHint.textContent = `Video ${formatTime(duration)}. Maksimal ${maxSeconds} detik per proses — geser slider untuk memilih bagian yang lain.`;
        } else {
            trimHint.textContent = '';
        }
    }

    function loadFile(nextFile) {
        if (!nextFile || busy) return;

        clearError();
        resetResults();

        if (!nextFile.type.startsWith('video/')) {
            showError('File harus berupa video.');
            return;
        }

        if (nextFile.size > maxMb * 1024 * 1024) {
            showError(`Ukuran video maksimal ${maxMb} MB.`);
            return;
        }

        if (previewUrl) URL.revokeObjectURL(previewUrl);

        file = nextFile;
        previewUrl = URL.createObjectURL(nextFile);
        preview.src = previewUrl;
        preview.load();

        fileName.textContent = nextFile.name;
        editor.hidden = false;
        submitButton.disabled = true;

        preview.onloadedmetadata = () => {
            duration = Number(preview.duration);

            if (!Number.isFinite(duration) || duration <= 0) {
                showError('Durasi video tidak terbaca. Coba file MP4 lain.');
                return;
            }

            if (duration > 300) {
                showError('Video terlalu panjang. Maksimal 5 menit.');
                return;
            }

            fileDuration.textContent = formatTime(duration);
            startRange.min = '0';
            startRange.max = duration.toFixed(1);
            endRange.min = '0';
            endRange.max = duration.toFixed(1);
            startRange.value = '0';
            endRange.value = Math.min(duration, maxSeconds).toFixed(1);

            updateRange('end');
            setBusy(false);
        };

        preview.onerror = () => {
            showError('Video tidak bisa diputar di browser ini.');
            submitButton.disabled = true;
        };
    }

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-over');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-over');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        loadFile(event.dataTransfer.files[0]);
    });

    input.addEventListener('change', () => {
        loadFile(input.files[0]);
    });

    startRange.addEventListener('input', () => updateRange('start'));
    endRange.addEventListener('input', () => updateRange('end'));

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!file || busy) return;

        clearError();
        resetResults();
        setBusy(true);
        setProgress(0, 'Mengunggah…');

        const data = new FormData();
        data.append('video', file);
        data.append('start', startRange.value);
        data.append('end', endRange.value);
        data.append('mode', document.querySelector('input[name="mode"]:checked').value);

        upload(data)
            .then((response) => {
                let payload = {};

                try {
                    payload = JSON.parse(response.body);
                } catch {
                    payload = {};
                }

                if (response.status === 202) {
                    setProgress(5, 'Menunggu antrean…');
                    poll(payload.status_url);
                    return;
                }

                if (response.status === 419) {
                    showError('Sesi kedaluwarsa. Refresh halaman lalu coba lagi.');
                } else if (response.status === 429) {
                    showError(payload.message || 'Terlalu banyak percobaan. Tunggu beberapa menit.');
                } else {
                    showError(payload.message || 'Gagal mengunggah video.');
                }

                setBusy(false);
                progressWrap.hidden = true;
            })
            .catch(() => {
                showError('Tidak bisa menghubungi server. Coba lagi.');
                setBusy(false);
                progressWrap.hidden = true;
            });
    });

    function upload(data) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/encode');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);

            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) return;

                const percent = Math.round((event.loaded / event.total) * 100);
                setProgress(Math.min(95, percent), `Mengunggah… ${percent}%`);
            });

            xhr.addEventListener('load', () => {
                resolve({ status: xhr.status, body: xhr.responseText });
            });

            xhr.addEventListener('error', () => reject(new Error('upload')));
            xhr.addEventListener('abort', () => reject(new Error('abort')));
            xhr.send(data);
        });
    }

    function poll(url) {
        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error('poll');

                return response.json();
            })
            .then((payload) => {
                if (payload.status === 'done') {
                    setProgress(100, 'Selesai');
                    renderDownloads(payload.downloads || []);
                    setBusy(false);

                    return;
                }

                if (payload.status === 'failed') {
                    showError(payload.error || 'Gagal memproses video.');
                    setBusy(false);
                    progressWrap.hidden = true;

                    return;
                }

                if (payload.status === 'processing') {
                    setProgress(payload.progress || 0, 'Mengoptimalkan video Full HD…');
                } else {
                    setProgress(payload.progress || 0, 'Menunggu antrean…');
                }

                window.setTimeout(() => poll(url), 1000);
            })
            .catch(() => {
                showError('Koneksi ke server terputus. Coba lagi.');
                setBusy(false);
                progressWrap.hidden = true;
            });
    }

    function renderDownloads(items) {
        downloads.replaceChildren();

        items.forEach((item, index) => {
            const link = document.createElement('a');
            link.className = 'download';
            link.href = item.url;
            link.download = item.name;

            const label = document.createElement('span');
            label.textContent = items.length > 1 ? `Part ${index + 1}` : 'Video';

            const meta = document.createElement('span');
            meta.className = 'download-meta';
            meta.textContent = [item.name, formatSize(item.size)].filter(Boolean).join(' · ');

            const button = document.createElement('span');
            button.className = 'download-button';
            button.textContent = 'Unduh';

            link.append(label, meta, button);
            downloads.append(link);
        });

        resultWrap.hidden = false;
    }
})();
