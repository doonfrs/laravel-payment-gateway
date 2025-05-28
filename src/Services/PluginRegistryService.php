<?php

namespace Trinavo\PaymentGateway\Services;

class PluginRegistryService
{
    /**
     * Get all registered plugins with their keys
     */
    public function getRegisteredPlugins(): array
    {
        $plugins = config('payment-gateway.plugins', []);

        return $this->normalizePluginArray($plugins);
    }

    /**
     * Get plugin class by plugin key
     */
    public function getPluginClass(string $pluginKey): ?string
    {
        $plugins = $this->getRegisteredPlugins();

        return $plugins[$pluginKey] ?? null;
    }

    /**
     * Get plugin key from class name
     */
    public function getPluginKey(string $pluginClass): string
    {
        // Extract the plugin name from the class name
        $className = class_basename($pluginClass);

        // Remove common suffixes
        $pluginName = preg_replace('/Plugin$|PaymentPlugin$|Gateway$|PaymentGateway$/', '', $className);

        // Convert to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $pluginName));
    }

    /**
     * Check if a plugin is registered
     */
    public function isPluginRegistered(string $pluginKey): bool
    {
        $plugins = $this->getRegisteredPlugins();

        return isset($plugins[$pluginKey]);
    }

    /**
     * Get all plugin keys
     */
    public function getPluginKeys(): array
    {
        return array_keys($this->getRegisteredPlugins());
    }

    /**
     * Normalize plugin array to support both index-based and key-value formats
     */
    protected function normalizePluginArray(array $plugins): array
    {
        $normalized = [];

        foreach ($plugins as $key => $value) {
            if (is_numeric($key)) {
                // Index-based array: value is the class name
                $pluginClass = $value;
                $pluginKey = $this->getPluginKey($pluginClass);
            } else {
                // Key-value array: key is the plugin name, value is the class
                $pluginKey = $key;
                $pluginClass = $value;
            }

            $normalized[$pluginKey] = $pluginClass;
        }

        return $normalized;
    }

    /**
     * Find plugin key by class name (reverse lookup)
     */
    public function findPluginKeyByClass(string $pluginClass): ?string
    {
        $plugins = $this->getRegisteredPlugins();

        foreach ($plugins as $key => $class) {
            if ($class === $pluginClass) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Get plugin information for debugging
     */
    public function getPluginInfo(): array
    {
        $plugins = $this->getRegisteredPlugins();
        $info = [];

        foreach ($plugins as $key => $class) {
            $info[] = [
                'key' => $key,
                'class' => $class,
                'exists' => class_exists($class),
                'basename' => class_basename($class),
            ];
        }

        return $info;
    }
}
