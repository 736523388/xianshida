!(function () {

    // 定义编辑器标准配置
    CKEDITOR.editorConfig = function (config) {
        config.language = 'zh-cn';
        config.toolbar = [
            {name: 'document', items: ['Source']},
            {name: 'styles', items: ['Font', 'FontSize']},
            {name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat', 'TextColor', 'BGColor', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', 'NumberedList', 'BulletedList']},
            {name: 'uimage', items: ['Link', 'Unlink', 'Table', 'UploadImage', 'UploadVideo']},
            {name: 'tools', items: ['Maximize']}
        ];
        config.allowedContent = true;
        config.extraPlugins = 'uimage,txvideo';
        config.format_tags = 'p;h1;h2;h3;pre';
        config.removeButtons = 'Underline,Subscript,Superscript';
        config.removeDialogTabs = 'image:advanced;link:advanced';
        config.font_names = '宋体/SimSun;新宋体/NSimSun;仿宋/FangSong;楷体/KaiTi;黑体/SimHei;微软雅黑/Microsoft YaHei;' + config.font_names;
    };

    // 自定义图片上传插件
    CKEDITOR.plugins.add("uimage", {
        init: function (editor) {
            editor.ui.addButton("UploadImage", {label: "上传本地图片", command: 'uimage', icon: 'image', toolbar: 'insert,10'});
            editor.addCommand('uimage', {
                exec: function (editor) {
                    var field = '_editor_upload_' + Math.floor(Math.random() * 100000);
                    /**
                     * 下面原本是mode=one
                     * 改为多图上传
                     * @author jungshen
                     * @type {string}
                     */
                    var url = window.ROOT_URL + '/index.php/admin/plugs/upfile.html?mode=mut&type=png,jpg,gif,jpeg&field=' + field;
                    $('<input type="hidden">').attr('name', field).appendTo(editor.element.$).on('change', function () {
                        /**
                         * 处理多图上传
                         * @author jungshen
                         * @type {*|string[]}
                         */
                        var jungshen_tmp_arr = this.value.split('|');
                        $.each(jungshen_tmp_arr,function (index,val) {
                            var element = CKEDITOR.dom.element.createFromHtml('<img src="' + val + '" border="0" title="image" />');
                            editor.insertElement(element);
                        });
                        $(this).remove();
                    });
                    $.form.iframe(url, '插入图片');
                }
            });
        }
    });

    // 自定义视频插入插件
    CKEDITOR.plugins.add('txvideo', {
        init: function (editor) {
            editor.ui.addButton("UploadVideo", {label: "插入HTML代码", command: 'uvideo', icon: 'flash', toolbar: 'insert,10'});
            editor.addCommand('uvideo', {
                exec: function (editor) {
                    layer.prompt({title: '插入HTML代码', formType: 2, area: ['600px', '300px']}, function (html, index) {
                        layer.close(index);
                        var element = CKEDITOR.dom.element.createFromHtml('<div style="text-align:center;">' + html + '</div>');
                        editor.insertElement(element);
                    });
                }
            });
        }
    });

})(); 