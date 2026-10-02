/**
 * Puts the AI hint on the Fotorama product gallery (Luma): the labelled icon on each stage frame, the small mark
 * on each thumbnail, for the gallery items that carry aiIcon / aiMark / aiAlt (see AddAiHintToGalleryJsonPlugin).
 * Fotorama creates its frames lazily, so every frame event checks all items again.
 */
define(['jquery'], function ($) {
    'use strict';

    function badge(src, alt, size) {
        return $('<img>', {src: src, alt: alt, title: alt, 'class': 'ai-image-hint ai-image-hint--' + size});
    }

    function addBadges(event, fotorama) {
        $.each(fotorama.data || [], function (index, item) {
            if (!item.aiIcon) {
                return;
            }
            if (item.$stageFrame && !item.$stageFrame.children('.ai-image-hint').length) {
                item.$stageFrame.append(badge(item.aiIcon, item.aiAlt, 'full'));
            }
            if (item.$navThumbFrame && !item.$navThumbFrame.children('.ai-image-hint').length) {
                item.$navThumbFrame.addClass('ai-image-hint-host').append(badge(item.aiMark, item.aiAlt, 'thumb'));
            }
        });
    }

    return function (Gallery) {
        return Gallery.extend({
            initialize: function (config, element) {
                // bound before Fotorama starts, its events bubble up from the gallery element
                $(element).on('fotorama:ready fotorama:show fotorama:load', addBadges);

                return this._super(config, element);
            }
        });
    };
});
