# WillEdTech Campus · tema de Moodle

Tema hijo de **Boost** que aplica la marca WillEdTech (Brand 3.4) al aula `campus.willedtech.io`.
Componente: `theme_willedtech`. Probado contra el código de Moodle 5.2 y pensado para 5.0 a 5.3.

## Qué hace
- **Marca:** el renderer entrega siempre los lockups oficiales de Campus. Barra superior con `campus-horizontal.svg` (52 px de alto desde 1121 px, 44 px hasta 1120 px) y `campus-compacto.svg` en móvil (36 px). Pantalla de acceso con `campus-vertical.svg`. Favicon: solo la "C" de Campus (`pix/campus-favicon.svg`, propio del tema; el renderer sobrescribe `favicon()`).
- **Tokens:** `scss/willedtech-bootstrap.scss` traduce la marca a Bootstrap 5 (color, tipografía, radios y foco).
- **Tipografías:** Merriweather Sans 800 y Atkinson Hyperlegible, autoalojadas (OFL, licencias en `fonts/`).
- **Acabado:** radios asimétricos 1:3, respuesta al pulsar y foco visible; respeta el movimiento reducido.
- **Banners de sección:** los 25 banners de Brand (§11) en claro y oscuro, en `pix/banners/`, y `style/banners.css` en el SCSS final. El docente pega en la descripción de la sección las dos imágenes (`wt-banner__claro` y `wt-banner__oscuro`) y el tema muestra solo la del modo activo (`html[data-bs-theme="dark"]`). Se enlazan con `/theme/willedtech/pix/banners/<tipo>-<modo>.png`; el HTML de cada uno se copia desde `banners.html` de Brand.
- Todo lo demás (maquetación, plantillas, bloques, iconos, editor) se hereda de Boost sin cambios.

## Qué no hace
- No sobrescribe plantillas de Boost: la barra superior cambió entre 5.2 y 5.3, y así el tema no se rompe al actualizar.
- No cambia el contenido de los cursos ni los colores que elija cada docente.
- Ignora los logos subidos en *Apariencia > Logos*: la marca es siempre la oficial.

## Instalar
1. Copia esta carpeta en `public/theme/willedtech` (en Moodle 5.0 y anteriores, en `theme/willedtech`).
2. Entra como administrador y completa la actualización.
3. *Administración del sitio > Apariencia > Temas*: elige **WillEdTech Campus**.
4. *Página principal > Configuración*: nombre completo **WillEdTech Campus**, nombre corto **Campus**.

## Marca: cómo se actualiza
Los archivos de `pix/` (incluido `pix/banners/`), `style/` y `scss/willedtech-bootstrap.scss` son copias de Brand registradas en `brand.lock.json`. **No se editan aquí.**

```
node tools/brand-verify.mjs                         # la copia está íntegra
node <brand>/tools/brand-sync.mjs plan --app .      # qué cambiaría al actualizar
node <brand>/tools/brand-sync.mjs apply --app .     # copia y registra la versión
```

Tras actualizar, sube `$plugin->version` en `version.php` y purga cachés.

## Refinamiento de acceso y navegación (archivos y lógica)
- `classes/output/core_renderer.php`: `favicon()` devuelve `pix/campus-favicon.svg` (solo la C) sin tocar `pix/favicon.svg` de Brand.
- `scss/post.scss`: logo vertical de acceso a 72 px (antes 112); `#username` y `#password` (incluido su input-group) con ancho completo y 3 rem de alto; botón de acceso a ancho completo; espaciado, hover y foco de la navegación superior.
- Naming: cadenas del tema ya dicen "WillEdTech Campus". "Cursos/Mis cursos" son cadenas del core de Moodle y no se tocan; el nombre del sitio se fija en *Página principal > Configuración* (ver Instalar).

## Mis cursos: tarjetas y gestión rápida (1.1.0)
- `templates/core_course/coursecard.mustache`: pie compacto (entrar · interruptor · menú), sello «Oculto a los estudiantes» y portada tipográfica de marca cuando el curso no tiene imagen.
- `templates/block_myoverview/view-cards.mustache` y `course-action-menu.mustache`: menú ⋮ en dos grupos («Mi vista» y «Gestión del curso») y carga de `theme_willedtech/quickmanage`.
- `amd/src/quickmanage.js`: interruptor de visibilidad, atajos (ocultar/mostrar, editar ajustes, participantes) y filtros Todos · Visibles · Ocultos · Favoritos. `amd/build/quickmanage.min.js` es copia literal (el tema no usa Grunt): si cambias `src`, copia el archivo.
- `db/services.php` + `classes/external/`: `theme_willedtech_course_quickstate` (permisos, solo lectura) y `theme_willedtech_set_course_visibility` (exige `moodle/course:visibility`). Al instalar, completa la actualización para registrarlos.
- Los filtros cuentan las tarjetas cargadas en pantalla; con paginación, solo las de la página.
