/**
 * particles-init.js – tsParticles Background Initializer
 *
 * Initialisiert die tsParticles-Hintergrundanimation (Punkt-Linien-Netzwerk).
 * Farbgebung: Identisch zu sine-math.com (weiss auf dunklem Hintergrund).
 * Mouse-Interaktion: Grab-Effekt auf Desktop, deaktiviert auf Mobile.
 *
 * Abhaengigkeit: tsparticles.slim.bundle.min.js (muss vorher geladen sein)
 * Beide Scripts werden via next/script strategy="lazyOnload" geladen.
 * Da die Ladereihenfolge nicht garantiert ist, wird ein Retry eingesetzt.
 *
 * v3 API: loadSlim(tsParticles) muss VOR tsParticles.load() aufgerufen werden,
 * um die Slim-Plugins (Links, Grab, Move etc.) zu registrieren.
 *
 * Graceful Degradation: Wenn tsParticles nicht verfuegbar ist, passiert nichts.
 */
(function () {
	'use strict';

	var MAX_RETRIES = 10;
	var RETRY_INTERVAL = 200; // ms

	function init() {
		if (typeof tsParticles === 'undefined' || typeof loadSlim === 'undefined') return false;

		loadSlim(tsParticles).then(function () {
			tsParticles.load({
				id: 'tsparticles',
				options: {
					fullScreen: false,
					fpsLimit: 60,
					particles: {
						number: {
							value: 50,
							density: { enable: true, area: 800 }
						},
						color: { value: '#ffffff' },
						opacity: { value: 0.2 },
						size: { value: { min: 1, max: 3 } },
						links: {
							enable: true,
							distance: 150,
							color: '#ffffff',
							opacity: 0.08,
							width: 1
						},
						move: {
							enable: true,
							speed: 0.5,
							direction: 'none',
							random: true,
							straight: false,
							outModes: { default: 'out' }
						}
					},
					interactivity: {
						detectsOn: 'window',
						events: {
							onHover: { enable: true, mode: 'grab' },
							resize: true
						},
						modes: {
							grab: { distance: 140, links: { opacity: 0.15 } }
						}
					},
					detectRetina: true,
					responsive: [
						{
							maxWidth: 768,
							options: {
								particles: {
									number: { value: 25 },
									move: { speed: 0.3 }
								},
								interactivity: {
									events: { onHover: { enable: false } }
								}
							}
						}
					]
				}
			});
		});

		return true;
	}

	function tryInit(attempt) {
		if (init()) return;
		if (attempt < MAX_RETRIES) {
			setTimeout(function () { tryInit(attempt + 1); }, RETRY_INTERVAL);
		}
		// Nach MAX_RETRIES: stille Aufgabe — Graceful Degradation
	}

	if (document.readyState === 'complete') {
		tryInit(0);
	} else {
		window.addEventListener('load', function () { tryInit(0); });
	}
})();
