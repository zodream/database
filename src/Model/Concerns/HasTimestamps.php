<?php
declare(strict_types=1);
namespace Zodream\Database\Model\Concerns;

use Zodream\Helpers\Time;

trait HasTimestamps {
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public bool $timestamps = true;

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch() {
        if (! $this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    protected function updateTimestamps(): void {
        $time = $this->freshTimestamp();
        if ($this->isEmpty(static::UPDATED_AT)
            && $this->hasColumn(static::UPDATED_AT)) {
            $this->setUpdatedAt($time);
        }

        if ($this->isNewRecord
            && $this->isEmpty(static::CREATED_AT)
            && $this->hasColumn(static::CREATED_AT)) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Set the value of the "created at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function setCreatedAt($value) {
        $this->{static::CREATED_AT} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function setUpdatedAt($value) {
        $this->{static::UPDATED_AT} = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     *
     */
    public function freshTimestamp() {
        return time();
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function freshTimestampString() {
        return Time::format($this->freshTimestamp());
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps() {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn() {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn() {
        return static::UPDATED_AT;
    }

    public function getCreatedAtAttribute() {
        return $this->formatTimeAttribute($this->getCreatedAtColumn());
    }

    public function getUpdatedAtAttribute() {
        return $this->formatTimeAttribute($this->getUpdatedAtColumn());
    }

    protected function formatTimeAttribute($key) {
        if (!$this->hasColumn($key)) {
            return '';
        }
        $value = $this->getAttributeSource($key);
        return Time::format(empty($value) ? '' : $value);
    }
}