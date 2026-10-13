/**
 * Puts the AI hint on the Fotorama product gallery (Luma): the labelled icon on each stage frame, the small mark
 * on each thumbnail, for the gallery items that carry aiIcon / aiMark / aiAlt (see AddAiHintToGalleryJsonPlugin).
 * Fotorama creates its frames lazily, so every frame event checks all items again. The events are caught on the
 * document: fullscreen moves the Fotorama element to <body>, out of the gallery element.
 */
define(['jquery'], function ($) {
    'use strict';

    // Fotorama fits the stage image into its frame uncropped, so the frame's corner can lie beside a picture of
    // another ratio: the margins move the icon onto the picture's corner, once it is laid out and on every resize
    var observer = new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            pinToImage($(entry.target).closest('.fotorama__stage__frame'));
        });
    });

    function badge(src, alt, size) {
        return $('<img>', {src: src, alt: alt, title: alt, 'class': 'ai-image-hint ai-image-hint--' + size});
    }

    function pinToImage(frame) {
        var icon = frame.children('.ai-image-hint'),
            img = frame.find('.fotorama__img, .fotorama__img--full').filter(':visible')[0],
            box = img && img.getBoundingClientRect(),
            outer = frame[0] && frame[0].getBoundingClientRect();

        // a zoomed picture is larger than the frame, the icon then stays in the frame's corner
        if (icon.length && box && box.width) {
            icon.css({
                marginRight: Math.max(outer.right - box.right, 0),
                marginBottom: Math.max(outer.bottom - box.bottom, 0)
            });
        }
    }

    function addBadges(event, fotorama) {
        $.each(fotorama.data || [], function (index, item) {
            if (!item.aiIcon) {
                return;
            }
            if (item.$stageFrame) {
                if (!item.$stageFrame.children('.ai-image-hint').length) {
                    item.$stageFrame.append(badge(item.aiIcon, item.aiAlt, 'full'));
                }
                // the frame resizes with the window and fullscreen, the pictures as they load and zoom
                item.$stageFrame.find('.fotorama__img, .fotorama__img--full').addBack().each(function () {
                    observer.observe(this);
                });
            }
            if (item.$navThumbFrame && !item.$navThumbFrame.children('.ai-image-hint').length) {
                item.$navThumbFrame.addClass('ai-image-hint-host').append(badge(item.aiMark, item.aiAlt, 'thumb'));
            }
        });
    }

    $(document).on('fotorama:ready fotorama:show fotorama:load fotorama:fullscreenenter', addBadges);

    // the mixin only makes sure the handler above is loaded together with the gallery
    return function (Gallery) {
        return Gallery;
    };
});
