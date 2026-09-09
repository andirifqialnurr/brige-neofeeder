<?php

namespace App\Services\NeoFeeder\Contracts;

final class NeoFeederContractRegistry
{
    /**
     * @return array<string, ChannelContract>
     */
    public function channels(): array
    {
        $channels = config('neofeeder-contracts.channels', []);

        return collect($channels)
            ->mapWithKeys(fn (array $payload, string $key) => [$key => ChannelContract::fromArray($payload)])
            ->all();
    }

    public function channel(string $key): ?ChannelContract
    {
        return $this->channels()[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function dependencyOrder(): array
    {
        return (new ChannelDependencyGraph($this->channels()))->orderedKeys();
    }
}
