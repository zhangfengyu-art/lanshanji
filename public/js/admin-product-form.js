(function (window) {
    'use strict';

    var cropper = null;
    var currentAspectRatio = NaN;
    var panelId = '#product-image-live-preview';
    var modalId = '#product-image-crop-modal';
    var fileInputSelector = '.field_image input[type="file"], input[type="file"][name="image"], input[type="file"][name$="[image]"]';

    function syncPurchaseLimitField() {
        var status = $('select[name="sale_status"]').val();
        var $group = $('input[name="purchase_limit"]').closest('.form-group');
        if (status === 'LIMITED') {
            $group.show();
        } else {
            $group.hide();
        }
    }

    function syncTobaccoFields() {
        var type = $('select[name="tobacco_type"]').val();
        var $sticksGroup = $('input[name="unit_sticks"]').closest('.form-group');
        if (type === 'cigarette' || type === 'heated_tobacco') {
            $sticksGroup.show();
        } else {
            $sticksGroup.hide();
        }
        if (type === 'non_tobacco' || type === 'rolling_tobacco') {
            $('input[name="unit_sticks"]').val(0);
        }
    }

    function syncTobaccoTypeFromCategory() {
        var patterns = window.__heatedCategoryPatterns || [];
        var catText = $('select[name="category_id"] option:selected').text() || '';
        var isHeated = patterns.some(function (p) {
            return p && catText.indexOf(p) !== -1;
        });
        if (isHeated) {
            $('select[name="tobacco_type"]').val('heated_tobacco').trigger('change');
        }
    }

    function bindTobaccoFields() {
        $(document).off('change.productForm', 'select[name="sale_status"]').on('change.productForm', 'select[name="sale_status"]', syncPurchaseLimitField);
        $(document).off('change.productForm', 'select[name="tobacco_type"]').on('change.productForm', 'select[name="tobacco_type"]', syncTobaccoFields);
        $(document).off('change.productForm', 'select[name="category_id"]').on('change.productForm', 'select[name="category_id"]', syncTobaccoTypeFromCategory);
        syncPurchaseLimitField();
        syncTobaccoFields();
        syncTobaccoTypeFromCategory();
    }

    function applyDefaultCropBox() {
        if (!cropper) {
            return;
        }

        var containerData = cropper.getContainerData();
        var imageData = cropper.getImageData();
        if (!containerData || !imageData || !imageData.width || !imageData.height) {
            return;
        }

        if (Number.isFinite(currentAspectRatio) && currentAspectRatio > 0) {
            var side = Math.min(imageData.width, imageData.height) * 0.9;
            cropper.setCropBoxData({
                width: side,
                height: side,
                left: imageData.left + (imageData.width - side) / 2,
                top: imageData.top + (imageData.height - side) / 2,
            });
            return;
        }

        var targetWidth = Math.max(120, imageData.width * 0.92);
        var targetHeight = Math.max(160, imageData.height * 0.92);
        targetWidth = Math.min(targetWidth, containerData.width * 0.95);
        targetHeight = Math.min(targetHeight, containerData.height * 0.95);

        cropper.setCropBoxData({
            width: targetWidth,
            height: targetHeight,
            left: imageData.left + (imageData.width - targetWidth) / 2,
            top: imageData.top + (imageData.height - targetHeight) / 2,
        });
    }

    function getPayloadInput() {
        return $('input[name="_image_crop_payload"]');
    }

    function getMetaInput() {
        return $('input[name="_image_crop_meta"]');
    }

    function findExistingImageSource() {
        var selectors = [
            '.field_image .file-preview img',
            '.field_image .thumbnail img',
            '.file-preview img',
            '.thumbnail img'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var src = $(selectors[i]).first().attr('src');
            if (src) {
                return src;
            }
        }

        var value = $('input[name="image"][type="hidden"]').val();
        if (!value) {
            return '';
        }

        if (/^(https?:)?\/\//i.test(value) || value.indexOf('/') === 0) {
            return value;
        }

        return '/' + value.replace(/^\/+/, '');
    }

    function ensurePreviewPanel() {
        if ($(panelId).length) {
            return;
        }

        var panelHtml = ''
            + '<div id="product-image-live-preview" class="box box-default" style="margin-top:8px;">'
            + '  <div class="box-header with-border"><h3 class="box-title">图片即时预览</h3></div>'
            + '  <div class="box-body" style="display:flex;gap:24px;flex-wrap:wrap;">'
            + '    <div>'
            + '      <div style="font-size:12px;color:#777;margin-bottom:8px;">列表卡片（1:1）</div>'
            + '      <div style="position:relative;width:220px;aspect-ratio:1/1;overflow:hidden;background:#ffffff;">'
            + '        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">'
            + '          <img class="js-preview-list" src="" style="display:none;width:100%;height:100%;object-fit:contain;">'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <div>'
            + '      <div style="font-size:12px;color:#777;margin-bottom:8px;">详情主图（1:1）</div>'
            + '      <div style="position:relative;width:320px;border:1px solid #eeeeee;border-radius:2px;background:#ffffff;overflow:hidden;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;">'
            + '        <img class="js-preview-detail" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;padding:16px;">'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        var $formGroup = $(fileInputSelector).first().closest('.form-group');
        if ($formGroup.length) {
            $formGroup.after(panelHtml);
        }
    }

    function updatePreview(src) {
        if (!src) {
            return;
        }
        ensurePreviewPanel();
        $(panelId + ' .js-preview-list').attr('src', src).css('display', 'block');
        $(panelId + ' .js-preview-detail').attr('src', src).css('display', 'block');
    }

    function ensureModal() {
        if ($(modalId).length) {
            return;
        }

        var modalHtml = ''
            + '<div class="modal fade" id="product-image-crop-modal" tabindex="-1" role="dialog">'
            + '  <div class="modal-dialog modal-lg" role="document">'
            + '    <div class="modal-content">'
            + '      <div class="modal-header">'
            + '        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>'
            + '        <h4 class="modal-title">图片裁切与缩放</h4>'
            + '      </div>'
            + '      <div class="modal-body">'
            + '        <p style="color:#888;">默认自由比例裁切，拖拽可移动画面，滚轮可缩放。</p>'
            + '        <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;">'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-ratio-free">自由比例</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-ratio-square">1:1 方图</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-zoom-in">放大</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-zoom-out">缩小</button>'
            + '          <button type="button" class="btn btn-default btn-sm" id="js-crop-reset">重置</button>'
            + '        </div>'
            + '        <div style="display:flex;gap:16px;flex-wrap:wrap;">'
            + '          <div style="flex:1 1 460px;min-width:320px;">'
            + '            <div style="width:100%;height:420px;border:1px solid #e5e5e5;background:#f9f9f9;display:flex;align-items:center;justify-content:center;overflow:hidden;">'
            + '              <img id="product-image-crop-target" src="" style="max-width:100%;">'
            + '            </div>'
            + '          </div>'
            + '          <div style="width:220px;">'
            + '            <div style="font-size:12px;color:#777;margin-bottom:8px;">裁切预览</div>'
            + '            <div class="js-cropper-preview" style="width:200px;height:200px;border:1px solid #e5e5e5;overflow:hidden;background:#fff;"></div>'
            + '          </div>'
            + '        </div>'
            + '      </div>'
            + '      <div class="modal-footer">'
            + '        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>'
            + '        <button type="button" class="btn btn-primary" id="js-apply-product-image-crop">应用裁切</button>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        $('body').append(modalHtml);

        $(modalId).on('hidden.bs.modal', function () {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            $('#product-image-crop-target').attr('src', '');
        });
    }

    function loadCropperAssets(callback, onError) {
        if (typeof window.Cropper !== 'undefined') {
            callback();
            return;
        }

        if (!document.getElementById('cropper-css-cdn')) {
            var css = document.createElement('link');
            css.id = 'cropper-css-cdn';
            css.rel = 'stylesheet';
            css.href = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css';
            document.head.appendChild(css);
        }

        var scriptTag = document.getElementById('cropper-js-cdn');
        if (scriptTag) {
            scriptTag.addEventListener('load', callback);
            return;
        }

        scriptTag = document.createElement('script');
        scriptTag.id = 'cropper-js-cdn';
        scriptTag.src = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js';
        scriptTag.onload = callback;
        scriptTag.onerror = function () {
            if (typeof onError === 'function') {
                onError();
            }
        };
        document.body.appendChild(scriptTag);

        setTimeout(function () {
            if (typeof window.Cropper === 'undefined' && typeof onError === 'function') {
                onError();
            }
        }, 5000);
    }

    function openCropper(dataUrl) {
        ensureModal();
        var $image = $('#product-image-crop-target');
        $image.attr('src', dataUrl);

        $(modalId).modal('show');

        if (cropper) {
            cropper.destroy();
            cropper = null;
        }

        if (typeof window.Cropper === 'undefined') {
            return;
        }

        currentAspectRatio = NaN;
        cropper = new window.Cropper($image[0], {
            aspectRatio: currentAspectRatio,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.95,
            responsive: true,
            background: false,
            preview: '.js-cropper-preview',
            movable: true,
            zoomable: true,
            scalable: false,
            rotatable: true,
            ready: function () {
                applyDefaultCropBox();
            },
        });
    }

    function bindCropControls() {
        $(document).off('click.productForm', '#js-crop-ratio-free').on('click.productForm', '#js-crop-ratio-free', function () {
            if (!cropper) {
                return;
            }
            currentAspectRatio = NaN;
            cropper.setAspectRatio(NaN);
            applyDefaultCropBox();
        });

        $(document).off('click.productForm', '#js-crop-ratio-square').on('click.productForm', '#js-crop-ratio-square', function () {
            if (!cropper) {
                return;
            }
            currentAspectRatio = 1;
            cropper.setAspectRatio(1);
            applyDefaultCropBox();
        });

        $(document).off('click.productForm', '#js-crop-zoom-in').on('click.productForm', '#js-crop-zoom-in', function () {
            if (cropper) {
                cropper.zoom(0.1);
            }
        });

        $(document).off('click.productForm', '#js-crop-zoom-out').on('click.productForm', '#js-crop-zoom-out', function () {
            if (cropper) {
                cropper.zoom(-0.1);
            }
        });

        $(document).off('click.productForm', '#js-crop-reset').on('click.productForm', '#js-crop-reset', function () {
            if (cropper) {
                cropper.reset();
            }
        });
    }

    function bindCropApply() {
        $(document).off('click.productForm', '#js-apply-product-image-crop').on('click.productForm', '#js-apply-product-image-crop', function () {
            if (!cropper) {
                return;
            }

            var canvas = cropper.getCroppedCanvas({
                maxWidth: 1200,
                maxHeight: 1200,
                fillColor: '#ffffff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                return;
            }

            var croppedDataUrl = canvas.toDataURL('image/jpeg', 0.86);
            var $fileInput = $(fileInputSelector).first();
            var cropData = cropper.getData(true);
            var meta = {
                x: cropData.x,
                y: cropData.y,
                width: cropData.width,
                height: cropData.height,
                rotate: cropData.rotate,
                scaleX: cropData.scaleX,
                scaleY: cropData.scaleY,
                aspectRatio: Number.isFinite(currentAspectRatio) ? currentAspectRatio : 'free',
                exportWidth: 1200,
                exportHeight: 1200
            };

            var $payloadInput = getPayloadInput();
            if ($payloadInput.length) {
                $payloadInput.val('');
            }

            var $metaInput = getMetaInput();
            if ($metaInput.length) {
                $metaInput.val(JSON.stringify(meta));
            }

            if ($fileInput.length && window.DataTransfer && canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        return;
                    }
                    var file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    $fileInput[0].files = dt.files;
                }, 'image/jpeg', 0.86);
            }

            updatePreview(croppedDataUrl);
            $(modalId).modal('hide');
        });
    }

    function bindFileInput() {
        $(document).off('change.productForm', fileInputSelector).on('change.productForm', fileInputSelector, function (e) {
            var files = e.target.files || [];
            if (!files.length) {
                return;
            }

            var file = files[0];
            if (!file || !/^image\//i.test(file.type)) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function (evt) {
                var source = evt && evt.target ? evt.target.result : '';
                if (!source) {
                    return;
                }

                updatePreview(source);
                openCropper(source);

                loadCropperAssets(function () {
                    openCropper(source);
                }, function () {
                    if (window.toastr && toastr.warning) {
                        toastr.warning('裁切组件加载失败，已展示原图预览，可继续保存。');
                    } else {
                        alert('裁切组件加载失败，已展示原图预览，可继续保存。');
                    }
                });
            };
            reader.readAsDataURL(file);
        });

        $(document).off('change.productForm', '.field_image .fileinput').on('change.productForm', '.field_image .fileinput', function () {
            setTimeout(function () {
                var source = findExistingImageSource();
                if (source) {
                    updatePreview(source);
                }
            }, 50);
        });
    }

    function renderInitialPreview() {
        ensurePreviewPanel();
        var existing = findExistingImageSource();
        if (existing) {
            updatePreview(existing);
        }
    }

    function initImageCropper() {
        if (!$(fileInputSelector).length) {
            return;
        }

        ensurePreviewPanel();
        bindFileInput();
        bindCropApply();
        bindCropControls();
        renderInitialPreview();
    }

    window.AdminProductForm = {
        init: function () {
            if (typeof $ === 'undefined') {
                return;
            }

            bindTobaccoFields();
            initImageCropper();
        }
    };
})(window);
