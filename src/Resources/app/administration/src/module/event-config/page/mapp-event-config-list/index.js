import template from './mapp-event-config-list.html.twig';

const snakeCase = Shopware.Utils.string.snakeCase;
const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('mapp-event-config-list', {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            items: null,
            sortBy: 'eventName',
            sortDirection: 'ASC',
            isLoading: false,
            total: 0
        };
    },

    computed: {

        mappEventRepository() {
            return this.repositoryFactory.create('mapp_event');
        },

        mappEventCriteria() {
            const criteria = new Criteria();

            criteria.setTerm(null);
            if (this.term) {
                // php implementation splits the term by each dot, so we do a custom search
                const terms = this.term.split(' ');
                const fields = ['eventName', 'messageId'];

                fields.forEach((field) => {
                    terms.forEach((term) => {
                        if (term.length > 1) {
                            criteria.addQuery(Criteria.contains(field, term), 500);
                        }
                    });
                });
            }
            criteria.addAssociation('salesChannels');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        mappEventColumns() {
            return [{
                property: 'eventName',
                dataIndex: 'eventName',
                label: 'Event Name',
                routerLink: 'mapp.event.config.detail',
                multiLine: true,
                allowResize: true,
                primary: true
            }, {
                property: 'title',
                dataIndex: 'title',
                label: 'Title',
                routerLink: 'mapp.event.config.detail',
                multiLine: true,
                allowResize: true
              }, {
                  property: 'messageId',
                  dataIndex: 'messageId',
                  label: 'Mapp MessageId',
                  routerLink: 'mapp.event.config.detail',
                  multiLine: true,
                  allowResize: true
            }, {
                property: 'salesChannels',
                dataIndex: 'salesChannels',
                label: 'Sales Channel',
                allowResize: true,
                multiLine: true
            }, {
                property: 'active',
                dataIndex: 'active',
                label: 'Active',
                align: 'center',
                allowResize: true
            }];
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            return this.mappEventRepository
                .search(this.mappEventCriteria, Shopware.Context.api)
                .then((response) => {
                    this.items = response;
                    this.items.forEach((item, index, items) => {
                        items[index].salesChannels = item.salesChannels.map(s => s.name).join(", ");
                    });
                    this.total = response.total;
                    this.isLoading = false;
                });
        },

        snakeCaseEventName(value) {
            return snakeCase(value);
        }
    }
});
