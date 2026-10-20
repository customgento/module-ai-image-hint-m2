/**
 * Hides the AI mark of a server rendered product image while a theme script, such as a listing swatch, shows
 * another image in its place, and shows it again when the image comes back. Plain script, so it runs in Luma and
 * Hyvä alike. The swapped in image's own hint is not shown yet.
 *
 * Images are compared by gallery file, so a CDN or custom media host, an image cache size or a WebP copy of the
 * same file (example.webp or example.jpg.webp) still counts as that image. A src whose file cannot be told, like a
 * lazy loader's placeholder or an image service that rewrites the path, changes nothing on purpose: a label too many
 * on a real photo is harmless, a missing one on an AI image is not.
 */
(function () {
    'use strict';

    // "/a/b/example" of ".../catalog/product[/cache/<hash>]/a/b/example.jpg[.webp]", '' for any other url; only
    // image extensions are stripped, so "my.ring.jpg" and "my.other.jpg" stay two files
    var FILE = /\/catalog\/product(?:\/cache\/[0-9a-f]{32})?(\/[^?#]+?)(?:\.(?:jpe?g|png|gif|webp|avif))*(?:[?#]|$)/i;

    function fileOf(url) {
        var match = FILE.exec(url || '');

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
