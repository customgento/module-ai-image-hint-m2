/**
 * Renders the Luma checkout order summary thumbnail through our template, which adds the badge to core's.
 */
define(function () {
    'use strict';

    return function (Thumbnail) {
        return Thumbnail.extend({
            defaults: {
                template: 'CustomGento_AiImageHint/checkout-thumbnail'
            }
        });
    };
});
