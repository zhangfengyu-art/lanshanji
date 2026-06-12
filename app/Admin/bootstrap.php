<?php

/**
 * Laravel-admin - admin builder based on Laravel.
 * @author z-song <https://github.com/z-song>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 * Encore\Admin\Form::forget(['map', 'editor']);
 *
 * Or extend custom form field:
 * Encore\Admin\Form::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 *
 */

Encore\Admin\Form::forget(['map']);

/*
 * SweetAlert 删除确认：请求失败时恢复按钮，避免「确认」变灰后无法再点。
 * 同时抬高弹层层级，避免被 CKEditor / Bootstrap 遮罩挡住点击。
 */
Encore\Admin\Admin::style(<<<'CSS'
.sweet-overlay {
    z-index: 19891014 !important;
}
.sweet-alert {
    z-index: 19891015 !important;
}
.sweet-alert button {
    pointer-events: auto !important;
}
CSS
);

Encore\Admin\Admin::script(<<<'JS'
(function () {
    if (typeof $ === 'undefined') {
        return;
    }

    $(document).ajaxError(function (event, jqxhr, settings) {
        if (!settings || typeof swal === 'undefined') {
            return;
        }

        var isDelete = settings.type === 'POST' && settings.data
            && (settings.data.indexOf('_method=delete') !== -1 || settings.data.indexOf('_method%3Ddelete') !== -1);

        if (!isDelete) {
            return;
        }

        if (typeof swal.enableButtons === 'function') {
            swal.enableButtons();
        }

        var message = '删除失败，请刷新页面后重试';
        if (jqxhr.status === 419) {
            message = '登录或页面已过期，请刷新页面后重试';
        } else if (jqxhr.status === 403) {
            message = '没有删除权限';
        } else if (jqxhr.responseJSON && jqxhr.responseJSON.message) {
            message = jqxhr.responseJSON.message;
        } else if (jqxhr.responseText) {
            try {
                var payload = JSON.parse(jqxhr.responseText);
                if (payload.message) {
                    message = payload.message;
                }
            } catch (e) {
                // ignore
            }
        }

        swal(message, '', 'error');
    });
})();
JS
);
