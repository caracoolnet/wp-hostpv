<?php
/**
 * HosTPV — Animación de botones (módulo independiente)
 * ─────────────────────────────────────────────────────
 * Archivo APARTE de hostpv.php, mismo criterio que
 * hostpv-text-animations.php y hostpv-custom-cursor.php: se autorregistra
 * por completo (sus hooks de Elementor, su pestaña de ajustes y su
 * wp_footer condicional) y hostpv.php solo necesita un require_once.
 *
 * QUÉ HACE:
 *  Le añade una animación de relleno al botón NATIVO de Elementor al pasar
 *  el ratón. No crea ningún widget nuevo: el botón sigue siendo el de
 *  Elementor y su color, tamaño, borde y tipografía se siguen tocando desde
 *  el panel de Estilo de siempre. Lo único que pone este módulo es la forma
 *  en que el color de hover ENTRA en el botón, que Elementor no sabe hacer
 *  (cambia de color de golpe).
 *
 * CÓMO SE DECIDE QUÉ BOTÓN SE ANIMA:
 *  Hay un ajuste GLOBAL en la página de ajustes del plugin (un efecto para
 *  todos los botones del sitio, o ninguno) y cada botón concreto puede
 *  saltárselo desde su propio panel de edición, eligiendo otro efecto o
 *  quitándolo. Así no hay que ir botón por botón para un sitio entero.
 *
 * ⚠️ POR QUÉ NO SE TOCA EL HTML DEL BOTÓN (15/09, a la segunda).
 *  La primera versión inyectaba un atributo `data-hostpv-btn` en el HTML ya
 *  renderizado, con el filtro `elementor/widget/render_content`. No sirve:
 *  Elementor guarda el HTML ya pintado de cada widget en su caché de
 *  elementos, y cuando lo sirve de ahí ese filtro NO se ejecuta. Resultado
 *  medido en el sitio real: de 25 páginas, 12 se quedaron sin el atributo —
 *  todas aquellas cuya caché se había escrito ANTES de encender el efecto,
 *  incluida la plantilla de cabecera, que es la que lleva el botón que
 *  aparece en todas las páginas. Y no se arregla solo: esa caché no se
 *  vuelve a escribir hasta que alguien guarda ese documento.
 *
 *  Así que ahora el efecto no vive en el HTML del widget, sino en dos sitios
 *  que la caché no puede dejar obsoletos:
 *   - el ajuste global se marca con una clase en el <body> (`body_class` se
 *     ejecuta siempre, en cada petición);
 *   - el ajuste de un botón concreto se marca con `prefix_class`, o sea una
 *     clase en el envoltorio del widget que sale de sus propios ajustes
 *     guardados — si cambian, Elementor invalida la caché de ESE widget él
 *     solo, que es justo lo que hace falta.
 *  El CSS de abajo cruza las dos cosas: la clase del cuerpo aplica a los
 *  botones que no han elegido nada (`:not([class*="hostpv-btnfx-"])`) y la
 *  clase del widget gana siempre, incluida la opción de quitarse el efecto.
 *
 * RENDIMIENTO:
 *  El CSS/JS no se carga en todas las páginas: se imprime una sola vez en el
 *  wp_footer y solo si hay efecto global puesto o algún botón de esa página
 *  lo pide por su cuenta.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HosTPV_Buttons {

	/** Opción global: la clave de un efecto de EFFECTS, o cadena vacía =
	 *  ningún botón se anima salvo los que lo pidan uno a uno. */
	const OPTION_KEY_DEFAULT = 'hostpv_btnfx_default';

	/** Los efectos disponibles. La clave viaja tal cual en la clase
	 *  `hostpv-btnfx-…` y es la que engancha el CSS de más abajo. */
	const EFFECTS = [
		'centro'   => 'Relleno desde el centro',
		'lateral'  => 'Relleno desde la izquierda',
		'vertical' => 'Relleno desde abajo',
		'diagonal' => 'Barrido diagonal',
		'cortina'  => 'Cortina (dos mitades)',
		'destello' => 'Destello (no rellena)',
	];

	public function __construct() {
		// Controles nuevos en el panel de edición del botón nativo. Tiene
		// que ser after_section_end y no after_section_start: la sección
		// nativa sigue ABIERTA en after_section_start y Elementor no deja
		// abrir una sección dentro de otra (mismo tropiezo ya documentado
		// en el módulo de animaciones de texto).
		add_action( 'elementor/element/button/section_style/after_section_end', [ $this, 'register_button_controls' ], 10, 2 );

		// El efecto global se marca con una clase en el <body>.
		add_filter( 'body_class', [ $this, 'add_body_class' ] );

		// Pestaña propia dentro de la página de ajustes que ya existe, por
		// los dos hooks genéricos que expone hostpv.php.
		add_action( 'hostpv_settings_tabs', [ $this, 'render_settings_tab_button' ] );
		add_action( 'hostpv_settings_panels', [ $this, 'render_settings_tab_panel' ] );

		// El campo vive dentro del mismo <form> que ya tiene hostpv.php, así
		// que se guarda enganchando su misma acción. Prioridad 5, antes de
		// que HosTPV::save_settings() redirija y corte la petición.
		add_action( 'admin_post_hostpv_save', [ $this, 'save_global_default' ], 5 );

		add_action( 'wp_footer', [ $this, 'print_assets' ] );
	}

	// ── Controles del widget "Botón" ───────────────────────────────────

	/** @param \Elementor\Element_Base $element */
	public function register_button_controls( $element, $args ) {
		$element->start_controls_section( 'hostpv_btnfx_section', [
			'label' => __( 'HosTPV — Animación de relleno', 'hostpv' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$options = [
			''    => __( '— Lo que diga el ajuste general —', 'hostpv' ),
			'off' => __( 'Sin animación en este botón', 'hostpv' ),
		] + self::EFFECTS;

		$element->add_control( 'hostpv_btnfx_mode', [
			'label'        => __( 'Efecto', 'hostpv' ),
			'type'         => \Elementor\Controls_Manager::SELECT,
			'options'      => $options,
			'default'      => '',
			// prefix_class deja la elección en una clase del envoltorio del
			// widget, que sale de los ajustes guardados y por tanto sobrevive
			// a la caché de elementos de Elementor (ver la cabecera).
			'prefix_class' => 'hostpv-btnfx-',
			'description'  => __( 'El ajuste general está en HosTPV → Botones. Lo que se elija aquí solo afecta a este botón.', 'hostpv' ),
		] );

		$element->add_control( 'hostpv_btnfx_fill', [
			'label'       => __( 'Color del relleno', 'hostpv' ),
			'type'        => \Elementor\Controls_Manager::COLOR,
			'description' => __( 'El color que entra al pasar el ratón. Si se deja vacío se usa el color de fondo de hover que ya tenga el botón más arriba.', 'hostpv' ),
			'selectors'   => [
				'{{WRAPPER}} .elementor-button' => '--hostpv-btn-fill: {{VALUE}};',
			],
		] );

		$element->end_controls_section();
	}

	// ── Marca del efecto global en el <body> ───────────────────────────

	public function add_body_class( $classes ) {
		$fx = get_option( self::OPTION_KEY_DEFAULT, '' );
		if ( isset( self::EFFECTS[ $fx ] ) ) $classes[] = 'hostpv-btnfx-' . $fx;
		return $classes;
	}

	// ── Admin: pestaña "Botones" dentro de la página de ajustes ────────

	public function render_settings_tab_button() {
		?>
		<button type="button" class="hp-tab" data-hp-tab="tab-btnfx">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="8" width="19" height="8" rx="4"/><path d="M8 12h8"/></svg>
			Botones
		</button>
		<?php
	}

	public function render_settings_tab_panel() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$current = get_option( self::OPTION_KEY_DEFAULT, '' );
		?>
		<div id="tab-btnfx" class="hp-tab-panel" style="display:none;">
			<div class="hp-card">
				<div class="hp-card-head">
					<div class="hp-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="8" width="19" height="8" rx="4"/><path d="M8 12h8"/></svg></div>
					<h2>Animación de relleno en los botones</h2>
				</div>
				<p class="hp-card-desc">Cómo entra el color de hover en TODOS los botones del sitio. Sigue siendo el botón nativo de Elementor: su color, su tamaño y su borde se tocan donde siempre, en el panel de Estilo. Un botón concreto puede elegir otro efecto, o quitárselo, desde su propio panel de edición.</p>
				<div class="hp-field-grid">
					<label for="hostpv_btnfx_default">Efecto</label>
					<select name="hostpv_btnfx_default" id="hostpv_btnfx_default">
						<option value="">— Ninguno —</option>
						<?php foreach ( self::EFFECTS as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<p class="hp-hint">El color que entra es el mismo color de fondo de hover que el botón ya tenga puesto en Elementor. Si un botón no tiene ninguno, no se anima nada: primero hay que darle un color de hover.</p>
			</div>
		</div>
		<?php
	}

	public function save_global_default() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'hostpv_save' );

		$fx = isset( $_POST['hostpv_btnfx_default'] ) ? sanitize_text_field( wp_unslash( $_POST['hostpv_btnfx_default'] ) ) : '';
		if ( $fx !== '' && ! isset( self::EFFECTS[ $fx ] ) ) $fx = '';
		update_option( self::OPTION_KEY_DEFAULT, $fx );
	}

	// ── ¿Hace falta el CSS/JS en esta página? ──────────────────────────

	/** Con efecto global puesto hace falta en todas (el botón de la cabecera
	 *  sale en todas). Si no hay efecto global, solo si algún botón de esta
	 *  página lo pide por su cuenta — y eso se mira en lo GUARDADO del
	 *  documento, nunca en el render, que la caché de elementos se salta. */
	private static function needs_assets() {
		$global = get_option( self::OPTION_KEY_DEFAULT, '' );
		if ( isset( self::EFFECTS[ $global ] ) ) return true;

		if ( ! is_singular() || ! did_action( 'elementor/loaded' ) ) return false;
		$post_id = get_queried_object_id();
		if ( ! $post_id ) return false;
		$doc = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $doc || ! $doc->is_built_with_elementor() ) return false;

		$encontrado = false;
		self::walk_elements( $doc->get_elements_data(), function ( $el ) use ( &$encontrado ) {
			if ( $encontrado ) return;
			if ( ! isset( $el['widgetType'] ) || $el['widgetType'] !== 'button' ) return;
			$s    = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : [];
			$mode = isset( $s['hostpv_btnfx_mode'] ) ? $s['hostpv_btnfx_mode'] : '';
			if ( isset( self::EFFECTS[ $mode ] ) ) $encontrado = true;
		} );

		return $encontrado;
	}

	/** Recorre el árbol guardado de Elementor llamando a $cb con cada
	 *  elemento, contenedores anidados incluidos. */
	private static function walk_elements( $elements, $cb ) {
		if ( ! is_array( $elements ) ) return;
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) continue;
			$cb( $el );
			if ( ! empty( $el['elements'] ) ) self::walk_elements( $el['elements'], $cb );
		}
	}

	// ── CSS/JS ─────────────────────────────────────────────────────────

	/** Los dos caminos por los que un botón acaba con un efecto: la clase del
	 *  <body> (solo para los botones que no han elegido nada) y la clase del
	 *  propio widget (que gana siempre). */
	private static function selectores( $fx ) {
		return [
			'body.hostpv-btnfx-' . $fx . ' .elementor-widget-button:not([class*="hostpv-btnfx-"]) .elementor-button',
			'.elementor-widget-button.hostpv-btnfx-' . $fx . ' .elementor-button',
		];
	}

	/** Une la lista de selectores añadiéndole a cada uno el mismo sufijo
	 *  (`::before`, `:hover::before`…). */
	private static function con( $sels, $sufijo ) {
		$out = [];
		foreach ( $sels as $s ) $out[] = $s . $sufijo;
		return implode( ',', $out );
	}

	public function print_assets() {
		if ( ! self::needs_assets() ) return;

		$css = ':root{--hostpv-btn-ease:cubic-bezier(.22,.61,.36,1);}';

		foreach ( array_keys( self::EFFECTS ) as $fx ) {
			$s = self::selectores( $fx );

			// Base común a todos los efectos. La variable --hostpv-btn-on es
			// la señal que lee el JS para saber qué botones están animados:
			// no hay forma de seleccionar por variable en CSS, así que se
			// marca aquí y allí se comprueba con getComputedStyle.
			$css .= self::con( $s, '' ) . '{position:relative;overflow:hidden;z-index:0;'
			     . '--hostpv-btn-on:1;'
			     . 'transition:color .4s var(--hostpv-btn-ease),border-color .4s var(--hostpv-btn-ease);}';
			$css .= self::con( $s, ' > *' ) . '{position:relative;z-index:1;}';

			if ( $fx === 'destello' ) {
				// No rellena: cruza una luz y el botón se queda con su color,
				// así que se marca con un 2 para que el JS sepa que a este no
				// tiene que fijarle el color de reposo.
				$css .= self::con( $s, '' ) . '{--hostpv-btn-on:2;}';
				$css .= self::con( $s, '::after' ) . '{content:"";position:absolute;top:0;bottom:0;width:45%;left:-60%;z-index:0;'
				     . 'background:linear-gradient(100deg,transparent,rgba(255,255,255,.85),transparent);transition:left .6s ease;}';
				$css .= self::con( $s, ':hover::after' ) . '{left:120%;}';
				continue;
			}

			$css .= self::con( $s, '::before' ) . '{content:"";position:absolute;inset:0;z-index:0;'
			     . 'background-color:var(--hostpv-btn-fill,transparent);transition:transform .45s var(--hostpv-btn-ease);}';

			if ( $fx === 'centro' ) {
				$css .= self::con( $s, '::before' ) . '{transform:scaleX(0);transform-origin:center;}';
				$css .= self::con( $s, ':hover::before' ) . '{transform:scaleX(1);}';
			} elseif ( $fx === 'lateral' ) {
				$css .= self::con( $s, '::before' ) . '{transform:scaleX(0);transform-origin:left center;}';
				$css .= self::con( $s, ':hover::before' ) . '{transform:scaleX(1);}';
			} elseif ( $fx === 'vertical' ) {
				$css .= self::con( $s, '::before' ) . '{transform:scaleY(0);transform-origin:bottom center;}';
				$css .= self::con( $s, ':hover::before' ) . '{transform:scaleY(1);}';
			} elseif ( $fx === 'diagonal' ) {
				// Se ensancha para que las esquinas inclinadas no dejen el
				// botón a medio pintar.
				$css .= self::con( $s, '::before' ) . '{left:-20%;right:-20%;width:auto;transform:scaleX(0) skewX(-18deg);transform-origin:left center;}';
				$css .= self::con( $s, ':hover::before' ) . '{transform:scaleX(1) skewX(-18deg);}';
			} elseif ( $fx === 'cortina' ) {
				$css .= self::con( $s, '::before' ) . '{bottom:50%;transform:scaleX(0);transform-origin:left center;}';
				$css .= self::con( $s, '::after' ) . '{content:"";position:absolute;left:0;right:0;top:50%;bottom:0;z-index:0;'
				     . 'background-color:var(--hostpv-btn-fill,transparent);transform:scaleX(0);transform-origin:right center;'
				     . 'transition:transform .45s var(--hostpv-btn-ease);}';
				$css .= self::con( $s, ':hover::before' ) . ',' . self::con( $s, ':hover::after' ) . '{transform:scaleX(1);}';
			}
		}

		// Un botón que se ha quitado el efecto a mano se queda como estaba.
		$css .= '.elementor-widget-button.hostpv-btnfx-off .elementor-button{--hostpv-btn-on:0;}'
		     . '.elementor-widget-button.hostpv-btnfx-off .elementor-button::before,'
		     . '.elementor-widget-button.hostpv-btnfx-off .elementor-button::after{display:none;}';

		// Con movimiento reducido no se anima nada: el botón se queda con el
		// cambio de color nativo de Elementor, que el JS no llega a tocar.
		// Se acota a nuestros propios selectores para no cargarse un
		// pseudo-elemento de otro sitio.
		$quietos = [];
		foreach ( array_keys( self::EFFECTS ) as $fx ) {
			foreach ( self::selectores( $fx ) as $sel ) {
				$quietos[] = $sel . '::before';
				$quietos[] = $sel . '::after';
			}
		}
		$css .= '@media (prefers-reduced-motion: reduce){' . implode( ',', $quietos ) . '{display:none;}}';
		?>
		<style id="hostpv-btnfx-css"><?php echo $css; // phpcs:ignore -- CSS propio, ya escapado por construcción ?></style>
		<script id="hostpv-btnfx-js">
		(function(){
			if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

			// --hostpv-btn-on lo pone el CSS de arriba: 1 = relleno animado,
			// 2 = destello (no rellena, así que no hay color que fijar).
			var botones = Array.prototype.slice.call(document.querySelectorAll('.elementor-button'))
				.filter(function(b){ return getComputedStyle(b).getPropertyValue('--hostpv-btn-on').trim() === '1'; });

			botones.forEach(function(b){
				// Color del relleno: el de hover que ese botón ya tenga
				// puesto en Elementor. Se busca en las hojas de estilo YA
				// cargadas (se excluye la nuestra) una regla :hover con
				// background-color que le aplique a este botón en concreto.
				// Si el widget trae su propio "Color del relleno", Elementor
				// ya ha escrito --hostpv-btn-fill por CSS y esto no hace
				// falta, así que solo se rellena si está vacío.
				var yaTiene = getComputedStyle(b).getPropertyValue('--hostpv-btn-fill').trim();
				var hoverBg = yaTiene ? '' : buscarFondoHover(b);
				if (hoverBg) b.style.setProperty('--hostpv-btn-fill', hoverBg);
				if (!hoverBg && !yaTiene) return;

				// Y se fija el color de reposo como estilo en línea, para que
				// la regla :hover de Elementor no cambie el fondo de golpe
				// por debajo de la animación (un estilo en línea gana a
				// cualquier selector de hoja mientras no lleve !important, y
				// las de Elementor no lo llevan).
				var reposo = getComputedStyle(b).backgroundColor;
				if (reposo) b.style.backgroundColor = reposo;
			});

			function buscarFondoHover(el){
				var encontrado = '';
				var propia = document.getElementById('hostpv-btnfx-css');

				function recorrer(reglas){
					for (var i = 0; i < reglas.length; i++){
						var regla = reglas[i];
						// Una @media o un @supports es un contenedor: hay que
						// entrar, porque si no las reglas de dentro (las de
						// móvil, por ejemplo) no se mirarían nunca.
						if (regla.cssRules && !regla.selectorText){ recorrer(regla.cssRules); continue; }
						if (!regla.selectorText || !regla.style) continue;
						if (!regla.style.backgroundColor) continue;
						var selectores = regla.selectorText.split(',');
						for (var j = 0; j < selectores.length; j++){
							var sel = selectores[j].trim();
							if (sel.indexOf(':hover') === -1) continue;
							var base = sel.replace(/:hover/g, '');
							if (!base) continue;
							try {
								if (el.matches(base)) encontrado = regla.style.backgroundColor;
							} catch (e) { /* selector que matches() no entiende */ }
						}
					}
				}

				for (var s = 0; s < document.styleSheets.length; s++){
					var hoja = document.styleSheets[s];
					if (hoja.ownerNode === propia) continue;
					var reglas;
					try { reglas = hoja.cssRules || hoja.rules; } catch (e) { continue; }
					if (!reglas) continue;
					recorrer(reglas);
				}
				return encontrado;
			}
		})();
		</script>
		<?php
	}
}

new HosTPV_Buttons();
