/**
 * Hides the AI mark of a server rendered product image while a theme script, such as a listing swatch, shows
 * another image in its place, and shows it again when the image comes back. Plain script, so it runs in Luma and
 * Hyvä alike. The swapped in image's own hint is not shown yet.
 *
 * Images are compared by gallery file, so a CDN host, an image cache size or a WebP copy of the same file still
 * counts as that image. A src that is no product image at all, like a lazy loader's placeholder, changes nothing.
 */
(function () {
    'use strict';

    // "/a/b/example" of ".../media/catalog/product[/cache/<hash>]/a/b/example.jpg", '' for any other url
    function fileOf(url) {
        var match = /\/media\/catalog\/product(?:\/cache\/[0-9a-f]{32})?(\/[^?#]+?)(?:\.\w+)?(?:[?#]|$)/.exec(url || '');

        return match ? match[1] : '';
    }

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            var img = mutation.target;
            var wrap = img.tagName === 'IMG' && img.closest('.ai-image-hint-wrap[data-ai-image]');
            var badge = wrap && wrap.querySelector(':scope > .ai-image-hint');
            var file = fileOf(img.getAttribute('src'));
            if (badge && img !== badge && file) {
                badge.hidden = file !== fileOf(wrap.dataset.aiImage);
            }
        });
    }).observe(document.documentElement, {subtree: true, attributes: true, attributeFilter: ['src']});
}());
