<?php
/**
 * HosTPV — Menú móvil (módulo independiente)
 * ─────────────────────────────────────────────────────
 * Archivo APARTE de hostpv.php, mismo criterio que los demás módulos: se
 * autorregistra por completo y hostpv.php solo necesita un require_once.
 *
 * QUÉ HACE:
 *  Un widget de Elementor con dos piezas: un botón de tres rayas y un panel
 *  de menú A PANTALLA COMPLETA que se abre al pulsarlo. Pensado para la
 *  cabecera, oculto en escritorio con los ajustes responsive de siempre de
 *  Elementor (Avanzado → Responsive), de modo que en pantallas grandes
 *  sigue mandando el menú horizontal y en móvil manda este.
 *
 *  Sale de un menú de WordPress (Apariencia → Menús), así que comparte los
 *  mismos enlaces que el menú de escritorio: se cambia en un sitio y cambia
 *  en los dos.
 *
 * DECISIONES:
 *  - Los submenús (Servicios, Productos) NO se pliegan: en una pantalla
 *    completa caben y esconderlos tras otro toque solo añade trabajo. Van
 *    debajo de su sección, más pequeños y con un poco de sangría.
 *  - El panel se abre y se cierra con Escape, con el botón de cerrar, al
 *    tocar un enlace y al tocar fuera de la lista. Mientras está abierto se
 *    bloquea el desplazamiento de la página de detrás.
 *  - Nada de librerías: son unas cuantas líneas de JavaScript.
 *  - Con `prefers-reduced-motion` el panel aparece sin animación de
 *    entrada, pero sigue funcionando igual.
 *
 * POR QUÉ EL CSS/JS SE IMPRIME SIEMPRE EN LA PORTADA:
 *  Este widget vive en la plantilla de cabecera, o sea que está en TODAS las
 *  páginas — condicionarlo no ahorraría nada. Y, sobre todo, condicionarlo
 *  al render sería un error ya conocido en este plugin: Elementor guarda el
 *  HTML ya pintado de cada widget en su caché de elementos y entonces los
 *  hooks de render no se ejecutan, así que una bandera puesta ahí se queda
 *  en false y la página sale con el HTML pero sin su CSS (ver el comentario
 *  largo en hostpv-buttons.php). Son unos pocos KB y el JS no hace nada si
 *  no encuentra el botón en la página.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HosTPV_Mobile_Menu {

	public function __construct() {
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
		add_action( 'wp_footer', [ $this, 'print_assets' ] );
	}

	public function register_widget( $widgets_manager ) {
		hostpv_register_mobile_menu_widget( $widgets_manager );
	}

	public function print_assets() {
		if ( is_admin() ) return;
		?>
		<style id="hostpv-mm-css">
			.hostpv-mm{
				--hostpv-mm-burger:#FFFFFF;
				--hostpv-mm-linea:2px;
				--hostpv-mm-ancho:28px;
				--hostpv-mm-fondo:#070707;
				--hostpv-mm-enlace:#FFFFFF;
				--hostpv-mm-realce:var(--e-global-color-accent,#FFC94A);
				/* En bloque, no en linea: como elemento en linea la caja se queda
				   3 px mas alta que el boton (el hueco del renglon) y el burger baja
				   esos 3 px respecto al logo cuando el contenedor los centra. */
				display:flex;
			}

			/* ── El botón de tres rayas ───────────────────────────── */
			.hostpv-mm-burger{
				appearance:none;background:none;border:0;padding:8px;margin:0;cursor:pointer;
				display:flex;flex-direction:column;justify-content:center;gap:6px;
				width:calc(var(--hostpv-mm-ancho) + 16px);height:calc(var(--hostpv-mm-ancho) + 16px);
				line-height:0;color:inherit;
			}
			.hostpv-mm-burger span{
				display:block;width:var(--hostpv-mm-ancho);height:var(--hostpv-mm-linea);
				background:var(--hostpv-mm-burger);border-radius:99px;
				transition:transform .3s cubic-bezier(.22,.61,.36,1), opacity .2s ease;
			}
			/* El tema pinta de rosa el fondo de cualquier <button> al pasarle el raton
			   o al enfocarlo ([type="button"]:hover). Estos dos no son botones de
			   accion, son controles, asi que se les quita en todos los estados y el
			   aviso visual se da cambiando el color de las rayas. */
			.hostpv-mm-burger,.hostpv-mm-burger:hover,.hostpv-mm-burger:focus,.hostpv-mm-burger:active,
			.hostpv-mm-cerrar,.hostpv-mm-cerrar:hover,.hostpv-mm-cerrar:focus,.hostpv-mm-cerrar:active{
				background:none;box-shadow:none;color:inherit;
				-webkit-tap-highlight-color:transparent;
			}
			.hostpv-mm-burger:hover span,
			.hostpv-mm-cerrar:hover::before,.hostpv-mm-cerrar:hover::after{
				background:var(--hostpv-mm-realce);
			}
			.hostpv-mm-burger:focus-visible{outline:2px solid var(--hostpv-mm-realce);outline-offset:3px;border-radius:6px;}
			/* Abierto: las tres rayas se convierten en una equis */
			.hostpv-mm.abierto .hostpv-mm-burger span:nth-child(1){transform:translateY(calc(var(--hostpv-mm-linea) + 6px)) rotate(45deg);}
			.hostpv-mm.abierto .hostpv-mm-burger span:nth-child(2){opacity:0;}
			.hostpv-mm.abierto .hostpv-mm-burger span:nth-child(3){transform:translateY(calc((var(--hostpv-mm-linea) + 6px) * -1)) rotate(-45deg);}

			/* ── El panel a pantalla completa ─────────────────────── */
			.hostpv-mm-panel{
				position:fixed;inset:0;z-index:99990;
				background:var(--hostpv-mm-fondo);
				display:flex;flex-direction:column;
				padding:24px 24px calc(24px + env(safe-area-inset-bottom));
				overflow-y:auto;overscroll-behavior:contain;
				opacity:0;visibility:hidden;transform:translateY(-12px);
				transition:opacity .28s ease, transform .28s cubic-bezier(.22,.61,.36,1), visibility .28s;
			}
			.hostpv-mm.abierto .hostpv-mm-panel{opacity:1;visibility:visible;transform:none;}
			.hostpv-mm-panel[hidden]{display:none;}

			.hostpv-mm-cerrar{
				appearance:none;background:none;border:0;padding:10px;margin:0 0 0 auto;cursor:pointer;
				width:46px;height:46px;position:relative;flex:0 0 auto;color:inherit;
			}
			.hostpv-mm-cerrar::before,.hostpv-mm-cerrar::after{
				content:"";position:absolute;left:11px;right:11px;top:50%;height:var(--hostpv-mm-linea);
				background:var(--hostpv-mm-burger);border-radius:99px;
			}
			.hostpv-mm-cerrar::before{transform:rotate(45deg);}
			.hostpv-mm-cerrar::after{transform:rotate(-45deg);}
			.hostpv-mm-cerrar:focus-visible{outline:2px solid var(--hostpv-mm-realce);outline-offset:2px;border-radius:8px;}

			/* margin-block:auto en vez de justify-content:center — centra
			   cuando sobra sitio, pero en una pantalla baja deja que la
			   lista crezca y se pueda desplazar en vez de cortarse por
			   arriba, que es lo que pasa al centrar un flex que desborda. */
			.hostpv-mm-nav{
				display:flex;flex-direction:column;
				margin-block:auto;padding:8px 0 32px;
			}
			.hostpv-mm-list,.hostpv-mm-list ul{list-style:none;margin:0;padding:0;}
			.hostpv-mm-list{display:flex;flex-direction:column;gap:6px;text-align:center;}
			.hostpv-mm-list > li{
				opacity:0;transform:translateY(10px);
			}
			.hostpv-mm.abierto .hostpv-mm-list > li{
				animation:hostpvMmEntra .42s cubic-bezier(.22,.61,.36,1) forwards;
				animation-delay:calc(var(--i, 0) * 45ms + 90ms);
			}
			@keyframes hostpvMmEntra{to{opacity:1;transform:none;}}

			.hostpv-mm-list a{
				display:block;padding:10px 8px;text-decoration:none;
				color:var(--hostpv-mm-enlace);
				font-size:30px;font-weight:600;line-height:1.2;letter-spacing:-.02em;
				transition:color .2s ease;
			}
			.hostpv-mm-list a:hover,
			.hostpv-mm-list .current-menu-item > a,
			.hostpv-mm-list .current_page_item > a{color:var(--hostpv-mm-realce);}

			/* Submenú: debajo de su sección, más pequeño, sin plegar */
			.hostpv-mm-list .sub-menu{
				margin:2px 0 10px;display:flex;flex-direction:column;gap:2px;
			}
			.hostpv-mm-list .sub-menu a{
				font-size:17px;font-weight:400;padding:6px 8px;
				color:var(--hostpv-mm-enlace);opacity:.72;
			}
			.hostpv-mm-list .sub-menu a:hover{opacity:1;color:var(--hostpv-mm-realce);}

			/* La página de detrás no se mueve mientras el panel está abierto */
			body.hostpv-mm-bloqueado{overflow:hidden;}

			@media (prefers-reduced-motion: reduce){
				.hostpv-mm-panel{transition:none;transform:none;}
				.hostpv-mm-burger span{transition:none;}
				.hostpv-mm.abierto .hostpv-mm-list > li{animation:none;opacity:1;transform:none;}
			}
		</style>
		<script id="hostpv-mm-js">
		(function(){
			function montar(caja){
				if (caja.dataset.hostpvMm === '1') return;
				caja.dataset.hostpvMm = '1';

				var boton  = caja.querySelector('.hostpv-mm-burger');
				var panel  = caja.querySelector('.hostpv-mm-panel');
				var cerrar = caja.querySelector('.hostpv-mm-cerrar');
				if (!boton || !panel) return;

				// El retardo escalonado de cada entrada del menú sale de su
				// posición, para no escribir 20 reglas en el CSS.
				var items = panel.querySelectorAll('.hostpv-mm-list > li');
				Array.prototype.forEach.call(items, function(li, i){ li.style.setProperty('--i', i); });

				var abierto = false;

				function abrir(){
					if (abierto) return;
					abierto = true;
					panel.hidden = false;
					// un frame para que la transición arranque desde el estado cerrado
					requestAnimationFrame(function(){ caja.classList.add('abierto'); });
					boton.setAttribute('aria-expanded', 'true');
					document.body.classList.add('hostpv-mm-bloqueado');
					if (cerrar) cerrar.focus();
				}

				function cerrarPanel(devolverFoco){
					if (!abierto) return;
					abierto = false;
					caja.classList.remove('abierto');
					boton.setAttribute('aria-expanded', 'false');
					document.body.classList.remove('hostpv-mm-bloqueado');
					if (devolverFoco) boton.focus();
					// se esconde del todo cuando termina la transición, para
					// que no quede alcanzable con el tabulador
					window.setTimeout(function(){ if (!abierto) panel.hidden = true; }, 300);
				}

				boton.addEventListener('click', function(){ abierto ? cerrarPanel(true) : abrir(); });
				if (cerrar) cerrar.addEventListener('click', function(){ cerrarPanel(true); });

				panel.addEventListener('click', function(e){
					var enlace = e.target.closest('a');
					if (enlace) {
						// "Servicios" y "Productos" son solo encabezados de
						// sección (enlace a "#"): no llevan a ninguna parte,
						// así que tampoco cierran el menú.
						var destino = enlace.getAttribute('href') || '';
						if (destino === '' || destino === '#') { e.preventDefault(); return; }
						cerrarPanel(false);
						return;
					}
					// tocar fuera de la lista también cierra
					if (!e.target.closest('.hostpv-mm-list') && !e.target.closest('.hostpv-mm-cerrar')) cerrarPanel(true);
				});

				document.addEventListener('keydown', function(e){
					if (e.key === 'Escape' && abierto) cerrarPanel(true);
				});

				// Si se agranda la ventana hasta escritorio con el panel
				// abierto, se cierra solo: el menú de escritorio vuelve y
				// quedarían los dos a la vez.
				window.addEventListener('resize', function(){
					if (abierto && !boton.offsetParent) cerrarPanel(false);
				});
			}

			function montarTodos(ambito){
				var cajas = (ambito || document).querySelectorAll('.hostpv-mm');
				Array.prototype.forEach.call(cajas, montar);
			}

			if (window.jQuery) {
				jQuery(window).on('elementor/frontend/init', function(){
					elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope){
						var el = $scope && $scope[0];
						if (el) montarTodos(el);
					});
				});
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function(){ montarTodos(document); });
			} else {
				montarTodos(document);
			}
		})();
		</script>
		<?php
	}
}

new HosTPV_Mobile_Menu();

/** Registra el widget "HosTPV — Menú móvil". Función SUELTA por el mismo
 *  motivo que el widget de Cajas 3D: PHP no permite declarar una clase
 *  dentro del cuerpo de otra, y así la clase solo se declara cuando
 *  Elementor dispara el hook. */
function hostpv_register_mobile_menu_widget( $widgets_manager ) {
	if ( ! class_exists( 'HosTPV_Widget_Mobile_Menu' ) ) {

		class HosTPV_Widget_Mobile_Menu extends \Elementor\Widget_Base {

			public function get_name()       { return 'hostpv_mobile_menu'; }
			public function get_title()      { return __( 'HosTPV — Menú móvil', 'hostpv' ); }
			public function get_icon()       { return 'eicon-menu-bar'; }
			public function get_categories() { return [ 'hostpv' ]; }
			public function get_keywords()   { return [ 'hostpv', 'menu', 'movil', 'hamburguesa', 'burger' ]; }

			/** Los menús de WordPress disponibles, para el desplegable. */
			private function menus_disponibles() {
				$out = [];
				foreach ( wp_get_nav_menus() as $m ) $out[ $m->term_id ] = $m->name;
				return $out;
			}

			protected function register_controls() {

				$this->start_controls_section( 'seccion_contenido', [
					'label' => __( 'Menú móvil', 'hostpv' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				] );

				$menus = $this->menus_disponibles();

				if ( empty( $menus ) ) {
					$this->add_control( 'sin_menus', [
						'type'            => \Elementor\Controls_Manager::RAW_HTML,
						'raw'             => __( 'Todavía no hay ningún menú creado. Se crean en Apariencia → Menús.', 'hostpv' ),
						'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
					] );
				} else {
					$this->add_control( 'menu', [
						'label'       => __( 'Menú', 'hostpv' ),
						'type'        => \Elementor\Controls_Manager::SELECT,
						'options'     => $menus,
						'default'     => array_key_first( $menus ),
						'description' => __( 'Es el mismo menú de Apariencia → Menús que usa la cabecera, así que se mantienen solos en sintonía.', 'hostpv' ),
					] );
				}

				$this->add_control( 'etiqueta_abrir', [
					'label'   => __( 'Texto para lectores de pantalla (abrir)', 'hostpv' ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => __( 'Abrir el menú', 'hostpv' ),
				] );

				$this->add_control( 'etiqueta_cerrar', [
					'label'   => __( 'Texto para lectores de pantalla (cerrar)', 'hostpv' ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => __( 'Cerrar el menú', 'hostpv' ),
				] );

				$this->add_control( 'aviso_responsive', [
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => __( 'Para que solo salga en móvil, ocúltalo en escritorio desde Avanzado → Responsive de este mismo widget.', 'hostpv' ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
				] );

				$this->end_controls_section();

				// ── Estilo: el botón ──
				$this->start_controls_section( 'seccion_estilo_boton', [
					'label' => __( 'Botón de rayas', 'hostpv' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				] );

				$this->add_control( 'color_rayas', [
					'label'     => __( 'Color de las rayas', 'hostpv' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#FFFFFF',
					'selectors' => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-burger: {{VALUE}};' ],
				] );

				$this->add_responsive_control( 'ancho_rayas', [
					'label'      => __( 'Ancho de las rayas', 'hostpv' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => [ 'px' ],
					'range'      => [ 'px' => [ 'min' => 16, 'max' => 48 ] ],
					'default'    => [ 'unit' => 'px', 'size' => 28 ],
					'selectors'  => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-ancho: {{SIZE}}{{UNIT}};' ],
				] );

				$this->add_control( 'grosor_rayas', [
					'label'      => __( 'Grosor de las rayas', 'hostpv' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => [ 'px' ],
					'range'      => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
					'default'    => [ 'unit' => 'px', 'size' => 2 ],
					'selectors'  => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-linea: {{SIZE}}{{UNIT}};' ],
				] );

				$this->end_controls_section();

				// ── Estilo: el panel ──
				$this->start_controls_section( 'seccion_estilo_panel', [
					'label' => __( 'Panel a pantalla completa', 'hostpv' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				] );

				$this->add_control( 'color_fondo', [
					'label'     => __( 'Fondo del panel', 'hostpv' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_SECONDARY ],
					'selectors' => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-fondo: {{VALUE}};' ],
				] );

				$this->add_control( 'color_enlace', [
					'label'     => __( 'Color de los enlaces', 'hostpv' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#FFFFFF',
					'selectors' => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-enlace: {{VALUE}};' ],
				] );

				$this->add_control( 'color_realce', [
					'label'     => __( 'Color al pasar por encima y de la página actual', 'hostpv' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ],
					'selectors' => [ '{{WRAPPER}} .hostpv-mm' => '--hostpv-mm-realce: {{VALUE}};' ],
				] );

				$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
					'name'     => 'tipografia_enlace',
					'label'    => __( 'Tipografía de los enlaces', 'hostpv' ),
					'selector' => '{{WRAPPER}} .hostpv-mm-list > li > a',
				] );

				$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
					'name'     => 'tipografia_subenlace',
					'label'    => __( 'Tipografía de los subenlaces', 'hostpv' ),
					'selector' => '{{WRAPPER}} .hostpv-mm-list .sub-menu a',
				] );

				$this->end_controls_section();
			}

			protected function render() {
				$s      = $this->get_settings_for_display();
				$menu   = isset( $s['menu'] ) ? $s['menu'] : '';
				$abrir  = isset( $s['etiqueta_abrir'] )  && $s['etiqueta_abrir']  !== '' ? $s['etiqueta_abrir']  : __( 'Abrir el menú', 'hostpv' );
				$cerrar = isset( $s['etiqueta_cerrar'] ) && $s['etiqueta_cerrar'] !== '' ? $s['etiqueta_cerrar'] : __( 'Cerrar el menú', 'hostpv' );
				$id     = 'hostpv-mm-panel-' . $this->get_id();

				if ( ! $menu ) {
					if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
						echo '<p>' . esc_html__( 'Elige un menú en el panel de la izquierda.', 'hostpv' ) . '</p>';
					}
					return;
				}
				?>
				<div class="hostpv-mm">
					<button type="button" class="hostpv-mm-burger" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( $abrir ); ?>">
						<span></span><span></span><span></span>
					</button>
					<div class="hostpv-mm-panel" id="<?php echo esc_attr( $id ); ?>" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $abrir ); ?>" hidden>
						<button type="button" class="hostpv-mm-cerrar" aria-label="<?php echo esc_attr( $cerrar ); ?>"></button>
						<nav class="hostpv-mm-nav">
							<?php
							wp_nav_menu( [
								'menu'        => (int) $menu,
								'container'   => false,
								'menu_class'  => 'hostpv-mm-list',
								'depth'       => 2,
								'fallback_cb' => '__return_empty_string',
							] );
							?>
						</nav>
					</div>
				</div>
				<?php
			}
		}
	}

	$widgets_manager->register( new \HosTPV_Widget_Mobile_Menu() );
}
