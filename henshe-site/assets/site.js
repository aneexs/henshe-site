/* Henshe Site: dark mode, phone menu, reading progress, text size, instant search and back-to-top (each only runs if its part is on the page) */
(function () {
	var d = document.documentElement;

	/* Dark mode: the button flips it and remembers the choice in this browser */
	document.querySelectorAll('.hs-mode').forEach(function (b) {
		b.setAttribute('aria-pressed', d.classList.contains('hs-dark'));
		b.addEventListener('click', function () {
			var on = d.classList.toggle('hs-dark');
			b.setAttribute('aria-pressed', on);
			try { localStorage.setItem('hs-dark', on ? '1' : '0'); } catch (e) {}
		});
	});

	/* Phone menu: built from the main menu links, so there is only one menu to edit */
	var burger = document.querySelector('.hs-burger'), drop = document.getElementById('hs-drop');
	if (burger && drop) {
		var ul = drop.querySelector('ul');
		var here = location.pathname.replace(/\/+$/, '') || '/';
		var path = function (a) { return new URL(a.href).pathname.replace(/\/+$/, '') || '/'; };
		var nav = document.querySelector('.hs-nav') || document.querySelector('header .wp-block-navigation');
		var inCats = false;
		if (nav) {
			nav.querySelectorAll('a.wp-block-navigation-item__content').forEach(function (a) {
				if (path(a) === here) {
					a.parentNode.classList.add('current-menu-item');
					if (a.closest('.wp-block-navigation-submenu')) inCats = true;
				}
			});
			if (inCats && nav.closest('.hs-header')) nav.closest('.hs-header').classList.add('hs-in-cats');
			var i = 0;
			var label = function (text) { var sp = document.createElement('span'); sp.textContent = text; return sp; };
			nav.querySelectorAll('.wp-block-navigation__container > .wp-block-navigation-item').forEach(function (li) {
				var n = document.createElement('li');
				n.style.setProperty('--i', i++);
				if (li.classList.contains('wp-block-navigation-submenu')) {
					var t = li.querySelector('.wp-block-navigation-submenu__toggle, .wp-block-navigation-item__content');
					var bt = document.createElement('button');
					bt.type = 'button';
					bt.className = 'hs-sub-toggle';
					bt.setAttribute('aria-expanded', 'false');
					bt.appendChild(label(t ? t.textContent.trim() : 'More'));
					bt.appendChild(document.createElement('i'));
					var wrap = document.createElement('div');
					wrap.className = 'hs-sub-wrap';
					wrap.inert = true;
					var inner = document.createElement('div');
					var su = document.createElement('ul');
					su.className = 'hs-sub';
					li.querySelectorAll('.wp-block-navigation__submenu-container a').forEach(function (a) {
						var x = document.createElement('li');
						if (path(a) === here) x.className = 'is-current';
						var c = document.createElement('a'); c.href = a.href; c.textContent = a.textContent.trim();
						x.appendChild(c); su.appendChild(x);
					});
					if (inCats) n.className = 'is-current-parent';
					bt.addEventListener('click', function () {
						var o = bt.getAttribute('aria-expanded') !== 'true';
						bt.setAttribute('aria-expanded', o); wrap.classList.toggle('is-open', o); wrap.inert = !o;
					});
					inner.appendChild(su); wrap.appendChild(inner);
					n.appendChild(bt); n.appendChild(wrap);
				} else {
					var a = li.querySelector('a');
					if (!a) return;
					if (path(a) === here) n.className = 'is-current';
					var c = document.createElement('a'); c.href = a.href; c.appendChild(label(a.textContent.trim()));
					if (path(a) === here) c.setAttribute('aria-current', 'page');
					n.appendChild(c);
				}
				ul.appendChild(n);
			});
		}
		var mhead = burger.closest('header') || burger.closest('.hs-header');
		var set = function (o) {
			burger.setAttribute('aria-expanded', o);
			burger.classList.toggle('is-open', o);
			d.classList.toggle('hs-menu-open', o);
			if (o) {
				if (mhead) d.style.setProperty('--hs-head', Math.max(0, mhead.getBoundingClientRect().bottom) + 'px');
				drop.hidden = false; drop.scrollTop = 0;
				requestAnimationFrame(function () { requestAnimationFrame(function () { drop.classList.add('is-open'); }); });
			} else {
				drop.classList.remove('is-open');
				setTimeout(function () { if (!drop.classList.contains('is-open')) drop.hidden = true; }, 350);
			}
		};
		burger.addEventListener('click', function () { set(burger.getAttribute('aria-expanded') !== 'true'); });
		drop.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
		matchMedia('(min-width: 992px)').addEventListener('change', function (m) { if (m.matches) set(false); });
		addEventListener('pageshow', function (e) { if (e.persisted) set(false); });
	}

	/* Reading progress: a thin red line under the header fills up while reading a story */
	var story = document.querySelector('.hs-post-body');
	var head = document.querySelector('header.wp-block-template-part');
	if (story && head && document.body.classList.contains('single-post')) {
		var bar = document.createElement('div');
		bar.className = 'hs-progress';
		bar.setAttribute('aria-hidden', 'true');
		head.appendChild(bar);
		var prog = function () {
			var r = story.getBoundingClientRect();
			var total = r.height - window.innerHeight + head.offsetHeight;
			var done = Math.min(1, Math.max(0, (head.offsetHeight - r.top) / (total > 0 ? total : 1)));
			bar.style.transform = 'scaleX(' + done + ')';
		};
		window.addEventListener('scroll', prog, { passive: true });
		window.addEventListener('resize', prog);
		prog();
	}

	/* Text size: Aa button switches between normal and larger */
	document.querySelectorAll('.hs-text-size').forEach(function (b) {
		b.addEventListener('click', function () {
			var next = d.dataset.hsText ? '' : 'l';
			if (next) d.dataset.hsText = next; else delete d.dataset.hsText;
			try { localStorage.setItem('hs-text', next); } catch (e) {}
		});
	});

	/* Instant search: matching stories appear under the search box while typing; Enter still opens all results */
	document.querySelectorAll('.hs-search').forEach(function (form) {
		var input = form.querySelector('input[type="search"]');
		if (!input || !window.fetch) return;
		var box = document.createElement('div');
		box.className = 'hs-sresults';
		box.hidden = true;
		form.appendChild(box);
		var timer, last = '';
		var esc = function (s) { var e = document.createElement('div'); e.textContent = s; return e.innerHTML; };
		var close = function () { box.hidden = true; };
		input.setAttribute('autocomplete', 'off');
		input.addEventListener('input', function () {
			clearTimeout(timer);
			var q = input.value.trim();
			if (q.length < 2) { close(); return; }
			timer = setTimeout(function () {
				last = q;
				fetch('/wp-json/wp/v2/posts?per_page=5&_embed=wp:featuredmedia&_fields=link,title,_links,_embedded&search=' + encodeURIComponent(q))
					.then(function (r) { return r.json(); })
					.then(function (posts) {
						if (q !== last) return;
						var html = posts.map(function (p) {
							var m = p._embedded && p._embedded['wp:featuredmedia'] && p._embedded['wp:featuredmedia'][0];
							var img = m && m.media_details && m.media_details.sizes && (m.media_details.sizes.thumbnail || m.media_details.sizes.medium);
							return '<a class="hs-sresult" href="' + p.link + '">' + (img ? '<img src="' + img.source_url + '" alt="" width="56" height="56">' : '<span class="hs-sresult__ph"></span>') + '<span>' + p.title.rendered + '</span></a>';
						}).join('');
						box.innerHTML = (html || '<p class="hs-snone">No stories match “' + esc(q) + '”.</p>')
							+ (html ? '<a class="hs-sall" href="/?s=' + encodeURIComponent(q) + '">See all results for “' + esc(q) + '” →</a>' : '');
						box.hidden = false;
					}).catch(close);
			}, 250);
		});
		document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
	});

	/* Back to top: shows after scrolling down */
	var top = document.querySelector('.hs-top');
	if (top) {
		var f = function () { top.classList.toggle('is-on', window.scrollY > 600); };
		window.addEventListener('scroll', f, { passive: true });
		f();
		top.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		});
	}
})();
