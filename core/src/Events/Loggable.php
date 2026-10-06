<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;


/**
 * Event which is written to the CMS watch log by the generic log listener.
 */
interface Loggable
{
    /**
     * Returns the watch log entry of the event.
     *
     * Info entries are only written to the watch channel and can be sampled by
     * "cms.watch.sample". Warnings are security-relevant and fall back to the
     * default log channel if no watch channel is configured.
     *
     * @return array{message: string, fields: array<string, mixed>, level?: 'info'|'warning', sample?: bool}
     */
    public function log() : array;
}
