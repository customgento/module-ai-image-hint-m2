/**
 * EU AI Act label for the images a theme draws in JavaScript: the product page gallery (Fotorama, Alpine,
 * ...) and the minicart (Knockout, Alpine, ...). Plain script without RequireJS or Alpine, so it runs in
 * any theme. Server rendered product images get their badge in PHP, see AddAiBadgeToProductImagePlugin.
 *
 * It only knows image urls: any <img> whose src is a labelled url gets the badge next to it, and loses
 * it again when the theme swaps the src.
 */
(function () {
    'use strict';

    var badges = new WeakMap();
    var queued = false;

    // image url => {icon, alt, size}, from the gallery block and the browser cached cart section
    function collectLabels() {
        var map = {};
        document.querySelectorAll('script.ai-image-label-data').forEach(function (el) {
            Object.assign(map, JSON.parse(el.textContent));
        });
        try {
            // both Luma and Hyvä keep the customer data sections under this key
            var cart = JSON.parse(localStorage.getItem('mage-cache-storage') || '{}').cart;
            ((cart && cart.items) || []).forEach(function (item) {
                var label = item.ai_image_label;
                var src = item.product_image && item.product_image.src;
                if (label && label.icon && src) {
                    map[src] = {icon: label.icon, alt: label.alt, size: ''};
                }
            });
        } catch (e) {
            // storage blocked or corrupt: no minicart badge, nothing else breaks
        }
        return map;
    }

    function update(img, label) {
        var badge = badges.get(img);
        if (badge && (!label || badge.dataset.for !== img.src)) {
            badge.remove();
            badges.delete(img);
            badge = null;
        }
        if (!label || badge) {
            return;
        }

        var host = img.parentElement;
        if (host.tagName === 'PICTURE') {
            host = host.parentElement;
        }
        // drawn already, by the PHP plugin or a theme template
        if (host.querySelector(':scope > .ai-image-label')) {
            return;
        }

        badge = document.createElement('img');
        badge.src = label.icon;
        badge.alt = badge.title = label.alt;
        badge.className = 'ai-image-label' + (label.size ? ' ai-image-label--' + label.size : '');
        badge.dataset.for = img.src;
        if (getComputedStyle(host).position === 'static') {
            host.classList.add('ai-image-label-host');
        }
        host.appendChild(badge);
        badges.set(img, badge);
    }

    // themes keep a server rendered copy of the main image under their JS gallery until it paints, so the
    // same url sits twice on top of each other: only the visible copy later in the DOM, drawn on top, gets the badge
    function isCovered(img, labelled) {
        var rect = img.getBoundingClientRect();
        return labelled.some(function (other) {
            if (other.src !== img.src || !(img.compareDocumentPosition(other) & Node.DOCUMENT_POSITION_FOLLOWING)) {
                return false;
            }
            if (other.checkVisibility && !other.checkVisibility({opacityProperty: true, visibilityProperty: true})) {
                return false;
            }
            var o = other.getBoundingClientRect();
            return o.left < rect.right && rect.left < o.right && o.top < rect.bottom && rect.top < o.bottom;
        });
    }

    function scan() {
        queued = false;
        var map = collectLabels();
        // core's gallery placeholder shares the main image url but is only hidden, not removed, once the gallery loads
        var imgs = Array.prototype.slice.call(
            document.querySelectorAll('img:not(.ai-image-label):not(.gallery-placeholder__image)')
        );
        var labelled = imgs.filter(function (img) {
            return map[img.src];
        });
        imgs.forEach(function (img) {
            update(img, map[img.src] && !isCovered(img, labelled) ? map[img.src] : null);
        });
    }

    // one scan per frame, however many nodes the gallery or minicart touched
    function queue() {
        if (!queued) {
            queued = true;
            requestAnimationFrame(scan);
        }
    }

    new MutationObserver(queue).observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        // style and class too: galleries show and hide the copies of an image that way (x-show, invisible, ...)
        attributeFilter: ['src', 'style', 'class']
    });
    queue();
}());
