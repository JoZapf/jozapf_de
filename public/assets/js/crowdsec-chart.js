/**
 * crowdsec-chart.js – CrowdSec Live Stats Chart Component
 * Verwendet auf: /pflichtpraktikum-anwendungsentwicklung-berlin/ (DE + EN)
 *
 * Self-contained module:
 * - Liest Config vom #crowdsec-chart Mount-Point (data-* Attribute)
 * - Lazy-loaded Chart.js + date-fns Adapter (wie github-repos.js mit Swiper)
 * - Baut DOM (Heading, Description, Canvas, Fallback, Status)
 * - Fetcht Grafana Public Dashboard API
 * - Rendert Stacked Area Chart mit Auto-Refresh
 * - Zeigt Fallback-Overlay (Ghost-Grid + SVG-Spinner) bei Fehler
 * - i18n über data-lang Attribut (de/en)
 * - Lazy Init via Intersection Observer
 */
(function () {
	'use strict';

	/* ── Vendor URLs ──────────────────────────────────────── */
	var CHARTJS_URL = '/vendor/chartjs/chart.umd.min.js';
	var DATEFNS_URL = '/vendor/chartjs/chartjs-adapter-date-fns.bundle.min.js';

	/* ── Chart Colors ─────────────────────────────────────── */
	var COLORS = [
		'#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff',
		'#ff9f40', '#c9cbcf', '#e7e9ed', '#7bc67b', '#f67280', '#6c5ce7'
	];

	/* ── i18n Strings ─────────────────────────────────────── */
	var I18N = {
		de: {
			heading: 'Live Stats: \u2013 CrowdSec IPS',
			description: 'Erkannte IPS Szenarien der letzten 24 Stunden ',
			descHighlight: '(Live-Daten vom Homelab)',
			loading: 'Live-Statistiken werden geladen\u2026',
			errorInitial: 'Live-Daten vor\u00fcbergehend nicht verf\u00fcgbar. Automatischer Retry l\u00e4uft.',
			errorFinal: 'Live-Statistiken sind derzeit nicht erreichbar. Bitte sp\u00e4ter erneut versuchen.',
			status: function (t) { return 'Stand: ' + t + ' \u00b7 Auto-Refresh 60 s \u00b7 CrowdSec IPS'; },
			locale: 'de-DE',
			tooltipFormat: 'dd.MM. HH:mm',
			displayFormats: { hour: 'HH:mm', day: 'dd.MM.' }
		},
		en: {
			heading: 'Live Stats: \u2013 CrowdSec IPS',
			description: 'Detected IPS scenarios over the last 24 hours ',
			descHighlight: '(live data from homelab)',
			loading: 'Loading live statistics\u2026',
			errorInitial: 'Live data temporarily unavailable. Auto-retry in progress.',
			errorFinal: 'Live statistics currently unreachable. Please try again later.',
			status: function (t) { return 'Updated: ' + t + ' \u00b7 Auto-Refresh 60 s \u00b7 CrowdSec IPS'; },
			locale: 'en-GB',
			tooltipFormat: 'dd/MM HH:mm',
			displayFormats: { hour: 'HH:mm', day: 'dd/MM' }
		}
	};

	/* ── Constants ─────────────────────────────────────────── */
	var MAX_ERROR_COUNT = 5;

	/* ── Vendor Lazy-Loading ───────────────────────────────── */
	var vendorLoaded = false;
	var vendorLoading = false;

	function loadScript(url) {
		return new Promise(function (resolve, reject) {
			if (document.querySelector('script[src="' + url + '"]')) {
				resolve();
				return;
			}
			var s = document.createElement('script');
			s.src = url;
			s.onload = resolve;
			s.onerror = reject;
			document.body.appendChild(s);
		});
	}

	function loadVendor() {
		if (vendorLoaded) return Promise.resolve();
		if (vendorLoading) {
			return new Promise(function (resolve) {
				var id = setInterval(function () {
					if (vendorLoaded) { clearInterval(id); resolve(); }
				}, 50);
			});
		}
		vendorLoading = true;
		console.log('[CrowdSec Chart] Loading Chart.js vendor\u2026');

		/* Chart.js muss vor dem Adapter geladen sein */
		return loadScript(CHARTJS_URL)
			.then(function () { return loadScript(DATEFNS_URL); })
			.then(function () {
				vendorLoaded = true;
				console.log('[CrowdSec Chart] Vendor loaded successfully');
			})
			.catch(function (err) {
				vendorLoading = false;
				console.error('[CrowdSec Chart] Failed to load vendor:', err);
				throw err;
			});
	}

	/* ── DOM Builder ───────────────────────────────────────── */
	function buildDOM(mount, strings) {
		var outer = document.createElement('div');
		outer.className = 'crowdsec-chart-outer';

		/* Heading */
		var h3 = document.createElement('h3');
		h3.className = 'crowdsec-chart__heading';
		h3.textContent = strings.heading;
		outer.appendChild(h3);

		/* Description */
		var desc = document.createElement('p');
		desc.className = 'crowdsec-chart__description';
		desc.textContent = strings.description;
		var hl = document.createElement('span');
		hl.className = 'highlight';
		hl.textContent = strings.descHighlight;
		desc.appendChild(hl);
		outer.appendChild(desc);

		/* Canvas wrapper (Chart + Fallback) */
		var wrapper = document.createElement('div');
		wrapper.className = 'crowdsec-chart-wrapper';

		var canvas = document.createElement('canvas');
		wrapper.appendChild(canvas);

		/* Fallback overlay */
		var fallback = document.createElement('div');
		fallback.className = 'chart-fallback';

		var bg = document.createElement('div');
		bg.className = 'chart-fallback__bg';
		fallback.appendChild(bg);

		var overlay = document.createElement('div');
		overlay.className = 'chart-fallback__overlay';

		/* Inline SVG spinner */
		var NS = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(NS, 'svg');
		svg.setAttribute('class', 'chart-fallback__spinner');
		svg.setAttribute('viewBox', '0 0 50 50');

		var track = document.createElementNS(NS, 'circle');
		track.setAttribute('class', 'fb-track');
		track.setAttribute('cx', '25');
		track.setAttribute('cy', '25');
		track.setAttribute('r', '20');
		svg.appendChild(track);

		var prog = document.createElementNS(NS, 'circle');
		prog.setAttribute('class', 'fb-progress');
		prog.setAttribute('cx', '25');
		prog.setAttribute('cy', '25');
		prog.setAttribute('r', '20');
		svg.appendChild(prog);

		overlay.appendChild(svg);

		var text = document.createElement('p');
		text.className = 'chart-fallback__text';
		text.textContent = strings.loading;
		overlay.appendChild(text);

		fallback.appendChild(overlay);
		wrapper.appendChild(fallback);
		outer.appendChild(wrapper);

		/* Status line */
		var status = document.createElement('p');
		status.className = 'crowdsec-chart__status';
		outer.appendChild(status);

		mount.appendChild(outer);

		return {
			canvas: canvas,
			fallback: fallback,
			fallbackText: text,
			status: status
		};
	}

	/* ── Chart Component ──────────────────────────────────── */
	function createChartComponent(mount) {
		var lang = mount.getAttribute('data-lang') || 'de';
		var endpoint = mount.getAttribute('data-endpoint');
		var refreshSec = parseInt(mount.getAttribute('data-refresh') || '60', 10);
		var strings = I18N[lang] || I18N.de;

		if (!endpoint) {
			console.error('[CrowdSec Chart] No data-endpoint on mount');
			return;
		}

		var els = buildDOM(mount, strings);
		var chart = null;
		var errorCount = 0;

		function showFallback(msg) {
			els.fallback.classList.remove('is-hidden');
			if (msg) els.fallbackText.textContent = msg;
		}

		function hideFallback() {
			els.fallback.classList.add('is-hidden');
		}

		function fetchAndRender() {
			fetch(endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: '{}'
			})
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function (json) {
				var frames = json.results.A.frames;

				var datasets = frames.map(function (frame, i) {
					var name = frame.schema.fields[1].labels.name
						.replace('crowdsecurity/', '')
						.replace('custom/', '')
						.replace('LePresidente/', '');
					var timestamps = frame.data.values[0];
					var values = frame.data.values[1];

					var step = Math.max(1, Math.floor(timestamps.length / 200));
					var data = [];
					for (var j = 0; j < timestamps.length; j += step) {
						data.push({ x: timestamps[j], y: values[j] || 0 });
					}
					return {
						label: name,
						data: data,
						borderColor: COLORS[i % COLORS.length],
						backgroundColor: COLORS[i % COLORS.length] + '40',
						borderWidth: 1.5,
						fill: true,
						pointRadius: 0,
						tension: 0.3
					};
				}).filter(function (ds) {
					return ds.data.some(function (p) { return p.y > 0; });
				});

				if (chart) {
					chart.data.datasets = datasets;
					chart.update('none');
				} else {
					chart = new Chart(els.canvas, {
						type: 'line',
						data: { datasets: datasets },
						options: {
							responsive: true,
							maintainAspectRatio: false,
							animation: false,
							interaction: { mode: 'index', intersect: false },
							scales: {
								x: {
									type: 'time',
									time: {
										tooltipFormat: strings.tooltipFormat,
										displayFormats: strings.displayFormats
									},
									ticks: { color: '#888', maxTicksLimit: 8 },
									grid: { color: '#333' }
								},
								y: {
									stacked: true,
									ticks: { color: '#888', precision: 0 },
									grid: { color: '#333' },
									title: { display: true, text: 'Events', color: '#888' }
								}
							},
							plugins: {
								legend: {
									labels: { color: '#ccc', boxWidth: 12, font: { size: 11 } }
								},
								tooltip: {
									backgroundColor: '#1a1a2eee',
									titleColor: '#fff',
									bodyColor: '#ccc'
								}
							}
						}
					});
				}

				/* Erfolg → Fallback ausblenden, Status aktualisieren */
				errorCount = 0;
				hideFallback();
				els.status.textContent = strings.status(
					new Date().toLocaleString(strings.locale)
				);
			})
			.catch(function (e) {
				errorCount++;
				console.warn('[CrowdSec Chart] Fetch error #' + errorCount + ':', e.message);
				var msg = errorCount >= MAX_ERROR_COUNT
					? strings.errorFinal
					: strings.errorInitial;
				showFallback(msg);
				els.status.textContent = '';
			});
		}

		/* Vendor laden → erster Fetch + Intervall */
		loadVendor()
			.then(function () {
				fetchAndRender();
				setInterval(fetchAndRender, refreshSec * 1000);
			})
			.catch(function () {
				showFallback(strings.errorInitial);
			});
	}

	/* ── Intersection Observer (Lazy Init) ─────────────────── */
	function setup() {
		var mount = document.getElementById('crowdsec-chart');
		if (!mount) return;

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						console.log('[CrowdSec Chart] Section visible, initializing\u2026');
						observer.disconnect();
						createChartComponent(mount);
					}
				});
			},
			{ rootMargin: '200px', threshold: 0 }
		);

		observer.observe(mount);
		console.log('[CrowdSec Chart] Lazy loading observer set up');
	}

	/* ── Entry Point ──────────────────────────────────────── */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', setup);
	} else {
		setup();
	}

})();
