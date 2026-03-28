/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Add subtitles to audio/video
 */
document.addEventListener('DOMContentLoaded', () => {
    function toSeconds(ts) {
        const [h, m, s] = ts.split(":").map(parseFloat);
        return h * 3600 + m * 60 + s;
    }


    function parseVTT(vtt) {
        return vtt
            .split(/\r?\n\r?\n/)
            .map(block => {
                const lines = block.trim().split(/\r?\n/);

                if (lines.length >= 2 && lines[0].includes("-->")) {
                    const [start, end] = lines[0].split("-->").map(t => toSeconds(t.trim()));
                    return { start, end, text: lines.slice(1).join("\n") };
                }
            })
            .filter(Boolean);
    }


    document.querySelectorAll('.cms-content .audio, .cms-content .video').forEach(el => {
        const media = el.querySelector('video') || el.querySelector('audio')
        const transcript = el.querySelector('.transcription')
        const caption = el.querySelector('.caption')
        const text = transcript?.textContent;

        if(media && caption && text) {
            const cues = parseVTT(text);

            media.addEventListener("timeupdate", () => {
                caption.textContent = cues.find(c => media.currentTime >= c.start && media.currentTime <= c.end)?.text || '';
            });

            // add track for video nevertheless
            const blob = new Blob([text], { type: 'text/vtt' });
            const track = document.createElement('track');

            track.kind = 'subtitles';
            track.src = URL.createObjectURL(blob);
            track.srclang = transcript.getAttribute('lang')

            media.appendChild(track);
        }
    });
});