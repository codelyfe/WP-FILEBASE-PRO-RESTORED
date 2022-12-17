<?php
/**
 * Created by PhpStorm.
 * User: Fabian
 * Date: 31.07.2017
 * Time: 11:24
 */

namespace WPFB;


class EasyUploader
{
    var $id;

    public function __construct()
    {
        $this->id = wp_generate_password(8, false, false);
    }

    public function render()
    {
        wpfb_loadclass('Output', 'Admin');

        ?>
        <style>
            .easy-uploader {
                border: 0.25em dashed #b4b9be;
                background: white;
                text-align: center;
                font-size: 16px;
                padding: 0.5em 1em;
                border-radius: 0.25em;
                overflow: hidden;
                position: relative;
                height: 7em;
            }

            .easy-uploader div.page {
                width: 100%;
                position: absolute;
                top: 0;
                left: 0;
                -webkit-transition: left 0.3s;
                transition: left 0.3s;
            }

            .easy-uploader div.page p {
                margin: 1em 1em;
            }

            .easy-uploader div.page-upload {
                left: -110%;
            }

            .easy-uploader div.page-category {
                left: +110%;
            }

            .easy-uploader div.page-uris {
                left: +110%;
            }

            .easy-uploader div.page.active {
                left: 0%;
            }

        </style>
        <div class="easy-uploader" id="easy-uploader-<?php echo esc_attr($this->id); ?>" tabindex="0">

            <div class="page page-upload active">
                <input class="paste-input" type="text" placeholder="📁Drop / 📋Paste files, folders, links "
                       style="margin: 0 1em; width: 100%;  max-width: 20em; font-size: inherit; border: none; box-shadow: none; padding: 2em 0;">


                <input class="file-input" type="file" name="file" style="display: none;" multiple="multiple">
                <input class="directory-input" type="file" name="directory" style="display: none;" multiple="multiple"
                       webkitdirectory mozdirectory msdirectory odirectory directory>
                <!-- tested in chrome, firefox -->
                <!--
                paste: files, links (link lists, one link each line), folders
                drop: files, folders, links

                on paste:

                split downloads,
                check url with HEAD request, let user choose wether to sideload or redirect

                it can event continue broken uploads!
                adaptive chunk size!

                TODO:-
                - if uploading a directory, need to ask for the category
                - if pasted a url, need to ask wether to sideload or just redirect
                - test safari

                -->
                <div style="display: inline-block; white-space: nowrap;">
                    <button class="file-input-btn" data-for="file-input">Select Files</button>
                    <button class="file-input-btn" data-for="directory-input">Select Directory</button>
                </div>

            </div>

            <div class="page page-category" style="display: none;">
                <p></p>
                <select name="category" class="postform wpfb-cat-select">
                    <!-- <option selected></option> -->
                    <?php echo \WPFB_Output::CatSelTree(array('add_cats' => true, 'check_add_perm' => true, 'selected' => -1, 'none_label' => __('Upload to Root', 'wp-filebase'))); ?>
                </select> &nbsp;
                <button>OK</button>
            </div>

            <div class="page page-uris" style="display: none;">
                <p></p>

                <button>Copy</button>
                <button>Redirect</button>
            </div>
        </div>
        <script type="application/javascript">
            (function ($) {
                var chunkFreqHz = 1/20;
                var chunkMinSize = 1024*200;

                var id = '<?php echo esc_js($this->id) ?>';
                var container = $('#easy-uploader-' + id);
                var uriRegEx = /[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/;

                var token = id;
                var txts = <?php echo json_encode(array(
                    'whereToUpload' => __('Where to upload the contents from <i>%s</i>?', 'wp-filebase'),
                    'copyOrRedirect' => __('Do you want to copy the file(s) from <i>%s1</i> to <i>%s2</i> or redirect users to the URI(s)?', 'wp-filebase'),
                    'invalidPaste' => __('You pasted something your browser cannot process. If you tried to paste a file or directory, please drag and drop or select it manually.', 'wp-filebase'),
                    'pageLeave' => __('Upload is still in progress. If you leave this page you can continue it later.', 'wp-filebase')
                )) ?>;

                var activeUploads = {};

                var showAlert = function (str) {
                    alert(str);
                };

                if (!(window.File && window.FileReader && window.FileList && window.Blob)) {
                    showAlert('Please update your browser!');
                }

                var uploadChunk = function (file, chunkIndex, offset, chunkSize) {
                    var chunk = (chunkIndex >= 0) ? file.slice(offset, Math.min(file.size, offset + chunkSize)) : null;

                    var fd = new FormData();
                    fd.append("wpfb_action", 'upload-chunked');
                    chunk && fd.append("upload", chunk);
                    fd.append("name", file.name);
                    fd.append("size", file.size);
                    fd.append("lastModified", file.lastModified);
                    fd.append("token", token);
                    fd.append("chunkIndex", chunkIndex);
                    fd.append("offset", offset);

                    var timeStart = Date.now();
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', wpfbConf.ajurl, true);

                    xhr.onload = function (e) {
                        var data;
                        try {
                            data = JSON.parse(xhr.responseText);
                        } finally {
                        }

                        if (!data || 'object' !== typeof data || xhr.status !== 200) {
                            console.error(activeUploads[file.uploadId], "error!");
                            delete activeUploads[file.uploadId];
                            return
                        }

                        if (data.offset === file.size) {
                            console.log(activeUploads[file.uploadId], "done!");
                            delete activeUploads[file.uploadId];
                            return;
                        }

                        // speed adaptive chunks with chunkFreqHz
                        var dt = Date.now() - timeStart;
                        var bps = chunk.size / dt * 1000;
                        var nextSize = Math.round((chunkSize + 2 * bps / chunkFreqHz) / 3);
                        nextSize = Math.min(data.maxChunkSize, Math.max(chunkMinSize, nextSize));
                        uploadChunk(file, chunkIndex + 1, data.offset, nextSize);
                    };

                    xhr.upload.onprogress = function (evt) {
                        console.log('progress', Math.round((offset + evt.loaded) / Math.max(file.size, evt.total) * 100))
                    };

                    xhr.send(fd);
                };

                var uploadFile = function (file, path) {
                    path = path || '';

                    file.uploadId = file.uploadId || (Math.random().toString(36) + '00000000000000000').slice(2, 12);

                    jQuery.ajax({
                        url: wpfbConf.ajurl, type: "POST", data: {
                            wpfb_action: 'upload-chunked',
                            name: file.name,
                            size: file.size,
                            lastModified: file.lastModified,
                            token: token,
                            chunkIndex: -1
                        }
                    }).done(function (data) {
                        if (data === "-1") {
                            showAlert('error!');
                            return;
                        }


                        // for speed calibration use small chunk (1MiB) for first upload
                        var firstChunkSize = Math.min(1024 * 1024, data.maxChunkSize);
                        activeUploads[file.uploadId] = {file: file, path: path};

                        if (data.offset === file.size) {
                            console.log(activeUploads[file.uploadId], "done!");
                            delete activeUploads[file.uploadId];
                            return;
                        }

                        uploadChunk(file, 0, data.offset || 0, firstChunkSize, 0);
                    });
                };

                var inputUris = function (uris, hosts) {
                    console.log('input-uris:', uris);
                    jQuery.ajax({url: wpfbConf.ajurl, type: "POST", data: {wpfb_action: 'sideload', uris: uris}});
                    showPageUris(hosts);
                };


                var traverseFileTree = function (item, path) {
                    path = path || "";
                    if (item.isFile) {
                        item.file(function (file) {
                            uploadFile(file, path);
                        });
                    } else if (item.isDirectory) {
                        var dirReader = item.createReader();
                        dirReader.readEntries(function (entries) {
                            for (var i = 0; i < entries.length; i++) {
                                traverseFileTree(entries[i], path + item.name + "/");
                            }
                        });
                    }
                };

                var showCategoryPage = function (folderName) {
                    container.find('.page').removeClass('active');
                    container.find('.page-category p').html(txts.whereToUpload.replace("%s", folderName));
                    container.find('.page-category').show().addClass('active');
                };

                var showPageUris = function (hosts) {
                    container.find('.page').removeClass('active');
                    container.find('.page-uris p').html(txts.copyOrRedirect.replace("%s1", hosts.join(', ')).replace("%s2", window.location.hostname));
                    container.find('.page-uris p').html(txts.copyOrRedirect.replace("%s1", hosts.join(', ')).replace("%s2", window.location.hostname));
                    container.find('.page-uris').show().addClass('active');
                };

                var inputDataTransfer = function (items) {
                    for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        if (item.kind === 'file') {
                            var itemEntry = item.webkitGetAsEntry();
                            if (!itemEntry || itemEntry.isFile) {
                                uploadFile(item.getAsFile())
                            } else {
                                traverseFileTree(itemEntry);
                                showCategoryPage(itemEntry.name);
                            }
                        } else if (item.kind === 'string') {
                            item.getAsString(inputString);
                        }
                    }
                };

                var inputString = function (str) {
                    var uris = str.split('\n').map(Function.prototype.call, String.prototype.trim);
                    var urisFiltered = [];
                    var hosts = [];
                    var hasInvalid = false;
                    for (var i = 0; i < uris.length; i++) {
                        if (uris[i].length === 0) continue;
                        if (!uris[i].match(uriRegEx)) {
                            console.error(uris[i]);
                            hasInvalid = true;
                            continue;
                        }
                        urisFiltered.push(uris[i]);
                        var m = uris[i].match(/:\/\/([^:\/]+)\//);
                        if (m && m[1] && hosts.indexOf(m[1]) === -1)
                            hosts.push(m[1]);
                    }

                    if (urisFiltered.length > 0)
                        inputUris(urisFiltered, hosts);

                    if (hasInvalid) {
                        showAlert('At least one of the URIs was invalid!');
                    }

                    return !hasInvalid;
                };

                var init = function (id) {
                    token = wpCookies.get('wpfb-ul-token') || (Math.random().toString(36) + '00000000000000000').slice(2, 12);
                    wpCookies.set('wpfb-ul-token', token, 604800);

                    if (!wpfbConf) {
                        showAlert('wpfbConf not set!');
                    }


                    console.log('init-easy-uploader', container);
                    container
                        .on('paste', function (event) {
                            var items = (event.clipboardData || event.originalEvent.clipboardData).items;
                            if (items.length === 0) {
                                showAlert(txts.invalidPaste)
                                return;
                            }
                            inputDataTransfer(items);
                            setTimeout(function () {
                                container.find('.paste-input').val('').blur();
                            }, 500);
                        })
                        .on('keypress', function (event) {
                            if (event.keyCode === 13) {
                                event.preventDefault();
                                var inp = container.find('.paste-input');
                                inputString(inp.val()) && inp.val('');
                            }
                        })
                        .on('dragover', function (event) {
                            event.preventDefault(); // allow to drop anything
                        })
                        .on('drop', function (event) {
                            event.preventDefault();

                            var items = event.originalEvent.dataTransfer.items;
                            inputDataTransfer(items);
                        })
                    ;

                    container.find('.file-input').on('change', function (event) {
                        var files = event.target.files;
                        for (var i = 0; i < files.length; i++) {
                            uploadFile(files[i]);
                        }
                        event.target.value = '';
                    });

                    container.find('.directory-input').on('change', function (event) {
                        var files = event.target.files;
                        for (var i = 0; i < files.length; i++) {
                            uploadFile(files[i], files[i].webkitRelativePath.slice(0, -files[i].name.length - 1));
                        }

                        if (files.length > 0) {
                            var p = event.target.files[0].webkitRelativePath;
                            showCategoryPage(p.substr(0, p.indexOf('/')));
                        }

                        event.target.value = '';
                    });

                    container.find('.file-input-btn').click(function (event) {
                        event.preventDefault();
                        container.find('.' + event.target.dataset["for"]).click();
                    });


                };

                init(id);

                window.addEventListener("beforeunload2", function (e) {
                    if (Object.keys(activeUploads).length === 0)
                        return true;

                    var msg = txts.pageLeave;
                    (e || window.event).returnValue = msg; //Gecko + IE
                    return msg; //Gecko + Webkit, Safari, Chrome etc.
                });

            })(jQuery);
        </script>
        <?php
    }
}