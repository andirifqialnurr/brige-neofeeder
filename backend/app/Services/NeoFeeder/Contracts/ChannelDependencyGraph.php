<?php

namespace App\Services\NeoFeeder\Contracts;

use InvalidArgumentException;

final class ChannelDependencyGraph
{
    /**
     * @param  array<string, ChannelContract>  $channels
     */
    public function __construct(
        private readonly array $channels,
    ) {
    }

    /**
     * @return list<string>
     */
    public function orderedKeys(): array
    {
        $ordered = [];
        $temporaryMarks = [];
        $permanentMarks = [];

        foreach (array_keys($this->channels) as $key) {
            $this->visit($key, $ordered, $temporaryMarks, $permanentMarks);
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $ordered
     * @param  array<string, bool>  $temporaryMarks
     * @param  array<string, bool>  $permanentMarks
     */
    private function visit(string $key, array &$ordered, array &$temporaryMarks, array &$permanentMarks): void
    {
        if (($permanentMarks[$key] ?? false) === true) {
            return;
        }

        if (($temporaryMarks[$key] ?? false) === true) {
            throw new InvalidArgumentException("Channel dependency cycle detected at [{$key}].");
        }

        $channel = $this->channels[$key] ?? null;

        if (! $channel instanceof ChannelContract) {
            throw new InvalidArgumentException("Channel dependency [{$key}] is not registered.");
        }

        $temporaryMarks[$key] = true;

        foreach ($channel->dependsOn as $dependencyKey) {
            $this->visit((string) $dependencyKey, $ordered, $temporaryMarks, $permanentMarks);
        }

        unset($temporaryMarks[$key]);

        $permanentMarks[$key] = true;
        $ordered[] = $key;
    }
}
