        </div>
    </div>
    <script>
        // TinyMCE initialisieren
        if (document.querySelector('.tinymce-editor')) {
            tinymce.init({
                selector: '.tinymce-editor',
                language: 'de',
                height: 500,
                menubar: 'file edit view insert format tools table',
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | forecolor backcolor | removeformat | code fullscreen',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.6; }',
                images_upload_url: '/admin.php?action=upload_tinymce',
                images_upload_credentials: true,
                automatic_uploads: true,
                file_picker_types: 'image',
                relative_urls: false,
                remove_script_host: true,
            });
        }

        // Mobile Sidebar Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('admin-sidebar');
            if (window.innerWidth <= 768) {
                const toggle = document.createElement('button');
                toggle.className = 'btn btn-primary';
                toggle.textContent = 'Menü';
                toggle.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:300;';
                toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
                document.body.appendChild(toggle);
            }
        });
    </script>
</body>
</html>
