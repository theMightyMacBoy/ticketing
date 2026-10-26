<div class="editor" data-editor data-upload="{{ route('laravel-ticket.tickets.attachments.store', $ticket) }}">
    <div class="toolbar" role="toolbar" aria-label="Formatting">
        <button class="quiet" type="button" data-cmd="bold">Bold</button>
        <button class="quiet" type="button" data-cmd="italic">Italic</button>
        <button class="quiet" type="button" data-cmd="insertUnorderedList">List</button>
        <button class="quiet" type="button" data-cmd="insertOrderedList">Numbered</button>
        <button class="quiet" type="button" data-cmd="link">Link</button>
        <button class="quiet" type="button" data-photo>Photo</button>
    </div>
    <div class="sheet" contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ $label }}" data-sheet hidden></div>
    <label class="fallback" for="{{ $id }}">{{ $label }}
        <textarea id="{{ $id }}" name="message"></textarea>
    </label>
    <label class="attach">Attach files
        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
    </label>
    <input data-photo-input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
</div>
@once
    <script>
        (function () {
            function boot() {
                document.querySelectorAll('[data-editor]').forEach(function (editor) {
                    var sheet = editor.querySelector('[data-sheet]');
                    var area = editor.querySelector('textarea');
                    var fallback = editor.querySelector('.fallback');
                    var form = editor.closest('form');

                    if (!sheet || !area) {
                        return;
                    }

                    sheet.hidden = false;

                    if (fallback) {
                        fallback.hidden = true;
                    }

                    try {
                        document.execCommand('styleWithCSS', false, false);
                        document.execCommand('defaultParagraphSeparator', false, 'p');
                    } catch (error) {}

                    function sync() {
                        area.value = sheet.innerHTML;
                    }

                    editor.querySelectorAll('[data-cmd]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            var command = button.getAttribute('data-cmd');
                            sheet.focus();

                            if (command === 'link') {
                                var href = window.prompt('Link address');

                                if (href) {
                                    document.execCommand('createLink', false, href);
                                }
                            } else if (command) {
                                document.execCommand(command, false, null);
                            }

                            sync();
                        });
                    });

                    sheet.addEventListener('input', sync);

                    if (form) {
                        form.addEventListener('submit', sync);
                    }

                    var photo = editor.querySelector('[data-photo]');
                    var picker = editor.querySelector('[data-photo-input]');

                    if (!photo || !picker || !form) {
                        return;
                    }

                    photo.addEventListener('click', function () {
                        picker.click();
                    });

                    picker.addEventListener('change', function () {
                        var file = picker.files && picker.files[0];
                        var token = form.querySelector('input[name=_token]');
                        picker.value = '';

                        if (!file) {
                            return;
                        }

                        var body = new FormData();
                        body.append('file', file);
                        body.append('_token', token ? token.value : '');

                        fetch(editor.getAttribute('data-upload'), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token ? token.value : '',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: body
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('upload');
                            }

                            return response.json();
                        }).then(function (data) {
                            var src = String(data.url || '').replace(/"/g, '');
                            var alt = String(data.name || 'photo').replace(/"/g, '');

                            if (src === '') {
                                throw new Error('upload');
                            }

                            sheet.focus();
                            document.execCommand('insertHTML', false, '<img src="' + src + '" alt="' + alt + '">');
                            sync();
                        }).catch(function () {
                            window.alert('The photo could not be added.');
                        });
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        }());
    </script>
@endonce
