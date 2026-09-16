<?php
/**
 * HosTPV — Actualizaciones desde GitHub Releases
 *
 * Mismo planteamiento que el resto de plugins de la casa: se consulta la
 * release más reciente del repo público y, si su etiqueta es mayor que la
 * versión instalada, se le pasa a WordPress como actualización disponible.
 * Sin librerías externas y sin panel: WordPress ofrece el botón de
 * actualizar donde siempre, en Plugins y en Escritorio → Actualizaciones.
 *
 * La respuesta se guarda 6 horas en un transient, así que la comprobación no
 * cuesta una petición por carga de página. Si GitHub falla, se cachea vacío
 * y se reintenta a las 6 horas en vez de insistir en cada visita.
 *
 * El paquete que se descarga es el zip adjunto a la release (el que subimos
 * a mano, con la carpeta ya nombrada `hostpv`). Si esa release no llevara
 * adjunto, se usa el zipball que genera GitHub, que descomprime en una
 * carpeta con el hash del commit: funciona, pero renombra la carpeta del
 * plugin, así que conviene adjuntar siempre el zip.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HOSTPV_GH_REPO', 'caracoolnet/wp-hostpv' );

add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_file = HOSTPV_BASENAME;
	if ( ! isset( $transient->checked[ $plugin_file ] ) ) {
		return $transient;
	}

	$cache_key = 'hostpv_gh_release';
	$release   = get_transient( $cache_key );

	if ( false === $release ) {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . HOSTPV_GH_REPO . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, [], 6 * HOUR_IN_SECONDS );
			return $transient;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		set_transient( $cache_key, $release ?: [], 6 * HOUR_IN_SECONDS );
	}

	if ( empty( $release['tag_name'] ) ) {
		return $transient;
	}

	$latest = ltrim( $release['tag_name'], 'v' );
	if ( ! version_compare( $latest, HOSTPV_VERSION, '>' ) ) {
		return $transient;
	}

	$zip_url = '';
	foreach ( $release['assets'] ?? [] as $asset ) {
		if ( ! empty( $asset['name'] ) && str_ends_with( $asset['name'], '.zip' ) ) {
			$zip_url = $asset['browser_download_url'];
			break;
		}
	}
	if ( ! $zip_url ) {
		$zip_url = $release['zipball_url'] ?? '';
	}

	$transient->response[ $plugin_file ] = (object) [
		'id'           => 'github.com/' . HOSTPV_GH_REPO,
		'slug'         => HOSTPV_SLUG,
		'plugin'       => $plugin_file,
		'new_version'  => $latest,
		'url'          => 'https://github.com/' . HOSTPV_GH_REPO,
		'package'      => $zip_url,
		'requires'     => '6.0',
		'tested'       => '6.8',
		'requires_php' => '7.4',
	];

	return $transient;
} );

/** La ficha que sale al pulsar «Ver detalles» en la lista de plugins. */
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}
	if ( ( $args->slug ?? '' ) !== HOSTPV_SLUG ) {
		return $result;
	}

	return (object) [
		'name'         => 'HosTPV',
		'slug'         => HOSTPV_SLUG,
		'version'      => HOSTPV_VERSION,
		'author'       => '<a href="https://caracool.net">Caracool</a>',
		'homepage'     => 'https://github.com/' . HOSTPV_GH_REPO,
		'requires'     => '6.0',
		'tested'       => '6.8',
		'requires_php' => '7.4',
		'sections'     => [
			'description' => 'Animaciones de marca de hostpv.com: logo animado como shortcode, widgets de Elementor (cajas 3D y menú móvil), cursor personalizado con inversión de color, animaciones de texto y de botones, y arreglos de maquetación, con panel de ajustes propio.',
			'changelog'   => '<h4>0.5.0</h4><p>El plugin se cuelga del menú compartido «Caracool», junto a los demás plugins de la casa que haya instalados; la URL de ajustes no cambia. Nuevo módulo de desbordes, que recorta a los lados la caja inclinada de las cabeceras para que en móvil no se pueda arrastrar la página en horizontal, dejando la diagonal salir por abajo como siempre. El burger y la equis del menú móvil ya no cogen el rosa que el tema pinta en cualquier botón al pasar el ratón. Las cajas 3D admiten tamaño por dispositivo hasta el 250 % y dejan de encogerse al pasar del 100 %. Y a partir de esta versión el plugin avisa él solo de nuevas versiones publicadas en GitHub.</p><h4>0.4.0</h4><p>Animación de relleno sobre el botón nativo de Elementor y widget de menú móvil a pantalla completa.</p>',
		],
	];
}, 10, 3 );
