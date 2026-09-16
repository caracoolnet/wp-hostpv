<?php
/**
 * HosTPV — Cursor personalizado (módulo independiente)
 * ─────────────────────────────────────────────────────
 * Archivo APARTE de hostpv.php a propósito, mismo criterio que
 * hostpv-text-animations.php: así esta función se puede ampliar, desactivar
 * por completo o mover a otro plugin sin tocar el archivo principal. Se
 * carga con un solo require_once desde hostpv.php y se autorregistra: no
 * depende de que HosTPV::__construct() sepa nada de él.
 *
 * QUÉ HACE (pedido 14/08, prototipado antes en un HTML suelto —
 * cursor-circulo-demo.html — hasta dar con los valores buenos):
 *  1) Sustituye el cursor del ratón, en el frontend público, por un círculo
 *     fino cuyo diámetro se ajusta desde esta misma pestaña de ajustes.
 *  2) El círculo usa `mix-blend-mode: difference` — invierte automáticamente
 *     el color de cualquier texto o imagen que tenga debajo (texto negro se
 *     ve blanco dentro del círculo, y al revés), sin marcar nada elemento a
 *     elemento: es una propiedad CSS que el navegador resuelve solo, píxel a
 *     píxel.
 *  3) El círculo sigue al ratón con una física de muelle amortiguado
 *     (spring-damper), no con un simple "pegado" al puntero — se nota
 *     orgánico, con inercia. Suavidad/Rebote son ajustables.
 *  4) Efecto imán: al acercarse a cualquier <a> real de la página, el
 *     círculo se deja atraer hacia su centro y crece un % configurable —
 *     cuanto más cerca, más fuerte, hasta el máximo al estar centrado. No
 *     hace falta marcar los enlaces uno a uno: se detectan todos los <a> de
 *     la página automáticamente.
 *  5) Se probó también una estela que se degradaba al mover el ratón rápido
 *     — se descartó a petición explícita del cliente tras probarla en el
 *     prototipo, así que esta versión NO la incluye.
 *
 * DECISIONES DE RENDIMIENTO / ACCESIBILIDAD:
 *  - No se imprime NADA si el módulo está desactivado (interruptor
 *    "Activar cursor") o en el admin — mismo patrón de impresión
 *    condicional que ya usa hostpv-text-animations.php.
 *  - `prefers-reduced-motion: reduce` desactiva el muelle (el círculo sigue
 *    al ratón de forma directa, sin inercia/rebote) — la inversión de color
 *    y el imán se mantienen (no son animaciones de movimiento en sí), pero
 *    se evita el vaivén elástico para quien lo tenga desactivado a nivel de
 *    sistema.
 *  - El propio círculo lleva `pointer-events:none`, así que nunca intercepta
 *    clics — el comportamiento real de clic/hover del sitio no cambia en
 *    absoluto, solo la parte visual del puntero.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HosTPV_Custom_Cursor {

	const OPTION_KEY = 'hostpv_cursor_settings';

	/** true en cuanto se decide que hace falta imprimir el cursor en ESTA
	 *  petición (cursor activado y no estamos en el admin) — mismo patrón
	 *  que HosTPV_Text_Animations::$needs_assets / HosTPV::$logo_assets_printed. */
	private static $needs_assets = false;

	public function __construct() {
		// Ajustes: misma pestaña compartida que ya usan Logo animado / Caja y
		// Halo / Animaciones de texto — hostpv.php no sabe nada de este
		// módulo, solo ofrece los dos hooks genéricos.
		add_action( 'hostpv_settings_tabs',   [ $this, 'render_settings_tab_button' ] );
		add_action( 'hostpv_settings_panels', [ $this, 'render_settings_tab_panel' ] );

		// Guardado: MISMA acción que ya usa el formulario compartido de
		// hostpv.php (un solo botón "Guardar cambios" para toda la página),
		// prioridad 5 para guardar antes de que HosTPV::save_settings()
		// (prioridad 10) haga el redirect+exit final.
		add_action( 'admin_post_hostpv_save', [ $this, 'save_settings' ], 5 );

		// Frontend: se decide si hace falta imprimir algo tan pronto como se
		// puede (wp) para que print_assets(), enganchado a wp_footer, ya
		// sepa la respuesta sin tener que releer la opción otra vez.
		add_action( 'wp', [ $this, 'maybe_flag_assets' ] );
		add_action( 'wp_footer', [ $this, 'print_assets' ] );
	}

	// ── Ajustes ──────────────────────────────────────────────────────

	/** Valores por defecto FIJOS del plugin (no dependen de lo que haya
	 *  guardado ahora mismo la opción) — se usan tanto para rellenar los
	 *  huecos al leer (get_settings()) como de red de seguridad al guardar
	 *  un valor no numérico/corrupto (save_settings()), para que ese caso
	 *  siempre caiga al mismo sitio fijo en vez de depender de qué hubiera
	 *  guardado antes. */
	private static function defaults() {
		return [
			'enabled'          => false, // desactivado por defecto: es un cambio visual global, se activa a propósito
			'diameter'         => 32,    // px
			'hide_native'      => true,
			'softness'         => 65,    // 0-100 · rigidez del muelle (más alto = más flotante)
			'bounce'           => 35,    // 0-100 · elasticidad (más alto = más rebote)
			'magnet_enabled'   => true,
			'magnet_radius'    => 70,    // px
			'magnet_strength'  => 55,    // %
			'magnet_scale'     => 80,    // %
		];
	}

	public static function get_settings() {
		return wp_parse_args( get_option( self::OPTION_KEY, [] ), self::defaults() );
	}

	/** Recorta cada valor numérico a su rango válido — evita que un valor
	 *  manipulado a mano en el POST (o una opción corrupta) produzca CSS/JS
	 *  con números disparatados en el frontend público. */
	private static function clamp( $value, $min, $max, $default ) {
		if ( ! is_numeric( $value ) ) return $default;
		$value = (int) $value;
		return max( $min, min( $max, $value ) );
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'hostpv_save' );

		$defaults = self::defaults();

		update_option( self::OPTION_KEY, [
			'enabled'         => ! empty( $_POST['hostpv_cursor_enabled'] ),
			'diameter'        => self::clamp( $_POST['hostpv_cursor_diameter']        ?? null, 10, 90,  $defaults['diameter'] ),
			'hide_native'     => ! empty( $_POST['hostpv_cursor_hide_native'] ),
			'softness'        => self::clamp( $_POST['hostpv_cursor_softness']        ?? null, 0,  100, $defaults['softness'] ),
			'bounce'          => self::clamp( $_POST['hostpv_cursor_bounce']          ?? null, 0,  100, $defaults['bounce'] ),
			'magnet_enabled'  => ! empty( $_POST['hostpv_cursor_magnet_enabled'] ),
			'magnet_radius'   => self::clamp( $_POST['hostpv_cursor_magnet_radius']   ?? null, 20, 160, $defaults['magnet_radius'] ),
			'magnet_strength' => self::clamp( $_POST['hostpv_cursor_magnet_strength'] ?? null, 0,  100, $defaults['magnet_strength'] ),
			'magnet_scale'    => self::clamp( $_POST['hostpv_cursor_magnet_scale']    ?? null, 0,  200, $defaults['magnet_scale'] ),
		] );
	}

	// ── Admin: pestaña "Cursor" dentro de la página de ajustes existente ──

	public function render_settings_tab_button() {
		?>
		<button type="button" class="hp-tab" data-hp-tab="tab-cursor">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/></svg>
			Cursor
		</button>
		<?php
	}

	/** Enganchado a `hostpv_settings_panels`. Vive DENTRO del <form> ya
	 *  abierto por hostpv.php — sin <form>/nonce/botón propios, solo los
	 *  campos. Los deslizadores (range) muestran su valor en vivo con un
	 *  poco de JS al final del panel, mismo patrón visual (hp-field-grid)
	 *  que el resto de pestañas. */
	public function render_settings_tab_panel() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = self::get_settings();
		?>
		<style>
			.hp-range-row{ display:flex; align-items:center; gap:12px; }
			.hp-range-row input[type=range]{ flex:1; accent-color:var(--hp-accent, #ffc94a); }
			.hp-range-row .hp-range-val{ min-width:56px; text-align:right; font-size:12.5px; font-weight:650; color:var(--hp-ink-soft, #6b6660); font-variant-numeric:tabular-nums; }
		</style>
		<div id="tab-cursor" class="hp-tab-panel" style="display:none;">
			<div class="hp-card">
				<div class="hp-card-head">
					<div class="hp-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/></svg></div>
					<h2>Cursor personalizado</h2>
				</div>
				<p class="hp-card-desc">Sustituye el puntero del ratón, en la web pública, por un círculo que invierte el color de lo que tiene debajo (texto negro se ve blanco dentro del círculo, y al revés) y se deja atraer hacia los enlaces. No afecta al panel de administración.</p>

				<div class="hp-toggle-row">
					<div>
						<div class="hp-label">Activar cursor personalizado</div>
						<p class="hp-hint">Con esto desactivado no se carga ni un byte del cursor en el sitio.</p>
					</div>
					<label class="hp-switch">
						<input type="checkbox" name="hostpv_cursor_enabled" value="1" <?php checked( $s['enabled'] ); ?>>
						<span class="hp-track"></span>
					</label>
				</div>

				<div class="hp-field-grid">
					<label for="hpcur_diameter">Diámetro</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_diameter" name="hostpv_cursor_diameter" min="10" max="90" value="<?php echo esc_attr( $s['diameter'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_diameter"><?php echo esc_html( $s['diameter'] ); ?> px</span>
					</div>

					<label>Ocultar cursor del sistema</label>
					<label class="hp-switch">
						<input type="checkbox" name="hostpv_cursor_hide_native" value="1" <?php checked( $s['hide_native'] ); ?>>
						<span class="hp-track"></span>
					</label>
				</div>
			</div>

			<div class="hp-card">
				<div class="hp-card-head">
					<h2>Movimiento</h2>
				</div>
				<p class="hp-card-desc">Cómo de fluido se ve el seguimiento del ratón.</p>
				<div class="hp-field-grid">
					<label for="hpcur_softness">Suavidad</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_softness" name="hostpv_cursor_softness" min="0" max="100" value="<?php echo esc_attr( $s['softness'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_softness"><?php echo esc_html( $s['softness'] ); ?></span>
					</div>
					<p class="hp-field-hint">Más alto = más flotante, con más retardo. Más bajo = más pegado al puntero real.</p>

					<label for="hpcur_bounce">Rebote / elasticidad</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_bounce" name="hostpv_cursor_bounce" min="0" max="100" value="<?php echo esc_attr( $s['bounce'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_bounce"><?php echo esc_html( $s['bounce'] ); ?></span>
					</div>
					<p class="hp-field-hint">Más alto = más rebote antes de asentarse. Más bajo = frena en seco, sin rebote.</p>
				</div>
			</div>

			<div class="hp-card">
				<div class="hp-card-head">
					<h2>Imán en enlaces</h2>
					<span class="hp-badge hp-badge-muted">Se aplica a todos los &lt;a&gt; solos</span>
				</div>
				<p class="hp-card-desc">Al acercarse a cualquier enlace de la página, el círculo se atrae hacia su centro y crece.</p>

				<div class="hp-toggle-row">
					<div>
						<div class="hp-label">Activar imán</div>
					</div>
					<label class="hp-switch">
						<input type="checkbox" name="hostpv_cursor_magnet_enabled" value="1" <?php checked( $s['magnet_enabled'] ); ?>>
						<span class="hp-track"></span>
					</label>
				</div>

				<div class="hp-field-grid">
					<label for="hpcur_magnet_radius">Radio de atracción</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_magnet_radius" name="hostpv_cursor_magnet_radius" min="20" max="160" value="<?php echo esc_attr( $s['magnet_radius'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_magnet_radius"><?php echo esc_html( $s['magnet_radius'] ); ?> px</span>
					</div>

					<label for="hpcur_magnet_strength">Fuerza del imán</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_magnet_strength" name="hostpv_cursor_magnet_strength" min="0" max="100" value="<?php echo esc_attr( $s['magnet_strength'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_magnet_strength"><?php echo esc_html( $s['magnet_strength'] ); ?>%</span>
					</div>

					<label for="hpcur_magnet_scale">Ampliación al acercarse</label>
					<div class="hp-range-row">
						<input type="range" id="hpcur_magnet_scale" name="hostpv_cursor_magnet_scale" min="0" max="200" value="<?php echo esc_attr( $s['magnet_scale'] ); ?>">
						<span class="hp-range-val" data-hpcur-out="hpcur_magnet_scale"><?php echo esc_html( $s['magnet_scale'] ); ?>%</span>
					</div>
				</div>
			</div>
		</div>
		<script>
			// Sufijo de cada slider (px, % o nada) según su propio id — se lee
			// una vez por campo, no hace falta adivinarlo por rango de valores.
			var hpcurSuffixes = {
				hpcur_diameter: ' px',
				hpcur_softness: '',
				hpcur_bounce: '',
				hpcur_magnet_radius: ' px',
				hpcur_magnet_strength: '%',
				hpcur_magnet_scale: '%'
			};
			document.querySelectorAll('[data-hpcur-out]').forEach(function (out) {
				var input = document.getElementById(out.dataset.hpcurOut);
				if (!input) return;
				var suffix = hpcurSuffixes[input.id] || '';
				var render = function () { out.textContent = input.value + suffix; };
				input.addEventListener('input', render);
				render();
			});
		</script>
		<?php
	}

	// ── Frontend ─────────────────────────────────────────────────────

	/** Se decide UNA vez por petición, en cuanto WordPress ya sabe qué se
	 *  está sirviendo (hook `wp`), si hace falta imprimir el cursor: tiene
	 *  que estar activado Y no ser el admin ni un feed. */
	public function maybe_flag_assets() {
		if ( is_admin() || is_feed() ) return;
		$s = self::get_settings();
		if ( ! empty( $s['enabled'] ) ) {
			self::$needs_assets = true;
		}
	}

	public function print_assets() {
		if ( ! self::$needs_assets ) return;
		$s = self::get_settings();

		$diameter        = (int) $s['diameter'];
		$hide_native      = ! empty( $s['hide_native'] );
		$softness         = (int) $s['softness'];
		$bounce           = (int) $s['bounce'];
		$magnet_enabled   = ! empty( $s['magnet_enabled'] ) ? 'true' : 'false';
		$magnet_radius    = (int) $s['magnet_radius'];
		$magnet_strength  = (int) $s['magnet_strength'];
		$magnet_scale     = (int) $s['magnet_scale'];
		?>
		<style id="hostpv-cursor-css">
			<?php if ( $hide_native ) : ?>
			html, body{ cursor:none !important; }
			#wpadminbar, #wpadminbar *{ cursor:auto !important; }
			<?php endif; ?>
			#hostpv-cursor-circle{
				position:fixed;
				top:0; left:0;
				width:<?php echo $diameter; ?>px;
				height:<?php echo $diameter; ?>px;
				border-radius:50%;
				background:#ffffff;
				mix-blend-mode:difference;
				pointer-events:none;
				z-index:2147483647;
				transform:translate3d(-100px,-100px,0) translate(-50%,-50%);
				will-change:transform,width,height;
			}
			@media (hover:none) and (pointer:coarse){
				/* Sin ratón real (táctil) — no tiene sentido ni se puede mostrar el cursor. */
				#hostpv-cursor-circle{ display:none; }
				<?php if ( $hide_native ) : ?>
				html, body{ cursor:auto !important; }
				<?php endif; ?>
			}
		</style>
		<div id="hostpv-cursor-circle"></div>
		<script id="hostpv-cursor-js">
		(function () {
			var circle = document.getElementById('hostpv-cursor-circle');
			if (!circle || matchMedia('(hover:none) and (pointer:coarse)').matches) return;

			var cfg = {
				diameter: <?php echo $diameter; ?>,
				softness: <?php echo $softness; ?>,
				bounce: <?php echo $bounce; ?>,
				magnetEnabled: <?php echo $magnet_enabled; ?>,
				magnetRadius: <?php echo $magnet_radius; ?>,
				magnetStrength: <?php echo $magnet_strength; ?>,
				magnetScale: <?php echo $magnet_scale; ?>
			};
			var reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

			var linkRects = [];
			function refreshLinkRects() {
				var links = document.querySelectorAll('a');
				linkRects = [];
				for (var i = 0; i < links.length; i++) {
					if (links[i].closest('#wpadminbar')) continue;
					var r = links[i].getBoundingClientRect();
					if (r.width === 0 && r.height === 0) continue;
					linkRects.push({
						left: r.left, top: r.top, right: r.right, bottom: r.bottom,
						cx: r.left + r.width / 2, cy: r.top + r.height / 2
					});
				}
			}
			window.addEventListener('resize', refreshLinkRects);
			window.addEventListener('scroll', refreshLinkRects, true);
			refreshLinkRects();
			setTimeout(refreshLinkRects, 400);

			function distToRect(px, py, r) {
				var dx = Math.max(r.left - px, 0, px - r.right);
				var dy = Math.max(r.top - py, 0, py - r.bottom);
				return Math.hypot(dx, dy);
			}

			var mouseX = -100, mouseY = -100, hasMouse = false;
			document.addEventListener('mousemove', function (e) {
				mouseX = e.clientX; mouseY = e.clientY; hasMouse = true;
			});

			var posX = mouseX, posY = mouseY, velX = 0, velY = 0;
			var curDiameter = cfg.diameter, diaVel = 0;
			var lastFrameT = performance.now();

			function loop() {
				var now = performance.now();
				var dt = Math.min((now - lastFrameT) / 1000, 0.05);
				lastFrameT = now;

				if (!hasMouse) { requestAnimationFrame(loop); return; }

				var targetX = mouseX, targetY = mouseY, targetDiameter = cfg.diameter;

				if (cfg.magnetEnabled && linkRects.length) {
					var nearest = null, nearestDist = Infinity;
					for (var i = 0; i < linkRects.length; i++) {
						var d = distToRect(mouseX, mouseY, linkRects[i]);
						if (d < nearestDist) { nearestDist = d; nearest = linkRects[i]; }
					}
					if (nearest && nearestDist < cfg.magnetRadius) {
						var pull = 1 - (nearestDist / cfg.magnetRadius);
						var strength = (cfg.magnetStrength / 100) * pull;
						targetX = mouseX + (nearest.cx - mouseX) * strength;
						targetY = mouseY + (nearest.cy - mouseY) * strength;
						targetDiameter = cfg.diameter * (1 + (cfg.magnetScale / 100) * pull);
					}
				}

				if (reduceMotion) {
					posX = targetX; posY = targetY; curDiameter = targetDiameter;
				} else {
					var t = cfg.softness / 100;
					var k = 260 - t * 235;
					var zeta = 1.05 - (cfg.bounce / 100) * 0.75;
					var c = zeta * 2 * Math.sqrt(k);

					var ax = (targetX - posX) * k - velX * c;
					var ay = (targetY - posY) * k - velY * c;
					velX += ax * dt; velY += ay * dt;
					posX += velX * dt; posY += velY * dt;

					var dk = 170, dc = 2 * Math.sqrt(dk) * 0.85;
					var da = (targetDiameter - curDiameter) * dk - diaVel * dc;
					diaVel += da * dt;
					curDiameter += diaVel * dt;
					if (curDiameter < 2) curDiameter = 2;
				}

				circle.style.width = curDiameter + 'px';
				circle.style.height = curDiameter + 'px';
				circle.style.transform = 'translate3d(' + posX + 'px,' + posY + 'px,0) translate(-50%,-50%)';

				requestAnimationFrame(loop);
			}
			requestAnimationFrame(loop);
		})();
		</script>
		<?php
	}
}

new HosTPV_Custom_Cursor();
