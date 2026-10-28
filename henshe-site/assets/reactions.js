(function () {
	var api = window.kaleoReactions && window.kaleoReactions.api;
	if (!api || !window.fetch) return;

	function store(postId, value) {
		var key = 'kaleo-reaction-' + postId;
		try {
			if (value === undefined) return localStorage.getItem(key);
			value ? localStorage.setItem(key, value) : localStorage.removeItem(key);
		} catch (e) { return null; }
	}

	function paint(box, counts, mine) {
		box.querySelectorAll('.kaleo-reactions__btn').forEach(function (btn) {
			var key = btn.dataset.reaction;
			if (counts) {
				var n = counts[key] || 0;
				btn.querySelector('.kaleo-reactions__count').textContent = n ? n.toLocaleString() : '';
			}
			btn.setAttribute('aria-pressed', key === mine ? 'true' : 'false');
		});
	}

	document.querySelectorAll('.kaleo-reactions[data-post]').forEach(function (box) {
		var postId = box.dataset.post;
		var url = api + postId;
		var busy = false;

		paint(box, null, store(postId));
		// Page HTML may be cached, so fetch fresh counts.
		fetch(url + '?t=' + Date.now(), { credentials: 'omit' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (d) { if (d) paint(box, d.counts, store(postId)); })
			.catch(function () {});

		box.addEventListener('click', function (e) {
			var btn = e.target.closest('.kaleo-reactions__btn');
			if (!btn || busy) return;
			var key = btn.dataset.reaction;
			showTip(btn);
			var prev = store(postId);
			var next = prev === key ? null : key;
			var body = {};
			if (next) body.add = next;
			if (prev) body.remove = prev;

			// Show the change straight away, then confirm with the server.
			store(postId, next);
			paint(box, null, next);
			adjust(btn, next ? 1 : -1);
			if (prev && next) adjust(box.querySelector('[data-reaction="' + prev + '"]'), -1);
			if (next) {
				btn.classList.remove('is-pop');
				void btn.offsetWidth;
				btn.classList.add('is-pop');
			}

			busy = true;
			fetch(url, {
				method: 'POST',
				credentials: 'omit',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body)
			})
				.then(function (r) { return r.ok ? r.json() : Promise.reject(); })
				.then(function (d) { paint(box, d.counts, next); })
				.catch(function () { store(postId, prev); paint(box, null, prev); })
				.finally(function () { busy = false; });
		});
	});

	// Phones hide the names, so show the tapped reaction's name for a moment.
	var tipTimer;
	function showTip(btn) {
		var tip = btn.querySelector('.kaleo-reactions__tip');
		if (!tip) {
			tip = document.createElement('span');
			tip.className = 'kaleo-reactions__tip';
			tip.setAttribute('aria-hidden', 'true');
			tip.textContent = btn.getAttribute('aria-label');
			btn.appendChild(tip);
		}
		document.querySelectorAll('.kaleo-reactions__btn.is-tip').forEach(function (b) { b.classList.remove('is-tip'); });
		btn.classList.add('is-tip');
		clearTimeout(tipTimer);
		tipTimer = setTimeout(function () { btn.classList.remove('is-tip'); }, 1500);
	}

	function adjust(btn, delta) {
		if (!btn) return;
		var el = btn.querySelector('.kaleo-reactions__count');
		var n = Math.max(0, (parseInt(el.textContent.replace(/\D/g, ''), 10) || 0) + delta);
		el.textContent = n ? n.toLocaleString() : '';
	}
})();
