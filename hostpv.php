<?php
/**
 * Plugin Name: HosTPV
 * Plugin URI:  https://hostpv.com
 * Description: Animaciones de marca de Hostpv (logo animado como shortcode; widget de Elementor "Cajas 3D" para cabeceras, categoría HosTPV en el panel de widgets; plantilla HTML de "Caja y Halo" en los ajustes, solo para copiar/pegar; cursor personalizado con inversión de color e imán en enlaces; animaciones de texto y subrayado a mano sobre el título nativo; animación de relleno sobre el botón nativo), con panel de ajustes propio. Plugin de Caracool, sin dependencias externas.
 * Version:     0.5.0
 * Author:      Caracool
 * Author URI:  https://caracool.net
 * Text Domain: hostpv
 */

// ── Bloquear acceso directo al archivo ────────────────────────
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HOSTPV_VERSION',  '0.5.0' );
define( 'HOSTPV_PATH',     plugin_dir_path( __FILE__ ) );
define( 'HOSTPV_URL',      plugin_dir_url( __FILE__ ) );
define( 'HOSTPV_SLUG',     'hostpv' );
// Ruta del plugin tal y como la nombra WordPress (carpeta/archivo). Se define
// aqui, en el archivo principal, porque en los modulos plugin_basename()
// devolveria el modulo y no el plugin.
define( 'HOSTPV_BASENAME', plugin_basename( __FILE__ ) );

// 16/09: menu compartido de la casa. Archivo comun a todos los plugins de
// Caracool (fuente de verdad: github.com/caracoolnet/wp-caracool-shared). El
// primero que carga crea el menu padre "Caracool" en la prioridad 9 y los
// demas se cuelgan de el en la 10, sin que ninguno dependa de los otros.
require_once HOSTPV_PATH . 'inc/caracool-menu.php';

// Este plugin se presenta en la portada del menu compartido.
add_filter( 'caracool_plugins', function ( $lista ) {
	$lista[] = [
		'nombre'  => 'HosTPV',
		'pagina'  => HOSTPV_SLUG,
		'version' => HOSTPV_VERSION,
		'resumen' => 'Animaciones de marca, widgets de Elementor y cursor propio para hostpv.com.',
	];
	return $lista;
} );

// 12/08: "Animaciones de texto" (títulos H1-H6 vía widget Elementor +
// regla global de enlaces en párrafos) vive en un archivo APARTE a
// propósito (para poder ampliarlo, desactivarlo o moverlo a
// otro plugin sin tocar este archivo). Se autorregistra por completo (sus
// propios hooks de Elementor, su propio submenú de ajustes, su propio
// wp_footer condicional) — no necesita que HosTPV::__construct() sepa nada
// de él, así que basta con cargar el archivo aquí. No requiere Elementor
// activo para incluirse sin errores: sus hooks son de Elementor y
// simplemente no se disparan si Elementor no está.
if ( file_exists( HOSTPV_PATH . 'hostpv-text-animations.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-text-animations.php';
}

// 14/08: "Cursor personalizado" (círculo con inversión de color + imán en
// enlaces, prototipado antes en un HTML suelto hasta dar con los valores
// buenos) — mismo criterio que el módulo de arriba: archivo APARTE,
// autorregistrado por completo (su propia pestaña de ajustes, su propio
// wp_footer condicional), hostpv.php no necesita saber nada de él.
if ( file_exists( HOSTPV_PATH . 'hostpv-custom-cursor.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-custom-cursor.php';
}

// 15/09: "Animación de botones" (relleno que entra al pasar el ratón sobre
// el botón NATIVO de Elementor) — mismo criterio que los dos módulos de
// arriba: archivo APARTE, autorregistrado por completo (su pestaña de
// ajustes, sus hooks de Elementor, su wp_footer condicional), hostpv.php no
// necesita saber nada de él.
if ( file_exists( HOSTPV_PATH . 'hostpv-buttons.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-buttons.php';
}

// 15/09: "Menú móvil" (botón de tres rayas + panel de menú a pantalla
// completa, como widget de Elementor para la cabecera) — mismo criterio:
// archivo APARTE, autorregistrado, hostpv.php no sabe nada de él.
if ( file_exists( HOSTPV_PATH . 'hostpv-mobile-menu.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-mobile-menu.php';
}

// 16/09: "Desbordes de maquetación" — recorta a los lados el SVG de la caja
// inclinada de las cabeceras, que en móvil sobresalía y dejaba la página
// arrastrable en horizontal. Mismo criterio: archivo APARTE, autorregistrado.
if ( file_exists( HOSTPV_PATH . 'hostpv-desbordes.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-desbordes.php';
}

// 16/09: avisos de version nueva desde las releases del repo publico
// caracoolnet/wp-hostpv. Mismo criterio que los demas modulos: archivo
// aparte y autorregistrado.
if ( file_exists( HOSTPV_PATH . 'hostpv-updater.php' ) ) {
	require_once HOSTPV_PATH . 'hostpv-updater.php';
}

// ─────────────────────────────────────────────────────────────
// CLASE PRINCIPAL
// ─────────────────────────────────────────────────────────────
class HosTPV {

    const OPTION_KEY = 'hostpv_settings';

    /** Página y widget de referencia de los que se lee en directo el HTML de
     *  "Caja y Halo" para la pestaña de ajustes (ver get_caja_halo_reference_html()).
     *  Contacto, widget "HTML" `219bf861` — caja negra que cae + halo que se dibuja. */
    const CAJA_HALO_REF_POST_ID   = 2419;
    const CAJA_HALO_REF_WIDGET_ID = '219bf861';

    /** Se pone a true la primera vez que se imprime el <style>/<script> de cada
     *  módulo, para no duplicarlo si el shortcode se usa varias veces en la página. */
    private static $logo_assets_printed  = false;

    /**
     * Logotipo de Caracool, incrustado como SVG inline (misma pieza que usa
     * Caracool OneStep) para que el panel de ajustes lleve la marca de la agencia.
     */
    private static function caracool_logo_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 325.72 100.01" style="width:130px;height:auto;display:block;fill:#1a1a1a;" aria-label="Caracool" role="img"><path d="M39.23,9.44c0,4.65-.8,7.71-2.13,9.98-1.6-1.2-3.99-2.26-6.92-2.26-7.58,0-14.5,6.92-14.5,35.91,0,23.14,2.93,30.06,10.37,30.06,4.12,0,7.85-.67,10.37-2,1.06,2.26,2,5.59,2,10.37,0,3.86-6.12,8.25-14.1,8.25-15.16,0-24.34-3.99-24.34-45.49C0,10.64,14.63,2.66,26.6,2.66c11.31,0,12.64,3.86,12.64,6.78"/><path d="M41.9,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.89-.66-18.89-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M65.44,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.67,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82"/><path d="M88.31,35.24c6.12-4.26,11.04-5.98,18.49-5.98s8.91,1.33,8.91,5.98c0,2.39-.27,5.98-1.46,9.31-1.86-.93-3.59-1.06-4.92-1.06-1.6,0-4.12.8-5.72,3.06l-.13,48.54q0,2.66-15.16,2.66v-62.51Z"/><path d="M119.96,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.88-.66-18.88-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M143.51,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.66,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82"/><path d="M202.69,36.97c-2.53-1.86-4.65-3.59-9.84-3.59-9.44,0-20.22,3.99-20.22,33.12,0,26.6,6.92,29.39,17.82,29.39,5.19,0,9.44-2.39,12.5-4.92.53.93.8,2.13.8,3.06,0,1.73-6.38,5.99-13.7,5.99-12.9,0-21.81-2.53-21.81-34.05,0-33.12,13.43-36.71,24.21-36.71,5.59,0,11.17,2.79,11.17,4.26,0,1.06-.27,2.53-.93,3.46"/><path d="M234.21,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M233.01,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06"/><path d="M286.88,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M285.68,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06"/><path d="M325.71,96.29c0,1.46.27,1.46-4.39,1.46V2.39c0-2.39,1.07-2.39,4.39-2.39v96.29Z"/></svg>';
    }

    /** Trazado de la "caja" (badge) del logotipo de Hostpv — misma forma que se usa
     *  como marco en el logo animado y como pieza base de las cajas 3D en cascada.
     *  Público (no privado) para que el widget "Cajas 3D", que vive en su propia
     *  función/clase suelta (ver hostpv_register_cajas3d_widget()), pueda usarlo. */
    public static function hostpv_caja_d() {
        return 'M391.01,2.83c7.81,0,15.24,3.39,20.39,9.31,5.14,5.91,7.46,13.75,6.37,21.51l-34.36,244.82c-2.29,16.29-16.39,28.57-32.8,28.57-1.73,0-3.49-.14-5.22-.41L20.99,255.37c-6.06-.96-11.43-4.46-14.74-9.62-3.31-5.16-4.27-11.5-2.62-17.41L56.74,38.31c3.67-13.15,15.31-22.36,28.95-22.92L389.86,2.85c.39-.02.77-.02,1.15-.02M391.01,0c-.42,0-.84,0-1.26.03L85.57,12.57c-14.82.61-27.56,10.7-31.55,24.99L.91,227.58c-3.92,14.02,5.26,28.31,19.64,30.58l324.41,51.24c1.9.3,3.8.45,5.66.45,17.63,0,33.07-12.98,35.6-31l34.36-244.82c2.53-18.05-11.52-34.03-29.56-34.03';
    }

    // ── Constructor ───────────────────────────────────────────
    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_notices',          [ $this, 'admin_notices' ] );
        add_action( 'admin_post_hostpv_save', [ $this, 'save_settings' ] );

        add_shortcode( 'hostpv_logo_animado', [ $this, 'shortcode_logo_animado' ] );

        // v0.3.1 — las "cajas 3D en cascada" DEJAN de existir como opción de
        // fondo de Container (con su hook de antes de renderizar + el hook
        // especial de vista previa del editor + el <style>/<script>
        // compartido en el footer con bandera global). Ahora son un widget
        // de Elementor normal: se arrastra a un Container y ya está, sin
        // registrar controles en TODOS los Containers del sitio ni depender
        // de hooks de bajo nivel del frontend de Elementor. Ver
        // hostpv_register_cajas3d_widget() al final del archivo.

        // 12/08: el widget de Elementor "HosTPV — Caja y Halo" (que existió
        // en v0.3.0) se eliminó — la composición caja+halo
        // de cabecera se sigue usando, pero a mano con un widget HTML de
        // Elementor (ver la pestaña "Caja y Halo (plantilla HTML)" en los
        // ajustes, que da el código listo para copiar/pegar), no como widget
        // propio del plugin.
        add_action( 'elementor/elements/categories_registered', [ $this, 'elementor_register_category' ] );
        add_action( 'elementor/widgets/register',                [ $this, 'elementor_register_widgets' ] );

        // 12/08: enlace "Administrar" en las acciones de la fila del plugin
        // en Plugins → Instalados (junto a "Desactivar"/"Borrar"), directo a
        // la página de ajustes — así no hay que buscarla en el menú lateral.
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ] );
    }

    // ── Ajustes por defecto ───────────────────────────────────
    public function get_settings() {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            // Logo animado
            'logo_enabled' => true,
            'logo_color'   => '#ffffff',
            // Caja y Halo — cuadro editable/guardable (14/08, ver UNDÉCIMA/
            // DUODÉCIMA TANDA). Vacío = "todavía no se ha guardado nada a
            // propósito", en ese caso la pantalla de ajustes muestra de
            // relleno la lectura en directo (o el respaldo) sin persistirla.
            'caja_halo_html' => '',
        ] );
    }

    // ── Guardar ajustes ───────────────────────────────────────
    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
        check_admin_referer( 'hostpv_save' );

        update_option( self::OPTION_KEY, [
            'logo_enabled' => ! empty( $_POST['logo_enabled'] ),
            'logo_color'   => sanitize_hex_color( $_POST['logo_color'] ?? '' ) ?: '#ffffff',
            // Sin wp_kses_post(): el cuadro necesita admitir <style>/SVG tal
            // cual (igual que el widget "HTML" de Elementor del que se lee),
            // wp_kses_post() recortaría atributos/etiquetas necesarios. Es
            // seguro porque esta pantalla ya exige manage_options + nonce —
            // mismo nivel de confianza que el propio widget HTML de Elementor.
            'caja_halo_html' => isset( $_POST['caja_halo_html'] ) ? wp_unslash( $_POST['caja_halo_html'] ) : '',
        ] );

        wp_safe_redirect( admin_url( 'admin.php?page=' . HOSTPV_SLUG . '&saved=1' ) );
        exit;
    }

    // ── Avisos admin ──────────────────────────────────────────
    public function admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== self::hook() ) return;

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Configuración guardada.</p></div>';
        }
    }

    // ── Menú admin ────────────────────────────────────────────
    /** Cuelga los ajustes del menú compartido "Caracool" en vez de tener
     *  entrada propia de primer nivel. El slug de la página NO cambia, así que
     *  admin.php?page=hostpv sigue funcionando y ningún enlace guardado se
     *  rompe. Lo que sí cambia es el identificador de pantalla, que pasa de
     *  toplevel_page_hostpv a caracool_page_hostpv (ver self::hook()). */
    public function add_menu() {
        add_submenu_page(
            CARACOOL_MENU_SLUG,
            'HosTPV',
            'HosTPV',
            'manage_options',
            HOSTPV_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    /** Identificador de la pantalla de ajustes: slug del padre + _page_ +
     *  slug de la página, que es lo que compone WordPress en
     *  get_plugin_page_hookname(). */
    private static function hook() {
        return CARACOOL_MENU_SLUG . '_page_' . HOSTPV_SLUG;
    }

    /** Añade el enlace "Administrar" a las acciones de la fila del plugin en
     *  Plugins → Instalados (donde están "Desactivar"/"Borrar"), enganchado
     *  vía plugin_action_links_{basename} en el constructor. Se pone AL
     *  PRINCIPIO del array (array_unshift, no array_merge al final) para que
     *  quede como primer enlace, más visible que al final de la fila. */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . HOSTPV_SLUG ) ) . '">' . __( 'Administrar', 'hostpv' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== self::hook() ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    /** Iconos SVG inline (propios, geométricos, sin depender de ningún
     *  paquete externo) usados en la cabecera y en las pestañas del
     *  rediseño visual de la página de ajustes. Puramente decorativos —
     *  devuelven solo el <svg>, el llamador pone el tamaño/color por CSS
     *  (stroke:currentColor). 13/08 (v2): se sustituyeron los iconos
     *  "mágicos" (sparkle/puzzle) — identificados como parte de lo que
     *  hacía el diseño "oler a genérico de IA" — por el mismo estilo
     *  lineal genérico (24x24, stroke-width 1.8) que usa de verdad
     *  Caracool OneStep. */
    private static function hp_icon( $name ) {
        $icons = [
            'image'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
            'layers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>',
        ];
        return $icons[ $name ] ?? '';
    }

    /** CSS del rediseño visual de la página de ajustes. 13/08 (v2): tras
     *  feedback de que la v1 "no era tan limpia como [Caracool OneStep],
     *  las cajas se descuadraban y todo olía a genérico de IA", se usó
     *  el .zip real de OneStep como referencia. Esta v2 es un PORT literal del
     *  sistema de diseño real de OneStep (mismas variables, mismos números
     *  de spacing/radius/font-weight, mismo uso — tinta casi negra para
     *  pestañas/botones/interruptores activos, el color de marca SOLO como
     *  acento puntual en iconos de tarjeta y badges, nunca como relleno de
     *  botón — y grid CSS en vez de flexbox para las filas de campo, que es
     *  lo que arregla el descuadre: con flexbox cada fila calculaba su
     *  ancho de etiqueta por separado, con grid todas las filas del panel
     *  comparten la MISMA columna). Único cambio real de marca: el acento
     *  es el ámbar real de Hostpv (#ffc94a, tomado en vivo de hostpv.com)
     *  en vez del terracota de OneStep (#c1502e). */
    private static function hp_settings_css() {
        return <<<'HOSTPV_SETTINGS_CSS'
        <style>
            .hp-wrap{ --hp-bg:#f6f5f2; --hp-panel:#ffffff; --hp-border:#e7e4de;
                --hp-ink:#18140d; --hp-ink-soft:#6b6660; --hp-ink-faint:#a29c93;
                --hp-accent:#ffc94a; --hp-accent-soft:#fff3d6; --hp-accent-ink:#6b4a00;
                --hp-ok:#2f7a4f; --hp-ok-soft:#e3f1e8;
                --hp-radius-lg:16px; --hp-radius-md:10px; --hp-radius-sm:7px;
                --hp-shadow:0 1px 2px rgba(24,20,13,.04), 0 8px 24px -12px rgba(24,20,13,.12);
                --hp-mono: ui-monospace,"SF Mono","Cascadia Code",Menlo,Consolas,monospace;
                max-width:920px; }
            .hp-wrap *{ box-sizing:border-box; }
            .hp-head{ display:flex; align-items:center; justify-content:space-between; gap:20px; margin:18px 0 26px; flex-wrap:wrap; }
            .hp-head-id{ display:flex; align-items:center; gap:14px; }
            .hp-head-titles h1{ margin:0; padding:0; font-size:20px; font-weight:650; letter-spacing:-.01em; line-height:1.3; }
            .hp-ver-pill{ font-size:11px; font-weight:600; color:var(--hp-ink-soft); background:var(--hp-panel); border:1px solid var(--hp-border); padding:3px 9px; border-radius:999px; letter-spacing:.02em; margin-left:6px; vertical-align:2px; }
            .hp-head-sub{ margin:4px 0 0; font-size:13px; color:var(--hp-ink-soft); }
            .hp-status-chip{ display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--hp-ok); background:var(--hp-ok-soft); border-radius:999px; padding:6px 12px 6px 10px; white-space:nowrap; }
            .hp-status-chip .hp-dot{ width:6px; height:6px; border-radius:50%; background:var(--hp-ok); }

            .hp-tabs{ display:flex; gap:4px; background:var(--hp-panel); border:1px solid var(--hp-border); border-radius:var(--hp-radius-md); padding:4px; margin-bottom:22px; width:max-content; flex-wrap:wrap; }
            .hp-tab{ display:flex; align-items:center; gap:7px; border:none; background:transparent; font:inherit; font-size:13.5px; font-weight:560; color:var(--hp-ink-soft); padding:8px 14px; border-radius:7px; cursor:pointer; transition:background .12s,color .12s; }
            .hp-tab svg{ width:16px; height:16px; opacity:.75; }
            .hp-tab:hover{ background:#f1efe9; color:var(--hp-ink); }
            .hp-tab.hp-tab-active{ background:var(--hp-ink); color:#fff; }
            .hp-tab.hp-tab-active svg{ opacity:1; }

            .hp-card{ background:var(--hp-panel); border:1px solid var(--hp-border); border-radius:var(--hp-radius-lg); box-shadow:var(--hp-shadow); padding:24px 26px; margin-bottom:16px; }
            .hp-card-head{ display:flex; align-items:center; gap:11px; margin-bottom:4px; }
            .hp-card-icon{ width:32px; height:32px; border-radius:9px; background:var(--hp-accent-soft); color:var(--hp-accent-ink); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
            .hp-card-icon svg{ width:17px; height:17px; }
            .hp-card-head h2{ margin:0; padding:0; border:0; font-size:14.5px; font-weight:640; letter-spacing:-.005em; }
            .hp-card-desc{ margin:10px 0 0; font-size:13px; line-height:1.55; color:var(--hp-ink-soft); }
            .hp-card-desc code{ background:#f1efe9; border-radius:4px; padding:1px 5px; font-size:12px; font-family:var(--hp-mono); }

            .hp-toggle-row{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding-top:16px; }
            .hp-toggle-row .hp-label{ font-size:14px; font-weight:570; }
            .hp-toggle-row .hp-hint{ margin:4px 0 0; font-size:12.5px; color:var(--hp-ink-soft); max-width:520px; line-height:1.5; }
            .hp-switch{ position:relative; width:40px; height:24px; flex-shrink:0; margin-top:1px; display:block; cursor:pointer; }
            .hp-switch input{ opacity:0; width:0; height:0; position:absolute; }
            .hp-switch .hp-track{ position:absolute; inset:0; background:#dcd8d1; border-radius:999px; transition:.15s; }
            .hp-switch .hp-track::before{ content:""; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.15s; box-shadow:0 1px 2px rgba(0,0,0,.25); }
            .hp-switch input:checked + .hp-track{ background:var(--hp-ink); }
            .hp-switch input:checked + .hp-track::before{ transform:translateX(16px); }

            .hp-field-grid{ display:grid; grid-template-columns:190px 1fr; gap:14px 18px; align-items:start; margin-top:18px; }
            .hp-field-grid label{ font-size:13px; font-weight:570; padding-top:9px; }
            .hp-field-grid .hp-field-hint{ grid-column:2; margin:-6px 0 0; font-size:12px; color:var(--hp-ink-faint); }
            .hp-wrap input[type=text], .hp-wrap select, .hp-wrap textarea{
                width:100%; font:inherit; font-size:13.5px; padding:9px 11px;
                border:1px solid var(--hp-border); border-radius:var(--hp-radius-sm);
                background:#fdfcfb; color:var(--hp-ink);
            }
            .hp-wrap textarea.hp-code{ font-family:var(--hp-mono); font-size:12.5px; }
            .hp-wrap input[type=text]:focus, .hp-wrap select:focus, .hp-wrap textarea:focus{
                outline:none; border-color:var(--hp-ink); box-shadow:0 0 0 3px rgba(24,20,13,.08);
            }

            .hp-preview-stage{ background:#0b1f3a; border-radius:var(--hp-radius-md); padding:30px; display:flex; justify-content:center; margin-top:16px; }
            .hp-preview-stage > div{ max-width:340px; width:100%; }

            .hp-badge{ font-size:11px; font-weight:650; padding:3px 8px; border-radius:999px; background:var(--hp-accent-soft); color:var(--hp-accent-ink); white-space:nowrap; margin-left:auto; }
            .hp-badge.hp-badge-muted{ background:#efece7; color:var(--hp-ink-soft); }

            .hp-copy-group{ margin-top:16px; }
            .hp-copy-group textarea{ width:100%; }
            .hp-copy-actions{ display:flex; align-items:center; gap:12px; margin-top:12px; flex-wrap:wrap; }
            #hp-copy-caja-halo-msg{ color:var(--hp-ok); font-size:12px; font-weight:650; }

            .hp-wrap .button.button-primary{
                background:var(--hp-ink) !important; border-color:var(--hp-ink) !important; color:#fff !important;
                border-radius:999px !important; font-weight:610 !important; padding:8px 20px !important; height:auto !important;
                box-shadow:none !important; text-shadow:none !important;
            }
            .hp-wrap .button.button-primary:hover{ background:#000 !important; border-color:#000 !important; }
            .hp-wrap .button:not(.button-primary){
                border-radius:999px !important; border:1px solid var(--hp-border) !important; background:transparent !important;
                color:var(--hp-ink) !important; font-weight:610 !important; padding:8px 18px !important; height:auto !important;
                box-shadow:none !important; text-shadow:none !important;
            }
            .hp-wrap .button:not(.button-primary):hover{ background:#f1efe9 !important; }
            .hp-wrap .submit{ margin-top:22px; }

            .hp-foot-credit{ text-align:center; font-size:11.5px; color:var(--hp-ink-faint); margin-top:34px; }
        </style>
        HOSTPV_SETTINGS_CSS;
    }

    /** Lista del "contrato" (rediseño visual) — TODO lo que el PHP y el JS
     *  de esta página necesitan para funcionar, que el rediseño tiene
     *  prohibido renombrar. No se ejecuta, es documentación viva junto al
     *  código para que cualquier cambio futuro de estilo la respete:
     *  - name: logo_enabled, logo_color, caja_halo_html, action (hidden,
     *    valor hostpv_save), _wpnonce/_wp_http_referer (de wp_nonce_field).
     *  - id: logo_color, caja_halo_html, tab-logo, tab-caja-halo,
     *    hp-copy-caja-halo, hp-copy-caja-halo-msg, hp-pull-caja-halo
     *    (+ tab-txtfx e id del selector de párrafo, en
     *    hostpv-text-animations.php).
     *  - clases-gancho de JS: .hp-tab (+hp-tab-active), .hp-tab-panel,
     *    .hp-color-field (wpColorPicker). data-hp-tab en cada pestaña
     *    (probado por test_hostpv_text_animations.php). Desde el 14/08 el
     *    textarea de Caja y Halo tiene id="caja_halo_html" fijo — tanto
     *    #hp-copy-caja-halo como #hp-pull-caja-halo lo leen/escriben por
     *    ese id directamente (ya NO por "closest('div') →
     *    querySelector('textarea')"), así que ya no depende de qué
     *    etiqueta envuelve al botón. #hp-pull-caja-halo trae el HTML leído
     *    en directo en su atributo data-caja-halo-live (JSON). */
    private static function hp_settings_contract() { /* solo documentación, sin efecto */ }

    // ── Página de ajustes ─────────────────────────────────────
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $s = $this->get_settings();
        echo self::hp_settings_css();
        ?>
        <div class="wrap hp-wrap">
            <div class="hp-head">
                <div class="hp-head-id">
                    <?php echo self::caracool_logo_svg(); // phpcs:ignore -- markup fijo, sin datos de usuario ?>
                    <div class="hp-head-titles">
                        <h1>HosTPV <span class="hp-ver-pill">v<?php echo esc_html( HOSTPV_VERSION ); ?></span></h1>
                        <p class="hp-head-sub">Animaciones de marca de Hostpv — logo animado, cajas 3D, plantilla Caja y Halo y cursor personalizado</p>
                    </div>
                </div>
                <div class="hp-status-chip"><span class="hp-dot"></span>Activo</div>
            </div>

            <div class="hp-tabs">
                <button type="button" class="hp-tab hp-tab-active" data-hp-tab="tab-logo">
                    <?php echo self::hp_icon( 'image' ); ?> Logo animado
                </button>
                <button type="button" class="hp-tab" data-hp-tab="tab-caja-halo">
                    <?php echo self::hp_icon( 'layers' ); ?> Caja y Halo
                </button>
                <?php
                /**
                 * 12/08: punto de extensión genérico para que OTROS módulos del
                 * plugin (archivos aparte, como hostpv-text-animations.php)
                 * puedan añadir su propia pestaña a ESTA misma página de
                 * ajustes, sin que este archivo tenga que saber nada de ellos.
                 * Cada módulo engancha aquí un <button class="hp-tab" data-hp-tab="...">
                 * igual que las dos de arriba. Ver hostpv_settings_panels más
                 * abajo para el contenido de esa pestaña.
                 */
                do_action( 'hostpv_settings_tabs' );
                ?>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'hostpv_save' ); ?>
                <input type="hidden" name="action" value="hostpv_save">

                <!-- ── TAB: LOGO ANIMADO ── -->
                <div id="tab-logo" class="hp-tab-panel">
                    <div class="hp-card">
                        <div class="hp-card-head">
                            <div class="hp-card-icon"><?php echo self::hp_icon( 'image' ); ?></div>
                            <h2>Logo animado</h2>
                        </div>
                        <p class="hp-card-desc">El marco entra primero, luego las 6 letras de "HOSTPV" una a una, y por último el eslogan.</p>

                        <div class="hp-toggle-row">
                            <div>
                                <div class="hp-label">Activar shortcode <code>[hostpv_logo_animado]</code></div>
                                <p class="hp-hint">Colócalo en cualquier página, entrada o plantilla.</p>
                            </div>
                            <label class="hp-switch">
                                <input type="checkbox" name="logo_enabled" value="1" <?php checked( $s['logo_enabled'] ); ?>>
                                <span class="hp-track"></span>
                            </label>
                        </div>

                        <div class="hp-field-grid">
                            <label for="logo_color">Color del logo</label>
                            <input type="text" name="logo_color" id="logo_color" value="<?php echo esc_attr( $s['logo_color'] ); ?>" class="hp-color-field" data-default-color="#ffffff">
                        </div>
                    </div>

                    <div class="hp-card">
                        <div class="hp-card-head">
                            <div class="hp-card-icon"><?php echo self::hp_icon( 'image' ); ?></div>
                            <h2>Vista previa</h2>
                            <span class="hp-badge hp-badge-muted">Guarda para actualizar</span>
                        </div>
                        <p class="hp-card-desc">Con los ajustes guardados actualmente.</p>
                        <div class="hp-preview-stage">
                            <div>
                                <?php echo $this->shortcode_logo_animado( [] ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB: CAJA Y HALO (CUADRO GUARDABLE + LECTURA EN DIRECTO) ── -->
                <?php
                $caja_halo_ref   = self::get_caja_halo_reference_html();
                $caja_halo_saved = $s['caja_halo_html'];
                // Si nunca se ha guardado nada a propósito, se rellena la vista
                // con la lectura en directo (o el respaldo) para no empezar con
                // el cuadro vacío — pero SIN persistirlo hasta que se pulse
                // "Guardar cambios" de verdad.
                $caja_halo_box_value = ( $caja_halo_saved !== '' ) ? $caja_halo_saved : $caja_halo_ref['html'];
                ?>
                <div id="tab-caja-halo" class="hp-tab-panel" style="display:none;">
                    <div class="hp-card">
                        <div class="hp-card-head">
                            <div class="hp-card-icon"><?php echo self::hp_icon( 'layers' ); ?></div>
                            <h2>Caja y Halo</h2>
                            <span class="hp-badge">Editable</span>
                        </div>
                        <p class="hp-card-desc">Código del widget "HTML" de la página Contacto (caja negra que cae + halo que se dibuja al terminar). Este cuadro se guarda como parte de los ajustes del plugin — edítalo a mano si quieres, o pulsa "Leer de Contacto ahora" para traer el código tal como está hoy en esa página, y guarda para quedarte con esa versión.</p>
                        <?php if ( $caja_halo_ref['live'] ) : ?>
                        <p class="hp-hint" style="margin-top:10px;">
                            "Leer de Contacto ahora" trae el HTML <strong>real</strong>, tal como está hoy en el widget de esa página.
                        </p>
                        <?php else : ?>
                        <p class="hp-hint" style="margin-top:10px;color:#a33;">
                            <strong>Aviso:</strong> no se ha podido leer el widget real de la página Contacto ahora mismo (puede que se haya movido o borrado) — "Leer de Contacto ahora" traería una copia de seguridad estática, que puede estar desactualizada.
                        </p>
                        <?php endif; ?>
                        <div class="hp-copy-group">
                            <textarea name="caja_halo_html" id="caja_halo_html" rows="26" class="hp-code"><?php echo esc_textarea( $caja_halo_box_value ); ?></textarea>
                            <p class="hp-copy-actions">
                                <button type="button" class="button" id="hp-pull-caja-halo" data-caja-halo-live="<?php echo esc_attr( wp_json_encode( $caja_halo_ref['html'] ) ); ?>">Leer de Contacto ahora</button>
                                <button type="button" class="button" id="hp-copy-caja-halo">Copiar al portapapeles</button>
                                <span id="hp-copy-caja-halo-msg" style="display:none;">¡Copiado!</span>
                            </p>
                            <p class="hp-hint" style="margin:8px 0 0;">"Leer de Contacto ahora" solo rellena el cuadro — para que quede guardado hace falta pulsar el botón de guardar, al final de la página.</p>
                        </div>
                    </div>
                </div>

                <?php
                /**
                 * 12/08: pareja del hook de arriba — aquí van los PANELES
                 * (contenido) de las pestañas que añadan otros módulos. Cada
                 * módulo engancha un <div id="tab-xxx" class="hp-tab-panel"
                 * style="display:none;">...</div>, mismo patrón que los dos
                 * de arriba. Van DENTRO de este mismo <form> (un solo envío,
                 * un solo botón "Guardar cambios" para toda la página), así
                 * que el módulo que añada campos aquí tiene que guardar sus
                 * propios valores enganchando `admin_post_hostpv_save` (la
                 * MISMA acción que ya usa este formulario) con prioridad baja
                 * (ej. 5) para que le dé tiempo a guardar antes de que
                 * HosTPV::save_settings() haga el redirect+exit final.
                 */
                do_action( 'hostpv_settings_panels' );
                ?>

                <?php submit_button( 'Guardar cambios' ); ?>
            </form>

            <p class="hp-foot-credit">Hecho con ❤️ por Caracool</p>
        </div>

        <script>
            var hpTabs  = document.querySelectorAll('.hp-tab');
            var hpPanes = document.querySelectorAll('.hp-tab-panel');
            hpTabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    hpTabs.forEach(function (t) { t.classList.remove('hp-tab-active'); });
                    hpPanes.forEach(function (p) { p.style.display = 'none'; });
                    tab.classList.add('hp-tab-active');
                    document.getElementById(tab.dataset.hpTab).style.display = '';
                });
            });
            jQuery(function ($) {
                $('.hp-color-field').wpColorPicker();
            });
            var hpCopyBtn = document.getElementById('hp-copy-caja-halo');
            if (hpCopyBtn) {
                hpCopyBtn.addEventListener('click', function () {
                    var ta  = document.getElementById('caja_halo_html');
                    var msg = document.getElementById('hp-copy-caja-halo-msg');
                    var done = function () {
                        if (!msg) return;
                        msg.style.display = '';
                        setTimeout(function () { msg.style.display = 'none'; }, 1800);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(ta.value).then(done);
                    } else {
                        ta.select();
                        document.execCommand('copy');
                        done();
                    }
                });
            }
            var hpPullBtn = document.getElementById('hp-pull-caja-halo');
            if (hpPullBtn) {
                hpPullBtn.addEventListener('click', function () {
                    var ta = document.getElementById('caja_halo_html');
                    if (!ta) return;
                    ta.value = JSON.parse(hpPullBtn.dataset.cajaHaloLive);
                });
            }
        </script>
        <?php
    }

    /** Busca, recorriendo el árbol completo (recursivo), el primer nodo
     *  cuyo `id` de Elementor coincida exactamente. Devuelve el nodo entero
     *  (con sus `settings`) o null si no aparece. */
    private static function find_widget_by_id( array $nodes, $id ) {
        foreach ( $nodes as $node ) {
            if ( ( $node['id'] ?? '' ) === $id ) return $node;
            if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $found = self::find_widget_by_id( $node['elements'], $id );
                if ( $found !== null ) return $found;
            }
        }
        return null;
    }

    /** Igual que find_widget_by_id() pero busca por `widgetType` — se usa
     *  como respaldo si el `id` concreto ya no existe (p.ej. si el widget
     *  se borró y se volvió a crear, lo que le da un id nuevo). */
    private static function find_first_widget_by_type( array $nodes, $widget_type ) {
        foreach ( $nodes as $node ) {
            if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === $widget_type ) {
                return $node;
            }
            if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $found = self::find_first_widget_by_type( $node['elements'], $widget_type );
                if ( $found !== null ) return $found;
            }
        }
        return null;
    }

    /** Lee EN DIRECTO (sin copia fija en el plugin) el HTML del widget
     *  "HTML" de la página de referencia (Contacto, ver las constantes
     *  CAJA_HALO_REF_*): la composición "caja negra que cae + halo que se
     *  dibuja" tal como esté en ese momento. Se muestra en la
     *  pestaña "Caja y Halo" del panel de ajustes — ver render_settings_page().
     *  Busca primero por `id` exacto; si no lo encuentra, cae a buscar el
     *  primer widget de tipo "html" de la página; si tampoco hay nada
     *  legible (página borrada/movida, meta vacía), cae a la copia de
     *  seguridad estática (caja_halo_fallback_template_html()). Devuelve
     *  ['html' => string, 'live' => bool] — 'live' indica si el contenido
     *  viene de la lectura real o de la copia de seguridad, para poder
     *  avisar en pantalla. */
    private static function get_caja_halo_reference_html() {
        $raw = get_post_meta( self::CAJA_HALO_REF_POST_ID, '_elementor_data', true );
        if ( $raw ) {
            $data = json_decode( $raw, true );
            if ( is_array( $data ) ) {
                $widget = self::find_widget_by_id( $data, self::CAJA_HALO_REF_WIDGET_ID );
                if ( $widget === null ) {
                    $widget = self::find_first_widget_by_type( $data, 'html' );
                }
                $html = $widget['settings']['html'] ?? '';
                if ( trim( (string) $html ) !== '' ) {
                    return [ 'html' => $html, 'live' => true ];
                }
            }
        }
        return [ 'html' => self::caja_halo_fallback_template_html(), 'live' => false ];
    }

    /** Copia de seguridad ESTÁTICA (SOLO copiar/pegar, no se ejecuta desde
     *  el plugin) de la composición "caja negra + halo" — se usa ÚNICAMENTE
     *  si get_caja_halo_reference_html() no consigue leer el widget real de
     *  la página Contacto (borrada, movida, o el widget cambió de id y ya
     *  no hay ningún widget "html" en la página). En el día a día la
     *  pestaña de ajustes NO usa esta copia — lee el widget real en
     *  directo cada vez que se abre. */
    private static function caja_halo_fallback_template_html() {
        return <<<'HOSTPV_CAJAHALO_TEMPLATE'
<style>
.hp-box-wrap{
  position:absolute;
  top:-650px;
  left:0;
  right:0;
  z-index:0;
  pointer-events:none;
  overflow:visible;
  --hp-halo-x:1px;
  --hp-halo-y:5px;
  --hp-halo-scale:.98;
}
/* =========================
   CAJA NEGRA
========================= */
.hp-box-wrap .hp-caja-svg{
  position:absolute;
  top:0;
  left:50%;
  width:1440px;
  max-width:none;
  height:auto;
  display:block;
  overflow:visible;
  transform:translateX(-50%);
  opacity:0;
  animation:hp-fall-caja 600ms cubic-bezier(.16,1,.3,1) forwards;
}
.hp-box-wrap .hp-caja-negra{
  fill:#0c0c0c;
}
/* =========================
   HALO
========================= */
.hp-box-wrap .hp-halo-svg{
  position:absolute;
  top:0;
  left:50%;
  width:1440px;
  max-width:none;
  height:auto;
  display:block;
  overflow:visible;
  transform:
    translateX(-50%)
    translate(var(--hp-halo-x), var(--hp-halo-y))
    scale(var(--hp-halo-scale));
  transform-origin:top center;
}
.hp-box-wrap .hp-halo-path{
  fill:none;
  stroke:#fff;
  stroke-width:0.5;
  stroke-linecap:round;
  stroke-linejoin:round;
  stroke-miterlimit:10;
  shape-rendering:geometricPrecision;
  stroke-dasharray:1258px;
  stroke-dashoffset:1258px;
  animation:hp-draw-halo 1500ms linear 300ms forwards;
}
/* =========================
   ANIMACIÓN CAJA
========================= */
@keyframes hp-fall-caja{
  0%{
    opacity:0;
    transform:translateX(-50%) translateY(-160px);
  }
  100%{
    opacity:1;
    transform:translateX(-50%) translateY(0);
  }
}
@keyframes hp-draw-halo{
  to{
    stroke-dashoffset:0;
  }
}
/* =========================
   RESPONSIVE
========================= */
@media (max-width:1024px){
  .hp-box-wrap{
    top:-300px;             /* nuevo */
    --hp-halo-x:0px;
    --hp-halo-y:2px;
    --hp-halo-scale:.98;
  }
  .hp-box-wrap .hp-caja-svg,
  .hp-box-wrap .hp-halo-svg{
    width:130%;
  }
}
@media (max-width:767px){
  .hp-box-wrap{
    top:-200px;             /* nuevo */
    --hp-halo-x:0px;
    --hp-halo-y:1px;
    --hp-halo-scale:.98;
  }
  .hp-box-wrap .hp-caja-svg,
  .hp-box-wrap .hp-halo-svg{
    width:240%;
  }
}
</style>
<div class="hp-box-wrap">
  <!-- CAJA NEGRA -->
  <svg
    class="hp-caja-svg"
    viewBox="0 0 420.86 309.85"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      class="hp-caja-negra"
      d="M420.57,34.03l-34.36,244.82c-2.53,18.02-17.97,31-35.6,31-1.87,0-3.76-.15-5.66-.45L20.54,258.16c-14.37-2.27-23.55-16.56-19.63-30.58L54.01,37.55c4-14.28,16.74-24.37,31.56-24.98L389.75.03c.42-.02.84-.03,1.26-.03,18.03,0,32.09,15.98,29.56,34.03Z"
    />
  </svg>
  <!-- HALO -->
  <svg
    class="hp-halo-svg"
    viewBox="0 0 416.2 305.2"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      class="hp-halo-path"
      d="
        M235.44,6.795
        L387.53,.52
        c.39,-.02
        .77,-.02
        1.15,-.02
        c7.81,0
        15.24,3.39
        20.39,9.31
        c5.14,5.91
        7.46,13.75
        6.37,21.51
        l-34.36,244.82
        c-2.29,16.29
        -16.39,28.57
        -32.8,28.57
        c-1.73,0
        -3.49,-.14
        -5.22,-.41
        L18.65,253.05
        c-6.06,-.96
        -11.43,-4.46
        -14.74,-9.62
        c-3.31,-5.16
        -4.27,-11.5
        -2.62,-17.41
        L54.4,35.99
        c3.67,-13.15
        15.31,-22.36
        28.95,-22.92
        L235.44,6.795
      "
    />
  </svg>
</div>
HOSTPV_CAJAHALO_TEMPLATE;
    }

    // ── Shortcode: logo animado ────────────────────────────────
    public function shortcode_logo_animado( $atts ) {
        $s = $this->get_settings();
        if ( empty( $s['logo_enabled'] ) ) return '';

        $color = esc_attr( $s['logo_color'] );
        $out   = '';

        if ( ! self::$logo_assets_printed ) {
            self::$logo_assets_printed = true;
            $out .= <<<'HOSTPV_LOGO_STYLE'
<style>
    .hlogo{ overflow:visible; }
    .hlogo path, .hlogo polygon{ fill:var(--hlogo-color,#fff); }
    .hlogo-badge{
      transform-origin:210.43px 154.925px;
      opacity:0;
      animation:hlogo-badge-in .7s cubic-bezier(.22,1,.36,1) .05s forwards;
    }
    .hlogo-wordmark .hlogo-letter{
      opacity:0;
      transform:translateY(14px);
      animation:hlogo-letter-in .55s cubic-bezier(.22,1,.36,1) forwards;
      animation-delay:calc(.38s + var(--i) * .09s);
    }
    .hlogo-tagline{
      opacity:0;
      transform:translateY(6px);
      animation:hlogo-tag-in .6s ease-out 1.05s forwards;
    }
    @keyframes hlogo-badge-in{ from{opacity:0;transform:scale(.82);} to{opacity:1;transform:scale(1);} }
    @keyframes hlogo-letter-in{ from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }
    @keyframes hlogo-tag-in{ from{opacity:0;transform:translateY(6px);} to{opacity:1;transform:translateY(0);} }
    @media (prefers-reduced-motion:reduce){
      .hlogo-badge,.hlogo-letter,.hlogo-tagline{ animation:none!important;opacity:1!important;transform:none!important; }
    }
  </style>
HOSTPV_LOGO_STYLE;
        }

        $out .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420.86 309.85" class="hlogo" role="img" aria-label="Hostpv" style="--hlogo-color:' . $color . ';width:100%;height:auto;">';
        $out .= '<g class="hlogo-badge"><path d="' . self::hostpv_caja_d() . '"/></g>';
        $out .= '<g class="hlogo-wordmark"><g class="hlogo-letter" style="--i:0"><polygon points="85.32 148.89 85.32 102.72 92.33 102.72 92.33 122.72 116.7 122.72 116.7 102.72 123.7 102.7 123.7 148.89 116.7 148.89 116.7 129.17 92.33 129.17 92.33 148.89 85.32 148.89"/></g><g class="hlogo-letter" style="--i:1"><path d="M131.46,125.81c0-13.32,10.37-23.68,23.68-23.68s23.55,10.36,23.55,23.68-10.3,23.89-23.55,23.89-23.68-10.36-23.68-23.89M171.48,125.81c0-9.54-7.21-17.03-16.34-17.03s-16.47,7.49-16.47,17.03,7.28,17.23,16.47,17.23,16.34-7.55,16.34-17.23"/></g><g class="hlogo-letter" style="--i:2"><path d="M183.23,139.81l5.9-2.88c1.79,3.98,5.7,6.11,11.12,6.11s9.47-2.54,9.47-7.07c0-5.08-4.67-5.97-10.09-7-6.93-1.37-15.51-3.64-15.51-13.39,0-7.62,6.93-13.38,15.65-13.38,7,0,13.25,3.3,15.99,9.61l-5.83,3.02c-1.85-3.78-5.35-6.11-10.43-6.11-4.67,0-8.44,2.74-8.44,6.66,0,4.94,4.87,6.04,10.43,7.14,6.86,1.37,15.24,3.64,15.24,13.39,0,8.1-7.21,13.73-16.48,13.73-7.76,0-14.28-3.5-17.02-9.82"/></g><g class="hlogo-letter" style="--i:3"><polygon points="259.82 111.97 246.22 111.97 246.22 148.9 236.2 148.9 236.2 111.97 222.61 111.97 222.61 102.7 259.82 102.7 259.82 111.97"/></g><g class="hlogo-letter" style="--i:4"><path d="M265.11,148.9v-46.2h16.27c13.11,0,21.14,6.11,21.14,16.82s-7.9,17.16-20.59,17.16h-6.8v12.22h-10.02ZM275.13,111.97v15.44h6.52c6.59,0,10.57-2.54,10.57-7.89,0-5.08-3.98-7.55-10.57-7.55h-6.52Z"/></g><g class="hlogo-letter" style="--i:5"><path d="M315.29,141.35l-11.88-38.65h10.3l10.64,35.77c.27.89.76,1.37,1.44,1.37s1.1-.48,1.37-1.37l10.57-35.77h10.3l-11.88,38.65c-1.78,5.7-5.08,8.51-10.43,8.51s-8.65-2.81-10.43-8.51"/></g></g>';
        $out .= '<g class="hlogo-tagline"><path d="M87.42,187.07c-.69-.31-1.23-.74-1.62-1.29-.39-.55-.59-1.18-.61-1.9h1.84c.06.62.32,1.14.77,1.56s1.1.63,1.96.63,1.47-.21,1.94-.62c.47-.41.71-.94.71-1.58,0-.5-.14-.92-.42-1.23-.28-.31-.62-.55-1.04-.72-.42-.16-.98-.34-1.68-.53-.87-.23-1.57-.45-2.09-.68-.52-.23-.97-.58-1.34-1.07s-.56-1.14-.56-1.96c0-.72.18-1.36.55-1.91.37-.55.88-.98,1.54-1.29s1.42-.45,2.28-.45c1.24,0,2.25.31,3.04.93s1.23,1.44,1.33,2.46h-1.89c-.06-.5-.33-.95-.79-1.33-.47-.38-1.08-.58-1.85-.58-.72,0-1.31.19-1.76.56-.45.37-.68.89-.68,1.56,0,.48.14.87.41,1.17.27.3.61.53,1,.69.4.16.96.34,1.67.54.87.24,1.57.48,2.1.71.53.23.98.59,1.36,1.08s.57,1.14.57,1.98c0,.64-.17,1.25-.51,1.82s-.85,1.03-1.51,1.38c-.67.35-1.46.53-2.36.53s-1.65-.15-2.34-.46Z"/><path d="M100.62,186.68c-1.01-.57-1.81-1.37-2.39-2.4s-.88-2.19-.88-3.47.29-2.44.88-3.47c.59-1.03,1.38-1.83,2.39-2.4,1.01-.57,2.12-.86,3.35-.86s2.36.29,3.37.86c1.01.57,1.8,1.37,2.38,2.39.58,1.02.87,2.18.87,3.48s-.29,2.46-.87,3.48c-.58,1.02-1.38,1.82-2.38,2.39-1.01.57-2.13.86-3.37.86s-2.34-.29-3.35-.86ZM106.45,185.4c.74-.43,1.32-1.04,1.74-1.83.42-.79.63-1.71.63-2.76s-.21-1.98-.63-2.77c-.42-.79-1-1.4-1.73-1.83-.73-.43-1.56-.64-2.5-.64s-1.77.21-2.5.64c-.73.43-1.31,1.04-1.73,1.83s-.63,1.71-.63,2.77.21,1.97.63,2.76c.42.79,1,1.41,1.74,1.83.74.43,1.57.64,2.49.64s1.75-.21,2.49-.64Z"/><path d="M115.89,186h4.62v1.4h-6.34v-13.19h1.72v11.79Z"/><path d="M125.33,174.22v8.34c0,1.17.29,2.04.86,2.61.57.57,1.37.85,2.39.85s1.8-.28,2.37-.85c.57-.57.86-1.44.86-2.61v-8.34h1.72v8.32c0,1.1-.22,2.02-.66,2.77-.44.75-1.04,1.31-1.79,1.67-.75.37-1.59.55-2.53.55s-1.78-.18-2.53-.55c-.75-.37-1.34-.92-1.78-1.67-.44-.75-.65-1.67-.65-2.77v-8.32h1.72Z"/><path d="M137.94,177.33c.58-1.03,1.37-1.83,2.37-2.41,1-.58,2.12-.87,3.34-.87,1.44,0,2.69.35,3.77,1.04,1.07.69,1.85,1.68,2.35,2.95h-2.06c-.37-.79-.89-1.41-1.58-1.83-.69-.43-1.51-.64-2.47-.64s-1.75.21-2.48.64c-.73.43-1.31,1.04-1.72,1.83s-.62,1.71-.62,2.77.21,1.96.62,2.75.99,1.4,1.72,1.83c.73.43,1.56.64,2.48.64s1.78-.21,2.47-.63c.69-.42,1.21-1.03,1.58-1.83h2.06c-.49,1.26-1.27,2.24-2.35,2.92-1.07.69-2.33,1.03-3.77,1.03-1.22,0-2.34-.29-3.34-.86-1-.57-1.79-1.37-2.37-2.39s-.87-2.18-.87-3.46.29-2.44.87-3.47Z"/><path d="M155.35,174.22v13.19h-1.72v-13.19h1.72Z"/><path d="M162.22,186.68c-1.01-.57-1.81-1.37-2.39-2.4s-.88-2.19-.88-3.47.29-2.44.88-3.47c.59-1.03,1.38-1.83,2.39-2.4,1.01-.57,2.12-.86,3.35-.86s2.36.29,3.37.86c1.01.57,1.8,1.37,2.38,2.39.58,1.02.87,2.18.87,3.48s-.29,2.46-.87,3.48c-.58,1.02-1.38,1.82-2.38,2.39-1.01.57-2.13.86-3.37.86s-2.34-.29-3.35-.86ZM168.05,185.4c.74-.43,1.32-1.04,1.74-1.83.42-.79.63-1.71.63-2.76s-.21-1.98-.63-2.77c-.42-.79-1-1.4-1.73-1.83-.73-.43-1.56-.64-2.5-.64s-1.77.21-2.5.64c-.73.43-1.31,1.04-1.73,1.83s-.63,1.71-.63,2.77.21,1.97.63,2.76c.42.79,1,1.41,1.74,1.83.74.43,1.57.64,2.49.64s1.75-.21,2.49-.64Z"/><path d="M186.13,187.4h-1.72l-6.92-10.5v10.5h-1.72v-13.21h1.72l6.92,10.48v-10.48h1.72v13.21Z"/><path d="M192.09,175.62v4.41h4.81v1.42h-4.81v4.54h5.37v1.42h-7.09v-13.21h7.09v1.42h-5.37Z"/><path d="M203.22,187.07c-.69-.31-1.23-.74-1.62-1.29-.39-.55-.59-1.18-.61-1.9h1.84c.06.62.32,1.14.77,1.56s1.1.63,1.96.63,1.47-.21,1.94-.62c.47-.41.71-.94.71-1.58,0-.5-.14-.92-.42-1.23-.28-.31-.62-.55-1.04-.72-.42-.16-.98-.34-1.68-.53-.87-.23-1.57-.45-2.09-.68-.52-.23-.97-.58-1.34-1.07s-.56-1.14-.56-1.96c0-.72.18-1.36.55-1.91.37-.55.88-.98,1.54-1.29s1.42-.45,2.28-.45c1.24,0,2.25.31,3.04.93s1.23,1.44,1.33,2.46h-1.89c-.06-.5-.33-.95-.79-1.33-.47-.38-1.08-.58-1.85-.58-.72,0-1.31.19-1.76.56-.45.37-.68.89-.68,1.56,0,.48.14.87.41,1.17.27.3.61.53,1,.69.4.16.96.34,1.67.54.87.24,1.57.48,2.1.71.53.23.98.59,1.36,1.08s.57,1.14.57,1.98c0,.64-.17,1.25-.51,1.82s-.85,1.03-1.51,1.38c-.67.35-1.46.53-2.36.53s-1.65-.15-2.34-.46Z"/><path d="M221.85,174.22v13.19h-1.72v-13.19h1.72Z"/><path d="M236.45,187.4h-1.72l-6.92-10.5v10.5h-1.72v-13.21h1.72l6.92,10.48v-10.48h1.72v13.21Z"/><path d="M248.81,174.22v1.4h-3.59v11.79h-1.72v-11.79h-3.61v-1.4h8.93Z"/><path d="M253.95,175.62v4.41h4.8v1.42h-4.8v4.54h5.37v1.42h-7.09v-13.21h7.09v1.42h-5.37Z"/><path d="M273.23,178.04c-.37-.77-.9-1.36-1.59-1.79s-1.5-.63-2.42-.63-1.75.21-2.49.63-1.32,1.03-1.74,1.82c-.42.79-.63,1.7-.63,2.73s.21,1.94.63,2.72c.42.78,1,1.38,1.74,1.81s1.57.63,2.49.63c1.29,0,2.35-.38,3.18-1.15.83-.77,1.32-1.81,1.46-3.12h-5.26v-1.4h7.09v1.32c-.1,1.08-.44,2.08-1.02,2.98-.58.9-1.34,1.61-2.29,2.14-.95.52-2,.79-3.16.79-1.22,0-2.34-.29-3.35-.86-1.01-.57-1.81-1.37-2.39-2.39-.59-1.02-.88-2.18-.88-3.46s.29-2.44.88-3.47c.59-1.03,1.38-1.83,2.39-2.4,1.01-.57,2.12-.86,3.35-.86,1.4,0,2.64.35,3.72,1.04s1.86,1.67,2.36,2.93h-2.06Z"/><path d="M286.19,187.4l-3.14-5.39h-2.08v5.39h-1.72v-13.19h4.26c1,0,1.84.17,2.53.51.69.34,1.2.8,1.54,1.38.34.58.51,1.24.51,1.99,0,.91-.26,1.71-.79,2.4s-1.31,1.15-2.36,1.38l3.31,5.52h-2.06ZM280.97,180.63h2.54c.93,0,1.63-.23,2.1-.69.47-.46.7-1.07.7-1.84s-.23-1.39-.69-1.82c-.46-.43-1.16-.64-2.11-.64h-2.54v5Z"/><path d="M299.85,184.47h-5.75l-1.06,2.93h-1.82l4.77-13.11h1.99l4.75,13.11h-1.82l-1.06-2.93ZM299.36,183.07l-2.38-6.66-2.38,6.66h4.77Z"/><path d="M307.83,186h4.62v1.4h-6.34v-13.19h1.72v11.79Z"/><path d="M317.31,175.62v4.41h4.8v1.42h-4.8v4.54h5.37v1.42h-7.09v-13.21h7.09v1.42h-5.37Z"/><path d="M328.44,187.07c-.69-.31-1.23-.74-1.62-1.29-.39-.55-.59-1.18-.61-1.9h1.83c.06.62.32,1.14.77,1.56.45.42,1.1.63,1.96.63s1.47-.21,1.94-.62c.47-.41.71-.94.71-1.58,0-.5-.14-.92-.42-1.23-.28-.31-.62-.55-1.04-.72-.42-.16-.98-.34-1.68-.53-.87-.23-1.57-.45-2.09-.68-.52-.23-.97-.58-1.34-1.07s-.56-1.14-.56-1.96c0-.72.18-1.36.55-1.91.37-.55.88-.98,1.54-1.29s1.42-.45,2.28-.45c1.24,0,2.25.31,3.04.93s1.23,1.44,1.33,2.46h-1.89c-.06-.5-.33-.95-.79-1.33s-1.08-.58-1.85-.58c-.72,0-1.3.19-1.76.56-.45.37-.68.89-.68,1.56,0,.48.14.87.41,1.17.27.3.61.53,1,.69.4.16.96.34,1.67.54.87.24,1.57.48,2.1.71.53.23.98.59,1.36,1.08.38.49.57,1.14.57,1.98,0,.64-.17,1.25-.51,1.82-.34.57-.84,1.03-1.51,1.38-.67.35-1.46.53-2.37.53s-1.65-.15-2.34-.46Z"/></g>';
        $out .= '</svg>';

        return $out;
    }

    // ── Widgets de Elementor ─────────────────────────────────────

    /** Categoría propia "HosTPV" en el panel de widgets de Elementor, para
     *  que el widget de abajo no aparezca suelto entre los widgets
     *  genéricos. */
    public function elementor_register_category( $elements_manager ) {
        $elements_manager->add_category( 'hostpv', [
            'title' => __( 'HosTPV', 'hostpv' ),
            'icon'  => 'fa fa-plug',
        ] );
    }

    /** Registra el widget "Cajas 3D". La clase del widget se define en una
     *  función SUELTA al final del archivo (hostpv_register_cajas3d_widget()),
     *  no aquí dentro — PHP no permite anidar una declaración de clase
     *  dentro del cuerpo de OTRA clase (ni siquiera dentro de un método),
     *  así que la clase del widget tiene que vivir dentro de una función
     *  normal para poder declararse solo cuando Elementor dispara este
     *  hook (y no arriesgar un fatal error si Elementor estuviera inactivo).
     *  12/08: el plugin llegó a tener también un widget "HosTPV — Caja y
     *  Halo" (v0.3.0) — se eliminó; esa composición
     *  ahora solo se ofrece como código de referencia leído en directo (ver
     *  get_caja_halo_reference_html()). */
    public function elementor_register_widgets( $widgets_manager ) {
        hostpv_register_cajas3d_widget( $widgets_manager );
    }

}

new HosTPV();

/** Registra el widget de Elementor "HosTPV — Cajas 3D". Función SUELTA por
 *  el mismo motivo explicado en el doc comment de
 *  HosTPV::elementor_register_widgets() (PHP no permite anidar una
 *  declaración de clase dentro de otra).
 *
 *  v0.3.1 — sustituye por completo a la integración anterior de "cajas 3D
 *  en cascada" como opción de fondo de cualquier Container de Elementor
 *  (que tenía su propio hook antes de renderizar + el hook especial de
 *  vista previa del editor + un <style>/<script> compartido con bandera
 *  global impreso en el footer). Motivo del cambio (11/08): esa
 *  integración registraba sus controles en TODOS los Containers de TODAS
 *  las páginas del sitio (aunque solo se usa en Inicio), ensuciando el
 *  panel de Elementor en todas partes. Como widget normal, Elementor ya
 *  sabe en qué páginas está colocado — los controles solo aparecen al
 *  arrastrar el widget a un Container. Al ser un widget normal su render()
 *  se ejecuta igual en el editor que en el sitio real, así que tampoco hace
 *  falta ningún hook especial de vista previa.
 *
 *  Las cajas se construyen aquí mismo en PHP dentro de render() (antes se
 *  inyectaban por JS en un Container ajeno) — el JS que queda es solo el
 *  motor de animación (ratón real/virtual, cascada tipo "látigo"), igual
 *  que en la versión anterior. Se quitó también la opción "logo en la caja
 *  central" (no se usa, eliminada del todo el 12/08).
 *
 *  Rendimiento (12/08): el bucle de animación (requestAnimationFrame)
 *  se para por completo cuando la pestaña no está visible (Page Visibility
 *  API, `document.hidden`) O cuando la ventana del navegador no tiene el
 *  foco del sistema operativo (`document.hasFocus()`) — cubre tanto
 *  "cambié de pestaña" como "el navegador está abierto pero no es la
 *  ventana activa". Se reengancha solo con los eventos 'visibilitychange'
 *  y 'focus' (no hace falta comprobar en cada frame si no se está
 *  animando: el bucle se corta del todo, cero consumo de CPU mientras
 *  tanto, no solo "más lento"). */
function hostpv_register_cajas3d_widget( $widgets_manager ) {
        if ( ! class_exists( 'HosTPV_Widget_Cajas3D' ) ) {

            class HosTPV_Widget_Cajas3D extends \Elementor\Widget_Base {

                /** El <style>/<script> compartido se imprime una sola vez
                 *  por petición, aunque el widget se use varias veces en la
                 *  misma página. */
                private static $assets_printed = false;

                public function get_name() { return 'hostpv_cajas3d'; }
                public function get_title() { return __( 'HosTPV — Cajas 3D', 'hostpv' ); }
                public function get_icon() { return 'eicon-parallax'; }
                public function get_categories() { return [ 'hostpv' ]; }
                public function get_keywords() { return [ 'hostpv', 'cajas', '3d', 'cascada', 'animacion' ]; }

                protected function register_controls() {

                    $this->start_controls_section( 'section_cajas3d', [
                        'label' => __( 'Cajas 3D', 'hostpv' ),
                        'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
                    ] );

                    /* Ajustable por dispositivo: en movil el contenedor es mucho mas
                       estrecho y las cajas se quedan pequenas con el mismo porcentaje que
                       en escritorio. Y sin tope en el 100%: el ancho es el del contenedor,
                       asi que para que las cajas se vean grandes de verdad en una pantalla
                       estrecha hace falta poder pasar de ahi. Lo que sobresalga lo recorta
                       el propio widget, que ya lleva overflow:hidden. */
                    /* La unidad se fija en % tambien para tablet y movil. Sin esto,
                       Elementor da a los controles responsive la unidad px por
                       defecto, y como el rango solo esta definido para %, el
                       deslizador cae al rango generico de px (0-100): de ahi que en
                       movil no se pudiera pasar de 100. */
                    $this->add_responsive_control( 'size', [
                        'label'       => __( 'Tamaño', 'hostpv' ),
                        'type'        => \Elementor\Controls_Manager::SLIDER,
                        'size_units'  => [ '%' ],
                        'range'       => [ '%' => [ 'min' => 20, 'max' => 250 ] ],
                        'default'     => [ 'unit' => '%', 'size' => 80 ],
                        'device_args' => [
                            'tablet' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                            'mobile' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                        ],
                        'description' => __( 'Porcentaje del ancho del contenedor. Por encima de 100 las cajas lo desbordan a propósito. Ajustable por dispositivo.', 'hostpv' ),
                        'selectors'   => [
                            '{{WRAPPER}}' => '--hp3-size: {{SIZE}}%;',
                        ],
                    ] );

                    $this->add_responsive_control( 'offset_x', [
                        'label'       => __( 'Desplazamiento horizontal', 'hostpv' ),
                        'type'        => \Elementor\Controls_Manager::SLIDER,
                        'size_units'  => [ '%' ],
                        'range'       => [ '%' => [ 'min' => -60, 'max' => 60 ] ],
                        'default'     => [ 'unit' => '%', 'size' => 25 ],
                        'device_args' => [
                            'tablet' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                            'mobile' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                        ],
                        'description' => __( 'Negativo = hacia la izquierda. Positivo = hacia la derecha. Ajustable por dispositivo.', 'hostpv' ),
                        'selectors'   => [
                            '{{WRAPPER}}' => '--hp3-offset-x: {{SIZE}}%;',
                        ],
                    ] );

                    $this->add_responsive_control( 'offset_y', [
                        'label'       => __( 'Desplazamiento vertical', 'hostpv' ),
                        'type'        => \Elementor\Controls_Manager::SLIDER,
                        'size_units'  => [ '%' ],
                        'range'       => [ '%' => [ 'min' => -40, 'max' => 40 ] ],
                        'default'     => [ 'unit' => '%', 'size' => 0 ],
                        'device_args' => [
                            'tablet' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                            'mobile' => [ 'default' => [ 'unit' => '%', 'size' => '' ] ],
                        ],
                        'description' => __( 'Negativo = sube (para que sobresalga por arriba). Positivo = baja. Ajustable por dispositivo.', 'hostpv' ),
                        'selectors'   => [
                            '{{WRAPPER}}' => '--hp3-offset-y: {{SIZE}}%;',
                        ],
                    ] );

                    $this->add_control( 'layers', [
                        'label'   => __( 'Número de cajas', 'hostpv' ),
                        'type'    => \Elementor\Controls_Manager::NUMBER,
                        'min'     => 3,
                        'max'     => 14,
                        'default' => 9,
                    ] );

                    $this->add_control( 'color_outer', [
                        'label'   => __( 'Color exterior', 'hostpv' ),
                        'type'    => \Elementor\Controls_Manager::COLOR,
                        'default' => '#4da3ff',
                    ] );

                    $this->add_control( 'color_inner', [
                        'label'   => __( 'Color interior', 'hostpv' ),
                        'type'    => \Elementor\Controls_Manager::COLOR,
                        'default' => '#ffffff',
                    ] );

                    $this->add_control( 'sensitivity', [
                        'label'       => __( 'Amplitud del movimiento', 'hostpv' ),
                        'type'        => \Elementor\Controls_Manager::NUMBER,
                        'min'         => 0.3,
                        'max'         => 2,
                        'step'        => 0.1,
                        'default'     => 1,
                        'description' => __( 'Cuánto giran las cajas. 1 = normal, menos = más sutil, más = más exagerado. Se aplica igual al movimiento automático y al del ratón.', 'hostpv' ),
                    ] );

                    $this->add_control( 'mouse', [
                        'label'        => __( 'Seguir al ratón', 'hostpv' ),
                        'type'         => \Elementor\Controls_Manager::SWITCHER,
                        'label_on'     => __( 'Sí', 'hostpv' ),
                        'label_off'    => __( 'No', 'hostpv' ),
                        'return_value' => 'yes',
                        'default'      => '',
                        'description'  => __( 'Si lo activas, el cursor real manda mientras esté encima; en cuanto se queda quieto (o no hay activado este ajuste), las cajas se mueven solas simulando un ratón invisible dentro de la caja más pequeña.', 'hostpv' ),
                    ] );

                    $this->add_control( 'idle', [
                        'label'     => __( 'Segundos de inactividad antes de volver al movimiento automático', 'hostpv' ),
                        'type'      => \Elementor\Controls_Manager::NUMBER,
                        'min'       => 0.3,
                        'max'       => 4,
                        'step'      => 0.1,
                        'default'   => 1.2,
                        'condition' => [ 'mouse' => 'yes' ],
                    ] );

                    $this->end_controls_section();
                }

                protected function render() {
                    $s = $this->get_settings_for_display();

                    if ( ! self::$assets_printed ) {
                        self::$assets_printed = true;
                        echo <<<'HOSTPV_CAJAS3D_STYLE'
<style>
/* El widget se saca del flujo por completo (position:absolute; inset:0)
   para que nunca ocupe espacio real ni empuje
   a los demás elementos del Container, a la vez que sigue ocupando
   visualmente el 100% del Container (necesario para que las cajas se
   centren bien dentro de él). */
.elementor-widget-hostpv_cajas3d{ position:absolute !important; inset:0 !important; width:100% !important; height:100% !important; max-width:100% !important; margin:0 !important; z-index:0; overflow:hidden; }
.elementor-widget-hostpv_cajas3d:not(.elementor-element-edit-mode){ pointer-events:none !important; }
.elementor-widget-hostpv_cajas3d > .elementor-widget-container{ width:100% !important; height:100% !important; position:relative; }
.e-con:has(> .elementor-widget-hostpv_cajas3d),
.elementor-column:has(.elementor-widget-hostpv_cajas3d){ position:relative; }
.hostpv-cajas3d-stage{
  position:absolute;
  inset:0;
  perspective:1400px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.hostpv-cajas3d-rings{
  position:relative;
  width:var(--hp3-size, 80%);
  /* Sin encoger: es un hijo flexible del escenario, y por encima del 100% el
     navegador lo devolvia al ancho del contenedor. De ahi que el tamano pareciera
     topar en 100 aunque el control llegara a 250. */
  flex:0 0 auto;
  aspect-ratio:420.86/309.85;
  transform-style:preserve-3d;
  transform:translate(var(--hp3-offset-x, 0%), var(--hp3-offset-y, 0%));
}
.hostpv-caja3d-ring{
  position:absolute;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  transform-style:preserve-3d;
  filter:drop-shadow(0 0 6px rgba(120,170,255,.15));
  will-change:transform;
}
.hostpv-caja3d-ring svg{
  width:var(--scale);
  height:var(--scale);
  fill:var(--color);
  opacity:var(--op);
  display:block;
}
@media (prefers-reduced-motion:reduce){
  .hostpv-caja3d-ring{ animation:none!important; }
}
</style>
HOSTPV_CAJAS3D_STYLE;

                        echo <<<'HOSTPV_CAJAS3D_SCRIPT'
<script>
(function () {
  var HOSTPV_VMOUSE_STYLES = {
    smooth:  { moveMin: 1400, moveMax: 2600, holdMin: 0,   holdMax: 300,  range: 0.55, ease: easeInOutSine },
    erratic: { moveMin: 120,  moveMax: 280,  holdMin: 700, holdMax: 1400, range: 1,    ease: easeOutCubic  }
  };
  function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }
  function easeInOutSine(t) { return -(Math.cos(Math.PI * t) - 1) / 2; }

  function makeVirtualMouse() {
    var style = 'smooth';
    var x = 0, y = 0, fromX = 0, fromY = 0, targetX = 0, targetY = 0;
    var phase = 'move', phaseStart = 0, phaseDur = 800;

    function pickTarget(now) {
      var s = HOSTPV_VMOUSE_STYLES[style];
      fromX = x; fromY = y;
      var ang = Math.random() * Math.PI * 2;
      var r = s.range * (0.5 + Math.random() * 0.5);
      targetX = Math.max(-1, Math.min(1, Math.cos(ang) * r));
      targetY = Math.max(-1, Math.min(1, Math.sin(ang) * r));
      phase = 'move';
      phaseStart = now;
      phaseDur = s.moveMin + Math.random() * (s.moveMax - s.moveMin);
    }
    pickTarget(performance.now());

    return function step(now) {
      var s = HOSTPV_VMOUSE_STYLES[style];
      var elapsed = now - phaseStart;
      if (phase === 'move') {
        var t = Math.min(1, elapsed / phaseDur);
        var e = s.ease(t);
        x = fromX + (targetX - fromX) * e;
        y = fromY + (targetY - fromY) * e;
        if (t >= 1) {
          phase = 'hold';
          phaseStart = now;
          phaseDur = s.holdMin + Math.random() * (s.holdMax - s.holdMin);
        }
      } else if (elapsed >= phaseDur) {
        if (Math.random() < 0.35) style = (style === 'smooth' ? 'erratic' : 'smooth');
        pickTarget(now);
      }
      return { x: x, y: y };
    };
  }

  // ── Rendimiento: parar del todo el bucle de animación (ni un frame) si
  //    la pestaña no está visible o la ventana no tiene el foco del SO —
  //    no solo "más lento en segundo plano", sino cero CPU mientras tanto.
  //    Se reengancha con 'visibilitychange'/'focus', sin sondear en bucle. ──
  function shouldAnimate(root) {
    return root.isConnected && !document.hidden && document.hasFocus();
  }

  function initHostpvCajas3D(root) {
    var rings = Array.prototype.slice.call(root.querySelectorAll('.hostpv-caja3d-ring'));
    var N = rings.length;
    if (!N) return;

    var mouseOn = root.dataset.mouse === '1';
    var sensitivity = parseFloat(root.dataset.sensitivity) || 1;
    var idleAfter = (parseFloat(root.dataset.idle) || 1.2) * 1000;

    var mouseX = 0, mouseY = 0, lastMoveTime = performance.now(), mouseActive = false, hasMoved = false;
    if (mouseOn) {
      // El widget es decorativo y va con pointer-events:none (para no
      // bloquear clics reales del sitio que caen "debajo" de él) — eso
      // significa que nunca es el target de un mousemove, así que un
      // listener puesto en el propio widget (root.addEventListener) NUNCA
      // se dispara. Se escucha en window (no le afecta pointer-events de
      // un descendiente) y se calcula a mano si el cursor cae dentro del
      // rectángulo del widget, igual que antes limitaba el propio hit-test
      // del navegador.
      window.addEventListener('mousemove', function (e) {
        var r = root.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) return;
        mouseX = Math.max(-1, Math.min(1, ((e.clientX - r.left) / r.width) * 2 - 1));
        mouseY = Math.max(-1, Math.min(1, ((e.clientY - r.top) / r.height) * 2 - 1));
        lastMoveTime = performance.now();
        hasMoved = true;
      });
    }

    var virtualMouse = makeVirtualMouse();
    var currentY = new Array(N).fill(0);
    var currentX = new Array(N).fill(0);
    var running = false;

    function tick() {
      if (!shouldAnimate(root)) { running = false; return; }

      var now = performance.now();
      var vx, vy;

      if (mouseOn) {
        var idleFor = now - lastMoveTime;
        mouseActive = hasMoved && idleFor < idleAfter;
      }

      if (mouseOn && mouseActive) {
        vx = mouseX; vy = mouseY;
      } else {
        var v = virtualMouse(now);
        vx = v.x; vy = v.y;
      }

      for (var i = 0; i < N; i++) {
        var targetY, targetX, lerpAmt;
        if (i === 0) {
          targetY = vx * 55 * sensitivity;
          targetX = -vy * 18 * sensitivity;
          lerpAmt = (mouseOn && mouseActive) ? 0.14 : 0.1;
        } else {
          targetY = currentY[i - 1] * 0.97;
          targetX = currentX[i - 1] * 0.97;
          lerpAmt = 0.09;
        }
        currentY[i] += (targetY - currentY[i]) * lerpAmt;
        currentX[i] += (targetX - currentX[i]) * lerpAmt;
        rings[i].style.transform = 'rotateY(' + currentY[i] + 'deg) rotateX(' + currentX[i] + 'deg)';
      }
      requestAnimationFrame(tick);
    }

    function maybeStart() {
      if (running || !shouldAnimate(root)) return;
      running = true;
      requestAnimationFrame(tick);
    }

    document.addEventListener('visibilitychange', maybeStart);
    window.addEventListener('focus', maybeStart);
    maybeStart();
  }

  function initAll(scope) {
    var roots = (scope || document).querySelectorAll('.hostpv-cajas3d-stage');
    roots.forEach(function (root) {
      if (root.dataset.hpInit) return;
      root.dataset.hpInit = '1';
      initHostpvCajas3D(root);
    });
  }

  // Elementor vuelve a pintar el widget por AJAX dentro del editor cada vez
  // que cambias un ajuste (sin recargar la página) — "frontend/element_ready"
  // es lo único que avisa de que hay HTML nuevo que inicializar, tanto en
  // el editor como en la web real.
  if (window.jQuery) {
    jQuery(window).on('elementor/frontend/init', function () {
      elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
        var el = $scope && $scope[0];
        if (el) initAll(el);
      });
    });
  } else {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  }
})();
</script>
HOSTPV_CAJAS3D_SCRIPT;
                    }

                    $layers      = max( 3, absint( $s['layers'] ?? 9 ) );
                    $c_outer     = self::hex_to_rgb( $s['color_outer'] ?? '#4da3ff' );
                    $c_inner     = self::hex_to_rgb( $s['color_inner'] ?? '#ffffff' );
                    $mouse       = ! empty( $s['mouse'] ) && $s['mouse'] === 'yes';
                    $sensitivity = esc_attr( $s['sensitivity'] ?? 1 );
                    $idle        = esc_attr( $s['idle'] ?? 1.2 );
                    $d           = \HosTPV::hostpv_caja_d();

                    echo '<div class="hostpv-cajas3d-stage" data-mouse="' . ( $mouse ? '1' : '0' ) . '" data-sensitivity="' . $sensitivity . '" data-idle="' . $idle . '">';
                    echo '<div class="hostpv-cajas3d-rings">';
                    for ( $i = 0; $i < $layers; $i++ ) {
                        $t       = $layers > 1 ? $i / ( $layers - 1 ) : 0;
                        $scale   = round( 100 - $i * ( 60 / max( 1, $layers - 1 ) ), 3 );
                        $opacity = round( 1 - $i * ( 0.5 / max( 1, $layers - 1 ) ), 3 );
                        $color   = self::lerp_rgb( $c_outer, $c_inner, $t );
                        echo '<div class="hostpv-caja3d-ring" style="--scale:' . $scale . '%;--op:' . $opacity . ';--color:' . $color . ';">';
                        echo '<svg viewBox="0 0 420.86 309.85"><path d="' . esc_attr( $d ) . '"/></svg>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }

                // ── Utilidades de color (copia local: este widget vive en su
                //    propia función suelta, no dentro de la clase HosTPV, así
                //    que no puede llamar a sus métodos privados). ──
                private static function hex_to_rgb( $hex ) {
                    $hex = ltrim( (string) $hex, '#' );
                    if ( strlen( $hex ) === 3 ) {
                        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                    }
                    return [
                        hexdec( substr( $hex, 0, 2 ) ),
                        hexdec( substr( $hex, 2, 2 ) ),
                        hexdec( substr( $hex, 4, 2 ) ),
                    ];
                }

                private static function lerp_rgb( $c1, $c2, $t ) {
                    $r = round( $c1[0] + ( $c2[0] - $c1[0] ) * $t );
                    $g = round( $c1[1] + ( $c2[1] - $c1[1] ) * $t );
                    $b = round( $c1[2] + ( $c2[2] - $c1[2] ) * $t );
                    return "rgb($r,$g,$b)";
                }
            }
        }

        $widgets_manager->register( new \HosTPV_Widget_Cajas3D() );
}
