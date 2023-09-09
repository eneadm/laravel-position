<?php

namespace Nevadskiy\Position;

/**
 * @mixin HasPosition
 */
trait PositionLocker
{
    /**
     * The position locker callback.
     *
     * @var callable|null
     */
    protected static $positionLocker;

    /**
     * Lock model positions using the given callback.
     *
     * @param callable|int $locker
     */
    public static function lockPositions($locker = null): void
    {
        static::$positionLocker = is_callable($locker) ? $locker : static function (self $model) use ($locker) {
            return is_int($locker)
                ? $locker
                : $model->getStartPosition();
        };
    }

    /**
     * Unlock model positions.
     */
    public static function unlockPositions(): void
    {
        static::$positionLocker = null;
    }

    /**
     * Get the position locker callback.
     */
    public static function positionLocker(): ?callable
    {
        return static::$positionLocker;
    }
}