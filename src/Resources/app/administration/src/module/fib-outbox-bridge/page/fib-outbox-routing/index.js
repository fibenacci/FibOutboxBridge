import template from './fib-outbox-routing.html.twig';

const { Component, Context, Data, Mixin } = Shopware;
const { Criteria } = Data;

const DEFAULT_FLOW_EVENT_NAME = 'fib.outbox.forwarded';

Component.register('fib-outbox-routing', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            saveLoading: false,
            targets: [],
            routes: [],
            destinationRepository: null,
            routeRepository: null,
            showTargetModal: false,
            showRouteModal: false,
            targetDraft: this.createEmptyTargetDraft(),
            routeDraft: this.createEmptyRouteDraft(),
            editingTargetId: null,
            editingRouteId: null,
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

        routeColumns() {
            return [
                { property: 'name', label: this.$tc('fib-outbox-bridge.routing.routes.columns.name'), primary: true },
                { property: 'eventPattern', label: this.$tc('fib-outbox-bridge.routing.routes.columns.eventPattern') },
                { property: 'priority', label: this.$tc('fib-outbox-bridge.routing.routes.columns.priority') },
                { property: 'targetKeys', label: this.$tc('fib-outbox-bridge.routing.routes.columns.targetKeys') },
                { property: 'isActive', label: this.$tc('fib-outbox-bridge.routing.routes.columns.isActive') },
            ];
        },

        targetTypeOptions() {
            return [
                { value: 'webhook', label: this.$tc('fib-outbox-bridge.routing.targets.types.webhook') },
                { value: 'messenger', label: this.$tc('fib-outbox-bridge.routing.targets.types.messenger') },
                { value: 'flow', label: this.$tc('fib-outbox-bridge.routing.targets.types.flow') },
                { value: 'null', label: this.$tc('fib-outbox-bridge.routing.targets.types.null') },
            ];
        },

        targetOptions() {
            return this.targets.map((target) => ({
                value: target.technicalName,
                label: `${target.name} (${target.technicalName})`,
            }));
        },

        targetModalTitle() {
            return this.isEditingTarget
                ? this.$tc('fib-outbox-bridge.routing.targets.modal.editTitle')
                : this.$tc('fib-outbox-bridge.routing.targets.modal.createTitle');
        },

        routeModalTitle() {
            return this.isEditingRoute
                ? this.$tc('fib-outbox-bridge.routing.routes.modal.editTitle')
                : this.$tc('fib-outbox-bridge.routing.routes.modal.createTitle');
        },

        isEditingTarget() {
            return !!this.editingTargetId;
        },

        isEditingRoute() {
            return !!this.editingRouteId;
        },
    },

    created() {
        this.destinationRepository = this.repositoryFactory.create('fib_outbox_destination');
        this.routeRepository = this.repositoryFactory.create('fib_outbox_route');
        this.loadAll();
    },

    methods: {
        createEmptyTargetDraft() {
            return {
                name: '',
                technicalName: '',
                type: 'webhook',
                isActive: true,
                webhookUrl: '',
                routingKey: '',
                flowEventName: DEFAULT_FLOW_EVENT_NAME,
            };
        },

        createEmptyRouteDraft() {
            return {
                name: '',
                eventPattern: '*',
                priority: 100,
                isActive: true,
                targetKeys: [],
            };
        },

        loadAll() {
            this.isLoading = true;

            return Promise.all([
                this.loadTargets(),
                this.loadRoutes(),
            ]).finally(() => {
                this.isLoading = false;
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

        loadRoutes() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('priority', 'ASC'));

            return this.routeRepository.search(criteria, Context.api)
                .then((result) => {
                    this.routes = result;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.loadRoutesError'),
                    });
                });
        },

        openCreateTargetModal() {
            this.editingTargetId = null;
            this.targetDraft = this.createEmptyTargetDraft();
            this.showTargetModal = true;
        },

        openEditTargetModal(item) {
            const config = item?.config ?? {};

            this.editingTargetId = item.id;
            this.targetDraft = {
                name: item.name,
                technicalName: item.technicalName,
                type: item.type,
                isActive: item.isActive,
                webhookUrl: config?.url ?? '',
                routingKey: config?.routingKey ?? '',
                flowEventName: config?.flowEventName ?? DEFAULT_FLOW_EVENT_NAME,
            };
            this.showTargetModal = true;
        },

        closeTargetModal() {
            this.showTargetModal = false;
            this.targetDraft = this.createEmptyTargetDraft();
            this.editingTargetId = null;
        },

        saveTarget() {
            if (!this.targetDraft.name || !this.targetDraft.technicalName) {
                this.createNotificationError({
                    message: this.$tc('fib-outbox-bridge.routing.notifications.targetValidationError'),
                });

                return Promise.resolve();
            }

            const entity = this.isEditingTarget
                ? this.targets.find((item) => item.id === this.editingTargetId)
                : this.destinationRepository.create(Context.api);

            entity.name = this.targetDraft.name;
            entity.technicalName = this.targetDraft.technicalName;
            entity.type = this.targetDraft.type;
            entity.isActive = this.targetDraft.isActive;
            entity.config = this.buildTargetConfig();

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

        buildTargetConfig() {
            if (this.targetDraft.type === 'webhook') {
                return {
                    url: this.targetDraft.webhookUrl?.trim() ?? '',
                };
            }

            if (this.targetDraft.type === 'messenger') {
                return {
                    routingKey: this.targetDraft.routingKey?.trim() ?? '',
                };
            }

            if (this.targetDraft.type === 'flow') {
                const flowEventName = this.targetDraft.flowEventName?.trim() || DEFAULT_FLOW_EVENT_NAME;

                return { flowEventName };
            }

            return {};
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

        openCreateRouteModal() {
            this.editingRouteId = null;
            this.routeDraft = this.createEmptyRouteDraft();
            this.showRouteModal = true;
        },

        openEditRouteModal(item) {
            this.editingRouteId = item.id;
            this.routeDraft = {
                name: item.name,
                eventPattern: item.eventPattern,
                priority: item.priority,
                isActive: item.isActive,
                targetKeys: item.targetKeys ?? [],
            };
            this.showRouteModal = true;
        },

        closeRouteModal() {
            this.showRouteModal = false;
            this.routeDraft = this.createEmptyRouteDraft();
            this.editingRouteId = null;
        },

        saveRoute() {
            if (!this.routeDraft.name || !this.routeDraft.eventPattern || (this.routeDraft.targetKeys ?? []).length === 0) {
                this.createNotificationError({
                    message: this.$tc('fib-outbox-bridge.routing.notifications.routeValidationError'),
                });

                return Promise.resolve();
            }

            const entity = this.isEditingRoute
                ? this.routes.find((item) => item.id === this.editingRouteId)
                : this.routeRepository.create(Context.api);

            entity.name = this.routeDraft.name;
            entity.eventPattern = this.routeDraft.eventPattern;
            entity.priority = this.routeDraft.priority;
            entity.isActive = this.routeDraft.isActive;
            entity.targetKeys = this.routeDraft.targetKeys;

            this.saveLoading = true;

            return this.routeRepository.save(entity, Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.routeSavedSuccess'),
                    });
                    this.closeRouteModal();

                    return this.loadAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.routeSaveError'),
                    });
                })
                .finally(() => {
                    this.saveLoading = false;
                });
        },

        deleteRoute(item) {
            return this.routeRepository.delete(item.id, Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.routeDeleteSuccess'),
                    });

                    return this.loadAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.routing.notifications.routeDeleteError'),
                    });
                });
        },
    },
});
