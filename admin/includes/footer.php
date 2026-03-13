        </div>
    </div>
    <script>
        // Quill Editor initialisieren
        document.addEventListener('DOMContentLoaded', function() {
            const editorContainer = document.getElementById('quill-editor');
            const hiddenInput = document.getElementById('content-hidden');

            if (editorContainer && hiddenInput) {
                const quill = new Quill('#quill-editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, 4, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'align': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            ['blockquote', 'code-block'],
                            ['link', 'image'],
                            ['clean']
                        ]
                    }
                });

                // Vorhandenen Inhalt laden
                quill.root.innerHTML = hiddenInput.value;

                // Bild-Upload Handler
                quill.getModule('toolbar').addHandler('image', function() {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/*';
                    input.onchange = async function() {
                        const file = input.files[0];
                        if (!file) return;
                        const formData = new FormData();
                        formData.append('file', file);
                        try {
                            const res = await fetch('/admin.php?action=upload_tinymce', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await res.json();
                            if (data.location) {
                                const range = quill.getSelection(true);
                                quill.insertEmbed(range.index, 'image', data.location);
                            }
                        } catch(e) {
                            alert('Upload fehlgeschlagen.');
                        }
                    };
                    input.click();
                });

                // Vor dem Absenden HTML in hidden input übertragen
                document.querySelector('form').addEventListener('submit', function() {
                    hiddenInput.value = quill.root.innerHTML;
                });
            }

            // Mobile Sidebar Toggle
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
