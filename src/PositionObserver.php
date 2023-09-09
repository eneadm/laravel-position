<?php

namespace Nevadskiy\Position;

use Illuminate\Database\Eloquent\Model;

class PositionObserver
{
    /**
     * Handle the "saving" event for the model.
     *
     * @param Model|HasPosition $model
     */
    public function saving(Model $model): void
    {
        $this->assignPosition($model);
        $this->markAsTerminalPosition($model);
        $this->normalizePosition($model);
    }

    /**
     * Assign a position to the model.
     *
     * @param Model|HasPosition $model
     */
    protected function assignPosition(Model $model): void
    {
        if ($this->shouldSetPosition($model)) {
            $model->setPosition($this->getNextPosition($model));
        }
    }

    /**
     * Determine if a position should be set for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function shouldSetPosition(Model $model): bool
    {
        $positionColumn = $model->getPositionColumn();

        if ($model->isDirty($positionColumn)) {
            return false;
        }

        if ($model->getAttribute($positionColumn) === null) {
            return true;
        }

        return $this->isPositionGroupChanging($model);
    }

    /**
     * Get the next position for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function getNextPosition(Model $model): int
    {
        if ($model::positionLocker()) {
            return $model::positionLocker()($model);
        }

        return $model->getNextPosition();
    }

    /**
     * Mark the model as terminal if it is positioned at the end of the sequence.
     *
     * @param Model|HasPosition $model
     */
    protected function markAsTerminalPosition(Model $model): void
    {
        $model->terminal = $model->getPosition() === ($model->getStartPosition() - 1);
    }

    /**
     * @param Model|HasPosition $model
     */
    protected function normalizePosition(Model $model): void
    {
        if ($model->getPosition() >= $model->getStartPosition()) {
            return;
        }

        $position = $model->getPosition() + $model->newPositionQuery()->count();

        if (! $model->exists || $this->isPositionGroupChanging($model)) {
            $position++;
        }

        $model->setPosition($position);
    }

    /**
     * Handle the "created" event for the model.
     *
     * @param Model|HasPosition $model
     */
    public function created(Model $model): void
    {
        if (! $model::shouldShiftPosition()) {
            return;
        }

        if (! $model->terminal) {
            $model->newPositionQuery()
                ->whereKeyNot($model->getKey())
                ->shiftToEnd($model->getPosition());
        }
    }

    /**
     * Handle the "updated" event for the model.
     *
     * @param Model|HasPosition $model
     */
    public function updated(Model $model): void
    {
        if (! $model::shouldShiftPosition()) {
            return;
        }

        if ($this->wasPositionGroupChanged($model)) {
            $this->handlePositionGroupChange($model);
        } else if ($this->wasPositionChanged($model)) {
            $this->handlePositionChange($model);
        }
    }

    /**
     * Determine if the position group was changed for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function wasPositionGroupChanged(Model $model): bool
    {
        return $model->groupPositionBy() && $model->wasChanged($model->groupPositionBy());
    }

    /**
     * Determine if the position was changed for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function wasPositionChanged($model): bool
    {
        return $model->wasChanged($model->getPositionColumn());
    }

    /**
     * Handle the position group change for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function handlePositionGroupChange(Model $model): void
    {
        $positionColumn = $model->getPositionColumn();

        $model->newOriginalPositionQuery()
            ->whereKeyNot($model->getKey())
            ->shiftToStart($model->getOriginal($positionColumn));

        if (! $model->terminal) {
            $model->newPositionQuery()
                ->whereKeyNot($model->getKey())
                ->shiftToEnd($model->getAttribute($positionColumn));
        }
    }

    /**
     * Handle the position change for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function handlePositionChange(Model $model): void
    {
        $positionColumn = $model->getPositionColumn();
        $currentPosition = $model->getAttribute($positionColumn);
        $originalPosition = $model->getOriginal($positionColumn);

        if ($currentPosition < $originalPosition) {
            $model->newPositionQuery()
                ->whereKeyNot($model->getKey())
                ->shiftToEnd($currentPosition, $originalPosition);
        } else if ($currentPosition > $originalPosition) {
            $model->newPositionQuery()
                ->whereKeyNot($model->getKey())
                ->shiftToStart($originalPosition, $currentPosition);
        }
    }

    /**
     * Handle the "deleted" event for the model.
     *
     * @param Model|HasPosition $model
     */
    public function deleted(Model $model): void
    {
        if (! $model::shouldShiftPosition()) {
            return;
        }

        $model->newPositionQuery()->shiftToStart($model->getPosition());
    }

    /**
     * Determine if the position group is changing for the model.
     *
     * @param Model|HasPosition $model
     */
    protected function isPositionGroupChanging(Model $model): bool
    {
        $groupPositionColumns = $model->groupPositionBy();

        if (! $groupPositionColumns) {
            return false;
        }

        return $model->isDirty($groupPositionColumns);
    }
}
