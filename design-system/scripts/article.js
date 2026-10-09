// Article page: reading-progress bar (.gn-progress__bar) and table-of-contents highlight (.gn-toc a).
(function () {
  var bar = document.querySelector('.gn-progress__bar'), body = document.querySelector('.gn-prose');
  if (!bar || !body) return;
  var links = Array.prototype.slice.call(document.querySelectorAll('.gn-toc a'));
  function update() {
    var r = body.getBoundingClientRect(), h = r.height - window.innerHeight;
    var p = h > 0 ? Math.min(1, Math.max(0, -r.top / h)) : 0;
    bar.style.width = (p * 100) + '%';
    var current = null;
    links.forEach(function (a) { var t = document.querySelector(a.getAttribute('href')); if (t && t.getBoundingClientRect().top < 120) current = a; });
    links.forEach(function (a) { a.setAttribute('aria-current', String(a === (current || links[0]))); });
  }
  window.addEventListener('scroll', update, { passive: true });
  update();
})();
