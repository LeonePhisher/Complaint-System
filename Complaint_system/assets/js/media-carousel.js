(function () {
  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function getIndex(track) {
    const w = track.clientWidth || 1;
    return Math.round(track.scrollLeft / w);
  }

  function scrollToIndex(track, index) {
    const w = track.clientWidth || 1;
    track.scrollTo({ left: index * w, behavior: 'smooth' });
  }

  function initCarousel(carousel) {
    if (!carousel || carousel.dataset.mediaInit === '1') return;

    const track = carousel.querySelector('.media-track');
    if (!track) return;

    const items = Array.from(carousel.querySelectorAll('.media-item'));
    if (items.length <= 1) {
      carousel.dataset.mediaInit = '1';
      return;
    }

    const prevBtn = carousel.querySelector('.media-nav.prev');
    const nextBtn = carousel.querySelector('.media-nav.next');
    const dots = Array.from(carousel.querySelectorAll('.media-dot'));

    let raf = 0;
    function update() {
      raf = 0;
      const idx = clamp(getIndex(track), 0, items.length - 1);
      dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }

    function scheduleUpdate() {
      if (raf) return;
      raf = requestAnimationFrame(update);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        const idx = clamp(getIndex(track) - 1, 0, items.length - 1);
        scrollToIndex(track, idx);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        const idx = clamp(getIndex(track) + 1, 0, items.length - 1);
        scrollToIndex(track, idx);
      });
    }

    dots.forEach((dot, i) => {
      dot.addEventListener('click', function () {
        scrollToIndex(track, i);
      });
    });

    track.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);

    carousel.dataset.mediaInit = '1';
    update();
  }

  function initAll(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-media-carousel]').forEach(initCarousel);
  }

  window.initMediaCarousels = initAll;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    });
  } else {
    initAll(document);
  }
})();
