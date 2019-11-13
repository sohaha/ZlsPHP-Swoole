<?php
declare(strict_types=1);

namespace Zls\Swoole;

use Swoole\Coroutine as SwCoroutine;

class Context
{
    protected static $noCoContext = [];

    public static function set(string $id, &$value)
    {
        if (Co::inCoroutine()) {
            SwCoroutine::getContext()[$id] = $value;
        } else {
            self::$noCoContext[$id] = $value;
        }

        return $value;
    }

    public static function get(string $id, $default = null, $coroutineId = null)
    {
        if (Co::inCoroutine()) {
            if ($coroutineId !== null) {
                return SwCoroutine::getContext($coroutineId)[$id] ?? $default;
            }

            return SwCoroutine::getContext()[$id] ?? $default;
        }

        return self::$noCoContext[$id] ?? $default;
    }

    public static function has(string $id, $coroutineId = null)
    {
        if (Co::inCoroutine()) {
            if ($coroutineId !== null) {
                return isset(SwCoroutine::getContext($coroutineId)[$id]);
            }

            return isset(SwCoroutine::getContext()[$id]);
        }

        return isset(self::$noCoContext[$id]);
    }

    public static function getContainer()
    {
        if (Co::inCoroutine()) {
            return SwCoroutine::getContext();
        }

        return self::$noCoContext;
    }

    public static function copy(int $fromCoroutineId, array $keys = []): void
    {
        $from    = SwCoroutine::getContext($fromCoroutineId);
        $current = SwCoroutine::getContext();
        $current->exchangeArray($keys ? array_fill_keys($keys, $from->getArrayCopy()) : $from->getArrayCopy());
    }
}
