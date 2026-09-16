# HosTPV

Plugin de [Caracool](https://caracool.net) para **hostpv.com**. Reúne las piezas
de marca del sitio —animaciones, widgets de Elementor y arreglos de
maquetación— en un solo sitio, sin dependencias externas.

## Qué trae

| Módulo | Qué hace |
|---|---|
| `hostpv.php` | Panel de ajustes, logo animado como shortcode y widget de Elementor **Cajas 3D** (tamaño, desplazamiento y colores por dispositivo). |
| `hostpv-text-animations.php` | Animaciones de título letra a letra y subrayado a mano sobre el título nativo de Elementor. |
| `hostpv-custom-cursor.php` | Cursor propio: círculo con inversión de color e imán en los enlaces. Apagado en pantallas táctiles. |
| `hostpv-buttons.php` | Relleno que entra al pasar el ratón sobre el botón nativo de Elementor. |
| `hostpv-mobile-menu.php` | Widget de menú móvil: botón de tres rayas y panel a pantalla completa. |
| `hostpv-desbordes.php` | Recorta a los lados la caja inclinada de las cabeceras para que en móvil no se pueda arrastrar la página, dejando salir la diagonal por abajo. |
| `hostpv-updater.php` | Avisa de versiones nuevas publicadas como release en este repo. |
| `inc/caracool-menu.php` | Menú compartido «Caracool». Archivo común a todos los plugins de la casa. |

Cada módulo se registra solo: `hostpv.php` no necesita saber nada de ellos, y
quitar un archivo quita su función sin romper el resto.

## Instalar

Plugins → Añadir plugin → Subir plugin, con el zip de la
[última release](https://github.com/caracoolnet/wp-hostpv/releases/latest).

A partir de la 0.5.0 el plugin comprueba cada seis horas si hay una release
más nueva, así que las siguientes actualizaciones salen en Plugins como las de
cualquier otro.

## El menú de ajustes

Cuelga del menú **Caracool** de la barra lateral, donde aparecen también los
demás plugins de la casa que haya instalados. La URL sigue siendo
`admin.php?page=hostpv`.

## Publicar una versión

1. Subir el número en la cabecera de `hostpv.php` y en `HOSTPV_VERSION`.
2. Añadir el cambio al `changelog` de `hostpv-updater.php`.
3. Comprimir la carpeta `hostpv` (la carpeta, no su contenido suelto) en
   `hostpv.zip`.
4. Crear la release con la etiqueta `v` + número y adjuntar ese zip.

El actualizador busca el zip adjunto. Si no lo encuentra usa el que genera
GitHub, que descomprime en una carpeta con el hash del commit y renombra la
del plugin: por eso conviene adjuntarlo siempre.

## Antes de publicar, el archivo común

`inc/caracool-menu.php` es idéntico, byte a byte, en todos los plugins de
Caracool. Su fuente de verdad es
[caracoolnet/wp-caracool-shared](https://github.com/caracoolnet/wp-caracool-shared):
se compara con esa copia y se sustituye si no coincide.
