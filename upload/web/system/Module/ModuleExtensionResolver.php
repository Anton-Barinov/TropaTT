<?php
declare(strict_types=1);

namespace Web\System\Module;

/**
 * Resolves declarative "Class::method" strings from a module manifest into a
 * static callable. Classes are provided by the module autoloader, so this is
 * safe for self-contained modules installed alongside the core.
 */
final class ModuleExtensionResolver
{
    /**
     * @return callable|null A static [Class, method] callable, or null when the
     *                       spec is invalid or the target is not a public static
     *                       method (position renderers and web hooks are stateless).
     */
    public static function resolveCallable(string $spec): ?callable
    {
        $spec = trim($spec);
        if ($spec === '' || !str_contains($spec, '::')) {
            return null;
        }

        [$class, $method] = explode('::', $spec, 2);
        if ($class === '' || $method === '' || !class_exists($class) || !method_exists($class, $method)) {
            return null;
        }

        $reflection = new \ReflectionMethod($class, $method);
        if (!$reflection->isPublic() || !$reflection->isStatic()) {
            return null;
        }

        return [$class, $method];
    }
}
