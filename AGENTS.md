# AGENTS.md

# Portal de Noticias ERUN

## Propósito

Mantener una aplicación PHP segura, estable y fácil de comprender.

La última versión publicada es la referencia estable. La siguiente versión se
prepara en desarrollo simplificando progresivamente el código y la
organización sin perder funcionalidades ni introducir una arquitectura
compleja.

Prioridades, por este orden:

1. No perder datos ni romper funciones existentes.
2. Corregir errores y vulnerabilidades demostrados.
3. Mantener producción estable.
4. Hacer el código más didáctico y fácil de mantener.
5. Reducir duplicidad y tamaño de los archivos gradualmente.
6. Mejorar rendimiento únicamente con evidencia y mediciones.

---

## Entornos

- Producción: `/var/www/vhosts/erun.es/httpdocs` y `https://erun.es`.
- Desarrollo: `/var/www/vhosts/erun.es/develop.erun.es/httpdocs` y
  `https://develop.erun.es`.

Producción es la fuente oficial de usuarios, noticias, comentarios, uploads y
demás datos reales.

Desarrollo es el entorno obligatorio para:

- la siguiente versión;
- cambios de arquitectura u organización;
- nuevas funcionalidades;
- cambios de base de datos;
- simplificaciones que afecten a varios archivos;
- pruebas con proveedores externos.

En producción solo se realizarán:

- diagnósticos de solo lectura;
- correcciones pequeñas y urgentes autorizadas;
- despliegues previamente verificados;
- operaciones solicitadas expresamente por el usuario.

No analizar ni modificar otros dominios, repositorios, backups o proyectos sin
autorización expresa.

---

## Datos y sincronización

El flujo de código es:

```text
Desarrollo -> GitHub -> Producción
```

El flujo permitido de datos es:

```text
Producción -> copia privada -> anonimización -> Desarrollo
```

Nunca restaurar en producción una base de datos completa procedente de
desarrollo.

Para llevar una nueva versión a producción:

- desplegar el código validado;
- ejecutar migraciones pequeñas, explícitas e idempotentes;
- conservar los datos actuales de producción;
- no copiar usuarios, sesiones, comentarios, noticias o uploads de prueba.

GitHub debe contener código, documentación, estructura y migraciones. Nunca
debe contener bases de datos, uploads, logs, cachés, sesiones, `.env`, claves,
tokens, credenciales o backups con datos reales.

---

## Tecnologías

Conservar las tecnologías existentes:

- PHP 8.3;
- PDO y consultas preparadas;
- HTML5;
- CSS3;
- JavaScript sin frameworks innecesarios;
- PHPMailer;
- Brevo para correo transaccional;
- AEMET como proveedor meteorológico principal;
- Open-Meteo como respaldo meteorológico existente.

No añadir frameworks, librerías o servicios sin una necesidad demostrada y
autorización expresa.

---

## Arquitectura actual

Mantener una arquitectura PHP procedimental, modular y educativa.

No introducir:

- MVC;
- ORM;
- DDD;
- Repository Pattern;
- Service Layer;
- Clean Architecture;
- contenedores de dependencias;
- abstracciones que dificulten seguir el flujo del código.

Cada página debe poder entenderse siguiendo este orden:

```text
1. cargar bootstrap;
2. comprobar permiso;
3. validar entrada;
4. ejecutar una función del módulo;
5. preparar datos;
6. mostrar la vista o redirigir.
```

Organización objetivo, aplicada gradualmente:

```text
admin/          páginas administrativas
public/         páginas públicas
usuario/        área del comentarista
periodista/     área del articulista
privado/        área del colaborador
includes/       configuración y carga común
includes/modules/ lógica funcional reutilizable
includes/helpers/ utilidades pequeñas y genéricas
partials/       fragmentos visuales compartidos
assets/         CSS, JavaScript e imágenes de la interfaz
uploads/        archivos generados por usuarios
storage/        cachés, logs y temporales no públicos
migrations/     cambios versionados de base de datos
docs/           documentación técnica y de despliegue
```

Esta estructura es una dirección de trabajo, no autoriza una reorganización
masiva.

---

## Reglas de simplificación

Simplificar un módulo cada vez.

Preferir:

- funciones cortas con nombres descriptivos;
- parámetros y retornos claros;
- un único lugar para cada regla funcional;
- helpers pequeños solo cuando se reutilicen;
- comentarios que expliquen el motivo, no lo evidente;
- HTML, CSS y JavaScript en sus archivos correspondientes;
- consultas SQL cerca de la función funcional que las utiliza;
- wrappers breves cuando permitan compartir una implementación segura.

Evitar:

- archivos que mezclen autorización, SQL, HTML y JavaScript extensos;
- funciones genéricas con demasiadas responsabilidades;
- duplicar páginas completas entre roles;
- helpers globales sin una finalidad concreta;
- código muerto o archivos de instalación antiguos;
- renombrados masivos;
- cambios estéticos mezclados con cambios de lógica;
- refactorizar código estable solo por preferencia personal.

Dividir un archivo únicamente cuando existan responsabilidades claras. No
fragmentar el proyecto en muchos archivos pequeños sin beneficio didáctico.

No eliminar ni mover un archivo hasta comprobar rutas, includes, formularios,
JavaScript, cron, documentación y referencias Git relacionadas.

---

## Forma de trabajo

Antes de modificar:

1. Leer este archivo completo.
2. Confirmar el entorno y alcance.
3. Revisar el flujo actual y todas sus llamadas directas.
4. Demostrar el problema o la mejora necesaria.
5. Identificar archivos y efectos secundarios.
6. Proponer el cambio mínimo y sus pruebas.
7. Esperar aprobación, salvo autorización autónoma expresa para ese alcance.

Si existen varias soluciones incompatibles, riesgo para datos, dudas sobre el
comportamiento o necesidad de ampliar el alcance, detenerse y preguntar.

Cuando exista autorización autónoma:

- trabajar por lotes pequeños y homogéneos;
- verificar cada lote antes de continuar;
- conservar todos los cambios pendientes;
- detenerse si una prueba falla;
- no ampliar el trabajo a otros entornos o funciones.

---

## Cambios de base de datos

No modificar la estructura directamente sin una migración versionada.

Cada migración debe:

- tener un nombre fechado y descriptivo;
- contener solo el cambio necesario;
- ser idempotente cuando sea posible;
- preservar los datos existentes;
- indicar cómo verificarla;
- indicar cómo revertirla o recuperar desde backup;
- probarse primero sobre una copia en desarrollo.

No introducir datos personales ni contenido de desarrollo en una migración.

---

## Seguridad obligatoria

Mantener siempre:

- consultas preparadas;
- validación de entradas;
- escape o sanitización contextual de salidas;
- autorización por rol y propiedad;
- separación total entre contenido público y privado;
- CSRF en operaciones de modificación;
- POST para acciones destructivas;
- sesiones seguras y regeneración de identificador;
- contraseñas mediante `password_hash()` y `password_verify()`;
- tokens aleatorios, caducables y de un solo uso;
- uploads con MIME real, límites, optimización y nombres generados;
- TLS verificado en conexiones externas;
- límites de tamaño, timeout, caché y protección contra saturación;
- errores internos ocultos al cliente;
- logs sin secretos ni datos innecesarios.

No registrar ni mostrar:

- contraseñas o hashes completos;
- tokens o enlaces con tokens;
- cookies o cabeceras de autenticación;
- claves API;
- credenciales SMTP o de base de datos;
- consultas SQL, trazas o rutas internas al usuario final.

No reducir un control de seguridad para facilitar una prueba.

---

## Correo y proveedores externos

Brevo es el proveedor autorizado para correo transaccional. No sustituirlo ni
modificar sus credenciales sin autorización expresa.

Una respuesta correcta de PHPMailer solo confirma que el servidor SMTP aceptó
inicialmente el mensaje. La entrega final debe comprobarse en Brevo o en el
buzón del destinatario.

Para meteorología:

1. utilizar caché válida;
2. consultar AEMET como fuente principal;
3. utilizar Open-Meteo solo como respaldo;
4. mostrar la fuente real;
5. conservar límites, timeout y bloqueos contra saturación.

No añadir otro proveedor externo sin autorización.

---

## Verificación obligatoria

Después de cada intervención:

1. Mostrar los archivos modificados y el diff.
2. Ejecutar `php -l` en cada PHP modificado.
3. Validar JavaScript modificado con `node --check`.
4. Ejecutar `git diff --check`.
5. Confirmar que no se modificaron archivos ajenos.
6. Probar el flujo afectado en desarrollo.
7. Comprobar respuestas HTTP y ausencia de errores 500.
8. Indicar pruebas realizadas, no realizadas y manuales necesarias.
9. Revisar que el diff no contiene secretos ni datos reales.

Antes de una versión o despliegue:

- validar todo el PHP del proyecto, excluyendo vendor y datos;
- ejecutar auditoría de dependencias;
- comprobar rutas públicas principales;
- comprobar acceso no autorizado a administración y archivos internos;
- probar autenticación y funciones modificadas;
- verificar el procedimiento de reversión.

No considerar terminado un cambio con pruebas fallidas.

---

## Git y GitHub

Conservar siempre los cambios pendientes existentes.

No ejecutar sin autorización:

- `git reset`;
- `git restore`;
- `git clean`;
- checkout destructivo;
- rebase;
- force push;
- reescritura de historial;
- cambio de rama;
- eliminación o movimiento de etiquetas.

Las consultas `git status`, `git diff`, `git log` y `git show` están
permitidas cuando sean necesarias.

### Publicación habitual de cambios correctos

El usuario autoriza publicar automáticamente en GitHub las correcciones
aprobadas que superen todas las verificaciones.

Antes de escribir en GitHub se debe avisar indicando:

- archivos incluidos;
- rama de destino;
- mensaje de commit;
- pruebas superadas.

Después del aviso, sin una segunda confirmación, se podrá:

1. añadir únicamente los archivos autorizados;
2. crear un commit descriptivo;
3. publicar la rama de desarrollo correspondiente.

No publicar si una prueba falla, el alcance no está claro o no pueden separarse
cambios ajenos.

Esta autorización habitual no incluye:

- actualizar `main`;
- crear una versión o etiqueta;
- desplegar en producción;
- forzar un push;
- incluir configuración o datos locales.

Estas acciones requieren autorización expresa en cada versión.

---

## Despliegues

Un despliegue debe partir de un commit validado y etiquetado.

Conservar siempre en cada entorno:

- `.env`;
- credenciales y claves locales;
- conexión de base de datos;
- configuración SMTP;
- configuración AEMET;
- uploads;
- logs;
- cachés;
- backups;
- sesiones y datos temporales.

Desplegar código y migraciones, no una copia completa de datos de desarrollo.

Después del despliegue:

1. comprobar sintaxis;
2. comprobar portada, login y rutas modificadas;
3. comprobar permisos y archivos internos;
4. comprobar el flujo funcional afectado;
5. confirmar la versión y el estado Git;
6. informar de cualquier prueba manual pendiente.

---

## Criterio de finalización de una versión

Una versión estará preparada cuando:

- conserve todas las funciones verificadas de la versión 2;
- los módulos principales tengan responsabilidades comprensibles;
- no existan duplicidades funcionales importantes;
- los archivos grandes se hayan dividido solo donde aporte claridad;
- las rutas públicas permanezcan compatibles;
- las migraciones estén documentadas;
- no existan vulnerabilidades críticas conocidas;
- las pruebas de regresión principales sean repetibles;
- la documentación explique instalación, estructura y despliegue.

La simplicidad se medirá por la facilidad para seguir el flujo y modificarlo de
forma segura, no únicamente por reducir el número de archivos o líneas.
