<?php
declare(strict_types=1);


/**
 * FUNCIONES DE CLASIFICACIÓN AUTOMÁTICA RSS
 *
 * Detecta el tema (categoría) de una noticia RSS a partir de su contenido
 * usando reglas de palabras clave.
 */

/**
 * Detecta la categoría temática de una noticia RSS.
 *
 * Prioridad de búsqueda:
 * 1. Categoría predefinida de la fuente RSS (si existe)
 * 2. Título (mayor peso)
 * 3. Descripción / extracto
 * 4. Contenido completo
 *
 * @param string $titulo         Título de la noticia
 * @param string $descripcion    Descripción o extracto del RSS
 * @param string $contenido      Contenido completo (puede estar vacío)
 * @param int|null $idCategoriaFuente ID de categoría predefinida en la fuente RSS
 * @param PDO $pdo               Conexión PDO para consultar categorías
 * @return int ID de la categoría detectada, o 0 si no se pudo determinar
 */
function detectarTemaRss(
    string $titulo,
    string $descripcion,
    string $contenido,
    ?int $idCategoriaFuente,
    PDO $pdo
): int {
    // 1. Si la fuente tiene categoría predefinida y es válida, usarla
    if ($idCategoriaFuente !== null && $idCategoriaFuente > 0) {
        $stmt = $pdo->prepare(
            'SELECT id_categoria FROM categorias WHERE id_categoria = ? AND activa = 1 LIMIT 1'
        );
        $stmt->execute([$idCategoriaFuente]);
        if ($stmt->fetchColumn()) {
            return $idCategoriaFuente;
        }
    }

    // Preparar texto para análisis (todo en minúsculas, sin tildes)
    $textoCompleto = mb_strtolower(
        $titulo . ' ' . $descripcion . ' ' . $contenido,
        'UTF-8'
    );
    $textoCompleto = strtr($textoCompleto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'ñ', 'ü' => 'u',
    ]);

    $tituloLower = mb_strtolower($titulo, 'UTF-8');
    $tituloLower = strtr($tituloLower, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);

    // Definir diccionario de temas con palabras clave y pesos
    $temas = obtenerDiccionarioTemas();

    // Evaluar cada tema
    $mejorTema = 0;
    $mejorPuntuacion = 0;

    foreach ($temas as $idCategoria => $config) {
        $puntuacion = 0;

        foreach ($config['palabras'] as $palabra) {
            // Peso mayor si aparece en el título
            if (mb_stripos($tituloLower, $palabra, 0, 'UTF-8') !== false) {
                $puntuacion += $config['peso_titulo'] ?? 3;
            }
            // Peso estándar en el resto del texto
            if (mb_stripos($textoCompleto, $palabra, 0, 'UTF-8') !== false) {
                $puntuacion += $config['peso_texto'] ?? 1;
            }
        }

        if ($puntuacion > $mejorPuntuacion) {
            $mejorPuntuacion = $puntuacion;
            $mejorTema = $idCategoria;
        }
    }

    // Umbral mínimo para considerar que hay coincidencia
    if ($mejorPuntuacion < 2) {
        return 0;
    }

    return $mejorTema;
}

/**
 * Obtiene el diccionario de temas con palabras clave y pesos.
 * Las categorías se resuelven por slug para ser independientes de IDs.
 *
 * @return array<int, array{slug: string, palabras: string[], peso_titulo: int, peso_texto: int}>
 */
function obtenerDiccionarioTemas(): array
{
    return [
        2 => [ // Política
            'slug' => 'politica',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'gobierno', 'elecciones', 'parlamento', 'partido', 'ministro',
                'ley', 'senado', 'congreso', 'alcalde', 'presidente', 'diputado',
                'politica', 'electoral', 'votacion', 'referendum', 'coalicion',
                'oposicion', 'mocion', 'decreto', 'legislatura', 'campaa',
                'voto', 'candidato', 'conseller', 'conselleiro',
            ],
        ],
        6 => [ // Economía
            'slug' => 'economia',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'inflacion', 'empleo', 'empresas', 'mercado', 'euros', 'pib',
                'banco', 'deuda', 'fiscal', 'impuestos', 'economico', 'economia',
                'facturacion', 'beneficios', 'perdidas', 'bolsa', 'inversion',
                'presupuesto', 'salario', 'sueldo', 'industria', 'comercio',
                'exportacion', 'importacion', 'deficit', 'superavit',
            ],
        ],
        7 => [ // Salud / Sanidad
            'slug' => 'salud',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'hospital', 'salud', 'medicos', 'pandemia', 'vacuna',
                'enfermedad', 'sanitario', 'consulta', 'enfermera', 'doctor',
                'clinica', 'tratamiento', 'diagnostico', 'cirugia', 'farmaco',
                'epidemia', 'covid', 'gripe', ' cancer ', 'tumor',
                'sistema sanitar', 'servicio sanitar',
            ],
        ],
        5 => [ // Cultura
            'slug' => 'cultura',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'cultural', 'cultura', 'arte', 'literatura', 'cine', 'espectaculo',
                'teatro', 'musica', 'exposicion', 'museo', 'libro', 'novela',
                'autor', 'editorial', 'festival', 'concierto', 'ópera',
                'pintura', 'escultura', 'baile', 'danza', 'pelicula',
                'premio', 'galardon', 'certamen',
            ],
        ],
        1 => [ // Deportes
            'slug' => 'deportes',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'futbol', 'baloncesto', 'liga', 'champions', 'seleccion',
                'deport', 'jugador', 'entrenador', 'partido', 'gol',
                'equipo', 'copa', 'mundial', 'olimpic', 'tenis', 'balonmano',
                'ciclismo', 'natacion', 'atletismo', 'motor', 'formula 1',
                'tenis', 'ranking', 'competicion', 'mundial', 'europeo',
                'rafael nadal', 'carlos alcaraz', 'messi', 'ronaldo',
            ],
        ],
        3 => [ // Tecnología
            'slug' => 'tecnologia',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'tecnologia', 'digital', 'inteligencia artificial', 'ia ',
                'robot', 'software', 'hardware', 'internet', 'ciberseguridad',
                'app', 'aplicacion', 'smartphone', 'movil', 'ordenador',
                'datos', 'algoritmo', 'blockchain', 'criptomoneda',
                'startup', 'innovacion', '5g', 'cloud', 'computing',
                'apple', 'google', 'microsoft', 'openai', 'meta',
            ],
        ],
        4 => [ // Ciencia
            'slug' => 'ciencia',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'ciencia', 'cientifico', 'investigacion', 'descubrimiento',
                'estudio', 'universidad', 'laboratorio', 'espacio', 'nasa',
                'astronomia', 'planeta', 'clima', 'evolucion', 'genetica',
                'fisica', 'quimica', 'biologia', 'neurociencia',
                'experimento', 'teoria', 'premio nobel', 'galileo',
            ],
        ],
        8 => [ // Medio Ambiente
            'slug' => 'medio-ambiente',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'medio ambiente', 'ecologia', 'sostenibilidad', 'contaminacion',
                'cambio climatico', 'emisiones', 'renovable', 'energia',
                'biodiversidad', 'deforestacion', 'reciclaje', 'residuos',
                'océano', 'bosque', 'especie', 'proteccion', 'natural',
                'lluvia', 'sequia', 'inundacion', 'incendio forestal',
            ],
        ],
        13 => [ // Sucesos
            'slug' => 'sucesos',
            'peso_titulo' => 5,
            'peso_texto' => 1,
            'palabras' => [
                'suceso', 'accidente', 'incendio', 'victima', 'muerto',
                'muerte', 'asesinato', 'robos', 'robo', 'secuestro',
                'detenido', 'policia', 'guardia civil', 'sociedad',
                'emergencia', 'rescate', 'averia', 'colapso',
            ],
        ],
        14 => [ // Justicia
            'slug' => 'justicia',
            'peso_titulo' => 5,
            'peso_texto' => 1,
            'palabras' => [
                'justicia', 'juez', 'tribunal', 'sentencia', 'condena',
                'absolucion', 'juicio', 'fiscal', 'audiencia', 'denuncia',
                'procesado', 'imputado', 'recurso', 'apelacion', 'auto',
                'orden judicial', 'extradicion', 'indulto',
            ],
        ],
        15 => [ // Corrupción
            'slug' => 'corrupcion',
            'peso_titulo' => 5,
            'peso_texto' => 1,
            'palabras' => [
                'corrupcion', 'soborno', 'cohecho', 'malversacion',
                'prevaricacion', 'trama', 'fraude', 'evasion', 'blanqueo',
                'opaca', 'comision', 'irregularidad', 'desvios',
                'caja b', 'financiacion ilegal',
            ],
        ],
        12 => [ // Noticias externas
            'slug' => 'noticias-externas',
            'peso_titulo' => 3,
            'peso_texto' => 1,
            'palabras' => [
                'fuente externa', 'agencia', 'wire', 'reuters', 'efe',
                'press association', 'afp',
            ],
        ],
        11 => [ // Misterios
            'slug' => 'misterios',
            'peso_titulo' => 4,
            'peso_texto' => 1,
            'palabras' => [
                'misterio', 'enigma', 'paranormal', 'extraterrestre',
                'ovni', 'ufologia', 'conspiracion', 'leyenda',
                'arqueologia', 'perdido', 'desaparecido', 'desenterrado',
            ],
        ],
        16 => [ // Opinión
            'slug' => 'opinion',
            'peso_titulo' => 3,
            'peso_texto' => 1,
            'palabras' => [
                'opinion', 'columna', 'editorial', 'comentario', 'analisis',
                'reflexion', 'kritik', 'kritica', 'perspectiva',
                ' articulo de opinion ',
            ],
        ],
    ];
}

/**
 * Obtiene todas las categorías activas como array indexado por ID.
 *
 * @param PDO $pdo
 * @return array<int, array{id_categoria: int, nombre_categoria: string, slug_categoria: string}>
 */
function obtenerCategoriasActivas(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id_categoria, nombre_categoria, slug_categoria
         FROM categorias WHERE activa = 1 ORDER BY orden, nombre_categoria"
    );
    $resultado = [];
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $resultado[(int) $fila['id_categoria']] = $fila;
    }
    return $resultado;
}

/**
 * Obtiene todas las regiones activas como array indexado por ID.
 *
 * @param PDO $pdo
 * @return array<int, array{id_region: int, nombre: string, slug: string}>
 */
function obtenerRegionesActivas(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id_region, nombre, slug
         FROM regiones WHERE activa = 1 ORDER BY nombre"
    );
    $resultado = [];
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $resultado[(int) $fila['id_region']] = $fila;
    }
    return $resultado;
}
