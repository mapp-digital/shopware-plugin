import template from './mapp-event-config-detail.html.twig';

const snakeCase = Shopware.Utils.string.snakeCase;
const { Component, Utils, Mixin, Data: { Criteria }, Classes: { ShopwareError } } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('mapp-event-config-detail', {
    template,

    inject: [
        'repositoryFactory',
        'businessEventService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        mappEventId: {
            type: String,
            required: false,
            default: null
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    watch: {
        mappEventId() {
            this.loadData();
        }
    },

    data() {
        return {
            businessEvents: null,
            mappEvent: null,
            isLoading: false,
            recipients: [],
            isSaveSuccessful: false
        };
    },

    computed: {
        ...mapPropertyErrors('mappEvent', [
            'eventName'
        ]),

        mappEventRepository() {
            return this.repositoryFactory.create('mapp_event');
        },

        mappEventCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salesChannels');
            return criteria;
        },


        identifier() {
            if (this.mappEvent && this.mappEvent.eventName) {
                return this.$tc(`global.businessEvents.${snakeCase(this.mappEvent.eventName)}`);
            }
            return "New MappConnect Business Event";
        },


    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadData();
        },

        loadData() {
            this.isLoading = true;

            return Promise
                .all([this.getBusinessEvents(), this.getMappEvent()])
                .then(([businessEvents, mappEvent]) => {
                    this.businessEvents = this.addTranslatedEventNames(businessEvents);
                    this.mappEvent = mappEvent;

                    this.isLoading = false;

                    return Promise.resolve([businessEvents, mappEvent]);
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception
                    });
                    this.isLoading = false;

                    return Promise.reject(exception);
                });
        },

        getMappEvent() {
            if (!this.mappEventId) {
                const newMappEvent = this.mappEventRepository.create(Shopware.Context.api);
                newMappEvent.eventName = '';
                newMappEvent.active = false;
                return newMappEvent;
            }

            return this.mappEventRepository.get(
                this.mappEventId,
                Shopware.Context.api,
                this.mappEventCriteria
            );
        },


        getBusinessEvents() {
            return this.businessEventService.getBusinessEvents();
        },

        addTranslatedEventNames(businessEvents) {
            return businessEvents.map((businessEvent) => {
                const camelCaseEventName = snakeCase(businessEvent.name);
                return { ...businessEvent, label: this.$tc(`global.businessEvents.${camelCaseEventName}`) };
            });
        },

        onSave() {
            this.isLoading = true;

            return this.mappEventRepository
                .save(this.mappEvent, Shopware.Context.api)
                .then(() => {
                    if (this.mappEvent.isNew()) {
                        this.$router.push({
                            name: 'mapp.event.config.detail', params: { id: this.mappEvent.id }
                        });
                        return Promise.resolve(this.mappEvent);
                    }
                    this.loadData();
                    this.isSaveSuccessful = true;

                    return Promise.resolve(this.mappEvent);
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid')
                    });
                    this.isLoading = false;

                    return Promise.reject(exception);
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        snakeCaseEventName(value) {
            return snakeCase(value);
        }
        
    }
});
