import template from './fib-outbox-routing.html.twig';

const { Component, Context, Data, Mixin } = Shopware;
const { Criteria } = Data;

Component.register('fib-outbox-routing', {
    template,

    inject: ['repositoryFactory', 'fibOutboxActionService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            saveLoading: false,
            targets: [],
            destinationTypes: [],
            destinationRepository: null,
            showTargetModal: false,
            targetDraft: this.createEmptyTargetDraft(),
            editingTargetId: null,
        };
    },

    computed: {
        targetColumns() {
            return [
                { property: 'name', label: this.$tc('fib-outbox-bridge.routing.targets.columns.name'), primary: true },
                { property: 'technicalName', label: this.$tc('fib-outbox-bridge.routing.targets.columns.technicalName') },
                { property: 'type', label: this.$tc('fib-outbox-bridge.routing.targets.columns.type') },
                { property: 'isActive', label: this.$tc('fib-outbox-bridge.routing.targets.columns.isActive') },
            ];
        },

        targetTypeOptions() {
            return this.destinationTypes.map((typeDefinition) => ({
                value: typeDefinition.type,
                label: typeDefinition.label,
            }));
        },

        targetModalTitle() {
            return this.isEditingTarget
                ? this.$tc('fib-outbox-bridge.routing.targets.modal.editTitle')
                : this.$tc('fib-outbox-bridge.routing.targets.modal.createTitle');
        },

        isEditingTarget() {
            return !!this.editingTargetId;
        },

        selectedTypeDefinition() {
            return this.destinationTypes.find((typeDefinition) => typeDefinition.type === this.targetDraft.type) ?? null;
        },

        configFieldDefinitions() {
            return this.selectedTypeDefinition?.configFields ?? [];
        },
    },

    watch: {
        'targetDraft.type': {
            handler(newType, oldType) {
                if (newType === oldType) {
                    return;
                }

                this.targetDraft.config = this.normalizeConfigForType(newType, this.targetDraft.config);
            },
        },
    },

    created() {
        this.destinationRepository = this.repositoryFactory.create('fib_outbox_destination');
        this.loadAll();
    },

    methods: {
        createEmptyTargetDraft() {
            return {
                name: '',
                technicalName: '',
                type: '',
                isActive: true,
                config: {},
            };
        },

        loadAll() {
            this.isLoading = true;

            return Promise.all([
                this.loadDestinationTypes(),
                this.loadTargets(),
            ]).finally(() => {
                this.isLoading = false;
            });
        },

        loadDestinationTypes() {
            return this.fibOutboxActionService.getDestinationTypes()
                .then((response) => {
                    const payload = response?.data ?? response ?? {};
                    const types = Array.isArray(payload)
                        ? payload
                        : (Array.isArray(payload?.data) ? payload.data : []);

                    this.destinationTypes = types
                        .filter((typeDefinition) => typeDefinition?.type && typeDefinition?.label)
                        .map((typeDefinition) => ({
                            type: typeDefinition.type,
                            label: typeDefinition.label,
                            configFields: Array.isArray(typeDefinition?.configFields) ? typeDefinition.configFields : [],
                        }));
                })
                .catch(() => {
                    this.destinationTypes = [];
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.loadTargetsError'),
                    });
                });
        },

        loadTargets() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return this.destinationRepository.search(criteria, Context.api)
                .then((result) => {
                    this.targets = result;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.loadTargetsError'),
                    });
                });
        },

        openCreateTargetModal() {
            this.editingTargetId = null;
            this.targetDraft = this.createEmptyTargetDraft();
            this.targetDraft.type = this.destinationTypes[0]?.type ?? '';
            this.targetDraft.config = this.normalizeConfigForType(this.targetDraft.type, {});
            this.showTargetModal = true;
        },

        openEditTargetModal(item) {
            this.editingTargetId = item.id;
            this.targetDraft = {
                name: item.name,
                technicalName: item.technicalName,
                type: item.type,
                isActive: item.isActive,
                config: this.normalizeConfigForType(item.type, item?.config ?? {}),
            };
            this.showTargetModal = true;
        },

        closeTargetModal() {
            this.showTargetModal = false;
            this.targetDraft = this.createEmptyTargetDraft();
            this.editingTargetId = null;
        },

        saveTarget() {
            if (!this.targetDraft.name || !this.targetDraft.technicalName || !this.targetDraft.type) {
                this.createNotificationError({
                    message: this.$tc('fib-outbox-bridge.routing.notifications.targetValidationError'),
                });

                return Promise.resolve();
            }

            const entity = this.isEditingTarget
                ? this.targets.find((item) => item.id === this.editingTargetId)
                : this.destinationRepository.create(Context.api);

            if (!entity) {
                return Promise.resolve();
            }

            entity.name = this.targetDraft.name;
            entity.technicalName = this.targetDraft.technicalName;
            entity.type = this.targetDraft.type;
            entity.isActive = this.targetDraft.isActive;
            entity.config = this.normalizeConfigForType(this.targetDraft.type, this.targetDraft.config);

            this.saveLoading = true;

            return this.destinationRepository.save(entity, Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.targetSavedSuccess'),
                    });
                    this.closeTargetModal();

                    return this.loadAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.targetSaveError'),
                    });
                })
                .finally(() => {
                    this.saveLoading = false;
                });
        },

        deleteTarget(item) {
            return this.destinationRepository.delete(item.id, Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.targetDeleteSuccess'),
                    });

                    return this.loadAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.targetDeleteError'),
                    });
                });
        },

        normalizeConfigForType(type, config) {
            const normalized = {};
            const typeDefinition = this.destinationTypes.find((item) => item.type === type);
            const fields = typeDefinition?.configFields ?? [];

            fields.forEach((field) => {
                const fieldName = field?.name;
                if (!fieldName) {
                    return;
                }

                const valueFromConfig = config?.[fieldName];
                const defaultValue = field?.default ?? this.defaultValueForField(field);

                normalized[fieldName] = valueFromConfig ?? defaultValue;
            });

            return normalized;
        },

        defaultValueForField(field) {
            if (field?.type === 'bool') {
                return false;
            }

            return '';
        },

        isSensitiveConfigField(fieldName) {
            if (!fieldName) {
                return false;
            }

            const normalized = String(fieldName).toLowerCase();

            return normalized.includes('password')
                || normalized.includes('passphrase')
                || normalized.includes('secret')
                || normalized.includes('token')
                || normalized.includes('apikey')
                || normalized.includes('api_key')
                || normalized.includes('privatekey')
                || normalized.includes('private_key')
                || normalized.includes('accesskey')
                || normalized.includes('access_key');
        },

        getConfigFieldHelpText(field) {
            if (!this.isSensitiveConfigField(field?.name)) {
                return field?.helpText ?? '';
            }

            return this.$tc('fib-outbox-bridge.routing.targets.fields.secretFieldHelp');
        },
    },
});
