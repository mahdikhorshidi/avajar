/*
 * Avajar reels feed. Vanilla JS, no build step.
 *
 * - IntersectionObserver plays the visible reel and pauses the rest.
 * - Only the next reel gets preload="auto"; everything else stays "none".
 * - Quality starts at 360p. Later reels pick a rendition from
 *   navigator.connection (when present) plus measured throughput.
 *   A rendition is chosen when a reel's source is assigned, so the
 *   playing video never switches mid-playback.
 * - Save-Data or very low throughput: poster only, playback on tap.
 */
(function () {
  'use strict';

  var feed = document.getElementById('feed');
  if (!feed) return;

  var LEVELS = ['360', '540', '720'];
  // Approximate total bitrate (video + audio) per rendition, in Mbps.
  var LEVEL_MBPS = [0.42, 0.7, 1.33];
  // Estimated throughput needed to pick a rendition (about 2x headroom).
  var UP_THRESHOLDS = [0, 1.4, 2.7];
  var VERY_LOW_MBPS = 0.3;   // below this: poster-only mode
  var RECOVER_MBPS = 0.8;    // leave measured poster-only mode above this
  var STALL_WINDOW_MS = 30000;
  var STALLS_TO_DOWNGRADE = 2;
  var UPGRADE_HOLD_MS = 60000;
  var PROBE_BYTES = 196608;  // 192 KB
  var PROBE_TIMEOUT_MS = 4000;

  var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
  var level = 0;
  var measuredMbps = null;   // EWMA of probe + video download samples
  var stalls = [];
  var lastStallAt = -Infinity;
  var soundOn = false;
  var saveData = !!(conn && conn.saveData);
  var lowThroughput = false;
  var reels = [];
  var activeIndex = -1;
  var started = false;
  var ready = false;        // becomes true once the startup probe has finished
  var visibleIndex = 0;
  var hiddenWhilePlaying = false;
  var apiUrl = feed.getAttribute('data-api') || '';
  var nextCursor = feed.getAttribute('data-next-cursor') || '';
  var loadingMore = false;
  var template = document.getElementById('reel-template');

  function now() {
    return (window.performance && performance.now) ? performance.now() : Date.now();
  }

  function posterOnly() {
    return saveData || lowThroughput;
  }

  /* ---------- Throughput estimation ---------- */

  function addSample(mbps) {
    if (!(mbps >= 0) || !isFinite(mbps)) return;
    measuredMbps = measuredMbps === null ? mbps : measuredMbps * 0.6 + mbps * 0.4;
    if (measuredMbps < VERY_LOW_MBPS) lowThroughput = true;
    else if (lowThroughput && measuredMbps > RECOVER_MBPS) lowThroughput = false;
  }

  // null means unknown (e.g. Safari without navigator.connection and no sample yet).
  function estimateMbps() {
    var downlink = conn && typeof conn.downlink === 'number' && conn.downlink > 0 ? conn.downlink : null;
    if (measuredMbps !== null && downlink !== null) return Math.min(measuredMbps, downlink);
    if (measuredMbps !== null) return measuredMbps;
    return downlink;
  }

  // Active probe: time the first bytes of a 360p rendition. Needs CORS on the CDN;
  // if the request is blocked we fall back to passive measurement of video downloads.
  function probe(url) {
    return new Promise(function (resolve) {
      if (!url || !window.fetch || !window.ReadableStream) return resolve(null);
      var ctrl = window.AbortController ? new AbortController() : null;
      var t0 = now();
      var bytes = 0;
      var finished = false;
      var reader = null;

      function finish(timedOut) {
        if (finished) return;
        finished = true;
        clearTimeout(timer);
        if (reader) { try { reader.cancel(); } catch (e) { /* ignore */ } }
        if (ctrl) ctrl.abort();
        var secs = (now() - t0) / 1000;
        // A timeout with few bytes is itself a strong "very slow" signal.
        if (!timedOut && bytes < 32768) return resolve(null);
        var mbps = (bytes * 8) / secs / 1e6;
        addSample(mbps);
        resolve(mbps);
      }

      var timer = setTimeout(function () { finish(true); }, PROBE_TIMEOUT_MS);

      fetch(url, { cache: 'no-store', mode: 'cors', credentials: 'omit', signal: ctrl ? ctrl.signal : undefined })
        .then(function (res) {
          if (!res.ok || !res.body) throw new Error('probe');
          reader = res.body.getReader();
          function pump() {
            return reader.read().then(function (chunk) {
              if (finished) return;
              if (chunk.done) return finish(false);
              bytes += chunk.value.byteLength;
              if (bytes >= PROBE_BYTES) return finish(false);
              return pump();
            });
          }
          return pump();
        })
        .catch(function () {
          if (finished) return;
          finished = true;
          clearTimeout(timer);
          resolve(null);
        });
    });
  }

  function bufferedEnd(video) {
    var b = video.buffered;
    return b && b.length ? b.end(b.length - 1) : 0;
  }

  // Passive measurement: downloaded media seconds * rendition bitrate / elapsed time.
  // Time while the browser has suspended the download (preload policy) is not counted:
  // the window restarts when the reel starts playing.
  function watchDownload(video, lvl) {
    var t0 = now();
    var base = 0;
    var active = true;
    var done = false;
    function onProgress() {
      if (done || video.getAttribute('data-level') !== String(lvl)) return cleanup();
      if (!active) return;
      var gained = bufferedEnd(video) - base;
      var target = Math.min(3, (video.duration || 3) - base - 0.1);
      if (target > 0.5 && gained >= target) {
        addSample((gained * LEVEL_MBPS[lvl]) / ((now() - t0) / 1000));
        cleanup();
      }
    }
    function onSuspend() { active = false; }
    function onResume() {
      if (active || done) return;
      active = true;
      t0 = now();
      base = bufferedEnd(video);
    }
    function cleanup() {
      done = true;
      video.removeEventListener('progress', onProgress);
      video.removeEventListener('canplaythrough', onProgress);
      video.removeEventListener('suspend', onSuspend);
      video.removeEventListener('play', onResume);
    }
    video.addEventListener('progress', onProgress);
    video.addEventListener('canplaythrough', onProgress);
    video.addEventListener('suspend', onSuspend);
    video.addEventListener('play', onResume);
  }

  function chooseLevel() {
    var est = estimateMbps();
    if (est === null) return level;
    var target = 0;
    for (var i = LEVELS.length - 1; i >= 0; i--) {
      if (est >= UP_THRESHOLDS[i]) { target = i; break; }
    }
    if (target > level) {
      if (now() - lastStallAt < UPGRADE_HOLD_MS) return level;
      return level + 1; // step up one rung at a time
    }
    return target;
  }

  function onStall() {
    var t = now();
    lastStallAt = t;
    stalls.push(t);
    stalls = stalls.filter(function (s) { return t - s < STALL_WINDOW_MS; });
    if (stalls.length < STALLS_TO_DOWNGRADE) return;
    stalls = [];
    if (level > 0) {
      level--;
    } else {
      lowThroughput = true;
    }
    // Re-source the preloaded next reel if it has not started yet.
    var next = reels[activeIndex + 1];
    if (next) prepareNext(next);
  }

  /* ---------- Video source management ---------- */

  function setSource(video, lvl) {
    var src = video.getAttribute('data-src-' + LEVELS[lvl]);
    if (!src) return;
    video.setAttribute('data-level', String(lvl));
    video.src = src;
    watchDownload(video, lvl);
  }

  function release(video) {
    if (!video.getAttribute('src') || !video.paused) return;
    video.removeAttribute('src');
    video.removeAttribute('data-level');
    video.removeAttribute('data-started');
    video.load();
  }

  function prepareNext(el) {
    var v = el.video;
    if (v.hasAttribute('data-started')) return; // never touch a reel that already played
    if (posterOnly()) {
      v.preload = 'none';
      return;
    }
    level = chooseLevel();
    v.preload = 'auto';
    if (v.getAttribute('data-level') !== String(level)) setSource(v, level);
  }

  function setPlaying(el, playing) {
    el.node.classList.toggle('is-playing', playing);
    el.play.hidden = playing || !el.needsTap;
  }

  function play(el) {
    var v = el.video;
    if (!v.getAttribute('src')) {
      // First reel always starts at 360p.
      if (started) level = chooseLevel();
      setSource(v, started ? level : 0);
    }
    started = true;
    v.preload = 'auto';
    v.muted = !soundOn;
    var p = v.play();
    if (p && p.catch) {
      p.catch(function () {
        // Autoplay blocked (e.g. iOS Low Power Mode): wait for a tap.
        el.needsTap = true;
        el.play.hidden = false;
      });
    }
  }

  function activate(index) {
    if (index === activeIndex) return;
    activeIndex = index;
    for (var i = 0; i < reels.length; i++) {
      var el = reels[i];
      var v = el.video;
      if (i !== index && !v.paused) v.pause();
      if (i === index) continue;
      if (i === index + 1) {
        prepareNext(el);
      } else {
        v.preload = 'none';
        if (Math.abs(i - index) > 2) release(v);
      }
    }
    var cur = reels[index];
    if (posterOnly() && !cur.video.hasAttribute('data-started')) {
      cur.needsTap = true;
      cur.play.hidden = false;
    } else {
      play(cur);
    }
    if (index >= reels.length - 3) loadMore();
  }

  function updateSoundButtons() {
    for (var i = 0; i < reels.length; i++) {
      reels[i].sound.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
      reels[i].sound.setAttribute('aria-label', soundOn ? 'بی‌صدا کردن' : 'روشن کردن صدا');
    }
  }

  function onTap(el) {
    var v = el.video;
    if (el.needsTap && v.paused) {
      el.needsTap = false;
      el.play.hidden = true;
      if (!v.getAttribute('src')) setSource(v, posterOnly() ? 0 : chooseLevel());
      play(el);
      return;
    }
    soundOn = !soundOn;
    v.muted = !soundOn;
    updateSoundButtons();
    if (v.paused && v.getAttribute('src')) play(el);
  }

  /* ---------- Reel wiring ---------- */

  var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    for (var i = 0; i < entries.length; i++) {
      var en = entries[i];
      if (en.isIntersecting && en.intersectionRatio >= 0.6) {
        visibleIndex = reels.indexOf(en.target.reelState);
        if (ready) activate(visibleIndex);
      }
    }
  }, { root: feed, threshold: [0.6] }) : null;

  function register(article) {
    var el = {
      node: article,
      video: article.querySelector('.reel__video'),
      play: article.querySelector('.reel__play'),
      sound: article.querySelector('.reel__sound'),
      needsTap: false
    };
    article.reelState = el;
    var v = el.video;
    v.muted = true;
    v.playsInline = true;
    v.setAttribute('webkit-playsinline', '');

    v.addEventListener('playing', function () {
      v.setAttribute('data-started', '');
      el.needsTap = false;
      setPlaying(el, true);
    });
    v.addEventListener('pause', function () { setPlaying(el, false); });
    v.addEventListener('waiting', function () {
      if (reels[activeIndex] === el && v.hasAttribute('data-started')) onStall();
    });

    article.querySelector('.reel__media').addEventListener('click', function () { onTap(el); });
    el.sound.addEventListener('click', function (ev) {
      ev.stopPropagation();
      onTap(el);
    });

    reels.push(el);
    if (observer) observer.observe(article);
    else {
      // Very old browsers: plain controls, user starts playback.
      v.controls = true;
      setSource(v, 0);
    }
  }

  function buildReel(item) {
    var frag = template.content.cloneNode(true);
    var article = frag.querySelector('.reel');
    var ns = article.querySelector('noscript');
    if (ns) ns.parentNode.removeChild(ns);
    article.setAttribute('data-slug', item.slug);
    var img = article.querySelector('.reel__poster');
    img.src = item.poster;
    var v = article.querySelector('.reel__video');
    v.setAttribute('data-src-360', item.sources['360']);
    v.setAttribute('data-src-540', item.sources['540']);
    v.setAttribute('data-src-720', item.sources['720']);
    v.setAttribute('aria-label', item.title);
    var a = article.querySelector('.reel__title a');
    a.href = item.url;
    a.textContent = item.title;
    article.querySelector('.reel__caption').textContent = item.caption;
    return article;
  }

  function loadMore() {
    if (loadingMore || !nextCursor || !apiUrl || !template || !window.fetch) return;
    loadingMore = true;
    fetch(apiUrl + '?cursor=' + encodeURIComponent(nextCursor), { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('feed ' + res.status);
        return res.json();
      })
      .then(function (data) {
        nextCursor = data.next_cursor || '';
        (data.items || []).forEach(function (item) {
          var article = buildReel(item);
          feed.appendChild(article);
          register(article);
        });
        updateSoundButtons();
        // The new item may now be the "next" reel.
        var next = reels[activeIndex + 1];
        if (next) prepareNext(next);
      })
      .catch(function () { /* retried on the next activation */ })
      .then(function () { loadingMore = false; });
  }

  /* ---------- Page lifecycle ---------- */

  document.addEventListener('visibilitychange', function () {
    var cur = reels[activeIndex];
    if (!cur) return;
    if (document.hidden) {
      hiddenWhilePlaying = !cur.video.paused;
      cur.video.pause();
    } else if (hiddenWhilePlaying) {
      hiddenWhilePlaying = false;
      play(cur);
    }
  });

  if (conn && conn.addEventListener) {
    conn.addEventListener('change', function () { saveData = !!conn.saveData; });
  }

  var initial = feed.querySelectorAll('.reel');
  for (var i = 0; i < initial.length; i++) register(initial[i]);
  if (!reels.length || !observer) return;

  // Let the probe decide poster-only mode before the first reel starts,
  // but never wait longer than the probe timeout plus a little slack.
  var first = reels[0].video;
  var startFeed = function () {
    if (ready) return;
    ready = true;
    var hint = conn && conn.effectiveType;
    if (measuredMbps === null && (hint === 'slow-2g' || hint === '2g')) lowThroughput = true;
    activate(visibleIndex);
  };
  if (saveData) {
    startFeed();
  } else {
    probe(first.getAttribute('data-src-360')).then(startFeed);
    setTimeout(startFeed, PROBE_TIMEOUT_MS + 500);
  }
})();
