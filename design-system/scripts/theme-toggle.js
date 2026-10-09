// Theme toggle for .gn-theme-toggle buttons (see components/SiteHeader).
// Flips data-theme on <html>, stores the choice under "gn-theme", keeps aria in sync.
document.querySelectorAll('.gn-theme-toggle').forEach(function (btn) {
  var root = document.documentElement;
  function sync() {
    var dark = root.getAttribute('data-theme') === 'dark';
    btn.setAttribute('aria-pressed', String(dark));
    btn.setAttribute('aria-label', dark ? 'লাইট মোড' : 'ডার্ক মোড');
  }
  sync();
  btn.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('gn-theme', next); } catch (e) {}
    sync();
  });
  new MutationObserver(sync).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
});
