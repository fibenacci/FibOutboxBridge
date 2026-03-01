<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination;

class OutboxDestinationStrategyRegistry
{
    /**
     * @var array<string, OutboxDestinationStrategyInterface>
     */
    private array $strategiesByType = [];

    /**
     * @param iterable<OutboxDestinationStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $type = $strategy->getType();
            if (empty($type)) {
                continue;
            }

            $this->strategiesByType[$type] = $strategy;
        }
    }

    public function getByType(string $type): ?OutboxDestinationStrategyInterface
    {
        if (empty($type)) {
            return null;
        }

        return $this->strategiesByType[$type] ?? null;
    }

    /**
     * @return list<array{
     *     type: string,
     *     label: string,
     *     configFields: list<array{
     *      name: string,
     *      type: string,
     *      label: string,
     *      required?: bool,
     *      placeholder?: string,
     *      default?: scalar|null
     *     }>
     *    }>
     */
    public function getTypeDefinitions(): array
    {
        $definitions = [];

        foreach ($this->strategiesByType as $type => $strategy) {
            $definitions[] = [
                'type' => $type,
                'label' => $strategy->getLabel(),
                'configFields' => $strategy->getConfigFields(),
            ];
        }

        usort($definitions, static function (array $a, array $b): int {
            return strcmp($a['label'], $b['label']);
        });

        return $definitions;
    }
}
