<?php
/**
 * HosTPV — Animaciones de texto (módulo independiente)
 * ─────────────────────────────────────────────────────
 * Archivo APARTE de hostpv.php a propósito (12/08): así
 * esta función se puede ampliar, desactivar por completo o incluso mover a
 * otro plugin sin tocar el archivo principal. Se carga con un solo
 * require_once desde hostpv.php (ver el comentario junto a esa línea) y se
 * autorregistra: no depende de que HosTPV::__construct() sepa nada de él.
 *
 * QUÉ HACE:
 *  1) Añade una pestaña "HosTPV — Animación de texto" al widget nativo
 *     "Título" (Heading) de Elementor, con casillas independientes para
 *     animación de ENTRADA (al hacer scroll) y de HOVER (si el título tiene
 *     un enlace configurado). Se aplica igual a H1, H2, H3... (Elementor
 *     usa un único widget "heading" con un control interno que elige el
 *     nivel, así que no hace falta tratarlos por separado).
 *  2) Añade una regla GLOBAL (un único ajuste en el admin) para los enlaces
 *     que van DENTRO de párrafos de texto normal (<p>...<a>...</a>...</p>) —
 *     se pidió esto como mecanismo aparte del de los títulos, no uno a
 *     uno por enlace. Ese ajuste vive DENTRO de la página de ajustes que ya
 *     existe del plugin ("HosTPV" en el menú lateral), como una pestaña más
 *     junto a "Logo animado"/"Caja y Halo" — no como página/submenú aparte
 *     (12/08: se probó primero como submenú propio, pero se prefirió que
 *     todo esté en la misma pantalla). hostpv.php expone dos hooks
 *     genéricos (`hostpv_settings_tabs`/`hostpv_settings_panels`) para que
 *     este módulo (y cualquier otro futuro) añada su pestaña sin que
 *     hostpv.php tenga que saber nada de animaciones de texto.
 *  3) NO toca cabecera ni pie de página (decisión del 12/08): esos
 *     quedan fuera por ahora porque, si se montan con Elementor Theme
 *     Builder, el hook `elementor/widget/render_content` no llega a los
 *     widgets de esas plantillas cuando se muestran solas (limitación real
 *     y documentada de Elementor — ver issue elementor/elementor#10039).
 *     Si en una versión futura hace falta cabecera/pie, habría que revisar
 *     esa limitación primero.
 *
 * DECISIONES DE RENDIMIENTO:
 *  - El CSS/JS del efecto NO se carga en todas las páginas: se imprime una
 *    sola vez en el `wp_footer`, y SOLO si en esa página concreta se ha
 *    inyectado de verdad al menos un atributo `data-hostpv-load` o
 *    `data-hostpv-hover` (bandera estática `self::$needs_assets`, mismo
 *    patrón que `HosTPV::$logo_assets_printed` en el archivo principal).
 *    Si una página no usa ningún título ni enlace animado, no se manda ni
 *    un byte de este módulo.
 *  - Elementor solo añade los 4 controles nuevos al panel de EDICIÓN del
 *    widget "Título" (`elementor/element/heading/section_title/after_section_end`
 *    — OJO: tiene que ser `after_section_end`, no `after_section_start`. La
 *    sección nativa "section_title" sigue ABIERTA en `after_section_start`
 *    [bug real, 12/08: detectado en el sitio real — "Elementor: You
 *    can't start a section before the end of the previous section
 *    'section_title'" — porque `start_controls_section()` no se puede llamar
 *    mientras otra sección de Elementor sigue abierta; hay que esperar a que
 *    esa sección nativa termine de verdad con `after_section_end` antes de
 *    abrir la nuestra]).
 *    Esto por sí solo NO cambia el HTML del frontend — hace falta además el
 *    filtro `elementor/widget/render_content` (2) para reescribir el HTML
 *    ya renderizado con los atributos `data-hostpv-*`, que es lo que de
 *    verdad activa el efecto en la página. Confirmado en la documentación
 *    oficial: https://developers.elementor.com/docs/hooks/render-widget-content/
 *  - La animación "Entrance Animation" nativa de Elementor usa Animate.css
 *    con presets fijos — no vale para estos 11 efectos a medida, por eso
 *    hace falta este módulo en vez de reutilizar ese control nativo.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HosTPV_Text_Animations {

	/** Opción global (un solo valor: la clave de un efecto de HOVER_EFFECTS,
	 *  o cadena vacía = desactivado) para los enlaces dentro de párrafos. */
	const OPTION_KEY_P_LINKS = 'hostpv_txtfx_p_links';

	/** 12/08 (tras la primera entrega): opción global con
	 *  un efecto de ENTRADA y uno de HOVER por defecto para cada nivel de
	 *  título (H1-H6) — array `['h1' => ['load' => 'mask', 'hover' => ''],
	 *  'h2' => [...], ...]`, cadena vacía = sin efecto por defecto en ese
	 *  nivel/aspecto. Si un título CONCRETO tiene su propia casilla
	 *  (load o hover) activada en el widget, esa gana sobre este valor por
	 *  defecto para ESE título — ver render_heading_content(). */
	const OPTION_KEY_H_DEFAULTS = 'hostpv_txtfx_h_defaults';

	/** Los 6 niveles de título que cubre el ajuste global de arriba. */
	const HEADING_LEVELS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	/** El trazo del subrayado a mano. Va estirado sobre el trozo de título
	 *  que se subraya (preserveAspectRatio="none" + width/height al 100 %),
	 *  así que la curva se adapta a palabras cortas y largas por igual. El
	 *  color, el grosor y la altura salen de los controles de la pestaña
	 *  Estilo del widget; aquí solo va la forma. */
	const MARK_SVG = '<svg viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M3,77.5s200.54-11,493,0" transform="translate(-2.75 -68.11)"/></svg>';

	/** Los 5 efectos de "entrada" que sobrevivieron a las rondas de recorte
	 *  en muestra-animaciones-texto.html (12/08). Claves = nombres técnicos
	 *  usados en el atributo data-hostpv-load y en el CSS/JS de abajo. */
	const LOAD_EFFECTS = [
		'mask'     => 'Deslizar hacia arriba (mask reveal)',
		'scramble' => 'Decodificar / scramble',
		'chars'    => 'Aparecer letra a letra',
		'clip'     => 'Barrido tipo cortina (clip-path)',
		'bounce'   => 'Rebote elástico (por palabra)',
	];

	/** Los 6 efectos de "hover" que sobrevivieron (familia Fill sweep x4 +
	 *  familia Spotlight/color reveal x2). */
	const HOVER_EFFECTS = [
		'fill-sweep'   => 'Relleno de color (fill sweep)',
		'bg-sweep'     => 'Fondo de color (invierte el texto)',
		'fill-sweep-v' => 'Relleno vertical (líquido)',
		'diag-sweep'   => 'Barrido diagonal',
		'spotlight'    => 'Foco de color (spotlight)',
		'glow'         => 'Halo que sigue al cursor',
	];

	/** true en cuanto se inyecta de verdad un atributo data-hostpv-* en la
	 *  página que se está sirviendo — controla si print_assets() manda algo
	 *  en el wp_footer de ESA petición concreta. */
	private static $needs_assets = false;

	public function __construct() {
		// 1) Controles nuevos en el panel de edición del widget "Título".
		add_action( 'elementor/element/heading/section_title/after_section_end', [ $this, 'register_heading_controls' ], 10, 2 );

		// 15/09: los controles de aspecto del subrayado a mano van a la
		// pestaña Estilo del mismo widget (misma razón que arriba para usar
		// after_section_end y no after_section_start).
		add_action( 'elementor/element/heading/section_title_style/after_section_end', [ $this, 'register_heading_style_controls' ], 10, 2 );

		// 2) Reescribe el HTML ya renderizado del widget "Título" con los
		//    atributos data-hostpv-load / data-hostpv-hover.
		add_filter( 'elementor/widget/render_content', [ $this, 'render_heading_content' ], 10, 2 );

		// 3) Regla global: enlaces dentro de <p> del contenido normal.
		add_filter( 'the_content', [ $this, 'apply_paragraph_link_hover' ], 20 );

		// Ajuste global: 12/08, vive DENTRO de la misma
		// página de ajustes del plugin ("HosTPV" en el menú lateral), como
		// una pestaña más — NO como página/submenú aparte (así se probó
		// primero, pero se prefirió tenerlo todo en un solo sitio). Se
		// engancha a los dos hooks genéricos que expone
		// HosTPV::render_settings_page() en hostpv.php
		// (hostpv_settings_tabs / hostpv_settings_panels) — hostpv.php no
		// sabe nada de animaciones de texto, solo ofrece el punto de
		// extensión; toda la lógica sigue aquí.
		add_action( 'hostpv_settings_tabs', [ $this, 'render_settings_tab_button' ] );
		add_action( 'hostpv_settings_panels', [ $this, 'render_settings_tab_panel' ] );

		// El campo de este módulo vive DENTRO del mismo <form> que ya tiene
		// hostpv.php (un solo botón "Guardar cambios" para toda la página),
		// así que se guarda enganchando la MISMA acción que ya usa ese
		// formulario (admin_post_hostpv_save) en vez de tener una acción
		// propia. Prioridad 5 (antes que HosTPV::save_settings(), que está a
		// la prioridad por defecto 10) para que dé tiempo a guardar el
		// option ANTES de que save_settings() haga wp_safe_redirect()+exit.
		add_action( 'admin_post_hostpv_save', [ $this, 'save_global_defaults' ], 5 );

		// Impresión condicional del CSS/JS compartido.
		add_action( 'wp_footer', [ $this, 'print_assets' ] );
	}

	// ── 1) Controles del widget "Título" de Elementor ──────────────────

	/** @param \Elementor\Element_Base $element */
	public function register_heading_controls( $element, $args ) {
		$element->start_controls_section( 'hostpv_txtfx_section', [
			'label' => __( 'HosTPV — Animación de texto', 'hostpv' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$element->add_control( 'hostpv_load_enabled', [
			'label'        => __( 'Animar la entrada', 'hostpv' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Sí', 'hostpv' ),
			'label_off'    => __( 'No', 'hostpv' ),
			'return_value' => 'yes',
			'default'      => '',
			'description'  => __( 'Se dispara una vez, la primera vez que el título entra en la pantalla al hacer scroll.', 'hostpv' ),
		] );

		$element->add_control( 'hostpv_load_effect', [
			'label'     => __( 'Efecto de entrada', 'hostpv' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => self::LOAD_EFFECTS,
			'default'   => 'mask',
			'condition' => [ 'hostpv_load_enabled' => 'yes' ],
		] );

		$element->add_control( 'hostpv_hover_enabled', [
			'label'        => __( 'Animar al pasar el ratón', 'hostpv' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Sí', 'hostpv' ),
			'label_off'    => __( 'No', 'hostpv' ),
			'return_value' => 'yes',
			'default'      => '',
			'description'  => __( 'Solo tiene efecto si este título tiene un enlace puesto en el control "Enlace" del propio widget. Si no hay enlace, esta casilla no hace nada.', 'hostpv' ),
		] );

		$element->add_control( 'hostpv_hover_effect', [
			'label'     => __( 'Efecto de hover', 'hostpv' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => self::HOVER_EFFECTS,
			'default'   => 'fill-sweep',
			'condition' => [ 'hostpv_hover_enabled' => 'yes' ],
		] );

		$element->add_control( 'hostpv_mark_text', [
			'label'       => __( 'Subrayar a mano', 'hostpv' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'la palabra del título que va subrayada', 'hostpv' ),
			'label_block' => true,
			'separator'   => 'before',
			'description' => __( 'Escribe aquí, tal cual, el trozo del título que lleva el trazo debajo. Tiene que coincidir letra por letra con lo que hay en el campo Título. Si se deja vacío, no se dibuja nada. El color y el grosor del trazo están en la pestaña Estilo.', 'hostpv' ),
		] );

		$element->end_controls_section();
	}

	/** Controles de aspecto del subrayado — van a la pestaña ESTILO del
	 *  propio widget "Título", no a un CSS aparte: el color y el grosor se
	 *  tocan donde se tocan todos los demás estilos de ese título.
	 *  @param \Elementor\Element_Base $element */
	public function register_heading_style_controls( $element, $args ) {
		$element->start_controls_section( 'hostpv_mark_style_section', [
			'label'     => __( 'HosTPV — Subrayado a mano', 'hostpv' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'hostpv_mark_text!' => '' ],
		] );

		$element->add_control( 'hostpv_mark_color', [
			'label'     => __( 'Color del trazo', 'hostpv' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
			'selectors' => [
				'{{WRAPPER}} .hostpv-mark > svg path' => 'stroke: {{VALUE}};',
			],
		] );

		$element->add_control( 'hostpv_mark_weight', [
			'label'      => __( 'Grosor del trazo', 'hostpv' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 6, 'max' => 70, 'step' => 1 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 38 ],
			'selectors'  => [
				'{{WRAPPER}} .hostpv-mark > svg path' => 'stroke-width: {{SIZE}}{{UNIT}};',
			],
		] );

		$element->add_control( 'hostpv_mark_offset', [
			'label'      => __( 'Altura del trazo', 'hostpv' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ '%' ],
			'range'      => [ '%' => [ 'min' => 60, 'max' => 110, 'step' => 1 ] ],
			'default'    => [ 'unit' => '%', 'size' => 90 ],
			'selectors'  => [
				'{{WRAPPER}} .hostpv-mark > svg' => 'top: {{SIZE}}{{UNIT}};',
			],
			'description' => __( 'Sube o baja el trazo respecto a la palabra. Cuanto más alto el valor, más abajo queda.', 'hostpv' ),
		] );

		$element->end_controls_section();
	}

	// ── 2) Inyección de atributos en el HTML ya renderizado ────────────

	/**
	 * @param string                   $content
	 * @param \Elementor\Widget_Base   $widget
	 * @return string
	 */
	public function render_heading_content( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || $widget->get_name() !== 'heading' ) {
			return $content;
		}

		// Se necesita SIEMPRE saber el nivel real (h1-h6) que ha renderizado
		// el widget — tanto si tiene su propia casilla activada como si hay
		// que caer al valor por defecto global de ese nivel. El widget
		// "Título" renderiza UN solo <h1>-<h6> (con clases de Elementor); si
		// tiene enlace, el <a> va DENTRO de ese heading.
		if ( ! preg_match( '/<(h[1-6])(\s[^>]*)?>/i', $content, $m ) ) {
			return $content; // no debería pasar en un widget Título normal, pero por si acaso
		}
		$level = strtolower( $m[1] );

		$settings   = $widget->get_settings_for_display();
		$h_defaults = self::get_heading_defaults();

		// ── Efecto de ENTRADA: gana la casilla propia del widget si está
		//    activada; si no, cae al valor por defecto global de ESTE nivel
		//    (H1-H6), si se ha puesto uno — si algún widget tiene la
		//    animación activada, eso salta la configuración global en ese
		//    texto en concreto (comportamiento explícito, 12/08). ──
		$load_fx = '';
		if ( isset( $settings['hostpv_load_enabled'] ) && $settings['hostpv_load_enabled'] === 'yes' ) {
			$load_fx = isset( $settings['hostpv_load_effect'] ) ? $settings['hostpv_load_effect'] : 'mask';
			if ( ! isset( self::LOAD_EFFECTS[ $load_fx ] ) ) $load_fx = 'mask';
		} elseif ( isset( self::LOAD_EFFECTS[ $h_defaults[ $level ]['load'] ] ) ) {
			$load_fx = $h_defaults[ $level ]['load'];
		}

		// ── Efecto de HOVER: mismo mecanismo (propio > global por nivel),
		//    pero solo tiene algo que animar si el título tiene un enlace
		//    puesto (se comprueba más abajo al buscar el <a>). ──
		$hover_fx = '';
		if ( isset( $settings['hostpv_hover_enabled'] ) && $settings['hostpv_hover_enabled'] === 'yes' ) {
			$hover_fx = isset( $settings['hostpv_hover_effect'] ) ? $settings['hostpv_hover_effect'] : 'fill-sweep';
			if ( ! isset( self::HOVER_EFFECTS[ $hover_fx ] ) ) $hover_fx = 'fill-sweep';
		} elseif ( isset( self::HOVER_EFFECTS[ $h_defaults[ $level ]['hover'] ] ) ) {
			$hover_fx = $h_defaults[ $level ]['hover'];
		}

		// ── SUBRAYADO A MANO: no tiene valor por defecto global, se pone
		//    título a título escribiendo el trozo que va subrayado. ──
		$mark_text = isset( $settings['hostpv_mark_text'] ) ? trim( (string) $settings['hostpv_mark_text'] ) : '';

		if ( $load_fx === '' && $hover_fx === '' && $mark_text === '' ) return $content;

		$modified = false;

		if ( $mark_text !== '' ) {
			$new = self::wrap_mark( $content, $mark_text );
			if ( $new !== null ) { $content = $new; $modified = true; }
		}

		// Se marca solo la primera etiqueta encontrada (limit 1) — no puede
		// haber una segunda en el HTML de este mismo widget.
		if ( $load_fx !== '' ) {
			$count = 0;
			$new = preg_replace(
				'/<(h[1-6])(\s[^>]*)?>/i',
				'<$1$2 data-hostpv-load="' . esc_attr( $load_fx ) . '">',
				$content,
				1,
				$count
			);
			if ( $count ) { $content = $new; $modified = true; }
		}

		if ( $hover_fx !== '' ) {
			$count = 0;
			$new = preg_replace(
				'/<a(\s[^>]*)?>/i',
				'<a$1 data-hostpv-hover="' . esc_attr( $hover_fx ) . '">',
				$content,
				1,
				$count
			);
			// Si $count es 0 es que el título no tiene enlace puesto — no
			// pasa nada, tanto la casilla individual como el valor por
			// defecto global simplemente no tienen nada que animar.
			if ( $count ) { $content = $new; $modified = true; }
		}

		if ( $modified ) self::$needs_assets = true;

		return $content;
	}

	/**
	 * Envuelve la PRIMERA aparición de $needle en el HTML ya renderizado del
	 * título con el span del subrayado y le cuelga el <svg> del trazo.
	 *
	 * Se busca solo en los trozos de TEXTO, nunca dentro de una etiqueta: si
	 * no, un título con un enlace podría "encontrar" la palabra dentro de un
	 * href o de una clase y romper el HTML. Por eso el contenido se parte
	 * antes en etiquetas y texto (PREG_SPLIT_DELIM_CAPTURE) y solo se toca lo
	 * segundo.
	 *
	 * La comparación se hace sobre el texto decodificado (&amp; vuelve a ser
	 * &) para que lo que se escribe en el campo del widget coincida con lo que
	 * se lee en pantalla, no con la versión escapada.
	 *
	 * @return string|null El HTML nuevo, o null si no se encontró el trozo.
	 */
	private static function wrap_mark( $content, $needle ) {
		$parts = preg_split( '/(<[^>]*>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) return null;

		foreach ( $parts as $i => $part ) {
			if ( $part === '' || $part[0] === '<' ) continue; // etiqueta, no se toca

			$plain = html_entity_decode( $part, ENT_QUOTES, 'UTF-8' );
			$pos   = mb_strpos( $plain, $needle );
			if ( $pos === false ) continue;

			$before = mb_substr( $plain, 0, $pos );
			$found  = mb_substr( $plain, $pos, mb_strlen( $needle ) );
			$after  = mb_substr( $plain, $pos + mb_strlen( $needle ) );

			$parts[ $i ] = esc_html( $before )
				. '<span class="hostpv-mark">' . esc_html( $found ) . self::MARK_SVG . '</span>'
				. esc_html( $after );

			return implode( '', $parts );
		}

		return null;
	}

	/** Lee la opción global de valores por defecto por nivel de título y la
	 *  normaliza a los 6 niveles con 'load'/'hover' siempre presentes (cadena
	 *  vacía = sin valor por defecto), para no tener que comprobar isset()
	 *  en cada sitio que la usa. */
	private static function get_heading_defaults() {
		$raw = get_option( self::OPTION_KEY_H_DEFAULTS, [] );
		$out = [];
		foreach ( self::HEADING_LEVELS as $level ) {
			$out[ $level ] = [
				'load'  => isset( $raw[ $level ]['load'] )  ? $raw[ $level ]['load']  : '',
				'hover' => isset( $raw[ $level ]['hover'] ) ? $raw[ $level ]['hover'] : '',
			];
		}
		return $out;
	}

	// ── 3) Regla global: <a> dentro de <p> ──────────────────────────────

	public function apply_paragraph_link_hover( $content ) {
		if ( is_admin() || is_feed() || ! is_string( $content ) || $content === '' ) return $content;

		$fx = get_option( self::OPTION_KEY_P_LINKS, '' );
		if ( ! $fx || ! isset( self::HOVER_EFFECTS[ $fx ] ) ) return $content;

		$touched = false;

		$new = preg_replace_callback(
			'/<p\b[^>]*>.*?<\/p>/is',
			function ( $m ) use ( $fx, &$touched ) {
				$block = preg_replace(
					'/<a(?![^>]*data-hostpv-hover)(\s[^>]*)?>/i',
					'<a$1 data-hostpv-hover="' . esc_attr( $fx ) . '">',
					$m[0],
					-1,
					$count
				);
				if ( $count ) $touched = true;
				return $block;
			},
			$content
		);

		if ( $touched && $new !== null ) {
			self::$needs_assets = true;
			$content = $new;
		}

		return $content;
	}

	// ── Admin: pestaña "Animaciones de texto" dentro de la página de ajustes existente ──

	/** Enganchado a `hostpv_settings_tabs` (hostpv.php). Solo el botón de
	 *  pestaña — mismo patrón que las dos pestañas ya existentes
	 *  (Logo animado / Caja y Halo). */
	public function render_settings_tab_button() {
		?>
		<button type="button" class="hp-tab" data-hp-tab="tab-txtfx">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20 9 4l5 16M6.5 14h5"/><path d="M15 20l3-9 3 9M16.5 16.5h3"/></svg>
			Animaciones de texto
		</button>
		<?php
	}

	/** Enganchado a `hostpv_settings_panels` (hostpv.php). El contenido de
	 *  la pestaña — vive DENTRO del <form> ya abierto por hostpv.php, así
	 *  que NO lleva su propio <form>/nonce/botón, solo los campos. Se
	 *  guarda con save_global_defaults() (enganchado a la misma
	 *  admin_post_hostpv_save del formulario compartido). */
	public function render_settings_tab_panel() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$current_p = get_option( self::OPTION_KEY_P_LINKS, '' );
		$h_defaults = self::get_heading_defaults();
		?>
		<div id="tab-txtfx" class="hp-tab-panel" style="display:none;">
			<div class="hp-card">
				<div class="hp-card-head">
					<div class="hp-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20 9 4l5 16M6.5 14h5"/><path d="M15 20l3-9 3 9M16.5 16.5h3"/></svg></div>
					<h2>Animaciones de texto — valores por defecto (H1-H6)</h2>
				</div>
				<p class="hp-card-desc">Efecto de entrada y de hover que se aplica POR DEFECTO a TODOS los títulos de cada nivel. Si un título CONCRETO tiene su propia casilla activada en el widget "Título" de Elementor, ese valor individual GANA sobre el de aquí para ese título en particular. No afecta a cabecera ni a pie de página.</p>
				<?php foreach ( self::HEADING_LEVELS as $level ) : $level_upper = strtoupper( $level ); ?>
					<div class="hp-field-grid">
						<label><?php echo esc_html( $level_upper ); ?></label>
						<div style="display:flex;gap:24px;flex-wrap:wrap;">
							<label style="display:flex;flex-direction:column;gap:4px;padding-top:0;font-weight:500;">
								<span class="hp-hint" style="margin:0;">Entrada</span>
								<select name="hostpv_txtfx_h_defaults[<?php echo esc_attr( $level ); ?>][load]">
									<option value="">— Sin efecto por defecto —</option>
									<?php foreach ( self::LOAD_EFFECTS as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $h_defaults[ $level ]['load'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label style="display:flex;flex-direction:column;gap:4px;padding-top:0;font-weight:500;">
								<span class="hp-hint" style="margin:0;">Hover (si el título tiene enlace)</span>
								<select name="hostpv_txtfx_h_defaults[<?php echo esc_attr( $level ); ?>][hover]">
									<option value="">— Sin efecto por defecto —</option>
									<?php foreach ( self::HOVER_EFFECTS as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $h_defaults[ $level ]['hover'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="hp-card">
				<div class="hp-card-head">
					<div class="hp-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.5-1.5"/></svg></div>
					<h2>Enlaces dentro de párrafos</h2>
				</div>
				<p class="hp-card-desc">Regla global para todos los <code>&lt;a&gt;</code> que estén dentro de un <code>&lt;p&gt;</code> del contenido normal de páginas/entradas.</p>
				<div class="hp-field-grid">
					<label for="hostpv_txtfx_p_links">Efecto de hover</label>
					<div>
						<select name="hostpv_txtfx_p_links" id="hostpv_txtfx_p_links">
							<option value="">— Desactivado —</option>
							<?php foreach ( self::HOVER_EFFECTS as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_p, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="hp-field-hint">No hace falta tocar cada enlace uno a uno — se aplica solo.</p>
				</div>
			</div>
		</div>
		<?php
	}

	/** Enganchado a la MISMA acción `admin_post_hostpv_save` que ya usa el
	 *  formulario compartido de hostpv.php, con prioridad 5 (antes que
	 *  HosTPV::save_settings(), en la 10) para guardar estos options ANTES
	 *  de que ese método haga wp_safe_redirect()+exit. No hace ni nonce-die
	 *  ni redirect propios — el formulario compartido ya se encarga de todo
	 *  eso una sola vez para toda la página. Guarda las DOS opciones de esta
	 *  pestaña: los valores por defecto por nivel de título Y la regla de
	 *  enlaces de párrafo. */
	public function save_global_defaults() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'hostpv_save' );

		$fx = isset( $_POST['hostpv_txtfx_p_links'] ) ? sanitize_text_field( wp_unslash( $_POST['hostpv_txtfx_p_links'] ) ) : '';
		if ( $fx !== '' && ! isset( self::HOVER_EFFECTS[ $fx ] ) ) $fx = '';
		update_option( self::OPTION_KEY_P_LINKS, $fx );

		$posted = ( isset( $_POST['hostpv_txtfx_h_defaults'] ) && is_array( $_POST['hostpv_txtfx_h_defaults'] ) )
			? wp_unslash( $_POST['hostpv_txtfx_h_defaults'] )
			: [];
		$clean = [];
		foreach ( self::HEADING_LEVELS as $level ) {
			$load  = isset( $posted[ $level ]['load'] )  ? sanitize_text_field( $posted[ $level ]['load'] )  : '';
			$hover = isset( $posted[ $level ]['hover'] ) ? sanitize_text_field( $posted[ $level ]['hover'] ) : '';
			if ( $load !== '' && ! isset( self::LOAD_EFFECTS[ $load ] ) )     $load  = '';
			if ( $hover !== '' && ! isset( self::HOVER_EFFECTS[ $hover ] ) ) $hover = '';
			$clean[ $level ] = [ 'load' => $load, 'hover' => $hover ];
		}
		update_option( self::OPTION_KEY_H_DEFAULTS, $clean );
	}

	// ── ¿Hace falta el CSS/JS en esta página? ───────────────────────────

	/**
	 * 15/09 — LA CACHÉ DE ELEMENTOS DE ELEMENTOR.
	 *
	 * self::$needs_assets se pone a true desde el filtro de render, pero ese
	 * filtro NO se ejecuta cuando Elementor sirve el widget desde su caché de
	 * elementos (guarda el HTML ya pintado). Y como el HTML cacheado SÍ trae
	 * los atributos data-hostpv-* inyectados en su día, el resultado era el
	 * peor posible: la página salía con los atributos puestos pero sin el CSS
	 * ni el JS que los hacen funcionar. Comprobado con dos peticiones
	 * seguidas a la home: en la primera van la hoja y el script, en la
	 * segunda ya no. O sea que las animaciones de texto llevaban tiempo sin
	 * verse en la práctica, porque casi todas las visitas reciben la versión
	 * cacheada.
	 *
	 * La salida es no depender del render: se mira lo que hay GUARDADO en el
	 * documento de Elementor de esta página, que la caché no afecta. Es una
	 * sola lectura de metadatos que WordPress ya tiene en memoria.
	 */
	private static function document_needs_assets() {
		if ( ! is_singular() || ! did_action( 'elementor/loaded' ) ) return false;

		$post_id = get_queried_object_id();
		if ( ! $post_id ) return false;

		$doc = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $doc || ! $doc->is_built_with_elementor() ) return false;

		// ¿Hay algún valor por defecto global puesto? Si lo hay, cualquier
		// título de ese nivel en la página ya necesita los assets.
		$hay_global = false;
		foreach ( self::get_heading_defaults() as $d ) {
			if ( $d['load'] !== '' || $d['hover'] !== '' ) { $hay_global = true; break; }
		}

		$encontrado = false;
		self::walk_elements( $doc->get_elements_data(), function ( $el ) use ( $hay_global, &$encontrado ) {
			if ( $encontrado ) return;
			if ( ! isset( $el['widgetType'] ) || $el['widgetType'] !== 'heading' ) return;

			if ( $hay_global ) { $encontrado = true; return; }

			$s = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : [];
			if ( ( isset( $s['hostpv_load_enabled'] )  && $s['hostpv_load_enabled']  === 'yes' )
			  || ( isset( $s['hostpv_hover_enabled'] ) && $s['hostpv_hover_enabled'] === 'yes' )
			  || ( isset( $s['hostpv_mark_text'] )     && trim( (string) $s['hostpv_mark_text'] ) !== '' ) ) {
				$encontrado = true;
			}
		} );

		return $encontrado;
	}

	/** Recorre el árbol guardado de Elementor (contenedores dentro de
	 *  contenedores) llamando a $cb con cada elemento. */
	private static function walk_elements( $elements, $cb ) {
		if ( ! is_array( $elements ) ) return;
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) continue;
			$cb( $el );
			if ( ! empty( $el['elements'] ) ) self::walk_elements( $el['elements'], $cb );
		}
	}

	// ── Impresión condicional del CSS/JS compartido ─────────────────────

	/**
	 * Se engancha a wp_footer SIEMPRE, pero solo imprime algo si
	 * self::$needs_assets se puso a true durante el render de ESTA página
	 * (en render_heading_content() o en apply_paragraph_link_hover(), que
	 * ya se han ejecutado antes de llegar aquí porque el contenido de la
	 * página se genera antes que el footer). Mismo patrón que
	 * HosTPV::$logo_assets_printed en hostpv.php.
	 *
	 * El JS reutiliza, adaptada a atributos data-hostpv-* en vez de al
	 * data-fx de la demo, la misma lógica ya verificada con Playwright en
	 * muestra-animaciones-texto.html — incluido el arreglo del 12/08 del
	 * espacio que colapsa a ancho 0 dentro de un span inline-block (el
	 * espacio se añade como nodo de texto HERMANO, nunca como último
	 * carácter dentro de un span).
	 */
	public function print_assets() {
		if ( ! self::$needs_assets && ! self::document_needs_assets() ) return;
		?>
		<style id="hostpv-txtfx-css">
			:root{
				/* 12/08: valor de RESERVA unicamente -- el JS de mas abajo
				   sobrescribe esta variable por elemento (con
				   style="--hostpv-fx-accent:..." inline) con el color de
				   hover NATIVO real de cada enlace/titulo en concreto
				   (leido de las hojas de estilo del tema/Elementor).
				   Este azul solo se usa si ese enlace
				   no tiene ningun color de hover nativo detectable. */
				--hostpv-fx-accent: #2271b1;
				--hostpv-fx-ease: cubic-bezier(.16,1,.3,1);
				--hostpv-fx-ease-bounce: cubic-bezier(.34,1.56,.64,1);
			}

			/* 1) Slide-up mask reveal */
			.hostpv-fx-mask-word{display:inline-block;overflow:hidden;vertical-align:top;}
			.hostpv-fx-mask-word-inner{display:inline-block;transform:translateY(115%);opacity:0;}
			.hostpv-fx-in .hostpv-fx-mask-word-inner{animation:hostpvFxMaskUp .85s var(--hostpv-fx-ease) forwards;animation-delay:calc(var(--i) * 70ms + 60ms);}
			@keyframes hostpvFxMaskUp{to{transform:translateY(0);opacity:1;}}

			/* 2) Decode / scramble */
			.hostpv-fx-scramble-glyph{color:var(--hostpv-fx-accent);opacity:.85;}

			/* 3) Char stagger */
			/* 12/08: envoltorio por palabra — evita que el navegador parta la
			   palabra a mitad de letra al hacer wrap (ver splitChars() en JS). */
			.hostpv-fx-char-word{display:inline-block;vertical-align:top;}
			.hostpv-fx-char{display:inline-block;opacity:0;transform:translateY(.5em) rotate(4deg);}
			.hostpv-fx-in .hostpv-fx-char{animation:hostpvFxCharIn .6s var(--hostpv-fx-ease) forwards;animation-delay:calc(var(--i) * 26ms);}
			@keyframes hostpvFxCharIn{to{opacity:1;transform:translateY(0) rotate(0);}}

			/* 4) Clip-path wipe (cortina, despacio a propósito — 2.4s) */
			.hostpv-fx-clip-target{display:inline-block;clip-path:inset(0 100% 0 0);}
			.hostpv-fx-in .hostpv-fx-clip-target{animation:hostpvFxClipWipe 2.4s var(--hostpv-fx-ease) forwards;}
			@keyframes hostpvFxClipWipe{to{clip-path:inset(0 0 0 0);}}

			/* 5) Elastic bounce-in */
			.hostpv-fx-bounce-word{display:inline-block;vertical-align:top;}
			.hostpv-fx-bounce-word-inner{display:inline-block;transform:scale(.25);opacity:0;}
			.hostpv-fx-in .hostpv-fx-bounce-word-inner{animation:hostpvFxBounceIn .65s var(--hostpv-fx-ease-bounce) forwards;animation-delay:calc(var(--i) * 100ms + 40ms);}
			@keyframes hostpvFxBounceIn{to{transform:scale(1);opacity:1;}}

			/* 6) Fill sweep */
			a[data-hostpv-hover="fill-sweep"]{position:relative;display:inline-block;}
			a[data-hostpv-hover="fill-sweep"] .hostpv-fx-base{transition:opacity .3s;}
			a[data-hostpv-hover="fill-sweep"] .hostpv-fx-over{position:absolute;inset:0;color:var(--hostpv-fx-accent);clip-path:inset(0 100% 0 0);transition:clip-path .5s var(--hostpv-fx-ease);pointer-events:none;}
			a[data-hostpv-hover="fill-sweep"]:hover .hostpv-fx-over{clip-path:inset(0 0 0 0);}

			/* 7) Background sweep (invierte el color del texto) */
			a[data-hostpv-hover="bg-sweep"]{position:relative;z-index:0;display:inline-block;padding:2px 8px;transition:color .3s var(--hostpv-fx-ease) .05s;}
			a[data-hostpv-hover="bg-sweep"]::before{content:'';position:absolute;inset:0;background:var(--hostpv-fx-accent);border-radius:6px;transform:scaleX(0);transform-origin:left;transition:transform .4s var(--hostpv-fx-ease);z-index:-1;}
			a[data-hostpv-hover="bg-sweep"]:hover{color:#fff;}
			a[data-hostpv-hover="bg-sweep"]:hover::before{transform:scaleX(1);}

			/* 8) Fill sweep vertical (líquido) */
			a[data-hostpv-hover="fill-sweep-v"]{position:relative;display:inline-block;}
			a[data-hostpv-hover="fill-sweep-v"] .hostpv-fx-base{transition:opacity .3s;}
			a[data-hostpv-hover="fill-sweep-v"] .hostpv-fx-over{position:absolute;inset:0;color:var(--hostpv-fx-accent);clip-path:inset(100% 0 0 0);transition:clip-path .45s var(--hostpv-fx-ease);pointer-events:none;}
			a[data-hostpv-hover="fill-sweep-v"]:hover .hostpv-fx-over{clip-path:inset(0 0 0 0);}

			/* 9) Diagonal wipe */
			a[data-hostpv-hover="diag-sweep"]{position:relative;display:inline-block;}
			a[data-hostpv-hover="diag-sweep"] .hostpv-fx-base{transition:opacity .3s;}
			a[data-hostpv-hover="diag-sweep"] .hostpv-fx-over{position:absolute;inset:0;color:var(--hostpv-fx-accent);clip-path:polygon(-15% 0%, -5% 0%, -25% 100%, -35% 100%);transition:clip-path .5s var(--hostpv-fx-ease);pointer-events:none;}
			a[data-hostpv-hover="diag-sweep"]:hover .hostpv-fx-over{clip-path:polygon(-15% 0%, 130% 0%, 110% 100%, -35% 100%);}

			/* 10) Spotlight / color reveal */
			a[data-hostpv-hover="spotlight"]{position:relative;display:inline-block;}
			a[data-hostpv-hover="spotlight"] .hostpv-fx-base{opacity:.55;}
			a[data-hostpv-hover="spotlight"] .hostpv-fx-over{position:absolute;inset:0;color:var(--hostpv-fx-accent);-webkit-mask-image:radial-gradient(circle 90px at var(--hostpv-fx-mx,-999px) var(--hostpv-fx-my,-999px), #000 0%, transparent 70%);mask-image:radial-gradient(circle 90px at var(--hostpv-fx-mx,-999px) var(--hostpv-fx-my,-999px), #000 0%, transparent 70%);pointer-events:none;}

			/* 12) Subrayado a mano (trazo que se dibuja bajo una palabra).
			   El trazo arranca "sin dibujar" (dasharray 0) y solo se anima
			   cuando el JS marca el span al entrar en pantalla — así no se
			   ve aparecer de golpe en un título que todavía no ha llegado.
			   El color, el grosor y la altura los escribe Elementor desde
			   los controles de la pestaña Estilo del widget. */
			/* isolation:isolate convierte el subrayado en su propio contexto
			   de apilamiento. Así el <svg> con z-index:-1 se pinta POR DETRÁS
			   del texto pero por delante del fondo de la sección, que es lo
			   que se busca: sin esto el trazo se dibujaba encima de las
			   letras (se veía sobre todo en las de asta descendente), y un
			   z-index negativo suelto se habría ido detrás del fondo. */
			.hostpv-mark{position:relative;display:inline-block;white-space:nowrap;isolation:isolate;}
			.hostpv-mark > svg{position:absolute;left:0;top:90%;width:100%;height:100%;overflow:visible;pointer-events:none;z-index:-1;}
			/* Por defecto el trazo va del color de Énfasis del Kit, que es el
			   ámbar de la marca. El control "Color del trazo" de la pestaña
			   Estilo lo pisa cuando se toca, porque Elementor escribe su
			   regla con muchísima más especificidad. Si un día no hubiera
			   color de Énfasis, cae al color del propio título. */
			.hostpv-mark > svg path{fill:none;stroke:var(--e-global-color-accent, currentColor);stroke-width:38px;stroke-dasharray:0 1500;}
			/* Ciclo de 6 s: el trazo se pinta en unos 0,8 s (más rápido que
			   el de Jeg Kit, que tardaba 1,5 s dentro de un ciclo de 10 s),
			   se queda unos 4 s y se va. */
			.hostpv-mark-in > svg path{animation:hostpvMarkDraw 6s linear infinite;}
			@keyframes hostpvMarkDraw{
				0%{stroke-dasharray:0 1500;}
				14%{stroke-dasharray:1500 1500;}
				84%{opacity:1;}
				92%{stroke-dasharray:1500 1500;opacity:0;}
				100%{stroke-dasharray:0 1500;opacity:0;}
			}
			/* Con movimiento reducido el trazo se queda pintado y quieto. */
			@media (prefers-reduced-motion: reduce){
				.hostpv-mark > svg path{stroke-dasharray:1500 1500;}
				.hostpv-mark-in > svg path{animation:none;}
			}

			/* 11) Cursor-follow glow halo */
			a[data-hostpv-hover="glow"]{position:relative;display:inline-block;}
			.hostpv-fx-glow-orb{position:absolute;width:170px;height:170px;transform:translate(-50%,-50%);background:radial-gradient(circle, var(--hostpv-fx-accent) 0%, transparent 72%);filter:blur(8px);opacity:0;pointer-events:none;z-index:0;transition:opacity .25s var(--hostpv-fx-ease);}
			a[data-hostpv-hover="glow"] .hostpv-fx-glow-text{position:relative;z-index:1;}
		</style>
		<script id="hostpv-txtfx-js">
		(function(){

			// ── Split de palabras/letras (mismo arreglo del 12/08: el espacio
			// va como nodo de texto HERMANO fuera de los spans, nunca como
			// último carácter dentro de uno — si no, colapsa a ancho 0). ──
			// 12/08 (bug <br/>): recorre los childNodes ORIGINALES en vez de
			// usar textContent — así un <br/> dentro del título (p.ej.
			// "Especialistas <br/>en TPV") se conserva como salto de línea
			// real en vez de desaparecer/fusionar el texto de los dos lados.
			// Cualquier otro nodo (texto u otro elemento) se trata como texto
			// plano, igual que antes.
			function collectSegments(el){
				var segments = [];
				Array.prototype.forEach.call(el.childNodes, function(node){
					if (node.nodeType === 1 && node.nodeName === 'BR'){
						segments.push({ br: true });
					} else if (node.nodeType === 1 && node.classList && node.classList.contains('hostpv-mark')){
						// El subrayado a mano es un trozo del título con un
						// <svg> encima. No puede aplanarse a texto (se perdería
						// el trazo), así que viaja como segmento propio y son
						// splitWords()/splitChars() quienes trocean SU texto
						// por dentro y le devuelven el <svg> al final.
						segments.push({ mark: node });
					} else {
						var text = node.nodeType === 3 ? node.nodeValue : node.textContent;
						if (text) segments.push({ text: text });
					}
				});
				return segments;
			}

			function splitWords(el, wrapClass, innerClass){
				var segments = collectSegments(el);
				var frag = document.createDocumentFragment();
				var i = 0;
				segments.forEach(function(seg){
					if (seg.br){
						frag.appendChild(document.createElement('br'));
						return;
					}
					emitWords(seg.mark ? null : frag, seg, function(target, w){
						var mask = document.createElement('span');
						mask.className = wrapClass;
						var inner = document.createElement('span');
						inner.className = innerClass;
						inner.style.setProperty('--i', i);
						inner.textContent = w;
						mask.appendChild(inner);
						target.appendChild(mask);
						i++;
					}, frag);
				});
				el.innerHTML = '';
				el.appendChild(frag);
			}

			// Trocea el texto de un segmento en palabras y deja que quien
			// llama decida qué span envuelve cada una. Si el segmento es un
			// subrayado, las palabras se meten DENTRO de su propio span (que
			// se clona) y el <svg> del trazo se vuelve a colgar al final, para
			// que siga cubriendo justo ese trozo de título.
			function emitWords(_unused, seg, make, frag){
				var target = frag, text = seg.text, markEl = null, svg = null;
				if (seg.mark){
					markEl = seg.mark.cloneNode(true);
					svg = markEl.querySelector('svg');
					text = markEl.textContent;
					markEl.innerHTML = '';
					target = markEl;
				}
				var words = String(text).split(' ');
				words.forEach(function(w, idx){
					if (w !== '') make(target, w);
					if (idx < words.length - 1){
						target.appendChild(document.createTextNode(' '));
					}
				});
				if (markEl){
					if (svg) markEl.appendChild(svg);
					frag.appendChild(markEl);
				}
			}

			// 12/08 (bug ruptura a mitad de palabra): las letras de una MISMA
			// palabra se agrupan dentro de un span envoltorio
			// "hostpv-fx-char-word" (display:inline-block), igual que hace
			// splitWords() con sus palabras. Así la palabra entera es una
			// unidad atómica para el algoritmo de saltos de línea del
			// navegador y solo puede partirse por un espacio real — antes,
			// al ser cada letra un <span display:inline-block> suelto y
			// contiguo (sin espacio entre ellas), el navegador podía
			// insertar un salto de línea entre dos letras de la misma
			// palabra (reproducido y confirmado con test aislado).
			function splitChars(el){
				var segments = collectSegments(el);
				var frag = document.createDocumentFragment();
				var i = 0;
				segments.forEach(function(seg){
					if (seg.br){
						frag.appendChild(document.createElement('br'));
						return;
					}
					emitWords(null, seg, function(target, w){
						var wordWrap = document.createElement('span');
						wordWrap.className = 'hostpv-fx-char-word';
						Array.from(w).forEach(function(ch){
							var span = document.createElement('span');
							span.className = 'hostpv-fx-char';
							span.style.setProperty('--i', i);
							span.textContent = ch;
							wordWrap.appendChild(span);
							i++;
						});
						target.appendChild(wordWrap);
					}, frag);
				});
				el.innerHTML = '';
				el.appendChild(frag);
			}

			// ── Text scramble (decode) ──────────────────────────────────
			function TextScramble(el){
				this.el = el;
				this.chars = '!<>-_\\/[]{}—=+*^?#$%&';
				this.update = this.update.bind(this);
			}
			TextScramble.prototype.setText = function(newText){
				var oldText = this.el.textContent;
				var length = Math.max(oldText.length, newText.length);
				this.queue = [];
				for (var i = 0; i < length; i++){
					var from = oldText[i] || '';
					var to = newText[i] || '';
					var start = Math.floor(Math.random() * 30);
					var end = start + Math.floor(Math.random() * 30) + 10;
					this.queue.push({ from: from, to: to, start: start, end: end });
				}
				cancelAnimationFrame(this.frameRequest);
				this.frame = 0;
				this.update();
			};
			TextScramble.prototype.update = function(){
				var output = '', complete = 0;
				for (var i = 0, n = this.queue.length; i < n; i++){
					var q = this.queue[i];
					if (this.frame >= q.end){
						complete++;
						output += q.to;
					} else if (this.frame >= q.start){
						if (!q.char || Math.random() < 0.28){
							q.char = this.chars[Math.floor(Math.random() * this.chars.length)];
						}
						output += '<span class="hostpv-fx-scramble-glyph">' + q.char + '</span>';
					} else {
						output += q.from;
					}
				}
				this.el.innerHTML = output;
				if (complete === this.queue.length) return;
				this.frameRequest = requestAnimationFrame(this.update);
				this.frame++;
			};

			// ── LOAD: prepara cada título con data-hostpv-load ──────────
			var loadEls = Array.prototype.slice.call(document.querySelectorAll('[data-hostpv-load]'));
			loadEls.forEach(function(el){
				var fx = el.getAttribute('data-hostpv-load');
				// Si el título tiene enlace, el split se hace DENTRO del <a>
				// para no romper el enlace ni perder su estructura.
				var target = el.querySelector(':scope > a') || el;
				if (fx === 'mask'){
					splitWords(target, 'hostpv-fx-mask-word', 'hostpv-fx-mask-word-inner');
				} else if (fx === 'chars'){
					splitChars(target);
				} else if (fx === 'bounce'){
					splitWords(target, 'hostpv-fx-bounce-word', 'hostpv-fx-bounce-word-inner');
				} else if (fx === 'clip'){
					var clipSegments = collectSegments(target);
					var clipFrag = document.createDocumentFragment();
					clipSegments.forEach(function(seg){
						if (seg.br){
							clipFrag.appendChild(document.createElement('br'));
							return;
						}
						var span = document.createElement('span');
						span.className = 'hostpv-fx-clip-target';
						span.textContent = seg.text;
						clipFrag.appendChild(span);
					});
					target.innerHTML = '';
					target.appendChild(clipFrag);
				}
				else if (fx === 'scramble'){
					// 'scramble' no reestructura nada todavía (el efecto en
					// sí se dispara al entrar en pantalla), PERO si este
					// mismo título también tiene un hover activado sobre su
					// <a>, ese hover se prepara justo debajo (síncrono, antes
					// de que el usuario haga scroll) y reestructura los hijos
					// del <a> — metiendo el <br/> un nivel más adentro. Por
					// eso capturamos los segmentos (incluido el <br/>) AQUÍ,
					// mientras el <a> todavía tiene su contenido original,
					// para no perderlo cuando el scramble arranque de verdad
					// más tarde.
					el.hostpvScrambleSegments = collectSegments(target);
				}
			});

			if (loadEls.length && 'IntersectionObserver' in window){
				var loadIo = new IntersectionObserver(function(entries){
					entries.forEach(function(entry){
						if (!entry.isIntersecting) return;
						var el = entry.target;
						el.classList.add('hostpv-fx-in');
						var fx = el.getAttribute('data-hostpv-load');
						if (fx === 'scramble' && !el.dataset.hostpvDone){
							el.dataset.hostpvDone = '1';
							var target = el.querySelector(':scope > a') || el;
							// 12/08 (bug <br/>): si el título tiene un <br/>,
							// se trocea en segmentos por cada salto de línea y
							// cada segmento se "descifra" con su propio
							// TextScramble — el <br/> en sí se deja fijo entre
							// medias, nunca se mete en la cola de scramble
							// (si no, perdíamos el salto o salía un glifo raro
							// en su lugar). Se usan los segmentos capturados
							// al principio (antes de que un hover en el mismo
							// <a> reestructurase sus hijos); si por lo que
							// sea no se capturaron, se recalculan ahora como
							// respaldo.
							var scrambleSegments = el.hostpvScrambleSegments || collectSegments(target);
							var scrambleFrag = document.createDocumentFragment();
							var scramblers = [];
							scrambleSegments.forEach(function(seg){
								if (seg.br){
									scrambleFrag.appendChild(document.createElement('br'));
									return;
								}
								var span = document.createElement('span');
								scrambleFrag.appendChild(span);
								scramblers.push({ span: span, text: seg.text });
							});
							target.innerHTML = '';
							target.appendChild(scrambleFrag);
							scramblers.forEach(function(s){
								new TextScramble(s.span).setText(s.text);
							});
						}
						loadIo.unobserve(el);
					});
				}, { threshold: 0.35 });
				loadEls.forEach(function(el){ loadIo.observe(el); });
			}

			// 12/08: los efectos de hover que dependen
			// de un color ("fill", "bg-sweep", "glow"...) ya NO usan un azul
			// fijo propio del plugin — usan el MISMO color que ese enlace/
			// título ya tendría al pasar el ratón por encima de forma
			// nativa (el que venga del tema o de los ajustes de Elementor,
			// sea cual sea, sin que el plugin necesite saber de dónde sale).
			// Se consigue leyendo de verdad las hojas de estilo YA cargadas
			// en la página (recorriendo su CSSOM) en busca de una regla
			// ":hover" que aplicaría a este elemento en concreto — se
			// EXCLUYE a propósito nuestra propia hoja "hostpv-txtfx-css"
			// (si no, encontraría nuestras propias reglas de hover en vez
			// de las nativas). Si no se encuentra ninguna, se deja el valor
			// por defecto de --hostpv-fx-accent (el azul de siempre, como
			// último recurso, para no dejar el efecto sin color).
			function getNativeHoverColor(el){
				var ownStyleTag = document.getElementById('hostpv-txtfx-css');
				var found = null;
				function scanRuleList(rules){
					for (var i = 0; i < rules.length; i++){
						var rule = rules[i];
						// OJO: hay que comprobar selectorText ANTES que cssRules —
						// en algunos navegadores una CSSStyleRule normal TAMBIÉN
						// expone una propiedad .cssRules (lista vacía, valor
						// "truthy"), así que si se comprobara cssRules primero
						// CUALQUIER regla de estilo normal se confundiría con
						// una regla contenedora tipo @media y nunca llegaría a
						// leerse su selector/color (bug real encontrado con
						// Playwright al probar esto). Si tiene selectorText es
						// una regla de estilo normal — procesarla. Si no, y
						// tiene cssRules, es un contenedor (@media/@supports) —
						// bajar un nivel y seguir mirando dentro.
						if (typeof rule.selectorText !== 'string') {
							if (rule.cssRules) scanRuleList(rule.cssRules);
							continue;
						}
						if (rule.selectorText.indexOf(':hover') === -1) continue;
						if (!rule.style || !rule.style.color) continue;
						var selectors = rule.selectorText.split(',');
						for (var j = 0; j < selectors.length; j++){
							var sel = selectors[j].trim();
							if (sel.indexOf(':hover') === -1) continue;
							var baseSel = sel.replace(/:hover/g, '');
							if (!baseSel) continue;
							try {
								if (el.matches(baseSel)) found = rule.style.color;
							} catch (e) { /* selector no soportado por matches(), se ignora */ }
						}
					}
				}
				for (var s = 0; s < document.styleSheets.length; s++){
					var sheet = document.styleSheets[s];
					if (sheet.ownerNode === ownStyleTag) continue; // nunca nuestra propia hoja
					var rules;
					try { rules = sheet.cssRules || sheet.rules; } catch (e) { continue; } // hoja de otro origen, sin acceso
					if (!rules) continue;
					scanRuleList(rules);
				}
				return found;
			}

			// ── HOVER: prepara cada enlace con data-hostpv-hover ────────
			var hoverEls = Array.prototype.slice.call(document.querySelectorAll('a[data-hostpv-hover]'));
			hoverEls.forEach(function(a){
				var nativeColor = getNativeHoverColor(a);
				if (nativeColor) a.style.setProperty('--hostpv-fx-accent', nativeColor);
			});
			// 12/08 (bug <br/>): igual que en load, se rellenan los spans
			// recorriendo collectSegments() en vez de asignar textContent
			// entero, para que un <br/> dentro del enlace (p.ej. un H1 con
			// <br/> que además lleva vínculo) se preserve en ambas copias
			// (base/over, o el texto del glow) en vez de perderse.
			function fillWithSegments(container, segments){
				segments.forEach(function(seg){
					if (seg.br){
						container.appendChild(document.createElement('br'));
						return;
					}
					container.appendChild(document.createTextNode(seg.text));
				});
			}

			hoverEls.forEach(function(a){
				var fx = a.getAttribute('data-hostpv-hover');
				var segments = collectSegments(a);

				if (fx === 'fill-sweep' || fx === 'fill-sweep-v' || fx === 'diag-sweep' || fx === 'spotlight'){
					a.innerHTML = '';
					var base = document.createElement('span');
					base.className = 'hostpv-fx-base';
					fillWithSegments(base, segments);
					var over = document.createElement('span');
					over.className = 'hostpv-fx-over';
					over.setAttribute('aria-hidden', 'true');
					fillWithSegments(over, segments);
					a.appendChild(base);
					a.appendChild(over);

					if (fx === 'spotlight'){
						a.addEventListener('mousemove', function(e){
							var r = a.getBoundingClientRect();
							a.style.setProperty('--hostpv-fx-mx', (e.clientX - r.left) + 'px');
							a.style.setProperty('--hostpv-fx-my', (e.clientY - r.top) + 'px');
						});
						a.addEventListener('mouseleave', function(){
							a.style.setProperty('--hostpv-fx-mx', '-999px');
							a.style.setProperty('--hostpv-fx-my', '-999px');
						});
					}
				} else if (fx === 'glow'){
					a.innerHTML = '';
					var orb = document.createElement('span');
					orb.className = 'hostpv-fx-glow-orb';
					orb.setAttribute('aria-hidden', 'true');
					var txt = document.createElement('span');
					txt.className = 'hostpv-fx-glow-text';
					fillWithSegments(txt, segments);
					a.appendChild(orb);
					a.appendChild(txt);
					a.addEventListener('mousemove', function(e){
						var r = a.getBoundingClientRect();
						orb.style.left = (e.clientX - r.left) + 'px';
						orb.style.top = (e.clientY - r.top) + 'px';
						orb.style.opacity = '.55';
					});
					a.addEventListener('mouseleave', function(){
						orb.style.opacity = '0';
					});
				}
				// 'bg-sweep' no necesita JS: es puro CSS :hover (::before).
			});

			// ── SUBRAYADO A MANO ────────────────────────────────────────
			// Va al final a propósito: splitWords()/splitChars() han podido
			// CLONAR el span del subrayado al trocear el título, así que los
			// que hay que observar son los que están en el DOM AHORA, no los
			// que había cuando arrancó el script.
			var markEls = Array.prototype.slice.call(document.querySelectorAll('.hostpv-mark'));
			if (markEls.length && 'IntersectionObserver' in window){
				var markIo = new IntersectionObserver(function(entries){
					entries.forEach(function(entry){
						if (!entry.isIntersecting) return;
						var el = entry.target;
						// Si ese título además tiene animación de entrada, el
						// trazo espera a que las letras terminen de colocarse;
						// si no, se dibuja casi al momento.
						var espera = el.closest('[data-hostpv-load]') ? 700 : 120;
						setTimeout(function(){ el.classList.add('hostpv-mark-in'); }, espera);
						markIo.unobserve(el);
					});
				}, { threshold: 0.5 });
				markEls.forEach(function(el){ markIo.observe(el); });
			} else {
				// Navegador sin IntersectionObserver: se dibuja y ya está.
				markEls.forEach(function(el){ el.classList.add('hostpv-mark-in'); });
			}

		})();
		</script>
		<?php
	}
}

new HosTPV_Text_Animations();
