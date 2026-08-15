/**
 * Configuración COMPLETA de TinyMCE para comentarios
 * Similar al editor de noticias pero sin imágenes
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Inicializando editor de comentarios...');
    
    const textarea = document.getElementById('comentario-editor');
    if (!textarea) {
        
        return;
    }
    
    // Si ya hay un editor, lo removemos
    if (tinymce.get('comentario-editor')) {
        tinymce.get('comentario-editor').remove();
    }
    
    console.log('✅ Inicializando TinyMCE con configuración COMPLETA');
    
    tinymce.init({
        selector: '#comentario-editor',
        height: 300,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'table', 'help', 'wordcount', 'emoticons'
        ],
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link emoticons | removeformat | help',
        toolbar_mode: 'sliding',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
        branding: false,
        elementpath: true,
        statusbar: true,
        placeholder: 'Escribe tu comentario...',
        license_key: 'gpl',
        
        // Configuración de seguridad (sin imágenes)
        invalid_elements: 'script,iframe,object,embed,form,input,button,img,image,media',
        extended_valid_elements: 'a[href|target|rel]',
        
        // Configuración de enlaces
        link_assume_external_targets: true,
        default_link_target: '_blank',
        
        // Permitir colores de texto y fondo
        color_map: [
            '#000000', 'Black',
            '#FF0000', 'Red',
            '#00FF00', 'Green',
            '#0000FF', 'Blue',
            '#FFFF00', 'Yellow',
            '#FF00FF', 'Magenta',
            '#00FFFF', 'Cyan',
            '#FFFFFF', 'White'
        ],
        
        setup: function(editor) {
            editor.on('init', function() {
                console.log('✅ Editor COMPLETO listo');
            });
            
            // Contador de caracteres
            editor.on('keyup', function() {
                const content = editor.getContent({format: 'text'});
                const length = content.length;
                const maxChars = 1000;
                
                let counter = document.getElementById('char-counter');
                if (!counter) {
                    counter = document.createElement('div');
                    counter.id = 'char-counter';
                    counter.style.cssText = 'text-align: right; font-size: 12px; color: #666; margin-top: 5px;';
                    editor.getElement().parentNode.appendChild(counter);
                }
                
                if (length > maxChars) {
                    counter.innerHTML = '<span style="color: red;">⚠️ Has excedido el límite de ' + maxChars + ' caracteres (' + length + ')</span>';
                    const submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    counter.innerHTML = '📝 ' + length + '/' + maxChars + ' caracteres';
                    const submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }
    });
});
