define(function(require) {
    'use strict';

    const BaseView = require('oroui/js/app/views/base/view');
    const $ = require('jquery');

    const GrantWatcherView = BaseView.extend({
        events: {
            click: 'updateClientView'
        },

        /**
         * @inheritdoc
         */
        constructor: function GrantWatcherView(options) {
            GrantWatcherView.__super__.constructor.call(this, options);
        },

        initialize: function(options) {
            this.ownerField = $(options.ownerField);
            this.redirectUrisField = $(options.redirectUrisField);

            this.updateClientView();
        },

        updateClientView: function() {
            const selectedGrant = this.$el.filter(':checked').val();
            if ('client_credentials' === selectedGrant) {
                this.ownerField.removeClass('hide');
            } else {
                this.ownerField.addClass('hide');
            }
            if ('authorization_code' === selectedGrant) {
                this.redirectUrisField.removeClass('hide');
            } else {
                this.redirectUrisField.addClass('hide');
            }
        }
    });

    return GrantWatcherView;
});
