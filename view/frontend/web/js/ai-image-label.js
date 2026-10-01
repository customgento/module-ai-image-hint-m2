/**
 * EU AI Act label for the images a theme draws in JavaScript: the product page gallery (Fotorama, Alpine,
 * ...) and the minicart (Knockout, Alpine, ...). Plain script without RequireJS or Alpine, so it runs in
 * any theme. Server rendered product images get their badge in PHP, see AddAiBadgeToProductImagePlugin.
 *
 * It only knows image urls: any <img> whose src is a labelled url gets the badge next to it, and loses
 * it again when the theme swaps the src. Server rendered marks are hidden the same way while a theme script,
 * such as a listing swatch, shows another image in their place.
 */
(function () {
    'use strict';

    // img => the badge this script drew for it
    var badges = new Map();
    var queued = false;
    // image url => label, rebuilt only when nodes come or go: a new data block or a re-rendered minicart
    // always adds nodes, while sliders and menus that animate style/class reuse the cached map
    var map = null;

    // image url => label of the child images listing swatches swapped in, see loadSwatchLabels()
    var swatchLabels = {};
    // swatches/ajax/media url => true once read (or in flight), or the number of failed reads so far
    var swatchUrls = {};

    // image url => {icon, alt, size}, from the gallery block, the browser cached cart section and listing swatches
    function collectLabels() {
        var map = Object.assign({}, swatchLabels);
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
                    map[src] = {icon: label.icon, alt: label.alt};
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
        // drawn already: the server rendered mark of this very image, or a theme template's own badge
        var wrapper = img.closest('.ai-image-label-wrapper');
        var server = wrapper && wrapper.querySelector(':scope > .ai-image-label[data-image]');
        if ((server && server.dataset.image === img.src)
            || host.querySelector(':scope > .ai-image-label:not([data-image])')) {
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
        map = map || collectLabels();
        // a server rendered mark belongs to the url it was drawn for (AddAiBadgeToProductImagePlugin): shown
        // while any image of its tile still shows that url (themes add hover or overlay images next to it)
        document.querySelectorAll('.ai-image-label-wrapper > .ai-image-label[data-image]').forEach(function (badge) {
            var url = new URL(badge.dataset.image, document.baseURI).href;
            var shown = Array.prototype.some.call(
                badge.parentElement.querySelectorAll('img:not(.ai-image-label)'),
                function (img) { return img.src === url; }
            );
            badge.style.display = shown ? '' : 'none';
        });
        // a theme that replaces the <img> instead of swapping its src leaves our badge behind in the container
        badges.forEach(function (badge, img) {
            if (!img.isConnected) {
                badge.remove();
                badges.delete(img);
            }
        });
        // nothing labelled on this page and no badge of ours left to remove
        if (!Object.keys(map).length && !badges.size) {
            return;
        }
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
    function queue(mutations) {
        // ponytail: an in-place src swap of a minicart item keeps the stale map; also rebuild on customer-data
        // updates if a theme ever does that
        if (!mutations || mutations.some(function (m) { return m.type === 'childList'; })) {
            map = null;
        }
        if (!queued) {
            queued = true;
            requestAnimationFrame(scan);
        }
    }

    // Listing swatches (Luma via jQuery, Hyvä via fetch) load the child's images from swatches/ajax/media and
    // swap the tile image to one of them; that response carries their labels (AddAiLabelToSwatchMediaPlugin).
    // It is requested once more here instead of intercepting the theme's request, but only when the server
    // flagged it as labelled (Server-Timing), and Varnish or the full page cache usually answers it.
    function loadSwatchLabels(url) {
        var failed = swatchUrls[url] || 0;
        if (failed === true || failed >= 3) {
            return;
        }
        swatchUrls[url] = true;
        fetch(url, {credentials: 'same-origin'}).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.ai_image_label) {
                Object.assign(swatchLabels, data.ai_image_label);
                queue();
            }
        }).catch(function () {
            // tried again on the next request for this child, the parent's mark is hidden meanwhile
            swatchUrls[url] = failed + 1;
        });
    }

    function isLabelledSwatchMedia(entry) {
        if (entry.name.indexOf('/swatches/ajax/media') === -1
            || new URL(entry.name).origin !== location.origin) {
            return false;
        }
        // browsers without Server-Timing support get every response read again
        return !entry.serverTiming || entry.serverTiming.some(function (timing) {
            return timing.name === 'ai-image-label';
        });
    }

    try {
        new PerformanceObserver(function (list) {
            list.getEntries().filter(isLabelledSwatchMedia).forEach(function (entry) {
                loadSwatchLabels(entry.name);
            });
        // buffered: also the requests made before this deferred script ran
        }).observe({type: 'resource', buffered: true});
    } catch (e) {
        // no PerformanceObserver: swapped listing images get no label, the parent's mark is still hidden
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
