import template from './sw-flow-fib-outbox-send_to_destination-modal.html.twig';

const { Criteria } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        sequence: {
            type: Object,
            required: true,
            default: () => ({}),
        },
    },

    data() {
        return {
            config: {
                destinationType: 'webhook',
                destinationId: null,
                destinationLabel: null,
                destinationTechnicalName: null,
            },
            destinations: [],
            isLoading: false,
            error: {
                destinationType: null,
                destinationId: null,
            },
        };
    },

    computed: {
        destinationTypeOptions() {
            return [
                { value: 'webhook', label: this.$tc('fib-outbox-bridge.flow.modal.destinationTypes.webhook') },
                { value: 'messenger', label: this.$tc('fib-outbox-bridge.flow.modal.destinationTypes.messenger') },
                { value: 'flow', label: this.$tc('fib-outbox-bridge.flow.modal.destinationTypes.flow') },
            ];
        },

        destinationOptions() {
            return this.destinations.map((destination) => ({
                value: destination.id,
                label: `${destination.name} (${destination.technicalName})`,
            }));
        },

        destinationRepository() {
            return this.repositoryFactory.create('fib_outbox_destination');
        },
    },

    watch: {
        'config.destinationType': {
            handler(newValue, oldValue) {
                if (newValue === oldValue) {
                    return;
                }

                this.config.destinationId = null;
                this.config.destinationLabel = null;
                this.config.destinationTechnicalName = null;
                this.loadDestinations();
            },
        },
    },

    created() {
        this.hydrateConfig();
        this.loadDestinations();
    },

    methods: {
        hydrateConfig() {
            const existingConfig = this.sequence?.config ?? {};

            this.config = {
                destinationType: existingConfig?.destinationType ?? 'webhook',
                destinationId: existingConfig?.destinationId ?? null,
                destinationLabel: existingConfig?.destinationLabel ?? null,
                destinationTechnicalName: existingConfig?.destinationTechnicalName ?? null,
            };
        },

        loadDestinations() {
            this.isLoading = true;

            const criteria = new Criteria(1, 250);
            criteria.addFilter(Criteria.equals('isActive', true));
            criteria.addFilter(Criteria.equals('type', this.config.destinationType));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return this.destinationRepository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.destinations = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onClose() {
            this.$emit('modal-close');
        },

        onSave() {
            this.error.destinationType = null;
            this.error.destinationId = null;

            if (!this.config.destinationType) {
                this.error.destinationType = {};
            }

            if (!this.config.destinationId) {
                this.error.destinationId = {};
            }

            if (this.error.destinationType || this.error.destinationId) {
                return;
            }

            const selectedDestination = this.destinations.find((destination) => destination.id === this.config.destinationId);

            const sequence = {
                ...this.sequence,
                config: {
                    destinationType: this.config.destinationType,
                    destinationId: this.config.destinationId,
                    destinationLabel: selectedDestination?.name ?? null,
                    destinationTechnicalName: selectedDestination?.technicalName ?? null,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
};
