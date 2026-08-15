// Configuración de TinyMCE para Portal News
// Permite: subida local de imágenes, inserción de imágenes por URL, videos externos
function initTinyMCE(selector) {
    tinymce.init({
        selector: selector,
        height: 500,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help | image media link',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; }',
        
        // ========================================
        // SUBIDA LOCAL DE IMÁGENES (ya funciona)
        // ========================================
        images_upload_url: '/ajax/upload-editor-image.php',
        automatic_uploads: true,
        file_picker_types: 'image media',
        
        // ========================================
        // PERMITIR IMÁGENES Y VIDEOS POR URL EXTERNA
        // ========================================
        image_advtab: true,  // Muestra pestaña avanzada para URL externa
        media_advtab: true,   // Muestra pestaña avanzada para videos externos
        
        // ========================================
        // MANEJADOR PARA SUBIDA LOCAL (ya existente)
        // ========================================
        images_upload_handler: function (blobInfo, success, failure, progress) {
            var xhr, formData;
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', '/ajax/upload-editor-image.php');

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable && typeof progress === 'function') {
                    progress(e.loaded / e.total * 100);
                }
            };

            xhr.onload = function() {
                var json;

                if (xhr.status === 403) {
                    failure('No tienes permiso para subir imágenes', { remove: true });
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    try {
                        json = JSON.parse(xhr.responseText);
                    } catch (error) {
                        json = null;
                    }
                    failure(json && typeof json.error === 'string'
                        ? json.error
                        : 'Error al subir la imagen (HTTP ' + xhr.status + ')');
                    return;
                }

                try {
                    json = JSON.parse(xhr.responseText);
                } catch (error) {
                    failure('Respuesta inválida al subir la imagen');
                    return;
                }

                if (!json || typeof json.location !== 'string') {
                    failure('Error al subir la imagen');
                    return;
                }

                success(json.location);
            };

            xhr.onerror = function() {
                failure('Error de conexión');
            };

            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('csrf_token', csrfMeta ? csrfMeta.content : '');
            xhr.send(formData);
        },
        
        // ========================================
        // MANEJADOR PARA INSERCIÓN POR URL (imágenes y videos)
        // ========================================
        file_picker_callback: function(callback, value, meta) {
            // Para imágenes
            if (meta.filetype === 'image') {
                var input = document.createElement('input');
                input.setAttribute('type', 'text');
                input.setAttribute('placeholder', 'Pega la URL de la imagen (https://...)');
                input.style.width = '300px';
                input.style.padding = '8px';
                input.style.margin = '10px';
                input.style.border = '1px solid #ccc';
                input.style.borderRadius = '4px';
                
                var confirmButton = document.createElement('button');
                confirmButton.textContent = 'Insertar imagen';
                confirmButton.style.padding = '8px 16px';
                confirmButton.style.margin = '10px';
                confirmButton.style.backgroundColor = '#4CAF50';
                confirmButton.style.color = 'white';
                confirmButton.style.border = 'none';
                confirmButton.style.borderRadius = '4px';
                confirmButton.style.cursor = 'pointer';
                
                var cancelButton = document.createElement('button');
                cancelButton.textContent = 'Cancelar';
                cancelButton.style.padding = '8px 16px';
                cancelButton.style.margin = '10px';
                cancelButton.style.backgroundColor = '#ccc';
                cancelButton.style.color = '#333';
                cancelButton.style.border = 'none';
                cancelButton.style.borderRadius = '4px';
                cancelButton.style.cursor = 'pointer';
                
                var container = document.createElement('div');
                container.style.textAlign = 'center';
                container.appendChild(input);
                container.appendChild(confirmButton);
                container.appendChild(cancelButton);
                
                var dialog = window.top ? window.top.document.body : document.body;
                var modal = document.createElement('div');
                modal.style.position = 'fixed';
                modal.style.top = '50%';
                modal.style.left = '50%';
                modal.style.transform = 'translate(-50%, -50%)';
                modal.style.background = 'white';
                modal.style.padding = '20px';
                modal.style.borderRadius = '8px';
                modal.style.boxShadow = '0 0 20px rgba(0,0,0,0.3)';
                modal.style.zIndex = '10000';
                modal.style.minWidth = '350px';
                modal.appendChild(container);
                
                dialog.appendChild(modal);
                
                confirmButton.onclick = function() {
                    var url = input.value.trim();
                    if (url) {
                        callback(url);
                    }
                    dialog.removeChild(modal);
                };
                
                cancelButton.onclick = function() {
                    dialog.removeChild(modal);
                };
                
                input.focus();
            }
            
            // Para videos (opcional, TinyMCE ya permite pegar URL de YouTube/Vimeo)
            if (meta.filetype === 'media') {
                // El plugin 'media' ya permite insertar videos por URL
                // Solo mostramos un mensaje informativo
                console.log('Para insertar videos, usa el botón "Insertar video" y pega la URL de YouTube, Vimeo, etc.');
            }
        },
        
        // ========================================
        // CONFIGURACIÓN DE ENLACES
        // ========================================
        relative_urls: false,
        remove_script_host: false,
        convert_urls: true,
        
        // ========================================
        // PERSONALIZACIÓN
        // ========================================
        branding: false,
        elementpath: true,
        resize: true,
        
        // ========================================
        // CONFIGURACIÓN PARA MÓVILES
        // ========================================
        mobile: {
            menubar: true,
            toolbar: 'undo redo | bold italic | bullist numlist | link image media'
        },
        
        // ========================================
        // INICIALIZACIÓN
        // ========================================
        setup: function(editor) {
            editor.on('init', function() {
                console.log('TinyMCE inicializado correctamente');
                console.log('Puedes: subir imágenes localmente o insertar por URL');
            });
        }
    });
}
