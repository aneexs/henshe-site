/* Trending slider: an endless loop. The cards are drawn three times; after each move
   the view jumps back to the same card in the middle copy, so there is never an end.
   It moves on by itself every few seconds and stops while a reader hovers, touches or tabs into it. */
document.querySelectorAll('.hs-trend').forEach(function (box) {
	var track = box.querySelector('.hs-trend__track');
	var cards = track.children;
	var n = +box.dataset.count || cards.length / 3;
	var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var held = false, timer, settle;

	function setWidth() { return cards[n].offsetLeft - cards[0].offsetLeft; }
	function step() { return cards[1].offsetLeft - cards[0].offsetLeft; }
	function jump(x) {
		track.style.scrollBehavior = 'auto';
		track.scrollLeft = x;
		track.style.scrollBehavior = '';
	}
	function recentre() {
		var w = setWidth();
		if (track.scrollLeft < w * 0.5) jump(track.scrollLeft + w);
		else if (track.scrollLeft >= w * 1.5) jump(track.scrollLeft - w);
	}
	function move(dir) { track.scrollBy({ left: dir * step(), behavior: still ? 'auto' : 'smooth' }); }

	box.querySelector('.hs-trend__prev').addEventListener('click', function () { move(-1); restart(); });
	box.querySelector('.hs-trend__next').addEventListener('click', function () { move(1); restart(); });
	track.addEventListener('scroll', function () { clearTimeout(settle); settle = setTimeout(recentre, 160); }, { passive: true });
	window.addEventListener('resize', function () { jump(setWidth() + Math.round((track.scrollLeft % setWidth()) / step()) * step()); });

	function restart() {
		clearInterval(timer);
		if (!still) timer = setInterval(function () { if (!held && !document.hidden) move(1); }, 3500);
	}
	['mouseenter', 'focusin', 'touchstart'].forEach(function (e) { box.addEventListener(e, function () { held = true; }, { passive: true }); });
	['mouseleave', 'focusout', 'touchend'].forEach(function (e) { box.addEventListener(e, function () { held = false; restart(); }, { passive: true }); });

	jump(setWidth());
	restart();
});
