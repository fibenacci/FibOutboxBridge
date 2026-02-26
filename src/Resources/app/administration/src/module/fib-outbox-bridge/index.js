import './page/fib-outbox-event-list';

const { Module } = Shopware;

Module.register('fib-outbox-bridge', {
    type: 'plugin',
    name: 'fib-outbox-bridge',
    title: 'fib-outbox-bridge.module.title',
    description: 'fib-outbox-bridge.module.description',
    color: '#185adb',
    icon: 'regular-share-nodes',
    version: '1.0.0',
    targetVersion: '1.0.0',
    entity: 'fib_outbox_event',

    routes: {
        list: {
            component: 'fib-outbox-event-list',
            path: 'list',
            meta: { privilege: 'order.viewer' },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'fib.outbox.bridge.list',
        icon: 'regular-share-nodes',
        privilege: 'order.viewer',
    },
});
