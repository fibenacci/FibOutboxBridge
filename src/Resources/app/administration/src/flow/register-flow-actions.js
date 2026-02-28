const flowBuilderService = Shopware.Service('flowBuilderService');

if (flowBuilderService) {
    Shopware.Component.register(
        'sw-flow-fib-outbox-send-to-destination-modal',
        () => import('./component/sw-flow-fib-outbox-send_to_destination-modal')
    );

    flowBuilderService.addIcons({
        fibOutboxSendToDestination: 'regular-iot-connection',
    });

    flowBuilderService.addActionNames({
        FIB_OUTBOX_SEND_TO_DESTINATION: 'action.fib.outbox.send.to.destination',
    });

    flowBuilderService.addActionGroupMapping({
        'action.fib.outbox.send.to.destination': 'general',
    });

    flowBuilderService.addLabels({
        fibOutboxSendToDestination: 'fib-outbox-bridge.flow.actions.sendToDestination',
    });

    flowBuilderService.addDescriptionCallbacks({
        'action.fib.outbox.send.to.destination': ({ sequence, translator }) => {
            const config = sequence?.config ?? {};
            const type = config?.destinationType ?? '';
            const label = config?.destinationLabel ?? config?.destinationTechnicalName ?? config?.destinationId ?? '';

            if (!type || !label) {
                return translator.$tc('fib-outbox-bridge.flow.actions.descriptionMissing');
            }

            return translator.$tc('fib-outbox-bridge.flow.actions.description', 0, {
                type,
                destination: label,
            });
        },
    });
}
