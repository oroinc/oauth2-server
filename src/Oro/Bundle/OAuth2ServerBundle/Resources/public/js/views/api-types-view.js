define(function(require) {
    'use strict';

    const BaseView = require('oroui/js/app/views/base/view');
    const $ = require('jquery');

    const ApiTypesView = BaseView.extend({
        events: {
            click: 'updateApiTypesView'
        },

        /**
         * @inheritdoc
         */
        constructor: function ApiTypesView(options) {
            ApiTypesView.__super__.constructor.call(this, options);
        },

        initialize: function(options) {
            this.apiTypesField = $(options.apiTypesField);

            this.updateApiTypesView();
        },

        updateApiTypesView: function() {
            if (this.$el.find('input').is(':checked')) {
                this.apiTypesField.addClass('hide');
            } else {
                this.apiTypesField.removeClass('hide');
            }
        }
    });

    return ApiTypesView;
});
