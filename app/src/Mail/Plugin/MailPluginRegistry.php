<?php

namespace App\Mail\Plugin;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class MailPluginRegistry
{
    /** @var array<string, MailPluginInterface> */
    private array $plugins = [];

    public function __construct(#[AutowireIterator('app.mail_plugin')] iterable $plugins)
    {
        foreach ($plugins as $plugin) {
            $this->plugins[$plugin->code()] = $plugin;
        }
    }

    /** @return array<string, MailPluginInterface> */
    public function all(): array
    {
        return $this->plugins;
    }

    public function get(string $code): MailPluginInterface
    {
        return $this->plugins[$code] ?? throw new \InvalidArgumentException(sprintf('Plugin mail inconnu : "%s".', $code));
    }
}
