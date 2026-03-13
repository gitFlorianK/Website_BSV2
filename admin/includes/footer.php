    </main>
</div>
<script>
if (document.querySelector('.tinymce-editor')) {
    tinymce.init({
        selector: '.tinymce-editor',
        skin: 'oxide-dark',
        content_css: 'dark',
        height: 500,
        menubar: true,
        plugins: 'lists link image table code fullscreen media preview',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media | table | code fullscreen',
        images_upload_url: '/admin.php?action=upload_image_tinymce',
        automatic_uploads: true,
        file_picker_types: 'image',
        relative_urls: false,
        remove_script_host: true,
    });
}
</script>
</body>
</html>
