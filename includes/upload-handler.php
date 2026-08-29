<?php
declare(strict_types=1);


/**
 * MANEJADOR DE SUBIDA DE ARCHIVOS
 * Versión simplificada con soporte para video y múltiples directorios
 * 
 * ✅ Soporte para límites de almacenamiento por usuario
 * ✅ Validación de tamaños desde configuración
 */

class UploadHandler {
    private $archivo;
    private $tipo;
    private $tipo_archivo;
    private $errores = [];
    private $nombre_generado;
    private $id_usuario;
    
    public function __construct($archivo, $tipo = 'noticia', $tipo_archivo = 'imagen', $id_usuario = null) {
        $this->archivo = $archivo;
        $this->tipo = $tipo;
        $this->tipo_archivo = $tipo_archivo;
        $this->id_usuario = $id_usuario ?? ($_SESSION['usuario_id'] ?? 0);
    }
    
    /**
     * Obtiene el tamaño máximo permitido según el tipo de archivo
     */
    private function getTamañoMaximo() {
        try {
            $pdo = db();
            
            if ($this->tipo_archivo === 'video') {
                $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'limite_video_mb'");
                $stmt->execute();
                $mb = (int)$stmt->fetchColumn();
                $mb = ($mb > 0) ? $mb : 50; // Default 50MB
                return $mb * 1024 * 1024;
            } elseif ($this->tipo_archivo === 'pdf') {
                $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'limite_pdf_mb'");
                $stmt->execute();
                $mb = (int)$stmt->fetchColumn();
                return (($mb > 0) ? $mb : 20) * 1024 * 1024;
            } else {
                $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'limite_imagen_mb'");
                $stmt->execute();
                $mb = (int)$stmt->fetchColumn();
                $mb = ($mb > 0) ? $mb : 5; // Default 5MB
                return $mb * 1024 * 1024;
            }
        } catch (Exception $e) {
            registrarErrorInterno('UPLOAD.LIMITE.OBTENER', $e);
            return match ($this->tipo_archivo) {
                'video' => MAX_VIDEO_SIZE,
                'pdf' => MAX_PDF_SIZE,
                default => MAX_FILE_SIZE,
            };
        }
    }
    
    public function validar() {
        if ($this->archivo['error'] !== UPLOAD_ERR_OK) {
            if ($this->archivo['error'] === UPLOAD_ERR_NO_FILE) {
                return false; // Archivo opcional
            }
            $this->errores[] = $this->getErrorMessage($this->archivo['error']);
            return false;
        }
        
        if (!is_uploaded_file($this->archivo['tmp_name'])) {
            $this->errores[] = 'Archivo no válido';
            return false;
        }
        
        // ============================================
        // ✅ VALIDAR LÍMITE DE ALMACENAMIENTO DEL USUARIO
        // ============================================
        if ($this->id_usuario > 0 && function_exists('verificarLimiteAlmacenamiento')) {
            $tamañoParaCuota = $this->archivo['size'];
            if ($this->tipo_archivo === 'imagen') {
                $extension = strtolower(pathinfo($this->archivo['name'], PATHINFO_EXTENSION));
                $tamañoOptimizadoMaximo = $extension === 'gif'
                    ? 1024 * 1024
                    : ($this->tipo === 'perfil' ? 200 * 1024 : 300 * 1024);
                $tamañoParaCuota = min($tamañoParaCuota, $tamañoOptimizadoMaximo);
            }

            $verificacion = verificarLimiteAlmacenamiento($this->id_usuario, $tamañoParaCuota);
            if (!$verificacion['permitido']) {
                $this->errores[] = $verificacion['mensaje'];
                return false;
            }
        }
        
        // Validar tamaño según tipo
        $tamaño_maximo = $this->getTamañoMaximo();
        if ($this->archivo['size'] > $tamaño_maximo) {
            $max_mb = round($tamaño_maximo / (1024 * 1024), 1);
            $this->errores[] = "El archivo excede el tamaño máximo de {$max_mb} MB";
            return false;
        }
        
        if ($this->tipo_archivo === 'video') {
            return $this->validarVideo();
        }

        if ($this->tipo_archivo === 'pdf') {
            return $this->validarPdf();
        }

        return $this->validarImagen();
    }
    
    private function validarImagen() {
        $extension = strtolower(pathinfo($this->archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $this->errores[] = 'Formato no permitido. Use: JPG, PNG, GIF, WEBP';
            return false;
        }

        $info = @getimagesize($this->archivo['tmp_name']);
        if ($info === false || empty($info['mime'])) {
            $this->errores[] = 'El archivo no es una imagen válida';
            return false;
        }

        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);
        $maxPixeles = 40_000_000;
        if ($ancho <= 0 || $alto <= 0 || $ancho > intdiv($maxPixeles, $alto)) {
            $this->errores[] = 'La resolución de la imagen supera el máximo permitido de 40 megapíxeles';
            return false;
        }

        $extensionesMime = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];
        if (!isset($extensionesMime[$info['mime']]) || !in_array($extension, $extensionesMime[$info['mime']], true)) {
            $this->errores[] = 'La extensión no coincide con el contenido de la imagen';
            return false;
        }

        return true;
    }
    
    private function validarVideo() {
        $extension = strtolower(pathinfo($this->archivo['name'], PATHINFO_EXTENSION));
        $mimesPermitidos = [
            'mp4' => ['video/mp4'],
            'webm' => ['video/webm'],
            'ogg' => ['video/ogg', 'application/ogg'],
            'mov' => ['video/quicktime'],
        ];

        if (!isset($mimesPermitidos[$extension])) {
            $this->errores[] = 'Formato de video no permitido. Use: MP4, WEBM, OGG, MOV';
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($this->archivo['tmp_name']);
        if (!is_string($mime) || !in_array($mime, $mimesPermitidos[$extension], true)) {
            $this->errores[] = 'La extensión no coincide con el contenido del vídeo';
            return false;
        }
        
        return true;
    }

    private function validarPdf(): bool {
        $extension = strtolower(pathinfo($this->archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $this->errores[] = 'Formato de documento no permitido. Use: PDF';
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($this->archivo['tmp_name']);
        if ($mime !== 'application/pdf') {
            $this->errores[] = 'La extensión no coincide con el contenido del PDF';
            return false;
        }

        $cabecera = file_get_contents($this->archivo['tmp_name'], false, null, 0, 5);
        if ($cabecera !== '%PDF-') {
            $this->errores[] = 'El archivo no contiene un PDF válido';
            return false;
        }

        return true;
    }
    
    /**
 * Comprimir y redimensionar imagen para optimizar almacenamiento
 * @param string $ruta_origen Ruta del archivo temporal
 * @param string $ruta_destino Ruta donde guardar la imagen optimizada
 * @return bool True si se optimizó correctamente
 */
private function optimizarImagen($ruta_origen, $ruta_destino) {
    if (!extension_loaded('imagick')) {
        $this->errores[] = 'No está disponible el optimizador de imágenes';
        return false;
    }

    $info = @getimagesize($ruta_origen);
    if ($info === false || empty($info['mime'])) {
        return false;
    }

    $maxDimension = $this->tipo === 'perfil' ? 300 : 1024;
    $maxBytes = $this->tipo === 'perfil' ? 200 * 1024 : 300 * 1024;

    try {
        $imagen = new Imagick($ruta_origen);

        if ($info['mime'] === 'image/gif') {
            $maxBytes = 1024 * 1024;
            $imagen = $imagen->coalesceImages();

            foreach ($imagen as $fotograma) {
                $ancho = $fotograma->getImageWidth();
                $alto = $fotograma->getImageHeight();
                if ($ancho > $maxDimension || $alto > $maxDimension) {
                    $fotograma->thumbnailImage($maxDimension, $maxDimension, true);
                }
                $fotograma->setImagePage(0, 0, 0, 0);
            }

            $colores = 256;
            for ($intento = 0; $intento < 5; $intento++) {
                foreach ($imagen as $fotograma) {
                    $fotograma->quantizeImage($colores, Imagick::COLORSPACE_RGB, 0, false, false);
                }
                $optimizada = $imagen->optimizeImageLayers();
                $optimizada->writeImages($ruta_destino, true);
                $optimizada->clear();
                clearstatcache(true, $ruta_destino);

                if (is_file($ruta_destino) && filesize($ruta_destino) <= $maxBytes) {
                    $imagen->clear();
                    return true;
                }

                $colores = max(32, (int)($colores / 2));
                foreach ($imagen as $fotograma) {
                    $nuevoAncho = max(1, (int)round($fotograma->getImageWidth() * 0.9));
                    $nuevoAlto = max(1, (int)round($fotograma->getImageHeight() * 0.9));
                    $fotograma->resizeImage($nuevoAncho, $nuevoAlto, Imagick::FILTER_LANCZOS, 1);
                    $fotograma->setImagePage(0, 0, 0, 0);
                }
            }

            $imagen->clear();
            if (is_file($ruta_destino)) {
                unlink($ruta_destino);
            }
            $this->errores[] = 'El GIF no puede optimizarse por debajo de 1 MB';
            return false;
        }

        $imagen->setIteratorIndex(0);
        if (method_exists($imagen, 'autoOrient')) {
            $imagen->autoOrient();
        } elseif (method_exists($imagen, 'autoOrientImage')) {
            $imagen->autoOrientImage();
        }
        if ($imagen->getImageWidth() > $maxDimension || $imagen->getImageHeight() > $maxDimension) {
            $imagen->thumbnailImage($maxDimension, $maxDimension, true);
        }
        $imagen->stripImage();
        $imagen->setImageFormat('webp');
        $imagen->setOption('webp:method', '6');

        $calidad = 82;
        for ($intento = 0; $intento < 12; $intento++) {
            $imagen->setImageCompressionQuality($calidad);
            $imagen->writeImage($ruta_destino);
            clearstatcache(true, $ruta_destino);

            if (is_file($ruta_destino) && filesize($ruta_destino) <= $maxBytes) {
                $imagen->clear();
                return true;
            }

            if ($calidad > 42) {
                $calidad -= 8;
            } else {
                $nuevoAncho = max(1, (int)round($imagen->getImageWidth() * 0.9));
                $nuevoAlto = max(1, (int)round($imagen->getImageHeight() * 0.9));
                $imagen->resizeImage($nuevoAncho, $nuevoAlto, Imagick::FILTER_LANCZOS, 1);
                $calidad = 66;
            }
        }

        $imagen->clear();
        if (is_file($ruta_destino)) {
            unlink($ruta_destino);
        }
        $this->errores[] = 'La imagen no puede optimizarse al tamaño requerido';
        return false;
    } catch (Throwable $e) {
        if (isset($imagen)) {
            $imagen->clear();
        }
        if (is_file($ruta_destino)) {
            unlink($ruta_destino);
        }
        registrarErrorInterno('UPLOAD.IMAGEN.OPTIMIZAR', $e);
        return false;
    }
}

    /**
     * Comprueba la cuota con el tamaño real del archivo ya procesado.
     */
    private function validarCuotaFinal(string $ruta): bool {
        if (
            $this->id_usuario <= 0
            || !function_exists('verificarLimiteAlmacenamiento')
        ) {
            return true;
        }

        clearstatcache(true, $ruta);
        $tamano = is_file($ruta) ? filesize($ruta) : false;
        if ($tamano === false) {
            $this->errores[] = 'No se pudo comprobar el archivo procesado';
            return false;
        }

        $verificacion = verificarLimiteAlmacenamiento(
            $this->id_usuario,
            (int) $tamano
        );
        if (!$verificacion['permitido']) {
            $this->errores[] = $verificacion['mensaje'];
            return false;
        }

        return true;
    }
    
    public function subir() {
        if ($this->archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No hay archivo
        }
        
        if (!$this->validar()) {
            return false;
        }
        
        // ===== DETERMINAR DIRECTORIO SEGÚN EL TIPO =====
        switch ($this->tipo) {
            case 'noticia':
                $directorio = UPLOAD_NOTICIAS;
                break;
            case 'perfil':
                $directorio = UPLOAD_PERFILES;
                break;
            case 'comentario':
                $directorio = UPLOAD_COMENTARIOS;
                break;
            case 'editor':
                $directorio = UPLOADS_PATH . 'editor' . DIRECTORY_SEPARATOR;
                break;
            default:
                $directorio = UPLOADS_PATH;
        }
        
        // Asegurar que el directorio existe
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        // Generar nombre
        $extensionOriginal = strtolower(pathinfo($this->archivo['name'], PATHINFO_EXTENSION));
        $extension = $this->tipo_archivo === 'imagen' && $extensionOriginal !== 'gif'
            ? 'webp'
            : $extensionOriginal;
        
        // Prefijo según tipo
        $prefijo = 'img_';
        if ($this->tipo_archivo === 'video') {
            $prefijo = 'vid_';
        } elseif ($this->tipo_archivo === 'pdf') {
            $prefijo = 'pdf_';
        } elseif ($this->tipo === 'perfil') {
            $prefijo = 'avatar_';
        }
        
        $nombre_base = $prefijo . uniqid() . '_' . time();
        $this->nombre_generado = $nombre_base . '.' . $extension;
        
        // Evitar sobreescritura
        $contador = 1;
        while (file_exists($directorio . $this->nombre_generado)) {
            $this->nombre_generado = $nombre_base . "_$contador." . $extension;
            $contador++;
        }
        
        $ruta_destino = $directorio . $this->nombre_generado;
        
        // Si es imagen, optimizar antes de guardar
        $es_imagen = in_array($extensionOriginal, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        
        if ($es_imagen && $this->tipo_archivo !== 'video') {
            // Optimizar imagen
            if ($this->optimizarImagen($this->archivo['tmp_name'], $ruta_destino)) {
                if (!$this->validarCuotaFinal($ruta_destino)) {
                    unlink($ruta_destino);
                    return false;
                }
                chmod($ruta_destino, 0644);
                return $this->nombre_generado;
            }
        } else {
            // Para videos o si no es imagen, mover normal
            if (move_uploaded_file($this->archivo['tmp_name'], $ruta_destino)) {
                if (!$this->validarCuotaFinal($ruta_destino)) {
                    unlink($ruta_destino);
                    return false;
                }
                chmod($ruta_destino, 0644);
                return $this->nombre_generado;
            }
        }
        
        $this->errores[] = "Error al mover el archivo";
        return false;
    }
    
    public function getErrores() {
        return $this->errores;
    }
    
    private function getErrorMessage($codigo) {
        $mensajes = [
            UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño máximo",
            UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño máximo del formulario",
            UPLOAD_ERR_PARTIAL => "El archivo se subió parcialmente",
            UPLOAD_ERR_NO_FILE => "No se subió ningún archivo",
            UPLOAD_ERR_NO_TMP_DIR => "Falta la carpeta temporal",
            UPLOAD_ERR_CANT_WRITE => "Error al escribir el archivo",
            UPLOAD_ERR_EXTENSION => "Subida detenida por una extensión de PHP"
        ];
        
        return $mensajes[$codigo] ?? "Error desconocido";
    }
}

function detectarTipoMultimediaSubido(array $archivo): ?string
{
    $extension = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));

    if (in_array($extension, ALLOWED_EXTENSIONS, true)) {
        return 'imagen';
    }
    if (in_array($extension, ALLOWED_VIDEO_EXTENSIONS, true)) {
        return 'video';
    }
    if (in_array($extension, ALLOWED_PDF_EXTENSIONS, true)) {
        return 'pdf';
    }

    return null;
}

/**
 * Función helper para subir archivos
 */
function subirArchivo($archivo, $tipo, $tipo_archivo = 'imagen', $id_usuario = null) {
    $upload = new UploadHandler($archivo, $tipo, $tipo_archivo, $id_usuario);
    $resultado = $upload->subir();
    
    if ($resultado === false) {
        $_SESSION['errores_upload'] = $upload->getErrores();
    }
    
    return $resultado;
}
