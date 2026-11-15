# WillEdTech Campus · tema de Moodle

Tema hijo de **Boost** que aplica la marca WillEdTech (Brand 3.2) al aula `campus.willedtech.io`.
Componente: `theme_willedtech`. Probado contra el código de Moodle 5.2 y pensado para 5.0 a 5.3.

## Qué hace
- **Marca:** el renderer entrega siempre los lockups oficiales de Campus. Barra superior con `campus-horizontal.svg` (52 px de alto desde 1121 px, 44 px hasta 1120 px) y `campus-compacto.svg` en móvil (36 px). Pantalla de acceso con `campus-vertical.svg`. Favicon: la W oficial.
- **Tokens:** `scss/willedtech-bootstrap.scss` traduce la marca a Bootstrap 5 (color, tipografía, radios y foco).
- **Tipografías:** Merriweather Sans 800 y Atkinson Hyperlegible, autoalojadas (OFL, licencias en `fonts/`).
- **Acabado:** radios asimétricos 1:3, respuesta al pulsar y foco visible; respeta el movimiento reducido.
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
Los archivos de `pix/`, `style/` y `scss/willedtech-bootstrap.scss` son copias de Brand registradas en `brand.lock.json`. **No se editan aquí.**

```
node tools/brand-verify.mjs                         # la copia está íntegra
node <brand>/tools/brand-sync.mjs plan --app .      # qué cambiaría al actualizar
node <brand>/tools/brand-sync.mjs apply --app .     # copia y registra la versión
```

Tras actualizar, sube `$plugin->version` en `version.php` y purga cachés.
