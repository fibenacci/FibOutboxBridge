import './page/fib-outbox-event-list';
import './page/fib-outbox-routing';

const { Module } = Shopware;

Module.register('fib-outbox-bridge', {
    type: 'plugin',
    name: 'fib-outbox-bridge',
    title: 'fib-outbox-bridge.module.title',
    description: 'fib-outbox-bridge.module.description',
    color: '#185adb',
    icon: 'regular-iot-connection',
    version: '1.0.0',
    targetVersion: '1.0.0',
    entity: 'fib_outbox_event',

    routes: {
        list: {
            component: 'fib-outbox-event-list',
            path: 'list',
            meta: { privilege: 'fib_outbox_event:read' },
        },
        routing: {
            component: 'fib-outbox-routing',
            path: 'routing',
            meta: { privilege: 'fib_outbox_route:read' },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'fib.outbox.bridge.list',
        icon: 'regular-iot-connection',
        privilege: 'fib_outbox_event:read',
    },
});
