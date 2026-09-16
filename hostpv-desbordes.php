<?php
/**
 * HosTPV — Desbordes de maquetación
 *
 * Arregla el desplazamiento lateral que aparece en móvil en toda la web.
 *
 * QUÉ PASA
 * La caja negra inclinada de las cabeceras es un SVG dentro de un widget HTML
 * (`.hp-box-wrap`). Está dibujado para salirse de su sitio a propósito: así la
 * diagonal cruza de lado a lado. En escritorio cabe de sobra, pero en una
 * pantalla de móvil ese SVG mide 900 px y sobresale por los dos lados, de modo
 * que el navegador da a la página 638 px de ancho y se puede arrastrar en
 * horizontal. Pasa en todas las páginas con esa cabecera.
 *
 * POR QUÉ NO SE ARREGLA DESDE EL PANEL
 * Lo que Elementor ofrece en Avanzado → Desbordamiento es "oculto", que recorta
 * por los cuatro lados: quita el desplazamiento lateral pero también se lleva la
 * diagonal, y la cabecera se queda con el borde inferior recto. Hace falta
 * recortar SOLO en horizontal y dejar pasar lo que sobresale por abajo, y eso el
 * panel no lo tiene.
 *
 * CÓMO SE ARREGLA
 * `overflow-x: clip` en el contenedor que lleva ese widget dentro. Se recorta a
 * los lados, la diagonal sigue saliendo por abajo igual que ahora y el ancho de
 * la página vuelve a ser el de la pantalla. Comprobado en Ágora a 390 px: de 638
 * a 375 de ancho, con la cabecera idéntica.
 *
 * Se usa `clip` y no `hidden` a propósito: `hidden` convierte la caja en una zona
 * desplazable y obliga a los dos ejes; `clip` recorta sin más y permite que el
 * otro eje siga siendo visible.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', function () {

	if ( is_admin() ) {
		return;
	}
	?>
<style id="hostpv-desbordes">
/* El contenedor que lleva la caja inclinada de la cabecera recorta a los lados,
   pero deja salir la diagonal por abajo. */
.e-con:has(> .elementor-widget > .elementor-widget-container > .hp-box-wrap){
	overflow-x: clip;
	overflow-y: visible;
}
</style>
	<?php
}, 99 );
