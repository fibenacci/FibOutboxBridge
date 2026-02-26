import template from './fib-outbox-event-list.html.twig';

const { Component, Context, Data, Mixin } = Shopware;
const { Criteria } = Data;

Component.register('fib-outbox-event-list', {
    template,

    inject: ['repositoryFactory', 'fibOutboxActionService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            repository: null,
            items: [],
            total: 0,
            page: 1,
            limit: 25,
            sortBy: 'occurredAt',
            sortDirection: 'DESC',
            isLoading: false,
            searchTerm: '',
            statusFilter: '',
            summary: {
                pending: 0,
                processing: 0,
                published: 0,
                dead: 0,
                lagSeconds: null,
            },
            actionLoading: {
                dispatch: false,
                resetStuck: false,
                requeueDead: false,
            },
        };
    },

    computed: {
        columns() {
            return [
                { property: 'status', label: this.$tc('fib-outbox-bridge.list.columns.status'), sortable: true },
                { property: 'eventName', label: this.$tc('fib-outbox-bridge.list.columns.eventName'), primary: true, sortable: true },
                { property: 'aggregateType', label: this.$tc('fib-outbox-bridge.list.columns.aggregateType'), sortable: true },
                { property: 'aggregateId', label: this.$tc('fib-outbox-bridge.list.columns.aggregateId') },
                { property: 'attempts', label: this.$tc('fib-outbox-bridge.list.columns.attempts'), align: 'right', sortable: true },
                { property: 'occurredAt', label: this.$tc('fib-outbox-bridge.list.columns.occurredAt'), sortable: true },
                { property: 'availableAt', label: this.$tc('fib-outbox-bridge.list.columns.availableAt'), sortable: true },
                { property: 'publishedAt', label: this.$tc('fib-outbox-bridge.list.columns.publishedAt'), sortable: true },
                { property: 'lockOwner', label: this.$tc('fib-outbox-bridge.list.columns.lockOwner') },
                { property: 'lastError', label: this.$tc('fib-outbox-bridge.list.columns.lastError') },
            ];
        },

        statusOptions() {
            return [
                { value: '', label: this.$tc('fib-outbox-bridge.list.filters.allStatuses') },
                { value: 'pending', label: 'pending' },
                { value: 'processing', label: 'processing' },
                { value: 'published', label: 'published' },
                { value: 'dead', label: 'dead' },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('fib_outbox_event');
        this.refreshAll();
    },

    methods: {
        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            if (this.statusFilter) {
                criteria.addFilter(Criteria.equals('status', this.statusFilter));
            }

            return criteria;
        },

        loadItems() {
            if (!this.repository) {
                return Promise.resolve();
            }

            this.isLoading = true;

            return this.repository.search(this.buildCriteria(), Context.api)
                .then((result) => {
                    this.items = result;
                    this.total = result.total;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.list.notifications.loadError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadSummary() {
            if (!this.repository) {
                return Promise.resolve();
            }

            return Promise.all([
                this.countByStatus('pending'),
                this.countByStatus('processing'),
                this.countByStatus('published'),
                this.countByStatus('dead'),
                this.getOldestPendingLagSeconds(),
            ]).then(([pending, processing, published, dead, lagSeconds]) => {
                this.summary = {
                    pending,
                    processing,
                    published,
                    dead,
                    lagSeconds,
                };
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('fib-outbox-bridge.list.notifications.summaryError'),
                });
            });
        },

        countByStatus(status) {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('status', status));

            return this.repository.search(criteria, Context.api).then((result) => result.total ?? 0);
        },

        getOldestPendingLagSeconds() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('status', 'pending'));
            criteria.addSorting(Criteria.sort('occurredAt', 'ASC'));

            return this.repository.search(criteria, Context.api).then((result) => {
                const first = result?.first?.() ?? result?.[0];
                const occurredAt = first?.occurredAt;

                if (!occurredAt) {
                    return null;
                }

                const occurredTs = new Date(occurredAt).getTime();
                if (Number.isNaN(occurredTs)) {
                    return null;
                }

                return Math.max(0, Math.floor((Date.now() - occurredTs) / 1000));
            });
        },

        refreshAll() {
            return Promise.all([
                this.loadItems(),
                this.loadSummary(),
            ]);
        },

        onPageChange(page) {
            this.page = page;
            this.loadItems();
        },

        onLimitChange(limit) {
            this.limit = limit;
            this.page = 1;
            this.loadItems();
        },

        onSortColumn({ sortBy, sortDirection }) {
            this.sortBy = sortBy;
            this.sortDirection = sortDirection;
            this.loadItems();
        },

        onSearchTermChange() {
            this.page = 1;
            this.loadItems();
        },

        onStatusFilterChange(value) {
            this.statusFilter = value ?? '';
            this.page = 1;
            this.loadItems();
        },

        clearFilters() {
            this.searchTerm = '';
            this.statusFilter = '';
            this.page = 1;
            this.refreshAll();
        },

        runDispatchBatch() {
            this.actionLoading.dispatch = true;

            return this.fibOutboxActionService.dispatch(this.limit)
                .then((response) => {
                    const data = response?.data ?? response ?? {};
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.list.notifications.dispatchSuccess', 0, {
                            claimed: data.claimed ?? 0,
                            published: data.published ?? 0,
                            retried: data.retried ?? 0,
                            dead: data.dead ?? 0,
                        }),
                    });

                    return this.refreshAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.list.notifications.dispatchError'),
                    });
                })
                .finally(() => {
                    this.actionLoading.dispatch = false;
                });
        },

        runResetStuck() {
            this.actionLoading.resetStuck = true;

            return this.fibOutboxActionService.resetStuck()
                .then((response) => {
                    const data = response?.data ?? response ?? {};
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.list.notifications.resetStuckSuccess', 0, {
                            count: data.reset ?? 0,
                        }),
                    });

                    return this.refreshAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.list.notifications.resetStuckError'),
                    });
                })
                .finally(() => {
                    this.actionLoading.resetStuck = false;
                });
        },

        runRequeueDead() {
            this.actionLoading.requeueDead = true;

            return this.fibOutboxActionService.requeueDead(this.limit)
                .then((response) => {
                    const data = response?.data ?? response ?? {};
                    this.createNotificationSuccess({
                        message: this.$tc('fib-outbox-bridge.list.notifications.requeueDeadSuccess', 0, {
                            count: data.requeued ?? 0,
                        }),
                    });

                    return this.refreshAll();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('fib-outbox-bridge.list.notifications.requeueDeadError'),
                    });
                })
                .finally(() => {
                    this.actionLoading.requeueDead = false;
                });
        },

        formatDate(value) {
            return value ? (Shopware.Utils.format.date(value) || value) : '—';
        },

        statusVariant(status) {
            if (status === 'published') {
                return 'success';
            }
            if (status === 'pending') {
                return 'info';
            }
            if (status === 'processing') {
                return 'warning';
            }
            if (status === 'dead') {
                return 'danger';
            }

            return 'neutral';
        },

        lagLabel() {
            if (this.summary.lagSeconds === null || this.summary.lagSeconds === undefined) {
                return '—';
            }

            const seconds = Number(this.summary.lagSeconds);
            if (Number.isNaN(seconds)) {
                return '—';
            }

            if (seconds < 60) {
                return `${seconds}s`;
            }

            if (seconds < 3600) {
                return `${Math.floor(seconds / 60)}m`;
            }

            return `${Math.floor(seconds / 3600)}h`;
        },

        shortText(value, max = 120) {
            if (!value) {
                return '—';
            }

            const text = String(value);
            if (text.length <= max) {
                return text;
            }

            return `${text.slice(0, max)}…`;
        },

        isAnyActionLoading() {
            return this.actionLoading.dispatch || this.actionLoading.resetStuck || this.actionLoading.requeueDead;
        },
    },
});
