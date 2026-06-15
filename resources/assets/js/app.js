
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

Vue.component('example-component', require('./components/ExampleComponent.vue'));

require('./components/SelectDistrict');
require('./components/UserAddressesCreateAndEdit');

const app = new Vue({
    el: '#app'
});

window.formatShopPrice = function (value) {
    var n = (Math.round(parseFloat(value) * 100) / 100).toFixed(2);
    return window.AppSiteModeA ? n + '日元' : '￥' + n;
};

window.promptLoginToShop = function (loginUrl) {
    loginUrl = loginUrl || window.AppLoginUrl || '/login';
    var jsI18n = window.AppI18n || {};

    swal({
        title: jsI18n.please_login_to_shop || '请先登录后再选物',
        text: jsI18n.login_to_shop_detail || '登录后即可加入购物车、收藏商品并结算下单。',
        icon: 'warning',
        buttons: {
            cancel: jsI18n.cancel_later || '稍后',
            confirm: {
                text: jsI18n.go_login || '去登录',
                value: true,
            },
        },
    }).then(function (value) {
        if (value) {
            location.href = loginUrl;
        }
    });
};

$(function () {
    var i18n = window.AppI18n || {};
    var cartI18n = window.AppI18nCart || {};
    var mobileNavMedia = window.matchMedia('(max-width: 991px)');

    function t(dict, key, fallback) {
        return (dict && dict[key]) ? dict[key] : fallback;
    }

    function isMobileNav() {
        return mobileNavMedia.matches;
    }

    function closeMobileCategorySubmenu() {
        $('.top-category-item--has-children').removeClass('is-open');
        $('[data-submenu-toggle]').attr('aria-expanded', 'false');
        $('[data-submenu-panel]').removeClass('is-active').attr('hidden', true);
        $('[data-submenu-backdrop]').attr('hidden', true);
    }

    function positionMobileCategorySubmenu() {
        var $wrap = $('.site-header__nav-wrap');
        if (!$wrap.length) {
            return;
        }

        var top = $wrap.offset().top + $wrap.outerHeight();
        document.documentElement.style.setProperty('--mobile-submenu-top', top + 'px');
    }

    $(document).on('click', '[data-submenu-toggle]', function (e) {
        if (!isMobileNav()) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var $item = $(this).closest('.top-category-item--has-children');
        var categoryId = $item.data('category-nav');
        var $panel = $('[data-submenu-panel="' + categoryId + '"]');
        var isOpen = $item.hasClass('is-open');

        closeMobileCategorySubmenu();

        if (!isOpen && $panel.length) {
            $item.addClass('is-open');
            $(this).attr('aria-expanded', 'true');
            positionMobileCategorySubmenu();
            $panel.removeAttr('hidden').addClass('is-active');
            $('[data-submenu-backdrop]').removeAttr('hidden');
        }
    });

    $(document).on('click', '[data-submenu-backdrop]', function () {
        closeMobileCategorySubmenu();
    });

    $(window).on('resize', function () {
        if (!isMobileNav()) {
            closeMobileCategorySubmenu();
        }
    });

    var $root = $('[data-mini-cart]');
    if (!$root.length) {
        return;
    }

    var isAuth = $root.data('auth') === 1 || $root.data('auth') === '1';
    var summaryUrl = $root.data('summary-url');
    var checkoutUrl = $root.data('checkout-url');
    var loginUrl = $root.data('login-url');
    var $count = $('[data-mini-cart-count]');
    var $items = $('[data-mini-cart-items]');
    var $drawer = $('[data-mini-cart-drawer]');
    var $toast = $('[data-mini-toast]');

    function toast(message) {
        $toast.text(message).addClass('is-visible');
        clearTimeout(window.__miniCartToastTimer);
        window.__miniCartToastTimer = setTimeout(function () {
            $toast.removeClass('is-visible');
        }, 1300);
    }

    function renderItems(list) {
        if (!list.length) {
            $items.html('<div class="mini-cart-empty">' + t(cartI18n, 'empty', '购物车还是空的') + '</div>');
            return;
        }

        var html = list.map(function (item) {
            var stock = parseInt(item.stock, 10) || 99999;
            return '<div class="mini-cart-item">' +
                '<a class="mini-cart-item__img" href="' + item.product_url + '"><img src="' + item.image_url + '" alt=""></a>' +
                '<div class="mini-cart-item__meta">' +
                '<a class="mini-cart-item__title" href="' + item.product_url + '">' + item.title + '</a>' +
                '<div class="mini-cart-item__sku">' + item.sku_title + '</div>' +
                '<div class="mini-cart-item__price">' + window.formatShopPrice(item.price) + '</div>' +
                '<div class="mini-cart-item__actions" data-sku-id="' + item.sku_id + '" data-stock="' + stock + '">' +
                '<button type="button" class="mini-cart-item__btn" data-mini-cart-minus>-</button>' +
                '<span class="mini-cart-item__qty">x' + item.amount + '</span>' +
                '<button type="button" class="mini-cart-item__btn" data-mini-cart-plus>+</button>' +
                '<button type="button" class="mini-cart-item__remove" data-mini-cart-remove>' + t(cartI18n, 'remove', '移除') + '</button>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');

        $items.html(html);
    }

    function setCartCount(count) {
        count = parseInt(count, 10) || 0;
        $count.text(count);
        $count.toggleClass('is-zero', count <= 0);
    }

    function applyAddCartResponse(response) {
        var data = response && response.data;
        if (data && typeof data.count !== 'undefined') {
            setCartCount(data.count);

            return true;
        }

        return false;
    }

    function refresh() {
        if (!isAuth || !summaryUrl) {
            setCartCount(0);
            renderItems([]);
            return;
        }

        axios.get(summaryUrl).then(function (res) {
            var data = res.data || {};
            setCartCount(data.count || 0);
            renderItems(data.items || []);
        }).catch(function () {
            setCartCount(0);
            renderItems([]);
        });
    }

    $('[data-mini-cart-toggle]').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!isAuth) {
            window.promptLoginToShop(loginUrl);
            return;
        }
        var willOpen = !$drawer.hasClass('is-open');
        $drawer.toggleClass('is-open');
        if (willOpen) {
            refresh();
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('[data-mini-cart]').length
            && !$(e.target).closest('[data-mini-cart-toggle]').length) {
            $drawer.removeClass('is-open');
        }
    });

    $('[data-mini-cart-checkout]').attr('href', checkoutUrl);

    window.MiniCart = {
        refresh: refresh,
        toast: toast,
        setCount: setCartCount
    };

    $(document).on('click', '[data-add-cart]', function () {
        var $btn = $(this);
        $btn.addClass('is-clicked');
        setTimeout(function () {
            $btn.removeClass('is-clicked');
        }, 170);
    });

    function updateMiniCartAmount($actions, delta) {
        var skuId = $actions.data('sku-id');
        var stock = parseInt($actions.data('stock'), 10) || 99999;
        var $qty = $actions.find('.mini-cart-item__qty');
        var current = parseInt(($qty.text() || '').replace('x', ''), 10) || 1;
        var next = current + delta;

        if (next < 1) {
            next = 1;
        }
        if (next > stock) {
            next = stock;
        }
        if (next === current) {
            return;
        }

        axios.patch('/cart/' + skuId, {
            amount: next,
            sku_id: skuId,
        }).then(function () {
            refresh();
        }).catch(function (error) {
            if (error.response && error.response.status === 422) {
                toast(t(i18n, 'insufficient_stock', '库存不足'));
                return;
            }
            toast(t(i18n, 'update_failed', '更新失败'));
        });
    }

    $(document).on('click', '[data-mini-cart-minus]', function () {
        updateMiniCartAmount($(this).closest('.mini-cart-item__actions'), -1);
    });

    $(document).on('click', '[data-mini-cart-plus]', function () {
        updateMiniCartAmount($(this).closest('.mini-cart-item__actions'), 1);
    });

    $(document).on('click', '[data-mini-cart-remove]', function () {
        var skuId = $(this).closest('.mini-cart-item__actions').data('sku-id');
        axios.delete('/cart/' + skuId).then(function () {
            refresh();
        }).catch(function () {
            toast(t(i18n, 'remove_failed', '移除失败'));
        });
    });

    refresh();
});
