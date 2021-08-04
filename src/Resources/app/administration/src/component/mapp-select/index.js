import template from './mapp-select.html.twig';

Shopware.Component.register('mapp-select', {
    template: template,

    inject: ['mappconnect'],

    data() {
        return {
          results: [],
          isLoading: true,
        };
    },

    props: {
        value: {
            required: true
        },
        entity: {
            required: true
        }
    },

    created() {
      this.isLoading = true;
      if (this.entity == "group")
        this.mappconnect.getGroups().then(ret => {
            this.isLoading = true;
            this.results = ret;
          }
        );
      if (this.entity == "message")
        return this.mappconnect.getMessages().then(ret => {
            this.isLoading = true;
            this.results = ret;
          }
        );
    },

    computed: {
      options() {
        return this.results;
      }
    }

});
