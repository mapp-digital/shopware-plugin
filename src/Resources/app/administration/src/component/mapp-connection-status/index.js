import template from './mapp-connection-status.html.twig';

Shopware.Component.register('mapp-connection-status', {
    template: template,

    inject: ['mappconnect'],

    model: {
     prop: 'value',
     event: 'change'
    },

    data() {
        return {
          isLoading: false,
          label: "Checking..."
        };
    },

    props: {
        value: {
            required: true
        }
    },

    created() {
      this.isLoading = true;
      this.mappconnect.getConnectionStatus().then(ret => {
            this.isLoading = false;
            if (ret.status == "OK") {
              this.label = "OK. Connected";
              if (!this.value) {
                this.$emit('change', true);
              }
            }
            if (ret.status == "ERROR") {
              this.label = "ERROR: " + this.error;
            }
            if (ret.status == "NO") {
              this.label = "Not connected";
            }
      });
    }

});
