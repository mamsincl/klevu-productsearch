define([
    'jquery'
], function ($) {
    'use strict';
    return function (target) {
        $.validator.addMethod(
            'validate-klevu-js-api',
            function (value) {
                return !value || value.startsWith('klevu-');
            },
            $.mage.__('Klevu JS API key must begin with "klevu-".')
        );
        $.validator.addMethod(
            'validate-klevu-rest-api',
            function (value) {
                return !value || value.length >= 10;
            },
            $.mage.__('Klevu Rest API key must be at least 10 characters long.')
        );

        return target;
    };
});
