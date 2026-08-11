import BaseView from 'oroui/js/app/views/base/view';
import $ from 'jquery';

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
        this.confidentialField = $(options.confidentialField);
        this.skipAuthorizeClientAllowedField = $(options.skipAuthorizeClientAllowedField);

        this.updateClientView();
    },

    updateClientView: function() {
        const selectedGrant = this.$el.filter(':checked').val();
        if ('client_credentials' === selectedGrant) {
            this.ownerField.removeClass('hide');
        } else {
            this.ownerField.addClass('hide');
            this.clearOwnerField();
        }
        if ('authorization_code' === selectedGrant) {
            this.redirectUrisField.removeClass('hide');
            this.confidentialField.removeClass('hide');
            this.skipAuthorizeClientAllowedField.removeClass('hide');
        } else {
            this.redirectUrisField.addClass('hide');
            this.confidentialField.addClass('hide');
            this.skipAuthorizeClientAllowedField.addClass('hide');
        }
    },

    clearOwnerField: function() {
        const $ownerInput = this.ownerField.find(':input[name]');

        if ($ownerInput.val()) {
            $ownerInput.val(null).trigger('change');
        }
    }
});

export default GrantWatcherView;
