#!/usr/bin/env bash
# Encode one source video into the three 9:16 renditions + WebP poster used by the feed.
# Run locally (needs ffmpeg + ffprobe with libx264, aac and libwebp), then upload the
# output folder to the CDN and paste the URLs/paths into the admin.
#
# Usage: tools/encode.sh <input-video> <output-dir> [poster-time-seconds]
# Output: <output-dir>/360.mp4 540.mp4 720.mp4 poster.webp
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <input-video> <output-dir> [poster-time-seconds]" >&2
  exit 1
fi

INPUT=$1
OUT=$2
POSTER_AT=${3:-1}
FPS=30
GOP=$((FPS * 2)) # keyframe every 2 s

command -v ffmpeg >/dev/null || { echo "ffmpeg not found" >&2; exit 1; }
command -v ffprobe >/dev/null || { echo "ffprobe not found" >&2; exit 1; }
[[ -f "$INPUT" ]] || { echo "Input not found: $INPUT" >&2; exit 1; }
mkdir -p "$OUT"

# Scale to cover the 9:16 frame, then center-crop.
vf() {
  echo "scale=$1:$2:force_original_aspect_ratio=increase,crop=$1:$2,setsar=1,fps=$FPS"
}

# width height video-kbps audio-kbps name
encode() {
  local w=$1 h=$2 vk=$3 ak=$4 name=$5
  echo "==> $name (${w}x${h}, ${vk}k video)"
  ffmpeg -hide_banner -loglevel error -y -i "$INPUT" \
    -map 0:v:0 -map '0:a:0?' \
    -vf "$(vf "$w" "$h")" \
    -c:v libx264 -preset slow -profile:v main -level:v 3.1 -pix_fmt yuv420p \
    -b:v "${vk}k" -maxrate "$((vk * 11 / 10))k" -bufsize "$((vk * 2))k" \
    -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
    -c:a aac -b:a "${ak}k" -ac 2 -ar 44100 \
    -movflags +faststart \
    "$OUT/$name.mp4"
}

encode 360 640 350 64 360
encode 540 960 600 96 540
encode 720 1280 1200 128 720

# Poster: clamp the timestamp to the clip length.
DURATION=$(ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "$INPUT")
if awk -v d="$DURATION" -v t="$POSTER_AT" 'BEGIN { exit !(t >= d) }'; then
  POSTER_AT=0
fi
echo "==> poster.webp (at ${POSTER_AT}s)"
ffmpeg -hide_banner -loglevel error -y -ss "$POSTER_AT" -i "$INPUT" \
  -frames:v 1 -vf "$(vf 720 1280)" \
  -c:v libwebp -quality 80 -compression_level 6 \
  "$OUT/poster.webp"

printf '\nDone. Duration for the admin form: %.0f seconds\n' "$DURATION"
ls -l "$OUT"
